<?php

require_once "owner-header.php";
require_once __DIR__ . "/../helpers/content_helper.php";
require_once __DIR__ . "/../helpers/collection_helper.php";

// Collection Management - pag-eedit ng Chibi/Hirono/Funko Pop/Special Figures na produkto sa Collection page

$grouped = figurify_collection_get_grouped($conn);

$chibiItems   = figurify_collection_filter_category($grouped["normal"], "chibi");
$hironoItems  = figurify_collection_filter_category($grouped["normal"], "hirono");
$funkoItems   = figurify_collection_filter_category($grouped["normal"], "funko");
/* hiwalay na Custom Box section */
$customItems  = figurify_collection_filter_category($grouped["special"], "custom");
$specialItems = array_values(array_filter($grouped["special"], fn($item) => $item["category"] !== "custom"));

$saved = isset($_GET["saved"]);


// card ng isang produkto (Chibi/Hirono/Funko Pop/Custom Box).
// Ginagaya rin ito ng <template> sa ibaba para sa "+ Add".
function figurify_cm_collection_item(string $groupKey, string $label, array $item, string $fallbackImg): void
{
    $itemId = (int) $item["item_id"];
    $imageValue = $item["image"] ?? "";
    $name = "items[{$groupKey}][{$itemId}]";
    ?>
    <div class="content-subgroup">
        <div class="content-subgroup-title"><?php echo e($label); ?> #<?php echo $itemId; ?></div>

        <label class="content-field content-field-full">
            Image
            <div class="content-media-field">
                <div
                    class="content-media-preview content-media-upload cm-ratio-square"
                    onclick="this.querySelector('input[type=file]').click()"
                >
                    <img src="<?php echo e(figurify_resolve_image_src($imageValue, $fallbackImg)); ?>" alt="<?php echo e($label); ?> #<?php echo $itemId; ?> preview">
                    <div class="content-media-upload-overlay">
                        <span>Click to change</span>
                    </div>
                    <input
                        type="file"
                        accept="image/*"
                        hidden
                        onchange="figurifyUploadMedia(this, 'image')"
                    >
                </div>
                <input type="hidden" name="<?php echo e($name); ?>[image]" value="<?php echo e($imageValue); ?>">
                <p class="content-media-status"></p>
            </div>
        </label>

        <input type="hidden" name="<?php echo e($name); ?>[item_id]" value="<?php echo $itemId; ?>">
        <input type="hidden" name="<?php echo e($name); ?>[remove]" value="0">

        <label class="content-field">
            Product Type
            <input type="text" name="<?php echo e($name); ?>[type]" value="<?php echo e($item["type"]); ?>" placeholder="e.g. Half-body, Standee">
        </label>

        <label class="content-field">
            Size
            <input type="text" name="<?php echo e($name); ?>[size]" value="<?php echo e($item["size"]); ?>" placeholder='e.g. 2&quot; or Custom'>
        </label>

        <label class="content-field">
            Price
            <input type="text" name="<?php echo e($name); ?>[price]" value="<?php echo e($item["price"]); ?>" placeholder="e.g. ₱450">
        </label>

        <div class="cm-item-footer">
            <label class="content-field cm-checkbox-field">
                <input type="checkbox" name="<?php echo e($name); ?>[bestseller]" value="1" <?php echo !empty($item["is_bestseller"]) ? "checked" : ""; ?>>
                Best Seller
            </label>

            <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
        </div>
    </div>
    <?php
}

