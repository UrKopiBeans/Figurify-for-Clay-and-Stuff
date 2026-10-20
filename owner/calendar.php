<?php

require_once "owner-header.php";

$figurifyRole    = "owner";
$figurifyPageDir = __DIR__;

/* Shared sa owner at staff calendar.php - set muna $figurifyRole at
   $figurifyPageDir bago mag-require nito. */
require_once __DIR__ . "/../helpers/booking_helper.php";

$bookedDates    = figurify_get_booked_dates($conn);
$bookingsByDate = figurify_get_bookings_by_date($conn);

/* Same rush/non-rush limit logic ng customer-facing commission.php
   calendar, para magkatugma kung anong araw puno na (2/2 rush, 15/15 non-rush). */
$rushDayCounts     = figurify_get_rush_day_counts($conn);
$nonrushDateCounts = figurify_get_nonrush_date_counts($conn);

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

<title>Figurify — Calendar</title>

<style>
/* shared owner/staff dashboard styles - owner.css/staff.css just @import this now */


/* owner dashboard styles */

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

/* app */
.app {
    min-height: 100vh;
}

/* sidebar */







/* main */
.main {
    margin-left: 230px;

    width: calc(100% - 230px);

    padding: 22px 22px 38px 14px;
}

/* topbar */
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

/* date time */
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

/* top actions */
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

/* cards */
.card {
    background: white;

    border: 1px solid var(--gray-light);

    border-radius: 18px;

    padding: 17px;

    box-shadow:
        0 5px 16px rgba(71,45,55,.04);
}

/* statistics */
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

/* dashboard layout - left col: stats + calendar + recent bookings, right col: shipment card */

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

/* dashboard grid */
.dashboard-grid {
    display: grid;

    grid-template-columns:
        1fr 1.2fr;

    gap: 18px;

    align-items: start;
}

/* card header */
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

/* orders */
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

/* status */
.status {
    display: inline-block;

    padding: 5px 8px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;

    white-space: nowrap;
}

/* status colors - same as Staff Dashboard, Calendar, and My Orders. Single source: Admin/order_status_helper.php. */

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

/* calendar controls */
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

/* calendar */
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

/* Dashboard calendar (#calendarDays) ay read-only preview lang,
   hindi clickable - pointer-events:none para di mag-trigger ng :hover. */
#calendarDays .day {
    pointer-events: none;
}

#calendarDays .day:hover {
    background: transparent;
    cursor: default;
}

/* calendar legend */
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
    background: #ffe3f1;
    border: 1px solid #f6c8de;
}

.booked-dot {
    background: #f6dede;

    border: 1px solid #e0a6a6;
}

/* shipments */
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

/* overview */
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

/* quick actions */
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

/* empty */
.empty-state {
    padding: 30px 10px;

    text-align: center;

    color: #a0959b;

    font-size: 11px;
}

/* modal */
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

/* responsive */
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

/* inner page */
.page-description {
    color: var(--gray);
    font-size: 11px;
    margin-top: 4px;
}

/* booking table */
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

/* completed orders table - 6 columns (Customer + Courier) instead of 5, same as Staff Dashboard */

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

/* full calendar */
.full-calendar-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow-y: auto;
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

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;

    cursor: pointer;

    transition: .2s;
}

.calendar-page-controls button:hover {
    background: var(--pink);

    color: white;
}

.calendar-arrow-icon {
    display: block;
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
    width: 100%;
    height: auto;
    aspect-ratio: 1 / 1;

    border: 1px solid var(--gray-light);

    border-radius: 12px;

    font-size: 12px;

    cursor: pointer;

    background: var(--white);

    transition: .15s ease;
}

.large-day:hover {
    background: #ffe2f0;
}

.large-day.available {
    background: #fffbed;
}

.large-day.booked {
    background: #f6dede;

    color: #c98080;

    border-color: #e0a6a6;

    font-weight: 800;

    text-decoration: line-through;
}

/* Capacity status - kapareho ng logic AT kulay ng Commission
   calendar mismo: "Unavailable" kapag puno na, hindi na puwedeng
   ma-book ng customer kahit "may laman" pa lang ang araw. */
