<?php

final class TicketService
{
    public function __construct()
    {
        throw new RuntimeException('TicketService is a static utility.');
    }

    public static function ensureInfrastructure(PDO $pdo): void
    {
        self::ensureEmployeeInfrastructure($pdo);
        self::ensureTicketLedgerSchema($pdo);
        self::ensureSystemAccount($pdo, 'gift_shop_budget');
        self::ensureSystemAccount($pdo, 'gift_shop_revenue');
        self::ensureSystemAccount($pdo, 'gift_shop_investment');
        self::ensureSystemAccount($pdo, 'gift_shop_inventory_spend');

        $departments = $pdo->query('SELECT department_id FROM departments')->fetchAll();
        foreach ($departments as $department) {
            $departmentId = (int)$department['department_id'];
            self::ensureDepartmentAccounts($pdo, $departmentId);
            self::syncDepartmentStaffingStatus($pdo, $departmentId);
        }
    }

    public static function ensureEmployeeInfrastructure(PDO $pdo): void
    {
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
                KEY idx_sick_requests_status (status),
                CONSTRAINT fk_sick_requests_employee
                    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_sick_requests_reviewer
                    FOREIGN KEY (reviewed_by_user_id) REFERENCES users(user_id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private static function ensureTicketLedgerSchema(PDO $pdo): void
    {
        $stmt = $pdo->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_accounts' AND COLUMN_NAME = 'account_kind'"
        );
        $row = $stmt ? $stmt->fetch() : null;
        if ($row && strpos((string) $row['COLUMN_TYPE'], 'gift_shop_inventory_spend') === false) {
            $pdo->exec(
                "ALTER TABLE ticket_accounts MODIFY COLUMN account_kind ENUM(
                    'gift_shop_budget','gift_shop_revenue','gift_shop_investment','gift_shop_inventory_spend',
                    'department_reserve','department_generated','member_wallet','session_wallet'
                ) NOT NULL"
            );
        }

        $stmt = $pdo->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_transactions' AND COLUMN_NAME = 'transaction_type'"
        );
        $row = $stmt ? $stmt->fetch() : null;
        if ($row && strpos((string) $row['COLUMN_TYPE'], 'gift_shop_inventory_procurement') === false) {
            $pdo->exec(
                "ALTER TABLE ticket_transactions MODIFY COLUMN transaction_type ENUM(
                    'department_admission','department_payout','gift_shop_redemption',
                    'gift_shop_inventory_procurement','gift_shop_inventory_credit',
                    'owner_allocation','owner_generated_transfer','owner_investment','member_claim_transfer','manual_override'
                ) NOT NULL"
            );
        }
    }

    public static function createDepartment(
        PDO $pdo,
        string $name,
        string $departmentType,
        int $entranceFeeTickets,
        int $capacity,
        string $operatingStatus,
        string $description
    ): int {
        $name = trim($name);
        $description = trim($description);
        self::assertDepartmentInput($name, $departmentType, $entranceFeeTickets, $capacity, $operatingStatus);

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO departments (name, department_type, entrance_fee_tickets, capacity, operating_status, description)
                 VALUES (:name, :department_type, :entrance_fee_tickets, :capacity, :operating_status, :description)'
            );
            $stmt->execute([
                'name' => $name,
                'department_type' => $departmentType,
                'entrance_fee_tickets' => self::normalizeEntranceFee($departmentType, $entranceFeeTickets),
                'capacity' => self::normalizeCapacity($departmentType, $capacity),
                'operating_status' => $operatingStatus,
                'description' => $description !== '' ? $description : null,
            ]);

            $departmentId = (int)$pdo->lastInsertId();
            self::ensureDepartmentAccounts($pdo, $departmentId);
            self::syncAllPlayAreasAgainstOperationalBudget($pdo);

            $pdo->commit();

            return $departmentId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function updateDepartment(
        PDO $pdo,
        int $departmentId,
        string $name,
        string $departmentType,
        int $entranceFeeTickets,
        int $capacity,
        string $operatingStatus,
        string $description
    ): void {
        $name = trim($name);
        $description = trim($description);
        self::assertDepartmentInput($name, $departmentType, $entranceFeeTickets, $capacity, $operatingStatus);

        $stmt = $pdo->prepare(
            'UPDATE departments
             SET name = :name,
                 department_type = :department_type,
                 entrance_fee_tickets = :entrance_fee_tickets,
                 capacity = :capacity,
                 operating_status = :operating_status,
                 description = :description
             WHERE department_id = :department_id'
        );
        $stmt->execute([
            'department_id' => $departmentId,
            'name' => $name,
            'department_type' => $departmentType,
            'entrance_fee_tickets' => self::normalizeEntranceFee($departmentType, $entranceFeeTickets),
            'capacity' => self::normalizeCapacity($departmentType, $capacity),
            'operating_status' => $operatingStatus,
            'description' => $description !== '' ? $description : null,
        ]);

        self::ensureDepartmentAccounts($pdo, $departmentId);
        self::syncAllPlayAreasAgainstOperationalBudget($pdo);
    }

    public static function createGiftShopItem(
        PDO $pdo,
        string $name,
        int $ticketPrice,
        int $costTicketsPerUnit,
        int $stock,
        string $category,
        string $description,
        int $giftShopDepartmentId,
        ?int $createdByUserId = null
    ): void {
        $name = trim($name);
        $category = trim($category);
        $description = trim($description);

        if ($name === '') {
            throw new RuntimeException('Gift shop items must have a name.');
        }
        if ($ticketPrice < 10 || $ticketPrice > 1000) {
            throw new RuntimeException('Gift shop item prices must stay between 10 and 1000 tickets.');
        }
        if ($stock < 0) {
            throw new RuntimeException('Gift shop item stock cannot be negative.');
        }
        if ($costTicketsPerUnit < 0) {
            throw new RuntimeException('Gift shop unit cost cannot be negative.');
        }

        $initialSpendTickets = $stock > 0 && $costTicketsPerUnit > 0
            ? $stock * $costTicketsPerUnit
            : 0;
        if ($initialSpendTickets < 0) {
            throw new RuntimeException('That stocking total overflowed the ticket amount.');
        }

        $pdo->beginTransaction();

        try {
            $budgetAccount = self::ensureSystemAccount($pdo, 'gift_shop_budget');
            $inventorySpendAccount = self::ensureSystemAccount($pdo, 'gift_shop_inventory_spend');

            if ($initialSpendTickets > 0) {
                self::postTransaction(
                    $pdo,
                    'gift_shop_inventory_procurement',
                    $initialSpendTickets,
                    (int)$budgetAccount['ticket_account_id'],
                    (int)$inventorySpendAccount['ticket_account_id'],
                    [
                        'department_id' => $giftShopDepartmentId,
                        'created_by_user_id' => $createdByUserId,
                        'note' => 'Initial inventory procurement (unit cost x stock, tickets).',
                    ]
                );
            }

            $stmt = $pdo->prepare(
                'INSERT INTO gift_shop_items (name, ticket_price, cost_price, stock, category, description)
                 VALUES (:name, :ticket_price, :cost_price, :stock, :category, :description)'
            );
            $stmt->execute([
                'name' => $name,
                'ticket_price' => $ticketPrice,
                'cost_price' => number_format((float) $costTicketsPerUnit, 2, '.', ''),
                'stock' => $stock,
                'category' => $category !== '' ? $category : null,
                'description' => $description !== '' ? $description : null,
            ]);

            self::syncAllPlayAreasAgainstOperationalBudget($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function updateGiftShopItem(
        PDO $pdo,
        int $itemId,
        string $name,
        int $ticketPrice,
        int $costTicketsPerUnit,
        int $stock,
        string $status,
        string $category,
        string $description,
        int $giftShopDepartmentId,
        ?int $createdByUserId = null
    ): void {
        $name = trim($name);
        $category = trim($category);
        $description = trim($description);

        if ($name === '') {
            throw new RuntimeException('Gift shop items must have a name.');
        }
        if ($ticketPrice < 10 || $ticketPrice > 1000) {
            throw new RuntimeException('Gift shop item prices must stay between 10 and 1000 tickets.');
        }
        if ($stock < 0) {
            throw new RuntimeException('Gift shop item stock cannot be negative.');
        }
        if ($costTicketsPerUnit < 0) {
            throw new RuntimeException('Gift shop unit cost cannot be negative.');
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Invalid gift shop item status.');
        }

        $pdo->beginTransaction();

        try {
            $itemStmt = $pdo->prepare(
                'SELECT gift_shop_item_id, stock, cost_price
                 FROM gift_shop_items
                 WHERE gift_shop_item_id = :item_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $itemStmt->execute(['item_id' => $itemId]);
            $existing = $itemStmt->fetch();

            if (!$existing) {
                throw new RuntimeException('Gift shop item not found.');
            }

            $oldStock = (int) $existing['stock'];
            $oldUnitCost = max(0, (int) round((float) $existing['cost_price']));
            $deltaStock = $stock - $oldStock;

            $budgetAccount = self::ensureSystemAccount($pdo, 'gift_shop_budget');
            $inventorySpendAccount = self::ensureSystemAccount($pdo, 'gift_shop_inventory_spend');

            if ($deltaStock > 0) {
                $spend = $deltaStock * $costTicketsPerUnit;
                if ($spend < 0) {
                    throw new RuntimeException('That stocking total overflowed the ticket amount.');
                }
                if ($spend > 0) {
                    self::postTransaction(
                        $pdo,
                        'gift_shop_inventory_procurement',
                        $spend,
                        (int) $budgetAccount['ticket_account_id'],
                        (int) $inventorySpendAccount['ticket_account_id'],
                        [
                            'department_id' => $giftShopDepartmentId,
                            'gift_shop_item_id' => $itemId,
                            'created_by_user_id' => $createdByUserId,
                            'note' => 'Additional inventory (unit cost x units added, tickets).',
                        ]
                    );
                }
            } elseif ($deltaStock < 0) {
                $removedUnits = -$deltaStock;
                $creditTickets = $removedUnits * $oldUnitCost;
                if ($creditTickets < 0) {
                    throw new RuntimeException('That inventory credit overflowed the ticket amount.');
                }
                if ($creditTickets > 0) {
                    self::postTransaction(
                        $pdo,
                        'gift_shop_inventory_credit',
                        $creditTickets,
                        (int) $inventorySpendAccount['ticket_account_id'],
                        (int) $budgetAccount['ticket_account_id'],
                        [
                            'department_id' => $giftShopDepartmentId,
                            'gift_shop_item_id' => $itemId,
                            'created_by_user_id' => $createdByUserId,
                            'note' => 'Inventory adjustment credit (units removed x prior unit cost, tickets).',
                        ]
                    );
                }
            }

            $stmt = $pdo->prepare(
                'UPDATE gift_shop_items
                 SET name = :name,
                     ticket_price = :ticket_price,
                     cost_price = :cost_price,
                     stock = :stock,
                     status = :status,
                     category = :category,
                     description = :description
                 WHERE gift_shop_item_id = :item_id'
            );
            $stmt->execute([
                'item_id' => $itemId,
                'name' => $name,
                'ticket_price' => $ticketPrice,
                'cost_price' => number_format((float) $costTicketsPerUnit, 2, '.', ''),
                'stock' => $stock,
                'status' => $status,
                'category' => $category !== '' ? $category : null,
                'description' => $description !== '' ? $description : null,
            ]);

            self::syncAllPlayAreasAgainstOperationalBudget($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function addInvestment(PDO $pdo, int $amount, ?int $createdByUserId = null): void
    {
        if ($amount <= 0) {
            throw new RuntimeException('Investment tickets must be greater than zero.');
        }

        $pdo->beginTransaction();

        try {
            $budgetAccount = self::ensureSystemAccount($pdo, 'gift_shop_budget');
            $investmentAccount = self::ensureSystemAccount($pdo, 'gift_shop_investment');

            self::postTransaction(
                $pdo,
                'owner_investment',
                $amount,
                null,
                (int)$budgetAccount['ticket_account_id'],
                ['created_by_user_id' => $createdByUserId, 'note' => 'Owner added ticket budget.']
            );
            self::postTransaction(
                $pdo,
                'owner_investment',
                $amount,
                null,
                (int)$investmentAccount['ticket_account_id'],
                ['created_by_user_id' => $createdByUserId, 'note' => 'Reporting counter for owner-backed tickets.']
            );

            self::syncAllPlayAreasAgainstOperationalBudget($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function allocateDepartmentReserve(PDO $pdo, int $departmentId, int $amount, ?int $createdByUserId = null): void
    {
        if ($amount === 0) {
            throw new RuntimeException('Budget adjustments cannot be zero.');
        }

        $pdo->beginTransaction();

        try {
            $budgetAccount = self::ensureSystemAccount($pdo, 'gift_shop_budget');
            $reserveAccount = self::ensureAccount($pdo, 'department_reserve', $departmentId);
            $moveAmount = abs($amount);

            if ($amount > 0) {
                self::postTransaction(
                    $pdo,
                    'owner_allocation',
                    $moveAmount,
                    (int)$budgetAccount['ticket_account_id'],
                    (int)$reserveAccount['ticket_account_id'],
                    ['department_id' => $departmentId, 'created_by_user_id' => $createdByUserId]
                );
            } else {
                self::postTransaction(
                    $pdo,
                    'owner_allocation',
                    $moveAmount,
                    (int)$reserveAccount['ticket_account_id'],
                    (int)$budgetAccount['ticket_account_id'],
                    ['department_id' => $departmentId, 'created_by_user_id' => $createdByUserId]
                );
            }

            self::syncAllPlayAreasAgainstOperationalBudget($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function transferGeneratedToBudget(PDO $pdo, int $departmentId, int $amount, ?int $createdByUserId = null): void
    {
        if ($amount <= 0) {
            throw new RuntimeException('Transfer tickets must be greater than zero.');
        }

        $pdo->beginTransaction();

        try {
            $generatedAccount = self::ensureAccount($pdo, 'department_generated', $departmentId);
            $budgetAccount = self::ensureSystemAccount($pdo, 'gift_shop_budget');

            self::postTransaction(
                $pdo,
                'owner_generated_transfer',
                $amount,
                (int)$generatedAccount['ticket_account_id'],
                (int)$budgetAccount['ticket_account_id'],
                ['department_id' => $departmentId, 'created_by_user_id' => $createdByUserId]
            );

            self::syncAllPlayAreasAgainstOperationalBudget($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function openSession(
        PDO $pdo,
        int $employeeId,
        int $departmentId,
        string $displayName,
        string $admissionMode,
        ?int $attendeeId,
        ?int $createdByUserId = null,
        string $notes = ''
    ): int {
        $displayName = trim($displayName);
        $notes = trim($notes);

        if ($displayName === '') {
            throw new RuntimeException('Sessions need a display name.');
        }
        if (!in_array($admissionMode, ['walk_in', 'member_wallet', 'manual_override'], true)) {
            throw new RuntimeException('Invalid admission mode.');
        }

        $department = self::fetchDepartment($pdo, $departmentId);
        if ($department === null || $department['department_type'] !== 'play_area') {
            throw new RuntimeException('Sessions can only be opened for play-area departments.');
        }
        if ($department['operating_status'] !== 'active') {
            throw new RuntimeException('This department is not active and cannot admit attendees right now.');
        }

        if ($admissionMode === 'member_wallet' && $attendeeId === null) {
            throw new RuntimeException('Member-wallet admissions require a member selection.');
        }

        $pdo->beginTransaction();

        try {
            $activeSessionCount = self::countActiveSessions($pdo, $departmentId);
            if ((int)$department['capacity'] > 0 && $activeSessionCount >= (int)$department['capacity']) {
                throw new RuntimeException('This department is at capacity. Please place new guests on the waitlist before opening another session.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO attendee_sessions (attendee_id, department_id, employee_id, display_name, admission_mode, entrance_fee_tickets, notes)
                 VALUES (:attendee_id, :department_id, :employee_id, :display_name, :admission_mode, :entrance_fee_tickets, :notes)'
            );
            $stmt->execute([
                'attendee_id' => $admissionMode === 'member_wallet' ? $attendeeId : null,
                'department_id' => $departmentId,
                'employee_id' => $employeeId,
                'display_name' => $displayName,
                'admission_mode' => $admissionMode,
                'entrance_fee_tickets' => (int)$department['entrance_fee_tickets'],
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $sessionId = (int)$pdo->lastInsertId();
            self::ensureAccount($pdo, 'session_wallet', null, null, $sessionId);
            $budgetAccount = self::ensureSystemAccount($pdo, 'gift_shop_budget');

            if ((int)$department['entrance_fee_tickets'] > 0) {
                $transactionType = $admissionMode === 'manual_override' ? 'manual_override' : 'department_admission';
                $sourceAccountId = null;
                if ($admissionMode === 'member_wallet') {
                    $memberWallet = self::ensureAccount($pdo, 'member_wallet', null, $attendeeId);
                    $sourceAccountId = (int)$memberWallet['ticket_account_id'];
                }

                self::postTransaction(
                    $pdo,
                    $transactionType,
                    (int)$department['entrance_fee_tickets'],
                    $sourceAccountId,
                    (int)$budgetAccount['ticket_account_id'],
                    [
                        'department_id' => $departmentId,
                        'attendee_id' => $admissionMode === 'member_wallet' ? $attendeeId : null,
                        'attendee_session_id' => $sessionId,
                        'employee_id' => $employeeId,
                        'created_by_user_id' => $createdByUserId,
                        'note' => $notes !== '' ? $notes : null,
                    ]
                );
            }

            self::syncAllPlayAreasAgainstOperationalBudget($pdo);
            $pdo->commit();

            return $sessionId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function closeSession(
        PDO $pdo,
        int $sessionId,
        int $employeeId,
        int $payoutTickets,
        ?int $createdByUserId = null,
        string $notes = ''
    ): void {
        if ($payoutTickets < 0) {
            throw new RuntimeException('Payout tickets cannot be negative.');
        }

        $session = self::fetchSession($pdo, $sessionId);
        if ($session === null) {
            throw new RuntimeException('Session not found.');
        }
        if ($session['closed_at'] !== null) {
            throw new RuntimeException('This attendee session has already been closed.');
        }

        $notes = trim($notes);
        $combinedNotes = trim((string)$session['notes']);
        if ($notes !== '') {
            $combinedNotes = $combinedNotes !== '' ? $combinedNotes . ' | ' . $notes : $notes;
        }

        $pdo->beginTransaction();

        try {
            if ($payoutTickets > 0) {
                $budgetAccount = self::ensureSystemAccount($pdo, 'gift_shop_budget');
                $destinationAccount = null;

                if ($session['attendee_id'] !== null && $session['admission_mode'] === 'member_wallet') {
                    $destinationAccount = self::ensureAccount($pdo, 'member_wallet', null, (int)$session['attendee_id']);
                } else {
                    $destinationAccount = self::ensureAccount($pdo, 'session_wallet', null, null, $sessionId);
                }

                self::postTransaction(
                    $pdo,
                    'department_payout',
                    $payoutTickets,
                    (int)$budgetAccount['ticket_account_id'],
                    (int)$destinationAccount['ticket_account_id'],
                    [
                        'department_id' => (int)$session['department_id'],
                        'attendee_id' => $session['attendee_id'] !== null ? (int)$session['attendee_id'] : null,
                        'attendee_session_id' => $sessionId,
                        'employee_id' => $employeeId,
                        'created_by_user_id' => $createdByUserId,
                        'note' => $notes !== '' ? $notes : null,
                    ]
                );
            }

            $update = $pdo->prepare(
                'UPDATE attendee_sessions
                 SET payout_tickets = :payout_tickets,
                     closed_at = NOW(),
                     notes = :notes
                 WHERE session_id = :session_id'
            );
            $update->execute([
                'payout_tickets' => $payoutTickets,
                'notes' => $combinedNotes !== '' ? $combinedNotes : null,
                'session_id' => $sessionId,
            ]);

            self::syncAllPlayAreasAgainstOperationalBudget($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function redeemGiftShopItem(
        PDO $pdo,
        int $departmentId,
        int $employeeId,
        int $itemId,
        int $quantity,
        int $sourceAccountId,
        ?int $attendeeId,
        ?int $sessionId,
        ?int $createdByUserId = null,
        string $notes = ''
    ): void {
        if ($quantity <= 0) {
            throw new RuntimeException('Redemption quantity must be greater than zero.');
        }

        $pdo->beginTransaction();

        try {
            $itemStmt = $pdo->prepare(
                'SELECT gift_shop_item_id, ticket_price, stock, status
                 FROM gift_shop_items
                 WHERE gift_shop_item_id = :item_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $itemStmt->execute(['item_id' => $itemId]);
            $item = $itemStmt->fetch();

            if (!$item) {
                throw new RuntimeException('Gift shop item not found.');
            }
            if ($item['status'] !== 'active') {
                throw new RuntimeException('This gift shop item is inactive.');
            }
            if ((int)$item['stock'] < $quantity) {
                throw new RuntimeException('Not enough stock is available for that redemption.');
            }

            $totalTickets = (int)$item['ticket_price'] * $quantity;
            $revenueAccount = self::ensureSystemAccount($pdo, 'gift_shop_revenue');

            self::postTransaction(
                $pdo,
                'gift_shop_redemption',
                $totalTickets,
                $sourceAccountId,
                (int)$revenueAccount['ticket_account_id'],
                [
                    'department_id' => $departmentId,
                    'attendee_id' => $attendeeId,
                    'attendee_session_id' => $sessionId,
                    'employee_id' => $employeeId,
                    'gift_shop_item_id' => $itemId,
                    'created_by_user_id' => $createdByUserId,
                    'note' => trim($notes) !== '' ? trim($notes) : null,
                ]
            );

            $updateStock = $pdo->prepare(
                'UPDATE gift_shop_items SET stock = stock - :quantity WHERE gift_shop_item_id = :item_id'
            );
            $updateStock->execute([
                'quantity' => $quantity,
                'item_id' => $itemId,
            ]);

            $insertRedemption = $pdo->prepare(
                'INSERT INTO gift_shop_redemptions (
                    gift_shop_item_id,
                    department_id,
                    employee_id,
                    attendee_id,
                    attendee_session_id,
                    source_account_id,
                    quantity,
                    total_tickets,
                    notes
                 ) VALUES (
                    :gift_shop_item_id,
                    :department_id,
                    :employee_id,
                    :attendee_id,
                    :attendee_session_id,
                    :source_account_id,
                    :quantity,
                    :total_tickets,
                    :notes
                 )'
            );
            $insertRedemption->execute([
                'gift_shop_item_id' => $itemId,
                'department_id' => $departmentId,
                'employee_id' => $employeeId,
                'attendee_id' => $attendeeId,
                'attendee_session_id' => $sessionId,
                'source_account_id' => $sourceAccountId,
                'quantity' => $quantity,
                'total_tickets' => $totalTickets,
                'notes' => trim($notes) !== '' ? trim($notes) : null,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function claimSessionToMember(
        PDO $pdo,
        int $sessionId,
        int $employeeId,
        string $name,
        string $email,
        string $membershipCode
    ): int {
        $name = trim($name);
        $email = trim($email);
        $membershipCode = trim($membershipCode);

        if ($name === '' || $membershipCode === '') {
            throw new RuntimeException('Member claims need a name and membership code.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Member claim email must be valid.');
        }

        $session = self::fetchSession($pdo, $sessionId);
        if ($session === null) {
            throw new RuntimeException('Session not found.');
        }
        if ($session['closed_at'] === null) {
            throw new RuntimeException('Only closed sessions can be claimed.');
        }
        if ($session['attendee_id'] !== null) {
            throw new RuntimeException('That session is already linked to a member.');
        }

        $pdo->beginTransaction();

        try {
            $insertAttendee = $pdo->prepare(
                'INSERT INTO attendees (name, email, membership_code, is_member, verified_by_employee_id, verified_at)
                 VALUES (:name, :email, :membership_code, 1, :verified_by_employee_id, NOW())'
            );
            $insertAttendee->execute([
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'membership_code' => $membershipCode,
                'verified_by_employee_id' => $employeeId,
            ]);
            $attendeeId = (int)$pdo->lastInsertId();

            $memberWallet = self::ensureAccount($pdo, 'member_wallet', null, $attendeeId);
            $sessionWallet = self::ensureAccount($pdo, 'session_wallet', null, null, $sessionId);

            if ((int)$sessionWallet['balance'] > 0) {
                self::postTransaction(
                    $pdo,
                    'member_claim_transfer',
                    (int)$sessionWallet['balance'],
                    (int)$sessionWallet['ticket_account_id'],
                    (int)$memberWallet['ticket_account_id'],
                    [
                        'department_id' => (int)$session['department_id'],
                        'attendee_id' => $attendeeId,
                        'attendee_session_id' => $sessionId,
                        'employee_id' => $employeeId,
                        'note' => 'Customer-support verification claim transfer.',
                    ]
                );
            }

            $updateSession = $pdo->prepare(
                'UPDATE attendee_sessions
                 SET attendee_id = :attendee_id
                 WHERE session_id = :session_id'
            );
            $updateSession->execute([
                'attendee_id' => $attendeeId,
                'session_id' => $sessionId,
            ]);

            $pdo->commit();

            return $attendeeId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public static function ensureSystemAccount(PDO $pdo, string $accountKind): array
    {
        return self::ensureAccount($pdo, $accountKind);
    }

    public static function ensureDepartmentAccounts(PDO $pdo, int $departmentId): void
    {
        self::ensureAccount($pdo, 'department_reserve', $departmentId);
        self::ensureAccount($pdo, 'department_generated', $departmentId);
    }

    public static function ensureAccount(
        PDO $pdo,
        string $accountKind,
        ?int $departmentId = null,
        ?int $attendeeId = null,
        ?int $sessionId = null
    ): array {
        $accountCode = self::buildAccountCode($accountKind, $departmentId, $attendeeId, $sessionId);
        $select = $pdo->prepare(
            'SELECT ticket_account_id, account_code, account_kind, department_id, attendee_id, attendee_session_id, balance
             FROM ticket_accounts
             WHERE account_code = :account_code
             LIMIT 1'
        );
        $select->execute(['account_code' => $accountCode]);
        $existing = $select->fetch();
        if ($existing) {
            return $existing;
        }

        $insert = $pdo->prepare(
            'INSERT INTO ticket_accounts (account_code, account_kind, department_id, attendee_id, attendee_session_id)
             VALUES (:account_code, :account_kind, :department_id, :attendee_id, :attendee_session_id)'
        );
        $insert->execute([
            'account_code' => $accountCode,
            'account_kind' => $accountKind,
            'department_id' => $departmentId,
            'attendee_id' => $attendeeId,
            'attendee_session_id' => $sessionId,
        ]);

        $select->execute(['account_code' => $accountCode]);

        return (array)$select->fetch();
    }

    private static function departmentHasClockedInStaff(PDO $pdo, int $departmentId): bool
    {
        $staffedStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM employee_shifts s
             JOIN employees e ON e.employee_id = s.employee_id
             WHERE e.department_id = :department_id
               AND e.status <> 'terminated'
               AND s.entry_type = 'live'
               AND s.end_time IS NULL"
        );
        $staffedStmt->execute(['department_id' => $departmentId]);

        return (int) $staffedStmt->fetchColumn() > 0;
    }

    /**
     * Play areas share one operating budget (gift_shop_budget). When staffed, they are active only while that budget has tickets left to cover payouts and operations.
     */
    private static function syncAllPlayAreasAgainstOperationalBudget(PDO $pdo): void
    {
        $budgetAccount = self::ensureSystemAccount($pdo, 'gift_shop_budget');
        $locked = self::lockAccount($pdo, (int) $budgetAccount['ticket_account_id']);
        $budgetBalance = (int) $locked['balance'];

        $stmt = $pdo->prepare(
            "SELECT department_id
             FROM departments
             WHERE department_type = 'play_area'
               AND operating_status <> 'inactive'"
        );
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            $deptId = (int) $row['department_id'];
            if (!self::departmentHasClockedInStaff($pdo, $deptId)) {
                continue;
            }
            $nextStatus = $budgetBalance > 0 ? 'active' : 'out_of_order';
            $update = $pdo->prepare(
                "UPDATE departments
                 SET operating_status = :operating_status
                 WHERE department_id = :department_id
                   AND operating_status <> 'inactive'"
            );
            $update->execute([
                'operating_status' => $nextStatus,
                'department_id' => $deptId,
            ]);
        }
    }

    public static function syncDepartmentStaffingStatus(PDO $pdo, ?int $departmentId): void
    {
        if ($departmentId === null || $departmentId <= 0) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT department_type, operating_status
             FROM departments
             WHERE department_id = :department_id
             LIMIT 1'
        );
        $stmt->execute(['department_id' => $departmentId]);
        $department = $stmt->fetch();

        if (!$department || $department['operating_status'] === 'inactive') {
            return;
        }

        if (!self::departmentHasClockedInStaff($pdo, $departmentId)) {
            $update = $pdo->prepare(
                "UPDATE departments
                 SET operating_status = 'out_of_order'
                 WHERE department_id = :department_id
                   AND operating_status <> 'inactive'"
            );
            $update->execute(['department_id' => $departmentId]);
            return;
        }

        if ($department['department_type'] === 'play_area') {
            self::syncAllPlayAreasAgainstOperationalBudget($pdo);
            return;
        }

        $update = $pdo->prepare(
            "UPDATE departments
             SET operating_status = 'active'
             WHERE department_id = :department_id
               AND operating_status <> 'inactive'"
        );
        $update->execute(['department_id' => $departmentId]);
    }

    public static function postTransaction(
        PDO $pdo,
        string $transactionType,
        int $amount,
        ?int $sourceAccountId,
        ?int $destinationAccountId,
        array $context = []
    ): int {
        if ($amount <= 0) {
            throw new RuntimeException('Ticket transactions must move a positive amount.');
        }
        if ($sourceAccountId === null && $destinationAccountId === null) {
            throw new RuntimeException('Ticket transactions need a source or destination account.');
        }

        if ($sourceAccountId !== null) {
            $source = self::lockAccount($pdo, $sourceAccountId);
            if ((int)$source['balance'] < $amount) {
                throw new RuntimeException('That action would overdraw the selected ticket account.');
            }

            $updateSource = $pdo->prepare(
                'UPDATE ticket_accounts SET balance = balance - :amount WHERE ticket_account_id = :ticket_account_id'
            );
            $updateSource->execute([
                'amount' => $amount,
                'ticket_account_id' => $sourceAccountId,
            ]);
        }

        if ($destinationAccountId !== null) {
            self::lockAccount($pdo, $destinationAccountId);
            $updateDestination = $pdo->prepare(
                'UPDATE ticket_accounts SET balance = balance + :amount WHERE ticket_account_id = :ticket_account_id'
            );
            $updateDestination->execute([
                'amount' => $amount,
                'ticket_account_id' => $destinationAccountId,
            ]);
        }

        $insert = $pdo->prepare(
            'INSERT INTO ticket_transactions (
                transaction_type,
                source_account_id,
                destination_account_id,
                amount,
                department_id,
                attendee_id,
                attendee_session_id,
                employee_id,
                gift_shop_item_id,
                created_by_user_id,
                note
             ) VALUES (
                :transaction_type,
                :source_account_id,
                :destination_account_id,
                :amount,
                :department_id,
                :attendee_id,
                :attendee_session_id,
                :employee_id,
                :gift_shop_item_id,
                :created_by_user_id,
                :note
             )'
        );
        $insert->execute([
            'transaction_type' => $transactionType,
            'source_account_id' => $sourceAccountId,
            'destination_account_id' => $destinationAccountId,
            'amount' => $amount,
            'department_id' => $context['department_id'] ?? null,
            'attendee_id' => $context['attendee_id'] ?? null,
            'attendee_session_id' => $context['attendee_session_id'] ?? null,
            'employee_id' => $context['employee_id'] ?? null,
            'gift_shop_item_id' => $context['gift_shop_item_id'] ?? null,
            'created_by_user_id' => $context['created_by_user_id'] ?? null,
            'note' => $context['note'] ?? null,
        ]);

        return (int)$pdo->lastInsertId();
    }

    private static function assertDepartmentInput(
        string $name,
        string $departmentType,
        int $entranceFeeTickets,
        int $capacity,
        string $operatingStatus
    ): void {
        if ($name === '') {
            throw new RuntimeException('Departments need a name.');
        }
        if (!in_array($departmentType, ['play_area', 'gift_shop', 'customer_support'], true)) {
            throw new RuntimeException('Invalid department type.');
        }
        if (!in_array($operatingStatus, ['active', 'out_of_order', 'inactive'], true)) {
            throw new RuntimeException('Invalid department status.');
        }
        if ($departmentType === 'play_area' && ($entranceFeeTickets < 10 || $entranceFeeTickets > 100)) {
            throw new RuntimeException('Play-area entrance fees must stay between 10 and 100 tickets.');
        }
        if ($departmentType === 'play_area' && $capacity < 1) {
            throw new RuntimeException('Play-area departments must define a capacity greater than zero.');
        }
        if ($departmentType !== 'play_area' && $entranceFeeTickets < 0) {
            throw new RuntimeException('Non-play-area entrance fees cannot be negative.');
        }
    }

    private static function normalizeEntranceFee(string $departmentType, int $entranceFeeTickets): int
    {
        return $departmentType === 'play_area' ? $entranceFeeTickets : 0;
    }

    private static function normalizeCapacity(string $departmentType, int $capacity): int
    {
        return $departmentType === 'play_area' ? $capacity : 0;
    }

    private static function fetchDepartment(PDO $pdo, int $departmentId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT department_id, department_type, entrance_fee_tickets, capacity, operating_status
             FROM departments
             WHERE department_id = :department_id
             LIMIT 1'
        );
        $stmt->execute(['department_id' => $departmentId]);
        $department = $stmt->fetch();

        return $department ?: null;
    }

    private static function countActiveSessions(PDO $pdo, int $departmentId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM attendee_sessions
             WHERE department_id = :department_id
               AND closed_at IS NULL'
        );
        $stmt->execute(['department_id' => $departmentId]);

        return (int)$stmt->fetchColumn();
    }

    private static function fetchSession(PDO $pdo, int $sessionId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT session_id, attendee_id, department_id, admission_mode, payout_tickets, notes, closed_at
             FROM attendee_sessions
             WHERE session_id = :session_id
             LIMIT 1'
        );
        $stmt->execute(['session_id' => $sessionId]);
        $session = $stmt->fetch();

        return $session ?: null;
    }

    private static function lockAccount(PDO $pdo, int $accountId): array
    {
        $stmt = $pdo->prepare(
            'SELECT ticket_account_id, balance
             FROM ticket_accounts
             WHERE ticket_account_id = :ticket_account_id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute(['ticket_account_id' => $accountId]);
        $account = $stmt->fetch();

        if (!$account) {
            throw new RuntimeException('Ticket account not found.');
        }

        return $account;
    }

    private static function buildAccountCode(
        string $accountKind,
        ?int $departmentId = null,
        ?int $attendeeId = null,
        ?int $sessionId = null
    ): string {
        return match ($accountKind) {
            'gift_shop_budget', 'gift_shop_revenue', 'gift_shop_investment', 'gift_shop_inventory_spend' => $accountKind,
            'department_reserve', 'department_generated' => $departmentId !== null
                ? $accountKind . ':' . $departmentId
                : throw new RuntimeException('Department ticket accounts require a department id.'),
            'member_wallet' => $attendeeId !== null
                ? $accountKind . ':' . $attendeeId
                : throw new RuntimeException('Member wallets require an attendee id.'),
            'session_wallet' => $sessionId !== null
                ? $accountKind . ':' . $sessionId
                : throw new RuntimeException('Session wallets require a session id.'),
            default => throw new RuntimeException('Unknown ticket account kind.'),
        };
    }
}
