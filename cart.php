<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_items = [];
$total = 0;

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT c.id, c.quantity, p.name, p.price, p.image_path, p.stock, p.category, p.description
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
}

// Get cart count
$cartCount = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $cartCount = $result['total'] ?? 0;
    } catch (PDOException $e) {
        $cartCount = 0;
    }
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
	<title>Cart | Facecap</title>
	<!--
		CSS
		============================================= -->
	<link rel="stylesheet" href="css/linearicons.css">
	<link rel="stylesheet" href="css/font-awesome.min.css">
	<link rel="stylesheet" href="css/themify-icons.css">
	<link rel="stylesheet" href="css/bootstrap.css">
	<link rel="stylesheet" href="css/owl.carousel.css">
	<link rel="stylesheet" href="css/nice-select.css">
	<link rel="stylesheet" href="css/nouislider.min.css">
	<link rel="stylesheet" href="css/ion.rangeSlider.css" />
	<link rel="stylesheet" href="css/ion.rangeSlider.skinFlat.css" />
	<link rel="stylesheet" href="css/magnific-popup.css">
	<link rel="stylesheet" href="css/main.css">
	<style>
		.shop-hero {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			padding: 120px 0 80px 0;
			color: white;
		}
		.shop-hero h1 {
			font-size: 3rem;
			font-weight: bold;
			margin-bottom: 20px;
		}
		.shop-hero p {
			font-size: 1.2rem;
			opacity: 0.9;
		}
		.cart-section {
			padding: 80px 0;
		}
		.cart-item-card {
			background: white;
			border-radius: 10px;
			overflow: hidden;
			transition: transform 0.3s ease, box-shadow 0.3s ease;
			margin-bottom: 30px;
			box-shadow: 0 5px 20px rgba(0,0,0,0.08);
			display: flex;
			align-items: center;
			padding: 20px;
		}
		.cart-item-card:hover {
			transform: translateY(-2px);
			box-shadow: 0 15px 40px rgba(0,0,0,0.15);
		}
		.cart-item-image {
			width: 120px;
			height: 120px;
			object-fit: cover;
			border-radius: 8px;
			margin-right: 20px;
		}
		.cart-item-details {
			flex: 1;
		}
		.cart-item-title {
			font-size: 18px;
			font-weight: 600;
			margin-bottom: 10px;
			color: #333;
		}
		.cart-item-category {
			display: inline-block;
			background: #f8f9fa;
			padding: 4px 12px;
			border-radius: 20px;
			font-size: 12px;
			color: #666;
			margin-bottom: 10px;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		.cart-item-price {
			font-size: 18px;
			font-weight: bold;
			color: #667eea;
			margin-bottom: 10px;
		}
		.cart-item-controls {
			display: flex;
			align-items: center;
			gap: 15px;
		}
		.quantity-controls {
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.quantity-input {
			width: 60px;
			text-align: center;
			border: 1px solid #ddd;
			border-radius: 5px;
			padding: 5px;
		}
		.update-btn, .remove-btn {
			padding: 8px 15px;
			border: none;
			border-radius: 5px;
			font-weight: 600;
			transition: all 0.3s ease;
			cursor: pointer;
		}
		.update-btn {
			background: #28a745;
			color: white;
		}
		.update-btn:hover {
			background: #218838;
		}
		.remove-btn {
			background: #dc3545;
			color: white;
		}
		.remove-btn:hover {
			background: #c82333;
		}
		.cart-summary {
			background: white;
			border-radius: 10px;
			padding: 30px;
			box-shadow: 0 5px 20px rgba(0,0,0,0.08);
			margin-bottom: 30px;
		}
		.summary-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 15px 0;
			border-bottom: 1px solid #eee;
		}
		.summary-row:last-child {
			border-bottom: none;
			font-weight: bold;
			font-size: 18px;
			color: #667eea;
		}
		.checkout-btn {
			width: 100%;
			padding: 15px;
			background: #667eea;
			color: white;
			border: none;
			border-radius: 5px;
			font-weight: 600;
			font-size: 16px;
			transition: background 0.3s ease;
			text-decoration: none;
			display: inline-block;
			text-align: center;
		}
		.checkout-btn:hover {
			background: #5a6fd8;
			color: white;
			text-decoration: none;
		}
		.empty-cart {
			text-align: center;
			padding: 100px 0;
		}
		.empty-cart i {
			font-size: 6rem;
			color: #ccc;
			margin-bottom: 30px;
		}
		.empty-cart h4 {
			margin-bottom: 20px;
			color: #666;
		}
		.continue-shopping-btn {
			padding: 12px 30px;
			background: #667eea;
			color: white;
			border: none;
			border-radius: 5px;
			font-weight: 600;
			transition: background 0.3s ease;
			text-decoration: none;
		}
		.continue-shopping-btn:hover {
			background: #5a6fd8;
			color: white;
			text-decoration: none;
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
		.subtotal {
			font-size: 14px;
			color: #666;
			margin-top: 5px;
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
							<li class="nav-item submenu dropdown">
								<a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
								 aria-expanded="false">Shop</a>
								<ul class="dropdown-menu">
									<li class="nav-item"><a class="nav-link" href="category.html">Shop Category</a></li>
									<li class="nav-item"><a class="nav-link" href="single-product.html">Product Details</a></li>
									<li class="nav-item"><a class="nav-link" href="checkout.html">Product Checkout</a></li>
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

	<!-- Start Cart Hero Area -->
	<section class="shop-hero">
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-lg-8 text-center">
					<h1>Shopping Cart</h1>
					<p>Review your selected items and proceed to checkout when ready.</p>
				</div>
			</div>
		</div>
	</section>
	<!-- End Cart Hero Area -->

	<!-- Start Cart Items Area -->
	<section class="cart-section">
		<div class="container">
			<?php if (!empty($cart_items)): ?>
			<div class="row">
				<div class="col-lg-8">
					<div class="row justify-content-center">
						<div class="col-lg-12 text-center mb-5">
							<div class="section-title">
								<h2>Your Items</h2>
								<p>Manage the items in your cart before checkout.</p>
							</div>
						</div>
					</div>
					
					<?php foreach ($cart_items as $item): ?>
					<div class="cart-item-card">
						<?php if ($item['image_path']): ?>
							<img src="<?php echo htmlspecialchars($item['image_path']); ?>" 
								 alt="<?php echo htmlspecialchars($item['name']); ?>" 
								 class="cart-item-image">
						<?php else: ?>
							<div class="cart-item-image d-flex align-items-center justify-content-center" style="background: #f8f9fa;">
								<i class="fa fa-image fa-2x text-muted"></i>
							</div>
						<?php endif; ?>
						
						<div class="cart-item-details">
							<span class="cart-item-category"><?php echo ucfirst(htmlspecialchars($item['category'])); ?></span>
							<h6 class="cart-item-title"><?php echo htmlspecialchars($item['name']); ?></h6>
							<p style="font-size: 14px; color: #666; margin-bottom: 10px;">
								<?php echo htmlspecialchars(substr($item['description'], 0, 80)) . '...'; ?>
							</p>
							<div class="cart-item-price">$<?php echo number_format($item['price'], 2); ?></div>
							<div class="subtotal">Subtotal: $<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
						</div>
						
						<div class="cart-item-controls">
							<form method="POST" action="update_cart.php" style="display: inline-block;">
								<input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
								<div class="quantity-controls">
									<label for="quantity" style="font-size: 14px; color: #666;">Qty:</label>
									<input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
										   min="1" max="<?php echo $item['stock']; ?>" class="quantity-input">
									<button type="submit" class="update-btn">
										<i class="ti-check"></i> Update
									</button>
								</div>
							</form>
							<form method="POST" action="remove_from_cart.php" style="display: inline-block;">
								<input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
								<button type="submit" class="remove-btn" onclick="return confirm('Remove this item from cart?')">
									<i class="ti-trash"></i> Remove
								</button>
							</form>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				
				<div class="col-lg-4">
					<div class="cart-summary">
						<h4 style="margin-bottom: 30px; color: #333;">Order Summary</h4>
						
						<div class="summary-row">
							<span>Items (<?php echo count($cart_items); ?>)</span>
							<span>$<?php echo number_format($total, 2); ?></span>
						</div>
						
						<div class="summary-row">
							<span>Shipping</span>
							<span>Free</span>
						</div>
						
						<div class="summary-row">
							<span>Total</span>
							<span>$<?php echo number_format($total, 2); ?></span>
						</div>
						
						<div style="margin-top: 30px;">
							<a href="checkout.php" class="checkout-btn">
								<i class="ti-credit-card"></i> Proceed to Checkout
							</a>
						</div>
						
						<div style="margin-top: 15px;">
							<a href="shop.php" class="continue-shopping-btn">
								<i class="ti-arrow-left"></i> Continue Shopping
							</a>
						</div>
					</div>
				</div>
			</div>
			<?php else: ?>
			<div class="empty-cart">
				<div class="row justify-content-center">
					<div class="col-lg-6">
						<i class="ti-shopping-cart"></i>
						<h4>Your cart is empty</h4>
						<p>Looks like you haven't added any items to your cart yet. Start shopping to build your streetwear collection!</p>
						<a href="shop.php" class="continue-shopping-btn">
							<i class="ti-bag"></i> Start Shopping
						</a>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</section>
	<!-- End Cart Items Area -->

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
	<script src="js/countdown.js"></script>
	<script src="js/jquery.magnific-popup.min.js"></script>
	<script src="js/owl.carousel.min.js"></script>
	<!--gmaps Js-->
	<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCjCGmQ0Uq4exrzdcL6rvxywDDOvfAu6eE"></script>
	<script src="js/gmaps.min.js"></script>
	<script src="js/main.js"></script>
</body>

</html>