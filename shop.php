<?php
session_start();
require_once 'config.php';

// Get all products
try {
    $pdo = getConnection();
    $stmt = $pdo->query("SELECT * FROM products WHERE stock > 0 ORDER BY created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
}

// Get cart count if user is logged in
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
	<title>Shop | Facecap</title>
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
		.shop-section {
			padding: 80px 0;
		}
		.product-card {
			background: white;
			border-radius: 10px;
			overflow: hidden;
			transition: transform 0.3s ease, box-shadow 0.3s ease;
			margin-bottom: 30px;
			box-shadow: 0 5px 20px rgba(0,0,0,0.08);
		}
		.product-card:hover {
			transform: translateY(-5px);
			box-shadow: 0 15px 40px rgba(0,0,0,0.15);
		}
		.product-image {
			width: 100%;
			height: 250px;
			object-fit: cover;
			transition: transform 0.3s ease;
		}
		.product-card:hover .product-image {
			transform: scale(1.05);
		}
		.product-details {
			padding: 20px;
		}
		.product-title {
			font-size: 18px;
			font-weight: 600;
			margin-bottom: 10px;
			color: #333;
		}
		.product-category {
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
		.product-price {
			font-size: 20px;
			font-weight: bold;
			color: #667eea;
			margin-bottom: 15px;
		}
		.product-stock {
			font-size: 12px;
			color: #28a745;
			margin-bottom: 15px;
		}
		.add-to-cart-btn {
			width: 100%;
			padding: 12px;
			background: #667eea;
			color: white;
			border: none;
			border-radius: 5px;
			font-weight: 600;
			transition: background 0.3s ease;
		}
		.add-to-cart-btn:hover {
			background: #5a6fd8;
			color: white;
		}
		.no-products {
			text-align: center;
			padding: 100px 0;
		}
		.no-products i {
			font-size: 4rem;
			color: #ccc;
			margin-bottom: 30px;
		}
		.filter-section {
			background: #f8f9fa;
			padding: 30px 0;
			margin-bottom: 50px;
		}
		.filter-btn {
			margin: 5px;
			padding: 8px 20px;
			border: 1px solid #ddd;
			background: white;
			border-radius: 25px;
			transition: all 0.3s ease;
		}
		.filter-btn:hover,
		.filter-btn.active {
			background: #667eea;
			color: white;
			border-color: #667eea;
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
		.login-prompt {
			background: #fff3cd;
			border: 1px solid #ffeaa7;
			color: #856404;
			padding: 15px;
			border-radius: 5px;
			margin: 20px 0;
			text-align: center;
		}
		.login-prompt a {
			color: #667eea;
			font-weight: bold;
			text-decoration: none;
		}
		.login-prompt a:hover {
			text-decoration: underline;
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

	<!-- Start Shop Hero Area -->
	<section class="shop-hero">
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-lg-8 text-center">
					<h1>Shop Collection</h1>
					<p>Discover our premium streetwear caps. Bold designs, premium quality, authentic culture.</p>
				</div>
			</div>
		</div>
	</section>
	<!-- End Shop Hero Area -->

	<!-- Login Prompt for Non-logged in users -->
	<?php if (!isset($_SESSION['user_id'])): ?>
	<div class="container">
		<div class="login-prompt">
			<strong>Want to add items to your cart?</strong> 
			<a href="login.php">Sign in to your account</a> or 
			<a href="register.php">create a new account</a> to start shopping!
		</div>
	</div>
	<?php endif; ?>

	<!-- Start Filter Section -->
	<section class="filter-section">
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-lg-8 text-center">
					<button class="filter-btn active" data-filter="all">All Products</button>
					<button class="filter-btn" data-filter="caps">Caps</button>
					<button class="filter-btn" data-filter="snapback">Snapback</button>
					<button class="filter-btn" data-filter="bucket">Bucket Hats</button>
					<button class="filter-btn" data-filter="trucker">Trucker</button>
				</div>
			</div>
		</div>
	</section>
	<!-- End Filter Section -->

	<!-- Start Shop Products Area -->
	<section class="shop-section">
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-lg-6 text-center">
					<div class="section-title">
						<h1>Our Products</h1>
						<p>Handpicked collection of premium caps and accessories for the modern streetwear enthusiast.</p>
					</div>
				</div>
			</div>

			<?php if (!empty($products)): ?>
			<div class="row">
				<?php foreach ($products as $product): ?>
				<div class="col-lg-3 col-md-6">
					<div class="product-card">
						<?php if ($product['image_path']): ?>
							<img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
								 alt="<?php echo htmlspecialchars($product['name']); ?>" 
								 class="product-image">
						<?php else: ?>
							<div class="product-image d-flex align-items-center justify-content-center" style="background: #f8f9fa;">
								<i class="fa fa-image fa-3x text-muted"></i>
							</div>
						<?php endif; ?>
						
						<div class="product-details">
							<span class="product-category"><?php echo ucfirst(htmlspecialchars($product['category'])); ?></span>
							<h6 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h6>
							<p style="font-size: 14px; color: #666; margin-bottom: 15px;">
								<?php echo htmlspecialchars(substr($product['description'], 0, 80)) . '...'; ?>
							</p>
							<div class="price">
								<h6 class="product-price">$<?php echo number_format($product['price'], 2); ?></h6>
							</div>
							<div class="product-stock"><?php echo $product['stock']; ?> in stock</div>
							<div class="prd-bottom">
								<?php if (isset($_SESSION['user_id'])): ?>
									<button class="add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">
										<span class="ti-bag"></span> Add to Bag
									</button>
								<?php else: ?>
									<button class="add-to-cart-btn" onclick="window.location.href='login.php'">
										<span class="ti-user"></span> Login to Add to Bag
									</button>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php else: ?>
			<div class="no-products">
				<div class="row justify-content-center">
					<div class="col-lg-6">
						<i class="fa fa-shopping-bag"></i>
						<h4>No Products Available</h4>
						<p>Our collection is being updated. Check back soon for fresh drops and exclusive releases!</p>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</section>
	<!-- End Shop Products Area -->

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

	<script>
	// Filter functionality
	$(document).ready(function() {
		$('.filter-btn').on('click', function() {
			$('.filter-btn').removeClass('active');
			$(this).addClass('active');
			
			var filter = $(this).data('filter');
			
			if (filter === 'all') {
				$('.product-card').parent().show();
			} else {
				$('.product-card').parent().hide();
				$('.product-category').each(function() {
					if ($(this).text().toLowerCase().includes(filter)) {
						$(this).closest('.product-card').parent().show();
					}
				});
			}
		});
		
		// Add to cart functionality (only for logged in users)
		$('.add-to-cart-btn[data-product-id]').on('click', function() {
			var productId = $(this).data('product-id');
			var button = $(this);
			var originalText = button.html();
			
			button.html('<i class="fa fa-spinner fa-spin"></i> Adding...');
			button.prop('disabled', true);
			
			// AJAX call to add to cart
			$.ajax({
				url: 'add_to_cart.php',
				method: 'POST',
				data: {
					product_id: productId,
					quantity: 1
				},
				dataType: 'json',
				success: function(response) {
					if (response.success) {
						button.html('<i class="fa fa-check"></i> Added!');
						button.css('background', '#28a745');
						
						// Update cart count in header
						var cartBadge = $('.cart-badge');
						if (cartBadge.length) {
							var currentCount = parseInt(cartBadge.text()) || 0;
							cartBadge.text(currentCount + 1);
						} else {
							$('.cart-link').append('<span class="cart-badge">1</span>');
						}
						
						setTimeout(function() {
							button.html(originalText);
							button.css('background', '');
							button.prop('disabled', false);
						}, 2000);
					} else {
						button.html('<i class="fa fa-exclamation"></i> Error!');
						button.css('background', '#dc3545');
						
						setTimeout(function() {
							button.html(originalText);
							button.css('background', '');
							button.prop('disabled', false);
						}, 2000);
						
						if (response.message) {
							alert(response.message);
						}
					}
				},
				error: function() {
					button.html('<i class="fa fa-exclamation"></i> Error!');
					button.css('background', '#dc3545');
					
					setTimeout(function() {
						button.html(originalText);
						button.css('background', '');
						button.prop('disabled', false);
					}, 2000);
					
					alert('Failed to add item to cart. Please try again.');
				}
			});
		});
	});
	</script>
</body>

</html>