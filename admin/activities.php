<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/functions.php';
require_admin();

$msg = ''; $err = '';

/* Refresh an activity's cover = its first media */
function refresh_cover(PDO $pdo, int $aid): void {
    $c = $pdo->prepare("SELECT file_path FROM activity_media WHERE activity_id=? ORDER BY id LIMIT 1");
    $c->execute([$aid]);
    $pdo->prepare("UPDATE activities SET image=? WHERE id=?")->execute([$c->fetchColumn() ?: null, $aid]);
}

/* Save multiple uploaded images into activity_media */
function save_activity_images(PDO $pdo, int $aid, array &$msgs): void {
    if (empty($_FILES['images'])) return;
    $n = count($_FILES['images']['name']);
    for ($i = 0; $i < $n; $i++) {
        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $file = [
            'name'     => $_FILES['images']['name'][$i],
            'tmp_name' => $_FILES['images']['tmp_name'][$i],
            'size'     => $_FILES['images']['size'][$i],
            'error'    => $_FILES['images']['error'][$i],
        ];
        $r = process_simple_image($file, 'activities');
        if ($r['ok']) {
            $pdo->prepare("INSERT INTO activity_media (activity_id, file_path, uploaded_at) VALUES (?,?,?)")
                ->execute([$aid, $r['path'], date('Y-m-d H:i:s')]);
            $msgs[] = e($file['name']) . ' uploaded';
        } else {
            $msgs[] = e($file['name']) . ' FAILED: ' . e($r['error']);
        }
    }
    refresh_cover($pdo, $aid);
}

/* CREATE (with optional multiple images) */
if (isset($_POST['create'])) {
    csrf_verify();
    $pdo->prepare("INSERT INTO activities (title, description, created_at) VALUES (?,?,?)")
        ->execute([trim($_POST['title']), trim($_POST['description']), date('Y-m-d H:i:s')]);
    $aid = (int)$pdo->lastInsertId();
    $notes = [];
    save_activity_images($pdo, $aid, $notes);
    $msg = 'Activity added!' . ($notes ? ' (' . implode(', ', $notes) . ')' : '');
}

/* UPDATE title/description */
if (isset($_POST['update'])) {
    csrf_verify();
    $pdo->prepare("UPDATE activities SET title=?, description=? WHERE id=?")
        ->execute([trim($_POST['title']), trim($_POST['description']), (int)$_POST['id']]);
    $msg = 'Activity updated!';
}

/* ADD MORE MEDIA to an existing activity */
if (isset($_POST['add_media'])) {
    csrf_verify();
    $aid = (int)$_POST['id'];
    $notes = [];
    save_activity_images($pdo, $aid, $notes);
    $msg = 'Media added: ' . implode(', ', $notes);
}

/* DELETE one media item */
if (isset($_POST['del_media'])) {
    csrf_verify();
    $mid = (int)$_POST['media_id'];
    $c = $pdo->prepare("SELECT activity_id, file_path FROM activity_media WHERE id=?");
    $c->execute([$mid]);
    if ($row = $c->fetch()) {
        $abs = __DIR__ . '/../' . $row['file_path'];
        if (is_file($abs) && !str_contains($row['file_path'], 'act_')) @unlink($abs);
        $pdo->prepare("DELETE FROM activity_media WHERE id=?")->execute([$mid]);
        refresh_cover($pdo, (int)$row['activity_id']);
        $msg = 'Photo removed.';
    }
}

/* DELETE whole activity (with all its media) */
if (isset($_POST['delete'])) {
    csrf_verify();
    $aid = (int)$_POST['id'];
    foreach ($pdo->query("SELECT file_path FROM activity_media WHERE activity_id=$aid") as $r) {
        $abs = __DIR__ . '/../' . $r['file_path'];
        if (is_file($abs) && !str_contains($r['file_path'], 'act_')) @unlink($abs);
    }
    $pdo->prepare("DELETE FROM activity_media WHERE activity_id=?")->execute([$aid]);
    $pdo->prepare("DELETE FROM activities WHERE id=?")->execute([$aid]);
    $msg = 'Activity deleted.';
}

