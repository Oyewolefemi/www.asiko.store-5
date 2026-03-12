<?php
// kiosk/Red/customize.php
include 'header.php'; 
require_once __DIR__ . '/../EnvLoader.php';

if (($_SESSION['admin_role'] ?? '') !== 'superadmin') {
    echo "<script>window.location.href = 'admin_dashboard.php';</script>";
    exit;
}

$envFilePath = __DIR__ . '/../.env';

// --- AUTO-FIX PERMISSIONS ---
if (!file_exists($envFilePath)) {
    @file_put_contents($envFilePath, "");
}
if (!is_writable($envFilePath)) {
    @chmod($envFilePath, 0666);
}

EnvLoader::load($envFilePath);

$error = ''; $success = '';

// --- COLOR PARSER FOR OPACITY ---
function parseColorStr($str, $defaultHex = '#ffffff') {
    $str = trim((string)$str);
    if (preg_match('/rgba\((\d+),\s*(\d+),\s*(\d+),\s*([0-9.]+)\)/', $str, $matches)) {
        $hex = sprintf("#%02x%02x%02x", $matches[1], $matches[2], $matches[3]);
        return [$hex, round($matches[4] * 100)];
    }
    if (preg_match('/^#[0-9a-fA-F]{8}$/', $str)) {
         $hex = substr($str, 0, 7);
         $alpha = hexdec(substr($str, 7, 2)) / 255;
         return [$hex, round($alpha * 100)];
    }
    if (preg_match('/^#[0-9a-fA-F]{3,6}$/', $str)) {
         return [$str, 100];
    }
    return [$defaultHex, 100];
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
        $success = "Store settings updated successfully!";
        EnvLoader::load($envFilePath); 
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
}

if (!is_writable($envFilePath)) {
    $error = "CRITICAL ERROR: Your kiosk/.env file is locked. Please set its permissions to 666 or 777 to allow saving.";
} 

$current_logo_db = EnvLoader::get('LOGO_PATH');
$admin_logo_preview = get_logo_url((string)$current_logo_db); 

