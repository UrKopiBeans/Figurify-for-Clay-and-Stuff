<?php

/* =====================================================================
   SECURITY HELPER   (helpers/security_helper.php)

   Pinagsama sa iisang file ang dating 10 helper:
     session_helper, access_helper, csrf_helper, rate_limit_helper,
     remember_helper, ban_helper, upload_helper, proof_helper,
     resend_cooldown_helper, env_config

   Walang pinalitang pangalan ng function, kaya ang mga page na gumagamit
   nito ay hindi kailangang galawin maliban sa require line
   (palitan lang ng "security_helper.php").

   INDEX (hanapin ang "SECTION n" para tumalon):
     SECTION 0  - Settings + environment + maliliit na shared tools
     SECTION 1  - Session            (figurify_start_session)
     SECTION 2  - Access / role check (owner, staff pages)
     SECTION 3  - CSRF token
     SECTION 4  - Rate limit         (login lockout + spam limit)
     SECTION 5  - Remember me        (30 araw na login)
     SECTION 6  - Ban + Archive      (banned na email, naka-archive na account)
     SECTION 7  - Upload check       (totoong larawan lang)
     SECTION 8  - Proof URL          (proof of payment / refund)
     SECTION 9  - Resend cooldown    (verification code)
     SECTION 10 - Pop up messages    (figurify_flash / figurify_toast_now)

   Tip sa debugging: kung may error na hindi mo makita, gawing true
   ang FIGURIFY_DEBUG sa baba (huwag iiwan na true sa totoong server).
   ===================================================================== */

/* ---------------------------------------------------------------------
   PANTANDAAN (simpleng paliwanag)
   ANO ITO     : ang bantay ng buong site: login session, role (owner/staff/customer),
                 proteksyon sa pekeng form, limit ng spam, ban, at ligtas na upload.
   PARA SAAN   : para hindi mapasok ang site at hindi masira ng maling user.
   PANO NAGANA : bawat page ay nagsisimula sa figurify_start_session(). Kapag
                 kailangan ng owner/staff, tinatawag ang figurify_require_role(). Bawat form
                 na nagse-save ay may CSRF token. Ang sobrang maling login ay nilo-lock.
   HANAPIN DITO: SECTION 1 session | 2 role | 3 CSRF | 4 rate limit | 5 remember me
                 | 6 ban | 7 upload check | 8 proof URL | 9 resend cooldown
                 | 10 pop up messages
   --------------------------------------------------------------------- */


/* =====================================================================
   SECTION 0 - SETTINGS + ENVIRONMENT + SHARED TOOLS
   (galing sa: env_config.php)
   Lahat ng numero at database login na kailangan ng buong file ay dito
   lang nakalagay, para isang lugar lang ang papalitan.
   ===================================================================== */

const FIGURIFY_DEBUG = false;          // true = ipakita ang PHP error sa browser

/* LOCALHOST o PUBLIC? Kusa itong nalalaman, kaya IISANG code lang ang dalawa:
   Windows (XAMPP sa laptop) o binuksan sa localhost = LOCALHOST, kung hindi = PUBLIC (GoDaddy).
   Gamit ito ng database login dito, ng Admin/database.php at ng Admin/mail_config.php. */
define("FIGURIFY_LOCAL", PHP_OS_FAMILY === "Windows" || in_array($_SERVER["SERVER_NAME"] ?? "", ["localhost", "127.0.0.1"], true));

const FIGURIFY_DB_HOST = "localhost";  // database login ng buong system (dito lang nakalagay)
const FIGURIFY_DB_USER = FIGURIFY_LOCAL ? "root" : "figurifyuser";
/* mga password ay nasa helpers/secrets.php (hindi naka-commit, tingnan ang secrets.example.php) */
define("FIGURIFY_SECRETS", is_file(__DIR__ . "/secrets.php") ? require __DIR__ . "/secrets.php" : []);
define("FIGURIFY_DB_PASS", FIGURIFY_LOCAL ? "" : (FIGURIFY_SECRETS["db_pass_public"] ?? ""));
const FIGURIFY_DB_NAME = "figurify_db";

/* link ng website (para sa email na galing sa scheduled task, walang browser doon) */
const FIGURIFY_SITE_URL = FIGURIFY_LOCAL ? "http://localhost/clayandstuff" : "https://clayandstuff.com"; // maikling link (public_html/.htaccess)

const FIGURIFY_REMEMBER_DAYS           = 30;   // tagal ng "Remember me"
const FIGURIFY_LOGIN_MAX_ATTEMPTS      = 5;    // ilang palpak na login bago ma-lock
const FIGURIFY_LOGIN_WINDOW_MINUTES    = 10;   // sa loob ng ilang minuto binibilang
const FIGURIFY_LOGIN_LOCK_MINUTES      = [5, 10, 20]; // tagal ng lock: una 5 min, pangalawa 10 min, pangatlo pataas 20 min (max)
const FIGURIFY_RESEND_COOLDOWN_SECONDS = 300;  // 5 minuto bago makapag-resend ng code
const FIGURIFY_BAN_CHECK_SECONDS       = 60;   // gaano kadalas i-check kung na-ban

/* Oras ng Pilipinas para sa buong system. Kung wala ito, ang gagamitin ay
   timezone ng server at puwedeng magkamali ng isang araw ang "today". */
date_default_timezone_set("Asia/Manila");

