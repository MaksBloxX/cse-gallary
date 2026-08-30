<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/functions.php';
require_admin();

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($current, $admin['password'])) {
        $err = 'Your current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $err = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $err = 'New password and confirmation do not match.';
    } elseif (password_verify($new, $admin['password'])) {
        $err = 'New password must be different from the current one.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?")->execute([$hash, $admin['id']]);
        session_regenerate_id(true); // extra safety after a credential change
        $msg = 'Password updated successfully. Use your new password next time you log in.';
    }
}

/* Warn on the well-known demo password so nobody forgets to change it */
$stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$stillDemo = password_verify('ebaub123', (string)$stmt->fetchColumn());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password — EBAUB CSE Gallery Admin</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="icon" type="image/png" href="../assets/cse-logo.png">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="dashboard.php">
      <img class="brand-logo-img" src="../assets/cse-logo.png" alt="EBAUB CSE Logo" width="48" height="48" style="width:48px;height:48px">
      <div class="brand-text"><b>Account Settings</b><span>Logged in as <?= e($_SESSION['admin_name']) ?></span></div>
    </a>
    <nav class="nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="activities.php">Activities</a>
      <a href="registrations.php">Registrations</a>
      <a href="../index.php" target="_blank">View Site</a>
      <a class="btn-login" href="logout.php">Logout</a>
    </nav>
  </div>
</header>

<main class="container">
  <h2 class="section-title">Change Password</h2>

  <?php if ($stillDemo): ?>
    <div class="alert alert-error">
      ⚠️ You are still using the <b>default demo password</b> shipped with this project.
      Please change it now before putting the site online — anyone who has read the
      project's README knows this password.
    </div>
  <?php endif; ?>

  <?php if ($msg): ?><div class="alert alert-ok"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

  <div class="panel" style="max-width:480px">
    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label>Current Password</label>
        <input type="password" name="current_password" required autofocus>
      </div>
      <div class="field">
        <label>New Password</label>
        <input type="password" name="new_password" required minlength="8" placeholder="At least 8 characters">
      </div>
      <div class="field">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required minlength="8">
      </div>
      <button class="btn btn-primary" type="submit">Update Password</button>
    </form>
  </div>
</main>

</body>
</html>
