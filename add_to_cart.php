<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to add items to cart'
    ]);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get and validate input
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if ($product_id <= 0 || $quantity <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid product or quantity'
    ]);
    exit();
}

try {
    $pdo = getConnection();
    
    // Check if product exists and has enough stock
    $stmt = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id = ? AND stock > 0");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode([
            'success' => false,
            'message' => 'Product not found or out of stock'
        ]);
        exit();
    }
    
    // Check if product is already in cart
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cartItem) {
        // Update existing cart item
        $newQuantity = $cartItem['quantity'] + $quantity;
        
        // Check if new quantity exceeds stock
        if ($newQuantity > $product['stock']) {
            echo json_encode([
                'success' => false,
                'message' => 'Not enough stock available. Only ' . $product['stock'] . ' items in stock.'
            ]);
            exit();
        }
        
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newQuantity, $cartItem['id']]);
    } else {
        // Add new cart item
        if ($quantity > $product['stock']) {
            echo json_encode([
                'success' => false,
                'message' => 'Not enough stock available. Only ' . $product['stock'] . ' items in stock.'
            ]);
            exit();
        }
        
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
    }
    
    // Get updated cart count
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = $result['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart successfully',
        'cart_count' => $cartCount
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>