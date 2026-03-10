<?php
session_start();

require 'db.php';

// Helper function to output CSV
function outputCSV($filename, $header, $rows) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

// If export requested, process and export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_type'])) {
    $type = $_POST['export_type'];
    $data = [];

    if ($type === 'inventory' || $type === 'all') {
        $stmt = $pdo->query("SELECT p.id, p.name, c.name as category, s.name as supplier, 
                            p.quantity_in_stock as quantity, p.unit, p.selling_price as price 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            LEFT JOIN suppliers s ON p.supplier_id = s.id 
                            WHERE p.is_active = 1");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($type !== 'all') {
            outputCSV('inventory.csv', ['ID', 'Name', 'Category', 'Supplier', 'Quantity', 'Unit', 'Price'], $rows);
        }
        $data['inventory'] = $rows;
    }

    if ($type === 'purchase_orders' || $type === 'all') {
        $stmt = $pdo->query("SELECT po.id, po.po_number, s.name as supplier, 
                            poi.quantity_ordered, poi.quantity_received, poi.unit_cost, 
                            poi.total_cost, po.order_date, po.status
                            FROM purchase_orders po 
                            LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
                            LEFT JOIN suppliers s ON po.supplier_id = s.id
                            LEFT JOIN products p ON poi.product_id = p.id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($type !== 'all') {
            outputCSV('purchase_orders.csv', ['ID', 'PO Number', 'Supplier', 'Qty Ordered', 'Qty Received', 'Unit Cost', 'Total Cost', 'Order Date', 'Status'], $rows);
        }
        $data['purchase_orders'] = $rows;
    }

    if ($type === 'finance' || $type === 'all') {
        $stmt = $pdo->query("SELECT fr.id, fr.type, fr.amount, fc.name as category, 
                            fr.description, fr.transaction_date, u.name as created_by
                            FROM finance_records fr 
                            LEFT JOIN finance_categories fc ON fr.category_id = fc.id
                            LEFT JOIN users u ON fr.created_by = u.id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($type !== 'all') {
            outputCSV('finance.csv', ['ID', 'Type', 'Amount', 'Category', 'Description', 'Date', 'Created By'], $rows);
        }
        $data['finance'] = $rows;
    }

    if ($type === 'orders' || $type === 'all') {
        $stmt = $pdo->query("SELECT o.id, o.order_number, u.name as customer, o.subtotal, 
                            o.delivery_fee, o.grand_total, o.status, o.order_date,
                            oi.product_name, oi.quantity, oi.unit_price, oi.total_price
                            FROM orders o 
                            LEFT JOIN order_items oi ON o.id = oi.order_id
                            LEFT JOIN users u ON o.user_id = u.id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($type !== 'all') {
            outputCSV('orders.csv', ['Order ID', 'Order Number', 'Customer', 'Subtotal', 'Delivery Fee', 'Grand Total', 'Status', 'Order Date', 'Product', 'Quantity', 'Unit Price', 'Total Price'], $rows);
        }
        $data['orders'] = $rows;
    }

    if ($type === 'stock_movements' || $type === 'all') {
        $stmt = $pdo->query("SELECT sm.id, p.name as product, sm.movement_type, 
                            sm.quantity_change, sm.quantity_before, sm.quantity_after, 
                            sm.reference_type, sm.reference_id, sm.created_at, u.name as created_by
                            FROM stock_movements sm 
                            LEFT JOIN products p ON sm.product_id = p.id
                            LEFT JOIN users u ON sm.created_by = u.id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($type !== 'all') {
            outputCSV('stock_movements.csv', ['ID', 'Product', 'Movement Type', 'Quantity Change', 'Qty Before', 'Qty After', 'Reference Type', 'Reference ID', 'Date', 'Created By'], $rows);
        }
        $data['stock_movements'] = $rows;
    }

    // Export all: merge to one CSV, different sections separated by empty line
    if ($type === 'all') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="all_data.csv"');
        $out = fopen('php://output', 'w');

        // Inventory
        if (!empty($data['inventory'])) {
            fwrite($out, "=== INVENTORY ===\n");
            fputcsv($out, ['ID', 'Name', 'Category', 'Supplier', 'Quantity', 'Unit', 'Price']);
            foreach ($data['inventory'] as $row) {
                fputcsv($out, array_values($row));
            }
            fwrite($out, "\n");
        }

        // Purchase Orders
        if (!empty($data['purchase_orders'])) {
            fwrite($out, "=== PURCHASE ORDERS ===\n");
            fputcsv($out, ['ID', 'PO Number', 'Supplier', 'Qty Ordered', 'Qty Received', 'Unit Cost', 'Total Cost', 'Order Date', 'Status']);
            foreach ($data['purchase_orders'] as $row) {
                fputcsv($out, array_values($row));
            }
            fwrite($out, "\n");
        }

        // Finance
        if (!empty($data['finance'])) {
            fwrite($out, "=== FINANCE ===\n");
            fputcsv($out, ['ID', 'Type', 'Amount', 'Category', 'Description', 'Date', 'Created By']);
            foreach ($data['finance'] as $row) {
                fputcsv($out, array_values($row));
            }
            fwrite($out, "\n");
        }

        // Orders
        if (!empty($data['orders'])) {
            fwrite($out, "=== ORDERS ===\n");
            fputcsv($out, ['Order ID', 'Order Number', 'Customer', 'Subtotal', 'Delivery Fee', 'Grand Total', 'Status', 'Order Date', 'Product', 'Quantity', 'Unit Price', 'Total Price']);
            foreach ($data['orders'] as $row) {
                fputcsv($out, array_values($row));
            }
            fwrite($out, "\n");
        }

        // Stock Movements
        if (!empty($data['stock_movements'])) {
            fwrite($out, "=== STOCK MOVEMENTS ===\n");
            fputcsv($out, ['ID', 'Product', 'Movement Type', 'Quantity Change', 'Qty Before', 'Qty After', 'Reference Type', 'Reference ID', 'Date', 'Created By']);
            foreach ($data['stock_movements'] as $row) {
                fputcsv($out, array_values($row));
            }
            fwrite($out, "\n");
        }

        fclose($out);
        exit;
    }
}
?>

<?php include 'header.php'; ?>
<h2 class="text-2xl font-bold mb-4">Export Data</h2>
<form method="post" class="max-w-md bg-white p-6 rounded shadow flex flex-col gap-4">
    <label class="font-semibold">Select data to export:</label>
    <select name="export_type" class="border p-2 rounded" required>
        <option value="inventory">Inventory (Products)</option>
        <option value="purchase_orders">Purchase Orders</option>
        <option value="finance">Finance Records</option>
        <option value="orders">Customer Orders</option>
        <option value="stock_movements">Stock Movements</option>
        <option value="all">All (combined CSV)</option>
    </select>
    <button class="bg-blue-600 text-white px-4 py-2 rounded" type="submit">Export</button>
</form>
<?php include 'footer.php'; ?>