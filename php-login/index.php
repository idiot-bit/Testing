<?php
session_start();

function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function envVal(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return $value === false || $value === null || $value === '' ? $default : (string) $value;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

loadEnv(__DIR__ . '/.env');

$siteName = envVal('SITE_NAME', 'Aurora Access');
$faviconEmoji = envVal('FAVICON_EMOJI', '🔐');
$logoText = envVal('LOGO_TEXT', 'AA');

$users = [
    envVal('LOGIN_USER_1', 'demo') => envVal('LOGIN_PASS_1', 'demo123'),
    envVal('LOGIN_USER_2', 'admin') => envVal('LOGIN_PASS_2', 'admin123'),
];

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

$errors = [];
$screen = 'username';

if (($_SESSION['authenticated'] ?? false) === true) {
    $screen = 'welcome';
} elseif (isset($_SESSION['pending_username'])) {
    $screen = 'password';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'username') {
        $username = trim((string) ($_POST['username'] ?? ''));

        if ($username === '') {
            $errors[] = 'Username is required.';
            $screen = 'username';
        } elseif (!array_key_exists($username, $users)) {
            $errors[] = 'Username not found.';
            $screen = 'username';
        } else {
            $_SESSION['pending_username'] = $username;
            $screen = 'password';
        }
    }

    if ($action === 'password') {
        $password = (string) ($_POST['password'] ?? '');
        $pendingUsername = $_SESSION['pending_username'] ?? null;

        if ($pendingUsername === null) {
            $errors[] = 'Session ended. Start again.';
            $screen = 'username';
        } elseif ($password === '') {
            $errors[] = 'Password is required.';
            $screen = 'password';
        } elseif (!hash_equals((string) $users[$pendingUsername], $password)) {
            $errors[] = 'Incorrect password.';
            $screen = 'password';
        } else {
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $pendingUsername;
            unset($_SESSION['pending_username']);
            $screen = 'welcome';
        }
    }

    if ($action === 'back') {
        unset($_SESSION['pending_username']);
        $screen = 'username';
    }
}

