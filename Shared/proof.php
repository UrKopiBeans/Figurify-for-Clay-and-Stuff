<?php

/* ==========================================================================
   PROOF DOWNLOAD   (Shared/proof.php)
   Ligtas na pagbukas ng proof of payment at proof of refund (may access check).
   ========================================================================== */

/* Authorized download ng proof of payment / proof of refund.
   Tingnan ang helpers/proof_helper.php. */

require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session(); // ?portal=owner|staff|customer ang pumipili ng session

require_once __DIR__ . "/../Admin/database.php";

$path = (string) ($_GET["f"] ?? "");

function figurify_proof_deny(int $code = 404): void
{
    http_response_code($code);
    header("Content-Type: text/plain; charset=UTF-8");
    exit($code === 403 ? "Not allowed." : "Not found.");
}

if (!preg_match('#^uploads/(payment_proofs|refund_proofs|shipping_receipts)/[A-Za-z0-9_.-]+\.(jpe?g|png|webp|gif)$#i', $path) || strpos($path, "..") !== false) {
    figurify_proof_deny(404);
}

if (!isset($_SESSION["user_id"], $_SESSION["role"])) {
    figurify_proof_deny(403);
}

$role   = (string) $_SESSION["role"];
$userId = (int) $_SESSION["user_id"];

if ($role === "customer") {

    // sariling order lang (IDOR protection)
    $stmt = $conn->prepare(
        "SELECT order_id FROM orders
         WHERE user_id = ? AND (payment_proof = ? OR balance_proof = ? OR refund_proof = ?)
         LIMIT 1"
    );
    $stmt->bind_param("isss", $userId, $path, $path, $path);
    $stmt->execute();
    $owns = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /* resibo ng courier (shipping_receipt) - kung may column na */
    if (!$owns && strpos($path, "uploads/shipping_receipts/") === 0) {
        try {
            $rstmt = $conn->prepare("SELECT order_id FROM orders WHERE user_id = ? AND shipping_receipt = ? LIMIT 1");
            if ($rstmt) {
                $rstmt->bind_param("is", $userId, $path);
                $rstmt->execute();
                $owns = (bool) $rstmt->get_result()->fetch_assoc();
                $rstmt->close();
            }
        } catch (Throwable $e) {
            $owns = false;
        }
    }

    if (!$owns) {
        figurify_proof_deny(403);
    }

} elseif (!in_array($role, ["admin", "staff"], true)) {
    figurify_proof_deny(403);
}

$conn->close();

$abs  = realpath(__DIR__ . "/../" . $path);
$root = realpath(__DIR__ . "/../uploads");

if (!$abs || !$root || strpos($abs, $root) !== 0 || !is_file($abs)) {
    figurify_proof_deny(404);
}

$mime = mime_content_type($abs);

if (!in_array($mime, ["image/jpeg", "image/png", "image/webp", "image/gif"], true)) {
    figurify_proof_deny(404);
}

header("Content-Type: " . $mime);
header("Content-Length: " . filesize($abs));
header("Cache-Control: private, max-age=300");
header("X-Content-Type-Options: nosniff");
header("Content-Disposition: inline; filename=\"" . basename($abs) . "\"");

readfile($abs);
exit();

/* ==================== END PROOF DOWNLOAD ==================== */
