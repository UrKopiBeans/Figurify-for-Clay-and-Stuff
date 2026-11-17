<?php

/* =====================================================================
   order_actions.php  -  PINAGSAMANG ORDER ACTIONS NG OWNER AT STAFF
   =====================================================================
   Dating folder na helpers/order_actions/ na may 7 file. Isang file na lang
   ngayon, at wala na ang folder:
     - _bootstrap.php            (SECTION 1 at 2)
     - send_quotation.php        (SECTION 3)
     - decline_quote.php         (SECTION 3)
     - send_progress_update.php  (SECTION 3)
     - accept_revision.php       (SECTION 3)
     - decline_revision.php      (SECTION 3)
     - update_active_order.php   (SECTION 3)
   Puwede nang i-delete ang buong folder na helpers/order_actions/.

   PAANO SILA GUMAGANA
   ---------------------------------------------------------------------
   Ang owner/xxx.php at staff/xxx.php ay maliliit na wrapper (12 file). Bawat isa ay
   nagse-set ng dalawang variable, tapos nire-require ang file na ito:
       $figurifyPortalRole  = "admin" o "staff"   (sino ang nag-click)
       $figurifyOrderAction = "send_quotation"    (alin sa 6 na action)
       require __DIR__ . "/../helpers/order_actions.php";
   Iisang logic para sa owner at staff, kaya hindi na dalawang kopya na pwedeng magkaiba.
   Ang mga form sa pages (action="send_quotation.php") ay hindi nagbago.

   Dito sa file, ganito ang takbo (tingnan ang mga SECTION):
     SECTION 1 - SETUP: role/action check (404 kung mali), session, database, mga helper, role check
     SECTION 2 - MALILIIT NA TOOLS: figurify_action_done(), figurify_save_uploaded_image(),
                 figurify_delete_upload()
     SECTION 3 - ANG 7 NA ACTION (bawat isa ay function)
     SECTION 4 - RUN: tinatawag ang action na hiniling ng wrapper
   Lahat ng pagpapalit ng status ay dumadaan sa order_workflow.php
   (figurify_wf_perform) - dito rin dumadaan ang CSRF at role check.
   ===================================================================== */

/* ---------------------------------------------------------------------
   PANTANDAAN (simpleng paliwanag)
   ANO ITO     : ang gumagawa ng 6 na aksyon sa order na pareho para sa owner at staff:
                 send_quotation, decline_quote, return_for_correction, send_progress_update,
                 accept_revision, decline_revision, update_active_order.
   PARA SAAN   : isang logic na lang, hindi na dalawang kopya (isa sa owner, isa sa staff).
   PANO NAGANA : 1) Ang owner/xxx.php o staff/xxx.php ay nagse-set ng role at action.
                 2) Ni-require nito ang file na ito.
                 3) Dito chine-check ang role, nire-run ang tamang action, at nire-redirect
                    ang user pabalik gamit ang figurify_action_done().
                 Hindi ito ginagamit nang direkta; lagi itong dumadaan sa wrapper file.
   --------------------------------------------------------------------- */


/* =====================================================================
   SECTION 1 - SETUP (dating _bootstrap.php)
   Ang lahat ng nasa seksyong ito ay tumatakbo sa global scope (kaya
   global pa rin ang $conn gaya ng dati).
   ===================================================================== */

$figurifyActionHandlers = [
    "send_quotation"       => "figurify_order_action_send_quotation",
    "decline_quote"        => "figurify_order_action_decline_quote",
    "return_for_correction" => "figurify_order_action_return_for_correction",
    "send_progress_update" => "figurify_order_action_send_progress_update",
    "accept_revision"      => "figurify_order_action_accept_revision",
    "decline_revision"     => "figurify_order_action_decline_revision",
    "update_active_order"  => "figurify_order_action_update_active_order",
];

