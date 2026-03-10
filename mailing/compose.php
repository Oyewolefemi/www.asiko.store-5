<?php
// mailing/compose.php
session_start();

// 1. ISOLATED MAILING SYSTEM INCLUDES
require_once 'db.php'; 
require_once 'api/remote_mailer.php'; 

// Security Check - Only Super Admin can send blasts
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    die("Access Denied. Only Super Admin can send bulk emails.");
}

$msg = '';
$error = '';

// Fetch available templates for the dropdown
$templates = [];
try {
    $templates = $pdo->query("SELECT id, template_name FROM mail_templates ORDER BY template_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$subject = trim($_POST['subject'] ?? '');
$content = $_POST['content'] ?? '';

// --- DRAFT-TO-BROADCAST MAGIC PIPELINE (Requires fetching post from Asiko DB) ---
if (empty($subject) && empty($content) && isset($_GET['post_id'])) {
    try {
        $stmt = $pdo_asiko->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$_GET['post_id']]);
        $draft_post = $stmt->fetch();
        
        if ($draft_post) {
            $subject = "New Story: " . $draft_post['title'];
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $domain = $_SERVER['HTTP_HOST'];
            $article_link = $protocol . "://" . $domain . "/article.php?id=" . $draft_post['id'];
            
            $content = "<h2 style=\"text-align: center; color: #1e293b; font-family: Georgia, serif;\">" . htmlspecialchars($draft_post['title']) . "</h2>\n";
            
            if (!empty($draft_post['image_url'])) {
                $content .= "<p style=\"text-align: center;\"><img src=\"{$protocol}://{$domain}/{$draft_post['image_url']}\" width=\"250\" style=\"width: 250px; height: auto; border-radius: 8px; margin: 15px auto; display: inline-block; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);\"></p>\n";
            }
            
            $excerpt = mb_substr(strip_tags($draft_post['content']), 0, 200) . '...';
            $content .= "<p style=\"font-size: 16px; line-height: 1.6; color: #475569; text-align: center; padding: 0 20px;\">{$excerpt}</p>\n";
            $content .= "<div style=\"text-align: center; margin-top: 25px;\">\n";
            $content .= "  <a href=\"{$article_link}\" class=\"button\" style=\"display: inline-block; padding: 12px 30px; border-radius: 6px; font-weight: bold; font-family: sans-serif; text-transform: uppercase; font-size: 13px; letter-spacing: 1px;\">Read Full Story</a>\n";
            $content .= "</div>";
        }
    } catch(Exception $e) {}
}
// -----------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_id = (int)($_POST['template_id'] ?? 1);
    $audience = trim($_POST['audience'] ?? 'all');

    // Fetch template name for logging
    $template_name = 'Custom Template';
    foreach ($templates as $t) {
        if ($t['id'] == $template_id) {
            $template_name = $t['template_name'];
            break;
        }
    }

    if (empty($subject) || empty($content)) {
        $error = "Subject and message content are required.";
    } else {
        // 1. Gather the Audience
        $recipients = [];
        
        // Fetch Registered Members (Using the read-only connection to Asiko DB)
        if ($audience === 'members' || $audience === 'all') {
            try {
                $stmt = $pdo_asiko->query("SELECT email, name FROM users WHERE email IS NOT NULL AND email != ''");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $recipients[trim($row['email'])] = $row['name'] ?? 'Member'; // Key by email to prevent dupes
                }
            } catch (PDOException $e) {}
        }
        
        // Fetch Newsletter Subscribers (From internal Mailing DB)
        if ($audience === 'subscribers' || $audience === 'all') {
            try {
                $stmt = $pdo->query("SELECT email FROM mail_subscribers WHERE status = 'active' AND email IS NOT NULL AND email != ''");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $email = trim($row['email']);
                    if (!isset($recipients[$email])) {
                        $recipients[$email] = 'Subscriber'; // Add if not already a member
                    }
                }
            } catch (PDOException $e) {}
        }

        $success_count = 0;

        // 2. Loop and Send via the API Helper
        if (count($recipients) > 0) {
            foreach ($recipients as $email => $name) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    
                    // Route through our new central microservice API
                    $result = trigger_remote_email(
                        $email, 
                        $subject, 
                        $content, 
                        'Mailing Admin', 
                        $name
                    );

                    if (isset($result['status']) && $result['status'] === 'success') {
                        $success_count++;
                    }
                }
            }

            // 3. Log the Campaign in Mailing DB
            try {
                $logStmt = $pdo->prepare("INSERT INTO mail_campaigns (subject, theme, audience, recipients_count, sent_at) VALUES (?, ?, ?, ?, NOW())");
                $logStmt->execute([$subject, $template_name, $audience, $success_count]);
            } catch (PDOException $e) {}

            $msg = "Campaign sent successfully to " . number_format($success_count) . " recipients!";
        } else {
            $error = "No valid email addresses found for the selected audience.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compose Broadcast - Mailing System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
</head>
<body class="bg-gray-100 font-sans">

<header class="bg-white shadow-sm border-b px-6 py-4 flex justify-between items-center">
    <h1 class="font-bold text-xl text-gray-800">Mailing Microservice</h1>
    <a href="../kiosk/Red/admin_dashboard.php" class="text-blue-600 hover:underline text-sm font-bold">← Back to Ecosystem</a>
</header>

<div class="container mx-auto mt-8 px-4 pb-12 max-w-6xl">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800 tracking-tight">Compose Campaign</h2>
            <p class="text-gray-500 mt-1">Draft and broadcast beautifully themed emails via microservice API.</p>
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
        <a href="compose.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap border-b-2 border-green-600 text-green-600 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">campaign</span> Compose
        </a>
        <a href="subscribers.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">group</span> Audience
        </a>
        <a href="settings.php" class="px-6 py-4 font-bold text-sm whitespace-nowrap text-gray-500 hover:text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">settings</span> Settings
        </a>
    </div>

    <form method="POST" class="bg-white p-8 rounded-xl shadow-sm border border-gray-200">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            
            <div class="md:col-span-2">
                <label class="block font-bold text-gray-800 mb-2">Subject Line</label>
                <input type="text" name="subject" required value="<?php echo htmlspecialchars($subject); ?>" class="w-full border border-gray-300 p-4 rounded-lg text-lg focus:ring-2 focus:ring-green-500 outline-none" placeholder="Catchy email subject...">
            </div>

            <div class="space-y-6">
                <div>
                    <label class="block font-bold text-gray-800 mb-2">Design Template</label>
                    <select name="template_id" class="w-full border border-gray-300 p-3 rounded bg-gray-50 focus:ring-2 focus:ring-green-500 outline-none font-bold text-gray-700">
                        <?php foreach($templates as $tpl): ?>
                            <option value="<?php echo $tpl['id']; ?>"><?php echo htmlspecialchars($tpl['template_name']); ?></option>
                        <?php endforeach; ?>
                        <?php if(empty($templates)): ?>
                            <option value="1">Fallback Template</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-gray-800 mb-2">Target Audience</label>
                    <select name="audience" class="w-full border border-gray-300 p-3 rounded bg-gray-50 focus:ring-2 focus:ring-green-500 outline-none font-bold text-gray-700">
                        <option value="all">Everyone (Members + Subscribers)</option>
                        <option value="members">Registered Members Only</option>
                        <option value="subscribers">Newsletter Subscribers Only</option>
                    </select>
                </div>
            </div>
            
        </div>

        <div>
            <label class="block font-bold text-gray-800 mb-2">Message Body</label>
            <div class="bg-indigo-50 p-4 rounded-t-lg border border-indigo-100 mb-[-1px] relative z-10 text-sm text-indigo-800">
                <strong>Tip:</strong> You do not need to add your logo, header, or footer. Just write the core message. Our system will automatically wrap it in your selected design template.
            </div>
            <textarea name="content" id="content" required class="w-full h-96"><?php echo htmlspecialchars($content); ?></textarea>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200 flex justify-end">
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-4 px-10 rounded-lg shadow-lg transition transform hover:-translate-y-0.5 flex items-center gap-2 text-lg">
                <span class="material-symbols-outlined">send</span> Send Broadcast
            </button>
        </div>
    </form>
</div>

<script>
    CKEDITOR.replace('content', { 
        height: 400, 
        versionCheck: false,
        toolbar: [
            { name: 'document', items: [ 'Source' ] },
            { name: 'clipboard', items: [ 'Undo', 'Redo' ] },
            { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', 'RemoveFormat' ] },
            { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'JustifyLeft', 'JustifyCenter', 'JustifyRight' ] },
            { name: 'links', items: [ 'Link', 'Unlink' ] },
            { name: 'insert', items: [ 'Image', 'Table', 'HorizontalRule' ] },
            { name: 'styles', items: [ 'Format', 'Font', 'FontSize' ] },
            { name: 'colors', items: [ 'TextColor', 'BGColor' ] }
        ]
    });
</script>

</body>
</html>