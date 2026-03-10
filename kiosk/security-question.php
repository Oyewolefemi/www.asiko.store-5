<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php';
include 'functions.php';

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: index.php");
    exit;
}

$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

try {
    $stmt = $pdo->prepare("SELECT security_question FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['pending_user_id']]);
    $userData = $stmt->fetch();

    if (!$userData || empty($userData['security_question'])) {
        session_destroy();
        include 'header.php';
        // FIX #14: Replaced the specific error with a generic one
        printError("Invalid user ID or incorrect security answer.");
        include 'footer.php';
        exit;
    }
    $securityQuestion = $userData['security_question'];

} catch (Exception $e) {
    include 'header.php';
    printError("Database error.");
    include 'footer.php';
    exit;
}

$csrf_token = generateCsrfToken();

include 'header.php';
?>

<main class="container mx-auto py-12 px-4 md:px-8">
  <div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-center text-gray-900 mb-4">Security Check</h2>
    <p class="mb-6 text-gray-600 text-center">Please answer your security question to continue.</p>
    
    <div class="bg-gray-50 p-4 rounded-md mb-6 border border-gray-200">
        <p class="font-semibold text-lg text-center text-gray-800"><?= htmlspecialchars(html_entity_decode($securityQuestion, ENT_QUOTES, 'UTF-8')); ?></p>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 text-sm font-medium">
            Invalid user ID or incorrect security answer.
        </div>
    <?php endif; ?>

    <form action="security-question-process.php" method="POST" class="space-y-6">
      <input type="hidden" name="csrf_token" value="<?= $csrf_token; ?>">
      
      <div>
        <label for="securityAnswer" class="block mb-2 text-sm font-medium text-gray-700">Your Answer</label>
        <input type="text" id="securityAnswer" name="security_answer" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Type your answer here..." required>
      </div>
      
      <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white" style="background-color: var(--primary-cyan, #06b6d4);">
          Verify Answer
      </button>
    </form>
  </div>
</main>

<?php include 'footer.php'; ?>