.large-day.rush-full,
.large-day.nonrush-full,
.large-day.unavailable {
    background: #f4f0f3;

    color: #c5b9c0;

    font-weight: 700;

    box-shadow: inset 0 0 0 1px #d8cdd4;
}

.large-day.day-selected {
    background: #f4a4ca;

    color: white;

    border-color: #c9689a;

    box-shadow: 0 3px 0 #d986ad;

    font-weight: 800;
}

/* araw ngayon: mapusyaw na pink (nasa dulo para hindi ma-override) */
.large-day.today {
    background: #ffe3f1;

    color: #b6608f;

    font-weight: 800;
}

/* Kapag pareho itong "today" AT "day-selected", panatilihin na
   lang ang matingkad na pink ng selected - wala nang
   karagdagang glow/border pa. */
.large-day.day-selected.today {
    background: #f4a4ca;

    color: white;

    border-color: #c9689a;

    box-shadow: 0 3px 0 #d986ad;

    font-weight: 800;
}

.large-day.empty {
    cursor: default;
    border-color: transparent;
    background: transparent;
}

.large-day.empty:hover {
    background: transparent;
}

/* responsive inner pages */
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
<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<main class="main">


<header class="topbar">

    <div class="welcome">

        <h2 class="portal-title">
            Calendar
        </h2>

    </div>



        <div class="top-actions">

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


<div class="calendar-page-layout">


    <!-- LEFT: CALENDAR -->

    <section class="card full-calendar-card">


        <div class="calendar-page-controls">

            <button
                type="button"
                id="fullPreviousMonth"
                class="calendar-arrow-btn"
                aria-label="Previous month"
            >
                <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>


            <h3 id="fullCalendarMonth">
                Loading...
            </h3>


            <button
                type="button"
                id="fullNextMonth"
                class="calendar-arrow-btn"
                aria-label="Next month"
            >
                <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

        </div>


        <div class="calendar-week full-week">

            <div>SUN</div>
            <div>MON</div>
            <div>TUE</div>
            <div>WED</div>
            <div>THU</div>
            <div>FRI</div>
            <div>SAT</div>

        </div>


        <div
            class="calendar-days full-calendar-days"
            id="fullCalendarDays"
        ></div>


        <div class="calendar-legend">

            <span>

                <i class="legend-dot available-dot"></i>

                Available

            </span>


            <span>

                <i class="legend-dot booked-dot"></i>

                Fully booked

            </span>


            <span>

                <i class="legend-dot unavailable-dot"></i>

                Unavailable

            </span>


            <span>

                <i class="legend-dot selected-dot"></i>

                Selected

            </span>

        </div>


    </section>


    <!-- RIGHT: BOOKED ORDERS LIST -->

    <section class="card" id="dayBookingsCard">

        <div class="card-header">

            <h3 id="dayBookingsTitle">
                Booked Orders
            </h3>

            <a
                href="#"
                id="clearDayFilter"
                class="clear-day-filter"
                style="display:none;"
            >
                Show all ×
            </a>

        </div>

        <div id="dayBookingsList"></div>

    </section>



</div>


<style>

.calendar-page-layout{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:20px;
    align-items:stretch;
}

/* Bawal mag-scroll ang buong page dito - naka-fit sa 100vh ang
   .app/.main/.calendar-page-layout chain (flexbox, min-height:0),
   kaya #dayBookingsList na lang ang mag-i-scroll kapag sobra ang laman. */
@media (min-width: 901px){

    html, body{
        height: 100%;
        overflow: hidden;
    }

    .app{
        height: 100vh;
    }

    .main{
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .calendar-page-layout{
        flex: 1 1 auto;
        min-height: 0;
    }

}

@media (max-width: 900px){

    .calendar-page-layout{
        grid-template-columns: 1fr;
    }

}

#dayBookingsCard{
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

#dayBookingsCard .card-header{
    align-items:center;
}

/* 3 columns x auto rows - bawat booking may sariling container/card */
#dayBookingsList{
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding-right: 4px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-auto-rows: min-content;
    align-content: start;
    gap: 12px;
}

#dayBookingsList .empty-state{
    grid-column: 1 / -1;
}