/* Error handling: itago sa browser (para hindi makita ng audience) pero
   i-log pa rin sa server. Kapag FIGURIFY_DEBUG = true, ipapakita sa browser. */
ini_set("display_errors", FIGURIFY_DEBUG ? "1" : "0");
ini_set("display_startup_errors", FIGURIFY_DEBUG ? "1" : "0");
error_reporting(E_ALL);
ini_set("log_errors", "1");


/* e() = i-escape ang text bago i-print sa HTML (proteksyon laban sa XSS) */
if (!function_exists("e")) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }
}

/* Totoong HTTPS ba ang site? Kailangan sa secure cookie. Naka-auto-detect
   para gumana pa rin sa XAMPP (http lang) */
function figurify_is_https(): bool
{
    return (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (($_SERVER["SERVER_PORT"] ?? "") == 443)
        || (strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");
}

/* Sariling database connection para sa mga helper na tinatawag bago pa
   magkaroon ng $conn (remember me at ban check). Ibinabalik ang connection,
   o null kung hindi maabot ang database. Ang tumawag ang magsasara nito. */
function figurify_security_db(): ?mysqli
{
    try {
        $db = @new mysqli(FIGURIFY_DB_HOST, FIGURIFY_DB_USER, FIGURIFY_DB_PASS, FIGURIFY_DB_NAME);

        if ($db->connect_error) {
            return null;
        }

        $db->set_charset("utf8mb4");

        return $db;
    } catch (\Throwable $e) {
        return null;
    }
}

/* ======== SIMBOLO NA LIGTAS SA DATABASE ========
   BAKIT     : ang mga table ay latin1, kaya ang ₱, →, — at emoji ay nagiging "?" pag-save.
               Hindi na ginagalaw ang database: PHP na lang ang nag-aayos bago mag-save at bago ipakita.
   figurify_db_text()   : BAGO MAG-SAVE. ₱ -> "PHP ", → -> "->", — -> "-", curly quotes -> ' ",
                          at tinatanggal ang emoji (hindi kaya ng latin1). Ang ñ, é ay okay lang.
   figurify_show_text() : BAGO IPAKITA. "PHP 450" -> "₱450", "->" -> "to", " - " -> ", ". Inaayos din ang lumang
                          "?" na naka-save na: "?450" -> "₱450", " ? " -> $oldMiddle ("," o "to").
   GINAGAMIT NG: messaging_helper (notifications), reports_logs_helper at order_helper (activity log,
                 history, mga reason/notes ng order), content_helper (site content, collection),
                 owner/save_collection.php, my-order/submit_review.php, Commission/submit_order.php,
                 Notification/ (bell), owner/activity-log.php. */
function figurify_db_text(string $text): string
{
    $text = str_replace(
        ["₱", "→", "←", "—", "–", "‘", "’", "“", "”", "…", "•", "★"],
        ["PHP ", "->", "<-", "-", "-", "'", "'", '"', '"', "...", "-", "*"],
        $text
    );

    $text = str_replace("PHP  ", "PHP ", $text);

    $clean = preg_replace('/[^\x{0000}-\x{00FF}]/u', "", $text);

    return $clean ?? $text;   // null = sirang UTF-8; ibalik na lang ang text
}

function figurify_show_text(string $text, string $oldMiddle = ","): string
{
    $text = (string) preg_replace('/\?(?=\d)/', "₱", $text);
    $text = (string) preg_replace('/\bPHP ?(?=\d)/', "₱", $text);
    $text = (string) preg_replace('/(?<=\d) - (?=₱?\d)/u', " to ", $text);   // "₱450 - ₱600" -> "₱450 to ₱600"
    $text = str_replace([" -> ", " ? ", " - "], [" to ", $oldMiddle . " ", ", "], $text);

    return $text;
}
/* ======== END SIMBOLO NA LIGTAS SA DATABASE ======== */


/* =====================================================================
   SECTION 1 - SESSION
   (galing sa: session_helper.php)
   Gamitin ang figurify_start_session() kapalit ng session_start().
   May hiwalay na session cookie ang customer, staff, at owner, kaya
   puwedeng sabay na naka-login ang tatlo sa magkakaibang tab.
   ===================================================================== */

/* Alamin kung anong portal ang kasalukuyang page: "customer" / "staff" / "owner" */
function figurify_session_portal(): string
{
    // 1) Puwedeng piliin nang direkta gamit ang ?portal= (ginagamit ng logout links)
    if (!empty($_GET["portal"])) {
        $requested = strtolower((string) $_GET["portal"]);

        if (in_array($requested, ["customer", "staff", "owner"], true)) {
            return $requested;
        }
    }

    // 2) Alamin mula sa folder ng kasalukuyang file
    $path = str_replace("\\", "/", $_SERVER["SCRIPT_FILENAME"] ?? $_SERVER["SCRIPT_NAME"] ?? "");

    if (strpos($path, "/staff/") !== false) {
        return "staff";
    }

    if (strpos($path, "/owner/") !== false) {
        return "owner";
    }

    // 3) Mga folder ng customer. Laging customer session ito, para pagkatapos
    //    mag-logout ng owner/staff ay hindi magkamali ng session ang browser.
    foreach (["/Home/", "/Collection/", "/Commission/", "/my-order/", "/Profile/"] as $customerFolder) {
        if (strpos($path, $customerFolder) !== false) {
            return "customer";
        }
    }

    // 4) Ang pag-submit ng login form ay customer
    if (substr($path, -strlen("/Login/Login.php")) === "/Login/Login.php"
        && ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        return "customer";
    }

    // 5) Shared pages (hal. logout.php): tingnan kung saan galing ang bisita
    $referer = $_SERVER["HTTP_REFERER"] ?? "";

    if (strpos($referer, "/staff/") !== false) {
        return "staff";
    }

    if (strpos($referer, "/owner/") !== false) {
        return "owner";
    }

    return "customer";
}

/* Simulan ang session. Ang takbo, in order:
   1. piliin ang portal -> 2. piliin ang pangalan ng cookie -> 3. i-set ang
   secure na cookie settings -> 4. session_start -> 5. ibalik ang login
   kung may Remember me -> 6. i-logout kung na-ban. */
function figurify_start_session(?string $portal = null): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $portal = $portal ?? figurify_session_portal();

    $sessionNames = [
        "customer" => "figurify_customer_sess",
        "staff"    => "figurify_staff_sess",
        "owner"    => "figurify_owner_sess",
    ];

    $sessionName = $sessionNames[$portal] ?? $sessionNames["customer"];

    session_name($sessionName);

    // Gamitin ang sariling session ID ng portal. Kung wala o sira, gumawa ng bago.
    $cookieSessionId = $_COOKIE[$sessionName] ?? "";

    if (is_string($cookieSessionId) && preg_match('/^[A-Za-z0-9,-]{22,256}$/', $cookieSessionId)) {
        session_id($cookieSessionId);
    } else {
        session_id(session_create_id());
    }

    // httponly = hindi mababasa ng JavaScript; samesite Lax = dagdag proteksyon laban sa CSRF
    session_set_cookie_params([
        "lifetime" => 0,
        "path"     => "/",
        "domain"   => "",
        "secure"   => figurify_is_https(),
        "httponly" => true,
        "samesite" => "Lax",
    ]);

    session_start();

    figurify_remember_restore($portal);   // SECTION 5
    figurify_kick_if_banned();            // SECTION 6
}


/* Log out ng ISANG portal lang (customer, staff o owner) sa browser na ito: session + Remember me.
   Hiwalay ang login ng bawat portal, kaya hindi nagagalaw ang ibang naka-login (hal. owner at staff sabay).
   GINAGAMIT NG: Login/logout.php */
function figurify_end_portal_session(string $portal): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    figurify_remember_forget($portal);
    figurify_start_session($portal);

    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    session_destroy();
}


