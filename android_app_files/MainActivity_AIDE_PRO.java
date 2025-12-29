package com.silentpanel.app;

import android.app.Activity;
import android.os.Bundle;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

public class MainActivity extends Activity {

    private WebView webView;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webview);
        
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
            
            // Enable JavaScript
            webSettings.setJavaScriptEnabled(true);
            
            // Enable DOM Storage
            webSettings.setDomStorageEnabled(true);
            
            // Enable Database
            webSettings.setDatabaseEnabled(true);
            
            // Cache settings
            webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);
            
            // Display settings
            webSettings.setUseWideViewPort(true);
            webSettings.setLoadWithOverviewMode(true);
            
            // Zoom settings
            webSettings.setSupportZoom(true);
            webSettings.setBuiltInZoomControls(true);
            webSettings.setDisplayZoomControls(false);

            // Set WebViewClient
            webView.setWebViewClient(new WebViewClient() {
                @Override
                public boolean shouldOverrideUrlLoading(WebView view, String url) {
                    view.loadUrl(url);
                    return true;
                }
            });
        } catch (Exception e) {
            Toast.makeText(this, "Error setting up WebView: " + e.getMessage(), Toast.LENGTH_SHORT).show();
        }
    }

    private void loadWebsite() {
        try {
            // CHANGE THIS TO YOUR WEBSITE URL
            String websiteUrl = "https://YOUR_WEBSITE_URL.com";
            
            if (websiteUrl.equals("https://YOUR_WEBSITE_URL.com")) {
                Toast.makeText(this, "Please update website URL in MainActivity", Toast.LENGTH_LONG).show();
                webView.loadUrl("about:blank");
            } else {
                webView.loadUrl(websiteUrl);
            }
        } catch (Exception e) {
            Toast.makeText(this, "Error loading website: " + e.getMessage(), Toast.LENGTH_SHORT).show();
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
}
