<?php
// admin/mailing/index.php
session_start();

// UPDATE INCLUDE PATHS FOR ASIKO SYSTEM
require_once '../kiosk/config.php'; 
require_once '../kiosk/functions.php';

// Security Check - Only Super Admin
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    die("Access Denied. Only Super Admin can view the mailing dashboard.");
}

// 1. Fetch Stats 
$stats = [
    'members' => 0,
    'subscribers' => 0,
    'total_campaigns' => 0,
    'total_emails_sent' => 0
];

try {
    // Changed from site_members to the Asiko users table
    $stats['members'] = $pdo->query("SELECT COUNT(*) FROM users WHERE email IS NOT NULL AND email != ''")->fetchColumn();
} catch (PDOException $e) {}

try {
    $stats['subscribers'] = $pdo->query("SELECT COUNT(*) FROM mail_subscribers WHERE status = 'active'")->fetchColumn();
} catch (PDOException $e) {}

try {
    $stats['total_campaigns'] = $pdo->query("SELECT COUNT(*) FROM mail_campaigns")->fetchColumn();
    $stats['total_emails_sent'] = $pdo->query("SELECT SUM(recipients_count) FROM mail_campaigns")->fetchColumn() ?: 0;
} catch (PDOException $e) {}

$total_audience = $stats['members'] + $stats['subscribers'];

// 2. Fetch Recent Campaigns
$recent_campaigns = [];
try {
    $recent_campaigns = $pdo->query("SELECT * FROM mail_campaigns ORDER BY sent_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mailing Overview - Asiko Mall</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body class="bg-gray-100 font-sans">

<header class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
    <h1 class="font-bold text-xl text-gray-800">Asiko Mailing Center</h1>
    <a href="../kiosk/Red/admin_dashboard.php" class="text-blue-600 hover:underline text-sm font-bold">← Back to Admin</a>
</header>

<div class="container mx-auto mt-8 px-4 pb-12 max-w-6xl">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Mailing Command Center</h2>
            <p class="text-gray-500 mt-1">Broadcast newsletters, manage audiences, and track campaigns.</p>
        </div>
        <div class="flex gap-3">
            <a href="../kiosk/Red/admin_dashboard.php" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-bold py-2.5 px-5 rounded shadow-sm transition flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">arrow_back</span> Main Dashboard
            </a>
            <a href="compose.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-5 rounded shadow-lg transition flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">edit_square</span> Compose Campaign
            </a>
        </div>
    </div>

    <div class="flex border-b border-gray-200 mb-8 overflow-x-auto no-scrollbar bg-white rounded-t-xl px-4 pt-2 shadow-sm">
        <a href="index.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap border-b-2 border-green-600 text-green-600 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">dashboard</span> Overview
        </a>
        <a href="compose.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">campaign</span> Campaigns
        </a>
        <a href="subscribers.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">group</span> Audience
        </a>
        <a href="settings.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">settings</span> Settings
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">public</span>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Total Audience</p>
                <p class="text-2xl font-black text-gray-800"><?php echo number_format($total_audience); ?></p>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">badge</span>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Registered Users</p>
                <p class="text-2xl font-black text-gray-800"><?php echo number_format($stats['members']); ?></p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">mark_email_read</span>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Total Campaigns</p>
                <p class="text-2xl font-black text-gray-800"><?php echo number_format($stats['total_campaigns']); ?></p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
            <div class="w-12 h-12 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined">stacked_email</span>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Emails Sent</p>
                <p class="text-2xl font-black text-gray-800"><?php echo number_format($stats['total_emails_sent']); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">Recent Broadcasts</h3>
            <?php if(count($recent_campaigns) > 0): ?>
                <a href="compose.php" class="text-sm font-bold text-green-600 hover:underline">View All</a>
            <?php endif; ?>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white text-xs uppercase tracking-wider text-gray-500 border-b border-gray-200">
                        <th class="p-4 font-bold">Campaign Subject</th>
                        <th class="p-4 font-bold">Theme</th>
                        <th class="p-4 font-bold">Audience Target</th>
                        <th class="p-4 font-bold text-right">Recipients</th>
                        <th class="p-4 font-bold text-right">Sent Date</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-700 divide-y divide-gray-100">
                    <?php if(count($recent_campaigns) > 0): ?>
                        <?php foreach($recent_campaigns as $camp): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-4 font-bold text-gray-900"><?php echo htmlspecialchars($camp['subject']); ?></td>
                                <td class="p-4">
                                    <span class="inline-block px-2 py-1 text-[10px] font-bold uppercase tracking-wider rounded border 
                                        <?php 
                                            echo $camp['theme'] == 'security' ? 'bg-gray-100 text-gray-700 border-gray-300' : 
                                                ($camp['theme'] == 'community' ? 'bg-green-100 text-green-800 border-green-300' : 
                                                'bg-blue-100 text-blue-800 border-blue-300');
                                        ?>">
                                        <?php echo htmlspecialchars($camp['theme']); ?>
                                    </span>
                                </td>
                                <td class="p-4 uppercase tracking-wider text-[11px] font-mono font-bold text-gray-500">
                                    <?php echo htmlspecialchars($camp['audience']); ?>
                                </td>
                                <td class="p-4 text-right font-bold text-gray-600">
                                    <?php echo number_format($camp['recipients_count']); ?>
                                </td>
                                <td class="p-4 text-right text-xs text-gray-500 font-mono">
                                    <?php echo date('M d, Y g:i A', strtotime($camp['sent_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="p-12 text-center text-gray-500">
                                <span class="material-symbols-outlined text-4xl mb-3 text-gray-300">forward_to_inbox</span>
                                <p class="text-base font-serif">No campaigns sent yet.</p>
                                <a href="compose.php" class="text-green-600 font-bold text-sm hover:underline mt-2 inline-block">Draft your first campaign</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>