/* =====================================================================
   SECTION 2 - ACCESS / ROLE CHECK
   (galing sa: access_helper.php)
   Para sa owner at staff pages: kailangan naka-login at tamang role.
   ===================================================================== */

/* Para sa action files na walang HTML (verify_order.php, atbp.).
   Hindi naka-login -> Login page. Maling role -> Home page. */
function figurify_require_role(string $requiredRole): void
{
    if (!isset($_SESSION["user_id"])) {
        header("Location: ../Login/Login.php");
        exit();
    }

    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== $requiredRole) {
        header("Location: ../Home/Home.php");
        exit();
    }
}

/* First + Middle (initial lang) + Last = "Omar B. Hadji Abdulbari".
   Pareho ng formula ng users.full_name sa database. Walang middle name = hindi isasama. */
function figurify_full_name(string $first, ?string $middle, string $last): string
{
    $middle  = trim((string) $middle);
    $initial = $middle === "" ? "" : mb_strtoupper(mb_substr($middle, 0, 1)) . ".";

    return trim(preg_replace("/\s+/", " ", $first . " " . $initial . " " . $last));
}

/* Para sa buong page na may HTML. Pareho ang check sa itaas, tapos
   ibinabalik ang [pangalan, unang letra] para sa header/avatar. */
function figurify_require_role_page(string $requiredRole, string $fallbackName): array
{
    figurify_require_role($requiredRole);

    $displayName = $_SESSION["full_name"] ?? $fallbackName;
    $initial = strtoupper(substr(trim($displayName), 0, 1));

    figurify_touch_last_seen();

    return [$displayName, $initial];
}

/* I-update ang users.last_seen tuwing may bubukas na owner/staff page.
   May try/catch kasi baka wala pang "last_seen" column. */
function figurify_touch_last_seen(): void
{
    global $conn;

    if (!isset($conn) || !isset($_SESSION["user_id"])) {
        return;
    }

    try {
        $stmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE user_id = ?");

        if ($stmt) {
            $userId = (int) $_SESSION["user_id"];
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }
    } catch (\Throwable $e) {
        // balewalain lang
    }
}

/* Para sa User Management: "Online now" / "Offline · 5m ago" / "Never logged in" */
function figurify_presence_status(?string $lastSeen): array
{
    $lastSeenTime = ($lastSeen === null || $lastSeen === "") ? false : strtotime($lastSeen);

    if ($lastSeenTime === false) {
        return ["online" => false, "label" => "Never logged in"];
    }

    $secondsAgo = time() - $lastSeenTime;

    // Online kung may aktibidad sa loob ng huling 5 minuto
    if ($secondsAgo <= 300) {
        return ["online" => true, "label" => "Online now"];
    }

    if ($secondsAgo < 3600) {
        $ago = max(1, (int) floor($secondsAgo / 60)) . "m ago";
    } elseif ($secondsAgo < 86400) {
        $ago = (int) floor($secondsAgo / 3600) . "h ago";
    } else {
        $ago = (int) floor($secondsAgo / 86400) . "d ago";
    }

    return ["online" => false, "label" => "Offline · " . $ago];
}


