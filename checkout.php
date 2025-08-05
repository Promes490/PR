<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_items = [];
$total = 0;
$message = '';

// Get cart items
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.image_path, p.stock, p.category, p.description
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cart_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
} catch (PDOException $e) {
    $cart_items = [];
    error_log("Cart fetch error: " . $e->getMessage());
}

// Get user details
$user_details = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user_details = [];
    error_log("User fetch error: " . $e->getMessage());
}

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email', 'phone', 'address1', 'city', 'country', 'zip'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception("Missing required fields: " . implode(', ', $missing_fields));
        }
        
        // Check if cart is empty
        if (empty($cart_items)) {
            throw new Exception("Cart is empty");
        }
        
        // Check terms acceptance
        if (!isset($_POST['terms'])) {
            throw new Exception("Please accept the terms and conditions");
        }
        
        $pdo->beginTransaction();
        
        // Insert order with correct column names matching your database schema
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, total_amount, status, order_date, 
                               billing_first_name, billing_last_name, billing_email, 
                               billing_phone, billing_address, billing_city, billing_country, billing_zip)
            VALUES (?, ?, 'pending', NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $address = trim($_POST['address1'] . ' ' . ($_POST['address2'] ?? ''));
        
        $stmt->execute([
            $user_id,
            $total,
            trim($_POST['first_name']),
            trim($_POST['last_name']),
            trim($_POST['email']),
            trim($_POST['phone']),
            $address,
            trim($_POST['city']),
            trim($_POST['country']),
            trim($_POST['zip'])
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        if (!$order_id) {
            throw new Exception("Failed to create order record");
        }
        
        // Insert order items and update stock
        foreach ($cart_items as $item) {
            // Check stock availability
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $current_stock = $stmt->fetchColumn();
            
            if ($current_stock < $item['quantity']) {
                throw new Exception("Insufficient stock for product: " . $item['name']);
            }
            
            // Insert order item
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
            
            // Update product stock
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Clear cart
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        
        // Redirect to confirmation page or dashboard
        header('Location: dashboard.php?order_placed=1');
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Error placing order: " . $e->getMessage();
        error_log("Order placement error: " . $e->getMessage());
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Database error: Please try again later.";
        error_log("PDO error in order placement: " . $e->getMessage());
    }
}

// If cart is empty, redirect to cart page
if (empty($cart_items) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit();
}

// Get cart count for header
$cartCount = 0;
try {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = $result['total'] ?? 0;
} catch (PDOException $e) {
    $cartCount = 0;
    error_log("Cart count error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zxx" class="no-js">

<head>
    <!-- Mobile Specific Meta -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Favicon-->
    <link rel="shortcut icon" href="img/fav.png">
    <!-- Author Meta -->
    <meta name="author" content="CodePixar">
    <!-- Meta Description -->
    <meta name="description" content="">
    <!-- Meta Keyword -->
    <meta name="keywords" content="">
    <!-- meta character set -->
    <meta charset="UTF-8">
    <!-- Site Title -->
    <title>Checkout | Facecap</title>

    <!--
            CSS
            ============================================= -->
    <link rel="stylesheet" href="css/linearicons.css">
    <link rel="stylesheet" href="css/owl.carousel.css">
    <link rel="stylesheet" href="css/themify-icons.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/nice-select.css">
    <link rel="stylesheet" href="css/nouislider.min.css">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .order_box {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .product-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .product-item:last-child {
            border-bottom: none;
        }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 15px;
        }
        .product-details {
            flex: 1;
        }
        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .product-category {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .product-price {
            font-weight: 600;
            color: #667eea;
        }
        .alert {
            margin-bottom: 20px;
        }
        .billing_details {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .checkout_area {
            padding: 80px 0;
            background: #f8f9fa;
        }
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .cart-link {
            position: relative;
            display: inline-block;
        }
        .form-error {
            border-color: #dc3545 !important;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body>

    <!-- Start Header Area -->
    <header class="header_area sticky-header">
        <div class="main_menu">
            <nav class="navbar navbar-expand-lg navbar-light main_box">
                <div class="container">
                    <!-- Brand and toggle get grouped for better mobile display -->
                    <a class="navbar-brand logo_h" href="index.html"><img src="img/logo.png" alt=""></a>
                    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
                     aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <!-- Collect the nav links, forms, and other content for toggling -->
                    <div class="collapse navbar-collapse offset" id="navbarSupportedContent">
                        <ul class="nav navbar-nav menu_nav ml-auto">
                            <li class="nav-item"><a class="nav-link" href="index.html">Home</a></li>
                            <li class="nav-item submenu dropdown active">
                                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                                 aria-expanded="false">Shop</a>
                                <ul class="dropdown-menu">
                                    <li class="nav-item"><a class="nav-link" href="category.html">Shop Category</a></li>
                                    <li class="nav-item"><a class="nav-link" href="single-product.html">Product Details</a></li>
                                    <li class="nav-item active"><a class="nav-link" href="checkout.php">Product Checkout</a></li>
                                    <li class="nav-item"><a class="nav-link" href="cart.php">Shopping Cart</a></li>
                                    <li class="nav-item"><a class="nav-link" href="confirmation.html">Confirmation</a></li>
                                </ul>
                            </li>
                            <li class="nav-item submenu dropdown">
                                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                                 aria-expanded="false">Pages</a>
                                <ul class="dropdown-menu">
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                                    <?php else: ?>
                                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                                    <?php endif; ?>
                                    <li class="nav-item"><a class="nav-link" href="tracking.html">Tracking</a></li>
                                    <li class="nav-item"><a class="nav-link" href="elements.html">Elements</a></li>
                                </ul>
                            </li>
                            <li class="nav-item"><a class="nav-link" href="contact.html">Contact</a></li>
                        </ul>
                        <ul class="nav navbar-nav navbar-right">
                            <li class="nav-item">
                                <a href="cart.php" class="cart cart-link">
                                    <span class="ti-bag"></span>
                                    <?php if ($cartCount > 0): ?>
                                        <span class="cart-badge"><?php echo $cartCount; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <button class="search"><span class="lnr lnr-magnifier" id="search"></span></button>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
        <div class="search_input" id="search_input_box">
            <div class="container">
                <form class="d-flex justify-content-between">
                    <input type="text" class="form-control" id="search_input" placeholder="Search Here">
                    <button type="submit" class="btn"></button>
                    <span class="lnr lnr-cross" id="close_search" title="Close Search"></span>
                </form>
            </div>
        </div>
    </header>
    <!-- End Header Area -->

    <!-- Start Banner Area -->
    <section class="banner-area organic-breadcrumb">
        <div class="container">
            <div class="breadcrumb-banner d-flex flex-wrap align-items-center justify-content-end">
                <div class="col-first">
                    <h1>Checkout</h1>
                    <nav class="d-flex align-items-center">
                        <a href="index.html">Home<span class="lnr lnr-arrow-right"></span></a>
                        <a href="checkout.php">Checkout</a>
                    </nav>
                </div>
            </div>
        </div>
    </section>
    <!-- End Banner Area -->

    <!--================Checkout Area =================-->
    <section class="checkout_area section_gap">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="billing_details">
                <div class="row">
                    <div class="col-lg-8">
                        <h3>Billing Details</h3>
                        <form class="row contact_form" method="post" novalidate="novalidate" id="checkout-form">
                            <div class="col-md-6 form-group p_star">
                                <input type="text" class="form-control" name="first_name" required 
                                       value="<?php echo htmlspecialchars($user_details['name'] ?? ''); ?>">
                                <span class="placeholder" data-placeholder="First name"></span>
                            </div>
                            <div class="col-md-6 form-group p_star">
                                <input type="text" class="form-control" name="last_name" required
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                <span class="placeholder" data-placeholder="Last name"></span>
                            </div>
                            <div class="col-md-12 form-group">
                                <input type="text" class="form-control" name="company" placeholder="Company name (optional)"
                                       value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 form-group p_star">
                                <input type="text" class="form-control" name="phone" required
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                <span class="placeholder" data-placeholder="Phone number"></span>
                            </div>
                            <div class="col-md-6 form-group p_star">
                                <input type="email" class="form-control" name="email" required 
                                       value="<?php echo htmlspecialchars($user_details['email'] ?? ''); ?>">
                                <span class="placeholder" data-placeholder="Email Address"></span>
                            </div>
                            <div class="col-md-12 form-group p_star">
                                <select class="country_select form-control" name="country" required>
                                    <option value="">Select Country</option>
                                    <option value="Nigeria" <?php echo (($_POST['country'] ?? '') == 'Nigeria') ? 'selected' : ''; ?>>Nigeria</option>
                                    <option value="United States" <?php echo (($_POST['country'] ?? '') == 'United States') ? 'selected' : ''; ?>>United States</option>
                                    <option value="United Kingdom" <?php echo (($_POST['country'] ?? '') == 'United Kingdom') ? 'selected' : ''; ?>>United Kingdom</option>
                                    <option value="Canada" <?php echo (($_POST['country'] ?? '') == 'Canada') ? 'selected' : ''; ?>>Canada</option>
                                    <option value="Australia" <?php echo (($_POST['country'] ?? '') == 'Australia') ? 'selected' : ''; ?>>Australia</option>
                                </select>
                            </div>
                            <div class="col-md-12 form-group p_star">
                                <input type="text" class="form-control" name="address1" required
                                       value="<?php echo htmlspecialchars($_POST['address1'] ?? ''); ?>">
                                <span class="placeholder" data-placeholder="Address line 01"></span>
                            </div>
                            <div class="col-md-12 form-group p_star">
                                <input type="text" class="form-control" name="address2"
                                       value="<?php echo htmlspecialchars($_POST['address2'] ?? ''); ?>">
                                <span class="placeholder" data-placeholder="Address line 02 (optional)"></span>
                            </div>
                            <div class="col-md-12 form-group p_star">
                                <input type="text" class="form-control" name="city" required
                                       value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                <span class="placeholder" data-placeholder="Town/City"></span>
                            </div>
                            <div class="col-md-12 form-group">
                                <input type="text" class="form-control" name="zip" placeholder="Postcode/ZIP" required
                                       value="<?php echo htmlspecialchars($_POST['zip'] ?? ''); ?>">
                            </div>
                            <div class="col-md-12 form-group">
                                <textarea class="form-control" name="order_notes" rows="3" placeholder="Order Notes (optional)"><?php echo htmlspecialchars($_POST['order_notes'] ?? ''); ?></textarea>
                            </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="order_box">
                            <h2>Your Order</h2>
                            
                            <!-- Order Items -->
                            <div class="order-items" style="margin-bottom: 20px;">
                                <?php foreach ($cart_items as $item): ?>
                                <div class="product-item">
                                    <?php if ($item['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="product-image">
                                    <?php else: ?>
                                        <div class="product-image d-flex align-items-center justify-content-center" 
                                             style="background: #f8f9fa;">
                                            <i class="fa fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-details">
                                        <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="product-category"><?php echo ucfirst(htmlspecialchars($item['category'])); ?></div>
                                        <div style="font-size: 14px; color: #666;">Qty: <?php echo $item['quantity']; ?></div>
                                    </div>
                                    
                                    <div class="product-price">
                                        $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Order Summary -->
                            <ul class="list list_2">
                                <li><a href="#">Subtotal <span>$<?php echo number_format($total, 2); ?></span></a></li>
                                <li><a href="#">Shipping <span>Free</span></a></li>
                                <li><a href="#">Total <span>$<?php echo number_format($total, 2); ?></span></a></li>
                            </ul>

                            <!-- Payment Options -->
                            <div class="payment_item active">
                                <div class="radion_btn">
                                    <input type="radio" id="cash_on_delivery" name="payment_method" value="cod" checked>
                                    <label for="cash_on_delivery">Cash on Delivery</label>
                                    <div class="check"></div>
                                </div>
                                <p>Pay with cash when your order is delivered to your doorstep.</p>
                            </div>

                            <div class="payment_item">
                                <div class="radion_btn">
                                    <input type="radio" id="bank_transfer" name="payment_method" value="bank">
                                    <label for="bank_transfer">Bank Transfer</label>
                                    <div class="check"></div>
                                </div>
                                <p>Make your payment directly into our bank account. Please use your Order ID as the payment reference.</p>
                            </div>

                            <div class="creat_account">
                                <input type="checkbox" id="terms" name="terms" required>
                                <label for="terms">I've read and accept the </label>
                                <a href="#">terms & conditions*</a>
                            </div>

                            <button type="submit" name="place_order" class="primary-btn" style="width: 100%; margin-top: 20px;">
                                Place Order
                            </button>
                        </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!--================End Checkout Area =================-->

    <!-- start footer Area -->
    <footer class="footer-area section_gap">
        <div class="container">
          <div class="row">
            <!-- About Us -->
            <div class="col-lg-3 col-md-6 col-sm-6">
              <div class="single-footer-widget">
                <h6>About Facecap</h6>
                <p>
                  Facecap is more than streetwear — it's a statement. We create bold, high-quality caps for those who lead, inspire, and set the trend.
                </p>
              </div>
            </div>
      
            <!-- Newsletter -->
            <div class="col-lg-4 col-md-6 col-sm-6">
              <div class="single-footer-widget">
                <h6>Newsletter</h6>
                <p>Get updates on exclusive drops, new arrivals, and weekly deals.</p>
                <div id="mc_embed_signup">
                  <form target="_blank" novalidate="true"
                    action="https://spondonit.us12.list-manage.com/subscribe/post?u=1462626880ade1ac87bd9c93a&amp;id=92a4423d01"
                    method="get" class="form-inline">
                    <div class="d-flex flex-row">
                      <input class="form-control" name="EMAIL" placeholder="Enter Email" onfocus="this.placeholder = ''"
                        onblur="this.placeholder = 'Enter Email'" required="" type="email">
                      <button class="click-btn btn btn-default"><i class="fa fa-long-arrow-right" aria-hidden="true"></i></button>
                      <div style="position: absolute; left: -5000px;">
                        <input name="b_36c4fd991d266f23781ded980_aefe40901a" tabindex="-1" value="" type="text">
                      </div>
                    </div>
                    <div class="info"></div>
                  </form>
                </div>
              </div>
            </div>
      
            <!-- Instagram Feed -->
            <div class="col-lg-3 col-md-6 col-sm-6">
              <div class="single-footer-widget mail-chimp">
                <h6 class="mb-20">Instagram Feed</h6>
                <ul class="instafeed d-flex flex-wrap">
                  <li><img src="img/i1.jpg" alt="cap 1"></li>
                  <li><img src="img/i2.jpg" alt="cap 2"></li>
                  <li><img src="img/i3.jpg" alt="cap 3"></li>
                  <li><img src="img/i4.jpg" alt="cap 4"></li>
                  <li><img src="img/i5.jpg" alt="cap 5"></li>
                  <li><img src="img/i6.jpg" alt="cap 6"></li>
                  <li><img src="img/i7.jpg" alt="cap 7"></li>
                  <li><img src="img/i8.jpg" alt="cap 8"></li>
                </ul>
              </div>
            </div>
      
            <!-- Social Media -->
            <div class="col-lg-2 col-md-6 col-sm-6">
              <div class="single-footer-widget">
                <h6>Follow Us</h6>
                <p>Join our streetwear family</p>
                <div class="footer-social d-flex align-items-center">
                  <a href="#"><i class="fa fa-facebook"></i></a>
                  <a href="#"><i class="fa fa-twitter"></i></a>
                  <a href="#"><i class="fa fa-instagram"></i></a>
                  <a href="#"><i class="fa fa-tiktok"></i></a>
                </div>
              </div>
            </div>
          </div>
      
          <!-- Bottom Footer -->
          <div class="footer-bottom d-flex justify-content-center align-items-center flex-wrap">
            <p class="footer-text m-0">
              &copy;<script>document.write(new Date().getFullYear());</script> Facecap | Built for culture, powered by creativity
            </p>
          </div>
        </div>
      </footer>
      <!-- End footer Area -->

    <script src="js/vendor/jquery-2.2.4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js" integrity="sha384-b/U6ypiBEHpOf/4+1nzFpr53nxSS+GLCkfwBdFNTxtclqqenISfwAzpKaMNFNmj4"
        crossorigin="anonymous"></script>
    <script src="js/vendor/bootstrap.min.js"></script>
    <script src="js/jquery.ajaxchimp.min.js"></script>
    <script src="js/jquery.nice-select.min.js"></script>
    <script src="js/jquery.sticky.js"></script>
    <script src="js/nouislider.min.js"></script>
    <script src="js/jquery.magnific-popup.min.js"></script>
    <script src="js/owl.carousel.min.js"></script>
    <!--gmaps Js-->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCjCGmQ0Uq4exrzdcL6rvxywDDOvfAu6eE"></script>
    <script src="js/gmaps.min.js"></script>
    <script src="js/main.js"></script>

    <script>
    document.getElementById('checkout-form').addEventListener('submit', function(e) {
        // Basic client-side validation
        const required = ['first_name', 'last_name', 'email', 'phone', 'address1', 'city', 'country', 'zip'];
        let hasError = false;
        
        required.forEach(function(field) {
            const input = document.getElementsByName(field)[0];
            if (!input.value.trim()) {
                input.classList.add('form-error');
                hasError = true;
            } else {
                input.classList.remove('form-error');
            }
        });
        
        const termsCheckbox = document.getElementById('terms');
        if (!termsCheckbox.checked) {
            alert('Please accept the terms and conditions');
            hasError = true;
        }
        
        if (hasError) {
            e.preventDefault();
            return false;
        }
    });
    </script>
</body>

</html>