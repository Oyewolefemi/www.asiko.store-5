<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
try {
    $stmt = $pdo->prepare("SELECT balance FROM wallet WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch();
    $balance = $wallet ? $wallet['balance'] : 0.00;
} catch (Exception $e) {
    printError("Error fetching wallet balance: " . $e->getMessage());
    $balance = 0.00;
}
?>
<main class="container mx-auto py-10 px-4 md:px-8">
  <h1 class="text-3xl font-bold text-merry-primary mb-6">My Wallet</h1>
  <div class="bg-gray-100 p-6 rounded-lg shadow">
    <p class="text-xl font-semibold mb-2">Current Balance: $<?php echo number_format($balance, 2); ?></p>
    <div class="flex flex-col md:flex-row md:space-x-4">
      <button class="mb-4 md:mb-0 bg-merry-primary text-merry-white py-2 px-4 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-merry-primary">
        Add Funds
      </button>
      <button class="bg-merry-primary text-merry-white py-2 px-4 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-merry-primary">
        Withdraw
      </button>
    </div>
  </div>
</main>
<?php include 'footer.php'; ?>