/* hindi puwedeng buksan nang direkta: kailangan galing sa wrapper (role + action) */
if (!isset($figurifyPortalRole) || !in_array($figurifyPortalRole, ["admin", "staff"], true)
    || !isset($figurifyOrderAction) || !isset($figurifyActionHandlers[$figurifyOrderAction])) {
    http_response_code(404);
    exit();
}

require_once __DIR__ . "/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";

require_once __DIR__ . "/messaging_helper.php";
require_once __DIR__ . "/order_helper.php";
require_once __DIR__ . "/expiration_helper.php";   // para sa send_quotation

figurify_require_role($figurifyPortalRole);

$figurifyActorLabel = ($figurifyPortalRole === "admin") ? "the owner" : "staff";


/* =====================================================================
   SECTION 2 - MALILIIT NA TOOLS (dating _bootstrap.php)
   ===================================================================== */

/* ANO ITO: flash message (kung meron) + isara ang database + redirect sa $url, tapos tapos na ang request.
   Ginagamit ng lahat ng action sa ibaba para tapusin ang request. */
function figurify_action_done(mysqli $conn, string $url, ?string $flash = null, string $type = "success"): void
{
    if ($flash !== null) {
        figurify_flash($flash, $type);
    }

    $conn->close();
    header("Location: " . $url);
    exit();
}

/* ANO ITO: ligtas na upload ng larawan.
   Image upload na sinisigurong totoong larawan (MIME + extension mula sa
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

    $absDir = __DIR__ . "/../" . trim($relativeDir, "/") . "/";

    if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) {
        return [null, "Could not create upload folder."];
    }

    $name = $prefix . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $allowed[$mime];

    if (!move_uploaded_file($file["tmp_name"], $absDir . $name)) {
        return [null, "Failed to save the uploaded photo."];
    }

    return [trim($relativeDir, "/") . "/" . $name, null];
}

/* ANO ITO: nagbubura ng file, pero sa loob lang ng uploads/ (hindi makakabura ng ibang file). */
function figurify_delete_upload(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === "") {
        return;
    }

    $abs = realpath(__DIR__ . "/../" . $relativePath);
    $uploadsRoot = realpath(__DIR__ . "/../uploads");

    if ($abs && $uploadsRoot && strpos($abs, $uploadsRoot) === 0 && is_file($abs)) {
        @unlink($abs);
    }
}


/* =====================================================================
   SECTION 3 - ANG 6 NA ACTION
   (dating send_quotation, decline_quote, send_progress_update,
    accept_revision, decline_revision at update_active_order .php)
   Bawat action ay isang function na tumatanggap ng $conn at ng label ng gumawa
   ("the owner" / "staff"). Lahat ay nagtatapos sa figurify_action_done().
   ===================================================================== */

/* ACTION: send_quotation  (form: owner/quotation.php at staff/quotation.php, action="send_quotation.php")
   STAFF/OWNER - verify ng order at pagpapadala ng quotation.
   PENDING_STAFF_VERIFICATION -> STAFF_VERIFIED -> QUOTATION_PENDING -> QUOTATION_SENT
   FLOW: 1) CSRF check  2) i-expire ang lumang quote  3) sa SERVER kinukuwenta ang total (hindi galing sa browser)
         4) abisuhan ang customer (notification + email)  5) abisuhan ang ibang staff  6) balik sa quotation page */
function figurify_order_action_send_quotation(mysqli $conn, string $figurifyActorLabel): void
{
    $order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
    $backUrl  = "quotation.php?order_id=" . $order_id;

    figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

    figurify_expire_old_quotes($conn);

    $prices = (isset($_POST["quoted_price"]) && is_array($_POST["quoted_price"])) ? $_POST["quoted_price"] : [];

    $result = figurify_wf_perform($conn, $order_id, "verify_and_quote", ["prices" => $prices]);

    if (!$result["ok"]) {
        figurify_action_done($conn, $backUrl, $result["error"], "error");
    }

    $total = (float) $result["data"]["total"];

    /* system: maikli lang - Gmail: buong mensahe (helpers/messaging_helper.php) */
    figurify_notify_user(
        $conn,
        (int) $result["order"]["user_id"],
        $order_id,
        "Your quotation for Order #" . $order_id . " is ready: ₱" . number_format($total, 2)
            . ". Please accept or cancel it in My Orders within 2 days.",
        0,
        null,
        false
    );

    figurify_email_quotation_ready($conn, (int) $result["order"]["user_id"], $order_id, $total);

    figurify_notify_staff(
        $conn,
        $order_id,
        "Order #" . $order_id . " was verified and quoted by " . $figurifyActorLabel . ".",
        (int) $_SESSION["user_id"]
    );

    figurify_action_done(
        $conn,
        "quotation.php?order_id=" . $order_id . "&qfilter=quoted&sent=1",
        "Quotation of ₱" . number_format($total, 2) . " sent for order " . $order_id . "."
    );
}


