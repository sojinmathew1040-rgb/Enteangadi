package com.enteangadi.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Build;
import android.os.IBinder;
import android.webkit.CookieManager;
import androidx.core.app.NotificationCompat;
import org.json.JSONArray;
import org.json.JSONObject;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class BackgroundNotificationService extends Service {
    private ScheduledExecutorService scheduler;

    @Override
    public void onCreate() {
        super.onCreate();
        scheduler = Executors.newSingleThreadScheduledExecutor();
        // Poll every 3 seconds for new messages
        scheduler.scheduleAtFixedRate(this::pollUnreadMessages, 2, 3, TimeUnit.SECONDS);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        createForegroundNotificationChannel();

        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, "foreground_service_channel")
            .setSmallIcon(android.R.drawable.stat_notify_sync)
            .setContentTitle("Enteangadi Background Checker")
            .setContentText("Checking for new messages...")
            .setPriority(NotificationCompat.PRIORITY_MIN)
            .setCategory(NotificationCompat.CATEGORY_SERVICE);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            startForeground(2002, builder.build(), android.content.pm.ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC);
        } else {
            startForeground(2002, builder.build());
        }

        return START_STICKY;
    }

    private void createForegroundNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            CharSequence name = "Background Service";
            String description = "Ensures message checks run continuously";
            int importance = NotificationManager.IMPORTANCE_MIN;
            NotificationChannel channel = new NotificationChannel("foreground_service_channel", name, importance);
            channel.setDescription(description);
            channel.setShowBadge(false);
            NotificationManager notificationManager = getSystemService(NotificationManager.class);
            if (notificationManager != null) {
                notificationManager.createNotificationChannel(channel);
            }
        }
    }

    @Override
    public void onDestroy() {
        if (scheduler != null) {
            scheduler.shutdown();
        }
        super.onDestroy();
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            CharSequence name = "New Messages";
            String description = "Notifications for new chat messages";
            int importance = NotificationManager.IMPORTANCE_HIGH;
            NotificationChannel channel = new NotificationChannel("new_messages_channel", name, importance);
            channel.setDescription(description);
            channel.setShowBadge(true);
            channel.enableVibration(true);
            channel.setVibrationPattern(new long[]{100, 200, 300, 400, 500, 400, 300, 200, 400});
            channel.enableLights(true);
            NotificationManager notificationManager = getSystemService(NotificationManager.class);
            if (notificationManager != null) {
                notificationManager.createNotificationChannel(channel);
            }
        }
    }

    private void showNotification(String senderName, String messageText, int unreadCount) {
        createNotificationChannel();

        Intent intent = new Intent(this, MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        PendingIntent pendingIntent = PendingIntent.getActivity(
            this,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT | (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M ? PendingIntent.FLAG_IMMUTABLE : 0)
        );

        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, "new_messages_channel")
            .setSmallIcon(android.R.drawable.stat_notify_chat)
            .setContentTitle("Enteangadi - " + senderName)
            .setContentText(messageText)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setDefaults(NotificationCompat.DEFAULT_ALL)
            .setNumber(unreadCount);

        NotificationManager notificationManager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (notificationManager != null) {
            notificationManager.notify((int) System.currentTimeMillis(), builder.build());
        }
    }

    private void pollUnreadMessages() {
        try {
            SharedPreferences prefs = getSharedPreferences("EnteangadiPrefs", MODE_PRIVATE);
            String serverUrl = prefs.getString("server_url", null);
            if (serverUrl == null || serverUrl.isEmpty()) {
                return;
            }

            CookieManager cookieManager = CookieManager.getInstance();
            String cookies = cookieManager.getCookie(serverUrl);

            URL url = new URL(serverUrl + (serverUrl.endsWith("/") ? "" : "/") + "user/api_unread_messages.php");
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            if (cookies != null) {
                conn.setRequestProperty("Cookie", cookies);
            }
            conn.setConnectTimeout(5000);
            conn.setReadTimeout(5000);

            if (conn.getResponseCode() == 200) {
                BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                StringBuilder response = new StringBuilder();
                String inputLine;
                while ((inputLine = in.readLine()) != null) {
                    response.append(inputLine);
                }
                in.close();

                JSONObject data = new JSONObject(response.toString());
                if (data.optBoolean("success", false)) {
                    JSONArray messages = data.optJSONArray("messages");
                    if (messages != null && messages.length() > 0) {
                        int lastSeenId = prefs.getInt("last_seen_message_id", 0);
                        int maxId = lastSeenId;
                        boolean shouldSave = false;
                        int unreadCount = messages.length();

                        // Display oldest to newest
                        for (int i = messages.length() - 1; i >= 0; i--) {
                            JSONObject msg = messages.getJSONObject(i);
                            int msgId = msg.optInt("id", 0);
                            if (msgId > lastSeenId) {
                                String senderName = msg.optString("sender_name", "User");
                                String messageText = msg.optString("message_text", "");

                                if (messageText.startsWith("[AUDIO]:")) {
                                    messageText = "🎙️ Voice note";
                                } else if (messageText.startsWith("[IMAGE]:")) {
                                    messageText = "📷 Shared photo";
                                }

                                showNotification(senderName, messageText, unreadCount);
                                if (msgId > maxId) {
                                    maxId = msgId;
                                }
                                shouldSave = true;
                            }
                        }

                        if (shouldSave) {
                            prefs.edit().putInt("last_seen_message_id", maxId).apply();
                        }
                    }
                }
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}
