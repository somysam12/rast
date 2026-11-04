package com.mundo.gms;

import android.app.Activity;
import android.content.Context;
import android.content.pm.ApplicationInfo;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.BitmapShader;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.ColorFilter;
import android.graphics.LinearGradient;
import android.graphics.Matrix;
import android.graphics.Outline;
import android.graphics.Paint;
import android.graphics.Rect;
import android.graphics.RectF;
import android.graphics.Shader;
import android.graphics.Typeface;
import android.graphics.drawable.Drawable;
import android.graphics.drawable.GradientDrawable;
import android.graphics.drawable.RippleDrawable;
import android.content.res.ColorStateList;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.view.View;
import android.view.ViewPropertyAnimator;
import android.view.Window;
import android.view.WindowManager;
import android.view.animation.PathInterpolator;
import android.widget.Button;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;

import java.util.Locale;
import java.util.concurrent.Executor;

public final class GoogleAuthUi implements AutoCloseable {
    public final int accent = -14391318; // Google blue
    public final Activity activity;
    public final boolean dark;
    public final int ink;
    public final int muted;
    private LinearLayout footer;
    private View card;

    public static final class AvatarDrawable extends Drawable {
        private final Bitmap bitmap;
        private final Matrix matrix = new Matrix();
        private final Paint paint;

        public AvatarDrawable(Bitmap bm) {
            paint = new Paint(Paint.ANTI_ALIAS_FLAG);
            bitmap = bm;
            paint.setShader(new BitmapShader(bm, Shader.TileMode.CLAMP, Shader.TileMode.CLAMP));
        }

        @Override
        public void draw(Canvas canvas) {
            Rect b = getBounds();
            float scale = Math.max((float) b.width() / bitmap.getWidth(), (float) b.height() / bitmap.getHeight());
            matrix.setScale(scale, scale);
            matrix.postTranslate(b.exactCenterX() - bitmap.getWidth() * scale / 2f,
                    b.exactCenterY() - bitmap.getHeight() * scale / 2f);
            paint.getShader().setLocalMatrix(matrix);
            canvas.drawCircle(b.exactCenterX(), b.exactCenterY(),
                    Math.min(b.width(), b.height()) / 2f, paint);
        }

        @Override public int getOpacity() { return android.graphics.PixelFormat.TRANSLUCENT; }
        @Override public void setAlpha(int a) { paint.setAlpha(a); invalidateSelf(); }
        @Override public void setColorFilter(ColorFilter cf) { paint.setColorFilter(cf); invalidateSelf(); }

        @Override
        public void getOutline(Outline outline) {
            outline.setOval(getBounds());
            outline.setAlpha(1f);
        }
    }

    public GoogleAuthUi(Activity activity) {
        this.activity = activity;
        boolean isDark = (activity.getResources().getConfiguration().uiMode & 0x30) == 0x20;
        this.dark = isDark;
        this.ink = isDark ? -657412 : -15196112;
        this.muted = isDark ? -4866612 : -10129277;
    }

    private boolean motionEnabled() {
        return Settings.Global.getFloat(activity.getContentResolver(), "animator_duration_scale", 1f) != 0f;
    }

    public int dp(float v) {
        return Math.round(v * activity.getResources().getDisplayMetrics().density);
    }

    public int availableHeight() {
        return Math.max(dp(120), activity.getResources().getDisplayMetrics().heightPixels - dp(24));
    }

    public boolean compact() {
        return activity.getResources().getConfiguration().orientation == 2;
    }

    public Drawable rounded(int color, float radius) {
        GradientDrawable d = new GradientDrawable();
        d.setColor(color);
        d.setCornerRadius(dp(radius));
        return d;
    }

    public void rowSurface(View view) {
        int rippleColor = dark ? 587202559 : 337930218;
        int bgColor = dark ? 285212671 : 1895825407;
        view.setBackground(new RippleDrawable(ColorStateList.valueOf(rippleColor),
                rounded(bgColor, 18), rounded(-1, 18)));
    }

