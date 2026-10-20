<?php

/* Shared bootstrap ng owner/ at staff/ action endpoints.
   Ang owner/xxx.php at staff/xxx.php ay maliliit na wrapper na
   nagse-set ng $figurifyPortalRole ("admin" o "staff") tapos nire-require
   ang file dito - iisang logic, hindi na dalawang kopya na pwedeng
   magkaiba. Lahat ng status change ay dumadaan sa order_workflow.php. */

if (!isset($figurifyPortalRole) || !in_array($figurifyPortalRole, ["admin", "staff"], true)) {
    http_response_code(404);
    exit();
}

require_once __DIR__ . "/../session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../../Admin/database.php";

if (!defined("ALL_IN_ONE_FUNCTIONS_ONLY")) {
    define("ALL_IN_ONE_FUNCTIONS_ONLY", true);
}
require_once __DIR__ . "/../../Shared/all-in-one.php"; // [ALL-IN-ONE] pop up functions
require_once __DIR__ . "/../notification_helper.php";
require_once __DIR__ . "/../mailer_helper.php";
require_once __DIR__ . "/../access_helper.php";
require_once __DIR__ . "/../order_workflow.php";

figurify_require_role($figurifyPortalRole);

$figurifyActorLabel = ($figurifyPortalRole === "admin") ? "the owner" : "staff";

/* redirect + flash helper */
function figurify_action_done(mysqli $conn, string $url, ?string $flash = null, string $type = "success"): void
{
    if ($flash !== null) {
        figurify_flash($flash, $type);
    }

    $conn->close();
    header("Location: " . $url);
    exit();
}

/* Image upload na sinisigurong totoong larawan (MIME + extension mula sa
   MIME, random na filename, hindi executable). Ibinabalik ang relative
   path, o null + error message. */
function figurify_save_uploaded_image(array $file, string $relativeDir, string $prefix, int $maxBytes = 5242880): array
{
    $allowed = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp",
        "image/gif"  => "gif",
    ];

    if (!isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($file["tmp_name"])) {
        return [null, "Please choose a photo to send."];
    }

    if ($file["size"] <= 0 || $file["size"] > $maxBytes) {
        return [null, "The photo must be smaller than " . round($maxBytes / 1048576) . "MB."];
    }

    $mime = function_exists("mime_content_type") ? mime_content_type($file["tmp_name"]) : "";

    if (!isset($allowed[$mime]) || @getimagesize($file["tmp_name"]) === false) {
        return [null, "Only JPG, PNG, GIF, or WEBP images are allowed."];
    }

    $absDir = __DIR__ . "/../../" . trim($relativeDir, "/") . "/";

    if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
        return [null, "Could not create upload folder."];
    }

    $name = $prefix . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $allowed[$mime];

    if (!move_uploaded_file($file["tmp_name"], $absDir . $name)) {
        return [null, "Failed to save the uploaded photo."];
    }

    return [trim($relativeDir, "/") . "/" . $name, null];
}

function figurify_delete_upload(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === "") {
        return;
    }

    $abs = realpath(__DIR__ . "/../../" . $relativePath);
    $uploadsRoot = realpath(__DIR__ . "/../../uploads");

    if ($abs && $uploadsRoot && strpos($abs, $uploadsRoot) === 0 && is_file($abs)) {
        @unlink($abs);
    }
}
