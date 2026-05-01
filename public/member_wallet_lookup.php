<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database/Database.php';

header('Content-Type: application/json; charset=utf-8');

$respond = static function (array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(['ok' => false, 'message' => 'Unsupported request method.'], 405);
}

$code = trim((string)($_POST['membership_code'] ?? ''));
if ($code === '') {
    $respond(['ok' => false, 'message' => 'Membership code is required.'], 422);
}
if (strlen($code) > 50) {
    $respond(['ok' => false, 'message' => 'Membership code is too long.'], 422);
}

try {
    $pdo = Database::connect();
} catch (Throwable $e) {
    $respond(['ok' => false, 'message' => 'Service temporarily unavailable.'], 503);
}

$lookup = $pdo->prepare(
    'SELECT
        a.attendee_id,
        a.name,
        a.membership_code,
        w.ticket_account_id AS wallet_account_id,
        COALESCE(w.balance, 0) AS balance
     FROM attendees a
     LEFT JOIN ticket_accounts w
       ON w.attendee_id = a.attendee_id
      AND w.account_kind = \'member_wallet\'
     WHERE a.membership_code = :code
     LIMIT 1'
);
$lookup->execute(['code' => $code]);
$row = $lookup->fetch(PDO::FETCH_ASSOC);

if ($row === false) {
    $respond(['ok' => false, 'message' => 'No member found with that membership code.'], 404);
}

$attendeeId = (int)$row['attendee_id'];
$walletAccountId = $row['wallet_account_id'] !== null ? (int)$row['wallet_account_id'] : null;
$balance = (int)$row['balance'];

if ($walletAccountId !== null) {
    $txStmt = $pdo->prepare(
        'SELECT
            t.ticket_transaction_id,
            t.transaction_type,
            t.amount,
            t.note,
            t.created_at,
            t.source_account_id,
            t.destination_account_id,
            d.name AS department_name
         FROM ticket_transactions t
         LEFT JOIN departments d ON d.department_id = t.department_id
         WHERE t.attendee_id = :attendee_id
            OR t.source_account_id = :wallet_src
            OR t.destination_account_id = :wallet_dest
         ORDER BY t.created_at DESC, t.ticket_transaction_id DESC
         LIMIT 50'
    );
    $txStmt->execute([
        'attendee_id' => $attendeeId,
        'wallet_src' => $walletAccountId,
        'wallet_dest' => $walletAccountId,
    ]);
} else {
    $txStmt = $pdo->prepare(
        'SELECT
            t.ticket_transaction_id,
            t.transaction_type,
            t.amount,
            t.note,
            t.created_at,
            t.source_account_id,
            t.destination_account_id,
            d.name AS department_name
         FROM ticket_transactions t
         LEFT JOIN departments d ON d.department_id = t.department_id
         WHERE t.attendee_id = :attendee_id
         ORDER BY t.created_at DESC, t.ticket_transaction_id DESC
         LIMIT 50'
    );
    $txStmt->execute(['attendee_id' => $attendeeId]);
}

$rawTransactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);
$transactions = [];

foreach ($rawTransactions as $trow) {
    $amount = (int)$trow['amount'];
    $src = $trow['source_account_id'] !== null ? (int)$trow['source_account_id'] : null;
    $dst = $trow['destination_account_id'] !== null ? (int)$trow['destination_account_id'] : null;

    $delta = 0;
    if ($walletAccountId !== null) {
        if ($dst === $walletAccountId) {
            $delta = $amount;
        } elseif ($src === $walletAccountId) {
            $delta = -$amount;
        }
    }

    $createdAt = $trow['created_at'];
    if ($createdAt instanceof DateTimeInterface) {
        $createdAtIso = $createdAt->format('c');
    } else {
        $createdAtIso = (string)$createdAt;
    }

    $transactions[] = [
        'ticketTransactionId' => (int)$trow['ticket_transaction_id'],
        'transactionType' => (string)$trow['transaction_type'],
        'amount' => $amount,
        'delta' => $delta,
        'note' => $trow['note'] !== null ? (string)$trow['note'] : null,
        'createdAt' => $createdAtIso,
        'sourceAccountId' => $src,
        'destinationAccountId' => $dst,
        'departmentName' => $trow['department_name'] !== null ? (string)$trow['department_name'] : null,
    ];
}

$respond([
    'ok' => true,
    'member' => [
        'name' => (string)$row['name'],
        'membershipCode' => (string)$row['membership_code'],
    ],
    'walletAccountId' => $walletAccountId,
    'balance' => $balance,
    'transactions' => $transactions,
]);
