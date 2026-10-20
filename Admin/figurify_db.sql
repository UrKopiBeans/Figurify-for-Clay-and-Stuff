--  Default accounts (password nilang lahat: password):
--    owner@gmail.com    -> admin
--    staff@gmail.com    -> staff
--    customer@gmail.com -> customer
-- =====================================================================

DROP DATABASE IF EXISTS figurify_db;

CREATE DATABASE figurify_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE figurify_db;


/* =========================================================
   1. USERS
========================================================= */

CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('customer', 'staff', 'admin') NOT NULL DEFAULT 'customer',
    profile_picture VARCHAR(255) NULL,
    contact_number VARCHAR(20) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen   DATETIME NULL DEFAULT NULL
);


/* =========================================================
   2. EMAIL VERIFICATIONS (Login/send_code.php, verify_code.php)
========================================================= */

CREATE TABLE email_verifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NOT NULL,
    code        VARCHAR(10) NOT NULL,
    expires_at  DATETIME NOT NULL,
    verified    TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email (email)
);


/* =========================================================
   3. ORDERS (Commission/submit_order.php + status updates
      sa staff/owner/upload files)
========================================================= */

CREATE TABLE orders (
    order_id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT NOT NULL,

    order_type           VARCHAR(20) NOT NULL,              -- rush / nonrush
    order_method         VARCHAR(30) NOT NULL DEFAULT 'reference', -- reference (Image Submission) / create_style (Dress Up)
    booking_date         DATE NOT NULL,

    rush_fee             DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal             DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount         DECIMAL(10,2) NOT NULL DEFAULT 0,

    status               VARCHAR(30) NOT NULL DEFAULT 'pending',   -- tingnan ang helpers/order_workflow.php para sa allowed transitions
    revision_count       TINYINT UNSIGNED NOT NULL DEFAULT 0,      -- one-time revision: 0 o 1
    decline_reason       VARCHAR(255) NULL,                 -- dahilan kapag na-decline/reject ng staff o owner
    review_token         VARCHAR(64) NULL,
    quote_expires_at     DATETIME NULL,

    shipping_name        VARCHAR(150) NULL,
    shipping_address     VARCHAR(255) NULL,
    pin_address          VARCHAR(255) NULL,
    shipping_contact     VARCHAR(20) NULL,
    courier              VARCHAR(30) NULL,

    tracking_number      VARCHAR(100) NULL,
    shipping_receipt     VARCHAR(255) NULL,                 -- resibo ng courier (larawan) na inilagay sa Ship Out
    shipping_fee         DECIMAL(10,2) NULL,
    balance_to_pay       DECIMAL(10,2) NULL,

    payment_reference    VARCHAR(100) NULL,
    payment_proof        VARCHAR(255) NULL,

    balance_payment_reference  VARCHAR(100) NULL,  -- balance (bago i-ship out) — hiwalay sa unang downpayment sa itaas
    balance_proof               VARCHAR(255) NULL,
    balance_paid_at             DATETIME NULL,

    refund_account_name    VARCHAR(150) NULL,
    refund_account_number  VARCHAR(50) NULL,
    refund_method           VARCHAR(50) NULL,

    /* REFUND PROCESS (owner lang) — napupuno kapag na-decline ang order na
       may bayad na, o kapag sinimulan ng owner ang refund (hal. lumampas sa
       booking date). status: for_refund -> refunded (o cancelled kung walang
       pumasok na bayad) */
    refund_reason           VARCHAR(255) NULL,
    refund_type             VARCHAR(10) NULL,      -- full / partial
    refund_amount           DECIMAL(10,2) NULL,
    refund_reference        VARCHAR(100) NULL,     -- reference no. ng ipinadalang refund
    refund_proof            VARCHAR(255) NULL,     -- screenshot / resibo ng refund
    refund_requested_at     DATETIME NULL,
    refunded_at             DATETIME NULL,

    paid_at              DATETIME NULL,
    shipped_at           DATETIME NULL,                     -- naka-set sa sandaling i-Ship Out (status -> 'shipped')
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_orders_user (user_id),
    INDEX idx_orders_status (status),

    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
);


/* =========================================================
   4. ORDER FIGURES (bawat "figure" na idinagdag sa cart)
========================================================= */

