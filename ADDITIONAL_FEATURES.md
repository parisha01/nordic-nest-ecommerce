# Nordic Nest — Additional Features Summary

This document lists everything added **on top of** the existing enhanced
build described in `ENHANCEMENTS.md` (Product Filters, Password Reset,
Order Timeline, Image Upload Guidance, Email Notification Log). Nothing
in that file, `README.md`, `config/db.php`, or any admin/database
credentials was changed — these are purely additive features, applied
carefully around the existing code.

---

## 🐛 Bug fix (found while integrating, not a new feature)

`order.php` used `$in_array(...)` (a variable) instead of PHP's built-in
`in_array(...)` function in two places in the order-status timeline. This
is a **fatal PHP error** that crashed the order confirmation page for the
default `pending` status and several others — meaning, as delivered,
almost every customer viewing their own order hit a white-screen crash.
Fixed to `in_array(...)` in both spots. Verified with a live test: a
freshly placed order (status `pending`) now renders correctly instead of
fatally erroring.

---

## 1. Inventory / Stock Management

Every product now has a `stock` quantity, managed from the admin product
form (`admin/product_form.php`). The shop and product pages show
"out of stock" / "only N left" states, "Add to cart" is disabled once
stock hits zero, the cart caps requested quantities to what's actually
available, and checkout re-checks and **atomically decrements stock
inside its database transaction** — so two customers racing for the last
unit can't both succeed; the loser sees a clear "sold out" message and
their order is rolled back rather than partially created.

**Files touched:** `database/schema.sql`, `admin/product_form.php`,
`admin/products.php`, `shop.php`, `product.php`, `cart.php`,
`checkout.php`, `includes/functions.php`.

## 2. Wishlist

Logged-in members can save products from the shop grid, a product page,
or the wishlist page itself (♡ Save / ♥ Saved toggle). Saved products
persist in the database against the account (not the session), so they
follow the member across devices. A count badge appears in the nav.

**Files added:** `wishlist.php`. **Files touched:** `shop.php`,
`product.php`, `includes/header.php`, `includes/functions.php`,
`database/schema.sql` (new `wishlists` table).

## 3. Coupon / Discount Codes

Admin-managed discount codes (percentage or fixed amount, with an
optional minimum order value, usage limit, and expiry date), applied at
checkout. Every rule is **re-validated server-side at the moment of
payment** — never trusted from a hidden form field — and the discount is
recorded on the order alongside the subtotal it was calculated from.
Three sample codes are seeded: `WELCOME10` (10% off), `SAVE20` ($20 off
orders over $150), and `EXPIRED5` (already expired, for testing that
rejection path).

**Files added:** `admin/coupons.php`. **Files touched:** `checkout.php`,
`order.php`, `admin/order_detail.php`, `admin/dashboard.php`,
`includes/functions.php`, `database/schema.sql` (new `coupons` table).

## 4. Order Cancellation & Refund Requests

A member can request to **cancel** a still-pending/processing order, or
request a **refund** on one that's already shipped/completed, with a
short reason. An admin reviews the request in the new
`admin/order_requests.php` queue. Approving a cancellation automatically
returns the ordered quantities to stock (they never shipped); approving
a refund marks the order refunded without auto-restocking, since whether
a returned item is resaleable needs a human decision. The friend's
4-step order timeline (Pending → Processing → Shipped → Delivered) has
no slot for these two new terminal states, so a cancelled/refunded order
now shows a simple status badge instead, rather than a misleading
"still in progress" bar.

**Files added:** `admin/order_requests.php`. **Files touched:**
`order.php`, `admin/order_detail.php`, `admin/dashboard.php`,
`admin/orders.php`, `includes/functions.php`, `database/schema.sql`
(new `order_requests` table, `orders.status` extended with `refunded`).

## 5. Verified-Purchase Reviews

A review is only ever tagged **"✓ Verified Purchase"** by checking,
server-side, whether the reviewer's own order history actually contains
that product on a shipped/completed order — never trusted from the
review submission form itself. The badge appears on the public
testimonials page, the individual product page, and the admin
moderation queue.

**Files touched:** `testimonials.php`, `admin/testimonials.php`,
`product.php`, `includes/functions.php`, `database/schema.sql`
(`testimonials.is_verified_purchase` column).

---

## Database migration

If this project's schema was already imported once, run
`database/migration_add_more_features.sql` instead of re-importing
`schema.sql` from scratch — it adds exactly the columns/tables above
(stock, wishlists, coupons, order_requests, refunded status,
is_verified_purchase) on top of the existing password-reset columns,
without touching any existing data.

---

## Testing performed

All of the above was tested against a live MySQL database, not just
reviewed as code:

- `php -l` syntax check passes on every file
- Fresh schema import creates all 10 tables correctly, including the
  friend's `reset_token`/`reset_token_expires` columns untouched
- Migration script tested against a simulated old-schema database —
  preserves existing data, adds new tables/columns correctly
- End-to-end: register → add to cart with stock capping → wishlist
  toggle → coupon apply with correct discount math → checkout with
  atomic stock decrement → order placed
- End-to-end: cancellation request → admin approval → order status
  updated → stock correctly restocked
- End-to-end: verified-purchase review → badge correctly shown after
  admin approval
- Confirmed the friend's existing search/price/rating filters on
  `shop.php` still work correctly alongside the new stock/wishlist
  additions
- Full live HTTP smoke test of every public and admin page — no PHP
  errors or warnings
