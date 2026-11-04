package com.mundo.gms;

import android.content.Context;
import android.content.ContentProvider;
import android.content.ContentValues;
import android.content.Intent;
import android.database.Cursor;
import android.net.Uri;
import android.os.Binder;
import android.os.Bundle;
import android.os.Process;
import android.util.Log;

import com.mundo.MundoCore;
import com.mundo.app.BActivityThread;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.IOException;
import java.util.ArrayList;
import java.util.Collection;
import java.util.Iterator;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

public final class EmbeddedGoogleProvider extends ContentProvider {
    private static final String TAG = "MundoEmbeddedGoogle";

    private GmsAuthBackend backend;
    private GmsStore store;
    private final ConcurrentHashMap<String, Bundle> pending = new ConcurrentHashMap<>();
    private final ConcurrentHashMap<String, String> consents = new ConcurrentHashMap<>();

    private static String grantKey(String email, String pkg, String sig) {
        return email + ":" + pkg + ":" + sig;
    }

    private void hostOnly() {
        if (Binder.getCallingPid() != Process.myPid()) {
            throw new SecurityException("Host login UI required");
        }
    }

    private static Bundle intentBundle(Intent intent) {
        Bundle b = new Bundle();
        b.putParcelable("intent", intent);
        return b;
    }

    private String validateGuest(String pkg) {
        if (pkg == null || pkg.isEmpty()) throw new SecurityException("Unknown cloned app");
        try {
            int userId = BActivityThread.getUserId();
            Object info = MundoCore.getBPackageManager().getPackageInfo(pkg, 0, userId);
            if (info == null) throw new SecurityException("Unknown cloned app");
        } catch (SecurityException e) {
            throw e;
        } catch (Exception e) {
            throw new SecurityException("Unknown cloned app");
        }
        String sha1 = GmsBridge.getSha1Fingerprint(pkg);
        if (sha1 != null && sha1.length() == 40) return sha1;
        throw new SecurityException("Cloned app has no signing certificate");
    }

    @Override
    public Bundle call(String method, String arg, Bundle extras) {
        if (Binder.getCallingUid() != Process.myUid()) {
            throw new SecurityException("Private embedded Google provider");
        }
        Bundle bundle = extras;
        try {
            if ("accounts".equals(method)) {
                ArrayList<String> list = new ArrayList<>();
                JSONObject accounts = store.read().optJSONObject("accounts");
                if (accounts != null) {
                    Iterator<String> keys = accounts.keys();
                    while (keys.hasNext()) list.add(keys.next());
                }
                Bundle result = new Bundle();
                result.putStringArrayList("accounts", list);
                return result;
            }
            if ("checkin".equals(method)) {
                hostOnly();
                Bundle result = new Bundle();
                result.putString("androidId", backend.checkin());
                return result;
            }
            if ("profile".equals(method)) {
                hostOnly();
                return handleProfile(bundle != null ? bundle.getString("email") : null,
                        bundle != null && bundle.getBoolean("refresh"));
            }
            if ("login".equals(method)) {
                hostOnly();
                String masterToken = bundle != null ? bundle.getString("oauthCookie") : null;
                return backend.importLogin(masterToken);
            }
            if ("pending".equals(method)) {
                hostOnly();
                String session = bundle != null ? bundle.getString("session") : null;
                Bundle pendingBundle = session != null ? pending.get(session) : null;
                if (pendingBundle == null)
                    return EmbeddedGoogleAuth.error(8, "This Google consent session has expired");
                return new Bundle(pendingBundle);
            }
            if ("consent".equals(method)) {
                hostOnly();
                String session = bundle != null ? bundle.getString("session") : null;
                Bundle removed = session != null ? pending.remove(session) : null;
                String consentResult = bundle != null ? bundle.getString("consentResult") : null;
                if (removed != null && consentResult != null && !consentResult.isEmpty()
                        && !"CAA".equals(consentResult) && consentResult.length() <= 65536) {
                    consents.put(removed.getString("key"), consentResult);
                    return new Bundle();
                }
                return EmbeddedGoogleAuth.error(16, "Google consent was canceled");
            }
            if ("grant".equals(method)) {
                hostOnly();
                String email = bundle.getString("email");
                String guest = bundle.getString("guest");
                String sig = validateGuest(guest);
                String key = grantKey(email, guest, sig);
                synchronized (store) {
                    JSONObject data = store.read();
                    JSONObject grants = data.optJSONObject("grants");
                    if (grants == null) grants = new JSONObject();
                    ArrayList<String> scopes = bundle.getStringArrayList("scopes");
                    grants.put(key, new JSONObject()
                            .put("scopes", new JSONArray((Collection<?>) scopes))
                            .put("client", bundle.getString("client", "")));
                    data.put("grants", grants);
                    store.write(data);
                }
                return new Bundle();
            }
            if ("accountId".equals(method)) {
                Bundle result = new Bundle();
                result.putString("accountId", backend.account(bundle.getString("email")).optString("id"));
                return result;
            }
            if ("signIn".equals(method) || "rawToken".equals(method)) {
                return handleSignIn(method, bundle);
            }
            return EmbeddedGoogleAuth.error(10, "Unknown embedded Google operation");
        } catch (GmsAuthBackend.AuthFailure e) {
            Log.w(TAG, "Google authentication rejected the request");
            return EmbeddedGoogleAuth.error(10, e.publicMessage());
        } catch (Exception e) {
            Log.w(TAG, "Embedded authentication operation failed: " + e.getClass().getSimpleName());
            if ("checkin".equals(method) && e instanceof IOException) {
                return EmbeddedGoogleAuth.error(7, "Google check-in failed: " + e.getMessage());
            }
            int code = GoogleAuthRequestPlan.statusCode(e);
            String msg = e instanceof IllegalArgumentException ? e.getMessage()
                    : "Embedded Google sign-in could not be completed";
            return EmbeddedGoogleAuth.error(code, msg);
        }
    }

