<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/expire_helper.php";


// must be logged in

if (!isset($_SESSION["user_id"])) {

    header("Location: ../Login/Login.php");
    exit();

}

$user_id = $_SESSION["user_id"];


// clean up expired quotations muna para makita agad ang auto-cancel

figurify_expire_old_quotes($conn);
require_once __DIR__ . "/../helpers/order_complete_helper.php";
figurify_autocomplete_shipped_orders($conn);


// get all notifications of this user, newest first

$stmt = $conn->prepare(
    "SELECT notification_id, order_id, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

$stmt->close();


// mark all as read, customer opened this page na

$markStmt = $conn->prepare(
    "UPDATE notifications
     SET is_read = 1
     WHERE user_id = ? AND is_read = 0"
);

$markStmt->bind_param("i", $user_id);
$markStmt->execute();
$markStmt->close();

$conn->close();


// "2m ago" style na oras para sa listahan
if (!function_exists("cs_notif_time_ago")) {
    function cs_notif_time_ago($datetime)
    {
        $ts   = strtotime($datetime);
        $diff = time() - $ts;

        if ($diff < 60)     { return "Just now"; }
        if ($diff < 3600)   { return floor($diff / 60) . "m ago"; }
        if ($diff < 86400)  { return floor($diff / 3600) . "h ago"; }
        if ($diff < 604800) { return floor($diff / 86400) . "d ago"; }

        return date("M j, Y", $ts);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Notifications — Clay and Stuff</title>


    <link rel="stylesheet" href="notifications.css?v=4" />
</head>
<body>

<?php include "../Shared/navbar.php"; ?>

<header class="page-hero">
    <div class="page-wrap">
        <span class="page-eyebrow">STAY UPDATED</span>
        <h1>Your Notifications</h1>
        <p>
            Updates about your commissions — like when our staff sends
            you a quotation — will show up here.
        </p>
    </div>
</header>

<main class="notif-page">

    <?php if (empty($notifications)): ?>

        <div class="notif-empty-state">
            <p>
                You don't have any notifications yet.
                <a href="../my-order/my-orders.php">View My Orders →</a>
            </p>
        </div>

    <?php else: ?>

        <div class="notification-list">

            <?php foreach ($notifications as $note): ?>

                <a
                    href="../my-order/my-orders.php<?php echo !empty($note["order_id"]) ? "?order_id=" . (int) $note["order_id"] : ""; ?>"
                    class="notification-card <?php echo $note["is_read"] ? "" : "unread"; ?>"
                >

                    <div class="notification-body">
                        <p><?php echo htmlspecialchars($note["message"]); ?></p>
                    </div>

                    <span
                        class="notification-time"
                        title="<?php echo date("F j, Y — g:i A", strtotime($note["created_at"])); ?>"
                    >
                        <?php echo cs_notif_time_ago($note["created_at"]); ?>
                    </span>

                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<?php include "../Shared/footer.php"; ?>

</body>
</html>
