<?php
/* ==========================================================================
   NOTIFICATIONS PAGE   (Notification/notifications.php)
   Style: Notification/notifications.css      Maliit na script lang sa dulo ([5] SCRIPT) para sa Request Revision.
   (Ang auto-refresh kapag may bagong notification ay nasa Shared/navbar.php,
    gamit ang Notification/customer_bell.php.)

   LAMAN (taas pababa):
     [1] SETUP       helpers, session, database, dapat naka-login
     [2] DATA        notifications ng user, markahan na nabasa na
     [3] FUNCTIONS   cs_notif_time_ago()
     [4] HTML        navbar, page hero, listahan (may It's Good / Revision at bituin), footer
     [5] SCRIPT      Request Revision box
   ========================================================================== */


/* ==========================================================================
   [1] SETUP
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/expiration_helper.php";
require_once __DIR__ . "/../helpers/order_helper.php";

// must be logged in

if (!isset($_SESSION["user_id"])) {

    header("Location: ../Login/Login.php");
    exit();

}

$user_id = $_SESSION["user_id"];
/* ====================== END SETUP ====================== */


/* ==========================================================================
   [2] DATA
   ========================================================================== */

// clean up expired quotations muna para makita agad ang auto-cancel

figurify_expire_old_quotes($conn);
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


// mga notification na may sagot na puwedeng pindutin dito mismo (hindi na kailangang pumunta sa My Orders)
//   $progressActions[order_id] = ["update_id" => .., "can_revision" => ..]  - may progress photo na hinihintay ang sagot
//   $reviewActions[order_id]   = review_token                               - completed na, wala pang review

$progressActions = [];

$pendingStmt = $conn->prepare(
    "SELECT pu.order_id, pu.update_id
     FROM order_progress_updates pu
     JOIN orders o ON o.order_id = pu.order_id
     WHERE o.user_id = ? AND pu.customer_response = 'pending' AND pu.is_final = 0
     ORDER BY pu.update_id DESC"
);
$pendingStmt->bind_param("i", $user_id);
$pendingStmt->execute();

foreach ($pendingStmt->get_result() as $pendingRow) {

    $pendingOrder = (int) $pendingRow["order_id"];

    // pinakabago lang kada order, at dapat tunay na naghihintay pa ang order (backend ang huling bantay)
    if (!isset($progressActions[$pendingOrder]) && figurify_wf_can($conn, $pendingOrder, "approve_progress")["ok"]) {
        $progressActions[$pendingOrder] = [
            "update_id"    => (int) $pendingRow["update_id"],
            "can_revision" => figurify_wf_can($conn, $pendingOrder, "request_revision")["ok"],
        ];
    }
}

$pendingStmt->close();

$reviewActions = [];

$reviewStmt = $conn->prepare(
    "SELECT o.order_id
     FROM orders o
     LEFT JOIN reviews r ON r.order_id = o.order_id
     WHERE o.user_id = ? AND o.status = 'completed' AND r.order_id IS NULL"
);
$reviewStmt->bind_param("i", $user_id);
$reviewStmt->execute();

foreach ($reviewStmt->get_result() as $reviewRow) {
    $reviewActions[(int) $reviewRow["order_id"]] = figurify_ensure_review_token($conn, (int) $reviewRow["order_id"]);
}

$reviewStmt->close();

// isang beses lang ipapakita ang sagot kada order (sa pinakabagong notification na tugma)
$progressShown = [];
$reviewShown   = [];


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
/* ====================== END DATA ====================== */


/* ==========================================================================
   [3] FUNCTIONS
   ========================================================================== */

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
/* ====================== END FUNCTIONS ====================== */


/* ==========================================================================
   [4] HTML
   ========================================================================== */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifications | Clay and Stuff</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="notifications.css">
</head>

<body>

<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->



<!-- ====================== PAGE HERO (css: notifications.css [2]) ====================== -->
<header class="page-hero">
    <div class="page-wrap">
        <span class="page-eyebrow">STAY UPDATED</span>
        <h1>Your Notifications</h1>
        <p>
            Updates about your orders will show up here.
        </p>
    </div>
</header>
<!-- ====================== END PAGE HERO ====================== -->


