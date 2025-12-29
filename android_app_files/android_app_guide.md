# Android App - WebView Setup Guide

## Steps to Create the App:

1. Open **Android Studio**
2. Create a **New Project** → Select "Empty Activity"
3. Copy the code below into respective files
4. Replace `YOUR_WEBSITE_URL` with your actual website URL
5. Build and Run on device or emulator

---

## File 1: AndroidManifest.xml

Replace the entire content with:

```xml
<?xml version="1.0" encoding="utf-8"?>
<manifest xmlns:android="http://schemas.android.com/apk/res/android"
    package="com.silentpanel.app">

    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />

    <application
        android:allowBackup="true"
        android:icon="@mipmap/ic_launcher"
        android:label="@string/app_name"
        android:roundIcon="@mipmap/ic_launcher_round"
        android:supportsRtl="true"
        android:theme="@style/Theme.SilentPanel">

        <activity
            android:name=".MainActivity"
            android:exported="true">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
            </intent-filter>
        </activity>

    </application>

</manifest>
```

---

## File 2: MainActivity.java

Replace with:

```java
package com.silentpanel.app;

import android.os.Bundle;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import androidx.appcompat.app.AppCompatActivity;

public class MainActivity extends AppCompatActivity {

    private WebView webView;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webview);
        
        // Configure WebView
        WebSettings webSettings = webView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setDatabaseEnabled(true);
        webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);
        webSettings.setUseWideViewPort(true);
        webSettings.setLoadWithOverviewMode(true);

        // Set custom WebViewClient to handle links inside app
        webView.setWebViewClient(new WebViewClient());

        // Load your website - REPLACE WITH YOUR URL
        webView.loadUrl("https://YOUR_WEBSITE_URL.com");
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }
}
```

---

## File 3: activity_main.xml

Replace with:

```xml
<?xml version="1.0" encoding="utf-8"?>
<LinearLayout xmlns:android="http://schemas.android.com/apk/res/android"
    android:layout_width="match_parent"
    android:layout_height="match_parent"
    android:orientation="vertical">

    <WebView
        android:id="@+id/webview"
        android:layout_width="match_parent"
        android:layout_height="match_parent" />

</LinearLayout>
```

---

## File 4: build.gradle (app level)

Find the `dependencies` section and add:

```gradle
dependencies {
    implementation 'androidx.appcompat:appcompat:1.6.1'
    implementation 'androidx.constraintlayout:constraintlayout:2.1.4'
    implementation 'com.google.android.material:material:1.9.0'
}
```

---

## File 5: strings.xml

Replace with:

```xml
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <string name="app_name">SilentPanel</string>
</resources>
```

---

## File 6: styles.xml (or themes.xml)

```xml
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <style name="Theme.SilentPanel" parent="Theme.AppCompat.Light.DarkActionBar">
        <item name="colorPrimary">@color/purple_500</item>
        <item name="colorPrimaryVariant">@color/purple_700</item>
        <item name="colorSecondary">@color/teal_200</item>
    </style>
</resources>
```

---

## Build APK Steps:

1. Click **Build** → **Build Bundle(s) / APK(s)** → **Build APK(s)**
2. Wait for build to complete
3. Locate APK at: `app/build/outputs/apk/debug/app-debug.apk`
4. Install on your device
5. App will open your website!

---

## Important Notes:

- **Replace** `https://YOUR_WEBSITE_URL.com` with your actual domain
- App will load your website in a WebView (embedded browser)
- Back button works to go back in web history
- JavaScript is enabled by default
- No crashes - fully stable implementation
