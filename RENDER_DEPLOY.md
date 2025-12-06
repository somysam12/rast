# Render.com Deployment Guide

## Quick Start

### Step 1: Push Code to GitHub
Push your code to a GitHub repository.

### Step 2: Create New Web Service on Render
1. Go to https://render.com and sign in
2. Click "New" -> "Blueprint"
3. Connect your GitHub repository
4. Render will automatically detect `render.yaml` and create:
   - A PostgreSQL database (`mod-apk-db`)
   - A web service (`mod-apk-manager`)

### Step 3: Wait for Deployment
- The database will be created first
- Then the Docker image will be built and deployed
- The entrypoint script will automatically initialize the database tables

### Step 4: Access Your Application
After deployment, you can access your application at the URL provided by Render.

**Default Admin Login:**
- Username: `admin`
- Password: `admin123`

**Important:** Change the admin password immediately after first login!

## Manual Deployment (Alternative)

If you prefer to set up services manually:

### 1. Create PostgreSQL Database
1. Go to Render Dashboard -> New -> PostgreSQL
2. Choose a name (e.g., `mod-apk-db`)
3. Select Free plan
4. Click "Create Database"
5. Copy the "Internal Database URL"

### 2. Create Web Service
1. Go to Render Dashboard -> New -> Web Service
2. Connect your repository
3. Settings:
   - **Environment**: Docker
   - **Plan**: Free
   - **Health Check Path**: `/health.php`
4. Add Environment Variable:
   - Key: `DATABASE_URL`
   - Value: (paste the Internal Database URL from step 1)
5. Click "Create Web Service"

## File Structure for Render

```
├── Dockerfile              # Docker build configuration
├── docker-entrypoint.sh    # Startup script (initializes DB)
├── render.yaml             # Render Blueprint configuration
├── apache.conf             # Apache virtual host config
├── health.php              # Health check endpoint
├── .htaccess               # URL rewriting and security
├── .dockerignore           # Files excluded from Docker build
└── uploads/apks/.gitkeep   # Uploads directory placeholder
```

## Troubleshooting

### Database Connection Issues
- Check that `DATABASE_URL` environment variable is set correctly
- Verify the database is running in Render Dashboard
- Check the service logs for connection errors

### 502 Bad Gateway
- Wait a few minutes for the service to fully start
- Check health check endpoint: `https://your-app.onrender.com/health.php`
- Review service logs in Render Dashboard

### File Upload Issues
- Note: Render's filesystem is ephemeral
- Uploaded APK files will be lost on redeployment
- For persistent storage, consider using external storage (S3, Cloudflare R2, etc.)

## Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `DATABASE_URL` | PostgreSQL connection string | Yes |

## Notes

- The free tier spins down after 15 minutes of inactivity
- First request after spin-down may take 30-60 seconds
- Upgrade to a paid plan for always-on service