/* Edit mode */
$editing = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM activities WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editing = $s->fetch();
}

$activities = $pdo->query("SELECT * FROM activities ORDER BY COALESCE(sort_order,id), id")->fetchAll();
$mediaBy = [];
foreach ($pdo->query("SELECT * FROM activity_media ORDER BY id") as $m) $mediaBy[$m['activity_id']][] = $m;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CSE Activities — EBAUB Gallery Admin</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="icon" type="image/png" href="../assets/cse-logo.png">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="dashboard.php">
      <img class="brand-logo-img" src="../assets/cse-logo.png" alt="EBAUB CSE Logo" width="48" height="48" style="width:48px;height:48px">
      <div class="brand-text"><b>CSE Activities</b><span>Logged in as <?= e($_SESSION['admin_name']) ?></span></div>
    </a>
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
  <?php if ($msg): ?><div class="alert alert-ok"><?= $msg ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

  <div class="panel">
    <h3><?= $editing ? 'Edit Activity (title & description)' : 'Add New Activity' ?></h3>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>
      <div class="field">
        <label>Activity Title</label>
        <input type="text" name="title" required placeholder="e.g. Arduino & Robotics Projects" value="<?= e($editing['title'] ?? '') ?>">
      </div>
      <div class="field">
        <label>Short Description</label>
        <textarea name="description" rows="2" placeholder="One or two lines about this activity..."><?= e($editing['description'] ?? '') ?></textarea>
      </div>
      <?php if (!$editing): ?>
      <div class="field">
        <label>Photos (you can select multiple at once)</label>
        <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp" style="padding:8px">
      </div>
      <?php endif; ?>
      <?php if ($editing): ?>
        <button class="btn btn-primary" name="update" value="1">Save Changes</button>
        <a class="btn btn-danger" href="activities.php">Cancel</a>
      <?php else: ?>
        <button class="btn btn-primary" name="create" value="1">Add Activity</button>
      <?php endif; ?>
    </form>
  </div>

  <h2 class="section-title">All Activities (<?= count($activities) ?>)</h2>
  <?php if (!$activities): ?>
    <div class="empty">No activities yet. Add the first one above.</div>
  <?php else: ?>
  <?php foreach ($activities as $a): $items = $mediaBy[$a['id']] ?? []; ?>
  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
      <div>
        <b style="font-size:16px;color:var(--green-dark)"><?= e($a['title']) ?></b>
        <span style="color:var(--muted);font-size:13px"> · <?= count($items) ?> photo<?= count($items) === 1 ? '' : 's' ?></span>
        <p style="font-size:13.5px;color:var(--muted);margin-top:4px;max-width:640px"><?= e($a['description']) ?></p>
      </div>
      <div>
        <a class="btn" style="padding:7px 13px;font-size:13px;background:#eef3f7" href="activities.php?edit=<?= $a['id'] ?>">Edit</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this activity AND all its photos?')">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $a['id'] ?>">
          <button class="btn btn-danger" style="padding:7px 13px;font-size:13px" name="delete" value="1">Delete</button>
        </form>
      </div>
    </div>

    <?php if ($items): ?>
    <div class="media-grid" style="margin-top:14px;grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
      <?php foreach ($items as $i => $m): ?>
      <div class="media-item media-admin-item" style="cursor:default;aspect-ratio:16/10">
        <img src="../<?= e($m['file_path']) ?>" loading="lazy" alt="">
        <?php if ($i === 0): ?>
          <span style="position:absolute;top:6px;left:6px;background:rgba(19,92,50,.92);color:#fff;font-size:10px;font-weight:700;padding:3px 8px;border-radius:999px">COVER</span>
        <?php endif; ?>
        <form method="post" onsubmit="return confirm('Remove this photo?')">
          <?= csrf_field() ?>
          <input type="hidden" name="media_id" value="<?= $m['id'] ?>">
          <button class="del" name="del_media" value="1" title="Remove">✕</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $a['id'] ?>">
      <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp" required style="font-size:13px">
      <button class="btn btn-primary" style="padding:7px 14px;font-size:13px" name="add_media" value="1">Add Photos</button>
    </form>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</main>

</body>
</html>
