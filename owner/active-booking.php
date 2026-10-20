<?php

require_once __DIR__ . "/owner-header.php";

$figurifyRole    = "owner";
$figurifyPageDir = __DIR__;

/* shared logic + markup ng owner at staff active-booking.php. Kailangang naka-set na muna ng entry file: $figurifyRole at $figurifyPageDir bago i-require ito. */

require_once __DIR__ . "/../helpers/progress_expire_helper.php";

/* auto-approve any progress update the customer never responded to within 24 hours, BEFORE we pull the list of active orders, para tama agad ang tabs/buttons na makikita sa load na ito */
figurify_autoapprove_stale_progress_updates($conn);
require_once __DIR__ . "/../helpers/order_complete_helper.php";
figurify_autocomplete_shipped_orders($conn);

/* session/role check, $ownerName, e, statusLabel, etc. galing na sa owner-header.php sa itaas - iisang pinagmumulan na lang para pareho lahat ng owner page. */

$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";

/* SAME HELPERS USED IN quotation.php / my-orders.php - para magkatugma ang buong figure details na makikita dito sa Verification (Customer Order tab) at doon. */

function figureHasSize($fig)
{
    return !empty($fig["size_label"]);
}

function hironoBaseBoxPrice($fig)
{
    return (float) $fig["box_addon_price"] - (float) $fig["hirono_blind_items_total"];
}

function figureStyleImage($style)
{
    $map = [
        "Chibi"     => "figures/chibi/chibi-style.jpg",
        "Funko Pop" => "figures/funko-pop/funko-pop-style.jpg",
        "Hirono"    => "figures/hirono/hirono-style.jpg",
    ];

    return $map[$style] ?? null;
}

function productTypeImage($style, $product)
{
    $map = [
        "Chibi" => [
            "Full Body Standee"  => "figures/chibi/chibi-full-body-standee.jpg",
            "Full Body Keychain" => "figures/chibi/chibi-full-body-keychain.jpg",
            "Half Body Keychain" => "figures/chibi/chibi-half-body-keychain.jpg",
            "Head Only Keychain" => "figures/chibi/chibi-head-only-keychain.jpg",
        ],
        "Funko Pop" => [
            "Full Body Standee"  => "figures/funko-pop/funko-pop-full-body-standee.jpg",
            "Full Body Keychain" => "figures/funko-pop/funko-pop-full-body-keychain.jpg",
            "Half Body Keychain" => "figures/funko-pop/funko-pop-half-body-keychain.jpg",
            "Head Only Keychain" => "figures/funko-pop/funko-pop-head-only-keychain.jpg",
        ],
        "Hirono" => [
            "Full Body Standee"  => "figures/hirono/hirono-full-body-standee.jpg",
            "Full Body Keychain" => "figures/hirono/hirono-full-body-keychain.jpg",
            "Half Body Keychain" => "figures/hirono/hirono-half-body-keychain.jpg",
            "Head Only Keychain" => "figures/hirono/hirono-head-only-keychain.jpg",
        ],
    ];

    return $map[$style][$product] ?? null;
}

function hironoBlindTypeImage($blindType)
{
    return ($blindType === "set") ? "boxes/blind-box-set.jpg" : "figures/hirono/hirono-style.jpg";
}

function hironoBoxDesignImage($design)
{
    $map = [
        "checkered"   => "boxes/hirono-box-checkered.jpg",
        "hirono_peek" => "boxes/hirono-box-peek.jpg",
    ];

    return $map[$design] ?? null;
}

function hironoBoxDesignLabel($design)
{
    $labels = [
        "checkered"   => "Checkered",
        "hirono_peek" => "Hirono Peek",
    ];

    return $labels[$design] ?? $design;
}

function hironoBlindItemImage($itemKey)
{
    return "figures/hirono/hirono-style.jpg";
}

function orderMethodLabel($method)
{
    $labels = [
        "reference"    => "Image Submission",
        "create_style" => "Dress Up",
    ];

    return $labels[$method] ?? "Image Submission";
}

function figurify_render_progress_history_block($progressUpdates, $progressError)
{
?>
                    <div class="progress-history-block" style="font-size:12px;">


                        <?php
                        /* pop up na (Shared/all-in-one.php) sa halip na banner - isang beses lang ipapakita */
                        static $figurifyProgressErrorShown = false;
                        if ($progressError !== "" && !$figurifyProgressErrorShown) {
                            $figurifyProgressErrorShown = true;
                            echo figurify_toast_now($progressError, "error");
                        }
                        ?>

                        <?php if (empty($progressUpdates)): ?>

                            <?php /* walang ipapakitang text kapag wala pang progress photo */ ?>

                        <?php else: ?>

                            <div class="progress-history-list">

                                <?php foreach ($progressUpdates as $pu): ?>

                                    <div class="progress-history-item">

                                        <img
                                            src="../<?php echo htmlspecialchars($pu["image_path"]); ?>"
                                            alt="Progress photo"
                                            class="progress-history-thumb"
                                            style="cursor:zoom-in;"
                                            onclick="openImageLightbox('../<?php echo htmlspecialchars($pu["image_path"], ENT_QUOTES); ?>', 'Progress update — <?php echo date("M j, Y", strtotime($pu["created_at"])); ?>')"
                                        >

                                        <div class="progress-history-body">

                                            <?php if (!empty($pu["staff_message"])): ?>
                                                <p class="progress-history-caption" style="font-size:11.5px;">
                                                    <?php echo htmlspecialchars($pu["staff_message"]); ?>
                                                </p>
                                            <?php endif; ?>

                                            <span class="progress-history-date" style="font-size:10.5px;">
                                                Sent <?php echo date("M j, Y — g:i A", strtotime($pu["created_at"])); ?>
                                            </span>

                                            <?php if ($pu["customer_response"] === "pending"): ?>

                                                <span class="progress-status-text is-waiting">Waiting for customer</span>

                                            <?php elseif ($pu["customer_response"] === "approved"): ?>

                                                <span class="progress-status-text is-approved"><?php echo !empty($pu["is_final"]) ? "Final photo — ready to ship" : "Customer approved"; ?></span>

                                                <?php if (!empty($pu["auto_approved"])): ?>
                                                    <p class="progress-history-revision-note" style="font-size:10.5px;">
                                                        Auto-approved — customer didn't respond within 24 hours
                                                    </p>
                                                <?php endif; ?>

                                            <?php elseif ($pu["customer_response"] === "in_progress"): ?>

                                                <span class="progress-status-text is-progress">Revision accepted — in progress</span>

                                                <?php if (!empty($pu["revision_note"])): ?>
                                                    <p class="progress-history-revision-note progress-history-revision-request">
                                                        <strong>Revision request</strong>
                                                        “<?php echo nl2br(htmlspecialchars($pu["revision_note"])); ?>”
                                                    </p>
                                                <?php endif; ?>

                                            <?php elseif ($pu["customer_response"] === "declined"): ?>

                                                <span class="progress-status-text is-declined">Revision declined</span>

                                                <?php if (!empty($pu["revision_note"])): ?>
                                                    <p class="progress-history-revision-note progress-history-revision-request">
                                                        <strong>Revision request</strong>
                                                        “<?php echo nl2br(htmlspecialchars($pu["revision_note"])); ?>”
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (!empty($pu["staff_reply"])): ?>
                                                    <p class="progress-history-revision-note progress-history-revision-request">
                                                        <strong>Our reply:</strong>
                                                        <?php echo nl2br(htmlspecialchars($pu["staff_reply"])); ?>
                                                    </p>
                                                <?php endif; ?>

                                            <?php else: ?>

                                                <?php if (!empty($pu["revision_note"])): ?>
                                                    <p class="progress-history-revision-note progress-history-revision-request">
                                                        <strong>Revision request</strong>
                                                        “<?php echo nl2br(htmlspecialchars($pu["revision_note"])); ?>”
                                                    </p>
                                                <?php endif; ?>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>
<?php
}



/* get only the active orders (already approved, ginagawa na) - pwede i-Ship Out o i-update ang status dito */

$orders = [];

/* try muna kasama ang tracking_number/shipping_fee/balance_to_pay columns - kung wala pa yang columns sa DB mo, mag-tthrow ng exception ang prepare (di lang false), kaya try/catch ito */
$ordersHaveShippingColumns = true;

try {

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.order_method, o.booking_date, o.rush_fee,
                o.subtotal, o.total_amount, o.status, o.created_at,
                o.shipping_name, o.shipping_address, o.shipping_contact, o.courier,
                o.tracking_number, o.shipping_fee, o.balance_to_pay, o.shipped_at,
                o.payment_reference, o.payment_proof, o.paid_at,
                o.refund_account_name, o.refund_account_number, o.refund_method,
                u.full_name, u.email
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         WHERE LOWER(o.status) IN ('processing', 'on process', 'active', 'paid', 'approved', 'awaiting_balance', 'to_verify_balance', 'for_balance_approval', 'to_ship', 'shipped')
         ORDER BY o.booking_date ASC, o.created_at ASC"
    );

    if ($stmt === false) {
        throw new \mysqli_sql_exception("prepare() returned false");
    }

} catch (\Throwable $e) {

    $ordersHaveShippingColumns = false;

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.order_method, o.booking_date, o.rush_fee,
                o.subtotal, o.total_amount, o.status, o.created_at,
                o.shipping_name, o.shipping_address, o.shipping_contact, o.courier,
                o.payment_reference, o.payment_proof, o.paid_at,
                o.refund_account_name, o.refund_account_number, o.refund_method,
                u.full_name, u.email
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         WHERE LOWER(o.status) IN ('processing', 'on process', 'active', 'paid', 'approved', 'awaiting_balance', 'to_verify_balance', 'for_balance_approval', 'to_ship', 'shipped')
         ORDER BY o.booking_date ASC, o.created_at ASC"
    );

}

$stmt->execute();

$ordersResult = $stmt->get_result();

while ($row = $ordersResult->fetch_assoc()) {

    if (!$ordersHaveShippingColumns) {
        $row["tracking_number"] = null;
        $row["shipping_fee"]    = null;
        $row["balance_to_pay"]  = null;
        $row["shipped_at"]      = null;
    }

    $orders[] = $row;
}

$stmt->close();


/* GET FIGURES + IMAGES FOR EACH ORDER (para makita rin ng owner kung ano talaga ang inaprubahan bago i-verify ang bayad) */

foreach ($orders as &$order) {

    $fstmt = $conn->prepare(
        "SELECT figure_id, figure_style, product_type, size_label,
                figure_name, notes, product_price, name_fee, figure_total,
                box_addon_type, box_addon_price,
                funko_box_type, funko_box_name, funko_box_number, funko_box_color,
                hirono_blind_type, hirono_box_design, hirono_box_color, hirono_letter,
                hirono_nickname, hirono_date, hirono_blind_items, hirono_blind_items_total,
                quoted_price
         FROM order_figures
         WHERE order_id = ?"
    );

    /* fallback kung wala pang hirono_box_design/hirono_blind_items columns - bumalik na lang sa mas lumang query para di mag-crash ang buong page */

    $figuresHaveExtendedHironoColumns = ($fstmt !== false);

    if (!$figuresHaveExtendedHironoColumns) {

        $fstmt = $conn->prepare(
            "SELECT figure_id, figure_style, product_type, size_label,
                    figure_name, notes, product_price, name_fee, figure_total,
                    box_addon_type, box_addon_price,
                    funko_box_type, funko_box_name, funko_box_number, funko_box_color,
                    hirono_blind_type, hirono_box_color, hirono_letter,
                    hirono_nickname, hirono_date, quoted_price
             FROM order_figures
             WHERE order_id = ?"
        );

    }

    if ($fstmt === false) {
        $order["figures"] = [];
        continue;
    }

    $fstmt->bind_param("i", $order["order_id"]);
    $fstmt->execute();

    $fresult = $fstmt->get_result();

    $figures = [];

    while ($frow = $fresult->fetch_assoc()) {

        /* kung fallback query ang nagamit, punan na lang ng default values para di na kailangang i-check paulit-ulit sa HTML */
        if (!$figuresHaveExtendedHironoColumns) {
            $frow["hirono_box_design"]         = null;
            $frow["hirono_blind_items"]        = null;
            $frow["hirono_blind_items_total"]  = 0;
        }

        $imgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_images
             WHERE figure_id = ?"
        );

        $images = [];

        if ($imgStmt !== false) {

            $imgStmt->bind_param("i", $frow["figure_id"]);
            $imgStmt->execute();

            $imgResult = $imgStmt->get_result();

            while ($irow = $imgResult->fetch_assoc()) {
                $images[] = $irow["image_path"];
            }

            $imgStmt->close();

        }

        $frow["images"] = $images;


        /* BOX REFERENCE IMAGES (Hirono Blind Box only) - kung wala pang order_figure_box_images table sa database mo, mabibigo lang itong query nang tahimik at mananatiling walang box images (hindi babagsak ang buong page dahil dito). */

        $boxImages = [];

        $boxImgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_box_images
             WHERE figure_id = ?"
        );

        if ($boxImgStmt !== false) {

            $boxImgStmt->bind_param("i", $frow["figure_id"]);
            $boxImgStmt->execute();

            $boxImgResult = $boxImgStmt->get_result();

            while ($birow = $boxImgResult->fetch_assoc()) {
                $boxImages[] = $birow["image_path"];
            }

            $boxImgStmt->close();

        }

        $frow["box_images"] = $boxImages;


        /* Optional Hirono Blind Box extras (Tear Blind Paper / Pouch / Digital Art), saved as a JSON object of { item_key: price }. Decode it into a simple list of [label, price] pairs for display. */

        $blindItemLabels = [
            "tear_blind_paper" => "Tear Blind Paper",
            "pouch"             => "Pouch",
            "digital_art"       => "Digital Art (Soft Copy) w/ Photo Card",
        ];

        $frow["blind_items_list"] = [];

        if (!empty($frow["hirono_blind_items"])) {

            $decodedItems = json_decode($frow["hirono_blind_items"], true);

            if (is_array($decodedItems)) {

                foreach ($decodedItems as $itemKey => $itemPrice) {

                    $frow["blind_items_list"][] = [
                        "label" => $blindItemLabels[$itemKey] ?? ucwords(str_replace("_", " ", $itemKey)),
                        "price" => (float) $itemPrice,
                        "image" => hironoBlindItemImage($itemKey),
                    ];

                }

            }

        }

        $figures[] = $frow;
    }

    $fstmt->close();

    $order["figures"] = $figures;
}

unset($order);


/* may pending na revision request ba? (base sa pinakahuling progress update) */

foreach ($orders as &$order) {

    $order["has_revision"] = false;

    /* "To Ship" check - actual o.status column na ito, di na guess base sa last progress update */
    $order["is_to_ship"] = (strtolower(trim((string) $order["status"])) === "to_ship");

    /* "Shipped" na ba ito? - ganito rin, base sa totoong column value (o.status = 'shipped'), na itinatakda sa sandaling i-Ship Out ng owner gamit ang bagong popup (Tracking Number / Shipping Fee / Balance to Pay). */
    $order["is_shipped"] = (strtolower(trim((string) $order["status"])) === "shipped");

    if (!$order["is_to_ship"] && !$order["is_shipped"]) {

        $latestStmt = $conn->prepare(
            "SELECT customer_response
             FROM order_progress_updates
             WHERE order_id = ?
             ORDER BY created_at DESC
             LIMIT 1"
        );

        if ($latestStmt !== false) {

            $latestStmt->bind_param("i", $order["order_id"]);
            $latestStmt->execute();

            $latestRow = $latestStmt->get_result()->fetch_assoc();
            $latestStmt->close();

            if ($latestRow && $latestRow["customer_response"] === "revision") {
                $order["has_revision"] = true;
            }

        }

    }

}

unset($order);


/* PROCESSING / REVISION / TO SHIP TAB FILTER */

$activeFilter = isset($_GET["afilter"]) ? strtolower(trim($_GET["afilter"])) : "all";

if (!in_array($activeFilter, ["all", "processing", "revision", "to_ship", "shipped"], true)) {
    $activeFilter = "all";
}

/* 3 column layout: order list / order info at figure details / action buttons */
$useVerificationLayout = true;

$requestedActiveOrderId = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;

$revisionCount   = 0;
$processingCount = 0;

foreach ($orders as $countOrder) {
    if ($countOrder["is_shipped"] || $countOrder["is_to_ship"]) {
        continue; 
    } elseif ($countOrder["has_revision"]) {
        $revisionCount++;
    } else {
        $processingCount++;
    }
}

$visibleOrders = array_values(array_filter(
    $orders,
    function ($order) use ($activeFilter, $requestedActiveOrderId) {

        // laging ipakita ang partikular na order na hiniling
        // sa URL, kahit anong tab ang naka-active
        if ($requestedActiveOrderId > 0 && (int) $order["order_id"] === $requestedActiveOrderId) {
            return true;
        }

        if ($activeFilter === "all") {
            // "All" dito ay Processing + Revision lang (hindi kasama
            // ang To Ship / Shipped, dahil may sarili na silang
            // Shipments page).
            return !$order["is_to_ship"] && !$order["is_shipped"];
        }

        if ($activeFilter === "shipped") {
            return $order["is_shipped"];
        }

        if ($activeFilter === "to_ship") {
            return $order["is_to_ship"];
        }

        if ($activeFilter === "revision") {
            return $order["has_revision"] && !$order["is_to_ship"] && !$order["is_shipped"];
        }

        return !$order["has_revision"] && !$order["is_to_ship"] && !$order["is_shipped"];
    }
));


/* WHICH ORDER IS CURRENTLY OPEN ON THE RIGHT SIDE */

/* Wala nang AUTO-SELECT ng unang order sa listahan, parehong pattern ng Quotation/Verification pages. Dapat i-click muna ang isang row bago lumabas ang detail sa right side. */
$selectedOrderId = isset($_GET["order_id"])
    ? (int) $_GET["order_id"]
    : 0;

$selectedOrder = null;

foreach ($orders as $order) {

    if ((int) $order["order_id"] === $selectedOrderId) {
        $selectedOrder = $order;
        break;
    }
}


/* PROGRESS UPDATE HISTORY FOR THE SELECTED ORDER (mga photo update na na-send na, at kung paano na-sagot ng customer - Pending / Approved / Revision + note) */

$progressUpdates = [];
$progressError    = $_GET["progress_error"] ?? "";

if ($selectedOrderId > 0) {

    $progStmt = $conn->prepare(
        "SELECT update_id, image_path, staff_message, customer_response,
                revision_note, staff_reply, is_final, auto_approved,
                created_at, responded_at
         FROM order_progress_updates
         WHERE order_id = ?
         ORDER BY created_at DESC, update_id DESC"
    );

    /* kung wala pang "auto_approved" column (bago pa lang idagdag ang migration), bumalik sa lumang query para hindi babagsak ang buong page - auto_approved na lang ang mawawala */
    if ($progStmt === false) {

        $progStmt = $conn->prepare(
            "SELECT update_id, image_path, staff_message, customer_response,
                    revision_note, staff_reply, is_final, created_at, responded_at
             FROM order_progress_updates
             WHERE order_id = ?
             ORDER BY created_at DESC, update_id DESC"
        );

    }

    if ($progStmt) {

        $progStmt->bind_param("i", $selectedOrderId);
        $progStmt->execute();

        $progResult = $progStmt->get_result();

        while ($progRow = $progResult->fetch_assoc()) {

            if (!array_key_exists("auto_approved", $progRow)) {
                $progRow["auto_approved"] = 0;
            }

            $progressUpdates[] = $progRow;

        }

        $progStmt->close();

    }

}

$latestRevisionUpdateId = 0;
$latestResponse         = !empty($progressUpdates) ? $progressUpdates[0]["customer_response"] : null;
$latestIsFinal          = !empty($progressUpdates) ? !empty($progressUpdates[0]["is_final"]) : false;

if ($latestResponse === "revision") {
    $latestRevisionUpdateId = (int) $progressUpdates[0]["update_id"];
}

// Kung ang order na ito ay "to_ship" o "shipped" na, tapos na
// ang lahat ng photo approvals - huwag na ulit ipakita ang
// Send Update / To Ship (quick) buttons kahit ano pa ang laman
// ng pinakahuling progress update.
$selectedOrderStatus     = $selectedOrder ? strtolower(trim((string) $selectedOrder["status"])) : "";
$isAlreadyToShipOrBeyond = in_array($selectedOrderStatus, ["to_ship", "shipped"], true);

// Send Update / Accept / Decline Revision - base sa pinakahuling progress update.
// Hindi pwedeng mag-send ulit habang hindi pa sumasagot ang customer.
$canSendUpdate      = !in_array($latestResponse, ["revision", "pending"], true) && !$latestIsFinal && !$isAlreadyToShipOrBeyond;
$awaitingCustomerResponse = ($latestResponse === "pending");
$awaitingRevisionDecision = ($latestResponse === "revision");

// Kapag "approved" (Good) o "in_progress" (na-accept na yung
// revision) ang latest, ang SUSUNOD na Send Update ay yung
// FINAL/tapos na larawan na - dito na dapat awtomatikong
// "approved" (walang Good/Revision popup sa customer, note na
// lang at email).
$autoFinalUpdate = in_array($latestResponse, ["approved", "in_progress"], true);

// "TO SHIP" (QUICK BUTTON) - lalabas sa tabi ng "Send Update"
// sa sandaling "Good"/approved na ang order o na-accept na ang
// revision, para hindi na kailangang magpadala pa ng final na
// larawan bago ito lumipat sa "to_ship". Nawawala ito kapag
// wala nang Send Update na ipapakita (hal. to_ship/shipped na).
$canMarkToShip = $autoFinalUpdate && $canSendUpdate;

// Ship Out - base sa status ng order (to_ship). Walang booking-date lock.

$canShipOut = ($selectedOrderStatus === "to_ship");

/* WORKFLOW-BASED ACTIONS - ang ipinapakitang buttons ay galing sa
   transition map (helpers/order_workflow.php). UI convenience lang ito;
   ang backend pa rin ang nagpapasya sa bawat POST. */

$wfState        = "";
$wfAllowed      = [];
$wfRevisionUsed = 0;
$wfBalanceDue   = 0.0;
$wfBalancePaid  = false;
$wfShipChecks   = [];

if ($selectedOrder) {

    $wfLatestRow = !empty($progressUpdates) ? $progressUpdates[0] : null;
    $wfState     = figurify_wf_canonical_from($selectedOrder["status"], $wfLatestRow);
    $wfAllowed   = figurify_wf_allowed_actions($wfState, ($figurifyRole === "owner") ? FIGURIFY_WF_OWNER : FIGURIFY_WF_STAFF);

    $wfFullStmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $wfSelId    = (int) $selectedOrder["order_id"];
    $wfFullStmt->bind_param("i", $wfSelId);
    $wfFullStmt->execute();
    $wfFullOrder = $wfFullStmt->get_result()->fetch_assoc() ?: [];
    $wfFullStmt->close();

    $wfRevisionUsed = (int) ($wfFullOrder["revision_count"] ?? 0);
    $wfBalanceDue   = figurify_wf_balance_amount($wfFullOrder);
    $wfBalancePaid  = ($wfBalanceDue <= 0) || !empty($wfFullOrder["balance_paid_at"]);
    $wfShipChecks   = figurify_wf_shipping_checklist($conn, $wfFullOrder, $wfLatestRow);
}

$wfHasFinalPhoto = false;

foreach ($progressUpdates as $wfPu) {
    if (!empty($wfPu["is_final"])) {
        $wfHasFinalPhoto = true;
        break;
    }
}

// Send Update: normal progress (PROCESSING / REVISION_IN_PROGRESS), o
// final photo kapag Ready to Ship na (impormasyon lang, walang status change)
$autoFinalUpdate          = ($wfState === "READY_TO_SHIP");
$canSendUpdate            = in_array("submit_progress", $wfAllowed, true)
                            || ($autoFinalUpdate && in_array("submit_final_photo", $wfAllowed, true) && !$wfHasFinalPhoto && $wfBalancePaid);
/* kapag naipadala na ang revised photo (pagkatapos ng na-accept
   na revision), wala nang Send Update - kahit ang "final photo". */
$wfRevisedPhotoSent = false;
foreach ($progressUpdates as $wfPuIndex => $wfPu) {
    if ($wfPuIndex > 0 && in_array(($wfPu["customer_response"] ?? ""), ["in_progress", "declined"], true)) {
        $wfRevisedPhotoSent = true;
        break;
    }
}
if ($wfRevisedPhotoSent) {
    $canSendUpdate = false;
}

$awaitingCustomerResponse = ($wfState === "PROGRESS_SUBMITTED");
$awaitingRevisionDecision = ($wfState === "REVISION_REQUESTED");

// "Finalize" - para lang sa lumang naka-approve pero "processing" pa, o
// lumang to_ship na hindi pa nababayaran ang balance. System ang nagko-compute.
$canMarkToShip = in_array("finalize_order", $wfAllowed, true)
    && ($wfState === "CUSTOMER_APPROVED" || !$wfBalancePaid);

$canShipOut = in_array("ship_order", $wfAllowed, true) && $wfBalancePaid;


$conn->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Figurify — Active Bookings</title>

<style>
/* one <style> block na lang para sa page na ito - dating hiwalay na 2 css files (active-booking-styles.css + owner.css) pinagsama na dito */

/* active-booking's own stylesheet - hiwalay sa ibang pages kahit magkatulad itsura, para di masira ang iba pag ni-edit. Merged from my-orders.css, owner/orders.css, owner/quotation.css, owner/progress-update.css. */

