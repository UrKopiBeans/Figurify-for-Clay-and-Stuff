<?php

require_once __DIR__ . "/staff-header.php";
require_once __DIR__ . "/../helpers/booking_helper.php";
require_once __DIR__ . "/../helpers/order_helper.php";
figurify_autocomplete_shipped_orders($conn); /* 7 araw na shipped -> complete */

$figurifyRole    = "staff";
$figurifyPageDir = __DIR__;

$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";

/* shared with owner/shipments.php - entry file kailangan mag-set ng $figurifyRole at $figurifyPageDir muna */

/* all shipments - "to_ship" or "shipped" orders, not just the within-2-days ones on
   the dashboard's Upcoming Shipments card. see helpers/booking_helper.php figurify_get_all_shipments */

$shipmentTab = isset($_GET["tab"]) ? strtolower(trim((string) $_GET["tab"])) : "all";

if (!in_array($shipmentTab, ["all", "to_ship", "shipped"], true)) {
    $shipmentTab = "all";
}

$allShipments = figurify_get_all_shipments(
    $conn,
    $shipmentTab === "all" ? null : $shipmentTab
);

$toShipCount  = 0;
$shippedCount = 0;

foreach ($allShipments as $shipmentRow) {

    $rowStatus = strtolower(trim((string) ($shipmentRow["status"] ?? "")));

    if (in_array($rowStatus, ["to_ship", "address_returned"], true)) {
        $toShipCount++;
    } elseif ($rowStatus === "shipped") {
        $shippedCount++;
    }

}

/* same select-left, view-right pattern as Quotation/Verification/Active Booking, instead of jumping straight to Active Booking on row click */
$selectedOrderId = isset($_GET["order_id"])
    ? (int) $_GET["order_id"]
    : 0;

$selectedShipment = null;

foreach ($allShipments as $shipmentRow) {

    if ((int) $shipmentRow["order_id"] === $selectedOrderId) {
        $selectedShipment = $shipmentRow;
        break;
    }

}

/* payment/shipping + refund/return details for the selected shipment - not in
   figurify_get_all_shipments (that's basic info only), separate query here so we
   don't touch other code using the shared helper */

if ($selectedShipment) {

    $paymentStmt = $conn->prepare(
        "SELECT shipping_name, shipping_address, shipping_contact,
                payment_reference, payment_proof, paid_at, shipping_fee,
                refund_account_name, refund_account_number, refund_method
         FROM orders
         WHERE order_id = ?"
    );

    $paymentStmt->bind_param("i", $selectedShipment["order_id"]);
    $paymentStmt->execute();

    $paymentRow = $paymentStmt->get_result()->fetch_assoc();
    $paymentStmt->close();

    if ($paymentRow) {
        $selectedShipment = array_merge($selectedShipment, $paymentRow);
    }

}

/* full figure details + custom box for the selected shipment, same query as Quotation / Active Booking */

if ($selectedShipment) {

    $selectedShipment["figures"] = [];

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

    $fstmt->bind_param("i", $selectedShipment["order_id"]);
    $fstmt->execute();

    $fresult = $fstmt->get_result();

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
                        "label" => $blindItemLabels[$itemKey] ?? $itemKey,
                        "price" => (float) $itemPrice,
                    ];

                }

            }

        }

        $selectedShipment["figures"][] = $frow;

    }

    $fstmt->close();

}

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

<title>Figurify | Shipments</title>

<!-- ===================== STYLE NG PAGE NA ITO: shipments.css ===================== -->
<link rel="stylesheet" href="shipments.css">

</head>


<body class="quotation-page">

<div class="app">

<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<main class="main">


<header class="topbar">

    <div class="welcome">

        <h2 class="portal-title">
            Shipments
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


