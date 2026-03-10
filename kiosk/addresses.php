<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    printError("You must be logged in to view addresses.");
    exit;
}

// Handle deletion if "delete" is provided in GET
if (isset($_GET['delete'])) {
    $addr_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
        $stmt->execute([$addr_id, $user_id]);
        header("Location: addresses.php");
        exit;
    } catch (Exception $e) {
        printError("Error deleting address: " . $e->getMessage());
    }
}

// Retrieve addresses
try {
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $addresses = $stmt->fetchAll();
} catch (Exception $e) {
    printError("Error fetching addresses: " . $e->getMessage());
    $addresses = [];
}
?>


<main class="container mx-auto py-10 px-4 md:px-8 pb-24">
  <h1 class="text-3xl font-bold text-luxury-black mb-6">My Addresses</h1>
  <?php if (!empty($addresses)): ?>
    <div class="space-y-4">
      <?php foreach ($addresses as $addr): ?>
        <div class="address-card border p-4 rounded-lg shadow-sm hover:shadow-lg transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div>
                    <p class="font-semibold text-lg text-luxury-black"><?php echo sanitize($addr['full_name']); ?></p>
                    <p class="text-luxury-gray"><?php echo sanitize($addr['address_line1']); ?></p>
                    <p class="text-luxury-gray"><?php echo sanitize($addr['city']); ?>, <?php echo sanitize($addr['state']); ?></p>
                    <p class="text-sm text-gray-400 mt-2">Added on: <?php echo date('F j, Y', strtotime($addr['created_at'])); ?></p>
                </div>
                <div>
                    <a href="addresses.php?delete=<?php echo $addr['id']; ?>" onclick="return confirm('Are you sure you want to delete this address?');" class="btn btn-danger text-sm">Delete</a>
                </div>
            </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-center py-10 border-dashed border-2 border-gray-300 rounded-lg">
      <p class="text-lg text-luxury-gray mb-4">You have no saved addresses.</p>
      <a href="checkout.php" class="btn">Add an Address</a>
    </div>
  <?php endif; ?>
</main>
<?php include 'footer.php'; ?>