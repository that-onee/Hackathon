<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

require_once __DIR__ . '/auth.php';

$error = '';
$mode  = $_GET['mode'] ?? 'login'; // 'login' or 'register'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'login';

    if ($mode === 'register') {
        $result = registerUser($_POST['username'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '');
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $_SESSION['user_id']  = $result['user_id'];
            $_SESSION['username'] = $result['username'];
            header('Location: dashboard.php'); exit;
        }
    } else {
        $result = loginUser($_POST['identifier'] ?? '', $_POST['password'] ?? '');
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $_SESSION['user_id']  = $result['user']['id'];
            $_SESSION['username'] = $result['user']['username'];
            header('Location: dashboard.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Project Generator — Rotterdam TC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cabinet+Grotesk:wght@500;700;800&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">

<div class="auth-bg">
  <div class="bg-orb orb-1"></div>
  <div class="bg-orb orb-2"></div>
  <div class="grid-overlay"></div>
</div>

<div class="auth-wrap">
  <div class="auth-card">
    <!-- Logo -->
    <div class="auth-logo">
      <div class="logo-icon-wrap">AI</div>
      <div>
        <div class="logo-title">AI Project Generator</div>
        <div class="logo-sub">Rotterdam TC · Hackathon 2026</div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="auth-tabs">
      <button class="tab-btn <?= $mode==='login'?'active':'' ?>" onclick="switchMode('login')">Sign In</button>
      <button class="tab-btn <?= $mode==='register'?'active':'' ?>" onclick="switchMode('register')">Register</button>
    </div>

    <?php if ($error): ?>
    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- LOGIN FORM -->
    <form method="POST" id="login-form" class="auth-form <?= $mode==='login'?'':'hidden' ?>">
      <input type="hidden" name="mode" value="login">
      <div class="form-group">
        <label>Username or Email</label>
        <input type="text" name="identifier" placeholder="your@email.com" required autocomplete="username">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-primary btn-full">Sign In →</button>
    </form>

    <!-- REGISTER FORM -->
    <form method="POST" id="register-form" class="auth-form <?= $mode==='register'?'':'hidden' ?>">
      <input type="hidden" name="mode" value="register">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" placeholder="coolstudent42" required minlength="3" maxlength="30" autocomplete="username">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" placeholder="you@school.nl" required autocomplete="email">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="At least 6 characters" required minlength="6" autocomplete="new-password">
      </div>
      <button type="submit" class="btn-primary btn-full">Create Account →</button>
    </form>

    <p class="auth-footer">Cross-disciplinary learning, powered by AI.</p>
  </div>
</div>

<script>
function switchMode(mode) {
  document.getElementById('login-form').classList.toggle('hidden', mode !== 'login');
  document.getElementById('register-form').classList.toggle('hidden', mode !== 'register');
  document.querySelectorAll('.tab-btn').forEach((b, i) => {
    b.classList.toggle('active', (i === 0 && mode === 'login') || (i === 1 && mode === 'register'));
  });
}
</script>
</body>
</html>
