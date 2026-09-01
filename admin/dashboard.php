<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/functions.php';
require_admin();

$msg = ''; $err = '';

/* Nudge whoever is logged in to change the shipped demo password */
$pwCheck = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
$pwCheck->execute([$_SESSION['admin_id']]);
$usingDemoPassword = password_verify('ebaub123', (string)$pwCheck->fetchColumn());

/* Helper to parse custom fields submitted from admin form */
function parse_submitted_custom_fields(): ?string {
    $customFields = [];
    if (!empty($_POST['cf_label']) && is_array($_POST['cf_label'])) {
        foreach ($_POST['cf_label'] as $idx => $lbl) {
            $lbl = trim($lbl);
            $rawOpts = trim($_POST['cf_options'][$idx] ?? '');
            $opts = array_values(array_filter(array_map('trim', explode(',', $rawOpts))));
            if ($lbl !== '' && !empty($opts)) {
                $customFields[] = [
                    'label' => $lbl,
                    'options' => $opts
                ];
            }
        }
    }
    return $customFields ? json_encode($customFields, JSON_UNESCAPED_UNICODE) : null;
}

/* CREATE */
if (isset($_POST['create'])) {
    csrf_verify();
    $customRoles = parse_submitted_custom_fields();
    $stmt = $pdo->prepare("INSERT INTO events (title, category, event_date, reg_deadline, description, custom_roles, created_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([trim($_POST['title']), trim($_POST['category']), $_POST['event_date'], ($_POST['reg_deadline'] ?? '') ?: null, trim($_POST['description']), $customRoles, date('Y-m-d H:i:s')]);
    $msg = 'Event created successfully!';
}

/* UPDATE */
if (isset($_POST['update'])) {
    csrf_verify();
    $customRoles = parse_submitted_custom_fields();
    $stmt = $pdo->prepare("UPDATE events SET title=?, category=?, event_date=?, reg_deadline=?, description=?, custom_roles=? WHERE id=?");
    $stmt->execute([trim($_POST['title']), trim($_POST['category']), $_POST['event_date'], ($_POST['reg_deadline'] ?? '') ?: null, trim($_POST['description']), $customRoles, (int)$_POST['id']]);
    $msg = 'Event updated!';
}

/* DELETE (event + all its media files) */
if (isset($_POST['delete'])) {
    csrf_verify();
    $id = (int)$_POST['id'];
    $files = $pdo->prepare("SELECT file_path FROM media WHERE event_id=?");
    $files->execute([$id]);
    foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $f) {
        $abs = __DIR__ . '/../' . $f;
        if (is_file($abs) && !str_contains($f, 'demo_')) unlink($abs); // keep demo files
    }
    $pdo->prepare("DELETE FROM media WHERE event_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$id]);
    $msg = 'Event and all its media deleted.';
}

/* HERO SLIDE UPLOAD (homepage slider, 3 fixed slots) */
if (isset($_POST['hero_upload']) && !empty($_FILES['hero_file']['name'])) {
    csrf_verify();
    $r = process_hero_upload($_FILES['hero_file'], (int)($_POST['slot'] ?? 1));
    if ($r['ok']) $msg = 'Slider image updated!';
    else $err = $r['error'];
}

/* Edit mode? */
$editing = null;
$editingCustomFields = [];
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM events WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editing = $s->fetch();
    if ($editing) {
        $editingCustomFields = get_event_custom_fields($editing);
    }
}

$events = $pdo->query("
    SELECT e.*, (SELECT COUNT(*) FROM media m WHERE m.event_id=e.id) AS media_count,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id) AS reg_count
    FROM events e ORDER BY e.event_date DESC")->fetchAll();

/* ---- Storage statistics (admin only, cached 2 min for performance) ---- */
function fmt_bytes($b) {
    if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576)    return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)       return round($b / 1024) . ' KB';
    return (int)$b . ' B';
}

