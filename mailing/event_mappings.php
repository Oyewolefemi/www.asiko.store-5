<?php
// mailing/event_mappings.php
// ============================================================
// Visual UI for managing event mappings without touching code.
// Add a new app → add rows here → flip toggle on → done.
// ============================================================
session_start();
require_once 'db.php';

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    die("Access Denied.");
}

$msg   = '';
$error = '';

// --- DELETE ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM mail_event_mappings WHERE id = ?")->execute([(int)$_GET['delete']]);
    header("Location: event_mappings.php?msg=deleted");
    exit;
}

// --- TOGGLE ACTIVE ---
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $pdo->prepare("UPDATE mail_event_mappings SET is_active = NOT is_active WHERE id = ?")->execute([(int)$_GET['toggle']]);
    header("Location: event_mappings.php");
    exit;
}

// --- SAVE (ADD or EDIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mapping'])) {
    $id              = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $app_source      = trim($_POST['app_source']);
    $event_name      = trim($_POST['event_name']);
    $display_name    = trim($_POST['display_name']);
    $template_id     = (int)$_POST['template_id'];
    $subject_tpl     = trim($_POST['subject_template']);
    $recipient_field = trim($_POST['recipient_field']);
    $body_tpl        = $_POST['body_template'];
    $is_active       = isset($_POST['is_active']) ? 1 : 0;

    if (empty($app_source) || empty($event_name) || empty($subject_tpl) || empty($recipient_field)) {
        $error = "App, Event Name, Subject, and Recipient Field are all required.";
    } else {
        try {
            if ($id) {
                $pdo->prepare("
                    UPDATE mail_event_mappings
                    SET app_source=?, event_name=?, display_name=?, template_id=?,
                        subject_template=?, recipient_field=?, body_template=?, is_active=?
                    WHERE id=?
                ")->execute([$app_source, $event_name, $display_name, $template_id,
                              $subject_tpl, $recipient_field, $body_tpl, $is_active, $id]);
                $msg = "Mapping updated.";
            } else {
                $pdo->prepare("
                    INSERT INTO mail_event_mappings
                        (app_source, event_name, display_name, template_id, subject_template, recipient_field, body_template, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$app_source, $event_name, $display_name, $template_id,
                              $subject_tpl, $recipient_field, $body_tpl, $is_active]);
                $msg = "Mapping created.";
            }
        } catch (PDOException $e) {
            $error = $e->getCode() == 23000
                ? "A mapping for '{$app_source} → {$event_name}' already exists."
                : "Database error: " . $e->getMessage();
        }
    }
}

// --- FETCH DATA ---
$mappings  = $pdo->query("SELECT * FROM mail_event_mappings ORDER BY app_source, event_name")->fetchAll();
$templates = $pdo->query("SELECT id, template_name FROM mail_templates ORDER BY id")->fetchAll();

// For the edit form
$editing = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM mail_event_mappings WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') $msg = "Mapping deleted.";
}

// Group mappings by app for display
$grouped = [];
foreach ($mappings as $m) {
    $grouped[$m['app_source']][] = $m;
}
ksort($grouped);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Mappings – Mailing System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
</head>
<body class="bg-gray-100 font-sans">

<header class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center sticky top-0 z-30">
    <h1 class="font-bold text-xl text-gray-800">Mailing Microservice</h1>
    <a href="../hq/index.php" class="text-blue-600 hover:underline text-sm font-bold">← Back to HQ</a>
</header>

