<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
$passwordUpdated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];
    
    if ($new_password !== $confirm_new_password) {
        printError("New passwords do not match.");
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            if ($user && verifyPassword($current_password, $user['password'])) {
                $new_hash = secureHash($new_password);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$new_hash, $user_id])) {
                    $passwordUpdated = true;
                } else {
                    printError("Error updating password.");
                }
            } else {
                printError("Current password is incorrect.");
            }
        } catch (Exception $e) {
            printError("Error updating password: " . $e->getMessage());
        }
    }
}
?>
<main class="container mx-auto py-10 px-4 md:px-8">
  <h1 class="text-3xl font-bold text-merry-primary mb-6">Security Settings</h1>
  
  <?php if ($passwordUpdated): ?>
    <div class="bg-green-200 border border-green-400 text-green-800 p-4 rounded mb-4">
      Password updated successfully.
    </div>
  <?php endif; ?>
  
  <form method="POST" class="max-w-md mx-auto border p-6 rounded-lg shadow">
    <input type="hidden" name="action" value="update_password">
    <div class="mb-4">
      <label class="block mb-1">Current Password:</label>
      <input type="password" name="current_password" class="w-full border p-2 rounded-lg" required>
    </div>
    <div class="mb-4">
      <label class="block mb-1">New Password:</label>
      <input type="password" name="new_password" class="w-full border p-2 rounded-lg" required>
    </div>
    <div class="mb-4">
      <label class="block mb-1">Confirm New Password:</label>
      <input type="password" name="confirm_new_password" class="w-full border p-2 rounded-lg" required>
    </div>
    <button type="submit" class="w-full bg-merry-primary text-merry-white py-2 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-merry-primary">
      Update Password
    </button>
  </form>
</main>
<?php include 'footer.php'; ?>