    public LinearLayout dialog() {
        LinearLayout root = new LinearLayout(activity) {
            @Override
            protected void onMeasure(int widthSpec, int heightSpec) {
                super.onMeasure(widthSpec, View.MeasureSpec.makeMeasureSpec(0, 0));
                int max = Math.min(getMeasuredHeight(), availableHeight());
                if (View.MeasureSpec.getMode(heightSpec) != 0)
                    max = Math.min(max, View.MeasureSpec.getSize(heightSpec));
                super.onMeasure(widthSpec, View.MeasureSpec.makeMeasureSpec(max, View.MeasureSpec.EXACTLY));
            }
        };
        root.setOrientation(LinearLayout.VERTICAL);
        float pad = compact() ? 12 : 24;
        float padB = compact() ? 12 : 14;
        root.setPadding(dp(24), dp(pad), dp(24), dp(padB));

        LinearLayout content = column();
        ScrollView scroll = new ScrollView(activity);
        scroll.setFillViewport(false);
        scroll.setVerticalScrollBarEnabled(false);
        scroll.addView(content);
        root.addView(scroll, new LinearLayout.LayoutParams(-1, 0, 1f));

        LinearLayout foot = column();
        this.footer = foot;
        foot.setPadding(0, dp(compact() ? 8 : 12), 0, 0);
        root.addView(foot, new LinearLayout.LayoutParams(-1, -2));

        activity.setContentView(root);
        Window window = activity.getWindow();
        GradientDrawable bg = new GradientDrawable();
        bg.setColor(dark ? -1946157056 : -1056964609); // glass-like bg
        bg.setCornerRadius(dp(30));
        window.setBackgroundDrawable(bg);
        window.addFlags(WindowManager.LayoutParams.FLAG_DIM_BEHIND);
        window.setDimAmount(0.18f);
        int maxW = activity.getResources().getDisplayMetrics().widthPixels - dp(32);
        window.setLayout(Math.min(dp(compact() ? 560 : 392), maxW), -2);
        window.setGravity(android.view.Gravity.CENTER);
        activity.setFinishOnTouchOutside(false);
        enter(root);
        return content;
    }

    public void enter(View v) {
        card = v;
        v.setAlpha(0f);
        v.setTranslationY(dp(18));
        v.setScaleX(0.96f);
        v.setScaleY(0.96f);
        if (motionEnabled()) {
            v.animate().alpha(1f).translationY(0f).scaleX(1f).scaleY(1f)
                    .setDuration(360).setInterpolator(new PathInterpolator(0.18f, 0.75f, 0.2f, 1f)).start();
        } else {
            v.setAlpha(1f); v.setTranslationY(0f); v.setScaleX(1f); v.setScaleY(1f);
        }
    }

    public LinearLayout footer() { return footer; }

    public LinearLayout column() {
        LinearLayout ll = new LinearLayout(activity);
        ll.setOrientation(LinearLayout.VERTICAL);
        return ll;
    }

    public void space(LinearLayout parent, int dp) {
        parent.addView(new View(activity), new LinearLayout.LayoutParams(1, dp(dp)));
    }

    public TextView text(String s, int sp, boolean medium) {
        TextView tv = new TextView(activity);
        tv.setText(s);
        tv.setTextSize(sp);
        tv.setTextColor(ink);
        tv.setTypeface(Typeface.create(medium ? "sans-serif-medium" : "sans-serif", Typeface.NORMAL));
        tv.setIncludeFontPadding(false);
        tv.setLineSpacing(dp(3), 1f);
        return tv;
    }

    public TextView caption(String s) {
        TextView tv = text(s, 13, false);
        tv.setTextColor(muted);
        return tv;
    }

