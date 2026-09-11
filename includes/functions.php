<?php
/**
 * includes/functions.php
 * -----------------------------------------------------------
 * Shared helpers used across every page: session bootstrap,
 * authentication / role guards, CSRF protection, input
 * sanitisation and small validation helpers, and flash
 * messages for user feedback after redirects.
 * -----------------------------------------------------------
 */

// Start the session once, with hardened cookie settings.
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,   // JS can't read the session cookie (XSS mitigation)
        'cookie_samesite' => 'Lax',  // basic CSRF mitigation on cross-site requests
        'use_strict_mode' => true,
    ]);
}

require_once __DIR__ . '/../config/db.php';

/* ------------------------------------------------------------
 * Output / input helpers
 * ---------------------------------------------------------- */

// Always escape dynamic values before printing them into HTML.
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Trim + strip surrounding whitespace from posted text fields.
function clean(?string $value): string
{
    return trim($value ?? '');
}

/* ------------------------------------------------------------
 * CSRF protection — every state-changing form includes a
 * hidden csrf_token field, checked on submission.
 * ---------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_check(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/* ------------------------------------------------------------
 * Auth helpers
 * ---------------------------------------------------------- */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

// Redirect helper (always exit immediately after).
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

// Call at the top of any page that requires a logged-in user.
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Please log in to continue.';
        redirect('login.php');
    }
}

// Call at the top of any page that is admin-only.
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        $_SESSION['flash_error'] = 'You do not have permission to view that page.';
        redirect('../index.php');
    }
}

/* ------------------------------------------------------------
 * Flash messages — one-time notices shown after a redirect
 * (e.g. "Message sent", "Invalid email or password").
 * ---------------------------------------------------------- */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function flash_get(string $type): ?string
{
    if (empty($_SESSION['flash_' . $type])) {
        return null;
    }
    $msg = $_SESSION['flash_' . $type];
    unset($_SESSION['flash_' . $type]);
    return $msg;
}

/* ------------------------------------------------------------
 * Shopping cart — stored in the session so guests can add
 * items without an account. Shape: $_SESSION['cart'] =
 * [ product_id => quantity, ... ]. An order row is only ever
 * created once a logged-in member checks out (see checkout.php).
 * ---------------------------------------------------------- */
const CART_MAX_QTY_PER_ITEM = 20;

