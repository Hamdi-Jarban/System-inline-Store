<?php
class ProductController extends Controller
{
    protected $products;
    protected $category;
    protected $brand;
    protected $warehouse;
    protected $supplier;

    function __construct()
    {
        $this->products  = $this->model("Product");
        $this->category  = $this->model("Category");
        $this->brand     = $this->model("Brand");
        $this->warehouse = $this->model("Warehouse");
        $this->supplier  = $this->model("Supplier");
    }

    public function index()
    {
        $products = $this->products->getAllProductsWithDetails();
        $data = [
            'title'    => 'إدارة المنتجات',
            'products' => $products,
        ];
        $this->view('product/index', $data);
    }

    public function edit($productId = null)
    {
        if (!$productId) {
            header('Location: ' . BASE_URL . 'products');
            exit;
        }

        $product = $this->products->getProductById($productId);

        if (!$product) {
            $_SESSION['error'] = 'المنتج غير موجود';
            header('Location: ' . BASE_URL . 'products');
            exit;
        }

        $data = [
            'title'      => 'تعديل: ' . $product->name,
            'product'    => $product,
            'brands'     => $this->brand->getAll(),
            'categories' => $this->category->getAll(),
            'suppliers'  => $this->supplier->getAll(),
            'warehouse'  => $this->warehouse->getAll(),
        ];
        $this->view('product/edit', $data);
    }

    public function update($productId = null)
    {
        if (!$productId || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'products');
            exit;
        }

        $newImage = null;
        try {
            $this->products->db->beginTransaction();

            $productData = [
                'name'        => $_POST['product_name'],
                'description' => $_POST['description'] ?? null,
                'brand_id'    => !empty($_POST['brand_id']) ? $_POST['brand_id'] : null,
                'is_active'   => $_POST['is_active'] ?? 1,
            ];

            $newImage = $this->products->AddFile("product_image");
            $oldProduct = $this->products->getProductById($productId);
            if ($newImage) {
                $productData['base_image_url'] = $newImage;
            }

            $this->products->updateProduct($productId, $productData);

            $this->products->db->commit();

            if ($newImage && !empty($oldProduct->base_image_url)) {
                $oldPath = APP_PATH . 'public/' . ltrim($oldProduct->base_image_url, '/');
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $_SESSION['message'] = '✅ تم تحديث المنتج بنجاح';
            header('Location: ' . BASE_URL . 'products');
            exit;
        } catch (Throwable $e) {
            if ($this->products->db->inTransaction()) {
                $this->products->db->rollBack();
            }
            if ($newImage) {
                $uploadedPath = APP_PATH . 'public/' . ltrim($newImage, '/');
                if (is_file($uploadedPath)) {
                    @unlink($uploadedPath);
                }
            }
            error_log('Product update failed: ' . $e->getMessage());
            $_SESSION['error'] = 'تعذر حفظ تعديلات المنتج.';
            header('Location: ' . BASE_URL . 'product/edit/' . $productId);
            exit;
        }
    }

    public function delete($productId = null)
    {
        if (!$productId) {
            header('Location: ' . BASE_URL . 'products');
            exit;
        }

        try {
            $product = $this->products->getProductById($productId);
            if ($product) {
                if (!empty($product->base_image_url) && is_file(APP_PATH . 'public/' . ltrim($product->base_image_url, '/'))) {
                    unlink(APP_PATH . 'public/' . ltrim($product->base_image_url, '/'));
                }
                if (!empty($product->variant_images)) {
                    foreach ($product->variant_images as $img) {
                        if (!empty($img->image_url) && is_file(APP_PATH . 'public/' . ltrim($img->image_url, '/'))) {
                            unlink(APP_PATH . 'public/' . ltrim($img->image_url, '/'));
                        }
                    }
                }
            }

            $this->products->deleteProduct($productId);
            $_SESSION['message'] = '✅ تم حذف المنتج بنجاح';
        } catch (Throwable $e) {
            error_log('Product deletion failed: ' . $e->getMessage());
            $_SESSION['error'] = 'تعذر حذف المنتج.';
        }

        header('Location: ' . BASE_URL . 'products');
        exit;
    }

    public function create()
    {
        $data = [
            'title'      => 'إضافة منتج جديد',
            'brands'     => $this->brand->getAll(),
            'categories' => $this->category->getAll(),
            'suppliers'  => $this->supplier->getAll(),
            'warehouse'  => $this->warehouse->getAll(),
        ];
        $this->view('product/create', $data);
    }

