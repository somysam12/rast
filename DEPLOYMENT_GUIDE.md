# SilentMultiPanel - Deployment Guide

## Current Status
- ✓ Repository cleaned (removed 1.5GB+ useless files)
- ✓ 51 active PHP files (core app only)
- ✗ Database: Currently using SQLite (not suitable for production)

## Problem: SQLite Won't Work in Production

SQLite stores data in a local file (`data/database.db`). In cloud hosting:
- File gets deleted when server restarts
- Multiple servers can't share the same database
- No data persistence

## Solution: Use PostgreSQL

---

## DEPLOYMENT OPTION 1: RENDER.COM (RECOMMENDED) ⭐

### Step 1: Prepare Database Config
Edit `config/database.php`:

```php
<?php
// Replace everything with this:

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $db_url = parse_url(getenv('DATABASE_URL'));
            
            $dsn = "pgsql:host=" . $db_url['host'] 
                   . ";port=" . ($db_url['port'] ?? 5432)
                   . ";dbname=" . ltrim($db_url['path'], '/');
            
            $pdo = new PDO(
                $dsn,
                $db_url['user'],
                $db_url['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            return $pdo;
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}
```

### Step 2: Create Render.com Account
1. Go to https://render.com
2. Sign up with GitHub account (easier deployment)

### Step 3: Create PostgreSQL Database
1. Dashboard → New → PostgreSQL
2. Choose free tier
3. Copy connection string → Save it

### Step 4: Deploy App
1. Push code to GitHub
2. In Render → New → Web Service
3. Connect GitHub repo
4. Settings:
   - Build Command: `echo "No build needed"`
   - Start Command: `php -S 0.0.0.0:5000`
   - Add environment variable:
     - Key: `DATABASE_URL`
     - Value: (paste your PostgreSQL connection string)

### Step 5: Initialize Database
1. SSH into Render instance
2. Run: `php -r "require 'config/database.php'; require 'includes/functions.php'; initializeDatabase();"`
3. Or manually run `database.sql` using psql

---

## DEPLOYMENT OPTION 2: RAILWAY.APP

### Advantages:
- Simpler UI than Render
- Free PostgreSQL included
- Auto-deploys from GitHub

### Steps:
1. https://railway.app → Sign up
2. Create project → PostgreSQL (included free)
3. Add service → PHP app
4. Connect GitHub
5. Add `DATABASE_URL` env var
6. Deploy

---

## DEPLOYMENT OPTION 3: HEROKU (Still Works)

1. Install Heroku CLI
2. `heroku create appname`
3. `heroku addons:create heroku-postgresql:hobby-dev`
4. `git push heroku main`

---

## WHAT NOT TO DO

❌ **Do NOT deploy to Vercel** - Vercel doesn't support traditional PHP apps
❌ **Do NOT keep SQLite** - Data will be lost after redeploy
❌ **Do NOT expose DATABASE_URL in code** - Use environment variables only

---

## IMPORTANT FILES

**Keep committed to GitHub:**
- ✓ All `.php` files
- ✓ `assets/` directory
- ✓ `config/database.php` (with updated PostgreSQL code)
- ✓ `includes/` directory
- ✓ `database.sql` (schema file)
- ✓ `README.md`

**DO NOT commit:**
- ✗ `.env` files with secrets
- ✗ `data/database.db`
- ✗ `uploads/` (create .gitkeep instead)

---

## Quick Checklist

- [ ] Update `config/database.php` for PostgreSQL
- [ ] Push to GitHub
- [ ] Create Render.com account
- [ ] Create PostgreSQL database on Render
- [ ] Create Web Service on Render, connect GitHub
- [ ] Add DATABASE_URL environment variable
- [ ] Deploy
- [ ] Test at your Render URL

---

## If You Get Database Errors

1. Check if `DATABASE_URL` env var is set
2. Verify PostgreSQL is running
3. Run database schema: `psql your-db < database.sql`
4. Check `includes/functions.php` has `initializeDatabase()` function