function cart_get(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_add(int $productId, int $qty = 1): void
{
    $qty = max(1, $qty);
    $cart = cart_get();
    $current = $cart[$productId] ?? 0;
    $cart[$productId] = min(CART_MAX_QTY_PER_ITEM, $current + $qty);
    $_SESSION['cart'] = $cart;
}

function cart_set_qty(int $productId, int $qty): void
{
    $cart = cart_get();
    if ($qty <= 0) {
        unset($cart[$productId]);
    } else {
        $cart[$productId] = min(CART_MAX_QTY_PER_ITEM, $qty);
    }
    $_SESSION['cart'] = $cart;
}

function cart_remove(int $productId): void
{
    $cart = cart_get();
    unset($cart[$productId]);
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

// Total number of individual items (for the nav badge).
function cart_count(): int
{
    return array_sum(cart_get());
}

/**
 * Loads the current cart contents against live product data.
 * Silently drops (and re-saves) any line whose product has since
 * been deleted, so the cart never references a dead product id.
 * Returns a list of ['product' => row, 'qty' => int, 'line_total' => float].
 */
function cart_items(PDO $pdo): array
{
    $cart = cart_get();
    if (empty($cart)) {
        return [];
    }

    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, category, price, image_url, stock FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();
    $productsById = [];
    foreach ($products as $p) {
        $productsById[(int)$p['id']] = $p;
    }

    $items = [];
    $changed = false;
    foreach ($cart as $productId => $qty) {
        $productId = (int)$productId;
        if (!isset($productsById[$productId])) {
            unset($cart[$productId]); // product no longer exists — drop it
            $changed = true;
            continue;
        }
        $product = $productsById[$productId];

        // Stock may have dropped (another customer bought the last ones)
        // since this was added to the session cart — cap it silently here
        // so the displayed total is always accurate; checkout.php does the
        // authoritative re-check inside its transaction.
        $availableStock = (int)$product['stock'];
        if ($qty > $availableStock) {
            $qty = $availableStock;
            $cart[$productId] = $qty;
            $changed = true;
        }
        if ($qty <= 0) {
            unset($cart[$productId]);
            $changed = true;
            continue;
        }

        $lineTotal = (float)$product['price'] * $qty;
        $items[] = ['product' => $product, 'qty' => (int)$qty, 'line_total' => $lineTotal];
    }

    if ($changed) {
        $_SESSION['cart'] = $cart;
    }

    return $items;
}

function cart_total(array $items): float
{
    return array_sum(array_column($items, 'line_total'));
}

/* ------------------------------------------------------------
 * Validation helpers
 * ---------------------------------------------------------- */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Basic password strength rule: at least 8 characters incl. one letter and one number.
function is_valid_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

/* ------------------------------------------------------------
 * ENHANCEMENT: Password reset helpers
 * ---------------------------------------------------------- */
function generate_reset_token(): string
{
    return bin2hex(random_bytes(32));
}

function log_email_notification(string $to, string $subject, string $body): void
{
    $log_file = __DIR__ . '/../logs/email_log.txt';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $message = sprintf(
        "[%s] TO: %s | SUBJECT: %s | BODY: %s\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        substr($body, 0, 100) . '...'
    );
    file_put_contents($log_file, $message, FILE_APPEND);
}

/* ------------------------------------------------------------
 * NEW: Wishlist — "save for later" list, one row per member per
 * product. Unlike the cart, this always requires an account
 * (tied to users.id, not the session), so it persists across
 * devices/browsers once a member logs back in.
 * ---------------------------------------------------------- */
function wishlist_toggle(PDO $pdo, int $userId, int $productId): bool
{
    // Returns true if the product ended up ON the wishlist, false if removed.
    $stmt = $pdo->prepare('SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    if ($stmt->fetch()) {
        $del = $pdo->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?');
        $del->execute([$userId, $productId]);
        return false;
    }
    $ins = $pdo->prepare('INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)');
    $ins->execute([$userId, $productId]);
    return true;
}

function wishlist_product_ids(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT product_id FROM wishlists WHERE user_id = ?');
    $stmt->execute([$userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function wishlist_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM wishlists WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int)$stmt->fetch()['c'];
}

/* ------------------------------------------------------------
 * NEW: Coupons — validated server-side at checkout, never
 * trusted from a hidden form field.
 * ---------------------------------------------------------- */
function coupon_validate(PDO $pdo, string $code, float $subtotal): array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['ok' => false, 'error' => 'Please enter a coupon code.'];
    }

    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE code = ?');
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon || !$coupon['active']) {
        return ['ok' => false, 'error' => 'That coupon code is not valid.'];
    }
    if ($coupon['expires_at'] !== null && $coupon['expires_at'] < date('Y-m-d')) {
        return ['ok' => false, 'error' => 'That coupon has expired.'];
    }
    if ($coupon['max_uses'] !== null && (int)$coupon['times_used'] >= (int)$coupon['max_uses']) {
        return ['ok' => false, 'error' => 'That coupon has already been fully redeemed.'];
    }
    if ($subtotal < (float)$coupon['min_order']) {
        return ['ok' => false, 'error' => 'This coupon needs a minimum order of $' . number_format((float)$coupon['min_order'], 2) . '.'];
    }

    return ['ok' => true, 'coupon' => $coupon];
}

function coupon_discount_amount(array $coupon, float $subtotal): float
{
    $discount = $coupon['discount_type'] === 'percent'
        ? $subtotal * ((float)$coupon['discount_value'] / 100)
        : (float)$coupon['discount_value'];

    return round(min($discount, $subtotal), 2);
}

/* ------------------------------------------------------------
 * NEW: Order cancellation / refund requests — a member can ask
 * to cancel a still-pending/processing order, or request a
 * refund on one that's already shipped/completed.
 * ---------------------------------------------------------- */
function order_request_eligible_type(array $order): ?string
{
    return match ($order['status']) {
        'pending', 'processing' => 'cancellation',
        'shipped', 'completed'  => 'refund',
        default                 => null,
    };
}

function order_has_open_request(PDO $pdo, int $orderId): bool
{
    $stmt = $pdo->prepare("SELECT id FROM order_requests WHERE order_id = ? AND status = 'pending'");
    $stmt->execute([$orderId]);
    return (bool)$stmt->fetch();
}

function order_latest_request(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM order_requests WHERE order_id = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/* ------------------------------------------------------------
 * NEW: Verified-purchase reviews — a review is only ever
 * flagged as a verified purchase by checking the database
 * ourselves at submission time (whether this user's order_items
 * contain the product, on an order that shipped/completed) —
 * never trusted from the submission form.
 * ---------------------------------------------------------- */
function user_has_purchased_product(PDO $pdo, int $userId, int $productId): bool
{
    $stmt = $pdo->prepare(
        "SELECT oi.id FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.user_id = ? AND oi.product_id = ? AND o.status IN ('shipped', 'completed')
         LIMIT 1"
    );
    $stmt->execute([$userId, $productId]);
    return (bool)$stmt->fetch();
}
