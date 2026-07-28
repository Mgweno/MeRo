<?php
require_once 'db.php';

$message = "";

// Handle Customer Order & Contact Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'place_order') {
        $name = trim($_POST['customer_name']);
        $email = trim($_POST['customer_email']);
        $phone = trim($_POST['customer_phone']);
        $productIds = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];

        if (!empty($productIds) && is_array($productIds) && count($productIds) === count($quantities)) {
            try {
                $pdo->beginTransaction();

                $totalAmount = 0;
                $itemsToProcess = [];

                // Validate and gather details for all requested products
                for ($i = 0; $i < count($productIds); $i++) {
                    $pId = intval($productIds[$i]);
                    $qty = intval($quantities[$i]);

                    if ($pId > 0 && $qty > 0) {
                        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                        $stmt->execute([$pId]);
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$product || $product['stock_quantity'] < $qty) {
                            $productName = $product ? $product['name'] : 'Selected item';
                            throw new Exception("Product '{$productName}' is out of stock or requested quantity is unavailable.");
                        }

                        $itemTotal = $product['selling_price'] * $qty;
                        $totalAmount += $itemTotal;

                        $itemsToProcess[] = [
                            'product_id' => $pId,
                            'quantity' => $qty,
                            'price' => $product['selling_price']
                        ];
                    }
                }

                if (empty($itemsToProcess)) {
                    throw new Exception("Please select at least one valid product and quantity.");
                }

                // 1. Insert customer order record
                $orderStmt = $pdo->prepare("INSERT INTO customer_orders (customer_name, customer_email, customer_phone, total_amount) VALUES (?, ?, ?, ?)");
                $orderStmt->execute([$name, $email, $phone, $totalAmount]);
                $orderId = $pdo->lastInsertId();

                // 2. Insert order items & deduct stock for each product
                $itemStmt = $pdo->prepare("INSERT INTO customer_order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $updateStock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");

                foreach ($itemsToProcess as $item) {
                    $itemStmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
                    $updateStock->execute([$item['quantity'], $item['product_id']]);
                }

                $pdo->commit();
                $message = "<div class='alert success'>Order placed successfully for " . count($itemsToProcess) . " item(s)! Total: Tshs " . number_format($totalAmount, 2) . ". Thank you for shopping with us!</div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert error'>Failed to place order: " . $e->getMessage() . "</div>";
            }
        } else {
            $message = "<div class='alert error'>Please select at least one item to order.</div>";
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

// Fetch all registered products
$products = $pdo->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Determine active page tab view (default is 'home')
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MERO Mini Market</title>
    <!-- Google Fonts & FontAwesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #003366;
            --primary-hover: #1ac8c2;
            --bg-light: #9bc1bb;
            --bg-card: #9bc1bb;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --badge-bg: #fee2e2;
            --badge-text: #991b1b;
            --danger: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #ffffff;
            color: var(--text-main);
            line-height: 1.5;
        }

        /* Top Navigation Header */
        header {
            background: #ffffff;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--text-main);
            text-decoration: none;
        }

        .logo-icon {
            color: var(--primary);
            font-size: 1.5rem;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 28px;
            align-items: center;
        }

        nav a {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        nav a:hover, nav a.active {
            color: var(--primary);
            font-weight: 700;
        }

        .btn-cta {
            background: var(--primary);
            color: white;
            padding: 10px 22px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-cta:hover {
            background: var(--primary-hover);
        }

        /* Hero Banner Section */
        .hero {
            background: linear-gradient(180deg, #fff5f3 0%, #ffffff 100%);
            padding: 60px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .hero-inner {
            max-width: 1200px;
            margin: 0 auto;
        }

        .category-tag {
            color: var(--primary);
            text-transform: uppercase;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 12px;
            display: block;
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.15;
            color: #111827;
            margin-bottom: 16px;
        }

        .hero p {
            color: var(--text-muted);
            font-size: 1.05rem;
            max-width: 600px;
            margin-bottom: 28px;
        }

        .hero-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 10px 22px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.88rem;
            transition: background 0.2s;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: #f3f4f6;
        }

        /* Container & Alerts */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 24px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-weight: 500;
        }

        .success {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Section Titles */
        .section-header {
            margin-bottom: 30px;
        }

        .section-header h2 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #111827;
        }

        /* About Us Section Styles */
        .about-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 36px;
            margin-bottom: 60px;
            line-height: 1.8;
        }

        .about-card p {
            margin-bottom: 16px;
            color: var(--text-muted);
        }

        /* Product Cards Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 60px;
        }

        .product-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 28px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .product-card:hover {
            border-color: #d1d5db;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }

        .product-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #111827;
        }

        .product-desc {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .product-card .price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 12px;
        }

        /* Form Styling */
        .form-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 36px;
            margin-bottom: 60px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
        }

        /* Order Item Rows Styling */
        .order-items-wrapper {
            margin-top: 10px;
            margin-bottom: 20px;
            border: 1px dashed var(--border-color);
            padding: 20px;
            border-radius: 10px;
            background-color: #f9fafb;
        }

        .order-item-row {
            display: grid;
            grid-template-columns: 3fr 1fr 40px;
            gap: 12px;
            align-items: center;
            margin-bottom: 12px;
        }

        .order-item-row:last-child {
            margin-bottom: 0;
        }

        .btn-remove-item {
            background: var(--danger);
            color: white;
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-remove-item:hover {
            opacity: 0.85;
        }

        .btn-add-item {
            background: #f3f4f6;
            color: var(--text-main);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
        }

        .btn-add-item:hover {
            background: #e5e7eb;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: var(--primary-hover);
        }

        /* Direct Contact Quick Links */
        .contact-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 60px;
        }

        .contact-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            color: white;
        }

        .pill-whatsapp { background: #25d366; }
        .pill-phone { background: #0284c7; }
        .pill-email { background: #ea580c; }

        /* Footer Section */
        footer {
            background: #ede9f0;
            border-top: 1px solid var(--border-color);
            padding-top: 60px;
        }

        .footer-top {
            max-width: 1800px;
            margin: 0 auto;
            padding: 0 24px 50px 24px;
            display: grid;
            grid-template-columns: 2fr 1fr 1.5fr 1fr;
            gap: 40px;
        }

        .footer-col h4 {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-col p, .footer-col li {
            font-size: 0.88rem;
            color: var(--text-muted);
            line-height: 1.7;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 10px;
        }

        .footer-col a {
            text-decoration: none;
            color: var(--text-muted);
            transition: color 0.2s;
        }

        .footer-col a:hover {
            color: var(--text-main);
        }

        .social-icons {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }

        .social-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s;
        }

        .social-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Bottom Info Strip */
        .footer-info-strip {
            border-top: 1px solid var(--border-color);
            padding: 24px;
            background: #fafafa;
        }

        .info-strip-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .info-box {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .info-icon-wrapper {
            background: #f3f4f6;
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }

        .info-box label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary);
            margin: 0;
        }

        .info-box p {
            font-weight: 700;
            font-size: 0.9rem;
            color: #111827;
        }

        .copyright {
            text-align: center;
            padding: 20px;
            font-size: 0.8rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border-color);
            background: #ffffff;
        }

        /* Floating WhatsApp Button */
        .floating-whatsapp {
            position: fixed;
            bottom: 28px;
            right: 28px;
            background: #25d366;
            color: white;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            text-decoration: none;
            z-index: 999;
            transition: transform 0.2s;
        }

        .floating-whatsapp:hover {
            transform: scale(1.08);
        }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .footer-top { grid-template-columns: 1fr; }
            .info-strip-inner { grid-template-columns: 1fr; gap: 16px; }
            nav { display: none; }
            .hero h1 { font-size: 2rem; }
            .order-item-row { grid-template-columns: 1fr; }
        }
        .logo-img {
    height: 45px; /* Adjust height to match your header size */
    width: auto;  /* Maintains aspect ratio */
    object-fit: contain;
    border-radius: 50%; /* Optional: rounds image edges nicely */
}
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header>
       <div class="nav-container">
    <a href="?tab=home" class="logo-area">
        <!-- Replaced icon with your logo badge image -->
        <img src="images/mero-logo.png" alt="MeRo Logo" class="logo-img">
        <span>
            <span style="color: darkblue;">Me</span><span style="color: red;">Ro</span> 
            <span style="color: #1f2937;">SHOPPING</span> 
            <span style="color: red;">MINI</span> 
            <span style="color: darkblue;">MARKET</span>
        </span>
    </a>
      
  
            <nav>
                <ul>
                    <li><a href="?tab=home" class="<?php echo ($tab === 'home') ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="?tab=products" class="<?php echo ($tab === 'products') ? 'active' : ''; ?>">Products</a></li>
                    <li><a href="?tab=order" class="<?php echo ($tab === 'order') ? 'active' : ''; ?>">Place Order</a></li>
                    <li><a href="?tab=about" class="<?php echo ($tab === 'about') ? 'active' : ''; ?>">About Us</a></li>
                    <li><a href="?tab=contact" class="<?php echo ($tab === 'contact') ? 'active' : ''; ?>">Contact</a></li>
                </ul>
            </nav>
            <a href="?tab=order" class="btn-cta">Request Quote</a>
        </div>
    </header>

    <!-- Main Content Area -->
    <div class="container">

        <?php echo $message; ?>

        <!-- HERO SECTION (Shown in Home Tab) -->
        <?php if ($tab === 'home'): ?>
            <section class="hero" style="margin-bottom: 40px; border-radius: 12px;">
                <div class="hero-inner">
                    <span class="category-tag">TANZANIAN QUALITY & RELIABILITY</span>
                    <h1>Rooted in Local Service.<br>Built for Real Needs.</h1>
                    <p>MeRo Shopping Mini Market supplies top quality registered products right here in Kyela. Browse our inventory, order online, or contact us directly.</p>
                    <div class="hero-buttons">
                        <a href="?tab=products" class="btn-cta">Explore Products</a>
                        <a href="?tab=contact" class="btn-secondary">Get in Touch</a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- ABOUT US TAB -->
        <?php if ($tab === 'about'): ?>
            <section id="about">
                <div class="section-header">
                    <span class="category-tag">OUR STORY</span>
                    <h2>About MeRo Mini Market</h2>
                </div>
                <div class="about-card">
                    <p>Welcome to <strong>MeRo SHOPPING Mini Market</strong>, your trusted community retail hub located at Basai Petroleum Company Ltd in Kyela. We are dedicated to delivering genuine quality products and seamless shopping experiences for all our local customers.</p>
                    <p>Whether you visit us in person or place an order through our digital platform, we ensure that every product registered in our inventory meets rigorous quality standards. Our mission is to make quality household and daily goods accessible, affordable, and conveniently deliverable across the region.</p>
                </div>
            </section>
        <?php endif; ?>

        <!-- PRODUCTS CATALOG TAB (Shown in Home & Products Tab) -->
        <?php if ($tab === 'home' || $tab === 'products'): ?>
            <section id="products">
                <div class="section-header">
                    <span class="category-tag">PRODUCTS / CATALOG</span>
                    <h2>Registered Products</h2>
                </div>

                <div class="product-grid">
                    <?php foreach ($products as $p): ?>
                        <div class="product-card">
                            <div>
                                <h3><?php echo htmlspecialchars($p['name']); ?></h3>
                                <p class="product-desc">Available for direct purchase and fast delivery.</p>
                                <div class="price">Tshs <?php echo number_format($p['selling_price'], 2); ?></div>
                            </div>
                            <div>
                                <a href="?tab=order" class="btn-secondary" style="display: inline-block; width: 100%; text-align: center;">Order Item</a>
                            </div>
                        </div>
                    <?php endforeach; if (empty($products)): ?>
                        <p>No products available at the moment.</p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- PLACE ORDER TAB (Shown in Home & Order Tab) -->
        <?php if ($tab === 'home' || $tab === 'order'): ?>
            <section id="order">
                <div class="section-header">
                    <span class="category-tag">ONLINE CHECKOUT</span>
                    <h2>Make an Online Order</h2>
                </div>

                <div class="form-card">
                    <form method="POST" action="shop.php?tab=<?php echo htmlspecialchars($tab); ?>">
                        <input type="hidden" name="action" value="place_order">

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Your Full Name</label>
                                <input type="text" name="customer_name" placeholder="Rose Temu" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="customer_email" placeholder="example@meromarket.com" required>
                            </div>
                            <div class="form-group full-width">
                                <label>Phone Number</label>
                                <input type="text" name="customer_phone" placeholder="+255..." required>
                            </div>

                            <!-- Multi-item Container -->
                            <div class="form-group full-width">
                                <label>Select Product Items & Quantities</label>
                                <div class="order-items-wrapper">
                                    <div id="order-items-container">
                                        <!-- Default first item row -->
                                        <div class="order-item-row">
                                            <div>
                                                <select name="product_ids[]" required>
                                                    <option value="">-- Choose a Product --</option>
                                                    <?php foreach ($products as $p): if ($p['stock_quantity'] > 0): ?>
                                                        <option value="<?php echo $p['id']; ?>">
                                                            <?php echo htmlspecialchars($p['name']); ?> - Tshs <?php echo number_format($p['selling_price'], 2); ?>
                                                        </option>
                                                    <?php endif; endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <input type="number" name="quantities[]" min="1" value="1" placeholder="Qty" required>
                                            </div>
                                            <div>
                                                <button type="button" class="btn-remove-item" onclick="removeOrderRow(this)" title="Remove Item"><i class="fa-solid fa-trash"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" class="btn-add-item" onclick="addOrderRow()">
                                        <i class="fa-solid fa-plus"></i> Add Another Item
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">Submit Multi-Item Order</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <!-- CONTACT TAB / INQUIRIES (Shown in Home & Contact Tab) -->
        <?php if ($tab === 'home' || $tab === 'contact'): ?>
            <!-- Direct Instant Contact Bar -->
            <div class="contact-bar">
                <a href="https://wa.me/255713028326?text=Hello%20MERO%20Mini%20Market,%20I%20would%20like%20to%20inquire%20about%20your%20products." class="contact-pill pill-whatsapp" target="_blank">
                    <i class="fa-brands fa-whatsapp"></i> WhatsApp
                </a>
                <a href="tel:+255675898957" class="contact-pill pill-phone">
                    <i class="fa-solid fa-phone"></i> Call +255 675 898 957
                </a>
                <a href="tel:+255795661110" class="contact-pill pill-phone">
                    <i class="fa-solid fa-phone"></i> Call +255 795 661 110
                </a>
                <a href="mailto:temurose914@gmail.com?subject=Inquiry%20regarding%20products" class="contact-pill pill-email">
                    <i class="fa-solid fa-envelope"></i> Email Us
                </a>
            </div>

            <!-- Contact Form -->
            <section id="contact">
                <div class="section-header">
                    <span class="category-tag">INQUIRIES</span>
                    <h2>Send Us a Message</h2>
                </div>

                <div class="form-card">
                    <form method="POST" action="shop.php?tab=<?php echo htmlspecialchars($tab); ?>">
                        <input type="hidden" name="action" value="send_message">

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Your Name</label>
                                <input type="text" name="msg_name" placeholder="Your Name" required>
                            </div>
                            <div class="form-group">
                                <label>Your Email</label>
                                <input type="email" name="msg_email" placeholder="email@example.com" required>
                            </div>
                            <div class="form-group full-width">
                                <label>Message / Inquiry</label>
                                <textarea name="msg_content" rows="4" required placeholder="Type your questions or inquiries here..."></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">Send Message</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

    </div>

    <!-- Footer Area -->
    <footer>
        <div class="footer-top">
            <div class="footer-col">
                <a href="?tab=home" class="logo-area">
        <!-- Replaced icon with your logo badge image -->
        <img src="images/mero-logo.png" alt="MeRo Logo" class="logo-img">
        <span>
            <span style="color: darkblue;">Me</span><span style="color: red;">Ro</span> 
            <span style="color: #1f2937;">SHOPPING</span> 
            <span style="color: red;">MINI</span> 
            <span style="color: darkblue;">MARKET</span>
        </span>
    </a>
      
                <p><strong>Quality mini market supply, online order delivery, and direct store purchases engineered for Kyela.</strong></p>
            </div>

            <div class="footer-col">
                <h4><i class="fa-solid fa-bars"></i> Navigation</h4>
                <ul>
                    <li><a href="?tab=home">Home</a></li>
                    <li><a href="?tab=products">Products</a></li>
                    <li><a href="?tab=order">Place Order</a></li>
                    <li><a href="?tab=about">About Us</a></li>
                    <li><a href="?tab=contact">Contact Us</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4><i class="fa-solid fa-location-dot"></i> Contact</h4>
                <strong>
                    <p>MeRo SHOPPING MINI MARKET</p>
                    <p>+255 675 898 957</p>
                    <p>+255 795 661 110</p>
                    <p>temurose914@gmail.com</p>
                    <p>mexbernaldo@gmail.com</p>
                </strong>
            </div>

            <div class="footer-col">
                <h4><i class="fa-solid fa-share-nodes"></i> Follow Us</h4>
                <div class="social-icons">
                    <a href="https://www.tiktok.com/@merominmarketmtej" class="social-btn" target="_blank">
                        <i class="fa-brands fa-tiktok"></i>
                    </a>
                    <a href="#" class="social-btn"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="social-btn"><i class="fa-brands fa-linkedin-in"></i></a>
                    <a href="https://wa.me/255713028326" class="social-btn"><i class="fa-brands fa-whatsapp"></i></a>
                </div>
            </div>
        </div>

        <!-- Info Strip -->
        <div class="footer-info-strip">
            <div class="info-strip-inner">
                <div class="info-box">
                    <div class="info-icon-wrapper"><i class="fa-regular fa-calendar-check"></i></div>
                    <div>
                        <label>Open Days</label>
                        <p>Monday to Sunday</p>
                    </div>
                </div>
                <div class="info-box">
                    <div class="info-icon-wrapper"><i class="fa-regular fa-clock"></i></div>
                    <div>
                        <label>Working Hours</label>
                        <p>07:00 to 23:00</p>
                    </div>
                </div>
                <div class="info-box">
                    <div class="info-icon-wrapper"><i class="fa-solid fa-location-dot"></i></div>
                    <div>
                        <label>Location</label>
                        <p>Basai Petroleum Company Ltd, Kyela</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="copyright">
            © <?php echo date('Y'); ?> MeRo Mini Market. All rights reserved.
        </div>
    </footer>

    <!-- Floating WhatsApp Widget -->
    <a href="https://wa.me/255713028326?text=Hello%20MERO%20Mini%20Market" class="floating-whatsapp" target="_blank">
        <i class="fa-brands fa-whatsapp"></i>
    </a>

    <!-- JavaScript for Dynamic Multi-Item Rows -->
    <script>
        function addOrderRow() {
            const container = document.getElementById('order-items-container');
            const firstRow = container.querySelector('.order-item-row');
            if (firstRow) {
                const newRow = firstRow.cloneNode(true);
                // Reset select value and quantity
                const select = newRow.querySelector('select');
                if (select) select.selectedIndex = 0;
                
                const qtyInput = newRow.querySelector('input[type="number"]');
                if (qtyInput) qtyInput.value = 1;

                container.appendChild(newRow);
            }
        }

        function removeOrderRow(btn) {
            const container = document.getElementById('order-items-container');
            const rows = container.querySelectorAll('.order-item-row');
            // Ensure at least one order row remains
            if (rows.length > 1) {
                btn.closest('.order-item-row').remove();
            } else {
                alert('An order must contain at least one item.');
            }
        }
    </script>

</body>
</html>