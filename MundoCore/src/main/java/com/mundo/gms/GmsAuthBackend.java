package com.mundo.gms;

import android.content.Context;
import android.content.res.Configuration;
import android.os.Build;
import android.os.Bundle;
import android.util.Base64;
import android.util.DisplayMetrics;

import org.json.JSONObject;

import java.io.IOException;
import java.security.SecureRandom;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.TimeZone;
import java.util.concurrent.ConcurrentHashMap;

public final class GmsAuthBackend {

    public static final class AuthFailure extends IOException {
        public final String code;
        public AuthFailure(String code) {
            super("Google authentication failed");
            this.code = code;
        }
        public String publicMessage() {
            if ("BadAuthentication".equals(code)) return "Google requires you to sign in again";
            if ("NeedsBrowser".equals(code)) return "Google requires additional account verification";
            if ("UnregisteredOnApiConsole".equals(code)) return "Google rejected this app's OAuth registration";
            if ("ServiceDisabled".equals(code)) return "Google has disabled this account service";
            return "Google could not authorize this sign-in";
        }
    }

    private final Context context;
    private final GmsStore store;
    private final GmsTransport transport = new GmsTransport();
    private final ConcurrentHashMap<String, Object> profileLocks = new ConcurrentHashMap<>();

    public GmsAuthBackend(Context context, GmsStore store) {
        this.context = context;
        this.store = store;
    }

    private static void requireOk(Map<String, String> resp) throws AuthFailure {
        String err = resp.get("Error");
        if (err != null && !"OK".equals(err)) throw new AuthFailure(err);
    }

    private static String value(Map<String, String> map, String key) {
        String v = map.get(key);
        return v != null ? v : "";
    }

    public String checkin() throws Exception {
        JSONObject stored = store.read();
        String existingId = stored.optString("androidId");
        if (!existingId.isEmpty()) return existingId;

        DisplayMetrics dm = context.getResources().getDisplayMetrics();
        Configuration cfg = context.getResources().getConfiguration();

        MicroGAuthProtocol.Proto deviceProto = new MicroGAuthProtocol.Proto()
                .string(1, Build.FINGERPRINT).string(2, Build.HARDWARE).string(3, Build.BRAND)
                .string(5, Build.BOOTLOADER).string(6, "android-google")
                .number(7, Build.TIME / 1000).string(9, Build.DEVICE)
                .number(10, Build.VERSION.SDK_INT).string(11, Build.MODEL)
                .string(12, Build.MANUFACTURER).string(13, Build.PRODUCT).number(14, 0L);

        byte[] checkinPayload = new MicroGAuthProtocol.Proto()
                .bytes(1, deviceProto.encode())
                .number(2, 0L)
                .bytes(3, new MicroGAuthProtocol.Proto()
                        .string(1, "event_log_start")
                        .number(3, System.currentTimeMillis()).encode())
                .string(8, "WIFI::").number(9, 0L).encode();

        MicroGAuthProtocol.Proto screenProto = new MicroGAuthProtocol.Proto()
                .number(1, cfg.touchscreen).number(2, cfg.keyboard).number(3, cfg.navigation)
                .number(4, cfg.screenLayout & 15)
                .number(5, cfg.keyboard == 2 ? 1L : 0L)
                .number(6, cfg.navigation == 2 ? 1L : 0L)
                .number(7, dm.densityDpi).number(8, 196608L)
                .number(12, dm.widthPixels).number(13, dm.heightPixels)
                .string(14, Locale.getDefault().toLanguageTag());
        for (String abi : Build.SUPPORTED_ABIS) screenProto.string(11, abi);

        byte[] requestProto = new MicroGAuthProtocol.Proto()
                .number(2, 0L).string(3, "").bytes(4, checkinPayload)
                .string(6, Locale.getDefault().toString())
                .number(7, new SecureRandom().nextLong())
                .string(11, "").string(12, TimeZone.getDefault().getID())
                .number(14, 3L).bytes(18, screenProto.encode()).number(20, 0L).encode();

        long androidIdLong = 0, securityToken = 0;
        for (MicroGAuthProtocol.Field f : MicroGAuthProtocol.decode(transport.checkin(requestProto))) {
            if (f.id == 7) securityToken = f.number;
            if (f.id == 8) androidIdLong = f.number;
        }
        if (androidIdLong == 0 || securityToken == 0) {
            throw new IOException("Google check-in did not issue a device identity");
        }
        String androidId = Long.toHexString(androidIdLong);
        synchronized (store) {
            JSONObject data = store.read();
            data.put("androidId", androidId);
            data.put("checkinSecurityToken", Long.toHexString(securityToken));
            store.write(data);
        }
        return androidId;
    }

