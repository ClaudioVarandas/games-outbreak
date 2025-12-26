# Laravel Cloud Deployment Guide

This guide covers deploying Games Outbreak to Laravel Cloud.

## Prerequisites

- Laravel Cloud account (sign up at [cloud.laravel.com](https://cloud.laravel.com))
- GitHub/GitLab repository with your code
- IGDB API credentials
- Domain name (optional, Laravel Cloud provides one)

## Step 1: Connect Repository

1. Log in to [Laravel Cloud](https://cloud.laravel.com)
2. Click "New Project"
3. Connect your GitHub/GitLab repository
4. Select the `games-outbreak` repository

## Step 2: Configure Environment Variables

In Laravel Cloud dashboard, go to your project → Environment Variables and set:

### Application
```
APP_NAME="Games Outbreak"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app.laravel.cloud
APP_KEY= (will be auto-generated)
```

### Database
Laravel Cloud automatically provides a MySQL database. The connection details are automatically set, but you can verify:
```
DB_CONNECTION=mysql
DB_HOST= (auto-configured)
DB_PORT=3306
DB_DATABASE= (auto-configured)
DB_USERNAME= (auto-configured)
DB_PASSWORD= (auto-configured)
```

### Cache & Queue
Laravel Cloud uses Redis automatically. Set:
```
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### Redis (Auto-configured)
```
REDIS_CLIENT=phpredis
REDIS_HOST= (auto-configured)
REDIS_PASSWORD= (auto-configured)
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

### IGDB API
```
IGDB_CLIENT_ID=your_igdb_client_id
IGDB_ACCESS_TOKEN=your_igdb_client_secret
```

### Filesystem
For production, consider using S3 for file storage:
```
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
```

Or use local storage (default):
```
FILESYSTEM_DISK=local
```

## Step 3: Configure Scheduled Tasks

Laravel Cloud automatically runs scheduled tasks defined in `routes/console.php`. Add your scheduled commands:

```php
<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule upcoming games update (daily at 2 AM)
Schedule::command('igdb:upcoming:update --days=90')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Schedule monthly game lists creation (first day of each month at 1 AM)
Schedule::command('games:lists:create-monthly')
    ->monthlyOn(1, '01:00')
    ->withoutOverlapping()
    ->onOneServer();
```

## Step 4: Deploy

1. Laravel Cloud will automatically detect your Laravel application
2. It will run:
   - `composer install --no-dev --optimize-autoloader`
   - `npm ci && npm run build`
   - `php artisan migrate --force` (on first deploy)
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
3. Your application will be live at the provided URL

## Step 5: Post-Deployment

### Run Initial Migrations
If migrations didn't run automatically:
```bash
php artisan migrate --force
```

### Create Storage Link
```bash
php artisan storage:link
```

### Seed Initial Data (if needed)
```bash
php artisan db:seed
```

### Create Monthly Lists for Current Year
```bash
php artisan games:lists:create-monthly --year=2025
```

### Create Admin User

**IMPORTANT**: Create your admin user immediately after deployment to secure admin access.

#### Interactive Mode (Recommended)
```bash
php artisan user:create-admin
```

The command will:
1. Prompt for email address
2. Prompt for password (hidden input)
3. Prompt for password confirmation (hidden input)
4. Validate inputs (email format, password strength - minimum 8 characters)
5. Check if admin already exists
6. Show summary and ask for confirmation
7. Create admin user with `is_admin = true`
8. Display success message

#### Non-Interactive Mode (For Automation)
```bash
php artisan user:create-admin --email=admin@example.com --password=secure_password --force
```

**Security Notes**:
- Never commit admin credentials to git
- Use strong passwords (minimum 8 characters enforced)
- Run command directly on server via SSH
- The command checks for existing admins and prevents duplicates
- Password is automatically hashed using Laravel's bcrypt

**Example Output**:
```
Creating Admin User

Email address: admin@example.com
Password: 
Confirm password: 

Summary:
  Email: admin@example.com
  Password: ********
  Admin: Yes

Create this admin user? (yes/no) [yes]:
> yes

✓ Admin user created successfully!
  ID: 1
  Email: admin@example.com
  Name: Admin
  Admin: Yes

You can now log in with these credentials.
```

## Step 6: Queue Workers

Laravel Cloud automatically runs queue workers. Ensure your jobs are configured to use Redis:

- Queue connection: `redis` (already set in environment)
- Failed jobs are stored in database automatically

## Step 7: Monitoring

Laravel Cloud provides:
- Application logs (view in dashboard)
- Error tracking
- Performance metrics
- Queue monitoring

## Important Notes

### File Storage
- **Local storage**: Files are stored on the server (lost on redeploy)
- **S3 storage**: Recommended for production (persistent)

### Scheduled Tasks
- Must be defined in `routes/console.php` using `Schedule::command()`
- Laravel Cloud runs `php artisan schedule:run` every minute automatically
- Use `withoutOverlapping()` to prevent concurrent runs
- Use `onOneServer()` for multi-server setups

### Queue Jobs
- Queue workers run automatically
- Failed jobs are stored in `failed_jobs` table
- Monitor queue in Laravel Cloud dashboard

### Database Backups
- Laravel Cloud handles database backups automatically
- Check backup settings in dashboard

### SSL/HTTPS
- Laravel Cloud provides SSL certificates automatically
- Custom domains can be configured in dashboard

## Troubleshooting

### Jobs Not Processing
- Verify `QUEUE_CONNECTION=redis` in environment
- Check queue worker logs in dashboard
- Ensure Redis is properly configured

### Scheduled Tasks Not Running
- Verify tasks are defined in `routes/console.php`
- Check Laravel Cloud logs for schedule execution
- Ensure commands exist and are registered

### Storage Issues
- Run `php artisan storage:link` after deployment
- Consider using S3 for persistent storage
- Check file permissions

### Performance
- Enable OPcache (handled automatically)
- Use Redis for cache and sessions
- Monitor performance metrics in dashboard

## Cost Estimate

Laravel Cloud pricing varies by plan:
- **Hobby**: $20/month (suitable for small projects)
- **Pro**: $40/month (recommended for production)
- **Business**: Custom pricing

Includes:
- Hosting
- Database
- Redis
- SSL certificates
- Automatic backups
- Queue workers
- Scheduled tasks

## Support

- Laravel Cloud Documentation: [cloud.laravel.com/docs](https://cloud.laravel.com/docs)
- Laravel Cloud Support: Available in dashboard
- Community: [Laravel Discord](https://discord.gg/laravel)

