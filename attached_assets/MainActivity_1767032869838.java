package com.silentpanel.app;

import android.app.Activity;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.Window;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.widget.Toast;

public class MainActivity extends Activity {

    private WebView webView;
    private ProgressBar progressBar;
    private View splashScreen;
    private Handler uiHandler;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        uiHandler = new Handler(Looper.getMainLooper());

        requestWindowFeature(Window.FEATURE_NO_TITLE);
        getWindow().setFlags(
			WindowManager.LayoutParams.FLAG_FULLSCREEN,
			WindowManager.LayoutParams.FLAG_FULLSCREEN
        );

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            getWindow().setStatusBarColor(0xFF0A0E27);
        }

        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webview);
        progressBar = findViewById(R.id.progressBar);
        splashScreen = findViewById(R.id.splash_screen);

        if (webView != null) {
            setupWebView();
            loadWebsite();
        } else {
            Toast.makeText(this, "WebView not found", Toast.LENGTH_SHORT).show();
        }
    }

    private void setupWebView() {
        try {
            WebSettings webSettings = webView.getSettings();

            webSettings.setJavaScriptEnabled(true);
            webSettings.setJavaScriptCanOpenWindowsAutomatically(true);

            webSettings.setRenderPriority(WebSettings.RenderPriority.HIGH);

            webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);
            webSettings.setDomStorageEnabled(true);
            webSettings.setDatabaseEnabled(true);
            webSettings.setAppCacheEnabled(true);
            webSettings.setAppCachePath(getCacheDir().getAbsolutePath());

            webSettings.setLoadsImagesAutomatically(true);
            webSettings.setBlockNetworkImage(false);
            webSettings.setBlockNetworkLoads(false);

            webSettings.setUseWideViewPort(true);
            webSettings.setLoadWithOverviewMode(true);

            webSettings.setBuiltInZoomControls(true);
            webSettings.setDisplayZoomControls(false);

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                webSettings.setMixedContentMode(
					WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
                );
            }

            CookieManager.getInstance().setAcceptCookie(true);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                CookieManager.getInstance()
					.setAcceptThirdPartyCookies(webView, true);
            }

            webView.setOnLongClickListener(new View.OnLongClickListener() {
					@Override
					public boolean onLongClick(View v) {
						return true;
					}
				});

            webView.setDrawingCacheEnabled(true);
            webView.setDrawingCacheQuality(View.DRAWING_CACHE_QUALITY_HIGH);

            webView.setWebViewClient(new FastWebViewClient());
            webView.setWebChromeClient(new FastWebChromeClient());

        } catch (Exception e) {
            Toast.makeText(
				this,
				"Error setting up WebView: " + e.getMessage(),
				Toast.LENGTH_SHORT
            ).show();
        }
    }

    private void loadWebsite() {
        try {
            // YOUR WEBSITE LINK (ONLY CHANGE)
            String websiteUrl = "https://silentmultipanel.vippanel.in";

            if (progressBar != null) {
                progressBar.setVisibility(View.VISIBLE);
                progressBar.setProgress(0);
            }

            webView.loadUrl(websiteUrl);

        } catch (Exception e) {
            Toast.makeText(
				this,
				"Error loading website: " + e.getMessage(),
				Toast.LENGTH_SHORT
            ).show();
        }
    }

    @Override
    public void onBackPressed() {
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }

    @Override
    protected void onPause() {
        super.onPause();
        if (webView != null) {
            webView.onPause();
            webView.pauseTimers();
        }
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (webView != null) {
            webView.onResume();
            webView.resumeTimers();
        }
    }

    @Override
    protected void onDestroy() {
        if (webView != null) {
            webView.clearHistory();
            webView.clearCache(true);
            webView.destroy();
        }
        super.onDestroy();
    }

    private class FastWebViewClient extends WebViewClient {

        @Override
        public void onPageStarted(WebView view, String url,
                                  android.graphics.Bitmap favicon) {
            super.onPageStarted(view, url, favicon);

            if (splashScreen != null &&
				splashScreen.getVisibility() == View.VISIBLE) {

                splashScreen.animate()
					.alpha(0f)
					.setDuration(200)
					.withEndAction(new Runnable() {
						@Override
						public void run() {
							splashScreen.setVisibility(View.GONE);
						}
					})
					.start();
            }

            if (progressBar != null) {
                progressBar.setVisibility(View.VISIBLE);
                progressBar.setProgress(10);
            }
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);
            if (progressBar != null) {
                progressBar.setProgress(100);
                progressBar.setVisibility(View.GONE);
            }
        }

        @Override
        public boolean shouldOverrideUrlLoading(WebView view, String url) {
            view.loadUrl(url);
            return true;
        }
    }

    private class FastWebChromeClient extends WebChromeClient {

        @Override
        public void onProgressChanged(WebView view, int newProgress) {
            super.onProgressChanged(view, newProgress);
            if (progressBar != null) {
                progressBar.setProgress(newProgress);
                if (newProgress >= 100) {
                    progressBar.setVisibility(View.GONE);
                }
            }
        }
    }
}
