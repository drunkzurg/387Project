<?php

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
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = (string)($_POST['name'] ?? '');
  $email = (string)($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');
  $confirm = (string)($_POST['confirm_password'] ?? '');
  $role = (string)($_POST['role'] ?? 'employee');

  $allowedRoles = ['employee', 'owner', 'hr'];
  if (!in_array($role, $allowedRoles, true)) {
    $role = 'employee';
  }

  if (trim($name) === '') {
    $error = 'Name is required.';
  } elseif (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
    $error = 'Please enter a valid email.';
  } elseif (strlen($password) < 6) {
    $error = 'Password must be at least 6 characters.';
  } elseif ($password !== $confirm) {
    $error = 'Passwords do not match.';
  } else {
    $user = Auth::register($name, $email, $password, $role);
    if ($user !== null) {
      header('Location: index.php');
      exit;
    }

    $error = 'Could not create account (email may already be in use).';
  }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Create Account</title>
</head>
<body>
  <?php echo debugToolbarRender($currentUser); ?>
  <h1>Create Account</h1>

  <?php if ($error !== null): ?>
    <p style="color: red;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <form method="post" action="">
    <div>
      <label for="name">Name</label><br />
      <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" required />
    </div>

    <div style="margin-top: 8px;">
      <label for="email">Email</label><br />
      <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required />
    </div>

    <div style="margin-top: 8px;">
      <label for="password">Password</label><br />
      <input id="password" name="password" type="password" required />
    </div>

    <div style="margin-top: 8px;">
      <label for="confirm_password">Confirm password</label><br />
      <input id="confirm_password" name="confirm_password" type="password" required />
    </div>

    <div style="margin-top: 8px;">
      <label for="role">Role</label><br />
      <select id="role" name="role" required>
        <option value="employee">Employee</option>
        <option value="owner">Owner</option>
        <option value="hr">HR</option>
      </select>
      <small>All registrations require admin approval.</small>
    </div>

    <div style="margin-top: 12px;">
      <button type="submit">Create account</button>
    </div>
  </form>

  <p style="margin-top: 16px;"><a href="login.php">Back to login</a></p>
</body>
</html>
