# Android App Setup Guide - SilentMultiPanel

## Features
- **Splash Screen**: Launch logo with professional fade
- **Refresh Button**: Easy page reload for users
- **High Performance**: 120 FPS hardware acceleration enabled
- **File Support**: Full support for APK uploads/downloads within app
- **Responsive**: Automatic scaling for all screen sizes

## Setup Instructions

### 1. Project Structure
Ensure your Android Studio project has the following files in these locations:
- `MainActivity.java` -> `app/src/main/java/com/SilentMultiPanel/app/`
- `activity_main.xml` -> `app/src/main/res/layout/`
- `strings.xml` -> `app/src/main/res/values/`
- `splash_logo.jpg` -> `app/src/main/res/drawable/`

### 2. Permissions
Ensure these are in your `AndroidManifest.xml`:
```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
```

### 3. Build & Run
1. Open in Android Studio or AIDE
2. Build APK
3. Install and enjoy the smooth experience!

---
*Note: The app is hardcoded to connect to https://silentmultipanel.vippanel.in/*
