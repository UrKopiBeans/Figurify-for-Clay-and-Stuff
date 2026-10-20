<?php

require_once "owner-header.php";
require_once __DIR__ . "/../helpers/activity_log_helper.php";
require_once __DIR__ . "/../helpers/csrf_helper.php";
require_once __DIR__ . "/../helpers/ban_helper.php";

/* CSRF: ang create/role change/delete ng account ay kailangang
   may tamang token (awtomatikong nilalagay ng Shared/all-in-one.php) */
if ($_SERVER["REQUEST_METHOD"] === "POST" && !figurify_verify_csrf($_POST["csrf_token"] ?? null)) {
    $_POST = [];
    $csrfRejected = true;
}


/* MESSAGE */

$success = "";
$error = !empty($csrfRejected) ? "Your session expired or the request was invalid. Please try again." : "";


/* CREATE USER */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["action"])
    && $_POST["action"] === "create_user"
) {

    $full_name = trim($_POST["full_name"] ?? "");
    $email     = trim($_POST["email"] ?? "");
    $password  = $_POST["password"] ?? "";
    $role      = strtolower(trim($_POST["role"] ?? "customer"));

    $allowedRoles = [
        "customer",
        "staff",
        "admin"
    ];

    if (!in_array($role, $allowedRoles, true)) {
        $error = "Invalid account role.";
    }


    elseif (
        $full_name === ""
        || $email === ""
        || $password === ""
    ) {

        $error = "Please complete all required fields.";

    }


    elseif (strlen($password) < 6) {

        $error = "Password must contain at least 6 characters.";

    }


    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    }


    else {

        $check = $conn->prepare(
            "SELECT user_id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $check->bind_param(
            "s",
            $email
        );

        $check->execute();

        $check->store_result();


        if ($check->num_rows > 0) {

            $error = "An account with this email already exists.";

            $check->close();

        } else {

            $check->close();


            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            $stmt = $conn->prepare(
                "INSERT INTO users
                (
                    full_name,
                    email,
                    password,
                    role
                )
                VALUES (?, ?, ?, ?)"
            );


            $stmt->bind_param(
                "ssss",
                $full_name,
                $email,
                $hashedPassword,
                $role
            );


            if ($stmt->execute()) {

                $success =
                    "Account created successfully.";

                figurify_log_activity(
                    $conn,
                    "user_created",
                    "Created a new \"" . $role . "\" account: " . $full_name . " (" . $email . ")"
                );

            } else {

                $error =
                    "Unable to create account.";

            }


            $stmt->close();
        }
    }
}


/* UPDATE ROLE */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["action"])
    && $_POST["action"] === "update_role"
) {

    $user_id = (int)($_POST["user_id"] ?? 0);
    $role    = strtolower(trim($_POST["role"] ?? ""));


    $allowedRoles = [
        "customer",
        "staff",
        "admin"
    ];


    if ($user_id <= 0) {

        $error = "Invalid user.";

    }

    elseif (!in_array($role, $allowedRoles, true)) {

        $error = "Invalid role.";

    }

    elseif ($user_id === (int)$_SESSION["user_id"]) {

        $error =
            "You cannot change your own owner role.";

    }

    else {

        $targetStmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
        $targetStmt->bind_param("i", $user_id);
        $targetStmt->execute();
        $targetUser = $targetStmt->get_result()->fetch_assoc();
        $targetStmt->close();

        $stmt = $conn->prepare(
            "UPDATE users
             SET role = ?
             WHERE user_id = ?"
        );

        $stmt->bind_param(
            "si",
            $role,
            $user_id
        );


        if ($stmt->execute()) {

            $success =
                "User role updated successfully.";

            figurify_log_activity(
                $conn,
                "user_role_updated",
                "Changed " . ($targetUser["full_name"] ?? ("user #" . $user_id)) . "'s role to \"" . $role . "\""
            );

        } else {

            $error =
                "Unable to update user role.";

        }


        $stmt->close();
    }
}


/* DELETE USER */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["action"])
    && $_POST["action"] === "delete_user"
) {

    $user_id = (int)($_POST["user_id"] ?? 0);


    if ($user_id <= 0) {

        $error = "Invalid user.";

    }

    elseif ($user_id === (int)$_SESSION["user_id"]) {

        $error =
            "You cannot delete your own account.";

    }

    else {

        $targetStmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
        $targetStmt->bind_param("i", $user_id);
        $targetStmt->execute();
        $targetUser = $targetStmt->get_result()->fetch_assoc();
        $targetStmt->close();

        $stmt = $conn->prepare(
            "DELETE FROM users
             WHERE user_id = ?"
        );

        $stmt->bind_param(
            "i",
            $user_id
        );


        if ($stmt->execute()) {

            $success =
                "User account deleted successfully.";

            figurify_log_activity(
                $conn,
                "user_deleted",
                "Deleted account: " . ($targetUser["full_name"] ?? ("user #" . $user_id))
                    . (!empty($targetUser["email"]) ? (" (" . $targetUser["email"] . ")") : "")
            );

        } else {

            $error =
                "Unable to delete user.";

        }


        $stmt->close();
    }
}