/* [1] FROM my-order/my-orders.css */
/* CLAY AND STUFF - MY ORDERS (Redesigned UI - same data/flow, new layout & look, based on the approved order_preview_updated_v3.html) */

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    
    color:#75445e;

    


    line-height:1.5;
}


/* PAGE / HEADER */

.orders-page{
    width:min(1400px,95%);
    margin:auto;
    padding:6px 0 24px;
}

.orders-header{
    margin-bottom:10px;
    flex-shrink:0;
}

.orders-header h1{
    color:#86365f;
    
    font-size:clamp(26px,4vw,34px);
}

.orders-header p{
    margin-top:6px;
    color:#956d80;
    font-size:13px;
}


/* TOP-OF-PAGE NOTICE BANNER */

.order-notice{
    margin-bottom:20px;
    padding:12px 16px;
    border-radius:14px;
    font-size:12px;
    font-weight:800;
}

.order-notice-success{
    background:#e3f6ea;
    color:#2f6b46;
    border:1px solid #bfe4cc;
}

.order-notice-cancel{
    background:#fdeaea;
    color:#a3403f;
    border:1px solid #f3c6c6;
}


/* NO ORDERS */

.no-orders{
    text-align:center;
    padding:60px 20px;
    background:rgba(255,255,255,.92);
    border:2px solid white;
    outline:1px solid #e4d5df;
    border-radius:22px;
    box-shadow:5px 7px 0 rgba(216,194,222,.3);
}

.no-orders-icon{
    font-size:42px;
    margin-bottom:10px;
}

.no-orders p{
    color:#956d80;
    font-size:13px;
    margin-bottom:14px;
}