CREATE TABLE order_figures (
    figure_id            INT AUTO_INCREMENT PRIMARY KEY,
    order_id             INT NOT NULL,

    figure_style         VARCHAR(50) NOT NULL,
    product_type         VARCHAR(50) NOT NULL,
    size_value           VARCHAR(50) NULL,
    size_label           VARCHAR(100) NULL,
    figure_name          VARCHAR(150) NOT NULL,
    notes                TEXT NULL,

    product_price        DECIMAL(10,2) NOT NULL DEFAULT 0,
    name_fee             DECIMAL(10,2) NOT NULL DEFAULT 0,
    figure_total         DECIMAL(10,2) NOT NULL DEFAULT 0,

    /* Box add-on (Custom Funko Box / Hirono Blind Box) */
    box_addon_type       VARCHAR(30) NULL,
    box_addon_price      DECIMAL(10,2) NULL DEFAULT 0,

    funko_box_type       VARCHAR(50) NULL,
    funko_box_name       VARCHAR(100) NULL,
    funko_box_number     VARCHAR(50) NULL,
    funko_box_color      VARCHAR(50) NULL,

    hirono_blind_type    VARCHAR(50) NULL,
    hirono_box_design    VARCHAR(50) NULL,
    hirono_box_color     VARCHAR(50) NULL,
    hirono_letter        VARCHAR(10) NULL,
    hirono_nickname      VARCHAR(100) NULL,
    hirono_date          VARCHAR(50) NULL,
    hirono_blind_items       TEXT NULL,        -- JSON: {"tear_blind_paper":50,...}
    hirono_blind_items_total DECIMAL(10,2) NULL DEFAULT 0,

    quoted_price         DECIMAL(10,2) NULL,   -- per-figure price na inilagay ng staff/owner sa quotation

    dressup_data         LONGTEXT NULL,        -- JSON ng Dress Up (Create Style) design: gender, skin, hair, top, bottom, shoes, colors, 3D model paths

    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_figures_order (order_id),

    CONSTRAINT fk_figures_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE
);


/* =========================================================
   5. ORDER FIGURE IMAGES (reference images ng customer)
========================================================= */

CREATE TABLE order_figure_images (
    image_id     INT AUTO_INCREMENT PRIMARY KEY,
    figure_id    INT NOT NULL,
    image_path   VARCHAR(255) NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_figimg_figure (figure_id),

    CONSTRAINT fk_figimg_figure
        FOREIGN KEY (figure_id) REFERENCES order_figures(figure_id)
        ON DELETE CASCADE
);


/* =========================================================
   6. ORDER FIGURE BOX IMAGES (Hirono Blind Box reference)
========================================================= */

CREATE TABLE order_figure_box_images (
    box_image_id  INT AUTO_INCREMENT PRIMARY KEY,
    figure_id     INT NOT NULL,
    image_path    VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_boximg_figure (figure_id),

    CONSTRAINT fk_boximg_figure
        FOREIGN KEY (figure_id) REFERENCES order_figures(figure_id)
        ON DELETE CASCADE
);


/* =========================================================
   7. ORDER PROGRESS UPDATES
      (base table + 4 migrations mo na sinama na dito:
       staff_reply, is_final, pinalawak na
       customer_response ENUM, at auto_approved)
========================================================= */

CREATE TABLE order_progress_updates (
    update_id         INT AUTO_INCREMENT PRIMARY KEY,

    order_id          INT NOT NULL,

    sent_by_user_id   INT NULL,
    sent_by_role      ENUM('staff', 'admin') NOT NULL DEFAULT 'staff',

    image_path        VARCHAR(255) NOT NULL,
    staff_message     VARCHAR(500) NULL,

    customer_response ENUM('pending', 'approved', 'revision', 'declined', 'in_progress')
                        NOT NULL DEFAULT 'pending',
    revision_note     VARCHAR(1000) NULL,
    staff_reply       VARCHAR(500) NULL,
    is_final          TINYINT(1) NOT NULL DEFAULT 0,
    auto_approved     TINYINT(1) NOT NULL DEFAULT 0,

    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at      TIMESTAMP NULL,
    reminder_sent_at  DATETIME NULL,                  -- paalala bago ang auto-approve

    INDEX idx_order_id (order_id),
    INDEX idx_pending_lookup (order_id, customer_response),

    CONSTRAINT fk_progress_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_progress_sender
        FOREIGN KEY (sent_by_user_id) REFERENCES users(user_id)
        ON DELETE SET NULL
);


/* =========================================================
   8. NOTIFICATIONS
========================================================= */

CREATE TABLE notifications (
    notification_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    order_id         INT NULL,
    message          VARCHAR(500) NOT NULL,
    is_read          TINYINT(1) NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_notif_user (user_id),

    CONSTRAINT fk_notif_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_notif_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE SET NULL
);


/* =========================================================
   9. REVIEWS (customer feedback sa isang "completed" order —
      pwede isumite sa My Orders o sa Gmail link, isa lang
      bawat order)
========================================================= */

CREATE TABLE reviews (
    review_id     INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT NOT NULL UNIQUE,
    user_id       INT NOT NULL,

    rating        TINYINT NOT NULL,
    review_text   TEXT NULL,

    submitted_via ENUM('system', 'email') NOT NULL DEFAULT 'system',

    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_reviews_order
        FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
);


/* =========================================================
   10. REVIEW IMAGES (mga larawang isinama sa isang review)
========================================================= */

CREATE TABLE review_images (
    review_image_id  INT AUTO_INCREMENT PRIMARY KEY,
    review_id        INT NOT NULL,
    image_path       VARCHAR(255) NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_review_images_review (review_id),

    CONSTRAINT fk_review_images_review
        FOREIGN KEY (review_id) REFERENCES reviews(review_id)
        ON DELETE CASCADE
);


