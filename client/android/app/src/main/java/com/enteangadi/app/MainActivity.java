package com.enteangadi.app;

import android.content.Intent;
import android.os.Build;
import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(MicrophonePermissionPlugin.class);
        super.onCreate(savedInstanceState);

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

        // Start optimized background notification polling service
        try {
            Intent serviceIntent = new Intent(this, BackgroundNotificationService.class);
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                startForegroundService(serviceIntent);
            } else {
                startService(serviceIntent);
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
}
