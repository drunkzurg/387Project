<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Debug/DebugToolbar.php';
require_once __DIR__ . '/../src/View/FrontendAssets.php';
require_once __DIR__ . '/../src/Services/TicketService.php';

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
TicketService::ensureInfrastructure($pdo);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS hr_action_logs (
        hr_action_log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        action_type VARCHAR(60) NOT NULL,
        employee_id INT UNSIGNED NULL,
        handled_by_user_id INT UNSIGNED NOT NULL,
        details VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (hr_action_log_id),
        KEY idx_hr_action_logs_employee (employee_id),
        KEY idx_hr_action_logs_user (handled_by_user_id),
        KEY idx_hr_action_logs_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
$pdo->exec('ALTER TABLE employee_shifts MODIFY end_time DATETIME NULL');
$entryTypeColumn = $pdo->query("SHOW COLUMNS FROM employee_shifts LIKE 'entry_type'")->fetch();
if (!$entryTypeColumn) {
    $pdo->exec("ALTER TABLE employee_shifts ADD entry_type ENUM('live','manual') NOT NULL DEFAULT 'manual' AFTER end_time");
}
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS employee_sick_requests (
        sick_request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id INT UNSIGNED NOT NULL,
        request_date DATE NOT NULL,
        status ENUM('waiting','approved','denied') NOT NULL DEFAULT 'waiting',
        notes VARCHAR(255) NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_by_user_id INT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        review_notes VARCHAR(255) NULL,
        PRIMARY KEY (sick_request_id),
        UNIQUE KEY uq_sick_request_employee_day (employee_id, request_date),
        KEY idx_sick_requests_employee (employee_id),
        KEY idx_sick_requests_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$flash = $_SESSION['hr_dashboard_flash'] ?? null;
$error = $_SESSION['hr_dashboard_error'] ?? null;
unset($_SESSION['hr_dashboard_flash'], $_SESSION['hr_dashboard_error']);

$logHrAction = static function (string $actionType, ?int $employeeId, string $details = '') use ($pdo, $user): void {
    $stmt = $pdo->prepare(
        'INSERT INTO hr_action_logs (action_type, employee_id, handled_by_user_id, details)
         VALUES (:action_type, :employee_id, :handled_by_user_id, :details)'
    );
    $stmt->execute([
        'action_type' => $actionType,
        'employee_id' => $employeeId,
        'handled_by_user_id' => (int)$user['user_id'],
        'details' => $details !== '' ? $details : null,
    ]);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'add_employee') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $departmentId = (int)($_POST['department_id'] ?? 0);
            $hourlyWage = (float)($_POST['hourly_wage'] ?? 0);

            if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('Employee name and valid user email are required.');
            }

            $userRow = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
            $userRow->execute(['email' => $email]);
            $userData = $userRow->fetch();
            if (!$userData) {
                throw new RuntimeException('No user exists with that email.');
            }

            $check = $pdo->prepare('SELECT employee_id FROM employees WHERE user_id = :user_id LIMIT 1');
            $check->execute(['user_id' => $userData['user_id']]);
            if ($check->fetch()) {
                throw new RuntimeException('That user already has an employee record.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO employees (user_id, name, department_id, hourly_wage, status)
                 VALUES (:user_id, :name, :department_id, :hourly_wage, "active")'
            );
            $insert->execute([
                'user_id' => $userData['user_id'],
                'name' => $name,
                'department_id' => $departmentId > 0 ? $departmentId : null,
                'hourly_wage' => number_format($hourlyWage, 2, '.', ''),
            ]);

            $employeeId = (int)$pdo->lastInsertId();
            $logHrAction('add_employee', $employeeId, 'Added employee ' . $name . '.');
            $_SESSION['hr_dashboard_flash'] = 'Employee added.';
        } elseif ($action === 'update_employee') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $departmentId = (int)($_POST['department_id'] ?? 0);
            $hourlyWage = (float)($_POST['hourly_wage'] ?? 0);
            $status = (string)($_POST['status'] ?? 'active');

            if ($employeeId <= 0 || $name === '') {
                throw new RuntimeException('Employee and name are required.');
            }
            if (!in_array($status, ['active', 'transferred', 'terminated'], true)) {
                throw new RuntimeException('Invalid employee status.');
            }

            $oldDepartmentStmt = $pdo->prepare('SELECT department_id FROM employees WHERE employee_id = :employee_id LIMIT 1');
            $oldDepartmentStmt->execute(['employee_id' => $employeeId]);
            $oldDepartmentId = $oldDepartmentStmt->fetchColumn();

            $update = $pdo->prepare(
                'UPDATE employees
                 SET name = :name,
                     department_id = :department_id,
                     hourly_wage = :hourly_wage,
                     status = :status
                 WHERE employee_id = :employee_id'
            );
            $update->execute([
                'employee_id' => $employeeId,
                'name' => $name,
                'department_id' => $departmentId > 0 ? $departmentId : null,
                'hourly_wage' => number_format($hourlyWage, 2, '.', ''),
                'status' => $status,
            ]);

            $logHrAction('update_employee', $employeeId, 'Updated employee details.');
            TicketService::syncDepartmentStaffingStatus($pdo, $oldDepartmentId !== false && $oldDepartmentId !== null ? (int)$oldDepartmentId : null);
            TicketService::syncDepartmentStaffingStatus($pdo, $departmentId > 0 ? $departmentId : null);
            $_SESSION['hr_dashboard_flash'] = 'Employee updated.';
        } elseif ($action === 'terminate_employee') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                throw new RuntimeException('Employee is required.');
            }

            $departmentStmt = $pdo->prepare('SELECT department_id FROM employees WHERE employee_id = :employee_id LIMIT 1');
            $departmentStmt->execute(['employee_id' => $employeeId]);
            $departmentId = $departmentStmt->fetchColumn();

            $pdo->prepare('UPDATE employees SET status = "terminated" WHERE employee_id = :employee_id')
                ->execute(['employee_id' => $employeeId]);
            $logHrAction('terminate_employee', $employeeId, 'Terminated employee.');
            TicketService::syncDepartmentStaffingStatus($pdo, $departmentId !== false && $departmentId !== null ? (int)$departmentId : null);
            $_SESSION['hr_dashboard_flash'] = 'Employee terminated.';
        } elseif ($action === 'add_shift') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $startValue = trim((string)($_POST['start_time'] ?? ''));
            $endValue = trim((string)($_POST['end_time'] ?? ''));
            $startTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startValue);
            $endTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $endValue);

            if ($employeeId <= 0 || !$startTime || !$endTime || $endTime <= $startTime) {
                throw new RuntimeException('Choose a valid employee shift start and end time.');
            }

            $insert = $pdo->prepare(
                'INSERT INTO employee_shifts (employee_id, start_time, end_time)
                 VALUES (:employee_id, :start_time, :end_time)'
            );
            $insert->execute([
                'employee_id' => $employeeId,
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s'),
            ]);
            $logHrAction('add_shift', $employeeId, 'Added shift from ' . $startTime->format('M j g:i A') . ' to ' . $endTime->format('M j g:i A') . '.');
            $_SESSION['hr_dashboard_flash'] = 'Shift added.';
        } elseif ($action === 'review_sick_request') {
            $requestId = (int)($_POST['sick_request_id'] ?? 0);
            $status = (string)($_POST['status'] ?? '');
            $reviewNotes = trim((string)($_POST['review_notes'] ?? ''));

            if ($requestId <= 0 || !in_array($status, ['approved', 'denied'], true)) {
                throw new RuntimeException('Choose a valid sick request decision.');
            }

            $requestStmt = $pdo->prepare('SELECT employee_id, request_date FROM employee_sick_requests WHERE sick_request_id = :id LIMIT 1');
            $requestStmt->execute(['id' => $requestId]);
            $request = $requestStmt->fetch();
            if (!$request) {
                throw new RuntimeException('Sick request not found.');
            }

            $update = $pdo->prepare(
                'UPDATE employee_sick_requests
                 SET status = :status,
                     reviewed_by_user_id = :reviewed_by_user_id,
                     reviewed_at = NOW(),
                     review_notes = :review_notes
                 WHERE sick_request_id = :id'
            );
            $update->execute([
                'id' => $requestId,
                'status' => $status,
                'reviewed_by_user_id' => (int)$user['user_id'],
                'review_notes' => $reviewNotes !== '' ? $reviewNotes : null,
            ]);

            $logHrAction('sick_request_' . $status, (int)$request['employee_id'], ucfirst($status) . ' sick day for ' . $request['request_date'] . '.');
            $_SESSION['hr_dashboard_flash'] = 'Sick request ' . $status . '.';
        }
    } catch (Throwable $e) {
        $_SESSION['hr_dashboard_error'] = $e->getMessage();
    }

    header('Location: hr_dashboard.php');
    exit;
}

