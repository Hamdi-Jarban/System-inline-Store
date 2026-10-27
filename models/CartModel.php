<?php
class CartModel {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }

    public function add($product) {
        $id = (int)($product['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }

        $quantity = (int)($product['quantity'] ?? 1);
        if ($quantity < 1) {
            $quantity = 1;
        }

        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id] = (int)$_SESSION['cart'][$id] + $quantity;
        } else {
            $_SESSION['cart'][$id] = $quantity;
        }

        return true;
    }

    public function getItems() {
        return $_SESSION['cart'] ?? [];
    }

    public function setQuantity($id, $quantity) {
        $productId = (int)($id ?? 0);
        $newQty = (int)($quantity ?? 0);

        if ($productId <= 0) {
            throw new InvalidArgumentException('معرّف المنتج غير صالح.');
        }

        if ($newQty <= 0) {
            unset($_SESSION['cart'][$productId]);
            return;
        }

        if ($newQty > 99) {
            throw new InvalidArgumentException('الكمية القصوى المسموح بها 99.');
        }

        $_SESSION['cart'][$productId] = $newQty;
    }

    public function remove($id) {
        $productId = (int)($id ?? 0);
        if ($productId > 0 && isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
        }
    }

    public function clear() {
        $_SESSION['cart'] = [];
    }

    public function getTotal($items = []) {
        $total = 0.0;
        foreach ($items as $id => $product) {
            $price = (float)($product->price ?? 0);
            $quantity = (int)($product->quantity ?? $_SESSION['cart'][$id] ?? 0);
            $total += $price * $quantity;
        }
        return $total;
    }
}
