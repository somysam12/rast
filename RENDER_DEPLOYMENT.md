# SilentMultiPanel - Complete Render Deployment Tutorial

## Step 1: Sign Up on Render.com

1. Go to https://render.com
2. Click **Sign Up**
3. Choose **GitHub** signup (easiest)
4. Authorize Render to access your GitHub account

---

## Step 2: Push Code to GitHub

Before deploying to Render, your code must be on GitHub:

```bash
# If not already on GitHub:
git init
git add .
git commit -m "Clean repo ready for deployment"
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git
git branch -M main
git push -u origin main
```

**If you don't have GitHub:**
1. Go to https://github.com/new
2. Create a new repository (name it anything)
3. Follow the instructions to push your code

---

## Step 3: Create PostgreSQL Database on Render

1. Log in to https://dashboard.render.com
2. Click **New +** → Select **PostgreSQL**
3. Fill in details:
   - **Name**: `silentpanel-db` (or any name)
   - **Database**: `silentpanel` (any name)
   - **User**: `silentpanel` (any username)
   - **Region**: Choose closest to you (e.g., Singapore, Mumbai)
   - **Plan**: Free tier (fine for testing)
4. Click **Create Database**
5. ⏳ Wait 2-3 minutes for database to start

**⚠️ IMPORTANT: Copy your database connection string!**

After it starts, you'll see a page like:

```
PostgreSQL Database
silentpanel-db

Connections:
External Database URL: 
postgresql://silentpanel_user:PASSWORD@dpg-xxxxx.render.internal:5432/silentpanel
```

**Copy the entire URL** (save it somewhere safe, you'll need it)

---

## Step 4: Create Web Service (Deploy App)

1. Back in Render dashboard, click **New +** → Select **Web Service**
2. Select your GitHub repository with the code
3. Fill in details:
   - **Name**: `silentpanel` (any name)
   - **Environment**: `PHP`
   - **Build Command**: 
     ```
     composer install || echo "No composer needed"
     ```
   - **Start Command**: 
     ```
     php -S 0.0.0.0:5000
     ```
   - **Plan**: Free tier (or paid if you want)

4. **Add Environment Variable**:
   - Click **Environment**
   - Click **Add Environment Variable**
   - **Key**: `DATABASE_URL`
   - **Value**: (Paste the PostgreSQL URL you copied earlier)
   - Click **Save**

5. Click **Create Web Service**

⏳ **Wait 3-5 minutes** - Render will automatically deploy your code!

---

## Step 5: Initialize Database Tables

After deployment succeeds, you need to create database tables:

1. In Render dashboard, go to your Web Service
2. Click **Shell** (top right)
3. Run this command:
   ```
   php -r "require 'config/database.php';"
   ```

This will automatically create all tables and the default admin user.

**Or access your deployed URL and refresh the page** - it will auto-initialize.

---

## Step 6: Access Your App

1. In Render dashboard → Your Web Service
2. You'll see a URL like: `https://silentpanel.render.com`
3. Click that link to open your app!

---

## Default Admin Credentials

After deployment, login with:
- **Username**: `admin`
- **Password**: `admin123`

⚠️ **CHANGE THIS IMMEDIATELY AFTER LOGIN!**

---

## Troubleshooting

### "Application crashed" or "502 Bad Gateway"

1. Click **Logs** to see what went wrong
2. Common issues:
   - DATABASE_URL not set (check Environment tab)
   - PostgreSQL not running yet (wait a few minutes)
   - Syntax error in PHP code

**Check logs:**
```
Click "Logs" tab in Web Service → Shows all errors
```

### Database Connection Failed

```
Error: could not translate host name "dpg-xxxxx.render.internal" to address
```

This means:
- PostgreSQL is still starting (wait 2 minutes)
- DATABASE_URL is wrong (copy it again from PostgreSQL dashboard)

### "Key not found" errors on login

The database tables weren't created. Run in Shell:
```
php -r "require 'config/database.php'; initializeDatabase();"
```

---

## What Changed in Code

✅ **You don't need to change anything!** The code automatically detects:

- If `DATABASE_URL` environment variable exists → Uses **PostgreSQL** (Render)
- If not set → Uses **SQLite** (Local Replit)

**The file `config/database.php` now has:**
- ✓ Auto-detection of PostgreSQL vs SQLite
- ✓ Proper connection string parsing
- ✓ Automatic table creation
- ✓ Default admin user creation

---

## Database Features (PostgreSQL on Render)

| Feature | SQLite (Local) | PostgreSQL (Render) |
|---------|---|---|
| Data Persistence | ❌ Lost on restart | ✅ Always saved |
| Multi-user | ❌ Slow/conflicts | ✅ Handles concurrent users |
| Backups | ⚠️ Manual | ✅ Automatic daily |
| Scalability | ❌ Limited | ✅ Grows with users |

---

## After Deployment: Next Steps

1. **Change admin password**: 
   - Login → Settings → Change password

2. **Add your mods** in admin panel

3. **Create users** and test

4. **Set up SSL** (Render does this automatically with HTTPS)

---

## File Storage (Uploads)

Your `uploads/` folder is temporary on Render (lost on redeploy).

For permanent file storage, add object storage:
1. Render → New Object Store
2. Or use Cloudinary for images (free tier available)

---

## Updating Your Code After Deployment

When you make changes:

```bash
# Make changes locally
git add .
git commit -m "Your changes"
git push origin main
```

Render automatically redeplooys within 1-2 minutes!

---

## Rolling Back to Previous Version

1. Go to Render Web Service → **Logs** tab
2. Find the previous successful deployment
3. Click **Redeploy** button

---

## Monitoring & Logs

Check application health:
1. Web Service → **Logs** tab (real-time logs)
2. **Metrics** tab (CPU, memory usage)
3. **Events** tab (deployment history)

---

## Support

If you have issues:
1. Check **Logs** (most helpful)
2. Visit https://render.com/docs
3. Email Render support

---

## Quick Reference

| Task | Steps |
|------|-------|
| **Check logs** | Web Service → Logs |
| **Set env vars** | Web Service → Environment |
| **Redeploy** | Web Service → Manual Deploy |
| **Access database shell** | PostgreSQL DB → Shell |
| **View metrics** | Web Service → Metrics |

---

**Your app is now deployed on Render with a permanent PostgreSQL database!** 🚀

