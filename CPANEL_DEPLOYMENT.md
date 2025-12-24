# SilentMultiPanel - cPanel (Shared Hosting) Deployment Guide

## What is cPanel?

cPanel is a control panel used by traditional web hosting providers. If your hosting provider has cPanel, you can deploy this app directly without needing Render or Heroku.

**Popular providers with cPanel:**
- Bluehost
- HostGator
- SiteGround
- DreamHost
- GoDaddy
- Namecheap

---

## Step 1: Download Project as ZIP

1. In your Replit project, click **Files** (sidebar)
2. Right-click root folder → **Download**
3. Save the ZIP file to your computer

Alternatively, download from terminal:
```bash
# This creates a ZIP of the entire project
zip -r silentmultipanel.zip . -x ".git/*" "data/*" ".initialized" "*.db"
```

---

## Step 2: Access Your cPanel

1. Open your hosting provider's login
2. Type in URL: `yourdomain.com/cpanel` or `yourdomain.com:2083`
3. Login with your cPanel credentials

---

## Step 3: Create MySQL Database

In cPanel → **MySQL Databases**:

1. **Create New Database**:
   - Database Name: `yoursite_silentpanel` (can be anything)
   - Click **Create Database**

2. **Create Database User**:
   - Username: `yoursite_user` (can be anything)
   - Password: (create a strong password)
   - Click **Create User**

3. **Add User to Database**:
   - Select user and database
   - Permissions: Check **ALL PRIVILEGES**
   - Click **Make Changes**

**Save these details:**
```
Database Name: yoursite_silentpanel
Database User: yoursite_user
Database Password: YourPassword123
Database Host: localhost
```

---

## Step 4: Upload Project Files

In cPanel → **File Manager**:

1. Open `public_html` folder (this is your website root)
2. Click **Upload**
3. Select the ZIP file you downloaded
4. After upload, right-click ZIP → **Extract**
5. All files are now in `public_html`

**Your site will be at:** `https://yourdomain.com`

---

## Step 5: Configure Database Connection

Edit the file: `config/database.php`

In cPanel File Manager:
1. Navigate to `public_html/config/`
2. Right-click `database.php` → **Edit**
3. Add this at the very beginning (after `<?php`):

```php
<?php
// Add these BEFORE the existing code:
putenv('DB_HOST=localhost');
putenv('DB_NAME=yoursite_silentpanel');
putenv('DB_USER=yoursite_user');
putenv('DB_PASS=YourPassword123');
```

Replace with YOUR actual database details from Step 3!

**Example (filled in):**
```php
<?php
putenv('DB_HOST=localhost');
putenv('DB_NAME=mysite_panel');
putenv('DB_USER=mysite_admin');
putenv('DB_PASS=MyPassword@123');

// Then continue with the rest of the code...
function getDBConnection() {
...
```

---

## Step 6: Create Database Tables

The database tables auto-create on first page load.

**To manually trigger table creation:**

1. In cPanel → **Terminal** (or use phpMyAdmin)
2. Access your site: `https://yourdomain.com`
3. Just refresh the page

**Or use phpMyAdmin directly:**

1. cPanel → **phpMyAdmin**
2. Select your database
3. Click **Import**
4. Find file: `database.sql` (upload from your computer)
5. Click **Go/Import**

---

## Step 7: Test Your Site

1. Open `https://yourdomain.com`
2. You should see the SilentMultiPanel homepage
3. Click **Login**
4. Use default credentials:
   - Username: `admin`
   - Password: `admin123`

⚠️ **Change admin password immediately after login!**

---

## Troubleshooting

### "Database connection failed"

**Cause:** Wrong database credentials

**Fix:**
1. Check `config/database.php` - verify DB_HOST, DB_NAME, DB_USER, DB_PASS
2. In phpMyAdmin, test the connection
3. Make sure database user has ALL PRIVILEGES on the database

### "File not found" or "404 Error"

**Cause:** Files not in correct folder

**Fix:**
1. Make sure files are in `public_html`, not in a subfolder
2. Your home page should be at: `/public_html/index.php`

