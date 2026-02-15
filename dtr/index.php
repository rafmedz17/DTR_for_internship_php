<?php
require_once 'config/db.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login';

if (($_GET['success'] ?? '') === 'password_reset') {
    $success = 'Password changed successfully. You can now sign in.';
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
        $mode = 'login';
    }
}

// Handle Register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $department = trim($_POST['department']);

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
        $mode = 'register';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
        $mode = 'register';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered.';
            $mode = 'register';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, department) VALUES (?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, $hash, $department]);
            $success = 'Account created successfully. Please log in.';
            $mode = 'login';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR System — Sign In</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: { 50: '#f0f3f9', 100: '#d9e0ef', 200: '#b3c1df', 300: '#8da2cf', 400: '#6783bf', 500: '#4a6aa8', 600: '#3b5486', 700: '#2c3f65', 800: '#1e2a44', 900: '#0f1523' },
                        gold: { 50: '#fdf9ef', 100: '#faf0d0', 200: '#f5e0a1', 300: '#efd072', 400: '#e8be43', 500: '#d4a828', 600: '#a9861f', 700: '#7e6417', 800: '#54430f', 900: '#2a2108' },
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
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

            <?php if ($error): ?>
                <div class="mb-6 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-300 text-sm"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" class="<?= $mode === 'login' ? '' : 'hidden' ?>" id="loginForm">
                <h2 class="text-lg font-medium text-white mb-6">Sign in to your account</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-navy-300 mb-1.5">Email</label>
                        <input type="email" name="email" required
                            class="w-full px-4 py-2.5 rounded-lg bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-400 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition"
                            placeholder="you@example.com">
                    </div>
                    <div>
                        <label class="block text-sm text-navy-300 mb-1.5">Password</label>
                        <div class="relative">
                            <input type="password" name="password" required id="loginPassword"
                                class="w-full px-4 py-2.5 pr-10 rounded-lg bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-400 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition"
                                placeholder="Enter your password">
                            <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-navy-400 hover:text-gold-400 transition select-none"
                                onmousedown="showPass('loginPassword')" onmouseup="hidePass('loginPassword')" onmouseleave="hidePass('loginPassword')"
                                ontouchstart="showPass('loginPassword')" ontouchend="hidePass('loginPassword')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="submit" name="login"
                    class="w-full mt-6 py-2.5 rounded-lg bg-gold-500 hover:bg-gold-600 text-navy-900 font-medium text-sm transition-colors">
                    Sign In
                </button>
                <div class="text-right mt-2">
                    <a href="forgot_password.php" class="text-xs text-navy-400 hover:text-gold-400 transition">Forgot password?</a>
                </div>
                <p class="text-center text-sm text-navy-400 mt-4">
                    Don't have an account?
                    <a href="#" onclick="toggleForm('register')" class="text-gold-400 hover:text-gold-300 transition">Create one</a>
                </p>
            </form>

            <!-- Register Form -->
            <form method="POST" class="<?= $mode === 'register' ? '' : 'hidden' ?>" id="registerForm">
                <h2 class="text-lg font-medium text-white mb-6">Create your account</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-navy-300 mb-1.5">Full Name</label>
                        <input type="text" name="full_name" required
                            class="w-full px-4 py-2.5 rounded-lg bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-400 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition"
                            placeholder="Juan Dela Cruz">
                    </div>
                    <div>
                        <label class="block text-sm text-navy-300 mb-1.5">Email</label>
                        <input type="email" name="email" required
                            class="w-full px-4 py-2.5 rounded-lg bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-400 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition"
                            placeholder="you@example.com">
                    </div>
                    <div>
                        <label class="block text-sm text-navy-300 mb-1.5">Department</label>
                        <input type="text" name="department"
                            class="w-full px-4 py-2.5 rounded-lg bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-400 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition"
                            placeholder="e.g. IT Department">
                    </div>
                    <div>
                        <label class="block text-sm text-navy-300 mb-1.5">Password</label>
                        <div class="relative">
                            <input type="password" name="password" required minlength="6" id="regPassword"
                                class="w-full px-4 py-2.5 pr-10 rounded-lg bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-400 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition"
                                placeholder="Min. 6 characters">
                            <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-navy-400 hover:text-gold-400 transition select-none"
                                onmousedown="showPass('regPassword')" onmouseup="hidePass('regPassword')" onmouseleave="hidePass('regPassword')"
                                ontouchstart="showPass('regPassword')" ontouchend="hidePass('regPassword')">
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
                            <input type="password" name="confirm_password" required id="regConfirm"
                                class="w-full px-4 py-2.5 pr-10 rounded-lg bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-400 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition"
                                placeholder="Re-enter your password">
                            <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-navy-400 hover:text-gold-400 transition select-none"
                                onmousedown="showPass('regConfirm')" onmouseup="hidePass('regConfirm')" onmouseleave="hidePass('regConfirm')"
                                ontouchstart="showPass('regConfirm')" ontouchend="hidePass('regConfirm')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="submit" name="register"
                    class="w-full mt-6 py-2.5 rounded-lg bg-gold-500 hover:bg-gold-600 text-navy-900 font-medium text-sm transition-colors">
                    Create Account
                </button>
                <p class="text-center text-sm text-navy-400 mt-6">
                    Already have an account?
                    <a href="#" onclick="toggleForm('login')" class="text-gold-400 hover:text-gold-300 transition">Sign in</a>
                </p>
            </form>
        </div>

        <p class="text-center text-xs text-navy-500 mt-6">DTR System &copy; <?= date('Y') ?></p>
    </div>

    <script>
        function toggleForm(mode) {
            document.getElementById('loginForm').classList.toggle('hidden', mode !== 'login');
            document.getElementById('registerForm').classList.toggle('hidden', mode !== 'register');
        }
        function showPass(id) { document.getElementById(id).type = 'text'; }
        function hidePass(id) { document.getElementById(id).type = 'password'; }
    </script>
</body>
</html>
