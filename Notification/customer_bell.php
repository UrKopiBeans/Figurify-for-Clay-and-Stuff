<?php
/* ==========================================================================
   CUSTOMER NOTIFICATION CHECK   (Notification/customer_bell.php)
   JSON endpoint lang (walang HTML). Tinatawag kada 3 segundo ng
   [3] NOTIFICATION AUTO-REFRESH sa Shared/navbar.php para malaman kung may
   bagong notification ang naka-login na customer.
   Kaparehong pattern ng Notification/staff_bell.php (owner/staff).

   Sagot:  { "notifications": [...20 pinakabago], "unread_count": N }
   ========================================================================== */

require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

header("Content-Type: application/json");


// dapat naka-login

if (!isset($_SESSION["user_id"])) {

    http_response_code(403);
    echo json_encode(["error" => "forbidden"]);
    exit();

}

require_once __DIR__ . "/../Admin/database.php";

$user_id = (int) $_SESSION["user_id"];


// 20 pinakabagong notification + bilang ng hindi pa nababasa

$stmt = $conn->prepare(
    "SELECT notification_id, order_id, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 20"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$notifications = [];
$unreadCount   = 0;

while ($row = $result->fetch_assoc()) {

    $row["notification_id"] = (int) $row["notification_id"];
    $row["order_id"]        = $row["order_id"] !== null ? (int) $row["order_id"] : null;
    $row["is_read"]         = (int) $row["is_read"];
    $row["message"]         = figurify_show_text($row["message"]); // "PHP 450" / lumang "?450" -> ₱450

    if (!$row["is_read"]) {
        $unreadCount++;
    }

    $notifications[] = $row;

}

$stmt->close();
$conn->close();

echo json_encode([
    "notifications" => $notifications,
    "unread_count"  => $unreadCount,
]);
/* ====================== END CUSTOMER NOTIFICATION CHECK ====================== */
