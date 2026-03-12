<?php
// mailing/settings.php
session_start();

// 1. ISOLATED MAILING SYSTEM INCLUDES
require_once 'db.php'; 
require_once 'event_mappings.php'; // NEW: Includes the router logic

// Security Check - Use HQ Master Vault Session
if (!isset($_SESSION['master_admin_id'])) {
    header("Location: ../hq/login.php?app=mailing/settings.php");
    exit;
}

$msg = '';
$tab = $_GET['tab'] ?? 'triggers'; 

// --- NEW: HANDLE ROUTING RULES SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_mapping') {
    $app = trim($_POST['app_name']);
    $event = trim($_POST['event_name']);
    $template = trim($_POST['template_file']);

    if (!empty($app) && !empty($event) && !empty($template)) {
        if (add_mapping($pdo, $app, $event, $template)) {
            $msg = "Routing rule saved successfully!";
        } else {
            $msg = "Failed to save the routing rule.";
        }
    } else {
        $msg = "All fields are required to create a rule.";
    }
}

if (isset($_GET['delete_mapping_id'])) {
    $id = (int)$_GET['delete_mapping_id'];
    if (delete_mapping($pdo, $id)) {
        $msg = "Routing rule deleted.";
    }
}

// --- HANDLE SMTP FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_mail_settings'])) {
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM mail_settings WHERE setting_key = ?");
    $updateStmt = $pdo->prepare("UPDATE mail_settings SET setting_value = ? WHERE setting_key = ?");
    $insertStmt = $pdo->prepare("INSERT INTO mail_settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($_POST as $key => $value) {
        if ($key != 'submit_mail_settings' && $key != 'tab') {
            $checkStmt->execute([$key]);
            if ($checkStmt->fetchColumn() > 0) {
                $updateStmt->execute([trim($value), $key]);
            } else {
                $insertStmt->execute([$key, trim($value)]);
            }
        }
    }
    $msg = "SMTP settings saved successfully!";
}

// --- HANDLE EVENT TRIGGERS SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_triggers'])) {
    $trigger_keys = [
        'notify_customer_signup', 'notify_customer_login', 'notify_customer_pwd_reset', 
        'notify_customer_order_receipt', 'notify_customer_order_status', 
        'notify_vendor_new_order', 'notify_vendor_low_stock', 
        'notify_hq_new_vendor', 'notify_hq_admin_login'
    ];
    
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM mail_settings WHERE setting_key = ?");
    $updateStmt = $pdo->prepare("UPDATE mail_settings SET setting_value = ? WHERE setting_key = ?");
    $insertStmt = $pdo->prepare("INSERT INTO mail_settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($trigger_keys as $key) {
        $val = isset($_POST[$key]) ? '1' : '0'; // Checkbox is sent only if checked
        
        $checkStmt->execute([$key]);
        if ($checkStmt->fetchColumn() > 0) {
            $updateStmt->execute([$val, $key]);
        } else {
            $insertStmt->execute([$key, $val]);
        }
    }
    $msg = "Event Notification Triggers saved successfully!";
}