    public Bundle importLogin(String masterToken) throws Exception {
        if (masterToken == null || masterToken.isEmpty() || masterToken.length() > 16384) {
            throw new IllegalArgumentException("Google login did not return an account session");
        }
        Map<String, String> params = MicroGAuthProtocol.authFields(
                checkin(), null, masterToken, "ac2dm", "com.google.android.gms", MicroGAuthProtocol.GOOGLE_CERT);
        params.put("callerPkg", "com.google.android.gms");
        params.put("ACCESS_TOKEN", "1");
        params.put("add_account", "1");
        params.put("droidguard_results", "null");
        Map<String, String> resp = transport.auth(params);
        requireOk(resp);
        String email = resp.get("Email");
        String token = resp.get("Token");
        if (email == null || !email.contains("@") || token == null || token.isEmpty()) {
            throw new IOException("Google did not issue the embedded account credential");
        }
        JSONObject account = new JSONObject()
                .put("master", token).put("email", email)
                .put("id", value(resp, "accountId"))
                .put("givenName", value(resp, "firstName"))
                .put("familyName", value(resp, "lastName"));
        synchronized (store) {
            JSONObject data = store.read();
            JSONObject accounts = data.optJSONObject("accounts");
            if (accounts == null) accounts = new JSONObject();
            accounts.put(email, account);
            data.put("accounts", accounts);
            store.write(data);
        }
        Bundle result = new Bundle();
        result.putString("authAccount", email);
        result.putString("accountType", GmsBridge.GOOGLE_ACCOUNT_TYPE);
        return result;
    }

    public JSONObject account(String email) throws Exception {
        JSONObject accounts = store.read().optJSONObject("accounts");
        JSONObject acct = accounts != null ? accounts.optJSONObject(email) : null;
        if (acct == null) throw new IllegalArgumentException("Add this Google account first");
        return acct;
    }

    public Map<String, String> token(JSONObject account, String callerPkg, String callerSig,
            String service, String consentResult, boolean forceRefresh, boolean fresh) throws Exception {
        Map<String, String> params = MicroGAuthProtocol.authFields(
                checkin(), account.getString("email"), account.getString("master"),
                service, callerPkg, callerSig);
        params.put("has_permission", "1");
        params.put("oauth2_foreground", "1");
        params.put("oauth2_include_email", "1");
        params.put("oauth2_include_profile", "1");
        params.put("include_granted_scopes", "1");
        if (service.startsWith("oauth2:server:")) {
            params.put("oauth2_prompt", fresh ? "consent" : "auto");
            params.put("it_caveat_types", "2");
        }
        if (forceRefresh) {
            byte[] nonce = new byte[16];
            new SecureRandom().nextBytes(nonce);
            params.put("token_request_options", Base64.encodeToString(
                    new MicroGAuthProtocol.Proto().number(7, 1L).number(10, 3L)
                            .string(11, Base64.encodeToString(nonce, Base64.NO_WRAP)).encode(),
                    Base64.NO_WRAP));
        }
        if (consentResult != null) params.put("consent_result", consentResult);
        Map<String, String> resp = transport.auth(params);
        if ("remote_consent".equals(resp.get("issueAdvice")) && resp.get("ResolutionDataBase64") != null) {
            return resp;
        }
        requireOk(resp);
        return resp;
    }

    public Bundle signIn(Bundle request, String callerSig, String consentResult) throws Exception {
        String email = request.getString("email");
        String callerPkg = request.getString("guest");
        JSONObject account = account(email);
        ArrayList<String> scopes = request.getStringArrayList("scopes");
        boolean idToken = request.getBoolean("idToken");
        boolean authCode = request.getBoolean("authCode");
        String clientId = request.getString("client");
        List<String> tokenTypes = GoogleAuthRequestPlan.tokenTypes(clientId, scopes, idToken, authCode);
        String baseService = GoogleAuthRequestPlan.tokenTypes(null, scopes, false, false).get(0);

        Map<String, String> baseResp = token(account, callerPkg, callerSig, baseService, consentResult, false, consentResult == null);
        if (!"remote_consent".equals(baseResp.get("issueAdvice"))) {
            requireOk(baseResp);
        } else {
            return null; // signal consent needed to provider
        }
        String baseAuth = baseResp.get("Auth");
        if (baseAuth == null || baseAuth.isEmpty()) throw new IOException("Google did not authorize scopes");
        String accountId = value(baseResp, "accountId");
        if (accountId.isEmpty()) accountId = account.optString("id");

        Bundle result = new Bundle();
        result.putString("accountId", accountId);
        result.putString("givenName", account.optString("givenName"));
        result.putString("familyName", account.optString("familyName"));
        result.putString("displayName", account.optString("displayName"));
        result.putString("photoUrl", account.optString("photoUrl"));

        for (String svc : tokenTypes) {
            boolean needsRefresh = idToken || authCode;
            Map<String, String> tResp = needsRefresh
                    ? token(account, callerPkg, callerSig, svc, consentResult, request.getBoolean("forceRefresh"), false)
                    : baseResp;
            String auth = tResp.get("Auth");
            if (auth == null || auth.isEmpty()) throw new IOException("Google did not issue the requested credential");
            if (svc.startsWith("audience:")) {
                if (GmsBridge.isValidGoogleIdToken(auth, clientId)) {
                    result.putString(GmsBridge.EXTRA_ID_TOKEN, auth);
                } else {
                    throw new IOException("Google ID token claims do not match the request");
                }
            } else if (svc.startsWith("oauth2:server:")) {
                result.putString(GmsBridge.EXTRA_SERVER_AUTH_CODE, auth);
            } else {
                result.putString("authtoken", auth);
            }
        }
        if (accountId.isEmpty()) throw new IOException("Google did not return the account identifier");
        return result;
    }
}
