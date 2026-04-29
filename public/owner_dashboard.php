<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Debug/DebugToolbar.php';
require_once __DIR__ . '/../src/Services/TicketService.php';
require_once __DIR__ . '/../src/View/FrontendAssets.php';

debugToolbarHandleRequest();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = Auth::user();
if (!$user || $user['role'] !== 'owner') {
    header('Location: login.php');
    exit;
}

$pdo = Database::connect();
TicketService::ensureInfrastructure($pdo);

$flash = $_SESSION['owner_dashboard_flash'] ?? null;
$error = $_SESSION['owner_dashboard_error'] ?? null;
unset($_SESSION['owner_dashboard_flash'], $_SESSION['owner_dashboard_error']);

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$departmentLabel = static function (string $type): string {
    return match ($type) {
        'play_area' => 'Play Area',
        'gift_shop' => 'Gift Shop',
        'customer_support' => 'Customer Support',
        default => $type,
    };
};
$statusLabel = static fn(string $status): string => str_replace('_', ' ', ucfirst($status));
$availabilityLabel = static function (array $department): string {
    if ($department['department_type'] !== 'play_area') {
        return str_replace('_', ' ', ucfirst((string)$department['operating_status']));
    }

    if ($department['operating_status'] !== 'active') {
        return str_replace('_', ' ', ucfirst((string)$department['operating_status']));
    }

    return (int)$department['active_attendees'] >= (int)$department['capacity'] ? 'Waitlist' : 'Open';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? '');

        switch ($action) {
            case 'create_department':
                TicketService::createDepartment(
                    $pdo,
                    (string)($_POST['department_name'] ?? ''),
                    (string)($_POST['department_type'] ?? 'play_area'),
                    (int)($_POST['entrance_fee_tickets'] ?? 0),
                    (int)($_POST['capacity'] ?? 0),
                    (string)($_POST['operating_status'] ?? 'active'),
                    (string)($_POST['description'] ?? '')
                );
                $_SESSION['owner_dashboard_flash'] = 'Department created successfully.';
                break;

            case 'update_department':
                TicketService::updateDepartment(
                    $pdo,
                    (int)($_POST['department_id'] ?? 0),
                    (string)($_POST['name'] ?? ''),
                    (string)($_POST['department_type'] ?? 'play_area'),
                    (int)($_POST['entrance_fee_tickets'] ?? 0),
                    (int)($_POST['capacity'] ?? 0),
                    (string)($_POST['operating_status'] ?? 'active'),
                    (string)($_POST['description'] ?? '')
                );
                $_SESSION['owner_dashboard_flash'] = 'Department updated successfully.';
                break;

            case 'add_investment':
                TicketService::addInvestment(
                    $pdo,
                    (int)($_POST['amount'] ?? 0),
                    (int)$user['user_id']
                );
                $_SESSION['owner_dashboard_flash'] = 'Gift shop budget increased from owner investment.';
                break;

            case 'transfer_generated':
                TicketService::transferGeneratedToBudget(
                    $pdo,
                    (int)($_POST['department_id'] ?? 0),
                    (int)($_POST['amount'] ?? 0),
                    (int)$user['user_id']
                );
                $_SESSION['owner_dashboard_flash'] = 'Generated tickets moved into the gift shop budget.';
                break;

        }
    } catch (Throwable $e) {
        $_SESSION['owner_dashboard_error'] = $e->getMessage();
    }

    header('Location: owner_dashboard.php');
    exit;
}

$summary = [
    'gift_shop_budget' => 0,
    'gift_shop_revenue' => 0,
    'gift_shop_investment' => 0,
    'gift_shop_inventory_spend' => 0,
    'department_generated' => 0,
    'active_attendees' => 0,
];

$summaryRows = $pdo->query(
    "SELECT account_kind, balance
     FROM ticket_accounts
     WHERE account_kind IN (
        'gift_shop_budget',
        'gift_shop_revenue',
        'gift_shop_investment',
        'gift_shop_inventory_spend',
        'department_generated'
     )"
)->fetchAll();

foreach ($summaryRows as $row) {
    if (array_key_exists($row['account_kind'], $summary)) {
        $summary[$row['account_kind']] += (int)$row['balance'];
    }
}

$summary['active_attendees'] = (int)$pdo->query(
    'SELECT COUNT(*) FROM attendee_sessions WHERE closed_at IS NULL'
)->fetchColumn();

$summary['circulation'] = (int)$pdo->query(
    "SELECT COALESCE(SUM(balance), 0)
     FROM ticket_accounts
     WHERE account_kind <> 'gift_shop_investment'"
)->fetchColumn();