$storageLimitGB = 5;   /* <- university server quota; adjust here if needed */
$cacheFile = sys_get_temp_dir() . '/ebaub_gallery_stats.json';
$stats = null;
if (is_file($cacheFile) && time() - filemtime($cacheFile) < 120) {
    $stats = json_decode((string)file_get_contents($cacheFile), true);
}
if (!is_array($stats) || !isset($stats['total']) || !isset($stats['f_act'])) {
    $base = realpath(__DIR__ . '/..');
    $totalBytes = 0;
    $byFolder = ['events' => 0, 'hero' => 0, 'activities' => 0, 'other' => 0];
    $up = $base . '/uploads';
    if (is_dir($up)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($up, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $sz = $f->getSize();
            $totalBytes += $sz;
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($up) + 1));
            $top = explode('/', $rel)[0];
            $byFolder[isset($byFolder[$top]) ? $top : 'other'] += $sz;
        }
    }
    $imgB = $vidB = 0; $imgC = $vidC = 0; $perEvent = [];
    foreach ($pdo->query("SELECT m.file_path, m.file_type, e.title FROM media m JOIN events e ON e.id = m.event_id") as $r) {
        $p = $base . '/' . $r['file_path'];
        $sz = is_file($p) ? (int)filesize($p) : 0;
        if ($r['file_type'] === 'video') { $vidB += $sz; $vidC++; } else { $imgB += $sz; $imgC++; }
        $perEvent[$r['title']] = ($perEvent[$r['title']] ?? 0) + $sz;
    }
    arsort($perEvent);
    $stats = [
        'total'     => $totalBytes,
        'img_b'     => $imgB, 'img_c' => $imgC,
        'vid_b'     => $vidB, 'vid_c' => $vidC,
        'top_event' => $perEvent ? array_key_first($perEvent) : null,
        'top_bytes' => $perEvent ? (int)reset($perEvent) : 0,
        'disk_free' => (float)(@disk_free_space($base) ?: 0),
        'f_events'  => $byFolder['events'],
        'f_hero'    => $byFolder['hero'],
        'f_act'     => $byFolder['activities'],
    ];
    @file_put_contents($cacheFile, json_encode($stats));
}
$limitBytes = $storageLimitGB * 1073741824;
$usedPct = min(100, round($stats['total'] / $limitBytes * 100, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — EBAUB CSE Gallery Admin</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="icon" type="image/png" href="../assets/cse-logo.png">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="dashboard.php">
      <img class="brand-logo-img" src="../assets/cse-logo.png" alt="EBAUB CSE Logo" width="48" height="48" style="width:48px;height:48px">
      <div class="brand-text"><b>Admin Dashboard</b><span>Logged in as <?= e($_SESSION['admin_name']) ?></span></div>
    </a>
    <button type="button" class="admin-menu-toggle" onclick="toggleAdminMenu()" aria-label="Open menu">☰</button>
    <nav class="nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="activities.php">Activities</a>
      <a href="registrations.php">Registrations</a>
      <a href="../index.php" target="_blank">View Site</a>
      <a href="change-password.php">Account</a>
      <a class="btn-login" href="logout.php">Logout</a>
    </nav>
  </div>
</header>

<main class="container">
  <?php if ($usingDemoPassword): ?>
    <div class="alert alert-error">
      ⚠️ You're logged in with the <b>default demo password</b> that ships with this project.
      Anyone who reads the README knows it — please
      <a href="change-password.php" style="color:inherit;text-decoration:underline;font-weight:700">change it now</a>
      before putting the site online.
    </div>
  <?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin-bottom:26px">
    <div class="panel" style="margin-bottom:0">
      <div style="font-size:12px;color:var(--muted);font-weight:700;letter-spacing:.5px">Storage Used</div>
      <div style="font-size:24px;font-weight:800;color:var(--green-dark);margin:6px 0"><?= fmt_bytes($stats['total']) ?> / <?= $storageLimitGB ?> GB</div>
      <div style="background:#e6ecef;border-radius:99px;height:10px;overflow:hidden">
        <div style="width:<?= $usedPct ?>%;background:linear-gradient(90deg,#1b7a43,#2f9c5c);height:100%"></div>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:6px"><?= $usedPct ?>% of <?= $storageLimitGB ?> GB used · Server free: <?= fmt_bytes($stats['disk_free']) ?></div>
      <div style="font-size:11.5px;color:var(--muted);margin-top:4px"><b>Events:</b> <?= fmt_bytes($stats['f_events'] ?? 0) ?> · <b>Slider:</b> <?= fmt_bytes($stats['f_hero'] ?? 0) ?> · <b>Activities:</b> <?= fmt_bytes($stats['f_act'] ?? 0) ?></div>
    </div>
    <div class="panel" style="margin-bottom:0">
      <div style="font-size:12px;color:var(--muted);font-weight:700;letter-spacing:.5px">PHOTOS</div>
      <div style="font-size:24px;font-weight:800;color:var(--green-dark);margin:6px 0"><?= (int)$stats['img_c'] ?></div>
      <div style="font-size:12.5px;color:var(--muted)"><?= fmt_bytes($stats['img_b']) ?> on disk</div>
    </div>
    <div class="panel" style="margin-bottom:0">
      <div style="font-size:12px;color:var(--muted);font-weight:700;letter-spacing:.5px">VIDEOS</div>
      <div style="font-size:24px;font-weight:800;color:var(--green-dark);margin:6px 0"><?= (int)$stats['vid_c'] ?></div>
      <div style="font-size:12.5px;color:var(--muted)"><?= fmt_bytes($stats['vid_b']) ?> on disk</div>
    </div>
  </div>

  <div class="panel">
    <h3><?= $editing ? 'Edit Event' : 'Create New Event' ?></h3>
    <form method="post">
      <?= csrf_field() ?>
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>
      <div class="form-row">
        <div class="field">
          <label>Event Title</label>
          <input type="text" name="title" required placeholder="e.g. Robotics Workshop 2025" value="<?= e($editing['title'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Category</label>
          <select name="category">
            <?php foreach (['Seminar','Workshop','Contest','Study Tour','Cultural','Other'] as $c): ?>
              <option <?= ($editing['category'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label>Event Date (program day)</label>
          <input type="date" name="event_date" required value="<?= e($editing['event_date'] ?? date('Y-m-d')) ?>">
          <div style="font-size:12px;color:var(--muted);margin-top:5px">After the event date, it moves from Upcoming Events to Event Gallery.</div>
        </div>
        <div class="field">
          <label>Registration Deadline (optional)</label>
          <input type="date" name="reg_deadline" value="<?= e($editing['reg_deadline'] ?? '') ?>">
          <div style="font-size:12px;color:var(--muted);margin-top:5px">Registration starts as soon as you create the event, and closes after this date. Leave empty = open until the day before the event</div>
        </div>
      </div>
      <div style="background:#f8faf9;border:1.5px dashed #b9d7c4;border-radius:10px;padding:16px 18px;margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <label style="font-weight:700;font-size:14px;color:var(--green-dark);margin:0">Custom Registration Fields</label>
          <button type="button" class="btn" style="padding:5px 12px;font-size:12.5px;background:#eef6f1;color:var(--green-dark);border:1px solid var(--green)" onclick="addCustomField()">+ Add Field</button>
        </div>
        <div style="font-size:12.5px;color:var(--muted);margin-bottom:12px">
          Add custom dropdown fields for students (e.g. Field 1 Title: <b>Select Sport</b> with Options: <b>Cricket, Football, Badminton</b> · Field 2 Title: <b>Select Team</b> with Options: <b>Team A, Team B</b>). Leave empty if not needed.
        </div>
        <div id="customFieldsContainer"></div>
      </div>
      <div class="field">
        <label>Description</label>
        <textarea name="description" rows="3" placeholder="Short description of the event…"><?= e($editing['description'] ?? '') ?></textarea>
      </div>
      <?php if ($editing): ?>
        <button class="btn btn-primary" name="update" value="1">Save Changes</button>
        <a class="btn btn-danger" href="dashboard.php">Cancel</a>
      <?php else: ?>
        <button class="btn btn-primary" name="create" value="1">Create Event</button>
      <?php endif; ?>
    </form>
  </div>

  <div class="panel">
    <h3>Homepage Slider (3 background images)</h3>
    <p style="color:var(--muted);font-size:13.5px;margin-bottom:14px">
      These three images rotate in the homepage banner. Use wide photos, e.g. prize giving,
      student group photo, lab session. Images are auto-compressed.
    </p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px">
      <?php for ($n = 1; $n <= 3; $n++):
        $cur = glob(__DIR__ . '/../uploads/hero/slide_' . $n . '.*'); ?>
      <div>
        <div style="aspect-ratio:16/7;background:#eef2f4;border-radius:10px;overflow:hidden;margin-bottom:8px">
          <?php if ($cur): ?>
            <img src="../uploads/hero/<?= e(basename($cur[0])) ?>?v=<?= filemtime($cur[0]) ?>"
                 alt="Slide <?= $n ?>" style="width:100%;height:100%;object-fit:cover" loading="lazy">
          <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);font-size:13px">Empty slot</div>
          <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="slot" value="<?= $n ?>">
          <input type="file" name="hero_file" accept=".jpg,.jpeg,.png,.webp" required style="font-size:13px;margin-bottom:8px;max-width:100%">
          <button class="btn btn-primary" style="padding:7px 13px;font-size:13px" name="hero_upload" value="1">Set Slide <?= $n ?></button>
        </form>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <h2 class="section-title">All Events (<?= count($events) ?>)</h2>
  <table class="admin-table">
    <tr><th>Title</th><th>Category</th><th>Date</th><th>Status</th><th>Media</th><th>Regs</th><th style="width:280px">Actions</th></tr>
    <?php foreach ($events as $ev): ?>
    <tr>
      <td>
        <b><?= e($ev['title']) ?></b>
        <?php
          $evFields = get_event_custom_fields($ev);
          if ($evFields):
        ?>
          <div style="font-size:11.5px;color:var(--green);margin-top:3px">
            <?= count($evFields) ?> field(s): <?= e(implode(' · ', array_column($evFields, 'label'))) ?>
          </div>
        <?php endif; ?>
      </td>
      <td><?= e($ev['category']) ?></td>
      <td><?= date('d M Y', strtotime($ev['event_date'])) ?></td>
      <td>
        <?php if ($ev['event_date'] > date('Y-m-d')): ?>
          <?php if (!empty($ev['reg_deadline']) && date('Y-m-d') > $ev['reg_deadline']): ?>
            <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:800;background:#fdecec;color:#c0392b">REG CLOSED</span>
          <?php else: ?>
            <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:800;background:#fff7dd;color:#8a6d00">UPCOMING</span>
          <?php endif; ?>
        <?php elseif ($ev['event_date'] === date('Y-m-d')): ?>
          <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:800;background:var(--green-light);color:var(--green-dark)">TODAY</span>
        <?php else: ?>
          <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:800;background:#eef2f4;color:var(--muted)">PAST</span>
        <?php endif; ?>
      </td>
      <td><?= $ev['media_count'] ?> items</td>
      <td><a href="registrations.php?event=<?= $ev['id'] ?>" style="font-weight:700;color:var(--green)"><?= $ev['reg_count'] ?></a></td>
      <td>
        <a class="btn btn-primary" style="padding:7px 13px;font-size:13px" href="media.php?event=<?= $ev['id'] ?>">Manage Media</a>
        <a class="btn" style="padding:7px 13px;font-size:13px;background:#eef3f7" href="dashboard.php?edit=<?= $ev['id'] ?>">Edit</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this event AND all its photos/videos?')">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $ev['id'] ?>">
          <button class="btn btn-danger" style="padding:7px 13px;font-size:13px" name="delete" value="1">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</main>

<script>
function addCustomField(label = '', options = '') {
  const container = document.getElementById('customFieldsContainer');
  if (!container) return;
  const row = document.createElement('div');
  row.className = 'cf-row';
  row.style.cssText = 'background:#fff;border:1px solid #dfe6ea;border-radius:8px;padding:12px;margin-bottom:10px;display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;';

  const lblEsc = label.replace(/"/g, '&quot;');
  const optEsc = options.replace(/"/g, '&quot;');

  row.innerHTML = `
    <div style="flex:1 1 220px;min-width:180px">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Field Title / Question</label>
      <input type="text" name="cf_label[]" placeholder="e.g. Select Sport / Select Team" value="${lblEsc}" required style="width:100%;padding:8px 12px;font-size:13.5px;border:1px solid #d0d7de;border-radius:6px">
    </div>
    <div style="flex:2 1 320px;min-width:240px">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Options (comma-separated)</label>
      <input type="text" name="cf_options[]" placeholder="e.g. Cricket, Football, Badminton, Ludu" value="${optEsc}" required style="width:100%;padding:8px 12px;font-size:13.5px;border:1px solid #d0d7de;border-radius:6px">
    </div>
    <button type="button" class="btn btn-danger" style="padding:8px 12px;font-size:12px;margin-top:20px" onclick="this.closest('.cf-row').remove()">Remove</button>
  `;
  container.appendChild(row);
}

// Pre-populate if editing existing fields
<?php if (!empty($editingCustomFields)): ?>
  <?php foreach ($editingCustomFields as $ecf): ?>
    addCustomField(<?= json_encode($ecf['label']) ?>, <?= json_encode(implode(', ', $ecf['options'])) ?>);
  <?php endforeach; ?>
<?php endif; ?>
</script>

<script>function toggleAdminMenu(){document.querySelector('.site-header .nav')?.classList.toggle('admin-nav-open');}</script>
</body>
</html>