/* =====================================================================
   SECTION 3 - CSRF TOKEN
   (galing sa: csrf_helper.php)
   Proteksyon laban sa pekeng form mula sa ibang site.
   Kailangan naka-start na ang session bago gamitin.
   ===================================================================== */

/* Gumawa (o kunin) ang token ng session. Ilagay sa hidden input ng form. */
function figurify_csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

/* Tugma ba ang isinumiteng token sa nasa session? */
function figurify_verify_csrf(?string $submittedToken): bool
{
    if (empty($_SESSION["csrf_token"]) || empty($submittedToken)) {
        return false;
    }

    return hash_equals($_SESSION["csrf_token"], $submittedToken);
}


/* =====================================================================
   SECTION 4 - RATE LIMIT
   (galing sa: rate_limit_helper.php)
   Dalawang gamit:
     A) Login lockout: 5 palpak sa loob ng 10 minuto = lock (may countdown): 5 min, tapos 10 min, tapos 20 min (max)
     B) Spam limit para sa order/review forms
   Awtomatikong ginagawa ang mga table sa unang gamit.
   ===================================================================== */

/* Shared na pagbibilang para sa A at B: ilang segundo pa bago puwede ulit?
   0 = hindi naka-lock. Ang $table at $where ay galing lang sa loob ng file
   na ito (hindi sa user), kaya ligtas. */
function figurify_lock_seconds_remaining(
    mysqli $conn,
    string $table,
    string $where,
    string $types,
    array $params,
    int $maxAttempts,
    int $windowMinutes
): int {
    $windowSeconds = $windowMinutes * 60;
    $windowStart   = date("Y-m-d H:i:s", time() - $windowSeconds);

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c, MIN(attempted_at) AS oldest
         FROM {$table}
         WHERE {$where} AND attempted_at > ?"
    );
    $stmt->bind_param($types . "s", ...array_merge($params, [$windowStart]));
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $count = (int) ($row["c"] ?? 0);

    if ($count < $maxAttempts || empty($row["oldest"])) {
        return 0;
    }

    // Bibilang mula sa pinakaunang attempt sa loob ng window
    $unlocksAt = strtotime($row["oldest"]) + $windowSeconds;

    return max($unlocksAt - time(), 1);
}


/* ---- A) LOGIN LOCKOUT ---- */

function figurify_ensure_login_attempts_table(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            attempted_at DATETIME NOT NULL,
            INDEX idx_email_time (email, attempted_at)
        )"
    );

    // ilang beses na na-lock ang email + hanggang kailan ang lock ngayon
    $conn->query(
        "CREATE TABLE IF NOT EXISTS login_lockouts (
            email VARCHAR(255) NOT NULL PRIMARY KEY,
            strikes INT NOT NULL DEFAULT 0,
            locked_until DATETIME NULL
        )"
    );

    $ensured = true;
}

/* Ilang segundo pa bago makapag-login ulit ang email na ito. 0 = hindi lock. */
function figurify_login_lockout_seconds_remaining(mysqli $conn, string $email): int
{
    figurify_ensure_login_attempts_table($conn);

    $stmt = $conn->prepare("SELECT locked_until FROM login_lockouts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (empty($row["locked_until"])) {
        return 0;
    }

    return max(strtotime($row["locked_until"]) - time(), 0);
}

/* Itala ang isang palpak na login. Ibinabalik ang segundo ng lock kapag ito na ang
   pang-5 na palpak (5 min, 10 min, 20 min max). 0 = hindi pa naka-lock. */
function figurify_record_failed_login(mysqli $conn, string $email): int
{
    figurify_ensure_login_attempts_table($conn);

    $now  = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, attempted_at) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $now);
    $stmt->execute();
    $stmt->close();

    // ilan ang palpak sa loob ng window
    $windowStart = date("Y-m-d H:i:s", time() - FIGURIFY_LOGIN_WINDOW_MINUTES * 60);
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM login_attempts WHERE email = ? AND attempted_at > ?");
    $stmt->bind_param("ss", $email, $windowStart);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()["c"] ?? 0);
    $stmt->close();

    if ($count < FIGURIFY_LOGIN_MAX_ATTEMPTS) {
        return 0;
    }

    // ilang beses na na-lock dati (kapag lampas 1 araw na ang huling lock, magsisimula ulit sa 5 min)
    $stmt = $conn->prepare("SELECT strikes, locked_until FROM login_lockouts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $strikes = (int) ($row["strikes"] ?? 0);

    if (!empty($row["locked_until"]) && strtotime($row["locked_until"]) < time() - 86400) {
        $strikes = 0;
    }

    $minutes     = FIGURIFY_LOGIN_LOCK_MINUTES[min($strikes, count(FIGURIFY_LOGIN_LOCK_MINUTES) - 1)];
    $lockSeconds = $minutes * 60;
    $lockedUntil = date("Y-m-d H:i:s", time() + $lockSeconds);
    $newStrikes  = $strikes + 1;

    $stmt = $conn->prepare(
        "INSERT INTO login_lockouts (email, strikes, locked_until) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE strikes = VALUES(strikes), locked_until = VALUES(locked_until)"
    );
    $stmt->bind_param("sis", $email, $newStrikes, $lockedUntil);
    $stmt->execute();
    $stmt->close();

    // simula ulit ang bilang ng palpak pagkatapos ng lock
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();

    return $lockSeconds;
}

