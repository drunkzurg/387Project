<?php
// sys_admin only: approve pending users, optionally stub employees row, delete approved accounts
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Debug/DebugToolbar.php';
require_once __DIR__ . '/../src/View/FrontendAssets.php';

debugToolbarHandleRequest();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$currentUser = Auth::user();
if (!$currentUser || $currentUser['role'] !== 'sys_admin') {
    header('Location: login.php');
    exit;
}

$pdo = Database::connect();

// pull flash messages set by prior post/redirect cycle
$flash = $_SESSION['admin_dashboard_flash'] ?? null;
$error = $_SESSION['admin_dashboard_error'] ?? null;
unset($_SESSION['admin_dashboard_flash'], $_SESSION['admin_dashboard_error']);

// post/redirect/get — approve | reject | delete_existing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $userId = (int)$_POST['user_id'];

    try {
      if ($_POST['action'] === 'approve') {
        $stmt = $pdo->prepare('UPDATE users SET pending_approval = 0 WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        // provision stub employees row when approving staff-facing roles (hr fills details later)
        $userRow = $pdo->prepare('SELECT user_id, name, role FROM users WHERE user_id = :user_id');
        $userRow->execute(['user_id' => $userId]);
        $userData = $userRow->fetch();
        if ($userData && in_array($userData['role'], ['employee', 'owner', 'hr'], true)) {
          // skip insert when hr already linked this user
          $check = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = :user_id');
          $check->execute(['user_id' => $userId]);
          if (!$check->fetch()) {
            // default department is lowest id until hr reassigns
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
        $_SESSION['admin_dashboard_flash'] = 'Account approved.';
      } elseif ($_POST['action'] === 'reject') {
        $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :user_id AND pending_approval = 1');
        $stmt->execute(['user_id' => $userId]);
        $_SESSION['admin_dashboard_flash'] = 'Account request rejected.';
      } elseif ($_POST['action'] === 'delete_existing') {
        if ($userId === (int)$currentUser['user_id']) {
          throw new RuntimeException('You cannot delete your own admin account.');
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :user_id AND pending_approval = 0');
        $stmt->execute(['user_id' => $userId]);
        $_SESSION['admin_dashboard_flash'] = 'Existing account deleted.';
      }
    } catch (Throwable $e) {
      $_SESSION['admin_dashboard_error'] = $e->getMessage();
    }

    header('Location: admin_dashboard.php');
    exit;
}

// pending registration queue + approved directory for react props / fallback tables
$stmt = $pdo->query('SELECT user_id, name, email, role, pending_approval FROM users WHERE pending_approval = 1 ORDER BY user_id ASC');
$pendingUsers = $stmt->fetchAll();

$existingStmt = $pdo->query('SELECT user_id, name, email, role, pending_approval FROM users WHERE pending_approval = 0 ORDER BY FIELD(role, "sys_admin", "owner", "hr", "employee"), name ASC');
$existingUsers = $existingStmt->fetchAll();

// normalize rows for admin-dashboard.tsx + highlight current admin in delete guard
$mapUser = static function (array $user) use ($currentUser): array {
    return [
      'userId' => (int)$user['user_id'],
      'name' => (string)$user['name'],
      'email' => (string)$user['email'],
      'role' => (string)$user['role'],
      'pendingApproval' => (bool)$user['pending_approval'],
      'isCurrentUser' => (int)$user['user_id'] === (int)$currentUser['user_id'],
    ];
};

// vite bootstrap payload
$frontendProps = [
  'currentUser' => [
    'name' => (string)$currentUser['name'],
    'role' => (string)$currentUser['role'],
  ],
  'flash' => $flash,
  'error' => $error,
  'pendingUsers' => array_map($mapUser, $pendingUsers),
  'existingUsers' => array_map($mapUser, $existingUsers),
];

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - Pending Approvals</title>
  <?php echo frontendAssetsRender(); ?>
</head>
<body>
  <?php echo debugToolbarRender($currentUser); ?>
  <?php echo frontendJsonScript('admin-dashboard-props', $frontendProps); ?>
  <div id="admin-dashboard-root" data-react-page="adminDashboard" data-props-id="admin-dashboard-props"></div>
  <div class="ams-fallback">
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
  </div>
</body>
</html>
