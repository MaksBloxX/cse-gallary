<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

/* All Media: combined photo/video stream from Events and Activities &amp; Achievements.
   Performance: pagination (24/page) + lazy loading + videos never preload. */
$filter  = $_GET['filter'] ?? ''; // '', 'events', 'activities'
$type    = $_GET['type'] ?? '';   // '', 'image', 'video'
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;

$includeEvents = ($filter !== 'activities');
$includeActivities = ($filter !== 'events' && $type !== 'video');

$queries = [];
if ($includeEvents) {
    $evTypeCond = '';
    if ($type === 'image') $evTypeCond = "AND m.file_type = 'image'";
    if ($type === 'video') $evTypeCond = "AND m.file_type = 'video'";
    $queries[] = "
        SELECT 
            'event' AS source_type,
            m.id AS raw_id,
            m.event_id AS parent_id,
            m.file_path,
            m.file_type,
            m.uploaded_at,
            e.title AS source_title,
            e.event_date AS date_val
        FROM media m
        JOIN events e ON e.id = m.event_id
        WHERE 1=1 $evTypeCond
    ";
}

if ($includeActivities) {
    $queries[] = "
        SELECT 
            'activity' AS source_type,
            am.id AS raw_id,
            am.activity_id AS parent_id,
            am.file_path,
            'image' AS file_type,
            am.uploaded_at,
            a.title AS source_title,
            a.created_at AS date_val
        FROM activity_media am
        JOIN activities a ON a.id = am.activity_id
    ";
}

if (!empty($queries)) {
    $fullSql = implode(' UNION ALL ', $queries);
    $countSql = "SELECT COUNT(*) AS c FROM ($fullSql) AS u";
    $totalMedia = (int)$pdo->query($countSql)->fetch()['c'];
    $pages = max(1, (int)ceil($totalMedia / $perPage));
    $offset = ($page - 1) * $perPage;

    $itemsSql = "$fullSql ORDER BY uploaded_at DESC, raw_id DESC LIMIT $perPage OFFSET $offset";
    $media = $pdo->query($itemsSql)->fetchAll();
} else {
    $totalMedia = 0;
    $pages = 1;
    $media = [];
}