/* BAN / UNBAN */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && in_array($_POST["action"] ?? "", ["ban_user", "unban_email"], true)
) {

    if ($_POST["action"] === "ban_user") {

        $user_id = (int) ($_POST["user_id"] ?? 0);
        $reason  = trim((string) ($_POST["ban_reason"] ?? ""));

        if ($reason === "other") {
            $reason = trim((string) ($_POST["ban_reason_other"] ?? ""));
        }

        $targetStmt = $conn->prepare("SELECT user_id, full_name, email, role FROM users WHERE user_id = ? LIMIT 1");
        $targetStmt->bind_param("i", $user_id);
        $targetStmt->execute();
        $targetUser = $targetStmt->get_result()->fetch_assoc();
        $targetStmt->close();

        if (!$targetUser) {
            $error = "Invalid user.";
        } elseif ($user_id === (int) $_SESSION["user_id"]) {
            $error = "You cannot ban your own account.";
        } elseif ($targetUser["role"] === "admin") {
            $error = "Admin accounts can't be banned. Change the role first.";
        } elseif ($reason === "") {
            $error = "Please choose a reason for the ban.";
        } elseif (figurify_ban_email($conn, $targetUser["email"], $reason, (int) $_SESSION["user_id"])) {
            $success = $targetUser["full_name"] . " has been banned.";
            figurify_log_activity(
                $conn,
                "user_banned",
                "Banned account: " . $targetUser["full_name"] . " (" . $targetUser["email"] . ") — " . $reason
            );
        } else {
            $error = "Unable to ban this account.";
        }

    } else {

        $email = trim((string) ($_POST["email"] ?? ""));

        if ($email !== "" && figurify_unban_email($conn, $email)) {
            $success = $email . " has been unbanned.";
            figurify_log_activity($conn, "user_unbanned", "Unbanned: " . $email);
        } else {
            $error = "Unable to unban this email.";
        }
    }
}


/* GET USERS */

$users = [];
$bannedList = figurify_banned_list($conn);


// "last_seen" ay optional column (Online/Offline feature) - subukan muna, fallback sa query na wala nito
// kung hindi pa na-migrate ang DB.

try {

    $result = $conn->query(
        "SELECT
            user_id,
            full_name,
            email,
            role,
            last_seen
         FROM users
         ORDER BY user_id DESC"
    );

} catch (\Throwable $e) {

    $result = $conn->query(
        "SELECT
            user_id,
            full_name,
            email,
            role
         FROM users
         ORDER BY user_id DESC"
    );

}


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $row["last_seen"] = $row["last_seen"] ?? null;
        $row["ban"]       = $bannedList[strtolower((string) $row["email"])] ?? null;

        $users[] = $row;

    }
}


// Group users - Admin muna, Staff (iisang "Team" container), hiwalay na container ang Customers.
// Walang "owner" role sa DB, "Admin" ang sumasagisag sa owner account.

$adminUsers    = [];
$staffUsers    = [];
$customerUsers = [];

foreach ($users as $u) {

    if ($u["role"] === "admin") {
        $adminUsers[] = $u;
    } elseif ($u["role"] === "staff") {
        $staffUsers[] = $u;
    } else {
        $customerUsers[] = $u;
    }

}

$teamUsers = array_merge($adminUsers, $staffUsers);


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

<title>
    Figurify — User Management
</title>

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

    white-space: nowrap;
}

.banned-badge {
    color: #b42318;
    background: #fde8e7;
    padding: 5px 9px;
    border-radius: 99px;
    font-size: 9px;
    font-weight: 900;
    white-space: nowrap;
    cursor: help;
}

.unban-btn {
    border: 1px solid #cfe9d9;
    background: #effaf3;
    color: #187a43;
    padding: 6px 14px;
    border-radius: 99px;
    font-size: 10px;
    font-weight: 800;
    cursor: pointer;
}