/* ACTION: decline_quote  (form: owner/quotation.php at staff/quotation.php, action="decline_quote.php")
   STAFF/OWNER - tanggihan ang order request bago pa magbayad
   (Order Being Reviewed / Correction Required -> Cancelled).
   FLOW: 1) CSRF check  2) kunin ang dahilan (pili-an o "other")  3) kailangan may dahilan  4) abisuhan ang customer */
function figurify_order_action_decline_quote(mysqli $conn, string $figurifyActorLabel): void
{
    $order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
    $backUrl  = "quotation.php?order_id=" . $order_id;

    $key   = trim((string) ($_POST["decline_reason"] ?? ""));
    $other = mb_substr(trim((string) ($_POST["decline_reason_other"] ?? "")), 0, 190);

    // Maling / hindi malinaw na detalye = ibalik sa customer para ayusin (hindi kinakansela ang order).
    // Ang "other_fix" ay kailangan ng sariling paliwanag; sa iba ay optional na dagdag na note.
    $fixLabels = [
        "fix_details" => "Incorrect or incomplete order details",
        "fix_clarify" => "Needs clarification (for example, the figure style does not match the notes)",
        "fix_images"  => "Reference image is unclear or missing",
        "other_fix"   => $other,
    ];

    if (isset($fixLabels[$key])) {
        $_POST["correction_note"] = ($key === "other_fix" || $other === "") ? $fixLabels[$key] : $fixLabels[$key] . ": " . $other;
        figurify_order_action_return_for_correction($conn, $figurifyActorLabel);
        return;
    }

    figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

    $labels = [
        "unable_to_fulfill" => "Unable to Fulfill the Order",
        "out_of_scope"      => "Outside Our Services",
    ];

    $reason = ($key === "other") ? $other : ($labels[$key] ?? "");

    if ($reason === "") {
        figurify_action_done($conn, $backUrl, "Pick a reason first.", "error");
    }

    $result = figurify_wf_perform($conn, $order_id, "decline_order", ["reason" => $reason]);

    if (!$result["ok"]) {
        figurify_action_done($conn, $backUrl, $result["error"], "error");
    }

    figurify_customer_notice(
        $conn,
        (int) $result["order"]["user_id"],
        $order_id,
        "Sorry, we can't proceed with Order #" . $order_id . ": " . $reason . ".",
        "Update on your Order #" . $order_id,
        [
            "Sorry, we can’t proceed with <strong>Order #" . $order_id . "</strong>, so it was cancelled.",
            "<strong>Reason:</strong> " . figurify_email_e($reason),
            "You can place a new order anytime.",
            figurify_email_status_line(),
        ]
    );

    figurify_notify_staff(
        $conn,
        $order_id,
        "Order #" . $order_id . " was declined by " . $figurifyActorLabel . ".",
        (int) $_SESSION["user_id"]
    );

    figurify_action_done($conn, $backUrl, "Order #" . $order_id . " declined. The customer was notified.");
}


/* =====================================================================
   ACTION: return_for_correction  (form: staff/owner quotation.php)
   STAFF/OWNER - ibinabalik sa customer ang Image Submission request para ayusin.
   PENDING -> NEEDS CORRECTION. Kailangan may nakasulat na dapat ayusin; makikita
   ito ng customer sa My Orders at sa email. Kapag naipasa na ulit ng customer
   (my-order/correct-order.php), babalik ito sa Pending.
   ===================================================================== */
