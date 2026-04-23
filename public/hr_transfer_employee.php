<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Debug/DebugToolbar.php';

debugToolbarHandleRequest();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$user = Auth::user();
if (!$user || $user['role'] !== 'hr') {
    header('Location: login.php');
    exit;
}
$pdo = Database::connect();
$employeeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$emp = $pdo->prepare('SELECT * FROM employees WHERE employee_id = :id');
$emp->execute(['id' => $employeeId]);
$employee = $emp->fetch();
if (!$employee) {
    echo '<p>Employee not found.</p>';
    exit;
}
$departments = $pdo->query('SELECT department_id, name FROM departments ORDER BY name')->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['department_id'])) {
    $newDept = (int)$_POST['department_id'];
    $pdo->prepare('UPDATE employees SET department_id = :dept WHERE employee_id = :id')->execute([
        'dept' => $newDept,
        'id' => $employeeId
    ]);
    header('Location: hr_dashboard.php');
    exit;
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Transfer Employee</title>
</head>
<body>
  <?php echo debugToolbarRender($user); ?>
  <h1>Transfer Employee</h1>
  <form method="post" action="">
    <label>Employee: <strong><?php echo htmlspecialchars($employee['name']); ?></strong></label><br>
    <label>New Department:
      <select name="department_id" required>
        <?php foreach ($departments as $dept): ?>
          <option value="<?php echo (int)$dept['department_id']; ?>" <?php if ($dept['department_id'] == $employee['department_id']) echo 'selected'; ?>><?php echo htmlspecialchars($dept['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </label><br>
    <button type="submit">Transfer</button>
  </form>
  <p><a href="hr_dashboard.php">Back to HR Dashboard</a></p>
</body>
</html>