// --- HANDLE TEMPLATE SAVING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_template'])) {
    $tid = $_POST['template_id'] ?? 'new';
    $tname = trim($_POST['template_name']);
    if(empty($tname)) $tname = 'Untitled Template';

    $upload_fields = ['email_bg_image', 'email_header_img', 'email_footer_img'];
    
    // Save assets directly inside the isolated mailing folder
    $target_dir = __DIR__ . "/uploads/mail_assets/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $files_data = [];
    foreach ($upload_fields as $field) {
        if (!empty($_FILES[$field]['name'])) {
            $filename = "mail_" . str_replace('email_', '', $field) . "_" . time() . "_" . basename($_FILES[$field]['name']);
            $target = $target_dir . $filename;
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
                $files_data[$field] = "mailing/uploads/mail_assets/" . $filename;
            }
        }
    }

    if ($tid === 'new') {
        $stmt = $pdo->prepare("INSERT INTO mail_templates (
            template_name, email_bg_color, email_card_bg, email_body_text, email_link_color, 
            email_header_text, email_header_font, email_header_raw, email_footer_bg, email_footer_text, email_footer_raw
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tname, $_POST['email_bg_color'], $_POST['email_card_bg'], $_POST['email_body_text'], 
            $_POST['email_link_color'], $_POST['email_header_text'], $_POST['email_header_font'], $_POST['email_header_raw'], 
            $_POST['email_footer_bg'], $_POST['email_footer_text'], $_POST['email_footer_raw']
        ]);
        $new_id = $pdo->lastInsertId();

        foreach($files_data as $col => $val) {
            $pdo->prepare("UPDATE mail_templates SET $col = ? WHERE id = ?")->execute([$val, $new_id]);
        }
        $msg = "New template created successfully!";
        header("Location: settings.php?tab=design&tid=$new_id&msg=" . urlencode($msg));
        exit;
    } else {
        $stmt = $pdo->prepare("UPDATE mail_templates SET 
            template_name = ?, email_bg_color = ?, email_card_bg = ?, email_body_text = ?, 
            email_link_color = ?, email_header_text = ?, email_header_font = ?, email_header_raw = ?, 
            email_footer_bg = ?, email_footer_text = ?, email_footer_raw = ? 
            WHERE id = ?");
        $stmt->execute([
            $tname, $_POST['email_bg_color'], $_POST['email_card_bg'], $_POST['email_body_text'], 
            $_POST['email_link_color'], $_POST['email_header_text'], $_POST['email_header_font'], $_POST['email_header_raw'], 
            $_POST['email_footer_bg'], $_POST['email_footer_text'], $_POST['email_footer_raw'], $tid
        ]);

        foreach($files_data as $col => $val) {
            $pdo->prepare("UPDATE mail_templates SET $col = ? WHERE id = ?")->execute([$val, $tid]);
        }
        $msg = "Template updated successfully!";
    }
}

// --- HANDLE TEMPLATE DELETION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
    $tid = $_POST['delete_id'];
    $pdo->prepare("DELETE FROM mail_templates WHERE id = ?")->execute([$tid]);
    $msg = "Template deleted.";
    header("Location: settings.php?tab=design&msg=" . urlencode($msg));
    exit;
}