$qp = [];
if ($filter) $qp['filter'] = $filter;
if ($type)   $qp['type']   = $type;
$queryParamStr = $qp ? '&' . http_build_query($qp) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Media Gallery — EBAUB CSE Gallery</title>
<?php
    seo_meta_tags(
        'Media Gallery — EBAUB CSE Gallery',
        'Browse every photo and video from Department of CSE events and activities at Exim Bank Agricultural University Bangladesh (EBAUB), all in one gallery.',
        'website',
        $media[0]['file_path'] ?? null
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
      <div class="brand-text"><b>Department of CSE</b><span>All Media · EBAUB Gallery</span></div>
    </a>
    <nav class="nav">
      <a href="index.php">Home</a>
      <a href="activities.php">Activities</a>
      <a href="upcoming.php">Events</a>
      <a href="all-media.php" class="active">Media Gallery</a>
      <a href="https://ebaub.ac.bd/" target="_blank">Main Website</a>
    </nav>
  </div>
</header>

<main class="container">
  <h2 class="section-title">Media Gallery <small style="color:var(--muted);font-size:14px;font-weight:400">(<?= $totalMedia ?> items)</small></h2>

  <div class="chips" style="margin-bottom:22px">
    <a class="chip <?= ($filter === '' && $type === '') ? 'active' : '' ?>" href="all-media.php">All</a>
    <a class="chip <?= $filter === 'events' ? 'active' : '' ?>" href="all-media.php?filter=events">Events</a>
    <a class="chip <?= $filter === 'activities' ? 'active' : '' ?>" href="all-media.php?filter=activities">Activities</a>
    <a class="chip <?= ($type === 'image' && $filter === '') ? 'active' : '' ?>" href="all-media.php?type=image">Photos</a>
    <a class="chip <?= $type === 'video' ? 'active' : '' ?>" href="all-media.php?type=video">Videos</a>
  </div>

  <?php if (!$media): ?>
    <div class="empty">No media found for the selected filter.</div>
  <?php else: ?>
  <div class="media-grid">
    <?php foreach ($media as $i => $m): ?>
      <?php
        $isAct = ($m['source_type'] === 'activity');
        $caption = ($isAct ? 'Activity: ' : 'Event: ') . $m['source_title'];
        if (!empty($m['date_val'])) {
            $caption .= ' · ' . date('d M Y', strtotime($m['date_val']));
        }
      ?>
      <div class="media-item" data-index="<?= $i ?>" data-type="<?= $m['file_type'] ?>" data-src="<?= e($m['file_path']) ?>"
           data-caption="<?= e($caption) ?>">
        <?php if ($m['file_type'] === 'image'): ?>
          <img src="<?= e($m['file_path']) ?>" alt="<?= e($m['source_title']) ?>" loading="lazy">
        <?php else: ?>
          <video src="<?= e($m['file_path']) ?>#t=0.5" preload="metadata" muted></video>
          <div class="play-icon">▶</div>
        <?php endif; ?>
        <span class="badge-cat" style="top:auto;bottom:10px;left:10px;font-size:10px;font-weight:700;padding:3px 9px;<?= $isAct ? 'background:rgba(18,75,130,.88)' : 'background:rgba(19,92,50,.88)' ?>">
          <?= $isAct ? 'Activity' : 'Event' ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a class="<?= $p === $page ? 'current' : '' ?>"
         href="?page=<?= $p ?><?= $queryParamStr ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</main>

<!-- Lightbox -->
<div class="lightbox" id="lightbox">
  <button class="lb-btn lb-close" onclick="closeLb()">✕</button>
  <button class="lb-btn lb-prev" onclick="navLb(-1)">&#10094;</button>
  <div id="lbContent"></div>
  <button class="lb-btn lb-next" onclick="navLb(1)">&#10095;</button>
  <div class="lb-caption" id="lbCaption"></div>
</div>

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

<script>
const items = Array.from(document.querySelectorAll('.media-item'));
const lb = document.getElementById('lightbox');
const lbContent = document.getElementById('lbContent');
const lbCaption = document.getElementById('lbCaption');
let current = 0;

items.forEach(el => el.addEventListener('click', () => openLb(+el.dataset.index)));

function openLb(i) {
  current = i;
  render();
  lb.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function render() {
  const el = items[current];
  lbContent.innerHTML = el.dataset.type === 'video'
    ? `<video src="${el.dataset.src}" controls autoplay></video>`
    : `<img src="${el.dataset.src}" alt="">`;
  const img = lbContent.querySelector('img');
  if (img) {
    img.style.cursor = 'zoom-in';
    img.style.transition = 'transform .35s ease';
    img.addEventListener('click', function (e) {
      e.stopPropagation();
      if (img.dataset.zoom === '1') {
        img.dataset.zoom = '0';
        img.style.transform = 'scale(1)';
        img.style.cursor = 'zoom-in';
      } else {
        const r = img.getBoundingClientRect();
        img.style.transformOrigin =
          ((e.clientX - r.left) / r.width * 100) + '% ' +
          ((e.clientY - r.top) / r.height * 100) + '%';
        img.dataset.zoom = '1';
        img.style.transform = 'scale(2.2)';
        img.style.cursor = 'zoom-out';
      }
    });
  }
  lbCaption.textContent = `${el.dataset.caption}  —  ${current + 1} / ${items.length}`;
}
function navLb(dir) {
  current = (current + dir + items.length) % items.length;
  render();
}
function closeLb() {
  lb.classList.remove('open');
  lbContent.innerHTML = '';
  document.body.style.overflow = '';
}
lb.addEventListener('click', e => { if (e.target === lb) closeLb(); });
document.addEventListener('keydown', e => {
  if (!lb.classList.contains('open')) return;
  if (e.key === 'Escape') closeLb();
  if (e.key === 'ArrowLeft') navLb(-1);
  if (e.key === 'ArrowRight') navLb(1);
});
</script>

</body>
</html>
