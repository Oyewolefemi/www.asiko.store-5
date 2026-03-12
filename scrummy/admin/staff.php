<?php
require_once '../config.php';
include '../includes/admin_header.php';

// --- SECURITY: ONLY ALLOW ADMINS/MANAGERS ---
if (($_SESSION['admin_role'] ?? 'staff') === 'staff') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$error = ''; $success = '';
$currentAdminId = $_SESSION['admin_id'] ?? 0;

// Database Repair logic (Ensures columns role & created_at exist)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL) ENGINE=InnoDB;");
    $checkRole = $pdo->query("SHOW COLUMNS FROM admins LIKE 'role'");
    if (!$checkRole->fetch()) { $pdo->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(20) DEFAULT 'manager'"); }
    $checkDate = $pdo->query("SHOW COLUMNS FROM admins LIKE 'created_at'");
    if (!$checkDate->fetch()) { $pdo->exec("ALTER TABLE admins ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); }
} catch (PDOException $e) { $error = "DB Init Error."; }

// Handle Account Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'manager';

    if (strlen($username) < 3 || strlen($password) < 5) {
        $error = "Fill all fields correctly.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $role]);
            $success = "Account for <b>$username</b> created!";
        } catch (PDOException $e) { $error = "Username taken."; }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $targetId = (int)$_GET['delete'];
    if ($targetId != $currentAdminId) {
        $pdo->prepare("DELETE FROM admins WHERE id = ? AND role != 'superadmin'")->execute([$targetId]);
        $success = "Removed.";
    }
}

$staffList = $pdo->query("SELECT id, username, role, created_at FROM admins ORDER BY id ASC")->fetchAll();
?>

<div class="space-y-6">
    <div>
        <h1 class="text-3xl font-black text-gray-900 tracking-tight">Staff & Managers</h1>
        <p class="text-sm text-gray-500">Only Admins/Managers can access this management screen.</p>
    </div>

    <?php if($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-xl font-bold shadow-sm"><?= $success ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-50 bg-gray-50/50">
            <h3 class="font-bold text-gray-800">Add New User</h3>
        </div>
        <form method="POST" class="p-6 grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
            <input type="hidden" name="action" value="add_staff">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Username</label>
                <input type="text" name="username" required class="w-full border border-gray-200 p-3 rounded-xl outline-none bg-gray-50 focus:ring-2 focus:ring-primary/20">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Password</label>
                <input type="text" name="password" required class="w-full border border-gray-200 p-3 rounded-xl outline-none bg-gray-50 focus:ring-2 focus:ring-primary/20">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Role</label>
                <select name="role" class="w-full border border-gray-200 p-3 rounded-xl bg-gray-50 outline-none">
                    <option value="manager">Manager (Full Access)</option>
                    <option value="staff">Staff (Limited Access)</option>
                </select>
            </div>
            <button type="submit" class="w-full bg-primary text-white font-bold py-3.5 rounded-xl shadow-lg transition transform active:scale-95">
                Create Account
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-50 bg-gray-50/50">
            <h3 class="font-bold text-gray-800">Team Directory</h3>
        </div>
        <table class="w-full text-left">
            <thead class="bg-gray-50/50 text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                <tr><th class="p-5">Name</th><th class="p-5">Role</th><th class="p-5 text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach($staffList as $s): ?>
                <tr class="hover:bg-gray-50/50 transition">
                    <td class="p-5 font-bold text-gray-900"><?= htmlspecialchars($s['username']) ?></td>
                    <td class="p-5"><span class="px-2 py-1 bg-gray-100 rounded text-[10px] font-black uppercase"><?= $s['role'] ?></span></td>
                    <td class="p-5 text-right">
                        <?php if($s['id'] != $currentAdminId): ?>
                            <a href="?delete=<?= $s['id'] ?>" class="text-red-400 hover:text-red-600"><span class="material-symbols-outlined">delete_forever</span></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</main></div></body></html>