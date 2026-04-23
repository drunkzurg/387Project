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

// Fetch all employees with department info
$employees = $pdo->query('SELECT e.employee_id, e.name, e.status, e.hourly_wage, d.name AS department, u.email, u.role FROM employees e LEFT JOIN departments d ON e.department_id = d.department_id JOIN users u ON e.user_id = u.user_id ORDER BY e.employee_id ASC')->fetchAll();
$departments = $pdo->query('SELECT department_id, name FROM departments ORDER BY name')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>HR Dashboard</title>
</head>
<body>
  <?php echo debugToolbarRender($user); ?>
  <h1>HR Dashboard</h1>
  <p><a href="logout.php">Logout</a></p>
  <h2>Employees</h2>
  <table border="1" cellpadding="6" style="border-collapse: collapse;">
    <tr>
      <th>Name</th>
      <th>Email</th>
      <th>Role</th>
      <th>Department</th>
      <th>Wage</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($employees as $emp): ?>
      <tr>
        <td><?php echo htmlspecialchars($emp['name']); ?></td>
        <td><?php echo htmlspecialchars($emp['email']); ?></td>
        <td><?php echo htmlspecialchars($emp['role']); ?></td>
        <td><?php echo htmlspecialchars($emp['department']); ?></td>
        <td>$<?php echo number_format($emp['hourly_wage'], 2); ?></td>
        <td><?php echo htmlspecialchars($emp['status']); ?></td>
        <td>
          <a href="hr_edit_employee.php?id=<?php echo (int)$emp['employee_id']; ?>">Edit</a> |
          <a href="hr_transfer_employee.php?id=<?php echo (int)$emp['employee_id']; ?>">Transfer</a> |
          <a href="hr_shifts.php?id=<?php echo (int)$emp['employee_id']; ?>">Shifts</a> |
          <?php if ($emp['status'] !== 'terminated'): ?>
            <a href="hr_terminate_employee.php?id=<?php echo (int)$emp['employee_id']; ?>" onclick="return confirm('Terminate this employee?');">Terminate</a>
          <?php else: ?>
            <span style="color:gray;">Terminated</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <h2>Add New Employee</h2>
  <form method="post" action="hr_add_employee.php">
    <label>Name: <input type="text" name="name" required></label><br>
    <label>Email (must match user): <input type="email" name="email" required></label><br>
    <label>Department:
      <select name="department_id" required>
        <?php foreach ($departments as $dept): ?>
          <option value="<?php echo (int)$dept['department_id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </label><br>
    <label>Wage: <input type="number" name="hourly_wage" min="0" step="0.01" value="15.00" required></label><br>
    <button type="submit">Add Employee</button>
  </form>
</body>
</html>
