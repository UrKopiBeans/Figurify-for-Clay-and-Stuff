<?php

/* ==========================================================
   staff - QUOTATION PAGE
   Dating nasa Shared/quotation-page.php (iisang kopya para sa owner at staff).
   Ngayon ay may sariling kopya na ang owner at staff, kaya dito na ang buong page.
   $figurifyRole = "staff" ang pumipili ng header (staff-header.php).
   ========================================================== */

$figurifyRole    = "staff";
$figurifyPageDir = __DIR__;


require_once $figurifyPageDir . "/" . $figurifyRole . "-header.php";

require_once __DIR__ . "/../helpers/order_helper.php";


$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";


/* GET ALL ORDERS + CUSTOMER INFO (NEWEST FIRST) */

$orders = [];

/* get the requested order_id even if status is past pending/quoted, para makita pa rin ito (read-only, $canQuote na ang bahala sa pag-lock ng form) */
$requestedOrderId = isset($_GET["order_id"]) ? (int) $_GET["order_id"] : 0;

$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_type, o.order_method, o.ship_out_date, o.rush_fee,
            o.subtotal, o.total_amount, o.status, o.created_at,
            u.user_id AS customer_id, u.full_name, u.email
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     WHERE o.status IN ('pending', 'needs_correction', 'quoted') OR o.order_id = ?
     ORDER BY o.created_at DESC"
);

$stmt->bind_param("i", $requestedOrderId);
$stmt->execute();

$ordersResult = $stmt->get_result();

