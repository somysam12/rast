package com.mundo.gms;

import android.accounts.Account;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageInfo;
import android.content.pm.Signature;
import android.net.Uri;
import android.os.Bundle;
import android.os.Parcel;
import android.os.Parcelable;
import android.util.Base64;
import android.util.Log;

import com.mundo.MundoCore;
import com.mundo.app.BActivityThread;

import org.json.JSONArray;
import org.json.JSONObject;

import java.security.MessageDigest;
import java.util.List;

public final class GmsBridge {
    public static final String ACTION_GOOGLE_SIGN_IN = "com.google.android.gms.auth.GOOGLE_SIGN_IN";
    public static final String ACTION_NO_IMPL = "com.google.android.gms.auth.NO_IMPL";
    public static final String ACTION_START_SIGN_IN = "com.google.android.gms.auth.api.signin.ACTION_START_SIGN_IN";
    public static final String EXTRA_CALLING_PACKAGE = "mundo.gms.CALLING_PACKAGE";
    public static final String EXTRA_CALLING_UID = "mundo.gms.CALLING_UID";
    public static final String EXTRA_GOOGLE_SIGN_IN_ACCOUNT = "googleSignInAccount";
    public static final String EXTRA_GOOGLE_SIGN_IN_STATUS = "googleSignInStatus";
    public static final String EXTRA_GUEST_PACKAGE = "mundo.gms.GUEST_PACKAGE";
    public static final String EXTRA_ID_TOKEN = "mundo.gms.ID_TOKEN";
    public static final String EXTRA_ORIGINAL_INTENT = "mundo.gms.ORIGINAL_INTENT";
    public static final String EXTRA_SERVER_AUTH_CODE = "mundo.gms.SERVER_AUTH_CODE";
    public static final String EXTRA_SIGN_IN_ACCOUNT = "signInAccount";
    public static final String GMS_PACKAGE = "com.google.android.gms";
    public static final String GOOGLE_ACCOUNT_TYPE = "com.google";
    public static final String GSF_PACKAGE = "com.google.android.gsf";
    public static final String SIGN_IN_CENTER_ACTIVITY = "com.google.android.gms.auth.api.signin.internal.SignInCenterActivity";
    public static final String SIGN_IN_HUB_ACTIVITY = "com.google.android.gms.auth.api.signin.internal.SignInHubActivity";
    public static final String TAG = "MundoGmsBridge";
    public static final String VENDING_PACKAGE = "com.android.vending";

    private GmsBridge() {}

    private static String bytesToHex(byte[] bytes) {
        StringBuilder sb = new StringBuilder(bytes.length * 2);
        for (byte b : bytes) sb.append(String.format("%02x", b & 0xFF));
        return sb.toString();
    }

    private static String getCertificateFingerprint(String pkg, String algorithm) {
        if (pkg == null || pkg.isEmpty()) return null;
        try {
            Signature[] sigs = getPackageSignatures(pkg);
            if (sigs != null && sigs.length > 0) {
                return bytesToHex(MessageDigest.getInstance(algorithm).digest(sigs[0].toByteArray()));
            }
        } catch (Throwable t) {
            Log.w(TAG, "getCertificateFingerprint failed for " + pkg, t);
        }
        return null;
    }

    public static String getSha1Fingerprint(String pkg) {
        return getCertificateFingerprint(pkg, "SHA-1");
    }

    public static String getSha256Fingerprint(String pkg) {
        return getCertificateFingerprint(pkg, "SHA-256");
    }

    @SuppressWarnings("deprecation")
    public static Signature[] getPackageSignatures(String pkg) {
        if (pkg == null) return null;
        try {
            Context ctx = MundoCore.getContext();
            if (ctx != null) {
                PackageInfo pi = ctx.getPackageManager().getPackageInfo(pkg, 64);
                if (pi != null && pi.signatures != null && pi.signatures.length > 0) {
                    return pi.signatures;
                }
            }
        } catch (Throwable t) {
            Log.w(TAG, "getPackageSignatures failed for " + pkg, t);
        }
        try {
            int userId = BActivityThread.getUserId();
            PackageInfo pi = MundoCore.getBPackageManager().getPackageInfo(pkg, 64, userId);
            if (pi != null && pi.signatures != null) return pi.signatures;
        } catch (Throwable ignored) {}
        return null;
    }

    public static Account[] getRealGoogleAccounts() {
        return EmbeddedGoogleAuth.accounts(MundoCore.getContext());
    }