<!-- ====================== NOTIFICATION LIST (css: notifications.css [3][4]) ====================== -->
<main class="notif-page">

    <?php if (empty($notifications)): ?>

        <div class="notif-empty-state">
            <p>
                You don't have any notifications yet.
                <a href="../my-order/my-orders.php">View My Orders</a>
            </p>
        </div>

    <?php else: ?>

        <div class="notification-list">

            <?php foreach ($notifications as $note): ?>

                <?php
                $noteOrder = (int) ($note["order_id"] ?? 0);

                // may It's Good / Revision ba ang notification na ito? (progress photo, pinakabago lang)
                $showProgress = $noteOrder > 0 && isset($progressActions[$noteOrder]) && !isset($progressShown[$noteOrder])
                    && preg_match("/photo/i", $note["message"]);

                // may bituin ba? ("You can now leave a review", pinakabago lang)
                $showReview = $noteOrder > 0 && isset($reviewActions[$noteOrder]) && !isset($reviewShown[$noteOrder])
                    && preg_match("/leave a review/i", $note["message"]);

                if ($showProgress) { $progressShown[$noteOrder] = true; }
                if ($showReview)   { $reviewShown[$noteOrder]   = true; }
                ?>

                <div class="notification-item">

                <a
                    href="../my-order/my-orders.php<?php echo !empty($note["order_id"]) ? "?order_id=" . (int) $note["order_id"] : ""; ?>"
                    class="notification-card <?php echo $note["is_read"] ? "" : "unread"; ?>"
                >

                    <div class="notification-body">
                        <p><?php echo htmlspecialchars(figurify_show_text($note["message"])); ?></p>
                    </div>

                    <span
                        class="notification-time"
                        title="<?php echo date("F j, Y, g:i A", strtotime($note["created_at"])); ?>"
                    >
                        <?php echo cs_notif_time_ago($note["created_at"]); ?>
                    </span>

                </a>

                <?php if ($showProgress): ?>

                    <form method="POST" action="../my-order/respond_progress.php" class="notification-actions">
                        <input type="hidden" name="order_id" value="<?php echo $noteOrder; ?>">
                        <input type="hidden" name="update_id" value="<?php echo (int) $progressActions[$noteOrder]["update_id"]; ?>">
                        <input type="hidden" name="return_to" value="notifications">
                        <input type="hidden" name="response" value="approved">

                        <div class="notification-actions-row">
                            <button type="submit" class="notif-btn notif-btn-primary">It's Good</button>
                            <?php if ($progressActions[$noteOrder]["can_revision"]): ?>
                                <button type="button" class="notif-btn notif-btn-light" onclick="csNotifToggleRevision(this)">Request Revision</button>
                            <?php endif; ?>
                        </div>

                        <?php if ($progressActions[$noteOrder]["can_revision"]): ?>
                            <div class="notification-revision" hidden>
                                <textarea name="revision_note" rows="2" placeholder="Tell us what to revise..."></textarea>
                                <button type="button" class="notif-btn notif-btn-primary" onclick="csNotifSendRevision(this)">Send</button>
                            </div>
                        <?php endif; ?>
                    </form>

                <?php endif; ?>

                <?php if ($showReview): ?>

                    <div class="notification-actions">
                        <span class="notification-actions-label">Tap a star to rate:</span>
                        <span class="notification-stars">
                            <?php for ($star = 1; $star <= 5; $star++): ?>
                                <a href="../my-order/submit_review.php?order_id=<?php echo $noteOrder; ?>&amp;token=<?php echo urlencode($reviewActions[$noteOrder]); ?>&amp;rating=<?php echo $star; ?>" title="<?php echo $star; ?> star<?php echo $star > 1 ? "s" : ""; ?>">★</a>
                            <?php endfor; ?>
                        </span>
                    </div>

                <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>
<!-- ====================== END NOTIFICATION LIST ====================== -->


<!-- ====================== [5] SCRIPT  (Request Revision) ====================== -->
<script>
// buksan / isara ang box ng revision note
function csNotifToggleRevision(button) {
    var box = button.closest("form").querySelector(".notification-revision");
    box.hidden = !box.hidden;
}

// ipadala ang revision (kailangan may nakasulat)
function csNotifSendRevision(button) {
    var form = button.closest("form");

    if (form.querySelector("textarea").value.trim() === "") {
        if (window.csToast) { csToast("Tell us what you'd like changed.", "error"); }
        return;
    }

    form.querySelector('input[name="response"]').value = "revision";
    form.submit();
}
</script>
<!-- ====================== END SCRIPT ====================== -->


<!-- ====================== FOOTER (Shared/footer.php) ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