@media (max-width: 1200px){
    #dayBookingsList{ grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 900px){
    #dayBookingsList{ grid-template-columns: 1fr; }
}

.clear-day-filter{
    color:#6B7280;
    font-size:12px;
    font-weight:700;
    text-decoration:none;
    cursor:pointer;
    white-space:nowrap;
}

.clear-day-filter:hover{
    text-decoration:underline;
}

.day-booking-date{
    display:block;
    font-size:11px;
    color:#8A8690;
    font-weight:700;
    margin-top:2px;
}

.day-booking-row{
    display:flex;
    flex-direction:column;
    gap:8px;
    padding:12px;
    border-radius:12px;
    background:#F7F7F9;
    border:1px solid var(--gray-light);
    transition: background .15s ease, border-color .15s ease;
}

.day-booking-row:hover{
    background:#F1F0F4;
    border-color:#D8D4DC;
}

.day-booking-row.highlighted{
    background:#FDE6F1;
    border-color:#E896C4;
}

.day-booking-content{
    min-width:0;
}

.day-booking-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin-bottom:8px;
}

.day-booking-order-id{
    color:#A5446D;
    font-weight:900;
    font-size:14px;
}

/* Order details lang - Customer / Email / Order Type / Order
   Method / Booking Date - bilang simpleng row-at-column na
   mini table sa ilalim ng Order #. */
.day-booking-table{
    display:flex;
    flex-direction:column;
    gap:6px;
}

.day-booking-table-row{
    display:flex;
    flex-direction:column;
    gap:2px;
    padding:5px 0;
    border-bottom:1px solid #F3E4EC;
}

.day-booking-table-row:last-child{
    border-bottom:none;
}

.day-booking-label{
    font-size:11px;
    font-weight:800;
    color:#B5789D;
    text-transform:uppercase;
    letter-spacing:.02em;
}

.day-booking-value{
    font-size:12.5px;
    font-weight:700;
    color:#5c4654;
    word-break:break-word;
}

.day.booked{
    cursor:pointer;
}

.full-calendar-card .calendar-legend{
    gap:22px;
    margin-top:18px;
    font-size:13px;
    color:#6b5c64;
    justify-content:flex-start;
}

.full-calendar-card .calendar-legend span{
    gap:8px;
}

.full-calendar-card .legend-dot{
    width:13px;
    height:13px;
}

.available-dot{
    background: #fffbed;
    border: 1px solid #e9d9a0;
}

.unavailable-dot{
    background: #f4f0f3;
    border: 1px solid #d8cdd4;
}

.selected-dot{
    background: #f4a4ca;
    border: 1px solid #c9689a;
}

</style>


</main>

</div>


<script src="../Shared/dashboard.js"></script>
<script>
// owner dashboard JS


/* updateDateTime is defined sa shared script block sa itaas na
   (Owner + Staff), kaya wala na ulit dito. */


// search orders
const searchInput =
    document.getElementById(
        "searchOrders"
    );


if (searchInput) {

    searchInput.addEventListener(
        "input",
        function() {

            const value =
                this.value
                    .toLowerCase()
                    .trim();


            const orders =
                document.querySelectorAll(
                    ".searchable-order"
                );


            orders.forEach(
                order => {

                    const text =
                        (
                            order.dataset.search
                            || ""
                        ).toLowerCase();


                    order.style.display =
                        text.includes(value)
                        ? "grid"
                        : "none";

                }
            );

        }
    );

}


// dashboard buttons
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


// quick actions
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


// dashboard calendar
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

const fullCalendarDays =
    document.getElementById(
        "fullCalendarDays"
    );

const fullCalendarMonth =
    document.getElementById(
        "fullCalendarMonth"
    );

const fullPreviousMonth =
    document.getElementById(
        "fullPreviousMonth"
    );

const fullNextMonth =
    document.getElementById(
        "fullNextMonth"
    );


let fullCalendarDate =
    new Date();


const demoBookings = <?php echo json_encode($bookedDates); ?>;

const bookingsByDate = <?php echo json_encode($bookingsByDate); ?>;