    private Bundle handleProfile(String email, boolean refresh) throws Exception {
        JSONObject account = backend.account(email);
        Bundle result = new Bundle();
        result.putString("displayName", account.optString("displayName", ""));
        result.putString("givenName", account.optString("givenName", ""));
        result.putString("familyName", account.optString("familyName", ""));
        result.putString("email", email);
        return result;
    }

    private Bundle handleSignIn(String method, Bundle bundle) throws Exception {
        String guestPkg = bundle.getString("guest");
        String guestSig = validateGuest(guestPkg);
        String email = bundle.getString("email");
        String grantKey = grantKey(email, guestPkg, guestSig);
        backend.account(email); // throws IllegalArgumentException if no account
        if ("rawToken".equals(method)) {
            String type = bundle.getString("type");
            if ("^^_account_id_^^".equals(type)) {
                Bundle result = new Bundle();
                result.putString("authtoken", backend.account(email).optString("id"));
                return result;
            }
            return EmbeddedGoogleAuth.error(10, "Unsupported embedded Google token type");
        }
        // signIn
        JSONObject grants = store.read().optJSONObject("grants");
        JSONObject grant = grants != null ? grants.optJSONObject(grantKey) : null;
        ArrayList<String> requestedScopes = bundle.getStringArrayList("scopes");
        boolean hasGrant = grant != null
                && bundle.getString("client", "").equals(grant.optString("client"));
        if (hasGrant) {
            JSONArray grantedScopesArr = grant.optJSONArray("scopes");
            ArrayList<String> grantedScopes = new ArrayList<>();
            if (grantedScopesArr != null) {
                for (int i = 0; i < grantedScopesArr.length(); i++)
                    grantedScopes.add(grantedScopesArr.getString(i));
            }
            if (requestedScopes != null && !grantedScopes.containsAll(requestedScopes))
                hasGrant = false;
        }
        if (!hasGrant) {
            Context ctx = getContext();
            return intentBundle(new Intent(ctx, GmsConsentActivity.class)
                    .putExtra("request", bundle));
        }
        String consentResult = consents.get(grantKey);
        Bundle signInResult = backend.signIn(bundle, guestSig, consentResult);
        if (signInResult == null) return EmbeddedGoogleAuth.error(8, "Google sign-in returned empty result");
        if (signInResult.containsKey("consentUrl")) {
            String sessionId = UUID.randomUUID().toString();
            signInResult.putString("key", grantKey);
            pending.put(sessionId, signInResult);
            return intentBundle(new Intent(getContext(), GmsLoginActivity.class)
                    .putExtra("session", sessionId));
        }
        consents.remove(grantKey);
        signInResult.putString("authAccount", email);
        signInResult.putString("accountType", GmsBridge.GOOGLE_ACCOUNT_TYPE);
        return signInResult;
    }

    @Override
    public boolean onCreate() {
        store = new GmsStore(getContext());
        backend = new GmsAuthBackend(getContext(), store);
        return true;
    }

    @Override
    public Cursor query(Uri uri, String[] projection, String selection, String[] selectionArgs, String sortOrder) {
        throw new UnsupportedOperationException();
    }

    @Override
    public String getType(Uri uri) { return null; }

    @Override
    public Uri insert(Uri uri, ContentValues values) { throw new UnsupportedOperationException(); }

    @Override
    public int delete(Uri uri, String selection, String[] selectionArgs) { throw new UnsupportedOperationException(); }

    @Override
    public int update(Uri uri, ContentValues values, String selection, String[] selectionArgs) { throw new UnsupportedOperationException(); }
}
