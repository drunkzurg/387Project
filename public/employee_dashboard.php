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
if (!$user || $user['role'] !== 'employee') {
    header('Location: login.php');
    exit;
}

$pdo = Database::connect();
TicketService::ensureInfrastructure($pdo);

$employeeStmt = $pdo->prepare(
    "SELECT
        e.employee_id,
        e.name,
        e.status,
        e.hourly_wage,
        d.department_id,
        d.name AS department_name,
        d.department_type,
        d.entrance_fee_tickets,
        d.capacity,
        d.operating_status,
        COALESCE(reserve_account.balance, 0) AS reserve_balance,
        COALESCE(generated_account.balance, 0) AS generated_balance
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.department_id
     LEFT JOIN ticket_accounts reserve_account
        ON reserve_account.department_id = d.department_id
       AND reserve_account.account_kind = 'department_reserve'
     LEFT JOIN ticket_accounts generated_account
        ON generated_account.department_id = d.department_id
       AND generated_account.account_kind = 'department_generated'
     WHERE e.user_id = :user_id
     LIMIT 1"
);
$employeeStmt->execute(['user_id' => $user['user_id']]);
$employee = $employeeStmt->fetch();
if ($employee && $employee['department_id'] !== null) {
    TicketService::syncDepartmentStaffingStatus($pdo, (int)$employee['department_id']);
    $employeeStmt->execute(['user_id' => $user['user_id']]);
    $employee = $employeeStmt->fetch();
}

$flash = $_SESSION['employee_dashboard_flash'] ?? null;
$error = $_SESSION['employee_dashboard_error'] ?? null;
unset($_SESSION['employee_dashboard_flash'], $_SESSION['employee_dashboard_error']);

$startValue = '';
$endValue = '';

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$formatDateTime = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('M j, Y g:i A', $timestamp);
};
$formatHours = static fn(int $minutes): string => number_format($minutes / 60, 2) . ' hrs';
$departmentLabel = static function (?string $type): string {
    return match ($type) {
        'play_area' => 'Play Area',
        'gift_shop' => 'Gift Shop',
        'customer_support' => 'Customer Support',
        default => 'Unassigned',
    };
};
$statusLabel = static fn(string $status): string => str_replace('_', ' ', ucfirst($status));

