<?php
/**
 * config/db.php
 * -----------------------------------------------------------
 * Central database connection for Nordic Nest.
 * Uses PDO with prepared statements throughout the app to
 * prevent SQL injection (never build queries by concatenating
 * user input directly into SQL strings).
 *
 * Update the four constants below to match your local
 * (XAMPP/WAMP) or hosted (InfinityFree/GoogieHost) MySQL
 * credentials before running the site.
 * -----------------------------------------------------------
 */

// ---- EDIT THESE FOR YOUR ENVIRONMENT -----------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'nordicnest');
define('DB_USER', 'root');
define('DB_PASS', '');
// -------------------------------------------------------------

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
    ]);
} catch (PDOException $e) {
    // Never leak DB credentials or raw driver errors to the browser.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Sorry — something went wrong connecting to the database. Please try again shortly.');
}
