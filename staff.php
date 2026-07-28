<?php
require_once 'db.php';
session_start();

// Authorization check for staff
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

$message = "";

// Handle Staff Sale Processing or Order Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'sell_product') {
        $productId = $_POST['product_id'];
        $quantitySold = intval($_POST['quantity']);

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product && $product['stock_quantity'] >= $quantitySold && $quantitySold > 0) {
            $totalAmount = $product['selling_price'] * $quantitySold;
            $itemProfit = ($product['selling_price'] - $product['cost_price']) * $quantitySold;

            try {
                $pdo->beginTransaction();

                $saleStmt = $pdo->prepare("INSERT INTO sales (total_amount, total_profit) VALUES (?, ?)");
                $saleStmt->execute([$totalAmount, $itemProfit]);
                $saleId = $pdo->lastInsertId();

                $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, profit) VALUES (?, ?, ?, ?, ?)");
                $itemStmt->execute([$saleId, $productId, $quantitySold, $product['selling_price'], $itemProfit]);

                $updateStock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $updateStock->execute([$quantitySold, $productId]);

                $pdo->commit();
                $message = "<p style='color: green;'>Sale completed successfully!</p>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
            }
        } else {
            $message = "<p style='color: red;'>Error: Out of stock or invalid quantity.</p>";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
        $stmt = $pdo->prepare("UPDATE customer_orders SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['order_id']]);
        header("Location: staff.php");
        exit;
    }
}

// Fetch fresh, synchronized inventory and customer orders
$products = $pdo->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$customerOrders = $pdo->query("
    SELECT co.*, p.name as product_name, coi.quantity 
    FROM customer_orders co 
    JOIN customer_order_items coi ON co.id = coi.order_id 
    JOIN products p ON coi.product_id = p.id 
    ORDER BY co.order_date DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Portal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f4f9; color: #333; }
        h1, h2 { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #27ae60; color: white; }
        form { background: #fff; padding: 20px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 500px; }
        input, select { padding: 8px; margin: 5px 0 15px 0; width: 100%; box-sizing: border-box; }
        button { background: #27ae60; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 3px; }
        button:hover { background: #219653; }
        .logout { float: right; background: #c0392b; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>

    <a href="logout.php" class="logout">Logout</a>
    <h1>Staff Portal</h1>
    <p>Welcome, Staff Member [<?php echo htmlspecialchars($_SESSION['username']); ?>]</p>
    
    <?php echo $message; ?>

    <!-- Customer Online Orders Visible to Staff -->
    <h2>Customer Online Orders</h2>
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer Name</th>
                <th>Phone</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Total ($)</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($customerOrders as $ord): ?>
            <tr>
                <td><?php echo $ord['id']; ?></td>
                <td><?php echo htmlspecialchars($ord['customer_name']); ?></td>
                <td><?php echo htmlspecialchars($ord['customer_phone']); ?></td>
                <td><?php echo htmlspecialchars($ord['product_name']); ?></td>
                <td><?php echo $ord['quantity']; ?></td>
                <td>$<?php echo number_format($ord['total_amount'], 2); ?></td>
                <td><strong><?php echo $ord['status']; ?></strong></td>
                <td>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="update_order_status">
                        <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                        <select name="status" style="width:auto; display:inline; padding:4px;" onchange="this.form.submit()">
                            <option value="Pending" <?php if($ord['status']=='Pending') echo 'selected'; ?>>Pending</option>
                            <option value="Completed" <?php if($ord['status']=='Completed') echo 'selected'; ?>>Completed</option>
                            <option value="Cancelled" <?php if($ord['status']=='Cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
                    </form>
                </td>
            </tr>
            <?php endforeach; if(empty($customerOrders)): ?>
            <tr><td colspan="8">No customer online orders found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Process In-Store Sale -->
    <h2>Process In-Store Sale</h2>
    <form method="POST" action="staff.php">
        <input type="hidden" name="action" value="sell_product">
        <label>Select Product:</label>
        <select name="product_id" required>
            <option value="">-- Choose Product --</option>
            <?php foreach ($products as $p): if($p['stock_quantity'] > 0): ?>
                <option value="<?php echo $p['id']; ?>">
                    <?php echo htmlspecialchars($p['name']); ?> (Stock: <?php echo $p['stock_quantity']; ?> | Tshs <?php echo $p['selling_price']; ?>)
                </option>
            <?php endif; endforeach; ?>
        </select>
        <label>Quantity:</label>
        <input type="number" name="quantity" min="1" value="1" required>
        <button type="submit">Complete Sale</button>
    </form>

    <!-- Synchronized Inventory View -->
    <h2>Available Product Inventory</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Name</th><th>Selling Price</th><th>Stock Remaining</th></tr>
        </thead>
        <tbody>
            <?php foreach ($products as $row): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td>$<?php echo number_format($row['selling_price'], 2); ?></td>
                <td><strong><?php echo $row['stock_quantity']; ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>