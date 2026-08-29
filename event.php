<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();
if (!$event) { header('Location: index.php'); exit; }

$media = $pdo->prepare("SELECT * FROM media WHERE event_id = ? ORDER BY COALESCE(sort_order, id), id");
$media->execute([$id]);
$media = $media->fetchAll();

/* ---- Event Registration (upcoming events only) ---- */
$today        = date('Y-m-d');
$isUpcoming   = ($event['event_date'] > $today);   /* on the event day it becomes an album */
$deadline     = $event['reg_deadline'] ?? null;
/* registration open: event not finished AND (no deadline OR today <= deadline) */
$regOpen      = $isUpcoming && (empty($deadline) || $today <= $deadline);
$customFields = get_event_custom_fields($event);

$regMsg = ''; $regErr = '';
if ($regOpen && isset($_POST['register'])) {
    $role      = ($_POST['role'] ?? '') === 'teacher' ? 'teacher' : 'student';
    $name      = trim($_POST['name'] ?? '');
    $mobile    = trim($_POST['mobile'] ?? '');
    $semester  = trim($_POST['semester'] ?? '');
    $batch     = trim($_POST['batch'] ?? '');
    $regNo     = trim($_POST['reg_no'] ?? '');
    $desig     = trim($_POST['designation'] ?? '');
    $ref       = trim($_POST['reference'] ?? '');

    $studentAnswers = [];
    if ($role === 'student' && !empty($customFields)) {
        $subAnswers = $_POST['custom_answers'] ?? [];
        foreach ($customFields as $idx => $cf) {
            $val = trim($subAnswers[$idx] ?? '');
            if ($val === '') {
                $regErr = 'Please choose: ' . $cf['label'];
                break;
            }
            $studentAnswers[$cf['label']] = $val;
        }
    }

    if ($regErr !== '') {
        /* validation error already set */
    } elseif ($name === '' || $mobile === '') {
        $regErr = 'Name and mobile number are required.';
    } elseif ($role === 'student' && ($semester === '' || $batch === '' || $regNo === '')) {
        $regErr = 'Semester, batch and registration no. are required for students.';
    } elseif ($role === 'teacher' && $desig === '') {
        $regErr = 'Designation is required for teachers.';
    } else {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND mobile = ?");
        $chk->execute([$id, $mobile]);
        if ($chk->fetchColumn() > 0) {
            $regErr = 'This mobile number is already registered for this event.';
        } else {
            $savedEventRole = ($role === 'student' && !empty($studentAnswers))
                ? json_encode($studentAnswers, JSON_UNESCAPED_UNICODE)
                : null;

            $pdo->prepare("INSERT INTO registrations
                (event_id, role, name, semester, batch, reg_no, designation, mobile, reference, event_role, registered_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$id, $role, $name, $semester ?: null, $batch ?: null, $regNo ?: null,
                           $desig ?: null, $mobile, $ref ?: null, $savedEventRole, date('Y-m-d H:i:s')]);
            $regMsg = 'Registration successful! See you at the event.';
        }
    }
}

