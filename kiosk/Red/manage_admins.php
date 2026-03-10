<?php
// kiosk/Red/manage_admins.php
include 'header.php'; 

// Only Super Admin can access this
if (($_SESSION['admin_role'] ?? '') !== 'superadmin') {
    echo "<div class='p-6'><div class='bg-red-100 text-red-700 p-4 rounded'>Access Denied. Super Admin only.</div></div>";
    echo "</main></div></body></html>";
    exit();
}

$success = '';
$error = '';
$csrf_token = generateCsrfToken();

// Helper to handle logo upload consistently
function uploadStoreLogo($file) {
    $target_dir = __DIR__ . "/uploads/logos/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
    
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    
    if (!in_array($ext, $allowed)) throw new Exception("Invalid logo format. Only JPG, PNG, WEBP allowed.");
    
    $filename = "store_" . uniqid() . "_" . time() . "." . $ext;
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return "Red/uploads/logos/" . $filename; 
    }
    throw new Exception("Failed to upload logo.");
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Session expired. Please refresh.";
    } else {
        // --- 1. TOGGLE ERP ACCESS ---
        if (isset($_POST['toggle_erp_id'])) {
            $erp_id = intval($_POST['toggle_erp_id']);
            $current_status = intval($_POST['current_status']);
            $new_status = $current_status ? 0 : 1; 

            if ($erp_id === $_SESSION['admin_id']) {
                $error = "You cannot revoke your own access.";
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET erp_access = ? WHERE id = ?");
                $stmt->execute([$new_status, $erp_id]);
                $success = "Access permissions updated successfully.";
            }
        }
        // --- 2. DEEP DELETE ADMIN ---
        elseif (isset($_POST['delete_id'])) {
            $del_id = intval($_POST['delete_id']);
            if ($del_id === $_SESSION['admin_id']) {
                $error = "You cannot delete yourself!";
            } else {
                try {
                    $uStmt = $pdo->prepare("SELECT user_id FROM admins WHERE id = ?");
                    $uStmt->execute([$del_id]);
                    $uData = $uStmt->fetch();

                    $pdo->prepare("DELETE FROM products WHERE admin_id = ?")->execute([$del_id]);
                    $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$del_id]);

                    if ($uData && !empty($uData['user_id'])) {
                        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uData['user_id']]);
                    }
                    $success = "Vendor fully deleted. Email and username are now available.";
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
        // --- 3. CREATE ADMIN (VENDOR) ---
        elseif (isset($_POST['create_admin'])) {
            $new_user  = sanitize($_POST['new_username']);
            $new_email = sanitize($_POST['new_email']);
            $new_pass  = $_POST['new_password'];
            
            $store_name = sanitize($_POST['store_name']);
            $store_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $store_name), '-')); 
            
            if (empty($store_slug)) {
                $store_slug = 'shop-' . substr(md5(uniqid()), 0, 6);
            }

            $store_logo = null;

            if (empty($new_user) || empty($new_email) || empty($new_pass) || empty($store_name)) {
                $error = "Username, Email, Password, and Store Name are required.";
            } else {
                $checkAdmin = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
                $checkAdmin->execute([$new_user]);
                $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $checkUser->execute([$new_email]);

                if ($checkAdmin->rowCount() > 0) {
                    $error = "The username '{$new_user}' is currently in use.";
                } elseif ($checkUser->rowCount() > 0) {
                    $error = "The email '{$new_email}' is already in use.";
                } else {
                    try {
                        $original_slug = $store_slug;
                        $counter = 1;
                        while (true) {
                            $chkSlug = $pdo->prepare("SELECT id FROM admins WHERE store_slug = ?");
                            $chkSlug->execute([$store_slug]);
                            if ($chkSlug->rowCount() == 0) break; 
                            
                            $store_slug = $original_slug . '-' . $counter;
                            $counter++;
                        }

                        if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] == 0) {
                            $store_logo = uploadStoreLogo($_FILES['store_logo']);
                        }

                        $pdo->beginTransaction();
                        $hash = secureHash($new_pass);
                        
                        $stmtUser = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
                        $stmtUser->execute([$new_user, $new_email, $hash]);
                        $user_id = $pdo->lastInsertId();

                        $stmtAdmin = $pdo->prepare("INSERT INTO admins (user_id, username, password_hash, role, erp_access, store_name, store_slug, store_logo, created_at) VALUES (?, ?, ?, 'admin', 0, ?, ?, ?, NOW())");
                        $stmtAdmin->execute([$user_id, $new_user, $hash, $store_name, $store_slug, $store_logo]);

                        $pdo->commit();
                        $success = "Vendor Account created! <strong>$store_name</strong> is now active.";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "System Error: " . $e->getMessage();
                    }
                }
            }
        }
        // --- 4. UPDATE VENDOR ---
        elseif (isset($_POST['update_vendor'])) {
            $edit_id = intval($_POST['edit_id']);
            $store_name = sanitize($_POST['edit_store_name']);
            $store_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['edit_store_slug']), '-'));
            $phone = sanitize($_POST['edit_phone']);
            $bank_details = sanitize($_POST['edit_bank_details']);
            $business_description = sanitize($_POST['edit_business_description']);
            
            if (empty($store_slug)) {
                $store_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $store_name), '-'));
            }
            if (empty($store_slug)) {
                $store_slug = 'shop-' . substr(md5(uniqid()), 0, 6);
            }

            try {
                $original_slug = $store_slug;
                $counter = 1;
                while (true) {
                    $chk = $pdo->prepare("SELECT id FROM admins WHERE store_slug = ? AND id != ?");
                    $chk->execute([$store_slug, $edit_id]);
                    if ($chk->rowCount() == 0) break; 
                    
                    $store_slug = $original_slug . '-' . $counter;
                    $counter++;
                }

                $sql_append = "";
                $params = [$store_name, $store_slug, $phone, $bank_details, $business_description];
                
                if (isset($_FILES['edit_store_logo']) && $_FILES['edit_store_logo']['error'] == 0) {
                    $new_logo = uploadStoreLogo($_FILES['edit_store_logo']);
                    $sql_append .= ", store_logo = ?";
                    $params[] = $new_logo;
                }

                if (!empty($_POST['edit_password'])) {
                    $new_hash = secureHash($_POST['edit_password']);
                    $sql_append .= ", password_hash = ?";
                    $params[] = $new_hash;
                }
                
                $params[] = $edit_id;

                $stmt = $pdo->prepare("UPDATE admins SET store_name = ?, store_slug = ?, phone = ?, bank_details = ?, business_description = ? $sql_append WHERE id = ?");
                $stmt->execute($params);
                $success = "Store details updated successfully.";
            } catch (Exception $e) {
                $error = "Update failed: " . $e->getMessage();
            }
        }
    }
    $csrf_token = generateCsrfToken();
}

