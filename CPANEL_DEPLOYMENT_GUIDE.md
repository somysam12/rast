# SilentMultiPanel - cPanel Deployment Guide

## Complete Step-by-Step Tutorial for cPanel Hosting

This guide will help you deploy SilentMultiPanel on cPanel hosting with MySQL database.

---

## Part 1: Prepare Your Local Files

### Step 1: Clean Up Your Project
Before uploading to cPanel, remove unnecessary files:

```bash
# Files to DELETE (they are for development only):
- user_manage_keys.php.broken
- RENDER_DEPLOY.md
- RENDER_DEPLOYMENT.md
- DEPLOYMENT_GUIDE.md
- QUICK_START.md
- All *_simple.php files (they are alternatives, not needed)
- /data/database.db (SQLite database for Replit only)
```

**What to KEEP:**
- All main PHP files (login.php, register.php, admin_dashboard.php, etc.)
- `/config/` folder
- `/includes/` folder
- `/assets/` folder (CSS, JS, images)
- `/uploads/` folder (for MOD APKs)
- `fresh_database.sql` file (for setting up MySQL)

---

## Part 2: Set Up cPanel Database

### Step 2: Create Database in cPanel

1. Log in to your cPanel account
2. Go to **MySQL Databases** or **MariaDB**
3. Create a new database with name: `silentmu_silentdb`
4. Create MySQL user:
   - **Username**: `silentmu_silentdb`
   - **Password**: `844121@luvkush`
5. Give this user full privileges to the database (ALL PRIVILEGES)

### Step 3: Import Database Schema

1. In cPanel, go to **phpMyAdmin**
2. Select your `silentmu_silentdb` database from the left panel
3. Click the **Import** tab at the top
4. Choose the `fresh_database.sql` file from your project
5. Click **Go/Import**
6. Wait for success message

**Database is now ready with all tables and sample data!**

---

## Part 3: Upload Files to cPanel

### Step 4: Upload Project Files

1. In cPanel, go to **File Manager** or use **FTP/SFTP**
2. Navigate to your **public_html** folder (or subdirectory like `public_html/silentmu/`)
3. Upload all your project files EXCEPT:
   - Delete `database.db` file if it exists
   - Delete `user_manage_keys.php.broken`
   - Delete unnecessary SQL files like `database.sql`

**File structure on cPanel should look like:**
```
public_html/
├── index.php
├── login.php
├── register.php
├── admin_dashboard.php
├── user_dashboard.php
├── config/
│   └── database.php (already configured)
├── includes/
│   ├── auth.php
│   ├── functions.php
│   └── ...
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── uploads/
│   └── apks/
└── ... (all other PHP files)
```

---

## Part 4: Verify Environment Variables (Optional but Recommended)

### Step 5: Set Environment Variables in cPanel (if supported)

If your cPanel supports `.htaccess` or environment variables:

Create a `.htaccess` file in your root (public_html) with:

```apache
SetEnv DB_HOST localhost
SetEnv DB_NAME silentmu_silentdb
SetEnv DB_USER silentmu_silentdb
SetEnv DB_PASS 844121@luvkush
```

**Note**: If `.htaccess` method doesn't work, the code will automatically use the hardcoded credentials in `config/database.php` (which are already set).

---

## Part 5: Test Your Installation

### Step 6: Access Your Application

1. Open your browser and go to:
   ```
   http://yourdomain.com/index.php
   (or http://yourdomain.com/silentmu/ if in subfolder)
   ```

2. You should see the SilentMultiPanel homepage

3. **Test Login:**
   - Username: `admin`
   - Password: `admin123`
   - You should be redirected to the admin dashboard

4. **Test Register:**
   - Click "Register" on homepage
   - Create a new user account
   - Verify you can log in

### Step 7: Check Database Connection

If you see database connection errors:

1. Go back to cPanel **phpMyAdmin**
2. Click on your `silentmu_silentdb` database
3. Verify the tables were created:
   - users
   - license_keys
   - mods
   - transactions
   - user_sessions
   - etc.

4. Check the **users** table for admin account