    public function store()
    {
        $uploadedPaths = [];
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                return;
            }

            $this->products->db->beginTransaction();

            $baseImagePath = $this->products->AddFile("product_image");
            if ($baseImagePath) {
                $uploadedPaths[] = $baseImagePath;
            }

            $productData = [
                "name"           => $_POST["product_name"],
                "description"    => $_POST["description"] ?? null,
                "brand_id"       => !empty($_POST["brand_id"]) ? $_POST["brand_id"] : null,
                "base_image_url" => $baseImagePath ?? null,
                "is_active"      => $_POST["is_active"] ?? 1,
            ];

            $productId = $this->products->createProduct($productData);

            $categoriesId = $_POST['categories'] ?? [];
            if (!empty($categoriesId)) {
                $this->products->attachCategories($productId, $categoriesId);
            }

            $variants = $_POST['variants'] ?? [];
            if (empty($variants)) {
                throw new Exception('يجب إضافة متغير واحد على الأقل.');
            }

            $variantsId = [];
            foreach ($variants as $key => $variant) {
                if (empty(trim($variant['sku'] ?? '')) || !isset($variant['price']) || !is_numeric($variant['price']) || (float) $variant['price'] < 0) {
                    throw new Exception("المتغير رقم " . ($key + 1) . ": SKU والسعر مطلوبان");
                }

                $variantImagePath = $this->uploadimages($key);
                if ($variantImagePath) {
                    $uploadedPaths[] = $variantImagePath;
                }

                $variantData = [
                    'sku'          => trim($variant['sku']),
                    'size_option'  => $variant['size_option'] ?? null,
                    'color_option' => $variant['color_option'] ?? null,
                    'packaging'    => $variant['packaging'] ?? null,
                    'price'        => (float) $variant['price'],
                    'weight_kg'    => $variant['weight_kg'] ?? null,
                    'image_url'    => $variantImagePath,
                ];

                $variantsId[$key] = $this->products->addVariant($productId, $variantData);
            }

            $suppliers = $_POST['suppliers'] ?? [];
            foreach ($suppliers as $supplier) {
                if (empty($supplier['supplier_id']) || empty($supplier['supply_price'])) {
                    continue;
                }
                $this->products->attachSupplier($productId, $supplier);
            }

            $inventoryData = $_POST['inventory'] ?? [];
            if (!empty($inventoryData) && !empty($variantsId)) {
                foreach ($inventoryData as $inv) {
                    if (empty($inv['warehouse_id'])) {
                        continue;
                    }
                    $selectedVariantKey = $inv['variant_key'] ?? array_key_first($variantsId);
                    if (!isset($variantsId[$selectedVariantKey])) {
                        throw new Exception('اختر متغيرًا صحيحًا لكل سجل مخزون.');
                    }
                    $this->products->addInventory($variantsId[$selectedVariantKey], $inv);
                }
            }

            $this->products->db->commit();
            $_SESSION['message'] = '✅ تم إضافة المنتج بنجاح';
            header('Location: ' . BASE_URL . 'products');
            exit;
        } catch (Throwable $e) {
            if ($this->products->db->inTransaction()) {
                $this->products->db->rollBack();
            }
            foreach ($uploadedPaths as $path) {
                $absolutePath = APP_PATH . 'public/' . ltrim($path, '/');
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
            error_log('Product creation failed: ' . $e->getMessage());
            $_SESSION['error'] = 'تعذر حفظ المنتج؛ تحقق من البيانات وحاول مرة أخرى.';
            header('Location: ' . BASE_URL . 'product/create');
            exit;
        }
    }

    public function uploadimages($key)
    {
        if (empty($_FILES["variants"]['name'][$key]["image"]) || $_FILES["variants"]['error'][$key]["image"] !== 0) {
            return null;
        }

        $_FILES["variant_tmp"] = [
            'name' => $_FILES["variants"]['name'][$key]["image"],
            'type' => $_FILES["variants"]['type'][$key]["image"],
            'tmp_name' => $_FILES["variants"]['tmp_name'][$key]["image"],
            'error' => $_FILES["variants"]['error'][$key]["image"],
            'size' => $_FILES["variants"]['size'][$key]["image"],
        ];

        return $this->products->AddFile("variant_tmp");
    }
}