// Fetch all employees with department info
$employees = $pdo->query(
    'SELECT
        e.employee_id,
        e.name,
        e.status,
        e.hourly_wage,
        e.department_id,
        d.department_type,
        d.name AS department,
        u.email,
        u.role
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.department_id
     JOIN users u ON e.user_id = u.user_id
     ORDER BY e.employee_id ASC'
)->fetchAll();
$departments = $pdo->query('SELECT department_id, name FROM departments ORDER BY name')->fetchAll();
foreach ($departments as $department) {
    TicketService::syncDepartmentStaffingStatus($pdo, (int)$department['department_id']);
}

$weekStart = new DateTimeImmutable('monday this week');
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $day = $weekStart->modify("+{$i} days");
    $weekDays[$day->format('Y-m-d')] = [
        'date' => $day->format('Y-m-d'),
        'label' => $day->format('D'),
    ];
}

$shiftRowsStmt = $pdo->prepare(
    "SELECT
        employee_id,
        DATE(start_time) AS shift_date,
        SUM(CASE WHEN end_time > start_time THEN TIMESTAMPDIFF(MINUTE, start_time, end_time) ELSE 0 END) AS minutes_worked
     FROM employee_shifts
     WHERE start_time >= :week_start
       AND start_time < :week_end
     GROUP BY employee_id, DATE(start_time)"
);
$shiftRowsStmt->execute([
    'week_start' => $weekStart->format('Y-m-d 00:00:00'),
    'week_end' => $weekStart->modify('+7 days')->format('Y-m-d 00:00:00'),
]);
$shiftRows = $shiftRowsStmt->fetchAll();

