<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;

// Handle adding to wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_wishlist') {
    $product_id = intval($_POST['product_id']);
    try {
        $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $product_id]);
    } catch (Exception $e) {
        printError("Error adding wishlist item: " . $e->getMessage());
    }
}

// Handle deletion from wishlist
if (isset($_GET['delete'])) {
    $wishlist_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
        $stmt->execute([$wishlist_id, $user_id]);
    } catch (Exception $e) {
        printError("Error deleting wishlist item: " . $e->getMessage());
    }
}

// Retrieve wishlist items
try {
    $stmt = $pdo->prepare("SELECT wishlist.id, products.name, products.image_url, products.price FROM wishlist JOIN products ON wishlist.product_id = products.id WHERE wishlist.user_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_items = $stmt->fetchAll();
} catch (Exception $e) {
    printError("Error fetching wishlist: " . $e->getMessage());
    $wishlist_items = [];
}
?>
<main class="container mx-auto py-10 px-4 md:px-8">
  <h1 class="text-3xl font-bold text-merry-primary mb-6">Wishlist</h1>
  
  <form method="POST" class="max-w-md mx-auto border p-6 rounded-lg shadow mb-8">
    <input type="hidden" name="action" value="add_wishlist">
    <div class="mb-4">
      <label class="block mb-1">Product ID to Add:</label>
      <input type="number" name="product_id" class="w-full border p-2 rounded-lg" required>
    </div>
    <button type="submit" class="w-full bg-merry-primary text-merry-white py-2 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-merry-primary">
      Add to Wishlist
    </button>
  </form>

  <div class="space-y-4">
    <?php if (!empty($wishlist_items)): ?>
      <?php foreach ($wishlist_items as $item): ?>
        <div class="border p-4 rounded-lg shadow flex justify-between items-center">
          <div>
            <img src="<?php echo sanitize($item['image_url']); ?>" alt="<?php echo sanitize($item['name']); ?>" class="w-16 h-16 rounded mb-2">
            <p class="font-semibold"><?php echo sanitize($item['name']); ?></p>
            <p>$<?php echo number_format($item['price'], 2); ?></p>
          </div>
          <div>
            <a href="wishlist.php?delete=<?php echo $item['id']; ?>" class="bg-red-500 text-white py-1 px-3 rounded hover:bg-red-600 transition" onclick="return confirm('Are you sure?');">Delete</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No items in your wishlist.</p>
    <?php endif; ?>
  </div>
</main>
<?php include 'footer.php'; ?>