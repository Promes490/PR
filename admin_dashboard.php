<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo = getConnection();
        
        // Verify admin status
        $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['is_admin'] != 1) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
            exit();
        }
        
        switch ($_POST['action']) {
            case 'add_user':
                $fullname = trim($_POST['fullname']);
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = trim($_POST['password']);
                $is_admin = isset($_POST['is_admin']) ? 1 : 0;
                
                // Validation
                if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
                    echo json_encode(['success' => false, 'message' => 'All fields are required']);
                    exit();
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                    exit();
                }
                
                if (strlen($password) < 6) {
                    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                    exit();
                }
                
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
                    exit();
                }
                
                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (fullname, username, email, password, is_admin) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$fullname, $username, $email, $hashed_password, $is_admin]);
                
                echo json_encode(['success' => true, 'message' => 'User added successfully']);
                break;
                
            case 'delete_user':
                $user_id = (int)$_POST['user_id'];
                
                // Prevent admin from deleting themselves
                if ($user_id == $_SESSION['user_id']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
                    exit();
                }
                
                // Check if user exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->fetchColumn() == 0) {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    exit();
                }
                
                // Delete user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                break;
                
            case 'toggle_admin':
                $user_id = (int)$_POST['user_id'];
                
                // Prevent admin from removing their own admin status
                if ($user_id == $_SESSION['user_id']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot modify your own admin status']);
                    exit();
                }
                
                // Get current admin status
                $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $current_status = $stmt->fetchColumn();
                
                if ($current_status === false) {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                    exit();
                }
                
                // Toggle admin status
                $new_status = $current_status ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'User role updated successfully']);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit();
}

