<?php
// kiosk/Red/admin_profile.php
include 'header.php';

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Handle Password Update
    if (!empty($_POST['new_password'])) {
        $new_pass = $_POST['new_password'];
        if ($new_pass !== $_POST['confirm_password']) {
            $error = "New passwords do not match.";
        } else {
            $hash = secureHash($new_pass);
            $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$hash, $_SESSION['admin_id']])) $success = "Password updated.";
        }
    }

    // 2. Handle Logo & Details
    if (isset($_POST['update_details'])) {
        $store_logo_path = null;
        if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
            $target_dir = __DIR__ . "/uploads/logos/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $filename = "vendor_" . $_SESSION['admin_id'] . "_" . time() . ".png";
            if (move_uploaded_file($_FILES['store_logo']['tmp_name'], $target_dir . $filename)) {
                $store_logo_path = "Red/uploads/logos/" . $filename;
                // Update database with the logo path
                $pdo->prepare("UPDATE admins SET store_logo = ? WHERE id = ?")->execute([$store_logo_path, $_SESSION['admin_id']]);
            }
        }

        // FIX: Added '?? ""' fallback to prevent undefined array key warnings
        $store_name = sanitize($_POST['store_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $bank_details = sanitize($_POST['bank_details'] ?? '');
        $business_description = sanitize($_POST['business_description'] ?? '');

        $stmt = $pdo->prepare("UPDATE admins SET store_name = ?, phone = ?, bank_details = ?, business_description = ? WHERE id = ?");
        if ($stmt->execute([$store_name, $phone, $bank_details, $business_description, $_SESSION['admin_id']])) {
            $success = "Business profile updated successfully.";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();
?>

<div class="max-w-4xl mx-auto mt-8">
    <h1 class="text-3xl font-bold mb-6">Vendor Settings</h1>
    <?php if ($success): ?><div class="bg-green-100 text-green-700 p-4 rounded mb-4 font-bold"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 text-red-700 p-4 rounded mb-4 font-bold"><?= $error ?></div><?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-100">
            <h2 class="text-xl font-bold mb-4 border-b pb-2">Business Profile</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_details" value="1">
                
                <div class="mb-4">
                    <label class="block font-bold mb-1 text-sm text-gray-700">Store Logo</label>
                    <div class="flex items-center gap-4 mb-3">
                        <img src="<?= get_logo_url($admin['store_logo'] ?? '') ?>" class="h-16 w-16 border rounded bg-gray-50 object-cover">
                        <input type="file" name="store_logo" class="text-sm">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block font-bold mb-1 text-sm text-gray-700">Store Name</label>
                    <input type="text" name="store_name" value="<?= htmlspecialchars($admin['store_name'] ?? '') ?>" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-1 text-sm text-gray-700">Phone Number</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. 08012345678">
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-1 text-sm text-gray-700">Bank Account Details</label>
                    <textarea name="bank_details" rows="2" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Bank Name, Account Number, Name"><?= htmlspecialchars($admin['bank_details'] ?? '') ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="block font-bold mb-1 text-sm text-gray-700">Business Description</label>
                    <textarea name="business_description" rows="3" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="What does your store specialize in?"><?= htmlspecialchars($admin['business_description'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded transition shadow-md mt-2">Save Details</button>
            </form>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-100 h-fit">
            <h2 class="text-xl font-bold mb-4 border-b pb-2">Login Credentials</h2>
            <div class="mb-4">
                <label class="block text-gray-600 text-sm font-bold mb-1">Username</label>
                <input type="text" value="<?= htmlspecialchars($admin['username']) ?>" disabled class="w-full border bg-gray-100 p-2 rounded cursor-not-allowed text-gray-500">
            </div>
            
            <form method="POST">
                <h3 class="text-sm font-bold mt-6 mb-2 text-gray-700">Change Password</h3>
                <div class="mb-3">
                    <input type="password" name="new_password" placeholder="New Password" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="mb-4">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <button type="submit" class="w-full bg-gray-800 text-white font-bold py-3 rounded hover:bg-gray-900 transition shadow-md mt-2">Update Password</button>
            </form>
        </div>

    </div>
</div>

<?php echo "</main></div></body></html>"; ?>