.unban-btn:hover { background: #dff3e7; }

.submit-user.ban-submit { background: #d92d20 !important; }
.submit-user.ban-submit:hover { background: #b42318 !important; }

/* OFFLINE - kapareho ng .online-badge pero gray/muted, "Xh/Xd ago" text (see figurify_presence_status) */
.offline-badge {
    color: var(--gray);

    background: var(--background);

    padding: 5px 9px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;

    white-space: nowrap;
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


/* User Management styles */

.user-header,
.user-row {
    grid-template-columns:
        .5fr
        1fr
        1.5fr
        .8fr
        1fr;
}

.role-badge {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 99px;

    background: var(--pink-light);

    color: var(--pink-dark);

    font-size: 9px;

    font-weight: 900;
}


/* USER MANAGEMENT */

.user-page {
    max-width: 1400px;
}


/* HEADER */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 22px;
}

.page-header h2 {
    font-size: 26px;
}

.page-header p {
    color: #81777d;
    font-size: 12px;
    margin-top: 5px;
}


/* CREATE BUTTON */

.create-user-btn {
    display: inline-flex;
    align-items: center;
    gap: 9px;

    border: none;
    border-radius: 999px;
    padding: 9px 18px 9px 10px;

    background: linear-gradient(135deg, #f27fb4 0%, #d9539a 100%);
    color: #ffffff;

    
    font-weight: 800;
    font-size: 13px;
    letter-spacing: .2px;

    cursor: pointer;
    box-shadow: 0 6px 16px rgba(217, 83, 154, .28), inset 0 1px 0 rgba(255, 255, 255, .25);
    transition: transform .15s, box-shadow .15s, filter .15s;
}

.create-user-btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: rgba(255, 255, 255, .22);
}

.create-user-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.04);
    box-shadow: 0 10px 22px rgba(217, 83, 154, .34), inset 0 1px 0 rgba(255, 255, 255, .25);
}

.create-user-btn:active {
    transform: translateY(0);
    box-shadow: 0 4px 10px rgba(217, 83, 154, .25);
}

.create-user-btn:focus-visible {
    outline: 3px solid #f9c6e0;
    outline-offset: 2px;
}

@media (max-width: 600px) {
    .create-user-btn { padding: 7px 14px 7px 8px; font-size: 12px; }
    .create-user-btn-icon { width: 22px; height: 22px; }
}


/* ALERT */

.alert {
    padding: 13px 15px;

    border-radius: 12px;

    margin-bottom: 15px;

    font-size: 11px;
    font-weight: 700;
}

.alert-success {
    background: #e1f3e7;
    color: #397a57;
}

.alert-error {
    background: #ffe4e8;
    color: #b34e5c;
}


/* Search + Create Account row */

.user-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 15px;
}

.user-actions-row {
    display: flex;
    justify-content: flex-end;
    align-items: center;

    margin-bottom: 18px;
}

.user-search {
    width: 220px;

    border: 1px solid #eee7ea;

    border-radius: 12px;

    padding: 9px 12px;

    outline: none;

    font-size: 11px;
}

.user-search:focus {
    border-color: #ff7aa2;
}


/* Grouped containers - "Team" (Admin+Staff) at "Customers" hiwalay na .card, may sariling search box */

.user-group-card {
    margin-bottom: 20px;
}

.user-group-card:last-child {
    margin-bottom: 0;
}

.user-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;

    margin-bottom: 14px;
}

.user-group-header h3 {
    font-size: 15px;
    font-weight: 800;
}

.user-group-header p {
    color: #9a8c94;
    font-size: 11px;
    margin-top: 3px;
}


/* table-layout: fixed + parehong % width sa Team at Customers table para pantay ang column sizes */

.user-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.user-table th:nth-child(1), .user-table td:nth-child(1) { width: 26%; }
.user-table th:nth-child(2), .user-table td:nth-child(2) { width: 26%; }
.user-table th:nth-child(3), .user-table td:nth-child(3) { width: 14%; }
.user-table th:nth-child(4), .user-table td:nth-child(4) { width: 18%; }
.user-table th:nth-child(5), .user-table td:nth-child(5) { width: 16%; }

.user-table th {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-table td:nth-child(1),
.user-table td:nth-child(2) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-table th {
    text-align: left;

    color: #9a8c94;

    font-size: 9px;

    text-transform: uppercase;

    padding: 12px;

    border-bottom: 1px solid #eee7ea;
}

.user-table td {
    padding: 13px 12px;

    border-bottom: 1px solid #f1eaed;

    font-size: 11px;
}


/* USER */

.user-info {
    display: flex;
    align-items: center;
    gap: 9px;
}

/* ROLE */

.role {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 99px;

    font-size: 9px;

    font-weight: 900;
}

.role-admin {
    background: #ffe4ed;
    color: #a4476d;
}

.role-staff {
    background: #e8edff;
    color: #536fc5;
}

.role-customer {
    background: #e1f3e7;
    color: #3b8b62;
}


/* ACTIONS */

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.action-btn {
    border: none;

    padding: 7px 9px;

    border-radius: 8px;

    font-size: 9px;

    font-weight: 800;

    cursor: pointer;
}

.edit-btn {
    background: #e8edff;
    color: #536fc5;
}

.delete-btn {
    background: #ffe4e8;
    color: #b34e5c;
}


/* 3-DOTS MENU (Edit Role / Delete) */

.kebab-btn {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: #81777d;
    cursor: pointer;
}

.kebab-btn svg {
    fill: currentColor;
}

.kebab-btn:hover,
.kebab-btn[aria-expanded="true"] {
    background: #f4eef2;
    color: #29252a;
}

/* position: fixed para hindi maputol ng overflow ng table wrapper */
.row-menu {
    position: fixed;
    z-index: 1000;
    min-width: 140px;
    padding: 6px;
    background: #ffffff;
    border: 1px solid #eee7ea;
    border-radius: 10px;
    box-shadow: 0 10px 24px rgba(41, 37, 42, .14);
}

.row-menu[hidden] {
    display: none;
}

.row-menu form {
    margin: 0;
}

.row-menu-item {
    display: block;
    width: 100%;
    padding: 8px 10px;
    border: none;
    border-radius: 7px;
    background: transparent;
    text-align: left;
    font-size: 12.5px;
    font-weight: 600;
    color: #29252a;
    cursor: pointer;
}

.row-menu-item:hover {
    background: #f4eef2;
}

.row-menu-danger {
    color: #b34e5c;
}

.row-menu-danger:hover {
    background: #ffe4e8;
}


/* MODAL */

.user-modal {
    position: fixed;

    inset: 0;

    background: rgba(35,25,30,.42);

    display: none;

    align-items: center;
    justify-content: center;

    z-index: 999;
}

.user-modal.show {
    display: flex;
}

.user-modal-box {
    width: min(450px, 92%);

    background: white;

    border-radius: 20px;

    padding: 24px;
}

.user-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 20px;
}

