<?php
require_once 'config/db.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = '';
$validToken = null;

// Validate token
if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$token]);
    $validToken = $stmt->fetch();
}

// Handle new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Update password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $validToken['email']]);

        // Mark token as used
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);

        header('Location: index.php?success=password_reset');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — DTR System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: { 50:'#f0f3f9',100:'#d9e0ef',200:'#b3c1df',300:'#8da2cf',400:'#6783bf',500:'#4a6aa8',600:'#3b5486',700:'#2c3f65',800:'#1e2a44',900:'#0f1523' },
                        gold: { 50:'#fdf9ef',100:'#faf0d0',200:'#f5e0a1',300:'#efd072',400:'#e8be43',500:'#d4a828',600:'#a9861f',700:'#7e6417',800:'#54430f',900:'#2a2108' },
                    }
                }
            }
        }
        function showPass(id) { document.getElementById(id).type = 'text'; }
        function hidePass(id) { document.getElementById(id).type = 'password'; }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="min-h-screen bg-navy-900 flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-gold-500/10 border border-gold-500/20 mb-4">
                <svg class="w-7 h-7 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-semibold text-white tracking-tight">Daily Time Record</h1>
            <p class="text-navy-300 text-sm mt-1">Internship Tracker</p>
        </div>

        <!-- Card -->
        <div class="bg-navy-800/50 backdrop-blur border border-navy-700/50 rounded-2xl p-8">

            <?php if (!$token || !$validToken): ?>
                <!-- Invalid / expired token -->
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-500/10 border border-red-500/20 mb-4">
                        <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-medium text-white mb-2">Link Invalid or Expired</h2>
                    <p class="text-navy-400 text-sm mb-6">This reset link has already been used or has expired (links are valid for 1 hour).</p>
                    <a href="forgot_password.php"
                       class="inline-block px-6 py-2.5 rounded-lg bg-gold-500 hover:bg-gold-600 text-navy-900 font-medium text-sm transition-colors">
                        Request a New Link
                    </a>
                </div>

            <?php else: ?>
                <!-- Valid token — show password form -->
                <?php if ($error): ?>
                    <div class="mb-6 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-300 text-sm"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <h2 class="text-lg font-medium text-white mb-2">Set a New Password</h2>
                <p class="text-navy-400 text-sm mb-6">Choose a strong password for your account.</p>

                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-navy-300 mb-1.5">New Password</label>
                            <div class="relative">
                                <input type="password" name="password" required minlength="6" autofocus id="newPass"
                                    class="w-full px-4 py-2.5 pr-10 rounded-lg bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-400 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition"
                                    placeholder="Min. 6 characters">
                                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-navy-400 hover:text-gold-400 transition select-none"
                                    onmousedown="showPass('newPass')" onmouseup="hidePass('newPass')" onmouseleave="hidePass('newPass')"
                                    ontouchstart="showPass('newPass')" ontouchend="hidePass('newPass')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm text-navy-300 mb-1.5">Confirm Password</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" required id="confirmPass"
                                    class="w-full px-4 py-2.5 pr-10 rounded-lg bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-400 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition"
                                    placeholder="Re-enter your password">
                                <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-navy-400 hover:text-gold-400 transition select-none"
                                    onmousedown="showPass('confirmPass')" onmouseup="hidePass('confirmPass')" onmouseleave="hidePass('confirmPass')"
                                    ontouchstart="showPass('confirmPass')" ontouchend="hidePass('confirmPass')">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="submit"
                        class="w-full mt-6 py-2.5 rounded-lg bg-gold-500 hover:bg-gold-600 text-navy-900 font-medium text-sm transition-colors">
                        Change Password
                    </button>
                </form>
            <?php endif; ?>

            <p class="text-center text-sm text-navy-400 mt-6">
                <a href="index.php" class="text-gold-400 hover:text-gold-300 transition">&larr; Back to sign in</a>
            </p>
        </div>

        <p class="text-center text-xs text-navy-500 mt-6">DTR System &copy; <?= date('Y') ?></p>
    </div>
</body>
</html>
