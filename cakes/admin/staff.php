<?php
// cakes/admin/staff.php
require_once '../config.php';
include '../includes/admin_header.php';

// SECURITY: KICK OUT REGULAR STAFF
if (($_SESSION['admin_role'] ?? 'staff') === 'staff') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$error = ''; $success = '';
$currentAdminId = $_SESSION['admin_id'] ?? 0;

// DB AUTO-REPAIR: Ensures role and created_at exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL) ENGINE=InnoDB;");
    $checkRole = $pdo->query("SHOW COLUMNS FROM admins LIKE 'role'");
    if (!$checkRole->fetch()) { $pdo->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(20) DEFAULT 'manager'"); }
    $checkDate = $pdo->query("SHOW COLUMNS FROM admins LIKE 'created_at'");
    if (!$checkDate->fetch()) { $pdo->exec("ALTER TABLE admins ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); }
} catch (PDOException $e) { $error = "System initialising..."; }

// HANDLE NEW STAFF CREATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'manager';

    if (strlen($username) < 3 || strlen($password) < 5) {
        $error = "Please enter a valid username and password.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $role]);
            $success = "Bakery account for <b>$username</b> is ready!";
        } catch (PDOException $e) { $error = "Username already exists."; }
    }
}

// HANDLE DELETION
if (isset($_GET['delete'])) {
    $targetId = (int)$_GET['delete'];
    if ($targetId != $currentAdminId) {
        $pdo->prepare("DELETE FROM admins WHERE id = ? AND role != 'superadmin'")->execute([$targetId]);
        $success = "Staff removed from bakery.";
    }
}

$staffList = $pdo->query("SELECT id, username, role, created_at FROM admins ORDER BY id ASC")->fetchAll();
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Bakery Team</h1>
            <p class="text-sm text-gray-500">Manage local access for cakes managers and staff.</p>
        </div>
    </div>

    <?php if($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-xl font-bold shadow-sm"><?= $success ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl font-bold shadow-sm"><?= $error ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-50 bg-rose-50/30">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">person_add</span>
                Add New Staff/Manager
            </h3>
        </div>
        <form method="POST" class="p-6 grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Username</label>
                <input type="text" name="username" required placeholder="e.g. baker_jane" class="w-full border border-gray-200 p-3 rounded-xl outline-none bg-gray-50 focus:ring-2 focus:ring-primary/20">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Temporary Password</label>
                <input type="password" name="password" required placeholder="Set password" class="w-full border border-gray-200 p-3 rounded-xl outline-none bg-gray-50 focus:ring-2 focus:ring-primary/20">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Permissions</label>
                <select name="role" class="w-full border border-gray-200 p-3 rounded-xl bg-gray-50 outline-none">
                    <option value="manager">Manager (Full Access)</option>
                    <option value="staff">Staff (Orders Only)</option>
                </select>
            </div>
            <button type="submit" name="add_staff" class="w-full bg-primary text-white font-bold py-3.5 rounded-xl shadow-lg transition transform active:scale-95">
                Create Account
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-50 bg-gray-50/50">
            <h3 class="font-bold text-gray-800">Current Team</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50/50 text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                    <tr><th class="p-5">Name</th><th class="p-5">Role</th><th class="p-5 text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach($staffList as $s): ?>
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="p-5 font-bold text-gray-900"><?= htmlspecialchars($s['username']) ?></td>
                        <td class="p-5">
                            <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider 
                                <?= $s['role'] == 'superadmin' ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-500' ?>">
                                <?= $s['role'] ?>
                            </span>
                        </td>
                        <td class="p-5 text-right">
                            <?php if($s['id'] != $currentAdminId && $s['role'] != 'superadmin'): ?>
                                <a href="?delete=<?= $s['id'] ?>" onclick="return confirm('Remove access for this user?')" class="text-red-400 hover:text-red-600 transition">
                                    <span class="material-symbols-outlined">delete_forever</span>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</main></div></body></html>