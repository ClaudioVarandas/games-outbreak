


### OLD project ideas

# Go-Games Project

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

## Admin Panel

An admin panel is available at `/admin` for users with the `Admin` role. It provides interfaces for managing core application data.

**Features:**

*   **User Management:** View, create, edit, and delete users.
*   **Game Management:** View, create, edit, and delete games.
*   **Game List Management:** View, create, edit, and delete game lists.

**Access:**

*   Only users with the `Admin` role (defined in `App\Enums\UserRole`) can access `/admin` and its sub-routes.
*   Access is controlled via the `App\Http\Middleware\EnsureUserIsAdmin` middleware.
