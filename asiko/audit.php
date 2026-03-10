<?php
session_start();
require 'db.php';
require 'functions.php';
include 'header.php';

// --- Filtering and Fetching ---
$filterTable = $_GET['table'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterUser = $_GET['user'] ?? '';
$filterLimit = intval($_GET['limit'] ?? 100);

$where = ["1=1"];
$params = [];

if (!empty($filterTable)) {
    $where[] = "al.table_name = ?";
    $params[] = $filterTable;
}
if (!empty($filterAction)) {
    $where[] = "al.action = ?";
    $params[] = $filterAction;
}
if (!empty($filterUser)) {
    $where[] = "al.user_id = ?";
    $params[] = intval($filterUser);
}

$whereSql = implode(' AND ', $where);

// Fixed query - using correct column names from the database schema
$sql = "SELECT al.*, u.name as user_name 
        FROM audit_log al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE {$whereSql}
        ORDER BY al.created_at DESC 
        LIMIT {$filterLimit}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch unique tables and users for filters (for simplicity, only fetch from the last 500 records)
$filterData = $pdo->query("SELECT DISTINCT table_name FROM audit_log LIMIT 500")->fetchAll(PDO::FETCH_COLUMN);
$filterActions = $pdo->query("SELECT DISTINCT action FROM audit_log LIMIT 500")->fetchAll(PDO::FETCH_COLUMN);
$filterUsers = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

// Helper for status colors
function getActionBadge($action) {
    return match (strtolower($action)) {
        'create', 'insert', 'add' => 'bg-green-100 text-green-800',
        'update', 'edit', 'modify' => 'bg-blue-100 text-blue-800',
        'delete', 'remove' => 'bg-red-100 text-red-800',
        'login' => 'bg-purple-100 text-purple-800',
        'view' => 'bg-gray-100 text-gray-700',
        default => 'bg-yellow-100 text-yellow-800',
    };
}
?>

<div class="container mx-auto px-4 py-6">
    <h2 class="text-3xl font-bold text-gray-800 mb-6">System Audit Log</h2>
    <p class="text-gray-600 mb-4">Tracking all key user and system actions.</p>

    <!-- Filter Form -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <form method="get" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Filter by Table</label>
                <select name="table" class="border p-2 rounded text-sm w-40">
                    <option value="">All Tables</option>
                    <?php foreach ($filterData as $table): ?>
                        <option value="<?= htmlspecialchars($table) ?>" <?= $filterTable === $table ? 'selected' : '' ?>>
                            <?= htmlspecialchars($table) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Filter by Action</label>
                <select name="action" class="border p-2 rounded text-sm w-40">
                    <option value="">All Actions</option>
                    <?php foreach ($filterActions as $action): ?>
                        <option value="<?= htmlspecialchars($action) ?>" <?= $filterAction === $action ? 'selected' : '' ?>>
                            <?= ucfirst(htmlspecialchars($action)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Filter by User</label>
                <select name="user" class="border p-2 rounded text-sm w-40">
                    <option value="">All Users</option>
                    <?php foreach ($filterUsers as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= intval($filterUser) === intval($user['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['name'] ?: $user['id']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Apply Filters</button>
            <a href="audit.php" class="bg-gray-500 text-white px-4 py-2 rounded text-sm hover:bg-gray-600">Reset</a>
        </form>
    </div>

    <!-- Audit Table -->
    <div class="bg-white shadow rounded overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date/Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Record</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($logs as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?=date('M j, Y', strtotime($row['created_at']))?></div>
                            <div class="text-xs text-gray-500"><?=date('g:i:s A', strtotime($row['created_at']))?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?=htmlspecialchars($row['user_name'] ?? 'System')?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?= getActionBadge($row['action']) ?>">
                                <?=htmlspecialchars(ucfirst($row['action']))?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <div class="font-medium"><?=htmlspecialchars($row['table_name'] ?? '-')?></div>
                            <div class="text-xs">ID: <?=htmlspecialchars($row['record_id'] ?? '-')?></div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700 max-w-lg truncate">
                            <?=htmlspecialchars($row['description'] ?? '-')?>
                            <div class="text-xs text-gray-400 mt-1">IP: <?=htmlspecialchars($row['ip_address'] ?? '-')?></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($logs)): ?>
            <div class="text-center text-gray-500 p-8">
                <p>No audit log entries found for the current filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