$departments = $pdo->query(
    "SELECT
        d.department_id,
        d.name,
        d.department_type,
        d.entrance_fee_tickets,
        d.capacity,
        d.operating_status,
        d.description,
        COALESCE(reserve_account.balance, 0) AS reserve_balance,
        COALESCE(generated_account.balance, 0) AS generated_balance,
        COALESCE(active_counts.active_attendees, 0) AS active_attendees
     FROM departments d
     LEFT JOIN ticket_accounts reserve_account
        ON reserve_account.department_id = d.department_id
       AND reserve_account.account_kind = 'department_reserve'
     LEFT JOIN ticket_accounts generated_account
        ON generated_account.department_id = d.department_id
       AND generated_account.account_kind = 'department_generated'
     LEFT JOIN (
        SELECT department_id, COUNT(*) AS active_attendees
        FROM attendee_sessions
        WHERE closed_at IS NULL
        GROUP BY department_id
     ) active_counts
        ON active_counts.department_id = d.department_id
     ORDER BY FIELD(d.department_type, 'play_area', 'gift_shop', 'customer_support'), d.name"
)->fetchAll();

$playAreaDepartments = array_values(array_filter(
    $departments,
    static fn(array $department): bool => $department['department_type'] === 'play_area'
));

$recentTransactions = $pdo->query(
    "SELECT
        tx.ticket_transaction_id,
        tx.transaction_type,
        tx.amount,
        tx.note,
        tx.created_at,
        d.name AS department_name,
        e.name AS employee_name,
        item.name AS item_name
     FROM ticket_transactions tx
     LEFT JOIN departments d ON d.department_id = tx.department_id
     LEFT JOIN employees e ON e.employee_id = tx.employee_id
     LEFT JOIN gift_shop_items item ON item.gift_shop_item_id = tx.gift_shop_item_id
     ORDER BY tx.ticket_transaction_id DESC
     LIMIT 12"
)->fetchAll();

$weekStart = new DateTimeImmutable('monday this week');
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $day = $weekStart->modify("+{$i} days");
    $weekDays[$day->format('Y-m-d')] = [
        'date' => $day->format('Y-m-d'),
        'label' => $day->format('D M j'),
    ];
}

$departmentTrendRowsStmt = $pdo->prepare(
    "SELECT
        DATE(tx.created_at) AS activity_date,
        tx.department_id,
        SUM(CASE WHEN tx.transaction_type IN ('department_admission', 'manual_override') THEN tx.amount ELSE 0 END) AS generated_tickets,
        SUM(CASE WHEN tx.transaction_type = 'department_payout' THEN tx.amount ELSE 0 END) AS payout_tickets
     FROM ticket_transactions tx
     WHERE tx.department_id IS NOT NULL
       AND tx.created_at >= :week_start
       AND tx.created_at < :week_end
       AND tx.transaction_type IN ('department_admission', 'manual_override', 'department_payout')
     GROUP BY DATE(tx.created_at), tx.department_id
     ORDER BY activity_date ASC"
);
$departmentTrendRowsStmt->execute([
    'week_start' => $weekStart->format('Y-m-d 00:00:00'),
    'week_end' => $weekStart->modify('+7 days')->format('Y-m-d 00:00:00'),
]);
$departmentTrendRows = $departmentTrendRowsStmt->fetchAll();

$departmentTrendByDay = [];
foreach ($departmentTrendRows as $row) {
    $date = (string)$row['activity_date'];
    $departmentId = (int)$row['department_id'];
    $departmentTrendByDay[$date][$departmentId] = [
        'generated' => (int)$row['generated_tickets'],
        'payout' => (int)$row['payout_tickets'],
        'net' => (int)$row['generated_tickets'] - (int)$row['payout_tickets'],
    ];
}

$departmentTrend = [];
foreach ($weekDays as $date => $day) {
    $point = [
        'date' => $day['date'],
        'label' => $day['label'],
    ];

    foreach ($departments as $department) {
        $departmentId = (int)$department['department_id'];
        $key = 'department_' . $departmentId;
        $values = $departmentTrendByDay[$date][$departmentId] ?? ['generated' => 0, 'payout' => 0, 'net' => 0];
        $point[$key] = $values['net'];
        $point[$key . '_generated'] = $values['generated'];
        $point[$key . '_payout'] = $values['payout'];
    }

    $departmentTrend[] = $point;
}

