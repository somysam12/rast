package com.mundo.gms;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.Intent;
import android.graphics.drawable.ColorDrawable;
import android.net.Uri;
import android.net.http.SslError;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.SslErrorHandler;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.TextView;

import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public final class GmsLoginActivity extends Activity {
    private static final ExecutorService sExecutor = Executors.newCachedThreadPool();
    private final Handler mMain = new Handler(Looper.getMainLooper());

    private GoogleAuthUi mUi;
    private WebView mWeb;
    private TextView mStatus;
    private WavyProgressIndicator mProgress;
    private String mSession;
    private String mAndroidId;
    private volatile boolean mCompleting;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().addFlags(0x2000); // FLAG_SECURE
        mUi = new GoogleAuthUi(this);
        getWindow().setBackgroundDrawable(new ColorDrawable(0));
        getWindow().addFlags(0x2);    // FLAG_DIM_BEHIND
        getWindow().setDimAmount(0.2f);
        getWindow().setStatusBarColor(0);
        getWindow().setNavigationBarColor(mUi.dark ? -15196112 : -1182983);

        FrameLayout root = new FrameLayout(this);
        root.setFitsSystemWindows(true);

        LinearLayout column = mUi.column();
        mUi.glassSurface(column, 28f);

        FrameLayout.LayoutParams colLp = new FrameLayout.LayoutParams(-1, -1);
        int m = mUi.dp(12);
        colLp.setMargins(m, m, m, m);
        root.addView(column, colLp);

        LinearLayout header = new LinearLayout(this);
        header.setGravity(android.view.Gravity.CENTER_VERTICAL);
        header.setPadding(mUi.dp(20), mUi.dp(14), mUi.dp(12), mUi.dp(8));
        header.addView(mUi.brand("Sign in with Google"), new LinearLayout.LayoutParams(0, -2, 1f));
        Button closeBtn = mUi.button("×", false, this::finish);
        closeBtn.setTextSize(24f);
        closeBtn.setPadding(0, 0, 0, 0);
        closeBtn.setMinimumHeight(0);
        closeBtn.setContentDescription("Close Google sign-in");
        header.addView(closeBtn, new LinearLayout.LayoutParams(mUi.dp(40), mUi.dp(40)));
        column.addView(header);

        mStatus = mUi.caption("Connecting to Google…");
        mStatus.setGravity(android.view.Gravity.CENTER);
        mStatus.setPadding(mUi.dp(20), 0, mUi.dp(20), mUi.dp(14));
        column.addView(mStatus);

        mProgress = mUi.loading();
        LinearLayout.LayoutParams progLp = new LinearLayout.LayoutParams(-1, mUi.dp(20));
        progLp.setMargins(mUi.dp(20), 0, mUi.dp(20), mUi.dp(6));
        column.addView(mProgress, progLp);

        FrameLayout webContainer = new FrameLayout(this);
        webContainer.setBackgroundColor(-1);
        mWeb = new WebView(this);
        mWeb.setAlpha(0f);
        webContainer.addView(mWeb, new FrameLayout.LayoutParams(-1, -1));
        column.addView(webContainer, new LinearLayout.LayoutParams(-1, 0, 1f));

        setContentView(root);
        mUi.enter(column);
        configureWebView();

        mSession = getIntent().getStringExtra("session");
        if (mSession != null) {
            loadConsent();
        } else {
            sExecutor.execute(() -> {
                Bundle result = EmbeddedGoogleAuth.call(this, "checkin", null);
                mMain.post(() -> {
                    if (!isFinishing() && !isDestroyed()) {
                        if (result != null && !result.containsKey("errorCode")) {
                            mAndroidId = result.getString("androidId");
                            loadLogin();
                        } else {
                            showError(result);
                        }
                    }
                });
            });
        }
    }

    @SuppressLint({"SetJavaScriptEnabled", "AddJavascriptInterface"})
    private void configureWebView() {
        WebSettings s = mWeb.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(false);
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        s.setSaveFormData(false);
        s.setSupportMultipleWindows(false);
        s.setJavaScriptCanOpenWindowsAutomatically(false);
        s.setUserAgentString(s.getUserAgentString() + " MinuteMaid");
        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(mWeb, false);
        mWeb.addJavascriptInterface(new LoginBridge(), "mm");
        mWeb.addJavascriptInterface(new ConsentBridge(), "OAuthConsent");
        mWeb.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                mProgress.setVisibility(android.view.View.GONE);
                if (EmbeddedGoogleAuth.isGoogleUrl(url)) {
                    mStatus.setText("accounts.google.com");
                    mWeb.animate().alpha(1f).setDuration(240).start();
                    if (mSession == null) {
                        Uri parsed = Uri.parse(url);
                        String frag = parsed.getFragment();
                        String path = parsed.getPath();
                        if ("close".equals(frag) || "/o/oauth2/programmatic_auth".equals(path)
                                || "/signin/continue".equals(path)) {
                            completeLogin();
                        }
                    }
                }
            }

            @Override
            public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError err) {
                handler.cancel();
                showError(EmbeddedGoogleAuth.error(7, "Google’s secure connection could not be verified"));
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                return navigate(url);
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest req) {
                return req.isForMainFrame() && navigate(req.getUrl().toString());
            }
        });
    }

    private void loadLogin() {
        Locale locale = Locale.getDefault();
        Locale us = Locale.US;
        Uri.Builder ub = Uri.parse("https://accounts.google.com/EmbeddedSetup").buildUpon()
                .appendQueryParameter("source", "android")
                .appendQueryParameter("xoauth_display_name", "Google sign-in")
                .appendQueryParameter("lang", locale.getLanguage())
                .appendQueryParameter("cc", locale.getCountry().toLowerCase(us))
                .appendQueryParameter("langCountry", locale.toString().toLowerCase(us))
                .appendQueryParameter("hl", locale.toString().replace('_', '-'))
                .appendQueryParameter("tmpl", "new_account");
        String email = getIntent().getStringExtra("email");
        if (email != null) ub.appendQueryParameter("Email", email);
        mWeb.loadUrl(ub.build().toString());
    }

    private void loadConsent() {
        Bundle args = new Bundle();
        args.putString("session", mSession);
        Bundle result = EmbeddedGoogleAuth.call(this, "pending", args);
        if (result != null && !result.containsKey("errorCode")) {
            String consentUrl = result.getString("consentUrl");
            if (!EmbeddedGoogleAuth.isGoogleUrl(consentUrl)) {
                showError(EmbeddedGoogleAuth.error(8, "Invalid Google consent page"));
                return;
            }
            java.util.ArrayList<String> cookies = result.getStringArrayList("cookies");
            if (cookies != null) {
                for (String c : cookies) CookieManager.getInstance().setCookie(consentUrl, c);
            }
            CookieManager.getInstance().flush();
            mWeb.loadUrl(consentUrl);
        } else {
            showError(result);
        }
    }

    public void completeLogin() {
        if (isFinishing() || mCompleting || mSession != null
                || !EmbeddedGoogleAuth.isGoogleUrl(mWeb.getUrl())) return;
        String path = Uri.parse(mWeb.getUrl()).getPath();
        String baseUrl = "/o/oauth2/programmatic_auth".equals(path)
                ? "https://accounts.google.com/o/oauth2/programmatic_auth"
                : "https://accounts.google.com/EmbeddedSetup";
        String cookie = CookieManager.getInstance().getCookie(baseUrl);
        String oauthToken = null;
        if (cookie != null) {
            for (String part : cookie.split(";")) {
                String t = part.trim();
                if (t.startsWith("oauth_token=")) {
                    oauthToken = t.substring(12);
                    break;
                }
            }
        }
        if (oauthToken == null || oauthToken.isEmpty()) {
            showError(EmbeddedGoogleAuth.error(8, "Google did not return a completed account session"));
            return;
        }
        mCompleting = true;
        mProgress.setVisibility(android.view.View.VISIBLE);
        mStatus.setText("Completing Google sign-in…");
        final Bundle loginArgs = new Bundle();
        loginArgs.putString("oauthCookie", oauthToken);
        sExecutor.execute(() -> {
            Bundle result = EmbeddedGoogleAuth.call(this, "login", loginArgs);
            loginArgs.remove("oauthCookie");
            mMain.post(() -> {
                if (!isFinishing() && !isDestroyed()) {
                    if (result != null && !result.containsKey("errorCode")) {
                        Intent out = new Intent();
                        out.putExtras(result);
                        setResult(RESULT_OK, out);
                        CookieManager.getInstance().removeAllCookies(null);
                        finish();
                    } else {
                        mCompleting = false;
                        showError(result);
                    }
                }
            });
        });
    }

    public void completeConsent(String consentResult) {
        if (mSession == null || mCompleting) return;
        mCompleting = true;
        Bundle args = new Bundle();
        args.putString("session", mSession);
        args.putString("consentResult", consentResult);
        Bundle result = EmbeddedGoogleAuth.call(this, "consent", args);
        setResult(result != null && !result.containsKey("errorCode") ? RESULT_OK : RESULT_CANCELED);
        CookieManager.getInstance().removeAllCookies(null);
        finish();
    }

    public boolean navigate(String url) {
        if (EmbeddedGoogleAuth.isGoogleUrl(url)) return false;
        mStatus.setText("This Google sign-in step requires an unsupported external page");
        return true;
    }

    public void showError(Bundle errorBundle) {
        mProgress.setVisibility(android.view.View.GONE);
        String msg = "Google sign-in could not be completed";
        if (errorBundle != null) msg = errorBundle.getString("errorMessage", msg);
        mStatus.setText(msg);
    }

    @Override
    public void onBackPressed() {
        if (mWeb != null && mWeb.canGoBack()) mWeb.goBack();
        else finish();
    }

    @Override
    public void onDestroy() {
        if (mWeb != null) {
            mWeb.removeJavascriptInterface("mm");
            mWeb.removeJavascriptInterface("OAuthConsent");
            mWeb.stopLoading();
            mWeb.destroy();
        }
        if (isFinishing()) CookieManager.getInstance().removeAllCookies(null);
        if (mUi != null) mUi.close();
        super.onDestroy();
    }

    public final class LoginBridge {
        @JavascriptInterface public void addAccount(String s) {}
        @JavascriptInterface public void attemptLogin(String s, String s2) {}
        @JavascriptInterface public void backupSyncOptIn(String s) {}
        @JavascriptInterface public void clearOldLoginAttempts() {}
        @JavascriptInterface public void closeView() { runOnUiThread(() -> finish()); }
        @JavascriptInterface public String fetchVerifiedPhoneNumber() { return null; }
        @JavascriptInterface public String getAccounts() { return "[]"; }
        @JavascriptInterface public String getAllowedDomains() { return "[]"; }
        @JavascriptInterface public String getAndroidId() { return mAndroidId; }
        @JavascriptInterface public int getAuthModuleVersionCode() { return MicroGAuthProtocol.GMS_VERSION; }
        @JavascriptInterface public int getBuildVersionSdk() { return android.os.Build.VERSION.SDK_INT; }
        @JavascriptInterface public int getDeviceContactsCount() { return -1; }
        @JavascriptInterface public int getDeviceDataVersionInfo() { return 1; }
        @JavascriptInterface public String getFactoryResetChallenges() { return "[]"; }
        @JavascriptInterface public String getPhoneNumber() { return null; }
        @JavascriptInterface public int getPlayServicesVersionCode() { return MicroGAuthProtocol.GMS_VERSION; }
        @JavascriptInterface public String getSimSerial() { return null; }
        @JavascriptInterface public int getSimState() { return 0; }
        @JavascriptInterface public void goBack() { runOnUiThread(() -> onBackPressed()); }
        @JavascriptInterface public boolean hasPhoneNumber() { return false; }
        @JavascriptInterface public boolean hasTelephony() { return false; }
        @JavascriptInterface public void hideKeyboard() {}
        @JavascriptInterface public boolean isUserOwner() { return true; }
        @JavascriptInterface public void log(String s) {}
        @JavascriptInterface public void notifyOnTermsOfServiceAccepted() {}
        @JavascriptInterface public void setAccountIdentifier(String s) {}
        @JavascriptInterface public void setAllActionsEnabled(boolean z) {}
        @JavascriptInterface public void setBackButtonEnabled(boolean z) {}
        @JavascriptInterface public void setPrimaryActionEnabled(boolean z) {}
        @JavascriptInterface public void setPrimaryActionLabel(String s, int i) {}
        @JavascriptInterface public void setSecondaryActionEnabled(boolean z) {}
        @JavascriptInterface public void setSecondaryActionLabel(String s, int i) {}
        @JavascriptInterface public void showKeyboard() {}
        @JavascriptInterface public void showView() {}
        @JavascriptInterface public void skipLogin() { runOnUiThread(() -> finish()); }
    }

    public final class ConsentBridge {
        @JavascriptInterface public void cancel() { runOnUiThread(() -> finish()); }
        @JavascriptInterface public int getModuleVersion() { return MicroGAuthProtocol.GMS_VERSION; }
        @JavascriptInterface public void setConsentResult(String result) {
            runOnUiThread(() -> completeConsent(result));
        }
        @JavascriptInterface public void showView() {}
    }
}