    public static boolean isGmsService(ComponentName cn1, ComponentName cn2) {
        if (cn1 != null && GMS_PACKAGE.equals(cn1.getPackageName())) return true;
        if (cn2 != null && GMS_PACKAGE.equals(cn2.getPackageName())) return true;
        return false;
    }

    public static boolean isGoogleSignInComponent(ComponentName cn) {
        if (cn == null) return false;
        String cls = cn.getClassName();
        if (cls == null) return false;
        return cls.contains("SignInHubActivity") || cls.contains("SignInCenterActivity")
                || cls.contains("auth.api.signin") || cls.contains("GoogleSignInActivity");
    }

    public static boolean isGoogleSignInIntent(Intent intent) {
        if (intent == null) return false;
        ComponentName cn = intent.getComponent();
        if (cn != null && isGoogleSignInComponent(cn)) return true;
        String action = intent.getAction();
        return ACTION_GOOGLE_SIGN_IN.equals(action) || ACTION_START_SIGN_IN.equals(action)
                || ACTION_NO_IMPL.equals(action);
    }

    public static boolean isValidGoogleIdToken(String token, String clientId) {
        if (token == null || clientId == null) return false;
        try {
            String[] parts = token.split("\\.", -1);
            if (parts.length != 3 || parts[2].isEmpty()) return false;
            JSONObject header = new JSONObject(new String(Base64.decode(parts[0], Base64.URL_SAFE | Base64.NO_PADDING), "UTF-8"));
            JSONObject payload = new JSONObject(new String(Base64.decode(parts[1], Base64.URL_SAFE | Base64.NO_PADDING), "UTF-8"));
            String iss = payload.optString("iss");
            byte[] sig = Base64.decode(parts[2], Base64.URL_SAFE | Base64.NO_PADDING);
            boolean nonZeroSig = false;
            for (byte b : sig) if (b != 0) { nonZeroSig = true; break; }
            return "RS256".equals(header.optString("alg"))
                    && !header.optString("kid").isEmpty()
                    && ("https://accounts.google.com".equals(iss) || "accounts.google.com".equals(iss))
                    && clientId.equals(payload.optString("aud"))
                    && !payload.optString("sub").isEmpty()
                    && payload.optLong("exp", 0) > System.currentTimeMillis() / 1000
                    && nonZeroSig;
        } catch (Exception ignored) {}
        return false;
    }

    public static Intent createSignInBridgeIntent(Context context, Intent original, String callerPkg) {
        if (context == null) context = MundoCore.getContext();
        Intent intent = new Intent(context, GmsSignInBridgeActivity.class);
        intent.putExtra(EXTRA_ORIGINAL_INTENT, original);
        if (callerPkg == null || callerPkg.isEmpty()) callerPkg = BActivityThread.getAppPackageName();
        intent.putExtra(EXTRA_CALLING_PACKAGE, callerPkg);
        intent.putExtra(EXTRA_CALLING_UID, BActivityThread.getAppUid());
        return intent;
    }

    public static ClassLoader getGuestClassLoader(Context context, String guestPkg) {
        if (context == null) context = MundoCore.getContext();
        if (guestPkg == null || guestPkg.isEmpty()) guestPkg = BActivityThread.getAppPackageName();
        if (context != null && guestPkg != null && !guestPkg.isEmpty()) {
            try {
                Context pkgCtx = context.createPackageContext(guestPkg, Context.CONTEXT_INCLUDE_CODE);
                if (pkgCtx != null && pkgCtx.getClassLoader() != null) return pkgCtx.getClassLoader();
            } catch (Throwable t) {
                Log.w(TAG, "getGuestClassLoader failed for " + guestPkg, t);
            }
        }
        if (context != null) {
            try {
                Context gmsCtx = context.createPackageContext(GMS_PACKAGE, Context.CONTEXT_INCLUDE_CODE);
                if (gmsCtx != null && gmsCtx.getClassLoader() != null) return gmsCtx.getClassLoader();
            } catch (Throwable ignored) {}
            return context.getClassLoader();
        }
        return GmsBridge.class.getClassLoader();
    }

    public static Class<?> resolveClass(String className) {
        return resolveClass(className, null);
    }

