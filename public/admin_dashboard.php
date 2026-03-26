<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Database/Database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$currentUser = Auth::user();
if (!$currentUser || $currentUser['role'] !== 'sys_admin') {
    header('Location: login.php');
    exit;
}

$pdo = Database::connect();

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $userId = (int)$_POST['user_id'];
    if ($_POST['action'] === 'approve') {
      $stmt = $pdo->prepare('UPDATE users SET pending_approval = 0 WHERE user_id = :user_id');
      $stmt->execute(['user_id' => $userId]);
      // Check if user needs an employee record
      $userRow = $pdo->prepare('SELECT user_id, name, role FROM users WHERE user_id = :user_id');
      $userRow->execute(['user_id' => $userId]);
      $userData = $userRow->fetch();
      if ($userData && in_array($userData['role'], ['employee', 'owner', 'hr'], true)) {
        // Only create if not already present
        $check = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = :user_id');
        $check->execute(['user_id' => $userId]);
        if (!$check->fetch()) {
          // Assign to first department by default (can be changed by HR later)
          $dept = $pdo->query('SELECT department_id FROM departments ORDER BY department_id ASC LIMIT 1')->fetch();
          $departmentId = $dept ? $dept['department_id'] : null;
          $insert = $pdo->prepare('INSERT INTO employees (user_id, name, department_id, hourly_wage, status) VALUES (:user_id, :name, :department_id, 15.00, "active")');
          $insert->execute([
            'user_id' => $userId,
            'name' => $userData['name'],
            'department_id' => $departmentId
          ]);
        }
      }
    } elseif ($_POST['action'] === 'reject') {
      $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :user_id');
      $stmt->execute(['user_id' => $userId]);
    }
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch all pending users
$stmt = $pdo->query('SELECT user_id, name, email, role FROM users WHERE pending_approval = 1 ORDER BY user_id ASC');
$pendingUsers = $stmt->fetchAll();

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - Pending Approvals</title>
</head>
<body>
  <h1>Admin Dashboard</h1>
  <h2>Pending Account Approvals</h2>
  <?php if (count($pendingUsers) === 0): ?>
    <p>No accounts are pending approval.</p>
  <?php else: ?>
    <table border="1" cellpadding="6" style="border-collapse: collapse;">
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Actions</th>
      </tr>
      <?php foreach ($pendingUsers as $user): ?>
        <tr>
          <td><?php echo htmlspecialchars($user['name']); ?></td>
          <td><?php echo htmlspecialchars($user['email']); ?></td>
          <td><?php echo htmlspecialchars($user['role']); ?></td>
          <td>
            <form method="post" action="" style="display:inline;">
              <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>" />
              <button type="submit" name="action" value="approve">Approve</button>
              <button type="submit" name="action" value="reject" onclick="return confirm('Reject and delete this account?');">Reject</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
  <p style="margin-top: 16px;"><a href="index.php">Back to Home</a></p>
  <p><a href="logout.php">Logout</a></p>
</body>
</html>
