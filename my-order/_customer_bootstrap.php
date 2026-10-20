<?php

/* Shared bootstrap ng customer action endpoints (my-order/*.php).
   Customer session lang; ang ownership at status ay sinusuri ng
   helpers/order_workflow.php (hindi ng page). */

if (basename($_SERVER["SCRIPT_FILENAME"] ?? "") === basename(__FILE__)) {
    http_response_code(404);
    exit();
}

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";

if (!defined("ALL_IN_ONE_FUNCTIONS_ONLY")) {
    define("ALL_IN_ONE_FUNCTIONS_ONLY", true);
}
require_once __DIR__ . "/../Shared/all-in-one.php"; // [ALL-IN-ONE] pop up functions
require_once __DIR__ . "/../helpers/notification_helper.php";
require_once __DIR__ . "/../helpers/order_workflow.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../Login/Login.php");
    exit();
}

if (($_SESSION["role"] ?? "") !== "customer") {
    header("Location: ../Home/Home.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

function figurify_customer_done(mysqli $conn, string $url, ?string $flash = null, string $type = "success"): void
{
    if ($flash !== null) {
        figurify_flash($flash, $type);
    }

    $conn->close();
    header("Location: " . $url);
    exit();
}

/* proof-of-payment / reference image: totoong image lang, random na pangalan */
function figurify_customer_save_image(array $file, string $relativeDir, string $prefix): ?string
{
    $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];

    if (
        !isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK ||
        $file["size"] <= 0 || $file["size"] > 5 * 1024 * 1024 ||
        !is_uploaded_file($file["tmp_name"])
    ) {
        return null;
    }

    $mime = mime_content_type($file["tmp_name"]);

    if (!isset($allowed[$mime]) || @getimagesize($file["tmp_name"]) === false) {
        return null;
    }

    $absDir = __DIR__ . "/../" . trim($relativeDir, "/") . "/";

    if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
        return null;
    }

    $name = $prefix . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $allowed[$mime];

    return move_uploaded_file($file["tmp_name"], $absDir . $name)
        ? trim($relativeDir, "/") . "/" . $name
        : null;
}

function figurify_customer_delete_upload(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $abs  = realpath(__DIR__ . "/../" . $relativePath);
    $root = realpath(__DIR__ . "/../uploads");

    if ($abs && $root && strpos($abs, $root) === 0 && is_file($abs)) {
        @unlink($abs);
    }
}
