package com.enteangadi.app;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.os.Build;
import android.webkit.CookieManager;
import android.media.RingtoneManager;
import androidx.core.app.NotificationCompat;
import org.json.JSONArray;
import org.json.JSONObject;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

public class BackgroundNotificationService extends BroadcastReceiver {

    @Override
    public void onReceive(Context context, Intent intent) {
        final PendingResult pendingResult = goAsync();
        new Thread(new Runnable() {
            @Override
            public void run() {
                try {
                    pollUnreadMessages(context);
                } finally {
                    pendingResult.finish();
                }
            }
        }).start();
    }

    private void createNotificationChannel(Context context) {
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
            NotificationManager notificationManager = context.getSystemService(NotificationManager.class);
            if (notificationManager != null) {
                notificationManager.createNotificationChannel(channel);
            }
        }
    }

    private void showNotification(Context context, String senderName, String messageText, int unreadCount, int senderId, int productId) {
        createNotificationChannel(context);

        Intent intent = new Intent(context, MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        intent.putExtra("user_id", senderId);
        intent.putExtra("product_id", productId);
        PendingIntent pendingIntent = PendingIntent.getActivity(
            context,
            (int) System.currentTimeMillis(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT | (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M ? PendingIntent.FLAG_IMMUTABLE : 0)
        );

        NotificationCompat.Builder builder = new NotificationCompat.Builder(context, "new_messages_channel")
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle("Enteangadi - " + senderName)
            .setContentText(messageText)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION))
            .setDefaults(NotificationCompat.DEFAULT_ALL)
            .setNumber(unreadCount)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC);

        NotificationManager notificationManager = (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);
        if (notificationManager != null) {
            notificationManager.notify((int) System.currentTimeMillis(), builder.build());
        }
    }

    private void pollUnreadMessages(Context context) {
        try {
            SharedPreferences prefs = context.getSharedPreferences("EnteangadiPrefs", Context.MODE_PRIVATE);
            String serverUrl = prefs.getString("server_url", null);
            if (serverUrl == null || serverUrl.isEmpty()) {
                return;
            }

            String loggedInUserId = prefs.getString("logged_in_user_id", "");
            String apiUrl = serverUrl + (serverUrl.endsWith("/") ? "" : "/") + "user/api_unread_messages.php";
            if (!loggedInUserId.isEmpty()) {
                apiUrl += "?user_id=" + loggedInUserId;
            }

            URL url = new URL(apiUrl);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            String cookies = CookieManager.getInstance().getCookie(serverUrl);
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

                                int senderId = msg.optInt("sender_id", 0);
                                int productId = msg.optInt("product_id", 0);
                                showNotification(context, senderName, messageText, unreadCount, senderId, productId);
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
