<?php
class OrderController extends Controller {
    private $cart;
    private $db;

    public function __construct() {
        $this->cart = $this->model('CartModel');
        $this->db = new Database();
    }

    public function placeOrder() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit;
        }

        $token = $_POST['_csrf'] ?? '';
        if (!isset($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $token)) {
            http_response_code(403);
            exit('طلب غير صالح.');
        }

        $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$userId) {
            $userId = null;
        }

        $customerName = trim((string)($_POST['customer_name'] ?? ''));
        $customerPhone = trim((string)($_POST['customer_phone'] ?? ''));
        if ($customerName === '' || strlen($customerName) > 255 || $customerPhone === '' || strlen($customerPhone) > 50) {
            $_SESSION['error'] = 'أدخل اسمًا ورقم هاتف صحيحين.';
            header('Location: ' . BASE_URL . 'cart');
            exit;
        }

        $address = trim((string)($_POST['shipping_address'] ?? ''));
        if ($address === '' || strlen($address) > 12000) {
            $_SESSION['error'] = 'أدخل عنوان شحن صحيحًا.';
            header('Location: ' . BASE_URL . 'cart');
            exit;
        }

        $items = $this->cart->getItems();
        if (!$items) {
            $_SESSION['error'] = 'السلة فارغة.';
            header('Location: ' . BASE_URL . 'cart');
            exit;
        }

        try {
            $this->db->beginTransaction();

            $lines = [];
            $total = 0.0;

            foreach ($items as $variantId => $quantity) {
                $this->db->query('SELECT pv.variant_id, pv.price, p.is_active FROM product_variant pv JOIN product p ON p.product_id = pv.product_id WHERE pv.variant_id = :id FOR UPDATE');
                $this->db->bind(':id', (int)$variantId);
                $variant = $this->db->single();

                if (!$variant || !(int)$variant->is_active) {
                    throw new RuntimeException('أحد المنتجات لم يعد متاحًا.');
                }

                $this->db->query('SELECT inventory_id, quantity_in_stock FROM inventory WHERE variant_id = :id AND quantity_in_stock > 0 ORDER BY inventory_id FOR UPDATE');
                $this->db->bind(':id', (int)$variantId);
                $stocks = $this->db->resultSet();

                $available = array_sum(array_map(fn($row) => (int)$row->quantity_in_stock, $stocks));
                if ($available < (int)$quantity) {
                    throw new RuntimeException('المخزون غير كافٍ لأحد المنتجات.');
                }

                $lines[] = ['id' => (int)$variantId, 'quantity' => (int)$quantity, 'price' => (float)$variant->price, 'stocks' => $stocks];
                $total += (float)$variant->price * (int)$quantity;
            }

            $this->db->query("INSERT INTO order_header (user_id, customer_name, customer_phone, total_amount, order_status, shipping_address) VALUES (:user, :customer_name, :customer_phone, :total, 'pending', :address)");
            $this->db->bind(':user', $userId);
            $this->db->bind(':customer_name', $customerName);
            $this->db->bind(':customer_phone', $customerPhone);
            $this->db->bind(':total', $total);
            $this->db->bind(':address', $address);
            $this->db->execute();

            $orderId = $this->db->lastInsertId();

            foreach ($lines as $line) {
                $this->db->query('INSERT INTO order_item (order_id, variant_id, quantity, unit_price) VALUES (:order, :variant, :quantity, :price)');
                $this->db->bind(':order', (int)$orderId);
                $this->db->bind(':variant', $line['id']);
                $this->db->bind(':quantity', $line['quantity']);
                $this->db->bind(':price', $line['price']);
                $this->db->execute();

                $remaining = $line['quantity'];
                foreach ($line['stocks'] as $stock) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $take = min($remaining, (int)$stock->quantity_in_stock);
                    $this->db->query('UPDATE inventory SET quantity_in_stock = quantity_in_stock - :qty WHERE inventory_id = :id');
                    $this->db->bind(':qty', $take);
                    $this->db->bind(':id', (int)$stock->inventory_id);
                    $this->db->execute();
                    $remaining -= $take;
                }
            }

            $this->db->commit();
            $this->cart->clear();
            $_SESSION['message'] = 'تم تسجيل الطلب بنجاح. رقم الطلب: ' . (int)$orderId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('Order creation failed: ' . $e->getMessage());
            $_SESSION['error'] = $e instanceof RuntimeException ? $e->getMessage() : 'تعذر إتمام الطلب؛ تحقق من البيانات وحاول مرة أخرى.';
        }

        header('Location: ' . BASE_URL . 'cart');
        exit;
    }
}
