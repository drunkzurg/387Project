<?php

require_once __DIR__ . '/../Auth/Auth.php';
require_once __DIR__ . '/../Database/Database.php';

/**
 * @return list<array{userId:int,name:string,email:string,role:string}>
 */
function debugToolbarFetchUsers(): array
{
    try {
        $pdo = Database::connect();
    } catch (Throwable $e) {
        return [];
    }

    try {
        $stmt = $pdo->query(
            'SELECT user_id, name, email, role
             FROM users
             WHERE pending_approval = 0
             ORDER BY role ASC, email ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'userId' => (int)$row['user_id'],
            'name' => (string)$row['name'],
            'email' => (string)$row['email'],
            'role' => (string)$row['role'],
        ];
    }

    return $out;
}

function debugToolbarGetRoleHome(string $role): string
{
    $dashboards = [
        'sys_admin' => 'admin_dashboard.php',
        'owner' => 'owner_dashboard.php',
        'hr' => 'hr_dashboard.php',
        'employee' => 'employee_dashboard.php',
    ];

    return $dashboards[$role] ?? 'index.php';
}

function debugToolbarHandleRequest(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $action = $_POST['debug_toolbar_action'] ?? null;
    if (!is_string($action) || $action === '') {
        return;
    }

    if ($action === 'logout') {
        Auth::logout();
        header('Location: index.php');
        exit;
    }

    if ($action === 'login_as_user') {
        $userId = (int)($_POST['debug_toolbar_user_id'] ?? 0);
        if ($userId <= 0) {
            $_SESSION['debug_toolbar_error'] = 'Invalid user selected.';
            header('Location: index.php');
            exit;
        }

        Auth::logout();
        $user = Auth::loginAsUserIdForDebug($userId);

        if (is_array($user)) {
            header('Location: ' . debugToolbarGetRoleHome($user['role']));
            exit;
        }

        $_SESSION['debug_toolbar_error'] = 'Could not log in as that user (missing row, not approved, or database error).';
        header('Location: index.php');
        exit;
    }
}

function debugToolbarConsumeFlash(): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $message = $_SESSION['debug_toolbar_error'] ?? null;
    unset($_SESSION['debug_toolbar_error']);

    return is_string($message) && $message !== '' ? $message : null;
}

function debugToolbarRender(?array $currentUser = null): string
{
    $flash = debugToolbarConsumeFlash();
    $currentPath = basename((string)($_SERVER['PHP_SELF'] ?? ''));
    $users = debugToolbarFetchUsers();

    ob_start();
    ?>
    <aside id="debug-toolbar" style="position: fixed; right: 18px; bottom: 18px; z-index: 9999; font-family: Arial, sans-serif; font-size: 14px;">
        <button
            type="button"
            aria-controls="debug-toolbar-panel"
            aria-expanded="false"
            onclick="
                const panel = document.getElementById('debug-toolbar-panel');
                const isOpen = panel.style.display === 'block';
                panel.style.display = isOpen ? 'none' : 'block';
                this.setAttribute('aria-expanded', String(!isOpen));
            "
            style="width: 54px; height: 54px; border: 3px solid #000; border-radius: 999px; background: #ebd22f; color: #000; box-shadow: 5px 5px 0 #000; font-weight: 800; cursor: pointer;"
            title="Open debug toolbar"
        >
            DBG
        </button>
        <div id="debug-toolbar-panel" style="display: none; position: absolute; right: 0; bottom: 70px; width: min(480px, calc(100vw - 36px)); padding: 14px; border: 3px solid #000; border-radius: 8px; background: #fff; color: #000; box-shadow: 8px 8px 0 #000;">
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; justify-content: space-between;">
                <div>
                    <strong>Debug Toolbar</strong><br>
                    <span>Current page: <?php echo htmlspecialchars($currentPath, ENT_QUOTES, 'UTF-8'); ?></span><br>
                    <?php if ($currentUser !== null): ?>
                        <span>Signed in as <?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($currentUser['role'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                    <?php else: ?>
                        <span>Not signed in</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($flash !== null): ?>
                <p style="margin: 8px 0 0; color: #a40000;"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <div style="margin-top: 12px; display: flex; flex-wrap: wrap; gap: 8px;">
                <a href="index.php" style="padding: 6px 8px; border: 2px solid #000; border-radius: 5px; text-decoration: none; color: #000; background: #f7f7f7; box-shadow: 3px 3px 0 #000; text-align: center;">Home</a>
                <form method="post" action="" style="margin: 0;">
                    <input type="hidden" name="debug_toolbar_action" value="logout" />
                    <button type="submit" style="padding: 6px 8px; border: 2px solid #000; border-radius: 5px; background: #ff3b30; color: #000; box-shadow: 3px 3px 0 #000; cursor: pointer;">Logout</button>
                </form>
            </div>

            <?php if ($users === []): ?>
                <p style="margin: 0; color: #a40000;">No approved users — approve accounts in the admin dashboard, or check the database connection / seeds.</p>
            <?php else: ?>
                <div style="max-height: min(360px, 50vh); overflow-y: auto; margin-top: 4px; display: flex; flex-direction: column; gap: 6px;">
                    <?php foreach ($users as $u): ?>
                        <?php
                        $label = $u['name'] . ' — ' . $u['email'] . ' (' . $u['role'] . ')';
                        ?>
                        <form method="post" action="" style="margin: 0;">
                            <input type="hidden" name="debug_toolbar_action" value="login_as_user" />
                            <input type="hidden" name="debug_toolbar_user_id" value="<?php echo (int)$u['userId']; ?>" />
                            <button
                                type="submit"
                                title="<?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                style="width: 100%; padding: 8px 10px; border: 2px solid #000; border-radius: 5px; background: #fff; color: #000; box-shadow: 3px 3px 0 #000; cursor: pointer; text-align: left; font-size: 13px; line-height: 1.25;"
                            ><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
    <?php

    return (string)ob_get_clean();
}
