-- ============================================================
-- 19. جدول الطلبات (ORDER_HEADER)
-- ============================================================
CREATE TABLE `order_header` (
    `order_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `customer_name` VARCHAR(255) NOT NULL,
    `customer_phone` VARCHAR(50) NOT NULL,
    `order_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `order_status` VARCHAR(30) NOT NULL DEFAULT 'pending',
    `shipping_address` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`order_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`order_status`),
    KEY `idx_order_date` (`order_date`),
    CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) 
        REFERENCES `user`(`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='جدول الطلبات';