// Extract colors and opacities for the UI
list($themeHex, $themeOp) = parseColorStr(EnvLoader::get('THEME_COLOR'), '#3b82f6');
list($bgHex, $bgOp) = parseColorStr(EnvLoader::get('BACKGROUND_COLOR'), '#ffffff');
list($textHex, $textOp) = parseColorStr(EnvLoader::get('TEXT_COLOR'), '#1a1a1a');
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">
    <div class="lg:col-span-1 bg-white p-6 rounded-lg shadow-lg">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Store Customizer</h1>
        <?php if ($error) echo "<div class='bg-red-100 text-red-700 p-3 rounded mb-4 font-bold text-sm'>" . htmlspecialchars($error) . "</div>"; ?>
        <?php if ($success) echo "<div class='bg-green-100 text-green-700 p-3 rounded mb-4 font-bold text-sm'>" . htmlspecialchars($success) . "</div>"; ?>

        <form method="POST" enctype="multipart/form-data">
            
            <div class="p-4 border border-gray-200 rounded-lg mb-6 bg-gray-50">
                <h3 class="font-bold mb-4 text-gray-800">1. Branding</h3>
                <label class="block text-sm font-medium text-gray-700 mb-2">Store Name</label>
                <input type="text" name="store_name" id="input_store_name" value="<?php echo htmlspecialchars((string)EnvLoader::get('STORE_NAME'), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 p-2 rounded mb-4 outline-none focus:ring-2 focus:ring-blue-500">
                
                <label class="block text-sm font-medium text-gray-700 mb-2">Store Logo</label>
                <input type="file" name="logo" id="logo-input" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </div>

            <div class="p-4 border border-gray-200 rounded-lg mb-6 bg-gray-50">
                <h3 class="font-bold mb-4 text-gray-800">2. Colors & Opacity</h3>
                <div class="space-y-4">
                    
                    <div class="bg-white p-3 rounded border border-gray-200">
                        <label class="block text-xs font-bold text-gray-600 mb-2">Theme Color</label>
                        <div class="flex flex-col gap-2">
                            <input type="color" id="theme_hex" value="<?php echo $themeHex; ?>" class="w-full h-8 cursor-pointer border-0 p-0 rounded">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-bold uppercase">Opac</span>
                                <input type="range" min="0" max="100" id="theme_op" value="<?php echo $themeOp; ?>" class="flex-1 h-1 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                <span class="text-[10px] text-gray-500 w-8 text-right font-mono" id="theme_op_val"><?php echo $themeOp; ?>%</span>
                            </div>
                        </div>
                        <input type="hidden" name="theme_color" id="theme_final" value="<?php echo htmlspecialchars((string)EnvLoader::get('THEME_COLOR') ?: '#3b82f6', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="bg-white p-3 rounded border border-gray-200">
                        <label class="block text-xs font-bold text-gray-600 mb-2">Background Color</label>
                        <div class="flex flex-col gap-2">
                            <input type="color" id="bg_hex" value="<?php echo $bgHex; ?>" class="w-full h-8 cursor-pointer border-0 p-0 rounded">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-bold uppercase">Opac</span>
                                <input type="range" min="0" max="100" id="bg_op" value="<?php echo $bgOp; ?>" class="flex-1 h-1 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                <span class="text-[10px] text-gray-500 w-8 text-right font-mono" id="bg_op_val"><?php echo $bgOp; ?>%</span>
                            </div>
                        </div>
                        <input type="hidden" name="background_color" id="bg_final" value="<?php echo htmlspecialchars((string)EnvLoader::get('BACKGROUND_COLOR') ?: '#ffffff', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="bg-white p-3 rounded border border-gray-200">
                        <label class="block text-xs font-bold text-gray-600 mb-2">Text Color</label>
                        <div class="flex flex-col gap-2">
                            <input type="color" id="text_hex" value="<?php echo $textHex; ?>" class="w-full h-8 cursor-pointer border-0 p-0 rounded">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400 font-bold uppercase">Opac</span>
                                <input type="range" min="0" max="100" id="text_op" value="<?php echo $textOp; ?>" class="flex-1 h-1 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                <span class="text-[10px] text-gray-500 w-8 text-right font-mono" id="text_op_val"><?php echo $textOp; ?>%</span>
                            </div>
                        </div>
                        <input type="hidden" name="text_color" id="text_final" value="<?php echo htmlspecialchars((string)EnvLoader::get('TEXT_COLOR') ?: '#1a1a1a', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                </div>
            </div>

            <div class="p-4 border border-gray-200 rounded-lg mb-6 bg-gray-50">
                <h3 class="font-bold mb-4 text-gray-800">3. Typography</h3>
                <label class="block text-sm font-medium text-gray-700 mb-2">Font Family</label>
                <input type="text" name="font_family" placeholder="Arial, sans-serif" value="<?php echo htmlspecialchars((string)(EnvLoader::get('FONT_FAMILY') ?: 'sans-serif'), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 p-2 rounded mb-4 outline-none focus:ring-2 focus:ring-blue-500">
                
                <label class="block text-sm font-medium text-gray-700 mb-2">Base Font Size</label>
                <input type="text" name="font_size" placeholder="16px" value="<?php echo htmlspecialchars((string)(EnvLoader::get('FONT_SIZE') ?: '16px'), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 p-2 rounded outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="p-4 border border-gray-200 rounded-lg mb-6 bg-gray-50">
                <h3 class="font-bold mb-4 text-gray-800">4. Hero Banner</h3>
                <label class="block text-sm font-medium text-gray-700 mb-2">Hero Title</label>
                <input type="text" name="hero_title" id="input_hero_title" value="<?php echo htmlspecialchars((string)EnvLoader::get('HERO_TITLE'), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 p-2 rounded mb-4 outline-none focus:ring-2 focus:ring-blue-500">
                
                <label class="block text-sm font-medium text-gray-700 mb-2">Hero Subtitle</label>
                <textarea name="hero_subtitle" id="input_hero_subtitle" rows="2" class="w-full border border-gray-300 p-2 rounded outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars((string)EnvLoader::get('HERO_SUBTITLE'), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-lg shadow-md transition transform hover:-translate-y-0.5" <?php echo !is_writable($envFilePath) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>
                Save & Publish Settings
            </button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-gray-200 p-6 rounded-xl relative">
        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4">Live Preview</h3>
        
        <div id="preview-container" class="w-full min-h-[500px] rounded-lg shadow-xl overflow-hidden border border-gray-300 transition-colors duration-300" style="background-color: <?php echo htmlspecialchars((string)(EnvLoader::get('BACKGROUND_COLOR') ?: '#ffffff'), ENT_QUOTES, 'UTF-8'); ?>;">
            
            <header id="preview-header" class="p-4 flex justify-between items-center border-b border-opacity-20 shadow-sm transition-colors duration-300" style="background-color: <?php echo htmlspecialchars((string)(EnvLoader::get('THEME_COLOR') ?: '#ffffff'), ENT_QUOTES, 'UTF-8'); ?>;">
                <img id="logo-preview" src="<?php echo htmlspecialchars((string)$admin_logo_preview, ENT_QUOTES, 'UTF-8'); ?>" class="h-10 object-contain bg-white bg-opacity-80 rounded p-1">
                <span id="preview-store-name" class="font-bold transition-colors duration-300" style="color: <?php echo htmlspecialchars((string)(EnvLoader::get('TEXT_COLOR') ?: '#1a1a1a'), ENT_QUOTES, 'UTF-8'); ?>; font-family: <?php echo htmlspecialchars((string)(EnvLoader::get('FONT_FAMILY') ?: 'sans-serif'), ENT_QUOTES, 'UTF-8'); ?>; font-size: <?php echo htmlspecialchars((string)(EnvLoader::get('FONT_SIZE') ?: '16px'), ENT_QUOTES, 'UTF-8'); ?>;">
                    <?php echo htmlspecialchars((string)(EnvLoader::get('STORE_NAME') ?: 'Asiko Mall'), ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </header>

            <div id="preview-body" class="p-12 text-center transition-colors duration-300" style="font-family: <?php echo htmlspecialchars((string)(EnvLoader::get('FONT_FAMILY') ?: 'sans-serif'), ENT_QUOTES, 'UTF-8'); ?>; color: <?php echo htmlspecialchars((string)(EnvLoader::get('TEXT_COLOR') ?: '#1a1a1a'), ENT_QUOTES, 'UTF-8'); ?>;">
                <h2 id="preview-hero-title" class="text-4xl font-bold mb-4 tracking-tight"><?php echo htmlspecialchars((string)(EnvLoader::get('HERO_TITLE') ?: 'Welcome to our store'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <p id="preview-hero-subtitle" class="text-lg opacity-80 max-w-2xl mx-auto"><?php echo htmlspecialchars((string)(EnvLoader::get('HERO_SUBTITLE') ?: 'Discover amazing products today.'), ENT_QUOTES, 'UTF-8'); ?></p>
                
                <div class="mt-8">
                    <button id="preview-btn" class="px-8 py-3 rounded-full font-bold shadow-md transition-colors duration-300" style="background-color: <?php echo htmlspecialchars((string)(EnvLoader::get('THEME_COLOR') ?: '#3b82f6'), ENT_QUOTES, 'UTF-8'); ?>; color: #ffffff;">
                        Shop Now
                    </button>
                </div>
            </div>

        </div>
        
        <div class="absolute inset-0 pointer-events-none" style="background: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0,0,0,0.02) 10px, rgba(0,0,0,0.02) 20px);"></div>
    </div>
</div>

<script>
    // --- LIVE PREVIEW SCRIPT ---
    
    // 1. Text Inputs Updater
    document.getElementById('input_store_name').addEventListener('input', function() { document.getElementById('preview-store-name').innerText = this.value; });
    document.getElementById('input_hero_title').addEventListener('input', function() { document.getElementById('preview-hero-title').innerText = this.value; });
    document.getElementById('input_hero_subtitle').addEventListener('input', function() { document.getElementById('preview-hero-subtitle').innerText = this.value; });

    // 2. RGBA Color Engine
    function bindColorPicker(prefix) {
        const hexEl = document.getElementById(prefix + '_hex');
        const opEl = document.getElementById(prefix + '_op');
        const valEl = document.getElementById(prefix + '_op_val');
        const finalEl = document.getElementById(prefix + '_final');

        function updateColor() {
            // Update percentage text
            valEl.innerText = opEl.value + '%';
            
            // Convert Hex to RGBA
            let hex = hexEl.value.replace('#', '');
            let r = parseInt(hex.substring(0,2), 16);
            let g = parseInt(hex.substring(2,4), 16);
            let b = parseInt(hex.substring(4,6), 16);
            let a = opEl.value / 100;
            let rgbaStr = `rgba(${r}, ${g}, ${b}, ${a})`;
            
            // Save to hidden input
            finalEl.value = rgbaStr;
            
            // Apply Live Preview
            if(prefix === 'bg') {
                document.getElementById('preview-container').style.backgroundColor = rgbaStr;
            } else if(prefix === 'theme') {
                document.getElementById('preview-header').style.backgroundColor = rgbaStr;
                document.getElementById('preview-btn').style.backgroundColor = rgbaStr;
            } else if(prefix === 'text') {
                document.getElementById('preview-body').style.color = rgbaStr;
                document.getElementById('preview-store-name').style.color = rgbaStr;
            }
        }

        hexEl.addEventListener('input', updateColor);
        opEl.addEventListener('input', updateColor);
    }
    
    ['theme', 'bg', 'text'].forEach(bindColorPicker);
</script>

<?php echo "</main></div></body></html>"; ?>