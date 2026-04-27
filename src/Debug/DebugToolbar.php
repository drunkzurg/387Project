<?php

require_once __DIR__ . '/../Auth/Auth.php';
require_once __DIR__ . '/../Database/Database.php';

function debugToolbarGetDefaultAccounts(): array
{
    return [
        'sys_admin' => [
            'label' => 'Admin',
            'email' => 'admin@arcade.local',
            'password' => 'password',
        ],
        'owner' => [
            'label' => 'Owner',
            'email' => 'owner@arcade.local',
            'password' => 'password',
        ],
        'hr' => [
            'label' => 'HR',
            'email' => 'hr@arcade.local',
            'password' => 'password',
        ],
        'employee' => [
            'label' => 'Arcade Host',
            'email' => 'employee@arcade.local',
            'password' => 'password',
        ],
        'gift_shop_employee' => [
            'label' => 'Gift Shop Clerk',
            'email' => 'giftshop@arcade.local',
            'password' => 'password',
        ],
        'support_employee' => [
            'label' => 'Support Agent',
            'email' => 'support@arcade.local',
            'password' => 'password',
        ],
    ];
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
        header('Location: login.php');
        exit;
    }

    if ($action !== 'login_as') {
        return;
    }

    $role = $_POST['debug_toolbar_role'] ?? '';
    $accounts = debugToolbarGetDefaultAccounts();
    $account = $accounts[$role] ?? null;

    if ($account === null) {
        $_SESSION['debug_toolbar_error'] = 'Unknown debug account requested.';
        header('Location: index.php');
        exit;
    }

    Auth::logout();
    $user = Auth::attemptLogin($account['email'], $account['password']);

    if (is_array($user)) {
        header('Location: ' . debugToolbarGetRoleHome($user['role']));
        exit;
    }

    $_SESSION['debug_toolbar_error'] = 'Quick login failed. Load the sample seed users to use this toolbar.';
    header('Location: login.php');
    exit;
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
    $accounts = debugToolbarGetDefaultAccounts();

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
        <div id="debug-toolbar-panel" style="display: none; position: absolute; right: 0; bottom: 70px; width: min(420px, calc(100vw - 36px)); padding: 14px; border: 3px solid #000; border-radius: 8px; background: #fff; color: #000; box-shadow: 8px 8px 0 #000;">
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
                <div>Seed password: <code>password</code></div>
            </div>

            <?php if ($flash !== null): ?>
                <p style="margin: 8px 0 0; color: #a40000;"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <div style="margin-top: 12px; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px;">
                <a href="index.php" style="padding: 6px 8px; border: 2px solid #000; border-radius: 5px; text-decoration: none; color: #000; background: #f7f7f7; box-shadow: 3px 3px 0 #000; text-align: center;">Home</a>

                <?php foreach ($accounts as $role => $account): ?>
                    <form method="post" action="" style="margin: 0;">
                        <input type="hidden" name="debug_toolbar_action" value="login_as" />
                        <input type="hidden" name="debug_toolbar_role" value="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>" />
                        <button type="submit" style="width: 100%; padding: 6px 8px; border: 2px solid #000; border-radius: 5px; background: #fff; color: #000; box-shadow: 3px 3px 0 #000; cursor: pointer;">Login as <?php echo htmlspecialchars($account['label'], ENT_QUOTES, 'UTF-8'); ?></button>
                    </form>
                <?php endforeach; ?>

                <form method="post" action="" style="margin: 0;">
                    <input type="hidden" name="debug_toolbar_action" value="logout" />
                    <button type="submit" style="width: 100%; padding: 6px 8px; border: 2px solid #000; border-radius: 5px; background: #ff3b30; color: #000; box-shadow: 3px 3px 0 #000; cursor: pointer;">Logout</button>
                </form>
            </div>
        </div>
    </aside>
    <?php

    return (string)ob_get_clean();
}
