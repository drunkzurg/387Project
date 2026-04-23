<?php
require_once __DIR__ . '/../src/Auth/Auth.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Debug/DebugToolbar.php';
require_once __DIR__ . '/../src/Services/TicketService.php';

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

            case 'allocate_budget':
                TicketService::allocateDepartmentReserve(
                    $pdo,
                    (int)($_POST['department_id'] ?? 0),
                    (int)($_POST['amount'] ?? 0),
                    (int)$user['user_id']
                );
                $_SESSION['owner_dashboard_flash'] = 'Department reserve allocation saved.';
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

            case 'create_item':
                TicketService::createGiftShopItem(
                    $pdo,
                    (string)($_POST['name'] ?? ''),
                    (int)($_POST['ticket_price'] ?? 0),
                    (float)($_POST['cost_price'] ?? 0),
                    (int)($_POST['stock'] ?? 0),
                    (string)($_POST['category'] ?? ''),
                    (string)($_POST['description'] ?? '')
                );
                $_SESSION['owner_dashboard_flash'] = 'Gift shop item added.';
                break;

            case 'update_item':
                TicketService::updateGiftShopItem(
                    $pdo,
                    (int)($_POST['item_id'] ?? 0),
                    (string)($_POST['name'] ?? ''),
                    (int)($_POST['ticket_price'] ?? 0),
                    (float)($_POST['cost_price'] ?? 0),
                    (int)($_POST['stock'] ?? 0),
                    (string)($_POST['status'] ?? 'active'),
                    (string)($_POST['category'] ?? ''),
                    (string)($_POST['description'] ?? '')
                );
                $_SESSION['owner_dashboard_flash'] = 'Gift shop item updated.';
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
    'department_reserve' => 0,
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
        'department_reserve',
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

$departments = $pdo->query(
    "SELECT
        d.department_id,
        d.name,
        d.department_type,
        d.entrance_fee_tickets,
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

$giftShopItems = $pdo->query(
    'SELECT gift_shop_item_id, name, ticket_price, cost_price, stock, status, category, description
     FROM gift_shop_items
     ORDER BY ticket_price ASC, name ASC'
)->fetchAll();

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
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Owner Dashboard</title>
</head>
<body>
  <?php echo debugToolbarRender($user); ?>
  <h1>Owner Dashboard</h1>
  <p>Manage the ticket economy, department budgets, and gift shop catalog from one place.</p>

  <?php if ($flash !== null): ?>
    <p style="color: green;"><?php echo $escape((string)$flash); ?></p>
  <?php endif; ?>

  <?php if ($error !== null): ?>
    <p style="color: red;"><?php echo $escape((string)$error); ?></p>
  <?php endif; ?>

  <h2>Ticket Summary</h2>
  <ul>
    <li>Gift Shop Budget Available: <strong><?php echo number_format($summary['gift_shop_budget']); ?></strong> tickets</li>
    <li>Tickets Allocated To Departments: <strong><?php echo number_format($summary['department_reserve']); ?></strong> tickets</li>
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
      <th>Status</th>
      <th>Reserve</th>
      <th>Generated</th>
      <th>Active Attendees</th>
      <th>Update</th>
    </tr>
    <?php foreach ($departments as $department): ?>
      <tr>
        <td><?php echo $escape((string)$department['name']); ?></td>
        <td><?php echo $escape($departmentLabel((string)$department['department_type'])); ?></td>
        <td><?php echo number_format((int)$department['entrance_fee_tickets']); ?></td>
        <td><?php echo $escape($statusLabel((string)$department['operating_status'])); ?></td>
        <td><?php echo number_format((int)$department['reserve_balance']); ?></td>
        <td><?php echo number_format((int)$department['generated_balance']); ?></td>
        <td><?php echo number_format((int)$department['active_attendees']); ?></td>
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

  <h3>Allocate Budget To A Department</h3>
  <form method="post" action="">
    <input type="hidden" name="action" value="allocate_budget">
    <label>
      Department:
      <select name="department_id" required>
        <?php foreach ($playAreaDepartments as $department): ?>
          <option value="<?php echo (int)$department['department_id']; ?>">
            <?php echo $escape((string)$department['name']); ?> (reserve <?php echo number_format((int)$department['reserve_balance']); ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      Tickets:
      <input type="number" name="amount" min="1" step="1" required>
    </label>
    <button type="submit">Allocate</button>
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

  <h2>Gift Shop Catalog</h2>
  <form method="post" action="">
    <input type="hidden" name="action" value="create_item">
    <label>
      Item Name:
      <input type="text" name="name" required>
    </label>
    <br>
    <label>
      Ticket Price (10-1000):
      <input type="number" name="ticket_price" min="10" max="1000" step="1" required>
    </label>
    <br>
    <label>
      Cost Price:
      <input type="number" name="cost_price" min="0" step="0.01" value="0.00" required>
    </label>
    <br>
    <label>
      Stock:
      <input type="number" name="stock" min="0" step="1" value="0" required>
    </label>
    <br>
    <label>
      Category:
      <input type="text" name="category">
    </label>
    <br>
    <label>
      Description:
      <input type="text" name="description" maxlength="255">
    </label>
    <br>
    <button type="submit">Add Gift Shop Item</button>
  </form>

  <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%; margin-top: 12px;">
    <tr>
      <th>Name</th>
      <th>Ticket Price</th>
      <th>Stock</th>
      <th>Status</th>
      <th>Category</th>
      <th>Update</th>
    </tr>
    <?php foreach ($giftShopItems as $item): ?>
      <tr>
        <td><?php echo $escape((string)$item['name']); ?></td>
        <td><?php echo number_format((int)$item['ticket_price']); ?></td>
        <td><?php echo number_format((int)$item['stock']); ?></td>
        <td><?php echo $escape((string)$item['status']); ?></td>
        <td><?php echo $escape((string)($item['category'] ?? '')); ?></td>
        <td>
          <form method="post" action="">
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="item_id" value="<?php echo (int)$item['gift_shop_item_id']; ?>">
            <label>
              <span>Name</span><br>
              <input type="text" name="name" value="<?php echo $escape((string)$item['name']); ?>" required>
            </label>
            <br>
            <label>
              <span>Ticket Price</span><br>
              <input type="number" name="ticket_price" min="10" max="1000" value="<?php echo (int)$item['ticket_price']; ?>" required>
            </label>
            <br>
            <label>
              <span>Cost Price</span><br>
              <input type="number" name="cost_price" min="0" step="0.01" value="<?php echo $escape(number_format((float)$item['cost_price'], 2, '.', '')); ?>" required>
            </label>
            <br>
            <label>
              <span>Stock</span><br>
              <input type="number" name="stock" min="0" value="<?php echo (int)$item['stock']; ?>" required>
            </label>
            <br>
            <label>
              <span>Status</span><br>
              <select name="status" required>
                <option value="active" <?php echo $item['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $item['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
              </select>
            </label>
            <br>
            <label>
              <span>Category</span><br>
              <input type="text" name="category" value="<?php echo $escape((string)($item['category'] ?? '')); ?>">
            </label>
            <br>
            <label>
              <span>Description</span><br>
              <input type="text" name="description" maxlength="255" value="<?php echo $escape((string)($item['description'] ?? '')); ?>">
            </label>
            <br>
            <button type="submit">Save Item</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

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
</body>
</html>
