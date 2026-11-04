package com.mundo.gms;

import java.io.IOException;
import java.util.ArrayList;
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.concurrent.CancellationException;
import java.util.concurrent.TimeoutException;

public final class GoogleAuthRequestPlan {
    private GoogleAuthRequestPlan() {}

    public static int statusCode(Throwable th) {
        for (Throwable t = th; t != null; t = t.getCause()) {
            String msg = t.getMessage();
            if (msg != null && (msg.contains("UnregisteredOnApiConsole") || msg.contains("UNREGISTERED_ON_API_CONSOLE"))) {
                return 10;
            }
        }
        for (Throwable t = th; t != null; t = t.getCause()) {
            if (t instanceof TimeoutException) return 14;
        }
        while (th != null) {
            if (th instanceof CancellationException || th instanceof InterruptedException
                    || th.getClass().getSimpleName().equals("OperationCanceledException")) {
                return 16;
            }
            if (th instanceof IOException) return 7;
            if (th instanceof IllegalArgumentException || th instanceof SecurityException) return 10;
            th = th.getCause();
        }
        return 8;
    }

    public static List<String> tokenTypes(String serverClientId, List<String> scopes, boolean idToken, boolean authCode) {
        if ((idToken || authCode) && (serverClientId == null || serverClientId.trim().isEmpty())) {
            throw new IllegalArgumentException("Google sign-in requires a server client ID");
        }
        LinkedHashSet<String> scopeSet = new LinkedHashSet<>();
        if (scopes != null) {
            for (String s : scopes) {
                if (s != null && !s.trim().isEmpty()) scopeSet.add(s.trim());
            }
        }
        if (scopeSet.isEmpty()) {
            scopeSet.add("openid");
            scopeSet.add("email");
            scopeSet.add("profile");
        }
        StringBuilder sb = new StringBuilder();
        for (Iterator<String> it = scopeSet.iterator(); it.hasNext(); ) {
            String s = it.next();
            if (sb.length() > 0) sb.append(' ');
            sb.append(s);
        }
        String scopeStr = sb.toString();
        ArrayList<String> result = new ArrayList<>();
        if (idToken) result.add("audience:server:client_id:" + serverClientId);
        if (authCode) result.add("oauth2:server:client_id:" + serverClientId + ":api_scope:" + scopeStr);
        if (result.isEmpty()) result.add("oauth2:" + scopeStr);
        return result;
    }
}