if ($employee && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($employee['status'] === 'terminated') {
            throw new RuntimeException('Terminated employees cannot record new activity.');
        }

        $action = (string)($_POST['action'] ?? '');

        switch ($action) {
            case 'clock_in_shift':
                if ($employee['department_id'] === null) {
                    throw new RuntimeException('You need a department assignment before clocking in.');
                }

                $openShift = $pdo->prepare(
                    "SELECT shift_id FROM employee_shifts
                     WHERE employee_id = :employee_id
                       AND entry_type = 'live'
                       AND end_time IS NULL
                     LIMIT 1"
                );
                $openShift->execute(['employee_id' => $employee['employee_id']]);
                if ($openShift->fetch()) {
                    throw new RuntimeException('You already have an open live shift.');
                }

                $insertShift = $pdo->prepare(
                    "INSERT INTO employee_shifts (employee_id, start_time, end_time, entry_type)
                     VALUES (:employee_id, NOW(), NULL, 'live')"
                );
                $insertShift->execute(['employee_id' => $employee['employee_id']]);
                TicketService::syncDepartmentStaffingStatus($pdo, (int)$employee['department_id']);
                $_SESSION['employee_dashboard_flash'] = 'Live shift started.';
                break;

            case 'clock_out_shift':
                $closeShift = $pdo->prepare(
                    "UPDATE employee_shifts
                     SET end_time = NOW()
                     WHERE employee_id = :employee_id
                       AND entry_type = 'live'
                       AND end_time IS NULL
                     ORDER BY start_time DESC
                     LIMIT 1"
                );
                $closeShift->execute(['employee_id' => $employee['employee_id']]);
                if ($closeShift->rowCount() === 0) {
                    throw new RuntimeException('No open live shift found.');
                }

                TicketService::syncDepartmentStaffingStatus(
                    $pdo,
                    $employee['department_id'] !== null ? (int)$employee['department_id'] : null
                );
                $_SESSION['employee_dashboard_flash'] = 'Live shift closed.';
                break;

            case 'add_shift':
            case 'add_manual_shift':
                $startValue = trim((string)($_POST['start_time'] ?? ''));
                $endValue = trim((string)($_POST['end_time'] ?? ''));

                $startTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $startValue)
                    ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $startValue);
                $endTime = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $endValue)
                    ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $endValue);

                if (!$startTime || !$endTime) {
                    throw new RuntimeException('Please choose a valid start and end time.');
                }
                if ($endTime <= $startTime) {
                    throw new RuntimeException('Shift end time must be after the start time.');
                }

                $insertShift = $pdo->prepare(
                    "INSERT INTO employee_shifts (employee_id, start_time, end_time, entry_type)
                     VALUES (:employee_id, :start_time, :end_time, 'manual')"
                );
                $insertShift->execute([
                    'employee_id' => $employee['employee_id'],
                    'start_time' => $startTime->format('Y-m-d H:i:s'),
                    'end_time' => $endTime->format('Y-m-d H:i:s'),
                ]);
                $_SESSION['employee_dashboard_flash'] = 'Shift added successfully.';
                break;

            case 'request_sick_day':
                $requestDateValue = trim((string)($_POST['request_date'] ?? ''));
                $requestDate = DateTimeImmutable::createFromFormat('Y-m-d', $requestDateValue);
                if (!$requestDate) {
                    throw new RuntimeException('Please choose a valid sick day.');
                }

                $insertRequest = $pdo->prepare(
                    "INSERT INTO employee_sick_requests (employee_id, request_date, notes)
                     VALUES (:employee_id, :request_date, :notes)"
                );
                $insertRequest->execute([
                    'employee_id' => $employee['employee_id'],
                    'request_date' => $requestDate->format('Y-m-d'),
                    'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
                ]);
                $_SESSION['employee_dashboard_flash'] = 'Sick day request sent to HR.';
                break;

            case 'open_session':
                if ($employee['department_type'] !== 'play_area' || $employee['department_id'] === null) {
                    throw new RuntimeException('Only play-area employees can open attendee sessions.');
                }

                $memberId = (int)($_POST['attendee_id'] ?? 0);
                TicketService::openSession(
                    $pdo,
                    (int)$employee['employee_id'],
                    (int)$employee['department_id'],
                    (string)($_POST['display_name'] ?? ''),
                    (string)($_POST['admission_mode'] ?? 'walk_in'),
                    $memberId > 0 ? $memberId : null,
                    (int)$user['user_id'],
                    (string)($_POST['notes'] ?? '')
                );
                $_SESSION['employee_dashboard_flash'] = 'Attendee session opened.';
                break;

            case 'close_session':
                if ($employee['department_type'] !== 'play_area') {
                    throw new RuntimeException('Only play-area employees can close attendee sessions.');
                }

                TicketService::closeSession(
                    $pdo,
                    (int)($_POST['session_id'] ?? 0),
                    (int)$employee['employee_id'],
                    (int)($_POST['payout_tickets'] ?? 0),
                    (int)$user['user_id'],
                    (string)($_POST['notes'] ?? '')
                );
                $_SESSION['employee_dashboard_flash'] = 'Attendee session closed.';
                break;

            case 'create_item':
                if ($employee['department_type'] !== 'gift_shop' || $employee['department_id'] === null) {
                    throw new RuntimeException('Only gift shop staff can add catalog items.');
                }

                TicketService::createGiftShopItem(
                    $pdo,
                    (string)($_POST['name'] ?? ''),
                    (int)($_POST['ticket_price'] ?? 0),
                    (float)($_POST['cost_price'] ?? 0),
                    (int)($_POST['stock'] ?? 0),
                    (string)($_POST['category'] ?? ''),
                    (string)($_POST['description'] ?? '')
                );
                $_SESSION['employee_dashboard_flash'] = 'Gift shop item added.';
                break;

            case 'update_item':
                if ($employee['department_type'] !== 'gift_shop' || $employee['department_id'] === null) {
                    throw new RuntimeException('Only gift shop staff can update catalog items.');
                }

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
                $_SESSION['employee_dashboard_flash'] = 'Gift shop item updated.';
                break;

            case 'redeem_item':
                if ($employee['department_type'] !== 'gift_shop' || $employee['department_id'] === null) {
                    throw new RuntimeException('Only gift shop staff can redeem items.');
                }

                $sourceToken = explode(':', (string)($_POST['source_token'] ?? ''));
                if (count($sourceToken) !== 3) {
                    throw new RuntimeException('Please select a valid ticket source.');
                }

                [$sourceType, $entityId, $accountId] = $sourceToken;
                $attendeeId = null;
                $sessionId = null;
                if ($sourceType === 'member') {
                    $attendeeId = (int)$entityId;
                } elseif ($sourceType === 'session') {
                    $sessionId = (int)$entityId;
                } else {
                    throw new RuntimeException('Unknown redemption source type.');
                }

                TicketService::redeemGiftShopItem(
                    $pdo,
                    (int)$employee['department_id'],
                    (int)$employee['employee_id'],
                    (int)($_POST['item_id'] ?? 0),
                    (int)($_POST['quantity'] ?? 0),
                    (int)$accountId,
                    $attendeeId,
                    $sessionId,
                    (int)$user['user_id'],
                    (string)($_POST['notes'] ?? '')
                );
                $_SESSION['employee_dashboard_flash'] = 'Gift shop redemption recorded.';
                break;

            case 'claim_member':
                if ($employee['department_type'] !== 'customer_support') {
                    throw new RuntimeException('Only customer-support staff can verify member claims.');
                }

                TicketService::claimSessionToMember(
                    $pdo,
                    (int)($_POST['session_id'] ?? 0),
                    (int)$employee['employee_id'],
                    (string)($_POST['name'] ?? ''),
                    (string)($_POST['email'] ?? ''),
                    (string)($_POST['membership_code'] ?? '')
                );
                $_SESSION['employee_dashboard_flash'] = 'Session claim verified and converted into a member wallet.';
                break;
        }
    } catch (Throwable $e) {
        $_SESSION['employee_dashboard_error'] = $e->getMessage();
    }

    header('Location: employee_dashboard.php');
    exit;
}