    public Button button(String label, boolean filled, Runnable onClick) {
        Button btn = new Button(activity);
        btn.setText(label);
        btn.setAllCaps(false);
        btn.setTextSize(15);
        btn.setTypeface(Typeface.create("sans-serif-medium", Typeface.NORMAL));
        btn.setTextColor(filled ? -1 : ink);
        int ripple = filled ? 872415231 : 405039082;
        int bg = filled ? -14391318 : (dark ? 419430399 : 1979711487);
        btn.setBackground(new RippleDrawable(ColorStateList.valueOf(ripple), rounded(bg, 26), null));
        btn.setMinHeight(0);
        btn.setMinimumHeight(dp(52));
        btn.setMinWidth(0);
        btn.setPadding(dp(20), 0, dp(20), 0);
        btn.setClickable(true);
        btn.setOnClickListener(v -> { if (onClick != null) onClick.run(); });
        return btn;
    }

    public void press(View v, Runnable onClick) {
        v.setClickable(true);
        v.setFocusable(true);
        v.setOnClickListener(view -> { if (onClick != null) onClick.run(); });
    }

    public void reveal(View v, int index) {
        if (!motionEnabled()) return;
        v.setAlpha(0f);
        v.setTranslationY(dp(8));
        v.animate().alpha(1f).translationY(0f)
                .setStartDelay(Math.min(index, 5) * 35L)
                .setDuration(280).setInterpolator(new PathInterpolator(0.2f, 0f, 0f, 1f)).start();
    }

    public ImageView image(Drawable drawable, int sizeDp, String contentDesc) {
        ImageView iv = new ImageView(activity);
        iv.setImageDrawable(drawable);
        iv.setScaleType(ImageView.ScaleType.FIT_CENTER);
        iv.setContentDescription(contentDesc);
        iv.setLayoutParams(new LinearLayout.LayoutParams(dp(sizeDp), dp(sizeDp)));
        return iv;
    }

    public Drawable appIcon(String pkg) {
        if (pkg != null) {
            try {
                ApplicationInfo info = activity.getPackageManager().getApplicationInfo(pkg, 0);
                return info.loadIcon(activity.getPackageManager());
            } catch (Exception ignored) {}
        }
        return activity.getPackageManager().getDefaultActivityIcon();
    }

    public String appName(String pkg) {
        if (pkg != null) {
            try {
                ApplicationInfo info = activity.getPackageManager().getApplicationInfo(pkg, 0);
                return info.loadLabel(activity.getPackageManager()).toString();
            } catch (Exception ignored) {}
        }
        return pkg != null ? pkg : "your app";
    }

