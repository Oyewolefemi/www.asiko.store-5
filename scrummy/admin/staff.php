<?php
require_once '../config.php';
include '../includes/admin_header.php';

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$currentAdminId = $_SESSION['admin_id'];

// --- 1. HANDLE ADD NEW STAFF ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    
    // CSRF Validation Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Security Check Failed: Invalid CSRF Token.");
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (strlen($username) < 3 || strlen($password) < 6) {
        $error = "Username must be at least 3 characters and password at least 6 characters.";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetch()) {
            $error = "That username is already taken by another staff member.";
        } else {
            // Hash password and insert
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
            
            if ($stmt->execute([$username, $hash])) {
                $success = "Staff account '{$username}' created successfully.";
            } else {
                $error = "Failed to create staff account. Please try again.";
            }
        }
    }
}

// --- 2. HANDLE DELETE STAFF ---
if (isset($_GET['delete']) && isset($_GET['csrf'])) {
    
    // CSRF Check for GET request
    if (!hash_equals($_SESSION['csrf_token'], $_GET['csrf'])) {
        die("Security Check Failed: Invalid CSRF Token.");
    }

    $targetId = (int)$_GET['delete'];

    // Prevent deleting oneself
    if ($targetId === $currentAdminId) {
        $error = "You cannot delete your own currently active account.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$targetId]);
        header("Location: staff.php?deleted=1");
        exit;
    }
}

if (isset($_GET['deleted'])) {
    $success = "Staff account permanently removed.";
}

// Fetch all admins
$staffList = $pdo->query("SELECT id, username, created_at FROM admins ORDER BY id ASC")->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Staff Management</h1>
    <p class="text-sm text-gray-500">Create accounts and manage access for your team.</p>
</div>

<?php if($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-600 p-4 rounded-xl mb-6 font-bold flex items-center gap-3 shadow-sm">
        <span class="material-symbols-outlined text-red-500">error</span> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-xl mb-6 font-bold flex items-center gap-3 shadow-sm">
        <span class="material-symbols-outlined text-green-500">check_circle</span> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <div class="lg:col-span-2 space-y-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <h3 class="font-bold text-gray-900">Active Team Members</h3>
                <span class="bg-gray-200 text-gray-600 text-xs font-bold px-3 py-1 rounded-full"><?= count($staffList) ?> Accounts</span>
            </div>
            
            <table class="w-full text-left border-collapse">
                <thead class="bg-white text-xs font-bold text-gray-400 uppercase border-b border-gray-100">
                    <tr>
                        <th class="p-4">User</th>
                        <th class="p-4">Date Added</th>
                        <th class="p-4 text-right">Access</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-sm">
                    <?php foreach($staffList as $staff): ?>
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold uppercase">
                                    <?= substr(htmlspecialchars($staff['username']), 0, 1) ?>
                                </div>
                                <div>
                                    <span class="font-bold text-gray-900 block"><?= htmlspecialchars($staff['username']) ?></span>
                                    <?php if($staff['id'] === $currentAdminId): ?>
                                        <span class="text-[10px] text-green-600 font-bold uppercase bg-green-50 px-2 py-0.5 rounded">You</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 text-gray-500">
                            <?= date('M j, Y', strtotime($staff['created_at'])) ?>
                        </td>
                        <td class="p-4 text-right">
                            <?php if($staff['id'] !== $currentAdminId): ?>
                                <a href="staff.php?delete=<?= $staff['id'] ?>&csrf=<?= $_SESSION['csrf_token'] ?>" 
                                   onclick="return confirm('Are you sure you want to revoke access for <?= htmlspecialchars($staff['username']) ?>? This cannot be undone.');" 
                                   class="inline-flex items-center justify-center size-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition" title="Revoke Access">
                                    <span class="material-symbols-outlined text-[18px]">delete_forever</span>
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-gray-300 font-bold uppercase mr-2">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 sticky top-24">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                <div class="size-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined">person_add</span>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900">Add New Staff</h3>
                    <p class="text-xs text-gray-400">Grant dashboard access</p>
                </div>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="add_staff">

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Username</label>
                    <input type="text" name="username" required minlength="3" placeholder="e.g., cashier_john" class="w-full border p-3 rounded-xl bg-gray-50 outline-none focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Temporary Password</label>
                    <input type="text" name="password" required minlength="6" placeholder="Assign a password" class="w-full border p-3 rounded-xl bg-gray-50 outline-none focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition font-mono">
                    <p class="text-[10px] text-gray-400 mt-1">Provide this password to the employee.</p>
                </div>

                <button type="submit" class="w-full bg-gray-900 text-white font-bold py-3.5 rounded-xl hover:bg-black transition shadow-lg shadow-gray-900/20 mt-2 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">how_to_reg</span> Create Account
                </button>
            </form>
        </div>
    </div>

</div>

</main></div></body></html>