$summary = [
    'today_minutes' => 0,
    'week_minutes' => 0,
    'total_minutes' => 0,
];
$shifts = [];
$members = [];
$activeSessions = [];
$recentSessions = [];
$walletSources = [];
$giftShopItems = [];
$redeemableGiftShopItems = [];
$giftShopBudgetAvailable = 0;
$recentRedemptions = [];
$claimCandidates = [];
$openLiveShift = null;
$sickRequests = [];
$approvedSickDaysThisWeek = 0;

if ($employee) {
    $durationExpr = 'CASE WHEN end_time > start_time THEN TIMESTAMPDIFF(MINUTE, start_time, end_time) ELSE 0 END';

    $summaryStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN DATE(start_time) = CURDATE() THEN {$durationExpr} ELSE 0 END), 0) AS today_minutes,
            COALESCE(SUM(CASE WHEN YEARWEEK(start_time, 1) = YEARWEEK(CURDATE(), 1) THEN {$durationExpr} ELSE 0 END), 0) AS week_minutes,
            COALESCE(SUM({$durationExpr}), 0) AS total_minutes
         FROM employee_shifts
         WHERE employee_id = :employee_id"
    );
    $summaryStmt->execute(['employee_id' => $employee['employee_id']]);
    $summaryRow = $summaryStmt->fetch();

    if ($summaryRow) {
        $summary = [
            'today_minutes' => (int)$summaryRow['today_minutes'],
            'week_minutes' => (int)$summaryRow['week_minutes'],
            'total_minutes' => (int)$summaryRow['total_minutes'],
        ];
    }

    $shiftStmt = $pdo->prepare(
        "SELECT shift_id, start_time, end_time, entry_type, {$durationExpr} AS duration_minutes
         FROM employee_shifts
         WHERE employee_id = :employee_id
         ORDER BY start_time DESC"
    );
    $shiftStmt->execute(['employee_id' => $employee['employee_id']]);
    $shifts = $shiftStmt->fetchAll();

    $openShiftStmt = $pdo->prepare(
        "SELECT shift_id, start_time
         FROM employee_shifts
         WHERE employee_id = :employee_id
           AND entry_type = 'live'
           AND end_time IS NULL
         ORDER BY start_time DESC
         LIMIT 1"
    );
    $openShiftStmt->execute(['employee_id' => $employee['employee_id']]);
    $openLiveShift = $openShiftStmt->fetch() ?: null;

    $weekStart = new DateTimeImmutable('monday this week');
    $approvedSickStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM employee_sick_requests
         WHERE employee_id = :employee_id
           AND status = 'approved'
           AND request_date >= :week_start
           AND request_date < :week_end"
    );
    $approvedSickStmt->execute([
        'employee_id' => $employee['employee_id'],
        'week_start' => $weekStart->format('Y-m-d'),
        'week_end' => $weekStart->modify('+7 days')->format('Y-m-d'),
    ]);
    $approvedSickDaysThisWeek = (int)$approvedSickStmt->fetchColumn();
    $summary['week_minutes'] += $approvedSickDaysThisWeek * 8 * 60;
    $summary['total_minutes'] += $approvedSickDaysThisWeek * 8 * 60;

    $sickStmt = $pdo->prepare(
        "SELECT sick_request_id, request_date, status, notes, requested_at, reviewed_at, review_notes
         FROM employee_sick_requests
         WHERE employee_id = :employee_id
         ORDER BY request_date DESC
         LIMIT 12"
    );
    $sickStmt->execute(['employee_id' => $employee['employee_id']]);
    $sickRequests = $sickStmt->fetchAll();

    $members = $pdo->query(
        "SELECT
            a.attendee_id,
            a.name,
            a.membership_code,
            COALESCE(wallet.balance, 0) AS wallet_balance
         FROM attendees a
         LEFT JOIN ticket_accounts wallet
            ON wallet.attendee_id = a.attendee_id
           AND wallet.account_kind = 'member_wallet'
         WHERE a.is_member = 1
         ORDER BY a.name"
    )->fetchAll();

    if ($employee['department_type'] === 'play_area' && $employee['department_id'] !== null) {
        $activeStmt = $pdo->prepare(
            "SELECT
                s.session_id,
                s.display_name,
                s.admission_mode,
                s.entrance_fee_tickets,
                s.opened_at,
                s.notes,
                a.name AS attendee_name,
                COALESCE(wallet.balance, 0) AS session_wallet_balance
             FROM attendee_sessions s
             LEFT JOIN attendees a ON a.attendee_id = s.attendee_id
             LEFT JOIN ticket_accounts wallet
                ON wallet.attendee_session_id = s.session_id
               AND wallet.account_kind = 'session_wallet'
             WHERE s.department_id = :department_id
               AND s.closed_at IS NULL
             ORDER BY s.opened_at ASC"
        );
        $activeStmt->execute(['department_id' => $employee['department_id']]);
        $activeSessions = $activeStmt->fetchAll();

        $recentStmt = $pdo->prepare(
            "SELECT
                s.session_id,
                s.display_name,
                s.admission_mode,
                s.entrance_fee_tickets,
                s.payout_tickets,
                s.opened_at,
                s.closed_at,
                s.notes,
                a.name AS attendee_name,
                COALESCE(session_wallet.balance, 0) AS session_wallet_balance
             FROM attendee_sessions s
             LEFT JOIN attendees a ON a.attendee_id = s.attendee_id
             LEFT JOIN ticket_accounts session_wallet
                ON session_wallet.attendee_session_id = s.session_id
               AND session_wallet.account_kind = 'session_wallet'
             WHERE s.department_id = :department_id
               AND s.closed_at IS NOT NULL
             ORDER BY s.closed_at DESC
             LIMIT 10"
        );
        $recentStmt->execute(['department_id' => $employee['department_id']]);
        $recentSessions = $recentStmt->fetchAll();
    }

    if ($employee['department_type'] === 'gift_shop') {
        $giftShopBudgetAvailable = (int)($employee['reserve_balance'] ?? 0);

        $giftShopItems = $pdo->query(
            'SELECT gift_shop_item_id, name, ticket_price, cost_price, stock, status, category, description
             FROM gift_shop_items
             ORDER BY ticket_price ASC, name ASC'
        )->fetchAll();
        $redeemableGiftShopItems = array_values(array_filter(
            $giftShopItems,
            static fn(array $item): bool => $item['status'] === 'active'
        ));

        $memberWallets = $pdo->query(
            "SELECT
                CONCAT('member:', a.attendee_id, ':', wallet.ticket_account_id) AS source_token,
                a.name AS source_label,
                wallet.balance
             FROM attendees a
             JOIN ticket_accounts wallet
               ON wallet.attendee_id = a.attendee_id
              AND wallet.account_kind = 'member_wallet'
             WHERE a.is_member = 1
               AND wallet.balance > 0
             ORDER BY a.name"
        )->fetchAll();

        $sessionWallets = $pdo->query(
            "SELECT
                CONCAT('session:', s.session_id, ':', wallet.ticket_account_id) AS source_token,
                CONCAT(s.display_name, ' (', d.name, ')') AS source_label,
                wallet.balance
             FROM attendee_sessions s
             JOIN departments d ON d.department_id = s.department_id
             JOIN ticket_accounts wallet
               ON wallet.attendee_session_id = s.session_id
              AND wallet.account_kind = 'session_wallet'
             WHERE s.closed_at IS NOT NULL
               AND wallet.balance > 0
             ORDER BY s.closed_at DESC"
        )->fetchAll();

        $walletSources = array_merge($memberWallets, $sessionWallets);

        $recentRedemptions = $pdo->query(
            "SELECT
                r.redemption_id,
                r.quantity,
                r.total_tickets,
                r.redeemed_at,
                item.name AS item_name,
                attendee.name AS attendee_name,
                session.display_name AS session_name
             FROM gift_shop_redemptions r
             JOIN gift_shop_items item ON item.gift_shop_item_id = r.gift_shop_item_id
             LEFT JOIN attendees attendee ON attendee.attendee_id = r.attendee_id
             LEFT JOIN attendee_sessions session ON session.session_id = r.attendee_session_id
             ORDER BY r.redemption_id DESC
             LIMIT 10"
        )->fetchAll();
    }

    if ($employee['department_type'] === 'customer_support') {
        $claimCandidates = $pdo->query(
            "SELECT
                s.session_id,
                s.display_name,
                s.closed_at,
                d.name AS department_name,
                COALESCE(wallet.balance, 0) AS wallet_balance
             FROM attendee_sessions s
             JOIN departments d ON d.department_id = s.department_id
             JOIN ticket_accounts wallet
               ON wallet.attendee_session_id = s.session_id
              AND wallet.account_kind = 'session_wallet'
             WHERE s.attendee_id IS NULL
               AND s.closed_at IS NOT NULL
               AND wallet.balance > 0
             ORDER BY s.closed_at DESC"
        )->fetchAll();
    }
}

