<?php
require_once __DIR__ . "/../helpers/session_helper.php";
require_once __DIR__ . "/../helpers/access_helper.php";
require_once __DIR__ . "/../helpers/csrf_helper.php";

$error = null;

/* Login galing sa popup (auth-modal.php) gamit ang fetch.
   AJAX -> JSON (status/message/redirect) para hindi magsara ang popup. */

$isAjaxLogin = (($_POST["ajax"] ?? "") === "1")
    || (($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "XMLHttpRequest");

function figurify_login_site_base() {
    $documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
    $projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
    return substr($projectRoot, strlen($documentRoot)) . "/";
}

function figurify_login_ajax_respond($status, $message = "", $redirect = "", $retryAfter = 0) {
    header("Content-Type: application/json");
    $payload = [
        "status" => $status,
        "message" => $message,
        "redirect" => $redirect,
    ];

    // retry_after (segundo) - ginagamit ng popup para sa live countdown.
    if ($retryAfter > 0) {
        $payload["retry_after"] = $retryAfter;
    }

    echo json_encode($payload);
    exit();
}

/* POST = check login; GET (plain visit) = ipakita lang agad ang form. */

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "login") {

    require_once __DIR__ . "/../Admin/database.php";
    require_once __DIR__ . "/../helpers/rate_limit_helper.php";

    // Kailangan naka-start ang session para ma-check ang csrf_token.
    figurify_start_session();

    if (!figurify_verify_csrf($_POST["csrf_token"] ?? null)) {

        $error = "Your session has expired. Please refresh the page and try again.";

        if ($isAjaxLogin) {
            /* Bigyan ng bagong token ang popup para kusa itong
               mag-retry, imbes na utusan ang user na mag-refresh. */
            header("Content-Type: application/json");
            echo json_encode([
                "status"     => "csrf",
                "message"    => $error,
                "csrf_token" => figurify_csrf_token(),
            ]);
            exit();
        }

        header("Location: ../Home/Home.php?auth=login&auth_error=" . urlencode($error));
        exit();
    }

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $error = "Please enter your email and password.";

        if ($isAjaxLogin) {
            figurify_login_ajax_respond("error", $error);
        }

    } else {

        // 5 failed attempts sa loob ng 10 minuto = naka-lock muna.
        $retryAfterSeconds = figurify_login_lockout_seconds_remaining($conn, $email);

        /* 20 maling login galing sa iisang IP sa loob ng 10 minuto (kahit iba-ibang email) */
        $retryAfterSeconds = max(
            $retryAfterSeconds,
            figurify_action_rate_limit_seconds_remaining($conn, "login_fail_ip", figurify_client_ip(), 20, 10)
        );

        if ($retryAfterSeconds > 0) {

            $error = "Too many failed login attempts. Please try again later.";

            if ($isAjaxLogin) {
                figurify_login_ajax_respond("error", $error, "", $retryAfterSeconds);
            }

            $conn->close();
            header("Location: ../Home/Home.php?auth=login&auth_error=" . urlencode($error));
            exit();
        }

        require_once __DIR__ . "/../helpers/ban_helper.php";

        if (figurify_is_email_banned($conn, $email)) {

            $error = figurify_ban_message();

            if ($isAjaxLogin) {
                figurify_login_ajax_respond("error", $error);
            }

            $conn->close();
            header("Location: ../Home/Home.php?auth=login&auth_error=" . urlencode($error));
            exit();
        }

        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email, password, role, profile_picture
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password"])) {

                figurify_clear_login_attempts($conn, $email);

                // Bawat portal (customer/staff/owner) may sariling session cookie.
                $rolePortalMap = [
                    "admin"    => "owner",
                    "staff"    => "staff",
                    "customer" => "customer",
                ];

                session_write_close();
                figurify_start_session($rolePortalMap[$user["role"]] ?? "customer");

                // Bagong session ID pagkatapos mag-login (session fixation prevention).
                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["profile_picture"] = $user["profile_picture"];

                /* Bagong "last_seen" tracking - para makita sa User
                   Management (owner side) kung Online o Offline (at
                   kailan huling gumalaw) ang account na ito. */
                figurify_touch_last_seen();

                /* Remember me - mananatiling naka-login sa device na ito (30 araw) */
                if (($_POST["remember"] ?? "") === "1") {
                    require_once __DIR__ . "/../helpers/remember_helper.php";
                    figurify_remember_issue($conn, (int) $user["user_id"], $rolePortalMap[$user["role"]] ?? "customer");
                }

                $siteBase = figurify_login_site_base();

                $rolePortalRedirect = [
                    "admin"    => $siteBase . "owner/ownerdashboard.php",
                    "staff"    => $siteBase . "staff/staffdashboard.php",
                    "customer" => $siteBase . "Home/Home.php",
                ];

                if (isset($rolePortalRedirect[$user["role"]])) {

                    if ($isAjaxLogin) {
                        figurify_login_ajax_respond("success", "", $rolePortalRedirect[$user["role"]]);
                    }

                    header("Location: " . $rolePortalRedirect[$user["role"]]);
                    exit();

                } else {
                    session_unset();
                    session_destroy();
                    $error = "Invalid account role.";

                    if ($isAjaxLogin) {
                        figurify_login_ajax_respond("error", $error);
                    }
                }

            } else {
                figurify_record_failed_login($conn, $email);
                figurify_record_action_attempt($conn, "login_fail_ip", figurify_client_ip());

                $error = "Invalid email or password.";

                if ($isAjaxLogin) {
                    figurify_login_ajax_respond("error", $error);
                }
            }

        } else {
            figurify_record_failed_login($conn, $email);
                figurify_record_action_attempt($conn, "login_fail_ip", figurify_client_ip());

            $error = "Invalid email or password.";

            if ($isAjaxLogin) {
                figurify_login_ajax_respond("error", $error);
            }
        }

        $stmt->close();
    }

    $conn->close();

    /* Non-AJAX fallback lang ito (normal na gamit, laging AJAX ang
       popup): wala nang sariling HTML form, bumalik na lang sa Home
       na may ?auth_error= para awtomatikong bumukas ulit ang popup. */
    header("Location: ../Home/Home.php?auth=login&auth_error=" . urlencode($error ?? "Invalid email or password."));
    exit();

} else {

    /* Plain visit (direktang link o protected-page redirect). Wala
       nang sariling login page - papunta sa Home, may ?auth=login
       para awtomatikong bumukas ang popup kung hindi pa naka-login. */
    figurify_start_session();

    if (isset($_SESSION["user_id"])) {
        header("Location: ../Home/Home.php");
    } else {
        header("Location: ../Home/Home.php?auth=login");
    }
    exit();
}

