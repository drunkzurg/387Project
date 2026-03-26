<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Database/Database.php';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $department_id = (int)($_POST['department_id'] ?? 0);
    $hourly_wage = (float)($_POST['hourly_wage'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $pdo->prepare('UPDATE employees SET name = :name, department_id = :department_id, hourly_wage = :hourly_wage, status = :status WHERE employee_id = :id')->execute([
        'name' => $name,
        'department_id' => $department_id,
        'hourly_wage' => $hourly_wage,
        'status' => $status,
        'id' => $employeeId
    ]);
    header('Location: hr_dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Employee</title>
</head>
<body>
  <h1>Edit Employee</h1>
  <form method="post" action="">
    <label>Name: <input type="text" name="name" value="<?php echo htmlspecialchars($employee['name']); ?>" required></label><br>
    <label>Department:
      <select name="department_id" required>
        <?php foreach ($departments as $dept): ?>
          <option value="<?php echo (int)$dept['department_id']; ?>" <?php if ($dept['department_id'] == $employee['department_id']) echo 'selected'; ?>><?php echo htmlspecialchars($dept['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </label><br>
    <label>Wage: <input type="number" name="hourly_wage" min="0" step="0.01" value="<?php echo htmlspecialchars($employee['hourly_wage']); ?>" required></label><br>
    <label>Status:
      <select name="status">
        <option value="active" <?php if ($employee['status'] === 'active') echo 'selected'; ?>>Active</option>
        <option value="transferred" <?php if ($employee['status'] === 'transferred') echo 'selected'; ?>>Transferred</option>
        <option value="terminated" <?php if ($employee['status'] === 'terminated') echo 'selected'; ?>>Terminated</option>
      </select>
    </label><br>
    <button type="submit">Save</button>
  </form>
  <p><a href="hr_dashboard.php">Back to HR Dashboard</a></p>
</body>
</html>
