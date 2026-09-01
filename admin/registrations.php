<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/functions.php';
require_admin();

$eid = (int)($_GET['event'] ?? 0);
$msg = '';

/* Delete a registration */
if (isset($_POST['del_reg'])) {
    csrf_verify();
    $pdo->prepare("DELETE FROM registrations WHERE id = ?")->execute([(int)$_POST['reg_id']]);
    $msg = 'Registration deleted.';
}

/* Events list for the filter dropdown */
$events = $pdo->query("
    SELECT e.id, e.title, e.event_date,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS reg_count
    FROM events e ORDER BY e.event_date DESC")->fetchAll();

/* Registrations (filtered or all) */
$curEvent = null;
$curCustomFields = [];
if ($eid) {
    $eStmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $eStmt->execute([$eid]);
    $curEvent = $eStmt->fetch();
    if ($curEvent) {
        $curCustomFields = get_event_custom_fields($curEvent);
    }
    $stmt = $pdo->prepare("SELECT r.*, e.title AS event_title FROM registrations r JOIN events e ON e.id = r.event_id WHERE r.event_id = ? ORDER BY r.role DESC, r.id");
    $stmt->execute([$eid]);
} else {
    $stmt = $pdo->query("SELECT r.*, e.title AS event_title FROM registrations r JOIN events e ON e.id = r.event_id ORDER BY r.id DESC");
}
$regs = $stmt->fetchAll();

/* CSV export (attendance sheet) */
if ($eid && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="event_' . $eid . '_participants.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF"); /* UTF-8 BOM for Excel */

    if (!empty($curCustomFields)) {
        $customLabels = array_column($curCustomFields, 'label');
        $csvHeader = array_merge(['#', 'Type'], $customLabels, ['Name', 'Semester', 'Batch', 'Reg No', 'Designation', 'Mobile', 'Reference', 'Registered At']);
        fputcsv($out, $csvHeader);
        foreach ($regs as $i => $r) {
            $rMap = parse_registration_roles($r['event_role'] ?? '');
            $customValues = [];
            foreach ($customLabels as $cLabel) {
                $customValues[] = $rMap[$cLabel] ?? (count($customLabels) === 1 ? ($rMap['Role'] ?? '—') : '—');
            }
            fputcsv($out, array_merge(
                [$i + 1, ucfirst($r['role'])],
                $customValues,
                [$r['name'], $r['semester'], $r['batch'], $r['reg_no'], $r['designation'], $r['mobile'], $r['reference'], $r['registered_at']]
            ));
        }
    } else {
        fputcsv($out, ['#', 'Type', 'Role / Activity', 'Name', 'Semester', 'Batch', 'Reg No', 'Designation', 'Mobile', 'Reference', 'Registered At']);
        foreach ($regs as $i => $r) {
            fputcsv($out, [
                $i + 1,
                ucfirst($r['role']),
                get_registration_summary($r['event_role'] ?? '') ?: '—',
                $r['name'],
                $r['semester'],
                $r['batch'],
                $r['reg_no'],
                $r['designation'],
                $r['mobile'],
                $r['reference'],
                $r['registered_at']
            ]);
        }
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrations — EBAUB CSE Gallery Admin</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="icon" type="image/png" href="../assets/cse-logo.png">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="dashboard.php">
      <img class="brand-logo-img" src="../assets/cse-logo.png" alt="EBAUB CSE Logo" width="48" height="48" style="width:48px;height:48px">
      <div class="brand-text"><b>Event Registrations</b><span>Logged in as <?= e($_SESSION['admin_name']) ?></span></div>
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
  <?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>

  <div class="panel">
    <h3>Filter by Event</h3>
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <select name="event" style="padding:10px 14px;border:2px solid #dfe6ea;border-radius:10px;font-size:14.5px;min-width:260px">
        <option value="0">All events (<?= array_sum(array_column($events, 'reg_count')) ?> total)</option>
        <?php foreach ($events as $ev): ?>
          <option value="<?= $ev['id'] ?>" <?= $eid === (int)$ev['id'] ? 'selected' : '' ?>>
            <?= e($ev['title']) ?> — <?= $ev['reg_count'] ?> registered
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary">Show</button>
      <?php if ($eid && $regs): ?>
        <a class="btn" style="background:#eef3f7" href="registrations.php?event=<?= $eid ?>&export=csv">Download CSV</a>
      <?php endif; ?>
    </form>
  </div>

  <h2 class="section-title">Registrations (<?= count($regs) ?>)</h2>
  <?php if (!$regs): ?>
    <div class="empty">No registrations found<?= $eid ? ' for this event' : '' ?>.</div>
  <?php else: ?>
  <table class="admin-table">
    <tr>
      <th>#</th><th>Type</th><th>Segment/Role</th><th>Name</th><th>Details</th><th>Mobile</th><th>Reference</th>
      <?php if (!$eid): ?><th>Event</th><?php endif; ?>
      <th>Time</th><th></th>
    </tr>
    <?php foreach ($regs as $i => $r): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td>
        <span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:800;letter-spacing:.4px;<?= $r['role'] === 'teacher' ? 'background:#e8f0fd;color:#1a4f9c' : 'background:var(--green-light);color:var(--green-dark)' ?>">
          <?= strtoupper(e($r['role'])) ?>
        </span>
      </td>
      <td>
        <?php
          $rRoles = parse_registration_roles($r['event_role'] ?? '');
          if ($rRoles):
            foreach ($rRoles as $lbl => $val): ?>
              <span style="display:inline-block;padding:2px 8px;margin:2px 3px 2px 0;border-radius:6px;font-size:11.5px;font-weight:700;background:#eef6f1;color:var(--green-dark);border:1px solid #c9e4d4" title="<?= e($lbl) ?>">
                <?= e($val) ?>
              </span>
            <?php endforeach;
          else: ?>
            <span style="color:var(--muted);font-size:13px">—</span>
        <?php endif; ?>
      </td>
      <td><b><?= e($r['name']) ?></b></td>
      <td style="font-size:13.5px;color:var(--muted)">
        <?= $r['role'] === 'teacher'
            ? e($r['designation'])
            : 'Sem ' . e($r['semester']) . ' · Batch ' . e($r['batch']) . ' · Reg ' . e($r['reg_no']) ?>
      </td>
      <td><?= e($r['mobile']) ?></td>
      <td><?= e($r['reference'] ?: '—') ?></td>
      <?php if (!$eid): ?><td style="font-size:13px"><?= e($r['event_title']) ?></td><?php endif; ?>
      <td style="font-size:12.5px;color:var(--muted)"><?= e($r['registered_at']) ?></td>
      <td>
        <a class="btn" style="padding:6px 11px;font-size:12px;background:#eef3f7" href="edit-registration.php?id=<?= $r['id'] ?>">Edit</a>
        <form method="post" onsubmit="return confirm('Delete this registration?')" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
          <button class="btn btn-danger" style="padding:6px 11px;font-size:12px" name="del_reg" value="1">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</main>

<script>function toggleAdminMenu(){document.querySelector('.site-header .nav')?.classList.toggle('admin-nav-open');}</script>
</body>
</html>
