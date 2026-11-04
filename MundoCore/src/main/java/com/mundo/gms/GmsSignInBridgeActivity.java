package com.mundo.gms;

import android.accounts.Account;
import android.app.Activity;
import android.content.Intent;
import android.graphics.drawable.ColorDrawable;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.os.Parcelable;
import android.util.Log;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import com.mundo.app.BActivityThread;
import com.mundo.core.env.BEnvironment;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.File;
import java.io.FileOutputStream;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class GmsSignInBridgeActivity extends Activity {
    private static final String TAG = "MundoGmsSignIn";
    private static final int RC_ADD_ACCOUNT = 9003;
    private static final int RC_CHOOSE_ACCOUNT = 9004;
    private static final int RC_CONSENT = 9002;
    private static final ExecutorService sExecutor = Executors.newCachedThreadPool();

    private String mCallingPackage;
    private Intent mOriginalIntent;
    private Account mSelectedAccount;
    private List<String> mRequestedScopes = new ArrayList<>();
    private boolean mIdTokenRequested;
    private boolean mServerAuthCodeRequested;
    private boolean mForceCodeForRefreshToken;
    private String mServerClientId;
    private int mConsentAttempts;

    private GoogleAuthUi mUi;
    private LinearLayout mAccountListLayout;
    private ScrollView mAccountsScroll;
    private LinearLayout mProgressLayout;
    private TextView mStatusText;
    private final Handler mMainHandler = new Handler(Looper.getMainLooper());

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        requestWindowFeature(android.view.Window.FEATURE_NO_TITLE);
        if (getWindow() != null) getWindow().setBackgroundDrawable(new ColorDrawable(0));
        Intent intent = getIntent();
        if (intent != null) {
            mOriginalIntent = intent.getParcelableExtra(GmsBridge.EXTRA_ORIGINAL_INTENT);
            mCallingPackage = intent.getStringExtra(GmsBridge.EXTRA_CALLING_PACKAGE);
        }
        if (mCallingPackage == null || mCallingPackage.isEmpty()) {
            mCallingPackage = BActivityThread.getAppPackageName();
        }
        try {
            parseSignInOptions();
            if (savedInstanceState != null) {
                mSelectedAccount = savedInstanceState.getParcelable("selectedAccount");
                mConsentAttempts = savedInstanceState.getInt("consentAttempts");
            }
            setupView();
            populateAccounts();
        } catch (Exception e) {
            Log.w(TAG, "Invalid Google sign-in options", e);
            failAndFinish(10, "Google sign-in options could not be read");
        }
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        outState.putParcelable("selectedAccount", mSelectedAccount);
        outState.putInt("consentAttempts", mConsentAttempts);
    }

    @Override
    protected void onDestroy() {
        if (mUi != null) mUi.close();
        super.onDestroy();
    }

    @Override
    public void onBackPressed() {
        cancelAndFinish();
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == RC_CHOOSE_ACCOUNT) {
            if (resultCode == RESULT_OK && data != null) {
                String email = data.getStringExtra("authAccount");
                if (email != null && !email.isEmpty()) {
                    selectAccountAndSignIn(new Account(email, GmsBridge.GOOGLE_ACCOUNT_TYPE));
                    return;
                }
            }
            cancelAndFinish();
        } else if (requestCode == RC_CONSENT) {
            if (resultCode == RESULT_OK && mSelectedAccount != null) {
                selectAccountAndSignIn(mSelectedAccount);
            } else {
                cancelAndFinish();
            }
        } else if (requestCode == RC_ADD_ACCOUNT) {
            if (resultCode == RESULT_OK && data != null) {
                String email = data.getStringExtra("authAccount");
                if (email != null) {
                    selectAccountAndSignIn(new Account(email, GmsBridge.GOOGLE_ACCOUNT_TYPE));
                    return;
                }
            }
            populateAccounts();
        }
    }

    private void parseSignInOptions() {
        if (mOriginalIntent == null) throw new IllegalArgumentException("Missing Google sign-in intent");
        ClassLoader loader = GmsBridge.getGuestClassLoader(this, mCallingPackage);
        mOriginalIntent.setExtrasClassLoader(loader);
        Object opts = null;
        Bundle extras = mOriginalIntent.getExtras();
        if (extras != null) {
            extras.setClassLoader(loader);
            opts = extras.get("config");
        }
        boolean isConfig = false;
        if (opts instanceof Bundle) {
            Bundle configBundle = (Bundle) opts;
            configBundle.setClassLoader(loader);
            Parcelable p = configBundle.getParcelable("config");
            if (p == null) p = configBundle.getParcelable("signInConfiguration");
            if (p != null) { opts = p; isConfig = true; }
        }
        if (opts == null) opts = mOriginalIntent.getParcelableExtra("signInConfiguration");
        if (opts == null) { isConfig = false; opts = mOriginalIntent.getParcelableExtra("googleSignInOptions"); }
        if (opts instanceof Parcelable) {
            GmsSignInParcelCodec.Options o = GmsSignInParcelCodec.readOptions((Parcelable) opts, isConfig);
            mServerClientId = o.serverClientId;
            mRequestedScopes = o.scopes;
            mIdTokenRequested = o.idTokenRequested;
            mServerAuthCodeRequested = o.serverAuthCodeRequested;
            mForceCodeForRefreshToken = o.forceCodeForRefreshToken;
            GoogleAuthRequestPlan.tokenTypes(mServerClientId, mRequestedScopes, mIdTokenRequested, mServerAuthCodeRequested);
            Log.i(TAG, "parseSignInOptions: guest=" + mCallingPackage
                    + " idToken=" + mIdTokenRequested + " authCode=" + mServerAuthCodeRequested);
            return;
        }
        throw new IllegalArgumentException("Missing Google sign-in options");
    }

    private void setupView() {
        mUi = new GoogleAuthUi(this);
        LinearLayout dialog = mUi.dialog();

        dialog.addView(mUi.brand("Sign in with Google"));
        mUi.appHeading(dialog, mCallingPackage, "Choose an account to continue");

        mAccountsScroll = new ScrollView(this);
        mAccountsScroll.setFillViewport(false);
        mAccountsScroll.setVerticalScrollBarEnabled(false);
        mAccountListLayout = mUi.column();
        mAccountsScroll.addView(mAccountListLayout);
        dialog.addView(mAccountsScroll, new LinearLayout.LayoutParams(-1, -2));

        mProgressLayout = mUi.column();
        mProgressLayout.setGravity(android.view.Gravity.CENTER);
        mProgressLayout.setPadding(0, mUi.dp(22), 0, mUi.dp(22));
        mProgressLayout.setVisibility(android.view.View.GONE);
        mProgressLayout.addView(mUi.loading(), new LinearLayout.LayoutParams(-1, mUi.dp(20)));
        mUi.space(mProgressLayout, 16);
        mStatusText = mUi.caption("Signing in…");
        mStatusText.setGravity(android.view.Gravity.CENTER);
        mProgressLayout.addView(mStatusText);
        dialog.addView(mProgressLayout);

        mUi.space(dialog, mUi.compact() ? 0 : 12);
        mUi.footer().addView(mUi.button("Cancel", false, this::cancelAndFinish),
                new LinearLayout.LayoutParams(-1, mUi.dp(48)));
    }

    private void populateAccounts() {
        mAccountListLayout.removeAllViews();
        mAccountListLayout.setVisibility(android.view.View.VISIBLE);
        mAccountsScroll.setVisibility(android.view.View.VISIBLE);
        mProgressLayout.setVisibility(android.view.View.GONE);
        Account[] accounts = GmsBridge.getRealGoogleAccounts();
        int count = accounts != null ? accounts.length : 0;
        for (int i = 0; i < count; i++) {
            Account acct = accounts[i];
            String name = acct.name.contains("@") ? acct.name.substring(0, acct.name.indexOf('@')) : acct.name;
            if (name.isEmpty()) name = acct.name;
            final Account finalAcct = acct;
            android.view.View row = mUi.accountRow(name, acct.name, false, () -> selectAccountAndSignIn(finalAcct));
            mAccountListLayout.addView(row, new LinearLayout.LayoutParams(-1, -2));
            mUi.reveal(row, i);
            mUi.space(mAccountListLayout, 8);
        }
        final boolean noAccounts = count == 0;
        android.view.View addRow = mUi.accountRow(noAccounts ? "Add a Google account" : "Use another account",
                noAccounts ? "Sign in securely with Google" : null, true, this::launchAddAccount);
        mAccountListLayout.addView(addRow, new LinearLayout.LayoutParams(-1, -2));
        mUi.reveal(addRow, count);

        mUi.footer().removeAllViews();
        mUi.footer().addView(mUi.button("Cancel", false, this::cancelAndFinish),
                new LinearLayout.LayoutParams(-1, mUi.dp(48)));
    }

    private void selectAccountAndSignIn(Account account) {
        mSelectedAccount = account;
        showLoading("Signing in with " + account.name + "…");
        sExecutor.execute(() -> {
            try {
                Bundle result = GmsBridge.requestGoogleToken(GmsSignInBridgeActivity.this,
                        account, mServerClientId, mRequestedScopes, mCallingPackage,
                        mIdTokenRequested, mServerAuthCodeRequested, mForceCodeForRefreshToken);
                if (result == null) {
                    mMainHandler.post(() -> failAndFinish(8, "Google returned an empty sign-in result"));
                    return;
                }
                if (result.containsKey("errorCode")) {
                    int code = result.getInt("errorCode", 8);
                    String msg = result.getString("errorMessage", "Google authorization failed");
                    mMainHandler.post(() -> failAndFinish(code, msg));
                    return;
                }
                Intent pendingIntent = result.getParcelable("intent");
                if (pendingIntent != null) {
                    mMainHandler.post(() -> {
                        if (!isFinishing() && !isDestroyed()) {
                            mConsentAttempts++;
                            if (mConsentAttempts > 3) {
                                failAndFinish(8, "Google consent did not return the requested credential");
                                return;
                            }
                            try {
                                startActivityForResult(pendingIntent, RC_CONSENT);
                            } catch (Exception e) {
                                failAndFinish(8, "Google consent screen could not be opened");
                            }
                        }
                    });
                    return;
                }
                String idToken = result.getString(GmsBridge.EXTRA_ID_TOKEN);
                String serverAuthCode = result.getString(GmsBridge.EXTRA_SERVER_AUTH_CODE);
                String accountId = result.getString("accountId");
                if (idToken != null) {
                    try {
                        String[] parts = idToken.split("\\.");
                        if (parts.length >= 2) {
                            String payload = new String(android.util.Base64.decode(parts[1],
                                    android.util.Base64.URL_SAFE | android.util.Base64.NO_PADDING), "UTF-8");
                            accountId = new org.json.JSONObject(payload).optString("sub", accountId);
                        }
                    } catch (Exception ignored) {}
                }
                if (accountId == null) {
                    accountId = GmsBridge.requestGoogleAccountId(this, account, mCallingPackage);
                }
                final String finalId = accountId;
                final String finalIdToken = idToken;
                final String finalCode = serverAuthCode;
                final Bundle finalResult = result;
                mMainHandler.post(() -> {
                    if (!isFinishing() && !isDestroyed()) {
                        finishWithAccount(account, finalId, finalIdToken, finalCode,
                                finalResult.getString("displayName"),
                                finalResult.getString("givenName"),
                                finalResult.getString("familyName"),
                                finalResult.getString("photoUrl"));
                    }
                });
            } catch (Exception e) {
                Log.w(TAG, "Google authorization failed", e);
                int code = GoogleAuthRequestPlan.statusCode(e);
                mMainHandler.post(() -> failAndFinish(code, "Google authorization failed"));
            }
        });
    }

    private void finishWithAccount(Account account, String accountId, String idToken,
            String serverAuthCode, String displayName, String givenName, String familyName, String photoUrl) {
        if (isFinishing() || isDestroyed()) return;
        Log.i(TAG, "finishWithAccount: " + account.name + " id=" + accountId);
        try {
            long expiration = System.currentTimeMillis() / 1000 + 3600;
            List<String> grantedScopes = new ArrayList<>(mRequestedScopes);
            if (grantedScopes.isEmpty()) {
                grantedScopes.add("openid"); grantedScopes.add("email"); grantedScopes.add("profile");
            }
            Uri photoUri = photoUrl != null && !photoUrl.isEmpty() ? Uri.parse(photoUrl) : null;
            Parcelable gsa = GmsBridge.createGoogleSignInAccount(this, mCallingPackage,
                    accountId, idToken, account.name, displayName, givenName, familyName,
                    photoUri, serverAuthCode, expiration,
                    accountId != null ? accountId : account.name, grantedScopes);
            Parcelable status = GmsBridge.createStatus(0, null, mCallingPackage);
            Intent result = new Intent();
            if (gsa != null) result.putExtra(GmsBridge.EXTRA_GOOGLE_SIGN_IN_ACCOUNT, gsa);
            if (status != null) result.putExtra(GmsBridge.EXTRA_GOOGLE_SIGN_IN_STATUS, status);
            result.putExtra("signedInAccount", gsa);
            setResult(RESULT_OK, result);
            // Try to cache to guest prefs
            try {
                saveAccountToGuestPrefs(mCallingPackage, account.name, accountId, idToken,
                        displayName, givenName, familyName, photoUrl, expiration, grantedScopes);
            } catch (Exception ignored) {}
        } catch (Exception e) {
            Log.w(TAG, "finishWithAccount error", e);
            Parcelable status = GmsBridge.createStatus(8, "Could not build sign-in result", mCallingPackage);
            Intent result = new Intent();
            if (status != null) result.putExtra(GmsBridge.EXTRA_GOOGLE_SIGN_IN_STATUS, status);
            setResult(RESULT_CANCELED, result);
        }
        finish();
    }

    private void saveAccountToGuestPrefs(String pkg, String email, String accountId,
            String tokenId, String displayName, String givenName, String familyName,
            String photoUrl, long expiration, List<String> grantedScopes) throws Exception {
        int userId = BActivityThread.getUserId();
        File prefFile = BEnvironment.getXSharedPreferences(pkg, "com.google.android.gms.signin");
        if (prefFile == null) return;
        prefFile.getParentFile().mkdirs();
        JSONArray scopesArr = new JSONArray();
        for (String s : grantedScopes) scopesArr.put(s);
        JSONObject accountJson = new JSONObject()
                .put("id", accountId != null ? accountId : "")
                .put("tokenId", tokenId != null ? tokenId : "")
                .put("email", email)
                .put("displayName", displayName != null ? displayName : "")
                .put("givenName", givenName != null ? givenName : "")
                .put("familyName", familyName != null ? familyName : "")
                .put("photoUrl", photoUrl != null ? photoUrl : "")
                .put("expirationTime", expiration)
                .put("grantedScopes", scopesArr)
                .put("obfuscatedIdentifier", accountId != null ? accountId : email);
        String accountKey = "googleSignInAccount:" + email;
        String xml = "<?xml version='1.0' encoding='utf-8' standalone='yes' ?>\n<map>\n"
                + "    <int name=\"bridgeCredentialVersion\" value=\"2\" />\n"
                + "    <string name=\"defaultGoogleSignInAccount\">" + escapeXml(email) + "</string>\n"
                + "    <string name=\"" + escapeXml(accountKey) + "\">"
                + escapeXml(accountJson.toString()) + "</string>\n</map>";
        try (FileOutputStream fos = new FileOutputStream(prefFile)) {
            fos.write(xml.getBytes("UTF-8"));
        }
    }

    private static String escapeXml(String s) {
        if (s == null) return "";
        return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
                .replace("\"", "&quot;").replace("'", "&apos;");
    }

    public void cancelAndFinish() {
        if (isFinishing() || isDestroyed()) return;
        Parcelable status = GmsBridge.createStatus(16, "Canceled", mCallingPackage);
        Intent result = new Intent();
        if (status != null) result.putExtra(GmsBridge.EXTRA_GOOGLE_SIGN_IN_STATUS, status);
        setResult(RESULT_CANCELED, result);
        finish();
    }

    public void failAndFinish(int statusCode, String message) {
        if (isFinishing() || isDestroyed()) return;
        Log.w(TAG, "Google sign-in failed: guest=" + mCallingPackage + " status=" + statusCode + " msg=" + message);
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show();
        Parcelable status = GmsBridge.createStatus(statusCode, message, mCallingPackage);
        Intent result = new Intent();
        if (status != null) result.putExtra(GmsBridge.EXTRA_GOOGLE_SIGN_IN_STATUS, status);
        setResult(RESULT_CANCELED, result);
        finish();
    }

    private void showLoading(String message) {
        mAccountsScroll.setVisibility(android.view.View.GONE);
        mProgressLayout.setVisibility(android.view.View.VISIBLE);
        mStatusText.setText(message);
        mUi.footer().removeAllViews();
        mUi.footer().addView(mUi.button("Cancel", false, this::cancelAndFinish),
                new LinearLayout.LayoutParams(-1, mUi.dp(48)));
    }

    public void launchAddAccount() {
        startActivityForResult(EmbeddedGoogleAuth.loginIntent(this, null), RC_ADD_ACCOUNT);
    }
}