    public LinearLayout brand(String title) {
        LinearLayout ll = new LinearLayout(activity);
        ll.setGravity(android.view.Gravity.CENTER_VERTICAL);
        // Use Google G logo if GMS is available, else skip icon
        try {
            Drawable logo = googleLogo();
            ll.addView(image(logo, 20, "Google"));
        } catch (Exception ignored) {}
        TextView tv = text(title, 14, true);
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-2, -2);
        lp.setMarginStart(dp(10));
        ll.addView(tv, lp);
        return ll;
    }

    public Drawable googleLogo() {
        try {
            Context gmsCtx = activity.createPackageContext("com.google.android.gms", 0);
            String[] names = {"googleg_standard_color_24_vd", "auth_ic_googleg", "google_g_logo"};
            for (String name : names) {
                int id = gmsCtx.getResources().getIdentifier(name, "drawable", "com.google.android.gms");
                if (id != 0) return gmsCtx.getResources().getDrawable(id, gmsCtx.getTheme());
            }
        } catch (Exception ignored) {}
        // Fallback: draw a simple colored G
        GradientDrawable d = new GradientDrawable();
        d.setColor(Color.parseColor("#4285F4"));
        d.setShape(GradientDrawable.OVAL);
        return d;
    }

    public void appHeading(LinearLayout parent, String pkg, String subtitle) {
        if (compact()) {
            space(parent, 6);
            LinearLayout row = new LinearLayout(activity);
            row.setGravity(android.view.Gravity.CENTER_VERTICAL);
            row.addView(image(appIcon(pkg), 36, appName(pkg)));
            LinearLayout col = column();
            col.addView(text("Continue to " + appName(pkg), 19, true));
            space(col, 4);
            TextView cap = caption(subtitle);
            cap.setTextSize(12);
            col.addView(cap);
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0, -2, 1f);
            lp.setMarginStart(dp(14));
            row.addView(col, lp);
            parent.addView(row);
            space(parent, 10);
        } else {
            space(parent, 24);
            LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(dp(56), dp(56));
            lp.gravity = android.view.Gravity.CENTER_HORIZONTAL;
            parent.addView(image(appIcon(pkg), 56, appName(pkg)), lp);
            space(parent, 16);
            TextView title = text("Continue to " + appName(pkg), 24, true);
            title.setGravity(android.view.Gravity.CENTER);
            parent.addView(title, new LinearLayout.LayoutParams(-1, -2));
            space(parent, 8);
            TextView cap = caption(subtitle);
            cap.setGravity(android.view.Gravity.CENTER);
            parent.addView(cap, new LinearLayout.LayoutParams(-1, -2));
            space(parent, 24);
        }
    }

    public View accountRow(String name, String email, boolean isAdd, Runnable onClick) {
        LinearLayout row = new LinearLayout(activity);
        row.setGravity(android.view.Gravity.CENTER_VERTICAL);
        row.setPadding(dp(14), dp(14), dp(14), dp(14));
        row.setMinimumHeight(dp(72));
        rowSurface(row);
        // Avatar / plus indicator
        String letter = isAdd ? "+" : name.substring(0, name.offsetByCodePoints(0, 1)).toUpperCase(Locale.getDefault());
        TextView letterTv = text(letter, 19, true);
        letterTv.setGravity(android.view.Gravity.CENTER);
        int letterColor = isAdd ? muted : (dark ? -3811073 : -13541984);
        letterTv.setTextColor(letterColor);
        int avatarBg = isAdd ? (dark ? 419430399 : -1051913) : (dark ? -14469552 : -1839620);
        letterTv.setBackground(rounded(avatarBg, 24));
        letterTv.setImportantForAccessibility(View.IMPORTANT_FOR_ACCESSIBILITY_NO);
        FrameLayout avatar = new FrameLayout(activity);
        int frameBg = isAdd ? (dark ? 419430399 : -1051913) : (dark ? -14469552 : -1839620);
        avatar.setBackground(rounded(frameBg, 24));
        avatar.setClipToOutline(true);
        avatar.addView(letterTv, new FrameLayout.LayoutParams(-1, -1));
        row.addView(avatar, new LinearLayout.LayoutParams(dp(42), dp(42)));
        LinearLayout info = column();
        TextView nameTv = text(name, 15, true);
        nameTv.setMaxLines(2);
        info.addView(nameTv);
        if (email != null && !email.isEmpty()) {
            space(info, 4);
            TextView emailTv = caption(email);
            emailTv.setMaxLines(2);
            info.addView(emailTv);
        }
        LinearLayout.LayoutParams infoLp = new LinearLayout.LayoutParams(0, -2, 1f);
        infoLp.setMarginStart(dp(13));
        row.addView(info, infoLp);
        TextView arrow = text("›", 24, false);
        arrow.setTextColor(muted);
        arrow.setImportantForAccessibility(View.IMPORTANT_FOR_ACCESSIBILITY_NO);
        row.addView(arrow);
        press(row, onClick);
        return row;
    }

    public void glassSurface(View view, float radius) {
        int color = dark ? -1946157056 : -1056964609;
        view.setBackground(rounded(color, radius));
    }

    public WavyProgressIndicator loading() {
        return new WavyProgressIndicator(activity, -14391318);
    }

    @Override
    public void close() {}

    public static String scopeLabel(String scope) {
        if ("openid".equals(scope)) return "Basic account information";
        if ("email".equals(scope) || scope.endsWith("userinfo.email")) return "Your email address";
        if ("profile".equals(scope) || scope.endsWith("userinfo.profile")) return "Your name and profile photo";
        if (scope.contains("games")) return "Your Google Play Games profile";
        return scope;
    }
}
