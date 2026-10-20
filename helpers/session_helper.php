<?php

/* Iisang timezone para sa buong system (Philippines). Kung wala ito,
   ang default ng XAMPP/php.ini (madalas Europe/Berlin o UTC) ang gamit,
   kaya puwedeng magkamali ng isang araw ang "today" - sa Due Today /
   Overdue ng dashboard, sa 7-day booking rule, at sa mga petsang naka-display. */
date_default_timezone_set("Asia/Manila");

/* Hiwalay na session cookie kada portal (customer / staff / owner)
   para pwedeng sabay naka-login sa magkaibang tab.
   Gamitin ang figurify_start_session() kapalit ng session_start(). */

if (!function_exists("figurify_session_portal")) {

    function figurify_session_portal(): string
    {
        /* Explicit override via ?portal= (used by logout.php links) */
        if (!empty($_GET["portal"])) {
            $requested = strtolower($_GET["portal"]);

            if (in_array($requested, ["customer", "staff", "owner"], true)) {
                return $requested;
            }
        }

        /* Detect from the current file's folder */
        $path = str_replace(
            "\\",
            "/",
            $_SERVER["SCRIPT_FILENAME"] ?? $_SERVER["SCRIPT_NAME"] ?? ""
        );

        if (strpos($path, "/staff/") !== false) {
            return "staff";
        }

        if (strpos($path, "/owner/") !== false) {
            return "owner";
        }

        /* Mga page ng customer (Home, Collection, Commission, My Orders,
           Profile) at ang login mismo - laging customer session. Kung hindi,
           pagkatapos mag-logout ang Owner/Staff, dala pa ng browser ang
           referer ng owner/staff page, kaya maling session ang nagagamit
           at lumalabas ang "Your session has expired" pag nag-login ulit. */
        foreach (["/Home/", "/Collection/", "/Commission/", "/my-order/", "/Profile/"] as $customerFolder) {
            if (strpos($path, $customerFolder) !== false) {
                return "customer";
            }
        }

        if (substr($path, -strlen("/Login/Login.php")) === "/Login/Login.php"
            && ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
            return "customer";
        }

        /* Fallback for shared pages (like logout.php): check the referer */
        $referer = $_SERVER["HTTP_REFERER"] ?? "";

        if (strpos($referer, "/staff/") !== false) {
            return "staff";
        }

        if (strpos($referer, "/owner/") !== false) {
            return "owner";
        }

        return "customer";
    }
}


if (!function_exists("figurify_start_session")) {

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

        /* Siguraduhing sariling session ID ng portal ang gamit.
           Kapag nagpalit ng portal sa isang request (hal. login), naiiwan ang lumang ID
           at nabubura ang customer session kasama ang csrf_token. */
        $cookieSessionId = $_COOKIE[$sessionName] ?? "";

        if (is_string($cookieSessionId) && preg_match('/^[A-Za-z0-9,-]{22,256}$/', $cookieSessionId)) {
            session_id($cookieSessionId);
        } else {
            session_id(session_create_id());
        }

        /* Session cookie hardening: httponly (hindi mababasa ng JS),
           samesite=Lax (extra proteksyon laban sa CSRF), at secure
           na naka-auto-detect (naka-on lang kapag totoong HTTPS,
           para gumana pa rin sa local/XAMPP na plain HTTP). */
        $isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
            || (($_SERVER["SERVER_PORT"] ?? "") == 443)
            || ((strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "")) === "https");

        session_set_cookie_params([
            "lifetime" => 0,
            "path"     => "/",
            "domain"   => "",
            "secure"   => $isHttps,
            "httponly" => true,
            "samesite" => "Lax",
        ]);

        session_start();

        /* "Remember me" - ibalik ang login kung may valid na token */
        require_once __DIR__ . "/remember_helper.php";
        figurify_remember_restore($portal);

        /* naka-ban na account -> logout agad */
        require_once __DIR__ . "/ban_helper.php";
        figurify_kick_if_banned();
    }
}