/* Participation list (teachers first, then students) */
$pstmt = $pdo->prepare("SELECT * FROM registrations WHERE event_id = ? ORDER BY role DESC, id");
$pstmt->execute([$id]);
$participants = $pstmt->fetchAll();
$pTeachers = array_values(array_filter($participants, fn($p) => $p['role'] === 'teacher'));
$pStudents = array_values(array_filter($participants, fn($p) => $p['role'] === 'student'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($event['title']) ?> — EBAUB CSE Gallery</title>
<link rel="stylesheet" href="assets/style.css">
<link rel="icon" type="image/png" href="assets/cse-logo.png">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="index.php">
      <img class="brand-logo-img" src="assets/cse-logo.png" alt="EBAUB CSE Logo" width="48" height="48" style="width:48px;height:48px">
      <div class="brand-text"><b>Department of CSE</b><span>Media Gallery</span></div>
    </a>
    <nav class="nav">
      <a href="index.php">← Back to Albums</a>
      <a href="activities.php">Activities</a>
      <a href="upcoming.php">Upcoming Events</a>
      <a href="all-media.php">All Media</a>
    </nav>
  </div>
</header>

<main class="container">
  <h2 class="section-title"><?= e($event['title']) ?></h2>
  <p style="color:var(--muted);margin:-8px 0 6px"><?= date('d M Y', strtotime($event['event_date'])) ?> · <?= e($event['category']) ?> · <?= count($media) ?> items</p>
  <?php if ($isUpcoming && $regOpen): ?>
    <span style="display:inline-block;background:#f2b705;color:#3a2c00;font-size:12px;font-weight:800;letter-spacing:.5px;padding:6px 14px;border-radius:999px;margin-bottom:12px">UPCOMING EVENT — REGISTRATION OPEN<?= $deadline ? ' TILL ' . strtoupper(date('d M', strtotime($deadline))) : '' ?></span>
  <?php elseif ($isUpcoming): ?>
    <span style="display:inline-block;background:#fdecec;color:#c0392b;font-size:12px;font-weight:800;letter-spacing:.5px;padding:6px 14px;border-radius:999px;margin-bottom:12px">UPCOMING EVENT — REGISTRATION CLOSED</span>
  <?php endif; ?>
  <p style="max-width:820px;color:#3a4553;line-height:1.6;margin-bottom:26px"><?= e($event['description']) ?></p>

  <?php if ($regOpen): ?>
  <div class="panel" style="max-width:640px" id="registerBox">
    <h3>Register for this Event<?= $deadline ? ' <small style="font-weight:400;font-size:13px;color:var(--muted)">(deadline: ' . date('d M Y', strtotime($deadline)) . ')</small>' : '' ?></h3>
    <?php if ($regMsg): ?><div class="alert alert-ok"><?= e($regMsg) ?></div><?php endif; ?>
    <?php if ($regErr): ?><div class="alert alert-error"><?= e($regErr) ?></div><?php endif; ?>

    <div style="display:flex;gap:8px;margin-bottom:16px">
      <button type="button" class="chip active" id="tabStudent" onclick="setRole('student')">Student</button>
      <button type="button" class="chip" id="tabTeacher" onclick="setRole('teacher')">Teacher</button>
    </div>

    <form method="post" action="event.php?id=<?= $event['id'] ?>">
      <input type="hidden" name="role" id="roleInput" value="student">

      <div class="field">
        <label>Full Name *</label>
        <input type="text" name="name" required placeholder="e.g. Rahim Uddin">
      </div>

      <div id="studentFields">
        <?php if (!empty($customFields)): ?>
          <?php foreach ($customFields as $cfIdx => $cf): ?>
          <div class="field" style="margin-bottom:14px">
            <label><?= e($cf['label']) ?> *</label>
            <select name="custom_answers[<?= $cfIdx ?>]" class="student-cf-select" required style="width:100%;padding:10px 14px;border:2px solid #dfe6ea;border-radius:10px;font-size:14.5px;background:#fff">
              <option value="">-- Choose <?= e($cf['label']) ?> --</option>
              <?php foreach ($cf['options'] as $cOpt): ?>
                <option value="<?= e($cOpt) ?>"><?= e($cOpt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="form-row">
          <div class="field"><label>Semester *</label><input type="text" name="semester" placeholder="e.g. 6th"></div>
          <div class="field"><label>Batch *</label><input type="text" name="batch" placeholder="e.g. CSE-27"></div>
        </div>
        <div class="field"><label>Registration No. *</label><input type="text" name="reg_no" placeholder="e.g. 210105027"></div>
      </div>

      <div id="teacherFields" style="display:none">
        <div class="field"><label>Designation *</label><input type="text" name="designation" placeholder="e.g. Lecturer, Dept. of CSE"></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Mobile *</label><input type="text" name="mobile" required placeholder="e.g. 01710000000"></div>
        <div class="field"><label>Reference (optional)</label><input type="text" name="reference" placeholder="e.g. friend / notice board"></div>
      </div>
      <button class="btn btn-primary" name="register" value="1">Submit Registration</button>
    </form>
  </div>
  <?php elseif ($isUpcoming): ?>
  <div class="panel" style="max-width:640px">
    <h3 style="color:#c0392b">Registration Closed</h3>
    <p style="color:var(--muted);font-size:14.5px;line-height:1.6">
      The registration deadline (<?= date('d M Y', strtotime($deadline)) ?>) has passed.
      The event will be held on <?= date('d M Y', strtotime($event['event_date'])) ?> —
      photos and videos will be published here after the event.
    </p>
  </div>
  <?php endif; ?>

  <?php if ($participants): ?>
  <details class="panel" style="max-width:640px;cursor:pointer">
    <summary style="font-weight:800;color:var(--green-dark);font-size:16px">
      Participation List (<?= count($participants) ?>)
    </summary>
    <?php if ($pTeachers): ?>
      <p style="margin:14px 0 6px;font-weight:700;font-size:13px;color:var(--muted);letter-spacing:.5px">TEACHERS (<?= count($pTeachers) ?>)</p>
      <?php foreach ($pTeachers as $i => $p): ?>
        <div style="padding:8px 4px;border-top:1px solid #eef2f4;font-size:14.5px">
          <?= $i + 1 ?>. <b><?= e($p['name']) ?></b> <span style="color:var(--muted)">— <?= e($p['designation']) ?></span>
          <?php if (!empty($p['event_role'])): ?>
            <span style="display:inline-block;margin-left:6px;background:var(--green-light);color:var(--green-dark);font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:6px"><?= e($p['event_role']) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($pStudents): ?>
      <p style="margin:16px 0 6px;font-weight:700;font-size:13px;color:var(--muted);letter-spacing:.5px">STUDENTS (<?= count($pStudents) ?>)</p>
      <?php if (!empty($customFields)): ?>
        <?php
          $primaryField = $customFields[0]['label'] ?? '';
          $primaryOptions = $customFields[0]['options'] ?? [];

          $byPrimary = [];
          $otherStudents = [];
          foreach ($pStudents as $s) {
              $rolesMap = parse_registration_roles($s['event_role'] ?? '');
              $primaryVal = $rolesMap[$primaryField] ?? (count($rolesMap) === 1 ? reset($rolesMap) : '');
              if ($primaryVal && in_array($primaryVal, $primaryOptions, true)) {
                  $byPrimary[$primaryVal][] = $s;
              } else {
                  $otherStudents[] = $s;
              }
          }
        ?>
        <?php foreach ($primaryOptions as $pOpt): ?>
          <?php $group = $byPrimary[$pOpt] ?? []; ?>
          <?php if ($group): ?>
            <div style="margin:14px 0 6px;padding:6px 12px;background:#f6f8fa;border-left:3px solid var(--green);border-radius:4px;font-weight:700;font-size:13.5px;color:var(--green-dark)">
              <?= e($pOpt) ?> (<?= count($group) ?>)
            </div>
            <?php foreach ($group as $i => $p): ?>
              <?php
                $rMap = parse_registration_roles($p['event_role'] ?? '');
                $extraBadges = [];
                foreach ($rMap as $lbl => $val) {
                    if ($lbl !== $primaryField && $val !== '') {
                        $extraBadges[] = $val;
                    }
                }
              ?>
              <div style="padding:8px 4px 8px 10px;border-top:1px solid #eef2f4;font-size:14.5px">
                <?= $i + 1 ?>. <b><?= e($p['name']) ?></b> <span style="color:var(--muted)">— Batch <?= e($p['batch']) ?>, <?= e($p['semester']) ?> semester</span>
                <?php foreach ($extraBadges as $b): ?>
                  <span style="display:inline-block;margin-left:6px;background:#eef6f1;color:var(--green-dark);border:1px solid #c9e4d4;font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:6px"><?= e($b) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($otherStudents): ?>
          <div style="margin:12px 0 6px;padding:6px 10px;background:#f6f8fa;border-left:3px solid var(--muted);border-radius:4px;font-weight:700;font-size:13px;color:var(--text)">
            General Participants (<?= count($otherStudents) ?>)
          </div>
          <?php foreach ($otherStudents as $i => $p): ?>
            <?php $rSummary = get_registration_summary($p['event_role'] ?? ''); ?>
            <div style="padding:8px 4px 8px 10px;border-top:1px solid #eef2f4;font-size:14.5px">
              <?= $i + 1 ?>. <b><?= e($p['name']) ?></b> <span style="color:var(--muted)">— Batch <?= e($p['batch']) ?>, <?= e($p['semester']) ?> semester</span>
              <?php if ($rSummary): ?>
                <span style="display:inline-block;margin-left:6px;background:#eef6f1;color:var(--green-dark);border:1px solid #c9e4d4;font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:6px"><?= e($rSummary) ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php else: ?>
        <?php foreach ($pStudents as $i => $p): ?>
          <?php $rSummary = get_registration_summary($p['event_role'] ?? ''); ?>
          <div style="padding:8px 4px;border-top:1px solid #eef2f4;font-size:14.5px">
            <?= $i + 1 ?>. <b><?= e($p['name']) ?></b> <span style="color:var(--muted)">— Batch <?= e($p['batch']) ?>, <?= e($p['semester']) ?> semester</span>
            <?php if ($rSummary): ?>
              <span style="display:inline-block;margin-left:6px;background:var(--green-light);color:var(--green-dark);font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:6px"><?= e($rSummary) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>
  </details>
  <?php endif; ?>

  <?php if (!$media): ?>
    <div class="empty">No photos or videos have been uploaded to this album yet.</div>
  <?php else: ?>
  <div class="media-grid" id="mediaGrid">
    <?php foreach ($media as $i => $m): ?>
      <div class="media-item" data-index="<?= $i ?>" data-type="<?= $m['file_type'] ?>" data-src="<?= e($m['file_path']) ?>">
        <?php if ($m['file_type'] === 'image'): ?>
          <img src="<?= e($m['file_path']) ?>" alt="Photo <?= $i + 1 ?>" loading="lazy">
        <?php else: ?>
          <!-- Performance: video is NOT preloaded; only metadata/thumbnail -->
          <video src="<?= e($m['file_path']) ?>#t=0.5" preload="metadata" muted></video>
          <div class="play-icon">▶</div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
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
  lbCaption.textContent = `${current + 1} / ${items.length}`;
}
function navLb(dir) {
  current = (current + dir + items.length) % items.length;
  render();
}
function closeLb() {
  lb.classList.remove('open');
  lbContent.innerHTML = ''; // stop any playing video
  document.body.style.overflow = '';
}
lb.addEventListener('click', e => { if (e.target === lb) closeLb(); });
document.addEventListener('keydown', e => {
  if (!lb.classList.contains('open')) return;
  if (e.key === 'Escape') closeLb();
  if (e.key === 'ArrowLeft') navLb(-1);
  if (e.key === 'ArrowRight') navLb(1);
});

/* Registration: student / teacher tab switch */
function setRole(r) {
  const s = document.getElementById('studentFields');
  const t = document.getElementById('teacherFields');
  const ri = document.getElementById('roleInput');
  if (!s || !t || !ri) return;
  ri.value = r;
  s.style.display = r === 'student' ? '' : 'none';
  t.style.display = r === 'teacher' ? '' : 'none';
  document.querySelectorAll('.student-cf-select').forEach(el => el.required = (r === 'student'));
  document.getElementById('tabStudent').classList.toggle('active', r === 'student');
  document.getElementById('tabTeacher').classList.toggle('active', r === 'teacher');
}
</script>

</body>
</html>
