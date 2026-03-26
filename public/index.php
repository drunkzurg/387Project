<?php
// Arcade Management System
// Project scaffold entrypoint (will be wired up later).

require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Auth/Auth.php';

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


$user = Auth::user();
if ($user !== null) {
	// Check approval status
	require_once __DIR__ . '/../src/Database/Database.php';
	$pdo = Database::connect();
	$stmt = $pdo->prepare('SELECT pending_approval FROM users WHERE user_id = :uid');
	$stmt->execute(['uid' => $user['user_id']]);
	$row = $stmt->fetch();
	if ($row && !empty($row['pending_approval'])) {
		// Show pending message, do not redirect
	} else {
		// Redirect to dashboard
		if ($user['role'] === 'sys_admin') {
			header('Location: admin_dashboard.php');
			exit;
		} elseif ($user['role'] === 'owner') {
			header('Location: owner_dashboard.php');
			exit;
		} elseif ($user['role'] === 'hr') {
			header('Location: hr_dashboard.php');
			exit;
		} elseif ($user['role'] === 'employee') {
			header('Location: employee_dashboard.php');
			exit;
		}
	}
}

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Arcade Management System</title>
</head>
<body>
	<h1>Arcade Management System</h1>

	<?php if ($user === null): ?>
		<p>You are not logged in.</p>
		<p>
			<a href="login.php">Login</a>
			|
			<a href="register.php">Create account</a>
		</p>
	<?php else: ?>
		<p>
			Logged in as
			<strong><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
			(<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>)
		</p>
		<p><a href="logout.php">Logout</a></p>
		<p>Next: role-gated dashboards (coming next).</p>

		<?php if ($user['role'] === 'sys_admin'): ?>
			<p style="margin-top: 16px;"><a href="admin_dashboard.php">Admin Dashboard</a></p>
		<?php endif; ?>
	<?php endif; ?>

	<hr />
	<p><a href="index.php?db_test=1">DB smoke test</a></p>
</body>
</html>