### "Permission Denied" errors

**Cause:** File permissions

**Fix in cPanel:**
1. Right-click folder → **Change Permissions**
2. Set to `755` for folders, `644` for files
3. Check **Recursive** option

### "No such table: users"

**Cause:** Database not initialized

**Fix:**
1. Open `https://yourdomain.com` in browser (triggers auto-init)
2. Or run `database.sql` manually in phpMyAdmin

### MySQL "Connection refused"

**Cause:** Database host wrong (common with different servers)

**Fix:**
1. Try `localhost` first
2. If that fails, ask hosting provider for database host
3. Might be something like `db-server-123.hosting.com`

---

## Important Files

After uploading, these files are in `public_html`:

| File/Folder | Purpose |
|---|---|
| `index.php` | Homepage |
| `config/database.php` | Database connection (EDIT THIS) |
| `login.php`, `register.php` | User auth |
| `admin_*.php` | Admin panel pages |
| `user_*.php` | User dashboard pages |
| `assets/` | CSS, JavaScript, images |
| `uploads/` | User uploads (APK files) |
| `database.sql` | Database schema |
| `includes/` | Helper functions |

---

## After Deployment

1. **Change Admin Password**:
   - Login with `admin` / `admin123`
   - Go to Settings → Change Password
   - Create new password

2. **Configure Mods**:
   - Admin Panel → Add Mod
   - Add your MOD APK applications

3. **Set Up License Keys**:
   - Admin Panel → Add License
   - Generate license keys for your mods

4. **Promote Users to Admin (Optional)**:
   - Admin Panel → Manage Users
   - Change role from "user" to "admin"

---

## Moving Uploads to Permanent Storage

By default, uploads are in `public_html/uploads/`. This works but can fill up quickly.

**To use external storage:**

1. Use **Cloudinary** (free) for images/APKs
2. Or ask hosting for a separate storage account

---

## SSL/HTTPS

Most hosting providers offer free SSL:

1. cPanel → **AutoSSL** or **SSL/TLS Status**
2. Install certificate for your domain
3. Site will auto-redirect to HTTPS

---

## Database Backups

In cPanel → **MySQL Databases** → **Backup**:

1. Select your database
2. Click **Download Backup**
3. Save the SQL file locally

**Restore backup:**
1. phpMyAdmin → Select database
2. Import → Upload the backup file

---

## Performance Tips

1. **Enable caching** (if available in cPanel)
2. **Use asset minification** - already included in `assets/`
3. **Monitor database** - phpMyAdmin → Status
4. **Regular backups** - weekly recommended

---

## Getting Help

If something goes wrong:

1. **Check cPanel Error Logs**:
   - cPanel → Error Log
   - Shows what went wrong

2. **Check PHP Error Logs**:
   - cPanel → Raw Access Logs

3. **Test Database**:
   - cPanel → phpMyAdmin
   - Can you connect? Can you see tables?

4. **Contact Hosting Provider**:
   - Database host, credentials issues
   - File permission issues

---

## Quick Checklist

- [ ] Downloaded project as ZIP
- [ ] Extracted files to `public_html`
- [ ] Created MySQL database
- [ ] Created database user with ALL PRIVILEGES
- [ ] Edited `config/database.php` with database details
- [ ] Accessed homepage - tables auto-created
- [ ] Logged in with `admin` / `admin123`
- [ ] Changed admin password
- [ ] Added your mods
- [ ] Generated license keys
- [ ] Tested with real users

---

## Comparison: cPanel vs Render

| Feature | cPanel | Render |
|---------|--------|--------|
| Setup Time | 15-20 min | 5-10 min |
| Cost | Usually $5-15/month | Free tier available |
| Database | MySQL included | PostgreSQL (free) |
| Backups | Manual/Automatic | Automatic daily |
| Scaling | Limited | Unlimited |
| SSL | Usually free | Automatic |
| Support | Hosting provider | Render docs |

---

**Your site is now live on cPanel!** 🚀

For questions, check your hosting provider's documentation or contact their support.

