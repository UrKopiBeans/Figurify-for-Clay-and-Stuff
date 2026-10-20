<?php

/* Banned accounts.
   Naka-save ang banned na email sa sariling table (banned_emails), kaya kahit
   i-delete ang account, hindi na rin ito makakagawa ulit gamit ang parehong
   Gmail. Ginagamit sa Login, Google login, Sign up, Forgot password at sa
   User Management. */

if (!function_exists("figurify_ban_ensure_table")) {

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

    function figurify_ban_normalize(string $email): string
    {
        return strtolower(trim($email));
    }

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

    /* email => reason, para sa User Management */
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
            // wala
        }

        return $list;
    }

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

    function figurify_ban_message(): string
    {
        return "This account has been banned. If you think this is a mistake, please contact us.";
    }

    /* Tinatawag sa bawat page (galing figurify_start_session). Kapag na-ban
       habang naka-login, ila-logout siya. Isang beses lang kada minuto ang
       pag-check para hindi bumagal ang site. */
    function figurify_kick_if_banned(): void
    {
        if (php_sapi_name() === "cli" || empty($_SESSION["user_id"]) || empty($_SESSION["email"])) {
            return;
        }

        $lastCheck = (int) ($_SESSION["ban_checked_at"] ?? 0);

        if ($lastCheck > time() - 60) {
            return;
        }

        $_SESSION["ban_checked_at"] = time();

        try {
            $conn = @new mysqli("localhost", "root", "", "figurify_db");

            if ($conn->connect_error) {
                return;
            }

            $banned = figurify_is_email_banned($conn, (string) $_SESSION["email"]);
            $conn->close();
        } catch (\Throwable $e) {
            return;
        }

        if (!$banned) {
            return;
        }

        $_SESSION = [];
        session_destroy();

        if (headers_sent()) {
            return;
        }

        $scriptDir   = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? ""));
        $projectRoot = rtrim(dirname($scriptDir), "/");

        header("Location: " . $projectRoot . "/Home/Home.php?auth=login&auth_error=" . urlencode(figurify_ban_message()));
        exit();
    }
}
