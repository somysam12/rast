package web.browser;

import android.app.Activity;
import android.os.Bundle;
import android.view.View;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.ProgressBar;

public class main extends Activity {
    
    private WebView view;
    private ProgressBar loader;
    private Button refresh;
    
    @Override
    public void onCreate(Bundle b) {
        super.onCreate(b);
        setContentView(R.layout.main);
        
        view = (WebView) findViewById(R.id.view);
        loader = (ProgressBar) findViewById(R.id.loader);
        refresh = (Button) findViewById(R.id.refresh);
        
        view.getSettings().setJavaScriptEnabled(true);
        view.getSettings().setDomStorageEnabled(true);
        view.getSettings().setLoadWithOverviewMode(true);
        view.getSettings().setUseWideViewPort(true);
        
        view.setWebViewClient(new WebViewClient() {
            public void onPageStarted(WebView w, String u, android.graphics.Bitmap f) {
                loader.setVisibility(View.VISIBLE);
            }
            public void onPageFinished(WebView w, String u) {
                loader.setVisibility(View.GONE);
            }
        });
        
        refresh.setOnClickListener(new View.OnClickListener() {
            public void onClick(View v) {
                view.reload();
            }
        });
        
        view.loadUrl("https://silentmultipanel.vippanel.in");
    }
    
    @Override
    public void onBackPressed() {
        if (view.canGoBack()) {
            view.goBack();
        } else {
            super.onBackPressed();
        }
    }
}
