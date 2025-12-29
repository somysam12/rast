package app;

import android.app.Activity;
import android.os.Bundle;
import android.view.View;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ImageButton;
import android.widget.ProgressBar;

public class Activity extends android.app.Activity {
    WebView web;
    ProgressBar prog;
    ImageButton btn;

    public void onCreate(Bundle s) {
        super.onCreate(s);
        setContentView(R.layout.main);
        web = findViewById(R.id.web);
        prog = findViewById(R.id.prog);
        btn = findViewById(R.id.btn);
        web.getSettings().setJavaScriptEnabled(true);
        web.getSettings().setDomStorageEnabled(true);
        web.setWebViewClient(new WebViewClient() {
            public void onPageStarted(WebView v, String u, android.graphics.Bitmap b) {
                prog.setVisibility(View.VISIBLE);
            }
            public void onPageFinished(WebView v, String u) {
                prog.setVisibility(View.GONE);
            }
        });
        btn.setOnClickListener(v -> web.reload());
        web.loadUrl("https://silentmultipanel.vippanel.in");
    }
    
    public void onBackPressed() {
        if(web.canGoBack()) web.goBack();
        else super.onBackPressed();
    }
}