$shiftMinutesByEmployee = [];
foreach ($shiftRows as $row) {
    $shiftMinutesByEmployee[(int)$row['employee_id']][(string)$row['shift_date']] = (int)$row['minutes_worked'];
}

$approvedSickRowsStmt = $pdo->prepare(
    "SELECT employee_id, request_date
     FROM employee_sick_requests
     WHERE status = 'approved'
       AND request_date >= :week_start
       AND request_date < :week_end"
);
$approvedSickRowsStmt->execute([
    'week_start' => $weekStart->format('Y-m-d'),
    'week_end' => $weekStart->modify('+7 days')->format('Y-m-d'),
]);
$approvedSickByEmployee = [];
foreach ($approvedSickRowsStmt->fetchAll() as $row) {
    $approvedSickByEmployee[(int)$row['employee_id']][(string)$row['request_date']] = 8 * 60;
}

$recentShiftStmt = $pdo->query(
    'SELECT shift_id, employee_id, start_time, end_time
     FROM employee_shifts
     ORDER BY start_time DESC
     LIMIT 50'
);
$recentShiftRows = $recentShiftStmt->fetchAll();
$recentShiftsByEmployee = [];
foreach ($recentShiftRows as $shift) {
    $recentShiftsByEmployee[(int)$shift['employee_id']][] = [
        'shiftId' => (int)$shift['shift_id'],
        'startTime' => (string)$shift['start_time'],
        'endTime' => (string)$shift['end_time'],
    ];
}

$logs = $pdo->query(
    "SELECT
        log.hr_action_log_id,
        log.action_type,
        log.details,
        log.created_at,
        e.name AS employee_name,
        u.name AS handled_by_name
     FROM hr_action_logs log
     LEFT JOIN employees e ON e.employee_id = log.employee_id
     LEFT JOIN users u ON u.user_id = log.handled_by_user_id
     ORDER BY log.created_at DESC, log.hr_action_log_id DESC
     LIMIT 20"
)->fetchAll();

$sickRequests = $pdo->query(
    "SELECT
        sr.sick_request_id,
        sr.employee_id,
        sr.request_date,
        sr.status,
        sr.notes,
        sr.requested_at,
        sr.reviewed_at,
        sr.review_notes,
        e.name AS employee_name,
        reviewer.name AS reviewer_name
     FROM employee_sick_requests sr
     JOIN employees e ON e.employee_id = sr.employee_id
     LEFT JOIN users reviewer ON reviewer.user_id = sr.reviewed_by_user_id
     ORDER BY FIELD(sr.status, 'waiting', 'approved', 'denied'), sr.request_date DESC"
)->fetchAll();

