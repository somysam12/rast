package com.mundo.gms;

import android.app.Activity;
import android.os.Bundle;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;

import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public final class GmsConsentActivity extends Activity {
    private static final ExecutorService sExecutor = Executors.newCachedThreadPool();
    private GoogleAuthUi mUi;
    private volatile boolean mGranting;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        Bundle request = getIntent().getBundleExtra("request");
        if (request == null) { finish(); return; }

        mUi = new GoogleAuthUi(this);
        LinearLayout dialog = mUi.dialog();

        dialog.addView(mUi.brand("Google account"));
        mUi.appHeading(dialog, request.getString("guest"), "You’re in control of your account");

        LinearLayout twoCol = new LinearLayout(this);
        twoCol.setOrientation(mUi.compact() ? LinearLayout.HORIZONTAL : LinearLayout.VERTICAL);
        LinearLayout leftCol = mUi.column();
        LinearLayout rightCol = mUi.column();
        if (mUi.compact()) {
            twoCol.addView(leftCol, new LinearLayout.LayoutParams(0, -2, 0.95f));
            LinearLayout.LayoutParams rLp = new LinearLayout.LayoutParams(0, -2, 1.05f);
            rLp.setMarginStart(mUi.dp(18));
            twoCol.addView(rightCol, rLp);
        } else {
            twoCol.addView(leftCol);
            twoCol.addView(rightCol);
        }
        dialog.addView(twoCol);

        String email = request.getString("email", "");
        String name = email.contains("@") ? email.substring(0, email.indexOf('@')) : email;
        if (name.isEmpty()) name = "Google account";
        View accountRow = mUi.accountRow(name, email, false, null);
        if (accountRow instanceof LinearLayout) {
            LinearLayout ll = (LinearLayout) accountRow;
            if (ll.getChildCount() > 0) ll.removeViewAt(ll.getChildCount() - 1);
        }
        accountRow.setClickable(false);
        accountRow.setFocusable(false);
        leftCol.addView(accountRow);
        if (!mUi.compact()) mUi.space(leftCol, 22);

        rightCol.addView(mUi.text("This app will have access to", 14, true));
        mUi.space(rightCol, 12);
        LinearLayout scopesList = mUi.column();
        ArrayList<String> scopes = request.getStringArrayList("scopes");
        LinkedHashSet<String> labels = new LinkedHashSet<>();
        if (scopes != null) {
            for (String s : scopes) {
                if (s != null) labels.add(GoogleAuthUi.scopeLabel(s));
            }
        }
        if (labels.isEmpty()) labels.add("Basic account information");
        int idx = 0;
        for (String label : labels) {
            LinearLayout row = new LinearLayout(this);
            row.setGravity(android.view.Gravity.CENTER_VERTICAL);
            TextView check = mUi.text("✓", 15, true);
            check.setTextColor(-14391318); // Google blue
            row.addView(check, new LinearLayout.LayoutParams(mUi.dp(28), -2));
            row.addView(mUi.caption(label), new LinearLayout.LayoutParams(0, -2, 1f));
            scopesList.addView(row);
            mUi.space(scopesList, 12);
            mUi.reveal(row, idx++);
        }
        ScrollView scopesScroll = new ScrollView(this);
        scopesScroll.setVerticalScrollBarEnabled(false);
        scopesScroll.addView(scopesList);
        rightCol.addView(scopesScroll, new LinearLayout.LayoutParams(-1, -2));

        mUi.space(dialog, 8);

        final Bundle req = request;
        LinearLayout buttons = new LinearLayout(this);
        buttons.addView(mUi.button("Cancel", false, this::cancelAndFinish),
                new LinearLayout.LayoutParams(0, mUi.dp(52), 1f));
        LinearLayout.LayoutParams continueLp = new LinearLayout.LayoutParams(0, mUi.dp(52), 1f);
        continueLp.setMarginStart(mUi.dp(10));
        buttons.addView(mUi.button("Continue", true, () -> grantAccess(req)), continueLp);
        mUi.footer().addView(buttons);
    }

    private void grantAccess(Bundle request) {
        if (mGranting) return;
        mGranting = true;
        sExecutor.execute(() -> {
            Bundle result = EmbeddedGoogleAuth.call(this, "grant", request);
            int rc = (result != null && !result.containsKey("errorCode")) ? RESULT_OK : RESULT_CANCELED;
            runOnUiThread(() -> { setResult(rc); finish(); });
        });
    }

    private void cancelAndFinish() {
        setResult(RESULT_CANCELED);
        finish();
    }

    @Override
    public void onBackPressed() { cancelAndFinish(); }

    @Override
    public void onDestroy() {
        if (mUi != null) mUi.close();
        super.onDestroy();
    }
}
