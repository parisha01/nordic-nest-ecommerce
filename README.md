# Nordic Nest — Dynamic Website (ICT726 Assignment 4)

A PHP + MySQL dynamic web application for **Nordic Nest**, a Scandinavian-inspired
home goods retailer, built for ICT726 Web Development, Assignment 4.

This project extends the Assignment 3 static site into a full data-driven
application with user authentication, role-based access control, a
database-backed product catalogue, a customer review system, and SEO
optimisation.

---

## 1. Tech stack

- **PHP 8+** (no framework — plain PHP with PDO)
- **MySQL / MariaDB**
- **HTML5, CSS3, vanilla JavaScript** (no frontend framework)
- Sessions for auth, **bcrypt** (`password_hash()`) for passwords, CSRF
  tokens on every form, prepared statements everywhere (no raw SQL
  concatenation)

---

## 2. Local setup (XAMPP / WAMP / MAMP)

1. Install [XAMPP](https://www.apachefriends.org/) (or WAMP/MAMP) if you
   don't already have it, and start **Apache** and **MySQL** from the
   control panel.
2. Copy this whole `nordic-nest-dynamic` folder into your server's web
   root — e.g. `C:\xampp\htdocs\nordic-nest-dynamic` on Windows, or
   `/Applications/XAMPP/htdocs/nordic-nest-dynamic` on Mac.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
   - Click **New** in the left sidebar, create a database called
     `nordicnest` (or any name you like).
   - Select it, click **Import**, choose `database/schema.sql` from this
     project, and click **Go**. This creates all 7 tables and seeds 8
     sample products.
4. Open `config/db.php` and confirm the constants match your setup:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'nordicnest');   // match the DB name you created
   define('DB_USER', 'root');
   define('DB_PASS', '');             // XAMPP's default MySQL has no password
   ```
5. Visit `http://localhost/nordic-nest-dynamic/index.php` in your
   browser — the shop should load with the 8 seeded products.
