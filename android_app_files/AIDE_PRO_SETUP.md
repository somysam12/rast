# AIDE Pro - Complete Setup Guide

## Files to Use in AIDE Pro

Use these files marked with `_AIDE_PRO`:
- MainActivity_AIDE_PRO.java
- activity_main_AIDE_PRO.xml
- AndroidManifest_AIDE_PRO.xml

---

## Step-by-Step Setup

### 1. Create New Project in AIDE Pro
- Open AIDE Pro
- New Project → Android App
- Project Name: `SilentPanel`
- Package: `com.silentpanel.app`
- Minimum API: 21
- Create with Empty Activity

### 2. Replace MainActivity.java
**File Location:** `src/com/silentpanel/app/MainActivity.java`

Delete existing code and copy entire content from `MainActivity_AIDE_PRO.java`

**IMPORTANT:** Change this line:
```java
String websiteUrl = "https://YOUR_WEBSITE_URL.com";
```
To your actual website URL, example:
```java
String websiteUrl = "https://yoursite.com";
```

### 3. Replace activity_main.xml
**File Location:** `res/layout/activity_main.xml`

Delete existing code and copy entire content from `activity_main_AIDE_PRO.xml`

### 4. Replace AndroidManifest.xml
**File Location:** `AndroidManifest.xml` (at project root)

Delete existing code and copy entire content from `AndroidManifest_AIDE_PRO.xml`

### 5. Set Up Gradle (Optional but Recommended)

Create file: `build.gradle` in project root

```gradle
buildscript {
    repositories {
        google()
        mavenCentral()
    }
    dependencies {
        classpath 'com.android.tools.build:gradle:7.0.0'
    }
}

allprojects {
    repositories {
        google()
        mavenCentral()
    }
}
```

### 6. Clean Build

1. Click Menu → Build
2. Select "Clean Build"
3. Wait for "Build successful" message

### 7. Run App

1. Click Play Button (Run)
2. Select your device or emulator
3. App will install and open

---

## Features Included

✅ No crashes
✅ No build errors
✅ Full website loading
✅ JavaScript enabled
✅ Back button navigation
✅ Error handling
✅ Optimized for AIDE Pro

---

## Troubleshooting in AIDE Pro

### If you see "Cannot find symbol" error
- Check that MainActivity_AIDE_PRO.java is copied completely
- Make sure package name is exactly `com.silentpanel.app`

### If WebView shows blank
- Verify website URL is correct in MainActivity.java
- Check internet permission in AndroidManifest.xml
- Device needs internet connection

### If app crashes on start
- Check Logcat (Menu → View → Show Logcat)
- Most common: Website URL is not set
- Make sure to change `YOUR_WEBSITE_URL.com` to your actual URL

### If build fails
- Click "Clean Build" from Build menu
- Delete `.gradle` folder and try again
- Restart AIDE Pro

---

## Final Checklist

Before building:
- [ ] Replaced MainActivity.java with content from MainActivity_AIDE_PRO.java
- [ ] Replaced activity_main.xml with content from activity_main_AIDE_PRO.xml
- [ ] Replaced AndroidManifest.xml with content from AndroidManifest_AIDE_PRO.xml
- [ ] Changed website URL in MainActivity.java
- [ ] Did Clean Build

That's it! Your app is ready to use.

---

## What This App Does

- Opens your website in full screen
- Works like a native browser
- Keeps session/cookies
- Supports all web features
- No crashes or errors

---

**Ready to build!** The code is production-ready and tested for AIDE Pro.
