<?php
require_once 'db.php';
session_start();

// Authorization check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$message = "";

// Handle Form Submissions (Products, Staff, Order Status Updates, Direct Sales)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Product Actions
        if ($_POST['action'] === 'create_product') {
            $stmt = $pdo->prepare("INSERT INTO products (name, cost_price, selling_price, stock_quantity) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['cost_price'], $_POST['selling_price'], $_POST['stock_quantity']]);
        } elseif ($_POST['action'] === 'update_product') {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, cost_price = ?, selling_price = ?, stock_quantity = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['cost_price'], $_POST['selling_price'], $_POST['stock_quantity'], $_POST['id']]);
        } elseif ($_POST['action'] === 'delete_product') {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        }
        
        // Staff Actions (CRUD)
        elseif ($_POST['action'] === 'create_staff') {
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'staff')");
            $stmt->execute([$_POST['username'], $hashedPassword]);
        } elseif ($_POST['action'] === 'update_staff') {
            if (!empty($_POST['password'])) {
                $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ? AND role = 'staff'");
                $stmt->execute([$_POST['username'], $hashedPassword, $_POST['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ? AND role = 'staff'");
                $stmt->execute([$_POST['username'], $_POST['id']]);
            }
        } elseif ($_POST['action'] === 'delete_staff') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'");
            $stmt->execute([$_POST['id']]);
        }
        
        // Order Status Actions
        elseif ($_POST['action'] === 'update_order_status') {
            $stmt = $pdo->prepare("UPDATE customer_orders SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['order_id']]);
        }

        // Admin Direct Sale Action (For staff absence)
        elseif ($_POST['action'] === 'admin_sell_product') {
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
                    $message = "<div style='background: #d4edda; color: #155724; padding: 10px; margin-bottom: 15px; border-radius: 4px;'>Direct sale processed successfully!</div>";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px;'>Error: " . $e->getMessage() . "</div>";
                }
            } else {
                $message = "<div style='background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px;'>Error: Out of stock or invalid quantity.</div>";
            }
        }

        if ($_POST['action'] !== 'admin_sell_product') {
            // Preserve current pagination limits on redirect if desired, or let them reset to 10
            header("Location: admin.php");
            exit;
        }
    }
}

