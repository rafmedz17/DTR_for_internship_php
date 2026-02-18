<?php
require_once 'config/db.php';
requireLogin();

$user = getUser($pdo, $_SESSION['user_id']);
$today = date('Y-m-d');
$now = date('H:i:s');

// Get today's record
$stmt = $pdo->prepare("SELECT * FROM time_records WHERE user_id = ? AND date = ?");
$stmt->execute([$_SESSION['user_id'], $today]);
$todayRecord = $stmt->fetch();

// Get this week's summary
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$stmt = $pdo->prepare("SELECT COUNT(*) as days, COALESCE(SUM(hours_worked), 0) as total_hours FROM time_records WHERE user_id = ? AND date BETWEEN ? AND ?");
$stmt->execute([$_SESSION['user_id'], $weekStart, $weekEnd]);
$weekSummary = $stmt->fetch();

// Get total internship hours
$stmt = $pdo->prepare("SELECT COALESCE(SUM(hours_worked), 0) as total FROM time_records WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalHours = $stmt->fetchColumn();

// Get recent records (last 5)
$stmt = $pdo->prepare("SELECT * FROM time_records WHERE user_id = ? ORDER BY date DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentRecords = $stmt->fetchAll();

$hasTimedIn   = $todayRecord && $todayRecord['time_in'];
$onLunch      = $hasTimedIn && !empty($todayRecord['am_time_out']) && empty($todayRecord['pm_time_in']) && empty($todayRecord['time_out']);
$pmInProgress = $hasTimedIn && !empty($todayRecord['pm_time_in']) && empty($todayRecord['time_out']);
$hasTimedOut  = $todayRecord && $todayRecord['time_out'];
$amInProgress = $hasTimedIn && !$onLunch && !$pmInProgress && !$hasTimedOut;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — DTR System</title>
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="min-h-screen bg-navy-900 text-white">

    <!-- Navigation -->
    <nav class="border-b border-navy-700/50 bg-navy-800/30 backdrop-blur sticky top-0 z-10">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-gold-500/10 border border-gold-500/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="font-semibold text-sm tracking-tight">DTR System by Raf Medina</span>
            </div>
            <div class="flex items-center gap-6">
                <a href="dashboard.php" class="text-sm text-gold-400 font-medium">Dashboard</a>
                <a href="records.php" class="text-sm text-navy-300 hover:text-white transition">Records</a>
                <div class="flex items-center gap-3 pl-6 border-l border-navy-700/50">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-medium"><?= htmlspecialchars($user['full_name']) ?></p>
                        <p class="text-xs text-navy-400"><?= htmlspecialchars($user['position']) ?></p>
                    </div>
                    <a href="logout.php" class="text-navy-400 hover:text-red-400 transition" title="Sign out">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

        <!-- Greeting -->
        <div class="mb-8">
            <h1 class="text-2xl font-semibold">Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?></h1>
            <p class="text-navy-400 text-sm mt-1"><?= date('l, F j, Y') ?></p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-navy-800/50 border border-navy-700/50 rounded-xl p-5">
                <p class="text-xs text-navy-400 uppercase tracking-wider mb-1">Today</p>
                <p class="text-2xl font-semibold">
                    <?php if ($hasTimedOut): ?>
                        <?= number_format($todayRecord['hours_worked'], 1) ?><span class="text-sm text-navy-400 ml-1">hrs</span>
                    <?php elseif ($hasTimedIn): ?>
                        <span class="text-gold-400">In Progress</span>
                    <?php else: ?>
                        <span class="text-navy-500">—</span>
                    <?php endif; ?>
                </p>
                <?php if ($hasTimedOut && $todayRecord['under_time'] > 0): ?>
                    <p class="text-xs text-gold-400 mt-1">Under time: <?= number_format($todayRecord['under_time'], 1) ?> hrs</p>
                <?php endif; ?>
            </div>
            <div class="bg-navy-800/50 border border-navy-700/50 rounded-xl p-5">
                <p class="text-xs text-navy-400 uppercase tracking-wider mb-1">This Week</p>
                <p class="text-2xl font-semibold"><?= number_format($weekSummary['total_hours'], 1) ?><span class="text-sm text-navy-400 ml-1">hrs</span></p>
                <p class="text-xs text-navy-500 mt-1"><?= $weekSummary['days'] ?> day(s) logged</p>
            </div>
            <div class="bg-navy-800/50 border border-navy-700/50 rounded-xl p-5">
                <p class="text-xs text-navy-400 uppercase tracking-wider mb-1">Total Hours</p>
                <p class="text-2xl font-semibold"><?= number_format($totalHours, 1) ?><span class="text-sm text-navy-400 ml-1">hrs</span></p>
            </div>
        </div>

        <!-- Time In/Out Card -->
        <div class="bg-navy-800/50 border border-navy-700/50 rounded-2xl p-6 sm:p-8 mb-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
                <div class="text-center sm:text-left">
                    <p class="text-5xl font-light tracking-tight" id="liveClock"><?= date('h:i:s A') ?></p>
                    <div class="flex flex-wrap items-center gap-3 mt-3 text-xs text-navy-400">
                        <?php if ($hasTimedIn): ?>
                            <span class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                AM In: <?= date('h:i A', strtotime($todayRecord['time_in'])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($todayRecord['am_time_out'])): ?>
                            <span class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-gold-400"></span>
                                AM Out: <?= date('h:i A', strtotime($todayRecord['am_time_out'])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($todayRecord['pm_time_in'])): ?>
                            <span class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                PM In: <?= date('h:i A', strtotime($todayRecord['pm_time_in'])) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($hasTimedOut): ?>
                            <span class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span>
                                PM Out: <?= date('h:i A', strtotime($todayRecord['time_out'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex gap-3">

                    <?php if (!$hasTimedIn): ?>
                        <!-- State 1: Not timed in -->
                        <form method="POST" action="process.php">
                            <input type="hidden" name="action" value="time_in">
                            <button type="submit" class="px-8 py-3 rounded-xl bg-gold-500 hover:bg-gold-600 text-navy-900 font-semibold text-sm transition-colors">
                                AM Time In
                            </button>
                        </form>

                    <?php elseif ($amInProgress): ?>
                        <!-- State 2: AM In Progress -->
                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <form method="POST" action="process.php" id="journalFormAM" class="hidden">
                                <input type="hidden" name="action" value="save_journal">
                                <input type="hidden" name="remarks" id="journalAM" value="<?= htmlspecialchars($todayRecord['remarks'] ?? '') ?>">
                            </form>
                            <button type="button" onclick="openJournal('journalAM')"
                                class="px-4 py-3 rounded-xl bg-navy-700/50 hover:bg-navy-700 border border-navy-600/50 text-navy-300 hover:text-white text-sm transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Daily Journal
                            </button>
                            <form method="POST" action="process.php">
                                <input type="hidden" name="action" value="lunch_break">
                                <button type="submit" class="px-8 py-3 rounded-xl bg-gold-500/80 hover:bg-gold-500 text-navy-900 font-semibold text-sm transition-colors">
                                    AM Time Out
                                </button>
                            </form>
                            <form method="POST" action="process.php" onsubmit="return confirm('Undo your AM Time In for today?')">
                                <input type="hidden" name="action" value="reset_time_in">
                                <button type="submit" class="px-4 py-3 rounded-xl bg-navy-700/50 hover:bg-navy-700 border border-navy-600/50 text-navy-300 hover:text-white text-sm transition-colors">
                                    Undo
                                </button>
                            </form>
                        </div>

                    <?php elseif ($onLunch): ?>
                        <!-- State 3: AM Time Out / On Lunch Break -->
                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <form method="POST" action="process.php" id="journalFormLunch" class="hidden">
                                <input type="hidden" name="action" value="save_journal">
                                <input type="hidden" name="remarks" id="journalLunch" value="<?= htmlspecialchars($todayRecord['remarks'] ?? '') ?>">
                            </form>
                            <button type="button" onclick="openJournal('journalLunch')"
                                class="px-4 py-3 rounded-xl bg-navy-700/50 hover:bg-navy-700 border border-navy-600/50 text-navy-300 hover:text-white text-sm transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Daily Journal
                            </button>
                            <div class="px-5 py-3 rounded-xl bg-gold-500/10 border border-gold-500/20 text-gold-400 text-sm font-medium">
                                On Lunch Break
                            </div>
                            <form method="POST" action="process.php">
                                <input type="hidden" name="action" value="pm_time_in">
                                <button type="submit" class="px-8 py-3 rounded-xl bg-gold-500 hover:bg-gold-600 text-navy-900 font-semibold text-sm transition-colors">
                                    PM Time In
                                </button>
                            </form>
                            <form method="POST" action="process.php" onsubmit="return confirm('End your day now? Only AM hours will be counted.')">
                                <input type="hidden" name="action" value="time_out_halfday">
                                <button type="submit" class="px-8 py-3 rounded-xl bg-red-500/80 hover:bg-red-500 text-white font-semibold text-sm transition-colors">
                                    Time Out
                                </button>
                            </form>
                            <form method="POST" action="process.php" onsubmit="return confirm('Undo Lunch Break? You\'ll return to AM In Progress.')">
                                <input type="hidden" name="action" value="undo_am_timeout">
                                <button type="submit" class="px-4 py-3 rounded-xl bg-navy-700/50 hover:bg-navy-700 border border-navy-600/50 text-navy-300 hover:text-white text-sm transition-colors">
                                    Undo
                                </button>
                            </form>
                        </div>

                    <?php elseif ($pmInProgress): ?>
                        <!-- State 4: PM In Progress -->
                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <form method="POST" action="process.php" id="journalFormPM" class="hidden">
                                <input type="hidden" name="action" value="save_journal">
                                <input type="hidden" name="remarks" id="journalPM" value="<?= htmlspecialchars($todayRecord['remarks'] ?? '') ?>">
                            </form>
                            <button type="button" onclick="openJournal('journalPM')"
                                class="px-4 py-3 rounded-xl bg-navy-700/50 hover:bg-navy-700 border border-navy-600/50 text-navy-300 hover:text-white text-sm transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Daily Journal
                            </button>
                            <form method="POST" action="process.php">
                                <input type="hidden" name="action" value="time_out">
                                <button type="submit" class="px-8 py-3 rounded-xl bg-red-500/80 hover:bg-red-500 text-white font-semibold text-sm transition-colors">
                                    Time Out
                                </button>
                            </form>
                            <form method="POST" action="process.php" onsubmit="return confirm('Undo PM Time In? You\'ll return to Lunch Break.')">
                                <input type="hidden" name="action" value="undo_pm_timein">
                                <button type="submit" class="px-4 py-3 rounded-xl bg-navy-700/50 hover:bg-navy-700 border border-navy-600/50 text-navy-300 hover:text-white text-sm transition-colors">
                                    Undo
                                </button>
                            </form>
                        </div>

                    <?php else: ?>
                        <!-- State 5: Completed -->
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="px-6 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm font-medium">
                                Completed for today
                            </div>
                            <?php if ($todayRecord['under_time'] > 0): ?>
                                <div class="px-4 py-3 rounded-xl bg-gold-500/10 border border-gold-500/20 text-gold-400 text-sm">
                                    Under time: <?= number_format($todayRecord['under_time'], 1) ?> hrs
                                </div>
                            <?php endif; ?>
                            <form method="POST" action="process.php" onsubmit="return confirm('Undo Time Out? Your Time Out will be cleared.')">
                                <input type="hidden" name="action" value="resume">
                                <button type="submit" class="px-4 py-3 rounded-xl bg-gold-500/10 hover:bg-gold-500/20 border border-gold-500/30 text-gold-400 hover:text-gold-300 text-sm font-medium transition-colors">
                                    Undo
                                </button>
                            </form>
                            <form method="POST" action="process.php" id="journalFormCompleted" class="hidden">
                                <input type="hidden" name="action" value="save_journal">
                                <input type="hidden" name="remarks" id="journalCompleted" value="<?= htmlspecialchars($todayRecord['remarks'] ?? '') ?>">
                            </form>
                            <button type="button" onclick="openJournal('journalCompleted')"
                                class="px-4 py-3 rounded-xl bg-navy-700/50 hover:bg-navy-700 border border-navy-600/50 text-navy-300 hover:text-white text-sm transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Daily Journal
                            </button>
                            <form method="POST" action="process.php" onsubmit="return confirm('Reset your entire time record for today? This cannot be undone.')">
                                <input type="hidden" name="action" value="reset_time_in">
                                <button type="submit" class="px-4 py-3 rounded-xl bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-400 hover:text-red-300 text-sm transition-colors">
                                    Reset
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-navy-800/50 border border-navy-700/50 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-navy-700/50 flex items-center justify-between">
                <h2 class="font-medium text-sm">Recent Activity</h2>
                <a href="records.php" class="text-xs text-gold-400 hover:text-gold-300 transition">View all</a>
            </div>
            <?php if (empty($recentRecords)): ?>
                <div class="px-6 py-12 text-center text-navy-500 text-sm">No records yet. Start by timing in.</div>
            <?php else: ?>
                <div class="divide-y divide-navy-700/30">
                    <?php foreach ($recentRecords as $record): ?>
                        <div class="px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-lg bg-navy-700/50 flex items-center justify-center text-xs font-medium text-navy-300">
                                    <?= date('d', strtotime($record['date'])) ?><br>
                                    <span class="text-[10px] text-navy-500"><?= date('M', strtotime($record['date'])) ?></span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium"><?= date('l', strtotime($record['date'])) ?></p>
                                    <p class="text-xs text-navy-400">
                                        <?= $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '—' ?>
                                        &mdash;
                                        <?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '—' ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium"><?= $record['hours_worked'] ? number_format($record['hours_worked'], 1) . ' hrs' : '—' ?></p>
                                <p class="text-xs capitalize <?= $record['status'] === 'present' ? 'text-emerald-400' : ($record['status'] === 'half-day' ? 'text-gold-400' : 'text-red-400') ?>">
                                    <?= $record['status'] ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Journal Modal -->
    <div id="journalModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeJournal()"></div>
        <div class="relative bg-navy-800 border border-navy-700/50 rounded-2xl w-full max-w-lg p-6 shadow-2xl">
            <h3 class="text-lg font-medium text-white mb-1">Daily Journal</h3>
            <p class="text-xs text-navy-400 mb-4">Write about what you did today during your internship.</p>
            <textarea id="journalTextarea" rows="8" placeholder="What did you work on today?"
                class="w-full px-4 py-3 rounded-xl bg-navy-900/50 border border-navy-600/50 text-white placeholder-navy-500 text-sm focus:outline-none focus:border-gold-500/50 focus:ring-1 focus:ring-gold-500/25 transition resize-none"></textarea>
            <div class="flex items-center justify-end gap-2 mt-4">
                <button onclick="closeJournal()" class="px-4 py-2 rounded-lg bg-navy-700/50 hover:bg-navy-700 text-navy-300 text-sm transition-colors">Cancel</button>
                <button onclick="saveJournal()" class="px-6 py-2 rounded-lg bg-gold-500 hover:bg-gold-600 text-navy-900 font-medium text-sm transition-colors">Save</button>
            </div>
        </div>
    </div>

    <script>
        // Clock
        function updateClock() {
            const now = new Date();
            let h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            document.getElementById('liveClock').textContent =
                String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0') + ' ' + ampm;
        }
        setInterval(updateClock, 1000);

        // Journal modal
        let activeJournalInputId = null;

        function openJournal(inputId) {
            activeJournalInputId = inputId;
            const existing = document.getElementById(inputId).value;
            document.getElementById('journalTextarea').value = existing;
            const modal = document.getElementById('journalModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('journalTextarea').focus();
        }

        function closeJournal() {
            const modal = document.getElementById('journalModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            activeJournalInputId = null;
        }

        function saveJournal() {
            if (activeJournalInputId) {
                const value = document.getElementById('journalTextarea').value;
                document.getElementById(activeJournalInputId).value = value;
                // Also update timeOutJournal if it exists (so Time Out carries the latest remarks)
                const timeOutInput = document.getElementById('timeOutJournal');
                if (timeOutInput) timeOutInput.value = value;
                // Submit the journal form to save to DB immediately
                const form = document.getElementById(activeJournalInputId).closest('form');
                if (form) form.submit();
                return;
            }
            closeJournal();
        }
    </script>
</body>
</html>
