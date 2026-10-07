# ArkCheat — Rebuild Instructions

All source fixes are already applied in this repo. Follow these steps to build the AAR and APK in AndroidIDE.

---

## Step 1 — Build Hcore-release.aar (BBox / MundoCore)

1. Open `bbox_source/MundoCore/` as a project in AndroidIDE
2. Run `assembleRelease`
3. Output: `bbox_source/MundoCore/build/outputs/aar/MundoCore-release.aar`
4. Rename it to `Hcore-release.aar`
5. Copy `Hcore-release.aar` → `aRkCheat/app/libs/Hcore-release.aar` (replace the old one)

---

## Step 2 — Restore binary files into aRkCheat

These files are not in the repo (binaries). Get them from your original `aRkCheatSimp.zip`:

| File | Destination |
|------|-------------|
| `zen.jks` | `aRkCheat/app/zen.jks` |
| `jni/includes/curl/**/*.a` | `aRkCheat/app/src/main/jni/includes/curl/` |
| `jni/includes/curl/**/*.la` | same |
| `jni/includes/openssl/**/*.a` | `aRkCheat/app/src/main/jni/includes/openssl/` |
| `res/raw/*.wav` | `aRkCheat/app/src/main/res/raw/` |
| `res/font/*.ttf` | `aRkCheat/app/src/main/res/font/` |
| `assets/fonts/*.ttf` | `aRkCheat/app/src/main/assets/fonts/` |
| `assets/*.bin` (ff_core.bin etc.) | `aRkCheat/app/src/main/assets/` |
| `gradle/wrapper/gradle-wrapper.jar` | `aRkCheat/gradle/wrapper/` |

---

## Step 3 — Build APK

1. Open `aRkCheat/` as a project in AndroidIDE
2. Make sure `app/libs/Hcore-release.aar` is the newly built one from Step 1
3. Run `assembleRelease`
4. The FrozenFire task in `build.gradle` automatically:
   - Encrypts classes2+.dex → `assets/ff_core.bin`
   - Zipaligns and re-signs the output
5. Final APK: `aRkCheat/app/build/outputs/apk/release/app-release_protected.apk`

---

## What was fixed

### APK side (ArkApplication + ArkCrashLogger)
- `ArkCrashLogger.init()` now runs as the **very first line** of `attachBaseContext()`,
  before DexDecryptor and BBox init. Crashes during BBox startup are now captured
  in the log instead of going to Android's default crash handler silently.
- `ArkCrashLogger` classifies every crash with a `KEEPS_STOPPING_TYPE` line:
  - `BBox_Android16_IServiceConnection` — Samsung/Android 16 Binder protocol crash
  - `BBox_Firebase_SecurityException` — Firebase crash inside virtual container
  - `BBox_libbgmi_UnsatisfiedLinkError` — Corrupt/partial libbgmi.so
  - `BBox_PackageInfo_NPE` — PackageInfo.applicationInfo null pointer
  - `Generic_BBox_Crash` — any other crash with BBox stack frames
  - `Generic_Crash` — everything else

### BBox side (Hcore-release.aar source)
- **Fix 1 (Android 16)**: `ServiceConnectionDelegate.onTransact()` reads and discards
  the new `IBinderSession` param that Android 16 adds to `IServiceConnection.connected()`.
- **Fix 2 (Firebase)**: `ServiceConnectionDelegate.connected()` catches `SecurityException`
  from Firebase `WithinAppServiceBinder` inside virtual containers.
- **Fix 3 (libbgmi.so)**: `VNative` validates ELF magic before `System.load()`;
  corrupt file is deleted so next launch re-extracts it cleanly.
- **Fix 4 (PackageInfo NPE)**: `BPackageManagerService.getPackageInfo()` falls back
  to system PM when the package is not in BBox's virtual registry.
- **Fix 5 (Facebook Login)**: `FacebookLoginHelper.injectCorrectKeyHash()` replaces
  the OAuth URL key hash with the real guest package signing cert hash.
- **Fix 6 (Android 16 interface)**: New `IServiceConnectionBaklava` BlackReflection
  wrapper for the 4-arg `connected()` method.

---

## Log files (after installing fixed APK)

- Main log: `/storage/emulated/0/CrashLog/Ark.log`
- Crash reports: `/storage/emulated/0/CrashLog/reports/*.txt`
- Crash reports are also sent automatically to Telegram.
