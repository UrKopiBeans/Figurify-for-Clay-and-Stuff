<?php

/* "Remember me" sa login. Hindi password ang naka-save sa browser:
   random na token lang (selector + validator). Hash ng validator ang
   nasa database (remember_tokens), kaya kahit makita ang table, hindi
   ito magagamit. Tatagal ng 30 araw; nabubura kapag nag-log out,
   nag-reset ng password, o na-ban ang account. */

if (!function_exists("figurify_remember_cookie_name")) {

    define("FIGURIFY_REMEMBER_DAYS", 30);

    function figurify_remember_cookie_name(string $portal): string
    {
        return "figurify_remember_" . $portal;
    }

    function figurify_remember_portal_for_role(string $role): string
    {
        return ["admin" => "owner", "staff" => "staff"][$role] ?? "customer";
    }

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

    function figurify_remember_set_cookie(string $portal, string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        $isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
            || (($_SERVER["SERVER_PORT"] ?? "") == 443)
            || ((strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")) === "https");

        setcookie(figurify_remember_cookie_name($portal), $value, [
            "expires"  => $expires,
            "path"     => "/",
            "secure"   => $isHttps,
            "httponly" => true,
            "samesite" => "Lax",
        ]);

        if ($expires < time()) {
            unset($_COOKIE[figurify_remember_cookie_name($portal)]);
        } else {
            $_COOKIE[figurify_remember_cookie_name($portal)] = $value;
        }
    }

    /* Gagawa ng bagong token pagkatapos mag-login na naka-check ang Remember me */
    function figurify_remember_issue(mysqli $conn, int $userId, string $portal): void
    {
        figurify_remember_ensure_table($conn);

        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $hash      = hash("sha256", $validator);
        $expires   = time() + FIGURIFY_REMEMBER_DAYS * 86400;
        $expiresAt = date("Y-m-d H:i:s", $expires);

        $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $userId, $selector, $hash, $expiresAt);
        $stmt->execute();
        $stmt->close();

        /* linisin ang mga luma na */
        $conn->query("DELETE FROM remember_tokens WHERE expires_at < NOW()");

        figurify_remember_set_cookie($portal, $selector . ":" . $validator, $expires);
    }

    /* Burahin ang token ng portal na ito (logout) */
    function figurify_remember_forget(string $portal): void
    {
        $raw = $_COOKIE[figurify_remember_cookie_name($portal)] ?? "";

        if (is_string($raw) && preg_match('/^([a-f0-9]{24}):[a-f0-9]{64}$/', $raw, $m)) {
            try {
                $conn = @new mysqli("localhost", "root", "", "figurify_db");

                if (!$conn->connect_error) {
                    figurify_remember_ensure_table($conn);
                    $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                    $stmt->bind_param("s", $m[1]);
                    $stmt->execute();
                    $stmt->close();
                    $conn->close();
                }
            } catch (\Throwable $e) {
                // tuloy pa rin ang pag-logout kahit hindi naabot ang database
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

    /* Tinatawag ng figurify_start_session(): kung walang naka-login pero may
       Remember me cookie, ibabalik ang session (at papalitan ang token). */
    function figurify_remember_restore(string $portal): void
    {
        if (php_sapi_name() === "cli" || !empty($_SESSION["user_id"])) {
            return;
        }

        $raw = $_COOKIE[figurify_remember_cookie_name($portal)] ?? "";

        if ($raw === "") {
            return;
        }

        if (!is_string($raw) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $raw, $m)) {
            figurify_remember_set_cookie($portal, "", time() - 3600);
            return;
        }

        try {
            $conn = @new mysqli("localhost", "root", "", "figurify_db");

            if ($conn->connect_error) {
                return;
            }

            $conn->set_charset("utf8mb4");
            figurify_remember_ensure_table($conn);

            $stmt = $conn->prepare(
                "SELECT t.id, t.token_hash, t.expires_at,
                        u.user_id, u.full_name, u.email, u.role, u.profile_picture
                 FROM remember_tokens t
                 JOIN users u ON u.user_id = t.user_id
                 WHERE t.selector = ?
                 LIMIT 1"
            );
            $stmt->bind_param("s", $m[1]);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $valid = $row
                && hash_equals($row["token_hash"], hash("sha256", $m[2]))
                && strtotime($row["expires_at"]) > time()
                && figurify_remember_portal_for_role((string) $row["role"]) === $portal;

            if ($valid) {
                require_once __DIR__ . "/ban_helper.php";
                $valid = !figurify_is_email_banned($conn, (string) $row["email"]);
            }

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

            /* papalitan ang validator tuwing ginagamit (rotation) */
            $validator = bin2hex(random_bytes(32));
            $hash      = hash("sha256", $validator);
            $expires   = time() + FIGURIFY_REMEMBER_DAYS * 86400;
            $expiresAt = date("Y-m-d H:i:s", $expires);

            $upd = $conn->prepare("UPDATE remember_tokens SET token_hash = ?, expires_at = ? WHERE id = ?");
            $upd->bind_param("ssi", $hash, $expiresAt, $row["id"]);
            $upd->execute();
            $upd->close();
            $conn->close();

            session_regenerate_id(true);

            $_SESSION["user_id"]         = (int) $row["user_id"];
            $_SESSION["full_name"]       = $row["full_name"];
            $_SESSION["email"]           = $row["email"];
            $_SESSION["role"]            = $row["role"];
            $_SESSION["profile_picture"] = $row["profile_picture"];

            figurify_remember_set_cookie($portal, $m[1] . ":" . $validator, $expires);

        } catch (\Throwable $e) {
            return;
        }
    }
}
