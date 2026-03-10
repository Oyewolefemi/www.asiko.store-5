<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
try {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        printError("User not found.");
    }
} catch (Exception $e) {
    printError("Error fetching user data: " . $e->getMessage());
    $user = [];
}
?>
<main class="container mx-auto py-10 px-4 md:px-8">
  <h1 class="text-3xl font-bold text-merry-primary mb-6">Edit Profile</h1>
  <?php if ($user): ?>
    <form class="max-w-lg mx-auto border p-6 rounded-lg shadow">
      <div class="mb-4">
        <label for="profileName" class="block mb-1">Full Name:</label>
        <input type="text" id="profileName" class="w-full border p-2 rounded-lg" value="<?php echo sanitize($user['name']); ?>">
      </div>
      <div class="mb-4">
        <label for="profileEmail" class="block mb-1">Email:</label>
        <input type="email" id="profileEmail" class="w-full border p-2 rounded-lg" value="<?php echo sanitize($user['email']); ?>">
      </div>
      <!-- Additional fields if they exist -->
      <button type="submit" class="w-full bg-merry-primary text-merry-white py-2 rounded-full hover:opacity-90 transition-all">
        Save Changes
      </button>
    </form>
  <?php else: ?>
    <p>No user data found.</p>
  <?php endif; ?>
</main>
<?php include 'footer.php'; ?>