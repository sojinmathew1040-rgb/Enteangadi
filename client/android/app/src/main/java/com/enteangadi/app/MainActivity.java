package com.enteangadi.app;

import android.content.Intent;
import android.os.Build;
import android.os.Bundle;
import android.app.AlarmManager;
import android.app.PendingIntent;
import android.content.Context;
import android.os.SystemClock;
import com.getcapacitor.BridgeActivity;
import android.webkit.WebView;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceError;
import android.view.View;
import android.widget.RelativeLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.graphics.Color;

public class MainActivity extends BridgeActivity {
    private RelativeLayout loadingLayout;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(MicrophonePermissionPlugin.class);
        super.onCreate(savedInstanceState);

        // Initialize fullscreen loading screen layout dynamically
        try {
            loadingLayout = new RelativeLayout(this);
            loadingLayout.setBackgroundColor(Color.parseColor("#ffffff"));
            loadingLayout.setGravity(android.view.Gravity.CENTER);
            loadingLayout.setVisibility(View.GONE);

            ProgressBar spinner = new ProgressBar(this);
            spinner.setId(View.generateViewId());
            spinner.setIndeterminate(true);
            RelativeLayout.LayoutParams spinnerParams = new RelativeLayout.LayoutParams(
                RelativeLayout.LayoutParams.WRAP_CONTENT,
                RelativeLayout.LayoutParams.WRAP_CONTENT
            );
            spinnerParams.addRule(RelativeLayout.CENTER_IN_PARENT);
            spinner.setLayoutParams(spinnerParams);
            loadingLayout.addView(spinner);

            TextView textView = new TextView(this);
            textView.setText("Connecting to Enteangadi...");
            textView.setTextColor(Color.parseColor("#2E7D32"));
            textView.setTextSize(16);
            textView.setGravity(android.view.Gravity.CENTER);
            RelativeLayout.LayoutParams textParams = new RelativeLayout.LayoutParams(
                RelativeLayout.LayoutParams.WRAP_CONTENT,
                RelativeLayout.LayoutParams.WRAP_CONTENT
            );
            textParams.addRule(RelativeLayout.BELOW, spinner.getId());
            textParams.topMargin = 40;
            textParams.addRule(RelativeLayout.CENTER_HORIZONTAL);
            textView.setLayoutParams(textParams);
            loadingLayout.addView(textView);

            addContentView(loadingLayout, new android.view.ViewGroup.LayoutParams(
                android.view.ViewGroup.LayoutParams.MATCH_PARENT,
                android.view.ViewGroup.LayoutParams.MATCH_PARENT
            ));
        } catch (Exception e) {
            e.printStackTrace();
        }

        // Set custom web view client to handle error paths and reconnect loader
        try {
            getBridge().setWebViewClient(new CustomWebViewClient(getBridge()));
        } catch (Exception e) {
            e.printStackTrace();
        }

        // Save server URL from Capacitor config/bridge to SharedPreferences for the background service
        try {
            String serverUrl = getBridge().getServerUrl();
            getSharedPreferences("EnteangadiPrefs", MODE_PRIVATE)
                .edit()
                .putString("server_url", serverUrl)
                .apply();
        } catch (Exception e) {
            e.printStackTrace();
        }

        // Inject custom native bridge helper to store user ID
        try {
            getBridge().getWebView().addJavascriptInterface(new Object() {
                @android.webkit.JavascriptInterface
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

        // Start optimized background notification polling alarm
        try {
            Intent alarmIntent = new Intent(this, BackgroundNotificationService.class);
            PendingIntent pendingIntent = PendingIntent.getBroadcast(
                this, 
                0, 
                alarmIntent, 
                PendingIntent.FLAG_UPDATE_CURRENT | (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M ? PendingIntent.FLAG_IMMUTABLE : 0)
            );
            AlarmManager alarmManager = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
            if (alarmManager != null) {
                // Poll every 30 seconds
                alarmManager.setRepeating(
                    AlarmManager.ELAPSED_REALTIME_WAKEUP,
                    SystemClock.elapsedRealtime() + 5000,
                    30000,
                    pendingIntent
                );
            }
        } catch (Exception e) {
            e.printStackTrace();
        }

        // Handle notification click on cold start
        handleNotificationClick(getIntent(), true);
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleNotificationClick(intent, false);
    }

    private void handleNotificationClick(Intent intent, boolean isColdStart) {
        if (intent != null && intent.hasExtra("user_id") && intent.hasExtra("product_id")) {
            final int userId = intent.getIntExtra("user_id", 0);
            final int productId = intent.getIntExtra("product_id", 0);
            if (userId > 0 && productId > 0) {
                String serverUrl = getSharedPreferences("EnteangadiPrefs", MODE_PRIVATE).getString("server_url", null);
                if (serverUrl == null || serverUrl.isEmpty()) {
                    try {
                        serverUrl = getBridge().getServerUrl();
                    } catch (Exception e) {
                        e.printStackTrace();
                    }
                }
                if (serverUrl != null && !serverUrl.isEmpty()) {
                    final String chatUrl = serverUrl + (serverUrl.endsWith("/") ? "" : "/") + "user/chat.php?user_id=" + userId + "&product_id=" + productId;
                    
                    long delay = isColdStart ? 1500 : 100;
                    getBridge().getWebView().postDelayed(new Runnable() {
                        @Override
                        public void run() {
                            try {
                                getBridge().getWebView().loadUrl(chatUrl);
                            } catch (Exception e) {
                                e.printStackTrace();
                            }
                        }
                    }, delay);
                }
            }
        }
    }

    private void showLoadingScreen() {
        runOnUiThread(new Runnable() {
            @Override
            public void run() {
                if (loadingLayout != null) {
                    loadingLayout.setVisibility(View.VISIBLE);
                }
            }
        });
    }

    private void hideLoadingScreen() {
        runOnUiThread(new Runnable() {
            @Override
            public void run() {
                if (loadingLayout != null) {
                    loadingLayout.setVisibility(View.GONE);
                }
            }
        });
    }

    private class CustomWebViewClient extends com.getcapacitor.BridgeWebViewClient {
        private boolean hasError = false;
        private boolean isReloading = false;

        public CustomWebViewClient(com.getcapacitor.Bridge bridge) {
            super(bridge);
        }

        @Override
        public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
            super.onPageStarted(view, url, favicon);
            hasError = false;
        }

        @Override
        public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
            super.onReceivedError(view, errorCode, description, failingUrl);
            hasError = true;
            showLoadingScreen();
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            super.onReceivedError(view, request, error);
            if (request.isForMainFrame()) {
                hasError = true;
                showLoadingScreen();
            }
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);
            if (!hasError) {
                hideLoadingScreen();
                isReloading = false;
            } else {
                if (!isReloading) {
                    isReloading = true;
                    view.postDelayed(new Runnable() {
                        @Override
                        public void run() {
                            view.reload();
                            isReloading = false;
                        }
                    }, 3000);
                }
            }
        }
    }
}
