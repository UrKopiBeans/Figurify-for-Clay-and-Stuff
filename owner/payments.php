<?php

require_once "owner-header.php";

$figurifyRole    = "owner";

/* Payments page: kita kung bayad na ang Down Payment (50%) at Balance.
   Galing sa status ng order:
   - down payment bayad na kapag processing pataas
   - balance bayad na kapag to_ship pataas */

$paymentApprovedStatuses = [
    "processing", "awaiting_balance", "to_verify_balance",
    "for_balance_approval", "to_ship", "address_returned", "shipped", "completed",
];

$bookings = [];

$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_type, o.order_method, o.ship_out_date,
            o.total_amount, o.payment_option, o.status, o.created_at,
            o.shipping_fee, o.balance_to_pay,
            o.payment_reference, o.payment_proof, o.paid_at,
            o.balance_payment_reference, o.balance_proof, o.balance_paid_at,
            u.full_name, u.email
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     ORDER BY o.created_at DESC"
);

$stmt->execute();

$bookingsResult = $stmt->get_result();

while ($row = $bookingsResult->fetch_assoc()) {

    $statusKey = figurify_status_key($row["status"] ?? "");

    // na-approve na ng owner ang down payment lang ang ipinapakita
    if (!in_array($statusKey, $paymentApprovedStatuses, true)) {
        continue;
    }

    $total = (float) ($row["total_amount"] ?? 0);

    $row["status_key"]      = $statusKey;
    $row["is_full_payment"] = figurify_wf_is_full_payment($row);   // Full Payment: walang balance
    $row["dp_amount"]       = figurify_wf_downpayment_amount($row);
    $row["balance_amount"]  = ($row["balance_to_pay"] !== null && $row["balance_to_pay"] !== "")
        ? (float) $row["balance_to_pay"]
        : figurify_wf_balance_amount($row);
    $row["balance_state"]   = $row["is_full_payment"]
        ? ["key" => "fully_paid", "label" => "Paid", "class" => "pay-badge-paid"]
        : figurifyBalanceState($statusKey);

    $bookings[] = $row;
}

$stmt->close();

$paymentCounts = [
    "all"             => count($bookings),
    "fully_paid"      => 0,
    "balance_review"  => 0,
    "balance_unpaid"  => 0,
    "balance_not_due" => 0,
];

foreach ($bookings as $countRow) {
    $paymentCounts[$countRow["balance_state"]["key"]]++;
}

/* select-left, view-right - kapareho ng ibang qv- pages */
$selectedOrderId = isset($_GET["order_id"])
    ? (int) $_GET["order_id"]
    : 0;

$selectedBooking = null;