/* Burahin ang mga palpak na attempt (tinatawag kapag nakapag-login nang tama) */
function figurify_clear_login_attempts(mysqli $conn, string $email): void
{
    figurify_ensure_login_attempts_table($conn);

    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM login_lockouts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();
}


/* ---- B) SPAM LIMIT (order/review forms) ----
   $actionType = anong form, $identifier = email o session id */

function figurify_ensure_action_attempts_table(mysqli $conn): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS action_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action_type VARCHAR(50) NOT NULL,
            identifier VARCHAR(255) NOT NULL,
            attempted_at DATETIME NOT NULL,
            INDEX idx_action_identifier_time (action_type, identifier, attempted_at)
        )"
    );

    $ensured = true;
}

/* 0 = puwede pang mag-submit; higit sa 0 = ilang segundo pa bago puwede ulit */
function figurify_action_rate_limit_seconds_remaining(
    mysqli $conn,
    string $actionType,
    string $identifier,
    int $maxAttempts,
    int $windowMinutes
): int {
    figurify_ensure_action_attempts_table($conn);

    return figurify_lock_seconds_remaining(
        $conn, "action_attempts", "action_type = ? AND identifier = ?", "ss",
        [$actionType, $identifier], $maxAttempts, $windowMinutes
    );
}

/* ANO ITO: isinusulat sa table na action_attempts na may ginawang aksyon (hal. nag-resend ng code).
   PARA SAAN: ito ang binibilang ng rate limit para malaman kung sobra na ang spam. */
function figurify_record_action_attempt(mysqli $conn, string $actionType, string $identifier): void
{
    figurify_ensure_action_attempts_table($conn);

    $stmt = $conn->prepare(
        "INSERT INTO action_attempts (action_type, identifier, attempted_at) VALUES (?, ?, NOW())"
    );
    $stmt->bind_param("ss", $actionType, $identifier);
    $stmt->execute();
    $stmt->close();
}

/* IP ng bisita (para sa limit kada device/network) */
function figurify_client_ip(): string
{
    return substr((string) ($_SERVER["REMOTE_ADDR"] ?? "unknown"), 0, 64);
}


/* =====================================================================
   SECTION 5 - REMEMBER ME
   (galing sa: remember_helper.php)
   Hindi password ang naka-save sa browser kundi random na token
   ("selector:validator"). Ang hash lang ng validator ang nasa database
   (remember_tokens), kaya hindi magagamit kahit makita ang table.
   Tumatagal ng 30 araw. Nabubura kapag nag-logout, nag-reset ng password,
   o na-ban. Nagpapalit ang validator tuwing ginagamit.
   ===================================================================== */

function figurify_remember_cookie_name(string $portal): string
{
    return "figurify_remember_" . $portal;
}

/* role sa database -> portal ("admin" ay owner) */
function figurify_remember_portal_for_role(string $role): string
{
    return ["admin" => "owner", "staff" => "staff"][$role] ?? "customer";
}

/* ANO ITO: gumagawa ng table na remember_tokens kung wala pa (isang beses lang kada page).
   PARA SAAN: dito nakatago ang "Remember me" na token para hindi na mag-login ulit ang user sa loob ng 30 araw. */
function figurify_remember_ensure_table(mysqli $conn): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS remember_tokens (
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
        )"
    );

    $done = true;
}

/* I-set (o burahin, kung nakaraan na ang $expires) ang remember cookie */
function figurify_remember_set_cookie(string $portal, string $value, int $expires): void
{
    if (headers_sent()) {
        return;
    }

    $name = figurify_remember_cookie_name($portal);

    setcookie($name, $value, [
        "expires"  => $expires,
        "path"     => "/",
        "secure"   => figurify_is_https(),
        "httponly" => true,
        "samesite" => "Lax",
    ]);

    if ($expires < time()) {
        unset($_COOKIE[$name]);
    } else {
        $_COOKIE[$name] = $value;
    }
}

/* Gumawa ng bagong token pagkatapos mag-login na naka-check ang Remember me */
function figurify_remember_issue(mysqli $conn, int $userId, string $portal): void
{
    figurify_remember_ensure_table($conn);

    $selector  = bin2hex(random_bytes(12));      // pantukoy ng row
    $validator = bin2hex(random_bytes(32));      // ito ang sikreto
    $hash      = hash("sha256", $validator);     // hash lang ang ise-save
    $expires   = time() + FIGURIFY_REMEMBER_DAYS * 86400;
    $expiresAt = date("Y-m-d H:i:s", $expires);

    $stmt = $conn->prepare(
        "INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isss", $userId, $selector, $hash, $expiresAt);
    $stmt->execute();
    $stmt->close();

    // linisin ang mga nag-expire na
    $conn->query("DELETE FROM remember_tokens WHERE expires_at < NOW()");

    figurify_remember_set_cookie($portal, $selector . ":" . $validator, $expires);
}

