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
// Add shift
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start = $_POST['start_time'] ?? '';
    $end = $_POST['end_time'] ?? '';
    if ($start && $end) {
        $pdo->prepare('INSERT INTO employee_shifts (employee_id, start_time, end_time) VALUES (:eid, :start, :end)')->execute([
            'eid' => $employeeId,
            'start' => $start,
            'end' => $end
        ]);
    }
}
$shifts = $pdo->prepare('SELECT * FROM employee_shifts WHERE employee_id = :id ORDER BY start_time DESC');
$shifts->execute(['id' => $employeeId]);
$shifts = $shifts->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Shifts</title>
</head>
<body>
  <?php echo debugToolbarRender($user); ?>
  <h1>Manage Shifts for <?php echo htmlspecialchars($employee['name']); ?></h1>
  <form method="post" action="">
    <label>Start Time: <input type="datetime-local" name="start_time" required></label><br>
    <label>End Time: <input type="datetime-local" name="end_time" required></label><br>
    <button type="submit">Add Shift</button>
  </form>
  <h2>Shifts</h2>
  <table border="1" cellpadding="6" style="border-collapse: collapse;">
    <tr><th>Start</th><th>End</th></tr>
    <?php foreach ($shifts as $shift): ?>
      <tr>
        <td><?php echo htmlspecialchars($shift['start_time']); ?></td>
        <td><?php echo htmlspecialchars($shift['end_time']); ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p><a href="hr_dashboard.php">Back to HR Dashboard</a></p>
</body>
</html>
