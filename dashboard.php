<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// Get user details
$user_details = [];
$orders = [];
$orderStats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'delivered_orders' => 0,
    'total_spent' => 0
];

try {
    $pdo = getConnection();
    
    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get order statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            SUM(total_amount) as total_spent
        FROM orders 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        $orderStats = [
            'total_orders' => $stats['total_orders'] ?? 0,
            'pending_orders' => $stats['pending_orders'] ?? 0,
            'delivered_orders' => $stats['delivered_orders'] ?? 0,
            'total_spent' => $stats['total_spent'] ?? 0
        ];
    }
    
    // Get recent orders with items
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.total_amount,
            o.status,
            o.created_at,
            o.billing_first_name,
            o.billing_last_name,
            COUNT(oi.id) as item_count,
            GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $message = "Error loading dashboard data.";
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
}

// Check for order placed message
$orderPlaced = isset($_GET['order_placed']) && $_GET['order_placed'] == '1';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Dashboard | Facecap</title>
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/themify-icons.css">
  <link rel="stylesheet" href="css/main.css">
  <style>
    .dashboard-container {
      margin-top: 60px;
      padding: 40px 0;
      background: #f8f9fa;
      min-height: 100vh;
    }

    .sidebar {
      background: white;
      border-radius: 10px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.08);
      padding: 0;
      overflow: hidden;
    }

    .sidebar-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 30px 20px;
      text-align: center;
    }

    .sidebar-header h5 {
      margin: 0;
      font-weight: 600;
    }

    .sidebar a {
      display: block;
      padding: 15px 25px;
      color: #333;
      text-decoration: none;
      border-bottom: 1px solid #f0f0f0;
      transition: all 0.3s ease;
    }

    .sidebar a:hover {
      background-color: #f8f9fa;
      color: #667eea;
      text-decoration: none;
    }

    .sidebar a.active {
      background-color: #667eea;
      color: white;
    }

    .dashboard-content {
      padding: 0 30px;
    }

    .welcome-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .stat-card {
      background: white;
      border-radius: 15px;
      padding: 25px;
      text-align: center;
      box-shadow: 0 5px 20px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 20px;
      transition: transform 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-3px);
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: 700;
      color: #667eea;
      margin-bottom: 10px;
    }

    .stat-label {
      color: #666;
      font-weight: 500;
      text-transform: uppercase;
      font-size: 0.9rem;
      letter-spacing: 1px;
    }

    .orders-card {
      background: white;
      border-radius: 15px;
      padding: 30px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.08);
      border: none;
    }

    .orders-card h5 {
      color: #333;
      font-weight: 600;
      margin-bottom: 25px;
      font-size: 1.4rem;
    }

    .table {
      margin-bottom: 0;
    }

    .table th {
      border-top: none;
      font-weight: 600;
      color: #666;
      text-transform: uppercase;
      font-size: 0.85rem;
      letter-spacing: 1px;
      padding: 15px;
    }

    .table td {
      padding: 15px;
      vertical-align: middle;
    }

    .badge {
      padding: 8px 12px;
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .badge-warning {
      background: #ffeaa7;
      color: #d63031;
    }

    .badge-success {
      background: #55efc4;
      color: #00b894;
    }

    .badge-info {
      background: #74b9ff;
      color: #0984e3;
    }

    .no-orders {
      text-align: center;
      padding: 60px 20px;
      color: #666;
    }

    .no-orders i {
      font-size: 4rem;
      color: #ddd;
      margin-bottom: 20px;
    }

    .alert-success {
      background: linear-gradient(135deg, #55efc4 0%, #00b894 100%);
      border: none;
      color: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 30px;
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

    .product-names {
      max-width: 250px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  </style>
</head>

<body>
  <!-- Start Header Area -->
  <header class="header_area sticky-header">
    <div class="main_menu">
      <nav class="navbar navbar-expand-lg navbar-light main_box">
        <div class="container">
          <a class="navbar-brand logo_h" href="index.html"><img src="img/logo.png" alt=""></a>
          <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
           aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <div class="collapse navbar-collapse offset" id="navbarSupportedContent">
            <ul class="nav navbar-nav menu_nav ml-auto">
              <li class="nav-item"><a class="nav-link" href="index.html">Home</a></li>
              <li class="nav-item submenu dropdown">
                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true"
                 aria-expanded="false">Shop</a>
                <ul class="dropdown-menu">
                  <li class="nav-item"><a class="nav-link" href="category.html">Shop Category</a></li>
                  <li class="nav-item"><a class="nav-link" href="single-product.html">Product Details</a></li>
                  <li class="nav-item"><a class="nav-link" href="checkout.php">Product Checkout</a></li>
                  <li class="nav-item"><a class="nav-link" href="cart.php">Shopping Cart</a></li>
                </ul>
              </li>
              <li class="nav-item active"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
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

  <div class="container-fluid dashboard-container">
    <div class="container">
      <?php if ($orderPlaced): ?>
        <div class="alert alert-success">
          <i class="fa fa-check-circle"></i> 
          <strong>Order Placed Successfully!</strong> Your order has been received and is being processed.
        </div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>

      <div class="row">
        <div class="col-md-3">
          <div class="sidebar">
            <div class="sidebar-header">
              <h5>My Account</h5>
            </div>
            <a href="dashboard.php" class="active"><i class="fa fa-dashboard"></i> Dashboard</a>
            <a href="orders.php"><i class="fa fa-shopping-bag"></i> My Orders</a>
            <a href="profile.php"><i class="fa fa-user"></i> Profile</a>
            <a href="wishlist.php"><i class="fa fa-heart"></i> Wishlist</a>
            <a href="logout.php"><i class="fa fa-sign-out"></i> Logout</a>
          </div>
        </div>
        
        <div class="col-md-9 dashboard-content">
          <!-- Welcome Card -->
          <div class="welcome-card">
            <h3>Welcome back, <?php echo htmlspecialchars($user_details['name'] ?? 'Customer'); ?>!</h3>
            <p>Here's a quick overview of your account activity and recent orders.</p>
          </div>

          <!-- Statistics Cards -->
          <div class="row">
            <div class="col-md-3">
              <div class="stat-card">
                <div class="stat-number"><?php echo $orderStats['total_orders']; ?></div>
                <div class="stat-label">Total Orders</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="stat-card">
                <div class="stat-number"><?php echo $orderStats['pending_orders']; ?></div>
                <div class="stat-label">Pending Orders</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="stat-card">
                <div class="stat-number"><?php echo $orderStats['delivered_orders']; ?></div>
                <div class="stat-label">Delivered</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($orderStats['total_spent'], 0); ?></div>
                <div class="stat-label">Total Spent</div>
              </div>
            </div>
          </div>

          <!-- Recent Orders -->
          <div class="orders-card">
            <h5><i class="fa fa-shopping-bag"></i> Recent Orders</h5>
            
            <?php if (empty($orders)): ?>
              <div class="no-orders">
                <i class="fa fa-shopping-bag"></i>
                <h4>No Orders Yet</h4>
                <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
                <a href="shop.php" class="btn btn-primary">Start Shopping</a>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Order ID</th>
                      <th>Date</th>
                      <th>Products</th>
                      <th>Items</th>
                      <th>Status</th>
                      <th>Total</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                      <td><strong>#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                      <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                      <td>
                        <div class="product-names" title="<?php echo htmlspecialchars($order['product_names']); ?>">
                          <?php echo htmlspecialchars($order['product_names'] ?: 'No products'); ?>
                        </div>
                      </td>
                      <td><?php echo $order['item_count']; ?> item<?php echo $order['item_count'] != 1 ? 's' : ''; ?></td>
                      <td>
                        <?php
                        $statusClass = 'badge-info';
                        $statusText = ucfirst($order['status']);
                        
                        switch ($order['status']) {
                          case 'pending':
                            $statusClass = 'badge-warning';
                            break;
                          case 'delivered':
                            $statusClass = 'badge-success';
                            break;
                          case 'shipped':
                            $statusClass = 'badge-info';
                            break;
                          case 'cancelled':
                            $statusClass = 'badge-danger';
                            break;
                        }
                        ?>
                        <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                      </td>
                      <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                      <td>
                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                          View Details
                        </a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              
              <?php if (count($orders) >= 10): ?>
                <div class="text-center mt-3">
                  <a href="orders.php" class="btn btn-primary">View All Orders</a>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

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
  <script src="js/main.js"></script>
</body>

</html>