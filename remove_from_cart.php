<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['cart_id'])) {
    header('Location: cart.php');
    exit();
}

$cart_id = (int) $_POST['cart_id'];

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$cart_id, $_SESSION['user_id']]);
} catch (PDOException $e) {}

header('Location: cart.php');
exit();
