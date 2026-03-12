<?php
// kiosk/Red/admin_profile.php
include 'header.php';

$admin_id = $_SESSION['admin_id'];
$is_super = ($_SESSION['admin_role'] ?? '') === 'superadmin';
$msg = '';
$error = '';

// --- Handle Form Submission (Only for Vendors) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_super) {
    $store_name = trim($_POST['store_name']);
    $store_slug = trim($_POST['store_slug']);
    $store_slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($store_slug));
    
    $phone = trim($_POST['phone']);
    $bank_details = trim($_POST['bank_details']);
    $business_description = trim($_POST['business_description']);
    
    $logo_query_part = "";
    $params = [
        ':store_name' => $store_name,
        ':store_slug' => $store_slug,
        ':phone' => $phone,
        ':bank_details' => $bank_details,
        ':business_description' => $business_description,
        ':id' => $admin_id
    ];

    if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/logos/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        
        $file_ext = strtolower(pathinfo($_FILES['store_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_filename = 'store_' . uniqid() . '_' . time() . '.' . $file_ext;
            
            if (move_uploaded_file($_FILES['store_logo']['tmp_name'], $upload_dir . $new_filename)) {
                $logo_query_part = ", store_logo = :store_logo";
                $params[':store_logo'] = 'Red/uploads/logos/' . $new_filename;
            } else {
                $error = "Failed to upload logo image.";
            }
        } else {
            $error = "Invalid file type. Only JPG and PNG allowed.";
        }
    }

    if (empty($error)) {
        try {
            $sql = "UPDATE admins SET 
                    store_name = :store_name,
                    store_slug = :store_slug,
                    phone = :phone,
                    bank_details = :bank_details,
                    business_description = :business_description
                    $logo_query_part
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['store_name'] = $store_name; 
            $msg = "Store profile updated successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { 
                $error = "The Store URL (Slug) '{$store_slug}' is already taken by another vendor.";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// --- Fetch Current Profile Data (Only for Vendors) ---
$vendor = [];
if (!$is_super) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Store Profile</h1>
        <p class="text-gray-500">Manage your brand, payout details, and public storefront appearance.</p>
    </div>

    <?php if ($msg): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-bold">
            <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm font-bold">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($is_super): ?>
        <div class="bg-blue-50 border border-blue-200 text-blue-800 p-8 rounded-xl text-center shadow-sm">
            <span class="material-symbols-outlined text-4xl mb-3 text-blue-500">admin_panel_settings</span>
            <h2 class="text-xl font-bold mb-2">Master HQ Admin</h2>
            <p>You are logged in via the Central Vault. This Store Profile page is specifically designed for <strong>Third-Party Vendors</strong> to set up their individual storefronts.</p>
            <p class="mt-4">To manage global mall settings, please use the <a href="customize.php" class="font-bold underline">Site Appearance</a> menu.</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <form method="POST" enctype="multipart/form-data" class="p-8">
                
                <div class="flex items-center gap-6 mb-8 pb-8 border-b border-gray-100">
                    <div class="w-24 h-24 rounded-full border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden shrink-0">
                        <?php if (!empty($vendor['store_logo']) && $vendor['store_logo'] !== 'default_logo.png'): ?>
                            <img src="../<?php echo htmlspecialchars(str_replace('Red/', '', $vendor['store_logo']), ENT_QUOTES, 'UTF-8'); ?>" alt="Store Logo" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-gray-400 text-xs text-center px-2">No Logo</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Store Logo</label>
                        <input type="file" name="store_logo" accept="image/*" class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-400 mt-2">Recommended: 300x300px (JPG or PNG).</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Display Name (Store Name)</label>
                        <input type="text" name="store_name" value="<?php echo htmlspecialchars($vendor['store_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Store URL (Slug)</label>
                        <div class="flex items-center">
                            <span class="bg-gray-100 border border-r-0 border-gray-300 px-3 py-3 rounded-l-lg text-gray-500 text-sm">asiko.store/mall/</span>
                            <input type="text" name="store_slug" value="<?php echo htmlspecialchars($vendor['store_slug'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required placeholder="my-awesome-store" class="w-full px-4 py-3 rounded-r-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Business Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($vendor['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Bank Details (For Payouts)</label>
                        <input type="text" name="bank_details" value="<?php echo htmlspecialchars($vendor['bank_details'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Bank Name - Acct Name - Acct Number" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>

                <div class="mb-8">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Business Description</label>
                    <textarea name="business_description" rows="4" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Tell customers what you sell..."><?php echo htmlspecialchars($vendor['business_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="flex justify-end pt-6 border-t border-gray-100">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg transition">
                        Save Store Profile
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php echo "</main></div></body></html>"; ?>