$activityStart = (new DateTimeImmutable('-13 days'))->format('Y-m-d 00:00:00');
$activityRowsStmt = $pdo->prepare(
    "SELECT
        tx.ticket_transaction_id,
        tx.transaction_type,
        tx.amount,
        tx.created_at,
        tx.department_id,
        d.name AS department_name,
        item.name AS item_name
     FROM ticket_transactions tx
     LEFT JOIN departments d ON d.department_id = tx.department_id
     LEFT JOIN gift_shop_items item ON item.gift_shop_item_id = tx.gift_shop_item_id
     LEFT JOIN ticket_accounts destination_account ON destination_account.ticket_account_id = tx.destination_account_id
     WHERE tx.created_at >= :activity_start
       AND tx.transaction_type IN (
        'department_admission',
        'department_payout',
        'gift_shop_redemption',
        'gift_shop_inventory_procurement',
        'gift_shop_inventory_credit',
        'owner_generated_transfer',
        'owner_investment',
        'manual_override'
       )
       AND (
        tx.transaction_type <> 'owner_investment'
        OR destination_account.account_kind = 'gift_shop_budget'
       )
     ORDER BY tx.created_at ASC, tx.ticket_transaction_id ASC"
);
$activityRowsStmt->execute(['activity_start' => $activityStart]);
$activityRows = $activityRowsStmt->fetchAll();

$signedAmount = static function (array $transaction): int {
    $type = (string) $transaction['transaction_type'];
    if ($type === 'department_payout' || $type === 'gift_shop_inventory_procurement') {
        return -1 * (int) $transaction['amount'];
    }

    return (int) $transaction['amount'];
};

$activityChart = array_map(
    static function (array $transaction) use ($signedAmount): array {
        return [
            'id' => (int)$transaction['ticket_transaction_id'],
            'label' => date('M j g:i A', strtotime((string)$transaction['created_at'])),
            'value' => $signedAmount($transaction),
            'type' => (string)$transaction['transaction_type'],
            'department' => (string)($transaction['department_name'] ?? ''),
            'item' => (string)($transaction['item_name'] ?? ''),
        ];
    },
    $activityRows
);

$investmentRowsStmt = $pdo->prepare(
    "SELECT
        tx.ticket_transaction_id,
        tx.amount,
        tx.note,
        tx.created_at,
        u.name AS created_by_name
     FROM ticket_transactions tx
     JOIN ticket_accounts destination_account
       ON destination_account.ticket_account_id = tx.destination_account_id
      AND destination_account.account_kind = 'gift_shop_budget'
     LEFT JOIN users u ON u.user_id = tx.created_by_user_id
     WHERE tx.transaction_type = 'owner_investment'
     ORDER BY tx.created_at DESC, tx.ticket_transaction_id DESC
     LIMIT 10"
);
$investmentRowsStmt->execute();
$investmentLogs = $investmentRowsStmt->fetchAll();

$departmentColors = ['#e3337e', '#b82063', '#ff6aa7', '#8f174d', '#f0a0c1', '#c6427d', '#6f123c'];
$frontendProps = [
    'currentUser' => [
        'name' => (string)$user['name'],
        'role' => (string)$user['role'],
    ],
    'flash' => $flash,
    'error' => $error,
    'summary' => [
        'credits' => $summary['gift_shop_budget'],
        'circulation' => $summary['circulation'],
        'giftShopRevenue' => $summary['gift_shop_revenue'],
        'inventoryProcurement' => $summary['gift_shop_inventory_spend'],
        'departmentGenerated' => $summary['department_generated'],
        'activeAttendees' => $summary['active_attendees'],
    ],
    'departments' => array_map(
        static function (array $department, int $index) use ($departmentColors): array {
            return [
                'departmentId' => (int)$department['department_id'],
                'key' => 'department_' . (int)$department['department_id'],
                'name' => (string)$department['name'],
                'departmentType' => (string)$department['department_type'],
                'entranceFeeTickets' => (int)$department['entrance_fee_tickets'],
                'capacity' => (int)$department['capacity'],
                'operatingStatus' => (string)$department['operating_status'],
                'description' => (string)($department['description'] ?? ''),
                'reserveBalance' => (int)$department['reserve_balance'],
                'generatedBalance' => (int)$department['generated_balance'],
                'color' => $departmentColors[$index % count($departmentColors)],
            ];
        },
        $departments,
        array_keys($departments)
    ),
    'departmentTrend' => $departmentTrend,
    'activityChart' => $activityChart,
    'recentTransactions' => array_map(
        static function (array $transaction) use ($signedAmount): array {
            return [
                'id' => (int)$transaction['ticket_transaction_id'],
                'type' => (string)$transaction['transaction_type'],
                'amount' => (int)$transaction['amount'],
                'signedAmount' => $signedAmount($transaction),
                'departmentName' => (string)($transaction['department_name'] ?? ''),
                'employeeName' => (string)($transaction['employee_name'] ?? ''),
                'itemName' => (string)($transaction['item_name'] ?? ''),
                'createdAt' => (string)$transaction['created_at'],
                'note' => (string)($transaction['note'] ?? ''),
            ];
        },
        $recentTransactions
    ),
    'investmentLogs' => array_map(
        static function (array $transaction): array {
            return [
                'id' => (int)$transaction['ticket_transaction_id'],
                'amount' => (int)$transaction['amount'],
                'createdAt' => (string)$transaction['created_at'],
                'createdByName' => (string)($transaction['created_by_name'] ?? 'Owner'),
                'note' => (string)($transaction['note'] ?? ''),
            ];
        },
        $investmentLogs
    ),
];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Owner Dashboard</title>
  <?php echo frontendAssetsRender(); ?>
