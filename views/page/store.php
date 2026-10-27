<?php
$csrf = $_SESSION['_csrf'] ?? '';
$productsList = $products ?? [];
?>
<div class="page-shell">
    <div class="page-header">
        <h2>المتجر</h2>
        <p>تصفح المنتجات المتاحة وقم بإضافة العناصر إلى السلة.</p>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert success"><?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert error"><?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (empty($productsList)): ?>
        <div class="empty-state">لا يوجد منتجات متاحة حاليًا.</div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($productsList as $productRow): ?>
                <?php $product = $productRow['product']; ?>
                <div class="product-card">
                    <div class="product-image-wrap">
                        <?php $image = $product->base_image_url ?? ''; ?>
                        <?php if ($image): ?>
                            <img src="<?= htmlspecialchars(BASE_URL . ltrim($image, '/'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($product->name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <?php else: ?>
                            <div class="product-placeholder">No Image</div>
                        <?php endif; ?>
                    </div>

                    <div class="product-card-body">
                        <h3><?= htmlspecialchars($product->name ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="description"><?= htmlspecialchars($product->description ?? '', ENT_QUOTES, 'UTF-8'); ?></p>

                        <?php if (!empty($productRow['variants'])): ?>
                            <div class="variant-list">
                                <?php foreach ($productRow['variants'] as $variant): ?>
                                    <div class="variant-item">
                                        <div class="variant-meta">
                                            <span><?= htmlspecialchars($variant->SKU ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span><?= (float)($variant->price ?? 0); ?> ر.س</span>
                                        </div>
                                        <div class="variant-actions">
                                            <span>المخزون: <?= (int)($variant->stock ?? 0); ?></span>
                                            <form method="post" action="<?= BASE_URL; ?>cart/add">
                                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="variant_id" value="<?= (int)($variant->variant_id ?? 0); ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                <button type="submit">أضف للسلة</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