try {
    $pdo = getConnection();

    // Check if user is admin
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['is_admin'] != 1) {
        // Not an admin, redirect to user dashboard
        header("Location: dashboard.php");
        exit();
    }

    // Get total users
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'] ?? 0;

    // Get total admins
    $stmt = $pdo->query("SELECT COUNT(*) as total_admins FROM users WHERE is_admin = 1");
    $totalAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['total_admins'] ?? 0;

    // Get total products
    $stmt = $pdo->query("SELECT COUNT(*) as total_products FROM products");
    $totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'] ?? 0;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Facecap</title>
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .dashboard-container {
            margin-top: 80px;
            margin-bottom: 50px;
        }
        .admin-badge {
            background-color: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 5px;
        }
        .alert {
            margin-bottom: 20px;
        }
        .table-actions button {
            margin: 2px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0;
            font-size: 2.5rem;
        }
        .stat-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            background: none;
            padding: 15px 20px;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
        }
        .nav-tabs .nav-link.active {
            background: #007bff;
            color: white;
            border: none;
        }
        .tab-content {
            background: white;
            border-radius: 0 10px 10px 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <header class="header_area sticky-header">
        <div class="main_menu">
            <nav class="navbar navbar-expand-lg navbar-light main_box">
                <div class="container">
                    <a class="navbar-brand logo_h" href="index.html"><img src="img/logo.png" alt=""></a>
                    <div class="collapse navbar-collapse offset">
                        <ul class="nav navbar-nav menu_nav ml-auto">
                            <li class="nav-item"><a class="nav-link" href="index.html">Home</a></li>
                            <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                            <li class="nav-item"><a class="nav-link" href="dashboard.php">User Dashboard</a></li>
                            <li class="nav-item active"><a class="nav-link" href="admin_dashboard.php">Admin Panel</a></li>
                            <li class="nav-item"><a class="nav-link" href="upload.php">Upload Products</a></li>
                            <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <div class="container dashboard-container">
        <!-- Alert container -->
        <div id="alert-container"></div>

        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user']); ?>! <span class="admin-badge">ADMIN</span></h2>
                <p class="text-muted">Manage your system from this admin panel</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <h3 id="total-users-count"><?php echo $totalUsers; ?></h3>
                    <p><i class="fa fa-users"></i> Total Users</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h3 id="total-products-count"><?php echo $totalProducts; ?></h3>
                    <p><i class="fa fa-box"></i> Products</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h3>0</h3>
                    <p><i class="fa fa-shopping-cart"></i> Orders</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <h3 id="total-admins-count"><?php echo $totalAdmins; ?></h3>
                    <p><i class="fa fa-user-shield"></i> Admins</p>
                </div>
            </div>
        </div>

        <!-- Main Content with Tabs -->
        <div class="card">
            <div class="card-header" style="background: #f8f9fa; border-bottom: none;">
                <ul class="nav nav-tabs card-header-tabs" id="adminTabs">
                    <li class="nav-item">
                        <a class="nav-link active" id="dashboard-tab" data-toggle="tab" href="#dashboard">
                            <i class="fa fa-tachometer-alt mr-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="products-tab" data-toggle="tab" href="#products">
                            <i class="fa fa-box mr-2"></i>Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="orders-tab" data-toggle="tab" href="#orders">
                            <i class="fa fa-shopping-cart mr-2"></i>Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="users-tab" data-toggle="tab" href="#users">
                            <i class="fa fa-users mr-2"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="settings-tab" data-toggle="tab" href="#settings">
                            <i class="fa fa-cog mr-2"></i>Settings
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="tab-content">
                <!-- Dashboard Tab -->
                <div class="tab-pane fade show active" id="dashboard">
                    <h4>Quick Actions</h4>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="upload.php" class="btn btn-primary btn-block btn-lg">
                                <i class="fa fa-plus mr-2"></i>Add Product
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <button class="btn btn-outline-primary btn-block btn-lg" onclick="$('#users-tab').tab('show')">
                                <i class="fa fa-users mr-2"></i>Manage Users
                            </button>
                        </div>
                        <div class="col-md-3 mb-3">
                            <button class="btn btn-outline-primary btn-block btn-lg" onclick="$('#orders-tab').tab('show')">
                                <i class="fa fa-shopping-cart mr-2"></i>View Orders
                            </button>
                        </div>
                        <div class="col-md-3 mb-3">
                            <button class="btn btn-outline-secondary btn-block btn-lg" onclick="$('#settings-tab').tab('show')">
                                <i class="fa fa-cog mr-2"></i>Settings
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h4>Recent Activity</h4>
                        <p class="text-muted">System activity and recent changes will appear here.</p>
                    </div>
                </div>

                <!-- Products Tab -->
                <div class="tab-pane fade" id="products">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4><i class="fa fa-box mr-2"></i>Product Management</h4>
                        <a href="upload.php" class="btn btn-success">
                            <i class="fa fa-plus mr-2"></i>Add New Product
                        </a>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card p-3 text-center">
                                <h5>Total Products</h5>
                                <p class="h4 text-primary"><?php echo $totalProducts; ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card p-3 text-center">
                                <h5>In Stock</h5>
                                <p class="h4 text-success">-</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card p-3 text-center">
                                <h5>Out of Stock</h5>
                                <p class="h4 text-danger">-</p>
                            </div>
                        </div>
                    </div>
                    
                    <p class="text-muted">
                        <i class="fa fa-info-circle mr-2"></i>
                        Go to the <a href="upload.php">Upload Products</a> page to manage your product inventory.
                    </p>
                </div>

                <!-- Orders Tab -->
                <div class="tab-pane fade" id="orders">
                    <h4><i class="fa fa-shopping-cart mr-2"></i>Order Management</h4>
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card p-3 text-center">
                                <h5>Total Orders</h5>
                                <p class="h4">0</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card p-3 text-center">
                                <h5>Pending</h5>
                                <p class="h4 text-warning">0</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card p-3 text-center">
                                <h5>Processing</h5>
                                <p class="h4 text-info">0</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card p-3 text-center">
                                <h5>Completed</h5>
                                <p class="h4 text-success">0</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center py-4">
                        <i class="fa fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No orders yet. Orders will appear here once customers start purchasing products.</p>
                    </div>
                </div>

                <!-- Users Tab -->
                <div class="tab-pane fade" id="users">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4><i class="fa fa-users mr-2"></i>User Management</h4>
                        <button class="btn btn-success" data-toggle="modal" data-target="#addUserModal">
                            <i class="fa fa-plus mr-2"></i>Add New User
                        </button>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card p-3 text-center">
                                <h5>Total Users</h5>
                                <p class="h4 text-primary" id="users-total-count"><?php echo $totalUsers; ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3 text-center">
                                <h5>Administrators</h5>
                                <p class="h4 text-danger" id="users-admin-count"><?php echo $totalAdmins; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-table-body">
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT id, fullname, username, email, is_admin FROM users ORDER BY id DESC");
                                    while ($user_row = $stmt->fetch()) {
                                        echo "<tr data-user-id='" . $user_row['id'] . "'>";
                                        echo "<td>" . $user_row['id'] . "</td>";
                                        echo "<td>" . htmlspecialchars($user_row['fullname']) . "</td>";
                                        echo "<td>" . htmlspecialchars($user_row['username']) . "</td>";
                                        echo "<td>" . htmlspecialchars($user_row['email']) . "</td>";
                                        echo "<td>" . ($user_row['is_admin'] ? '<span class="badge badge-danger">Admin</span>' : '<span class="badge badge-info">User</span>') . "</td>";
                                        echo "<td class='table-actions'>";
                                        
                                        if ($user_row['id'] != $_SESSION['user_id']) {
                                            echo "<button class='btn btn-sm btn-warning' onclick='toggleAdmin(" . $user_row['id'] . ", " . ($user_row['is_admin'] ? 'false' : 'true') . ")'>";
                                            echo $user_row['is_admin'] ? 'Remove Admin' : 'Make Admin';
                                            echo "</button>";
                                            echo "<button class='btn btn-sm btn-danger' onclick='deleteUser(" . $user_row['id'] . ", \"" . htmlspecialchars($user_row['username']) . "\")'>";
                                            echo "<i class='fa fa-trash'></i>";
                                            echo "</button>";
                                        } else {
                                            echo "<span class='text-muted'>Current User</span>";
                                        }
                                        
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='6'>Error loading users</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div class="tab-pane fade" id="settings">
                    <h4><i class="fa fa-cog mr-2"></i>System Settings</h4>
                    
                    <form>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Site Name</label>
                                    <input type="text" class="form-control" value="Facecap">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Site Email</label>
                                    <input type="email" class="form-control" value="admin@facecap.com">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Timezone</label>
                                    <select class="form-control">
                                        <option>UTC</option>
                                        <option>America/New_York</option>
                                        <option>Europe/London</option>
                                        <option>Asia/Tokyo</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Currency</label>
                                    <select class="form-control">
                                        <option>USD ($)</option>
                                        <option>EUR (€)</option>
                                        <option>GBP (£)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save mr-2"></i>Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="addUserForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" class="form-control" name="fullname" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" name="is_admin">
                            <label class="form-check-label">Make this user an administrator</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="footer-area section_gap">
        <div class="container text-center">
            <p class="footer-text m-0">&copy; <script>document.write(new Date().getFullYear());</script> Facecap. All rights reserved.</p>
        </div>
    </footer>
  <script src="js/vendor/jquery-2.2.4.min.js"></script>
  <script src="js/vendor/bootstrap.min.js"></script>
  
  <script>
    function showSection(sectionName) {
      // Hide all sections
      const sections = ['dashboard', 'products', 'orders', 'users', 'settings'];
      sections.forEach(section => {
        const element = document.getElementById(section + '-section');
        if (element) {
          element.style.display = 'none';
        }
      });
      
      // Show selected section
      const selectedSection = document.getElementById(sectionName + '-section');
      if (selectedSection) {
        selectedSection.style.display = 'block';
      }
      
      // Update active sidebar link
      const sidebarLinks = document.querySelectorAll('.sidebar a');
      sidebarLinks.forEach(link => {
        link.classList.remove('active');
      });
      
      // Add active class to clicked link
      event.target.classList.add('active');
    }

    function showAlert(message, type = 'info') {
      const alertContainer = document.getElementById('alert-container');
      const alertDiv = document.createElement('div');
      alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
      alertDiv.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      `;
      alertContainer.appendChild(alertDiv);
      
      // Auto-dismiss after 5 seconds
      setTimeout(() => {
        if (alertDiv.parentNode) {
          alertDiv.remove();
        }
      }, 5000);
    }

    // Add User Form Handler
    document.getElementById('addUserForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      formData.append('action', 'add_user');
      
      fetch('admin_dashboard.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showAlert(data.message, 'success');
          $('#addUserModal').modal('hide');
          document.getElementById('addUserForm').reset();
          
          // Refresh users table and counts
          location.reload();
        } else {
          showAlert(data.message, 'danger');
        }
      })
      .catch(error => {
        showAlert('An error occurred while adding the user.', 'danger');
        console.error('Error:', error);
      });
    });

    function deleteUser(userId, username) {
      if (!confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
        return;
      }
      
      const formData = new FormData();
      formData.append('action', 'delete_user');
      formData.append('user_id', userId);
      
      fetch('admin_dashboard.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showAlert(data.message, 'success');
          
          // Remove the row from table
          const row = document.querySelector(`tr[data-user-id="${userId}"]`);
          if (row) {
            row.remove();
          }
          
          // Update counts
          updateCounts();
        } else {
          showAlert(data.message, 'danger');
        }
      })
      .catch(error => {
        showAlert('An error occurred while deleting the user.', 'danger');
        console.error('Error:', error);
      });
    }

    function toggleAdmin(userId, makeAdmin) {
      const action = makeAdmin ? 'make admin' : 'remove admin privileges from';
      if (!confirm(`Are you sure you want to ${action} this user?`)) {
        return;
      }
      
      const formData = new FormData();
      formData.append('action', 'toggle_admin');
      formData.append('user_id', userId);
      
      fetch('admin_dashboard.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showAlert(data.message, 'success');
          
          // Refresh the page to update the table
          location.reload();
        } else {
          showAlert(data.message, 'danger');
        }
      })
      .catch(error => {
        showAlert('An error occurred while updating the user role.', 'danger');
        console.error('Error:', error);
      });
    }

    function updateCounts() {
      // Count remaining rows in table
      const rows = document.querySelectorAll('#users-table-body tr');
      const totalUsers = rows.length;
      let adminCount = 0;
      
      rows.forEach(row => {
        const badge = row.querySelector('.badge-danger');
        if (badge) {
          adminCount++;
        }
      });
      
      // Update dashboard counts
      document.getElementById('total-users-count').textContent = totalUsers;
      document.getElementById('total-admins-count').textContent = adminCount;
      document.getElementById('users-total-count').textContent = totalUsers;
      document.getElementById('users-admin-count').textContent = adminCount;
    }
  </script>
</body>

</html>