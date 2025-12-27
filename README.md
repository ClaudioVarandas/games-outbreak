# Games Outbreak Project

## Setup


```bash
# Run migrations and seeders
php artisan migrate --seed

# Create the admin user id 1, required for the system lists
php artisan user:create-admin --email=admin@example.com --password=secure_password --force

# Create monthly game lists for the current year
php artisan games:lists:create-monthly --year=2026

# Populate db with games
php artisan igdb:upcoming:update --start-date=2026-01-06 --days=10 
```

### DB
```shell

# dump prod db
mysqldump -h xxx.db.laravel.cloud -P 3306 -u <user> -p --single-transaction main > dump-go-main.sql

# import DB
mysql -u root -p games_outbreak < /usr/dumps/dump-go-main.sql

```


### Create Admin User

After initial setup, create an admin user to access admin features:

**Interactive Mode (Recommended)**:
```bash
php artisan user:create-admin
```

The command will prompt for:
- Email address
- Password (hidden input)
- Password confirmation (hidden input)

**Non-Interactive Mode**:
```bash
php artisan user:create-admin --email=admin@example.com --password=secure_password --force
```

**Security Notes**:
- Use strong passwords (minimum 8 characters enforced)
- Never commit admin credentials to git
- Run command directly on server via SSH for production
- Password is automatically hashed using Laravel's bcrypt



## Goal

This project is a web application for managing game lists and tracking game statuses.

## Features

- Track your game collection and statuses (Playing, Beaten, Completed, etc.)
- Organize games into custom lists
- **Backlog**: a dedicated list for games you plan to play
- **Wishlist**: a dedicated list for games you want to buy
- Quickly add/remove games to/from **Backlog** and **Wishlist** with one-click icons on each game card
- Admin panel for managing users, games, and lists

## Game Lists

Each user can have:

- **Multiple `regular` lists** (custom named lists)
- **One `backlog` list** (automatically created if missing)
- **One `wishlist` list** (automatically created if missing)

### List Types

| Type      | Description                          | Unique per user |
|-----------|--------------------------------------|-----------------|
| regular   | User-created custom lists            | No              |
| backlog   | Games the user plans to play         | Yes             |
| wishlist  | Games the user wants to buy          | Yes             |

## Backlog

- The backlog is a **special list** with `type = 'backlog'`.
- It is created automatically when the user visits **My Games**.
- Users can add/remove games to/from their backlog.
- Displayed under the **Backlog** tab on `/my-games`.

## Wishlist

- The wishlist is a **special list** with `type = 'wishlist'`.
- It is created automatically when the user visits **My Games**.
- Users can add/remove games to/from their wishlist.
- Displayed under the **Wishlist** tab on `/my-games`.
- Useful for tracking games you want to buy or try in the future.

## Monthly Game Lists

Monthly game lists are **system lists** that showcase games releasing in a specific month. These lists are automatically created and managed by administrators.

### Characteristics

- **Type**: System lists (`is_system = true`)
- **Visibility**: Public (`is_public = true`)
- **Active Status**: Controlled by `is_active` flag and date ranges (`start_at`, `end_at`)
- **Slug**: Auto-generated from month name (e.g., `january-2026`)
- **Access**: Viewable by all users via `/list/{slug}` route

### Creation

Create monthly lists for a specific year using the Artisan command:

```bash
php artisan games:lists:create-monthly --year=2026
```

This creates 12 lists (one for each month) with:
- Start date: First day of the month
- End date: Last day of the month
- Auto-generated unique slugs
- Public and active flags set

### Display

- **Homepage**: The active monthly list (within current date range) is displayed as "Featured Games"
- **Monthly Releases Page**: Full list view at `/monthly-releases`
- **Public Slug View**: Accessible at `/list/{slug}` for any active/public list

### Management

- Lists are created by admin user (user_id = 1)
- Games are added manually by admin or via seeders
- Lists can be activated/deactivated via `is_active` flag
- Date ranges control when lists appear on the homepage

## Seasonal Banners

The homepage features seasonal event banners displayed at the top of the page, above the Featured Games section.

### Image Specifications

**Location**: Place banner images in `public/images/` directory

**Recommended Sizes**:
- **Aspect Ratio**: 16:9 (rectangular)
- **Two Banners Side-by-Side**:
  - Minimum: 1200px × 675px
  - Optimal: 1600px × 900px
  - Maximum: 1920px × 1080px (Full HD)
- **Single Banner (Full Width)**:
  - Optimal: 1920px × 1080px (Full HD)

**File Format**: JPG or WebP (optimized for web, < 500KB per image recommended)

**Usage**: Update banner data in `resources/views/homepage/index.blade.php`:

```php
<x-seasonal-banners :banners="[
    [
        'image' => '/images/seasonal-event-1.jpg',
        'link' => route('monthly-releases'),
        'title' => 'January Releases',
        'description' => 'Discover the best games releasing this month',
        'alt' => 'January Releases Banner'
    ],
    [
        'image' => '/images/seasonal-event-2.jpg',
        'link' => route('upcoming'),
        'title' => 'Upcoming Games',
        'description' => 'See what\'s coming soon',
        'alt' => 'Upcoming Games Banner'
    ]
]" />
```

**Layout Behavior**:
- **2 Banners**: Displayed side-by-side on desktop, stacked on mobile
- **1 Banner**: Spans full width on all screen sizes
- **Responsive**: Automatically adapts to screen size

**Note**: For retina/high-DPI displays, use 2x resolution (e.g., 1920px × 1080px for standard, 3840px × 2160px for retina).