// --- FETCH DATA FOR UI ---
try {
    $ms = $pdo->query("SELECT setting_key, setting_value FROM mail_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) { $ms = []; }

$all_templates = [];
try {
    $all_templates = $pdo->query("SELECT id, template_name FROM mail_templates ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$active_tid = $_GET['tid'] ?? (!empty($all_templates) ? $all_templates[0]['id'] : 'new');

$t_data = [
    'template_name' => 'New Custom Template',
    'email_card_bg' => '#ffffff',
    'email_body_text' => '#1a1a1a',
    'email_link_color' => '#2563eb', 
    'email_footer_bg' => '#f3f4f6',
    'email_footer_text' => '#6b7280',
    'email_bg_color' => '#f8faf9',
    'email_header_text' => 'Asiko Mall',
    'email_header_font' => 'Arial, sans-serif',
    'email_header_raw' => '',
    'email_footer_raw' => '',
    'email_bg_image' => '',
    'email_header_img' => '',
    'email_footer_img' => ''
];

if ($active_tid !== 'new') {
    $stmt = $pdo->prepare("SELECT * FROM mail_templates WHERE id = ?");
    $stmt->execute([$active_tid]);
    $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched) {
        $t_data = array_merge($t_data, $fetched);
    }
}

// Fetch router mappings
$mappings = get_all_mappings($pdo);
$html_files = get_available_templates();

if(isset($_GET['msg'])) $msg = $_GET['msg'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mailing Settings - Asiko System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    
    <style>
        .toggle-bg { position: relative; transition: background-color 0.3s ease; }
        .toggle-bg:after {
            content: '';
            position: absolute;
            top: 2px; left: 2px;
            background-color: white;
            border: 1px solid #d1d5db;
            border-radius: 50%;
            height: 20px; width: 20px;
            transition: transform 0.3s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        input:checked + .toggle-bg { background-color: #22c55e !important; }
        input:checked + .toggle-bg:after {
            transform: translateX(20px);
            border-color: transparent;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">

<header class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
    <h1 class="font-bold text-xl text-gray-800">Mailing Microservice</h1>
    <a href="../hq/index.php" class="text-blue-600 hover:underline text-sm font-bold">← Back to Directory</a>
</header>

<div class="container mx-auto mt-8 px-4 pb-12 max-w-7xl">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Mailing System Settings</h2>
            <p class="text-sm text-gray-500 mt-1">Configure SMTP, manage templates, and set notification triggers.</p>
        </div>
    </div>

    <?php if($msg): ?>
        <div class="bg-green-100 text-green-800 p-4 rounded mb-6 font-bold border border-green-200 shadow-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">check_circle</span> <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="flex border-b border-gray-200 mb-6 overflow-x-auto no-scrollbar bg-white rounded-t-xl px-4 pt-2 shadow-sm">
        <a href="index.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">dashboard</span> Overview
        </a>
        <a href="compose.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">campaign</span> Campaigns
        </a>
        <a href="subscribers.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">group</span> Audience
        </a>
        <a href="?tab=triggers" class="px-6 py-4 font-bold text-sm whitespace-nowrap <?php echo $tab=='triggers' ? 'border-b-2 border-green-600 text-green-600' : 'text-gray-500 hover:text-gray-800'; ?>">Trigger Events</a>
        <a href="?tab=design" class="px-6 py-4 font-bold text-sm whitespace-nowrap <?php echo $tab=='design' ? 'border-b-2 border-green-600 text-green-600' : 'text-gray-500 hover:text-gray-800'; ?>">Template Builder</a>
        <a href="?tab=router" class="px-6 py-4 font-bold text-sm whitespace-nowrap <?php echo $tab=='router' ? 'border-b-2 border-green-600 text-green-600' : 'text-gray-500 hover:text-gray-800'; ?>">Template Router</a>
        <a href="?tab=smtp" class="px-6 py-4 font-bold text-sm whitespace-nowrap <?php echo $tab=='smtp' ? 'border-b-2 border-green-600 text-green-600' : 'text-gray-500 hover:text-gray-800'; ?>">SMTP Configuration</a>
    </div>

    <?php if ($tab == 'triggers'): ?>
        <form method="POST">
            <input type="hidden" name="submit_triggers" value="1">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden h-fit">
                    <div class="bg-blue-50 border-b border-gray-100 p-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">groups</span>
                        <h3 class="font-bold text-gray-800 text-lg">Customer Alerts (B2C)</h3>
                    </div>
                    
                    <div class="divide-y divide-gray-100">
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition">
                            <div><p class="font-bold text-gray-800 text-sm">Welcome / Sign-Up</p><p class="text-[11px] text-gray-500">Sent when a new account is created.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_customer_signup" value="1" class="sr-only" <?php echo ($ms['notify_customer_signup'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full toggle-bg"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition">
                            <div><p class="font-bold text-gray-800 text-sm">New Login Alert</p><p class="text-[11px] text-gray-500">Security alert sent upon successful login.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_customer_login" value="1" class="sr-only" <?php echo ($ms['notify_customer_login'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full toggle-bg"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition">
                            <div><p class="font-bold text-gray-800 text-sm">Password Reset</p><p class="text-[11px] text-gray-500">Sent during password recovery.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_customer_pwd_reset" value="1" class="sr-only" <?php echo ($ms['notify_customer_pwd_reset'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full toggle-bg"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition">
                            <div><p class="font-bold text-gray-800 text-sm">Order Receipt</p><p class="text-[11px] text-gray-500">Invoice sent after successful checkout.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_customer_order_receipt" value="1" class="sr-only" <?php echo ($ms['notify_customer_order_receipt'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full toggle-bg"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition">
                            <div><p class="font-bold text-gray-800 text-sm">Order Status Update</p><p class="text-[11px] text-gray-500">Sent when order is marked shipped/delivered.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_customer_order_status" value="1" class="sr-only" <?php echo ($ms['notify_customer_order_status'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full toggle-bg"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden h-fit">
                    <div class="bg-orange-50 border-b border-gray-100 p-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-orange-600">storefront</span>
                        <h3 class="font-bold text-gray-800 text-lg">Vendor Alerts (B2B)</h3>
                    </div>
                    
                    <div class="divide-y divide-gray-100">
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition">
                            <div><p class="font-bold text-gray-800 text-sm">New Order Received</p><p class="text-[11px] text-gray-500">Alerts the specific vendor to prepare order.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_vendor_new_order" value="1" class="sr-only" <?php echo ($ms['notify_vendor_new_order'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full toggle-bg"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition">
                            <div><p class="font-bold text-gray-800 text-sm">Low Stock Alert</p><p class="text-[11px] text-gray-500">Warns vendor when inventory is low.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_vendor_low_stock" value="1" class="sr-only" <?php echo ($ms['notify_vendor_low_stock'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full toggle-bg"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden h-fit">
                    <div class="bg-purple-50 border-b border-gray-100 p-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-purple-600">shield_person</span>
                        <h3 class="font-bold text-gray-800 text-lg">System Alerts (HQ)</h3>
                    </div>
                    
                    <div class="divide-y divide-gray-100">
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition">
                            <div><p class="font-bold text-gray-800 text-sm">New Vendor Registration</p><p class="text-[11px] text-gray-500">Notifies HQ when a new store joins.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_hq_new_vendor" value="1" class="sr-only" <?php echo ($ms['notify_hq_new_vendor'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full toggle-bg"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition">
                            <div><p class="font-bold text-gray-800 text-sm">Admin Dashboard Login</p><p class="text-[11px] text-gray-500">High-level security login alert.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="notify_hq_admin_login" value="1" class="sr-only" <?php echo ($ms['notify_hq_admin_login'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 rounded-full toggle-bg"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="col-span-1 md:col-span-2 lg:col-span-3 flex justify-end">
                    <button type="submit" class="bg-gray-900 hover:bg-black text-white font-bold py-3 px-8 rounded-lg shadow-lg transition">
                        Save Trigger Preferences
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($tab == 'smtp'): ?>
        <div class="bg-blue-50 text-blue-800 p-4 rounded mb-6 border border-blue-200 shadow-sm text-sm">
            <strong>Note:</strong> We recommend configuring your core SMTP settings inside the main ecosystem <code>.env</code> file for maximum security. These settings here will act as an override for your marketing blasts if provided.
        </div>

        <form method="POST">
            <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 max-w-4xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block font-bold text-gray-700 mb-2">SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($ms['smtp_host'] ?? 'mail.asiko.store'); ?>" class="w-full border border-gray-300 p-3 rounded outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-2">SMTP Port</label>
                        <input type="text" name="smtp_port" value="<?php echo htmlspecialchars($ms['smtp_port'] ?? '465'); ?>" class="w-full border border-gray-300 p-3 rounded outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-2">Encryption</label>
                        <select name="smtp_crypto" class="w-full border border-gray-300 p-3 rounded bg-white outline-none">
                            <option value="ssl" <?php echo ($ms['smtp_crypto'] ?? 'ssl') == 'ssl' ? 'selected' : ''; ?>>SSL (Port 465)</option>
                            <option value="tls" <?php echo ($ms['smtp_crypto'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS (Port 587)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-2">Username</label>
                        <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($ms['smtp_user'] ?? 'sales@asiko.store'); ?>" class="w-full border border-gray-300 p-3 rounded outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block font-bold text-gray-700 mb-2">Password</label>
                        <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($ms['smtp_pass'] ?? ''); ?>" class="w-full border border-gray-300 p-3 rounded outline-none focus:ring-2 focus:ring-green-500" placeholder="••••••••">
                    </div>
                    <div class="md:col-span-2 pt-4">
                        <label class="block font-bold text-gray-700 mb-2">"From" Name</label>
                        <input type="text" name="from_name" value="<?php echo htmlspecialchars($ms['from_name'] ?? 'Asiko System'); ?>" class="w-full border border-gray-300 p-3 rounded outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div class="md:col-span-2 pt-4">
                        <button type="submit" name="submit_mail_settings" class="bg-green-600 text-white font-bold py-3 px-6 rounded w-full md:w-auto">Save Connection</button>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($tab == 'design'): ?>
        
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-6 flex flex-wrap gap-4 items-center justify-between">
            <div class="flex items-center gap-4">
                <label class="font-bold text-gray-700">Editing Template:</label>
                <select onchange="window.location.href='?tab=design&tid='+this.value" class="border border-gray-300 p-2 rounded bg-gray-50 font-bold outline-none">
                    <?php foreach($all_templates as $tpl): ?>
                        <option value="<?php echo $tpl['id']; ?>" <?php echo $active_tid == $tpl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tpl['template_name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="new" <?php echo $active_tid == 'new' ? 'selected' : ''; ?>>+ Create New Template</option>
                </select>
            </div>
            
            <?php if($active_tid != 'new'): ?>
                <form method="POST" onsubmit="return confirm('Delete this template permanently?');">
                    <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($active_tid); ?>">
                    <button type="submit" name="delete_template" class="text-red-500 hover:text-red-700 font-bold text-sm flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">delete</span> Delete
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="template_id" value="<?php echo htmlspecialchars($active_tid); ?>">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <div class="lg:col-span-5 space-y-6">
                    
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
                        <label class="text-xs font-bold text-gray-500 uppercase block mb-2">Template Name</label>
                        <input type="text" name="template_name" value="<?php echo htmlspecialchars($t_data['template_name'] ?? ''); ?>" required placeholder="e.g. Weekly Newsletter" class="w-full text-lg font-bold border p-3 rounded outline-none focus:ring-2 focus:ring-green-500">
                    </div>

                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">1. Layout & Text Colors</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">Master Background</label>
                                <input type="color" name="email_bg_color" id="ctrl_bg_color" value="<?php echo htmlspecialchars($t_data['email_bg_color'] ?? '#f8faf9'); ?>" class="block w-full h-10 border rounded mt-1 cursor-pointer">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">Content Card BG</label>
                                <input type="color" name="email_card_bg" id="ctrl_card_bg" value="<?php echo htmlspecialchars($t_data['email_card_bg'] ?? '#ffffff'); ?>" class="block w-full h-10 border rounded mt-1 cursor-pointer">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">Main Body Text</label>
                                <input type="color" name="email_body_text" id="ctrl_body_text" value="<?php echo htmlspecialchars($t_data['email_body_text'] ?? '#1a1a1a'); ?>" class="block w-full h-10 border rounded mt-1 cursor-pointer">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-green-600 uppercase">Hyperlinks</label>
                                <input type="color" name="email_link_color" id="ctrl_link_color" value="<?php echo htmlspecialchars($t_data['email_link_color'] ?? '#2563eb'); ?>" class="block w-full h-10 border rounded mt-1 cursor-pointer">
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">2. Custom Header</h3>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="col-span-2">
                                <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Overlay Text</label>
                                <input type="text" name="email_header_text" id="ctrl_header_text" value="<?php echo htmlspecialchars($t_data['email_header_text'] ?? ''); ?>" class="w-full text-sm border p-2 rounded outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            <div class="col-span-2">
                                <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Header Font</label>
                                <select name="email_header_font" id="ctrl_header_font" class="w-full text-sm border p-2 rounded outline-none focus:ring-2 focus:ring-green-500">
                                    <option value="Arial, sans-serif" <?php echo ($t_data['email_header_font'] ?? '') == 'Arial, sans-serif' ? 'selected' : ''; ?>>Arial (Sans-Serif)</option>
                                    <option value="Georgia, serif" <?php echo ($t_data['email_header_font'] ?? '') == 'Georgia, serif' ? 'selected' : ''; ?>>Georgia (Serif)</option>
                                    <option value="'Times New Roman', Times, serif" <?php echo ($t_data['email_header_font'] ?? '') == "'Times New Roman', Times, serif" ? 'selected' : ''; ?>>Times New Roman</option>
                                    <option value="Tahoma, Geneva, sans-serif" <?php echo ($t_data['email_header_font'] ?? '') == 'Tahoma, Geneva, sans-serif' ? 'selected' : ''; ?>>Tahoma</option>
                                    <option value="'Trebuchet MS', Helvetica, sans-serif" <?php echo ($t_data['email_header_font'] ?? '') == "'Trebuchet MS', Helvetica, sans-serif" ? 'selected' : ''; ?>>Trebuchet MS</option>
                                    <option value="Impact, Charcoal, sans-serif" <?php echo ($t_data['email_header_font'] ?? '') == 'Impact, Charcoal, sans-serif' ? 'selected' : ''; ?>>Impact (Bold/Condensed)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4 pt-4 border-t border-gray-100">
                            <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Background Image Banner</label>
                            <p class="text-[10px] text-gray-400 mb-2">Your text will neatly overlay this image.</p>
                            <?php if(!empty($t_data['email_header_img'])): ?><div class="text-xs text-green-600 mb-1">✓ Background Image Active</div><?php endif; ?>
                            <input type="file" name="email_header_img" accept=".png,.jpg,.jpeg,.gif" class="w-full text-sm border p-2 rounded">
                        </div>

                        <div class="mb-4 pt-4 border-t border-gray-100">
                            <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Raw HTML Code (Advanced)</label>
                            <p class="text-[10px] text-gray-400 mb-2">Overrides the text and image builder to use pure custom HTML.</p>
                            <textarea name="email_header_raw" id="ctrl_header_raw" class="w-full h-24 text-xs font-mono border p-2 rounded outline-none bg-gray-50"><?php echo htmlspecialchars($t_data['email_header_raw'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">3. Custom Footer</h3>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">Footer BG Color</label>
                                <input type="color" name="email_footer_bg" id="ctrl_footer_bg" value="<?php echo htmlspecialchars($t_data['email_footer_bg'] ?? '#f3f4f6'); ?>" class="block w-full h-10 border rounded mt-1 cursor-pointer">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-gray-500 uppercase">Footer Text Color</label>
                                <input type="color" name="email_footer_text" id="ctrl_footer_text" value="<?php echo htmlspecialchars($t_data['email_footer_text'] ?? '#6b7280'); ?>" class="block w-full h-10 border rounded mt-1 cursor-pointer">
                            </div>
                        </div>

                        <div class="mb-4 pt-4 border-t border-gray-100">
                            <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Footer Signature/Logo</label>
                            <?php if(!empty($t_data['email_footer_img'])): ?><div class="text-xs text-green-600 mb-1">✓ Image Active</div><?php endif; ?>
                            <input type="file" name="email_footer_img" accept=".png,.jpg,.jpeg,.gif" class="w-full text-sm border p-2 rounded">
                        </div>

                        <div class="mb-4 pt-4 border-t border-gray-100">
                            <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Raw HTML Code (Advanced)</label>
                            <textarea name="email_footer_raw" id="ctrl_footer_raw" class="w-full h-24 text-xs font-mono border p-2 rounded outline-none bg-gray-50"><?php echo htmlspecialchars($t_data['email_footer_raw'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" name="submit_template" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-lg shadow-lg transition transform hover:-translate-y-0.5">
                        <?php echo $active_tid == 'new' ? 'Save as New Template' : 'Update Template'; ?>
                    </button>
                </div>

                <div class="lg:col-span-7 relative">
                    <div class="sticky top-24">
                        <div class="flex justify-between items-center mb-2 px-2">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Live Preview</span>
                        </div>
                        
                        <div id="preview_frame" class="border border-gray-300 rounded-lg overflow-hidden shadow-xl" style="background-color: <?php echo htmlspecialchars($t_data['email_bg_color'] ?? '#f8faf9'); ?>; padding: 40px;">
                            <div id="preview_card" class="max-w-[500px] mx-auto rounded-md overflow-hidden shadow-sm border border-gray-100" style="background-color: <?php echo htmlspecialchars($t_data['email_card_bg'] ?? '#ffffff'); ?>;">
                                
                                <div id="preview_header_wrapper">
                                    <?php if(!empty($t_data['email_header_raw'])): ?>
                                        <div id="preview_header_raw_container"><?php echo $t_data['email_header_raw']; ?></div>
                                    <?php else: ?>
                                        <div id="preview_header" class="text-center" style="
                                            padding: 50px 20px; 
                                            <?php if(!empty($t_data['email_header_img'])): ?>
                                                background-image: url('../<?php echo htmlspecialchars($t_data['email_header_img']); ?>');
                                                background-size: cover;
                                                background-position: center;
                                                background-color: transparent;
                                            <?php else: ?>
                                                background-color: #1a1a1a;
                                            <?php endif; ?>
                                        ">
                                            <h1 id="preview_header_text" style="
                                                color: #ffffff; 
                                                margin: 0; 
                                                font-family: <?php echo htmlspecialchars($t_data['email_header_font'] ?? 'Arial, sans-serif'); ?>; 
                                                font-size: 32px; 
                                                font-weight: bold; 
                                                text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
                                            ">
                                                <?php echo !empty($t_data['email_header_text']) ? htmlspecialchars($t_data['email_header_text']) : 'Asiko System'; ?>
                                            </h1>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="p-8 font-sans leading-relaxed" id="preview_body" style="color: <?php echo htmlspecialchars($t_data['email_body_text'] ?? '#1a1a1a'); ?>;">
                                    <h2 class="text-xl font-bold mb-4">Template Preview</h2>
                                    <p class="mb-4">This demonstrates how your paragraphs will look.</p>
                                    <p>
                                        You can also check your 
                                        <a href="#" id="preview_link" style="color: <?php echo htmlspecialchars($t_data['email_link_color'] ?? '#2563eb'); ?>; font-weight: bold; text-decoration: underline;">Hyperlink Color</a>.
                                    </p>
                                </div>

                                <div id="preview_footer_wrapper">
                                    <?php if(!empty($t_data['email_footer_raw'])): ?>
                                        <div id="preview_footer_raw_container"><?php echo $t_data['email_footer_raw']; ?></div>
                                    <?php else: ?>
                                        <div id="preview_footer" class="p-6 text-center border-t border-gray-100" style="background-color: <?php echo htmlspecialchars($t_data['email_footer_bg'] ?? '#f3f4f6'); ?>;">
                                            <?php if(!empty($t_data['email_footer_img'])): ?>
                                                <img src="../<?php echo htmlspecialchars($t_data['email_footer_img']); ?>" class="h-12 mx-auto mb-4 opacity-80">
                                            <?php endif; ?>
                                            <p class="text-[10px] uppercase font-bold tracking-wider mb-1" id="preview_footer_copy" style="color: <?php echo htmlspecialchars($t_data['email_footer_text'] ?? '#6b7280'); ?>;">
                                                &copy; <?php echo date('Y'); ?> Asiko System
                                            </p>
                                            <p class="text-[10px]" style="color: <?php echo htmlspecialchars($t_data['email_footer_text'] ?? '#6b7280'); ?>; opacity: 0.7;">
                                                Manage your preferences | Unsubscribe
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <script>
                const inputs = {
                    'ctrl_bg_color': { id: 'preview_frame', prop: 'backgroundColor' },
                    'ctrl_card_bg': { id: 'preview_card', prop: 'backgroundColor' },
                    'ctrl_body_text': { id: 'preview_body', prop: 'color' },
                    'ctrl_link_color': { id: 'preview_link', prop: 'color' },
                    'ctrl_footer_bg': { id: 'preview_footer', prop: 'backgroundColor' },
                    'ctrl_footer_text': { id: 'preview_footer_copy', prop: 'color' },
                    'ctrl_header_text': { id: 'preview_header_text', prop: 'innerText' },
                    'ctrl_header_font': { id: 'preview_header_text', prop: 'fontFamily' }
                };

                Object.keys(inputs).forEach(key => {
                    const el = document.getElementById(key);
                    if(el) {
                        el.addEventListener('input', (e) => {
                            const val = e.target.value;
                            const targetEl = document.getElementById(inputs[key].id);
                            if(targetEl) {
                                if (inputs[key].prop === 'innerText') {
                                    targetEl.innerText = val || el.getAttribute('placeholder');
                                } else {
                                    targetEl.style[inputs[key].prop] = val;
                                }
                            }
                            if(key === 'ctrl_footer_text') {
                                const subText = document.querySelector('#preview_footer p:last-child');
                                if(subText) subText.style.color = val;
                            }
                        });
                    }
                });

                document.getElementById('ctrl_header_raw').addEventListener('input', function(e) {
                    const wrap = document.getElementById('preview_header_wrapper');
                    if (e.target.value.trim() !== '') {
                        wrap.innerHTML = '<div id="preview_header_raw_container">' + e.target.value + '</div>';
                    } else {
                        wrap.innerHTML = `<div id="preview_header" class="text-center p-8" style="background-color: #1a1a1a;"><h1 id="preview_header_text" class="text-white font-serif text-2xl" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.8);">Asiko System</h1></div>`;
                    }
                });

                document.getElementById('ctrl_footer_raw').addEventListener('input', function(e) {
                    const wrap = document.getElementById('preview_footer_wrapper');
                    if (e.target.value.trim() !== '') {
                        wrap.innerHTML = '<div id="preview_footer_raw_container">' + e.target.value + '</div>';
                    } else {
                        wrap.innerHTML = `<div id="preview_footer" class="p-6 text-center border-t border-gray-100" style="background-color: #f3f4f6;"><p class="text-[10px] uppercase font-bold tracking-wider mb-1" id="preview_footer_copy" style="color: #6b7280;">&copy; <?php echo date('Y'); ?> Asiko System</p></div>`;
                    }
                });
            </script>
        </form>
    <?php endif; ?>

    <?php if ($tab == 'router'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">Create New Rule</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_mapping">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Target App</label>
                            <select name="app_name" required class="w-full border border-gray-300 p-2 rounded outline-none focus:ring-2 focus:ring-green-500 bg-gray-50">
                                <option value="scrummy">Scrummy Nummy</option>
                                <option value="cakes">Cakes & More</option>
                                <option value="kiosk">Asiko Kiosk</option>
                                <option value="hq">Master Vault (HQ)</option>
                                <option value="*">Any App (Fallback)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Event Trigger</label>
                            <input list="event_names" name="event_name" required placeholder="e.g. order.placed" class="w-full border border-gray-300 p-2 rounded outline-none focus:ring-2 focus:ring-green-500">
                            <datalist id="event_names">
                                <option value="order.placed">
                                <option value="order.status_changed">
                            </datalist>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">HTML Template</label>
                            <?php if(empty($html_files)): ?>
                                <div class="text-xs text-red-500 font-bold p-2 bg-red-50 rounded">No .html files found in /templates/</div>
                            <?php else: ?>
                                <select name="template_file" required class="w-full border border-gray-300 p-2 rounded outline-none focus:ring-2 focus:ring-green-500 bg-gray-50">
                                    <option value="">-- Select Template --</option>
                                    <?php foreach($html_files as $tpl): ?>
                                        <option value="<?php echo htmlspecialchars($tpl); ?>"><?php echo htmlspecialchars($tpl); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="w-full bg-gray-900 hover:bg-black text-white font-bold py-3 rounded shadow transition">
                            Save Rule
                        </button>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                        <h3 class="font-bold text-gray-800">Active Routing Rules</h3>
                    </div>
                    <?php if (empty($mappings)): ?>
                        <div class="p-8 text-center text-gray-400">No template rules configured yet.</div>
                    <?php else: ?>
                        <table class="w-full text-left">
                            <thead class="bg-white text-xs font-bold text-gray-500 uppercase border-b border-gray-100">
                                <tr>
                                    <th class="p-4">App Source</th>
                                    <th class="p-4">Trigger Event</th>
                                    <th class="p-4">Template File</th>
                                    <th class="p-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 text-sm text-gray-700">
                                <?php foreach($mappings as $map): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="p-4 font-bold uppercase tracking-wider text-[10px]"><?php echo htmlspecialchars($map['app_name']); ?></td>
                                    <td class="p-4 font-mono text-gray-600 bg-gray-50 rounded inline-block mt-2 ml-2"><?php echo htmlspecialchars($map['event_name']); ?></td>
                                    <td class="p-4"><?php echo htmlspecialchars($map['template_file']); ?></td>
                                    <td class="p-4 text-right">
                                        <a href="?tab=router&delete_mapping_id=<?php echo $map['id']; ?>" onclick="return confirm('Remove this rule?')" class="text-red-400 hover:text-red-600">
                                            <span class="material-symbols-outlined">delete</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>