/* Snapshot ng booked order IDs (processing/to_ship/shipped/completed)
   sa load ng page - ginagamit ng live-update polling sa ibaba. */
const initialBookedOrderIds = <?php

    $flatBookedOrderIds = [];

    foreach ($bookingsByDate as $dateBookings) {
        foreach ($dateBookings as $booking) {
            $flatBookedOrderIds[] = (string) $booking["order_id"];
        }
    }

    echo json_encode(array_values(array_unique($flatBookedOrderIds)));

?>;

const FIGURIFY_BOOKED_CALENDAR_STATUSES = <?php echo json_encode(FIGURIFY_BOOKED_CALENDAR_STATUSES); ?>;

/* Capacity data, same source (booking_helper.php) ng commission.php
   calendar - para makita kung anong araw puno na, hindi lang may laman. */
const figurifyRushDayCounts     = <?php echo json_encode($rushDayCounts); ?>;
const figurifyNonrushDateCounts = <?php echo json_encode($nonrushDateCounts); ?>;

const FIGURIFY_RUSH_DAILY_CAP    = <?php echo (int) FIGURIFY_RUSH_DAILY_CAP; ?>;
const FIGURIFY_NONRUSH_DATE_CAP  = <?php echo (int) FIGURIFY_NONRUSH_DATE_CAP; ?>;

/* Ibinabalik ang capacity status ng isang petsa, gamit ang parehong
   panuntunan ng commission.js: puno na bang rush (2/2), o puno na
   bang non-rush (15/15, sa huling araw lang ng buwan may bisa ito). */
function getDayCapacityInfo(dateString) {

    const rushCount = figurifyRushDayCounts[dateString] || 0;

    if (rushCount >= FIGURIFY_RUSH_DAILY_CAP) {

        return {
            type: "rush-full",
            label: "Rush full (" + rushCount + "/" + FIGURIFY_RUSH_DAILY_CAP + ")"
        };

    }

    const dateObj = new Date(dateString + "T00:00:00");

    const lastDayOfMonth =
        new Date(
            dateObj.getFullYear(),
            dateObj.getMonth() + 1,
            0
        ).getDate();

    const isLastDayOfMonth =
        dateObj.getDate() === lastDayOfMonth;

    if (isLastDayOfMonth) {

        const nonrushCount =
            figurifyNonrushDateCounts[dateString] || 0;

        if (nonrushCount >= FIGURIFY_NONRUSH_DATE_CAP) {

            return {
                type: "nonrush-full",
                label: "Non-Rush full (" + nonrushCount + "/" + FIGURIFY_NONRUSH_DATE_CAP + ")"
            };

        }

    }

    return null;

}


const dayBookingsCard =
    document.getElementById(
        "dayBookingsCard"
    );

const dayBookingsTitle =
    document.getElementById(
        "dayBookingsTitle"
    );

const dayBookingsList =
    document.getElementById(
        "dayBookingsList"
    );


const clearDayFilter =
    document.getElementById(
        "clearDayFilter"
    );

let activeDayFilter = null;


function niceDateLabel(dateString) {

    return new Date(dateString + "T00:00:00")
        .toLocaleDateString(
            "en-US",
            {
                month: "long",
                day: "numeric",
                year: "numeric"
            }
        );

}


function orderTypeLabel(orderType) {

    return (orderType === "rush")
        ? "Rush"
        : "Non-Rush";

}


function orderMethodLabel(orderMethod) {

    return (orderMethod === "create_style")
        ? "Dress Up"
        : "Image Submission";

}


/* Iisang pinagmumulan ng status label/kulay (helpers/order_status_helper.php)
   ang ginagamit din dito, para pare-pareho sa Owner/Staff Dashboard
   at sa My Orders ng customer. */
const FIGURIFY_STATUS_LABELS = <?php echo json_encode(array_map(
    static function ($s) { return $s["label"]; },
    FIGURIFY_ORDER_STATUSES
)); ?>;

function statusSlug(status) {

    return String(status || "pending")
        .toLowerCase()
        .trim()
        .replace(/[\s-]+/g, "_");

}

