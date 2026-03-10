<?php
// admin/mailing/subscribers.php
session_start();

// 1. ISOLATED MAILING SYSTEM INCLUDES
require_once 'db.php'; 

// Security Check - Only Super Admin can manage audience
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    die("Access Denied. Only Super Admin can manage mailing audience.");
}

$msg = '';
$error = '';

// Handle Adding a Manual Subscriber
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subscriber'])) {
    $email = trim($_POST['email']);
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO mail_subscribers (email, status, subscribed_at) VALUES (?, 'active', NOW())");
            $stmt->execute([$email]);
            $msg = "Subscriber added successfully!";
        } catch (PDOException $e) {
            // Error 23000 is usually a duplicate entry (Unique constraint)
            if ($e->getCode() == 23000) {
                $error = "This email is already on your subscriber list.";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    } else {
        $error = "Please enter a valid email address.";
    }
}

// Handle Removing a Subscriber
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_subscriber'])) {
    $sub_id = (int)$_POST['subscriber_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM mail_subscribers WHERE id = ?");
        $stmt->execute([$sub_id]);
        $msg = "Subscriber removed.";
    } catch (PDOException $e) {
        $error = "Could not remove subscriber.";
    }
}

// Fetch Newsletter Subscribers (From internal Mailing DB)
$subscribers = [];
try {
    $subscribers = $pdo->query("SELECT * FROM mail_subscribers ORDER BY subscribed_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch Registered Site Members (Using the Asiko read-only connection)
$members = [];
try {
    $members = $pdo_asiko->query("SELECT id, name as username, email, created_at FROM users WHERE email IS NOT NULL AND email != '' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audience - Mailing System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body class="bg-gray-100 font-sans">

<header class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
    <h1 class="font-bold text-xl text-gray-800">Mailing Microservice</h1>
    <a href="../kiosk/Red/admin_dashboard.php" class="text-blue-600 hover:underline text-sm font-bold">← Back to Ecosystem</a>
</header>

<div class="container mx-auto mt-8 px-4 pb-12 max-w-6xl">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Audience Management</h2>
            <p class="text-gray-500 mt-1">Manage your newsletter subscribers and view registered members across the ecosystem.</p>
        </div>
    </div>

    <?php if($msg): ?>
        <div class="bg-green-100 text-green-800 p-4 rounded mb-6 font-bold border border-green-200 shadow-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">check_circle</span> <?php echo $msg; ?>
        </div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="bg-red-100 text-red-800 p-4 rounded mb-6 font-bold border border-red-200 shadow-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">error</span> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="flex border-b border-gray-200 mb-8 overflow-x-auto no-scrollbar bg-white rounded-t-xl px-4 pt-2 shadow-sm">
        <a href="index.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">dashboard</span> Overview
        </a>
        <a href="compose.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">campaign</span> Compose
        </a>
        <a href="subscribers.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap border-b-2 border-green-600 text-green-600 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">group</span> Audience
        </a>
        <a href="settings.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">settings</span> Settings
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-1 space-y-8">
            
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-1">Add Subscriber</h3>
                <p class="text-xs text-gray-500 mb-4">Manually add an email to your newsletter list.</p>
                
                <form method="POST" class="flex flex-col gap-3">
                    <input type="email" name="email" required placeholder="Email Address..." class="w-full border border-gray-300 p-3 rounded bg-gray-50 focus:bg-white focus:ring-2 focus:ring-green-500 outline-none">
                    <button type="submit" name="add_subscriber" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded transition flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm">person_add</span> Add to List
                    </button>
                </form>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Registered Members</h3>
                    <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-1 rounded-full"><?php echo count($members); ?></span>
                </div>
                <p class="text-xs text-gray-500 mb-4 leading-relaxed">
                    These are users who have created an account on the platform. They automatically receive editorial broadcasts and alerts.
                </p>
                
                <div class="max-h-64 overflow-y-auto pr-2 space-y-3 custom-scrollbar">
                    <?php if(count($members) > 0): ?>
                        <?php foreach($members as $m): ?>
                            <div class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded border border-transparent hover:border-gray-100 transition">
                                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs shrink-0">
                                    <?php echo strtoupper(substr($m['username'] ?? 'U', 0, 1)); ?>
                                </div>
                                <div class="overflow-hidden">
                                    <p class="text-sm font-bold text-gray-800 truncate"><?php echo htmlspecialchars($m['username'] ?? 'Unknown'); ?></p>
                                    <p class="text-[10px] text-gray-500 truncate font-mono"><?php echo htmlspecialchars($m['email']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 italic">No registered members yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Newsletter Subscribers</h3>
                        <p class="text-xs text-gray-500 mt-1">External users who joined via marketing forms.</p>
                    </div>
                    <span class="bg-green-100 text-green-800 text-xs font-bold px-3 py-1.5 rounded-full"><?php echo count($subscribers); ?> Total</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white text-xs uppercase tracking-wider text-gray-500 border-b border-gray-200">
                                <th class="p-4 font-bold">Email Address</th>
                                <th class="p-4 font-bold">Status</th>
                                <th class="p-4 font-bold">Subscribed Date</th>
                                <th class="p-4 font-bold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-gray-700 divide-y divide-gray-100">
                            <?php if(count($subscribers) > 0): ?>
                                <?php foreach($subscribers as $sub): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="p-4 font-medium text-gray-900">
                                            <?php echo htmlspecialchars($sub['email']); ?>
                                        </td>
                                        <td class="p-4">
                                            <?php if($sub['status'] == 'active'): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold uppercase tracking-wider rounded border bg-green-50 text-green-700 border-green-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold uppercase tracking-wider rounded border bg-red-50 text-red-700 border-red-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Unsubscribed
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-xs text-gray-500 font-mono">
                                            <?php echo date('M d, Y', strtotime($sub['subscribed_at'])); ?>
                                        </td>
                                        <td class="p-4 text-right">
                                            <form method="POST" onsubmit="return confirm('Remove this subscriber from the list?');" class="inline-block">
                                                <input type="hidden" name="subscriber_id" value="<?php echo $sub['id']; ?>">
                                                <button type="submit" name="remove_subscriber" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50 transition" title="Remove Subscriber">
                                                    <span class="material-symbols-outlined text-lg">delete</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="p-12 text-center text-gray-500">
                                        <span class="material-symbols-outlined text-4xl mb-3 text-gray-300">group_off</span>
                                        <p class="text-base font-serif">Your newsletter list is currently empty.</p>
                                        <p class="text-xs mt-1">Add someone manually on the left, or wait for site visitors to subscribe.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
    /* Custom Scrollbar for the members list */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

</body>
</html>