<section class="card qv-workspace">

    <div class="qv-shell">

        <!-- left: shipment list -->

        <div class="qv-list-panel">

            <div class="qv-toolbar">

                <?php
                $shipFilterLabels = [
                    "all"      => "All (" . (int) ($toShipCount + $shippedCount) . ")",
                    "to_ship"  => "To Ship (" . (int) $toShipCount . ")",
                    "shipped"  => "Shipped (" . (int) $shippedCount . ")",
                ];
                $shipFilterCurrentLabel = $shipFilterLabels[$shipmentTab] ?? $shipFilterLabels["all"];
                ?>

                <div class="qv-filter-dropdown" id="shipFilterDropdown">

                    <button
                        type="button"
                        class="qv-filter-trigger"
                        onclick="figurifyToggleFilterDropdown('shipFilterDropdown')"
                    >
                        <?php echo e($shipFilterCurrentLabel); ?>
                        <svg class="qv-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                    </button>

                    <div class="qv-filter-menu">

                        <a href="shipments.php?tab=all" class="qv-filter-option<?php echo $shipmentTab === 'all' ? ' active' : ''; ?>">
                            All (<?php echo (int) ($toShipCount + $shippedCount); ?>)
                        </a>

                        <a href="shipments.php?tab=to_ship" class="qv-filter-option<?php echo $shipmentTab === 'to_ship' ? ' active' : ''; ?>">
                            To Ship (<?php echo (int) $toShipCount; ?>)
                        </a>

                        <a href="shipments.php?tab=shipped" class="qv-filter-option<?php echo $shipmentTab === 'shipped' ? ' active' : ''; ?>">
                            Shipped (<?php echo (int) $shippedCount; ?>)
                        </a>

                    </div>

                </div>

                <div class="qv-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input
                        type="text"
                        id="qvSearchInput"
                        placeholder="Search order ID, name..."
                        oninput="qvFilterShipments(this.value)"
                    >
                </div>

            </div>


            <?php if (!empty($allShipments)): ?>

                <div class="qv-list" id="qvShipmentList">

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

                            <?php foreach ($allShipments as $order): ?>

                                <?php
                                $isActive     = ((int) $order["order_id"] === $selectedOrderId);
                                $qvSearchText = strtolower($order["order_id"] . " " . ($order["full_name"] ?? "") . " " . ($order["email"] ?? ""));
                                $qvRowHref    = "shipments.php?tab=" . e($shipmentTab) . "&order_id=" . (int) $order["order_id"];
                                ?>

                                <tr
                                    class="qv-row<?php echo $isActive ? " active" : ""; ?>"
                                    data-href="<?php echo e($qvRowHref); ?>"
                                    data-search="<?php echo e($qvSearchText); ?>"
                                    onclick="window.location.href = this.dataset.href;"
                                >

                                    <td class="qv-cell-id">
                                        #<?php echo (int) $order["order_id"]; ?>
                                    </td>

                                    <td>
                                        <?php echo e($order["email"] ?? "N/A"); ?>
                                    </td>

                                    <td>
                                        <?php echo ($order["order_type"] ?? "") === "rush" ? "Rush" : "Non-rush"; ?>
                                    </td>

                                    <td>
                                        <?php echo e(orderMethodLabel($order["order_method"] ?? "")); ?>
                                    </td>

                                    <td>
                                        <?php
                                        $rowShipDate = $order["ship_out_date"] ?? null;

                                        echo !empty($rowShipDate)
                                            ? date("M j", strtotime($rowShipDate))
                                            : "N/A";
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo figurify_status_badge($order["status"] ?? ""); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="qv-list-footer" id="qvListFooter">
                    Showing <?php echo count($allShipments); ?> of <?php echo count($allShipments); ?> shipments
                </div>

            <?php else: ?>

                <div class="qv-empty">
                    No shipments to show.
                </div>

            <?php endif; ?>

        </div>


        <!-- right: shipment detail -->

        <?php if (!$selectedShipment): ?>

            <div class="qv-right-col qv-right-col--blank">

                <div class="qv-detail-col">
                    <div class="qv-header-card">
                        <div class="qv-empty">
                            Select a shipment to view its details.
                        </div>
                    </div>
                </div>

                <div class="qv-summary">
                    <div class="qv-summary-head"><h2>Shipping Information</h2></div>
                    <div class="qv-summary-body">
                        <div class="qv-empty">Nothing to show yet.</div>
                    </div>
                </div>

            </div>

        <?php else: ?>

            <div class="qv-right-col">

                <div class="qv-detail-col">

                    <?php $shipFigureCount = count($selectedShipment["figures"]); ?>

                    <div class="qv-figure-panels">

                        <?php foreach ($selectedShipment["figures"] as $sIndex => $fig): ?>

                            <?php
                            /* rush fee kada figure (₱200 Head Only, ₱500 iba) + breakdown bago ang Total Amount */
                            require_once __DIR__ . "/../helpers/booking_helper.php";
                            $qvOrderFigures = $selectedShipment["figures"] ?? [];
                            $qvRushSplit    = figurify_rush_split($selectedShipment, $qvOrderFigures);
                            $qvFigRushFee   = (float) ($qvRushSplit["per"][$sIndex] ?? 0);
                            $qvFigHasBox    = in_array((string) ($fig["box_addon_type"] ?? ""), ["funko_box", "hirono_blind_box"], true);

                            $qvBreakdownRows = "";
                            if (count($qvOrderFigures) > 1 || $qvRushSplit["total"] > 0) {
                                foreach (array_values($qvOrderFigures) as $qvBi => $qvBf) {
                                    $qvBfPrice = figurify_figure_line_price($qvBf);
                                    /* rush fee ng figure: nasa kaliwa ng presyo, may "+" (hal. + ₱500.00 Rush Fee   ₱1,200.00) */
                                    $qvBfRush = (float) ($qvRushSplit["per"][$qvBi] ?? 0);
                                    $qvBreakdownRows .= '<div class="qv-sub-line"><span>Figure ' . ($qvBi + 1) . (figurify_figure_quantity($qvBf) > 1 ? ' × ' . figurify_figure_quantity($qvBf) : '') . '</span><span>' . ($qvBfRush > 0 ? '<small class="qv-sub-rush-plus">+ ₱' . number_format($qvBfRush, 2) . ' Rush Fee</small>' : '') . '₱' . number_format($qvBfPrice, 2) . '</span></div>';
                                }
                                if ($qvRushSplit["legacy"] > 0) {
                                    $qvBreakdownRows .= '<div class="qv-sub-line"><span>Rush Fee</span><span>₱' . number_format($qvRushSplit["legacy"], 2) . '</span></div>';
                                }
                            }
                            $qvTotalLine = '<div class="qv-total-breakdown">' . $qvBreakdownRows
                                . '<div class="qv-total-line"><span class="qv-total-line-label">Total Amount</span>'
                                . '<strong class="qv-total-line-value">₱' . number_format((float) ($selectedShipment["total_amount"] ?? 0), 2) . '</strong></div></div>';
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

                            $panelId = "ship-figure-panel-" . (int) $selectedShipment["order_id"] . "-" . $sIndex;
                            ?>

                            <div
                                class="qv-figure-panel<?php echo ($sIndex === 0) ? " active" : ""; ?>"
                                id="<?php echo $panelId; ?>"
                            >

                                <div class="qv-viewer-grid<?php echo figurify_dressup_for_figure($fig) !== null ? " dressup-grid" : ""; ?>">

                                <!-- figure details + gallery -->

                                <div class="qv-viewer-column">

                                <div class="qv-figure-card">

                                    <?php if ($shipFigureCount > 1): ?>

                                        <!-- figure tabs, inside the figure card above the heading -->

                                        <div class="qv-figure-tabs">

                                            <?php foreach ($selectedShipment["figures"] as $tabIndex => $tabFig): ?>

                                                <button
                                                    type="button"
                                                    class="qv-figure-tab-pill<?php echo ($tabIndex === $sIndex) ? " active" : ""; ?>"
                                                    data-figure-target="ship-figure-panel-<?php echo (int) $selectedShipment["order_id"]; ?>-<?php echo $tabIndex; ?>"
                                                    onclick="selectFigure(this)"
                                                >
                                                    Figure <?php echo $tabIndex + 1; ?>
                                                </button>

                                            <?php endforeach; ?>

                                        </div>

                                    <?php endif; ?>

                                    <div class="qv-details-head">
                                        Figure <?php echo $sIndex + 1; ?> Information
                                    </div>

                                    <div class="qv-figure-body">

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

                                <?php if (!$qvFigHasBox) { echo $qvTotalLine; } ?>

                                </div>
                                <!-- /.qv-figure-card -->

                                </div>
                                <!-- /.qv-viewer-column (figure details) -->


                                <!-- custom box details -->

                                <?php if ($qvFigHasBox): /* Box & Add-ons: lalabas lang kapag may box na inorder */ ?>
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

                                <?php echo $qvTotalLine; ?>

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

                    <!-- payment & shipping details -->

                    <?php /* walang ipapakita kapag wala pang naisumiteng bayad/shipping (hal. Pending pa) */ ?>
                    <?php if (!empty($selectedShipment["payment_reference"]) || !empty($selectedShipment["payment_proof"]) || !empty($selectedShipment["shipping_name"])): ?>
                    <div class="staff-payment-details">

                        <div class="staff-payment-details-title">
                            Payment &amp; Shipping Information
                        </div>

                        <div class="staff-payment-details-grid">

                            <span>
                                <strong>Ship To:</strong>
                                <?php echo e($selectedShipment["shipping_name"] ?? ""); ?>
                            </span>

                            <span>
                                <strong>Address:</strong>
                                <?php echo e($selectedShipment["shipping_address"] ?? ""); ?>
                            </span>

                                <?php require_once __DIR__ . "/../helpers/content_helper.php"; ?>
                                <?php figurify_render_pin_row((int) ($selectedShipment["order_id"] ?? 0)); ?>

                            <span>
                                <strong>Contact:</strong>
                                <?php echo e($selectedShipment["shipping_contact"] ?? ""); ?>
                            </span>

                            <span>
                                <strong>Courier:</strong>
                                <?php echo e(strtoupper((string) ($selectedShipment["courier"] ?? ""))); ?>
                            </span>

                            <span>
                                <strong>Reference No.:</strong>
                                <?php echo e($selectedShipment["payment_reference"] ?? ""); ?>
                            </span>

                            <?php if (!empty($selectedShipment["paid_at"] ?? null)): ?>
                                <span>
                                    <strong>Submitted:</strong>
                                    <?php echo date("F j, Y, g:i A", strtotime($selectedShipment["paid_at"])); ?>
                                </span>
                            <?php endif; ?>

                        </div>

                        <?php require_once __DIR__ . "/../helpers/content_helper.php"; ?>
                        <?php figurify_render_payment_proofs((int) ($selectedShipment["order_id"] ?? 0), "staff"); ?>

                    </div>
                    <?php endif; ?>

                    <!-- refund / return details -->

                    <div class="staff-payment-details">

                        <div class="staff-payment-details-title">
                            Refund Account Information
                        </div>

                        <div class="staff-payment-details-grid">

                            <span>
                                <strong>Account Name:</strong>
                                <?php echo e($selectedShipment["refund_account_name"] ?? ""); ?>
                            </span>

                            <span>
                                <strong>Account Number:</strong>
                                <?php echo e($selectedShipment["refund_account_number"] ?? ""); ?>
                            </span>

                            <span>
                                <strong>Preferred Bank/Method:</strong>
                                <?php echo e($selectedShipment["refund_method"] ?? ""); ?>
                            </span>

                        </div>

                    </div>

                </div>

                <div class="qv-summary">

                    <div class="qv-summary-head">
                        <h2>Shipment Details</h2>
                    </div>

                    <div class="qv-summary-body">

                        <?php
                        $selectedShipmentStatus = strtolower(trim((string) ($selectedShipment["status"] ?? "")));
                        $shipDate = $selectedShipment["ship_out_date"] ?? null;
                        ?>

                        <div class="qv-line-list">

                            <div class="qv-line">
                                <span class="qv-line-label">Ship Out Date</span>
                                <span class="qv-line-value"><?php echo !empty($shipDate) ? date("M d, Y", strtotime($shipDate)) : "N/A"; ?></span>
                            </div>

                            <?php if (!empty($selectedShipment["shipped_at"] ?? null)): ?>
                                <div class="qv-line">
                                    <span class="qv-line-label">Shipped On</span>
                                    <span class="qv-line-value"><?php echo date("M d, Y", strtotime($selectedShipment["shipped_at"])); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!in_array($selectedShipmentStatus, ["to_ship", "address_returned"], true)): ?>
                                <div class="qv-line">
                                    <span class="qv-line-label">Tracking Number</span>
                                    <span class="qv-line-value"><?php echo e($selectedShipment["tracking_number"] ?? "N/A"); ?></span>
                                </div>
                                <?php if (isset($selectedShipment["shipping_fee"]) && $selectedShipment["shipping_fee"] !== null): ?>
                                    <div class="qv-line">
                                        <span class="qv-line-label">Shipping Fee</span>
                                        <span class="qv-line-value">₱<?php echo number_format((float) $selectedShipment["shipping_fee"], 2); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                        </div>

                        <?php require_once __DIR__ . "/../helpers/content_helper.php"; ?>

                        <?php if ($selectedShipmentStatus === "to_ship"): ?>

                            <!-- SHIP OUT: tracking number, shipping fee at resibo -->

                            <form
                                method="POST"
                                action="update_active_order.php"
                                enctype="multipart/form-data"
                                class="qv-ship-form sd-ship-form"
                                onsubmit="return csConfirmSubmit(this, 'Ship out this order?\nThe customer will get the tracking number.', {okText: 'Ship Out'});"
                            >

                                <input type="hidden" name="order_id" value="<?php echo (int) $selectedShipment["order_id"]; ?>">
                                <input type="hidden" name="action" value="ship_out">
                                <input type="hidden" name="redirect_to" value="shipments">

                                <div class="sd-field">
                                    <label for="ship_tracking_number">Tracking Number</label>
                                    <input type="text" id="ship_tracking_number" name="tracking_number" inputmode="numeric" pattern="[0-9]+" placeholder="e.g. 1234567890" data-digits-only required>
                                </div>

                                <div class="sd-field">
                                    <label for="ship_shipping_fee">Shipping Fee</label>
                                    <input type="text" id="ship_shipping_fee" name="shipping_fee" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,2})?" placeholder="0.00" data-decimal-only required>
                                </div>

                                <div class="sd-receipt-block">
                                    <label for="ship_receipt">Shipping Receipt</label>
                                    <div class="sd-upload">
                                        <div class="sd-upload-preview" id="sdReceiptPreview">
                                            <span class="sd-upload-placeholder" id="sdReceiptEmpty">
                                                Click to upload the shipping receipt
                                                <br>
                                                <small>JPG, PNG, WEBP up to 5MB</small>
                                            </span>
                                            <img id="sdReceiptImg" alt="Receipt preview" hidden>
                                        </div>
                                        <input type="file" id="ship_receipt" name="shipping_receipt" accept="image/jpeg,image/png,image/webp" onchange="figurifyPreviewShipReceipt(this)">
                                    </div>
                                    <div class="sd-receipt-actions" id="sdReceiptActions" hidden>
                                        <button type="button" onclick="openImageLightbox(document.getElementById('sdReceiptImg').src, 'Shipping Receipt')">View</button>
                                        <button type="button" class="sd-remove" onclick="figurifyClearShipReceipt()">Remove</button>
                                    </div>
                                </div>

                                <div class="sd-action-row">
                                    <button type="button" class="sd-return-btn" onclick="figurifyToggleReturnModal(true)">Return to Customer</button>
                                    <button type="submit" class="sd-ship-btn">Ship Out</button>
                                </div>

                            </form>


                            <!-- ======== RETURN TO CUSTOMER POP UP: hindi mahanap ng courier ang address / mali ang details ======== -->
                            <div class="sd-return-overlay" id="sdReturnModal">
                                <div class="sd-return-box" role="dialog" aria-modal="true">

                                    <h3>Return to Customer</h3>

                                    <p class="sd-return-desc">
                                        Use this when LBC or Lalamove cannot deliver using these shipping details.
                                        The customer will see the reason in My Orders and update the details, then the order comes back here as Ready to Ship.
                                    </p>

                                    <form method="POST" action="update_active_order.php" class="sd-return-form">
                                        <input type="hidden" name="order_id" value="<?php echo (int) $selectedShipment["order_id"]; ?>">
                                        <input type="hidden" name="action" value="return_address">
                                        <input type="hidden" name="redirect_to" value="shipments">

                                        <select name="return_reason" required>
                                            <option value="" disabled selected>Select a reason…</option>
                                            <?php foreach (FIGURIFY_RETURN_ADDRESS_REASONS as $returnKey => $returnLabel): ?>
                                                <option value="<?php echo e($returnKey); ?>"><?php echo e($returnLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                        <div class="sd-return-actions">
                                            <button type="button" class="sd-return-cancel" onclick="figurifyToggleReturnModal(false)">Cancel</button>
                                            <button type="submit" class="sd-return-send">Return to Customer</button>
                                        </div>
                                    </form>

                                </div>
                            </div>
                            <!-- ======== END RETURN TO CUSTOMER POP UP ======== -->

                            
                        <?php elseif ($selectedShipmentStatus === "address_returned"): ?>

                            <!-- ibinalik sa customer: hinihintay na ayusin ang shipping details -->
                            <div class="sd-return-note">
                                <strong>Waiting for the customer</strong>
                                <p>This order was returned to the customer to fix the shipping details. It will move back to Ready to Ship once they update it.</p>
                                <?php $returnReason = figurify_wf_latest_reason($conn, (int) $selectedShipment["order_id"], "ADDRESS_RETURNED"); ?>
                                <?php if ($returnReason !== null): ?>
                                    <p><b>Reason:</b> <?php echo e($returnReason); ?></p>
                                <?php endif; ?>
                            </div>

                        <?php elseif ($selectedShipmentStatus === "shipped"): ?>

                            <?php figurify_render_shipping_receipt((int) $selectedShipment["order_id"], "staff"); ?>

                            <?php echo figurify_delivery_problem_html(figurify_delivery_problem($conn, (int) $selectedShipment["order_id"]), "Customer reported a delivery problem"); ?>

                            <!-- ======== MARK AS COMPLETED (shipped) ======== -->
                            <form
                                method="POST"
                                action="update_active_order.php"
                                class="sd-complete-form"
                                onsubmit="return csConfirmSubmit(this, 'Mark this order as completed?\nUse this when the courier or the customer confirmed that the order arrived.', {okText: 'Complete'});"
                            >
                                <input type="hidden" name="order_id" value="<?php echo (int) $selectedShipment["order_id"]; ?>">
                                <input type="hidden" name="action" value="complete_order">
                                <input type="hidden" name="redirect_to" value="shipments">
                                <button type="submit" class="sd-complete-btn">Mark as Completed</button>
                            </form>
                            <!-- ======== END MARK AS COMPLETED ======== -->


                        <?php else: ?>

                            <?php figurify_render_shipping_receipt((int) $selectedShipment["order_id"], "staff"); ?>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>


</main>

</div>


<!-- image lightbox for reference photos / box -->

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

// ---- FILTER DROPDOWN ----

/* filter dropdown - closes on click-outside or Escape (Escape handler is the shared one below) */

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

// ---- SHIPMENT SEARCH + SCROLL MEMORY ----
/* search shipments - same pattern as qvFilterOrders */

function qvFilterShipments(query) {

    var q = query.trim().toLowerCase();
    var rows = document.querySelectorAll("#qvShipmentList .qv-row");
    var visibleCount = 0;

    rows.forEach(function (row) {

        var matches = row.dataset.search.indexOf(q) !== -1;

        row.classList.toggle("qv-hidden", !matches);

        if (matches) {
            visibleCount++;
        }

    });

    var footer = document.getElementById("qvListFooter");

    if (footer) {
        footer.textContent = "Showing " + visibleCount + " of " + rows.length + " shipments";
    }

}


/* keep scroll position across shipment clicks - sessionStorage, same pattern as
   Quotation/Verification/Active Booking (normal navigation reloads the page). */

(function () {

    var panels = [
        { el: document.getElementById("qvShipmentList"), key: "qvShipmentListScroll" },
        { el: document.querySelector(".qv-right-col"), key: "qvShipmentDetailScroll" }
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

    document.querySelectorAll("#qvShipmentList .qv-row").forEach(function (row) {
        row.addEventListener("click", function () {

            panels.forEach(function (panel) {

                if (panel.el) {
                    sessionStorage.setItem(panel.key, panel.el.scrollTop);
                }

            });

        });
    });

})();

// ---- IMAGE LIGHTBOX, ESCAPE KEY, FIGURE TABS ----

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


/* switch which figure's details show, for orders with 2+ figures */

function selectFigure(button) {

    var panelsWrap = button.closest(".qv-figure-panels");

    if (!panelsWrap) {
        return;
    }

    var targetId = button.dataset.figureTarget;

    // each figure panel has its own copy of the tabs (foreach), so sync the "active" state on every copy, not just the one clicked
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

// ---- SHIPPING RECEIPT PHOTO PREVIEW ----

function figurifyPreviewShipReceipt(input) {
    var file = input.files && input.files[0];
    if (!file) { figurifyClearShipReceipt(); return; }
    if (!/^image\//.test(file.type)) {
        if (window.csToast) { csToast("Please choose an image file.", "error"); }
        figurifyClearShipReceipt();
        return;
    }
    var reader = new FileReader();
    reader.onload = function (e) {
        var img = document.getElementById("sdReceiptImg");
        img.src = e.target.result;
        img.hidden = false;
        document.getElementById("sdReceiptEmpty").hidden = true;
        document.getElementById("sdReceiptActions").hidden = false;
    };
    reader.readAsDataURL(file);
}
function figurifyClearShipReceipt() {
    document.getElementById("ship_receipt").value = "";
    var img = document.getElementById("sdReceiptImg");
    img.src = "";
    img.hidden = true;
    document.getElementById("sdReceiptEmpty").hidden = false;
    document.getElementById("sdReceiptActions").hidden = true;
}

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
/* ======== RETURN TO CUSTOMER POP UP (Shipments, Ready to Ship) ======== */
function figurifyToggleReturnModal(show) {
    document.getElementById("sdReturnModal").classList.toggle("active", show);
    document.body.classList.toggle("no-scroll", show);
}
/* ======== END RETURN TO CUSTOMER POP UP ======== */
</script>

</body>

</html>