6. **Create your admin account:** visit
   `http://localhost/nordic-nest-dynamic/setup/create_admin.php` once,
   fill in a name/email/password, and submit. This uses PHP's
   `password_hash()` to store the password correctly (a plain `.sql`
   file can't do this safely, which is why this step exists).
   **Delete `setup/create_admin.php` (or move it out of the folder)
   once you've created your admin account** — leaving it live would let
   anyone create an admin account on your site.
7. Log in at `login.php` with the account you just created — you'll be
   redirected to `admin/dashboard.php`.

---

## 3. Database schema

| Table | Purpose | Key columns / relationships |
|---|---|---|
| `users` | Registered accounts | `role` ENUM('admin','member'); passwords stored as bcrypt hashes, never plain text |
| `products` | Shop catalogue | `category` ENUM constrained to 4 values; admin-managed via CRUD |
| `testimonials` | Customer reviews | `user_id` → `users.id` (FK), `product_id` → `products.id` (FK, nullable for general feedback); `status` ENUM('pending','approved','rejected') drives the moderation workflow |
| `contact_messages` | Enquiries from the public contact form | `status` ENUM('new','read') |
| `newsletter_subscribers` | Homepage newsletter sign-ups | unique email constraint |
| `orders` | Placed orders | `user_id` → `users.id` (FK); `status` ENUM('pending','processing','shipped','completed','cancelled') drives the fulfilment workflow; snapshotted delivery details |
| `order_items` | Line items for each order | `order_id` → `orders.id` (FK, cascades on delete); `product_id` → `products.id` (FK, nullable); `product_name`/`unit_price` are snapshotted at purchase time so order history stays correct even if a product is later edited or deleted |

**Relationships:** `testimonials.user_id`/`product_id` and
`orders.user_id`/`order_items.order_id`/`order_items.product_id` are all
foreign keys, demonstrating a proper relational schema rather than a
single flat table. Deleting a user cascades their reviews and orders;
deleting a product sets `product_id` to NULL on any reviews or order
line items that referenced it (so the historical record isn't lost).

**Cart vs. orders:** the shopping cart itself is *not* a database table —
it lives in the PHP session (`$_SESSION['cart']`, see
`includes/functions.php`) so guests can add items without an account.
A real `orders` row (plus its `order_items`) is only ever created once a
logged-in member completes checkout.

Full definitions with data types, constraints and seed data are in
[`database/schema.sql`](database/schema.sql).

---

## 4. Roles & access control

| Role | Who | Can do |
|---|---|---|
| **Guest** (not logged in) | Any site visitor | Browse shop/gallery/about/testimonials, **add products to a cart and edit it**, submit the contact form, subscribe to the newsletter, register/log in |
| **Member** | Any registered account (default role on sign-up) | Everything a guest can, plus: **check out and place orders, view their own order history**, edit their own profile & password, submit product reviews (held as "pending" until approved) |
| **Admin** | Promoted via `setup/create_admin.php` | Everything a member can, plus: full CRUD on products, **view every order and update its fulfilment status**, approve/reject/delete any review, view & manage contact form enquiries |

Access control is enforced **on every request**, not just hidden in the
navigation menu:
- `require_login()` (in `includes/functions.php`) redirects to
  `login.php` if you try to submit a review, check out, or view
  `profile.php`/`order.php` while logged out.
- `require_admin()` redirects non-admins away from anything under
  `/admin/`, even if they guess the URL directly.
- `order.php` additionally checks that the order belongs to the
  logged-in member (or that the viewer is an admin) before showing it —
  so member A can never view member B's order by guessing an id.

**Cart → checkout flow:** anyone (including guests) can add items to
the cart from `shop.php` or `product.php`. Clicking **"Proceed to
checkout"** on `cart.php` while logged out shows a prompt to log in or
register instead of the cart contents being lost — both `login.php` and
`register.php` accept a `?next=checkout` parameter (from a small
server-side whitelist, not a raw redirect) that sends the user straight
back to `checkout.php` once authenticated, cart intact.

---

## 5. Key functionality checklist (maps to the assignment brief)

- ✅ **User authentication** — register, log in, log out (`register.php`,
  `login.php`, `logout.php`); two roles (`admin`, `member`); public/guest
  access covers the brief's third example ("normal").
- ✅ **Secure password storage** — `password_hash()` / `password_verify()`
  (bcrypt), never plain text.
- ✅ **Database with relationships** — 7 tables, 5 foreign keys, proper
  data types (ENUM, DECIMAL, TIMESTAMP).
- ✅ **PHP + MySQL CRUD** — full Create/Read/Update/Delete on `products`
  (`admin/products.php`, `admin/product_form.php`); Read/Update/Delete on
  `testimonials` (moderation) and `contact_messages` (inbox); Create/Read
  on `newsletter_subscribers`.
- ✅ **Cart & checkout** — session-based cart (`cart.php`) that works for
  guests; checkout (`checkout.php`) requires a logged-in member and
  writes an `orders` + `order_items` row inside a DB transaction; member
  order history on `profile.php`/`order.php`; admin order management
  (`admin/orders.php`, `admin/order_detail.php`) with status updates and
  a revenue figure on the dashboard.
- ✅ **At least two validated forms** — there are actually seven:
  register, login, contact, review submission, profile update, password
  change, and the admin product form — all validated server-side in PHP
  (never trusting client-side checks alone), with sticky field values and
  inline error messages.
- ✅ **Error handling & validation** — every form re-displays with the
  submitted values and per-field error text on failure; database errors
  are caught and never leak raw SQL/driver messages to the browser
  (`config/db.php`).
- ✅ **Accessibility** — semantic HTML5 landmarks, skip link, ARIA
  (`aria-current`, `aria-live`, `aria-expanded`, `aria-modal`,
  `role="dialog"`), labelled form fields, alt text on every image.
- ✅ **Responsiveness** — reuses the Assignment 3 CSS design system with
  the same three breakpoints (900/720/560px).
- ✅ **SEO** — see Section 6 below.
- ✅ **Privacy & security** — `privacy.php` notice; bcrypt hashing; CSRF
  tokens on every POST form; PDO prepared statements everywhere; HTTP-only
  + SameSite session cookies; session ID regenerated on login/register to
  prevent session fixation.

---

## 6. SEO notes

### Keyword research (informal, for a coursework prototype)
Target keywords were chosen to match how someone shopping for this kind
of product would actually search, based on the product categories:

| Page | Primary keyword(s) | Notes |
|---|---|---|
| Home | "scandinavian home goods", "sustainable furniture australia" | Brand + category intent |
| Shop | "buy oak furniture online", "sustainable lighting textiles kitchenware" | Transactional intent |
| Product pages | "[product name] + material" e.g. "oak lounge chair paper cord seat" | Long-tail, high-intent |
| About | "sustainable furniture brand story" | Informational/brand trust |
| Testimonials | "nordic nest reviews" | Branded + trust signal |

### On-page SEO implemented
- Unique `<title>` and `<meta name="description">` on **every** page
  (built dynamically per product on `product.php`).
- One `<h1>` per page, with a logical heading hierarchy below it.
- `<link rel="canonical">` on every page.
- Open Graph tags (`og:title`, `og:description`, `og:type`) for social
  sharing previews.
- **JSON-LD structured data** (`schema.org/Product` with `AggregateRating`)
  on every product page — this is what allows search engines to show
  star ratings/price directly in search results.
- Descriptive `alt` text on every image (also an accessibility
  requirement, doing double duty for image search).
- `robots.txt` and `sitemap.xml` at the project root — remember to
  replace `YOUR-DOMAIN-HERE` in both files once you've hosted the site.
- Clean, human-readable URLs for filtering (`shop.php?category=Lighting`
  rather than an opaque ID).

---

## 7. Hosting on a free host (e.g. InfinityFree)

1. Sign up at [infinityfree.net](https://infinityfree.net) (or
   GoogieHost) and create a new hosting account + subdomain.
2. In their control panel, open **MySQL Databases**, create a new
   database, and note the host, database name, username and password
   they give you (InfinityFree's DB host is usually **not** `localhost`
   — copy it exactly as shown).
3. Open **phpMyAdmin** from their control panel and import
   `database/schema.sql` the same way as the local setup.
4. Update `config/db.php` with the **hosting provider's** DB credentials
   (not your local XAMPP ones).
5. Upload every file in this folder via their **File Manager** or FTP
   (e.g. FileZilla) into the `htdocs` (InfinityFree) root.
6. Visit `https://your-subdomain.infinityfreeapp.com/setup/create_admin.php`
   once to create your admin account, then delete that file via the file
   manager.
7. Update `robots.txt` and `sitemap.xml` with your real domain.
8. Test the whole flow live: register a member account, submit a review,
   log in as admin and approve it, submit the contact form, check it
   appears in the admin inbox.

---

## 8. Project structure

```
nordic-nest-dynamic/
├── admin/                  # Admin-only pages (require_admin() on every file)
│   ├── dashboard.php
│   ├── products.php        # list + delete
│   ├── product_form.php    # add / edit (create + update)
│   ├── orders.php          # order list, filterable by status
│   ├── order_detail.php    # single order view + status update
│   ├── testimonials.php    # review moderation
│   └── messages.php        # contact form inbox
├── config/
│   └── db.php               # PDO connection (edit credentials here)
├── database/
│   └── schema.sql            # full schema + seed data
├── includes/
│   ├── functions.php        # session, auth guards, CSRF, cart, validation helpers
│   ├── header.php           # shared <head> + nav (incl. cart count)
│   └── footer.php           # shared footer + script tag
├── setup/
│   └── create_admin.php     # ONE-TIME use — delete after creating your admin
├── css/style.css
├── js/script.js
├── index.php, shop.php, product.php, about.php, gallery.php,
│   testimonials.php, contact.php, privacy.php
├── cart.php, checkout.php, order.php   # cart / checkout / order history
├── register.php, login.php, logout.php, profile.php
├── robots.txt, sitemap.xml, .htaccess
└── README.md                 # this file
```

---

## 9. ENHANCEMENTS (Beyond assignment requirements)

The following features have been added to strengthen the project:

### ✨ 1. Advanced Product Filtering
- **Price Range Filter** — Filter products by minimum and maximum price
- **Rating Filter** — Show only products with X stars or higher
- **Search Functionality** — Full-text search across product names and descriptions
- **Combined Filters** — All filters work together and can be mixed
- **Rating Display** — Average star rating and review count shown on each product card

### 📧 2. Password Reset System
- **Forgot Password Page** (`forgot_password.php`) — Users can request a password reset
- **Reset Link** — One-time use reset links that expire after 1 hour
- **Email Notifications** (logged to file) — Simulates email sending for testing
- **Token-based Security** — Cryptographically secure reset tokens
- Updated database schema to support reset tokens

### 📦 3. Order Tracking Timeline
- **Visual Status Timeline** — Shows order progress from Pending → Processing → Shipped → Delivered
- **Status Indicators** — Color-coded badges (✓ completed, 🔄 in progress, 📋 pending)
- **User-friendly Display** — Easy to see where their order is in the fulfillment process

### 🖼️ 4. Image Upload Guidance
- Enhanced product form with links to free image hosting services
- Guide for admins to upload images via Imgur, Cloudinary, or Picsum

### 📊 5. Email Notification System
- Added `log_email_notifications()` function in `includes/functions.php`
- All account/order events logged to `logs/email_log.txt`
- Ready for integration with real mail servers (Mailgun, SendGrid, etc.)
- Events logged:
  - Password reset requests
  - Successful password changes
  - Account registration
  - Order confirmations (ready for implementation)

### 🔗 6. Improved Navigation
- Added "Forgot Password" link to login page
- Better UX flow for lost passwords

---

## 10. AI assistance declaration

Parts of this application's boilerplate (repetitive CRUD patterns,
initial PHP syntax for PDO/session handling) were scaffolded with AI
assistance, then customised and adapted by hand — reused CSS class names
from the team's existing Assignment 3 stylesheet, wrote the actual
validation rules and error messages, and tailored the database schema
and admin workflows specifically to the Nordic Nest product/review/
enquiry model rather than a generic template. See the Assignment 3
report for the fuller AI use declaration format, if your unit requires
one for this submission too.

---

## 10. Known limitations (by design, per the assignment brief)

- Checkout records an order but does **not** process a real payment —
  no payment gateway is integrated, so there's nothing to configure
  and no real card details are ever collected (`privacy.php` reminds
  users of this too). A live version would add a gateway like Stripe.
- There's no per-product stock/inventory tracking, so the cart doesn't
  check availability — fine for a coursework catalogue of 8 items, but
  a real store would decrement stock on order and prevent overselling.
- Newsletter sign-up stores emails but doesn't send real emails (no mail
  server configured) — a live version would integrate a transactional
  email service.
- Product images use free `picsum.photos` placeholder URLs — replace
  `image_url` values in the database with real product photography for
  a production version.
#   n o r d i c - n e s t - e c o m m e r c e  
 