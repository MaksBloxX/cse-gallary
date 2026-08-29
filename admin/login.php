<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_id'])) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);           // prevent session fixation
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid username or password!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — EBAUB CSE Gallery</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="icon" type="image/png" href="../assets/cse-logo.png">
</head>
<body>
<div class="auth-wrap">
  <form class="auth-card" method="post">
    <div style="text-align:center;margin-bottom:14px">
      <img src="../assets/cse-logo.png" alt="EBAUB CSE Logo" width="84" height="84" style="width:84px;height:84px;margin:auto;display:block">
    </div>
    <h2>Admin Login</h2>
    <p class="sub">CSE Department Media Gallery — Teachers only</p>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="field">
      <label>Username</label>
      <input type="text" name="username" required autofocus placeholder="e.g. admin">
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="password" required placeholder="••••••••">
    </div>
    <button class="btn btn-primary btn-block" type="submit">Login</button>
    <p style="text-align:center;margin-top:16px;font-size:13px"><a href="../index.php" style="color:var(--green)">← Back to Gallery</a></p>
  </form>
</div>
</body>
</html>
