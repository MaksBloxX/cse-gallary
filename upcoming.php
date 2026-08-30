<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

/* All upcoming events (today or later), soonest first */
$stmt = $pdo->prepare("
    SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS reg_count
    FROM events e WHERE e.event_date > ? ORDER BY e.event_date");
$stmt->execute([date('Y-m-d')]);
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upcoming Events — EBAUB CSE Gallery</title>
<?php
    $nextEventImg = null;
    if (!empty($events)) {
        $imgStmt = $pdo->prepare("SELECT file_path FROM media WHERE event_id=? AND file_type='image' ORDER BY COALESCE(sort_order,id), id LIMIT 1");
        $imgStmt->execute([$events[0]['id']]);
        $nextEventImg = $imgStmt->fetchColumn() ?: null;
    }
    seo_meta_tags(
        'Upcoming Events — EBAUB CSE Gallery',
        'See upcoming seminars, contests, workshops and study tours from the Department of Computer Science & Engineering, EBAUB, and register online.',
        'website',
        $nextEventImg
    );
?>
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" type="image/png" href="assets/cse-logo.png">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="index.php">
      <img class="brand-logo-img" src="assets/cse-logo.png" alt="EBAUB CSE Logo" width="48" height="48" style="width:48px;height:48px">
      <div class="brand-text"><b>Department of CSE</b><span>Upcoming Events · EBAUB Gallery</span></div>
    </a>
    <nav class="nav">
      <a href="index.php">Home</a>
      <a href="activities.php">Activities</a>
      <a href="upcoming.php">Events</a>
      <a href="all-media.php">Media Gallery</a>
      <a href="https://ebaub.ac.bd/" target="_blank">Main Website</a>
    </nav>
  </div>
</header>

<main class="container">
  <h2 class="section-title">Upcoming Events <small style="color:var(--muted);font-size:14px;font-weight:400">(<?= count($events) ?> open for registration)</small></h2>

  <?php if (!$events): ?>
    <div class="empty">No upcoming events right now. Check back soon, or browse the <a href="index.php" style="color:var(--green);font-weight:700">gallery</a>.</div>
  <?php else: ?>
  <div class="grid">
    <?php foreach ($events as $ev): ?>
    <a class="event-card" href="event.php?id=<?= $ev['id'] ?>">
      <div class="card-body" style="border-top:4px solid #f2b705">
        <span style="display:inline-block;background:#fff7dd;color:#8a6d00;font-size:11px;font-weight:800;letter-spacing:.5px;padding:4px 11px;border-radius:999px;margin-bottom:10px">UPCOMING · <?= e($ev['category']) ?></span>
        <h3><?= e($ev['title']) ?></h3>
        <div class="date"><?= date('d M Y', strtotime($ev['event_date'])) ?> · <?= (int)$ev['reg_count'] ?> registered</div>
        <?php $rdOpen = empty($ev['reg_deadline']) || date('Y-m-d') <= $ev['reg_deadline']; ?>
        <?php if (!empty($ev['reg_deadline'])): ?>
          <div style="font-size:12.5px;font-weight:700;margin-top:4px;color:<?= $rdOpen ? '#8a6d00' : '#c0392b' ?>">
            <?= $rdOpen ? 'Register by ' . date('d M Y', strtotime($ev['reg_deadline'])) : 'Registration closed' ?>
          </div>
        <?php endif; ?>
        <p><?= e($ev['description']) ?></p>
        <div style="margin-top:12px;font-weight:700;color:var(--green);font-size:14px"><?= $rdOpen ? 'View Details &amp; Register' : 'View Details' ?> &rarr;</div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<footer class="site-footer">
  <div style="display:flex;gap:12px;justify-content:center;margin-bottom:16px">
    <a href="https://www.facebook.com/ebaub.cse" target="_blank" rel="noopener" title="Facebook" style="display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.12)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" width="16" height="16" fill="#dfeae4"><path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"/></svg></a>
    <a href="https://www.linkedin.com/company/exim-bank-agricultural-university-bangladesh-ebaub/" target="_blank" rel="noopener" title="LinkedIn" style="display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.12)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="16" height="16" fill="#dfeae4"><path d="M100.28 448H7.4V148.9h92.88zM53.79 108.1C24.09 108.1 0 83.5 0 53.8a53.79 53.79 0 0 1 107.58 0c0 29.7-24.1 54.3-53.79 54.3zM447.9 448h-92.68V302.4c0-34.7-.7-79.2-48.29-79.2-48.29 0-55.69 37.7-55.69 76.7V448h-92.78V148.9h89.08v40.8h1.3c12.4-23.5 42.69-48.3 87.88-48.3 94 0 111.28 61.9 111.28 141.3z"/></svg></a>
    <a href="https://wa.me/8801710061468" target="_blank" rel="noopener" title="WhatsApp" style="display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.12)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="16" height="16" fill="#dfeae4"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg></a>
  </div>
  <b>Exim Bank Agricultural University Bangladesh (EBAUB)</b><br>
  Boro Indara Moor, Chapainawabganj-6300 · cse@ebaub.edu.bd<br>
  <a href="admin/login.php" style="color:inherit" title="">©</a> <?= date('Y') ?> Department of CSE, EBAUB — Developed by MaksBlox IT
</footer>

</body>
</html>