    public static Class<?> resolveClass(String className, String guestPkg) {
        if (guestPkg == null || guestPkg.isEmpty()) guestPkg = BActivityThread.getAppPackageName();
        if (guestPkg != null && !guestPkg.isEmpty()) {
            try {
                Context ctx = MundoCore.getContext();
                if (ctx != null) {
                    Context pkgCtx = ctx.createPackageContext(guestPkg, Context.CONTEXT_INCLUDE_CODE);
                    return pkgCtx.getClassLoader().loadClass(className);
                }
            } catch (Throwable ignored) {}
        }
        try { return Class.forName(className); } catch (Throwable ignored) {}
        try {
            ClassLoader cl = Thread.currentThread().getContextClassLoader();
            if (cl != null) return cl.loadClass(className);
        } catch (Throwable ignored) {}
        try {
            Context ctx = MundoCore.getContext();
            if (ctx != null) return ctx.getClassLoader().loadClass(className);
        } catch (Throwable ignored) {}
        try {
            Context ctx = MundoCore.getContext();
            if (ctx != null) {
                Context gmsCtx = ctx.createPackageContext(GMS_PACKAGE, Context.CONTEXT_INCLUDE_CODE);
                return gmsCtx.getClassLoader().loadClass(className);
            }
        } catch (Throwable ignored) {}
        return null;
    }

    public static Parcelable createGoogleSignInAccount(Context context, String guestPkg,
            String id, String idToken, String email, String displayName,
            String givenName, String familyName, Uri photoUrl,
            String serverAuthCode, long expiration, String obfuscatedId, List<?> scopes) {
        Class<?> cls = resolveClass("com.google.android.gms.auth.api.signin.GoogleSignInAccount", guestPkg);
        if (cls != null && obfuscatedId != null && !obfuscatedId.isEmpty()) {
            try {
                return GmsSignInParcelCodec.googleAccount(cls, id, idToken, email,
                        displayName, givenName, familyName, photoUrl,
                        serverAuthCode, expiration, obfuscatedId, scopes);
            } catch (Exception e) {
                Log.w(TAG, "Could not construct guest GoogleSignInAccount", e);
            }
        }
        return null;
    }

    public static Parcelable createStatus(int statusCode, String message) {
        return createStatus(statusCode, message, BActivityThread.getAppPackageName());
    }

    public static Parcelable createStatus(int statusCode, String message, String guestPkg) {
        Class<?> cls = resolveClass("com.google.android.gms.common.api.Status", guestPkg);
        if (cls == null) return null;
        try {
            return GmsSignInParcelCodec.status(cls, statusCode, message);
        } catch (Exception e) {
            Log.w(TAG, "Could not construct guest Google sign-in Status", e);
            return null;
        }
    }

    public static Parcelable createSignInAccount(String guestPkg, String obfuscatedId,
            Parcelable account, String accountType) {
        Class<?> cls = resolveClass("com.google.android.gms.auth.api.signin.SignInAccount", guestPkg);
        if (cls != null && account != null && obfuscatedId != null && accountType != null) {
            try {
                return GmsSignInParcelCodec.signInAccount(cls, obfuscatedId, account, accountType);
            } catch (Exception e) {
                Log.w(TAG, "Could not construct guest SignInAccount", e);
            }
        }
        return null;
    }

    public static void writeSafeParcelStatus(Parcel p, int statusCode, String message) {
        p.dataPosition();
        p.writeInt(-45243);
        int sizePos = p.dataPosition();
        p.writeInt(0);
        int dataStart = p.dataPosition();
        p.writeInt(262145);
        p.writeInt(statusCode);
        if (message != null) {
            int fieldHeaderPos = p.dataPosition();
            p.writeInt(0);
            int fieldDataStart = p.dataPosition();
            p.writeString(message);
            int fieldDataSize = p.dataPosition() - fieldDataStart;
            int afterField = p.dataPosition();
            p.setDataPosition(fieldHeaderPos);
            p.writeInt((fieldDataSize << 16) | 2);
            p.setDataPosition(afterField);
        }
        p.writeInt(263144);
        p.writeInt(1);
        int end = p.dataPosition();
        p.setDataPosition(sizePos);
        p.writeInt(end - dataStart);
        p.setDataPosition(end);
    }

    public static Bundle requestGoogleToken(Context ctx, Account account, String serverClientId,
            List<String> scopes, String callerPkg, boolean idToken, boolean authCode, boolean forceRefresh) {
        return EmbeddedGoogleAuth.signIn(ctx, account, callerPkg, serverClientId,
                scopes, idToken, authCode, forceRefresh);
    }

    public static String requestGoogleAccountId(Context ctx, Account account, String callerPkg) {
        if (account == null) return null;
        Bundle args = new Bundle();
        args.putString("email", account.name);
        Bundle result = EmbeddedGoogleAuth.call(ctx, "accountId", args);
        return result != null ? result.getString("accountId") : null;
    }
}
