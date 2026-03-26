<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Database/Database.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$user = Auth::user();
if (!$user || $user['role'] !== 'owner') {
    header('Location: login.php');
    exit;
}
$pdo = Database::connect();
// Handle add department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['department_name'])) {
    $deptName = trim($_POST['department_name']);
    if ($deptName !== '') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO departments (name) VALUES (:name)');
        $stmt->execute(['name' => $deptName]);
        header('Location: owner_dashboard.php');
        exit;
    }
}
// Handle rename and delete department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $deptId = (int)$_POST['dept_id'];
    if ($_POST['action'] === 'rename' && isset($_POST['new_name'])) {
        $newName = trim($_POST['new_name']);
        if ($newName !== '') {
            $stmt = $pdo->prepare('UPDATE departments SET name = :name WHERE department_id = :id');
            $stmt->execute(['name' => $newName, 'id' => $deptId]);
        }
    } elseif ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM departments WHERE department_id = :id');
        $stmt->execute(['id' => $deptId]);
    }
    header('Location: owner_dashboard.php');
    exit;
}
$departments = $pdo->query('SELECT department_id, name FROM departments ORDER BY name')->fetchAll();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Owner Dashboard</title>
</head>
<body>
  <h1>Owner Dashboard</h1>
  <ul>
    <li><a href="#">Manage Arcades</a> (coming soon)</li>
    <li><a href="#">Manage Prizes</a> (coming soon)</li>
    <li><a href="#">View Revenue Reports</a> (coming soon)</li>
  </ul>
  <h2>Manage Departments</h2>
  <form method="post" action="">
    <label>New Department Name: <input type="text" name="department_name" required></label>
    <button type="submit">Add Department</button>
  </form>
  <h3>Existing Departments</h3>
  <ul>
    <?php foreach ($departments as $dept): ?>
      <li>
        <?php echo htmlspecialchars($dept['name']); ?>
        <form method="post" action="" style="display:inline; margin-left:10px;">
          <input type="hidden" name="dept_id" value="<?php echo (int)$dept['department_id']; ?>">
          <input type="text" name="new_name" placeholder="Rename" />
          <button type="submit" name="action" value="rename">Rename</button>
          <button type="submit" name="action" value="delete" onclick="return confirm('Delete this department?');">Delete</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <p><a href="index.php">Back to Home</a></p>
  <p><a href="logout.php">Logout</a></p>
</body>
</html>
