<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    $pdo = getConnection();
    
    // Check if user is admin
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['is_admin'] != 1) {
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle product upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo = getConnection();
        
        switch ($_POST['action']) {
            case 'add_product':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $category = trim($_POST['category']);
                
                // Validation
                if (empty($name) || empty($description) || $price <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Please fill all required fields with valid data']);
                    exit();
                }
                
                $image_path = null;
                
                // Handle file upload
                if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($_FILES['image']['type'], $allowed_types)) {
                        echo json_encode(['success' => false, 'message' => 'Only JPEG, PNG, and GIF images are allowed']);
                        exit();
                    }
                    
                    if ($_FILES['image']['size'] > $max_size) {
                        echo json_encode(['success' => false, 'message' => 'Image size must be less than 5MB']);
                        exit();
                    }
                    
                    // Create uploads directory if it doesn't exist
                    $upload_dir = 'uploads/products/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $extension;
                    $image_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
                        exit();
                    }
                }
                
                // Insert product into database
                $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, category, image_path, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $description, $price, $stock, $category, $image_path]);
                
                echo json_encode(['success' => true, 'message' => 'Product added successfully']);
                break;
                
            case 'delete_product':
                $product_id = intval($_POST['product_id']);
                
                // Get product info to delete image
                $stmt = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    // Delete image file if exists
                    if ($product['image_path'] && file_exists($product['image_path'])) {
                        unlink($product['image_path']);
                    }
                    
                    // Delete product from database
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit();
}

// Get all products
try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Upload | Facecap Admin</title>
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .upload-container {
            margin-top: 80px;
            margin-bottom: 50px;
        }
        .product-image {
            max-width: 80px;
            height: auto;
        }
        .table-actions button {
            margin: 2px;
        }
        .alert {
            margin-bottom: 20px;
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
                            <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Admin Panel</a></li>
                            <li class="nav-item active"><a class="nav-link" href="upload.php">Upload Products</a></li>
                            <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <div class="container upload-container">
        <!-- Alert container -->
        <div id="alert-container"></div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fa fa-plus mr-2"></i>Add New Product</h5>
                    </div>
                    <div class="card-body">
                        <form id="productForm" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Product Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea class="form-control" name="description" rows="3" required></textarea>
                            </div>
                            <div class="form-group">
                                <label>Price ($)</label>
                                <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label>Stock Quantity</label>
                                <input type="number" class="form-control" name="stock" min="0" value="0">
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select class="form-control" name="category">
                                    <option value="caps">Caps</option>
                                    <option value="hats">Hats</option>
                                    <option value="beanies">Beanies</option>
                                    <option value="accessories">Accessories</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Product Image</label>
                                <input type="file" class="form-control-file" name="image" accept="image/*">
                                <small class="text-muted">Max size: 5MB. Formats: JPG, PNG, GIF</small>
                            </div>
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fa fa-upload mr-2"></i>Add Product
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fa fa-list mr-2"></i>All Products (<?php echo count($products); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="products-table">
                                    <?php foreach ($products as $product): ?>
                                    <tr data-product-id="<?php echo $product['id']; ?>">
                                        <td>
                                            <?php if ($product['image_path']): ?>
                                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                                     alt="Product Image" class="product-image">
                                            <?php else: ?>
                                                <span class="text-muted">No image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($product['category'])); ?></td>
                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        <td><?php echo $product['stock']; ?></td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($products)): ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No products uploaded yet. Add your first product!</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            `;
            alertContainer.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Product form handler
        document.getElementById('productForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_product');
            
            fetch('upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('productForm').reset();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('An error occurred while adding the product.', 'danger');
                console.error('Error:', error);
            });
        });

        function deleteProduct(productId, productName) {
            if (!confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_product');
            formData.append('product_id', productId);
            
            fetch('upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                    if (row) {
                        row.remove();
                    }
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('An error occurred while deleting the product.', 'danger');
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>