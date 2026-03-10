<?php
include 'header.php';
include 'config.php';
include 'functions.php';

// Retrieve the user's name dynamically
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        printError("Error fetching user data: " . $e->getMessage());
        $user = null;
    }
}
?>

<main class="container mx-auto py-10 px-4 md:px-8 flex flex-col md:flex-row">
  <aside class="md:w-1/4 mb-6 md:mb-0">
    <nav class="bg-gray-100 p-4 rounded-lg shadow">
      <ul>
        <li class="mb-2"><a href="profile.php" class="block p-2 rounded hover:bg-merry-accent hover:text-merry-white">Profile</a></li>
        <li class="mb-2"><a href="order-history.php" class="block p-2 rounded hover:bg-merry-accent hover:text-merry-white">Order History</a></li>
        <li class="mb-2"><a href="addresses.php" class="block p-2 rounded hover:bg-merry-accent hover:text-merry-white">Addresses</a></li>
         <li class="mb-2"><a href="track.php" class="block p-2 rounded hover:bg-merry-accent hover:text-merry-white">Track</a></li>
	 <li class="mb-2"><a href="security.php" class="block p-2 rounded hover:bg-merry-accent hover:text-merry-white">Security</a></li>

        </ul>
    </nav>
  </aside>
  <section class="md:w-3/4 md:pl-8">
    <?php if ($user): ?>
      <h1 class="text-4xl font-bold text-merry-primary mb-6">Welcome, <?php echo sanitize($user['name']); ?>!</h1>
    <?php else: ?>
      <h1 class="text-4xl font-bold text-merry-primary mb-6">Welcome to Your Dashboard!</h1>
    <?php endif; ?>
    <p>Use the navigation on the left to manage your profile, view orders, and update account settings.</p>
  </section>
</main>
<?php include 'footer.php'; ?>