.no-orders a{
    display:inline-block;
    padding:12px 26px;
    border-radius:20px;
    background:linear-gradient(135deg,#f2699b,#e0447f);
    color:white;
    text-decoration:none;
    font-size:11px;
    font-weight:900;
    box-shadow:0 6px 14px rgba(224,68,127,.35);
    transition:.15s;
}

.no-orders a:hover{
    filter:brightness(1.05);
    transform:translateY(-2px);
}


/* MAIN LAYOUT - FAR LEFT LIST / EVERYTHING ELSE */

.orders-layout{
    display:grid;
    grid-template-columns:270px 1fr;
    gap:22px;
    align-items:start;
}

@media (max-width:800px){

    .orders-layout{
        grid-template-columns:1fr;
    }

    .orders-list-col{
        position:static;
    }

}


/* page scroll - normal page scroll na (di na no-scroll shell) para maabot ang footer. List at summary sticky pa rin. */


/* FAR LEFT: ORDER LIST (sidebar) */

.orders-list-col{
    position:sticky;
    top:98px; /* 78px navbar clearance + 20px breathing room, para di matakpan ng navbar */
}

.orders-list-shell{
    background:rgba(255,255,255,.92);
    border:2px solid white;
    outline:1px solid #e4d5df;
    border-radius:22px;
    box-shadow:5px 7px 0 rgba(216,194,222,.3);
    padding:18px 14px 14px;
}

.orders-list-scroll{
    display:flex;
    flex-direction:column;
    gap:12px;
    max-height:calc(100vh - 228px); /* 150px + 78px para sa navbar */
    overflow-y:auto;
    padding:3px 4px 5px 2px;
    scrollbar-width:thin;
    scrollbar-color:#f2a4ca transparent;
}

.orders-list-scroll::-webkit-scrollbar{width:6px;}
.orders-list-scroll::-webkit-scrollbar-track{background:transparent;}
.orders-list-scroll::-webkit-scrollbar-thumb{background:#f2a4ca;border-radius:10px;}

.order-list-item{
    display:flex;
    flex-direction:column;
    justify-content:center;
    width:100%;
    text-align:left;
    cursor:pointer;
    
    color:inherit;
    background:rgba(255,255,255,.92);
    border:2px solid white;
    outline:1px solid #e4d5df;
    border-radius:16px;
    padding:12px 14px;
    box-shadow:5px 7px 0 rgba(216,194,222,.25);
    transition:.15s;
}

.order-list-item:hover{
    transform:translateY(-2px);
}

.order-list-item.active{
    border-color:#f2a4ca;
    background:#fff0f7;
    box-shadow:5px 7px 0 rgba(242,164,202,.4);
}

.order-list-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin-bottom:6px;
}

.order-list-id{
    
    font-weight:bold;
    color:#873c63;
    font-size:13px;
}

.order-list-date{
    color:#a17488;
    font-size:9px;
    margin-bottom:4px;
}

.order-list-count{
    color:#a17488;
    font-size:9px;
    margin-top:2px;
}


/* STATUS BADGE COLORS (single source: order_status_helper.php) */

.order-status{
    flex-shrink:0;
    padding:5px 10px;
    border-radius:20px;
    font-size:8px;
    font-weight:900;
    letter-spacing:.4px;
    white-space:nowrap;
}

.status-pending{background: #FFF3C4;color: #29252A;}
.status-quoted{background: #E1F3E7;color: #29252A;}
.status-awaiting_payment{background: #FBE8D6;color: #29252A;}
.status-to_verify{background: #E1F3E7;color: #29252A;}
.status-for_approval{background: #E1F3E7;color: #29252A;}
.status-processing{background: #FBE8D6;color: #29252A;}
.status-awaiting_balance{background: #FBE8D6;color: #29252A;}
.status-to_verify_balance{background: #E1F3E7;color: #29252A;}
.status-for_balance_approval{background: #E1F3E7;color: #29252A;}
.status-to_ship{background: #E8EDFF;color: #29252A;}
.status-shipped{background: #E8EDFF;color: #29252A;}
.status-completed{background: #E1F3E7;color: #29252A;}
.status-cancelled{background: #FADCDC;color: #29252A;}


/* ORDER DETAIL - PLACEHOLDER / PANEL TOGGLE */

.orders-detail-col{
    min-width:0;
}

.order-detail-placeholder{
    display:none;
    text-align:center;
    padding:70px 20px;
    background:rgba(255,255,255,.85);
    border:2px solid white;
    outline:1px solid #e4d5df;
    border-radius:24px;
    box-shadow:5px 7px 0 rgba(216,194,222,.3);
}

.order-detail-placeholder.active{
    display:block;
}

.order-detail-placeholder .no-orders-icon{
    font-size:34px;
}

.order-detail-placeholder p{
    color:#956d80;
    font-size:13px;
}

.order-detail-panel{
    display:none;
}

.order-detail-panel.active{
    display:block;
}


/* ORDER CARD (the panel itself) */

.order-card{
    background:rgba(255,255,255,.95);
    border:3px solid white;
    outline:1px solid #e4d5df;
    border-radius:22px;
    padding:20px;
    box-shadow:5px 7px 0 rgba(216,194,222,.3);
    scroll-margin-top:98px; /* para pag scrollIntoView, di matabunan ng navbar */
}

/* DETAIL GRID - MAIN (order info + figures) / SIDE (summary) */

.detail-grid{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:20px;
    align-items:start;
}

@media (max-width:1100px){
    .detail-grid{
        grid-template-columns:1fr;
    }
}

.detail-main{
    display:flex;
    flex-direction:column;
    gap:20px;
    min-width:0;
}

.detail-side{
    min-width:0;
}

@media (min-width:801px){
    .detail-side{
        position:sticky;
        top:98px; /* pareho ng left order-list para same ang space nila sa navbar */
        align-self:start;
    }
}


/* GENERIC CARD (order info / figure viewer / summary) */

.card{
    background:rgba(255,255,255,.92);
    border:2px solid white;
    outline:1px solid #e4d5df;
    border-radius:20px;
    box-shadow:5px 7px 0 rgba(216,194,222,.25);
    padding:20px;
}


/* ORDER INFORMATION CARD */

.order-info-title{
    color:#d2417a;
    font-weight:bold;
    font-size:13px;
    letter-spacing:.2px;
    margin-bottom:14px;
}

.order-info-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:10px;
}

@media (max-width:900px){
    .order-info-grid{grid-template-columns:repeat(2, minmax(0, 1fr));}
}

@media (max-width:520px){
    .order-info-grid{grid-template-columns:1fr;}
}

.info-chip{
    display:flex;
    align-items:center;
    gap:9px;
    background:#fffaf6;
    border:1px solid #f5e6ee;
    border-radius:12px;
    padding:9px 12px;
    font-size:12.5px;
    min-width:0;
}

.info-chip .icon{
    font-size:16px;
    flex-shrink:0;
}

.info-chip .txt{
    display:flex;
    flex-direction:column;
    min-width:0;
}

.info-chip .dt{
    font-size:10px;
    color:#a97b90;
    text-transform:uppercase;
    letter-spacing:.3px;
}

.info-chip .dd{
    font-weight:bold;
    color:#75445e;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}


/* FIGURE VIEWER CARD */

.order-figures-title{
    color:#a17488;
    font-size:10px;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.5px;
    margin-bottom:12px;
}

.figure-tabs{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-bottom:18px;
}

.figure-tab-btn{
    border:none;
    background:transparent;
    font-size:12px;
    font-weight:bold;
    color:#a97b90;
    padding:8px 16px;
    border-radius:14px;
    cursor:pointer;
    transition:.15s;
    
}

.figure-tab-btn.active{
    color:#d2417a;
    background:#fdeaf1;
}

.figure-tab-btn:hover:not(.active){
    color:#d2417a;
}

.order-figure:not(.active){
    display:none;
}


/* VIEWER GRID: FIGURE DETAILS | CUSTOM BOX */

.viewer-grid{
    display:grid;
    grid-template-columns:1.1fr 1.3fr;
    gap:22px;
    align-items:start;
}

@media (max-width:650px){
    .viewer-grid{grid-template-columns:1fr;}
}

.viewer-column{
    min-width:0;
    display:flex;
    flex-direction:column;
}

.details-title{
    color:#d2417a;
    font-weight:bold;
    font-size:13px;
    letter-spacing:.3px;
    margin-bottom:14px;
    display:flex;
    align-items:center;
    gap:6px;
}


/* DETAIL ITEM CARD - reusable "line item" (image + label + value + price), used for Figure Details AND Custom Box. */

.detail-item-list{
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-bottom:8px;
}

.detail-item-card{
    display:flex;
    align-items:center;
    gap:12px;
    background:#fffaf6;
    border:1px solid #f5e6ee;
    border-radius:14px;
    padding:9px 12px;
}

.detail-item-image{
    width:44px;
    height:44px;
    border-radius:10px;
    object-fit:cover;
    flex-shrink:0;
    background:#fff0f7;
}

.detail-item-noimage{
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:19px;
    background:#fdeaf1;
}

.detail-item-body{
    display:flex;
    flex-direction:column;
    flex:1;
    min-width:0;
    gap:1px;
}

.detail-item-label{
    font-size:10px;
    color:#a97b90;
    text-transform:uppercase;
    letter-spacing:.3px;
}

.detail-item-value{
    font-size:13px;
    color:#75445e;
    word-break:break-word;
}

.detail-item-price{
    flex-shrink:0;
    font-size:13px;
    font-weight:bold;
    color:#d2417a;
    white-space:nowrap;
    text-align:right;
}

.detail-item-price.muted{
    color:#c9b3bf;
    font-weight:normal;
}


/* NOTES TO ARTIST */

.notes-box{
    margin:4px 0 8px;
    padding:10px 12px;
    background:#fff8fc;
    border:1px dashed #e5a7c6;
    border-radius:12px;
    font-size:12.5px;
}

.notes-box strong{
    display:block;
    margin-bottom:4px;
    color:#873c63;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.4px;
}

.notes-box p{
    color:#5c3348;
    line-height:1.5;
    word-break:break-word;
    white-space:pre-wrap;
}


/* REFERENCE IMAGE GALLERY */

.reference-section,
.box-reference-section{
    margin-top:auto;
    padding-top:14px;
}

.gallery-label{
    font-size:11.5px;
    font-weight:bold;
    color:#75445e;
    margin-bottom:10px;
    display:flex;
    align-items:baseline;
    gap:6px;
}

.gallery-label-count{
    font-weight:normal;
    font-size:10px;
    color:#a97b90;
}

.gallery-row{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.gallery-thumb{
    width:66px;
    height:66px;
    border-radius:12px;
    object-fit:cover;
    cursor:pointer;
    border:2px solid white;
    outline:1px solid #e6d4e4;
    background:#f3eef1;
    transition:.15s;
}

.gallery-thumb:hover{
    outline-color:#f2a4ca;
    transform:scale(1.05);
}

.no-image{
    width:100%;
    padding:16px 0;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#a99da4;
    font-size:11px;
    text-align:center;
    border-radius:12px;
    border:2px dashed #e6d4e4;
    background:#f9f5f4;
}


/* CUSTOM BOX CARD */

.box-card{
    background:#fdf4f8;
    border:1px solid #f4d9e6;
    border-radius:16px;
    padding:16px;
    height:100%;
    display:flex;
    flex-direction:column;
}

.box-card-title{
    color:#d2417a;
    font-weight:bold;
    font-size:13px;
    margin-bottom:14px;
    display:flex;
    align-items:center;
    gap:6px;
}

/* CUSTOM BOX: EMPTY STATE (walang box add-on) */

.box-card-empty{
    flex:1;
    align-items:center;
    justify-content:center;
    text-align:center;
    background:#fdf4f8;
    border:2px dashed #f0c6dc;
}

.box-empty-state{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:26px 14px;
}

.box-empty-icon{
    font-size:32px;
    opacity:.55;
    margin-bottom:4px;
}

.box-empty-title{
    font-weight:bold;
    color:#a4476d;
    font-size:12.5px;
}

.box-empty-text{
    color:#b696a4;
    font-size:11px;
    max-width:220px;
    line-height:1.5;
}

.box-detail-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:11px 18px;
    font-size:12.5px;
    margin-bottom:6px;
}

.box-detail-grid div{
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:1px;
}

.box-detail-grid div.full-row{
    grid-column:1 / -1;
}

.box-detail-grid div span:first-child{
    color:#a97b90;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.3px;
}

.box-detail-grid div span:last-child{
    font-weight:bold;
    color:#873c63;
    word-break:break-word;
}

@media (max-width:520px){
    .box-detail-grid{grid-template-columns:1fr;}
}


/* ORDER ACTION BOX (Quoted / Awaiting Payment / To Verify) - lives at the top of the Order Summary card. */

.action-box{
    border-radius:16px;
    padding:16px;
    margin-bottom:18px;
}

.action-box.action-quoted{
    background:#fdeaf1;
}

.action-box.action-awaiting{
    background:#fff3e2;
}

.action-box.action-verify{
    background:#eef6ee;
}

.action-title{
    font-weight:bold;
    font-size:13.5px;
    color:#75445e;
    margin-bottom:6px;
}

.action-text{
    font-size:12.5px;
    color:#8a6b78;
    line-height:1.5;
}

.action-deadline{
    display:block;
    margin-top:6px;
    font-size:11.5px;
    color:#c94f4f;
    font-weight:bold;
}

.action-buttons{
    display:flex;
    gap:10px;
    margin-top:14px;
    flex-wrap:wrap;
}

.action-buttons form{
    display:inline-block;
    flex:1;
}

.btn-continue,
.btn-cancel{
    display:inline-block;
    border:none;
    padding:11px 20px;
    border-radius:14px;
    font-size:12.5px;
    font-weight:bold;
    
    cursor:pointer;
    text-align:center;
    text-decoration:none;
    transition:.15s;
    width:100%;
}

.btn-continue{
    background:linear-gradient(135deg,#f2699b,#e0447f);
    color:white;
    box-shadow:0 6px 14px rgba(224,68,127,.35);
}

.btn-continue:hover{
    filter:brightness(1.05);
    transform:translateY(-2px);
}

.btn-cancel{
    border:1px solid #e0447f;
    background:white;
    color:#d2417a;
}

.btn-cancel:hover{
    background:#fdf4f8;
}

.btn-link{
    display:flex;
    align-items:center;
    justify-content:center;
}


/* ORDER SUMMARY (right column) */

.detail-side .summary-card{
    position:sticky;
    top:98px; /* pareho ng left order-list para same ang space nila sa navbar */
}

.summary-title{
    color:#d2417a;
    
    font-weight:bold;
    font-size:15px;
    margin-bottom:14px;
}

.summary-lines{
    display:flex;
    flex-direction:column;
}

.summary-line{
    display:flex;
    justify-content:space-between;
    align-items:center;
    font-size:13px;
    padding:8px 0;
    gap:10px;
}

.summary-line .icon{
    margin-right:8px;
}

.summary-line .label-wrap{
    display:flex;
    align-items:center;
    color:#75445e;
}

.summary-divider{
    height:1px;
    background:#f0e3ea;
    margin:8px 0;
}

.summary-total{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-top:6px;
}

.summary-total .label{
    color:#d2417a;
    font-weight:bold;
    font-size:11.5px;
    letter-spacing:.3px;
    text-transform:uppercase;
}

.summary-total .amount{
    color:#d2417a;
    font-weight:bold;
    font-size:22px;
    white-space:nowrap;
}

.expiry-note{
    margin-top:14px;
    display:flex;
    gap:10px;
    align-items:flex-start;
    background:#fff7f0;
    border-radius:14px;
    padding:12px 14px;
    font-size:12px;
    color:#8a6b78;
}


/* IMAGE LIGHTBOX (click a photo to enlarge) */

.image-lightbox{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(60,30,45,.75);
    z-index:5000; /* mas mataas sa navbar */
    align-items:center;
    justify-content:center;
    padding:30px;
}

.image-lightbox.show{
    display:flex;
}

.image-lightbox-content{
    position:relative;
    max-width:min(600px,92vw);
    text-align:center;
}

.image-lightbox-content img{
    max-width:100%;
    max-height:78vh;
    border-radius:14px;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
    object-fit:contain;
    background:#fff;
}

.image-lightbox-label{
    margin-top:12px;
    color:#fff0f7;
    font-size:12px;
    font-weight:700;
}

.image-lightbox-close{
    position:absolute;
    top:-18px;
    right:-18px;
    width:34px;
    height:34px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:none;
    border-radius:50%;
    background:white;
    color:#d2417a;
    font-size:14px;
    font-weight:900;
    cursor:pointer;
    box-shadow:0 2px 6px rgba(0,0,0,.15);
    transition:.15s;
}

.image-lightbox-close:hover{
    background:#fdeaf1;
    transform:scale(1.08);
}

@media(max-width:500px){
    .image-lightbox-close{top:-14px;right:0;}
}


/* TERMS & CONDITIONS MODAL */

.terms-modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(60,30,45,.55);
    z-index:1000;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.terms-modal-overlay.show{
    display:flex;
    overscroll-behavior:contain;
}

.terms-modal{
    background:white;
    width:min(480px,100%);
    max-height:85vh;
    display:flex;
    flex-direction:column;
    border-radius:20px;
    overflow:hidden;
}

.terms-modal-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:18px 20px;
    border-bottom:1px solid #f0e3ea;
}

.terms-modal-top h3{
    color:#86365f;
    
    font-size:18px;
}

.terms-modal-close{
    border:none;
    background:none;
    font-size:20px;
    cursor:pointer;
    color:#a97b90;
}

.terms-modal-body{
    padding:16px 20px;
    overflow-y:auto;
    overscroll-behavior:contain;
    font-size:12.5px;
    color:#75445e;
    line-height:1.6;
}

.terms-modal-body p{
    margin-bottom:10px;
}

.terms-modal-body ol{
    padding-left:18px;
}

.terms-modal-body li{
    margin-bottom:10px;
}

.terms-modal-body strong{
    color:#893a65;
}

.terms-modal-agree{
    padding:14px 20px;
    display:flex;
    gap:8px;
    align-items:flex-start;
    font-size:12px;
    color:#75445e;
    border-top:1px solid #f0e3ea;
    cursor:pointer;
}

.terms-modal-agree input{
    margin-top:2px;
    flex-shrink:0;
}

.terms-modal-confirm{
    margin:0 20px 18px;
    border:none;
    background:#f2a4ca;
    color:white;
    padding:12px;
    border-radius:14px;
    font-weight:bold;
    font-size:12.5px;
    cursor:pointer;
    transition:.15s;
}

.terms-modal-confirm:hover:not(:disabled){
    background:#e98db9;
}

.terms-modal-confirm:disabled{
    opacity:.5;
    cursor:not-allowed;
}



/* [2] FROM owner/orders.css */
/* SHARED orders.css (Owner + Staff) - dating magkahiwalay na kopya, iisa na lang para di mag-drift. */

/* FIGURIFY - STAFF ORDERS PAGE */

.orders-manager{
    padding: 20px;
}

.orders-manager-grid{
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    align-items: start;
}


/* LEFT: ORDER LIST */

.staff-order-list{
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 700px;
    overflow-y: auto;
    padding-right: 4px;
}

.staff-order-item{
    display: block;
    text-decoration: none;
    color: inherit;
    background: var(--white);
    border: 2px solid var(--gray-light);
    border-radius: 14px;
    padding: 12px 14px;
    transition: .15s ease;
}

.staff-order-item:hover{
    border-color: var(--pink);
}

.staff-order-item.active{
    border-color: var(--pink);
    background: var(--pink-soft);
}

.staff-order-item-top{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}

.staff-order-item-customer{
    font-size: 12px;
    color: var(--black);
    font-weight: 700;
    margin-bottom: 2px;
}

.staff-order-item-meta{
    font-size: 10px;
    color: var(--gray);
}


/* RIGHT: ORDER DETAIL */

.staff-order-detail{
    background: var(--white);
    border: 2px solid var(--gray-light);
    border-radius: 16px;
    padding: 20px;
    min-height: 300px;
}

.staff-detail-header{
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.staff-detail-header h3{
    color: var(--pink-dark);
    font-size: 18px;
}

.staff-detail-header p{
    font-size: 11px;
    color: var(--gray);
    margin-top: 2px;
}

.staff-detail-total{
    font-size: 18px;
    font-weight: 900;
    color: var(--pink-dark);
    white-space: nowrap;
}

.staff-detail-meta{
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    font-size: 11px;
    color: var(--gray);
    padding-bottom: 14px;
    margin-bottom: 14px;
    border-bottom: 2px dashed var(--gray-light);
}


/* PAYMENT / SHIPPING VERIFICATION BOX */

.staff-payment-details{
    padding: 14px;
    background: var(--white);
    border: 1.5px solid #C9BEC3;
    border-radius: 20px;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
}

.staff-payment-details-title{
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: var(--pink-dark, #a4476d);
    margin-bottom: 8px;
}

.staff-payment-details-grid{
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 8px 16px;
    font-size: 11px;
    color: var(--gray, #75445e);
}

.staff-payment-details-grid strong{
    color: var(--black, #4a2c3a);
    margin-right: 4px;
}


/* PROOF OF PAYMENT THUMBNAIL */

.staff-payment-proof{
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed var(--pink, #f2a4ca);
}

.staff-payment-proof-title{
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: var(--pink-dark, #a4476d);
    margin-bottom: 8px;
}

.staff-payment-proof-thumb{
    max-width: 220px;
    max-height: 220px;
    border-radius: 12px;
    border: 2px solid white;
    outline: 1px solid #e4d5df;
    cursor: pointer;
    object-fit: cover;
    transition: .15s ease;
}

.staff-payment-proof-thumb:hover{
    transform: translateY(-2px);
}


/* STATUS UPDATE FORM */

.status-update-form{
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 2px dashed var(--gray-light);
}

.status-update-form label{
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: var(--gray);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: .4px;
}

.status-update-row{
    display: flex;
    gap: 10px;
}

.status-update-row select{
    flex: 1;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid var(--gray-light);
    font-size: 12px;
    background: var(--white);
    color: var(--black);
}

.status-update-row button{
    padding: 8px 18px;
    border-radius: 10px;
    border: none;
    background: var(--pink);
    color: white;
    font-weight: 800;
    font-size: 11px;
    cursor: pointer;
    transition: .15s ease;
}

.status-update-row button:hover{
    background: var(--pink-dark);
}


/* VERIFICATION APPROVE / REJECT */

.verify-actions{
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 18px;
    border-bottom: 2px dashed var(--gray-light);
}

.verify-actions form{
    flex: 1;
}

.verify-btn{
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 10px;
    font-weight: 800;
    font-size: 12px;
    cursor: pointer;
    transition: .15s ease;
}

.verify-btn-approve{
    background: var(--green);
    color: white;
}

.verify-btn-approve:hover{
    background: #4f8d68;
}

.verify-btn-reject{
    background: var(--red);
    color: white;
}

.verify-btn-reject:hover{
    background: #c14f5d;
}

.verify-btn-update{
    background: var(--blue);
    color: white;
}

.verify-btn-update:hover{
    background: #4f6cc1;
}


/* Accept / Decline Revision - magkatabi at pantay ang laki */

.revision-decision-actions{
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: stretch;
    gap: 10px;
    width: 100%;
}

.revision-decision-actions form{
    flex: 1;
    min-width: 0;
    margin: 0;
    padding: 0;
}

.revision-decision-actions .verify-btn{
    width: 100%;
}

.revision-decision-actions button.verify-btn{
    flex: 1;
    min-width: 0;
}


/* JUMP TO QUOTATION */

.quotation-jump-link{
    display: inline-block;
    margin-bottom: 18px;
    padding: 9px 16px;
    border-radius: 10px;
    background: var(--pink-light);
    color: var(--pink-dark);
    font-size: 11px;
    font-weight: 800;
    text-decoration: none;
    transition: .15s ease;
}

.quotation-jump-link:hover{
    background: var(--pink);
    color: white;
}


/* FIGURES GRID */

.staff-figures{
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
}

.figure-notes{
    display: block;
    font-style: italic;
}


/* PER-FIGURE PRICE BREAKDOWN */

.figure-price-breakdown{
    margin-top: 8px;
    padding: 10px 12px;
    background: var(--pink-light, #fff0f7);
    border: 1px dashed var(--pink, #f2a4ca);
    border-radius: 10px;
}

.figure-price-row{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 3px 0;
    font-size: 11px;
    color: var(--gray, #75445e);
}

.figure-price-row-total{
    margin-top: 4px;
    padding-top: 6px;
    border-top: 1px dashed var(--pink, #f2a4ca);
    font-weight: 800;
    color: var(--black, #4a2c3a);
}

.figure-price-row-quoted{
    margin-top: 2px;
    padding: 6px 8px;
    background: var(--pink, #f2a4ca);
    border-radius: 8px;
    color: white;
    font-weight: 900;
}


/* EMPTY STATE */

.staff-order-detail .empty-state{
    text-align: center;
    padding: 60px 20px;
    color: var(--gray);
    font-size: 13px;
}


/* DETAIL VIEW TOGGLE (Customer Order / Payment) */

.detail-view-toggle{
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.detail-toggle-btn{
    padding: 9px 18px;
    border-radius: 10px;
    border: 2px solid var(--gray-light);
    background: var(--white);
    color: var(--gray, #75445e);
    font-weight: 800;
    font-size: 12px;
    cursor: pointer;
    transition: .15s ease;
}

.detail-toggle-btn:hover{
    border-color: var(--pink);
}

.detail-toggle-btn.active{
    background: var(--pink);
    border-color: var(--pink);
    color: white;
}

.detail-view-panel{
    display: none;
}

.detail-view-panel.active{
    display: block;
}


/* RESPONSIVE */

@media (max-width: 900px){

    .orders-manager-grid{
        grid-template-columns: 1fr;
    }

    .staff-order-list{
        max-height: none;
    }

}

/* [3] FROM owner/quotation.css */
/* shared quotation.css - 3-column workspace. "qv-" prefix para di ma-conflict sa generic class names ng my-orders.css/orders.css na naka-load din sa page. */

/* Processing / Revision tabs at search bar */
.qv-toolbar{
    margin-top: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.qv-tabs{
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.qv-tab{
    text-align: center;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    color: var(--gray);
    background: var(--background);
    border: 1px solid transparent;
    cursor: pointer;
    white-space: nowrap;
}

.qv-tab.active{
    background: var(--pink-light);
    color: var(--pink-dark);
    border-color: var(--pink);
}

/* filter dropdown - isang button na lang instead of tab pills, same design sa Quotation, Active Booking, Shipments */

.qv-filter-dropdown{
    position: relative;
    flex-shrink: 0;
}

.qv-filter-trigger{
    display: flex;
    align-items: center;
    gap: 6px;
    text-align: center;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    color: var(--pink-dark);
    background: var(--pink-light);
    border: 1px solid var(--pink);
    cursor: pointer;
    white-space: nowrap;
    
}

.qv-filter-caret{
    width: 10px;
    height: 10px;
    flex-shrink: 0;
    transition: transform .15s ease;
}

.qv-filter-dropdown.open .qv-filter-caret{
    transform: rotate(180deg);
}

.qv-filter-menu{
    display: none;
    flex-direction: column;
    gap: 2px;

    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    z-index: 20;

    min-width: 180px;

    background: var(--white);
    border: 1px solid var(--gray-light);
    border-radius: 12px;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
    padding: 6px;
}

.qv-filter-dropdown.open .qv-filter-menu{
    display: flex;
}

.qv-filter-option{
    display: block;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--black);
    text-decoration: none;
    white-space: nowrap;
}

.qv-filter-option:hover{
    background: var(--background);
}

.qv-filter-option.active{
    background: var(--pink-light);
    color: var(--pink-dark);
}

/* outer wrapping card - default puti ang ".card" pero client wanted colored background dito sa Quotation, kaya may override */


/* fixed-height layout: ang list at right panel lang ang may sariling scroll.
   Sa mobile, normal na scroll. */

/* walang outer card sa mga qv- page, layout wrapper lang ang .qv-workspace */
.qv-workspace{
    background: transparent;
    border: none;
    outline: none;
    border-radius: 0;
    box-shadow: none;
}

.qv-shell{
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 20px;
    align-items: start;
}

/* form wraps center + right columns so inputs post together, but <form> is block by default and breaks the grid - display:contents fixes that */
.qv-shell form{
    display: contents;
}

.qv-right-col{
    display: flex;
    flex-direction: column;
    gap: 8px;
}

@media (min-width: 1181px){

    html, body.quotation-page{
        height: 100%;
        overflow: hidden;
    }

    body.quotation-page .app{
        height: 100vh;
    }

    body.quotation-page .main{
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    body.quotation-page .qv-workspace{
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .qv-shell{
        flex: 1 1 auto;
        min-height: 0;
        height: 100%;
        align-items: stretch;
    }

    .qv-list-panel{
        height: 100%;
        min-height: 0;
    }

    .qv-right-col{
        height: 100%;
        min-height: 0;
        overflow-y: auto;
        background: var(--white);
        border-radius: 20px;
        border: 1.5px solid #C9BEC3;
        box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
        padding: 16px 18px 20px;
    }

}

.qv-figure-panels{
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.qv-figure-panel{
    display: none;
    flex-direction: column;
    gap: 14px;
}

.qv-figure-panel.active{
    display: flex;
}

@media (max-width: 1180px){
    .qv-shell{
        grid-template-columns: 1fr;
    }
}


/* LEFT - ORDER LIST */

.qv-list-panel{
    background: var(--white);
    border-radius: 20px;
    border: 1.5px solid #C9BEC3;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
    padding: 16px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.qv-search{
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--background);
    border: 1px solid var(--gray-light);
    border-radius: 999px;
    padding: 8px 14px;
    margin-bottom: 0;
    width: 200px;
    flex-shrink: 0;
}

.qv-search input{
    border: none;
    background: none;
    outline: none;
    font-size: 13px;
    width: 100%;
    color: var(--black);
    
}

.qv-search svg{ flex-shrink: 0; color: var(--gray); }

/* tabs grid, 2x2 (2 button per row) para pantay-pantay ang size, at may sapat na space para di na i-truncate ang labels */
.qv-filter-tabs{
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr 1fr;
    gap: 6px;
    margin-bottom: 14px;
}

.qv-filter-tabs .detail-toggle-btn{
    padding: 8px 6px;
    font-size: 11px;
    text-align: center;
    white-space: nowrap;
}

/* order list na naka-table; .qv-list may sariling scroll, sticky ang header */
.qv-list{
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto;
    border: 1px solid var(--gray-light);
    border-radius: 14px;
}

.qv-table{
    width: 100%;
    min-width: 760px;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 12.5px;
    table-layout: fixed;
}

/* parehong % width ng bawat column - Order / Email / Order Type /
   Order Method / Book Date / Status, sunod sa pagkakasunod nila sa
   <thead> */
.qv-table th:nth-child(1), .qv-table td:nth-child(1) { width: 8%; }
.qv-table th:nth-child(2), .qv-table td:nth-child(2) { width: 30%; }
.qv-table th:nth-child(3), .qv-table td:nth-child(3) { width: 14%; }
.qv-table th:nth-child(4), .qv-table td:nth-child(4) { width: 18%; }
.qv-table th:nth-child(5), .qv-table td:nth-child(5) { width: 14%; }
.qv-table th:nth-child(6), .qv-table td:nth-child(6) { width: 16%; }

/* Email column lang ang pwedeng lumampas sa laki niya (pinakamahaba),
   kaya dito lang ilalagay ang ellipsis kapag hindi na kasya */
.qv-table td:nth-child(2) {
    overflow: hidden;
    text-overflow: ellipsis;
}

.qv-table thead th{
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--background);
    color: var(--gray);
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .3px;
    text-align: left;
    padding: 10px 12px;
    white-space: nowrap;
    border-bottom: 1.5px solid var(--gray-light);
    border-right: 1px solid var(--gray-light);
}

.qv-table thead th:last-child{
    border-right: none;
}

.qv-row{
    cursor: pointer;
    transition: .15s ease;
}

.qv-row td{
    padding: 12px;
    border-bottom: 1px solid var(--gray-light);
    border-right: 1px solid var(--gray-light);
    white-space: nowrap;
    color: var(--black);
}

.qv-row td:last-child{
    border-right: none;
}

.qv-table tbody tr:last-child td{
    border-bottom: none;
}

.qv-row td.qv-cell-id{
    font-weight: 800;
    color: var(--pink-dark, var(--pink));
}

.qv-row:hover{
    background: var(--background);
}

.qv-row.active{
    background: #FFF3C4;
}

.qv-row.active td{
    color: #8A6D1D;
}

.qv-row.active td:first-child{
    box-shadow: none;
}

.qv-row.qv-hidden{
    display: none;
}

.qv-pill{
    display: inline-block;
    font-size: 9.5px;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: .3px;
    white-space: nowrap;
}

.qv-list-footer{
    margin-top: 14px;
    font-size: 11px;
    color: var(--gray);
    text-align: center;
}


/* CENTER - ORDER DETAIL */

.qv-detail-col{
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    gap: 16px;
    padding: 2px 10px 18px 2px;
}

.qv-header-card{
    background: var(--white);
    border-radius: 20px;
    border: 1.5px solid #C9BEC3;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
    padding: 16px 20px;
}

/* ORDER INFORMATION CARD - same visual language as the "Order Information" card sa Customer's My Orders page (icon chips sa loob ng grid), para magkatugma ang itsura sa Owner, Owner, at Customer side. */

.order-info-title{
    color: var(--pink-dark);
    font-weight: bold;
    font-size: 13px;
    letter-spacing: .2px;
    margin-bottom: 14px;
}

.order-info-grid{
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

@media (max-width: 900px){
    .order-info-grid{grid-template-columns: repeat(2, minmax(0, 1fr));}
}

@media (max-width: 520px){
    .order-info-grid{grid-template-columns: 1fr;}
}

.info-chip{
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fffaf6;
    border: 1px solid #f5e6ee;
    border-radius: 12px;
    padding: 8px 10px;
    font-size: 11px;
    min-width: 0;
}

.info-chip .icon{
    font-size: 14px;
    flex-shrink: 0;
}

.info-chip .txt{
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.info-chip .dt{
    font-size: 9px;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: .3px;
}

.info-chip .dd{
    font-size: 11px;
    font-weight: bold;
    color: var(--pink-dark);
    white-space: normal;
    overflow-wrap: break-word;
    word-break: break-word;
}


/* FIGURE DETAILS + CUSTOM BOX SIDE BY SIDE Kapareho ng "viewer-grid" sa Customer My Orders page - Figure Details sa kaliwa, Custom Box sa kanan, magkatabi sa desktop. */

/* figure details + custom box, naka-stack na lang (same pattern ng Quotation page, masikip na kasi sa narrow column) */
.qv-viewer-grid{
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.qv-viewer-column{
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.qv-viewer-column .qv-figure-card,
.qv-viewer-column .qv-box-card{
    height: auto;
}


/* GALLERY + FIGURE DETAILS CARD */

.qv-figure-card{
    background: var(--white);
    border-radius: 20px;
    border: 1.5px solid #C9BEC3;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
}

.qv-figure-tabs{
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}

.qv-figure-tab-pill{
    padding: 7px 16px;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--gray);
    background: var(--background);
    border: none;
    cursor: pointer;
}

.qv-figure-tab-pill.active{
    background: var(--pink);
    color: var(--white);
}

.qv-figure-body{
    display: flex;
    flex-direction: column;
}


/* FIGURE DETAILS */

.qv-details-head{
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--pink-dark);
    font-weight: 800;
    font-size: 13px;
    margin-bottom: 14px;
}


/* DETAIL ITEM CARD - "line item" row (image/icon + label + value + price), gaya ng disenyo ng Figure Details sa Customer My Orders page (detail-item-card), para consistent ang itsura sa pagitan ng Owner at Customer side. */

.qv-detail-item-list{
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.qv-detail-item-card{
    display: flex;
    align-items: center;
    gap: 12px;
    background: #fffaf6;
    border: 1px solid #f5e6ee;
    border-radius: 14px;
    padding: 9px 12px;
    flex-wrap: wrap;
}

.qv-detail-item-image{
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
    background: var(--pink-light);
}

.qv-detail-item-noimage{
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
}

.qv-detail-item-body{
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 120px;
    gap: 1px;
}

.qv-detail-item-label{
    font-size: 10px;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: .3px;
}

.qv-detail-item-value{
    font-size: 13px;
    color: var(--black);
    word-break: break-word;
}

.qv-detail-item-price{
    flex-shrink: 0;
    font-size: 13px;
    font-weight: 700;
    color: var(--pink-dark);
    white-space: nowrap;
    text-align: right;
}

.qv-detail-item-price-muted{
    color: var(--gray);
    font-weight: 400;
}

.qv-spec-label{
    font-size: 10.5px;
    color: var(--black);
    margin-bottom: 3px;
    font-weight: 700;
}

.qv-spec-value{
    font-size: 12px;
    font-weight: 400;
}

.qv-spec-price{
    font-size: 11px;
    color: var(--pink-dark);
    font-weight: 700;
    margin-top: 2px;
}

/* Makes an item span the full width of the grid row on its own (used for Date and Letter sa Custom Box Details), para hindi na sila kakatabe ng ibang field na hindi related. */
.qv-spec-full{
    grid-column: 1 / -1;
}

/* Pinipilit magsimula ng bagong row (ilalagay sa unang Optional Extra), para magkakatabi silang lahat sa sarili nilang row at hindi na sila madikit/nakikialam sa Date sa row bago nila. */
.qv-row-start{
    grid-column-start: 1;
}

.qv-letter-value{
    font-size: 11px;
    line-height: 1.5;
}

.qv-note-box{
    background: var(--pink-soft);
    border: 1px dashed var(--pink);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 13px;
    color: var(--black);
    line-height: 1.5;
    margin-top: 4px;
}

.qv-notes-row{
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px dashed var(--gray-light);
}


/* per-figure read-only total (Verification page only) - same look as .qv-total-row pero nested sa loob ng figure panel */

.qv-figure-total-row{
    margin-top: 4px;
    padding-top: 16px;
    border-top: 1px dashed var(--gray-light);
}


/* REFERENCE IMAGES */

.qv-reference-row{
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px dashed var(--gray-light);
}

.qv-reference-label{
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 800;
    color: var(--pink-dark);
    margin-bottom: 10px;
}

.qv-reference-thumbs{
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.qv-ref-thumb{
    width: 54px;
    height: 54px;
    border-radius: 10px;
    overflow: hidden;
    background: linear-gradient(150deg, #ffe1ec, #ffc2d6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: var(--pink-dark);
    cursor: pointer;
    border: none;
    padding: 0;
}

.qv-ref-thumb img{
    width: 100%;
    height: 100%;
    object-fit: cover;
}


/* CUSTOM BOX CARD */

.qv-box-card{
    background: var(--white);
    border-radius: 20px;
    border: 1.5px solid #C9BEC3;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
}

.qv-box-info-head{
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--pink-dark);
    font-weight: 800;
    font-size: 13px;
    margin-bottom: 14px;
}

.qv-box-spec-grid{
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 12px 16px;
}

/* empty state kapag walang custom box - dashed placeholder na lang instead na basta mawawala. Same style ng My Orders page. */
.qv-box-card-empty{
    border: 1px solid var(--gray-light);
    outline: none;
    box-shadow: none;
    background: var(--white);
    flex: 1;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.qv-box-empty-icon{
    font-size: 32px;
    opacity: .55;
    margin-bottom: 4px;
}

.qv-box-empty-title{
    font-weight: 800;
    color: var(--pink-dark);
    font-size: 13.5px;
    margin-bottom: 4px;
}

.qv-box-empty-text{
    color: var(--gray);
    font-size: 11.5px;
    max-width: 220px;
    line-height: 1.5;
}


/* RIGHT - QUOTATION SUMMARY */

/* order actions na may sariling card */
.qv-summary{
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    background: var(--white);
    border-radius: 18px;
    border: 1px solid var(--gray-light);
    box-shadow: 4px 5px 0 rgba(216, 194, 222, .2);
    margin-top: 6px;
}

.qv-summary-head{
    background: var(--pink-light);
    padding: 14px 18px;
    flex-shrink: 0;
    border-radius: 17px 17px 0 0;
}

.qv-summary-head h2{
    font-size: 16px;
    color: var(--pink-dark);
    font-weight: 700;
}

.qv-summary-body{
    padding: 16px 18px 18px;
}

.qv-line-list{
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 14px;
}

.qv-line{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.qv-line-label{
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--black);
}

.qv-line-icon{
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: var(--pink-light);
    color: var(--pink-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    flex-shrink: 0;
}

.qv-line-value{
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.qv-line-input-wrap{
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--pink-soft);
    border: 1.5px solid var(--pink);
    border-radius: 8px;
    padding: 4px 8px;
}

.qv-line-input-wrap span{
    font-size: 12px;
    font-weight: 800;
    color: var(--pink-dark);
}

.qv-line-input{
    width: 84px;
    border: none;
    background: none;
    outline: none;
    
    font-size: 13px;
    font-weight: 800;
    color: var(--black);
    text-align: right;
    -moz-appearance: textfield;
}

.qv-line-input::-webkit-outer-spin-button,
.qv-line-input::-webkit-inner-spin-button{
    -webkit-appearance: none;
    margin: 0;
}

.qv-divider{
    border: none;
    border-top: 1px dashed var(--gray-light);
    margin: 14px 0;
}

.qv-total-row{
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: nowrap;
    gap: 10px;
    margin-bottom: 16px;
}

.qv-total-row span{
    font-size: 11.5px;
    font-weight: 800;
    color: var(--pink-dark);
    text-transform: uppercase;
    letter-spacing: .3px;
}

.qv-total-row strong{
    font-size: 20px;
    color: var(--pink-dark);
}

.qv-price-breakdown{
    background: var(--pink-soft);
    border: 1.5px dashed var(--pink);
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 16px;
}

.qv-price-row{
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.qv-price-row span{
    font-size: 11.5px;
    font-weight: 800;
    color: var(--pink-dark);
    text-transform: uppercase;
    letter-spacing: .3px;
}

.qv-price-row strong{
    font-size: 18px;
    color: var(--pink-dark);
}

.qv-price-row-total{
    padding-bottom: 10px;
    border-bottom: 1px dashed var(--pink);
    margin-bottom: 10px;
}

.qv-price-row-downpayment strong{
    font-size: 20px;
}

.qv-validity-note{
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: var(--background);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 11.5px;
    color: var(--gray);
    margin-bottom: 16px;
}

.qv-validity-note strong{
    display: block;
    color: var(--black);
    font-size: 12px;
    margin-bottom: 2px;
}

.qv-send-btn{
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 999px;
    background: var(--pink);
    color: var(--white);
    font-weight: 800;
    font-size: 13.5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: .15s ease;
}

.qv-send-btn:hover{ background: var(--pink-dark); }

.qv-summary-hint{
    margin-top: 12px;
    font-size: 10.5px;
    color: var(--gray);
    line-height: 1.5;
}

.qv-locked-value{
    color: var(--pink-dark);
    font-weight: 900;
}

.qv-empty{
    padding: 40px 16px;
    text-align: center;
    color: var(--gray);
    font-size: 13px;
}

/* Thin, subtle scrollbar - ang .qv-list (left, order list) at ang .qv-right-col (right, buong detail + summary) na lang ang dalawang bahagi ng workspace na may sariling scroll. */
.qv-list::-webkit-scrollbar,
.qv-right-col::-webkit-scrollbar{
    width: 6px;
}

.qv-list::-webkit-scrollbar-thumb,
.qv-right-col::-webkit-scrollbar-thumb{
    background: var(--gray-light);
    border-radius: 999px;
}


/* [4] FROM owner/progress-update.css */
/* SHARED progress-update.css (Owner + Staff) - dating magkahiwalay na kopya, iisa na lang para di mag-drift. */

/* CLAY AND STUFF - PROGRESS UPDATES (STAFF) */

.progress-history-block{
    margin-top:24px;
    padding-top:20px;
    border-top:2px dashed var(--gray-light);
}

.progress-history-title{
    font-size:15px;
    font-weight:900;
    color:var(--black);
    margin-bottom:12px;
}

.progress-history-empty{
    font-size:13px;
    color:var(--gray);
}

.progress-history-list{
    display:flex;
    flex-direction:column;
    gap:14px;
}

.progress-history-item{
    display:flex;
    flex-direction:column;
    gap:10px;
    padding:12px;
    background:var(--white);
    border:1px solid var(--gray-light);
    border-radius:14px;
}

.progress-history-thumb{
    width:100%;
    height:160px;
    object-fit:cover;
    border-radius:10px;
    flex-shrink:0;
}

.progress-history-body{
    display:flex;
    flex-direction:column;
    gap:4px;
}

.progress-history-caption{
    font-size:13px;
    color:var(--black);
}

.progress-history-date{
    font-size:11px;
    color:var(--gray);
}

.progress-status-badge{
    display:inline-block;
    width:fit-content;
    padding:4px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:900;
    margin-top:2px;
}

.progress-status-pending{
    background:var(--yellow-light);
    color:var(--yellow);
}

.progress-status-approved{
    background:var(--green-light);
    color:var(--green);
}

.progress-status-revision{
    background: #FBE8D6;
    color: #29252A;
}

.progress-history-revision-note{
    font-size:12px;
    font-style:italic;
    color:var(--gray);
    background:var(--background);
    padding:8px 10px;
    border-radius:8px;
}


/* Decline Revision modal (pareho ng Decline sa Quotation at Verification) */

.decline-reason-select{
    width: 100%;
    padding: 9px 10px;
    border: 1px solid var(--gray-light);
    border-radius: 8px;
    font-size: 11.5px;
    
    background: var(--white);
    color: var(--black);
}

.decline-reason-select:invalid{
    color: var(--gray);
}

.decline-modal-overlay{
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.decline-modal-overlay.active{
    display: flex;
}

.decline-modal-box{
    background: var(--white);
    border-radius: 14px;
    padding: 22px;
    width: 100%;
    max-width: 360px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.decline-modal-eyebrow{
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--pink-dark);
    background: var(--pink-soft);
    display: inline-block;
    width: fit-content;
    padding: 4px 9px;
    border-radius: 999px;
    margin: 0 0 2px;
}

.decline-modal-title{
    font-size: 14px;
    font-weight: 800;
    color: var(--black);
    margin: 0;
}

.decline-modal-desc{
    font-size: 11.5px;
    color: var(--gray);
    margin: 0 0 4px;
}

.decline-reason-other{
    width: 100%;
    padding: 9px 10px;
    border: 1px solid var(--gray-light);
    border-radius: 8px;
    font-size: 11.5px;
    
    color: var(--black);
    resize: vertical;
    min-height: 60px;
}

.decline-modal-actions{
    display: flex;
    gap: 8px;
    margin-top: 4px;
}

.decline-modal-btn{
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 11.5px;
    cursor: pointer;
}

.decline-modal-btn-cancel{
    background: var(--gray-light);
    color: var(--black);
}

.decline-modal-btn-send{
    background: var(--red);
    color: white;
}


/* SEND UPDATE MODAL */

.progress-modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(60,30,45,.72);
    z-index:1500;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.progress-modal-overlay.show{
    display:flex;
}

.progress-modal{
    width:min(460px,94vw);
    max-height:90vh;
    overflow-y:auto;
    background:var(--white);
    border-radius:20px;
    padding:22px;
    box-shadow:0 25px 70px rgba(0,0,0,.28);
}

.progress-modal-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding-bottom:12px;
    margin-bottom:14px;
    border-bottom:2px dashed var(--gray-light);
}

.progress-modal-top h3{
    color:var(--pink-dark);
    font-size:17px;
}

.progress-modal-close{
    width:30px;
    height:30px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:none;
    border-radius:50%;
    background:var(--pink-light);
    color:var(--pink-dark);
    font-size:16px;
    font-weight:900;
    cursor:pointer;
}

.progress-modal-close:hover{
    background:var(--pink);
    color:white;
}

.progress-modal-hint{
    font-size:12px;
    color:var(--gray);
    margin-bottom:16px;
}

.progress-modal form{
    display:flex;
    flex-direction:column;
    gap:6px;
}

.progress-modal label{
    font-size:12px;
    font-weight:700;
    color:var(--black);
    margin-top:10px;
}

.progress-modal input[type="file"],
.progress-modal input[type="text"],
.progress-modal input[type="number"],
.progress-modal textarea{
    padding:10px 12px;
    border-radius:10px;
    border:1px solid var(--gray-light);
    
    font-size:13px;
    color:var(--black);
}

.progress-modal textarea{
    resize:vertical;
}

.progress-modal-submit{
    margin-top:18px;
    padding:12px;
    border:none;
    border-radius:12px;
    background:var(--pink);
    color:white;
    font-weight:900;
    font-size:13px;
    cursor:pointer;
    transition:.15s;
}

.progress-modal-submit:hover{
    background:var(--pink-dark);
}


/* PART 2: dating laman ng dashboard.css (via owner.css) (shared layout: app wrapper, sidebar, cards, table, status colors, atbp.) */

/* shared dashboard styles - common CSS ng owner + staff dashboard. owner.css/staff.css nag-@import na lang dito, role-specific styles na lang doon. */


/* FIGURIFY - OWNER DASHBOARD Pastel / Cute / Modern / Clean */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --pink: #f78fd4;
    --pink-dark: #b23e82;
    --pink-light: #ffc4e8;
    --pink-soft: #fff0f9;

    --black: #29252a;
    --gray: #81777d;
    --gray-light: #eee7ea;

    --white: #ffffff;
    --background: #f8f4f5;

    --green: #65a77e;
    --green-light: #e1f3e7;

    --yellow: #d59b2b;
    --yellow-light: #fff1cc;

    --blue: #6689dd;
    --blue-light: #e8edff;

    --red: #d96b78;
    --red-light: #ffe4e8;
}

html {
    scroll-behavior: smooth;
}

body {
    
    color: var(--black);
    min-height: 100vh;


    

}

button,
input,
a {
    
}

button,
a {
    cursor: pointer;
}

a {
    text-decoration: none;
    color: inherit;
}

/* APP */

.app {
    min-height: 100vh;
}

/* SIDEBAR */








/* MAIN */

.main {
    margin-left: 230px;

    width: calc(100% - 230px);

    padding: 22px 22px 38px 14px;
}

/* TOPBAR */

.topbar {
    display: flex;

    align-items: flex-start;
    justify-content: space-between;

    margin-bottom: 16px;
}

.welcome-label {
    color: var(--pink-dark);

    font-size: 9px;

    font-weight: 900;

    letter-spacing: 1.7px;

    margin-bottom: 5px;
}

.welcome h2 {
    font-size: 27px;

    letter-spacing: -1px;
}

.welcome p {
    color: var(--gray);

    font-size: 12px;

    margin-top: 5px;
}

/* DATE TIME */

.datetime {
    display: flex;
    align-items: center;

    gap: 9px;

    margin-top: 10px;

    color: #8b747d;

    font-size: 11px;

    font-weight: 700;
}

.datetime-icon {
    width: 27px;
    height: 27px;

    background: var(--pink-light);

    border-radius: 9px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 13px;
}

#currentDate {
    color: #765f68;
}

#currentTime {
    color: #a4476d;
    font-weight: 900;
}

.datetime-dot {
    color: #c7aeb8;
}

/* TOP ACTIONS */

.top-actions {
    display: flex;
    align-items: center;

    gap: 9px;
}

.search-box {
    width: 220px;

    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 12px;

    padding: 11px 13px;

    outline: none;

    font-size: 11px;

    transition: .2s;
}

.search-box:focus {
    border-color: var(--pink);

    box-shadow:
        0 0 0 3px var(--pink-light);
}

.notification-btn {
    position: relative;

    width: 42px;
    height: 42px;

    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 12px;

    font-size: 17px;

    transition: .2s;
}

.notification-btn:hover {
    background: var(--pink-soft);
}

.notification-badge {
    position: absolute;

    top: -5px;
    right: -5px;

    min-width: 17px;
    height: 17px;

    padding: 0 4px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: var(--pink);

    color: white;

    border-radius: 50%;

    border: 2px solid var(--background);

    font-size: 8px;
    font-weight: 900;
}


/* STATISTICS */

.stats {
    display: grid;

    grid-template-columns:
        3fr 1.1fr;

    align-items: start;

    gap: 14px;

    margin-bottom: 18px;
}

.stats-left {
    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    align-items: start;

    gap: 14px;
}

.stat-card {
    position: relative;

    overflow: hidden;
}

.stat-card::after {
    content: "";

    position: absolute;

    width: 80px;
    height: 80px;

    border-radius: 50%;

    background: var(--pink-light);

    right: -28px;
    top: -30px;
}

.approval-card::after {
    background: var(--yellow-light);
}

.stat-label {
    color: var(--black);

    font-size: 10px;

    font-weight: 900;
}

.stat-number {
    font-size: 30px;

    font-weight: 900;

    margin-top: 6px;
}

.stat-trend {
    margin-top: 5px;

    font-size: 10px;

    font-weight: 800;

    color: var(--green);
}

.attention {
    color: var(--yellow);
}

.shipment-stat-card {
    z-index: 1;

    display: flex;

    flex-direction: column;
}

.shipment-stat-card .stat-label {
    margin-bottom: 12px;
}

.shipment-stat-card .shipment-list {
    flex: 1;
}

/* DASHBOARD LAYOUT (left column: stats + calendar + recent bookings / right column: tall shipment card) */

.dashboard-layout {
    display: grid;

    grid-template-columns:
        2.3fr 1fr;

    gap: 18px;

    align-items: stretch;

    margin-bottom: 18px;
}

.dashboard-left {
    display: flex;
    flex-direction: column;

    gap: 18px;
}

.dashboard-right {
    display: flex;

    height: 100%;
}

.dashboard-right .shipment-stat-card {
    flex: 1;

    width: 100%;
    height: 100%;
}

.bottom-row {
    display: grid;

    grid-template-columns:
        1fr 1.2fr;

    gap: 18px;

    align-items: start;
}

/* DASHBOARD GRID */

.dashboard-grid {
    display: grid;

    grid-template-columns:
        1fr 1.2fr;

    gap: 18px;

    align-items: start;
}

/* CARD HEADER */

.card-header {
    display: flex;

    align-items: center;
    justify-content: space-between;

    margin-bottom: 15px;
}

.card-header h3 {
    font-size: 15px;
}

.view-link {
    border: none;

    background: transparent;

    color: #a4476d;

    font-size: 11px;

    font-weight: 800;
}

.view-link:hover {
    text-decoration: underline;
}

/* ORDERS */

.order-row {
    display: grid;

    grid-template-columns:
        1.2fr .9fr .75fr .75fr;

    gap: 8px;

    align-items: center;

    padding: 13px 3px;

    border-top: 1px solid #f0e7eb;

    font-size: 11px;
}

.order-row.header {
    border-top: none;

    color: var(--black);

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;
}

.customer {
    display: flex;

    align-items: center;

    gap: 8px;
}

.customer-icon {
    width: 31px;
    height: 31px;

    border-radius: 10px;

    background: #fbe5ed;

    display: flex;
    align-items: center;
    justify-content: center;
}

.customer strong {
    display: block;

    font-size: 11px;
}

.customer small {
    display: block;

    color: #9a8c94;

    font-size: 9px;

    margin-top: 2px;
}

/* STATUS */

.status {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;

    white-space: nowrap;
}

/* STATUS COLORS - pare-pareho ito sa Owner Dashboard, sa Calendar, at sa My Orders ng customer. Iisang pinagmumulan ang mga kulay na ito: Admin/order_status_helper.php. */

.status-pending {
    background: #FFF3C4;
    color: #29252A;
}

.status-quoted {
    background: #E1F3E7;
    color: #29252A;
}

.status-awaiting_payment {
    background: #FBE8D6;
    color: #29252A; white-space: nowrap; }

.status-to_verify {
    background: #E1F3E7;
    color: #29252A;
}

.status-for_approval {
    background: #E1F3E7;
    color: #29252A;
}

.status-processing {
    background: #FBE8D6;
    color: #29252A;
}

.status-awaiting_balance {
    background: #FBE8D6;
    color: #29252A;
}

.status-to_verify_balance {
    background: #E1F3E7;
    color: #29252A;
}

.status-for_balance_approval {
    background: #E1F3E7;
    color: #29252A;
}

.status-to_ship {
    background: #E8EDFF;
    color: #29252A;
}

.status-completed {
    background: #E1F3E7;
    color: #29252A;
}

.status-cancelled {
    background: #FADCDC;
    color: #29252A;
}

/* CALENDAR CONTROLS */

.calendar-controls {
    display: flex;

    align-items: center;
    justify-content: space-between;

    margin-bottom: 10px;
}

.calendar-controls button {
    width: 30px;
    height: 30px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: none;

    background: var(--pink-light);

    color: var(--pink-dark);

    border-radius: 50%;

    font-size: 16px;
    line-height: 1;
    font-weight: 900;

    cursor: pointer;

    transition: .2s;
}

.calendar-controls button:hover {
    background: var(--pink);
    color: white;

    transform: scale(1.06);
}

.calendar-controls strong {
    font-size: 12px;
}

/* CALENDAR */

.calendar-week {
    display: grid;

    grid-template-columns:
        repeat(7,1fr);

    gap: 4px;

    margin-bottom: 3px;
}

.calendar-week div {
    text-align: center;

    color: var(--black);

    font-size: 8px;
    font-weight: 900;

    padding: 4px 0;
}

.calendar-days {
    display: grid;

    grid-template-columns:
        repeat(7,1fr);

    gap: 4px;
}

.day {
    height: 30px;

    border-radius: 9px;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 10px;

    transition: .2s;
}

.day:hover {
    background: var(--pink-soft);
    cursor: pointer;
}

.day.empty {
    background: transparent;
}

.day.today {
    background: var(--pink);

    color: var(--black);

    font-weight: 900;
}

.day.booked {
    background: var(--pink-light);

    color: #a4476d;

    font-weight: 800;
}

.day.selected {
    outline: 2px solid var(--pink);
    outline-offset: 1px;
}

/* calendar preview is read-only, di gaya ng full calendar page - pointer-events:none para di mukhang clickable */
#calendarDays .day {
    pointer-events: none;
}

#calendarDays .day:hover {
    background: transparent;
    cursor: default;
}

/* CALENDAR LEGEND */

.calendar-legend {
    display: flex;

    gap: 15px;

    margin-top: 13px;

    font-size: 9px;

    color: #95878e;
}

.calendar-legend span {
    display: flex;

    align-items: center;

    gap: 5px;
}

.legend-dot {
    width: 8px;
    height: 8px;

    border-radius: 50%;
}

.today-dot {
    background: var(--pink);
}

.booked-dot {
    background: var(--pink-light);

    border: 1px solid #e8b9ca;
}

/* SHIPMENTS */

.shipment-list {
    display: grid;
    gap: 10px;
}

.shipment {
    display: flex;

    align-items: center;
    justify-content: space-between;

    background: #fff8fa;

    border: 1px solid #f3e4e9;

    padding: 10px 11px;

    border-radius: 12px;
}

.shipment strong {
    font-size: 11px;
}

.shipment small {
    display: block;

    color: #9b8f95;

    font-size: 9px;

    margin-top: 3px;
}

.shipment-icon {
    font-size: 18px;
}

.empty-shipment {
    min-height: 55px;
}

/* OVERVIEW */

.activity-card {
    margin-top: 18px;
}

.online-badge {
    color: var(--green);

    background: var(--green-light);

    padding: 5px 9px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;
}

.overview-grid {
    display: grid;

    grid-template-columns:
        repeat(4,1fr);

    gap: 10px;
}

.overview-box {
    display: flex;

    align-items: center;

    gap: 10px;

    background: var(--pink-soft);

    border: 1px solid #f4e2e8;

    border-radius: 13px;

    padding: 12px;
}

.overview-icon {
    width: 32px;
    height: 32px;

    border-radius: 10px;

    background: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 900;

    color: var(--pink-dark);
}

.overview-box strong {
    display: block;

    font-size: 14px;
}

.overview-box small {
    display: block;

    color: #9b8f95;

    font-size: 9px;

    margin-top: 2px;
}

/* QUICK ACTIONS */

.quick-actions {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 10px;

    margin-top: 18px;
}

.quick-btn {
    padding: 12px;

    background: var(--pink-soft);

    border: 1px dashed #d9b6c3;

    border-radius: 12px;

    font-size: 10px;

    font-weight: 800;

    transition: .2s;
}

.quick-btn:hover {
    background: var(--pink-light);

    transform: translateY(-1px);
}

/* EMPTY */

.empty-state {
    padding: 30px 10px;

    text-align: center;

    color: #a0959b;

    font-size: 11px;
}

/* MODAL */

.modal-overlay {
    display: none;

    position: fixed;

    inset: 0;

    background: rgba(35,25,30,.38);

    z-index: 999;

    align-items: center;
    justify-content: center;
}

.modal-overlay.show {
    display: flex;
}

.modal {
    width: min(420px,90%);

    background: white;

    border-radius: 20px;

    padding: 24px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.22);

    animation: modalIn .2s ease;
}

@keyframes modalIn {

    from {
        opacity: 0;
        transform:
            translateY(10px)
            scale(.97);
    }

    to {
        opacity: 1;
        transform:
            translateY(0)
            scale(1);
    }
}

.modal-top {
    display: flex;

    justify-content: space-between;

    align-items: center;
}

.modal h3 {
    font-size: 18px;
}

.close-modal {
    width: 30px;
    height: 30px;

    border: none;

    background: var(--pink-light);

    border-radius: 9px;

    font-size: 18px;
}

.modal p {
    color: var(--gray);

    font-size: 12px;

    line-height: 1.6;

    margin: 12px 0 18px;
}

.modal-button {
    border: none;

    background: var(--pink);

    color: var(--black);

    padding: 10px 18px;

    border-radius: 10px;

    font-weight: 800;
}

/* RESPONSIVE */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .dashboard-layout {
        grid-template-columns: 1fr;
    }

    .stats-left {
        grid-template-columns:
            repeat(3,1fr);
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .bottom-row {
        grid-template-columns: 1fr;
    }

    .overview-grid {
        grid-template-columns:
            repeat(2,1fr);
    }
}

@media (max-width: 800px) {






    .search-box {
        width: 180px;
    }
}

@media (max-width: 600px) {

    .main {
        padding: 18px;
    }

    .topbar {
        flex-direction: column;

        gap: 15px;
    }

    .top-actions {
        width: 100%;
    }

    .search-box {
        flex: 1;
    }

    .stats-left {
        grid-template-columns:
            1fr 1fr;
    }

    .order-row {
        grid-template-columns:
            1.5fr .8fr .7fr;
    }

    .order-row > div:nth-child(2) {
        display: none;
    }

    .datetime {
        flex-wrap: wrap;
    }

    .overview-grid {
        grid-template-columns: 1fr;
    }
}

/* INNER PAGE */

.page-description {
    color: var(--gray);
    font-size: 11px;
    margin-top: 4px;
}

/* BOOKING TABLE */

.booking-table {
    width: 100%;
    overflow-x: auto;
}

.booking-row {
    min-width: 720px;

    display: grid;

    grid-template-columns:
        .8fr
        1fr
        1fr
        .9fr
        .9fr;

    align-items: center;

    gap: 12px;

    padding: 14px 8px;

    border-top: 1px solid var(--gray-light);

    font-size: 11px;
}

.booking-header {
    border-top: none;

    color: var(--gray);

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;
}

.booking-row strong {
    font-size: 11px;
}

/* completed orders table - hiwalay sa .booking-row kasi 6 columns ito (may Customer + Courier), 5 lang yung isa */

.completed-row {
    min-width: 760px;

    display: grid;

    grid-template-columns:
        .7fr
        1fr
        .8fr
        .9fr
        .8fr
        .8fr;

    align-items: center;

    gap: 12px;

    padding: 14px 8px;

    border-top: 1px solid var(--gray-light);

    font-size: 11px;
}

.completed-header {
    border-top: none;

    color: var(--gray);

    font-size: 9px;

    font-weight: 900;

    text-transform: uppercase;
}

.completed-row strong {
    font-size: 11px;
}

/* FULL CALENDAR */

.full-calendar-card {
    height: fit-content;
}

.calendar-page-controls {
    display: flex;

    align-items: center;

    justify-content: space-between;

    margin: 10px 0 25px;
}

.calendar-page-controls h3 {
    font-size: 18px;
}

.calendar-page-controls button {
    width: 42px;
    height: 42px;

    border: none;

    background: var(--pink-light);

    color: var(--pink-dark);

    border-radius: 12px;

    font-size: 25px;

    font-weight: 900;

    transition: .2s;
}

.calendar-page-controls button:hover {
    background: var(--pink);

    color: white;
}

.full-week,
.full-calendar-days {
    width: 100%;
}

.full-week div {
    font-size: 12px;
    font-weight: 800;
}

.full-calendar-days {
    gap: 6px;
}

.large-day {
    height: 46px;

    border: none;

    border-radius: 11px;

    font-size: 12px;
}

.large-day:hover {
    background: var(--pink-soft);
}

.large-day.today {
    background: var(--pink);
}

.large-day.booked {
    background: var(--pink-light);

    color: var(--pink-dark);
}

/* RESPONSIVE INNER PAGES */

@media (max-width: 800px) {

    .quotation-info {
        grid-template-columns: 1fr;
    }

    .full-calendar-card {
        overflow-x: auto;
    }

    .full-calendar-days,
    .full-week {
        min-width: 600px;
    }

}


/* Na-merge na dito ang dating hiwalay na #orderView ID-selector styles - iisang <style> block na lang ang pinagmumulan. */

/* VIEW TOGGLE + VERIFY/CANCEL ROW - toggle buttons (Customer Order / Payment) sa kaliwa, Verify/Cancel buttons sa kanan - magkabilang dulo sila sa parehong hilera. */

.detail-view-toggle-row{
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    flex-wrap: nowrap !important;
    gap: 12px;
    margin-bottom: 8px;
    padding-bottom: 0;
    border-bottom: none;
}

.detail-view-toggle-row .detail-view-toggle{
    display: flex !important;
    flex-direction: row !important;
    margin-bottom: 0 !important;
    flex: 0 0 auto !important;
}

/* verify/cancel buttons - override ang orders.css flex:1/width:100% para di lumaki/mag-watak-watak */
.detail-view-toggle-row .verify-actions{
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    gap: 8px !important;
    margin: 0 0 0 auto !important;
    padding-bottom: 0 !important;
    border-bottom: none !important;
    flex: 0 0 auto !important;
    width: auto !important;
}

.detail-view-toggle-row .verify-actions form{
    display: inline-flex !important;
    flex: 0 0 auto !important;
    margin: 0 !important;
    padding: 0 !important;
    width: auto !important;
}

.detail-view-toggle-row .verify-btn{
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: auto !important;
    height: auto !important;
    min-width: 0 !important;
    padding: 8px 16px !important;
    font-size: 13px !important;
    line-height: 1.2 !important;
    white-space: nowrap !important;
    border-radius: 8px !important;
}

/* kung sumikip ang screen, saka lang pumapayag mag-wrap ang toggle row */
@media (max-width: 560px){
    .detail-view-toggle-row{
        flex-wrap: wrap !important;
    }
    .detail-view-toggle-row .verify-actions{
        margin-left: 0 !important;
    }
}


/* Send Update pop up + progress history */
.progress-modal-top{ border-bottom:0 !important; padding-bottom:4px !important; margin-bottom:6px !important; }
.progress-modal-hint{ margin-bottom:8px !important; }
.pu-photo-picker{ position:relative; }
.pu-photo-input{ position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
.pu-photo-empty{
    display:flex !important; flex-direction:column; align-items:center; justify-content:center; gap:4px;
    margin:0 !important; min-height:150px; padding:18px; border:2px dashed #f3b6d4; border-radius:14px;
    background:#fff7fb; color:#b23e82 !important; font-size:13px !important; font-weight:700 !important; cursor:pointer; text-align:center;
}
.pu-photo-empty:hover{ background:#ffeef7; }
.pu-photo-empty small{ color:#9a8590; font-weight:500; font-size:11.5px; }
.pu-photo-plus{ width:34px; height:34px; border-radius:50%; background:#ffd6ea; color:#b23e82; display:flex; align-items:center; justify-content:center; font-size:22px; line-height:1; }
.pu-photo-preview{ display:flex; flex-direction:column; gap:8px; }
.pu-photo-preview[hidden], .pu-photo-empty[hidden]{ display:none !important; }
.pu-photo-preview img{ width:100%; max-height:260px; object-fit:contain; background:#faf5f8; border:1px solid #f1dbe7; border-radius:12px; cursor:zoom-in; }
.pu-photo-actions{ display:flex; gap:8px; }
.pu-photo-btn{ padding:7px 14px; border-radius:9px; border:1px solid #ecd5e1; background:#fff; color:#5b2a45; font-size:12px; font-weight:700; cursor:pointer; }
.pu-photo-btn:hover{ background:#fdf4f8; }
.pu-photo-btn-remove{ color:#dc2626; border-color:#f5c2c2; }
.pu-modal-actions{ display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:16px; }
.pu-btn{ height:42px; border:0; border-radius:10px; font-size:13.5px; font-weight:700; color:#fff; cursor:pointer;  transition:background-color .15s ease; }
.pu-btn-send{ background:#22a35a; }
.pu-btn-send:hover{ background:#1b8a4b; }
.pu-btn-cancel{ background:#dc3b3b; }
.pu-btn-cancel:hover{ background:#c02f2f; }

/* buong larawan ang kita (hindi na naka-crop) */
.progress-history-thumb{ height:auto !important; max-height:340px; object-fit:contain !important; background:#faf5f8; }
.progress-history-revision-request{ font-style:normal !important; font-size:12.5px !important; color:#3b1f2e !important; background:#fff4e5 !important; border:1px solid #f6d7a8; }
.progress-history-revision-request strong{ display:block; margin-bottom:3px; color:#8a4b12; font-size:11.5px; }

/* action buttons sa pinakababa, pantay ang laki */
.ab-bottom-actions{ margin-top:16px !important; }
.ab-bottom-actions .verify-btn{ height:42px; padding:0 12px !important; font-size:13px !important; }
.ab-bottom-actions .revision-decision-actions{ flex-basis:100% !important; display:grid !important; grid-template-columns:1fr 1fr; gap:10px !important; }
.ab-bottom-actions .revision-decision-actions .verify-btn{ width:100% !important; }
.progress-history-block{ border-top:0 !important; padding-top:0 !important; margin-top:12px !important; }
/* maayos na layout ng progress / revision sa Order Actions */
.progress-history-list{ gap:18px !important; }
.progress-history-item{ padding:0 !important; border:0 !important; background:none !important; gap:12px !important; }
.progress-history-thumb{ border-radius:14px !important; border:1px solid #f1dbe7; }
.progress-history-body{ display:flex !important; flex-direction:row !important; flex-wrap:wrap; align-items:center; column-gap:8px; row-gap:8px !important; }
.progress-history-body > .progress-history-caption,
.progress-history-body > .progress-history-revision-note{ flex-basis:100%; margin:0 !important; }
.progress-history-caption{ font-size:13.5px !important; font-weight:600; color:#3b1f2e; }
.progress-history-date{ font-size:11.5px !important; color:#8a7884; }
.progress-history-body .progress-status-badge{ margin:0 !important; font-size:11px !important; padding:3px 10px !important; }
.progress-history-revision-request{ padding:12px 14px !important; border-radius:12px !important; line-height:1.5; }
.progress-history-revision-request strong{ margin-bottom:4px !important; }
.ab-bottom-actions{ margin-top:18px !important; padding-top:16px !important; border-top:1px dashed #efd9e4 !important; row-gap:10px !important; }
.ab-bottom-actions > .verify-btn-reject:last-child{ margin-top:2px; }
.qv-summary-body:not(:has(.progress-history-list)) .ab-bottom-actions{ border-top:0 !important; padding-top:0 !important; margin-top:4px !important; }
.ab-bottom-actions > *{ align-self:stretch; }
.ab-bottom-actions .verify-btn{ display:flex; align-items:center; justify-content:center; height:42px !important; }
/* status sa history - plain text (walang background), maayos na pwesto;
   Send Update at ibang button: sakto ang haba, nasa gitna */
.progress-history-body .progress-status-text{ font-size:12px; font-weight:700; }
.progress-status-text.is-progress{ color:#2563eb; }
.progress-status-text.is-approved{ color:#16a34a; }
.progress-status-text.is-waiting{ color:#b7791f; }
.progress-status-text.is-declined{ color:#dc2626; }
main .qv-right-col .ab-bottom-actions{ justify-content:center !important; }
main .qv-right-col .ab-bottom-actions > *{ flex:0 0 auto !important; }
main .qv-right-col .ab-bottom-actions > .verify-btn,
main .qv-right-col .ab-bottom-actions > form > .verify-btn{ width:auto !important; min-width:160px; padding:0 28px !important; }
main .qv-right-col .ab-bottom-actions > .revision-decision-actions{ flex:1 1 100% !important; }
main .qv-right-col .ab-bottom-actions > .progress-status-badge{ align-self:center; }
html body main .qv-right-col .verify-actions.ab-bottom-actions > button.verify-btn,
html body main .qv-right-col .verify-actions.ab-bottom-actions > form > button.verify-btn,
html body main .qv-right-col .verify-actions.ab-bottom-actions > span.verify-btn{ width:auto !important; min-width:140px !important; padding:0 22px !important; flex:0 0 auto !important; }
html body main .qv-right-col .verify-actions.ab-bottom-actions > form{ flex:0 0 auto !important; width:auto !important; }
html body main .qv-right-col .verify-actions.ab-bottom-actions .revision-decision-actions .verify-btn{ width:100% !important; }
html body main .qv-right-col .verify-actions.ab-bottom-actions > .progress-status-badge{ background:none !important; padding:0 !important; flex:1 1 100% !important; text-align:center; font-size:12.5px !important; color:#b7791f !important; }
</style>

<style>
/* RIGHT-SIDE CONTAINERS - iisang border para sa lahat ng container sa
   right panel (pareho ng .qv-header-card / .qv-figure-card / .qv-box-card),
   para kita ang border ng "No Custom Box Added", summary, at iba pa. */
.qv-right-col .qv-box-card-empty,
.qv-right-col .qv-summary,
.qv-right-col .rf-box,
.qv-right-col .progress-history-item{
    border: 1.5px solid #C9BEC3 !important;
    border-radius: 20px !important;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25) !important;
    outline: none !important;
}
/* PARE-PAREHONG PAGITAN (16px) ng lahat ng container sa right panel -
   para walang magkadikit na box. */
.qv-right-col{ gap: 16px !important; }
.qv-right-col .qv-summary{ margin-top: 0 !important; }
.qv-right-col .qv-detail-col,
.qv-right-col .qv-figure-panels,
.qv-right-col .qv-viewer-grid,
.qv-right-col .progress-history-list{ gap: 16px !important; }
.qv-right-col .qv-figure-panel.active{ gap: 16px !important; }
.qv-right-col .qv-viewer-column{ display: flex; flex-direction: column; gap: 16px; }
.qv-right-col .qv-summary-body > :is(.staff-payment-details, .rf-box, .qv-price-breakdown, .qv-line-list, .qv-note-box, details)
    + :is(.staff-payment-details, .rf-box, .qv-price-breakdown, .qv-line-list, .qv-note-box, details){
    margin-top: 16px !important;
}
.qv-right-col .staff-payment-details + .staff-payment-details{ margin-top: 16px !important; }
</style>
<style>
/* [RIGHT PANEL] Ayos ng right-side container (owner/staff):
   - walang border at bg sa presyo, presyo nasa kanan
   - row: label kaliwa | value gitna | presyo kanan
   - Quotation / summary sa baba: may bg */
html body .app .qv-shell .qv-right-col .qv-detail-item-card {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    gap: 12px !important;
    padding: 9px 0 !important;
    border: 0 !important;
    background: none !important;
}
html body .app .qv-shell .qv-right-col .qv-detail-item-body {
    flex: 1 1 0 !important;
    min-width: 0 !important;
    display: grid !important;
    grid-template-columns: 150px minmax(0, 1fr) !important;
    align-items: center !important;
    gap: 0 !important;
}
html body .app .qv-shell .qv-right-col .qv-box-spec-grid > div {
    display: grid !important;
    grid-template-columns: 150px minmax(0, 1fr) 110px !important;
    align-items: center !important;
    column-gap: 12px !important;
    padding: 9px 0 !important;
    border: 0 !important;
    background: none !important;
}
html body .app .qv-shell .qv-right-col .qv-detail-item-label,
html body .app .qv-shell .qv-right-col .qv-spec-label {
    color: var(--p-muted) !important;
    font-size: 14px !important;
    font-weight: 500 !important;
}
html body .app .qv-shell .qv-right-col .qv-detail-item-value,
html body .app .qv-shell .qv-right-col .qv-spec-value {
    justify-self: start !important;
    text-align: left !important;
    color: var(--p-plum) !important;
    font-size: 14px !important;
    font-weight: 800 !important;
}
html body .app .qv-shell .qv-right-col .qv-detail-item-price,
html body .app .qv-shell .qv-right-col .qv-detail-item-price-muted,
html body .app .qv-shell .qv-right-col .qv-spec-price,
html body .app .qv-shell .qv-right-col .qv-box-spec-grid .qv-spec-price {
    flex: 0 0 110px !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    background: none !important;
    text-align: right !important;
    white-space: nowrap !important;
    color: var(--p-plum) !important;
    font-size: 14px !important;
    font-weight: 800 !important;
}
html body .app .qv-shell .qv-right-col .qv-box-spec-grid .qv-spec-price::before { content: none !important; }

/* Quotation / summary sa baba: may bg, pare-parehong heading */
/* label ng notes / reference: ibalik ang sariling style */
html body .app .qv-shell .qv-right-col .qv-notes-row .qv-spec-label,
html body .app .qv-shell .qv-right-col .qv-reference-label {
    color: var(--p-primary) !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    letter-spacing: .06em !important;
    text-transform: uppercase !important;
    margin: 0 0 6px !important;
}

html body .app .qv-shell .qv-right-col .qv-summary {
    background: #fff4f6 !important;
    border: 0 !important;
    border-radius: 14px !important;
    padding: 16px 18px !important;
}
html body .app .qv-shell .qv-right-col .qv-summary-head {
    margin: 0 0 10px !important;
    padding: 0 !important;
    border: 0 !important;
    background: none !important;
}
html body .app .qv-shell .qv-right-col .qv-summary-head h2 {
    margin: 0 !important;
    font-family: var(--font-body) !important;
    font-size: 12.5px !important;
    font-weight: 800 !important;
    letter-spacing: .06em !important;
    text-transform: uppercase !important;
    color: var(--p-primary) !important;
}
</style>
</head>


<body class="<?php echo $useVerificationLayout ? "quotation-page" : ""; ?>">


<div class="app">



<link rel="stylesheet" href="../Shared/receipt-panels.css?v=7">
<style>
/* ==========================================================
   Figurify redesign - Owner at Staff PAGE STYLES (hindi sidebar)
   (dating Shared/panel-pages.css - may sariling kopya na ang bawat owner/staff page): iisang topbar,
   font, kulay, cards, table, buttons at status chips.
   Sundin ang Design System: Fraunces (headings) + Plus Jakarta Sans (body)
   Primary #C2325F | Plum #4A1D3B | Blush #FFEDF2 | Cream #FFF8F6 | Coral #E8707E
   Success #2F8F67 | Warning #9A6408 | Danger #B83227

   Font at background ng page: nasa Shared/all-in-one.php (hindi dito). Kapag offline,
   awtomatikong Georgia / system font ang gagamitin - hindi masisira ang layout.
   ========================================================== */


:root {
    --p-pad: clamp(14px, 2.2vw, 28px);
    --p-primary: #c2325f;
    --p-primary-dark: #a82a52;
    --p-plum: #4a1d3b;
    --p-blush: #ffedf2;
    --p-cream: #fff6f4;
    --p-line: #f3dce3;
    --p-muted: #7c6670;
    --p-ok: #2f8f67;   --p-ok-bg: #dff3e8;
    --p-warn: #9a6408; --p-warn-bg: #fdefbf;
    --p-bad: #b83227;  --p-bad-bg: #fbdada;
    --p-ship: #3552a8; --p-ship-bg: #e3eafd;
}

html body {
    
    color: var(--p-plum);
    
}
body .app :is(button, input, select, textarea) {  }

/* ---------- TOPBAR (pareho sa lahat ng page) ---------- */
body .app > main.main { padding: 0 var(--p-pad) 22px !important; }

body .app > main.main > .topbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 14px;
    margin: 0 calc(var(--p-pad) * -1) 16px !important;
    padding: 8px var(--p-pad) !important;
    min-height: 56px !important;
    background: #ffffff;
    border-bottom: 1px solid var(--p-line);
    flex: 0 0 auto !important;   /* hindi lumiliit ang topbar kahit mahaba ang laman ng page - walang "galaw" kapag pumili ng row */
}

body .portal-title {
    margin: 0 !important;
    padding: 0 !important;
    
    font-size: 24px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    letter-spacing: 0 !important;
    color: var(--p-plum) !important;
}
body .portal-subtitle { margin: 4px 0 0 !important; font-size: 12.5px; color: var(--p-muted) !important; }

body .app .top-actions { display: flex; align-items: center; gap: 14px; }

@media (max-width: 800px) {
    body .portal-title { font-size: 20px !important; }
}

/* ---------- CARDS ---------- */
body .app :is(.card:not(.qv-workspace), .st-card) {
    background: #ffffff;
    border: 1px solid var(--p-line) !important;
    border-radius: 22px !important;
    box-shadow: 0 10px 28px -18px rgba(74, 29, 59, .28) !important;
}

/* ---------- TABLES (qv / user / activity) ---------- */
body .app :is(.user-table, .activity-table) thead th {
    background: transparent !important;
    color: var(--p-muted) !important;
    border: 0 !important;
    border-bottom: 1.5px solid #ecd9e0 !important;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}
body .app :is(.user-table, .activity-table) tbody td {
    border: 0 !important;
    border-bottom: 1px solid #f6e8ed !important;
    color: var(--p-plum);
}
body .app :is(.user-table, .activity-table) tbody tr:hover td { background: #fff8fa; }

/* pare-parehong taas ng header/row ng listahan (hindi gumagalaw kapag lumipat ng page) */
body .app .qv-table thead th {
    height: 36px;
    padding: 0 12px !important;
    line-height: 1.2;
    vertical-align: middle;
}
body .app .qv-table tbody td {
    height: 46px;
    padding: 0 12px !important;
    line-height: 1.3;
    vertical-align: middle;
}
body .app .qv-table tbody td :is(.order-status, .pay-badge) {
    display: inline-block;
    padding: 4px 10px !important;
    font-size: 11px !important;
    line-height: 1.2;
    white-space: nowrap;
}

/* ---------- STATUS CHIPS (Design System: Paid / Pending / Overdue / Shipped) ---------- */
body .app :is(.order-status, .status) { border-radius: 999px; font-weight: 700; box-shadow: none !important; }

body .app :is(.order-status, .status):is(.status-pending, .status-awaiting_payment, .status-awaiting_balance) { background: var(--p-warn-bg) !important; color: var(--p-warn) !important; }
body .app :is(.order-status, .status):is(.status-quoted, .status-to_verify, .status-for_approval, .status-to_verify_balance, .status-for_balance_approval, .status-completed) { background: var(--p-ok-bg) !important; color: var(--p-ok) !important; }
body .app :is(.order-status, .status):is(.status-processing) { background: var(--p-blush) !important; color: var(--p-primary) !important; }
body .app :is(.order-status, .status):is(.status-to_ship, .status-shipped) { background: var(--p-ship-bg) !important; color: var(--p-ship) !important; }
body .app :is(.order-status, .status):is(.status-cancelled, .status-for_refund) { background: var(--p-bad-bg) !important; color: var(--p-bad) !important; }
body .app :is(.order-status, .status):is(.status-refunded) { background: #efe3f3 !important; color: var(--p-plum) !important; }

/* ---------- FILTER + SEARCH ---------- */
body .app .rf-filter-trigger {
    height: 44px;
    padding: 0 16px !important;
    border: 1px solid var(--p-line) !important;
    border-radius: 14px !important;
    background: #ffffff !important;
    color: var(--p-muted) !important;
    font-size: 14px;
    font-weight: 600;
    box-shadow: none !important;
}
body .app main input.user-search {
    width: 240px;
    height: 44px;
    padding: 0 16px 0 40px;
    border: 1px solid var(--p-line);
    border-radius: 14px;
    background: #ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239a8c94' stroke-width='2' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E") no-repeat 14px center / 16px 16px;
    font-size: 14px;
    color: var(--p-plum);
    outline: none;
}
body .app main input.user-search:focus { border-color: var(--p-primary); }

/* ---------- TABS (Content Management) - pill, kapareho ng Figure tabs ---------- */
body .app .cm-tabs .cm-tab {
    padding: 8px 18px !important;
    border: 1px solid var(--p-line) !important;
    border-radius: 999px !important;
    background: #ffffff !important;
    color: var(--p-muted) !important;
    font-size: 13px;
    font-weight: 700;
    box-shadow: none !important;
    text-decoration: none;
}
body .app .cm-tabs .cm-tab:hover { border-color: #e9a6bd !important; color: var(--p-primary) !important; transform: none; }
body .app .cm-tabs .cm-tab.active { background: var(--p-primary) !important; border-color: var(--p-primary) !important; color: #ffffff !important; }

/* ---------- RIGHT PANEL BUTTONS: Primary (tuloy) + Secondary (tanggihan) ---------- */
body .app .qv-right-col .verify-actions { display: flex; flex-wrap: wrap; gap: 10px; }
body .app .qv-right-col .verify-actions > * { flex: 1 1 calc(50% - 5px); min-width: 0; }
body .app .qv-right-col .verify-actions > form > button,
body .app .qv-right-col .verify-actions > button { width: 100%; }
body .app .qv-right-col .verify-actions > .revision-decision-actions { flex-basis: 100%; display: flex; gap: 10px; }
body .app .qv-right-col .revision-decision-actions > * { flex: 1 1 0; }
body .app .qv-right-col .revision-decision-actions > form > button { width: 100%; }

body .app .qv-right-col :is(.verify-btn-approve, .qv-send-btn, .qv-ship-form-submit, .qv-manage-btn, .rf-btn-primary) {
    height: 44px;
    border: 1.5px solid var(--p-primary) !important;
    border-radius: 999px !important;
    background: var(--p-primary) !important;
    color: #ffffff !important;
    font-size: 14px;
    font-weight: 700;
}
body .app .qv-right-col :is(.verify-btn-approve, .qv-send-btn, .qv-ship-form-submit, .qv-manage-btn, .rf-btn-primary):hover {
    background: var(--p-primary-dark) !important;
    border-color: var(--p-primary-dark) !important;
}
body .app .qv-right-col :is(.verify-btn-reject, .rf-btn-ghost) {
    height: 44px;
    border: 1.5px solid var(--p-primary) !important;
    border-radius: 999px !important;
    background: #ffffff !important;
    color: var(--p-primary) !important;
    font-size: 14px;
    font-weight: 700;
}
body .app .qv-right-col :is(.verify-btn-reject, .rf-btn-ghost):hover { background: var(--p-blush) !important; }

/* ---------- PAYMENT & SHIPPING / REFUND rows: label kaliwa, value kanan ---------- */
body .app .staff-payment-details { padding: 0 !important; border: 0 !important; background: transparent !important; box-shadow: none !important; }
body .app .staff-payment-details-title {
    margin: 0 0 8px;
    font-size: 12.5px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--p-primary);
}
body .app .staff-payment-details-grid { display: flex !important; flex-direction: column !important; gap: 0 !important; }
body .app .staff-payment-details-grid > span {
    display: grid;
    grid-template-columns: minmax(96px, 36%) 1fr;
    gap: 10px;
    padding: 7px 0;
    font-size: 14px;
    font-weight: 500;
    color: var(--p-plum);
    word-break: break-word;
}
body .app .staff-payment-details-grid > span > strong { font-weight: 500; color: var(--p-muted); }

/* ---------- pare-parehong pagitan ng mga container sa kanang panel ---------- */
body .app .qv-right-col .qv-detail-col { padding: 0; }

/* ==========================================================
   LISTA (kaliwa) + DETALYE (kanan) - All Bookings, Quotation, Verification, atbp.
   ========================================================== */

body .app .qv-workspace { padding: 0 !important; }
body .app .qv-shell {
    grid-template-columns: minmax(0, 1fr) minmax(400px, 470px);
    gap: 20px;
}
@media (max-width: 1180px) {
    body .app .qv-shell { grid-template-columns: 1fr; }
}

/* ---------- two white cards ---------- */
body .app .qv-list-panel,
body .app .qv-right-col {
    background: #ffffff !important;
    border: 1px solid var(--p-line) !important;
    border-radius: 22px !important;
    box-shadow: 0 10px 28px -18px rgba(74, 29, 59, .28) !important;
}
body .app .qv-list-panel { padding: 18px !important; }
body .app .qv-right-col { padding: 22px 24px 24px !important; gap: 14px !important; }

/* ---------- left: toolbar ---------- */
body .app .qv-toolbar { gap: 12px; margin-bottom: 14px; align-items: stretch; }

body .app .qv-filter-trigger {
    height: 44px;
    padding: 0 16px !important;
    border: 1px solid var(--p-line) !important;
    border-radius: 14px !important;
    background: #ffffff !important;
    color: var(--p-muted) !important;
    font-size: 14px;
    font-weight: 600;
    box-shadow: none !important;
}
body .app .qv-filter-trigger:hover { border-color: #e9a6bd !important; }

body .app .qv-search {
    height: 44px;
    padding: 0 16px;
    border: 1px solid var(--p-line);
    border-radius: 14px;
    background: #ffffff;
}
body .app .qv-search input { font-size: 14px; color: var(--p-plum); }

/* ---------- left: table ---------- */
body .app .qv-table thead th {
    background: transparent !important;
    color: var(--p-muted) !important;
    border: 0 !important;
    border-bottom: 1.5px solid #ecd9e0 !important;
    font-size: 11px !important;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}
body .app .qv-table tbody td {
    border: 0 !important;
    border-bottom: 1px solid #f6e8ed !important;
    font-size: 12.5px !important;
    color: var(--p-plum);
}
body .app .qv-table tbody tr:hover td { background: #fff8fa; }
body .app .qv-table tbody tr.active td { background: #ffe9ee !important; }
body .app .qv-cell-id { font-weight: 800; }
body .app .qv-table tbody td :is(.order-status, .pay-badge) {
    border-radius: 999px !important;
    font-weight: 700;
}
body .app .qv-list-footer { padding-top: 12px; font-size: 12px; color: var(--p-muted); }
body .app .qv-empty { color: var(--p-muted); }

/* ---------- right: head (title + status) ---------- */
body .app .qv-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
body .app .qv-panel-title {
    margin: 0;
    
    font-size: 26px;
    font-weight: 700;
    color: var(--p-plum);
}
body .app .qv-panel-head .order-status {
    padding: 5px 14px !important;
    border-radius: 999px !important;
    font-size: 11.5px !important;
    font-weight: 700;
}

/* inner cards become plain sections of the single right card */
body .app .qv-right-col .qv-header-card,
body .app .qv-right-col .qv-figure-card,
body .app .qv-right-col .qv-box-card,
body .app .qv-right-col .qv-box-card-empty,
body .app .qv-right-col .qv-summary {
    background: transparent !important;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
}
body .app .qv-right-col .qv-detail-col { padding: 0 !important; gap: 14px !important; }
body .app .qv-right-col .qv-box-card,
body .app .qv-right-col .qv-summary {
    padding-top: 16px !important;
    border-top: 1px solid #f3e6ea !important;
}

/* section headings */
body .app .qv-details-head,
body .app .qv-box-info-head,
body .app .qv-summary-head h2 {
    margin: 0 0 8px;
    
    font-size: 12.5px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--p-primary);
}
body .app .qv-summary-head {
    background: transparent !important;
    padding: 0 !important;
    border-radius: 0 !important;
}
body .app .qv-summary-body { padding: 0 !important; }

/* figure tabs */
body .app .qv-figure-tabs { gap: 8px; margin-bottom: 12px; }
body .app .qv-figure-tab-pill {
    padding: 8px 18px;
    border: 1px solid var(--p-line);
    border-radius: 999px;
    background: #ffffff;
    color: var(--p-muted);
    font-size: 13px;
    font-weight: 700;
}
body .app .qv-figure-tab-pill:hover { border-color: #e9a6bd; color: var(--p-primary); }
body .app .qv-figure-tab-pill.active {
    background: var(--p-primary);
    border-color: var(--p-primary);
    color: #ffffff;
}

/* ---------- key / value rows ---------- */
body .app .qv-detail-item-list {
    display: flex !important;
    flex-direction: column !important;
    gap: 0 !important;
}
body .app .qv-detail-item-card {
    display: grid !important;
    grid-template-columns: auto 1fr auto;
    align-items: center !important;
    gap: 0 10px !important;
    padding: 9px 0 !important;
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
}
body .app .qv-detail-item-image { display: none !important; }
body .app .qv-detail-item-body { display: contents !important; }
body .app .qv-detail-item-label {
    grid-column: 1;
    font-size: 14px;
    font-weight: 500;
    color: var(--p-muted);
}
body .app .qv-detail-item-value {
    grid-column: 2;
    justify-self: end;
    text-align: right;
    font-size: 14px;
    font-weight: 800;
    color: var(--p-plum);
}
body .app .qv-detail-item-price {
    grid-column: 3;
    padding: 2px 9px;
    border: 1px solid #ecd9e0;
    border-radius: 999px;
    background: #fffafb;
    font-size: 11px;
    font-weight: 700;
    color: var(--p-muted);
}

/* box spec rows */
body .app .qv-box-spec-grid {
    display: flex !important;
    flex-direction: column !important;
    gap: 0 !important;
}
body .app .qv-box-spec-grid > div {
    display: flex !important;
    align-items: baseline;
    gap: 8px;
    padding: 8px 0;
    grid-column: auto !important;
}
body .app .qv-spec-label {
    margin-right: auto;
    font-size: 14px;
    font-weight: 500;
    color: var(--p-muted);
}
body .app .qv-spec-value {
    font-size: 14px;
    font-weight: 800;
    color: var(--p-plum);
    text-align: right;
}
body .app .qv-spec-price { font-size: 13px; font-weight: 800; color: var(--p-plum); }

/* reference images */
body .app .qv-reference-label,
body .app .qv-notes-row .qv-spec-label {
    margin: 10px 0 8px;
    font-size: 13px;
    font-weight: 800;
    color: var(--p-plum);
}
body .app .qv-reference-thumbs { gap: 8px; }
body .app .qv-ref-thumb {
    width: 70px !important;
    height: 70px !important;
    border: 1px solid var(--p-line) !important;
    border-radius: 14px !important;
    overflow: hidden;
}

/* customer notes */
body .app .qv-note-box {
    padding: 14px 16px !important;
    border: 0 !important;
    border-radius: 14px !important;
    background: #fff4f6 !important;
    font-size: 14px;
    color: var(--p-plum);
}

/* ---------- quotation lines ---------- */
body .app .qv-line-list { gap: 10px; font-weight: 500; }
body .app .qv-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
body .app .qv-line-label { font-size: 14px; font-weight: 500; color: var(--p-muted); }
body .app .qv-line-value { font-size: 14px; font-weight: 800; color: var(--p-plum); }

body .app .qv-line-input-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: 150px;
    height: 46px;
    padding: 0 14px;
    border: 1px solid #ecd9e0;
    border-radius: 14px;
    background: #ffffff;
    color: var(--p-muted);
}
body .app .qv-line-input-wrap:focus-within { border-color: var(--p-primary); }
body .app .qv-line-input {
    width: 100%;
    border: 0 !important;
    outline: none;
    background: transparent !important;
    font-size: 15px;
    color: var(--p-muted);
}

body .app .qv-divider {
    border: 0;
    border-top: 1.5px dashed #ecd9e0;
    margin: 14px 0;
}
body .app .qv-total-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
body .app .qv-total-row > span { font-size: 14px; font-weight: 800; color: var(--p-plum); }
body .app .qv-total-row > strong {
    
    font-size: 30px;
    font-weight: 700;
    color: var(--p-primary);
}
body .app .qv-summary-hint {
    margin: 12px 0 0;
    font-size: 12px;
    line-height: 1.6;
    color: var(--p-muted);
}

/* ---------- buttons: Decline (outline) + Send Quotation (filled) ---------- */
body .app .qv-quote-actions {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-start;
    gap: 10px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #f3e6ea;
}
body .app .qv-right-col .qv-quote-actions > * { flex: 0 0 auto !important; width: auto !important; }
body .app .qv-right-col .qv-quote-actions button {
    height: 44px;
    padding: 0 22px !important;
    border-radius: 999px !important;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}
body .app .qv-right-col .qv-send-btn {
    background: var(--p-primary) !important;
    border: 1.5px solid var(--p-primary) !important;
    color: #ffffff !important;
}
body .app .qv-right-col .qv-send-btn:hover { background: #a82a52 !important; border-color: #a82a52 !important; }
body .app .qv-right-col .verify-btn-reject {
    background: #ffffff !important;
    border: 1.5px solid var(--p-primary) !important;
    color: var(--p-primary) !important;
}
body .app .qv-right-col .verify-btn-reject:hover { background: var(--p-blush) !important; }

/* ---------- decline pop-up ---------- */
body .decline-modal-box {
    border-radius: 22px;
    padding: 28px;
    box-shadow: 0 24px 60px -18px rgba(45, 14, 32, .5);
}
body .decline-modal-title {
    
    font-size: 24px;
    color: var(--p-plum);
}
body .decline-modal-desc { font-size: 13px; color: var(--p-muted); }
body .decline-reason-select,
body .decline-reason-other {
    height: 48px;
    border: 1px solid #ecd9e0;
    border-radius: 14px;
    background: #ffffff;
    color: var(--p-plum);
    font-size: 14px;
}
body .decline-reason-other { height: auto; min-height: 48px; }
body .decline-modal-actions { justify-content: flex-end; gap: 10px; }
body .decline-modal-btn { height: 40px; padding: 0 20px; border-radius: 12px; font-weight: 700; }
body .decline-modal-btn-cancel { background: var(--p-blush); border: 0; color: var(--p-primary); }
body .decline-modal-btn-send { background: var(--p-primary); border: 0; color: #ffffff; }


/* ==========================================================
   LISTAHAN SA KALIWA - pare-pareho sa LAHAT ng owner/staff page
   (All Bookings, Quotation, Verification, For Approval, Active Bookings,
   Shipments, Completed, Cancelled, Refunds, Payments)
   - Filter sa kaliwa, Search sa kanan (sakto ang haba)
   - Kasya ang buong table sa card: walang pahalang na scroll
   ========================================================== */

/* toolbar */
body .app .qv-list-panel .qv-toolbar {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 10px !important;
    margin: 0 0 14px !important;
}
body .app .qv-list-panel .qv-toolbar > :is(.qv-filter-dropdown, .qv-tabs, .ab-status-tabs, .qv-status-tabs) {
    flex: 0 0 auto;
    margin: 0 auto 0 0 !important;   /* filter / tabs: laging nasa kaliwa */
}
body .app .qv-list-panel .qv-filter-trigger {
    min-width: 0 !important;
    height: 42px !important;
    padding: 0 14px !important;
    gap: 8px;
    border: 1px solid #ecd9e0 !important;
    border-radius: 12px !important;
    background: #ffffff !important;
    color: var(--p-plum) !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    white-space: nowrap;
}
body .app .qv-list-panel .qv-search {
    flex: 0 1 280px !important;      /* search: laging nasa kanan, sakto ang haba */
    width: 280px !important;
    max-width: 55% !important;
    min-width: 160px;
    height: 42px !important;
    margin: 0 0 0 auto !important;
    padding: 0 14px !important;
    display: flex !important;
    align-items: center;
    gap: 8px;
    border: 1px solid #ecd9e0 !important;
    border-radius: 12px !important;
    background: #ffffff !important;
}
body .app .qv-list-panel .qv-search svg { flex: 0 0 auto; width: 14px; height: 14px; color: #9a8c94; }
body .app .qv-list-panel .qv-search input {
    flex: 1 1 auto;
    width: 100% !important;
    min-width: 0;
    height: 100%;
    padding: 0 !important;
    border: 0 !important;
    outline: none;
    background: transparent !important;
    font-size: 13.5px !important;
    color: var(--p-plum) !important;
}
body .app .qv-list-panel .qv-search input::placeholder { color: #8f7d86; }
body .app .qv-list-panel .qv-search:focus-within { border-color: var(--p-primary) !important; }

/* table - kasya sa card */
body .app .qv-list-panel .qv-list {
    overflow-x: hidden !important;
    overflow-y: auto;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
}
body .app .qv-list-panel .qv-table {
    width: 100% !important;
    min-width: 0 !important;
    table-layout: auto !important;
    border-collapse: collapse;
}
body .app .qv-list-panel .qv-table :is(th, td) {
    width: auto !important;
    min-width: 0 !important;
    max-width: none !important;
    white-space: normal !important;   /* bumababa sa 2 linya kapag kulang ang lapad */
    overflow-wrap: anywhere;
    overflow: visible !important;
    text-overflow: clip !important;
}
body .app .qv-list-panel .qv-table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    height: 34px !important;
    padding: 0 5px !important;
    background: #ffffff !important;
    border: 0 !important;
    border-bottom: 1.5px solid #ecd9e0 !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
    letter-spacing: .04em !important;
    line-height: 1.2;
    color: var(--p-muted) !important;
    text-transform: uppercase;
    overflow-wrap: normal;
}
body .app .qv-list-panel .qv-table tbody td {
    height: 40px !important;
    padding: 6px 5px !important;
    border: 0 !important;
    border-bottom: 1px solid #f6e8ed !important;
    font-size: 11.5px !important;
    font-weight: 500;
    line-height: 1.3 !important;
    color: var(--p-plum) !important;
    vertical-align: middle;
}
body .app .qv-list-panel .qv-table tbody td:first-child { font-weight: 800 !important; white-space: nowrap !important; }
body .app .qv-list-panel .qv-table tbody td :is(.order-status, .status, .op-chip, .pay-badge) {
    display: inline-block;
    max-width: none !important;
    padding: 3px 8px !important;
    font-size: 10.5px !important;
    line-height: 1.3 !important;
    white-space: nowrap !important;
}
body .app .qv-list-panel .qv-table tbody tr { cursor: pointer; }
body .app .qv-list-panel .qv-table tbody tr:hover td { background: #fff8fa !important; }
body .app .qv-list-panel .qv-table tbody tr.active td { background: #ffe9ee !important; }

body .app .qv-list-panel .qv-list-footer {
    padding: 12px 0 0 !important;
    text-align: left !important;
    font-size: 12px !important;
    color: var(--p-muted) !important;
}

@media (max-width: 600px) {
    body .app .qv-list-panel .qv-toolbar { flex-wrap: wrap !important; }
    body .app .qv-list-panel .qv-search { flex: 1 1 100% !important; width: 100% !important; max-width: none !important; }
}
</style>
<style>
/* [VERIFICATION] Verification + For Approval styles - nasa page na ito lang, hindi sa Shared. */
/* ==========================================================
   Figurify redesign - VERIFICATION + FOR APPROVAL (owner at staff Verification)
   I-link AFTER receipt-panels.css at panel-pages.css, sa dalawang
   page lang. Ino-override nito ang lumang "resibo" look ng
   receipt-panels.css (pink na banda, 12px na text, putol-putol na guhit)
   para sundin ang Design System. Hindi ginagalaw ang receipt-panels.css,
   kaya buo pa rin ang My Orders at ibang page.
   ========================================================== */

/* ---------- toolbar: filter kaliwa, search sumasakop sa natitira ---------- */
body .app .qv-toolbar { justify-content: flex-start; flex-wrap: nowrap; }
body .app .qv-toolbar .qv-search { flex: 1 1 auto; width: auto; min-width: 0; }

/* ---------- filter dropdown ---------- */
body .app .qv-filter-dropdown { position: relative; flex: 0 0 auto; }
body .app .qv-filter-trigger { display: inline-flex; align-items: center; gap: 10px; white-space: nowrap; cursor: pointer; }
body .app .qv-filter-trigger:focus-visible,
body .app .qv-filter-option:focus-visible { outline: 2px solid var(--p-primary); outline-offset: 2px; }
body .app .qv-filter-caret { width: 12px; height: 12px; flex: 0 0 auto; transition: transform .15s ease; }
body .app .qv-filter-dropdown.open .qv-filter-caret { transform: rotate(180deg); }
body .app .qv-filter-menu {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    z-index: 30;
    flex-direction: column;
    gap: 2px;
    min-width: 200px;
    padding: 6px;
    background: #ffffff;
    border: 1px solid var(--p-line);
    border-radius: 14px;
    box-shadow: 0 16px 34px -16px rgba(74, 29, 59, .4);
}
body .app .qv-filter-dropdown.open .qv-filter-menu { display: flex; }
body .app .qv-filter-option {
    display: block;
    padding: 9px 12px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    color: var(--p-plum);
    white-space: nowrap;
    text-decoration: none;
    cursor: pointer;
}
body .app .qv-filter-option:hover { background: #fff8fa; }
body .app .qv-filter-option.active { background: var(--p-blush); color: var(--p-primary); }

body .app .qv-row:focus-visible { outline: 2px solid var(--p-primary); outline-offset: -2px; }

/* ==========================================================
   RIGHT PANEL - body .app .qv-shell .qv-right-col = body .app .qv-shell .qv-right-col
   ========================================================== */

/* pare-parehong 24px na pagitan ng mga section, wala nang guhit sa pagitan */
body .app .qv-shell .qv-right-col { gap: 24px !important; }
body .app .qv-shell .qv-right-col > * + *,
body .app .qv-shell .qv-right-col .qv-detail-col > * + *,
body .app .qv-shell .qv-right-col .qv-viewer-grid > * + *,
body .app .qv-shell .qv-right-col .qv-summary {
    margin-top: 0 !important;
    padding-top: 0 !important;
    border-top: 0 !important;
}
body .app .qv-shell .qv-right-col .qv-detail-col { display: flex; flex-direction: column; gap: 24px !important; }
body .app .qv-shell .qv-right-col .qv-figure-panels { gap: 24px !important; }
body .app .qv-shell .qv-right-col .qv-figure-panel.active { gap: 24px !important; }
body .app .qv-shell .qv-right-col .qv-viewer-grid { display: flex !important; flex-direction: column; gap: 24px !important; }
body .app .qv-shell .qv-right-col .qv-viewer-column { display: flex !important; flex-direction: column; gap: 24px !important; }
body .app .qv-shell .qv-right-col .qv-box-card { padding-top: 0 !important; border-top: 0 !important; }

/* ---------- section headings: walang pink na banda ---------- */
body .app .qv-shell .qv-right-col .qv-details-head,
body .app .qv-shell .qv-right-col .qv-box-info-head,
body .app .qv-shell .qv-right-col .qv-summary-head,
body .app .qv-shell .qv-right-col .staff-payment-details-title,
body .app .qv-shell .qv-right-col .dressup-design-title,
body .app .qv-shell .qv-right-col .dressup-design:not(.dressup-viewer-block) > .dressup-design-title {
    display: flex !important;
    align-items: center;
    margin: 0 0 8px !important;
    padding: 0 !important;
    background: none !important;
    border: 0 !important;
    border-radius: 0 !important;
    color: var(--p-primary) !important;
    font-size: 12.5px !important;
    font-weight: 800 !important;
    letter-spacing: .06em !important;
    text-transform: uppercase !important;
    line-height: 1.3 !important;
}

/* figure tabs sa taas, tapos ang "Figure N Information" */
body .app .qv-shell .qv-right-col .qv-figure-card { gap: 0; }
body .app .qv-shell .qv-right-col .qv-figure-card > .qv-figure-tabs { order: 0; margin: 0 0 16px !important; }
body .app .qv-shell .qv-right-col .qv-figure-card > .qv-details-head { order: 1; }
body .app .qv-shell .qv-right-col .qv-figure-card > * + * { margin-top: 0 !important; }
body .app .qv-shell .qv-right-col .qv-figure-card > .qv-figure-body { order: 2; }
body .app .qv-shell .qv-right-col .qv-figure-card > .qv-notes-row { order: 3; margin-top: 18px !important; }
body .app .qv-shell .qv-right-col .qv-figure-card > .qv-total-breakdown { order: 4; }

/* ---------- rows: label kaliwa, value kanan, presyo = chip ---------- */
body .app .qv-shell .qv-right-col .qv-detail-item-card {
    display: grid !important;
    grid-template-columns: auto minmax(0, 1fr) auto !important;
    align-items: center !important;
    gap: 0 10px !important;
    padding: 9px 0 !important;
    border: 0 !important;
}
body .app .qv-shell .qv-right-col .qv-detail-item-body { display: contents !important; }
body .app .qv-shell .qv-right-col .qv-detail-item-label {
    grid-column: 1;
    color: var(--p-muted) !important;
    font-size: 14px !important;
    font-weight: 500 !important;
}
body .app .qv-shell .qv-right-col .qv-detail-item-value {
    grid-column: 2;
    justify-self: end;
    text-align: right;
    color: var(--p-plum) !important;
    font-size: 14px !important;
    font-weight: 800 !important;
}
body .app .qv-shell .qv-right-col .qv-detail-item-price {
    grid-column: 3;
    padding: 2px 9px;
    border: 1px solid #ecd9e0;
    border-radius: 999px;
    background: #fffafb;
    color: var(--p-muted) !important;
    font-size: 11px !important;
    font-weight: 700 !important;
}

/* box spec rows: label kaliwa, value (+ presyo) kanan */
body .app .qv-shell .qv-right-col .qv-box-spec-grid > div {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) auto auto !important;
    gap: 0 !important;
    align-items: baseline !important;
    padding: 8px 0 !important;
    border: 0 !important;
}
body .app .qv-shell .qv-right-col .qv-box-spec-grid .qv-spec-label {
    color: var(--p-muted) !important;
    font-size: 14px !important;
    font-weight: 500 !important;
}
body .app .qv-shell .qv-right-col .qv-box-spec-grid .qv-spec-value {
    color: var(--p-plum) !important;
    font-size: 14px !important;
    font-weight: 800 !important;
    text-align: right;
}
body .app .qv-shell .qv-right-col .qv-box-spec-grid .qv-spec-price {
    margin-left: 8px !important;
    color: var(--p-plum) !important;
    font-size: 14px !important;
    font-weight: 800 !important;
}
body .app .qv-shell .qv-right-col .qv-box-spec-grid .qv-spec-price::before { content: "\00B7\00A0"; color: var(--p-muted); }
body .app .qv-shell .qv-right-col .qv-box-empty-title { padding: 0 !important; color: var(--p-muted) !important; font-size: 14px !important; font-weight: 500 !important; }

/* payment / shipping / refund: label kaliwa, value sa pangalawang column */
body .app .qv-shell .qv-right-col .staff-payment-details-grid > span {
    grid-template-columns: minmax(110px, 38%) minmax(0, 1fr) !important;
    gap: 10px !important;
    padding: 7px 0 !important;
    border: 0 !important;
    color: var(--p-plum) !important;
    font-size: 14px !important;
    font-weight: 500 !important;
}
body .app .qv-shell .qv-right-col .staff-payment-details-grid > span > strong {
    color: var(--p-muted) !important;
    font-size: 14px !important;
    font-weight: 500 !important;
}

/* ---------- reference photos + customer notes ---------- */
body .app .qv-shell .qv-right-col .qv-reference-row { margin-top: 14px !important; }
body .app .qv-shell .qv-right-col .qv-reference-label,
body .app .qv-shell .qv-right-col .qv-notes-row .qv-spec-label {
    margin: 0 0 8px !important;
    color: var(--p-plum) !important;
    font-size: 13px !important;
    font-weight: 800 !important;
    letter-spacing: 0 !important;
    text-transform: none !important;
}
body .app .qv-shell .qv-right-col .qv-notes-row {
    margin: 0 !important;
    padding: 14px 16px !important;
    border: 0 !important;
    border-radius: 14px !important;
    background: #fff4f6 !important;
}
body .app .qv-shell .qv-right-col .qv-notes-row .qv-spec-label { margin-bottom: 4px !important; }
body .app .qv-shell .qv-right-col .qv-notes-row .qv-note-box { color: var(--p-plum) !important; font-size: 14px !important; line-height: 1.55 !important; }

/* ---------- Dress Up: figure info -> 3D -> box ---------- */
body .app .qv-shell .qv-right-col .qv-viewer-grid.dressup-grid > :nth-child(1) { order: 1; }
body .app .qv-shell .qv-right-col .qv-viewer-grid.dressup-grid > .dressup-3d-column { order: 2; }
body .app .qv-shell .qv-right-col .qv-viewer-grid.dressup-grid > :nth-child(2):not(.dressup-3d-column) { order: 3; }
body .app .qv-shell .qv-right-col .dressup-pick-label { color: var(--p-muted) !important; font-size: 14px !important; font-weight: 500 !important; }
body .app .qv-shell .qv-right-col .dressup-pick-value { color: var(--p-plum) !important; font-size: 14px !important; font-weight: 800 !important; }

/* ---------- total breakdown ---------- */
body .app .qv-shell .qv-right-col .qv-total-breakdown { margin-top: -8px !important; padding-top: 10px !important; border-top: 1px solid #f3e6ea !important; }
body .app .qv-shell .qv-right-col .qv-sub-line {
    padding: 4px 0 !important;
    border: 0 !important;
    color: var(--p-muted) !important;
    font-size: 14px !important;
}
body .app .qv-shell .qv-right-col .qv-sub-line span:last-child { color: var(--p-plum) !important; font-weight: 700; }
body .app .qv-shell .qv-right-col .qv-sub-line.qv-sub-rush span:first-child,
body .app .qv-shell .qv-right-col .qv-sub-line.qv-sub-rush span:last-child { color: var(--p-muted) !important; }
body .app .qv-shell .qv-right-col .qv-total-line { padding: 8px 0 0 !important; border: 0 !important; }
body .app .qv-shell .qv-right-col .qv-total-line-label { color: var(--p-plum) !important; font-size: 14px !important; font-weight: 800 !important; }
body .app .qv-shell .qv-right-col .qv-total-line-value {
    color: var(--p-plum) !important;
    
    font-size: 20px !important;
    font-weight: 700 !important;
}

/* ---------- summary: halagang sisingilin + mga button ---------- */
body .app .qv-shell .qv-right-col .qv-summary .qv-price-breakdown { margin: 0 !important; }
body .app .qv-shell .qv-right-col .qv-summary .qv-price-row { padding: 4px 0 !important; border: 0 !important; }
body .app .qv-shell .qv-right-col .qv-summary .qv-price-row > span {
    color: var(--p-muted);
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0;
    text-transform: none;
}
body .app .qv-shell .qv-right-col .qv-summary .qv-price-row > strong {
    color: var(--p-primary);
    
    font-size: 24px;
    font-weight: 700;
    line-height: 1.1;
}

/* Reject (outline) kaliwa, Verify / Approve (filled) kanan */
body .app .qv-shell .qv-right-col .verify-actions {
    flex-direction: row-reverse;
    justify-content: flex-start;
    margin-top: 16px !important;
    padding-top: 16px !important;
    border-top: 1px solid #f3e6ea !important;
}
body .app .qv-shell .qv-right-col .verify-actions > * { flex: 0 0 auto; }
body .app .qv-shell .qv-right-col .verify-actions > form > button,
body .app .qv-shell .qv-right-col .verify-actions > button { width: auto; padding: 0 26px; cursor: pointer; }
body .app .qv-shell .qv-right-col .verify-actions .verify-btn:focus-visible { outline: 2px solid var(--p-plum); outline-offset: 2px; }

/* ---------- reject pop-up ---------- */
body .decline-modal-overlay { background: rgba(45, 14, 32, .55); backdrop-filter: blur(2px); }
body .decline-modal-box { max-width: 420px; gap: 12px; }
body .decline-modal-desc { margin: 0 0 4px; line-height: 1.5; }
body .decline-reason-select:focus-visible,
body .decline-reason-other:focus-visible { outline: 2px solid var(--p-primary); outline-offset: 1px; border-color: var(--p-primary); }
body .decline-modal-btn { cursor: pointer; }
body .decline-modal-btn-send:hover { background: var(--p-primary-dark); }
body .decline-modal-btn-cancel:hover { background: #ffdde6; }

/* ---------- mobile ---------- */
@media (max-width: 600px) {
    body .app .qv-toolbar { flex-wrap: wrap; }
    body .app .qv-toolbar .qv-search { flex-basis: 100%; }
    body .app .qv-shell .qv-right-col .verify-actions > * { flex: 1 1 100%; }
    body .app .qv-shell .qv-right-col .verify-actions > form > button { width: 100%; }
}
</style>
<link rel="stylesheet" href="../Shared/active-booking-pages.css?v=1">
<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>




<!-- MAIN -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="welcome">

            <h2 class="portal-title">
                Active Bookings
            </h2>

        </div>

    

        <div class="top-actions">

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


    <?php
    /* filter tabs sa ilalim ng search bar */
    function figurify_render_active_booking_tabs($activeFilter, $processingCount, $revisionCount)
    {
        $abFilterLabels = [
            "all"        => "All (" . (int) ($processingCount + $revisionCount) . ")",
            "processing" => "Processing (" . (int) $processingCount . ")",
            "revision"   => "Revision (" . (int) $revisionCount . ")",
        ];
        $abFilterCurrentLabel = $abFilterLabels[$activeFilter] ?? $abFilterLabels["all"];
    ?>
        <div class="qv-filter-dropdown" id="abFilterDropdown">

            <button
                type="button"
                class="qv-filter-trigger"
                onclick="figurifyToggleFilterDropdown('abFilterDropdown')"
            >
                <?php echo e($abFilterCurrentLabel); ?>
                <svg class="qv-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
            </button>

            <div class="qv-filter-menu">

                <a
                    href="active-booking.php?afilter=all"
                    class="qv-filter-option<?php echo ($activeFilter === "all") ? " active" : ""; ?>"
                >
                    All (<?php echo (int) ($processingCount + $revisionCount); ?>)
                </a>

                <a
                    href="active-booking.php?afilter=processing"
                    class="qv-filter-option<?php echo ($activeFilter === "processing") ? " active" : ""; ?>"
                >
                    Processing (<?php echo (int) $processingCount; ?>)
                </a>

                <a
                    href="active-booking.php?afilter=revision"
                    class="qv-filter-option<?php echo ($activeFilter === "revision") ? " active" : ""; ?>"
                >
                    Revision (<?php echo (int) $revisionCount; ?>)
                </a>

            </div>

        </div>
    <?php
    }
    ?>


    <!-- VERIFICATION MANAGER -->

    <section class="card <?php echo $useVerificationLayout ? "qv-workspace" : "orders-manager"; ?>" style="padding: 10px 0 20px 0;">

        <div class="<?php echo $useVerificationLayout ? "qv-shell" : "orders-manager-grid"; ?>">


            <!-- LEFT: ORDER LIST -->

            <?php if ($useVerificationLayout): ?>

                <!-- Processing tab lang - parehong itsura ng left panel
                     ng Verification (search box + qv-card list). Ang
                     Revision / To Ship / Shipped tabs ay nananatili sa
                     dating staff-order-list na itsura sa ibaba. -->

                <div class="qv-list-panel">

                    <div class="qv-toolbar">

                        <?php
                        /* filter ayon sa Order Type - sa browser lang, parehong itsura ng Verification */
                        $qvRushCount = 0;
                        foreach ($visibleOrders as $qvCountRow) {
                            if (($qvCountRow["order_type"] ?? "") === "rush") {
                                $qvRushCount++;
                            }
                        }
                        $qvNonRushCount = count($visibleOrders) - $qvRushCount;
                        $qvAllLabel     = "All (" . count($visibleOrders) . ")";
                        ?>

                        <div class="qv-filter-dropdown" id="qvTypeFilterDropdown">

                            <button
                                type="button"
                                class="qv-filter-trigger"
                                aria-haspopup="true"
                                onclick="figurifyToggleFilterDropdown('qvTypeFilterDropdown')"
                            >
                                <span id="qvTypeFilterLabel"><?php echo e($qvAllLabel); ?></span>
                                <svg class="qv-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                            </button>

                            <div class="qv-filter-menu" id="qvTypeFilterMenu">
                                <a href="javascript:void(0)" class="qv-filter-option active" onclick="qvSetTypeFilter(this, 'all')">All (<?php echo count($visibleOrders); ?>)</a>
                                <a href="javascript:void(0)" class="qv-filter-option" onclick="qvSetTypeFilter(this, 'rush')">Rush (<?php echo (int) $qvRushCount; ?>)</a>
                                <a href="javascript:void(0)" class="qv-filter-option" onclick="qvSetTypeFilter(this, 'non-rush')">Non-rush (<?php echo (int) $qvNonRushCount; ?>)</a>
                            </div>

                        </div>

                        <div class="qv-search">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input
                                type="text"
                                id="qvSearchInput"
                                placeholder="Search order ID, name..."
                                oninput="qvFilterOrders(this.value)"
                            >
                        </div>

                    </div>

                    <?php if (empty($visibleOrders)): ?>

                        <div class="qv-empty">
                            <?php
                            if ($activeFilter === "revision") {
                                echo "No orders need revision right now.";
                            } elseif ($activeFilter === "to_ship") {
                                echo "No orders waiting to ship right now.";
                            } elseif ($activeFilter === "shipped") {
                                echo "No orders shipped yet.";
                            } else {
                                echo "No active bookings right now.";
                            }
                            ?>
                        </div>

                    <?php else: ?>

                        <div class="qv-list" id="qvOrderList">

                            <table class="qv-table">

                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Email</th>
                                        <th>Type</th>
                                        <th>Method</th>
                                        <th>Book Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($visibleOrders as $order): ?>

                                        <?php
                                        $isActive     = ((int) $order["order_id"] === $selectedOrderId);
                                        $qvSearchText = strtolower($order["order_id"] . " " . $order["full_name"] . " " . $order["email"]);
                                        $qvRowHref    = "active-booking.php?afilter=" . e($activeFilter) . "&order_id=" . (int) $order["order_id"];
                                        ?>

                                        <tr
                                            class="qv-row<?php echo $isActive ? " active" : ""; ?>"
                                            data-href="<?php echo e($qvRowHref); ?>"
                                            data-search="<?php echo e($qvSearchText); ?>"
                                            data-order-id="<?php echo (int) $order["order_id"]; ?>"
                                            data-type="<?php echo ($order["order_type"] === "rush") ? "rush" : "non-rush"; ?>"
                                            onclick="window.location.href = this.dataset.href;"
                                        >

                                            <td class="qv-cell-id">
                                                #<?php echo (int) $order["order_id"]; ?>
                                            </td>

                                            <td><?php echo e($order["email"]); ?></td>

                                            <td>
                                                <?php echo ($order["order_type"] === "rush") ? "Rush" : "Non-Rush"; ?>
                                            </td>

                                            <td>
                                                <?php echo e(orderMethodLabel($order["order_method"])); ?>
                                            </td>

                                            <td>
                                                <?php echo date("M d, Y", strtotime($order["booking_date"])); ?>
                                                <?php
                                                /* OVERDUE - lampas na sa booking date pero hindi pa naipapadala */
                                                if (in_array(figurify_status_key($order["status"]), ["processing", "to_ship"], true)
                                                    && strtotime($order["booking_date"]) < strtotime(date("Y-m-d"))): ?>
                                                    <span style="display:block; width:fit-content; margin:3px 0 0; padding:1px 7px; border-radius:999px; background:#FFE4C7; color:#8A4B12; font-size:9.5px; font-weight:800;">Overdue</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if (!empty($order["has_revision"])): ?>

                                                    <span class="progress-status-badge progress-status-revision">
                                                        Revision
                                                    </span>

                                                <?php else: ?>

                                                    <span class="order-status <?php echo figurify_status_class($order["status"]); ?>">
                                                        <?php echo e(statusLabel($order["status"])); ?>
                                                    </span>

                                                <?php endif; ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <div class="qv-list-footer" id="qvListFooter">
                            Showing <?php echo count($visibleOrders); ?> of <?php echo count($visibleOrders); ?> orders
                        </div>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="staff-order-list">

                    <?php figurify_render_active_booking_tabs($activeFilter, $processingCount, $revisionCount); ?>

                    <?php if (empty($visibleOrders)): ?>

                        <div class="empty-state">
                            <?php
                            if ($activeFilter === "revision") {
                                echo "No orders need revision right now.";
                            } elseif ($activeFilter === "to_ship") {
                                echo "No orders waiting to ship right now.";
                            } elseif ($activeFilter === "shipped") {
                                echo "No orders shipped yet.";
                            } else {
                                echo "No active bookings right now.";
                            }
                            ?>
                        </div>

                    <?php else: ?>

                        <?php foreach ($visibleOrders as $order): ?>

                            <?php
                            $isActive =
                                ((int) $order["order_id"] === $selectedOrderId);
                            ?>

                            <a
                                href="active-booking.php?afilter=<?php echo e($activeFilter); ?>&order_id=<?php echo (int) $order["order_id"]; ?>"
                                class="staff-order-item <?php echo $isActive ? "active" : ""; ?>"
                            >

                                <div class="staff-order-item-top">

                                    <strong>
                                        Order <?php echo (int) $order["order_id"]; ?>
                                    </strong>

                                    <?php if (!empty($order["has_revision"])): ?>

                                        <!-- kapag may pending revision request, ito lang ang badge na ipapakita -->
                                        <span class="progress-status-badge progress-status-revision">
                                            Revision
                                        </span>

                                    <?php else: ?>

                                        <span class="order-status <?php echo figurify_status_class($order["status"]); ?>">
                                            <?php echo e(statusLabel($order["status"])); ?>
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <div class="staff-order-item-customer">
                                    <?php echo e($order["full_name"]); ?>
                                </div>

                                <div class="staff-order-item-meta">
                                    <?php
                                    echo !empty($order["paid_at"])
                                        ? "Paid " . date("M j, Y", strtotime($order["paid_at"]))
                                        : date("M j, Y", strtotime($order["created_at"]));
                                    ?>
                                    ·
                                    ₱<?php echo number_format($order["total_amount"], 2); ?>
                                </div>

                            </a>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


            <?php if ($useVerificationLayout): ?>
                <style>.qv-right-col.qv-right-col--blank > *{display:none !important;}</style>
                <div class="qv-right-col<?php echo !$selectedOrder ? " qv-right-col--blank" : ""; ?>">
            <?php endif; ?>


            <!-- RIGHT: ORDER DETAIL -->

            <div class="<?php echo $useVerificationLayout ? "qv-detail-col" : "staff-order-detail"; ?>">

                <?php if (!$selectedOrder): ?>

                    <?php if ($useVerificationLayout): ?>

                        <div class="qv-header-card">
                            <div class="qv-empty">
                                Select an order on the left to review its payment details.
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="empty-state">
                            Select an order on the left to review its
                            payment details.
                        </div>

                    <?php endif; ?>

                <?php else: ?>

                    <?php if ($useVerificationLayout): ?>

                    <!-- PANEL HEAD: #order · Order + status, status tabs, booking/placed/total -->
                    <div class="ab-panel-top">

                        <div class="qv-panel-head">
                            <h2 class="qv-panel-title">#<?php echo (int) $selectedOrder["order_id"]; ?> · Order</h2>
                        </div>

                        <nav class="ab-status-tabs" aria-label="Filter bookings by status">
                            <?php
                            $abTabs = [
                                "all"        => "All (" . (int) ($processingCount + $revisionCount) . ")",
                                "processing" => "Processing (" . (int) $processingCount . ")",
                                "revision"   => "Revision (" . (int) $revisionCount . ")",
                            ];
                            foreach ($abTabs as $abTabKey => $abTabLabel):
                            ?>
                                <a
                                    href="active-booking.php?afilter=<?php echo e($abTabKey); ?>&amp;order_id=<?php echo (int) $selectedOrder["order_id"]; ?>"
                                    class="ab-status-tab<?php echo ($activeFilter === $abTabKey) ? " active" : ""; ?>"
                                ><?php echo e($abTabLabel); ?></a>
                            <?php endforeach; ?>
                        </nav>

                        <div class="ab-meta-row">
                            <span>Booking: <?php echo date("M j", strtotime($selectedOrder["booking_date"])); ?></span>
                            <span>Placed: <?php echo date("M j", strtotime($selectedOrder["created_at"])); ?></span>
                            <span class="ab-meta-total">₱<?php echo number_format((float) $selectedOrder["total_amount"], 2); ?></span>
                        </div>

                    </div>

                    <?php else: ?>

                    <div class="staff-detail-header">

                        <div>

                            <h3>
                                Order <?php echo (int) $selectedOrder["order_id"]; ?>
                            </h3>

                            <p>
                                <?php echo e($selectedOrder["full_name"]); ?>
                                ·
                                <?php echo e($selectedOrder["email"]); ?>
                            </p>

                        </div>

                        <div class="staff-detail-total">
                            ₱<?php echo number_format($selectedOrder["total_amount"], 2); ?>
                        </div>

                    </div>


                    <div class="staff-detail-meta">

                        <span>
                            <?php
                            echo ($selectedOrder["order_type"] === "rush")
                                ? "Rush Order"
                                : "Non-Rush Order";
                            ?>
                        </span>

                        <span>
                            Booking:
                            <?php echo date("F j, Y", strtotime($selectedOrder["booking_date"])); ?>
                        </span>

                        <span>
                            Placed:
                            <?php echo date("F j, Y", strtotime($selectedOrder["created_at"])); ?>
                        </span>

                    </div>

                    <?php endif; ?>




                    <!-- PROGRESS UPDATE HISTORY
                         Sa PROCESSING tab, lumilipat ito papunta sa RIGHT column
                         (qv-summary) - tignan sa ibaba. Dito pa rin ito lumalabas
                         (kasunod ng buttons) sa Revision / To Ship / Shipped tabs. -->

                    <?php if (!$useVerificationLayout): ?>
                        <?php figurify_render_progress_history_block($progressUpdates, $progressError); ?>
                    <?php endif; ?>


                    <!-- SEND PROGRESS UPDATE MODAL -->

                    <div class="progress-modal-overlay" id="progressModalOverlay">

                        <div class="progress-modal">

                            <div class="progress-modal-top">
                                <h3><?php echo $autoFinalUpdate ? "Send Final Photo" : "Send Progress Update"; ?></h3>
                            </div>

                            <p class="progress-modal-hint">
                                <?php if ($autoFinalUpdate): ?>
                                    Upload the photo of the finished order. The customer will be notified.
                                <?php else: ?>
                                    Upload a photo of the current progress. The customer can approve it or ask for a revision.
                                <?php endif; ?>
                            </p>

                            <form
                                method="POST"
                                action="send_progress_update.php"
                                enctype="multipart/form-data"
                            >

                                <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrderId; ?>">

                                <label>Photo</label>

                                <!-- isang larawan lang; may preview, pwedeng tingnan o alisin -->
                                <div class="pu-photo-picker" id="puPhotoPicker">
                                    <input
                                        type="file"
                                        id="progress_image"
                                        name="progress_image"
                                        accept="image/*"
                                        required
                                        class="pu-photo-input"
                                        onchange="figurifyPreviewProgressPhoto(this)"
                                    >
                                    <label for="progress_image" class="pu-photo-empty" id="puPhotoEmpty">
                                        <span class="pu-photo-plus">+</span>
                                        <span>Click to choose a photo</span>
                                        <small>1 image only (JPG, PNG, WEBP)</small>
                                    </label>
                                    <div class="pu-photo-preview" id="puPhotoPreview" hidden>
                                        <img id="puPhotoImg" src="" alt="Selected progress photo" onclick="openImageLightbox(this.src, 'Progress photo')">
                                        <div class="pu-photo-actions">
                                            <button type="button" class="pu-photo-btn" onclick="openImageLightbox(document.getElementById('puPhotoImg').src, 'Progress photo')">View</button>
                                            <button type="button" class="pu-photo-btn pu-photo-btn-remove" onclick="figurifyClearProgressPhoto()">Remove</button>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($autoFinalUpdate): ?>

                                    <input type="hidden" name="is_final" value="1">

                                <?php else: ?>

                                    <label for="staff_message">Message (optional)</label>
                                    <textarea
                                        id="staff_message"
                                        name="staff_message"
                                        rows="3"
                                        placeholder="e.g. Here's the sculpt so far!"
                                    ></textarea>

                                <?php endif; ?>

                                <div class="pu-modal-actions">
                                    <button type="submit" class="pu-btn pu-btn-send">Send to Customer</button>
                                    <button type="button" class="pu-btn pu-btn-cancel" onclick="figurifyCloseProgressModal()">Cancel</button>
                                </div>

                            </form>

                        </div>

                    </div>

                    <script>
                    function figurifyPreviewProgressPhoto(input) {
                        var file = input.files && input.files[0];
                        var empty = document.getElementById("puPhotoEmpty");
                        var preview = document.getElementById("puPhotoPreview");
                        var img = document.getElementById("puPhotoImg");
                        if (!file) { figurifyClearProgressPhoto(); return; }
                        if (!/^image\//.test(file.type)) {
                            if (window.csToast) { csToast("Please choose an image file.", "error"); }
                            figurifyClearProgressPhoto();
                            return;
                        }
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            img.src = e.target.result;
                            empty.hidden = true;
                            preview.hidden = false;
                        };
                        reader.readAsDataURL(file);
                    }
                    function figurifyClearProgressPhoto() {
                        var input = document.getElementById("progress_image");
                        if (input) { input.value = ""; }
                        document.getElementById("puPhotoImg").src = "";
                        document.getElementById("puPhotoPreview").hidden = true;
                        document.getElementById("puPhotoEmpty").hidden = false;
                    }
                    function figurifyCloseProgressModal() {
                        document.getElementById("progressModalOverlay").classList.remove("show");
                        figurifyClearProgressPhoto();
                        var msg = document.getElementById("staff_message");
                        if (msg) { msg.value = ""; }
                    }
                    </script>


                    <!-- SHIP OUT MODAL -->

                    <div class="progress-modal-overlay" id="shipOutModalOverlay">

                        <div class="progress-modal">

                            <div class="progress-modal-top">

                                <h3>Ship Out — Order #<?php echo (int) $selectedOrderId; ?></h3>

                                <button
                                    type="button"
                                    class="progress-modal-close"
                                    onclick="document.getElementById('shipOutModalOverlay').classList.remove('show')"
                                >
                                    ×
                                </button>

                            </div>

                            <p class="progress-modal-hint">
                                Fill up the shipping details below. The customer will be
                                notified once you submit this.
                            </p>

                            <form
                                method="POST"
                                action="update_active_order.php"
                            >

                                <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrderId; ?>">
                                <input type="hidden" name="action" value="ship_out">

                                <label for="tracking_number">Tracking Number</label>
                                <input
                                    type="text"
                                    id="tracking_number"
                                    name="tracking_number"
                                    placeholder="e.g. JT1234567890"
                                    required
                                >

                                <label for="shipping_fee">Shipping Fee</label>
                                <input
                                    type="number"
                                    id="shipping_fee"
                                    name="shipping_fee"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    required
                                >

                                <div class="pu-modal-actions">
                                    <button type="submit" class="pu-btn pu-btn-send">
                                        Submit
                                    </button>
                                    <button type="button" class="pu-btn pu-btn-cancel" onclick="document.getElementById('shipOutModalOverlay').classList.remove('show')">Cancel</button>
                                </div>

                            </form>

                        </div>

                    </div>


                    <!-- DECLINE REVISION MODAL -->

                    <div class="decline-modal-overlay" id="declineModal_revision">

                        <div class="decline-modal-box">

                            <p class="decline-modal-eyebrow">Revision Request</p>

                            <h3 class="decline-modal-title">Decline Revision</h3>

                            <p class="decline-modal-desc">
                                Pick a reason why this revision can't be done —
                                the customer will see this as a notification
                                and email.
                            </p>

                            <form
                                method="POST"
                                action="decline_revision.php"
                                class="decline-reason-form"
                                onsubmit="return figurifyConfirmDecline(this, 'Decline this revision?\nThe customer will see your reason.', {okText: 'Decline'});"
                            >

                                <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrderId; ?>">
                                <input type="hidden" name="update_id" value="<?php echo (int) $latestRevisionUpdateId; ?>">

                                <select
                                    name="decline_reason"
                                    class="decline-reason-select"
                                    required
                                    onchange="figurifyToggleDeclineOther(this)"
                                >
                                    <option value="" disabled selected>Select a reason…</option>
                                    <option value="unable_to_replicate">Unable to Match the Requested Change</option>
                                    <option value="requires_additional_cost">Requires Additional Cost</option>
                                    <option value="beyond_material_capability">Beyond Our Material/Process Capability</option>
                                    <option value="would_delay_completion">Would Significantly Delay Completion</option>
                                    <option value="other">Other (please specify)</option>
                                </select>

                                <textarea
                                    name="decline_reason_other"
                                    class="decline-reason-other"
                                    placeholder="Type the reason…"
                                    maxlength="190"
                                    style="display:none;"
                                ></textarea>

                                <div class="decline-modal-actions">
                                    <button type="button" class="decline-modal-btn decline-modal-btn-cancel" onclick="figurifyCloseDeclineModal('declineModal_revision')">Cancel</button>
                                    <button type="submit" class="decline-modal-btn decline-modal-btn-send">Send</button>
                                </div>

                            </form>

                        </div>

                    </div>


                    <!-- REQUEST BALANCE PAYMENT MODAL -->

                    <div class="progress-modal-overlay" id="requestBalanceOverlay">

                        <div class="progress-modal">

                            <div class="progress-modal-top">

                                <h3>Finalize Order</h3>

                                <button
                                    type="button"
                                    class="progress-modal-close"
                                    onclick="document.getElementById('requestBalanceOverlay').classList.remove('show')"
                                >
                                    ×
                                </button>

                            </div>

                            <p class="progress-modal-hint">
                                The system computes the remaining balance from the quotation.
                            </p>

                            <form
                                method="POST"
                                action="update_active_order.php"
                            >

                                <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">
                                <input type="hidden" name="action" value="finalize">
                                <input type="hidden" name="redirect_to" value="active-booking">

                                <div class="qv-total-row" style="margin:6px 0 14px;">
                                    <span>Balance Due</span>
                                    <strong>₱<?php echo number_format((float) $wfBalanceDue, 2); ?></strong>
                                </div>

                                <div class="pu-modal-actions">
                                    <button type="submit" class="pu-btn pu-btn-send">
                                        Finalize &amp; Request Balance
                                    </button>
                                    <button type="button" class="pu-btn pu-btn-cancel" onclick="document.getElementById('requestBalanceOverlay').classList.remove('show')">Cancel</button>
                                </div>

                            </form>

                        </div>

                    </div>


                    <!-- CUSTOMER ORDER VIEW (FIGURES) - buong detalye ng order: figure details, custom box, larawan, notes at presyo -->


                        <div class="qv-figure-panels">

                            <?php foreach ($selectedOrder["figures"] as $qIndex => $fig): ?>

                            <?php
                            /* rush fee kada figure (₱200 Head Only, ₱500 iba) + breakdown bago ang Total Amount */
                            require_once __DIR__ . "/../helpers/rush_split_helper.php";
                            $qvOrderFigures = $selectedOrder["figures"] ?? [];
                            $qvRushSplit    = figurify_rush_split($selectedOrder, $qvOrderFigures);
                            $qvFigRushFee   = (float) ($qvRushSplit["per"][$qIndex] ?? 0);
                            $qvFigHasBox    = in_array((string) ($fig["box_addon_type"] ?? ""), ["funko_box", "hirono_blind_box"], true);

                            $qvBreakdownRows = "";
                            if (count($qvOrderFigures) > 1 || $qvRushSplit["total"] > 0) {
                                foreach (array_values($qvOrderFigures) as $qvBi => $qvBf) {
                                    $qvBfPrice = (isset($qvBf["quoted_price"]) && $qvBf["quoted_price"] !== null)
                                        ? (float) $qvBf["quoted_price"]
                                        : (float) ($qvBf["figure_total"] ?? 0);
                                    $qvBreakdownRows .= '<div class="qv-sub-line"><span>Figure ' . ($qvBi + 1) . '</span><span>₱' . number_format($qvBfPrice, 2) . '</span></div>';
                                    if (($qvRushSplit["per"][$qvBi] ?? 0) > 0) {
                                        $qvBreakdownRows .= '<div class="qv-sub-line qv-sub-rush"><span>Rush Fee</span><span>₱' . number_format($qvRushSplit["per"][$qvBi], 2) . '</span></div>';
                                    }
                                }
                                if ($qvRushSplit["legacy"] > 0) {
                                    $qvBreakdownRows .= '<div class="qv-sub-line"><span>Rush Fee</span><span>₱' . number_format($qvRushSplit["legacy"], 2) . '</span></div>';
                                }
                            }
                            $qvTotalLine = '<div class="qv-total-breakdown">' . $qvBreakdownRows
                                . '<div class="qv-total-line"><span class="qv-total-line-label">Total Amount</span>'
                                . '<strong class="qv-total-line-value">₱' . number_format((float) ($selectedOrder["total_amount"] ?? 0), 2) . '</strong></div></div>';
                            ?>

                                <?php
                                /* try/catch per figure gamit ob_start - kapag may error sa isang figure lang, doon lang lalabas ang error, di masisira ang ibang figure */
                                try {
                                ob_start();

                                $hasSize = figureHasSize($fig);

                                $styleImg   = figureStyleImage($fig["figure_style"]);
                                $productImg = productTypeImage($fig["figure_style"], $fig["product_type"]);
                                // Image Submission: walang larawan ng style/product (magkatabing box na lang)
                                if (figurify_dressup_for_figure($fig) === null) {
                                    $styleImg   = null;
                                    $productImg = null;
                                }

                                $existingQuote = ($fig["quoted_price"] !== null)
                                    ? (float) $fig["quoted_price"]
                                    : (float) $fig["figure_total"];

                                $panelId = "verify-figure-panel-" . (int) $selectedOrder["order_id"] . "-" . $qIndex;
                                ?>

                                <div
                                    class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                    id="<?php echo $panelId; ?>"
                                >

                                    <div class="qv-viewer-grid<?php echo figurify_dressup_for_figure($fig) !== null ? " dressup-grid" : ""; ?>">

                                    <!-- LEFT: FIGURE DETAILS + GALLERY -->

                                    <div class="qv-viewer-column">

                                    <div class="qv-figure-card">

                                        <?php if (count($selectedOrder["figures"]) > 1): ?>

                                            <!-- FIGURE TABS - nasa loob na ng figure card,
                                                 sa itaas ng "Figure Details" heading. -->

                                            <div class="qv-figure-tabs">

                                                <?php foreach ($selectedOrder["figures"] as $tabIndex => $tabFig): ?>

                                                    <button
                                                        type="button"
                                                        class="qv-figure-tab-pill<?php echo ($tabIndex === $qIndex) ? " active" : ""; ?>"
                                                        data-figure-target="verify-figure-panel-<?php echo (int) $selectedOrder["order_id"]; ?>-<?php echo $tabIndex; ?>"
                                                        onclick="selectFigure(this)"
                                                    >
                                                        Figure <?php echo $tabIndex + 1; ?>
                                                    </button>

                                                <?php endforeach; ?>

                                            </div>

                                        <?php endif; ?>

                                        <div class="qv-details-head">
                                            Figure <?php echo $qIndex + 1; ?> Information
                                        </div>

                                        <div class="qv-figure-body">

                                            <!-- FIGURE SPECS - kaparehong "qv-detail-item-card"
                                                 na disenyo ng Quotation page. -->

                                            <div class="qv-detail-item-list">

                                                <div class="qv-detail-item-card">

                                                    <?php if ($styleImg): ?>
                                                        <img
                                                            class="qv-detail-item-image"
                                                            src="<?php echo e($siteBase . "Image/" . $styleImg); ?>"
                                                            alt="<?php echo e($fig["figure_style"]); ?>"
                                                        >
                                                    <?php else: ?>
                                                    <?php endif; ?>

                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Figure Style</span>
                                                        <span class="qv-detail-item-value"><?php echo e($fig["figure_style"]); ?></span>
                                                    </div>

                                                </div>

                                                <div class="qv-detail-item-card">

                                                    <?php if ($productImg): ?>
                                                        <img
                                                            class="qv-detail-item-image"
                                                            src="<?php echo e($siteBase . "Image/" . $productImg); ?>"
                                                            alt="<?php echo e($fig["product_type"]); ?>"
                                                        >
                                                    <?php else: ?>
                                                    <?php endif; ?>

                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Product Type</span>
                                                        <span class="qv-detail-item-value"><?php echo e($fig["product_type"]); ?></span>
                                                    </div>

                                                    <div class="qv-detail-item-price<?php echo $hasSize ? " qv-detail-item-price-muted" : ""; ?>">
                                                        <?php echo $hasSize ? "" : "₱" . number_format($fig["product_price"], 2); ?>
                                                    </div>

                                                </div>

                                                <div class="qv-detail-item-card">


                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Size</span>
                                                        <span class="qv-detail-item-value">
                                                            <?php echo $hasSize ? e($fig["size_label"]) : "No size required"; ?>
                                                        </span>
                                                    </div>

                                                    <div class="qv-detail-item-price<?php echo $hasSize ? "" : " qv-detail-item-price-muted"; ?>">
                                                        <?php echo $hasSize ? "₱" . number_format($fig["product_price"], 2) : ""; ?>
                                                    </div>

                                                </div>

                                                <?php if (figurify_dressup_for_figure($fig) === null): /* walang Figure Name sa Dress Up */ ?>
                                                <div class="qv-detail-item-card">


                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Figure Name</span>
                                                        <span class="qv-detail-item-value"><?php require_once __DIR__ . "/../helpers/rush_split_helper.php"; echo e(figurify_figure_name_display($fig)); ?></span>
                                                    </div>

                                                    <div class="qv-detail-item-price<?php echo ((float) $fig["name_fee"] > 0) ? "" : " qv-detail-item-price-muted"; ?>">
                                                        <?php echo ((float) $fig["name_fee"] > 0) ? "₱" . number_format($fig["name_fee"], 2) : ""; ?>
                                                    </div>

                                                </div>
                                                <?php endif; ?>

                                            <?php if ($qvFigRushFee > 0): ?>
                                                <div class="qv-detail-item-card qv-rush-row">
                                                    <div class="qv-detail-item-body">
                                                        <span class="qv-detail-item-label">Rush Fee</span>
                                                        <span class="qv-detail-item-value">Rush Order</span>
                                                    </div>
                                                    <div class="qv-detail-item-price">₱<?php echo number_format($qvFigRushFee, 2); ?></div>
                                                </div>
                                                <?php endif; ?>

                                            </div>
                                            <!-- /.qv-detail-item-list -->

                                            <!-- REFERENCE PHOTOS -->

                                            <?php if (figurify_dressup_for_figure($fig) !== null): ?>
                                                <?php figurify_render_dressup_design($fig, "staff"); ?>
                                            <?php else: ?>
                                            <div class="qv-reference-row">

                                                <div class="qv-reference-label">
                                                    <?php
                                                    $imgCount = count($fig["images"]);
                                                    echo $imgCount > 0
                                                        ? $imgCount . " Reference " . ($imgCount === 1 ? "Photo" : "Photos")
                                                        : "No Reference Photos Uploaded";
                                                    ?>
                                                </div>

                                                <?php if (!empty($fig["images"])): ?>

                                                    <div class="qv-reference-thumbs">

                                                        <?php foreach ($fig["images"] as $imgIndex => $imgPath): ?>

                                                            <?php $fullImg = $siteBase . $imgPath; ?>

                                                            <button
                                                                type="button"
                                                                class="qv-ref-thumb"
                                                                onclick="openImageLightbox('<?php echo e($fullImg); ?>', '<?php echo e($fig["figure_name"]); ?>')"
                                                            >
                                                                <img
                                                                    src="<?php echo e($fullImg); ?>"
                                                                    alt="<?php echo e($fig["figure_name"]); ?> — photo <?php echo $imgIndex + 1; ?>"
                                                                >
                                                            </button>

                                                        <?php endforeach; ?>

                                                    </div>

                                                <?php endif; ?>

                                            </div>
                                            <?php endif; ?>

                                        </div>
                                        <!-- /.qv-figure-body -->

                                        <?php if (!empty($fig["notes"]) && figurify_dressup_for_figure($fig) === null): ?>

                                            <div class="qv-notes-row">
                                                <div class="qv-spec-label">Customer Notes</div>
                                                <div class="qv-note-box"><?php echo nl2br(e($fig["notes"])); ?></div>
                                            </div>

                                        <?php endif; ?>

                                    <?php if (!$qvFigHasBox) { echo $qvTotalLine; } ?>

                                </div>
                                    <!-- /.qv-figure-card -->

                                    </div>
                                    <!-- /.qv-viewer-column (figure details) -->


                                    <!-- RIGHT: CUSTOM BOX DETAILS -->

                                    <div class="qv-viewer-column">

                                    <?php if ($fig["box_addon_type"] === "funko_box"): ?>

                                        <div class="qv-box-card">

                                            <div class="qv-box-info-head">Custom Box Information</div>

                                            <div class="qv-box-spec-grid">

                                                <div>
                                                    <div class="qv-spec-label">Box Type</div>
                                                    <div class="qv-spec-value">
                                                        <?php echo $fig["funko_box_type"] === "solo" ? "Solo Box" : "Couple Box"; ?>
                                                    </div>
                                                    <div class="qv-spec-price">₱<?php echo number_format($fig["box_addon_price"], 2); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Name on Box</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["funko_box_name"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Box Number</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["funko_box_number"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Box Color</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["funko_box_color"]); ?></div>
                                                </div>

                                            </div>

                                        </div>

                                    <?php elseif ($fig["box_addon_type"] === "hirono_blind_box"): ?>

                                        <?php
                                        $blindImg  = hironoBlindTypeImage($fig["hirono_blind_type"]);
                                        $designImg = hironoBoxDesignImage($fig["hirono_box_design"]);
                                        ?>

                                        <div class="qv-box-card">

                                            <div class="qv-box-info-head">Custom Box Information</div>

                                            <div class="qv-box-spec-grid">

                                                <div>
                                                    <div class="qv-spec-label">Box Type</div>
                                                    <div class="qv-spec-value">
                                                        <?php echo $fig["hirono_blind_type"] === "regular" ? "Regular Blind Box" : "Blind Box Set"; ?>
                                                    </div>
                                                    <div class="qv-spec-price">₱<?php echo number_format(hironoBaseBoxPrice($fig), 2); ?></div>
                                                </div>

                                                <?php if (!empty($fig["hirono_box_design"])): ?>
                                                    <div>
                                                        <div class="qv-spec-label">Box Design</div>
                                                        <div class="qv-spec-value"><?php echo e(hironoBoxDesignLabel($fig["hirono_box_design"])); ?></div>
                                                    </div>
                                                <?php endif; ?>

                                                <div>
                                                    <div class="qv-spec-label">Box Color</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["hirono_box_color"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Nickname</div>
                                                    <div class="qv-spec-value"><?php echo e($fig["hirono_nickname"]); ?></div>
                                                </div>

                                                <div>
                                                    <div class="qv-spec-label">Date</div>
                                                    <div class="qv-spec-value">
                                                        <?php
                                                        echo !empty($fig["hirono_date"])
                                                            ? date("F j", strtotime($fig["hirono_date"]))
                                                            : e($fig["hirono_date"]);
                                                        ?>
                                                    </div>
                                                </div>

                                                <?php foreach ($fig["blind_items_list"] as $extraIndex => $extra): ?>
                                                    <div<?php echo ($extraIndex === 0) ? ' class="qv-row-start"' : ''; ?>>
                                                        <div class="qv-spec-label">Optional Extra</div>
                                                        <div class="qv-spec-value"><?php echo e($extra["label"]); ?></div>
                                                        <div class="qv-spec-price">₱<?php echo number_format($extra["price"], 2); ?></div>
                                                    </div>
                                                <?php endforeach; ?>

                                                <div class="qv-spec-full">
                                                    <div class="qv-spec-label">Letter</div>
                                                    <div class="qv-spec-value qv-letter-value"><?php echo nl2br(e($fig["hirono_letter"])); ?></div>
                                                </div>

                                            </div>

                                            <?php if (!empty($fig["box_images"])): ?>

                                                <div class="qv-reference-row">

                                                    <div class="qv-reference-label">Box Reference Images</div>

                                                    <div class="qv-reference-thumbs">

                                                        <?php foreach ($fig["box_images"] as $boxImgIndex => $boxImgPath): ?>

                                                            <?php $fullBoxImg = $siteBase . $boxImgPath; ?>

                                                            <button
                                                                type="button"
                                                                class="qv-ref-thumb"
                                                                onclick="openImageLightbox('<?php echo e($fullBoxImg); ?>', 'Box Reference Image')"
                                                            >
                                                                <img
                                                                    src="<?php echo e($fullBoxImg); ?>"
                                                                    alt="Box reference photo <?php echo $boxImgIndex + 1; ?>"
                                                                >
                                                            </button>

                                                        <?php endforeach; ?>

                                                    </div>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    <?php else: ?>

                                        <!-- Walang box add-on na pinili ang customer para sa
                                             figure na ito - ipinapakita pa rin ang card, pero
                                             walang laman, para malinaw na "wala" ito, kagaya
                                             ng empty state sa Quotation page. -->

                                        <div class="qv-box-card qv-box-card-empty">

                                        <div class="qv-box-info-head">Custom Box Information</div>


                                            <div class="qv-box-empty-title">
                                                No Custom Box Added
                                            </div>

                                            <p class="qv-box-empty-text">
                                                This figure was ordered without a custom
                                                box add-on.
                                            </p>

                                        </div>

                                    <?php endif; ?>

                                    <?php if ($qvFigHasBox) { echo $qvTotalLine; } ?>

                                </div>
                                    <!-- /.qv-viewer-column (custom box) -->


                                        <?php if (figurify_dressup_for_figure($fig) !== null): ?>
                                            <!-- DRESS UP: 3D figure, katabi ng Figure Details -->
                                            <div class="qv-viewer-column dressup-3d-column">
                                                <?php figurify_render_dressup_viewer($fig); ?>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                    <!-- /.qv-viewer-grid -->

                                </div>
                                <!-- /.qv-figure-panel -->

                                <?php
                                /* wala tayong nakuhang error dito - i-print
                                   na ang buong buffered HTML ng figure na ito */
                                echo ob_get_clean();

                                } catch (\Throwable $figureRenderError) {

                                    /* may nangyaring error habang ginagawa ang figure na ito - huwag na lang ituloy ang kalahating-nagawang HTML nito */
                                    if (ob_get_level() > 0) {
                                        ob_end_clean();
                                    }
                                    ?>

                                    <div
                                        class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                        id="verify-figure-panel-<?php echo (int) $selectedOrder["order_id"]; ?>-<?php echo $qIndex; ?>"
                                    >
                                        <div class="staff-payment-details">
                                            <div class="staff-payment-details-title">
                                                May problema sa pagpapakita ng figure na ito (#<?php echo (int) ($fig["figure_id"] ?? 0); ?>)
                                            </div>
                                            <div class="staff-payment-details-grid">
                                                <span>
                                                    <?php echo e($figureRenderError->getMessage()); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                }
                                ?>

                            <?php endforeach; ?>

                        </div>
                        <!-- /.qv-figure-panels -->


                    <!-- PAYMENT & SHIPPING DETAILS + REFUND/RETURN DETAILS
                         Nasa ilalim na ito ng Figure Details (hindi na naka-toggle
                         sa likod ng "Payment" button) - laging nakikita na. -->

                        <!-- PAYMENT & SHIPPING DETAILS -->

                        <?php /* walang ipapakita kapag wala pang naisumiteng bayad/shipping (hal. Pending pa) */ ?>
                        <?php if (!empty($selectedOrder["payment_reference"]) || !empty($selectedOrder["payment_proof"]) || !empty($selectedOrder["shipping_name"])): ?>
                        <div class="staff-payment-details">

                            <div class="staff-payment-details-title">
                                Payment &amp; Shipping Information
                            </div>

                            <div class="staff-payment-details-grid">

                                <span>
                                    <strong>Ship To:</strong>
                                    <?php echo e($selectedOrder["shipping_name"]); ?>
                                </span>

                                <span>
                                    <strong>Address:</strong>
                                    <?php echo e($selectedOrder["shipping_address"]); ?>
                                </span>

                                <?php require_once __DIR__ . "/../helpers/payment_view_helper.php"; ?>
                                <?php figurify_render_pin_row((int) ($selectedOrder["order_id"] ?? 0)); ?>

                                <span>
                                    <strong>Contact:</strong>
                                    <?php echo e($selectedOrder["shipping_contact"]); ?>
                                </span>

                                <span>
                                    <strong>Courier:</strong>
                                    <?php echo e(strtoupper((string) ($selectedOrder["courier"] ?? ""))); ?>
                                </span>

                                <span>
                                    <strong>Reference No.:</strong>
                                    <?php echo e($selectedOrder["payment_reference"]); ?>
                                </span>

                                <?php if (!empty($selectedOrder["paid_at"])): ?>
                                    <span>
                                        <strong>Submitted:</strong>
                                        <?php echo date("F j, Y — g:i A", strtotime($selectedOrder["paid_at"])); ?>
                                    </span>
                                <?php endif; ?>

                            </div>

                            <?php require_once __DIR__ . "/../helpers/payment_view_helper.php"; ?>
                            <?php figurify_render_payment_proofs((int) ($selectedOrder["order_id"] ?? 0), "staff"); ?>

                        </div>
                        <?php endif; ?>


                        <!-- REFUND / RETURN DETAILS -->

                        <div class="staff-payment-details">

                            <div class="staff-payment-details-title">
                                Refund Account Information
                            </div>

                            <div class="staff-payment-details-grid">

                                <span>
                                    <strong>Account Name:</strong>
                                    <?php echo e($selectedOrder["refund_account_name"]); ?>
                                </span>

                                <span>
                                    <strong>Account Number:</strong>
                                    <?php echo e($selectedOrder["refund_account_number"]); ?>
                                </span>

                                <span>
                                    <strong>Preferred Bank/Method:</strong>
                                    <?php echo e($selectedOrder["refund_method"]); ?>
                                </span>

                            </div>

                        </div>


                <?php endif; ?>

            </div>


            <!-- RIGHT: ACTIONS / SUMMARY - action buttons at Progress Updates history -->

            <?php if ($useVerificationLayout): ?>

                <div class="qv-summary">

                    <div class="qv-summary-head">
                        <h2>Order Actions</h2>
                    </div>

                    <div class="qv-summary-body">

                        <?php if (!$selectedOrder): ?>

                            <div class="qv-empty">
                                Select an order on the left to see its actions.
                            </div>

                        <?php else: ?>

                            <?php if ($wfState === "READY_TO_SHIP" && !empty($wfShipChecks)): ?>
                                <ul style="list-style:none;margin:4px 0 12px;padding:0;font-size:12px;">
                                    <?php foreach ($wfShipChecks as $wfCheckLabel => $wfCheckOk): ?>
                                        <li style="color:<?php echo $wfCheckOk ? "#227A4C" : "#B42318"; ?>;">
                                            <?php echo $wfCheckOk ? "✓" : "✗"; ?> <?php echo e($wfCheckLabel); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <!-- REFUND (owner lang) - para sa bayad na order na hindi na itutuloy; mapupunta sa Refunds bilang "For Refund" -->

                            <?php
                            $rfSelStatus = figurify_status_key($selectedOrder["status"] ?? "");
                            $rfOverdue   = in_array($rfSelStatus, ["processing", "to_ship"], true)
                                && !empty($selectedOrder["booking_date"])
                                && strtotime($selectedOrder["booking_date"]) < strtotime(date("Y-m-d"));
                            ?>

                            <?php if ($rfOverdue): ?>
                                <div style="margin:8px 0 10px; padding:9px 12px; border-radius:12px; background:#FFF1E0; color:#8A4B12; font-size:11.5px; font-weight:700;">
                                    Overdue — the booking date (<?php echo date("M j, Y", strtotime($selectedOrder["booking_date"])); ?>)
                                    has already passed. If the customer no longer wants to continue, you can process a refund.
                                </div>
                            <?php endif; ?>


                            <div class="decline-modal-overlay" id="refundModal_start">

                                <div class="decline-modal-box">

                                    <p class="decline-modal-eyebrow">Refund — Order <?php echo (int) $selectedOrder["order_id"]; ?></p>

                                    <h3 class="decline-modal-title">Process Refund</h3>

                                    <p class="decline-modal-desc">
                                        Pick the reason. The order will move to Refunds (For Refund)
                                        and the customer will be notified. Send the refund to the
                                        customer's refund account, then upload the proof in Refunds.
                                    </p>

                                    <form
                                        method="POST"
                                        action="process_refund.php"
                                        class="decline-reason-form"
                                        onsubmit="return figurifyConfirmDecline(this, 'Move to For Refund?\nProduction will stop and the customer will be notified.', {okText: 'Move'});"
                                    >

                                        <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">
                                        <input type="hidden" name="action" value="start">

                                        <select
                                            name="refund_reason"
                                            class="decline-reason-select"
                                            required
                                            onchange="figurifyToggleDeclineOther(this)"
                                        >
                                            <option value="" disabled selected>Select a reason…</option>
                                            <option value="not_completed_by_booking_date">Order Not Completed by the Booking Date</option>
                                            <option value="unable_to_fulfill">Unable to Fulfill the Order</option>
                                            <option value="customer_cancelled">Customer Cancelled the Order</option>
                                            <option value="defective_or_incorrect">Defective or Incorrect Order</option>
                                            <option value="damaged_during_shipping">Damaged During Shipping</option>
                                            <option value="other">Other (please specify)</option>
                                        </select>

                                        <textarea
                                            name="refund_reason_other"
                                            class="decline-reason-other"
                                            placeholder="Type the reason…"
                                            maxlength="190"
                                            style="display:none;"
                                        ></textarea>

                                        <div class="decline-modal-actions">
                                            <button type="button" class="decline-modal-btn decline-modal-btn-cancel" onclick="figurifyCloseDeclineModal('refundModal_start')">Cancel</button>
                                            <button type="submit" class="decline-modal-btn decline-modal-btn-send">Move to For Refund</button>
                                        </div>

                                    </form>

                                </div>

                            </div>




                            <!-- PROGRESS UPDATES SENT -->

                            <?php figurify_render_progress_history_block($progressUpdates, $progressError); ?>

                            <!-- action buttons nasa pinakababa -->
                            <!-- ACTION BUTTONS (nakatayo pababa, hindi pahalang) -->

                            <div
                                class="verify-actions ab-bottom-actions"
                                style="flex-direction:row !important; flex-wrap:wrap !important; align-items:stretch !important; gap:10px !important; border-bottom:none !important;"
                            >

                                <?php if ($canShipOut): ?>

                                    <button
                                        type="button"
                                        class="verify-btn verify-btn-approve ab-btn-primary"
                                        onclick="document.getElementById('shipOutModalOverlay').classList.add('show')"
                                    >
                                        Ship Out
                                    </button>

                                <?php elseif (!empty($selectedOrder["is_shipped"])): ?>

                                    <span
                                        class="verify-btn"
                                        style="display:inline-flex; align-items:center; justify-content:center; background:#DCF2E1; color:#227A4C; cursor:default;"
                                    >
                                        Shipped
                                        <?php if (!empty($selectedOrder["tracking_number"])): ?>
                                            — <?php echo e($selectedOrder["tracking_number"]); ?>
                                        <?php endif; ?>
                                    </span>

                                    <form
                                        method="POST"
                                        action="update_active_order.php"
                                        onsubmit="return csConfirmSubmit(this, 'Mark as complete?\nThis order will move to Completed.', {okText: 'Complete'});"
                                       
                                    >

                                        <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">
                                        <input type="hidden" name="action" value="complete_order">

                                        <button type="submit" class="verify-btn verify-btn-approve ab-btn-primary">
                                            Complete
                                        </button>

                                    </form>

                                <?php endif; ?>

                                <?php if ($canSendUpdate): ?>

                                    <button
                                        type="button"
                                        class="verify-btn verify-btn-approve"
                                        onclick="document.getElementById('progressModalOverlay').classList.add('show')"
                                    >
                                        Send Progress Update
                                    </button>

                                <?php elseif ($awaitingCustomerResponse): ?>

                                    <span class="progress-status-badge progress-status-pending" style="font-size:12px;">
                                        Waiting for the customer's response
                                    </span>

                                <?php endif; ?>

                                <?php if ($canMarkToShip): ?>

                                    <!-- Bago i-mark na "to ship", kailangan munang hingin
                                         ang natitirang balance sa customer - dito lumalabas
                                         ang popup para ilagay ang halaga (tingnan ang
                                         #requestBalanceOverlay sa ibaba). -->
                                    <button
                                        type="button"
                                        class="verify-btn verify-btn-approve ab-btn-primary"
                                       
                                        onclick="document.getElementById('requestBalanceOverlay').classList.add('show')"
                                    >
                                        <?php echo $wfState === "READY_TO_SHIP" ? "Request Balance" : "Finalize Order"; ?>
                                    </button>

                                <?php endif; ?>

                                <?php if ($awaitingRevisionDecision && $latestRevisionUpdateId > 0): ?>

                                    <div class="revision-decision-actions">

                                        <form
                                            method="POST"
                                            action="accept_revision.php"
                                            onsubmit="return csConfirmSubmit(this, 'Accept this revision?\nThe customer will be notified.', {okText: 'Accept'});"
                                        >

                                            <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">
                                            <input type="hidden" name="update_id" value="<?php echo (int) $latestRevisionUpdateId; ?>">

                                            <button type="submit" class="verify-btn verify-btn-approve">
                                                Accept Revision
                                            </button>

                                        </form>

                                        <button
                                            type="button"
                                            class="verify-btn verify-btn-reject"
                                            onclick="figurifyOpenDeclineModal('declineModal_revision')"
                                        >
                                            Decline Revision
                                        </button>

                                    </div>

                                <?php endif; ?>

                                <?php /* Process Refund - wala sa revision part (Accept / Decline Revision lang doon) */ ?>
                                <?php if (!($awaitingRevisionDecision && $latestRevisionUpdateId > 0)): ?>
                                    <button
                                        type="button"
                                        class="verify-btn verify-btn-reject"
                                        onclick="figurifyOpenDeclineModal('refundModal_start')"
                                    >
                                        Process Refund
                                    </button>
                                <?php endif; ?>

                            </div>
                            <!-- /.verify-actions -->

                        <?php endif; ?>

                    </div>
                    <!-- /.qv-summary-body -->

                </div>
                <!-- /.qv-summary -->

            <?php endif; ?>


            <?php if ($useVerificationLayout): ?>
                </div>
                <!-- /.qv-right-col -->
            <?php endif; ?>


        </div>

    </section>


</main>

</div>


<!-- IMAGE LIGHTBOX (click any figure photo to enlarge) -->

<div
    class="image-lightbox"
    id="imageLightbox"
    onclick="closeImageLightbox(event)"
>

    <div class="image-lightbox-content">

        <button
            type="button"
            class="image-lightbox-close"
            onclick="closeImageLightbox()"
            aria-label="Close"
        >
            ✕
        </button>

        <img
            id="lightboxImage"
            src=""
            alt="Enlarged figure photo"
        >

        <div
            class="image-lightbox-label"
            id="lightboxLabel"
        ></div>

    </div>

</div>


<script>

/* Decline modal (pareho ng Quotation at Verification) */

function figurifyConfirmDecline(form, message, options) {
    var select = form.querySelector(".decline-reason-select");
    if (select && select.value === "other") {
        var other = form.querySelector(".decline-reason-other");
        if (other && other.value.trim() === "") {
            if (window.csToast) { csToast("Type the reason first.", "error"); } else { alert("Type the reason first."); }
            other.focus();
            return false;
        }
    }
    return window.csConfirmSubmit ? csConfirmSubmit(form, message, options || { okText: "Decline" }) : confirm(message);
}

// buksan ang decline pop-up (Decline button)
function figurifyOpenDeclineModal(modalId) {
    var overlay = document.getElementById(modalId);
    if (overlay) {
        overlay.classList.add("active");
    }
}

// isara ang decline pop-up (Cancel button, walang isu-submit)
function figurifyCloseDeclineModal(modalId) {
    var overlay = document.getElementById(modalId);
    if (overlay) {
        overlay.classList.remove("active");
    }
}

// ipakita/itago ang textbox pag pinili/tinanggal ang "Other" sa dropdown
function figurifyToggleDeclineOther(select) {
    var textarea = select.closest("form").querySelector(".decline-reason-other");
    if (!textarea) {
        return;
    }
    if (select.value === "other") {
        textarea.style.display = "block";
        textarea.required = true;
    } else {
        textarea.style.display = "none";
        textarea.required = false;
        textarea.value = "";
    }
}


/* FILTER DROPDOWN - toggle ng ".qv-filter-dropdown" (All / Processing / Revision, atbp). Isang dropdown lang ang bukas sa isang pagkakataon, at nagsasara ito pag nag-click sa labas o pag pinindot ang Escape. */

function figurifyToggleFilterDropdown(id) {

    var dropdown = document.getElementById(id);

    if (!dropdown) {
        return;
    }

    var isOpen = dropdown.classList.contains("open");

    document.querySelectorAll(".qv-filter-dropdown.open").forEach(function (el) {
        el.classList.remove("open");
    });

    if (!isOpen) {
        dropdown.classList.add("open");
    }

}

document.addEventListener("click", function (event) {

    document.querySelectorAll(".qv-filter-dropdown.open").forEach(function (dropdown) {
        if (!dropdown.contains(event.target)) {
            dropdown.classList.remove("open");
        }
    });

});

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        document.querySelectorAll(".qv-filter-dropdown.open").forEach(function (dropdown) {
            dropdown.classList.remove("open");
        });
    }

});


function openImageLightbox(src, label) {

    var lightbox = document.getElementById("imageLightbox");
    var image = document.getElementById("lightboxImage");
    var caption = document.getElementById("lightboxLabel");

    if (!lightbox || !image) {
        return;
    }

    image.src = src;

    if (caption) {
        caption.textContent = label || "";
    }

    lightbox.classList.add("show");

    document.body.style.overflow = "hidden";

}


function closeImageLightbox(event) {

    if (event && event.target && event.target.id === "lightboxImage") {
        return;
    }

    var lightbox = document.getElementById("imageLightbox");

    if (!lightbox) {
        return;
    }

    lightbox.classList.remove("show");

    document.body.style.overflow = "";

}


document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        closeImageLightbox();
    }

});


/* SWITCH WHICH FIGURE'S DETAILS ARE SHOWN (kapag may 2+ figure sa order na ito) - isang figure lang ang makikita sa isang pagkakataon, tulad ng ginagawa sa My Orders. */

function selectFigure(button) {

    var panelsWrap = button.closest(".qv-figure-panels");

    if (!panelsWrap) {
        return;
    }

    var targetId = button.dataset.figureTarget;

    /* bawat figure panel may sariling kopya ng tabs, kaya i-sync ang "active" state sa LAHAT ng kopya, hindi lang yung pinindot */
    panelsWrap
        .querySelectorAll(".qv-figure-tab-pill")
        .forEach(function (tab) {
            tab.classList.toggle("active", tab.dataset.figureTarget === targetId);
        });

    panelsWrap
        .querySelectorAll(".qv-figure-panel")
        .forEach(function (panel) {
            panel.classList.toggle("active", panel.id === targetId);
        });

}




/* search sa left panel (order ID / name), sa browser lang */

/* Filter (Order Type) + search sa left panel, parehong sa browser lang */

var qvTypeFilter = "all";

function qvSetTypeFilter(option, type) {

    qvTypeFilter = type;

    document.querySelectorAll("#qvTypeFilterMenu .qv-filter-option").forEach(function (el) {
        el.classList.toggle("active", el === option);
    });

    var label = document.getElementById("qvTypeFilterLabel");

    if (label) {
        label.textContent = option.textContent.trim();
    }

    var dropdown = document.getElementById("qvTypeFilterDropdown");

    if (dropdown) {
        dropdown.classList.remove("open");
    }

    var input = document.getElementById("qvSearchInput");

    qvFilterOrders(input ? input.value : "");

}

function qvFilterOrders(query) {

    var q = query.trim().toLowerCase();
    var cards = document.querySelectorAll("#qvOrderList .qv-row");
    var visibleCount = 0;

    cards.forEach(function (card) {

        var matchesText = card.dataset.search.indexOf(q) !== -1;
        var matchesType = (qvTypeFilter === "all") || (card.dataset.type === qvTypeFilter);
        var matches = matchesText && matchesType;

        card.classList.toggle("qv-hidden", !matches);

        if (matches) {
            visibleCount++;
        }

    });

    var footer = document.getElementById("qvListFooter");

    if (footer) {
        footer.textContent = "Showing " + visibleCount + " of " + cards.length + " orders";
    }

}


/* KEEP SCROLL POSITION ACROSS ORDER CLICKS - parehong pattern ng Quotation/Verification pages. */

(function () {

    var panels = [
        { el: document.getElementById("qvOrderList"), key: "qvOrderListScroll" },
        { el: document.querySelector(".qv-right-col"), key: "qvDetailScroll" }
    ];

    panels.forEach(function (panel) {

        if (!panel.el) {
            return;
        }

        var savedScroll = sessionStorage.getItem(panel.key);

        if (savedScroll !== null) {
            panel.el.scrollTop = parseInt(savedScroll, 10) || 0;
        }

    });

    document.querySelectorAll("#qvOrderList .qv-row").forEach(function (card) {
        card.addEventListener("click", function () {

            panels.forEach(function (panel) {

                if (panel.el) {
                    sessionStorage.setItem(panel.key, panel.el.scrollTop);
                }

            });

        });
    });

})();


/* iwasan ang double-submit sa action buttons (Send Update, Ship Out, Complete, atbp) - lock/disable agad ang button pagka-click para di makapag-doble ng notification/email */

document.querySelectorAll("form").forEach(function (actionForm) {

    actionForm.addEventListener("submit", function (event) {

        /* Kung na-cancel na ito ng ibang handler (hal. yung confirm sa Complete / To Ship), huwag na ito i-lock - wala namang talagang na-submit. */
        if (event.defaultPrevented) {
            return;
        }

        if (actionForm.dataset.figurifySubmitting === "1") {
            event.preventDefault();
            return;
        }

        actionForm.dataset.figurifySubmitting = "1";

        var submitBtn = actionForm.querySelector('button[type="submit"]');

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Please wait…";
        }

    });

});

</script>

</body>
</html>