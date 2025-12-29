package silent.owner;

import android.app.Activity;
import android.os.Bundle;
import android.view.View;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ImageButton;
import android.widget.ProgressBar;
import android.widget.Toast;

public class Main extends Activity {
    WebView web;
    ProgressBar bar;
    ImageButton btn;

    protected void onCreate(Bundle s) {
        super.onCreate(s);
        setContentView(R.layout.main);
        web = findViewById(R.id.web);
        bar = findViewById(R.id.bar);
        btn = findViewById(R.id.btn);

        web.getSettings().setJavaScriptEnabled(true);
        web.getSettings().setDomStorageEnabled(true);
        web.setWebViewClient(new WebViewClient() {
            public void onPageStarted(WebView v, String u, android.graphics.Bitmap b) {
                bar.setVisibility(View.VISIBLE);
            }
            public void onPageFinished(WebView v, String u) {
                bar.setVisibility(View.GONE);
            }
            public boolean shouldOverrideUrlLoading(WebView v, String u) {
                v.loadUrl(u);
                return true;
            }
        });

        btn.setOnClickListener(v -> {
            web.reload();
            Toast.makeText(this, "Refreshing", Toast.LENGTH_SHORT).show();
        });

        web.loadUrl("https://silentmultipanel.vippanel.in");
    }

    public void onBackPressed() {
        if(web.canGoBack()) web.goBack();
        else super.onBackPressed();
    }
}
