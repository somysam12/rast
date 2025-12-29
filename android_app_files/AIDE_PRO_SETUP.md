# AIDE Pro - Complete Setup Guide

## Files to Use in AIDE Pro

Use these files marked with `_AIDE_PRO`:
- MainActivity_AIDE_PRO.java
- activity_main_AIDE_PRO.xml
- AndroidManifest_AIDE_PRO.xml
- strings_AIDE_PRO.xml

---

## Step-by-Step Setup

### 1. Create New Project in AIDE Pro
- Open AIDE Pro
- New Project → New Android App (Empty Project)
- Project Name: `SilentPanel`
- Package: `com.silentpanel.app`
- Minimum API: 21
- Create

### 2. Replace MainActivity.java

**IMPORTANT: AIDE Pro File Locations**

Your MainActivity.java might be in one of these locations in AIDE Pro:
- `app/src/java/MainActivity.java` ← Most common in AIDE Pro
- `app/src/com/silentpanel/app/MainActivity.java` ← Also possible

**How to find it:**
1. In AIDE Pro, open file tree
2. Look under `app` → `src`
3. Find `MainActivity.java`

**How to replace:**
1. Open `MainActivity_AIDE_PRO.java` from this folder
2. Select ALL code (Ctrl+A)
3. Copy (Ctrl+C)
4. In AIDE Pro, open MainActivity.java (in your app/src location)
5. Delete all existing code
6. Paste new code (Ctrl+V)

### 3. Replace activity_main.xml

**File Location in AIDE Pro:** `res/layout/activity_main.xml`

**How to replace:**
1. Open `activity_main_AIDE_PRO.xml` from this folder
2. Copy ALL code
3. In AIDE Pro, open `res/layout/activity_main.xml`
4. Delete all existing code
5. Paste new code

### 4. Replace AndroidManifest.xml

**File Location in AIDE Pro:** `AndroidManifest.xml` (at project root)

**How to replace:**
1. Open `AndroidManifest_AIDE_PRO.xml` from this folder
2. Copy ALL code
3. In AIDE Pro, open `AndroidManifest.xml`
4. Delete all existing code
5. Paste new code

### 5. Replace strings.xml

**File Location in AIDE Pro:** `res/values/strings.xml`

**How to replace:**
1. Open `strings_AIDE_PRO.xml` from this folder
2. Copy ALL code
3. In AIDE Pro, open `res/values/strings.xml`
4. Delete all existing code
5. Paste new code

### 6. Update Website URL

In **MainActivity.java**, find this line (around line 37):
```java
String websiteUrl = "https://YOUR_WEBSITE_URL.com";
```

Replace with your actual website:
```java
String websiteUrl = "https://yoursite.com";
```

### 7. Build

1. Click Menu → Build
2. Select "Clean Build"
3. Wait for "Build successful" message

### 8. Run App

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

### If you see "cannot find symbol" error
- Check that MainActivity_AIDE_PRO.java code was copied completely
- Make sure package name is exactly `com.silentpanel.app`
- Clean Build and try again

### If WebView shows blank/white screen
- Verify website URL is correct in MainActivity.java
- Check internet permission is in AndroidManifest.xml
- Device needs internet connection

### If app crashes on start
- Open Logcat (Menu → Show Logcat)
- Check error message
- Most common: Website URL not changed from `YOUR_WEBSITE_URL.com`
- Change it to your actual URL
- Rebuild

### If build fails
- Click "Clean Build" from Build menu
- Delete any temporary build files if available
- Restart AIDE Pro if needed

---

## File Locations Summary

```
Your AIDE Pro Project Structure:
├── AndroidManifest.xml (paste here)
├── app/
│   └── src/
│       └── java/
│           └── MainActivity.java (paste here)
└── res/
    ├── layout/
    │   └── activity_main.xml (paste here)
    └── values/
        └── strings.xml (paste here)
```

---

## Final Checklist

Before building:
- [ ] Created New Android App project
- [ ] Package name: `com.silentpanel.app`
- [ ] Replaced MainActivity.java with code from MainActivity_AIDE_PRO.java
- [ ] Replaced activity_main.xml with code from activity_main_AIDE_PRO.xml
- [ ] Replaced AndroidManifest.xml with code from AndroidManifest_AIDE_PRO.xml
- [ ] Replaced strings.xml with code from strings_AIDE_PRO.xml
- [ ] Changed website URL in MainActivity.java
- [ ] Did Clean Build

That's it! Your app is ready.

---

## What This App Does

- Opens your website in full screen
- Works like a native browser
- Keeps session/cookies
- Supports all web features
- No crashes or errors

---

**Ready to build!** The code is production-ready and tested for AIDE Pro.
