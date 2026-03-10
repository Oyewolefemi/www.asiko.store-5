<?php
require_once '../config.php';
include '../includes/admin_header.php';

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Validation Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security Check Failed: Invalid CSRF Token.");
    }

    $updates = [
        'site_name'     => $_POST['site_name'],
        'primary_color' => $_POST['primary_color'],
        'header_bg'     => $_POST['header_bg'],
        'body_bg'       => $_POST['body_bg']
    ];

    // STRICT LOGO UPLOAD LOGIC
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $maxSize = 2 * 1024 * 1024; // 2MB Limit
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if ($_FILES['logo']['size'] > $maxSize) {
            die("Upload Error: File exceeds the 2MB limit.");
        }
        
        // Verify true MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $trueMime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($trueMime, $allowedMimes)) {
            die("Upload Error: Invalid file format. Only JPG, PNG, and WEBP are allowed.");
        }

        $targetDir = "../assets/uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        // Sanitize filename
        $safeFileName = preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($_FILES['logo']['name']));
        $fileName = time() . '_' . $safeFileName;
        $targetFile = $targetDir . $fileName;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
            $updates['logo_url'] = 'assets/uploads/' . $fileName;
        } else {
            die("Upload Error: Failed to move file to destination.");
        }
    }

    $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    foreach ($updates as $key => $val) {
        $stmt->execute([$key, $val]);
    }
    // Refresh to apply changes immediately
    echo "<script>window.location.href='settings.php?success=1';</script>";
    exit;
}
?>

<div class="max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
        <p class="text-sm text-gray-500">Customize the look and feel of your store.</p>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded-xl mb-6 font-bold flex items-center gap-2">
            <span class="material-symbols-outlined">check_circle</span> Settings Saved Successfully!
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 space-y-6">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Business Name</label>
            <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? 'Scrummy Nummy') ?>" class="w-full border p-3 rounded-xl outline-none focus:border-primary transition bg-gray-50 focus:bg-white">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Brand Color</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="primary_color" value="<?= htmlspecialchars($settings['primary_color'] ?? '#ec6d13') ?>" class="h-10 w-14 rounded cursor-pointer border p-1 bg-white">
                    <span class="text-xs text-gray-400">Buttons & Highlights</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Header Color</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="header_bg" value="<?= htmlspecialchars($settings['header_bg'] ?? '#ffffff') ?>" class="h-10 w-14 rounded cursor-pointer border p-1 bg-white">
                    <span class="text-xs text-gray-400">Top Navigation Bar</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Background Color</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="body_bg" value="<?= htmlspecialchars($settings['body_bg'] ?? '#f9fafb') ?>" class="h-10 w-14 rounded cursor-pointer border p-1 bg-white">
                    <span class="text-xs text-gray-400">Main Page Background</span>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Logo</label>
            <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:bg-gray-50 transition relative">
                <input type="file" name="logo" accept="image/jpeg, image/png, image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                <div class="space-y-2">
                    <span class="material-symbols-outlined text-4xl text-gray-300">cloud_upload</span>
                    <p class="text-sm text-gray-500 font-medium">Click to Upload New Logo (JPG, PNG, WEBP - Max 2MB)</p>
                </div>
            </div>
            <?php if(!empty($settings['logo_url'])): ?>
                <div class="mt-4 p-3 bg-gray-50 rounded-lg inline-block border border-gray-100">
                    <p class="text-xs text-gray-400 mb-2 uppercase font-bold">Current Logo:</p>
                    <img src="../<?= htmlspecialchars($settings['logo_url']) ?>" class="h-12 object-contain">
                </div>
            <?php endif; ?>
        </div>

        <button type="submit" class="w-full bg-gray-900 text-white font-bold py-4 rounded-xl hover:bg-black transition shadow-lg">Save Changes</button>
    </form>
</div>

</main></div></body></html>