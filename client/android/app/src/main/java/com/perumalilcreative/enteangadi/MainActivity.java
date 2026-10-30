package com.perumalilcreative.enteangadi;

import android.animation.ObjectAnimator;
import android.animation.PropertyValuesHolder;
import android.animation.ValueAnimator;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.JavascriptInterface;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.RelativeLayout;
import android.widget.TextView;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.BridgeWebViewClient;

public class MainActivity extends BridgeActivity {
    private RelativeLayout loadingLayout;
    private TextView percentText;
    private TextView statusText;
    private ProgressBar progressBar;
    private ObjectAnimator breatheAnimator;
    private int displayedPct = 0;
    private boolean progressTickerActive = false;

    private final Runnable progressTicker = new Runnable() {
        @Override
        public void run() {
            if (loadingLayout == null || loadingLayout.getVisibility() != View.VISIBLE) {
                progressTickerActive = false;
                return;
            }
            int webProgress = 0;
            try {
                WebView wv = getBridge() != null ? getBridge().getWebView() : null;
                if (wv != null) webProgress = wv.getProgress();
            } catch (Exception ignored) {}

            int target = Math.max(webProgress, displayedPct < 85 ? displayedPct + 3 : displayedPct);
            if (target > 96 && webProgress < 100) target = 96;
            if (target > displayedPct) {
                displayedPct = target;
                updateLoaderPercentUi(displayedPct);
            }
            if (webProgress >= 100) {
                hideLoadingScreen();
                progressTickerActive = false;
                return;
            }
            loadingLayout.postDelayed(this, 65);
        }
    };

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(MicrophonePermissionPlugin.class);
        super.onCreate(savedInstanceState);

        // Apply system bar & display cutout insets so API 35/36 Edge-to-Edge never overlaps
        // the top status bar or bottom 3-button / gesture navigation bar
        try {
            WindowCompat.setDecorFitsSystemWindows(getWindow(), true);
            getWindow().setStatusBarColor(Color.WHITE);
            getWindow().setNavigationBarColor(Color.WHITE);
            WindowInsetsControllerCompat controller = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
            if (controller != null) {
                controller.setAppearanceLightStatusBars(true);
                controller.setAppearanceLightNavigationBars(true);
            }
            View contentView = findViewById(android.R.id.content);
            if (contentView != null) {
                contentView.setBackgroundColor(Color.WHITE);
                ViewCompat.setOnApplyWindowInsetsListener(contentView, (v, windowInsets) -> {
                    Insets bars = windowInsets.getInsets(
                        WindowInsetsCompat.Type.systemBars()
                        | WindowInsetsCompat.Type.displayCutout()
                        | WindowInsetsCompat.Type.ime()
                    );
                    v.setPadding(bars.left, bars.top, bars.right, bars.bottom);
                    return WindowInsetsCompat.CONSUMED;
                });
            }
        } catch (Exception e) {
            e.printStackTrace();
        }

        WebView webView = getBridge().getWebView();

        // Enable hardware compositing and full HTTP/DOM/ServiceWorker caching for maximum speed
        try {
            webView.setLayerType(View.LAYER_TYPE_HARDWARE, null);
            WebSettings ws = webView.getSettings();
            ws.setDomStorageEnabled(true);
            ws.setDatabaseEnabled(true);
            ws.setCacheMode(WebSettings.LOAD_DEFAULT);
            ws.setMediaPlaybackRequiresUserGesture(false);
        } catch (Exception e) {
            e.printStackTrace();
        }

        initBreathingLoaderOverlay();
        showLoadingScreen("Loading Enteangadi...");

        try {
            getBridge().setWebViewClient(new CustomWebViewClient(getBridge()));
        } catch (Exception e) {
            e.printStackTrace();
        }

        try {
            String serverUrl = getBridge().getServerUrl();
            getSharedPreferences("EnteangadiPrefs", MODE_PRIVATE)
                .edit()
                .putString("server_url", serverUrl)
                .apply();
        } catch (Exception e) {
            e.printStackTrace();
        }

