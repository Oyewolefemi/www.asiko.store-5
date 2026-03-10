<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
$editMethod = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_payment') {
        $card_type = sanitize($_POST['card_type']);
        $card_last4 = sanitize($_POST['card_last4']);
        $expiry_date = $_POST['expiry_date'];
        $billing_address = sanitize($_POST['billing_address']);
        try {
            $stmt = $pdo->prepare("INSERT INTO payment_methods (user_id, card_type, card_last4, expiry_date, billing_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $card_type, $card_last4, $expiry_date, $billing_address]);
        } catch (Exception $e) {
            printError("Error adding payment method: " . $e->getMessage());
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_payment') {
        $method_id = $_POST['method_id'];
        $card_type = sanitize($_POST['card_type']);
        $card_last4 = sanitize($_POST['card_last4']);
        $expiry_date = $_POST['expiry_date'];
        $billing_address = sanitize($_POST['billing_address']);
        try {
            $stmt = $pdo->prepare("UPDATE payment_methods SET card_type = ?, card_last4 = ?, expiry_date = ?, billing_address = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$card_type, $card_last4, $expiry_date, $billing_address, $method_id, $user_id]);
        } catch (Exception $e) {
            printError("Error updating payment method: " . $e->getMessage());
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $method_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM payment_methods WHERE id = ? AND user_id = ?");
        $stmt->execute([$method_id, $user_id]);
    } catch (Exception $e) {
        printError("Error deleting payment method: " . $e->getMessage());
    }
}

// Handle edit action
if (isset($_GET['edit'])) {
    $method_id = intval($_GET['edit']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE id = ? AND user_id = ?");
        $stmt->execute([$method_id, $user_id]);
        $editMethod = $stmt->fetch();
    } catch (Exception $e) {
        printError("Error fetching payment method: " . $e->getMessage());
    }
}

// Retrieve all payment methods for current user
try {
    $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $methods = $stmt->fetchAll();
} catch (Exception $e) {
    printError("Error fetching payment methods: " . $e->getMessage());
    $methods = [];
}
?>

<main class="container mx-auto py-10 px-4 md:px-8">
  <h1 class="text-3xl font-bold text-merry-primary mb-6">Payment Methods</h1>
  
  <?php if ($editMethod): ?>
    <h2 class="text-2xl font-bold text-merry-primary mb-4">Edit Payment Method</h2>
    <form method="POST" class="max-w-lg mx-auto border p-6 rounded-lg shadow mb-8">
      <input type="hidden" name="action" value="update_payment">
      <input type="hidden" name="method_id" value="<?php echo $editMethod['id']; ?>">
      <div class="mb-4">
        <label class="block mb-1">Card Type:</label>
        <input type="text" name="card_type" class="w-full border p-2 rounded-lg" value="<?php echo sanitize($editMethod['card_type']); ?>" required>
      </div>
      <div class="mb-4">
        <label class="block mb-1">Card Last 4 Digits:</label>
        <input type="text" name="card_last4" class="w-full border p-2 rounded-lg" value="<?php echo sanitize($editMethod['card_last4']); ?>" required>
      </div>
      <div class="mb-4">
        <label class="block mb-1">Expiry Date:</label>
        <input type="date" name="expiry_date" class="w-full border p-2 rounded-lg" value="<?php echo $editMethod['expiry_date']; ?>" required>
      </div>
      <div class="mb-4">
        <label class="block mb-1">Billing Address:</label>
        <input type="text" name="billing_address" class="w-full border p-2 rounded-lg" value="<?php echo sanitize($editMethod['billing_address']); ?>" required>
      </div>
      <button type="submit" class="w-full bg-merry-primary text-merry-white py-2 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-merry-primary">
        Update Payment Method
      </button>
    </form>
  <?php endif; ?>

  <h2 class="text-2xl font-bold text-merry-primary mb-4">Add New Payment Method</h2>
  <form method="POST" class="max-w-lg mx-auto border p-6 rounded-lg shadow mb-8">
    <input type="hidden" name="action" value="add_payment">
    <div class="mb-4">
      <label class="block mb-1">Card Type:</label>
      <input type="text" name="card_type" class="w-full border p-2 rounded-lg" placeholder="e.g., Visa, MasterCard" required>
    </div>
    <div class="mb-4">
      <label class="block mb-1">Card Last 4 Digits:</label>
      <input type="text" name="card_last4" class="w-full border p-2 rounded-lg" placeholder="1234" required>
    </div>
    <div class="mb-4">
      <label class="block mb-1">Expiry Date:</label>
      <input type="date" name="expiry_date" class="w-full border p-2 rounded-lg" required>
    </div>
    <div class="mb-4">
      <label class="block mb-1">Billing Address:</label>
      <input type="text" name="billing_address" class="w-full border p-2 rounded-lg" required>
    </div>
    <button type="submit" class="w-full bg-merry-primary text-merry-white py-2 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-merry-primary">
      Add Payment Method
    </button>
  </form>

  <h2 class="text-2xl font-bold text-merry-primary mb-4">Your Payment Methods</h2>
  <div class="space-y-4">
    <?php if (!empty($methods)): ?>
      <?php foreach ($methods as $method): ?>
        <div class="border p-4 rounded-lg shadow flex justify-between items-center">
          <div>
            <p><?php echo sanitize($method['card_type']); ?> ending in <?php echo sanitize($method['card_last4']); ?></p>
            <p>Expires: <?php echo $method['expiry_date']; ?></p>
            <p><?php echo sanitize($method['billing_address']); ?></p>
          </div>
          <div class="flex space-x-2">
            <a href="payment-methods.php?edit=<?php echo $method['id']; ?>" class="bg-blue-500 text-white py-1 px-3 rounded hover:bg-blue-600 transition">Edit</a>
            <a href="payment-methods.php?delete=<?php echo $method['id']; ?>" class="bg-red-500 text-white py-1 px-3 rounded hover:bg-red-600 transition" onclick="return confirm('Are you sure you want to delete this payment method?');">Delete</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No payment methods found.</p>
    <?php endif; ?>
  </div>
</main>
<?php include 'footer.php'; ?>