$mapShift = static function (array $shift) use ($formatDateTime, $formatHours): array {
    return [
        'shiftId' => (int)$shift['shift_id'],
        'startTime' => (string)$shift['start_time'],
        'endTime' => $shift['end_time'] !== null ? (string)$shift['end_time'] : null,
        'entryType' => (string)($shift['entry_type'] ?? 'manual'),
        'durationMinutes' => (int)$shift['duration_minutes'],
        'formattedStart' => $formatDateTime((string)$shift['start_time']),
        'formattedEnd' => $shift['end_time'] !== null ? $formatDateTime((string)$shift['end_time']) : 'Open',
        'formattedHours' => $formatHours((int)$shift['duration_minutes']),
    ];
};

$frontendProps = [
    'currentUser' => [
        'name' => (string)$user['name'],
        'role' => (string)$user['role'],
    ],
    'flash' => $flash,
    'error' => $error,
    'employee' => $employee ? [
        'employeeId' => (int)$employee['employee_id'],
        'name' => (string)$employee['name'],
        'status' => (string)$employee['status'],
        'hourlyWage' => (float)$employee['hourly_wage'],
        'departmentId' => $employee['department_id'] !== null ? (int)$employee['department_id'] : null,
        'departmentName' => (string)($employee['department_name'] ?? 'Unassigned'),
        'departmentType' => (string)($employee['department_type'] ?? ''),
        'departmentLabel' => $departmentLabel($employee['department_type'] ?? null),
        'entranceFeeTickets' => (int)($employee['entrance_fee_tickets'] ?? 0),
        'capacity' => (int)($employee['capacity'] ?? 0),
        'operatingStatus' => (string)($employee['operating_status'] ?? ''),
        'reserveBalance' => (int)($employee['reserve_balance'] ?? 0),
        'generatedBalance' => (int)($employee['generated_balance'] ?? 0),
    ] : null,
    'summary' => [
        'todayHours' => round($summary['today_minutes'] / 60, 2),
        'weekHours' => round($summary['week_minutes'] / 60, 2),
        'approvedSickDaysThisWeek' => $approvedSickDaysThisWeek,
        'weekTargetHours' => 40 + ($approvedSickDaysThisWeek * 8),
        'totalHours' => round($summary['total_minutes'] / 60, 2),
    ],
    'openLiveShift' => $openLiveShift ? [
        'shiftId' => (int)$openLiveShift['shift_id'],
        'startTime' => (string)$openLiveShift['start_time'],
        'formattedStart' => $formatDateTime((string)$openLiveShift['start_time']),
    ] : null,
    'shifts' => array_map($mapShift, $shifts),
    'sickRequests' => array_map(
        static fn(array $request): array => [
            'sickRequestId' => (int)$request['sick_request_id'],
            'requestDate' => (string)$request['request_date'],
            'status' => (string)$request['status'],
            'notes' => (string)($request['notes'] ?? ''),
            'requestedAt' => (string)$request['requested_at'],
            'reviewedAt' => (string)($request['reviewed_at'] ?? ''),
            'reviewNotes' => (string)($request['review_notes'] ?? ''),
        ],
        $sickRequests
    ),
    'members' => array_map(
        static fn(array $member): array => [
            'attendeeId' => (int)$member['attendee_id'],
            'name' => (string)$member['name'],
            'membershipCode' => (string)($member['membership_code'] ?? ''),
            'walletBalance' => (int)$member['wallet_balance'],
        ],
        $members
    ),
    'activeSessions' => array_map(
        static fn(array $session): array => [
            'sessionId' => (int)$session['session_id'],
            'displayName' => (string)$session['display_name'],
            'admissionMode' => (string)$session['admission_mode'],
            'entranceFeeTickets' => (int)$session['entrance_fee_tickets'],
            'openedAt' => (string)$session['opened_at'],
            'attendeeName' => (string)($session['attendee_name'] ?? 'Walk-In'),
            'sessionWalletBalance' => (int)$session['session_wallet_balance'],
        ],
        $activeSessions
    ),
    'recentSessions' => array_map(
        static fn(array $session): array => [
            'sessionId' => (int)$session['session_id'],
            'displayName' => (string)$session['display_name'],
            'admissionMode' => (string)$session['admission_mode'],
            'payoutTickets' => (int)($session['payout_tickets'] ?? 0),
            'closedAt' => (string)$session['closed_at'],
            'attendeeName' => (string)($session['attendee_name'] ?? 'Walk-In'),
            'sessionWalletBalance' => (int)$session['session_wallet_balance'],
        ],
        $recentSessions
    ),
    'giftShopBudgetAvailable' => $giftShopBudgetAvailable,
    'giftShopItems' => array_map(
        static fn(array $item): array => [
            'itemId' => (int)$item['gift_shop_item_id'],
            'name' => (string)$item['name'],
            'ticketPrice' => (int)$item['ticket_price'],
            'costPrice' => (float)($item['cost_price'] ?? 0),
            'stock' => (int)$item['stock'],
            'status' => (string)$item['status'],
            'category' => (string)($item['category'] ?? ''),
            'description' => (string)($item['description'] ?? ''),
        ],
        $giftShopItems
    ),
    'redeemableGiftShopItems' => array_map(
        static fn(array $item): array => [
            'itemId' => (int)$item['gift_shop_item_id'],
            'name' => (string)$item['name'],
            'ticketPrice' => (int)$item['ticket_price'],
            'stock' => (int)$item['stock'],
        ],
        $redeemableGiftShopItems
    ),
    'walletSources' => array_map(
        static fn(array $source): array => [
            'sourceToken' => (string)$source['source_token'],
            'sourceLabel' => (string)$source['source_label'],
            'balance' => (int)$source['balance'],
        ],
        $walletSources
    ),
    'recentRedemptions' => array_map(
        static fn(array $redemption): array => [
            'redemptionId' => (int)$redemption['redemption_id'],
            'itemName' => (string)$redemption['item_name'],
            'quantity' => (int)$redemption['quantity'],
            'totalTickets' => (int)$redemption['total_tickets'],
            'attendeeName' => (string)($redemption['attendee_name'] ?? ''),
            'sessionName' => (string)($redemption['session_name'] ?? ''),
            'redeemedAt' => (string)$redemption['redeemed_at'],
        ],
        $recentRedemptions
    ),
    'claimCandidates' => array_map(
        static fn(array $candidate): array => [
            'sessionId' => (int)$candidate['session_id'],
            'displayName' => (string)$candidate['display_name'],
            'departmentName' => (string)$candidate['department_name'],
            'closedAt' => (string)$candidate['closed_at'],
            'walletBalance' => (int)$candidate['wallet_balance'],
        ],
        $claimCandidates
    ),
];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Employee Dashboard</title>
  <?php echo frontendAssetsRender(); ?>