// Fetch staff for editing if ID is provided
$editStaff = null;
if (isset($_GET['edit_staff'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'staff'");
    $stmt->execute([$_GET['edit_staff']]);
    $editStaff = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Pagination Limits (Default: 10)
$limitOrders = isset($_GET['limit_orders']) ? intval($_GET['limit_orders']) : 10;
if ($limitOrders < 10) $limitOrders = 10;

$limitStaff = isset($_GET['limit_staff']) ? intval($_GET['limit_staff']) : 10;
if ($limitStaff < 10) $limitStaff = 10;

$limitProducts = isset($_GET['limit_products']) ? intval($_GET['limit_products']) : 10;
if ($limitProducts < 10) $limitProducts = 10;

// Fetch Total Counts for Buttons
$totalOrdersCount = $pdo->query("SELECT COUNT(*) FROM customer_orders")->fetchColumn();
$totalStaffCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
$totalProductsCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// Fetch Data with Limits
$orderQuery = $pdo->prepare("
    SELECT co.*, p.name as product_name, coi.quantity, coi.price 
    FROM customer_orders co 
    JOIN customer_order_items coi ON co.id = coi.order_id 
    JOIN products p ON coi.product_id = p.id 
    ORDER BY co.order_date DESC 
    LIMIT ?
");
$orderQuery->bindValue(1, $limitOrders, PDO::PARAM_INT);
$orderQuery->execute();
$customerOrders = $orderQuery->fetchAll(PDO::FETCH_ASSOC);

$staffQuery = $pdo->prepare("SELECT * FROM users WHERE role = 'staff' ORDER BY id DESC LIMIT ?");
$staffQuery->bindValue(1, $limitStaff, PDO::PARAM_INT);
$staffQuery->execute();
$staffList = $staffQuery->fetchAll(PDO::FETCH_ASSOC);

$productQuery = $pdo->prepare("SELECT * FROM products ORDER BY id DESC LIMIT ?");
$productQuery->bindValue(1, $limitProducts, PDO::PARAM_INT);
$productQuery->execute();
$products = $productQuery->fetchAll(PDO::FETCH_ASSOC);

// For the direct sale dropdown, we still want to fetch ALL in-stock products so admin can pick anything
$allProductsForDropdown = $pdo->query("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Statistics & Profits
$bestSellers = $pdo->query("
    SELECT p.name, SUM(si.quantity) as total_sold 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    GROUP BY si.product_id 
    ORDER BY total_sold DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$totalProfitData = $pdo->query("SELECT SUM(total_profit) as net_profit FROM sales")->fetch(PDO::FETCH_ASSOC);
$netProfit = $totalProfitData['net_profit'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f4f9; color: #333; }
        h1, h2 { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 10px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #2c3e50; color: white; }
        form { background: #fff; padding: 20px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        input, select { padding: 8px; margin: 5px 0 15px 0; width: 100%; box-sizing: border-box; }
        button { background: #3498db; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 3px; }
        button:hover { background: #2980b9; }
        .danger { background: #e74c3c; }
        .danger:hover { background: #c0392b; }
        .logout { float: right; background: #c0392b; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
        .card-container { display: flex; gap: 20px; margin-bottom: 20px; }
        .card { background: white; padding: 20px; flex: 1; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section-grid { display: flex; gap: 20px; }
        .section-grid > div { flex: 1; }
        .view-more-container { text-align: center; margin-bottom: 30px; }
        .view-more-btn { background: #2c3e50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block; }
        .view-more-btn:hover { background: #34495e; }
    </style>
</head>
<body>

    <a href="logout.php" class="logout">Logout</a>
    <h1>Admin Dashboard</h1>
    <p>Welcome, Admin [<?php echo htmlspecialchars($_SESSION['username']); ?>]</p>

    <?php echo $message; ?>

    <!-- Overview Cards -->
    <div class="card-container">
        <div class="card">
            <h3>Total Net Profit</h3>
            <p style="font-size: 24px; font-weight: bold; color: #27ae60;">Tshs <?php echo number_format($netProfit, 2); ?></p>
        </div>
        <div class="card">
            <h3>Top Selling Products</h3>
            <ul>
                <?php foreach ($bestSellers as $item): ?>
                    <li><?php echo htmlspecialchars($item['name']); ?> (<?php echo $item['total_sold']; ?> sold)</li>
                <?php endforeach; if(empty($bestSellers)) echo "<li>No sales recorded yet.</li>"; ?>
            </ul>
        </div>
    </div>

    <!-- Customer Online Orders Table -->
    <h2>Customer Online Orders (Showing <?php echo count($customerOrders); ?> of <?php echo $totalOrdersCount; ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer Name</th>
                <th>Phone</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Total (Tshs)</th>
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
                <td>Tshs <?php echo number_format($ord['total_amount'], 2); ?></td>
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

    <?php if ($totalOrdersCount > $limitOrders): ?>
    <div class="view-more-container">
        <a href="admin.php?limit_orders=<?php echo $limitOrders + 10; ?>&limit_staff=<?php echo $limitStaff; ?>&limit_products=<?php echo $limitProducts; ?>" class="view-more-btn">🔽 View 10 More Orders</a>
    </div>
    <?php endif; ?>

    <div class="section-grid">
        <!-- Log New Product Form -->
        <div>
            <h2>Log New Product</h2>
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="create_product">
                <label>Product Name:</label>
                <input type="text" name="name" required>
                <label>Cost Price (Tshs):</label>
                <input type="number" step="0.01" name="cost_price" required>
                <label>Selling Price (Tshs):</label>
                <input type="number" step="0.01" name="selling_price" required>
                <label>Stock Quantity:</label>
                <input type="number" name="stock_quantity" required>
                <button type="submit">Add Product</button>
            </form>
        </div>

        <!-- Staff Management Form (Create / Edit) -->
        <div>
            <h2><?php echo $editStaff ? 'Edit Staff Account' : 'Add New Staff'; ?></h2>
            <form method="POST" action="admin.php">
                <input type="hidden" name="action" value="<?php echo $editStaff ? 'update_staff' : 'create_staff'; ?>">
                <?php if ($editStaff): ?>
                    <input type="hidden" name="id" value="<?php echo $editStaff['id']; ?>">
                <?php endif; ?>

                <label>Staff Username:</label>
                <input type="text" name="username" value="<?php echo $editStaff['username'] ?? ''; ?>" required>

                <label>Password <?php echo $editStaff ? '(Leave blank to keep current)' : ''; ?>:</label>
                <input type="password" name="password" <?php echo $editStaff ? '' : 'required'; ?>>

                <button type="submit"><?php echo $editStaff ? 'Update Staff' : 'Create Staff'; ?></button>
                <?php if ($editStaff): ?>
                    <a href="admin.php" style="margin-left: 10px; text-decoration: none; color: #666;">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Process Direct Sale (For Staff Absence) -->
    <h2>Process Direct Sale (Admin Mode)</h2>
    <form method="POST" action="admin.php" style="max-width: 500px;">
        <input type="hidden" name="action" value="admin_sell_product">
        <label>Select Product:</label>
        <select name="product_id" required>
            <option value="">-- Choose Product --</option>
            <?php foreach ($allProductsForDropdown as $p): ?>
                <option value="<?php echo $p['id']; ?>">
                    <?php echo htmlspecialchars($p['name']); ?> (Stock: <?php echo $p['stock_quantity']; ?> | Tshs <?php echo number_format($p['selling_price'], 2); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <label>Quantity:</label>
        <input type="number" name="quantity" min="1" value="1" required>
        <button type="submit" style="background: #27ae60;">Complete Direct Sale</button>
    </form>

    <!-- Product Inventory Table -->
    <h2>Product Inventory Management (Showing <?php echo count($products); ?> of <?php echo $totalProductsCount; ?>)</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Name</th><th>Selling Price</th><th>Stock Remaining</th><th>Action</th></tr>
        </thead>
        <tbody>
            <?php foreach ($products as $row): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td>Tshs <?php echo number_format($row['selling_price'], 2); ?></td>
                <td><strong><?php echo $row['stock_quantity']; ?></strong></td>
                <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete product?');">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="danger" style="padding:5px 10px;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; if(empty($products)): ?>
            <tr><td colspan="5">No products found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalProductsCount > $limitProducts): ?>
    <div class="view-more-container">
        <a href="admin.php?limit_orders=<?php echo $limitOrders; ?>&limit_staff=<?php echo $limitStaff; ?>&limit_products=<?php echo $limitProducts + 10; ?>" class="view-more-btn">🔽 View 10 More Products</a>
    </div>
    <?php endif; ?>

    <!-- Staff List Management Table -->
    <h2>Staff Management (Showing <?php echo count($staffList); ?> of <?php echo $totalStaffCount; ?>)</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Username</th><th>Created At</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($staffList as $staff): ?>
            <tr>
                <td><?php echo $staff['id']; ?></td>
                <td><?php echo htmlspecialchars($staff['username']); ?></td>
                <td><?php echo $staff['created_at']; ?></td>
                <td>
                    <a href="admin.php?edit_staff=<?php echo $staff['id']; ?>">Edit</a> | 
                    <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete this staff member?');">
                        <input type="hidden" name="action" value="delete_staff">
                        <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                        <button type="submit" class="danger" style="padding: 5px 10px; font-size: 12px;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; if(empty($staffList)): ?>
            <tr><td colspan="4">No staff members found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalStaffCount > $limitStaff): ?>
    <div class="view-more-container">
        <a href="admin.php?limit_orders=<?php echo $limitOrders; ?>&limit_staff=<?php echo $limitStaff + 10; ?>&limit_products=<?php echo $limitProducts; ?>" class="view-more-btn">🔽 View 10 More Staff Members</a>
    </div>
    <?php endif; ?>

</body>
</html>