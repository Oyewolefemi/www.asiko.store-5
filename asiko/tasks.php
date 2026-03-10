<?php
session_start();
require 'db.php';
require 'functions.php';
include 'header.php';

$success = '';
$error = '';
$currentUserId = $_SESSION['user_id'] ?? 1; // Fallback for testing

// Fetch all users for task assignment dropdown
$users = $pdo->query("SELECT id, name, email FROM users WHERE is_active = 1 ORDER BY name")->fetchAll();

// --- CRUD Operations ---

// 1. Add Task
if (isset($_POST['add_task'])) {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description'] ?? '');
    $assigned_to = intval($_POST['assigned_to_user_id']);
    $due_date = $_POST['due_date'];
    $priority = $_POST['priority'];
    
    if (empty($title)) {
        $error = "Task title is required.";
    } elseif ($assigned_to === 0) {
        $error = "Please assign the task to a user.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tasks (title, description, assigned_to_user_id, due_date, priority)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $description, $assigned_to, $due_date, $priority]);
            $success = "Task added successfully!";
        } catch (Exception $e) {
            $error = "Error adding task: " . $e->getMessage();
        }
    }
}

// 2. Update Task Status
if (isset($_POST['update_status'])) {
    $taskId = intval($_POST['task_id']);
    $newStatus = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $taskId]);
        $success = "Task status updated!";
    } catch (Exception $e) {
        $error = "Error updating task: " . $e->getMessage();
    }
}

// 3. Delete Task
if (isset($_GET['delete'])) {
    $taskId = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $success = "Task deleted.";
    } catch (Exception $e) {
        $error = "Error deleting task: " . $e->getMessage();
    }
    // Redirect to clean the URL
    header('Location: tasks.php');
    exit;
}

// --- Filtering and Fetching ---
$filterStatus = $_GET['status'] ?? 'pending';
$filterPriority = $_GET['priority'] ?? '';
$filterAssigned = $_GET['assigned_to'] ?? '';

$where = ["1=1"];
$params = [];

if ($filterStatus != 'all') {
    $where[] = "status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterPriority)) {
    $where[] = "priority = ?";
    $params[] = $filterPriority;
}
if (!empty($filterAssigned)) {
    $where[] = "assigned_to_user_id = ?";
    $params[] = intval($filterAssigned);
}

$whereSql = implode(' AND ', $where);

// Fetch Tasks
$sql = "
    SELECT t.*, u.name as assigned_to_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to_user_id = u.id
    WHERE {$whereSql}
    ORDER BY FIELD(t.priority, 'high', 'medium', 'low'), t.created_at DESC
    LIMIT 50
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Get total count for status tabs
$statusCounts = $pdo->query("SELECT status, COUNT(*) as count FROM tasks GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// Helper for priority colors
function getPriorityBadge($priority) {
    return match ($priority) {
        'high' => 'bg-red-500 text-white',
        'medium' => 'bg-yellow-500 text-gray-900',
        'low' => 'bg-green-500 text-white',
        default => 'bg-gray-500 text-white',
    };
}
?>

<div class="container mx-auto px-4 py-6">
    <h2 class="text-3xl font-bold mb-6 flex items-center gap-2">
        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2m-9 0V3h5v2M9 7h6m0 8l-2 2-4-4"/>
        </svg>
        Team Task Management
    </h2>

    <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <?php if ($success): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?=htmlspecialchars($success)?></div><?php endif; ?>
    
    <!-- Task Submission Form -->
    <div class="bg-white p-6 rounded-lg shadow mb-6">
        <h3 class="text-xl font-semibold mb-4">Create New Task / Work Order</h3>
        <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Title *</label>
                <input name="title" required placeholder="e.g., Restock Product X, Review P.O. 123" class="w-full border border-gray-300 p-2 rounded">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">Assigned To *</label>
                <select name="assigned_to_user_id" required class="w-full border border-gray-300 p-2 rounded">
                    <option value="0">Unassigned</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?=$user['id']?>" <?= $user['id'] == $currentUserId ? 'selected' : '' ?>>
                            <?=htmlspecialchars($user['name'] ?: $user['email'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">Priority</label>
                <select name="priority" class="w-full border border-gray-300 p-2 rounded">
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Description (Optional)</label>
                <textarea name="description" rows="1" placeholder="Details or context for the task" class="w-full border border-gray-300 p-2 rounded"></textarea>
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1">Due Date</label>
                <input name="due_date" type="date" class="w-full border border-gray-300 p-2 rounded">
            </div>
            
            <div class="md:col-span-1">
                <button name="add_task" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 w-full">
                    Add Task
                </button>
            </div>
        </form>
    </div>

    <!-- Task Filter and Tabs -->
    <div class="flex flex-wrap gap-2 mb-4">
        <?php 
        $statuses = ['pending', 'in_progress', 'completed', 'cancelled', 'all'];
        foreach ($statuses as $status): 
            $count = $statusCounts[$status] ?? 0;
            $badgeClass = match ($status) {
                'pending' => 'bg-yellow-500 text-gray-900',
                'in_progress' => 'bg-blue-500 text-white',
                'completed' => 'bg-green-500 text-white',
                'cancelled' => 'bg-gray-400 text-white',
                default => 'bg-gray-200 text-gray-900',
            };
        ?>
            <a href="?status=<?=$status?>&priority=<?=$filterPriority?>&assigned_to=<?=$filterAssigned?>" 
               class="px-4 py-2 rounded-full text-sm font-medium transition 
               <?= $filterStatus == $status ? $badgeClass . ' ring-2 ring-offset-2 ring-opacity-50 ring-blue-500' : $badgeClass ?> hover:opacity-80">
                <?= ucfirst(str_replace('_', ' ', $status)) ?> (<?=$count?>)
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50">
            <h3 class="text-xl font-semibold">Tasks (Filtered by: <?= ucfirst(str_replace('_', ' ', $filterStatus)) ?>)</h3>
        </div>
        
        <?php if (empty($tasks)): ?>
            <div class="p-8 text-center text-gray-500">
                <div class="text-6xl mb-4">🧘</div>
                <p>No tasks found matching the current filters.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($tasks as $task): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?= getPriorityBadge($task['priority']) ?>">
                                    <?= ucfirst($task['priority']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($task['title']) ?></div>
                                <div class="text-xs text-gray-500 max-w-xs truncate"><?= htmlspecialchars($task['description']) ?: '-' ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-900">
                                <?= htmlspecialchars($task['assigned_to_name'] ?: 'System') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="<?= (strtotime($task['due_date']) < time() && $task['status'] != 'completed') ? 'text-red-600 font-semibold' : 'text-gray-900' ?>">
                                    <?= $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : '-' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?= match ($task['status']) {
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                    'completed' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-gray-100 text-gray-800',
                                    default => 'bg-gray-100 text-gray-800',
                                } ?>">
                                    <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <form method="post" class="inline-flex gap-2">
                                    <input type="hidden" name="task_id" value="<?=$task['id']?>">
                                    <?php if ($task['status'] != 'completed'): ?>
                                        <button name="update_status" value="completed" class="text-green-600 hover:text-green-900" title="Mark as Completed">
                                            &#10003; Complete
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($task['status'] != 'in_progress' && $task['status'] != 'completed'): ?>
                                        <button name="update_status" value="in_progress" class="text-blue-600 hover:text-blue-900" title="Mark In Progress">
                                            Start
                                        </button>
                                    <?php endif; ?>
                                    <a href="?delete=<?=$task['id']?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this task?')" title="Delete Task">
                                        Delete
                                    </a>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