        try {
            webView.addJavascriptInterface(new Object() {
                @JavascriptInterface
                public void setLoggedInUser(String userId) {
                    getSharedPreferences("EnteangadiPrefs", MODE_PRIVATE)
                        .edit()
                        .putString("logged_in_user_id", userId)
                        .apply();
                }
            }, "EnteangadiNativeHelper");
        } catch (Exception e) {
            e.printStackTrace();
        }

        try {
            SharedPreferences prefs = getSharedPreferences("EnteangadiPrefs", MODE_PRIVATE);
            BackgroundNotificationService.updateAlarm(
                this,
                prefs.getBoolean("bg_active", true),
                prefs.getLong("bg_interval", 30)
            );
        } catch (Exception e) {
            e.printStackTrace();
        }

        handleNotificationClick(getIntent(), true);
    }

    private int dp(int val) {
        return Math.round(val * getResources().getDisplayMetrics().density);
    }

    private void initBreathingLoaderOverlay() {
        try {
            loadingLayout = new RelativeLayout(this);
            loadingLayout.setBackgroundColor(Color.parseColor("#F0FDF4"));
            loadingLayout.setGravity(Gravity.CENTER);

            LinearLayout centerBox = new LinearLayout(this);
            centerBox.setOrientation(LinearLayout.VERTICAL);
            centerBox.setGravity(Gravity.CENTER);

            // Breathing emblem card with app icon
            LinearLayout emblemCard = new LinearLayout(this);
            emblemCard.setGravity(Gravity.CENTER);
            int cardSize = dp(104);
            LinearLayout.LayoutParams cardParams = new LinearLayout.LayoutParams(cardSize, cardSize);
            cardParams.bottomMargin = dp(18);
            emblemCard.setLayoutParams(cardParams);

            GradientDrawable cardBg = new GradientDrawable();
            cardBg.setColor(Color.WHITE);
            cardBg.setCornerRadius(dp(26));
            cardBg.setStroke(dp(2), Color.parseColor("#BBF7D0"));
            emblemCard.setBackground(cardBg);
            emblemCard.setElevation(dp(8));

            ImageView logoImg = new ImageView(this);
            logoImg.setImageResource(getApplicationInfo().icon);
            int iconSize = dp(68);
            logoImg.setLayoutParams(new LinearLayout.LayoutParams(iconSize, iconSize));
            emblemCard.addView(logoImg);
            centerBox.addView(emblemCard);

            breatheAnimator = ObjectAnimator.ofPropertyValuesHolder(
                emblemCard,
                PropertyValuesHolder.ofFloat(View.SCALE_X, 1.0f, 1.08f),
                PropertyValuesHolder.ofFloat(View.SCALE_Y, 1.0f, 1.08f)
            );
            breatheAnimator.setDuration(1050);
            breatheAnimator.setRepeatCount(ValueAnimator.INFINITE);
            breatheAnimator.setRepeatMode(ValueAnimator.REVERSE);

            // Counting percentage (0% -> 100%)
            percentText = new TextView(this);
            percentText.setText("0%");
            percentText.setTextColor(Color.parseColor("#166534"));
            percentText.setTextSize(26);
            percentText.setTypeface(Typeface.DEFAULT_BOLD);
            percentText.setGravity(Gravity.CENTER);
            centerBox.addView(percentText);

            // Horizontal progress bar
            progressBar = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
            progressBar.setMax(100);
            progressBar.setProgress(0);
            LinearLayout.LayoutParams barParams = new LinearLayout.LayoutParams(dp(190), dp(6));
            barParams.topMargin = dp(10);
            barParams.bottomMargin = dp(12);
            progressBar.setLayoutParams(barParams);
            centerBox.addView(progressBar);

            statusText = new TextView(this);
            statusText.setText("Loading Enteangadi...");
            statusText.setTextColor(Color.parseColor("#475569"));
            statusText.setTextSize(13);
            statusText.setGravity(Gravity.CENTER);
            centerBox.addView(statusText);

            loadingLayout.addView(centerBox, new RelativeLayout.LayoutParams(
                ViewGroup.LayoutParams.WRAP_CONTENT,
                ViewGroup.LayoutParams.WRAP_CONTENT
            ));

            addContentView(loadingLayout, new ViewGroup.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            ));
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void updateLoaderPercentUi(int pct) {
        if (percentText != null) percentText.setText(pct + "%");
        if (progressBar != null) progressBar.setProgress(pct);
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleNotificationClick(intent, false);
    }

    private void handleNotificationClick(Intent intent, boolean isColdStart) {
        if (intent == null || !intent.hasExtra("user_id") || !intent.hasExtra("product_id")) return;
        final int userId = intent.getIntExtra("user_id", 0);
        final int productId = intent.getIntExtra("product_id", 0);
        if (userId <= 0 || productId <= 0) return;

        String serverUrl = getSharedPreferences("EnteangadiPrefs", MODE_PRIVATE).getString("server_url", null);
        if (serverUrl == null || serverUrl.isEmpty()) {
            try { serverUrl = getBridge().getServerUrl(); } catch (Exception ignored) {}
        }
        if (serverUrl != null && !serverUrl.isEmpty()) {
            final String chatUrl = serverUrl + (serverUrl.endsWith("/") ? "" : "/") +
                "user/chat.php?user_id=" + userId + "&product_id=" + productId;
            getBridge().getWebView().postDelayed(() -> {
                try { getBridge().getWebView().loadUrl(chatUrl); } catch (Exception ignored) {}
            }, isColdStart ? 450 : 60);
        }
    }

    private void showLoadingScreen(final String message) {
        runOnUiThread(() -> {
            if (loadingLayout == null) return;
            displayedPct = 0;
            updateLoaderPercentUi(0);
            if (statusText != null && message != null) statusText.setText(message);
            loadingLayout.setAlpha(1f);
            loadingLayout.setVisibility(View.VISIBLE);
            if (breatheAnimator != null && !breatheAnimator.isStarted()) breatheAnimator.start();
            if (!progressTickerActive) {
                progressTickerActive = true;
                loadingLayout.post(progressTicker);
            }
        });
    }

    private void hideLoadingScreen() {
        runOnUiThread(() -> {
            if (loadingLayout == null || loadingLayout.getVisibility() != View.VISIBLE) return;
            displayedPct = 100;
            updateLoaderPercentUi(100);
            loadingLayout.animate()
                .alpha(0f)
                .setDuration(180)
                .withEndAction(() -> {
                    loadingLayout.setVisibility(View.GONE);
                    if (breatheAnimator != null) breatheAnimator.cancel();
                })
                .start();
        });
    }

    private class CustomWebViewClient extends BridgeWebViewClient {
        private boolean hasError = false;
        private boolean isReloading = false;

        public CustomWebViewClient(Bridge bridge) {
            super(bridge);
        }

        @Override
        public void onPageStarted(WebView view, String url, Bitmap favicon) {
            super.onPageStarted(view, url, favicon);
            hasError = false;
        }

        @Override
        public void onPageCommitVisible(WebView view, String url) {
            super.onPageCommitVisible(view, url);
            // Hand off seamlessly to web UI as soon as first visual frame commits
            if (!hasError) hideLoadingScreen();
        }

        @Override
        public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
            super.onReceivedError(view, errorCode, description, failingUrl);
            hasError = true;
            showLoadingScreen("Reconnecting to Enteangadi...");
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            super.onReceivedError(view, request, error);
            if (request.isForMainFrame()) {
                hasError = true;
                showLoadingScreen("Reconnecting to Enteangadi...");
            }
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);
            if (!hasError) {
                hideLoadingScreen();
                isReloading = false;
            } else if (!isReloading) {
                isReloading = true;
                view.postDelayed(() -> {
                    view.reload();
                    isReloading = false;
                }, 2500);
            }
        }
    }
}
