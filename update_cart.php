<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['cart_id'], $_POST['quantity'])) {
    header('Location: cart.php');
    exit();
}

$cart_id = (int) $_POST['cart_id'];
$quantity = (int) $_POST['quantity'];

if ($quantity <= 0) {
    header('Location: cart.php');
    exit();
}

try {
    $pdo = getConnection();

    $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$quantity, $cart_id, $_SESSION['user_id']]);
} catch (PDOException $e) {}

header('Location: cart.php');
exit();
