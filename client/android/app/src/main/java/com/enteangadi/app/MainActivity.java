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

        // Start background notification polling service
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
    }
}