</head>
<body>
  <?php echo debugToolbarRender($user); ?>
  <?php echo frontendJsonScript('owner-dashboard-props', $frontendProps); ?>
  <div id="owner-dashboard-root" data-react-page="ownerDashboard" data-props-id="owner-dashboard-props"></div>
  <div class="ams-fallback">
  <h1>Owner Dashboard</h1>
  <p>Manage the ticket economy and department budgets from one place.</p>

  <?php if ($flash !== null): ?>
    <p style="color: green;"><?php echo $escape((string)$flash); ?></p>
  <?php endif; ?>

  <?php if ($error !== null): ?>
    <p style="color: red;"><?php echo $escape((string)$error); ?></p>
  <?php endif; ?>

  <h2>Ticket Summary</h2>
  <ul>
    <li>Operating budget (play-area payouts and gift shop stocking draw from this pool): <strong><?php echo number_format($summary['gift_shop_budget']); ?></strong> tickets</li>
    <li>Inventory procurement recorded (ledger): <strong><?php echo number_format($summary['gift_shop_inventory_spend']); ?></strong> tickets</li>
    <li>Generated Tickets Waiting Transfer: <strong><?php echo number_format($summary['department_generated']); ?></strong> tickets</li>
    <li>Gift Shop Ticket Revenue: <strong><?php echo number_format($summary['gift_shop_revenue']); ?></strong> tickets</li>
    <li>Owner Investment Counter: <strong><?php echo number_format($summary['gift_shop_investment']); ?></strong> tickets</li>
    <li>Active Attendees Across Departments: <strong><?php echo number_format($summary['active_attendees']); ?></strong></li>
  </ul>

  <h2>Create Department</h2>
  <form method="post" action="">
    <input type="hidden" name="action" value="create_department">
    <label>
      Name:
      <input type="text" name="department_name" required>
    </label>
    <br>
    <label>
      Type:
      <select name="department_type" required>
        <option value="play_area">Play Area</option>
        <option value="gift_shop">Gift Shop</option>
        <option value="customer_support">Customer Support</option>
      </select>
    </label>
    <br>
    <label>
      Entrance Fee (10-100 for play areas):
      <input type="number" name="entrance_fee_tickets" min="0" max="100" value="10" required>
    </label>
    <br>
    <label>
      Capacity:
      <input type="number" name="capacity" min="0" step="1" value="10" required>
    </label>
    <br>
    <label>
      Status:
      <select name="operating_status" required>
        <option value="active">Active</option>
        <option value="out_of_order">Out Of Order</option>
        <option value="inactive">Inactive</option>
      </select>
    </label>
    <br>
    <label>
      Description:
      <input type="text" name="description" maxlength="255">
    </label>
    <br>
    <button type="submit">Create Department</button>
  </form>

  <h2>Department Controls</h2>
  <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
    <tr>
      <th>Name</th>
      <th>Type</th>
      <th>Entrance Fee</th>
      <th>Capacity</th>
      <th>Status</th>
      <th>Availability</th>
      <th>Generated</th>
      <th>Active Attendees</th>
      <th>Update</th>
    </tr>
    <?php foreach ($departments as $department): ?>
      <tr>
        <td><?php echo $escape((string)$department['name']); ?></td>
        <td><?php echo $escape($departmentLabel((string)$department['department_type'])); ?></td>
        <td><?php echo number_format((int)$department['entrance_fee_tickets']); ?></td>
        <td>
          <?php if ($department['department_type'] === 'play_area'): ?>
            <?php echo number_format((int)$department['capacity']); ?>
          <?php else: ?>
            N/A
          <?php endif; ?>
        </td>
        <td><?php echo $escape($statusLabel((string)$department['operating_status'])); ?></td>
        <td><?php echo $escape($availabilityLabel($department)); ?></td>
        <td><?php echo number_format((int)$department['generated_balance']); ?></td>
        <td>
          <?php if ($department['department_type'] === 'play_area'): ?>
            <?php echo number_format((int)$department['active_attendees']); ?> / <?php echo number_format((int)$department['capacity']); ?>
          <?php else: ?>
            <?php echo number_format((int)$department['active_attendees']); ?>
          <?php endif; ?>
        </td>
        <td>
          <form method="post" action="">
            <input type="hidden" name="action" value="update_department">
            <input type="hidden" name="department_id" value="<?php echo (int)$department['department_id']; ?>">
            <label>
              <span>Name</span><br>
              <input type="text" name="name" value="<?php echo $escape((string)$department['name']); ?>" required>
            </label>
            <br>
            <label>
              <span>Type</span><br>
              <select name="department_type" required>
                <option value="play_area" <?php echo $department['department_type'] === 'play_area' ? 'selected' : ''; ?>>Play Area</option>
                <option value="gift_shop" <?php echo $department['department_type'] === 'gift_shop' ? 'selected' : ''; ?>>Gift Shop</option>
                <option value="customer_support" <?php echo $department['department_type'] === 'customer_support' ? 'selected' : ''; ?>>Customer Support</option>
              </select>
            </label>
            <br>
            <label>
              <span>Entrance Fee</span><br>
              <input type="number" name="entrance_fee_tickets" min="0" max="100" value="<?php echo (int)$department['entrance_fee_tickets']; ?>" required>
            </label>
            <br>
            <label>
              <span>Capacity</span><br>
              <input type="number" name="capacity" min="0" step="1" value="<?php echo (int)$department['capacity']; ?>" required>
            </label>
            <br>
            <label>
              <span>Status</span><br>
              <select name="operating_status" required>
                <option value="active" <?php echo $department['operating_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="out_of_order" <?php echo $department['operating_status'] === 'out_of_order' ? 'selected' : ''; ?>>Out Of Order</option>
                <option value="inactive" <?php echo $department['operating_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
              </select>
            </label>
            <br>
            <label>
              <span>Description</span><br>
              <input type="text" name="description" maxlength="255" value="<?php echo $escape((string)($department['description'] ?? '')); ?>">
            </label>
            <br>
            <button type="submit">Save Department</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Gift Shop Budget Actions</h2>
  <h3>Add Owner Investment</h3>
  <form method="post" action="">
    <input type="hidden" name="action" value="add_investment">
    <label>
      Tickets To Add:
      <input type="number" name="amount" min="1" step="1" required>
    </label>
    <button type="submit">Increase Budget</button>
  </form>

  <h3>Move Generated Tickets Into Gift Shop Budget</h3>
  <form method="post" action="">
    <input type="hidden" name="action" value="transfer_generated">
    <label>
      Department:
      <select name="department_id" required>
        <?php foreach ($playAreaDepartments as $department): ?>
          <option value="<?php echo (int)$department['department_id']; ?>">
            <?php echo $escape((string)$department['name']); ?> (generated <?php echo number_format((int)$department['generated_balance']); ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      Tickets:
      <input type="number" name="amount" min="1" step="1" required>
    </label>
    <button type="submit">Transfer To Budget</button>
  </form>

  <h2>Recent Ticket Activity</h2>
  <?php if ($recentTransactions === []): ?>
    <p>No ticket transactions have been recorded yet.</p>
  <?php else: ?>
    <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
      <tr>
        <th>ID</th>
        <th>Type</th>
        <th>Amount</th>
        <th>Department</th>
        <th>Employee</th>
        <th>Item</th>
        <th>Created</th>
        <th>Note</th>
      </tr>
      <?php foreach ($recentTransactions as $transaction): ?>
        <tr>
          <td><?php echo (int)$transaction['ticket_transaction_id']; ?></td>
          <td><?php echo $escape((string)$transaction['transaction_type']); ?></td>
          <td><?php echo number_format((int)$transaction['amount']); ?></td>
          <td><?php echo $escape((string)($transaction['department_name'] ?? '')); ?></td>
          <td><?php echo $escape((string)($transaction['employee_name'] ?? '')); ?></td>
          <td><?php echo $escape((string)($transaction['item_name'] ?? '')); ?></td>
          <td><?php echo $escape((string)$transaction['created_at']); ?></td>
          <td><?php echo $escape((string)($transaction['note'] ?? '')); ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <p><a href="index.php">Back to Home</a></p>
  <p><a href="logout.php">Logout</a></p>
  </div>
</body>
</html>