$currentUsername = h($_SESSION['pending_username'] ?? '');
$loggedInUser = h($_SESSION['username'] ?? '');
$faviconHref = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">'
    . rawurlencode($faviconEmoji)
    . '</text></svg>';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo h($siteName); ?> · Login</title>
    <meta name="description" content="Two-step mobile login UI in PHP.">
    <link rel="icon" type="image/svg+xml" href="<?php echo h($faviconHref); ?>">
    <style>
        :root {
            --bg: #070b16;
            --bg-2: #120a25;
            --card: rgba(17, 25, 45, 0.72);
            --stroke: rgba(255, 255, 255, 0.12);
            --text: #f2f5ff;
            --muted: #a7b2d6;
            --brand-a: #7c3aed;
            --brand-b: #0ea5e9;
            --danger: #fb7185;
            --ok: #34d399;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            min-height: 100dvh;
            color: var(--text);
            font-family: Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif;
            background:
                radial-gradient(110% 70% at 85% -10%, rgba(14, 165, 233, .35), transparent 60%),
                radial-gradient(90% 70% at -10% 0%, rgba(124, 58, 237, .36), transparent 60%),
                linear-gradient(160deg, var(--bg), var(--bg-2));
            display: grid;
            place-items: center;
            padding: 10px;
            overflow-y: auto;
        }
        .phone {
            width: min(100%, 390px);
            min-height: 72dvh;
            border-radius: 26px;
            border: 1px solid var(--stroke);
            background: linear-gradient(150deg, rgba(255,255,255,.08), rgba(255,255,255,.03));
            backdrop-filter: blur(12px);
            box-shadow: 0 20px 50px rgba(0,0,0,.45);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .top {
            padding: 18px 16px 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo {
            width: 36px;
            height: 36px;
            border-radius: 11px;
            display: grid;
            place-items: center;
            font-weight: 700;
            letter-spacing: .4px;
            background: linear-gradient(145deg, var(--brand-a), var(--brand-b));
            color: #fff;
            box-shadow: inset 0 0 12px rgba(255,255,255,.18);
        }
        .site {
            font-size: .95rem;
            font-weight: 650;
        }
        .step {
            font-size: .73rem;
            color: var(--muted);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 999px;
            padding: 5px 9px;
            white-space: nowrap;
        }
        .content {
            padding: 10px 16px 16px;
            display: grid;
            gap: 10px;
            margin-top: auto;
            margin-bottom: auto;
        }
        h1 {
            margin: 0;
            font-size: 1.35rem;
            letter-spacing: .2px;
        }
        .sub {
            margin: 0;
            color: var(--muted);
            font-size: .92rem;
            line-height: 1.45;
        }
        .error {
            font-size: .89rem;
            border-radius: 12px;
            padding: 10px 11px;
            background: rgba(251, 113, 133, .14);
            border: 1px solid rgba(251, 113, 133, .45);
            color: #ffe3e9;
        }
        label {
            font-size: .88rem;
            color: #dbe3ff;
            margin-bottom: 7px;
            display: block;
        }
        .field {
            width: 100%;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 14px;
            background: rgba(1, 8, 24, .45);
            color: var(--text);
            padding: 12px;
            outline: none;
            font-size: .96rem;
            margin-bottom: 12px;
        }
        .field:focus {
            border-color: #67e8f9;
            box-shadow: 0 0 0 3px rgba(103, 232, 249, .2);
        }
        .btn {
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 12px;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-main {
            color: #fff;
            background: linear-gradient(135deg, var(--brand-a), var(--brand-b));
            box-shadow: 0 10px 20px rgba(14, 165, 233, .28);
        }
        .btn-soft {
            margin-top: 9px;
            border: 1px solid rgba(255,255,255,.16);
            color: #e5e7eb;
            background: rgba(255,255,255,.07);
        }
        .ok {
            border: 1px solid rgba(52, 211, 153, .45);
            background: rgba(16, 185, 129, .15);
            color: #dbfff2;
            border-radius: 12px;
            padding: 10px 11px;
            font-size: .9rem;
        }
        .foot {
            margin-top: 8px;
            color: var(--muted);
            font-size: .8rem;
        }
        .link { color: #bfdbfe; text-decoration: none; font-weight: 600; }

        @media (min-width: 600px) {
            body { padding: 18px; }
            .phone { min-height: 68dvh; }
        }
    </style>
</head>
<body>
    <main class="phone">
        <header class="top">
            <div class="brand">
                <div class="logo"><?php echo h($logoText); ?></div>
                <div class="site"><?php echo h($siteName); ?></div>
            </div>
            <div class="step">
                <?php echo $screen === 'password' ? '2 / 2' : ($screen === 'username' ? '1 / 2' : 'Done'); ?>
            </div>
        </header>

        <section class="content">
            <?php if ($screen === 'username'): ?>
                <h1>Sign in</h1>
                <p class="sub">Enter your username to continue.</p>
                <?php foreach ($errors as $error): ?>
                    <div class="error"><?php echo h($error); ?></div>
                <?php endforeach; ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="username">
                    <label for="username">Username</label>
                    <input class="field" id="username" type="text" name="username" placeholder="your username" required>
                    <button class="btn btn-main" type="submit">Continue</button>
                </form>

            <?php elseif ($screen === 'password'): ?>
                <h1>Enter password</h1>
                <p class="sub">Username: <strong><?php echo $currentUsername; ?></strong></p>
                <?php foreach ($errors as $error): ?>
                    <div class="error"><?php echo h($error); ?></div>
                <?php endforeach; ?>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="password">
                    <label for="password">Password</label>
                    <input class="field" id="password" type="password" name="password" placeholder="your password" required>
                    <button class="btn btn-main" type="submit">Login</button>
                </form>

                <form method="post">
                    <input type="hidden" name="action" value="back">
                    <button class="btn btn-soft" type="submit">Back</button>
                </form>

            <?php else: ?>
                <h1>Welcome</h1>
                <div class="ok">Logged in as <strong><?php echo $loggedInUser; ?></strong>.</div>
                <p class="sub">Your session is active.</p>
                <p><a class="link" href="?logout=1">Logout</a></p>
                <p class="foot">Users come from <code>.env</code>.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