// Same idea pero para sa Special Figures - free-form ang Style/Kategorya
function figurify_cm_special_item(array $item, string $fallbackImg): void
{
    $itemId = (int) $item["item_id"];
    $imageValue = $item["image"] ?? "";
    $category = $item["category"] ?? "special";
    $name = "items[special][{$itemId}]";

    $categoryOptions = [
        "funko"   => "Funko Pop",
        "special" => "Other",
    ];
    ?>
    <div class="content-subgroup">
        <div class="content-subgroup-title">Special <?php echo $itemId; ?></div>

        <label class="content-field content-field-full">
            Image
            <div class="content-media-field">
                <div
                    class="content-media-preview content-media-upload cm-ratio-square"
                    onclick="this.querySelector('input[type=file]').click()"
                >
                    <img src="<?php echo e(figurify_resolve_image_src($imageValue, $fallbackImg)); ?>" alt="Special item <?php echo $itemId; ?> preview">
                    <div class="content-media-upload-overlay">
                        <span>Click to change</span>
                    </div>
                    <input
                        type="file"
                        accept="image/*"
                        hidden
                        onchange="figurifyUploadMedia(this, 'image')"
                    >
                </div>
                <input type="hidden" name="<?php echo e($name); ?>[image]" value="<?php echo e($imageValue); ?>">
                <p class="content-media-status"></p>
            </div>
        </label>

        <input type="hidden" name="<?php echo e($name); ?>[item_id]" value="<?php echo $itemId; ?>">
        <input type="hidden" name="<?php echo e($name); ?>[remove]" value="0">

        <label class="content-field">
            Style (header)
            <input type="text" name="<?php echo e($name); ?>[style]" value="<?php echo e($item["style"]); ?>" placeholder="e.g. Pop Culture">
        </label>

        <label class="content-field">
            Category (for filter)
            <select name="<?php echo e($name); ?>[category]">
                <?php foreach ($categoryOptions as $value => $text): ?>
                    <option value="<?php echo e($value); ?>" <?php echo $category === $value ? "selected" : ""; ?>><?php echo e($text); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="content-field">
            Product Type
            <input type="text" name="<?php echo e($name); ?>[type]" value="<?php echo e($item["type"]); ?>" placeholder="e.g. Custom Figure">
        </label>

        <label class="content-field">
            Size
            <input type="text" name="<?php echo e($name); ?>[size]" value="<?php echo e($item["size"]); ?>" placeholder="e.g. Custom">
        </label>

        <label class="content-field">
            Price
            <input type="text" name="<?php echo e($name); ?>[price]" value="<?php echo e($item["price"]); ?>" placeholder="e.g. Starts at ₱500">
        </label>

        <div class="cm-item-footer">
            <label class="content-field cm-checkbox-field">
                <input type="checkbox" name="<?php echo e($name); ?>[bestseller]" value="1" <?php echo !empty($item["is_bestseller"]) ? "checked" : ""; ?>>
                Best Seller
            </label>

            <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
        </div>
    </div>
    <?php
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

<title>Figurify — Content Management</title>

<style>

/* Shared dashboard styles (layout, sidebar, cards, tables, status colors) common sa owner AT staff.
   owner.css/staff.css ay nag-@import na lang dito, role-specific styles na lang ang natitira sa kanila. */

/* FIGURIFY - OWNER DASHBOARD (Pastel / Cute / Modern / Clean) */

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

/* DASHBOARD LAYOUT - left: stats + calendar + recent bookings, right: tall shipment card */

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

/* Status colors - pareho sa Staff Dashboard/Calendar/My Orders, galing Admin/order_status_helper.php */

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

.status-shipped {
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

/* Dashboard calendar is read-only preview (unlike full calendar page), kaya disabled ang hover/pointer */
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

    border: 1px solid #d9b6c3;

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

/* Completed orders table (completed.php) - hiwalay sa .booking-row, 6 columns ito (may Customer/Courier) */

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


/* CONTENT MANAGEMENT - form styling */

.content-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.content-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.content-subgroup {
    border: 1px solid #e6dbe0;
    border-radius: 12px;

    padding: 12px;

    display: flex;
    flex-direction: column;
    gap: 10px;
}

.content-subgroup-title {
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    color: #a4476d;
}

.content-field {
    display: flex;
    flex-direction: column;
    gap: 6px;

    font-size: 12px;
    font-weight: 700;
    color: #3c3035;
}

.content-field input,
.content-field select,
.content-field textarea {
    
    font-size: 13px;
    font-weight: 500;

    padding: 10px 12px;

    border: 1px solid #e6dbe0;
    border-radius: 10px;

    background: #fffbfc;
    color: #29252a;

    resize: vertical;
}

.content-field input:focus,
.content-field select:focus,
.content-field textarea:focus {
    outline: none;
    border-color: #ff7aa2;
}

.content-form-actions {
    display: flex;
    justify-content: flex-end;
}

.content-save-btn {
    border: none;
    border-radius: 12px;

    background: #ff7aa2;
    color: white;

    font-size: 13px;
    font-weight: 800;

    padding: 12px 24px;

    box-shadow: 3px 3px 0 rgba(41,37,42,.12);
}

.content-save-btn:hover {
    background: #a4476d;
}


/* Collection Management - isang column, sariling .card kada section */


/* Section header: icon + title + short description */

.content-section-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 4px;
}

.content-section-icon {
    font-size: 20px;
    line-height: 1;
}

.content-section-header p {
    color: #9a8c94;
    font-size: 11px;
    margin-top: 3px;
}


/* 2-column grid for short fields (eyebrow, button text, etc) */

.content-field-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

@media (max-width: 700px) {
    .content-field-grid {
        grid-template-columns: 1fr;
    }
}

.content-field-full {
    grid-column: 1 / -1;
}


/* Media field (image/video) - malaking preview (same aspect-ratio ng Home page) + input sa ibaba */

.content-media-field {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.content-media-preview {
    width: 100%;
    max-width: 220px;

    border-radius: 14px;
    overflow: hidden;

    border: 1px solid var(--gray-light);
    background: var(--background);

    box-shadow: 0 4px 12px rgba(71,45,55,.06);
}

.content-media-preview img,
.content-media-preview video {
    width: 100%;
    height: 100%;

    object-fit: cover;
    display: block;
}

/* Aspect ratios - kopya mula sa Home/Home.css para eksaktong tugma ang proporsyon */

/* Wide/banner ratio para sa Hero Carousel, larawan ang buong background ng slide */
.cm-ratio-hero {
    aspect-ratio: 8 / 3;
}

.cm-ratio-square {
    aspect-ratio: 1 / 1;
}

.cm-ratio-video {
    aspect-ratio: 1 / 1.02;
}


/* Click-to-upload - buong preview box ang button, i-click para magbukas ng file dialog */

.content-media-upload {
    position: relative;
    cursor: pointer;

    transition: transform .15s ease, box-shadow .15s ease;
}

.content-media-upload:hover {
    box-shadow: 0 6px 18px rgba(71,45,55,.14);
    transform: translateY(-2px);
}

.content-media-upload-overlay {
    position: absolute;
    inset: 0;

    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;

    padding: 10px;

    background: rgba(41,37,42,.55);
    color: white;

    font-size: 12px;
    font-weight: 800;

    opacity: 0;
    transition: opacity .15s ease;
}

.content-media-upload:hover .content-media-upload-overlay {
    opacity: 1;
}

.content-media-hint {
    font-size: 10.5px;
    font-weight: 600;
    color: #9a8c94;
}

.content-media-status {
    font-size: 11px;
    font-weight: 700;
    color: var(--green, #65a77e);
    min-height: 14px;
}

.content-media-status.error {
    color: var(--red, #d96b78);
}


/* Side-by-side groups (Hero slides, Products, Features) so they don't stack too tall */

.content-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    align-items: start;
}

.content-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    align-items: start;
}

@media (max-width: 1000px) {
    .content-grid-3,
    .content-grid-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .content-grid-3,
    .content-grid-4 {
        grid-template-columns: 1fr;
    }
}

/* About Us: video on the left, fields on the right */

.content-about-layout {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 20px;
    align-items: start;
}

/* bigger preview here than the default, fills the 380px column */
.content-about-layout .content-media-preview {
    max-width: 100%;
}

.content-about-fields {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

@media (max-width: 700px) {
    .content-about-layout {
        grid-template-columns: 1fr;
    }
}




/* CONTENT MANAGEMENT - TAB SWITCHER (Home / Collection) */

.cm-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.cm-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    padding: 10px 18px;

    background: white;
    border: 1px solid var(--gray-light);
    border-radius: 12px;

    font-size: 12px;
    font-weight: 800;
    color: var(--gray);

    transition: .15s;
}

.cm-tab:hover {
    background: var(--pink-soft);
}

.cm-tab.active {
    background: var(--pink);
    color: var(--black);
    border-color: var(--pink);
}


/* COLLECTION MANAGEMENT - product items + add/remove */

.cm-add-btn {
    align-self: flex-start;

    border: 1px solid #d9b6c3;
    border-radius: 12px;

    background: var(--pink-soft);
    color: var(--pink-dark, #a4476d);

    font-size: 12px;
    font-weight: 800;

    padding: 10px 16px;

    transition: .15s;
}

.cm-add-btn:hover {
    background: var(--pink-light);
    transform: translateY(-1px);
}

.cm-remove-btn {
    border: none;
    border-radius: 10px;

    background: var(--red-light, #ffe4e8);
    color: var(--red, #d96b78);

    font-size: 11px;
    font-weight: 800;

    padding: 8px 10px;

    align-self: flex-start;
}

.cm-remove-btn:hover {
    background: var(--red, #d96b78);
    color: white;
}

.cm-checkbox-field {
    flex-direction: row !important;
    align-items: center;
    gap: 8px !important;
}

.cm-checkbox-field input[type="checkbox"] {
    width: auto;
    padding: 0;
}


/* Header row: title/description on the left, "+ Add" button on the right */
.content-section-header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 4px;
}

.content-section-header-row .content-section-header {
    margin-bottom: 0;
}

.content-section-header-row .cm-add-btn {
    flex-shrink: 0;
}


/* smaller/compact product cards for the Collection tab */
.cm-compact-grid {
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
}

.cm-compact-grid .content-subgroup {
    padding: 6px;
    gap: 5px;
}

.cm-compact-grid .content-subgroup-title {
    font-size: 9px;
}

.cm-compact-grid .content-media-preview {
    max-width: 90px;
}

.cm-compact-grid .content-field {
    font-size: 10px;
    gap: 3px;
}

.cm-compact-grid .content-field input,
.cm-compact-grid .content-field select {
    font-size: 10px;
    padding: 3px 6px;
    height: 26px;
}

.cm-compact-grid .content-media-hint,
.cm-compact-grid .content-media-status {
    font-size: 9px;
}

/* Best Seller checkbox + Remove button in one row */
.cm-item-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.cm-compact-grid .cm-item-footer .content-field {
    margin-bottom: 0;
}

.cm-compact-grid .cm-remove-btn {
    font-size: 9px;
    padding: 5px 6px;
    align-self: center;
}

@media (max-width: 1500px) {
    .cm-compact-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}

@media (max-width: 1300px) {
    .cm-compact-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 1100px) {
    .cm-compact-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 700px) {
    .cm-compact-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}


/* textbox - sakto ang taas sa laman (walang sariling scrollbar), para hindi
   "sumasabit" ang page scroll kapag nasa ibabaw ng textbox ang cursor */
textarea.cs-autogrow {
    overflow-y: hidden !important;
    resize: none !important;
    box-sizing: border-box;
}
</style>

<style>
/* phone: 1 o 2 column lang ang collection items para hindi lumampas */
@media (max-width: 600px) {
    .cm-compact-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important; }
    .cm-compact-grid .content-subgroup { min-width: 0; }
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


<body>

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
<link rel="stylesheet" href="../Shared/content-pages.css?v=1">
<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<main class="main">


<header class="topbar">

    <div class="welcome">

        <h2 class="portal-title">
            Content Management
        </h2>

        

    </div>



        <div class="top-actions">

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


    <div class="cm-tabs">
        <a href="content-management.php" class="cm-tab">Home Page</a>
        <a href="collection-management.php" class="cm-tab active">Collection</a>
    </div>


<?php if ($saved): ?>

    <?php
        echo figurify_saved_toast("Changes saved. You will see this on the Collection page now."); // [ALL-IN-ONE] "Changes saved" pop up
    ?>

<?php endif; ?>


<form
    method="POST"
    action="save_collection.php"
    class="content-form"
>

    <!-- CHIBI -->

    <section class="card content-section" id="section-chibi">

        <div class="content-section-header-row">
            <div class="content-section-header">
                <div>
                    <h3>Chibi</h3>
                </div>
            </div>

            <button type="button" class="cm-add-btn" onclick="figurifyAddCollectionItem('chibi')">+ Add Chibi</button>
        </div>

        <div class="content-grid-3 cm-compact-grid" id="grid-chibi">

            <?php foreach ($chibiItems as $item): ?>
                <?php figurify_cm_collection_item("chibi", "Chibi", $item, "../Image/figures/chibi/chibi-head-keychain-pair.jpg"); ?>
            <?php endforeach; ?>

        </div>

    </section>


    <!-- HIRONO -->

    <section class="card content-section" id="section-hirono">

        <div class="content-section-header-row">
            <div class="content-section-header">
                <div>
                    <h3>Hirono</h3>
                </div>
            </div>

            <button type="button" class="cm-add-btn" onclick="figurifyAddCollectionItem('hirono')">+ Add Hirono</button>
        </div>

        <div class="content-grid-3 cm-compact-grid" id="grid-hirono">

            <?php foreach ($hironoItems as $item): ?>
                <?php figurify_cm_collection_item("hirono", "Hirono", $item, "../Image/figures/hirono/hirono-style.jpg"); ?>
            <?php endforeach; ?>

        </div>

    </section>


    <!-- FUNKO POP -->

    <section class="card content-section" id="section-funko">

        <div class="content-section-header-row">
            <div class="content-section-header">
                <div>
                    <h3>Funko Pop</h3>
                </div>
            </div>

            <button type="button" class="cm-add-btn" onclick="figurifyAddCollectionItem('funko')">+ Add Funko Pop</button>
        </div>

        <div class="content-grid-3 cm-compact-grid" id="grid-funko">

            <?php foreach ($funkoItems as $item): ?>
                <?php figurify_cm_collection_item("funko", "Funko Pop", $item, "../Image/figures/hirono/hirono-standee-3.5in-04.jpg"); ?>
            <?php endforeach; ?>

        </div>

    </section>


    <!-- CUSTOM BOX -->

    <section class="card content-section" id="section-custom">

        <div class="content-section-header-row">
            <div class="content-section-header">
                <div>
                    <h3>Custom Box</h3>
                </div>
            </div>

            <button type="button" class="cm-add-btn" onclick="figurifyAddCollectionItem('custom')">+ Add Custom Box</button>
        </div>

        <div class="content-grid-3 cm-compact-grid" id="grid-custom">

            <?php foreach ($customItems as $item): ?>
                <?php figurify_cm_collection_item("custom", "Custom Box", $item, "../Image/boxes/hirono-box-checkered.jpg"); ?>
            <?php endforeach; ?>

        </div>

    </section>


    <!-- SPECIAL FIGURES -->

    <section class="card content-section" id="section-special">

        <div class="content-section-header-row">
            <div class="content-section-header">
                <div>
                    <h3>Special Figures</h3>
                </div>
            </div>

            <button type="button" class="cm-add-btn" onclick="figurifyAddCollectionItem('special')">+ Add Special Figure</button>
        </div>

        <div class="content-grid-3 cm-compact-grid" id="grid-special">

            <?php foreach ($specialItems as $item): ?>
                <?php figurify_cm_special_item($item, "../Image/figures/hirono/hirono-style.jpg"); ?>
            <?php endforeach; ?>

        </div>

    </section>


    <div class="content-form-actions">
        <button
            type="submit"
            class="content-save-btn"
        >
            Save Changes
        </button>
    </div>

</form>


<!-- Templates para sa "+ Mag-add" - JS ang gagawa ng bagong subgroup base dito -->

<template id="tpl-chibi">
  <div class="content-subgroup" data-item>
    <div class="content-subgroup-title">New Chibi</div>

    <label class="content-field content-field-full">
      Image
      <div class="content-media-field">
        <div class="content-media-preview content-media-upload cm-ratio-square" onclick="this.querySelector('input[type=file]').click()">
          <img src="../Image/figures/chibi/chibi-head-keychain-pair.jpg" alt="New Chibi item preview">
          <div class="content-media-upload-overlay"><span>Click to change</span></div>
          <input type="file" accept="image/*" hidden onchange="figurifyUploadMedia(this, 'image')">
        </div>
        <input type="hidden" name="items[chibi][__IDX__][image]" value="">
        <p class="content-media-status"></p>
      </div>
    </label>

    <input type="hidden" name="items[chibi][__IDX__][item_id]" value="">
    <input type="hidden" name="items[chibi][__IDX__][remove]" value="0">

    <label class="content-field">
      Product Type
      <input type="text" name="items[chibi][__IDX__][type]" value="" placeholder="e.g. Half-body, Standee">
    </label>

    <label class="content-field">
      Size
      <input type="text" name="items[chibi][__IDX__][size]" value="" placeholder='e.g. 2&quot; or Custom'>
    </label>

    <label class="content-field">
      Price
      <input type="text" name="items[chibi][__IDX__][price]" value="" placeholder="e.g. ₱450">
    </label>

    <div class="cm-item-footer">
        <label class="content-field cm-checkbox-field">
            <input type="checkbox" name="items[chibi][__IDX__][bestseller]" value="1">
            Best Seller
        </label>

        <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
    </div>
  </div>
</template>

<template id="tpl-hirono">
  <div class="content-subgroup" data-item>
    <div class="content-subgroup-title">New Hirono</div>

    <label class="content-field content-field-full">
      Image
      <div class="content-media-field">
        <div class="content-media-preview content-media-upload cm-ratio-square" onclick="this.querySelector('input[type=file]').click()">
          <img src="../Image/figures/hirono/hirono-style.jpg" alt="New Hirono item preview">
          <div class="content-media-upload-overlay"><span>Click to change</span></div>
          <input type="file" accept="image/*" hidden onchange="figurifyUploadMedia(this, 'image')">
        </div>
        <input type="hidden" name="items[hirono][__IDX__][image]" value="">
        <p class="content-media-status"></p>
      </div>
    </label>

    <input type="hidden" name="items[hirono][__IDX__][item_id]" value="">
    <input type="hidden" name="items[hirono][__IDX__][remove]" value="0">

    <label class="content-field">
      Product Type
      <input type="text" name="items[hirono][__IDX__][type]" value="" placeholder="e.g. Standee, Couple Set">
    </label>

    <label class="content-field">
      Size
      <input type="text" name="items[hirono][__IDX__][size]" value="" placeholder='e.g. 3.5&quot; or Custom'>
    </label>

    <label class="content-field">
      Price
      <input type="text" name="items[hirono][__IDX__][price]" value="" placeholder="e.g. ₱750">
    </label>

    <div class="cm-item-footer">
        <label class="content-field cm-checkbox-field">
            <input type="checkbox" name="items[hirono][__IDX__][bestseller]" value="1">
            Best Seller
        </label>

        <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
    </div>
  </div>
</template>

<template id="tpl-funko">
  <div class="content-subgroup" data-item>
    <div class="content-subgroup-title">New Funko Pop</div>

    <label class="content-field content-field-full">
      Image
      <div class="content-media-field">
        <div class="content-media-preview content-media-upload cm-ratio-square" onclick="this.querySelector('input[type=file]').click()">
          <img src="../Image/figures/hirono/hirono-standee-3.5in-04.jpg" alt="New Funko Pop item preview">
          <div class="content-media-upload-overlay"><span>Click to change</span></div>
          <input type="file" accept="image/*" hidden onchange="figurifyUploadMedia(this, 'image')">
        </div>
        <input type="hidden" name="items[funko][__IDX__][image]" value="">
        <p class="content-media-status"></p>
      </div>
    </label>

    <input type="hidden" name="items[funko][__IDX__][item_id]" value="">
    <input type="hidden" name="items[funko][__IDX__][remove]" value="0">

    <label class="content-field">
      Product Type
      <input type="text" name="items[funko][__IDX__][type]" value="" placeholder="e.g. Standee">
    </label>

    <label class="content-field">
      Size
      <input type="text" name="items[funko][__IDX__][size]" value="" placeholder='e.g. 4&quot; or Custom'>
    </label>

    <label class="content-field">
      Price
      <input type="text" name="items[funko][__IDX__][price]" value="" placeholder="e.g. ₱1,000">
    </label>

    <div class="cm-item-footer">
        <label class="content-field cm-checkbox-field">
            <input type="checkbox" name="items[funko][__IDX__][bestseller]" value="1">
            Best Seller
        </label>

        <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
    </div>
  </div>
</template>

<template id="tpl-custom">
  <div class="content-subgroup" data-item>
    <div class="content-subgroup-title">New Custom Box</div>

    <label class="content-field content-field-full">
      Image
      <div class="content-media-field">
        <div class="content-media-preview content-media-upload cm-ratio-square" onclick="this.querySelector('input[type=file]').click()">
          <img src="../Image/boxes/hirono-box-checkered.jpg" alt="New Custom Box item preview">
          <div class="content-media-upload-overlay"><span>Click to change</span></div>
          <input type="file" accept="image/*" hidden onchange="figurifyUploadMedia(this, 'image')">
        </div>
        <input type="hidden" name="items[custom][__IDX__][image]" value="">
        <p class="content-media-status"></p>
      </div>
    </label>

    <input type="hidden" name="items[custom][__IDX__][item_id]" value="">
    <input type="hidden" name="items[custom][__IDX__][remove]" value="0">

    <label class="content-field">
      Product Type
      <input type="text" name="items[custom][__IDX__][type]" value="" placeholder="e.g. Solo Box, 3D Box">
    </label>

    <label class="content-field">
      Size
      <input type="text" name="items[custom][__IDX__][size]" value="" placeholder='e.g. Custom'>
    </label>

    <label class="content-field">
      Price
      <input type="text" name="items[custom][__IDX__][price]" value="" placeholder="e.g. ₱500">
    </label>

    <div class="cm-item-footer">
        <label class="content-field cm-checkbox-field">
            <input type="checkbox" name="items[custom][__IDX__][bestseller]" value="1">
            Best Seller
        </label>

        <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
    </div>
  </div>
</template>

<template id="tpl-special">
  <div class="content-subgroup" data-item>
    <div class="content-subgroup-title">New Special Figure</div>

    <label class="content-field content-field-full">
      Image
      <div class="content-media-field">
        <div class="content-media-preview content-media-upload cm-ratio-square" onclick="this.querySelector('input[type=file]').click()">
          <img src="../Image/figures/hirono/hirono-style.jpg" alt="New Special Figure item preview">
          <div class="content-media-upload-overlay"><span>Click to change</span></div>
          <input type="file" accept="image/*" hidden onchange="figurifyUploadMedia(this, 'image')">
        </div>
        <input type="hidden" name="items[special][__IDX__][image]" value="">
        <p class="content-media-status"></p>
      </div>
    </label>

    <input type="hidden" name="items[special][__IDX__][item_id]" value="">
    <input type="hidden" name="items[special][__IDX__][remove]" value="0">

    <label class="content-field">
      Style (header)
      <input type="text" name="items[special][__IDX__][style]" value="" placeholder="e.g. Pop Culture">
    </label>

    <label class="content-field">
      Category (for filter)
      <select name="items[special][__IDX__][category]">
        <option value="funko">Funko Pop</option>
        <option value="special" selected>Other</option>
      </select>
    </label>

    <label class="content-field">
      Product Type
      <input type="text" name="items[special][__IDX__][type]" value="" placeholder="e.g. Custom Figure">
    </label>

    <label class="content-field">
      Size
      <input type="text" name="items[special][__IDX__][size]" value="" placeholder="e.g. Custom">
    </label>

    <label class="content-field">
      Price
      <input type="text" name="items[special][__IDX__][price]" value="" placeholder="e.g. Starts at ₱500">
    </label>

    <div class="cm-item-footer">
        <label class="content-field cm-checkbox-field">
            <input type="checkbox" name="items[special][__IDX__][bestseller]" value="1">
            Best Seller
        </label>

        <button type="button" class="cm-remove-btn" onclick="figurifyRemoveCollectionItem(this)">Remove</button>
    </div>
  </div>
</template>


<script>
// Media upload (larawan) - kapareho ng content-management.php: auto-upload sa upload_content_media.php,
// then update hidden input + live preview.
function figurifyUploadMedia(fileInput, kind) {

    const file = fileInput.files && fileInput.files[0];

    if (!file) {
        return;
    }

    const field = fileInput.closest(".content-media-field");
    const hiddenInput = field.querySelector('input[type="hidden"]');
    const status = field.querySelector(".content-media-status");
    const previewImg = field.querySelector("img");

    if (status) {
        status.textContent = "Uploading...";
        status.classList.remove("error");
    }

    const formData = new FormData();
    formData.append("file", file);
    formData.append("kind", kind);
    formData.append("category", "collection");

    // ipadala ang lumang value para mabura ng server ang lumang file
    if (hiddenInput && hiddenInput.value) {
        formData.append("old_value", hiddenInput.value);
    }

    formData.append("csrf_token", window.csCsrfToken || "");

    fetch("upload_content_media.php", {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {

            if (!data.ok) {
                if (status) {
                    status.textContent = data.error || "Failed to upload file.";
                    status.classList.add("error");
                }
                return;
            }

            if (hiddenInput) {
                hiddenInput.value = data.value;
            }

            if (previewImg) {
                previewImg.src = data.src;
            }

            if (status) {
                status.textContent = "Uploaded. Click \"Save Changes\" to apply.";
            }

        })
        .catch(() => {
            if (status) {
                status.textContent = "Upload error. Please try again.";
                status.classList.add("error");
            }
        });

    fileInput.value = "";
}

// Add: clone ang <template> ng group, palitan ang __IDX__ placeholder ng temp ID, idagdag sa grid.
// Remove: kung existing item, itago + set hidden remove flag ("1"); kung bagong item pa, tanggalin sa DOM agad.
let figurifyCmCounter = 0;

function figurifyAddCollectionItem(groupKey) {
    const tpl = document.getElementById("tpl-" + groupKey);
    const grid = document.getElementById("grid-" + groupKey);

    if (!tpl || !grid) {
        return;
    }

    figurifyCmCounter++;
    const idx = "new" + figurifyCmCounter;

    const html = tpl.innerHTML.split("__IDX__").join(idx);

    const wrapper = document.createElement("div");
    wrapper.innerHTML = html.trim();

    const node = wrapper.firstElementChild;

    if (node) {
        grid.appendChild(node);

        // ipakita agad ang bagong item sa loob ng scroll box
        node.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
}

function figurifyRemoveCollectionItem(button) {
    const subgroup = button.closest(".content-subgroup");

    if (!subgroup) {
        return;
    }

    const idInput = subgroup.querySelector('input[name$="[item_id]"]');

    if (idInput && idInput.value) {
        const removeFlag = subgroup.querySelector('input[name$="[remove]"]');
        if (removeFlag) {
            removeFlag.value = "1";
        }
        subgroup.style.display = "none";
    } else {
        subgroup.remove();
    }
}

</script>


</main>

</div>

<script src="../Shared/dashboard.js"></script>

<?php  ?>


<script>
/* Auto-grow ng lahat ng textbox sa page na ito: lumalaki habang nagta-type,
   kaya walang scrollbar sa loob ng textbox at tuloy-tuloy ang scroll ng page. */
(function () {
    function grow(t) {
        if (!t.offsetParent) {
            return; // nakatago pa (ibang tab) - aayusin pag lumabas na
        }
        t.style.height = "auto";
        t.style.height = (t.scrollHeight + (t.offsetHeight - t.clientHeight)) + "px";
    }

    function growAll() {
        document.querySelectorAll("textarea.cs-autogrow").forEach(grow);
    }

    document.querySelectorAll("textarea").forEach(function (t) {
        t.classList.add("cs-autogrow");
        t.addEventListener("input", function () { grow(t); });
    });

    growAll();
    window.addEventListener("load", growAll);

    var timer;
    window.addEventListener("resize", function () {
        clearTimeout(timer);
        timer = setTimeout(growAll, 100);
    });

    // kapag lumipat ng tab (Home Page / Collection) o bumukas ang nakatagong section
    document.addEventListener("click", function () { setTimeout(growAll, 0); });
})();
</script>
</body>

</html>
