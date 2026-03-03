<?php
session_start();

/**
 * Demo config for UI branding and login.
 * Replace demo credentials before production use.
 */
$config = [
    'site_name' => 'Aurora Access',
    'logo_mode' => 'text', // text | image
    'logo_text' => 'AA',
    'logo_image' => 'assets/logo.svg',
    'favicon_mode' => 'emoji', // emoji | file
    'favicon_emoji' => '🔐',
    'favicon_file' => 'assets/favicon.ico',
    'users' => [
        'demo' => 'demo123',
        'admin' => 'admin123',
    ],
];

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function buildFaviconHref(array $config): string
{
    if (($config['favicon_mode'] ?? 'emoji') === 'file') {
        return (string) ($config['favicon_file'] ?? 'favicon.ico');
    }

    $emoji = (string) ($config['favicon_emoji'] ?? '🔐');
    return 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">'
        . rawurlencode($emoji)
        . '</text></svg>';
}

function renderLogo(array $config): string
{
    $mode = $config['logo_mode'] ?? 'text';

    if ($mode === 'image') {
        $src = h((string) ($config['logo_image'] ?? 'assets/logo.svg'));
        $name = h((string) ($config['site_name'] ?? 'My Website'));
        return '<img src="' . $src . '" alt="' . $name . ' logo" class="logo-image">';
    }

    $text = h((string) ($config['logo_text'] ?? 'LG'));
    return '<div class="logo-text">' . $text . '</div>';
}

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
        } elseif (!array_key_exists($username, $config['users'])) {
            $errors[] = 'This username was not found.';
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
            $errors[] = 'Session expired. Please start again.';
            $screen = 'username';
        } elseif ($password === '') {
            $errors[] = 'Password is required.';
            $screen = 'password';
        } elseif (!hash_equals((string) $config['users'][$pendingUsername], $password)) {
            $errors[] = 'Invalid password.';
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

$siteName = h((string) ($config['site_name'] ?? 'My Website'));
$faviconHref = buildFaviconHref($config);
$currentUsername = h($_SESSION['pending_username'] ?? '');
$loggedInUser = h($_SESSION['username'] ?? '');
$logoMarkup = renderLogo($config);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $siteName; ?> · Secure Login</title>
    <meta name="description" content="Two-step premium login interface in PHP.">
    <link rel="icon" type="image/svg+xml" href="<?php echo h($faviconHref); ?>">
    <style>
        :root {
            --bg-1: #060816;
            --bg-2: #10071f;
            --card: rgba(18, 24, 44, 0.7);
            --card-border: rgba(255, 255, 255, 0.12);
            --text: #ecf1ff;
            --muted: #a8b4d6;
            --primary: #8b5cf6;
            --primary-2: #6d28d9;
            --focus: #22d3ee;
            --danger: #fb7185;
            --success: #34d399;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            font-family: Inter, Segoe UI, Roboto, Arial, sans-serif;
            background:
                radial-gradient(circle at 15% 20%, rgba(79, 70, 229, 0.45), transparent 42%),
                radial-gradient(circle at 85% 0%, rgba(236, 72, 153, 0.25), transparent 35%),
                linear-gradient(145deg, var(--bg-1), var(--bg-2));
            display: grid;
            place-items: center;
            padding: 20px;
        }
        .shell {
            width: 100%;
            max-width: 460px;
            border: 1px solid var(--card-border);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.02));
            backdrop-filter: blur(14px);
            border-radius: 24px;
            box-shadow: 0 22px 60px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        .header {
            padding: 22px 24px 8px;
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
        .logo-text, .logo-image {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, #a78bfa, #6366f1);
            box-shadow: inset 0 0 14px rgba(255, 255, 255, 0.24);
            color: #fff;
            font-weight: 700;
        }
        .logo-image {
            object-fit: cover;
            padding: 4px;
            background: rgba(255, 255, 255, 0.12);
        }
        .brand-title {
            font-size: 1rem;
            font-weight: 650;
            letter-spacing: .2px;
        }
        .step {
            font-size: 0.75rem;
            color: var(--muted);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: 999px;
            padding: 5px 10px;
        }
        .content {
            padding: 14px 24px 24px;
        }
        h1 { margin: 0 0 6px; font-size: 1.45rem; }
        .subtitle { margin: 0 0 18px; color: var(--muted); }
        .error {
            margin-bottom: 14px;
            border-radius: 12px;
            background: rgba(251, 113, 133, .16);
            border: 1px solid rgba(251, 113, 133, .45);
            color: #ffe1e7;
            padding: 10px 12px;
            font-size: .93rem;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #dbe4ff;
            font-size: .92rem;
        }
        .input {
            width: 100%;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 14px;
            padding: 12px 13px;
            color: var(--text);
            background: rgba(1, 6, 20, .5);
            margin-bottom: 14px;
            outline: none;
            transition: .2s ease;
        }
        .input:focus {
            border-color: var(--focus);
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.2);
        }
        .button {
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 12px 14px;
            font-weight: 700;
            cursor: pointer;
            transition: transform .14s ease, box-shadow .14s ease;
        }
        .button:hover { transform: translateY(-1px); }
        .button-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow: 0 8px 20px rgba(109, 40, 217, 0.45);
        }
        .button-secondary {
            margin-top: 10px;
            background: rgba(255,255,255,0.08);
            color: #e5e7eb;
            border: 1px solid rgba(255,255,255,0.14);
        }
        .success {
            border-radius: 14px;
            border: 1px solid rgba(52, 211, 153, .45);
            background: rgba(16, 185, 129, .16);
            padding: 12px;
            margin-bottom: 14px;
            color: #d1fae5;
        }
        .tiny {
            margin-top: 14px;
            color: var(--muted);
            font-size: .83rem;
        }
        code {
            background: rgba(255,255,255,.08);
            padding: 2px 6px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,.12);
        }
        .logout { color: #c4b5fd; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="shell">
    <div class="header">
        <div class="brand">
            <?php echo $logoMarkup; ?>
            <div class="brand-title"><?php echo $siteName; ?></div>
        </div>
        <div class="step">
            <?php echo $screen === 'password' ? 'Step 2 of 2' : ($screen === 'username' ? 'Step 1 of 2' : 'Complete'); ?>
        </div>
    </div>

    <div class="content">
        <?php if ($screen === 'username'): ?>
            <h1>Welcome back</h1>
            <p class="subtitle">Enter your username to continue.</p>
            <?php foreach ($errors as $error): ?>
                <div class="error"><?php echo h($error); ?></div>
            <?php endforeach; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="username">
                <label for="username">Username</label>
                <input class="input" id="username" type="text" name="username" placeholder="Enter username" required>
                <button class="button button-primary" type="submit">Continue</button>
            </form>

        <?php elseif ($screen === 'password'): ?>
            <h1>Confirm your password</h1>
            <p class="subtitle">Signing in as <strong><?php echo $currentUsername; ?></strong>.</p>
            <?php foreach ($errors as $error): ?>
                <div class="error"><?php echo h($error); ?></div>
            <?php endforeach; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="password">
                <label for="password">Password</label>
                <input class="input" id="password" type="password" name="password" placeholder="Enter password" required>
                <button class="button button-primary" type="submit">Login securely</button>
            </form>

            <form method="post">
                <input type="hidden" name="action" value="back">
                <button class="button button-secondary" type="submit">Use a different username</button>
            </form>

        <?php else: ?>
            <h1>Access granted</h1>
            <div class="success">You are logged in as <strong><?php echo $loggedInUser; ?></strong>.</div>
            <p class="subtitle">Session login is active and ready for protected pages.</p>
            <p><a class="logout" href="?logout=1">Logout</a></p>
            <p class="tiny">Demo users: <code>demo / demo123</code> and <code>admin / admin123</code></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
