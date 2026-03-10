<?php
// kiosk/Red/customize.php
include 'header.php'; 
require_once __DIR__ . '/../EnvLoader.php';

if (($_SESSION['admin_role'] ?? '') !== 'superadmin') {
    echo "<script>window.location.href = 'admin_dashboard.php';</script>";
    exit;
}

$envFilePath = __DIR__ . '/../.env';
EnvLoader::load($envFilePath);

$error = ''; $success = '';

if (!is_writable($envFilePath)) {
    $error = "CRITICAL ERROR: Configuration file is not writable.";
} 

function updateEnvFile($filePath, $key, $value) {
    $value = str_replace('"', '\"', $value);
    $content = file_get_contents($filePath);
    if ($content === false) return false;
    $key = strtoupper($key);
    $pattern = "/^{$key}=.*/m";
    $replacement = "{$key}=\"{$value}\"";
    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, $replacement, $content);
    } else {
        if (substr($content, -1) !== "\n" && !empty($content)) $content .= "\n";
        $content .= $replacement . "\n";
    }
    return file_put_contents($filePath, $content) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_writable($envFilePath)) {
    try {
        $settings = ['STORE_NAME', 'THEME_COLOR', 'BACKGROUND_COLOR', 'TEXT_COLOR', 'FONT_FAMILY', 'FONT_SIZE', 'HERO_TITLE', 'HERO_SUBTITLE'];
        foreach($settings as $setting) {
            if(isset($_POST[strtolower($setting)])) {
                updateEnvFile($envFilePath, $setting, sanitize($_POST[strtolower($setting)]));
            }
        }

        // Handle Logo Upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_subdir = 'Red/uploads/logos/';
            $upload_dir_absolute = __DIR__ . '/uploads/logos/';
            if (!is_dir($upload_dir_absolute)) mkdir($upload_dir_absolute, 0755, true);
            
            $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $new_filename = 'logo_' . time() . '.' . $file_ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir_absolute . $new_filename)) {
                updateEnvFile($envFilePath, 'LOGO_PATH', $upload_subdir . $new_filename);
            }
        }
        $success = "Store settings updated!";
        EnvLoader::load($envFilePath);
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
}

$current_logo_db = EnvLoader::get('LOGO_PATH');
$admin_logo_preview = get_logo_url($current_logo_db); 
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">
    <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Store Customizer</h1>
        <?php if ($error) echo "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>$error</div>"; ?>
        <?php if ($success) echo "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>$success</div>"; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="p-4 border rounded-lg mb-6">
                <h3 class="font-bold mb-4">Branding</h3>
                <label class="block text-sm font-medium mb-2">Store Name</label>
                <input type="text" name="store_name" value="<?= htmlspecialchars(EnvLoader::get('STORE_NAME')) ?>" class="w-full border p-2 rounded mb-4">
                <label class="block text-sm font-medium mb-2">Store Logo</label>
                <input type="file" name="logo" id="logo-input" class="block w-full text-sm">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-md">Save & Publish</button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-gray-200 p-4 rounded-lg">
        <div id="preview-container" class="w-full h-full rounded shadow overflow-y-auto" style="background-color: <?= htmlspecialchars(EnvLoader::get('BACKGROUND_COLOR')) ?>;">
            <header class="p-4 flex justify-between items-center border-b">
                <img id="logo-preview" src="<?= htmlspecialchars($admin_logo_preview) ?>" class="h-10 object-contain">
                <span id="preview-store-name" class="font-bold"><?= htmlspecialchars(EnvLoader::get('STORE_NAME')) ?></span>
            </header>
            </div>
    </div>
</div>