$admins = $pdo->query("SELECT * FROM admins ORDER BY role DESC, created_at DESC")->fetchAll();
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Manage Vendors & Staff</h1>
    <a href="superadmin_dashboard.php" class="text-blue-600 hover:underline font-bold text-sm">← Back to Dashboard</a>
</div>

<?php if ($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 font-bold"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 font-bold"><?= $success ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <div class="bg-white p-6 rounded-lg shadow-md h-fit border border-gray-100">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-blue-600">person_add</span> Add New Vendor
        </h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="create_admin" value="1">
            
            <h3 class="text-xs font-bold text-gray-400 uppercase mb-2">Login Details</h3>
            <label class="block text-xs font-bold text-gray-700 mb-1">Username</label>
            <input type="text" name="new_username" class="w-full border p-2 rounded mb-2 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. nike_store" required>

            <label class="block text-xs font-bold text-gray-700 mb-1">Email Address</label>
            <input type="email" name="new_email" class="w-full border p-2 rounded mb-2 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. contact@nike.com" required>

            <label class="block text-xs font-bold text-gray-700 mb-1">Password</label>
            <input type="password" name="new_password" class="w-full border p-2 rounded mb-4 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Assign a password" required>

            <h3 class="text-xs font-bold text-gray-400 uppercase mb-2 pt-4 border-t">Store Details</h3>
            <label class="block text-xs font-bold text-gray-700 mb-1">Store Name (Quotes & Symbols Allowed)</label>
            <input type="text" name="store_name" class="w-full border p-2 rounded mb-2 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. DML CAKES 'N' MORE" required>
            
            <label class="block text-xs font-bold text-gray-700 mb-1">Store Logo</label>
            <input type="file" name="store_logo" class="w-full border p-2 rounded mb-4 text-sm" accept="image/*">

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded transition shadow">
                Create Vendor
            </button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-lg shadow-md overflow-hidden border border-gray-100">
        <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
            <h3 class="font-bold text-gray-700">Existing Accounts</h3>
            <span class="text-xs font-bold bg-gray-200 text-gray-600 px-2 py-1 rounded"><?= count($admins) ?> Total</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-white text-xs text-gray-400 uppercase tracking-wider border-b">
                    <tr>
                        <th class="px-6 py-3 font-bold">Store / User</th>
                        <th class="px-6 py-3 font-bold">Role</th>
                        <th class="px-6 py-3 font-bold text-center">Status</th>
                        <th class="px-6 py-3 font-bold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php foreach ($admins as $a): 
                        // FIX: Decode any existing corrupted database names so they display perfectly on screen
                        $clean_store_name = html_entity_decode($a['store_name'] ?? '', ENT_QUOTES, 'UTF-8');
                        $clean_store_slug = html_entity_decode($a['store_slug'] ?? '', ENT_QUOTES, 'UTF-8');
                        $clean_phone = html_entity_decode($a['phone'] ?? '', ENT_QUOTES, 'UTF-8');
                        $clean_bank = html_entity_decode($a['bank_details'] ?? '', ENT_QUOTES, 'UTF-8');
                        $clean_desc = html_entity_decode($a['business_description'] ?? '', ENT_QUOTES, 'UTF-8');
                        $clean_username = html_entity_decode($a['username'] ?? '', ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="hover:bg-blue-50 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <?php 
                                    $display_logo = !empty($a['store_logo']) ? get_logo_url($a['store_logo']) : 'https://placehold.co/100x100/f8f9fa/ccc?text=No+Logo';
                                ?>
                                <img src="<?= htmlspecialchars($display_logo) ?>" class="w-10 h-10 rounded-lg mr-3 object-cover border bg-white shadow-sm">
                                <div>
                                    <div class="font-bold text-gray-800 text-base">
                                        <?= htmlspecialchars($clean_store_name ?: $clean_username) ?>
                                    </div>
                                    <div class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($clean_username) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($a['role'] === 'superadmin'): ?>
                                <span class="bg-purple-100 border border-purple-200 text-purple-700 px-2 py-1 rounded text-[10px] font-bold tracking-wider">SUPER ADMIN</span>
                            <?php else: ?>
                                <span class="bg-blue-100 border border-blue-200 text-blue-700 px-2 py-1 rounded text-[10px] font-bold tracking-wider">VENDOR</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="px-6 py-4 text-center">
                            <?php if ($a['role'] === 'superadmin'): ?>
                                <span class="text-green-600 font-bold text-xs flex items-center justify-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> ACTIVE</span>
                            <?php else: ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="toggle_erp_id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= $a['erp_access'] ?>">
                                    
                                    <?php if ($a['erp_access'] == 1): ?>
                                        <button type="submit" class="text-green-600 font-bold text-xs hover:underline flex items-center justify-center gap-1 mx-auto">
                                            <span class="w-2 h-2 rounded-full bg-green-500"></span> Active
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="text-gray-400 font-bold text-xs hover:underline flex items-center justify-center gap-1 mx-auto">
                                            <span class="w-2 h-2 rounded-full bg-red-500"></span> Suspended
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>

                        <td class="px-6 py-4 text-right flex justify-end gap-2">
                            <?php if ($a['id'] !== $_SESSION['admin_id']): ?>
                                <button type="button" onclick='openEditModal(
                                    <?= json_encode($a['id']) ?>, 
                                    <?= htmlspecialchars(json_encode($clean_store_name), ENT_QUOTES, 'UTF-8') ?>, 
                                    <?= htmlspecialchars(json_encode($clean_store_slug), ENT_QUOTES, 'UTF-8') ?>,
                                    <?= htmlspecialchars(json_encode($clean_phone), ENT_QUOTES, 'UTF-8') ?>,
                                    <?= htmlspecialchars(json_encode($clean_bank), ENT_QUOTES, 'UTF-8') ?>,
                                    <?= htmlspecialchars(json_encode($clean_desc), ENT_QUOTES, 'UTF-8') ?>
                                )' class="text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded font-bold text-xs transition">Edit</button>

                                <form method="POST" onsubmit="return confirm('Are you sure? This will delete the vendor and their products forever.');" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="delete_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded font-bold text-xs transition">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="text-gray-300 text-xs font-bold italic">Current User</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="editModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh]">
        <div class="p-6 border-b flex justify-between items-center bg-gray-50 rounded-t-xl">
            <h2 class="text-xl font-bold text-gray-800">Edit Vendor Profile</h2>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 font-bold text-xl">&times;</button>
        </div>
        
        <div class="p-6 overflow-y-auto">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="update_vendor" value="1">
                <input type="hidden" name="edit_id" id="modal_edit_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold text-blue-600 uppercase border-b pb-1">Branding & Basics</h3>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Store Name</label>
                            <input type="text" name="edit_store_name" id="modal_store_name" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Store URL Slug</label>
                            <input type="text" name="edit_store_slug" id="modal_store_slug" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm" placeholder="e.g. my-store">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Update Logo (Optional)</label>
                            <input type="file" name="edit_store_logo" class="w-full border p-1.5 rounded bg-gray-50 text-sm" accept="image/*">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Force Password Reset (Optional)</label>
                            <input type="password" name="edit_password" class="w-full border p-2 rounded bg-red-50 text-red-700 outline-none focus:ring-2 focus:ring-red-500" placeholder="Leave blank to keep current">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-xs font-bold text-blue-600 uppercase border-b pb-1">Business Details</h3>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Phone Number</label>
                            <input type="text" name="edit_phone" id="modal_phone" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500" placeholder="080...">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Bank Account Details</label>
                            <textarea name="edit_bank_details" id="modal_bank" rows="2" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Bank, Account Number, Name"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Business Description</label>
                            <textarea name="edit_business_description" id="modal_desc" rows="3" class="w-full border p-2 rounded bg-gray-50 focus:bg-white outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="What does this vendor sell?"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 pt-4 border-t flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="bg-white border border-gray-300 text-gray-700 px-6 py-2.5 rounded-lg font-bold hover:bg-gray-50 shadow-sm transition">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-bold hover:bg-blue-700 shadow-md transition">Save Vendor Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditModal(id, name, slug, phone, bank, desc) {
    document.getElementById('modal_edit_id').value = id;
    document.getElementById('modal_store_name').value = name;
    document.getElementById('modal_store_slug').value = slug;
    document.getElementById('modal_phone').value = phone;
    document.getElementById('modal_bank').value = bank;
    document.getElementById('modal_desc').value = desc;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>

<?php echo "</main></div></body></html>"; ?>