function figurify_order_action_return_for_correction(mysqli $conn, string $figurifyActorLabel): void
{
    $order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
    $backUrl  = "quotation.php?order_id=" . $order_id;

    figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

    $note = mb_substr(trim((string) ($_POST["correction_note"] ?? "")), 0, 500);

    if ($note === "") {
        figurify_action_done($conn, $backUrl, "Tell the customer what needs to be corrected.", "error");
    }

    $result = figurify_wf_perform($conn, $order_id, "return_for_correction", ["reason" => $note]);

    if (!$result["ok"]) {
        figurify_action_done($conn, $backUrl, $result["error"], "error");
    }

    figurify_customer_notice(
        $conn,
        (int) $result["order"]["user_id"],
        $order_id,
        "Order #" . $order_id . " needs a correction: " . $note,
        "Please update your Order #" . $order_id,
        [
            "Please update <strong>Order #" . $order_id . "</strong> so we can send your quotation.",
            "<strong>What to correct:</strong> " . figurify_email_e($note),
            "Go to <strong>My Orders</strong> and click <strong>Fix My Order</strong>.",
            figurify_email_status_line(),
        ]
    );

    figurify_notify_staff(
        $conn,
        $order_id,
        "Order #" . $order_id . " was returned to the customer for correction by " . $figurifyActorLabel . ".",
        (int) $_SESSION["user_id"]
    );

    figurify_action_done($conn, $backUrl, "Order #" . $order_id . " was returned to the customer for correction.");
}
/* ====================== END ACTION: return_for_correction ====================== */


/* ACTION: send_progress_update  (form: staff/owner active-booking.php)
   STAFF/OWNER - pagpapadala ng progress photo.
   Normal: PROCESSING -> PROGRESS_SUBMITTED (customer ang sasagot)
   Revised photo: REVISION_IN_PROGRESS -> approved agad -> Awaiting Balance (o Ready to Ship)
   Final photo (is_final=1): para sa Ready to Ship, hindi nagpapalit ng status.
   FLOW: 1) CSRF  2) pre-check kung pwede (para walang naiiwang upload sa denied request)
         3) ligtas na upload ng larawan  4) i-save sa workflow  5) email na may larawan sa customer */
function figurify_order_action_send_progress_update(mysqli $conn, string $figurifyActorLabel): void
{
    $order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
    $message  = trim((string) ($_POST["staff_message"] ?? ""));
    $isFinal  = (($_POST["is_final"] ?? "") === "1");

    $backUrl  = "active-booking.php?order_id=" . $order_id;

    figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

    $wfAction = $isFinal ? "submit_final_photo" : "submit_progress";

    /* pre-check bago i-save ang file (para walang naiiwang upload sa denied request) */
    $can = figurify_wf_can($conn, $order_id, $wfAction);

    if (!$can["ok"]) {
        figurify_wf_perform($conn, $order_id, $wfAction, []); // para maitala ang denied attempt
        figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($can["error"]));
    }

    [$imagePath, $uploadError] = figurify_save_uploaded_image(
        $_FILES["progress_image"] ?? [],
        "uploads/orders/" . $order_id . "/progress",
        "progress_" . $order_id
    );

    if ($imagePath === null) {
        figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($uploadError));
    }

    $result = figurify_wf_perform($conn, $order_id, $wfAction, [
        "image_path" => $imagePath,
        "message"    => $message,
    ]);

    if (!$result["ok"]) {
        figurify_delete_upload($imagePath);
        figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($result["error"]));
    }

    /* notifications (pagkatapos lang ng matagumpay na transition) */

    $custId = (int) $result["order"]["user_id"];

    $isRevision = !$isFinal && !empty($result["data"]["is_revision"]);

    /* system: maikling notification - Gmail: buong template na may larawan */
    if ($isFinal) {
        $notify = "Order #" . $order_id . " is finished! We'll ship it soon.";
    } elseif ($isRevision) {
        $notify = "Revised photo for Order #" . $order_id . " is ready. "
            . (($result["data"]["balance_due"] ?? 0) > 0 ? "Please pay your balance in My Orders." : "We'll ship it soon.");
    } else {
        $notify = "New photo for Order #" . $order_id . ". Is it good?";
    }

    figurify_notify_user($conn, $custId, $order_id, $notify, 0, null, false);

    figurify_email_progress_update(
        $conn,
        $custId,
        (int) $order_id,
        __DIR__ . "/../" . $imagePath,
        (string) $message,
        $isRevision,
        $isFinal,
        (int) ($result["data"]["update_id"] ?? 0)
    );

    figurify_notify_staff(
        $conn,
        $order_id,
        $isFinal
            ? ("Final photo sent for order " . $order_id . ".")
            : ($isRevision
                ? ("Revised photo sent for order " . $order_id . ". It is now waiting for the balance payment.")
                : ("Progress update sent for order " . $order_id . ", waiting for the customer.")),
        (int) $_SESSION["user_id"]
    );

    figurify_action_done($conn, $backUrl, $isFinal
        ? "Final photo sent for order " . $order_id . "."
        : "Progress update sent for order " . $order_id . ".");
}


