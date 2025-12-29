package com.silentpanel.app;

import android.app.Activity;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.Window;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.widget.Toast;
import android.animation.Animator;
import android.animation.AnimatorListenerAdapter;

public class MainActivity extends Activity {

    private WebView webView;
    private ProgressBar progressBar;
    private View splashScreen;
    private Handler uiHandler;
    
    // For file uploads
    private ValueCallback<Uri[]> mUploadMessage;
    private final static int FILECHOOSER_RESULTCODE = 1;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        uiHandler = new Handler(Looper.getMainLooper());

        // Full screen and no title
        requestWindowFeature(Window.FEATURE_NO_TITLE);
        getWindow().setFlags(
                WindowManager.LayoutParams.FLAG_FULLSCREEN,
                WindowManager.LayoutParams.FLAG_FULLSCREEN
        );

        // Status bar color for immersive look
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            getWindow().setStatusBarColor(0xFF05070a); // Matching website bg
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
            // Enable hardware acceleration for smooth website animations
            webView.setLayerType(View.LAYER_TYPE_HARDWARE, null);
            
            WebSettings webSettings = webView.getSettings();

            webSettings.setJavaScriptEnabled(true);
            webSettings.setJavaScriptCanOpenWindowsAutomatically(true);
            
            // Enable file access for APK uploads
            webSettings.setAllowFileAccess(true);
            webSettings.setAllowContentAccess(true);

            webSettings.setDomStorageEnabled(true);
            webSettings.setDatabaseEnabled(true);
            webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);

            webSettings.setLoadsImagesAutomatically(true);
            webSettings.setUseWideViewPort(true);
            webSettings.setLoadWithOverviewMode(true);

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                webSettings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
            }

            CookieManager.getInstance().setAcceptCookie(true);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true);
            }

            // AIDE-safe long click listener (Java 7 compatible)
            webView.setOnLongClickListener(new View.OnLongClickListener() {
                @Override
                public boolean onLongClick(View v) {
                    return true;
                }
            });

            webView.setWebViewClient(new FastWebViewClient());
            webView.setWebChromeClient(new FastWebChromeClient());

        } catch (Exception e) {
            Toast.makeText(this, "Error setting up WebView: " + e.getMessage(), Toast.LENGTH_SHORT).show();
        }
    }

    private void loadWebsite() {
        try {
            // Updated to the URL from your working code
            String websiteUrl = "https://79de34c8-fff8-4ccf-9f46-36beeea3b515-00-23zygehn5l9uv.pike.replit.dev/"; 
            webView.loadUrl(websiteUrl);
        } catch (Exception e) {
            Toast.makeText(this, "Error loading website: " + e.getMessage(), Toast.LENGTH_SHORT).show();
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent intent) {
        super.onActivityResult(requestCode, resultCode, intent);
        if (requestCode == FILECHOOSER_RESULTCODE) {
            if (null == mUploadMessage) return;
            Uri[] result = null;
            if (resultCode == RESULT_OK && intent != null) {
                if (intent.getData() != null) {
                    result = new Uri[]{intent.getData()};
                }
            }
            mUploadMessage.onReceiveValue(result);
            mUploadMessage = null;
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
            webView.destroy();
        }
        super.onDestroy();
    }

    private class FastWebViewClient extends WebViewClient {

        @Override
        public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
            super.onPageStarted(view, url, favicon);

            if (splashScreen != null && splashScreen.getVisibility() == View.VISIBLE) {
                splashScreen.animate()
                        .alpha(0f)
                        .setDuration(200)
                        .setListener(new AnimatorListenerAdapter() {
                            @Override
                            public void onAnimationEnd(Animator animation) {
                                if (splashScreen != null) {
                                    splashScreen.setVisibility(View.GONE);
                                }
                            }
                        })
                        .start();
            }

            if (progressBar != null) {
                progressBar.setVisibility(View.VISIBLE);
            }
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);
            if (progressBar != null) {
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

        // Java 7 / AIDE compatible File Chooser (Android 5.0+)
        @Override
        public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> filePathCallback, WebChromeClient.FileChooserParams fileChooserParams) {
            if (mUploadMessage != null) {
                mUploadMessage.onReceiveValue(null);
            }
            mUploadMessage = filePathCallback;

            Intent intent = fileChooserParams.createIntent();
            try {
                startActivityForResult(intent, FILECHOOSER_RESULTCODE);
            } catch (Exception e) {
                mUploadMessage = null;
                Toast.makeText(MainActivity.this, "Cannot open file chooser", Toast.LENGTH_SHORT).show();
                return false;
            }
            return true;
        }
    }
}