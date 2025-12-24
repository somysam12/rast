# Database Comparison: Old vs Fresh

## 📍 Currently Being Used

**File Location:** `config/database.php`

This file creates tables **on first page load** automatically. It uses the code:

```php
function initializeDatabase() {
    // Creates these tables:
    - users
    - user_sessions
    - mods
    - license_keys
    - mod_apks
    - transactions
    - referral_codes
    - key_requests
    - key_confirmations
    - force_logouts
}
```

**10 Tables Total** - Basic features only

---

## 🆕 Fresh Database (NEW)

**File Name:** `fresh_database.sql`

This is a **complete, production-ready** database with:

**14 Tables** - All features covered:

| # | Table Name | Purpose |
|---|---|---|
| 1 | `users` | User accounts & authentication ✨ Enhanced with status, last_login |
| 2 | `user_sessions` | Session management ✨ With is_active tracking |
| 3 | `mods` | MOD applications ✨ With category, version, icon_url |
| 4 | `license_keys` | License management ✨ With activation_count, last_used |
| 5 | `mod_apks` | APK file uploads ✨ With file_hash, version |
| 6 | `transactions` | Payment history ✨ With payment_method, notes |
| 7 | `referral_codes` | Referral system ✨ Enhanced discount & bonus system |
| 8 | `key_requests` | Block/Reset requests ✨ With reviewed_by, admin_notes |
| 9 | `key_confirmations` | Action confirmations ✨ Same as current |
| 10 | `force_logouts` | Session termination ✨ With is_global flag |
| 11 | `notifications` | **NEW** - User notifications system |
| 12 | `applications` | **NEW** - User applications management |
| 13 | `activity_log` | **NEW** - Admin audit trail |
| 14 | `settings` | **NEW** - System configuration |

---

## Detailed Comparison

### 1. USERS TABLE

**Current (database.php):**
```sql
id, username, email, password, balance, role, 
referral_code, referred_by, created_at
```

**Fresh (fresh_database.sql):** ✨ Enhanced
```sql
id, username, email, password, balance, role, 
referral_code, referred_by, 
STATUS (active/suspended/deleted),  -- NEW
LAST_LOGIN,  -- NEW
UPDATED_AT  -- NEW
```

---

### 2. LICENSE_KEYS TABLE

**Current (database.php):**
```sql
id, mod_id, license_key, duration, duration_type, 
price, status, sold_to, sold_at, created_at
```

**Fresh (fresh_database.sql):** ✨ Enhanced
```sql
id, mod_id, license_key, duration, duration_type, 
price, status, sold_to, sold_at,
EXPIRES_AT,  -- NEW (automatic expiry)
LAST_USED,  -- NEW (track usage)
ACTIVATION_COUNT,  -- NEW (count activations)
UPDATED_AT  -- NEW
```

---

### 3. MODS TABLE

**Current (database.php):**
```sql
id, name, description, status, created_at
```

**Fresh (fresh_database.sql):** ✨ Enhanced
```sql
id, name, description,
CATEGORY,  -- NEW (game, tool, etc)
VERSION,  -- NEW (version tracking)
status,
ICON_URL,  -- NEW (MOD icon)
DOWNLOAD_COUNT,  -- NEW
UPDATED_AT  -- NEW
```

---

### 4. NEW TABLES IN FRESH DATABASE

#### `notifications` - User notifications
```sql
- user_id, title, message, type
- is_read status tracking
- Related to license_keys, transactions, etc
```

#### `applications` - User applications
```sql
- app_name, app_package, description
- api_key for integration
- status tracking
```

#### `activity_log` - Admin audit trail
```sql
- admin_id (who did it)
- action, entity_type, entity_id
- old_value, new_value (track changes)
- Compliance & auditing
```

#### `settings` - System configuration
```sql
- setting_key, setting_value
- Stores: site_name, currency, timezone, etc
- No need to hardcode settings
```

---

## Key Enhancements in Fresh Database

✅ **Better Performance:**
- Optimized indexes on frequently queried fields
- Foreign key constraints for data integrity
- Proper data types (DECIMAL vs FLOAT, BIGINT for file size)

✅ **Enhanced Features:**
- Automatic license expiration tracking
- Last login for user activity analysis
- Download count for popular MODs
- Audit log for admin actions
- Settings table for flexible configuration

✅ **Security:**
- Status fields for user suspension
- Activity logging for compliance
- Better referral tracking

✅ **Scalability:**
- Proper indexing
- Comments explaining each column
- Prepared for future features

✅ **Production Ready:**
- Supports 14 features vs 10
- Better data relationships
- Audit trail included
- Configuration system built-in

---

## How to Use Fresh Database

### Option 1: On cPanel
```
1. Go to phpMyAdmin
2. Select your database
3. Click SQL tab
4. Copy entire fresh_database.sql content
5. Paste & Execute
6. Done! All 14 tables created
```

### Option 2: Via Terminal/SSH
```bash
mysql -u username -p dbname < fresh_database.sql
```

### Option 3: Keep Current + Migrate Later
```
- Keep using config/database.php now
- When ready, backup current data
- Import fresh_database.sql
- Migrate data from old to new tables
```

---

## Migration Path (If Switching)

**If you want to switch from current to fresh database:**

```sql
-- Step 1: Backup old data
CREATE TABLE users_backup SELECT * FROM users;
CREATE TABLE license_keys_backup SELECT * FROM license_keys;

-- Step 2: Import fresh_database.sql
-- (executes fresh schema)

-- Step 3: Migrate data
INSERT INTO users (id, username, email, password, balance, role, referral_code, referred_by, created_at)
SELECT id, username, email, password, balance, role, referral_code, referred_by, created_at 
FROM users_backup;

-- Step 4: Verify & cleanup
DROP TABLE users_backup;
DROP TABLE license_keys_backup;
```

---

## Recommendation

**For Production Deployment:**
✅ Use `fresh_database.sql` - It's more robust

**For Quick Testing:**
⚡ Use current `config/database.php` - Auto-creates tables

**For Migration:**
🔄 Use `fresh_database.sql` - Better structure for future

---

## File Summary

| File | Purpose | When to Use |
|------|---------|------------|
| `database.sql` | **OLD** - Basic schema with sample data | Testing only |
| `fresh_database.sql` | **NEW** - Complete production schema | Recommended for deployment |
| `config/database.php` | Auto-creates old schema on load | Development/quick start |

---

**Which one should you use?**

✅ **For cPanel/Render deployment:** Use `fresh_database.sql`  
✅ **For better features:** Use `fresh_database.sql`  
⚡ **For quick start:** Use `config/database.php` auto-creation

