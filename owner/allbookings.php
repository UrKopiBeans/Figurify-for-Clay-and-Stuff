<?php

require_once "owner-header.php";

$figurifyRole    = "owner";
$figurifyPageDir = __DIR__;

$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";

/* Same helpers ng quotation.php/active-booking.php para magkatugma
   ang Figure Details + Payment na makikita dito. */

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


/* Shared by owner/allbookings.php and staff/allbookings.php.
   Entry file needs to set $figurifyRole and $figurifyPageDir before requiring this. */

$bookings = [];

/* Get all orders, no status filter. Try the newer columns first
   (tracking_number/shipping_fee/etc), fallback to old columns if not in DB yet. */
$bookingsHaveShippingColumns = true;

try {

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.order_method, o.booking_date,
                o.rush_fee, o.subtotal, o.total_amount, o.status, o.created_at,
                o.shipping_name, o.shipping_address, o.shipping_contact, o.courier,
                o.tracking_number, o.shipping_fee, o.balance_to_pay, o.shipped_at,
                o.payment_reference, o.payment_proof, o.paid_at,
                o.refund_account_name, o.refund_account_number, o.refund_method,
                u.full_name, u.email
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         ORDER BY o.created_at DESC"
    );

    if ($stmt === false) {
        throw new \mysqli_sql_exception("prepare() returned false");
    }

} catch (\Throwable $e) {

    $bookingsHaveShippingColumns = false;

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.order_method, o.booking_date,
                o.rush_fee, o.subtotal, o.total_amount, o.status, o.created_at,
                o.shipping_name, o.shipping_address, o.shipping_contact, o.courier,
                o.payment_reference, o.payment_proof, o.paid_at,
                o.refund_account_name, o.refund_account_number, o.refund_method,
                u.full_name, u.email
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         ORDER BY o.created_at DESC"
    );

}

$stmt->execute();

$bookingsResult = $stmt->get_result();

while ($row = $bookingsResult->fetch_assoc()) {

    if (!$bookingsHaveShippingColumns) {
        $row["tracking_number"] = null;
        $row["shipping_fee"]    = null;
        $row["balance_to_pay"]  = null;
        $row["shipped_at"]      = null;
    }

    $bookings[] = $row;
}

$stmt->close();

/* Parehong "select an order on the left, view details on the
   right" na pattern ito ng ibang qv- pages. */
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

$selectedOrder = &$selectedBooking;

/* Kunin lang yung figures ng napiling order, hindi lahat, para hindi bigat sa db. Same pattern sa completed.php/cancelled.php. */