while ($row = $ordersResult->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();


/* PENDING / QUOTED TAB FILTER (button sa itaas ng listahan) */

$quoteFilter = isset($_GET["qfilter"]) ? strtolower(trim($_GET["qfilter"])) : "all";

if (!in_array($quoteFilter, ["all", "pending", "correction", "quoted"], true)) {
    $quoteFilter = "all";
}

/* Kung may direktang hinihiling na order_id (redirect galing send_quotation.php o notification link), i-override ang $quoteFilter base sa TOTOONG status ng order - para tamang tab (Pending/Quoted) ang mabubuksan imbes na maiwan sa maling tab. */
if ($requestedOrderId > 0) {
    foreach ($orders as $checkOrder) {
        if ((int) $checkOrder["order_id"] === $requestedOrderId) {
            if (in_array($checkOrder["status"], ["pending", "needs_correction", "quoted"], true)) {
                // naka-"All" = manatili sa All; ibang tab lang ang ililipat kung wala roon ang order
                if ($quoteFilter !== "all" && figurifyQuoteTabFor($checkOrder["status"]) !== $quoteFilter) {
                    $quoteFilter = figurifyQuoteTabFor($checkOrder["status"]);
                }
            }
            break;
        }
    }
}

$pendingCount    = 0;
$quotedCount     = 0;
$correctionCount = 0;

foreach ($orders as $countOrder) {
    if ($countOrder["status"] === "quoted") {
        $quotedCount++;
    } elseif ($countOrder["status"] === "pending") {
        $pendingCount++;
    } elseif ($countOrder["status"] === "needs_correction") {
        $correctionCount++;
    }
}

$visibleOrders = array_values(array_filter(
    $orders,
    function ($order) use ($quoteFilter, $requestedOrderId) {

        // Isang order lang ang lalabas dito kung "pending"/"quoted" pa
        // TALAGA ang status - kapag lumipat na ito (e.g. naging
        // "to_verify"), aalis na rin sa listahang ito.

        $stillInQuoteStage = in_array($order["status"], ["pending", "needs_correction", "quoted"], true);

        if (!$stillInQuoteStage) {
            return false;
        }


        if ($quoteFilter === "all") {
            return true;
        }

        return figurifyQuoteTabFor($order["status"]) === $quoteFilter;
    }
));


/* GET FIGURES + ALL IMAGES FOR EACH ORDER */

foreach ($orders as &$order) {

    $fstmt = $conn->prepare(
        "SELECT figure_id, figure_style, product_type, size_label,
                figure_name, notes, product_price, name_fee, figure_total, quantity,
                box_addon_type, box_addon_price,
                funko_box_type, funko_box_name, funko_box_number, funko_box_color,
                hirono_blind_type, hirono_box_design, hirono_box_color, hirono_letter,
                hirono_nickname, hirono_date, hirono_blind_items, hirono_blind_items_total,
                quoted_price
         FROM order_figures
         WHERE order_id = ?"
    );

    $fstmt->bind_param("i", $order["order_id"]);
    $fstmt->execute();

    $fresult = $fstmt->get_result();

    $figures = [];

    while ($frow = $fresult->fetch_assoc()) {

        $imgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_images
             WHERE figure_id = ?"
        );

        $imgStmt->bind_param("i", $frow["figure_id"]);
        $imgStmt->execute();

        $imgResult = $imgStmt->get_result();

        $images = [];

        while ($irow = $imgResult->fetch_assoc()) {
            $images[] = $irow["image_path"];
        }

        $imgStmt->close();

        $frow["images"] = $images;


        /* BOX REFERENCE IMAGES (Hirono Blind Box only) */

        $boxImgStmt = $conn->prepare(
            "SELECT image_path
             FROM order_figure_box_images
             WHERE figure_id = ?"
        );

        $boxImgStmt->bind_param("i", $frow["figure_id"]);
        $boxImgStmt->execute();

        $boxImgResult = $boxImgStmt->get_result();

        $boxImages = [];

        while ($birow = $boxImgResult->fetch_assoc()) {
            $boxImages[] = $birow["image_path"];
        }

        $boxImgStmt->close();

        $frow["box_images"] = $boxImages;


        // Optional Hirono Blind Box extras, saved as JSON { item_key: price }.
        // Decode into [label, price] pairs para sa display.

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


/* WHICH ORDER IS CURRENTLY OPEN ON THE RIGHT SIDE */

/* Wala nang AUTO-SELECT ng unang order sa listahan. Dapat i-click muna ng owner ang isang row bago lumabas ang detail + quotation sa right side - kaya "" (walang order_id) ang default. */
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

/* dahilan ng correction (legacy needs_correction orders, galing order_status_history) - kinukuha bago isara ang $conn */
$qvCorrectionReasons = [];

foreach ($orders as $reasonOrder) {
    if (($reasonOrder["status"] ?? "") === "needs_correction") {
        $qvCorrectionReasons[(int) $reasonOrder["order_id"]] = figurify_wf_latest_reason($conn, (int) $reasonOrder["order_id"], "STAFF_CORRECTION_REQUIRED");
    }
}

$conn->close();


$quotationSent = isset($_GET["sent"]) && $_GET["sent"] === "1";

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php /* [POP UP AT SECURITY] pop up at security script lang (walang CSS) - Shared/popup-and-security.php */ include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Figurify | Quotation</title>

<!-- ===================== STYLE NG PAGE NA ITO: quotation.css ===================== -->
<link rel="stylesheet" href="quotation.css">

</head>


<body class="quotation-page">


<div class="app">


<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<!-- MAIN -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="welcome">

            <h2 class="portal-title">
                Quotation
            </h2>

        </div>

    

        <div class="top-actions">

<!-- ==========================================================
     [TOPBAR-DATETIME]  (dating Shared/topbar-datetime.php - nasa page na ito na)
     ANO ITO: petsa at oras sa taas ng page, sa kaliwa ng notification bell.
     PAANO GUMAGANA: ang JS sa Shared/sidebar.php ([3] TOPBAR DATE / TIME) ang nagpapalit ng .cs-tb-date at .cs-tb-time
     kada segundo (en-PH). Self-contained: hindi kailangan ng #currentDate / #currentTime.
     ========================================================== -->

<div class="cs-tb-datetime" aria-label="Current date and time">
    <span class="cs-tb-date">Loading date...</span>
    <span class="cs-tb-dot">•</span>
    <span class="cs-tb-time">Loading time...</span>
</div>

<!-- [END TOPBAR-DATETIME] -->

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


    <?php if ($quotationSent) { echo figurify_toast_now("Quotation sent to the customer.", "success"); } ?>


    <!-- QUOTATION WORKSPACE -->

    <section class="card qv-workspace">

        <div class="qv-shell">


            <!-- LEFT: ORDER LIST -->

            <div class="qv-list-panel">

                <div class="qv-toolbar">

                    <!-- PENDING / QUOTED FILTER DROPDOWN -->

                    <?php
                    $qvFilterLabels = [
                        "all"        => "All (" . (int) ($pendingCount + $correctionCount + $quotedCount) . ")",
                        "pending"    => "Pending (" . (int) $pendingCount . ")",
                        "quoted"     => "Quoted (" . (int) $quotedCount . ")",
                    ];
                    $qvFilterCurrentLabel = $qvFilterLabels[$quoteFilter] ?? $qvFilterLabels["all"];
                    ?>

                    <div class="qv-filter-dropdown" id="qvQuoteFilterDropdown">

                        <button
                            type="button"
                            class="qv-filter-trigger"
                            onclick="figurifyToggleFilterDropdown('qvQuoteFilterDropdown')"
                        >
                            <?php echo e($qvFilterCurrentLabel); ?>
                            <svg class="qv-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div class="qv-filter-menu">

                            <a
                                href="quotation.php?qfilter=all"
                                class="qv-filter-option<?php echo ($quoteFilter === "all") ? " active" : ""; ?>"
                            >
                                All (<?php echo (int) ($pendingCount + $correctionCount + $quotedCount); ?>)
                            </a>

                            <a
                                href="quotation.php?qfilter=pending"
                                class="qv-filter-option<?php echo ($quoteFilter === "pending") ? " active" : ""; ?>"
                            >
                                Pending (<?php echo (int) $pendingCount; ?>)
                            </a>


                            <a
                                href="quotation.php?qfilter=quoted"
                                class="qv-filter-option<?php echo ($quoteFilter === "quoted") ? " active" : ""; ?>"
                            >
                                Quoted (<?php echo (int) $quotedCount; ?>)
                            </a>

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
                        if ($quoteFilter === "quoted") {
                            echo "No quoted orders yet.";
                        } elseif ($quoteFilter === "pending") {
                            echo "No orders waiting for verification.";
                        } elseif ($quoteFilter === "correction") {
                            echo "No orders waiting for customer correction.";
                        } else {
                            echo "No orders yet.";
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
                                    <th>Order Type</th>
                                    <th>Order Method</th>
                                    <th>Ship Out Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($visibleOrders as $order): ?>

                                    <?php
                                    $isActive      = ((int) $order["order_id"] === $selectedOrderId);
                                    $statusColors  = figurify_status_colors($order["status"]);
                                    $qvSearchText  = strtolower($order["order_id"] . " " . $order["full_name"] . " " . $order["email"]);
                                    $qvRowFilter   = $quoteFilter;

                                    /* click ng row = select lang, hindi toggle off, para di basta mawala ang panel. May sariling "Clear selection" button na lang. */
                                    $qvRowHref = "quotation.php?order_id=" . (int) $order["order_id"] . "&qfilter=" . $qvRowFilter;
                                    ?>

                                    <tr
                                        class="qv-row<?php echo $isActive ? " active" : ""; ?>"
                                        data-href="<?php echo e($qvRowHref); ?>"
                                        data-search="<?php echo e($qvSearchText); ?>"
                                        data-order-id="<?php echo (int) $order["order_id"]; ?>"
                                        data-order-status="<?php echo e($order["status"]); ?>"
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
                                            <?php echo date("M d, Y", strtotime($order["ship_out_date"])); ?>
                                        </td>

                                        <td>
                                            <?php echo figurify_status_badge($order["status"]); ?>
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


            <!-- NO ORDER SELECTED -->

            <?php if (!$selectedOrder): ?>

                <div class="qv-right-col qv-right-col--blank">

                    <div class="qv-detail-col">
                        <div class="qv-header-card">
                            <div class="qv-empty">
                                Select an order to prepare its quotation.
                            </div>
                        </div>
                    </div>

                    <div class="qv-summary">
                        <div class="qv-summary-head"><h2>Quotation</h2></div>
                        <div class="qv-summary-body">
                            <div class="qv-empty">Nothing to quote yet.</div>
                        </div>
                    </div>

                </div>

            <?php else: ?>

                <?php
                $quoteFigureCount = count($selectedOrder["figures"]);

                /* quoting is only allowed habang "pending" pa. Once "quoted" na, locked na to para di na ma-edit ulit. */
                $canQuote = ($selectedOrder["status"] === "pending");

                /* ibinalik sa customer para i-correct - hinihintay pa ang customer */
                $awaitingCorrection = ($selectedOrder["status"] === "needs_correction");

                $rushFeeAmount = ($selectedOrder["order_type"] === "rush")
                    ? (float) $selectedOrder["rush_fee"]
                    : 0.0;
                ?>

                <?php if ($canQuote): ?>

                    <form
                        method="POST"
                        action="send_quotation.php"
                        id="quotationSendForm"
                    >

                        <input
                            type="hidden"
                            name="order_id"
                            value="<?php echo (int) $selectedOrder["order_id"]; ?>"
                        >

                <?php endif; ?>


                <!-- RIGHT CONTAINER: DETAIL + SUMMARY -->

                <div class="qv-right-col">

                <!-- PANEL HEAD: title + status ng order -->
                

                <!-- ORDER DETAIL -->

                <div class="qv-detail-col">

                    <div class="qv-figure-panels">

                        <?php foreach ($selectedOrder["figures"] as $qIndex => $fig): ?>

                            <?php
                            /* rush fee kada figure (₱200 Head Only, ₱500 iba) */
                            require_once __DIR__ . "/../helpers/booking_helper.php";
                            $qvOrderFigures = $selectedOrder["figures"] ?? [];
                            $qvRushSplit    = figurify_rush_split($selectedOrder, $qvOrderFigures);
                            $qvFigRushFee   = (float) ($qvRushSplit["per"][$qIndex] ?? 0);
                            ?>

                            <?php
                            $hasSize = figureHasSize($fig);

                            $styleImg   = figureStyleImage($fig["figure_style"]);
                            $productImg = productTypeImage($fig["figure_style"], $fig["product_type"]);
                            // Image Submission: walang larawan ng style/product (magkatabing box na lang)
                            if (figurify_dressup_for_figure($fig) === null) {
                                $styleImg   = null;
                                $productImg = null;
                            }

                            $panelId  = "quote-figure-panel-" . (int) $selectedOrder["order_id"] . "-" . $qIndex;
                            ?>

                            <div
                                class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                id="<?php echo $panelId; ?>"
                            >

                                <div class="qv-viewer-grid<?php echo figurify_dressup_for_figure($fig) !== null ? " qv-dressup-stack" : ""; ?>">

                                <!-- LEFT: FIGURE DETAILS + GALLERY -->

                                <div class="qv-viewer-column">

                                <div class="qv-figure-card">

                                    <?php if ($quoteFigureCount > 1): ?>

                                        <!-- FIGURE TABS - nasa loob na ng figure card,
                                             sa itaas ng "Figure Details" heading. -->

                                        <div class="qv-figure-tabs">

                                            <?php foreach ($selectedOrder["figures"] as $tabIndex => $tabFig): ?>

                                                <button
                                                    type="button"
                                                    class="qv-figure-tab-pill<?php echo ($tabIndex === $qIndex) ? " active" : ""; ?>"
                                                    data-figure-target="quote-figure-panel-<?php echo (int) $selectedOrder["order_id"]; ?>-<?php echo $tabIndex; ?>"
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

                                        <!-- FIGURE SPECS - sinundan ang layout/disenyo ng
                                             detail-item-card sa Customer My Orders page
                                             (icon/image + label + value + price sa isang
                                             pahalang na row), para consistent ang UI sa
                                             pagitan ng Owner at Customer side. -->

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
                                                    <span class="qv-detail-item-value"><?php require_once __DIR__ . "/../helpers/booking_helper.php"; echo e(figurify_figure_name_display($fig)); ?></span>
                                                </div>

                                                <div class="qv-detail-item-price<?php echo ((float) $fig["name_fee"] > 0) ? "" : " qv-detail-item-price-muted"; ?>">
                                                    <?php echo ((float) $fig["name_fee"] > 0) ? "₱" . number_format($fig["name_fee"], 2) : ""; ?>
                                                </div>

                                            </div>
                                            <?php endif; ?>

                                        <?php /* Quantity: ilang piraso ng figure na ito (isang figure lang kahit may qty 2 pataas) */ ?>
                                            <div class="qv-detail-item-card">

                                                <div class="qv-detail-item-body">
                                                    <span class="qv-detail-item-label">Quantity</span>
                                                    <span class="qv-detail-item-value"><?php echo figurify_figure_quantity($fig); ?> <?php echo figurify_figure_quantity($fig) === 1 ? "piece" : "pieces"; ?></span>
                                                </div>

                                                <div class="qv-detail-item-price qv-detail-item-price-muted"></div>

                                            </div>

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

                                        <!-- REFERENCE IMAGE -->

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
                                                                alt="<?php echo e($fig["figure_name"]); ?> photo <?php echo $imgIndex + 1; ?>"
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


                                </div>
                                <!-- /.qv-figure-card -->

                                </div>
                                <!-- /.qv-viewer-column (figure details) -->


                                <!-- RIGHT: BOX & ADD-ONS -->

                                <?php if (in_array((string) ($fig["box_addon_type"] ?? ""), ["funko_box", "hirono_blind_box"], true)): /* Box & Add-ons: lalabas lang kapag may box na inorder */ ?>
                                <div class="qv-viewer-column">

                                <?php if ($fig["box_addon_type"] === "funko_box"): ?>

                                    <div class="qv-box-card">

                                        <div class="qv-box-info-head">Box &amp; Add-ons</div>

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

                                        <div class="qv-box-info-head">Box &amp; Add-ons</div>

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

                                <?php endif; ?>


                                </div>
                                <!-- /.qv-viewer-column (box & add-ons) -->
                                <?php endif; ?>


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

                        <?php endforeach; ?>

                    </div>
                    <!-- /.qv-figure-panels -->

                </div>
                <!-- /.qv-detail-col -->


                <!-- RIGHT: QUOTATION SUMMARY -->

                <div class="qv-summary">

                    <div class="qv-summary-head">
                        <h2>Quotation</h2>
                    </div>

                    <div class="qv-summary-body">

                        <?php
                        require_once __DIR__ . "/../helpers/booking_helper.php";
                        $qfRushSplit = figurify_rush_split($selectedOrder, $selectedOrder["figures"] ?? []);
                        ?>

                        <div class="qv-line-list">

                            <?php foreach ($selectedOrder["figures"] as $qIndex => $fig): ?>

                                <?php
                                $existingQuote = ($fig["quoted_price"] !== null)
                                    ? (float) $fig["quoted_price"]
                                    : (float) $fig["figure_total"];
                                ?>

                                <div class="qv-line">

                                    <div class="qv-line-label">
                                        Figure <?php echo $qIndex + 1; ?>
                                        <?php if (figurify_figure_quantity($fig) > 1): ?>
                                            <small>× <?php echo figurify_figure_quantity($fig); ?> pieces (price per piece)</small>
                                        <?php endif; ?>
                                    </div>

                                    <?php /* rush fee ng figure: nasa kaliwa ng presyo, may "+" */ ?>
                                    <div class="qv-line-right">

                                    <?php if (($qfRushSplit["per"][$qIndex] ?? 0) > 0): ?>
                                        <small class="qv-sub-rush-plus">+ ₱<?php echo number_format($qfRushSplit["per"][$qIndex], 2); ?> Rush Fee</small>
                                    <?php endif; ?>

                                    <?php if ($canQuote): ?>

                                        <label class="qv-line-input-wrap" for="quotedPrice-<?php echo (int) $fig["figure_id"]; ?>">

                                            <span>₱</span>

                                            <input
                                                type="number"
                                                name="quoted_price[<?php echo (int) $fig["figure_id"]; ?>]"
                                                id="quotedPrice-<?php echo (int) $fig["figure_id"]; ?>"
                                                class="qv-line-input figure-quote-input"
                                                data-quantity="<?php echo figurify_figure_quantity($fig); ?>"
                                                step="0.01"
                                                min="0"
                                                required
                                                value="<?php echo number_format($existingQuote, 2, ".", ""); ?>"
                                                oninput="updateQuotationTotal()"
                                            >

                                        </label>

                                    <?php else: ?>

                                        <div class="qv-line-value">
                                            <?php echo ($fig["quoted_price"] !== null) ? "₱" . number_format($existingQuote * figurify_figure_quantity($fig), 2) : "Not yet quoted"; ?>
                                        </div>

                                    <?php endif; ?>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                            <?php if ($rushFeeAmount > 0 && $qfRushSplit["legacy"] > 0): ?>

                                <div class="qv-line">

                                    <div class="qv-line-label">
                                        Rush Fee
                                    </div>

                                    <div class="qv-line-value">
                                        ₱<?php echo number_format($rushFeeAmount, 2); ?>
                                    </div>

                                </div>

                            <?php endif; ?>

                        </div>

                        <hr class="qv-divider">

                        <div class="qv-total-row">

                            <span>
                                <?php echo $canQuote ? "Computed Total" : "Total Amount"; ?>
                            </span>

                            <?php if ($canQuote): ?>
                                <strong id="quotationComputedTotal">₱0.00</strong>
                            <?php else: ?>
                                <strong>₱<?php echo number_format($selectedOrder["total_amount"], 2); ?></strong>
                            <?php endif; ?>

                        </div>

                        

                        <?php if ($canQuote): ?>

                            <div class="qv-quote-actions">

                                <button type="submit" class="qv-send-btn" onclick="return csConfirmSubmit(this.form, 'Send this quotation?\nThe customer will be notified of the price.', {okText: 'Send', cancelText: 'Back'});">
                                    Send Quotation
                                </button>

                                <button type="button" class="verify-btn verify-btn-reject" onclick="figurifyOpenDeclineModal('declineModal_quotation')">
                                    Decline
                                </button>

                            </div>

                        <?php elseif ($awaitingCorrection): ?>

                            <?php $qvCorrectionReason = $qvCorrectionReasons[(int) $selectedOrder["order_id"]] ?? null; ?>

                            <p class="qv-summary-hint">
                                Returned to the customer for correction<?php echo $qvCorrectionReason ? ": <strong>" . e($qvCorrectionReason) . "</strong>" : "."; ?>
                                It will come back to <strong>Pending</strong> once the customer resubmits.
                            </p>

                            <div class="qv-quote-actions">
                                <button type="button" class="verify-btn verify-btn-reject" onclick="figurifyOpenDeclineModal('declineModal_quotation')">
                                    Decline
                                </button>
                            </div>

                        <?php else: ?>

                            <p class="qv-summary-hint">
                                This order is already past the quotation stage
                                (current status:
                                <?php echo e(statusLabel($selectedOrder["status"])); ?>),
                                so the quotation can no longer be changed here.
                            </p>

                        <?php endif; ?>

                    </div>

                </div>
                <!-- /.qv-summary -->

                </div>
                <!-- /.qv-right-col -->

                <?php if ($canQuote): ?>
                    </form>
                    <!-- /#quotationSendForm -->

                <?php endif; ?>

                <?php if ($canQuote || $awaitingCorrection): ?>

                    <form
                        method="POST"
                        action="decline_quote.php"
                        class="decline-reason-form quotation-decline-form"
                        onsubmit="return figurifyCheckDeclineForm(this);"
                    >

                        <input type="hidden" name="order_id" value="<?php echo (int) $selectedOrder["order_id"]; ?>">

                        <div class="decline-modal-overlay" id="declineModal_quotation">
                            <div class="decline-modal-box">
                                <h3 class="decline-modal-title">Decline Order</h3>
                                <p class="decline-modal-desc">Pick a reason. If the details need fixing, the order goes back to the customer. Other reasons cancel the order. The customer will see the reason.</p>

                                <select
                                    name="decline_reason"
                                    class="decline-reason-select"
                                    required
                                    onchange="figurifyToggleDeclineOther(this)"
                                >
                                    <option value="" disabled selected>Select a reason…</option>
                                    <?php if ($canQuote): ?>
                                        <optgroup label="Wrong or unclear details (send back to the customer)">
                                            <option value="fix_details">Incorrect or Incomplete Order Details</option>
                                            <option value="fix_clarify">Needs Clarification (e.g. figure style does not match the notes)</option>
                                            <option value="fix_images">Reference Image Is Unclear or Missing</option>
                                            <option value="other_fix">Other, send back (please specify)</option>
                                        </optgroup>
                                    <?php endif; ?>
                                    <optgroup label="Order problem (cancel the order)">
                                        <option value="unable_to_fulfill">Unable to Fulfill the Order</option>
                                        <option value="out_of_scope">Outside Our Services</option>
                                        <option value="other">Other, cancel order (please specify)</option>
                                    </optgroup>
                                </select>

                                <textarea
                                    name="decline_reason_other"
                                    class="decline-reason-other"
                                    placeholder="Type the reason…"
                                    maxlength="190"
                                    hidden
                                ></textarea>

                                <div class="decline-modal-actions">
                                    <button type="button" class="decline-modal-btn decline-modal-btn-cancel" onclick="figurifyCloseDeclineModal('declineModal_quotation')">Cancel</button>
                                    <button type="submit" class="decline-modal-btn decline-modal-btn-send">Send</button>
                                </div>
                            </div>
                        </div>

                    </form>
                <?php endif; ?>

            <?php endif; ?>

        </div>
        <!-- /.qv-shell -->

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
        ></button>

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

// ---- DECLINE MODAL, FILTER DROPDOWN, LIVE TOTAL, FIGURE TABS, LIGHTBOX, ORDER SEARCH, SCROLL MEMORY ----

// decline pop-up - checked bago i-submit: kung "Other" ang reason, dapat may laman ang textbox.
// Walang pangalawang "Are you sure?" pop up: ang Send/Decline button na mismo ang final.
function figurifyCheckDeclineForm(form) {
    var select = form.querySelector(".decline-reason-select");
    if (select && (select.value === "other" || select.value === "other_fix")) {
        var other = form.querySelector(".decline-reason-other");
        if (other && other.value.trim() === "") {
            if (window.csToast) { csToast("Type the reason first.", "error"); } else { alert("Type the reason first."); }
            other.focus();
            return false;
        }
    }
    return true;
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
    var isFix = select.value.indexOf("fix_") === 0;
    var isOther = select.value === "other" || select.value === "other_fix";
    textarea.hidden = !(isFix || isOther);
    textarea.required = isOther;
    textarea.placeholder = isFix ? "Add a note for the customer (optional)…" : "Type the reason…";
    if (textarea.hidden) {
        textarea.value = "";
    }
}

/* FILTER DROPDOWN - toggle ng ".qv-filter-dropdown" (All / Pending / Quoted, atbp). Isang dropdown lang ang bukas sa isang pagkakataon, at nagsasara ito pag nag-click sa labas o pag pinindot ang Escape. */

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


/* live total - sinusuma lang ang mga quoted price + rush fee habang nagta-type ang owner. Di ito ipino-post, server ang muling magco-compute base sa actual values (para di ma-manipulate). */

var quotationRushFee = <?php echo json_encode($rushFeeAmount ?? 0); ?>;

function updateQuotationTotal() {

    var inputs = document.querySelectorAll(".figure-quote-input");
    var total = quotationRushFee;

    inputs.forEach(function (input) {

        var value = parseFloat(input.value);
        var quantity = parseInt(input.dataset.quantity, 10) || 1;

        if (!isNaN(value)) {
            total += value * quantity;
        }

    });

    var totalDisplay = document.getElementById("quotationComputedTotal");

    if (totalDisplay) {
        totalDisplay.textContent = "₱" + total.toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

}

document.addEventListener("DOMContentLoaded", updateQuotationTotal);


/* SWITCH WHICH FIGURE'S DETAILS ARE SHOWN (kapag may 2+ figure sa isang order) */

function selectFigure(button) {

    var panelsWrap = button.closest(".qv-figure-panels");

    if (!panelsWrap) {
        return;
    }

    var targetId = button.dataset.figureTarget;

    /* May sariling kopya ng figure tabs ang bawat figure panel (paulit-ulit na render sa foreach), kaya i-sync ang "active" state sa LAHAT ng kopya, hindi lang yung pinindot. */
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


/* IMAGE LIGHTBOX */

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

    document.body.classList.add("no-scroll");

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

    document.body.classList.remove("no-scroll");

}


/* Escape key: isara ang filter dropdown at ang image lightbox */

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        document.querySelectorAll(".qv-filter-dropdown.open").forEach(function (dropdown) {
            dropdown.classList.remove("open");
        });
        closeImageLightbox();
    }

});


/* search sa left panel (order ID / name), sa browser lang */

function qvFilterOrders(query) {

    var q = query.trim().toLowerCase();
    var cards = document.querySelectorAll("#qvOrderList .qv-row");
    var visibleCount = 0;

    cards.forEach(function (card) {

        var matches = card.dataset.search.indexOf(q) !== -1;

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


/* keep scroll position across order clicks - normal <a> nav resets scroll, kaya i-save/restore natin yung dalawang panel na may sariling scroll */

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

// ==============================================================
// [FILTER-STATE.JS]  (dating Shared/filter-state.js - nasa page na ito na)
// ANO ITO: PUTI KAPAG 'ALL' ang napili sa filter dropdown, may kulay kapag iba.
// PAANO GUMAGANA: Naglalagay/nag-aalis lang ng class na .is-filtered. Walang binabago sa mismong filtering.
// ==============================================================

/* Filter dropdown: puti kapag "All" (unang option) ang napili, may kulay kapag iba.
   Walang binabago sa filtering mismo - class lang (.is-filtered) ang nilalagay. */
(function () {
    "use strict";

    function update(dd) {
        var opts = Array.prototype.slice.call(dd.querySelectorAll(".qv-filter-option"));
        if (!opts.length) { return; }
        var active = null;
        opts.forEach(function (o) { if (o.classList.contains("active")) { active = o; } });
        var filtered = !!active && opts.indexOf(active) > 0;
        dd.classList.toggle("is-filtered", filtered);
    }

    function init() {
        document.querySelectorAll(".qv-filter-dropdown").forEach(function (dd) {
            update(dd);
            new MutationObserver(function () { update(dd); })
                .observe(dd, { subtree: true, attributes: true, attributeFilter: ["class"] });
            dd.addEventListener("click", function () { setTimeout(function () { update(dd); }, 0); });
        });
    }

    if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", init); }
    else { init(); }
})();
</script>


</body>
</html>