<?php
/* ==========================================================================
   PROGRESS RESPONSE PAGE   (my-order/progress_response.php)
   Style: my-order/submit_review.css (parehong card)
   Para sa buttons ng Gmail ("It's Good" / "Request Revision"). Walang login, token lang
   (order_id + update_id + token). Pareho ang logic ng My Orders at Notifications:
   helpers/order_helper.php SECTION 5 (figurify_progress_respond).

   LAMAN (taas pababa):
     [1] SETUP + CHECK   i-check ang link, kunin ang progress update
     [2] FORM SAVE       It's Good / Request Revision (POST)
     [3] HTML            card (error / tapos na / form)
   ========================================================================== */


/* ==========================================================================
   [1] SETUP + CHECK
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/order_helper.php";
require_once __DIR__ . "/../helpers/messaging_helper.php";

$order_id  = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : (int) ($_POST["order_id"] ?? 0);
$update_id = isset($_GET["update_id"]) ? (int) $_GET["update_id"] : (int) ($_POST["update_id"] ?? 0);
$token     = trim((string) ($_GET["token"] ?? $_POST["token"] ?? ""));
$openBox   = (($_GET["do"] ?? "") === "revision") ? "revision" : "good";

$pageError = "";
$update    = null;
$customer  = null;

if ($order_id <= 0 || $update_id <= 0 || !figurify_progress_token_ok($conn, $order_id, $update_id, $token)) {

    $pageError = "This link is invalid or has expired.";

} else {

    $stmt = $conn->prepare(
        "SELECT pu.update_id, pu.image_path, pu.staff_message, pu.customer_response, pu.is_final,
                o.user_id, u.full_name
         FROM order_progress_updates pu
         JOIN orders o ON o.order_id = pu.order_id
         JOIN users u ON u.user_id = o.user_id
         WHERE pu.update_id = ? AND pu.order_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $update_id, $order_id);
    $stmt->execute();
    $update = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$update) {

        $pageError = "This link is invalid or has expired.";

    } elseif (isset($_SESSION["user_id"]) && (int) $_SESSION["user_id"] !== (int) $update["user_id"]) {

        // naka-login sa ibang account
        $pageError = "This link isn't for your account.";
        $update    = null;

    } else {

        // ang customer na may-ari ng order ang gagawa ng action (token ang patunay)
        $customer = ["id" => (int) $update["user_id"], "role" => FIGURIFY_WF_CUSTOMER, "name" => (string) $update["full_name"]];
    }
}

// sinagot na ba? (pending lang ang may form) - at may natitira pa bang revision?
$isPending   = $update && $update["customer_response"] === "pending" && empty($update["is_final"]);
$canRevision = false;

if ($isPending) {
    $canRevision = figurify_wf_can($conn, $order_id, "request_revision", $customer)["ok"];
}

$doneMessage = "";
$doneType    = "success";
/* ====================== END SETUP + CHECK ====================== */


/* ==========================================================================
   [2] FORM SAVE
   ========================================================================== */
if ($isPending && $_SERVER["REQUEST_METHOD"] === "POST") {

    $response = (($_POST["response"] ?? "") === "revision") ? "revision" : "approved";
    $note     = trim((string) ($_POST["revision_note"] ?? ""));

    if ($response === "revision" && $note === "") {

        $doneMessage = "Please tell us what you'd like changed.";
        $doneType    = "error";
        $openBox     = "revision";

    } else {

        $result      = figurify_progress_respond($conn, $order_id, $update_id, $response, $note, $customer);
        $doneMessage = $result["message"];
        $doneType    = $result["ok"] ? "success" : "error";

        if ($result["ok"]) {
            $isPending = false;   // tapos na - wala nang form
        }
    }
}

$conn->close();
/* ====================== END FORM SAVE ====================== */


/* ==========================================================================
   [3] HTML
   ========================================================================== */
$backHref = isset($_SESSION["user_id"]) ? "my-orders.php?order_id=" . (int) $order_id : "../Home/Home.php";
$backText = isset($_SESSION["user_id"]) ? "Go to My Orders" : "Go to Home";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Progress Photo | Clay and Stuff</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="submit_review.css">
</head>

<body class="page-review">

<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->

<!-- ====================== PROGRESS CARD (css: submit_review.css) ====================== -->
<main class="review-page">
<div class="card">

    <h1>Progress Photo</h1>

    <?php if ($pageError !== ""): ?>

        <div class="error-box"><?php echo htmlspecialchars($pageError); ?></div>

    <?php else: ?>

        <div class="sub">Order #<?php echo (int) $order_id; ?></div>

        <?php if ($doneMessage !== "") { echo figurify_toast_now($doneMessage, $doneType); } ?>

        <img class="progress-photo" src="../<?php echo htmlspecialchars($update["image_path"]); ?>" alt="Order progress photo">

        <?php if (!empty($update["staff_message"])): ?>
            <!-- mensahe ng staff: may container + kung kanino galing -->
            <div class="msg-box">
                <span class="msg-from">Message from Clay and Stuff</span>
                <p><?php echo nl2br(htmlspecialchars($update["staff_message"])); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($isPending): ?>

            <form method="POST" action="progress_response.php">
                <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">
                <input type="hidden" name="update_id" value="<?php echo (int) $update_id; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <?php if ($canRevision): ?>
                    <label class="field-label" for="revision_note">Want a change? Tell us what to revise (one revision only)</label>
                    <textarea name="revision_note" id="revision_note" placeholder="Tell us what to revise..."><?php echo $openBox === "revision" ? htmlspecialchars((string) ($_POST["revision_note"] ?? "")) : ""; ?></textarea>
                <?php endif; ?>

                <div class="form-actions form-actions-row">
                    <button type="submit" name="response" value="approved" class="btn-submit">It's Good</button>
                    <?php if ($canRevision): ?>
                        <button type="submit" name="response" value="revision" class="btn-back">Request Revision</button>
                    <?php endif; ?>
                </div>
            </form>

        <?php else: ?>

            <p class="meta">
                <?php
                // wala nang form: katatapos lang sumagot, sinagot na dati, o final photo na
                if ($doneMessage !== "" && $doneType === "success") {
                    echo htmlspecialchars($doneMessage);
                } elseif (!empty($update["is_final"])) {
                    echo "This is your final photo. We'll ship your order soon.";
                } else {
                    echo "You already answered this photo. Check My Orders for the latest.";
                }
                ?>
            </p>

        <?php endif; ?>

        <div class="form-actions"><a class="btn-back" href="<?php echo htmlspecialchars($backHref); ?>"><?php echo $backText; ?></a></div>

    <?php endif; ?>

</div>
</main>
<!-- ====================== END PROGRESS CARD ====================== -->


<!-- ====================== FOOTER (Shared/footer.php) ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
