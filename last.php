<?php
require_once 'db.php';

$message = "";

// Handle Customer Order & Contact Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'place_order') {
        $name = trim($_POST['customer_name']);
        $email = trim($_POST['customer_email']);
        $phone = trim($_POST['customer_phone']);
        $productId = $_POST['product_id'];
        $quantity = intval($_POST['quantity']);

        // Fetch product to verify availability and get price
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product && $product['stock_quantity'] >= $quantity && $quantity > 0) {
            $totalAmount = $product['selling_price'] * $quantity;

            try {
                $pdo->beginTransaction();

                // 1. Insert customer order
                $orderStmt = $pdo->prepare("INSERT INTO customer_orders (customer_name, customer_email, customer_phone, total_amount) VALUES (?, ?, ?, ?)");
                $orderStmt->execute([$name, $email, $phone, $totalAmount]);
                $orderId = $pdo->lastInsertId();

                // 2. Insert order items
                $itemStmt = $pdo->prepare("INSERT INTO customer_order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $itemStmt->execute([$orderId, $productId, $quantity, $product['selling_price']]);

                // 3. Automatically deduct inventory stock
                $updateStock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $updateStock->execute([$quantity, $productId]);

                $pdo->commit();
                $message = "<div class='alert success'>Order placed successfully! Thank you for shopping with us.</div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert error'>Failed to place order: " . $e->getMessage() . "</div>";
            }
        } else {
            $message = "<div class='alert error'>Selected product is out of stock or invalid quantity requested.</div>";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'send_message') {
        $cName = trim($_POST['msg_name']);
        $cEmail = trim($_POST['msg_email']);
        $cMsg = trim($_POST['msg_content']);

        if (!empty($cName) && !empty($cEmail) && !empty($cMsg)) {
            $msgStmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
            $msgStmt->execute([$cName, $cEmail, $cMsg]);
            $message = "<div class='alert success'>Your message has been sent to the seller successfully!</div>";
        } else {
            $message = "<div class='alert error'>Please fill in all contact fields.</div>";
        }
    }
}

// Fetch all products for the customer view
$products = $pdo->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to MERO Mini Market</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f9f9fb; color: #333; }
        header { background: #22b4ad; color: black; padding: 20px; text-align: center; }
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; }
        h2 { color: #2c3e50; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .product-card { background: white; border: 1px solid #ddd; border-radius: 5px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; }
        .product-card h3 { margin: 0 0 10px 0; color: #34495e; }
        .price { font-size: 18px; color: #27ae60; font-weight: bold; margin-bottom: 10px; }
        .stock { font-size: 14px; color: #7f8c8d; margin-bottom: 15px; }
        form { background: white; padding: 20px; border-radius: 5px; border: 1px solid #ddd; margin-bottom: 40px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        input, select, textarea { padding: 10px; margin: 5px 0 15px 0; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #3498db; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; }
        button:hover { background: #2980b9; }
        .whatsapp-btn { display: inline-block; background: #25d366; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-bottom: 20px; text-align: center; }
        .whatsapp-btn:hover { background: #20ba5a; }
        .contact-info { background: white; padding: 20px; border-radius: 5px; border: 1px solid #ddd; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .form-row { display: flex; gap: 15px; }
        .form-row > div { flex: 1; }
    </style>
</head>
<body>

    <header>
        <h1>MeRo SHOPPING Mini Market</h1>
        <p><i>Browse our registered products, order online, or contact us directly.</i></p>
        <p><i>TUNAPATIKANA BASAI PETROLEUM COMPANY LTD KYELA</i></p>
    </header>

    <div class="container">

        <?php echo $message; ?>

        <!-- Product Catalog -->
        <h2>Available Products</h2>
        <div class="product-grid">
            <?php foreach ($products as $p): ?>
                <div class="product-card">
                    <div>
                        <h3><?php echo htmlspecialchars($p['name']); ?></h3>
                        <div class="price">Tshs <?php echo number_format($p['selling_price'], 2); ?></div>
                        <div class="stock">
                            <?php if ($p['stock_quantity'] > 0): ?>
                                <span style="color: green;"><i>In Stock (<?php echo $p['stock_quantity']; ?> available)</i></span>
                            <?php else: ?>
                                <span style="color: red;"><i>Out of Stock</i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; if (empty($products)): ?>
                <p>No products available at the moment.</p>
            <?php endif; ?>
        </div>

        <!-- Place Online Order Form -->
        <h2>Make an Online Order</h2>
        <form method="POST" action="shop.php">
            <input type="hidden" name="action" value="place_order">

            <div class="form-row">
                <div>
                    <label>Your Full Name:</label>
                    <input type="text" name="customer_name" required>
                </div>
                <div>
                    <label>Email Address:</label>
                    <input type="email" name="customer_email" required>
                </div>
            </div>

            <label>Phone Number:</label>
            <input type="text" name="customer_phone" required>

            <label>Select Product to Buy:</label>
            <select name="product_id" required>
                <option value="">-- Choose a Product --</option>
                <?php foreach ($products as $p): if ($p['stock_quantity'] > 0): ?>
                    <option value="<?php echo $p['id']; ?>">
                        <?php echo htmlspecialchars($p['name']); ?> - Tshs <?php echo number_format($p['selling_price'], 2); ?>
                    </option>
                <?php endif; endforeach; ?>
            </select>

            <label>Quantity:</label>
            <input type="number" name="quantity" min="1" value="1" required>

            <button type="submit">Submit Online Order</button>
        </form>

<!-- Alternative with modern emojis -->
<a href="https://wa.me/255713028326?text=Hello%20MERO%20Mini%20Market,%20I%20would%20like%20to%20inquire%20about%20your%20products." style="background: #25d366; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;" target="_blank">
    💬 WhatsApp 
</a>

<a href="tel:+255675898957" style="background: #3498db; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">
    📲 Call Now
</a>
<a href="tel:+255795661110" style="background: #3498db; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">
    📲 Call Now
</a>

<a href="temurose914@gmail.com?subject=Inquiry%20regarding%20products" style="background: #e67e22; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">
    📧 Send Email
</a>
<a href="mexbernaldo@gmail.com?subject=Inquiry%20regarding%20products" style="background: #e67e22; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">
    📧 Send Email
</a>

        <!-- Contact the Seller Form -->
        <h2>Send Us a Message</h2>
        <form method="POST" action="shop.php">
            <input type="hidden" name="action" value="send_message">

            <div class="form-row">
                <div>
                    <label>Your Name:</label>
                    <input type="text" name="msg_name" required>
                </div>
                <div>
                    <label>Your Email:</label>
                    <input type="email" name="msg_email" required>
                </div>
            </div>

            <label>Message / Inquiry:</label>
            <textarea name="msg_content" rows="4" required placeholder="Type your questions or inquiries here..."></textarea>

            <button type="submit">Send Message</button>
        </form>

    </div>

</body>
</html>