$frontendProps = [
    'currentUser' => [
        'name' => (string)$user['name'],
        'role' => (string)$user['role'],
    ],
    'flash' => $flash,
    'error' => $error,
    'departments' => array_map(
        static fn(array $department): array => [
            'departmentId' => (int)$department['department_id'],
            'name' => (string)$department['name'],
        ],
        $departments
    ),
    'employees' => array_map(
        static function (array $employee) use ($weekDays, $shiftMinutesByEmployee, $approvedSickByEmployee, $recentShiftsByEmployee): array {
            $employeeId = (int)$employee['employee_id'];
            $weeklyHours = [];
            $totalMinutes = 0;
            foreach ($weekDays as $date => $day) {
                $minutes = ($shiftMinutesByEmployee[$employeeId][$date] ?? 0)
                    + ($approvedSickByEmployee[$employeeId][$date] ?? 0);
                $totalMinutes += $minutes;
                $weeklyHours[] = [
                    'date' => $day['date'],
                    'label' => $day['label'],
                    'hours' => round($minutes / 60, 2),
                ];
            }

            return [
                'employeeId' => $employeeId,
                'name' => (string)$employee['name'],
                'email' => (string)$employee['email'],
                'role' => (string)$employee['role'],
                'departmentId' => $employee['department_id'] !== null ? (int)$employee['department_id'] : null,
                'departmentType' => (string)($employee['department_type'] ?? ''),
                'departmentName' => (string)($employee['department'] ?? 'Unassigned'),
                'hourlyWage' => (float)$employee['hourly_wage'],
                'status' => (string)$employee['status'],
                'weeklyHours' => $weeklyHours,
                'totalWeekHours' => round($totalMinutes / 60, 2),
                'recentShifts' => $recentShiftsByEmployee[$employeeId] ?? [],
            ];
        },
        $employees
    ),
    'logs' => array_map(
        static fn(array $log): array => [
            'logId' => (int)$log['hr_action_log_id'],
            'actionType' => (string)$log['action_type'],
            'employeeName' => (string)($log['employee_name'] ?? ''),
            'handledByName' => (string)($log['handled_by_name'] ?? 'HR'),
            'details' => (string)($log['details'] ?? ''),
            'createdAt' => (string)$log['created_at'],
        ],
        $logs
    ),
    'sickRequests' => array_map(
        static fn(array $request): array => [
            'sickRequestId' => (int)$request['sick_request_id'],
            'employeeId' => (int)$request['employee_id'],
            'employeeName' => (string)$request['employee_name'],
            'requestDate' => (string)$request['request_date'],
            'status' => (string)$request['status'],
            'notes' => (string)($request['notes'] ?? ''),
            'requestedAt' => (string)$request['requested_at'],
            'reviewedAt' => (string)($request['reviewed_at'] ?? ''),
            'reviewerName' => (string)($request['reviewer_name'] ?? ''),
            'reviewNotes' => (string)($request['review_notes'] ?? ''),
        ],
        $sickRequests
    ),
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>HR Dashboard</title>
  <?php echo frontendAssetsRender(); ?>
</head>
<body>
  <?php echo debugToolbarRender($user); ?>
  <?php echo frontendJsonScript('hr-dashboard-props', $frontendProps); ?>
  <div id="hr-dashboard-root" data-react-page="hrDashboard" data-props-id="hr-dashboard-props"></div>
  <div class="ams-fallback">
  <h1>HR Dashboard</h1>
    <p>This dashboard requires JavaScript for editing actions.</p>
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
    </tr>
    <?php foreach ($employees as $emp): ?>
      <tr>
        <td><?php echo htmlspecialchars($emp['name']); ?></td>
        <td><?php echo htmlspecialchars($emp['email']); ?></td>
        <td><?php echo htmlspecialchars($emp['role']); ?></td>
        <td><?php echo htmlspecialchars($emp['department']); ?></td>
        <td>$<?php echo number_format($emp['hourly_wage'], 2); ?></td>
        <td><?php echo htmlspecialchars($emp['status']); ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
</body>
</html>