</head>
<body>
  <?php echo debugToolbarRender($user); ?>
  <?php echo frontendJsonScript('employee-dashboard-props', $frontendProps); ?>
  <div id="employee-dashboard-root" data-react-page="employeeDashboard" data-props-id="employee-dashboard-props"></div>
  <div class="ams-fallback">
  <h1>Employee Dashboard</h1>

  <?php if ($flash !== null): ?>
    <p style="color: green;"><?php echo $escape((string)$flash); ?></p>
  <?php endif; ?>

  <?php if ($error !== null): ?>
    <p style="color: red;"><?php echo $escape((string)$error); ?></p>
  <?php endif; ?>

  <?php if (!$employee): ?>
    <p>Your employee profile has not been set up yet. Please contact HR before logging shifts or ticket activity.</p>
  <?php else: ?>
    <p>
      Logged in as <strong><?php echo $escape((string)$employee['name']); ?></strong>.
      Department: <strong><?php echo $escape((string)($employee['department_name'] ?? 'Unassigned')); ?></strong>.
      Department Type: <strong><?php echo $escape($departmentLabel($employee['department_type'] ?? null)); ?></strong>.
      Wage: <strong>$<?php echo number_format((float)$employee['hourly_wage'], 2); ?>/hr</strong>.
      Status: <strong><?php echo $escape((string)$employee['status']); ?></strong>.
    </p>

    <?php if ($employee['department_id'] !== null): ?>
      <ul>
        <li>Department Status: <strong><?php echo $escape($statusLabel((string)$employee['operating_status'])); ?></strong></li>
        <li>Entrance Fee: <strong><?php echo number_format((int)$employee['entrance_fee_tickets']); ?></strong> tickets</li>
        <?php if ($employee['department_type'] === 'play_area'): ?>
          <li>Capacity: <strong><?php echo number_format((int)$employee['capacity']); ?></strong> attendees</li>
        <?php endif; ?>
        <li>Department Reserve: <strong><?php echo number_format((int)$employee['reserve_balance']); ?></strong> tickets</li>
        <li>Department Generated: <strong><?php echo number_format((int)$employee['generated_balance']); ?></strong> tickets</li>
      </ul>
    <?php endif; ?>

    <h2>Add Shift</h2>
    <form method="post" action="">
      <input type="hidden" name="action" value="add_shift">
      <label>
        Start Time:
        <input type="datetime-local" name="start_time" value="<?php echo $escape($startValue); ?>" required>
      </label>
      <br>
      <label>
        End Time:
        <input type="datetime-local" name="end_time" value="<?php echo $escape($endValue); ?>" required>
      </label>
      <br>
      <button type="submit">Add Shift</button>
    </form>

    <h2>Logged Hours</h2>
    <p>Hours are calculated from the shifts saved below.</p>
    <ul>
      <li>Today: <?php echo $formatHours($summary['today_minutes']); ?></li>
      <li>This week: <?php echo $formatHours($summary['week_minutes']); ?></li>
      <li>Total logged: <?php echo $formatHours($summary['total_minutes']); ?></li>
    </ul>

    <h2>My Shifts</h2>
    <?php if ($shifts === []): ?>
      <p>No shifts logged yet.</p>
    <?php else: ?>
      <table border="1" cellpadding="6" style="border-collapse: collapse;">
        <tr>
          <th>Start</th>
          <th>End</th>
          <th>Logged Hours</th>
        </tr>
        <?php foreach ($shifts as $shift): ?>
          <tr>
            <td><?php echo $escape($formatDateTime((string)$shift['start_time'])); ?></td>
            <td><?php echo $escape($formatDateTime((string)$shift['end_time'])); ?></td>
            <td><?php echo $formatHours((int)$shift['duration_minutes']); ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

    <?php if ($employee['department_type'] === 'play_area' && $employee['department_id'] !== null): ?>
      <h2>Open Attendee Session</h2>
      <p>
        Active attendees are any sessions that have been opened but not yet closed with a payout.
        Current attendance: <strong><?php echo number_format(count($activeSessions)); ?> / <?php echo number_format((int)$employee['capacity']); ?></strong>.
      </p>
      <form method="post" action="">
        <input type="hidden" name="action" value="open_session">
        <label>
          Display Name:
          <input type="text" name="display_name" required>
        </label>
        <br>
        <label>
          Admission Mode:
          <select name="admission_mode" required>
            <option value="walk_in">Walk-In</option>
            <option value="member_wallet">Member Wallet</option>
            <option value="manual_override">Manual Override</option>
          </select>
        </label>
        <br>
        <label>
          Member (required for member-wallet admissions):
          <select name="attendee_id">
            <option value="0">No member selected</option>
            <?php foreach ($members as $member): ?>
              <option value="<?php echo (int)$member['attendee_id']; ?>">
                <?php echo $escape((string)$member['name']); ?>
                (<?php echo $escape((string)($member['membership_code'] ?? 'no-code')); ?>,
                balance <?php echo number_format((int)$member['wallet_balance']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <br>
        <label>
          Notes:
          <input type="text" name="notes" maxlength="255">
        </label>
        <br>
        <button type="submit">Open Session</button>
      </form>

      <h2>Active Attendees</h2>
      <?php if ($activeSessions === []): ?>
        <p>No active attendee sessions in this department.</p>
      <?php else: ?>
        <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
          <tr>
            <th>Display Name</th>
            <th>Member</th>
            <th>Mode</th>
            <th>Opened</th>
            <th>Entrance Fee</th>
            <th>Close Session</th>
          </tr>
          <?php foreach ($activeSessions as $session): ?>
            <tr>
              <td><?php echo $escape((string)$session['display_name']); ?></td>
              <td><?php echo $escape((string)($session['attendee_name'] ?? 'Walk-In')); ?></td>
              <td><?php echo $escape((string)$session['admission_mode']); ?></td>
              <td><?php echo $escape($formatDateTime((string)$session['opened_at'])); ?></td>
              <td><?php echo number_format((int)$session['entrance_fee_tickets']); ?></td>
              <td>
                <form method="post" action="">
                  <input type="hidden" name="action" value="close_session">
                  <input type="hidden" name="session_id" value="<?php echo (int)$session['session_id']; ?>">
                  <label>
                    Payout Tickets:
                    <input type="number" name="payout_tickets" min="0" step="1" value="0" required>
                  </label>
                  <br>
                  <label>
                    Notes:
                    <input type="text" name="notes" maxlength="255">
                  </label>
                  <br>
                  <button type="submit">Close Session</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>

      <h2>Recent Closed Sessions</h2>
      <?php if ($recentSessions === []): ?>
        <p>No attendee sessions have been closed yet.</p>
      <?php else: ?>
        <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
          <tr>
            <th>Display Name</th>
            <th>Member</th>
            <th>Mode</th>
            <th>Payout</th>
            <th>Closed</th>
            <th>Remaining Session Wallet</th>
          </tr>
          <?php foreach ($recentSessions as $session): ?>
            <tr>
              <td><?php echo $escape((string)$session['display_name']); ?></td>
              <td><?php echo $escape((string)($session['attendee_name'] ?? 'Walk-In')); ?></td>
              <td><?php echo $escape((string)$session['admission_mode']); ?></td>
              <td><?php echo number_format((int)($session['payout_tickets'] ?? 0)); ?></td>
              <td><?php echo $escape($formatDateTime((string)$session['closed_at'])); ?></td>
              <td><?php echo number_format((int)$session['session_wallet_balance']); ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($employee['department_type'] === 'gift_shop' && $employee['department_id'] !== null): ?>
      <h2>Gift Shop Budget</h2>
      <p>
        Gift shop budget allocated by owner:
        <strong><?php echo number_format($giftShopBudgetAvailable); ?></strong> tickets.
      </p>

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

      <h2>Gift Shop Redemptions</h2>
      <form method="post" action="">
        <input type="hidden" name="action" value="redeem_item">
        <label>
          Item:
          <select name="item_id" required>
            <?php foreach ($redeemableGiftShopItems as $item): ?>
              <option value="<?php echo (int)$item['gift_shop_item_id']; ?>">
                <?php echo $escape((string)$item['name']); ?> -
                <?php echo number_format((int)$item['ticket_price']); ?> tickets
                (stock <?php echo number_format((int)$item['stock']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <br>
        <label>
          Quantity:
          <input type="number" name="quantity" min="1" step="1" value="1" required>
        </label>
        <br>
        <label>
          Ticket Source:
          <select name="source_token" required>
            <?php foreach ($walletSources as $source): ?>
              <option value="<?php echo $escape((string)$source['source_token']); ?>">
                <?php echo $escape((string)$source['source_label']); ?> -
                balance <?php echo number_format((int)$source['balance']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <br>
        <label>
          Notes:
          <input type="text" name="notes" maxlength="255">
        </label>
        <br>
        <button type="submit">Redeem Item</button>
      </form>

      <h3>Recent Gift Shop Redemptions</h3>
      <?php if ($recentRedemptions === []): ?>
        <p>No gift shop redemptions have been logged yet.</p>
      <?php else: ?>
        <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
          <tr>
            <th>Item</th>
            <th>Quantity</th>
            <th>Total Tickets</th>
            <th>Member</th>
            <th>Session</th>
            <th>Redeemed</th>
          </tr>
          <?php foreach ($recentRedemptions as $redemption): ?>
            <tr>
              <td><?php echo $escape((string)$redemption['item_name']); ?></td>
              <td><?php echo number_format((int)$redemption['quantity']); ?></td>
              <td><?php echo number_format((int)$redemption['total_tickets']); ?></td>
              <td><?php echo $escape((string)($redemption['attendee_name'] ?? '')); ?></td>
              <td><?php echo $escape((string)($redemption['session_name'] ?? '')); ?></td>
              <td><?php echo $escape($formatDateTime((string)$redemption['redeemed_at'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($employee['department_type'] === 'customer_support'): ?>
      <h2>Customer Support Claims</h2>
      <p>Convert walk-in session balances into verified member wallets after reviewing the ticket claim.</p>
      <?php if ($claimCandidates === []): ?>
        <p>No claimable sessions are waiting for verification.</p>
      <?php else: ?>
        <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
          <tr>
            <th>Session</th>
            <th>Department</th>
            <th>Closed</th>
            <th>Claimable Tickets</th>
            <th>Create Member</th>
          </tr>
          <?php foreach ($claimCandidates as $candidate): ?>
            <tr>
              <td><?php echo $escape((string)$candidate['display_name']); ?></td>
              <td><?php echo $escape((string)$candidate['department_name']); ?></td>
              <td><?php echo $escape($formatDateTime((string)$candidate['closed_at'])); ?></td>
              <td><?php echo number_format((int)$candidate['wallet_balance']); ?></td>
              <td>
                <form method="post" action="">
                  <input type="hidden" name="action" value="claim_member">
                  <input type="hidden" name="session_id" value="<?php echo (int)$candidate['session_id']; ?>">
                  <label>
                    Name:
                    <input type="text" name="name" required>
                  </label>
                  <br>
                  <label>
                    Email:
                    <input type="email" name="email">
                  </label>
                  <br>
                  <label>
                    Membership Code:
                    <input type="text" name="membership_code" required>
                  </label>
                  <br>
                  <button type="submit">Verify Claim</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>

  <p><a href="index.php">Back to Home</a></p>
  <p><a href="logout.php">Logout</a></p>
  </div>
</body>
</html>