/* ACTION: accept_revision  (form: staff/owner active-booking.php)
   STAFF/OWNER - REVISION_REQUESTED -> REVISION_IN_PROGRESS.
   Nasa production pa rin: ipapadala muna ang revised photo bago ang balance / Ready to Ship. */
function figurify_order_action_accept_revision(mysqli $conn, string $figurifyActorLabel): void
{
    $order_id  = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
    $update_id = isset($_POST["update_id"]) ? (int) $_POST["update_id"] : 0;
    $backUrl   = "active-booking.php?order_id=" . $order_id;

    figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

    $result = figurify_wf_perform($conn, $order_id, "accept_revision", ["update_id" => $update_id]);

    if (!$result["ok"]) {
        figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($result["error"]));
    }

    figurify_customer_notice(
        $conn,
        (int) $result["order"]["user_id"],
        $order_id,
        "Your revision request for Order #" . $order_id . " was accepted. We're working on it now.",
        "Your revision request for Order #" . $order_id . " has been accepted",
        [
            "Your revision request for <strong>Order #" . $order_id . "</strong> is accepted.",
            "We’ll send the revised photo once it’s done, so you can check the changes before we ship.",
            figurify_email_status_line(),
        ]
    );

    figurify_notify_staff($conn, $order_id, "Revision for order " . $order_id . " was accepted by " . $figurifyActorLabel . ".", (int) $_SESSION["user_id"]);

    figurify_action_done($conn, $backUrl, "Revision accepted for order " . $order_id . ".");
}


/* ACTION: decline_revision  (form: staff/owner active-booking.php)
   STAFF/OWNER - REVISION_REQUESTED -> PROCESSING (may dahilan).
   Nagamit na ang iisang revision ng customer (hindi ibinabalik). */
