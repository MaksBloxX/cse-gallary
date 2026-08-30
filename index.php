<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

/* Search + filter + pagination */
$q        = trim($_GET['q'] ?? '');
$cat      = trim($_GET['cat'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;

$where = ["event_date <= :today"]; $params = [':today' => date('Y-m-d')];   /* albums = event day onwards */
if ($q !== '')  { $where[] = "(title LIKE :q OR description LIKE :q OR strftime('%Y', event_date) LIKE :q)"; $params[':q'] = "%$q%"; }
if ($cat !== ''){ $where[] = "category = :cat"; $params[':cat'] = $cat; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) c FROM events $whereSql");
$total->execute($params);
$totalEvents = (int)$total->fetch()['c'];
$pages = max(1, (int)ceil($totalEvents / $perPage));
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT e.*,
        (SELECT COUNT(*) FROM media m WHERE m.event_id = e.id) AS media_count,
        (SELECT file_path FROM media m WHERE m.event_id = e.id AND m.file_type='image' ORDER BY COALESCE(m.sort_order, m.id), m.id LIMIT 1) AS cover,
        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS reg_count
    FROM events e $whereSql
    ORDER BY e.event_date DESC
    LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$events = $stmt->fetchAll();

$cats = ['Contest', 'Seminar', 'Workshop', 'Study Tour', 'Other'];

/* Activities &amp; Achievements showcase */
$activities = $pdo->query("SELECT * FROM activities ORDER BY COALESCE(sort_order,id), id")->fetchAll();
$actMedia = [];
try {
    foreach ($pdo->query("SELECT activity_id, file_path FROM activity_media ORDER BY id") as $am) {
        $actMedia[$am['activity_id']][] = $am['file_path'];
    }
} catch (Throwable $e) {}

/* Upcoming events (today or later) with registration counts */
$up = $pdo->prepare("
    SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS reg_count
    FROM events e WHERE e.event_date > ? ORDER BY e.event_date LIMIT 3");
$up->execute([date('Y-m-d')]);
$upcomingEvents = $up->fetchAll();

/* Hero slider: 3 slides uploaded from the Admin Dashboard (uploads/hero/slide_1..3).
   Fallback 1: latest event covers.  Fallback 2: demo image.  Never empty. */
$heroImgs = [];
for ($n = 1; $n <= 3; $n++) {
    foreach (glob(__DIR__ . '/uploads/hero/slide_' . $n . '.*') as $f) {
        $heroImgs[] = 'uploads/hero/' . basename($f) . '?v=' . filemtime($f);
        break;
    }
}
if (!$heroImgs) {
    $heroImgs = $pdo->query("
        SELECT (SELECT file_path FROM media m WHERE m.event_id = e.id AND m.file_type='image' ORDER BY COALESCE(m.sort_order, m.id), m.id LIMIT 1) AS cover
        FROM events e ORDER BY e.event_date DESC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    $heroImgs = array_values(array_filter($heroImgs));
}
if (!$heroImgs) $heroImgs = ['uploads/events/demo_seminar.jpg'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EBAUB CSE Department — Media Gallery</title>
<?php seo_meta_tags(
    'EBAUB CSE Department — Media Gallery',
    'Official media gallery of the Department of Computer Science & Engineering, Exim Bank Agricultural University Bangladesh (EBAUB). Browse seminars, contests, workshops, study tours, and department activities.',
    'website',
    $heroImgs[0] ?? null
); ?>
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" type="image/png" href="assets/cse-logo.png">
<style>
  /* Thread 1: title zoom + blur reveal */
  @keyframes heroZoomBlur {
    0%   { opacity: 0; filter: blur(14px); transform: scale(1.35); }
    100% { opacity: 1; filter: blur(0);    transform: scale(1); }
  }
  /* Thread 2: typewriter caret */
  #heroSubTyped::after { content: '|'; margin-left: 2px; animation: heroCaret .8s step-end infinite; }
  #heroSubTyped.done::after { content: ''; animation: none; }
  @keyframes heroCaret { 50% { opacity: 0; } }
</style>
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="index.php">
      <img class="brand-logo-img" src="assets/cse-logo.png" alt="EBAUB CSE Logo" width="48" height="48" style="width:48px;height:48px">
      <div class="brand-text"><b>Department of CSE</b><span>Media Gallery · Exim Bank Agricultural University Bangladesh</span></div>
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

<section class="hero">
  <?php foreach ($heroImgs as $i => $img): ?>
    <div class="hero-bg <?= $i === 0 ? 'active' : '' ?>" style="background-image:url('<?= e($img) ?>')"></div>
  <?php endforeach; ?>
  <div class="hero-content">
    <h1 style="animation:heroZoomBlur 1.4s ease-out both">Glimpses of the CSE Department</h1>
    <!-- invisible full text reserves the exact space -> no layout jump while typing -->
    <p id="heroSub" style="position:relative;margin:0">
      <span style="visibility:hidden">Explore our achievements, activities and events — from seminars and contests to workshops and study tours, all in one place.</span>
      <span id="heroSubTyped" style="position:absolute;top:0;left:0;right:0"></span>
    </p>
  </div>
  <?php if (count($heroImgs) > 1): ?>
  <div class="hero-dots">
    <?php foreach ($heroImgs as $i => $img): ?>
      <button class="hero-dot <?= $i === 0 ? 'active' : '' ?>" data-slide="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<form class="filter-bar" method="get" action="index.php">
  <div class="search-box">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search events, activities or keywords...">
  </div>
  <div class="chips">
    <a class="chip <?= $cat === '' ? 'active' : '' ?>" href="index.php<?= $q ? '?q=' . urlencode($q) : '' ?>">All</a>
    <?php foreach ($cats as $c): ?>
      <a class="chip <?= $cat === $c ? 'active' : '' ?>"
         href="index.php?cat=<?= urlencode($c) ?><?= $q ? '&q=' . urlencode($q) : '' ?>"><?= e($c) ?></a>
    <?php endforeach; ?>
  </div>
</form>

<main class="container">
  <?php if ($upcomingEvents): ?>
  <h2 class="section-title">Events</h2>
  <div class="grid" style="margin-bottom:38px">
    <?php foreach ($upcomingEvents as $ue): ?>
    <a class="event-card" href="event.php?id=<?= $ue['id'] ?>">
      <div class="card-body" style="border-top:4px solid #f2b705">
        <span style="display:inline-block;background:#fff7dd;color:#8a6d00;font-size:11px;font-weight:800;letter-spacing:.5px;padding:4px 11px;border-radius:999px;margin-bottom:10px">UPCOMING · <?= e($ue['category']) ?></span>
        <h3><?= e($ue['title']) ?></h3>
        <div class="date"><?= date('d M Y', strtotime($ue['event_date'])) ?> · <?= (int)$ue['reg_count'] ?> registered</div>
        <?php $rdOpen = empty($ue['reg_deadline']) || date('Y-m-d') <= $ue['reg_deadline']; ?>
        <?php if (!empty($ue['reg_deadline'])): ?>
          <div style="font-size:12.5px;font-weight:700;margin-top:4px;color:<?= $rdOpen ? '#8a6d00' : '#c0392b' ?>">
            <?= $rdOpen ? 'Register by ' . date('d M Y', strtotime($ue['reg_deadline'])) : 'Registration closed' ?>
          </div>
        <?php endif; ?>
        <p><?= e($ue['description']) ?></p>
        <div style="margin-top:12px;font-weight:700;color:var(--green);font-size:14px"><?= $rdOpen ? 'View Details &amp; Register' : 'View Details' ?> &rarr;</div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($activities): ?>
  <div class="activities-header">
    <h2 class="section-title" id="activities" style="margin:0">Activities &amp; Achievements</h2>
    <a class="btn-see-more" href="activities.php">See more &rarr;</a>
  </div>
  <div class="activities-scroll">
    <?php foreach ($activities as $a):
      $imgs = $actMedia[$a['id']] ?? ($a['image'] ? [$a['image']] : []); ?>
    <div class="activity-hcard activity-card" style="cursor:<?= $imgs ? 'pointer' : 'default' ?>"
         data-imgs="<?= e(json_encode($imgs)) ?>" data-title="<?= e($a['title']) ?>">
      <div class="activity-hcard-thumb">
        <?php if ($imgs): ?>
          <img src="<?= e($imgs[0]) ?>" alt="<?= e($a['title']) ?>" loading="lazy">
          <?php if (count($imgs) > 1): ?>
            <span class="activity-hcard-badge"><?= count($imgs) ?> photos</span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="activity-hcard-body">
        <h3><?= e($a['title']) ?></h3>
        <p><?= e($a['description']) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h2 class="section-title">Event Gallery <small style="color:var(--muted);font-size:14px;font-weight:400">· <?= $totalEvents ?> events</small></h2>

  <?php if (!$events): ?>
    <div class="empty">No events found. Try a different search.</div>
  <?php else: ?>
  <div class="grid">
    <?php foreach ($events as $ev): ?>
    <a class="event-card" href="event.php?id=<?= $ev['id'] ?>">
      <div class="card-thumb">
        <?php if ($ev['cover']): ?>
          <img src="<?= e($ev['cover']) ?>" alt="<?= e($ev['title']) ?>" loading="lazy">
        <?php endif; ?>
        <span class="badge-cat"><?= e($ev['category']) ?></span>
        <span class="badge-count"><?= $ev['media_count'] ?> items</span>
      </div>
      <div class="card-body">
        <h3><?= e($ev['title']) ?></h3>
        <div class="date"><?= date('d M Y', strtotime($ev['event_date'])) ?><?php if ($ev['reg_count'] > 0): ?> · <?= $ev['reg_count'] ?> participants<?php endif; ?></div>
        <p><?= e($ev['description']) ?></p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a class="<?= $p === $page ? 'current' : '' ?>"
         href="?page=<?= $p ?><?= $q ? '&q=' . urlencode($q) : '' ?><?= $cat ? '&cat=' . urlencode($cat) : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</main>

<!-- Activity slideshow lightbox -->
<div class="lightbox" id="actLb">
  <button class="lb-btn lb-close" onclick="actClose()">✕</button>
  <button class="lb-btn lb-prev" onclick="actNav(-1)">&#10094;</button>
  <div id="actLbContent"></div>
  <button class="lb-btn lb-next" onclick="actNav(1)">&#10095;</button>
  <div class="lb-caption" id="actLbCaption"></div>
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
/* Hero slider: crossfade every 4.5s, dots are clickable */
(function () {
  const slides = document.querySelectorAll('.hero-bg');
  const dots = document.querySelectorAll('.hero-dot');
  if (slides.length < 2) return;
  let idx = 0, timer;

  function show(n) {
    slides[idx].classList.remove('active');
    dots[idx].classList.remove('active');
    idx = (n + slides.length) % slides.length;
    slides[idx].classList.add('active');
    dots[idx].classList.add('active');
  }
  function auto() { timer = setInterval(() => show(idx + 1), 4500); }

  dots.forEach(d => d.addEventListener('click', () => {
    clearInterval(timer);
    show(+d.dataset.slide);
    auto();
  }));
  auto();
})();

/* Thread 2: typewriter reveal for the hero subtitle */
(function () {
  const sub = document.getElementById('heroSubTyped');
  if (!sub) return;
  const text = 'Explore our achievements, activities and events \u2014 from seminars and contests to workshops and study tours, all in one place.';
  let i = 0;
  setTimeout(function type() {
    if (i <= text.length) {
      sub.textContent = text.slice(0, i++);
      setTimeout(type, 22);
    } else {
      sub.classList.add('done');
    }
  }, 700);
})();

/* Activity cards: click opens a slideshow of that activity's photos */
let actImgs = [], actIdx = 0, actTitle = '';
const actLb = document.getElementById('actLb');
const actContent = document.getElementById('actLbContent');
const actCaption = document.getElementById('actLbCaption');

document.querySelectorAll('.activity-card').forEach(card => {
  card.addEventListener('click', () => {
    try { actImgs = JSON.parse(card.dataset.imgs || '[]'); } catch (e) { actImgs = []; }
    if (!actImgs.length) return;
    actTitle = card.dataset.title || '';
    actIdx = 0;
    actRender();
    actLb.classList.add('open');
    document.body.style.overflow = 'hidden';
  });
});
function actRender() {
  actContent.innerHTML = `<img src="${actImgs[actIdx]}" alt="">`;
  actCaption.textContent = `${actTitle}  —  ${actIdx + 1} / ${actImgs.length}`;
}
function actNav(d) { actIdx = (actIdx + d + actImgs.length) % actImgs.length; actRender(); }
function actClose() { actLb.classList.remove('open'); actContent.innerHTML = ''; document.body.style.overflow = ''; }
actLb.addEventListener('click', e => { if (e.target === actLb) actClose(); });
document.addEventListener('keydown', e => {
  if (!actLb.classList.contains('open')) return;
  if (e.key === 'Escape') actClose();
  if (e.key === 'ArrowLeft') actNav(-1);
  if (e.key === 'ArrowRight') actNav(1);
});
</script>

</body>
</html>