foreach ($bookings as $bookingRow) {

    if ((int) $bookingRow["order_id"] === $selectedOrderId) {
        $selectedBooking = $bookingRow;
        break;
    }

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

<title>Figurify | Payments</title>

<!-- ===================== STYLE NG PAGE NA ITO: payments.css ===================== -->
<link rel="stylesheet" href="payments.css">

</head>


<body class="quotation-page">

<div class="app">


<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<main class="main">


    <header class="topbar">

        <div class="welcome">

            <h2 class="portal-title">
                Payments
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


    <!-- Payments - qv- workspace: lahat ng order na na-approve na ng owner,
         at kung bayad na ang Down Payment at Balance -->

    <section class="card qv-workspace">

        <div class="qv-shell">

            <!-- LEFT: PAYMENT LIST -->

            <div class="qv-list-panel">

                <div class="qv-toolbar">

                    <div class="qv-filter-dropdown" id="qvStatusFilterDropdown">

                        <button
                            type="button"
                            class="qv-filter-trigger"
                            onclick="figurifyToggleFilterDropdown('qvStatusFilterDropdown')"
                        >
                            <span id="qvStatusFilterLabel">All (<?php echo (int) $paymentCounts["all"]; ?>)</span>
                            <svg class="qv-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div class="qv-filter-menu">

                            <?php
                            $paymentFilters = [
                                "all"             => "All",
                                "fully_paid"      => "Fully Paid",
                                "balance_review"  => "Balance For Approval",
                                "balance_unpaid"  => "Balance Unpaid",
                                "balance_not_due" => "Balance Not Yet Due",
                            ];
                            ?>

                            <?php foreach ($paymentFilters as $filterKey => $filterLabel): ?>
                                <?php $filterText = $filterLabel . " (" . (int) $paymentCounts[$filterKey] . ")"; ?>
                                <a
                                    href="javascript:void(0)"
                                    class="qv-filter-option<?php echo $filterKey === "all" ? " active" : ""; ?>"
                                    onclick="qvSetAllBookingsFilter(this, '<?php echo e($filterKey); ?>', '<?php echo e($filterText); ?>')"
                                >
                                    <?php echo e($filterText); ?>
                                </a>
                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div class="qv-search">

                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>

                        <input
                            type="text"
                            id="qvAllBookingsSearch"
                            placeholder="Search order ID, name..."
                            oninput="qvFilterAllBookings()"
                        >

                    </div>

                </div>

                <?php if (!empty($bookings)): ?>

                    <div class="qv-list" id="qvAllBookingsList">

                        <table class="qv-table">

                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Email</th>
                                    <th>Order Type</th>
                                    <th>Total</th>
                                    <th>Down</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($bookings as $booking): ?>

                                    <?php
                                    $isActive = ((int) $booking["order_id"] === $selectedOrderId);
                                    $balanceState = $booking["balance_state"];
                                    ?>

                                    <tr
                                        class="qv-row<?php echo $isActive ? " active" : ""; ?>"
                                        data-status="<?php echo e($balanceState["key"]); ?>"
                                        data-search="<?php echo e(strtolower(
                                            "fg-" . $booking["order_id"] . " #" . $booking["order_id"] . " " .
                                            ($booking["full_name"] ?? "") . " " .
                                            ($booking["email"] ?? "") . " " .
                                            ($booking["payment_reference"] ?? "") . " " .
                                            ($booking["balance_payment_reference"] ?? "") . " " .
                                            $booking["order_type"]
                                        )); ?>"
                                        data-href="payments.php?order_id=<?php echo (int) $booking["order_id"]; ?>"
                                        onclick="window.location.href=this.dataset.href;"
                                    >
                                        <td class="qv-cell-id">#<?php echo (int) $booking["order_id"]; ?></td>
                                        <td><?php echo e($booking["email"] ?? "N/A"); ?></td>
                                        <td><?php echo ($booking["order_type"] ?? "") === "rush" ? "Rush" : "Non-rush"; ?></td>
                                        <?php
                                        /* natitirang balance + payment status (Fully paid / Balance due) */
                                        $rowBalanceLeft = $balanceState["key"] === "fully_paid" ? 0 : (float) $booking["balance_amount"];
                                        $rowPayStatus   = [
                                            "fully_paid"      => ["Fully paid", "op-chip-ok"],
                                            "balance_review"  => ["Balance in review", "op-chip-ship"],
                                            "balance_unpaid"  => ["Balance due", "op-chip-warn"],
                                            "balance_not_due" => ["Balance not due", "op-chip-muted"],
                                        ][$balanceState["key"]] ?? [$balanceState["label"], "op-chip-muted"];
                                        ?>
                                        <td><span class="pay-amount">₱<?php echo number_format((float) $booking["total_amount"], 2); ?></span></td>
                                        <td>₱<?php echo number_format((float) $booking["dp_amount"], 2); ?></td>
                                        <td>₱<?php echo number_format($rowBalanceLeft, 2); ?></td>
                                        <td><span class="op-chip <?php echo $rowPayStatus[1]; ?>"><?php echo e($rowPayStatus[0]); ?></span></td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <div class="qv-list-footer" id="qvListFooter">
                        Showing <?php echo count($bookings); ?> of <?php echo count($bookings); ?> orders
                    </div>

                <?php else: ?>

                    <div class="qv-empty">
                        No approved payments yet.
                    </div>

                <?php endif; ?>

            </div>


            <!-- RIGHT: PAYMENT DETAILS -->

            <?php if (!$selectedBooking): ?>

                <div class="qv-right-col qv-right-col--blank">

                    <div class="qv-summary">
                        <div class="qv-summary-head"><h2>Payment Details</h2></div>
                        <div class="qv-summary-body">
                            <div class="qv-empty">
                                Select an order to see its payments.
                            </div>
                        </div>
                    </div>

                </div>

            <?php else: ?>

                <?php
                $balanceState = $selectedBooking["balance_state"];
                $isFullyPaid  = ($balanceState["key"] === "fully_paid");
                ?>

                <div class="qv-right-col">

                    <!-- TITLE + PAYMENT STATUS (Design System) -->
                    <div class="op-head">
                        <span class="op-pay-chip <?php echo $isFullyPaid ? "is-full" : "is-partial"; ?>">
                            Payment Status: <?php echo $isFullyPaid ? "Fully paid" : "Down payment only"; ?>
                        </span>
                    </div>

                    <div class="qv-summary">

                        <div class="qv-summary-body">

                            <div class="qv-line-list">
                                <div class="qv-line">
                                    <span class="qv-line-label">Customer</span>
                                    <span class="qv-line-value"><?php echo e($selectedBooking["full_name"] ?? "N/A"); ?></span>
                                </div>
                                <div class="qv-line">
                                    <span class="qv-line-label">Email</span>
                                    <span class="qv-line-value"><?php echo e($selectedBooking["email"] ?? "N/A"); ?></span>
                                </div>
                                <div class="qv-line">
                                    <span class="qv-line-label">Order Type</span>
                                    <span class="qv-line-value"><?php echo ($selectedBooking["order_type"] ?? "") === "rush" ? "Rush" : "Non-rush"; ?></span>
                                </div>
                                <div class="qv-line">
                                    <span class="qv-line-label">Total Amount</span>
                                    <span class="qv-line-value">₱<?php echo number_format((float) ($selectedBooking["total_amount"] ?? 0), 2, 2); ?></span>
                                </div>
                            </div>

                            <!-- DOWN PAYMENT -->

                            <div class="staff-payment-details pay-section">

                                <div class="pay-section-head">
                                    <div class="staff-payment-details-title"><?php echo e(figurify_wf_downpayment_label($selectedBooking)); ?></div>
                                    <?php echo figurifyPayBadge("Paid", "pay-badge-paid"); ?>
                                </div>

                                <div class="staff-payment-details-grid">

                                    <span>
                                        <strong>Amount:</strong>
                                        ₱<?php echo number_format((float) $selectedBooking["dp_amount"], 2, 2); ?>
                                    </span>

                                    <span>
                                        <strong>Reference No.:</strong>
                                        <?php echo e($selectedBooking["payment_reference"] ?: "N/A"); ?>
                                    </span>

                                    <span>
                                        <strong>Submitted:</strong>
                                        <?php echo !empty($selectedBooking["paid_at"])
                                            ? date("F j, Y, g:i A", strtotime($selectedBooking["paid_at"]))
                                            : "N/A"; ?>
                                    </span>

                                </div>

                                <div class="pay-note">Verified and approved by the owner.</div>

                                <?php if (!empty($selectedBooking["payment_proof"])): ?>

                                    <div class="staff-payment-proof">

                                        <div class="staff-payment-proof-title">Proof of Payment</div>

                                        <img
                                            src="<?php echo htmlspecialchars(figurify_proof_url($selectedBooking["payment_proof"]), ENT_QUOTES, "UTF-8"); ?>"
                                            alt="Down payment proof for order <?php echo (int) $selectedBooking["order_id"]; ?>"
                                            class="staff-payment-proof-thumb"
                                            onclick="openImageLightbox('<?php echo htmlspecialchars(figurify_proof_url($selectedBooking["payment_proof"]), ENT_QUOTES, "UTF-8"); ?>', 'Down Payment for Order #<?php echo (int) $selectedBooking["order_id"]; ?>')"
                                        >

                                    </div>

                                <?php endif; ?>

                            </div>

                            <?php if (!$selectedBooking["is_full_payment"]): ?>
                            <!-- BALANCE -->

                            <div class="staff-payment-details pay-section">

                                <div class="pay-section-head">
                                    <div class="staff-payment-details-title">Balance Payment</div>
                                    <?php echo figurifyPayBadge($balanceState["label"], $balanceState["class"]); ?>
                                </div>

                                <div class="staff-payment-details-grid">

                                    <span>
                                        <strong>Amount:</strong>
                                        ₱<?php echo number_format((float) $selectedBooking["balance_amount"], 2, 2); ?>
                                    </span>

                                    <span>
                                        <strong>Reference No.:</strong>
                                        <?php echo e($selectedBooking["balance_payment_reference"] ?: "N/A"); ?>
                                    </span>

                                    <span>
                                        <strong>Submitted:</strong>
                                        <?php echo !empty($selectedBooking["balance_paid_at"])
                                            ? date("F j, Y, g:i A", strtotime($selectedBooking["balance_paid_at"]))
                                            : "N/A"; ?>
                                    </span>

                                </div>

                                <div class="pay-note">
                                    <?php
                                    switch ($balanceState["key"]) {
                                        case "fully_paid":
                                            echo "Verified and approved by the owner. This order is fully paid.";
                                            break;
                                        case "balance_review":
                                            echo "The customer has submitted the balance payment. Waiting for the owner's approval in For Approval.";
                                            break;
                                        case "balance_unpaid":
                                            echo "The balance has been requested. Waiting for the customer to pay.";
                                            break;
                                        default:
                                            echo "The figure is still being made. The balance will be requested before shipping.";
                                    }
                                    ?>
                                </div>

                                <?php if (!empty($selectedBooking["balance_proof"])): ?>

                                    <div class="staff-payment-proof">

                                        <div class="staff-payment-proof-title">Proof of Payment</div>

                                        <img
                                            src="<?php echo htmlspecialchars(figurify_proof_url($selectedBooking["balance_proof"]), ENT_QUOTES, "UTF-8"); ?>"
                                            alt="Balance proof for order <?php echo (int) $selectedBooking["order_id"]; ?>"
                                            class="staff-payment-proof-thumb"
                                            onclick="openImageLightbox('<?php echo htmlspecialchars(figurify_proof_url($selectedBooking["balance_proof"]), ENT_QUOTES, "UTF-8"); ?>', 'Balance for Order #<?php echo (int) $selectedBooking["order_id"]; ?>')"
                                        >

                                    </div>

                                <?php endif; ?>

                            </div>
                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </section>