function statusBadgeLabel(status) {

    const slug = statusSlug(status);

    return FIGURIFY_STATUS_LABELS[slug] || String(status || "")
        .replace(/_/g, " ")
        .replace(/\b\w/g, function(c) {
            return c.toUpperCase();
        });

}


// Persistent na listahan ng LAHAT ng booked orders sa right side,
// grouped by date. Kapag may na-click na araw (activeDayFilter),
// highlight/scroll na lang papunta doon, hindi itinatago ang iba.

function renderBookingsList() {

    if (!dayBookingsList) {
        return;
    }

    let dateKeys =
        Object.keys(bookingsByDate)
            .sort();

    // Kapag may selected date, i-scope lang ang listahan doon -
    // kahit walang booking, "wala" dapat ang lalabas, hindi buong listahan.
    if (activeDayFilter) {
        dateKeys = dateKeys.filter(function(dateString) {
            return dateString === activeDayFilter;
        });
    }

    dayBookingsList.innerHTML = "";

    if (dayBookingsTitle) {

        dayBookingsTitle.textContent =
            activeDayFilter
            ? "Booked Orders · " + new Date(activeDayFilter + "T00:00:00").toLocaleDateString("en-US", { month: "short", day: "numeric" })
            : "Booked Orders";

    }

    if (dateKeys.length === 0) {

        dayBookingsList.innerHTML =
            "<div class='empty-state'>" +
            (
                activeDayFilter
                ? "No bookings on this date."
                : "No booked orders yet."
            ) +
            "</div>";

        if (clearDayFilter) {
            clearDayFilter.style.display =
                activeDayFilter ? "inline-block" : "none";
        }

        return;

    }

    dateKeys.forEach(function(dateString) {

        const orders =
            bookingsByDate[dateString] ||
            [];

        orders.forEach(function(order) {

            const row =
                document.createElement("div");

            row.className =
                "day-booking-row" +
                (
                    activeDayFilter === dateString
                    ? " highlighted"
                    : ""
                );

            row.dataset.date = dateString;

            /* compact na row: #order · pangalan · Rush/Non-rush chip
               (petsa lang ang dagdag kapag "Show all" ang view) */
            const isRush = String(order.order_type || "").toLowerCase() === "rush";
            const safeName = String(order.full_name || "—")
                .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

            row.className += " cal-row";
            row.title = orderMethodLabel(order.order_method) + " · " + niceDateLabel(dateString) + " · " + statusBadgeLabel(order.status);
            row.innerHTML =
                "<span class='cal-row-id'>#" + order.order_id + "</span>" +
                "<span class='cal-row-name'>" + safeName + "</span>" +
                (activeDayFilter ? "" : "<span class='cal-row-date'>" + new Date(dateString + "T00:00:00").toLocaleDateString("en-US", { month: "short", day: "numeric" }) + "</span>") +
                "<span class='cal-chip " + (isRush ? "cal-chip-rush" : "cal-chip-nonrush") + "'>" + (isRush ? "Rush" : "Non-rush") + "</span>";

            dayBookingsList.appendChild(
                row
            );

        });

    });

    if (clearDayFilter) {

        clearDayFilter.style.display =
            activeDayFilter
            ? "inline-block"
            : "none";

    }

}


function showDayBookings(dateString) {

    // Toggle: kapag pareho lang ng na-click at yung kasalukuyang
    // selected date, i-deselect (bumalik sa "show all" state).
    activeDayFilter =
        (activeDayFilter === dateString)
        ? null
        : dateString;

    // I-sync ang day-selected class sa calendar days imbes na
    // buuin ulit ang buong calendar.
    fullCalendarDays
        .querySelectorAll(".large-day")
        .forEach(function(dayEl) {
            dayEl.classList.remove("day-selected");
        });

    if (activeDayFilter) {

        const selectedDayEl =
            fullCalendarDays.querySelector(
                "[data-date='" + activeDayFilter + "']"
            );

        selectedDayEl?.classList.add("day-selected");

    }

    renderBookingsList();

    const firstMatch =
        dayBookingsList.querySelector(
            "[data-date='" + dateString + "']"
        );

    if (firstMatch) {

        firstMatch.scrollIntoView({
            behavior: "smooth",
            block: "nearest"
        });

    }

}


