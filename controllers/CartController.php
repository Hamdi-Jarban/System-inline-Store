<?php
class CartController extends Controller {
    private $cart;
    private $db;

    public function __construct() {
        $this->cart = $this->model('CartModel');
        $this->db = new Database();
    }

    private function requirePost() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit;
        }

        $token = $_POST['_csrf'] ?? '';
        if (!isset($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $token)) {
            http_response_code(403);
            exit('طلب غير صالح.');
        }
    }

    private function redirectCart() {
        header('Location: ' . BASE_URL . 'cart');
        exit;
    }

    public function index() {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        $items = [];
        foreach ($this->cart->getItems() as $id => $quantity) {
            $this->db->query('SELECT pv.variant_id, pv.SKU AS sku, pv.size_option, pv.color_option, pv.packaging, pv.price, pv.image_url, p.product_id, p.name, p.base_image_url FROM product_variant pv JOIN product p ON p.product_id = pv.product_id WHERE pv.variant_id = :id AND p.is_active = 1');
            $this->db->bind(':id', (int)$id);
            $product = $this->db->single();

            if ($product) {
                $product->quantity = (int)$quantity;
                $items[$id] = $product;
            } else {
                $this->cart->remove($id);
            }
        }

        $total = $this->cart->getTotal($items);
        $this->view('Cart/index', ['items' => $items, 'total' => $total, 'csrf' => $_SESSION['_csrf']]);
    }

    public function add() {
        $this->requirePost();

        $id = filter_var($_POST['variant_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);

        if (!$id || !$quantity || $quantity > 99) {
            $_SESSION['error'] = 'اختر متغيرًا وكمية صحيحة.';
            $this->redirectCart();
        }

        $this->db->query('SELECT pv.variant_id, SUM(i.quantity_in_stock) AS stock FROM product_variant pv JOIN product p ON p.product_id = pv.product_id LEFT JOIN inventory i ON i.variant_id = pv.variant_id WHERE pv.variant_id = :id AND p.is_active = 1 GROUP BY pv.variant_id');
        $this->db->bind(':id', $id);
        $variant = $this->db->single();

        if (!$variant || (int)$variant->stock < 1) {
            $_SESSION['error'] = 'المنتج غير متاح أو نفد المخزون.';
            $this->redirectCart();
        }

        $this->cart->add(['id' => $id, 'quantity' => min($quantity, (int)$variant->stock)]);
        $_SESSION['message'] = 'أضيف المنتج إلى السلة.';
        $this->redirectCart();
    }

    public function update() {
        $this->requirePost();

        try {
            $this->cart->setQuantity($_POST['variant_id'] ?? null, $_POST['quantity'] ?? null);
            $_SESSION['message'] = 'تم تحديث السلة.';
        } catch (Throwable $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        $this->redirectCart();
    }

    public function remove() {
        $this->requirePost();
        $this->cart->remove($_POST['variant_id'] ?? 0);
        $_SESSION['message'] = 'حُذف العنصر من السلة.';
        $this->redirectCart();
    }

    public function clear() {
        $this->requirePost();
        $this->cart->clear();
        $_SESSION['message'] = 'أُفرغت السلة.';
        $this->redirectCart();
    }
}
