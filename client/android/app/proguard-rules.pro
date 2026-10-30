# Add project specific ProGuard rules here.
# You can control the set of applied configuration files using the
# proguardFiles setting in build.gradle.

# Preserve file names and line numbers for Play Console deobfuscation stack traces
-keepattributes SourceFile,LineNumberTable
-keepattributes *Annotation*,Signature,InnerClasses,EnclosingMethod

# Keep WebView JavascriptInterface methods
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# Keep Enteangadi app classes
-keep class com.perumalilcreative.enteangadi.** { *; }

# Keep Capacitor Core, Bridge, and Plugins (loaded via reflection)
-keep class com.getcapacitor.** { *; }
-keep class * extends com.getcapacitor.Plugin { *; }
-keep class * extends com.getcapacitor.BridgeActivity { *; }
-keepclasseswithmembers class * {
    @com.getcapacitor.annotation.CapacitorPlugin <methods>;
}
-keepclasseswithmembers class * {
    @com.getcapacitor.PluginMethod <methods>;
}
-keepclasseswithmembers class * {
    @com.getcapacitor.NativePlugin <methods>;
}

# Keep Capacitor official plugin packages
-keep class com.capacitorjs.plugins.** { *; }
-keep class org.apache.cordova.** { *; }

-dontwarn com.google.android.gms.**
-dontwarn org.apache.cordova.**
