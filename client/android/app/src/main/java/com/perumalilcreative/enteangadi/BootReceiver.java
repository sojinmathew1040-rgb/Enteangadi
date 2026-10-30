package com.perumalilcreative.enteangadi;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.app.AlarmManager;
import android.app.PendingIntent;
import android.os.SystemClock;
import android.os.Build;

public class BootReceiver extends BroadcastReceiver {
    @Override
    public void onReceive(Context context, Intent intent) {
        if (Intent.ACTION_BOOT_COMPLETED.equals(intent.getAction())) {
            try {
                android.content.SharedPreferences prefs = context.getSharedPreferences("EnteangadiPrefs", Context.MODE_PRIVATE);
                boolean bgActive = prefs.getBoolean("bg_active", true);
                long bgInterval = prefs.getLong("bg_interval", 30);
                
                BackgroundNotificationService.updateAlarm(context, bgActive, bgInterval);
            } catch (Exception e) {
                e.printStackTrace();
            }
        }
    }
}
