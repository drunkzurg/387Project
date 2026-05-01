<?php

// fallback login page for users without js; mirrors auth_modal validation then redirects by role
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Debug/DebugToolbar.php';

debugToolbarHandleRequest();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$currentUser = Auth::user();
if ($currentUser !== null) {
  header('Location: index.php');
    exit;
}

$error = null;
$email = '';

// classic form post — same credentials path as auth_modal login action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string)($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $user = Auth::attemptLogin($email, $password);
    if ($user === 'pending_approval') {
      $error = 'Your account is pending admin approval. Please wait for an administrator to approve your registration.';
    } elseif ($user !== null) {
      // redirect by role (same routing table as auth_modal.php)
      if ($user['role'] === 'sys_admin') {
        header('Location: admin_dashboard.php');
      } elseif ($user['role'] === 'owner') {
        header('Location: owner_dashboard.php');
      } elseif ($user['role'] === 'hr') {
        header('Location: hr_dashboard.php');
      } elseif ($user['role'] === 'employee') {
        header('Location: employee_dashboard.php');
      } else {
        header('Location: index.php');
      }
      exit;
    } else {
      $error = 'Invalid email or password.';
    }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login</title>
</head>
<body>
  <?php echo debugToolbarRender($currentUser); ?>
  <h1>Login</h1>

  <?php if ($error !== null): ?>
    <p style="color: red;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <form method="post" action="">
    <div>
      <label for="email">Email</label><br />
      <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required />
    </div>

    <div style="margin-top: 8px;">
      <label for="password">Password</label><br />
      <input id="password" name="password" type="password" required />
    </div>

    <div style="margin-top: 12px;">
      <button type="submit">Sign in</button>
    </div>
  </form>

  <p style="margin-top: 16px;">
    <a href="register.php">Create account</a>
  </p>

  <p style="margin-top: 16px;">
    Seed users all have password <strong>password</strong> (if you loaded the seed file).
  </p>
</body>
</html>
