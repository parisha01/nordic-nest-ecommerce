-- =========================================================
--  NORDIC NEST (Enhanced) — migration for existing databases
--
--  If you already imported this project's schema.sql once and
--  have data you want to keep, run THIS file instead of
--  re-importing schema.sql from scratch. It adds the columns/
--  tables needed for: stock/inventory, wishlists, coupons, and
--  order cancellation/refund requests, and verified-purchase
--  reviews — on top of the existing password-reset columns,
--  which are left untouched.
--  (If you're setting up fresh, just import schema.sql — it
--  already includes all of this.)
-- =========================================================

ALTER TABLE products
    ADD COLUMN stock INT UNSIGNED NOT NULL DEFAULT 10 AFTER image_url;

ALTER TABLE orders
    ADD COLUMN subtotal    DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN coupon_code VARCHAR(40)   NULL AFTER subtotal,
    ADD COLUMN discount    DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER coupon_code,
    MODIFY COLUMN status ENUM('pending','processing','shipped','completed','cancelled','refunded') NOT NULL DEFAULT 'pending';

-- Backfill subtotal for any existing orders.
UPDATE orders SET subtotal = total WHERE subtotal = 0;

ALTER TABLE testimonials
    ADD COLUMN is_verified_purchase TINYINT(1) NOT NULL DEFAULT 0 AFTER product_id;

CREATE TABLE IF NOT EXISTS wishlists (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist_user_product (user_id, product_id),
    CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coupons (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(40)   NOT NULL UNIQUE,
    description     VARCHAR(190)  NULL,
    discount_type   ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value  DECIMAL(10,2) NOT NULL,
    min_order       DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_uses        INT UNSIGNED  NULL,
    times_used      INT UNSIGNED  NOT NULL DEFAULT 0,
    expires_at      DATE          NULL,
    active          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    request_type    ENUM('cancellation','refund') NOT NULL,
    reason          VARCHAR(500) NOT NULL,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note      VARCHAR(500) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at     TIMESTAMP    NULL,
    CONSTRAINT fk_order_requests_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_requests_user  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO coupons (code, description, discount_type, discount_value, min_order, max_uses, expires_at, active) VALUES
('WELCOME10', '10% off your first order',         'percent', 10.00, 0,      NULL, NULL,         1),
('SAVE20',    '$20 off orders over $150',          'fixed',  20.00, 150.00, NULL, NULL,         1),
('EXPIRED5',  'Old test coupon (already expired)', 'percent', 5.00, 0,      NULL, '2025-01-01', 1);