if ($selectedOrder) {

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

    $figures = [];

    if ($fstmt !== false) {

        $fstmt->bind_param("i", $selectedOrder["order_id"]);
        $fstmt->execute();

        $fresult = $fstmt->get_result();

        while ($frow = $fresult->fetch_assoc()) {

            if (!$figuresHaveExtendedHironoColumns) {
                $frow["hirono_box_design"]        = null;
                $frow["hirono_blind_items"]       = null;
                $frow["hirono_blind_items_total"] = 0;
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

    }

    $selectedOrder["figures"] = $figures;

}

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

<title>Figurify — All Bookings</title>

<style>
/* Shared dashboard styles - owner.css/staff.css just @import this, role-specific stuff stays in those files. */


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

/* CARDS */

.card {
    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 18px;

    padding: 17px;

    box-shadow:
        0 5px 16px rgba(71,45,55,.04);
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

/* Dashboard layout: left col = stats/calendar/recent bookings, right col = shipment card */

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

/* Status colors - same source as Staff Dashboard, Calendar, and customer My Orders (Admin/order_status_helper.php). */

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

/* Dashboard calendar (#calendarDays) ay read-only preview lang -
   pointer-events:none para di mag-trigger ng :hover. */
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

/* Completed orders table - separate from .booking-row since this has 6 columns (Customer + Courier), not 5. Same as Staff Dashboard. */

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



/* All Bookings page - same qv- workspace as other qv- pages, but one list with no status filter, and read-only (no edit/quote/verify here). */

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

@media (max-width: 1180px){
    .qv-shell{
        grid-template-columns: 1fr;
    }
}

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

.qv-toolbar{
    margin-top: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

/* Filter dropdown - same style as Active Booking / Quotation / Shipments. One button showing current filter, click to see all statuses. */

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
    max-height: 320px;
    overflow-y: auto;

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
    cursor: pointer;
}

.qv-filter-option:hover{
    background: var(--background);
}

.qv-filter-option.active{
    background: var(--pink-light);
    color: var(--pink-dark);
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

.qv-status-select{
    background: var(--background);
    border: 1px solid var(--gray-light);
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 12px;
    color: var(--black);
    
    outline: none;
    cursor: pointer;
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
.qv-table th:nth-child(2), .qv-table td:nth-child(2) { width: 27%; }
.qv-table th:nth-child(3), .qv-table td:nth-child(3) { width: 12%; }
.qv-table th:nth-child(4), .qv-table td:nth-child(4) { width: 18%; }
.qv-table th:nth-child(5), .qv-table td:nth-child(5) { width: 15%; }
.qv-table th:nth-child(6), .qv-table td:nth-child(6) { width: 20%; }

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

.qv-list-footer{
    margin-top: 14px;
    font-size: 11px;
    color: var(--gray);
    text-align: center;
}

.qv-right-col{
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.qv-detail-col{
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.qv-header-card{
    background: var(--white);
    border-radius: 20px;
    border: 1.5px solid #C9BEC3;
    box-shadow: 5px 7px 0 rgba(216, 194, 222, .25);
    padding: 16px 20px;
}

.qv-header-card h2{
    font-size: 16px;
    color: var(--pink-dark);
    margin-bottom: 12px;
}

.order-info-grid{
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

@media (max-width: 520px){
    .order-info-grid{grid-template-columns: 1fr;}
}

.info-chip{
    background: #fffaf6;
    border: 1px solid #f5e6ee;
    border-radius: 12px;
    padding: 8px 10px;
    font-size: 11px;
    min-width: 0;
}

.info-chip .dt{
    font-size: 9px;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: .3px;
    display: block;
}

.info-chip .dd{
    font-size: 11px;
    font-weight: bold;
    color: var(--pink-dark);
    white-space: normal;
    overflow-wrap: break-word;
    word-break: break-word;
}

.qv-summary{
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    background: var(--white);
    border-radius: 18px;
    border: 1px solid var(--gray-light);
    box-shadow: 4px 5px 0 rgba(216, 194, 222, .2);
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
}

.qv-line{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.qv-line-label{
    font-size: 12.5px;
    font-weight: 600;
    color: var(--black);
}

.qv-line-value{
    font-size: 13px;
    font-weight: 700;
    text-align: right;
}

.qv-empty{
    padding: 40px 16px;
    text-align: center;
    color: var(--gray);
    font-size: 13px;
}



/* FIGURE DETAILS + PAYMENT - same classes as active-booking.php
   (stacked Figure Details + Custom Box, hindi magkatabi). */

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

.qv-details-head{
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--pink-dark);
    font-weight: 800;
    font-size: 13px;
    margin-bottom: 14px;
}

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

.qv-spec-full{
    grid-column: 1 / -1;
}

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


/* PAYMENT / SHIPPING DETAILS */

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



/* Status badge colors, used by figurify_status_badge in the qv-table Status column. Same source as Quotation/Verification/Active Booking. */

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

/* Payment & Shipping: iisang container lang (walang container sa loob ng container) */
.qv-right-col .qv-summary .staff-payment-details{
    background: transparent !important;
    border: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    border-radius: 0 !important;
}
.qv-right-col .qv-summary .staff-payment-details + .staff-payment-details{
    border-top: 1px dashed #E6D5DF !important;
    padding-top: 14px !important;
}

/* Total Amount: nasa baba ng Customer Notes / ng 3D figure */
.qv-total-box{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px dashed #E6D5DF;
}
.qv-total-label{
    font-size: 12px;
    font-weight: 700;
    color: #7b315b;
}
.qv-total-value{
    font-size: 18px;
    font-weight: 800;
    color: #7b315b;
}
.dressup-3d-column .qv-total-box{
    margin-top: 10px;
}
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


<body class="quotation-page">

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
<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<main class="main">


    <header class="topbar">

        <div class="welcome">

            <h2 class="portal-title">
                All Bookings
            </h2>

        </div>


        <div class="top-actions">

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


    <!-- All Bookings - qv- workspace -->
    <!-- All Bookings: listahan ng lahat ng order, search lang. Figure Details at Payment sa kanan kapag may napili. -->

    <section class="card qv-workspace" style="padding: 10px 0 20px 0;">

        <div class="qv-shell">

            <!-- LEFT: BOOKING LIST -->

            <div class="qv-list-panel">

                <div class="qv-toolbar">

                    <div class="qv-filter-dropdown" id="qvStatusFilterDropdown">

                        <button
                            type="button"
                            class="qv-filter-trigger"
                            onclick="figurifyToggleFilterDropdown('qvStatusFilterDropdown')"
                        >
                            <span id="qvStatusFilterLabel">All</span>
                            <svg class="qv-filter-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div class="qv-filter-menu">

                            <a
                                href="javascript:void(0)"
                                class="qv-filter-option active"
                                onclick="qvSetAllBookingsFilter(this, 'all', 'All')"
                            >
                                All
                            </a>

                            <?php foreach (FIGURIFY_ORDER_STATUSES as $statusKey => $statusInfo): ?>
                                <a
                                    href="javascript:void(0)"
                                    class="qv-filter-option"
                                    onclick="qvSetAllBookingsFilter(this, '<?php echo e($statusKey); ?>', '<?php echo e($statusInfo["label"]); ?>')"
                                >
                                    <?php echo e($statusInfo["label"]); ?>
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
                            placeholder="Search order #, customer name..."
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
                                    <th>Order Method</th>
                                    <th>Book Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($bookings as $booking): ?>

                                    <?php

                                    $bookingDate = !empty($booking["booking_date"])
                                        ? date("M d, Y", strtotime($booking["booking_date"]))
                                        : "—";

                                    $isActive = ((int) $booking["order_id"] === $selectedOrderId);
                                    $statusKey = figurify_status_key($booking["status"] ?? "pending");

                                    ?>

                                    <tr
                                        class="qv-row<?php echo $isActive ? " active" : ""; ?>"
                                        data-status="<?php echo e($statusKey); ?>"
                                        data-search="<?php echo e(strtolower(
                                            "#" . $booking["order_id"] . " " . $booking["order_id"] . " fg-" . $booking["order_id"] . " " .
                                            ($booking["full_name"] ?? "") . " " .
                                            ($booking["email"] ?? "") . " " .
                                            $booking["order_type"] . " " .
                                            $booking["status"]
                                        )); ?>"
                                        data-href="allbookings.php?order_id=<?php echo (int) $booking["order_id"]; ?>"
                                        onclick="window.location.href=this.dataset.href;"
                                    >
                                        <td class="qv-cell-id">#<?php echo (int) $booking["order_id"]; ?></td>
                                        <td><?php echo e($booking["email"] ?? "—"); ?></td>
                                        <td><?php echo ($booking["order_type"] ?? "") === "rush" ? "Rush" : "Non-Rush"; ?></td>
                                        <td><?php echo e(orderMethodLabel($booking["order_method"] ?? "")); ?></td>
                                        <td><?php echo e($bookingDate); ?></td>
                                        <td><?php echo figurify_status_badge($booking["status"] ?? "pending"); ?></td>
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
                        No bookings found.
                    </div>

                <?php endif; ?>

            </div>


            <!-- RIGHT: ORDER DETAIL (Figure Details + Payment) -->

            <?php if (!$selectedBooking): ?>

                <style>.qv-right-col.qv-right-col--blank > *{display:none !important;}</style>
                <div class="qv-right-col qv-right-col--blank">

                    <div class="qv-detail-col">
                        <div class="qv-header-card">
                            <div class="qv-empty">
                                Select an order on the left to view its details.
                            </div>
                        </div>
                    </div>

                    <div class="qv-summary">
                        <div class="qv-summary-head"><h2>Payment</h2></div>
                        <div class="qv-summary-body">
                            <div class="qv-empty">Nothing to show yet.</div>
                        </div>
                    </div>

                </div>

            <?php else: ?>

                <div class="qv-right-col">

                    <!-- PANEL HEAD: Order # + status -->
                    <div class="qv-panel-head">
                        <h2 class="qv-panel-title">Order #<?php echo (int) $selectedBooking["order_id"]; ?></h2>
                    </div>

                    <div class="qv-detail-col">

                        <?php if (empty($selectedBooking["figures"])): ?>

                            <div class="qv-header-card">
                                <div class="qv-empty">No figure details found for this order.</div>
                            </div>

                        <?php else: ?>

                        <?php
                        // Total Amount: nasa baba ng Customer Notes (Image Submission) o ng 3D figure (Dress Up)
                        $qvTotalAmountHtml =
                            '<div class="qv-total-box">' .
                                '<span class="qv-total-label">Total Amount</span>' .
                                '<strong class="qv-total-value">₱' . number_format((float) ($selectedBooking["total_amount"] ?? 0), 2) . '</strong>' .
                            '</div>';
                        ?>

                        <div class="qv-figure-panels">

                            <?php foreach ($selectedBooking["figures"] as $qIndex => $fig): ?>

                            <?php
                            /* rush fee kada figure (₱200 Head Only, ₱500 iba) + breakdown bago ang Total Amount */
                            require_once __DIR__ . "/../helpers/rush_split_helper.php";
                            $qvOrderFigures = $selectedBooking["figures"] ?? [];
                            $qvRushSplit    = figurify_rush_split($selectedBooking, $qvOrderFigures);
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
                                . '<strong class="qv-total-line-value">₱' . number_format((float) ($selectedBooking["total_amount"] ?? 0), 2) . '</strong></div></div>';
                            ?>

                                <?php
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

                                $panelId = "allbookings-figure-panel-" . (int) $selectedBooking["order_id"] . "-" . $qIndex;
                                ?>

                                <div
                                    class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                    id="<?php echo $panelId; ?>"
                                >

                                    <div class="qv-viewer-grid<?php echo figurify_dressup_for_figure($fig) !== null ? " dressup-grid" : ""; ?>">

                                    <div class="qv-viewer-column">

                                    <div class="qv-figure-card">

                                        <?php if (count($selectedBooking["figures"]) > 1): ?>

                                            <div class="qv-figure-tabs">

                                                <?php foreach ($selectedBooking["figures"] as $tabIndex => $tabFig): ?>

                                                    <button
                                                        type="button"
                                                        class="qv-figure-tab-pill<?php echo ($tabIndex === $qIndex) ? " active" : ""; ?>"
                                                        data-figure-target="allbookings-figure-panel-<?php echo (int) $selectedBooking["order_id"]; ?>-<?php echo $tabIndex; ?>"
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

                                        <?php if (figurify_dressup_for_figure($fig) === null): ?>
                                        <?php endif; ?>

                                    <?php if (!$qvFigHasBox) { echo $qvTotalLine; } ?>

                                </div>
                                    <!-- /.qv-figure-card -->

                                    </div>
                                    <!-- /.qv-viewer-column (figure details) -->


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
                                echo ob_get_clean();

                                } catch (\Throwable $figureRenderError) {

                                    if (ob_get_level() > 0) {
                                        ob_end_clean();
                                    }
                                    ?>

                                    <div
                                        class="qv-figure-panel<?php echo ($qIndex === 0) ? " active" : ""; ?>"
                                        id="allbookings-figure-panel-<?php echo (int) $selectedBooking["order_id"]; ?>-<?php echo $qIndex; ?>"
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

                        <?php endif; ?>

                    </div>

                    <?php
                    $qvHasPaymentInfo = !empty($selectedBooking["payment_reference"]) || !empty($selectedBooking["payment_proof"]) || !empty($selectedBooking["shipping_name"]);
                    $qvHasRefundInfo  = !empty($selectedBooking["refund_account_name"]) || !empty($selectedBooking["refund_account_number"]) || !empty($selectedBooking["refund_method"]);
                    ?>

                    <?php /* walang container kapag wala pang naisumiteng bayad/shipping/refund (hal. Pending, Awaiting Payment) */ ?>
                    <?php if ($qvHasPaymentInfo || $qvHasRefundInfo): ?>

                    <div class="qv-summary">

                        <div class="qv-summary-head">
                            <h2><?php echo $qvHasPaymentInfo ? "Payment &amp; Shipping Information" : "Refund Account Information"; ?></h2>
                        </div>

                        <div class="qv-summary-body">

                            <?php if ($qvHasPaymentInfo): ?>
                            <div class="staff-payment-details">

                                <div class="staff-payment-details-grid">

                                    <span>
                                        <strong>Ship To:</strong>
                                        <?php echo e($selectedBooking["shipping_name"] ?? "—"); ?>
                                    </span>

                                    <span>
                                        <strong>Address:</strong>
                                        <?php echo e($selectedBooking["shipping_address"] ?? "—"); ?>
                                    </span>

                                <?php require_once __DIR__ . "/../helpers/payment_view_helper.php"; ?>
                                <?php figurify_render_pin_row((int) ($selectedBooking["order_id"] ?? 0)); ?>

                                    <span>
                                        <strong>Contact:</strong>
                                        <?php echo e($selectedBooking["shipping_contact"] ?? "—"); ?>
                                    </span>

                                    <span>
                                        <strong>Courier:</strong>
                                        <?php echo e(strtoupper((string) ($selectedBooking["courier"] ?? ""))); ?>
                                    </span>

                                    <span>
                                        <strong>Reference No.:</strong>
                                        <?php echo e($selectedBooking["payment_reference"] ?? "—"); ?>
                                    </span>

                                    <?php if (!empty($selectedBooking["paid_at"])): ?>
                                        <span>
                                            <strong>Submitted:</strong>
                                            <?php echo date("F j, Y — g:i A", strtotime($selectedBooking["paid_at"])); ?>
                                        </span>
                                    <?php endif; ?>

                                </div>

                                <?php require_once __DIR__ . "/../helpers/payment_view_helper.php"; ?>
                                <?php figurify_render_payment_proofs((int) ($selectedBooking["order_id"] ?? 0), "staff"); ?>

                            </div>
                            <?php endif; ?>

                            <?php if ($qvHasRefundInfo): ?>

                                <div class="staff-payment-details">

                                    <?php if ($qvHasPaymentInfo): ?>
                                    <div class="staff-payment-details-title">
                                        Refund Account Information
                                    </div>
                                    <?php endif; ?>

                                    <div class="staff-payment-details-grid">

                                        <span>
                                            <strong>Account Name:</strong>
                                            <?php echo e($selectedBooking["refund_account_name"] ?? "—"); ?>
                                        </span>

                                        <span>
                                            <strong>Account Number:</strong>
                                            <?php echo e($selectedBooking["refund_account_number"] ?? "—"); ?>
                                        </span>

                                        <span>
                                            <strong>Preferred Bank/Method:</strong>
                                            <?php echo e($selectedBooking["refund_method"] ?? "—"); ?>
                                        </span>

                                    </div>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </section>


</main>

</div>


<style>

.top-actions{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.filter-select{
    height:34px;
    padding:0 10px;
    border-radius:9px;
    border:1px solid #f0e0ea;
    background:#fff;
    color:#5c4654;
    font-size:11.5px;
    font-weight:700;
}

.booking-table-body{
    max-height:190px;
    overflow-y:auto;
}

.booking-table-body .booking-row:last-child{
    border-bottom:none;
}

</style>


<script src="../Shared/dashboard.js"></script>
<script>
/* FIGURIFY - OWNER DASHBOARD JS */


/* DATE & TIME */




/* DASHBOARD BUTTONS */

const viewBookings =
    document.getElementById(
        "viewBookings"
    );


viewBookings?.addEventListener(
    "click",
    function() {

        window.location.href =
            "allbookings.php";

    }
);


const viewShipments =
    document.getElementById(
        "viewShipments"
    );


viewShipments?.addEventListener(
    "click",
    function() {

        window.location.href =
            "active-booking.php";

    }
);


/* QUICK ACTIONS */

document
    .querySelectorAll(
        ".quick-btn"
    )
    .forEach(button => {

        button.addEventListener(
            "click",
            function() {

                const action =
                    this.dataset.action;


                if (
                    action ===
                    "quotation"
                ) {

                    window.location.href =
                        "quotation.php";

                }


                if (
                    action ===
                    "new-booking"
                ) {

                    window.location.href =
                        "allbookings.php";

                }

            }
        );

    });


/* DASHBOARD CALENDAR */

const calendarDays =
    document.getElementById(
        "calendarDays"
    );

const calendarMonth =
    document.getElementById(
        "calendarMonth"
    );

const previousMonth =
    document.getElementById(
        "previousMonth"
    );

const nextMonth =
    document.getElementById(
        "nextMonth"
    );


let currentCalendarDate =
    new Date();


const bookingDates =
    typeof figurifyBookedDates !== "undefined"
    ? figurifyBookedDates
    : [];


/* formatDate ay nasa Shared/dashboard.js na rin - iisang
   pinagmumulan lang para sa Owner AT Staff calendar. */


function renderCalendar() {

    if (
        !calendarDays ||
        !calendarMonth
    ) {

        return;

    }


    const year =
        currentCalendarDate
            .getFullYear();


    const month =
        currentCalendarDate
            .getMonth();


    calendarMonth.textContent =
        currentCalendarDate.toLocaleDateString(
            "en-US",
            {
                month: "long",
                year: "numeric"
            }
        );


    // Di pwede bumalik sa nakalipas na buwan - kasalukuyang buwan
    // na lang ang pinaka-maaga.
    const now = new Date();

    const isAtCurrentMonth =
        year === now.getFullYear() &&
        month === now.getMonth();

    if (previousMonth) {

        previousMonth.disabled = isAtCurrentMonth;

        previousMonth.style.opacity =
            isAtCurrentMonth ? "0.4" : "1";

        previousMonth.style.cursor =
            isAtCurrentMonth ? "not-allowed" : "pointer";

    }


    calendarDays.innerHTML = "";


    const jsFirstDay =
        new Date(
            year,
            month,
            1
        ).getDay();


    const firstDay =
        jsFirstDay === 0
            ? 6
            : jsFirstDay - 1;


    const daysInMonth =
        new Date(
            year,
            month + 1,
            0
        ).getDate();


    for (
        let i = 0;
        i < firstDay;
        i++
    ) {

        const empty =
            document.createElement(
                "div"
            );


        empty.className =
            "day empty";


        calendarDays.appendChild(
            empty
        );

    }


    const today =
        new Date();


    for (
        let day = 1;
        day <= daysInMonth;
        day++
    ) {

        const dayElement =
            document.createElement(
                "div"
            );


        dayElement.className =
            "day";


        dayElement.textContent =
            day;


        const dateString =
            formatDate(
                year,
                month,
                day
            );


        if (
            today.getFullYear() === year &&
            today.getMonth() === month &&
            today.getDate() === day
        ) {

            dayElement.classList.add(
                "today"
            );

        }


        if (
            bookingDates.includes(
                dateString
            )
        ) {

            dayElement.classList.add(
                "booked"
            );

        }


        calendarDays.appendChild(
            dayElement
        );

    }

}


previousMonth?.addEventListener(
    "click",
    function() {

        const now = new Date();

        const isAtCurrentMonth =
            currentCalendarDate.getFullYear() === now.getFullYear() &&
            currentCalendarDate.getMonth() === now.getMonth();

        if (isAtCurrentMonth) {
            return;
        }

        currentCalendarDate.setMonth(
            currentCalendarDate.getMonth() - 1
        );

        renderCalendar();

    }
);


nextMonth?.addEventListener(
    "click",
    function() {

        currentCalendarDate.setMonth(
            currentCalendarDate.getMonth() + 1
        );

        renderCalendar();

    }
);


renderCalendar();
</script>

<script>

/* SEARCH ALL BOOKINGS - same pattern ng qvFilterX sa ibang qv-
   pages. Search box na lang, walang status dropdown (dapat makita
   lahat ng order dito). */

/* Filter dropdown - only one open at a time, closes on outside click or Escape. Same behavior as the other qv- pages. */

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

    document.querySelectorAll("#qvStatusFilterDropdown .qv-filter-option").forEach(function (opt) {
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
    }

});

function qvFilterAllBookings() {

    var searchInput = document.getElementById("qvAllBookingsSearch");

    var q = searchInput ? searchInput.value.trim().toLowerCase() : "";
    var statusFilter = qvAllBookingsStatusFilter || "all";

    var rows = document.querySelectorAll("#qvAllBookingsList .qv-row");
    var visibleCount = 0;
    var matchedCount = 0;

    rows.forEach(function (row) {

        var matchesSearch = row.dataset.search.indexOf(q) !== -1;
        var matchesStatus = (statusFilter === "all") || (row.dataset.status === statusFilter);
        var matches = matchesSearch && matchesStatus;

        /* PAGINATION - 25 bawat "page"; laging nakikita ang napiling order */
        var withinLimit = (matchedCount < qvAllBookingsLimit) || row.classList.contains("active");

        if (matches) {
            matchedCount++;
        }

        row.classList.toggle("qv-hidden", !(matches && withinLimit));

        if (matches && withinLimit) {
            visibleCount++;
        }

    });

    var footer = document.getElementById("qvListFooter");

    if (footer) {
        footer.textContent = "Showing " + visibleCount + " of " + matchedCount
            + (matchedCount !== rows.length ? " matching (" + rows.length + " total)" : "") + " orders";

        if (visibleCount < matchedCount) {
            var more = document.createElement("button");
            more.type = "button";
            more.style.cssText = "margin-left:10px;font:inherit;font-size:12px;font-weight:700;padding:6px 14px;border:none;border-radius:999px;background:#F3EEFD;color:#6D28D9;cursor:pointer;";
            more.textContent = "Show more";
            more.onclick = function () {
                qvAllBookingsLimit += 25;
                qvFilterAllBookings();
            };
            footer.appendChild(more);
        }
    }

}

var qvAllBookingsLimit = 25;


/* KEEP SCROLL POSITION ACROSS ORDER CLICKS */

(function () {

    var panels = [
        { el: document.getElementById("qvAllBookingsList"), key: "qvAllBookingsListScroll" },
        { el: document.querySelector(".qv-right-col"), key: "qvAllBookingsDetailScroll" }
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

</script>


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


/* Switch which figure's details show when order has 2+ figures - only one visible at a time */

function selectFigure(button) {

    var panelsWrap = button.closest(".qv-figure-panels");

    if (!panelsWrap) {
        return;
    }

    var targetId = button.dataset.figureTarget;

    /* May sariling kopya ng figure tabs ang bawat figure panel
       (paulit-ulit na render sa foreach), kaya i-sync ang "active"
       state sa LAHAT ng kopya, hindi lang yung pinindot. */
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

</script>

</body>

</html>