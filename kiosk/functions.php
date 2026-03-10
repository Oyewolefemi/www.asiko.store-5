<?php
// /kiosk/functions.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!function_exists('sanitize')) {
    function sanitize($data) {
        // FIX: Removed htmlspecialchars to prevent converting quotes to &#039; in the database.
        // We only strip tags and trim to maintain raw strings like DML CAKES 'N' MORE.
        return strip_tags(trim($data ?? ''));
    }
}

/**
 * Universal helper to resolve logo and image paths accurately.
 * Prepends '../' if accessed from a subfolder like /Red/ and the path is relative to root.
 */
if (!function_exists('get_logo_url')) {
    function get_logo_url($path) {
        if (empty($path)) return 'https://placehold.co/100x100/f8f9fa/ccc?text=No+Image';
        
        // If it's already an absolute URL, return it
        if (strpos($path, 'http') === 0) return $path;

        // Determine if we are inside a subfolder (like /Red/)
        $current_dir = basename(getcwd());
        $is_subfolder = ($current_dir === 'Red' || $current_dir === 'admin');

        // If the path starts with 'Red/' but we are ALREADY in the Red folder, remove the prefix
        if ($is_subfolder && strpos($path, 'Red/') === 0) {
            return substr($path, 4);
        }

        // If we are in a subfolder and the path is relative to the kiosk root, go up one level
        if ($is_subfolder && strpos($path, 'Red/') !== 0) {
            return '../' . ltrim($path, '/');
        }

        return ltrim($path, '/');
    }
}

if (!function_exists('printError')) {
    function printError($message) {
        echo "<div class='container mx-auto my-4'><div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded' role='alert'><strong>Error:</strong> " . htmlspecialchars($message) . "</div></div>";
    }
}

if (!function_exists('printSuccess')) {
    function printSuccess($message) {
        echo "<div class='container mx-auto my-4'><div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded' role='alert'>" . htmlspecialchars($message) . "</div></div>";
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '₦' . number_format($amount, 2);
    }
}

if (!function_exists('getApplicablePrice')) {
    function getApplicablePrice($item, $bulk_discounts_array) {
        $base_price = (!empty($item['sale_price']) && $item['sale_price'] > 0) ? (float)$item['sale_price'] : (float)$item['price'];
        $discount_percent = 0;
        if (isset($bulk_discounts_array[$item['product_id']])) {
            foreach ($bulk_discounts_array[$item['product_id']] as $tier) {
                if ($item['quantity'] >= $tier['min_quantity']) {
                    $discount_percent = (float)$tier['discount_percentage'];
                    break; 
                }
            }
        }
        $discount_amount = ($base_price * $discount_percent) / 100;
        return $base_price - $discount_amount;
    }
}

if (!function_exists('generateLuhnOrderNumber')) {
    function generateLuhnOrderNumber($length = 8) {
        $body = '';
        for ($i = 0; $i < $length - 1; $i++) {
            $body .= mt_rand(0, 9);
        }

        $sum = 0;
        $alt = true;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $n = intval($body[$i]);
            if ($alt) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
            $alt = !$alt; 
        }

        $checkDigit = (10 - ($sum % 10)) % 10;
        return $body . $checkDigit;
    }
}

if (!function_exists('getProductImage')) {
    function getProductImage($imagePath) {
        if (empty($imagePath)) return 'https://placehold.co/100x100/f8f9fa/ccc?text=No+Image';
        $fixedPath = str_replace('/kios/', '/kiosk/', $imagePath);
        if (strpos($fixedPath, 'kiosk/') === 0) return '/' . $fixedPath; 
        elseif (strpos($fixedPath, '/kiosk/') === 0) return $fixedPath;
        return '/kiosk/Red/uploads/' . ltrim($fixedPath, '/'); 
    }
}

if (!function_exists('secureHash')) {
    function secureHash($password) { return password_hash($password, PASSWORD_DEFAULT); }
}

if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) { return password_verify($password, $hash); }
}

if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}
    
if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token) {
        if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            unset($_SESSION['csrf_token']); 
            return true;
        }
        return false;
    }
}

if (!function_exists('logActivity')) {
    function logActivity($admin_id, $action, $details = '') {
        global $pdo; 
        if (!$pdo) return;
        try {
            $stmt = $pdo->prepare("INSERT INTO admin_activities (admin_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$admin_id, $action, $details]);
        } catch (Exception $e) {}
    }
}

if (!function_exists('darken_color')) {
    function darken_color($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $factor = 1 - ($percent / 100);
        $r = max(0, min(255, round($r * $factor)));
        $g = max(0, min(255, round($g * $factor)));
        $b = max(0, min(255, round($b * $factor)));
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}
?>