clearDayFilter?.addEventListener(
    "click",
    function(event) {

        event.preventDefault();

        activeDayFilter = null;

        document
            .querySelectorAll("#fullCalendarDays .large-day")
            .forEach(function(dayEl) {
                dayEl.classList.remove("day-selected");
            });

        renderBookingsList();

    }
);


function renderFullCalendar() {

    if (!fullCalendarDays) {
        return;
    }


    const year =
        fullCalendarDate.getFullYear();

    const month =
        fullCalendarDate.getMonth();


    fullCalendarMonth.textContent =
        fullCalendarDate.toLocaleDateString(
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

    if (fullPreviousMonth) {

        fullPreviousMonth.disabled = isAtCurrentMonth;

        fullPreviousMonth.style.opacity =
            isAtCurrentMonth ? "0.4" : "1";

        fullPreviousMonth.style.cursor =
            isAtCurrentMonth ? "not-allowed" : "pointer";

    }


    fullCalendarDays.innerHTML = "";

    // Right-side list persistent, pero i-clear ang day filter/highlight
    // pag nag-palit ng buwan.
    activeDayFilter = null;


    const jsFirstDay =
        new Date(
            year,
            month,
            1
        ).getDay();


    /* Sunday-first (SUN ... SAT) */
    const firstDay =
        jsFirstDay;


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
            document.createElement("div");

        empty.className =
            "day empty";

        fullCalendarDays.appendChild(
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

        const element =
            document.createElement("div");


        element.className =
            "day large-day";


        element.textContent =
            day;


        const dateString =
            year +
            "-" +
            String(month + 1)
                .padStart(2, "0") +
            "-" +
            String(day)
                .padStart(2, "0");

        element.dataset.date = dateString;


        if (
            today.getFullYear() === year &&
            today.getMonth() === month &&
            today.getDate() === day
        ) {

            element.classList.add(
                "today"
            );

        }


        /* may order sa araw na ito (approved na) - para sa click/listahan lang */
        const hasBookings =
            demoBookings.includes(
                dateString
            );

        /* Red/"booked" LANG kapag FULL na ang slot (rush 2/2, non-rush 15/15).
           Ang petsang may 1 order pa lang ay hindi kinukulayan. */
        const capacityInfo =
            getDayCapacityInfo(dateString);

        const isBooked = !!capacityInfo;

        if (capacityInfo) {

            element.classList.add(
                "booked"
            );

            element.classList.add(
                capacityInfo.type
            );

            element.title =
                capacityInfo.label;

        }

        /* CAPACITY - ipakita ang bilang ng slot kahit hindi pa puno */
        if (!capacityInfo && hasBookings) {

            const rushUsed    = figurifyRushDayCounts[dateString] || 0;
            const nonrushUsed = figurifyNonrushDateCounts[dateString] || 0;
            const parts       = [];

            if (rushUsed > 0) {
                parts.push("Rush " + rushUsed + "/" + FIGURIFY_RUSH_DAILY_CAP);
            }
            if (nonrushUsed > 0) {
                parts.push("Non-Rush " + nonrushUsed + "/" + FIGURIFY_NONRUSH_DATE_CAP);
            }
            if (parts.length) {
                element.title = "Capacity: " + parts.join(" · ");
            }
        }

        if (!capacityInfo) {

            element.classList.add(
                "available"
            );

        }

        // Kapareho ng Dashboard calendar: gray/"Unavailable" ang
        // nakalipas na araw - pero panatilihin ang "booked" (red) kung
        // may booking dito, dahil dapat pa rin ma-click para makita ito.
        const cellDate =
            new Date(year, month, day);

        const todayAtMidnight =
            new Date(
                today.getFullYear(),
                today.getMonth(),
                today.getDate()
            );

        const isPast =
            cellDate < todayAtMidnight;

        if (isPast && !hasBookings) {

            element.classList.add(
                "unavailable"
            );

        }

        if (
            activeDayFilter === dateString
        ) {

            element.classList.add(
                "day-selected"
            );

        }

        // Kahit anong estado (available/booked/unavailable), clickable
        // pa rin ang araw - highlight lang ang selected date, tapos
        // right panel ang magpapakita ng bookings. Naka-lock ang past
        // days (isPast) MALIBAN kung may booking na (isBooked).
        const isClickable =
            !isPast || hasBookings;

        if (isClickable) {

            element.addEventListener(
                "click",
                function() {

                    showDayBookings(
                        dateString
                    );

                }
            );

        } else {

            element.style.cursor =
                "not-allowed";

        }


        fullCalendarDays.appendChild(
            element
        );

    }

}


fullPreviousMonth?.addEventListener(
    "click",
    function() {

        const now = new Date();

        const isAtCurrentMonth =
            fullCalendarDate.getFullYear() === now.getFullYear() &&
            fullCalendarDate.getMonth() === now.getMonth();

        if (isAtCurrentMonth) {
            return;
        }

        fullCalendarDate.setMonth(
            fullCalendarDate.getMonth() - 1
        );

        renderFullCalendar();

    }
);


fullNextMonth?.addEventListener(
    "click",
    function() {

        fullCalendarDate.setMonth(
            fullCalendarDate.getMonth() + 1
        );

        renderFullCalendar();

    }
);


renderFullCalendar();
renderBookingsList();

</script>


<!-- CALENDAR - Design System (Sunday-first, berde/pula/gray/primary na araw) -->
<style>
body .app .calendar-page-layout {
    display: grid !important;
    grid-template-columns: minmax(0, 1.55fr) minmax(320px, 1fr) !important;
    gap: 22px !important;
    align-items: start !important;
    flex: 0 0 auto !important;
}
body .app .full-calendar-card,
body .app #dayBookingsCard {
    height: auto !important;
    min-height: 0 !important;
    padding: 24px 24px 20px !important;
    overflow: visible !important;
}

