# Crowd Pulse

A web application for crowd reaction management.

## Overview

CrowdPulse is a web application for directing and managing audience emotions (applause, cheering, booing, murmuring, stomping, etc.) in a coordinated or choreographed manner. It supports both live and simulated audience modes, real-time admin controls and audience segmentation.

## Project Structure

The project follows an MVC flow:

- User visits: `http://localhost/Project/public/`
- `public/index.php` → front controller (routes all requests)
- `app/Controllers/HomeController.php` → business logic
- `app/Views/index.php` → HTML page (user sees this)

```plaintext
README.md               # Project documentation

app/
├── Controllers/        # Handle HTTP requests (route entrypoints)
├── Database/           # Database connection and management
├── Models/             # Models (DB representations like User, Command)
├── Services/           # App logic (CommandService, ValidationService)
└── Views/              # Views or templates shown to users

config/
├── database.php        # Database configuration
├── schema.sql          # SQL schema for database setup
├── init_db.php         # Database initialization script

public/
├── index.php           # App entry point
├── css/                # CSS styles
├── js/                 # JavaScript for client-side functionality
├── api/                # API endpoints
├── audio/              # Audio files for reactions
└── media/              # Images and other media assets

routes/
└── routes.php          # Route definitions (connect URLs to controllers)
└── Router.php          # Core routing logic that maps HTTP requests to controllers
```

Apache server start point: `Project/public`

## Database Setup

The application uses MySQL for data storage. Follow these steps to set up the database:

1. Make sure you have XAMPP installed with MySQL service running
2. Update database configuration in `config/database.php` if needed (default: localhost, root, no password)
3. Run the database initialization script:

   ```
   php config/init_db.php
   ```

## Features & Functional Overview

### Audience Reaction Commands

- Admins can send predefined reaction commands (e.g., "Applaud now", "Boo loudly in 3...2...1", "Cheer for 5 seconds").
- Commands can be triggered by countdowns, on-screen instructions, gestures, or manual admin input.

### Visual & Audio Cues

- Commands appear on audience screens (mobile/web-based).
- Optional synchronized lighting/sound cues (e.g., flash or tone before a reaction).

### Simulated Audience Mode

- Virtual audience members have pre-recorded sound files for each reaction type.
- System randomly selects a variation, plays it at a randomized volume and time offset, creating a realistic ripple effect.

### Audience Segmentation

- Participants can be grouped by gender, seating zone, arrival time, or custom tags (VIPs, fans, guests).
- Reactions can be targeted to specific groups.

### Gamification & Points System

- In live mode, audience members earn points for participation and timing.
- Points unlock new reactions, customize avatars/sounds, or can be gifted.
- Leaderboards and statistics encourage engagement.

### Roles & Permissions

- Roles: Active Participant, Passive Viewer, Group Leader.
- Invitations can be based on points, participation, or admin selection.

### Admin & Moderation Panel

- Schedule/configure events, assign roles, send live commands, monitor engagement, view analytics, and manage participants.

### Sound Intensity Monitoring

- Each reaction has an intensity scale (0-100).
- Optional live decibel monitoring for physical/hybrid events.

### Public Participation Link

- Quick-join link for each event.
- Participants can join as active audience or observer (if not eligible).

## Command Structure

Commands follow this structure:

```json
{
  "type": "command",
  "command": "<command_type>",
  "countdown": <countdown_seconds>,
  "intensity": <intensity_value>,
  "duration": <duration_seconds>,
  "targetGroups": ["group1", "group2"],
  "targetTags": ["tag1", "tag2"],
  "targetGender": "<gender>",
  "message": "<custom_message>"
}
```

## Available Commands

- clap/applause: Users clap their hands
- cheer: Users cheer vocally
- boo: Users express disapproval
- murmur: Users talk quietly among themselves
- stomp: Users stomp their feet
- silence: Users remain quiet

## How It Works

- Admin commands are stored in the database.
- Users' browsers connect to an SSE endpoint that streams commands in real-time.
- Browsers without SSE support fall back to periodic polling.
- No dependencies required: uses Server-Sent Events (SSE) instead of WebSockets.

## Browser Compatibility