<div class="max-w-7xl mx-auto px-4 py-8 pb-16">

    <div class="flex justify-between items-start mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800">Event Mappings</h2>
            <p class="text-gray-500 mt-1">
                Each row connects an app event to an email. Toggle the switch to activate or pause it.
                <strong>No code needed to add a new app.</strong>
            </p>
        </div>
        <a href="?new=1" class="bg-gray-900 hover:bg-black text-white font-bold py-2.5 px-5 rounded-lg shadow flex items-center gap-2 transition">
            <span class="material-symbols-outlined text-sm">add</span> New Mapping
        </a>
    </div>

    <!-- NAV TABS -->
    <div class="flex border-b border-gray-200 mb-8 overflow-x-auto bg-white rounded-t-xl px-4 pt-2 shadow-sm">
        <a href="index.php"         class="px-5 py-4 font-bold text-sm text-gray-500 hover:text-gray-800 whitespace-nowrap flex items-center gap-2"><span class="material-symbols-outlined text-base">dashboard</span> Overview</a>
        <a href="compose.php"       class="px-5 py-4 font-bold text-sm text-gray-500 hover:text-gray-800 whitespace-nowrap flex items-center gap-2"><span class="material-symbols-outlined text-base">campaign</span> Compose</a>
        <a href="subscribers.php"   class="px-5 py-4 font-bold text-sm text-gray-500 hover:text-gray-800 whitespace-nowrap flex items-center gap-2"><span class="material-symbols-outlined text-base">group</span> Audience</a>
        <a href="event_mappings.php" class="px-5 py-4 font-bold text-sm border-b-2 border-indigo-600 text-indigo-600 whitespace-nowrap flex items-center gap-2"><span class="material-symbols-outlined text-base">electrical_services</span> Event Mappings</a>
        <a href="settings.php"      class="px-5 py-4 font-bold text-sm text-gray-500 hover:text-gray-800 whitespace-nowrap flex items-center gap-2"><span class="material-symbols-outlined text-base">settings</span> Settings</a>
    </div>

    <?php if ($msg): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-xl mb-6 font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">check_circle</span> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl mb-6 font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">error</span> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- LEFT: FORM -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 sticky top-24">
                <h3 class="font-bold text-gray-900 mb-1 flex items-center gap-2">
                    <span class="material-symbols-outlined text-indigo-500">electrical_services</span>
                    <?= $editing ? 'Edit Mapping' : 'Add New Mapping' ?>
                </h3>
                <p class="text-xs text-gray-500 mb-5">
                    Map an event name fired by an app to an email template and subject. Use
                    <code class="bg-gray-100 px-1 rounded">&#123;&#123;key&#125;&#125;</code>
                    placeholders in the subject and body.
                </p>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="save_mapping" value="1">
                    <?php if ($editing): ?>
                        <input type="hidden" name="id" value="<?= $editing['id'] ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">App Source</label>
                            <input type="text" name="app_source" required
                                   value="<?= htmlspecialchars($editing['app_source'] ?? '') ?>"
                                   placeholder="cakes, scrummy, any"
                                   class="w-full border p-2.5 rounded-lg bg-gray-50 text-sm outline-none focus:border-indigo-500 font-mono">
                            <p class="text-[10px] text-gray-400 mt-1">Use <code>any</code> for all apps.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Event Name</label>
                            <input type="text" name="event_name" required
                                   value="<?= htmlspecialchars($editing['event_name'] ?? '') ?>"
                                   placeholder="order.placed"
                                   class="w-full border p-2.5 rounded-lg bg-gray-50 text-sm outline-none focus:border-indigo-500 font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Display Name</label>
                        <input type="text" name="display_name"
                               value="<?= htmlspecialchars($editing['display_name'] ?? '') ?>"
                               placeholder="e.g. Cakes: Order Confirmation"
                               class="w-full border p-2.5 rounded-lg bg-gray-50 text-sm outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Email Template</label>
                        <select name="template_id" class="w-full border p-2.5 rounded-lg bg-gray-50 text-sm outline-none focus:border-indigo-500">
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($editing['template_id'] ?? 1) == $t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['template_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Subject Template</label>
                        <input type="text" name="subject_template" required
                               value="<?= htmlspecialchars($editing['subject_template'] ?? '') ?>"
                               placeholder="Order #&#123;&#123;order_id&#125;&#125; Confirmed!"
                               class="w-full border p-2.5 rounded-lg bg-gray-50 text-sm outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Recipient Field</label>
                        <input type="text" name="recipient_field" required
                               value="<?= htmlspecialchars($editing['recipient_field'] ?? 'customer_email') ?>"
                               placeholder="customer_email"
                               class="w-full border p-2.5 rounded-lg bg-gray-50 text-sm outline-none focus:border-indigo-500 font-mono">
                        <p class="text-[10px] text-gray-400 mt-1">The key in your <code>data</code> array that holds the recipient's email.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Email Body</label>
                        <textarea name="body_template" rows="8"
                                  placeholder="<h2>Hi &#123;&#123;customer_name&#125;&#125;</h2><p>Your order is confirmed.</p>"
                                  class="w-full border p-2.5 rounded-lg bg-gray-50 text-xs font-mono outline-none focus:border-indigo-500 resize-y"><?= htmlspecialchars($editing['body_template'] ?? '') ?></textarea>
                        <p class="text-[10px] text-gray-400 mt-1">HTML is supported. Use &#123;&#123;placeholder&#125;&#125; for dynamic values.</p>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" class="sr-only"
                                <?= (!$editing || $editing['is_active']) ? 'checked' : '' ?>>
                            <div class="w-10 h-6 bg-gray-200 rounded-full relative transition-colors duration-200
                                        peer-checked:bg-indigo-500 toggle-bg"></div>
                        </label>
                        <span class="text-sm font-bold text-gray-700">Active (send emails immediately)</span>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <?php if ($editing): ?>
                            <a href="event_mappings.php" class="flex-1 py-2.5 text-center text-gray-500 font-bold hover:bg-gray-100 rounded-lg border text-sm">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg shadow text-sm transition">
                            <?= $editing ? 'Update Mapping' : 'Create Mapping' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RIGHT: MAPPINGS TABLE -->
        <div class="lg:col-span-2 space-y-6">

            <?php if (empty($grouped)): ?>
                <div class="bg-white p-12 rounded-xl border border-gray-200 text-center shadow-sm">
                    <span class="material-symbols-outlined text-5xl text-gray-300 mb-4">electrical_services</span>
                    <p class="font-bold text-gray-600 text-lg">No mappings yet.</p>
                    <p class="text-gray-400 text-sm mt-1">Create your first mapping using the form on the left.</p>
                </div>
            <?php else: ?>

                <?php foreach ($grouped as $appName => $rows): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3.5 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-indigo-500 text-lg">
                                <?= $appName === 'any' ? 'public' : 'apps' ?>
                            </span>
                            <h3 class="font-bold text-gray-900 tracking-wide font-mono">
                                <?= htmlspecialchars($appName) ?>
                            </h3>
                            <?php if ($appName === 'any'): ?>
                                <span class="text-[10px] bg-indigo-100 text-indigo-700 font-bold px-2 py-0.5 rounded uppercase">Wildcard</span>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-gray-400 font-bold"><?= count($rows) ?> event<?= count($rows) > 1 ? 's' : '' ?></span>
                    </div>

                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-gray-400 font-bold bg-white border-b border-gray-100">
                            <tr>
                                <th class="p-4">Event</th>
                                <th class="p-4">Subject Preview</th>
                                <th class="p-4 text-center">Active</th>
                                <th class="p-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($rows as $row): ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="p-4">
                                    <span class="font-mono font-bold text-gray-800 text-xs bg-gray-100 px-2 py-1 rounded">
                                        <?= htmlspecialchars($row['event_name']) ?>
                                    </span>
                                    <?php if ($row['display_name']): ?>
                                        <p class="text-gray-500 text-xs mt-1"><?= htmlspecialchars($row['display_name']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-gray-600 text-xs max-w-xs truncate">
                                    <?= htmlspecialchars($row['subject_template']) ?>
                                </td>
                                <td class="p-4 text-center">
                                    <a href="?toggle=<?= $row['id'] ?>" title="Click to toggle">
                                        <?php if ($row['is_active']): ?>
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-green-700 bg-green-100 px-2 py-1 rounded-full border border-green-200 hover:bg-green-200 transition">
                                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span> On
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-gray-500 bg-gray-100 px-2 py-1 rounded-full border border-gray-200 hover:bg-gray-200 transition">
                                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span> Off
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td class="p-4 text-right whitespace-nowrap">
                                    <a href="?edit=<?= $row['id'] ?>" class="text-blue-600 font-bold hover:underline text-xs mr-3">Edit</a>
                                    <a href="?delete=<?= $row['id'] ?>"
                                       onclick="return confirm('Delete this mapping?')"
                                       class="text-red-400 hover:text-red-600 font-bold text-xs">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>

            <?php endif; ?>

            <!-- HOW IT WORKS -->
            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-6 text-sm text-indigo-900">
                <h4 class="font-bold mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-indigo-500">info</span>
                    How to connect a new app — no code in this system
                </h4>
                <ol class="list-decimal list-inside space-y-2 text-indigo-800">
                    <li>Register the app in <strong>HQ → App Setup</strong>.</li>
                    <li>Drop <code class="bg-white px-1 rounded border border-indigo-200">fire_mail_event.php</code> into the app's <code>includes/</code> folder and include it in <code>config.php</code>.</li>
                    <li>Add one call in the app at the right moment: <br>
                        <code class="block bg-white mt-1 p-2 rounded border border-indigo-200 text-xs">fire_mail_event('order.placed', $data, 'newapp');</code>
                    </li>
                    <li>Come back here and <strong>Add a New Mapping</strong> for <code>app_source = newapp</code>, <code>event = order.placed</code>.</li>
                    <li>Flip the toggle to <strong>On</strong>. Done.</li>
                </ol>
            </div>

        </div>
    </div>
</div>

<style>
    .toggle-bg { transition: background-color 0.2s; }
    .toggle-bg::after {
        content: '';
        position: absolute;
        top: 2px; left: 2px;
        background: white;
        border-radius: 50%;
        height: 20px; width: 20px;
        transition: transform 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
    }
    input:checked + .toggle-bg { background-color: #6366f1; }
    input:checked + .toggle-bg::after { transform: translateX(16px); }
</style>

</body>
</html>
