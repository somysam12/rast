package com.mundo.gms;

import android.animation.ValueAnimator;
import android.content.Context;
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.Path;
import android.view.View;
import android.view.animation.LinearInterpolator;

public final class WavyProgressIndicator extends View {
    private final Paint paint;
    private final ValueAnimator animator;
    private float phase = 0f;

    public WavyProgressIndicator(Context ctx, int color) {
        super(ctx);
        paint = new Paint(Paint.ANTI_ALIAS_FLAG);
        paint.setColor(color);
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(3f * ctx.getResources().getDisplayMetrics().density);
        paint.setStrokeCap(Paint.Cap.ROUND);
        animator = ValueAnimator.ofFloat(0f, (float) (Math.PI * 2));
        animator.setDuration(1200);
        animator.setRepeatCount(ValueAnimator.INFINITE);
        animator.setInterpolator(new LinearInterpolator());
        animator.addUpdateListener(a -> {
            phase = (float) a.getAnimatedValue();
            invalidate();
        });
        animator.start();
    }

    @Override
    protected void onDraw(Canvas canvas) {
        int w = getWidth(), h = getHeight();
        if (w == 0 || h == 0) return;
        Path path = new Path();
        float amplitude = h * 0.3f;
        float freq = (float) (Math.PI * 2 / (w * 0.5f));
        path.moveTo(0, h * 0.5f);
        for (int x = 0; x <= w; x++) {
            float y = h * 0.5f + amplitude * (float) Math.sin(freq * x + phase);
            if (x == 0) path.moveTo(x, y);
            else path.lineTo(x, y);
        }
        canvas.drawPath(path, paint);
    }

    @Override
    protected void onDetachedFromWindow() {
        super.onDetachedFromWindow();
        animator.cancel();
    }

    @Override
    protected void onAttachedToWindow() {
        super.onAttachedToWindow();
        if (!animator.isRunning()) animator.start();
    }
}
