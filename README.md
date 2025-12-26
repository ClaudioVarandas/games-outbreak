# Games Outbreak Project

## Setup

### Initial Setup

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



## project ideas

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