function figurify_order_action_decline_revision(mysqli $conn, string $figurifyActorLabel): void
{
    $order_id  = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
    $update_id = isset($_POST["update_id"]) ? (int) $_POST["update_id"] : 0;
    $backUrl   = "active-booking.php?order_id=" . $order_id;

    figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

    $labels = [
        "unable_to_replicate"        => "Unable to Match the Requested Change",
        "requires_additional_cost"   => "Requires Additional Cost",
        "beyond_material_capability" => "Beyond Our Material/Process Capability",
        "would_delay_completion"     => "Would Significantly Delay Completion",
    ];

    $key    = trim((string) ($_POST["decline_reason"] ?? ""));
    $other  = mb_substr(trim((string) ($_POST["decline_reason_other"] ?? "")), 0, 190);
    $reason = ($key === "other") ? $other : ($labels[$key] ?? "");

    $result = figurify_wf_perform($conn, $order_id, "decline_revision", ["update_id" => $update_id, "reason" => $reason]);

    if (!$result["ok"]) {
        figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($result["error"]));
    }

    figurify_customer_notice(
        $conn,
        (int) $result["order"]["user_id"],
        $order_id,
        "We can't do the revision for Order #" . $order_id . ": " . $reason . ". We'll continue with your order.",
        "About your revision request for Order #" . $order_id,
        [
            "Sorry, we can’t do the revision for <strong>Order #" . $order_id . "</strong>.",
            "<strong>Reason:</strong> " . figurify_email_e($reason),
            "We’ll continue your order and send the next photo.",
            figurify_email_status_line(),
        ]
    );

    figurify_notify_staff($conn, $order_id, "Revision for order " . $order_id . " was declined by " . $figurifyActorLabel . ".", (int) $_SESSION["user_id"]);

    figurify_action_done($conn, $backUrl, "Revision declined for order " . $order_id . ".");
}


/* ACTION: update_active_order  (forms: staff/owner active-booking.php at shipments.php)
   STAFF/OWNER - actions sa Active Booking at Shipments. Apat lang ang pinapayagan:
   - request_balance / finalize: CUSTOMER_APPROVED -> AWAITING_BALANCE (o READY_TO_SHIP kung bayad na lahat)
       ang balance ay galing sa quotation (total - 50% down payment)
   - ship_out: READY_TO_SHIP -> SHIPPED (tracking number, shipping fee, at optional na resibo ng courier;
     ang resibo at shipping fee ay kasama sa Gmail ng customer). Ang customer ang pipindot ng
     "Order Received"; kung hindi, kusang COMPLETED pagkalipas ng 7 araw.
   - complete_order: SHIPPED -> COMPLETED (Mark as Completed, kapag kumpirmadong natanggap na)
   - return_address: READY_TO_SHIP -> ADDRESS_RETURNED (hindi mahanap ng courier ang address;
     aayusin ng customer sa My Orders, saka babalik sa Ready to Ship)
   Kahit anong ibang action (direktang pagpapalit ng status) ay HINAHARANG at nilo-log
   bilang "unauthorized_attempt" sa activity log. */