/* =========================================================
   11. SITE CONTENT (Content Management ng owner sa Home page)
   ---------------------------------------------------------
   Key-value table. Bawat editable na text/image sa Home.php
   (hero, about, featured products, commission CTA, footer/
   contact) ay may sariling content_key dito. Kapag walang
   laman/row pa para sa isang key, gagamitin na lang ng
   Home.php yung default text na naka-hardcode sa kanya, kaya
   safe ito kahit bago pa lang gawin ang table na ito.
========================================================= */

CREATE TABLE site_content (
    content_key    VARCHAR(60) NOT NULL PRIMARY KEY,
    content_value  TEXT NOT NULL,
    updated_by     INT NULL,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_content_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(user_id)
        ON DELETE SET NULL
);


/* =========================================================
   12. COLLECTION ITEMS (Collection Management ng owner —
       mga ready-made figure sa Collection page)
========================================================= */

CREATE TABLE collection_items (
    item_id        INT AUTO_INCREMENT PRIMARY KEY,
    section        VARCHAR(20)  NOT NULL DEFAULT 'normal',
    category       VARCHAR(30)  NOT NULL DEFAULT 'chibi',
    style          VARCHAR(60)  NOT NULL DEFAULT '',
    type           VARCHAR(60)  NOT NULL DEFAULT '',
    size           VARCHAR(30)  NOT NULL DEFAULT '',
    price          VARCHAR(30)  NOT NULL DEFAULT '',
    image          VARCHAR(255) NOT NULL DEFAULT '',
    is_bestseller  TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order     INT          NOT NULL DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


/* =========================================================
   13. ACTIVITY LOG (Activity Log page ng owner)
========================================================= */

CREATE TABLE activity_log (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    actor_id    INT NULL,
    actor_name  VARCHAR(100) NOT NULL,
    actor_role  VARCHAR(20) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    order_id    INT NULL,
    created_at  DATETIME NOT NULL,
    INDEX idx_created_at (created_at)
);


/* =========================================================
   13b. ORDER STATUS HISTORY (helpers/order_workflow.php) —
        bawat status transition, kasama ang transient steps
        (hal. PAYMENT_APPROVED, FINALIZED). Hindi nae-edit sa UI.
========================================================= */

CREATE TABLE order_status_history (
    history_id      INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL,
    from_state      VARCHAR(40) NULL,
    to_state        VARCHAR(40) NOT NULL,
    from_status     VARCHAR(30) NULL,
    to_status       VARCHAR(30) NULL,
    action          VARCHAR(50) NOT NULL,
    actor_id        INT NULL,
    actor_role      VARCHAR(20) NOT NULL,
    actor_name      VARCHAR(100) NULL,
    reason          VARCHAR(1000) NULL,
    details         TEXT NULL,
    created_at      DATETIME NOT NULL,
    INDEX idx_history_order (order_id, history_id)
);


/* =========================================================
   14. LOGIN ATTEMPTS (lockout pagkatapos ng sunod-sunod na maling login)
========================================================= */

CREATE TABLE login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(255) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_email_time (email, attempted_at)
);


/* =========================================================
   15. ACTION ATTEMPTS (rate limit sa pag-order, pag-send ng code, atbp.)
========================================================= */

CREATE TABLE action_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    action_type  VARCHAR(50) NOT NULL,
    identifier   VARCHAR(255) NOT NULL,
    attempted_at DATETIME NOT NULL,
    INDEX idx_action_identifier_time (action_type, identifier, attempted_at)
);


/* =========================================================
   BANNED EMAILS (mga account na na-ban sa User Management)
========================================================= */

CREATE TABLE banned_emails (
    email      VARCHAR(255) NOT NULL PRIMARY KEY,
    reason     VARCHAR(255) NULL,
    banned_by  INT NULL,
    banned_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


/* =========================================================
   REMEMBER TOKENS ("Remember me" sa login - helpers/remember_helper.php)
   Hash lang ng token ang naka-save, hindi ang password.
========================================================= */

CREATE TABLE remember_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    selector    CHAR(24) NOT NULL UNIQUE,
    token_hash  CHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_remember_user (user_id),

    CONSTRAINT fk_remember_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
);


/* =========================================================
   DEFAULT LOGIN ACCOUNTS
========================================================= */

INSERT INTO users (full_name, email, password, role)
VALUES
(
    'Figurify Owner',
    'owner@gmail.com',
    '$2y$10$QBeMig9UVZV8QVCXFddiZuX8adHkbR1rBk.jmNVyWgfp5PQoRZU1S',
    'admin'
),
(
    'Figurify Staff',
    'staff@gmail.com',
    '$2y$10$QBeMig9UVZV8QVCXFddiZuX8adHkbR1rBk.jmNVyWgfp5PQoRZU1S',
    'staff'
),
(
    'Test Customer',
    'customer@gmail.com',
    '$2y$10$QBeMig9UVZV8QVCXFddiZuX8adHkbR1rBk.jmNVyWgfp5PQoRZU1S',
    'customer'
)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    password  = VALUES(password),
    role      = VALUES(role);
