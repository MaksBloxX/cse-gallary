# EBAUB CSE Department — Event & Media Management System

A complete, working PHP event and media management system for the Department of CSE, Exim Bank
Agricultural University Bangladesh. Public gallery + secure admin panel with
automatic WebP image compression.

## Demo Login (hidden entrance)
There is **no visible "Admin Login" button** on the public site (by design).
Teachers enter in one of two ways:
1. **Secret footer link:** click the **©** symbol in the footer of any page.
2. **Direct URL:** type `/admin/login.php` in the browser.

| Field | Value |
|---|---|
| Username | `admin` |
| Password | `ebaub123` (change before going live — see Customization Guide) |

## The Pages
| # | Page | File | Who |
|---|------|------|-----|
| 1 | Home / Albums (hero slider, search, filter, activities scroller, pagination) | `index.php` | Public |
| 2 | Activities & Achievements (dedicated showcases, full writeups, multi-photo galleries) | `activities.php` | Public |
| 3 | Single Album + Lightbox + Registration + Role Participation List | `event.php` | Public |
| 4 | Upcoming Events (registration open/deadline indicator) | `upcoming.php` | Public |
| 5 | All Media (every photo/video, filter, pagination) | `all-media.php` | Public |
| 6 | Admin Login (hashed passwords, sessions) | `admin/login.php` | Teachers |
| 7 | Dashboard — Event & Media Management + Homepage Slider + Storage Monitor | `admin/dashboard.php` | Teachers |
| 8 | Media Upload & Management (multi-upload, Set Cover, reorder) | `admin/media.php` | Teachers |
| 9 | Activity Manager (create activities, upload multiple photos, delete) | `admin/activities.php` | Teachers |
| 10 | Event Registrations, Admin Edit & Attendance CSV Export | `admin/registrations.php`, `admin/edit-registration.php` | Teachers |
| 11 | Change Password / Account Security | `admin/change-password.php` | Teachers |

## Key features
- **Auto WebP compression** on upload (GD): a 458 KB JPEG became 49 KB in testing (~89% smaller). Graceful fallback if GD is missing.
- **Homepage slider**: 3 slides managed from the Admin Dashboard, auto-rotate every 4.5s.
- **Activities & Achievements**: create activities, laboratories, projects, contests or research centers from Admin → Activities, add location, facilities/instruments, assigned teachers/persons and multiple photos. The public page provides a desktop activity list with selected details and a mobile selection dropdown.
- **Responsive navigation**: desktop navigation stays horizontal; mobile public and admin pages use a clear hamburger menu with a visible glass-style background.
- **Event registration**: teachers can create multiple Google Form-like student dropdown fields, each with its own title and options (for example, Select Sport, Select Team, and Participation Role). Teachers do not need to fill student-only fields.
- **Participation arrangement**: teachers appear first in the public participation list; students are grouped by the first custom field, with other selected options shown as badges.
- **Combined All Media**: the All Media page includes both Event media and Activity media, with All, Events, Activities, Photos, and Videos filters.
- **Event lifecycle**: registration starts when an event is created, closes after the registration deadline, and the event moves from Upcoming Events to Event Gallery after the event date.
- **Registration correction**: students can use a one-time edit link before the deadline; teachers/admins can edit registrations from the admin panel when further correction is needed.
- **Event page layout**: on desktop, Registration Form and Full Details appear as separate side-by-side boxes; on mobile, Full Details can be opened or closed with a single Full Details arrow control, followed by the registration form and participation list.
- **Footer layout**: public pages keep the footer full-width and flush with the bottom of short pages.
- **Media arrangement**: Set Cover button + move up/down arrows; public pages follow the order. The needed DB column is added by **auto-migration** — no manual SQL.
- **Storage monitor** (admin only): total usage, photos vs videos, largest event, progress bar against quota. Cached 2 minutes.
- **Admin security**: CSRF protection on forms, hardened session cookies, security headers, hidden database errors in production, login slow-down, and a registration honeypot against basic spam bots.
- **Account security**: admins can change their password from the Account / Change Password page; the default password warning disappears after changing it.
- **SEO and sharing**: public pages include page-specific descriptions, canonical URLs, Open Graph tags, and Twitter Card tags for better search and social previews.
- **Performance**: lazy loading, pagination, videos never preload, prepared statements, output escaping, hashed passwords, session regeneration.
- **Fully responsive**: phone / tablet / desktop breakpoints in `assets/style.css`.

---

## CUSTOMIZATION GUIDE — what to change & where