Server-Sent Events are supported by all modern browsers:

- Chrome 9+
- Firefox 6+
- Safari 5+
- Edge 12+
- Opera 11.5+

For older browsers (e.g., IE), the app falls back to JSON polling.

## Recent Optimizations

The codebase has been cleaned up to remove:

- Unused placeholder files
- Redundant code in the SSE server
- Unnecessary user agent tracking
- Optimized database queries in the admin panel
- Improved performance in the CommandService

## Portable Setup Instructions

This project can be run on any machine with XAMPP installed without any configuration changes. Follow these steps:

### Prerequisites

- XAMPP installed (make sure PHP is in your system PATH)
- MySQL database (included with XAMPP)

### Running the Application

#### Windows

1. Clone or download this project to any directory on your machine
2. Double-click the `run.bat` file
3. Open your browser and navigate to <http://localhost:8080>

#### Mac/Linux

1. Clone or download this project to any directory on your machine
2. Make the run script executable: `chmod +x run.sh`
3. Execute the script: `./run.sh`
4. Open your browser and navigate to <http://localhost:8080>

### Database Setup

1. Start MySQL from your XAMPP control panel
2. Run the database setup script (from your browser): <http://localhost:8080/config/init_db.php>
   - This will create the necessary database and tables

### Stopping the Server

- Press `Ctrl+C` in the terminal/command prompt window to stop the PHP server

### Notes

- The project will use port 8080 by default. If this port is already in use, modify the port number in the run script.
- No Apache virtual hosts configuration is needed
- You can run this project from any directory

## Deployment Instructions

### Running the Project in XAMPP htdocs

1. Clone or download this project into your XAMPP's `htdocs` directory:

   ```
   C:\xampp\htdocs\crowd-pulse\
   ```

2. Start Apache and MySQL services in XAMPP Control Panel.

3. Access the application through your browser:

   ```
   http://localhost/crowd-pulse/public/
   ```

4. The application will automatically detect that it's running in a subfolder and adjust all paths accordingly.

### Database Configuration

1. Make sure your database configuration in `config/database.php` is correct.

2. If needed, run the database initialization script:

   ```
   http://localhost/crowd-pulse/config/init_db.php
   ```

3. You can test your database connection using:

   ```
   http://localhost/crowd-pulse/config/test_db_connection.php
   ```

### Troubleshooting

If you encounter any issues:

1. Check that all files have proper permissions.

2. Clear your browser cache if you see outdated CSS or JavaScript.

3. Check PHP error logs for any server-side issues.

## Development Notes

The application has been simplified to work directly from the XAMPP htdocs directory without requiring virtual hosts or .htaccess files. It uses a dynamic base path detection system to ensure all URLs work correctly regardless of the installation folder.

Key features:

1. The Router automatically detects the base path and handles subfolder installations.
2. All URLs in views and JavaScript files are generated dynamically.
3. A BASE_PATH constant is defined and used throughout the application.
4. Form validation is enabled for login and registration.

## Original Authors

Bozhidar Tomov, Mira Velikova

## Database Schema Updates

### Recent Schema Changes (2023)

The database schema has been simplified and optimized:

1. **Unified Categories Field**:
   - Previously separate `user_groups` and `user_tags` tables have been combined into a single `categories` field in the `users` table.
   - Categories are now stored as a comma-separated string, simplifying queries and reducing joins.

2. **Schema Consolidation**:
   - All schema definitions are now in a single source (`schema.sql`).
   - The database initialization process has been streamlined.

3. **Migration Tool**:
   - A migration script (`config/migrate_categories.php`) is available to move data from the old `user_categories` table to the new `categories` field in the `users` table.
   - Run this script if you're upgrading from a previous version.

4. **Automatic Database Creation**:
   - The application now automatically creates the database if it doesn't exist when first accessed.
   - No manual database setup is required for new installations.

### Running the Migration

If you're upgrading from a previous version:

1. Make sure your database configuration in `config/database.php` is correct.
2. Run the migration script:

   ```
   http://localhost/Project/config/migrate_categories.php
   ```

   or via command line:

   ```
   php config/migrate_categories.php
   ```