</main>

</div>


<!-- Image lightbox for figure photos - copied from active-booking.php/completed.php -->

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
/* Filter dropdown - only one open at a time, closes on outside click or Escape. */

var qvAllBookingsStatusFilter = "all";

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

function qvSetAllBookingsFilter(el, value, label) {

    qvAllBookingsStatusFilter = value;

    var labelEl = document.getElementById("qvStatusFilterLabel");
    if (labelEl) {
        labelEl.textContent = label;
    }

    var dropdown = document.getElementById("qvStatusFilterDropdown");

    dropdown.querySelectorAll(".qv-filter-option").forEach(function (opt) {
        opt.classList.toggle("active", opt === el);
    });

    document.querySelectorAll(".qv-filter-dropdown.open").forEach(function (dd) {
        dd.classList.remove("open");
    });

    qvFilterAllBookings();

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
        closeImageLightbox();
    }

});

function qvFilterAllBookings() {

    var searchInput = document.getElementById("qvAllBookingsSearch");

    var q = searchInput ? searchInput.value.trim().toLowerCase() : "";
    var statusFilter = qvAllBookingsStatusFilter || "all";

    var rows = document.querySelectorAll("#qvAllBookingsList .qv-row");
    var visibleCount = 0;

    rows.forEach(function (row) {

        var matchesSearch = row.dataset.search.indexOf(q) !== -1;
        var matchesStatus = (statusFilter === "all") || (row.dataset.status === statusFilter);
        var matches = matchesSearch && matchesStatus;

        row.classList.toggle("qv-hidden", !matches);

        if (matches) {
            visibleCount++;
        }

    });

    var footer = document.getElementById("qvListFooter");

    if (footer) {
        footer.textContent = "Showing " + visibleCount + " of " + rows.length + " orders";
    }

}

/* KEEP SCROLL POSITION ACROSS ORDER CLICKS */

(function () {

    var panels = [
        { el: document.getElementById("qvAllBookingsList"), key: "qvPaymentsListScroll" },
        { el: document.querySelector(".qv-right-col"), key: "qvPaymentsDetailScroll" }
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

    document.querySelectorAll("#qvAllBookingsList .qv-row").forEach(function (row) {
        row.addEventListener("click", function () {

            panels.forEach(function (panel) {

                if (panel.el) {
                    sessionStorage.setItem(panel.key, panel.el.scrollTop);
                }

            });

        });
    });

})();

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

</script>
</body>

</html>