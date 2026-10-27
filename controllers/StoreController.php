<?php
class StoreController extends Controller {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function index() {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        $this->db->query("SELECT p.product_id, p.name, p.description, p.base_image_url, pv.variant_id, pv.SKU AS sku, pv.price, pv.size_option, pv.color_option, COALESCE(SUM(i.quantity_in_stock), 0) AS stock FROM product p JOIN product_variant pv ON pv.product_id = p.product_id LEFT JOIN inventory i ON i.variant_id = pv.variant_id WHERE p.is_active = 1 GROUP BY p.product_id, p.name, p.description, p.base_image_url, pv.variant_id, pv.SKU, pv.price, pv.size_option, pv.color_option ORDER BY p.product_id DESC, pv.variant_id");
        $rows = $this->db->resultSet();

        $products = [];
        foreach ($rows as $row) {
            if (!isset($products[$row->product_id])) {
                $products[$row->product_id] = ['product' => $row, 'variants' => []];
            }
            $products[$row->product_id]['variants'][] = $row;
        }

        $this->view('page/store', ['products' => $products, 'csrf' => $_SESSION['_csrf']]);
    }
}
