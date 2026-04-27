<?php
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Debug/DebugToolbar.php';
require_once __DIR__ . '/../src/View/FrontendAssets.php';

debugToolbarHandleRequest();

if (isset($_GET['db_test']) && $_GET['db_test'] === '1') {
	header('Content-Type: text/plain; charset=utf-8');
	try {
		$pdo = Database::connect();
		$pdo->query('SELECT 1');
		echo "DB connection: OK\n";
	} catch (Throwable $e) {
		http_response_code(500);
		echo "DB connection: ERROR\n";
	}
	exit;
}

http_response_code(200);
header('Content-Type: text/html; charset=utf-8');

$user = Auth::user();
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$dashboardPath = static function (?array $user): ?string {
	if ($user === null) {
		return null;
	}

	return match ($user['role']) {
		'sys_admin' => 'admin_dashboard.php',
		'owner' => 'owner_dashboard.php',
		'hr' => 'hr_dashboard.php',
		'employee' => 'employee_dashboard.php',
		default => null,
	};
};

$pendingApproval = false;
$playAreas = [];
$pdoError = null;

try {
	$pdo = Database::connect();

	if ($user !== null) {
		$stmt = $pdo->prepare('SELECT pending_approval FROM users WHERE user_id = :uid LIMIT 1');
		$stmt->execute(['uid' => $user['user_id']]);
		$pendingApproval = (bool)$stmt->fetchColumn();
	}

	$playAreas = $pdo->query(
		"SELECT
			d.department_id,
			d.name,
			d.description,
			d.entrance_fee_tickets,
			d.capacity,
			d.operating_status,
			COALESCE(active_counts.active_attendees, 0) AS active_attendees
		FROM departments d
		LEFT JOIN (
			SELECT department_id, COUNT(*) AS active_attendees
			FROM attendee_sessions
			WHERE closed_at IS NULL
			GROUP BY department_id
		) active_counts
			ON active_counts.department_id = d.department_id
		WHERE d.department_type = 'play_area'
		ORDER BY d.name"
	)->fetchAll();
} catch (Throwable $e) {
	$pdoError = 'Live department availability is temporarily unavailable.';
}

$departmentAvailability = static function (array $department): array {
	$status = (string)$department['operating_status'];
	$activeAttendees = (int)$department['active_attendees'];
	$capacity = (int)$department['capacity'];

	if ($status === 'inactive') {
		return ['label' => 'Unavailable', 'detail' => 'This department is currently offline.'];
	}
	if ($status === 'out_of_order') {
		return ['label' => 'Out Of Order', 'detail' => 'Ticket payout budget is depleted right now.'];
	}
	if ($capacity > 0 && $activeAttendees >= $capacity) {
		return ['label' => 'Waitlist', 'detail' => 'Capacity is full right now.'];
	}

	$availableSpots = max($capacity - $activeAttendees, 0);

	return [
		'label' => 'Open',
		'detail' => $capacity > 0 ? $availableSpots . ' spot(s) available.' : 'Open for walk-ins.'
	];
};

$frontendPlayAreas = array_map(
	static function (array $department) use ($departmentAvailability): array {
		$availability = $departmentAvailability($department);

		return [
			'departmentId' => (int)$department['department_id'],
			'name' => (string)$department['name'],
			'description' => (string)($department['description'] ?? ''),
			'entranceFeeTickets' => (int)$department['entrance_fee_tickets'],
			'capacity' => (int)$department['capacity'],
			'activeAttendees' => (int)$department['active_attendees'],
			'availability' => [
				'label' => $availability['label'],
				'detail' => $availability['detail'],
			],
		];
	},
	$playAreas
);

$frontendProps = [
	'user' => $user !== null ? [
		'name' => (string)$user['name'],
		'role' => (string)$user['role'],
	] : null,
	'dashboardPath' => $dashboardPath($user),
	'pendingApproval' => $pendingApproval,
	'pdoError' => $pdoError,
	'playAreas' => $frontendPlayAreas,
];
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Arcade Management System</title>
	<?php echo frontendAssetsRender(); ?>
</head>
<body>
	<?php echo debugToolbarRender($user); ?>
	<?php echo frontendJsonScript('public-home-props', $frontendProps); ?>
	<div id="public-home-root" data-react-page="publicHome" data-props-id="public-home-props"></div>
	<div class="ams-fallback">
	<h1>Arcade Management System</h1>
	<p>Check live department availability before you head to the counter.</p>

	<?php if ($user === null): ?>
		<p>
			Staff access:
			<a href="login.php">Login</a>
			|
			<a href="register.php">Create account</a>
		</p>
	<?php else: ?>
		<p>
			Signed in as
			<strong><?php echo $escape((string)$user['name']); ?></strong>
			(<?php echo $escape((string)$user['role']); ?>)
		</p>
		<p>
			<?php if ($dashboardPath($user) !== null && !$pendingApproval): ?>
				<a href="<?php echo $escape((string)$dashboardPath($user)); ?>">Go to Dashboard</a>
				|
			<?php endif; ?>
			<a href="logout.php">Logout</a>
		</p>
		<?php if ($pendingApproval): ?>
			<p>Your account is pending approval. You can still view the public arcade board here.</p>
		<?php endif; ?>
	<?php endif; ?>

	<h2>Live Department Board</h2>
	<p>Entry prices are listed in tickets, and attendance updates from active sessions logged by staff.</p>

	<?php if ($pdoError !== null): ?>
		<p style="color: red;"><?php echo $escape($pdoError); ?></p>
	<?php elseif ($playAreas === []): ?>
		<p>No play areas have been configured yet.</p>
	<?php else: ?>
		<table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
			<tr>
				<th>Department</th>
				<th>Entry Cost</th>
				<th>Attendance</th>
				<th>Status</th>
				<th>Details</th>
			</tr>
			<?php foreach ($playAreas as $department): ?>
				<?php $availability = $departmentAvailability($department); ?>
				<tr>
					<td>
						<strong><?php echo $escape((string)$department['name']); ?></strong><br>
						<?php echo $escape((string)($department['description'] ?? '')); ?>
					</td>
					<td><?php echo number_format((int)$department['entrance_fee_tickets']); ?> tickets</td>
					<td><?php echo number_format((int)$department['active_attendees']); ?> / <?php echo number_format((int)$department['capacity']); ?></td>
					<td><?php echo $escape($availability['label']); ?></td>
					<td><?php echo $escape($availability['detail']); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>

	<hr />
	<p><a href="index.php?db_test=1">DB smoke test</a></p>
	</div>
</body>
</html>