.user-modal-header h3 {
    font-size: 18px;
}

.close-user-modal { display: none !important; }
.close-user-modal-old {
    border: none;

    width: 30px;
    height: 30px;

    border-radius: 9px;

    background: #ffe4ed;

    font-size: 18px;

    cursor: pointer;
}


/* FORM */

.form-group {
    margin-bottom: 13px;
}

.form-group label {
    display: block;

    font-size: 10px;

    font-weight: 800;

    margin-bottom: 5px;
}

.form-group input,
.form-group select {
    width: 100%;

    border: 1px solid #eee7ea;

    border-radius: 10px;

    padding: 11px;

    outline: none;

    font-size: 11px;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #ff7aa2;
}

.user-modal-actions { margin-top: 14px; }
.user-modal-actions .pu-btn { border: 0; border-radius: 10px; color: #fff; font-weight: 700;  cursor: pointer; }
.submit-user {
    width: 100%;

    border: none;

    background: #ff7aa2;

    padding: 12px;

    border-radius: 11px;

    font-weight: 900;

    cursor: pointer;

    margin-top: 5px;
}


/* ROLE FORM */

.role-form {
    display: flex;
    align-items: center;
    gap: 6px;
}

.role-form select {
    border: 1px solid #eee7ea;

    padding: 6px;

    border-radius: 8px;

    font-size: 9px;
}

.save-role {
    border: none;

    background: #e1f3e7;

    color: #397a57;

    padding: 6px 8px;

    border-radius: 7px;

    font-size: 9px;

    font-weight: 800;

    cursor: pointer;
}


@media(max-width:800px) {

    .user-toolbar,
    .user-actions-row,
    .user-group-header {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .user-search {
        width: 100%;
    }

    .user-table {
        min-width: 700px;
    }

    .user-page {
        overflow-x: auto;
    }

}

</style>

<style>
/* Punuin ang screen: ang huling container hanggang sa ilalim */
@media (min-width: 1101px) and (min-height: 640px) {
    .app > main.main {
        min-height: 100vh;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        padding-bottom: 20px !important;
    }
    .app > main.main > .user-page {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        max-width: none;
        padding-top: 0;
        margin-top: 0;
    }
    .app > main.main > .user-page > .user-group-card:last-of-type {
        flex: 1 1 auto;
        margin-bottom: 0;
    }
}
</style>
<style>
/* ==========================================================
   USER MANAGEMENT - Design System (Team / Customers / Banned Emails + modals)
   ========================================================== */

.um-page { display: flex; flex-direction: column; gap: 22px; }

.um-actions-row { display: flex; justify-content: flex-end; }

.um-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 40px;
    padding: 0 18px;
    border: 0;
    border-radius: 12px;
    
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s ease, color .15s ease;
}
.um-btn-primary { background: var(--p-primary, #c2325f); color: #ffffff; }
.um-btn-primary:hover { background: var(--p-primary-dark, #a82a52); }
.um-btn-soft { background: var(--p-blush, #ffedf2); color: var(--p-primary, #c2325f); }
.um-btn-soft:hover { background: #ffd9e3; }
.um-btn-danger { background: var(--p-bad, #b83227); color: #ffffff; }
.um-btn-danger:hover { background: #9c2a20; }
.um-btn-lg { height: 44px; padding: 0 20px; font-size: 14px; }

/* cards */
.um-card { padding: 24px 24px 18px !important; }

.um-card-head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px 20px;
    margin-bottom: 8px;
}
.um-card-title {
    margin: 0;
    
    font-size: 21px;
    font-weight: 700;
    color: var(--p-plum, #4a1d3b);
}
.um-card-sub { margin: 4px 0 0; font-size: 14px; color: var(--p-muted, #7c6670); }

.um-search {
    width: 280px;
    max-width: 100%;
    height: 48px;
    padding: 0 16px;
    border: 1px solid var(--p-line, #f3dce3);
    border-radius: 14px;
    background: #ffffff;
    
    font-size: 14.5px;
    color: var(--p-plum, #4a1d3b);
    outline: none;
}
.um-search::placeholder { color: #8f7d86; }
.um-search:focus { border-color: var(--p-primary, #c2325f); }

/* table */
.um-table-wrap { overflow-x: auto; }
.um-table { width: 100%; min-width: 680px; border-collapse: collapse; table-layout: fixed; }
/* pare-parehong lapad ng column sa Team at Customers */
.um-table-users th:nth-child(1) { width: 27%; }
.um-table-users th:nth-child(2) { width: 29%; }
.um-table-users th:nth-child(3) { width: 16%; }
.um-table-users th:nth-child(4) { width: 16%; }
.um-table-banned th:nth-child(1) { width: 27%; }
.um-table-banned th:nth-child(2) { width: 34%; }
.um-table-banned th:nth-child(3) { width: 27%; }
.um-table td { overflow: hidden; text-overflow: ellipsis; }
.um-table td.um-col-actions { overflow: visible; text-align: right; }
.um-table th.um-col-actions { text-align: right; }
.um-table thead th {
    height: 38px;
    padding: 0 8px;
    border-bottom: 1.5px solid #ecd9e0;
    text-align: left;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--p-muted, #7c6670);
    white-space: nowrap;
}
.um-table tbody td {
    height: 54px;
    padding: 0 8px;
    border-bottom: 1px solid #f6e8ed;
    font-size: 13px;
    color: var(--p-plum, #4a1d3b);
    vertical-align: middle;
}
.um-table tbody tr:hover td { background: #fff8fa; }
.um-table .um-col-actions { width: 12%; min-width: 90px; }
.um-email { color: var(--p-muted, #7c6670) !important; }

.um-user { display: flex; align-items: center; gap: 10px; min-width: 0; }
.um-avatar {
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--p-blush, #ffedf2);
    color: var(--p-plum, #4a1d3b);
    font-size: 12.5px;
    font-weight: 800;
}
.um-name { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.um-chip {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1.5;
    white-space: nowrap;
    background: var(--p-blush, #ffedf2);
    color: var(--p-primary, #c2325f);
}
.um-chip-ok  { background: var(--p-ok-bg, #dff3e8);  color: var(--p-ok, #2f8f67); }
.um-chip-bad { background: var(--p-bad-bg, #fbdada); color: var(--p-bad, #b83227); }

.um-empty-row td { height: 90px !important; text-align: center; color: var(--p-muted, #7c6670) !important; }

/* 3 dots + dropdown */
.um-kebab {
    margin-left: auto;
    display: grid;
    place-items: center;
    width: 32px;
    height: 32px;
    padding: 0;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--p-plum, #4a1d3b);
    cursor: pointer;
}
.um-kebab:hover,
.um-kebab[aria-expanded="true"] { background: var(--p-blush, #ffedf2); }
.um-kebab svg { fill: currentColor; }

.um-menu {
    position: fixed;
    z-index: 1200;
    min-width: 198px;
    padding: 8px;
    border: 1px solid var(--p-line, #f3dce3);
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 18px 40px -16px rgba(74, 29, 59, .35);
}
.um-menu[hidden] { display: none; }
.um-menu form { margin: 0; }
.um-menu-item {
    display: block;
    width: 100%;
    padding: 12px 14px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    text-align: left;
    
    font-size: 14.5px;
    font-weight: 500;
    color: var(--p-plum, #4a1d3b);
    cursor: pointer;
}
.um-menu-item:hover { background: #fff3f6; }
.um-menu-item.is-danger:hover { background: var(--p-bad-bg, #fbdada); color: var(--p-bad, #b83227); }

/* ---------- modals ---------- */
.um-modal {
    position: fixed;
    inset: 0;
    z-index: 1300;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(74, 29, 59, .45);
}
.um-modal.show { display: flex; }

.um-modal-box {
    width: min(420px, 100%);
    max-height: calc(100vh - 32px);
    overflow-y: auto;
    padding: 28px 28px 26px;
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 30px 70px -24px rgba(45, 14, 32, .55);
    animation: umModalIn .18s ease;
}
@keyframes umModalIn { from { transform: scale(.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.um-modal-title {
    margin: 0;
    
    font-size: 21px;
    font-weight: 700;
    color: var(--p-plum, #4a1d3b);
}
.um-modal-user { margin: 4px 0 0; font-size: 14.5px; color: var(--p-muted, #7c6670); }
.um-modal-user b { font-weight: 500; color: var(--p-muted, #7c6670); }

.um-modal form { margin-top: 2px; }

.um-field { margin-top: 16px; }
.um-field label {
    display: block;
    margin-bottom: 8px;
    font-size: 12.5px;
    font-weight: 800;
    letter-spacing: .02em;
    text-transform: uppercase;
    color: var(--p-plum, #4a1d3b);
}
.um-field input,
.um-field select {
    width: 100%;
    height: 48px;
    padding: 0 14px;
    border: 1px solid #ecd9e0;
    border-radius: 14px;
    background: #ffffff;
    
    font-size: 14.5px;
    color: var(--p-plum, #4a1d3b);
    outline: none;
}
.um-field input:focus,
.um-field select:focus { border-color: var(--p-primary, #c2325f); }
.um-field input::placeholder { color: #8f7d86; text-transform: none; }

.um-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }

@media (max-width: 700px) {
    .um-search { width: 100%; }
    .um-card-head > div:first-child { flex: 1 1 100%; }
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


<!-- SIDEBAR (shared across every owner page) -->

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


<!-- MAIN -->

<main class="main">

    <header class="topbar">

        <div class="welcome">
            <h2 class="portal-title">User Management</h2>
        </div>

        <div class="top-actions">
            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>
        </div>

    </header>

<div class="um-page">

    <?php
    // alert - pop up (Shared/all-in-one.php)
    if ($success !== "") {
        echo figurify_toast_now($success, "success");
    }

    if ($error !== "") {
        echo figurify_toast_now($error, "error");
    }


    // initials ng pangalan (hal. "Maria Santos" -> "M")
    function figurify_um_initial(string $name): string
    {
        $name = trim($name);

        return $name === "" ? "?" : strtoupper(mb_substr($name, 0, 1));
    }


    // label ng role sa chip - "admin" sa DB ang owner account
    function figurify_um_role_label(string $role): string
    {
        $labels = ["admin" => "Owner", "staff" => "Staff", "customer" => "Customer"];

        return $labels[$role] ?? ucfirst($role);
    }


    // Gumagawa lang ng <tr> - tinatawag para sa Team at Customers
    function figurify_render_user_rows(array $userList, string $emptyMessage): void
    {
        if (empty($userList)) {
            ?>
            <tr class="um-empty-row">
                <td colspan="5"><?php echo e($emptyMessage); ?></td>
            </tr>
            <?php
            return;
        }

        foreach ($userList as $user):

            $isSelf   = (int) $user["user_id"] === (int) $_SESSION["user_id"];
            $isBanned = !empty($user["ban"]);
            $presence = figurify_presence_status($user["last_seen"] ?? null);
            ?>

            <tr
                class="user-row"
                data-search="<?php echo e($user["full_name"] . " " . $user["email"] . " " . $user["role"] . " " . figurify_um_role_label($user["role"])); ?>"
                data-user-id="<?php echo (int) $user["user_id"]; ?>"
                data-user-name="<?php echo e($user["full_name"]); ?>"
                data-user-email="<?php echo e($user["email"]); ?>"
                data-user-role="<?php echo e($user["role"]); ?>"
            >

                <td>
                    <div class="um-user">
                        <span class="um-avatar" aria-hidden="true"><?php echo e(figurify_um_initial((string) $user["full_name"])); ?></span>
                        <span class="um-name"><?php echo e($user["full_name"]); ?></span>
                    </div>
                </td>

                <td class="um-email"><?php echo e($user["email"]); ?></td>

                <td>
                    <span class="um-chip"><?php echo e(figurify_um_role_label($user["role"])); ?></span>
                </td>

                <td>
                    <?php if ($isBanned): ?>
                        <span class="um-chip um-chip-bad" title="<?php echo e($user["ban"]["reason"] ?? ""); ?>">Banned</span>
                    <?php else: ?>
                        <span class="um-chip um-chip-ok" title="<?php echo e($presence["label"]); ?>">Active</span>
                    <?php endif; ?>
                </td>

                <td class="um-col-actions">

                    <?php if ($isSelf): ?>

                        <span class="um-chip">Current</span>

                    <?php else: ?>

                        <button
                            type="button"
                            class="um-kebab"
                            aria-label="More actions"
                            aria-haspopup="true"
                            aria-expanded="false"
                            onclick="toggleRowMenu(this, event)"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="5" r="1.8"></circle>
                                <circle cx="12" cy="12" r="1.8"></circle>
                                <circle cx="12" cy="19" r="1.8"></circle>
                            </svg>
                        </button>

                        <div class="um-menu" role="menu" hidden>

                            <button type="button" class="um-menu-item" role="menuitem" onclick="closeRowMenus(); editRole(this)">
                                Edit Role
                            </button>

                            <?php if ($isBanned): ?>
                                <form
                                    method="POST"
                                    onsubmit="closeRowMenus(); return csConfirmSubmit(this, 'Unban this account?\nThey will be able to log in again.', {okText: 'Unban'});"
                                >
                                    <input type="hidden" name="action" value="unban_email">
                                    <input type="hidden" name="email" value="<?php echo e($user["email"]); ?>">
                                    <button type="submit" class="um-menu-item" role="menuitem">Unban Account</button>
                                </form>
                            <?php elseif ($user["role"] !== "admin"): ?>
                                <button type="button" class="um-menu-item is-danger" role="menuitem" onclick="closeRowMenus(); openBanModal(this)">
                                    Ban Account
                                </button>
                            <?php endif; ?>

                            <form
                                method="POST"
                                onsubmit="closeRowMenus(); return csConfirmSubmit(this, 'Delete this account?\nThis can\'t be undone.', {okText: 'Delete'});"
                            >
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user["user_id"]; ?>">
                                <button type="submit" class="um-menu-item is-danger" role="menuitem">Delete</button>
                            </form>

                        </div>

                    <?php endif; ?>

                </td>

            </tr>

        <?php endforeach;
    }
    ?>


    <!-- CREATE ACCOUNT -->

    <div class="um-actions-row">
        <button type="button" class="um-btn um-btn-primary um-btn-lg" id="openCreateUser">+ Create Account</button>
    </div>


    <!-- TEAM: Owner (admin) muna, tapos Staff -->

    <section class="card um-card">

        <div class="um-card-head">
            <div>
                <h3 class="um-card-title">Team</h3>
                <p class="um-card-sub">Admin &amp; Staff accounts.</p>
            </div>

            <input
                type="search"
                class="um-search"
                placeholder="Search name, email, role..."
                aria-label="Search team accounts"
                oninput="figurifyFilterUserGroup('teamTable', this.value)"
            >
        </div>

        <div class="um-table-wrap">
            <table class="um-table um-table-users" id="teamTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="um-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php figurify_render_user_rows($teamUsers, "Walang admin o staff account."); ?>
                </tbody>
            </table>
        </div>

    </section>


    <!-- CUSTOMERS -->

    <section class="card um-card">

        <div class="um-card-head">
            <div>
                <h3 class="um-card-title">Customers</h3>
                <p class="um-card-sub">Customer accounts.</p>
            </div>

            <input
                type="search"
                class="um-search"
                placeholder="Search name, email, role..."
                aria-label="Search customer accounts"
                oninput="figurifyFilterUserGroup('customerTable', this.value)"
            >
        </div>

        <div class="um-table-wrap">
            <table class="um-table um-table-users" id="customerTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="um-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php figurify_render_user_rows($customerUsers, "Wala pang customer account."); ?>
                </tbody>
            </table>
        </div>

    </section>


    <!-- BANNED EMAILS -->

    <section class="card um-card">

        <div class="um-card-head">
            <div>
                <h3 class="um-card-title">Banned Emails</h3>
                <p class="um-card-sub">These emails can't log in or sign up again.</p>
            </div>
        </div>

        <div class="um-table-wrap">
            <table class="um-table um-table-banned">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Reason</th>
                        <th>Banned On</th>
                        <th class="um-col-actions"><span class="visually-hidden" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bannedList)): ?>
                        <tr class="um-empty-row">
                            <td colspan="4">Walang naka-ban na email.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bannedList as $banRow): ?>
                            <?php
                            $banTime = strtotime((string) $banRow["banned_at"]);
                            $banDate = $banTime ? date(date("Y", $banTime) === date("Y") ? "M j" : "M j, Y", $banTime) : "—";
                            ?>
                            <tr>
                                <td><?php echo e($banRow["email"]); ?></td>
                                <td><?php echo e($banRow["reason"] ?? ""); ?></td>
                                <td><?php echo e($banDate); ?></td>
                                <td class="um-col-actions">
                                    <form
                                        method="POST"
                                        onsubmit="return csConfirmSubmit(this, 'Unban this email?', {okText: 'Unban'});"
                                    >
                                        <input type="hidden" name="action" value="unban_email">
                                        <input type="hidden" name="email" value="<?php echo e($banRow["email"]); ?>">
                                        <button type="submit" class="um-btn um-btn-soft">Unban</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

</div>

</main>

</div>


<!-- CREATE ACCOUNT MODAL -->

<div class="um-modal" id="createUserModal" role="dialog" aria-modal="true" aria-labelledby="createUserTitle">

    <div class="um-modal-box">

        <h3 class="um-modal-title" id="createUserTitle">Create Account</h3>

        <form method="POST">

            <input type="hidden" name="action" value="create_user">

            <div class="um-field">
                <label for="cuFullName">Full Name</label>
                <input type="text" id="cuFullName" name="full_name" required>
            </div>

            <div class="um-field">
                <label for="cuEmail">Email</label>
                <input type="email" id="cuEmail" name="email" required>
            </div>

            <div class="um-field">
                <label for="cuPassword">Password</label>
                <input type="password" id="cuPassword" name="password" placeholder="Minimum 6 characters" minlength="6" required>
            </div>

            <div class="um-field">
                <label for="cuRole">Account Role</label>
                <select id="cuRole" name="role" required>
                    <option value="customer">Customer</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin / Owner</option>
                </select>
            </div>

            <div class="um-modal-actions">
                <button type="button" class="um-btn um-btn-soft" onclick="closeUserModal('createUserModal')">Cancel</button>
                <button type="submit" class="um-btn um-btn-primary">Create</button>
            </div>

        </form>

    </div>

</div>


<!-- BAN ACCOUNT MODAL -->

<div class="um-modal" id="banModal" role="dialog" aria-modal="true" aria-labelledby="banTitle">

    <div class="um-modal-box">

        <h3 class="um-modal-title" id="banTitle">Ban Account</h3>
        <p class="um-modal-user">USER: <b id="banUserName"></b></p>

        <form method="POST" onsubmit="return csConfirmSubmit(this, 'Ban this account?\nThey will be logged out and can\'t log in or sign up again with this email.', {okText: 'Ban'});">

            <input type="hidden" name="action" value="ban_user">
            <input type="hidden" name="user_id" id="banUserId">

            <div class="um-field">
                <label for="banReason">Reason</label>
                <select name="ban_reason" id="banReason" onchange="document.getElementById('banOtherWrap').hidden = this.value !== 'other';" required>
                    <option value="">Choose a reason</option>
                    <option value="Spam or fake orders">Spam or fake orders</option>
                    <option value="Bot or automated account">Bot or automated account</option>
                    <option value="Fake payment proof">Fake payment proof</option>
                    <option value="Abusive or rude behavior">Abusive or rude behavior</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="um-field" id="banOtherWrap" hidden>
                <label for="banOther">Other Reason</label>
                <input type="text" id="banOther" name="ban_reason_other" maxlength="200" placeholder="Type the reason">
            </div>

            <div class="um-modal-actions">
                <button type="button" class="um-btn um-btn-soft" onclick="closeUserModal('banModal')">Cancel</button>
                <button type="submit" class="um-btn um-btn-danger">Ban</button>
            </div>

        </form>

    </div>

</div>


<!-- CHANGE ROLE MODAL -->

<div class="um-modal" id="roleModal" role="dialog" aria-modal="true" aria-labelledby="roleTitle">

    <div class="um-modal-box">

        <h3 class="um-modal-title" id="roleTitle">Change User Role</h3>
        <p class="um-modal-user">USER: <b id="editUserName"></b></p>

        <form method="POST">

            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="user_id" id="editUserId">

            <div class="um-field">
                <label for="editUserRole">New Role</label>
                <select name="role" id="editUserRole">
                    <option value="customer">Customer</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin / Owner</option>
                </select>
            </div>

            <div class="um-modal-actions">
                <button type="button" class="um-btn um-btn-soft" onclick="closeUserModal('roleModal')">Cancel</button>
                <button type="submit" class="um-btn um-btn-primary">Save Role</button>
            </div>

        </form>

    </div>

</div>


<script>

/* MODALS */

function openUserModal(id) {
    var modal = document.getElementById(id);

    if (!modal) {
        return;
    }

    modal.classList.add("show");

    var first = modal.querySelector("select, input:not([type=hidden])");

    if (first) {
        setTimeout(function () { first.focus(); }, 30);
    }
}

function closeUserModal(id) {
    var modal = document.getElementById(id);

    if (modal) {
        modal.classList.remove("show");
    }
}

document.getElementById("openCreateUser").addEventListener("click", function () {
    openUserModal("createUserModal");
});

// i-click ang madilim na background para isara
document.querySelectorAll(".um-modal").forEach(function (modal) {
    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            modal.classList.remove("show");
        }
    });
});


/* 3-DOTS MENU */

function closeRowMenus() {
    document.querySelectorAll(".um-menu").forEach(function (menu) {
        menu.hidden = true;
    });
    document.querySelectorAll(".um-kebab[aria-expanded='true']").forEach(function (btn) {
        btn.setAttribute("aria-expanded", "false");
    });
}

function toggleRowMenu(button, event) {
    event.stopPropagation();

    var menu = button.nextElementSibling;
    var wasOpen = menu && !menu.hidden;

    closeRowMenus();

    if (!menu || wasOpen) {
        return;
    }

    menu.hidden = false;
    button.setAttribute("aria-expanded", "true");

    // ilalim ng 3 dots, nakahanay sa kanan; iakyat kung kulang ang espasyo sa baba
    var rect = button.getBoundingClientRect();
    var top = rect.bottom + 6;

    if (top + menu.offsetHeight > window.innerHeight - 8) {
        top = rect.top - menu.offsetHeight - 6;
    }

    menu.style.top = Math.max(8, top) + "px";
    menu.style.left = Math.max(8, rect.right - menu.offsetWidth) + "px";
}

document.addEventListener("click", function (event) {
    if (!event.target.closest(".um-menu")) {
        closeRowMenus();
    }
});

window.addEventListener("resize", closeRowMenus);
window.addEventListener("scroll", closeRowMenus, true);


/* EDIT ROLE + BAN - kinukuha ang user info sa data-* ng row */

function rowUser(el) {
    var row = el.closest(".user-row");

    return row ? row.dataset : {};
}

function editRole(el) {
    var user = rowUser(el);

    document.getElementById("editUserId").value = user.userId || "";
    document.getElementById("editUserName").textContent = user.userName || "";
    document.getElementById("editUserRole").value = user.userRole || "customer";

    openUserModal("roleModal");
}

function openBanModal(el) {
    var user = rowUser(el);

    document.getElementById("banUserId").value = user.userId || "";
    document.getElementById("banUserName").textContent = user.userName || "";
    document.getElementById("banReason").value = "";
    document.getElementById("banOtherWrap").hidden = true;

    openUserModal("banModal");
}


/* SEARCH - sariling filter ang Team at Customers */

function figurifyFilterUserGroup(tableId, rawValue) {
    var search = rawValue.toLowerCase().trim();
    var table = document.getElementById(tableId);

    if (!table) {
        return;
    }

    table.querySelectorAll(".user-row").forEach(function (row) {
        var text = (row.dataset.search || "").toLowerCase();
        row.style.display = text.indexOf(search) !== -1 ? "" : "none";
    });
}


/* ESCAPE - isara ang menu at modals */

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        closeRowMenus();
        document.querySelectorAll(".um-modal.show").forEach(function (modal) {
            modal.classList.remove("show");
        });
    }
});

</script>



</body>

</html>