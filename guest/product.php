<?php
$product_id = $_GET['id'] ?? null;
if ($product_id) {
    header("Location: ../product.php?id=" . urlencode($product_id));
} else {
    header("Location: index.php");
}
exit;