/* Burahin ang token ng portal na ito (tinatawag sa logout) */
function figurify_remember_forget(string $portal): void
{
    $raw = $_COOKIE[figurify_remember_cookie_name($portal)] ?? "";

    if (is_string($raw) && preg_match('/^([a-f0-9]{24}):[a-f0-9]{64}$/', $raw, $m)) {

        $conn = figurify_security_db();

        if ($conn) {
            try {
                figurify_remember_ensure_table($conn);

                $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                $stmt->bind_param("s", $m[1]);
                $stmt->execute();
                $stmt->close();
                $conn->close();
            } catch (\Throwable $e) {
                // tuloy pa rin ang logout kahit may error
            }
        }
    }

    figurify_remember_set_cookie($portal, "", time() - 3600);
}

/* Burahin lahat ng token ng isang user (hal. pagkatapos mag-reset ng password) */
function figurify_remember_forget_user(mysqli $conn, int $userId): void
{
    figurify_remember_ensure_table($conn);

    $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
}

/* Tinatawag ng figurify_start_session(). Kung walang naka-login pero may
   remember cookie, ibabalik ang login. Ang takbo:
   1) basahin ang cookie  2) hanapin ang token sa database
   3) i-check kung tama, hindi expired, tamang portal, hindi banned
   4) palitan ang validator  5) ibalik ang session */
function figurify_remember_restore(string $portal): void
{
    if (php_sapi_name() === "cli" || !empty($_SESSION["user_id"])) {
        return;   // scheduled task, o naka-login na
    }

    $raw = $_COOKIE[figurify_remember_cookie_name($portal)] ?? "";

    if ($raw === "") {
        return;
    }

    // 1) mali ang itsura ng cookie -> burahin
    if (!is_string($raw) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $raw, $m)) {
        figurify_remember_set_cookie($portal, "", time() - 3600);
        return;
    }

    [, $selector, $validator] = $m;

    $conn = figurify_security_db();

    if (!$conn) {
        return;
    }

    try {
        figurify_remember_ensure_table($conn);

        // 2) hanapin ang token kasama ang user
        $stmt = $conn->prepare(
            "SELECT t.id, t.token_hash, t.expires_at,
                    u.user_id, u.full_name, u.email, u.role, u.profile_picture
             FROM remember_tokens t
             JOIN users u ON u.user_id = t.user_id
             WHERE t.selector = ?
             LIMIT 1"
        );
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // 3) tama ba ang validator, hindi pa expired, tamang portal, hindi banned / archived
        $valid = $row
            && hash_equals($row["token_hash"], hash("sha256", $validator))
            && strtotime($row["expires_at"]) > time()
            && figurify_remember_portal_for_role((string) $row["role"]) === $portal
            && figurify_account_block_message($conn, (string) $row["email"]) === "";

        if (!$valid) {
            if ($row) {
                $del = $conn->prepare("DELETE FROM remember_tokens WHERE id = ?");
                $del->bind_param("i", $row["id"]);
                $del->execute();
                $del->close();
            }

            $conn->close();
            figurify_remember_set_cookie($portal, "", time() - 3600);
            return;
        }

        // 4) palitan ang validator tuwing ginagamit (rotation)
        $newValidator = bin2hex(random_bytes(32));
        $newHash      = hash("sha256", $newValidator);
        $expires      = time() + FIGURIFY_REMEMBER_DAYS * 86400;
        $expiresAt    = date("Y-m-d H:i:s", $expires);

        $upd = $conn->prepare("UPDATE remember_tokens SET token_hash = ?, expires_at = ? WHERE id = ?");
        $upd->bind_param("ssi", $newHash, $expiresAt, $row["id"]);
        $upd->execute();
        $upd->close();
        $conn->close();

        // 5) ibalik ang login
        session_regenerate_id(true);

        $_SESSION["user_id"]         = (int) $row["user_id"];
        $_SESSION["full_name"]       = $row["full_name"];
        $_SESSION["email"]           = $row["email"];
        $_SESSION["role"]            = $row["role"];
        $_SESSION["profile_picture"] = $row["profile_picture"];

        figurify_remember_set_cookie($portal, $selector . ":" . $newValidator, $expires);

    } catch (\Throwable $e) {
        return;
    }
}


/* =====================================================================
   SECTION 6 - BAN
   (galing sa: ban_helper.php)
   Naka-save ang banned na email sa sariling table (banned_emails), kaya
   kahit i-delete ang account ay hindi na ito makakagawa ulit gamit ang
   parehong email. Ginagamit sa Login, Sign up, Forgot password, at
   User Management.
   ===================================================================== */

function figurify_ban_ensure_table(mysqli $conn): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    try {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS banned_emails (
                email      VARCHAR(255) NOT NULL PRIMARY KEY,
                reason     VARCHAR(255) NULL,
                banned_by  INT NULL,
                banned_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
    } catch (\Throwable $e) {
        // tahimik lang, para hindi masira ang page
    }
}

/* lowercase at walang space, para pareho lagi ang pagkukumpara */
function figurify_ban_normalize(string $email): string
{
    return strtolower(trim($email));
}

