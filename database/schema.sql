-- =========================================================
--  NORDIC NEST — Database schema
--  ICT726 Assignment 4 — Dynamic Website
--
--  Import this file into a new, empty MySQL/MariaDB database
--  (phpMyAdmin: create a database, then Import > choose this file).
-- =========================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------
-- 1. users  — registered accounts (auth + role-based access)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120)  NOT NULL,
    email           VARCHAR(190)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255)  NOT NULL,
    role            ENUM('admin','member') NOT NULL DEFAULT 'member',
    reset_token     VARCHAR(64)   NULL,
    reset_token_expires DATETIME  NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 2. products — the shop catalogue (admin-managed, CRUD)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)  NOT NULL,
    category        ENUM('Furniture','Lighting','Textiles','Kitchen') NOT NULL,
    price           DECIMAL(10,2) NOT NULL,
    description     TEXT          NOT NULL,
    image_url       VARCHAR(255)  NOT NULL,
    stock           INT UNSIGNED  NOT NULL DEFAULT 0,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 3. testimonials — customer reviews (submitted by members,
--    moderated by admin before they appear publicly)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS testimonials (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NULL,
    is_verified_purchase TINYINT(1) NOT NULL DEFAULT 0,
    rating          TINYINT UNSIGNED NOT NULL,
    comment         VARCHAR(600) NOT NULL,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_testimonials_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_testimonials_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 4. contact_messages — enquiries from the public contact form
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    subject         VARCHAR(150) NOT NULL,
    message         VARCHAR(1000) NOT NULL,
    status          ENUM('new','read') NOT NULL DEFAULT 'new',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 5. newsletter_subscribers — homepage newsletter sign-up
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(190) NOT NULL UNIQUE,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 6. orders — one row per placed order. Cart itself lives in
--    the PHP session (guests can add to cart); an order is
--    only created once a logged-in member checks out.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    status          ENUM('pending','processing','shipped','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    subtotal        DECIMAL(10,2) NOT NULL DEFAULT 0,
    coupon_code     VARCHAR(40)   NULL,
    discount        DECIMAL(10,2) NOT NULL DEFAULT 0,
    total           DECIMAL(10,2) NOT NULL,
    full_name       VARCHAR(120)  NOT NULL,
    email           VARCHAR(190)  NOT NULL,
    phone           VARCHAR(30)   NOT NULL,
    address_line    VARCHAR(190)  NOT NULL,
    suburb          VARCHAR(120)  NOT NULL,
    state           VARCHAR(60)   NOT NULL,
    postcode        VARCHAR(15)   NOT NULL,
    notes           VARCHAR(500)  NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 7. order_items — line items for each order. Product name
--    and price are snapshotted at purchase time so an order's
--    history stays accurate even if the product is later
--    edited or deleted from the catalogue.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NULL,
    product_name    VARCHAR(150)  NOT NULL,
    unit_price      DECIMAL(10,2) NOT NULL,
    quantity        SMALLINT UNSIGNED NOT NULL,
    line_total      DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 8. wishlists — members can save products for later.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS wishlists (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    product_id      INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist_user_product (user_id, product_id),
    CONSTRAINT fk_wishlist_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlist_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 9. coupons — admin-managed discount codes applied at
--    checkout (percentage or fixed amount, optional minimum
--    order, usage cap and expiry date).
-- ---------------------------------------------------------
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

-- ---------------------------------------------------------
-- 10. order_requests — member-initiated cancellation/refund
--     requests, reviewed and resolved by an admin.
-- ---------------------------------------------------------
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
    CONSTRAINT fk_order_requests_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_requests_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
--  Seed data — sample products so the shop isn't empty
--  on first run. Prices/descriptions match the original
--  static Nordic Nest site. Stock levels include a mix of
--  healthy, low, and one sold-out item so the low-stock
--  admin warning and "out of stock" states are demonstrable.
-- =========================================================
INSERT INTO products (name, category, price, description, image_url, stock) VALUES
('Alder Table Lamp',   'Lighting',  89.00,  'Hand-glazed ceramic base with a natural linen shade.',              'https://picsum.photos/seed/alder-lamp/600/450', 24),
('Fjord Bowl Set',     'Kitchen',   64.00,  'Three stacking stoneware bowls, glazed by hand.',                   'https://picsum.photos/seed/fjord-bowls/600/450', 40),
('Birk Wool Throw',    'Textiles',  118.00, 'Undyed wool, woven on a family-run loom in Jutland.',               'https://picsum.photos/seed/birk-throw/600/450', 3),
('Vester Lounge Chair','Furniture', 465.00, 'Solid oak frame with a hand-woven paper-cord seat.',                'https://picsum.photos/seed/vester-chair/600/450', 6),
('Kalvi Side Table',   'Furniture', 210.00, 'Solid oak, finished with a matte hardwax oil.',                     'https://picsum.photos/seed/kalvi-table/600/450', 0),
('Solvang Pendant',    'Lighting',  142.00, 'Hand-woven rattan shade over a warm LED fitting.',                  'https://picsum.photos/seed/solvang-pendant/600/450', 15),
('Holt Linen Cushion Set','Textiles',76.00, 'Stonewashed linen, feather-down inserts included.',                 'https://picsum.photos/seed/holt-cushions/600/450', 30),
('Aske Mug Set of 4',  'Kitchen',   58.00,  'Speckled stoneware with a soft matte glaze.',                       'https://picsum.photos/seed/aske-mugs/600/450', 18);

-- Sample coupons so checkout's discount logic is demonstrable out of the box.
INSERT INTO coupons (code, description, discount_type, discount_value, min_order, max_uses, expires_at, active) VALUES
('WELCOME10', '10% off your first order',        'percent', 10.00, 0,      NULL, NULL,          1),
('SAVE20',    '$20 off orders over $150',         'fixed',  20.00, 150.00, NULL, NULL,          1),
('EXPIRED5',  'Old test coupon (already expired)','percent', 5.00, 0,      NULL, '2025-01-01',  1);

-- =========================================================
--  NOTE ON THE ADMIN ACCOUNT
--  Passwords must be hashed with PHP's password_hash(), which
--  can't be produced from plain SQL. Do NOT insert an admin
--  user here with a plain-text password.
--
--  After importing this schema, open setup/create_admin.php
--  once in your browser to create the first admin account
--  securely, then delete that file. See README.md.
-- =========================================================
