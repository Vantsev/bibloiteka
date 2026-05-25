<?php
require_once "includes/db.php";

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if (isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id]++;
    } else {
        $_SESSION['cart'][$id] = 1;
    }
}

// Возвращаемся обратно в магазин с флагом добавления
header("Location: products.php?added=" . $id);
exit;
