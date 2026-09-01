<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

/* Fetch all activities */
$activities = $pdo->query("SELECT * FROM activities WHERE COALESCE(published, 1) = 1 ORDER BY sort_order ASC, id DESC")->fetchAll();
$selectedActivityId = (int)($_GET['id'] ?? 0);

/* Fetch all activity media */
$allActMedia = $pdo->query("SELECT activity_id, file_path FROM activity_media ORDER BY id ASC")->fetchAll();
$actMedia = [];
foreach ($allActMedia as $row) {
    $actMedia[$row['activity_id']][] = $row['file_path'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activities &amp; Achievements — Department of CSE, EBAUB</title>
<?php
    $firstActImg = null;
    foreach ($activities as $a) {
        $imgs = $actMedia[$a['id']] ?? ($a['image'] ? [$a['image']] : []);
        if ($imgs) { $firstActImg = $imgs[0]; break; }
    }
    seo_meta_tags(
        'Activities & Achievements — Department of CSE, EBAUB',
        'Projects, robotics, hardware labs, programming contests, and student initiatives in the Department of Computer Science & Engineering, EBAUB.',
        'website',
        $firstActImg
    );
?>
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" type="image/png" href="assets/cse-logo.png">
<style>
.act-full-card {
  background: var(--card);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 26px;
  border: 1px solid rgba(0,0,0,.05);
  transition: transform .2s, box-shadow .2s;
}
.act-full-card:hover {
  box-shadow: 0 10px 24px rgba(16,40,28,.12);
}
.act-full-top {
  display: flex;
  flex-direction: row;
}
.act-full-thumb {
  width: 280px;
  min-width: 280px;
  position: relative;
  background: #dde3e8;
  cursor: pointer;
  overflow: hidden;
}
.act-full-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform .4s;
}
.act-full-thumb:hover img {
  transform: scale(1.05);
}
.act-full-body {
  padding: 24px 28px;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-width: 0;
}
.act-full-body h3 {
  font-size: 20px;
  font-weight: 700;
  margin: 0 0 10px;
  color: var(--text);
}
.act-full-body p {
  font-size: 14.5px;
  color: #4b5563;
  line-height: 1.65;
  margin: 0 0 14px;
}
.act-thumbs-strip {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  padding: 14px 28px 20px;
  background: #fbfdfc;
  border-top: 1px solid #eef2f4;
}
.act-mini-thumb {
  width: 72px;
  height: 72px;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all .2s;
  background: #dde3e8;
}
.act-mini-thumb:hover {
  border-color: var(--green);
  transform: scale(1.06);
}
.act-mini-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
@media (max-width: 768px) {
  .act-full-top {
    flex-direction: column;
  }
  .act-full-thumb {
    width: 100%;
    min-width: 0;
    height: 200px;
  }
  .act-full-body {
    padding: 16px;
  }
  .act-thumbs-strip {
    padding: 12px 16px 16px;
  }
  .act-mini-thumb {
    width: 60px;
    height: 60px;
  }
}
</style>
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="index.php">
      <img class="brand-logo-img" src="assets/cse-logo.png" alt="EBAUB CSE Logo" width="48" height="48" style="width:48px;height:48px">
      <div class="brand-text"><b>Department of CSE</b><span>Activities · EBAUB Gallery</span></div>
    </a>
    <button type="button" class="admin-menu-toggle" onclick="toggleAdminMenu()" aria-label="Open menu">☰</button>
    <nav class="nav">
      <a href="index.php">Home</a>
      <a href="activities.php" class="active">Activities</a>
      <a href="upcoming.php">Events</a>
      <a href="all-media.php">Media Gallery</a>
      <a href="https://ebaub.ac.bd/" target="_blank" rel="noopener">Main Website</a>
    </nav>
  </div>
</header>

<main class="container">
  <h2 class="section-title">CSE Department Activities <small style="color:var(--muted);font-size:14px;font-weight:400">(<?= count($activities) ?> showcases)</small></h2>
  <p style="color:var(--muted);margin:-8px 0 24px;font-size:14.5px">
    Discover projects, robotics, hardware labs, programming contests, and student initiatives in the Department of Computer Science &amp; Engineering.
  </p>

  <?php if (!$activities): ?>
    <div class="empty">No activities published yet.</div>
  <?php else: ?>
    <div class="activities-directory">
      <aside class="activities-sidebar">
        <div class="sidebar-title">ACTIVITIES &amp; ACHIEVEMENTS</div>
        <div class="activity-list">
        <?php foreach ($activities as $i => $a): ?>
          <button type="button" class="activity-list-item <?= (($selectedActivityId && $selectedActivityId === (int)$a['id']) || (!$selectedActivityId && $i === 0)) ? 'selected' : '' ?>" data-activity-id="<?= $a['id'] ?>" onclick="selectActivity(<?= $a['id'] ?>)"><?= e($a['title']) ?></button>
        <?php endforeach; ?>
        </div>
      </aside>
      <div class="activity-select-mobile">
        <label>Current Selection</label>
        <select onchange="selectActivity(this.value)">
          <?php foreach ($activities as $i => $a): ?><option value="<?= $a['id'] ?>"><?= e($a['title']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <section class="activities-details">
      <?php foreach ($activities as $i => $a): $items = $actMedia[$a['id']] ?? ($a['image'] ? [$a['image']] : []); ?>
        <article class="activity-detail-panel <?= (($selectedActivityId && $selectedActivityId === (int)$a['id']) || (!$selectedActivityId && $i === 0)) ? 'active' : '' ?>" id="activity-detail-<?= $a['id'] ?>">
          <div class="detail-kicker">ACTIVITY / FACILITY</div>
          <h2><?= e($a['title']) ?></h2>
          <?php if (!empty($a['description'])): ?><p class="detail-description"><?= nl2br(e($a['description'])) ?></p><?php endif; ?>
          <div class="detail-info-grid">
            <?php if (!empty($a['location'])): ?><div><b><img class="detail-icon" src="assets/icons/location.svg" alt="">Location</b><span><?= e($a['location']) ?></span></div><?php endif; ?>
            <?php if (!empty($a['facilities'])): ?><div><b><img class="detail-icon" src="assets/icons/tools.svg" alt="">Instruments</b><span><?= e($a['facilities']) ?></span></div><?php endif; ?>
          </div>
          <?php if (!empty($a['assigned_persons'])): ?><div class="assigned-box"><b><img class="detail-icon" src="assets/icons/person.svg" alt="">Assigned Person</b><span><?= nl2br(e($a['assigned_persons'])) ?></span></div><?php endif; ?>
          <div class="gallery-heading">Gallery <span><?= count($items) ?> photos</span></div>
          <div class="activity-gallery-grid">
          <?php foreach ($items as $m): ?><img src="<?= e($m) ?>" alt="<?= e($a['title']) ?>" loading="lazy" onclick="openActLb(<?= e(json_encode(array_values($items))) ?>, '<?= e(addslashes($a['title'])) ?>', 0)"><?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
      </section>
    </div>
  <?php endif; ?>
</main>

<!-- Lightbox Modal -->
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
let curImgs = [], curIdx = 0, curTitle = '';
const lb = document.getElementById('actLb');
const content = document.getElementById('actLbContent');
const caption = document.getElementById('actLbCaption');

function openActLb(imgs, title, startIndex) {
  curImgs = imgs || [];
  curTitle = title || '';
  curIdx = startIndex || 0;
  if (!curImgs.length) return;
  renderLb();
  lb.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function renderLb() {
  content.innerHTML = `<img src="${curImgs[curIdx]}" alt="" style="cursor:zoom-in;transition:transform .25s ease">`;
  const img = content.querySelector('img');
  img.addEventListener('click', function(e) { e.stopPropagation(); const z = img.dataset.zoom === '1'; img.dataset.zoom = z ? '0' : '1'; img.style.transform = z ? 'scale(1)' : 'scale(2)'; img.style.cursor = z ? 'zoom-in' : 'zoom-out'; });
  caption.textContent = `${curTitle}  —  ${curIdx + 1} / ${curImgs.length}`;
}
function actNav(d) {
  if (!curImgs.length) return;
  curIdx = (curIdx + d + curImgs.length) % curImgs.length;
  renderLb();
}
function actClose() {
  lb.classList.remove('open');
  content.innerHTML = '';
  document.body.style.overflow = '';
}
lb.addEventListener('click', e => { if (e.target === lb) actClose(); });
document.addEventListener('keydown', e => {
  if (!lb.classList.contains('open')) return;
  if (e.key === 'Escape') actClose();
  if (e.key === 'ArrowLeft') actNav(-1);
  if (e.key === 'ArrowRight') actNav(1);
});
function selectActivity(id) {
  document.querySelectorAll('.activity-detail-panel').forEach(el => el.classList.toggle('active', el.id === 'activity-detail-' + id));
  document.querySelectorAll('.activity-list-item').forEach(el => el.classList.toggle('selected', el.dataset.activityId == id));
  const select = document.querySelector('.activity-select-mobile select');
  if (select && select.value != id) select.value = id;
}

</script>

<script>function toggleAdminMenu(){document.querySelector('.site-header .nav')?.classList.toggle('admin-nav-open');}</script>
</body>
</html>