/* header: buwan sa kaliwa, maliit na ‹ › sa kanan */
body .app .calendar-page-controls {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 2px !important;
    margin: 0 0 14px !important;
}
body .app .calendar-page-controls h3 {
    order: -1;
    flex: 1 1 auto;
    margin: 0 !important;
    
    font-size: 23px !important;
    font-weight: 700 !important;
    text-align: left !important;
    color: var(--p-plum) !important;
}
body .app .calendar-page-controls button {
    display: grid !important;
    place-items: center;
    width: 26px !important;
    height: 26px !important;
    padding: 0 !important;
    border: 0 !important;
    border-radius: 8px !important;
    background: transparent !important;
    box-shadow: none !important;
    color: var(--p-plum) !important;
}
body .app .calendar-page-controls button:hover:not(:disabled) { background: var(--p-blush) !important; }
body .app .calendar-page-controls button svg { width: 16px; height: 16px; }

/* araw ng linggo */
body .app .full-week {
    display: grid !important;
    grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
    gap: 6px !important;
    margin: 0 0 6px !important;
}
body .app .full-week > div {
    padding: 0 !important;
    background: none !important;
    text-align: center;
    font-size: 12px !important;
    font-weight: 700 !important;
    letter-spacing: .02em;
    color: var(--p-plum) !important;
}