function figurify_order_action_update_active_order(mysqli $conn, string $figurifyActorLabel): void
{
    $order_id   = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
    $action     = strtolower(trim((string) ($_POST["action"] ?? "")));
    $redirectTo = strtolower(trim((string) ($_POST["redirect_to"] ?? "active-booking")));

    $backUrl = (($redirectTo === "shipments") ? "shipments.php" : "active-booking.php") . "?order_id=" . $order_id;

    figurify_wf_require_post_csrf($conn, $backUrl, $order_id);

    $actionMap = [
        "request_balance" => "finalize_order",
        "finalize"        => "finalize_order",
        "ship_out"        => "ship_order",
        "complete_order"  => "complete_order",
        "return_address"  => "return_shipping_details",
    ];

    if (!isset($actionMap[$action])) {

        figurify_wf_log_activity_row(
            $conn,
            figurify_wf_session_actor(),
            "unauthorized_attempt",
            "Blocked unsupported action \"" . mb_substr($action, 0, 40) . "\""
                . (isset($_POST["status"]) ? (" with status \"" . mb_substr((string) $_POST["status"], 0, 40) . "\"") : "")
                . " via update_active_order.php",
            $order_id > 0 ? $order_id : null
        );

        figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode("Direct status changes are not allowed."));
    }

    $wfAction = $actionMap[$action];

    $params = [];

    if ($wfAction === "ship_order") {
        $params = [
            "tracking_number" => $_POST["tracking_number"] ?? "",
            "shipping_fee"    => $_POST["shipping_fee"] ?? "",
        ];
    }

    if ($wfAction === "return_shipping_details") {
        $reasonKey = (string) ($_POST["return_reason"] ?? "");
        $params    = ["reason" => FIGURIFY_RETURN_ADDRESS_REASONS[$reasonKey] ?? ""];
    }

    /* resibo ng courier (optional) sa Ship Out */
    $shipReceiptPath = null;

    if ($wfAction === "ship_order" && isset($_FILES["shipping_receipt"]) && ($_FILES["shipping_receipt"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {

        [$shipReceiptPath, $shipReceiptError] = figurify_save_uploaded_image(
            $_FILES["shipping_receipt"],
            "uploads/shipping_receipts",
            "receipt_" . $order_id
        );

        if ($shipReceiptPath === null) {
            figurify_action_done($conn, $backUrl, $shipReceiptError ?: "Please upload a valid receipt photo.", "error");
        }
    }

    $result = figurify_wf_perform($conn, $order_id, $wfAction, $params);

    if (!$result["ok"] && $shipReceiptPath !== null) {
        figurify_delete_upload($shipReceiptPath);
        $shipReceiptPath = null;
    }

    if ($result["ok"] && $shipReceiptPath !== null) {

        /* idagdag ang column kung wala pa (best-effort), saka i-save ang path */
        $saveReceipt = function () use ($conn, $shipReceiptPath, $order_id): bool {
            try {
                $stmt = $conn->prepare("UPDATE orders SET shipping_receipt = ? WHERE order_id = ?");
                if (!$stmt) {
                    return false;
                }
                $stmt->bind_param("si", $shipReceiptPath, $order_id);
                $ok = $stmt->execute();
                $stmt->close();
                return $ok;
            } catch (Throwable $e) {
                return false;
            }
        };

        if (!$saveReceipt()) {
            try {
                $conn->query("ALTER TABLE orders ADD COLUMN shipping_receipt VARCHAR(255) NULL AFTER tracking_number");
            } catch (Throwable $e) {
                /* baka meron na - itutuloy pa rin */
            }
            $saveReceipt();
        }
    }

    if (!$result["ok"]) {
        // Shipments page ay walang progress_error display - flash ang gamit doon
        if ($redirectTo === "shipments") {
            figurify_action_done($conn, $backUrl, $result["error"], "error");
        }
        figurify_action_done($conn, $backUrl . "&progress_error=" . urlencode($result["error"]));
    }

    $customerId = (int) $result["order"]["user_id"];

    if ($wfAction === "finalize_order") {

        if ($result["to_status"] === "awaiting_balance") {

            $balance = (float) $result["data"]["balance_due"];

            figurify_customer_notice(
                $conn,
                $customerId,
                $order_id,
                "Order #" . $order_id . " is finalized. Please pay the remaining balance of ₱" . number_format($balance, 2) . " in My Orders.",
                "Remaining balance for your Order #" . $order_id,
                [
                    "<strong>Order #" . $order_id . "</strong> is finalized.",
                    "<strong>Remaining Balance:</strong> ₱" . number_format($balance, 2),
                    "Go to <strong>My Orders</strong>, click <strong>Pay Balance</strong>, and upload your proof. We ship once it’s approved.",
                ]
            );

            figurify_notify_staff($conn, $order_id, "Order #" . $order_id . " was finalized and is waiting for the customer's balance payment.", (int) $_SESSION["user_id"]);

            figurify_action_done($conn, $backUrl, "Order #" . $order_id . " finalized. Balance of ₱" . number_format($balance, 2) . " requested.");
        }

        figurify_customer_notice(
            $conn,
            $customerId,
            $order_id,
            "Order #" . $order_id . " is finalized and ready to ship.",
            "Your Order #" . $order_id . " is ready to ship",
            [
                "<strong>Order #" . $order_id . "</strong> is ready to ship.",
                "We’ll email your tracking number once it ships.",
                figurify_email_status_line(),
            ]
        );
        figurify_notify_staff($conn, $order_id, "Order #" . $order_id . " is ready to ship.", (int) $_SESSION["user_id"]);

        figurify_action_done($conn, $backUrl, "Order #" . $order_id . " finalized and ready to ship.");
    }

    if ($wfAction === "return_shipping_details") {

        figurify_customer_notice(
            $conn,
            $customerId,
            $order_id,
            "We couldn't ship Order #" . $order_id . ": " . $params["reason"] . ". Please update your shipping details in My Orders.",
            "Please update the shipping details of Order #" . $order_id,
            [
                "We couldn't ship <strong>Order #" . $order_id . "</strong> yet.",
                "<strong>Reason:</strong> " . figurify_email_e($params["reason"]),
                "Go to <strong>My Orders</strong> and click <strong>Update Shipping Details</strong>. We will ship it once the details are fixed.",
                figurify_email_status_line(),
            ]
        );
        figurify_notify_staff($conn, $order_id, "Order #" . $order_id . " was returned to the customer to fix the shipping details.", (int) $_SESSION["user_id"]);

        figurify_action_done($conn, $backUrl, "Order #" . $order_id . " was returned to the customer to fix the shipping details.");
    }

    if ($wfAction === "ship_order") {

        $tracking = trim((string) $params["tracking_number"]);

        $courierRow = $conn->query("SELECT courier FROM orders WHERE order_id = " . (int) $order_id)->fetch_assoc();
        $courierName = strtoupper(trim((string) ($courierRow["courier"] ?? "")));

        $shipFee = number_format((float) $params["shipping_fee"], 2);

        /* resibo ng courier: naka-embed sa Gmail (kung may in-upload) */
        $receiptImage  = "";
        $receiptEmbeds = [];

        if ($shipReceiptPath !== null) {
            $receiptImage  = '<img src="cid:shipreceipt" alt="Shipping Receipt" class="cs-mail-photo">';
            $receiptEmbeds = [["path" => __DIR__ . "/../" . $shipReceiptPath, "cid" => "shipreceipt"]];
        }

        figurify_customer_notice(
            $conn,
            $customerId,
            $order_id,
            "Order #" . $order_id . " has been shipped! Tracking number: " . $tracking . ". Shipping fee: ₱" . $shipFee . ".",
            "Your Order #" . $order_id . " has been shipped",
            [
                "<strong>Order #" . $order_id . "</strong> is on the way.",
                ($courierName !== "" ? "<strong>Courier:</strong> " . figurify_email_e($courierName) . "<br>" : "")
                    . "<strong>Tracking Number:</strong> " . figurify_email_e($tracking) . "<br>"
                    . "<strong>Shipping Fee:</strong> ₱" . $shipFee,
                $receiptImage !== "" ? "<strong>Shipping Receipt:</strong>" : "",
                $receiptImage,
                "When it arrives, click <strong>Order Received</strong> in <strong>My Orders</strong>. It completes automatically after " . FIGURIFY_AUTO_RECEIVE_DAYS . " days.",
                figurify_email_status_line(),
            ],
            $receiptEmbeds
        );

        figurify_notify_staff($conn, $order_id, "Order #" . $order_id . " was shipped out.", (int) $_SESSION["user_id"]);

        figurify_action_done($conn, $backUrl, "Order #" . $order_id . " shipped. The customer was notified.");
    }

    if ($wfAction === "complete_order") {

        /* review link (Gmail + bell) - kapareho ng Order Received ng customer */
        figurify_order_completed_notice($conn, $order_id, $customerId, "staff", (int) $_SESSION["user_id"]);

        figurify_notify_staff($conn, $order_id, "Order #" . $order_id . " was marked as completed by " . $figurifyActorLabel . ".", (int) $_SESSION["user_id"]);

        figurify_action_done($conn, $backUrl, "Order #" . $order_id . " marked as completed. The customer was asked for a review.");
    }
}


/* =====================================================================
   SECTION 4 - RUN
   Tinatawag ang action na hiniling ng wrapper (owner/ o staff/ file).
   ===================================================================== */

$figurifyHandler = $figurifyActionHandlers[$figurifyOrderAction];
$figurifyHandler($conn, $figurifyActorLabel);
