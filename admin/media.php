<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/functions.php';
require_admin();

$eventId = (int)($_GET['event'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM events WHERE id=?");
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) { header('Location: dashboard.php'); exit; }

$msgs = [];

/* UPLOAD (multiple files) */
if (!empty($_FILES['files'])) {
    $count = count($_FILES['files']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $file = [
            'name'     => $_FILES['files']['name'][$i],
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
            'size'     => $_FILES['files']['size'][$i],
            'error'    => $_FILES['files']['error'][$i],
        ];
        $result = process_upload($file);   // compress -> WebP magic happens here
        if ($result['ok']) {
            $pdo->prepare("INSERT INTO media (event_id, file_path, file_type, uploaded_at) VALUES (?,?,?,?)")
                ->execute([$eventId, $result['path'], $result['type'], date('Y-m-d H:i:s')]);
            $msgs[] = ['ok', e($file['name']) . ' uploaded & optimized'];
        } else {
            $msgs[] = ['error', e($file['name']) . ': ' . e($result['error'])];
        }
    }
}

/* DELETE single media */
if (isset($_POST['delete_media'])) {
    $mid = (int)$_POST['media_id'];
    $s = $pdo->prepare("SELECT file_path FROM media WHERE id=? AND event_id=?");
    $s->execute([$mid, $eventId]);
    if ($f = $s->fetch()) {
        $abs = __DIR__ . '/../' . $f['file_path'];
        if (is_file($abs) && !str_contains($f['file_path'], 'demo_')) unlink($abs);
        $pdo->prepare("DELETE FROM media WHERE id=?")->execute([$mid]);
        $msgs[] = ['ok', 'Media deleted.'];
    }
}

/* ARRANGE: set as cover / move up / move down */
if (isset($_POST['set_cover']) || isset($_POST['move_up']) || isset($_POST['move_dn'])) {
    $mid = (int)($_POST['media_id'] ?? 0);
    $rows = $pdo->prepare("SELECT id FROM media WHERE event_id=? ORDER BY COALESCE(sort_order,id), id");
    $rows->execute([$eventId]);
    $ids = array_map('intval', $rows->fetchAll(PDO::FETCH_COLUMN));
    $pos = array_search($mid, $ids, true);
    if ($pos !== false) {
        if (isset($_POST['set_cover'])) {
            array_splice($ids, $pos, 1);
            array_unshift($ids, $mid);
            $msgs[] = ['ok', 'Cover photo updated.'];
        } elseif (isset($_POST['move_up']) && $pos > 0) {
            [$ids[$pos - 1], $ids[$pos]] = [$ids[$pos], $ids[$pos - 1]];
            $msgs[] = ['ok', 'Moved up.'];
        } elseif (isset($_POST['move_dn']) && $pos < count($ids) - 1) {
            [$ids[$pos + 1], $ids[$pos]] = [$ids[$pos], $ids[$pos + 1]];
            $msgs[] = ['ok', 'Moved down.'];
        }
        $upd = $pdo->prepare("UPDATE media SET sort_order=? WHERE id=? AND event_id=?");
        foreach ($ids as $i => $id) $upd->execute([($i + 1) * 10, $id, $eventId]);
    }
}

$media = $pdo->prepare("SELECT * FROM media WHERE event_id=? ORDER BY COALESCE(sort_order,id), id");
$media->execute([$eventId]);
$media = $media->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Media — <?= e($event['title']) ?></title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="icon" type="image/png" href="../assets/cse-logo.png">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="dashboard.php">
      <img class="brand-logo-img" src="../assets/cse-logo.png" alt="EBAUB CSE Logo" width="48" height="48" style="width:48px;height:48px">
      <div class="brand-text"><b>Media Manager</b><span><?= e($event['title']) ?></span></div>
    </a>
    <nav class="nav">
      <a href="dashboard.php">← Dashboard</a>
      <a class="btn-login" href="logout.php">Logout</a>
    </nav>
  </div>
</header>

<main class="container">
  <?php foreach ($msgs as [$type, $text]): ?>
    <div class="alert alert-<?= $type === 'ok' ? 'ok' : 'error' ?>"><?= $text ?></div>
  <?php endforeach; ?>

  <div class="panel">
    <h3>Upload Photos / Videos</h3>
    <p style="color:var(--muted);font-size:13.5px;margin-bottom:14px">
      Images (JPG/PNG, max <?= MAX_IMAGE_MB ?>MB) are <b>auto-compressed to WebP</b>.
      Videos (MP4, max <?= MAX_VIDEO_MB ?>MB).
    </p>
    <form method="post" enctype="multipart/form-data" id="uploadForm">
      <div class="dropzone" id="dropzone">
        <b>Drag &amp; drop files here</b> or click to browse<br>
        <span id="fileCount" style="font-size:13px"></span>
        <input type="file" name="files[]" id="fileInput" multiple accept=".jpg,.jpeg,.png,.webp,.mp4" style="display:none">
      </div>
      <button class="btn btn-primary" style="margin-top:14px" type="submit">Upload Selected Files</button>
    </form>
  </div>

  <h2 class="section-title">Album Media (<?= count($media) ?>)</h2>
  <?php if (!$media): ?>
    <div class="empty">No media yet. Upload some photos above!</div>
  <?php else: ?>
  <?php
  /* the cover = first image in the arranged order */
  $coverId = null;
  foreach ($media as $mm) if ($mm['file_type'] === 'image') { $coverId = $mm['id']; break; }
  ?>
  <div class="media-grid">
    <?php foreach ($media as $m): ?>
    <div>
      <div class="media-item media-admin-item" style="cursor:default">
        <?php if ($m['file_type'] === 'image'): ?>
          <img src="../<?= e($m['file_path']) ?>" loading="lazy" alt="">
        <?php else: ?>
          <video src="../<?= e($m['file_path']) ?>#t=0.5" preload="metadata" muted></video>
          <div class="play-icon">▶</div>
        <?php endif; ?>
        <?php if ($m['id'] == $coverId): ?>
          <span style="position:absolute;top:8px;left:8px;background:rgba(19,92,50,.92);color:#fff;font-size:11px;font-weight:700;padding:4px 10px;border-radius:999px;letter-spacing:.4px">COVER</span>
        <?php endif; ?>
        <form method="post" onsubmit="return confirm('Delete this file?')">
          <input type="hidden" name="media_id" value="<?= $m['id'] ?>">
          <button class="del" name="delete_media" value="1" title="Delete">✕</button>
        </form>
      </div>
      <div style="display:flex;gap:6px;margin-top:8px">
        <?php if ($m['file_type'] === 'image' && $m['id'] != $coverId): ?>
        <form method="post" style="flex:1">
          <input type="hidden" name="media_id" value="<?= $m['id'] ?>">
          <button class="btn" name="set_cover" value="1" style="width:100%;padding:6px 8px;font-size:12px;background:#eef3f7">Set Cover</button>
        </form>
        <?php else: ?>
        <div style="flex:1"></div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="media_id" value="<?= $m['id'] ?>">
          <button class="btn" name="move_up" value="1" title="Move up" style="padding:6px 12px;font-size:13px;background:#eef3f7">&#8593;</button>
        </form>
        <form method="post">
          <input type="hidden" name="media_id" value="<?= $m['id'] ?>">
          <button class="btn" name="move_dn" value="1" title="Move down" style="padding:6px 12px;font-size:13px;background:#eef3f7">&#8595;</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<script>
const dz = document.getElementById('dropzone');
const input = document.getElementById('fileInput');
const countEl = document.getElementById('fileCount');

dz.addEventListener('click', () => input.click());
input.addEventListener('change', updateCount);

['dragover','dragenter'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('drag'); }));
['dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('drag'); }));
dz.addEventListener('drop', e => {
  input.files = e.dataTransfer.files;
  updateCount();
});
function updateCount() {
  countEl.textContent = input.files.length ? `${input.files.length} file(s) selected — press Upload` : '';
}
</script>

</body>
</html>
