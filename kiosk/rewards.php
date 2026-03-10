<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    header("Location: auth.php");
    exit;
}

$balance = 0;
$transactions = [];

try {
    // Get current points balance
    $stmt = $pdo->prepare("SELECT points FROM loyalty_points WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $balance = $stmt->fetchColumn() ?: 0;
    
    // Get transaction history
    $stmt_trans = $pdo->prepare("SELECT * FROM loyalty_transactions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt_trans->execute([$user_id]);
    $transactions = $stmt_trans->fetchAll();

} catch (Exception $e) {
    printError("Error fetching rewards data: " . $e->getMessage());
}
?>

<style>
.points-display {
    background: linear-gradient(135deg, var(--primary-cyan) 0%, var(--primary-cyan-hover) 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 2rem;
}
.points-label {
    font-size: 1rem;
    font-weight: 500;
    opacity: 0.8;
}
.points-balance {
    font-size: 3rem;
    font-weight: 700;
}
.transaction-list .transaction-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid var(--luxury-border);
}
.transaction-list .transaction-item:last-child {
    border-bottom: none;
}
.points-positive {
    color: #16a34a; /* green-600 */
    font-weight: 600;
}
.points-negative {
    color: #dc2626; /* red-600 */
    font-weight: 600;
}
</style>

<main class="container mx-auto py-10 px-4 md:px-8">
  <h1 class="text-3xl font-bold text-luxury-black mb-6">Rewards & Loyalty</h1>
  
  <div class="points-display">
    <div class="points-label">Your Points Balance</div>
    <div class="points-balance"><?= number_format($balance) ?></div>
  </div>

  <div class="bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-xl font-bold text-luxury-black mb-4">Transaction History</h2>
    <div class="transaction-list">
        <?php if (empty($transactions)): ?>
            <p class="text-center text-luxury-gray py-4">You have no transaction history yet.</p>
        <?php else: ?>
            <?php foreach ($transactions as $tx): ?>
                <div class="transaction-item">
                    <div>
                        <p class="font-semibold"><?= htmlspecialchars($tx['reason']) ?></p>
                        <p class="text-sm text-gray-500"><?= date('F j, Y, g:i a', strtotime($tx['created_at'])) ?></p>
                    </div>
                    <div class="<?= $tx['points_change'] > 0 ? 'points-positive' : 'points-negative' ?>">
                        <?= ($tx['points_change'] > 0 ? '+' : '') . number_format($tx['points_change']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
  </div>
</main>
<?php include 'footer.php'; ?>