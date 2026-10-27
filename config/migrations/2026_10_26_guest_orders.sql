-- Apply once to an existing System-inline-Store database.
-- Existing customer orders are preserved; new orders collect guest contact details.
ALTER TABLE `order_header`
    MODIFY `user_id` INT UNSIGNED NULL,
    ADD COLUMN `customer_name` VARCHAR(255) NULL AFTER `user_id`,
    ADD COLUMN `customer_phone` VARCHAR(50) NULL AFTER `customer_name`;