---

## Part 6: Post-Installation Configuration

### Step 8: Create Admin User (If Needed)

If the database import failed and you don't have the admin user:

1. In cPanel **phpMyAdmin**, go to your database
2. Click **SQL** tab
3. Paste this (replace with your hashed password):
```sql
INSERT INTO users (username, email, password, role, balance) 
VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 99999.00);
```
4. Click **Go**

Password for this account is: `admin123`

### Step 9: Configure File Permissions

In cPanel File Manager or via SSH:

```bash
# Make uploads folder writable
chmod 755 uploads/
chmod 755 uploads/apks/

# Make data folder writable (if exists)
chmod 755 data/

# Config should be readable
chmod 644 config/database.php
```

---

## Part 7: Enable SSL (HTTPS) - Recommended

1. In cPanel, go to **AutoSSL** or **Let's Encrypt**
2. Install free SSL certificate for your domain
3. In cPanel **Force HTTPS Redirect**, enable it
4. Update any hardcoded URLs in your code from `http://` to `https://`

---

## Troubleshooting

### Problem: "Database connection failed"

**Solution:**
1. Verify database credentials in cPanel MySQL
2. Check that user has privileges on the database
3. Make sure database name matches exactly: `silentmu_silentdb`

### Problem: "Table 'silentmu_silentdb.users' doesn't exist"

**Solution:**
1. Re-import `fresh_database.sql` via phpMyAdmin
2. Or manually create tables using phpMyAdmin SQL tab
3. Check database is selected before importing

### Problem: "Can't upload files"

**Solution:**
1. Make sure `uploads/apks/` folder exists
2. Set permissions: `chmod 755 uploads/apks/`
3. Verify your cPanel file size limits allow uploads

### Problem: "Image not loading" or "CSS not loading"

**Solution:**
1. Make sure all files in `assets/` folder are uploaded
2. Check file permissions are readable: `chmod 644`
3. Clear browser cache (Ctrl+Shift+Delete)

---

## Security Recommendations

1. **Change Admin Password** - After login, change default password
2. **Use HTTPS** - Enable SSL certificate (see Part 7)
3. **Remove Test Accounts** - Delete test users from database
4. **Set File Permissions** - PHP files: 644, Folders: 755
5. **Backup Database** - Regularly backup in cPanel
6. **Update Code** - Keep your code updated with latest security patches

---

## Database Backup and Restore

### Backup:
1. Go to cPanel **phpMyAdmin**
2. Select your database
3. Click **Export** at top
4. Choose **SQL** format
5. Click **Go** - file will download

### Restore:
1. Go to cPanel **phpMyAdmin**
2. Click **Import** tab
3. Upload your SQL backup file
4. Click **Go**

---

## Support Information

- **Admin Panel**: Access at `/admin_dashboard.php` (when logged as admin)
- **User Dashboard**: Access at `/user_dashboard.php` (when logged as user)
- **Database Credentials**:
  - Host: `localhost`
  - Database: `silentmu_silentdb`
  - User: `silentmu_silentdb`
  - Password: `844121@luvkush`

---

## Common File Paths on cPanel

- **Root**: `public_html/` or `public_html/silentmu/`
- **Config**: `public_html/config/database.php`
- **Database**: `MySQL/MariaDB on localhost`
- **Uploads**: `public_html/uploads/apks/`
- **phpMyAdmin**: `cpanel.yourdomain.com:2083` → phpMyAdmin icon

---

## Quick Reference Checklist

- [ ] Database created in cPanel with correct name and user
- [ ] `fresh_database.sql` imported successfully
- [ ] All project files uploaded to `public_html`
- [ ] Unnecessary files deleted
- [ ] `.htaccess` created (optional)
- [ ] File permissions set (755 for folders, 644 for files)
- [ ] Homepage loads at your domain
- [ ] Admin login works
- [ ] User registration works
- [ ] SSL/HTTPS enabled
- [ ] Backup created

---

**Your SilentMultiPanel is now live on cPanel!** 🎉

For issues, check the error logs in cPanel or contact your hosting provider's support.
