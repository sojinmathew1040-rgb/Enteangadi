package com.enteangadi.app;

import android.Manifest;
import android.media.MediaRecorder;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;
import com.getcapacitor.PermissionState;
import com.getcapacitor.JSObject;
import java.io.File;
import java.io.FileInputStream;

@CapacitorPlugin(
    name = "MicrophonePermission",
    permissions = {
        @Permission(
            alias = "microphone",
            strings = { Manifest.permission.RECORD_AUDIO }
        )
    }
)
public class MicrophonePermissionPlugin extends Plugin {

    private MediaRecorder recorder = null;
    private File audioFile = null;

    @PluginMethod
    public void checkPermission(PluginCall call) {
        if (getPermissionState("microphone") != PermissionState.GRANTED) {
            requestPermissionForAlias("microphone", call, "microphoneCallback");
        } else {
            JSObject ret = new JSObject();
            ret.put("status", "granted");
            call.resolve(ret);
        }
    }

    @PermissionCallback
    private void microphoneCallback(PluginCall call) {
        if (getPermissionState("microphone") == PermissionState.GRANTED) {
            JSObject ret = new JSObject();
            ret.put("status", "granted");
            call.resolve(ret);
        } else {
            call.reject("Microphone permission not granted");
        }
    }

    @PluginMethod
    public void startRecording(PluginCall call) {
        if (getPermissionState("microphone") != PermissionState.GRANTED) {
            call.reject("Microphone permission not granted");
            return;
        }

        try {
            if (recorder != null) {
                try {
                    recorder.stop();
                } catch (Exception ignored) {}
                recorder.release();
                recorder = null;
            }

            audioFile = File.createTempFile("voice_note_", ".m4a", getContext().getCacheDir());

            if (android.os.Build.VERSION.SDK_INT >= 29) {
                recorder = new MediaRecorder(getContext());
            } else {
                recorder = new MediaRecorder();
            }

            recorder.setAudioSource(MediaRecorder.AudioSource.MIC);
            recorder.setOutputFormat(MediaRecorder.OutputFormat.MPEG_4);
            recorder.setAudioEncoder(MediaRecorder.AudioEncoder.AAC);
            recorder.setAudioSamplingRate(44100);
            recorder.setAudioEncodingBitRate(96000);
            recorder.setOutputFile(audioFile.getAbsolutePath());

            recorder.prepare();
            recorder.start();

            JSObject ret = new JSObject();
            ret.put("status", "recording");
            call.resolve(ret);
        } catch (Exception e) {
            call.reject("Failed to start recording: " + e.getMessage(), e);
        }
    }

    @PluginMethod
    public void stopRecording(PluginCall call) {
        try {
            if (recorder != null) {
                try {
                    recorder.stop();
                } catch (RuntimeException stopException) {
                    recorder.release();
                    recorder = null;
                    if (audioFile != null) audioFile.delete();
                    call.reject("Recording too short or failed");
                    return;
                }
                recorder.release();
                recorder = null;

                if (audioFile != null && audioFile.exists()) {
                    byte[] bytes = new byte[(int) audioFile.length()];
                    FileInputStream fis = new FileInputStream(audioFile);
                    int read = fis.read(bytes);
                    fis.close();

                    String base64Audio = android.util.Base64.encodeToString(bytes, android.util.Base64.NO_WRAP);
                    
                    // Cleanup
                    audioFile.delete();

                    JSObject ret = new JSObject();
                    ret.put("format", "audio/mp4");
                    ret.put("base64", base64Audio);
                    call.resolve(ret);
                } else {
                    call.reject("Audio file not found");
                }
            } else {
                call.reject("Recorder not initialized or not recording");
            }
        } catch (Exception e) {
            call.reject("Failed to stop recording: " + e.getMessage(), e);
        }
    }
}
