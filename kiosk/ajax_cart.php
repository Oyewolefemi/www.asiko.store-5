<?php
// 1. Silence all errors immediately to prevent breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL); // Keep logging on, but display off

// 2. Start output buffering to catch any stray whitespace/warnings
ob_start();

session_start();
header('Content-Type: application/json');

try {
    include 'config.php';
    include 'functions.php';

    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) {
        throw new Exception('Not logged in. Please log in to add items.');
    }

    // Get raw input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input.');
    }

    $product_id = intval($input['product_id'] ?? 0);
    $quantityChange = intval($input['quantity'] ?? 0);
    $options = $input['options'] ?? []; 
    
    // Ensure options is strictly an array (handles null input from JS)
    if (!is_array($options)) {
        $options = [];
    }

    if ($product_id <= 0) {
        throw new Exception('Invalid product ID.');
    }

    // --- Validate options ---
    $stmt_prod_opts = $pdo->prepare("SELECT options, stock_quantity, min_order_quantity FROM products WHERE id = ?");
    $stmt_prod_opts->execute([$product_id]);
    $product = $stmt_prod_opts->fetch();

    if (!$product) {
        throw new Exception('Product not found.');
    }

    $required_options = $product['options'] ? json_decode($product['options'], true) : [];
    $selected_options_json = null;

    if (!empty($required_options)) {
        if (empty($options) || count($options) != count($required_options)) {
            throw new Exception('Please select all product options.');
        }
        // Validate that selected options are valid
        foreach ($required_options as $key => $values) {
            if (!isset($options[$key]) || !in_array($options[$key], $values)) {
                throw new Exception("Invalid option selected for $key.");
            }
        }
        // Sort keys
        ksort($options);
        $selected_options_json = json_encode($options);
    }

    // --- Handle Quantity Logic ---
    
    $message = '';

    // Logic 1: Absolute Quantity (from product detail)
    if (isset($input['quantity']) && $input['quantity'] > 0 && !isset($input['quantityChange'])) {
        $quantity_to_add = intval($input['quantity']);

        if ($quantity_to_add < $product['min_order_quantity']) {
            throw new Exception('Minimum order quantity is ' . $product['min_order_quantity'] . '.');
        }
        
        // Check existing cart item
        $sql_find = "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
        $params_find = [$user_id, $product_id];

        if ($selected_options_json) {
            $sql_find .= " AND selected_options = ?";
            $params_find[] = $selected_options_json;
        } else {
            $sql_find .= " AND selected_options IS NULL";
        }

        $stmt = $pdo->prepare($sql_find);
        $stmt->execute($params_find);
        $existing_cart_item = $stmt->fetch();
        
        $current_cart_quantity = $existing_cart_item ? $existing_cart_item['quantity'] : 0;
        
        // Check stock
        if (($current_cart_quantity + $quantity_to_add) > $product['stock_quantity']) {
            throw new Exception('Insufficient stock. Only ' . $product['stock_quantity'] . ' available.');
        }

        if ($existing_cart_item) {
            $newQty = $existing_cart_item['quantity'] + $quantity_to_add;
            $stmtUpdate = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmtUpdate->execute([$newQty, $existing_cart_item['id']]);
            $message = 'Item quantity updated in cart.';
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, selected_options) VALUES (?, ?, ?, ?)");
            $stmtInsert->execute([$user_id, $product_id, $quantity_to_add, $selected_options_json]);
            $message = 'Item added to cart.';
        }

    // Logic 2: Relative Change (from cart buttons +/-)
    } else if (isset($input['quantityChange'])) {
        $quantityChange = intval($input['quantityChange']);
        
        $sql_find = "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?";
        $params_find = [$user_id, $product_id];
        
        if ($selected_options_json) {
            $sql_find .= " AND selected_options = ?";
            $params_find[] = $selected_options_json;
        } else {
            $sql_find .= " AND selected_options IS NULL";
        }

        $stmt = $pdo->prepare($sql_find);
        $stmt->execute($params_find);
        $existing_cart_item = $stmt->fetch();

        if (!$existing_cart_item) {
            throw new Exception('Item not found in cart.');
        }
        
        $newQty = $existing_cart_item['quantity'] + $quantityChange;

        if ($newQty > $product['stock_quantity']) {
            throw new Exception('Insufficient stock.');
        }
        
        if ($newQty <= 0) {
            $stmtDelete = $pdo->prepare("DELETE FROM cart WHERE id = ?");
            $stmtDelete->execute([$existing_cart_item['id']]);
            $message = 'Item removed from cart.';
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmtUpdate->execute([$newQty, $existing_cart_item['id']]);
            $message = 'Cart updated successfully.';
        }

    } else {
        throw new Exception('Invalid quantity.');
    }

    // Get new total cart count
    $stmt_count = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt_count->execute([$user_id]);
    $new_cart_count = $stmt_count->fetchColumn() ?: 0;
    $_SESSION['cart_count'] = $new_cart_count;

    // 3. Clear buffer before sending success response
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => $message, 'cart_count' => $new_cart_count]);

} catch (Exception $e) {
    // 4. Clear buffer before sending error response
    if (ob_get_length()) ob_end_clean();
    
    // Send valid JSON error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>