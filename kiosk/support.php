<?php
// support.php

include 'header.php';     // This loads your site’s main header & navigation
include 'config.php';
include 'functions.php';

// If you have any POST or GET actions, handle them here.
$user_id = $_SESSION['user_id'] ?? 0;

// Example: Insert a support ticket if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    try {
        $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $subject, $message]);
    } catch (Exception $e) {
        printError("Error creating support ticket: " . $e->getMessage());
    }
}

// Retrieve support tickets
try {
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll();
} catch (Exception $e) {
    printError("Error fetching support tickets: " . $e->getMessage());
    $tickets = [];
}
?>

<main class="container mx-auto py-10 px-4 md:px-8">
  <h1 class="text-3xl font-bold text-merry-primary mb-6">Support</h1>
  <p>If you need help, please contact our customer service or open a support ticket.</p>

  <h2 class="text-2xl font-bold text-merry-primary mb-4 mt-8">Create a New Ticket</h2>
  <form method="POST" class="max-w-md mx-auto border p-6 rounded-lg shadow mb-8">
    <input type="hidden" name="action" value="create_ticket">
    <div class="mb-4">
      <label class="block mb-1">Subject:</label>
      <input type="text" name="subject" class="w-full border p-2 rounded-lg" required>
    </div>
    <div class="mb-4">
      <label class="block mb-1">Message:</label>
      <textarea name="message" class="w-full border p-2 rounded-lg" rows="4" required></textarea>
    </div>
    <button type="submit" class="w-full bg-merry-primary text-merry-white py-2 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-merry-primary">
      Submit Ticket
    </button>
  </form>
  
  <h2 class="text-2xl font-bold text-merry-primary mb-4">Your Tickets</h2>
  <div class="space-y-4">
    <?php if (!empty($tickets)): ?>
      <?php foreach ($tickets as $ticket): ?>
        <div class="border p-4 rounded-lg shadow hover:shadow-xl transition-shadow duration-200">
          <p class="font-semibold"><?php echo sanitize($ticket['subject']); ?></p>
          <p class="text-sm text-gray-600"><?php echo $ticket['created_at']; ?></p>
          <p class="mt-2"><?php echo sanitize($ticket['message']); ?></p>
          <p class="mt-2">Status: <?php echo sanitize($ticket['status']); ?></p>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No support tickets found.</p>
    <?php endif; ?>
  </div>
</main>

<?php include 'footer.php'; // Single footer include ?>