/* ANO ITO: tinitingnan kung banned ang email. true = banned, false = okay. */
function figurify_is_email_banned(mysqli $conn, string $email): bool
{
    $email = figurify_ban_normalize($email);

    if ($email === "") {
        return false;
    }

    figurify_ban_ensure_table($conn);

    try {
        $stmt = $conn->prepare("SELECT 1 FROM banned_emails WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $banned = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();

        return $banned;
    } catch (\Throwable $e) {
        return false;
    }
}

/* Listahan ng banned (email => row), para sa User Management */
function figurify_banned_list(mysqli $conn): array
{
    figurify_ban_ensure_table($conn);

    $list = [];

    try {
        $result = $conn->query("SELECT email, reason, banned_at FROM banned_emails");

        while ($result && ($row = $result->fetch_assoc())) {
            $list[$row["email"]] = $row;
        }
    } catch (\Throwable $e) {
        // walang gagawin
    }

    return $list;
}

/* ANO ITO: nagba-ban ng email (isinusulat sa banned_emails kasama ang dahilan at kung sino ang nag-ban).
   Kapag na-ban na ulit ang parehong email, ina-update lang ang dahilan. */
function figurify_ban_email(mysqli $conn, string $email, string $reason, ?int $bannedBy): bool
{
    $email  = figurify_ban_normalize($email);
    $reason = mb_substr(trim($reason), 0, 255);

    if ($email === "") {
        return false;
    }

    figurify_ban_ensure_table($conn);

    $stmt = $conn->prepare(
        "INSERT INTO banned_emails (email, reason, banned_by) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE reason = VALUES(reason), banned_by = VALUES(banned_by), banned_at = CURRENT_TIMESTAMP"
    );
    $stmt->bind_param("ssi", $email, $reason, $bannedBy);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/* ANO ITO: inaalis sa banned list ang email para makapag-login ulit. */
function figurify_unban_email(mysqli $conn, string $email): bool
{
    $email = figurify_ban_normalize($email);

    figurify_ban_ensure_table($conn);

    $stmt = $conn->prepare("DELETE FROM banned_emails WHERE email = ?");
    $stmt->bind_param("s", $email);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/* ANO ITO: ang mensaheng lumalabas sa banned na user. */
function figurify_ban_message(): string
{
    return "This account has been banned. If you think this is a mistake, please contact us.";
}

/* ARCHIVE (User Management): imbes na burahin, naka-archive ang account.
   Hindi na makakapag-login, pero buo pa rin ang orders, reviews at history.
   Column: users.archived_at (NULL = active) - kusang idinadagdag dito. */
function figurify_archive_ensure_column(mysqli $conn): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    try {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'archived_at'"
        );
        $stmt->execute();
        $hasColumn = (int) ($stmt->get_result()->fetch_assoc()["c"] ?? 0) > 0;
        $stmt->close();

        if (!$hasColumn) {
            $conn->query("ALTER TABLE users ADD COLUMN archived_at DATETIME NULL DEFAULT NULL");
        }
    } catch (\Throwable $e) {
        // tahimik lang, para hindi masira ang page
    }
}

/* ANO ITO: true kung naka-archive ang account ng email na ito. */
function figurify_is_email_archived(mysqli $conn, string $email): bool
{
    figurify_archive_ensure_column($conn);

    try {
        $email = figurify_ban_normalize($email);
        $stmt  = $conn->prepare("SELECT 1 FROM users WHERE email = ? AND archived_at IS NOT NULL LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $archived = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();

        return $archived;
    } catch (\Throwable $e) {
        return false;
    }
}

/* ANO ITO: iisang check bago papasukin ang user (Login, Remember me, bawat page).
   "" = puwedeng mag-login; kung hindi, ang mensaheng ipapakita (banned o archived). */
function figurify_account_block_message(mysqli $conn, string $email): string
{
    if (figurify_is_email_banned($conn, $email)) {
        return figurify_ban_message();
    }

    if (figurify_is_email_archived($conn, $email)) {
        return "This account has been archived. Please contact us if you need it back.";
    }

    return "";
}

/* Tinatawag ng figurify_start_session() sa bawat page. Kapag na-ban o na-archive
   habang naka-login, ila-logout. Isang beses lang kada minuto ang check para hindi
   bumagal ang site. */
function figurify_kick_if_banned(): void
{
    if (php_sapi_name() === "cli" || empty($_SESSION["user_id"]) || empty($_SESSION["email"])) {
        return;
    }

    $lastCheck = (int) ($_SESSION["ban_checked_at"] ?? 0);

    if ($lastCheck > time() - FIGURIFY_BAN_CHECK_SECONDS) {
        return;
    }

    $_SESSION["ban_checked_at"] = time();

    $conn = figurify_security_db();

    if (!$conn) {
        return;
    }

    $blockMessage = figurify_account_block_message($conn, (string) $_SESSION["email"]);
    $conn->close();

    if ($blockMessage === "") {
        return;
    }

    // Na-ban / na-archive: burahin ang session at ibalik sa Home na may error message
    $_SESSION = [];
    session_destroy();

    if (headers_sent()) {
        return;
    }

    $scriptDir   = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
    $projectRoot = rtrim(dirname($scriptDir), "/");

    header("Location: " . $projectRoot . "/Home/Home.php?auth=login&auth_error=" . urlencode($blockMessage));
    exit();
}


/* =====================================================================
   SECTION 7 - UPLOAD CHECK
   (galing sa: upload_helper.php)
   Hindi sapat ang extension lang. Tinitingnan ang laman mismo ng file
   (MIME + getimagesize) para hindi makapag-upload ng script na
   pinangalanang .jpg.
   ===================================================================== */

/* true kung totoong larawan (jpg/png/gif/webp) at hindi lalampas sa $maxBytes (default 10MB) */
function figurify_is_real_image(string $tmpPath, int $maxBytes = 10485760): bool
{
    if (!is_file($tmpPath) || filesize($tmpPath) <= 0 || filesize($tmpPath) > $maxBytes) {
        return false;
    }

    $mime = function_exists("mime_content_type") ? (string) mime_content_type($tmpPath) : "";

    if (!in_array($mime, ["image/jpeg", "image/png", "image/gif", "image/webp"], true)) {
        return false;
    }

    return @getimagesize($tmpPath) !== false;
}


/* =====================================================================
   SECTION 8 - PROOF URL
   (galing sa: proof_helper.php)
   Hindi direktang mabubuksan ang /uploads/ (bawal sa .htaccess), kaya
   dumadaan sa Shared/proof.php:
     - owner / staff: puwede sa lahat
     - customer: sariling order lang
   Gamit: <img src="<?php echo e(figurify_proof_url($order["payment_proof"])); ?>">
   ===================================================================== */

function figurify_proof_url(?string $relativePath): string
{
    $relativePath = (string) $relativePath;

    // Hanapin ang URL ng project folder (hal. "/clayandstuff/")
    $documentRoot = rtrim(str_replace("\\", "/", (string) realpath($_SERVER["DOCUMENT_ROOT"] ?? "")), "/");
    $projectRoot  = rtrim(str_replace("\\", "/", (string) realpath(__DIR__ . "/..")), "/");
    $siteBase     = ($documentRoot !== "" && strpos($projectRoot, $documentRoot) === 0)
        ? substr($projectRoot, strlen($documentRoot)) . "/"
        : "/";

    // Alamin kung aling portal ang nagtatanong (para malaman ng proof.php kung sino ang titingin)
    $script = str_replace("\\", "/", $_SERVER["SCRIPT_FILENAME"] ?? "");
    $portal = (strpos($script, "/owner/") !== false) ? "owner"
        : ((strpos($script, "/staff/") !== false) ? "staff" : "customer");

    return $siteBase . "Shared/proof.php?portal=" . $portal . "&f=" . rawurlencode($relativePath);
}


/* =====================================================================
   SECTION 9 - RESEND COOLDOWN
   (galing sa: resend_cooldown_helper.php)
   Para sa verification code (Sign up at Forgot password): 5 minuto bago
   makapagpadala ulit ng bagong code sa parehong email, para hindi ma-spam
   ang inbox. (Ang oras ay nasa FIGURIFY_RESEND_COOLDOWN_SECONDS sa taas.)
   ===================================================================== */

/* Ilang segundo pa ang hihintayin. 0 = puwede nang magpadala. */
function figurify_resend_cooldown_seconds_remaining(mysqli $conn, string $email): int
{
    $stmt = $conn->prepare(
        "SELECT created_at FROM email_verifications WHERE email = ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (empty($row["created_at"])) {
        return 0;
    }

    $elapsed   = time() - strtotime($row["created_at"]);
    $remaining = FIGURIFY_RESEND_COOLDOWN_SECONDS - $elapsed;

    return $remaining > 0 ? $remaining : 0;
}


/* =====================================================================
   SECTION 10 - POP UP MESSAGES
   (galing sa: lumang shared file)
   Ang JS na nagpapakita ng pop up (csToast) ay nasa
   Shared/popup-and-security.php.
     figurify_flash($message, $type)     - pop up sa SUSUNOD na page (bago mag-redirect)
     figurify_take_flashes()             - kinukuha at binubura ang naka-save na flash
     figurify_toast_now($message, $type) - pop up agad sa kasalukuyang page
     figurify_saved_toast($message)      - "Changes saved" pop up (Content / Collection)
   ===================================================================== */

/* I-save ang pop up para lumabas sa susunod na page (pagkatapos ng redirect). */
function figurify_flash(string $message, string $type = "success"): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if (!in_array($type, ["success", "error", "info"], true)) {
        $type = "success";
    }

    if (!isset($_SESSION["cs_flash"]) || !is_array($_SESSION["cs_flash"])) {
        $_SESSION["cs_flash"] = [];
    }

    $_SESSION["cs_flash"][] = [
        "message" => $message,
        "type"    => $type,
    ];
}

/* Kunin at burahin ang naka-save na pop up (ginagamit ng Shared/popup-and-security.php). */
function figurify_take_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }

    $flashes = (isset($_SESSION["cs_flash"]) && is_array($_SESSION["cs_flash"]))
        ? $_SESSION["cs_flash"]
        : [];

    unset($_SESSION["cs_flash"]);

    return $flashes;
}

/* Pop up agad sa kasalukuyang page (hal. may mali sa form). Ilagay kahit saan sa <body>. */
function figurify_toast_now(string $message, string $type = "error"): string
{
    if (!in_array($type, ["success", "error", "info"], true)) {
        $type = "error";
    }

    $payload = json_encode(
        ["message" => $message, "type" => $type],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );

    return "<script>(function(){var t=" . $payload . ";function s(){if(window.csToast){window.csToast(t.message,t.type);}}"
        . "if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',s);}else{s();}})();</script>";
}

/* "Changes saved" pop up - berde (success). Ang ?saved=1 sa URL ay
   tinatanggal ng [2] LINIS NG URL sa Shared/popup-and-security.php
   para hindi na lumabas ulit pag ni-refresh. */
function figurify_saved_toast(string $message = "Changes saved."): string
{
    return figurify_toast_now($message, "success");
}