/* mga araw */
body .app .full-calendar-days {
    display: grid !important;
    grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
    gap: 6px !important;
}
body .app .full-calendar-days .day.empty { background: none !important; border: 0 !important; }
body .app .full-calendar-days .large-day {
    width: 100% !important;
    height: 44px !important;
    aspect-ratio: auto !important;
    display: grid !important;
    place-items: center;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    border-radius: 12px !important;
    box-shadow: none !important;
    text-decoration: none !important;
    font-size: 14.5px !important;
    font-weight: 700 !important;
    cursor: pointer;
    transition: filter .12s ease;
}
body .app .full-calendar-days .large-day.available   { background: var(--p-ok-bg) !important;  color: var(--p-ok) !important; }
body .app .full-calendar-days .large-day.booked      { background: var(--p-bad-bg) !important; color: var(--p-bad) !important; }
body .app .full-calendar-days .large-day.unavailable { background: #ececec !important;         color: #9a8f94 !important; }
body .app .full-calendar-days .large-day.today:not(.day-selected) { box-shadow: inset 0 0 0 2px var(--p-ok) !important; }
body .app .full-calendar-days .large-day.booked.today:not(.day-selected) { box-shadow: inset 0 0 0 2px var(--p-bad) !important; }
body .app .full-calendar-days .large-day.day-selected { background: var(--p-primary) !important; color: #ffffff !important; }
body .app .full-calendar-days .large-day:hover:not(.unavailable):not(.day-selected) { filter: brightness(.96); }

/* legend - maliit na parisukat */
body .app .full-calendar-card .calendar-legend {
    display: flex !important;
    flex-wrap: wrap;
    justify-content: flex-start !important;
    gap: 6px 18px !important;
    margin-top: 18px !important;
    font-size: 13.5px !important;
    color: var(--p-plum) !important;
}
body .app .full-calendar-card .calendar-legend span { display: inline-flex; align-items: center; gap: 5px; }
body .app .full-calendar-card .legend-dot {
    width: 8px !important;
    height: 8px !important;
    border: 0 !important;
    border-radius: 2px !important;
}
body .app .full-calendar-card .available-dot   { background: var(--p-ok) !important; }
body .app .full-calendar-card .booked-dot      { background: var(--p-bad) !important; }
body .app .full-calendar-card .unavailable-dot { background: #a99ea3 !important; }
body .app .full-calendar-card .selected-dot    { background: var(--p-primary) !important; }

/* kanan: Booked Orders */
body .app #dayBookingsCard .card-header {
    display: flex !important;
    align-items: flex-start !important;
    justify-content: space-between !important;
    gap: 10px;
    margin: 0 0 8px !important;
    padding: 0 !important;
    border: 0 !important;
}
body .app #dayBookingsTitle {
    margin: 0 !important;
    
    font-size: 21px !important;
    font-weight: 700 !important;
    color: var(--p-plum) !important;
}
body .app #dayBookingsCard .clear-day-filter {
    flex: 0 0 auto;
    padding: 0 !important;
    background: none !important;
    border: 0 !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    color: var(--p-muted) !important;
    text-decoration: none !important;
}
body .app #dayBookingsCard .clear-day-filter:hover { color: var(--p-primary) !important; }

body .app #dayBookingsList {
    display: flex !important;
    flex-direction: column !important;
    gap: 2px !important;
    max-height: calc(100vh - 210px) !important;
    flex: 0 1 auto !important;
    overflow-y: auto !important;
    padding: 0 !important;
}
body .app #dayBookingsList .cal-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 14px;
    border-radius: 12px;
}
body .app #dayBookingsList .cal-row {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;
    text-align: left !important;
    padding: 14px !important;
    margin: 0 !important;
    min-height: 0 !important;
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    cursor: default;
}
body .app #dayBookingsList .cal-row:hover { background: #fff8fa !important; }
body .app #dayBookingsList .cal-row-id { flex: 0 0 auto; font-size: 15px; font-weight: 800; color: var(--p-plum); }
body .app #dayBookingsList .cal-row-name {
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 15px;
    color: var(--p-muted);
}
body .app #dayBookingsList .cal-row-date { flex: 0 0 auto; font-size: 12.5px; color: var(--p-muted); }
body .app #dayBookingsList .cal-chip {
    flex: 0 0 auto;
    padding: 3px 12px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
}
body .app #dayBookingsList .cal-chip-rush    { background: var(--p-warn-bg); color: var(--p-warn); }
body .app #dayBookingsList .cal-chip-nonrush { background: var(--p-ship-bg); color: var(--p-ship); }
body .app #dayBookingsList .empty-state {
    padding: 36px 10px !important;
    text-align: center;
    font-size: 14px;
    color: var(--p-muted) !important;
    background: none !important;
    border: 0 !important;
}

@media (max-width: 1000px) {
    body .app .calendar-page-layout { grid-template-columns: minmax(0, 1fr) !important; }
}
@media (max-width: 600px) {
    body .app .full-calendar-days .large-day { height: 38px !important; font-size: 13px !important; }
    body .app .full-week,
    body .app .full-calendar-days { gap: 4px !important; }
}
</style>

</body>

</html>