| What you want to change | File | What to edit |
|---|---|---|
| **Admin password** | database (`admins` table) | Generate a hash: `php -r "echo password_hash('NewPass', PASSWORD_DEFAULT);"` then paste it into the `password` field via phpMyAdmin |
| **Add another admin/teacher account** | database (`admins` table) | Insert a new row with username + hashed password (same method as above) |
| **Database connection** (XAMPP / university server) | `includes/config.php` | Comment the SQLite line, uncomment the MySQL block, fill in host / dbname / user / password given by the IT admin |
| **Image / video size limits** | `includes/config.php` | `MAX_IMAGE_MB` (default 5) and `MAX_VIDEO_MB` (default 25) |
| **Storage quota shown in dashboard** | `admin/dashboard.php` | `$storageLimitGB = 5;` — set to the real quota (e.g. 10) |
| **Event categories** (Seminar, Workshop...) | `admin/dashboard.php` | The array in the Category `<select>`: `['Seminar','Workshop','Contest','Study Tour','Cultural','Other']` |
| **Homepage slider images** | Admin Dashboard (no code!) | Login → "Homepage Slider" panel → Set Slide 1/2/3 |
| **Hero title / subtitle text** | `index.php` | The `<h1>` and `<p>` inside `<section class="hero">` |
| **Main Website link** | `index.php`, `all-media.php` | The `https://ebaub.ac.bd/` href in the nav |
| **Facebook / LinkedIn / WhatsApp links** | `index.php`, `event.php`, `all-media.php` | The three `<a href=...>` in the footer social block |
| **Footer address / email / credit** | `index.php`, `event.php`, `all-media.php` | The text inside `<footer class="site-footer">` |
| **Theme colors** (green, gold...) | `assets/style.css` | The `:root { --green: ... }` variables at the top |
| **Slider speed** | `index.php` | `setInterval(... , 4500)` — milliseconds |
| **Events per page / media per page** | `index.php` / `all-media.php` | `$perPage = 12;` / `$perPage = 24;` |
| **WebP quality / max resolution** | `includes/functions.php` | `imagewebp($src, ..., 80)` and `$maxDim = 1600` |

> Rule of thumb: after ANY change, press `Ctrl+F5` in the browser so the old
> cache does not fool you.

---

## DEPLOYING TO THE UNIVERSITY SERVER (cPanel)

### What goes to the server?
Upload the **entire `cse-gallery` folder** — every file and subfolder:

```
cse-gallery/
├── index.php, event.php, all-media.php, setup.php (setup.php optional)
├── admin/            (login, dashboard, activities, media, registrations, account files)
├── includes/         (config.php, functions.php)
├── assets/            (style.css, cse-logo.png, exim-logo.png)
├── .htaccess          (root security rules)
├── includes/          (config.php, functions.php, .htaccess)
├── uploads/           (events, activities, hero media, .htaccess)
└── mysql_schema.sql   (only needed once, for phpMyAdmin import)
```

Do NOT upload: `gallery.db` (that is only the local SQLite demo).

### Are the files "connected" to the university website? How does it work?
The gallery is **self-contained**: all its files link to each other with
relative paths, so it works from any folder. It connects to two things only:

1. **The database** — through ONE file: `includes/config.php`. On the
   university server the IT admin gives you MySQL credentials; you put them
   in the MySQL block of `config.php`. That is the only "wire" to plug in.
2. **The main university website** — there is NO code connection and none is
   needed. The gallery lives at its own URL. The IT admin simply adds a
   normal menu link on the main site pointing to the gallery.

### Step-by-step on cPanel
1. Ask the IT admin for: cPanel access (or FTP), a MySQL database + user, and
   where to place the folder (usually `public_html/`).
2. Upload the whole `cse-gallery` folder into `public_html/`
   → the gallery URL becomes `https://ebaub.ac.bd/cse-gallery`
   (or a subdomain like `cse.ebaub.ac.bd/gallery` if IT prefers).
3. In cPanel → phpMyAdmin: create the database, import `mysql_schema.sql`.
4. Edit `includes/config.php`: switch to the MySQL block with the credentials.
   Note: on shared hosting the DB name/user usually get a prefix like
   `ebaub_gallery` / `ebaub_admin` — use exactly what cPanel shows.
5. Make sure the `uploads/` folder is writable (cPanel File Manager →
   permissions `755`, or `775` if uploads fail).
6. Change the admin password (see Customization Guide).
7. Test: open the gallery URL, log in, create a test event, upload a photo,
   delete the test event.
8. Give the IT admin the final URL to link from the main website menu.

### After going live — routine use (no code ever needed)
Teachers only use the Admin Panel: create events, upload photos/videos,
set covers, arrange order, change slider images, watch the storage monitor.

---

## Run it on your PC with XAMPP (step-by-step)
1. Install **XAMPP**, start **Apache** and **MySQL**.
2. Copy this whole `cse-gallery` folder into `C:\xampp\htdocs\`.
3. `http://localhost/phpmyadmin` → create database `cse_gallery` → Import → `mysql_schema.sql`.
4. In `includes/config.php`: comment the SQLite line, uncomment the MySQL block (user `root`, empty password).
5. php.ini: enable `extension=gd`, and for multi-upload set
   `upload_max_filesize=25M`, `post_max_size=120M`, `max_file_uploads=40`.
   Restart Apache after saving. If using your current XAMPP installation, the
   project folder is `D:\\Program Files\\XMDL\\htdocs\\cse-gallery`.
6. Visit `http://localhost/cse-gallery`.

## Deployment path (tell your teacher this)
**Localhost (XAMPP) → Free cPanel staging (e.g. InfinityFree) → University server**
