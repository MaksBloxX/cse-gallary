<?php
/**
 * EBAUB CSE Gallery - Database Configuration
 * ------------------------------------------
 * DEMO MODE  : SQLite (works anywhere, no setup needed)
 * XAMPP MODE : MySQL  (uncomment the MySQL block below,
 *              comment out the SQLite block, and import mysql_schema.sql
 *              in phpMyAdmin first)
 */

/**
 * PRODUCTION MODE: set this to false once the site is live on the
 * university server. While true, PHP errors are shown on screen which is
 * useful for development but leaks file paths/queries to visitors.
 */
define('APP_DEBUG', false);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../php-error.log');
}

/* ---------- Session hardening ---------- */
ini_set('session.use_strict_mode', '1');
$sessionSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $sessionSecure,   // only over HTTPS once the site has a certificate
    'httponly' => true,             // JS can't read the session cookie
    'samesite' => 'Lax',
]);
session_start();

/* ---------- Basic security headers ---------- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

define('UPLOAD_DIR', __DIR__ . '/../uploads/events/');
define('UPLOAD_URL', 'uploads/events/');
define('MAX_IMAGE_MB', 5);
define('MAX_VIDEO_MB', 25);

try {
    /* ---------- SQLite (demo / portable) ---------- */
    $pdo = new PDO('sqlite:' . __DIR__ . '/../gallery.db');

    /* ---------- MySQL (for XAMPP / university server) ----------
    $pdo = new PDO(
        'mysql:host=localhost;dbname=cse_gallery;charset=utf8mb4',
        'root',      // username (XAMPP default: root)
        ''           // password (XAMPP default: empty)
    );
    ------------------------------------------------------------- */

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die('Sorry, the site is temporarily unavailable. Please try again shortly.');
}

/* Auto-migration: adds media.sort_order for photo arrangement.
   Runs silently once; safe on both SQLite and MySQL. */
try {
    $pdo->query("SELECT sort_order FROM media LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE media ADD COLUMN sort_order INTEGER NULL");
        $pdo->exec("UPDATE media SET sort_order = id");
    } catch (Throwable $e2) { /* tables not created yet - setup will handle it */ }
}

/* Auto-migration 3: CSE Activities showcase table */
try {
    $pdo->query("SELECT id FROM activities LIMIT 1");
} catch (Throwable $e) {
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS activities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(150) NOT NULL,
                description TEXT,
                image VARCHAR(255) NULL,
                sort_order INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS activities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                image TEXT,
                sort_order INTEGER,
                created_at TEXT
            )");
        }
    } catch (Throwable $e2) {}
}

/* Auto-migration 3b: multiple media per activity */
try {
    $pdo->query("SELECT id FROM activity_media LIMIT 1");
} catch (Throwable $e) {
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS activity_media (
                id INT AUTO_INCREMENT PRIMARY KEY,
                activity_id INT NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS activity_media (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                activity_id INTEGER NOT NULL,
                file_path TEXT NOT NULL,
                uploaded_at TEXT
            )");
        }
        /* copy each activity's existing single image into the new table */
        $pdo->exec("INSERT INTO activity_media (activity_id, file_path)
                    SELECT id, image FROM activities WHERE image IS NOT NULL AND image != ''");
    } catch (Throwable $e2) {}
}

/* Auto-migration 2b: registration deadline column on events */
try {
    $pdo->query("SELECT reg_deadline FROM events LIMIT 1");
} catch (Throwable $e) {
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec("ALTER TABLE events ADD COLUMN reg_deadline DATE NULL");
        } else {
            $pdo->exec("ALTER TABLE events ADD COLUMN reg_deadline TEXT NULL");
        }
    } catch (Throwable $e2) {}
}

/* Auto-migration 2: event registration table (student/teacher participation) */
try {
    $pdo->query("SELECT id FROM registrations LIMIT 1");
} catch (Throwable $e) {
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS registrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                role VARCHAR(10) NOT NULL DEFAULT 'student',
                name VARCHAR(150) NOT NULL,
                semester VARCHAR(30) NULL,
                batch VARCHAR(30) NULL,
                reg_no VARCHAR(50) NULL,
                designation VARCHAR(100) NULL,
                mobile VARCHAR(30) NOT NULL,
                reference VARCHAR(150) NULL,
                event_role TEXT NULL,
                edit_used INTEGER NOT NULL DEFAULT 0,
                registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS registrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                role TEXT NOT NULL DEFAULT 'student',
                name TEXT NOT NULL,
                semester TEXT, batch TEXT, reg_no TEXT, designation TEXT,
                mobile TEXT NOT NULL, reference TEXT,
                event_role TEXT NULL,
                registered_at TEXT
            )");
        }
    } catch (Throwable $e2) {}
}

/* Auto-migration 4: custom participation roles on events */
try {
    $pdo->query("SELECT custom_roles FROM events LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE events ADD COLUMN custom_roles TEXT NULL");
    } catch (Throwable $e2) {}
}

/* Auto-migration 5: event role column on registrations */
try {
    try { $pdo->query("SELECT edit_used FROM registrations LIMIT 1"); } catch (Throwable $e) { try { $pdo->exec("ALTER TABLE registrations ADD COLUMN edit_used INTEGER NOT NULL DEFAULT 0"); } catch (Throwable $ignore) {} }

    $pdo->query("SELECT event_role FROM registrations LIMIT 1");
} catch (Throwable $e) {
    try {
        $pdo->exec("ALTER TABLE registrations ADD COLUMN event_role TEXT NULL");
    } catch (Throwable $e2) {}
}

