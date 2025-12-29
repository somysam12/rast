# SilentMultiPanel Setup Tutorial

## 1. Web Panel Deployment (cPanel)
1.  **Upload**: Upload all files inside `final_delivery/web_panel` to your `public_html` folder.
2.  **Database**: 
    *   Create a MySQL database in cPanel.
    *   Import the SQL file (if you have one) or the system will use the existing configuration in `config/database.php`.
    *   Ensure your `config/database.php` matches your cPanel MySQL credentials.
3.  **Login**: Access your site and login with `admin` / `admin123`.

## 2. Android App Setup (AIDE Pro)
1.  **Project Structure**: Create a new "New Android App" in AIDE.
2.  **Replace Files**:
    *   Replace `MainActivity.java` with the one in `final_delivery/android_app/src/main/java/.../`
    *   Replace `AndroidManifest.xml` with the one in `final_delivery/android_app/src/main/`
    *   Replace `activity_main.xml` and `strings.xml` in their respective `res` folders.
3.  **Splash Logo**: Copy `splash_logo.jpg` into your `res/drawable/` folder.
4.  **Styles**: Copy `styles.xml` into `res/values/`.
5.  **Build**: Click the Play button in AIDE to build your smooth, fast APK with the refresh button and splash screen.

## 3. Key Features
*   **Refresh Button**: Tap the purple rotate icon at the bottom right to reload.
*   **Mod Uploads**: The app is fully configured to handle APK/Mod file selections.
*   **Smooth UI**: Hardware acceleration is enabled for lag-free performance.