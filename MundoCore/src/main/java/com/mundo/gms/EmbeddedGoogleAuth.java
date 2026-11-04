package com.mundo.gms;

import android.accounts.Account;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;

import com.mundo.MundoCore;
import com.mundo.app.BActivityThread;

import java.net.URI;
import java.util.ArrayList;
import java.util.List;

public final class EmbeddedGoogleAuth {
    private EmbeddedGoogleAuth() {}

    public static Account[] accounts(Context ctx) {
        ArrayList<String> emails = null;
        Bundle result = call(ctx, "accounts", null);
        if (result != null) emails = result.getStringArrayList("accounts");
        if (emails == null) return new Account[0];
        Account[] accts = new Account[emails.size()];
        for (int i = 0; i < emails.size(); i++) {
            accts[i] = new Account(emails.get(i), GmsBridge.GOOGLE_ACCOUNT_TYPE);
        }
        return accts;
    }

    public static Bundle call(Context ctx, String method, Bundle args) {
        if (ctx == null) ctx = MundoCore.getContext();
        String hostPkg = MundoCore.getHostPkg();
        if (hostPkg == null || hostPkg.isEmpty()) {
            if (ctx != null) hostPkg = ctx.getPackageName();
        }
        final Uri authority = Uri.parse("content://" + hostPkg + ".gms.embedded");
        try {
            if (ctx == null) return error(8, "Embedded Google provider context unavailable");
            return ctx.getContentResolver().call(authority, method, null, args);
        } catch (Exception e) {
            return error(8, "Embedded Google account provider is unavailable");
        }
    }

    public static Bundle error(int code, String message) {
        Bundle b = new Bundle();
        b.putInt("errorCode", code);
        b.putString("errorMessage", message);
        return b;
    }

    public static boolean isGoogleUrl(String url) {
        if (url == null) return false;
        try {
            URI uri = new URI(url);
            if ("https".equalsIgnoreCase(uri.getScheme())
                    && "accounts.google.com".equalsIgnoreCase(uri.getHost())
                    && uri.getRawUserInfo() == null) {
                return uri.getPort() == -1 || uri.getPort() == 443;
            }
        } catch (Exception ignored) {}
        return false;
    }

    public static Intent loginIntent(Context ctx, String email) {
        if (ctx == null) ctx = MundoCore.getContext();
        Intent intent = new Intent(ctx, GmsLoginActivity.class);
        if (email != null) intent.putExtra("email", email);
        return intent;
    }

    public static Bundle signIn(Context ctx, Account account, String callerPkg,
            String serverClientId, List<String> scopes, boolean idToken,
            boolean authCode, boolean forceRefresh) {
        Bundle args = new Bundle();
        args.putString("email", account.name);
        args.putString("guest", callerPkg);
        args.putString("client", serverClientId);
        args.putStringArrayList("scopes", scopes != null ? new ArrayList<>(scopes) : new ArrayList<String>());
        args.putBoolean("idToken", idToken);
        args.putBoolean("authCode", authCode);
        args.putBoolean("forceRefresh", forceRefresh);
        return call(ctx, "signIn", args);
    }
}
