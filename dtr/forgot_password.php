<?php
require_once 'config/db.php';
require_once 'config/mail.php';
require_once 'phpmailer/Exception.php';
require_once 'phpmailer/PHPMailer.php';
require_once 'phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Delete any existing unused tokens for this email
            $pdo->prepare("DELETE FROM password_resets WHERE email = ? AND used = 0")->execute([$email]);

            // Generate token
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)")->execute([$email, $token]);

            $resetLink = APP_URL . '/reset_password.php?token=' . $token;

            // Send email
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USERNAME;
                $mail->Password   = MAIL_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_PORT;

                $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Reset Your DTR System Password';
                $mail->Body    = '
                    <div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;background:#0f1523;color:#ffffff;border-radius:12px;overflow:hidden;">
                        <div style="background:#1e3a5f;padding:24px 32px;text-align:center;">
                            <h1 style="color:#c9a227;font-size:20px;margin:0;">DTR System</h1>
                            <p style="color:#ffffff;font-size:13px;margin:4px 0 0;">Daily Time Record</p>
                        </div>
                        <div style="padding:32px;">
                            <h2 style="font-size:16px;color:#ffffff;margin-top:0;">Password Reset Request</h2>
                            <p style="font-size:14px;color:#8da2cf;line-height:1.6;">
                                We received a request to reset your password. Click the button below to choose a new one.
                                This link expires in <strong style="color:#ffffff;">1 hour</strong>.
                            </p>
                            <div style="text-align:center;margin:28px 0;">
                                <a href="' . htmlspecialchars($resetLink) . '"
                                   style="background:#c9a227;color:#0f1523;font-weight:bold;font-size:14px;padding:12px 28px;border-radius:8px;text-decoration:none;display:inline-block;">
                                    Reset Password
                                </a>
                            </div>
                            <p style="font-size:12px;color:#4a6aa8;line-height:1.6;">
                                If you did not request this, you can safely ignore this email. Your password will not change.
                            </p>
                            <hr style="border:none;border-top:1px solid #2c3f65;margin:24px 0;">
                            <p style="font-size:11px;color:#4a6aa8;">
                                Or copy this link into your browser:<br>
                                <a href="' . htmlspecialchars($resetLink) . '" style="color:#c9a227;word-break:break-all;">' . htmlspecialchars($resetLink) . '</a>
                            </p>
                        </div>
                    </div>';
                $mail->AltBody = "Reset your DTR System password by visiting this link (expires in 1 hour):\n\n" . $resetLink;
                $mail->send();
            } catch (Exception $e) {
                // Log error silently — don't expose details to user
                error_log('PHPMailer error: ' . $mail->ErrorInfo);
            }
        }

        // Always show the same message (don't reveal if email exists)
        $success = 'If that email is registered, a reset link has been sent. Check your inbox.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — DTR System</title>
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

            <?php if ($error): ?>
                <div class="mb-6 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-300 text-sm"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <h2 class="text-lg font-medium text-white mb-2">Forgot your password?</h2>
                <p class="text-navy-400 text-sm mb-6">Enter your registered email and we'll send you a reset link.</p>
                <form method="POST">
                    <div>
                        <label class="block text-sm text-navy-300 mb-1.5">Email address</label>
                        <input type="email" name="email" required autofocus
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            class="w-full px-4 py-2.5 rounded-lg bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-400 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition"
                            placeholder="you@example.com">
                    </div>
                    <button type="submit"
                        class="w-full mt-6 py-2.5 rounded-lg bg-gold-500 hover:bg-gold-600 text-navy-900 font-medium text-sm transition-colors">
                        Send Reset Link
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
