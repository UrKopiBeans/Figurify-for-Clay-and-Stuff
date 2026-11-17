<?php

require_once "owner-header.php";
require_once __DIR__ . "/../helpers/content_helper.php";
require_once __DIR__ . "/../helpers/reports_logs_helper.php";
require_once __DIR__ . "/../helpers/security_helper.php";

/* CSRF: ang create/role change/delete ng account ay kailangang
   may tamang token (awtomatikong nilalagay ng Shared/popup-and-security.php) */
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

    $first_name  = trim($_POST["first_name"] ?? "");
    $middle_name = trim($_POST["middle_name"] ?? "");
    $last_name   = trim($_POST["last_name"] ?? "");
    $full_name   = figurify_full_name($first_name, $middle_name, $last_name);
    $email       = trim($_POST["email"] ?? "");
    $password    = $_POST["password"] ?? "";
    $role        = strtolower(trim($_POST["role"] ?? "customer"));

    $allowedRoles = [
        "customer",
        "staff",
        "admin"
    ];

    if (!in_array($role, $allowedRoles, true)) {
        $error = "Invalid account role.";
    }


    elseif (
        $first_name === ""
        || $last_name === ""
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
                    first_name,
                    middle_name,
                    last_name,
                    email,
                    password,
                    role
                )
                VALUES (?, NULLIF(?, ''), ?, ?, ?, ?)"
            );


            $stmt->bind_param(
                "ssssss",
                $first_name,
                $middle_name,
                $last_name,
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


/* ARCHIVE / RESTORE USER - imbes na burahin, naka-archive lang (hindi makakapag-login,
   pero buo pa ang orders at history). Check sa login: helpers/security_helper.php SECTION 6. */

figurify_archive_ensure_column($conn);

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && in_array($_POST["action"] ?? "", ["archive_user", "restore_user"], true)
) {

    $user_id = (int) ($_POST["user_id"] ?? 0);
    $archive = ($_POST["action"] === "archive_user");

    $targetStmt = $conn->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
    $targetStmt->bind_param("i", $user_id);
    $targetStmt->execute();
    $targetUser = $targetStmt->get_result()->fetch_assoc();
    $targetStmt->close();

    if (!$targetUser) {

        $error = "Invalid user.";

    } elseif ($archive && $user_id === (int) $_SESSION["user_id"]) {

        $error = "You cannot archive your own account.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE users SET archived_at = " . ($archive ? "NOW()" : "NULL") . " WHERE user_id = ?"
        );
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {

            if ($archive) {
                figurify_remember_forget_user($conn, $user_id);   // logout sa "Remember me" devices
            }

            $success = $targetUser["full_name"] . ($archive ? " has been archived." : " has been restored.");

            figurify_log_activity(
                $conn,
                $archive ? "user_archived" : "user_restored",
                ($archive ? "Archived account: " : "Restored account: ") . $targetUser["full_name"] . " (" . $targetUser["email"] . ")"
            );

        } else {

            $error = $archive ? "Unable to archive user." : "Unable to restore user.";

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
                "Banned account: " . $targetUser["full_name"] . " (" . $targetUser["email"] . "): " . $reason
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
            last_seen,
            archived_at
         FROM users
         ORDER BY user_id DESC"
    );

} catch (\Throwable $e) {

    $result = $conn->query(
        "SELECT
            user_id,
            full_name,
            email,
            role,
            archived_at
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


// Group users - Admin muna, Staff (iisang "Team" container), hiwalay na container ang Customers,
// at ang naka-archive ay nasa sariling "Archived" container.
// Walang "owner" role sa DB, "Admin" ang sumasagisag sa owner account.

$adminUsers    = [];
$staffUsers    = [];
$customerUsers = [];
$archivedUsers = [];

foreach ($users as $u) {

    if (!empty($u["archived_at"])) {
        $archivedUsers[] = $u;
    } elseif ($u["role"] === "admin") {
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
    <?php /* [POP UP AT SECURITY] pop up at security script lang (walang CSS) - Shared/popup-and-security.php */ include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>
    Figurify | User Management
</title>

<!-- ===================== STYLE NG PAGE NA ITO: user-management.css ===================== -->
<link rel="stylesheet" href="user-management.css">


</head>


<body class="sf-fixed">


<div class="app">


<!-- SIDEBAR (shared across every owner page) -->


<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<!-- MAIN -->

<main class="main">

    <header class="topbar">

        <div class="welcome">
            <h2 class="portal-title">User Management</h2>
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

<div class="um-page sf-body">

    <?php
    // alert - pop up (figurify_toast_now - helpers/security_helper.php)
    if ($success !== "") {
        echo figurify_toast_now($success, "success");
    }

    if ($error !== "") {
        echo figurify_toast_now($error, "error");
    }


    // label ng role sa chip - "admin" sa DB ang owner account
    function figurify_um_role_label(string $role): string
    {
        $labels = ["admin" => "Owner", "staff" => "Staff", "customer" => "Customer"];

        return $labels[$role] ?? ucfirst($role);
    }


    // Gumagawa lang ng <tr> - tinatawag para sa Team, Customers at Archived
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
            $isBanned   = !empty($user["ban"]);
            $isArchived = !empty($user["archived_at"]);
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
                    <span class="um-name"><?php echo e($user["full_name"]); ?></span>
                </td>

                <td class="um-email"><?php echo e($user["email"]); ?></td>

                <td>
                    <span class="um-chip"><?php echo e(figurify_um_role_label($user["role"])); ?></span>
                </td>

                <td>
                    <?php if ($isArchived): ?>
                        <span class="um-chip" title="Archived <?php echo e(date("M j, Y", strtotime((string) $user["archived_at"]))); ?>">Archived</span>
                    <?php elseif ($isBanned): ?>
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

                            <?php if ($isArchived): ?>
                            <form
                                method="POST"
                                onsubmit="closeRowMenus(); return csConfirmSubmit(this, 'Restore this account?\nThey will be able to log in again.', {okText: 'Restore'});"
                            >
                                <input type="hidden" name="action" value="restore_user">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user["user_id"]; ?>">
                                <button type="submit" class="um-menu-item" role="menuitem">Restore Account</button>
                            </form>
                            <?php else: ?>

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
                                onsubmit="closeRowMenus(); return csConfirmSubmit(this, 'Archive this account?\nThey won\'t be able to log in, but their orders stay saved. You can restore it anytime.', {okText: 'Archive'});"
                            >
                                <input type="hidden" name="action" value="archive_user">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user["user_id"]; ?>">
                                <button type="submit" class="um-menu-item is-danger" role="menuitem">Archive</button>
                            </form>
                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                </td>

            </tr>

        <?php endforeach;
    }
    ?>


    <!-- ISANG CONTAINER: sa itaas = Filter (Team / Customers / Archived / Banned Emails) sa kaliwa,
         Search + Create Account sa kanan; sa ilalim = ang napiling listahan -->

    <section class="card um-card">

        <div class="um-card-head">
            <?php
            echo cs_section_filter(
                "user-management",
                [
                    ["um-section-team",      "Team",          count($teamUsers)],
                    ["um-section-customers", "Customers",     count($customerUsers)],
                    ["um-section-archived",  "Archived",      count($archivedUsers)],
                    ["um-section-banned",    "Banned Emails", count($bannedList)],
                ]
            );
            ?>

            <div class="um-head-actions">
                <input
                    type="search"
                    class="um-search"
                    placeholder="Search name, email, role..."
                    aria-label="Search accounts"
                    oninput="figurifyFilterUsers(this.value)"
                >
                <button type="button" class="um-btn um-btn-primary um-btn-lg" id="openCreateUser">+ Create Account</button>
            </div>
        </div>


    <!-- TEAM: Owner (admin) muna, tapos Staff -->

    <div class="um-section" id="um-section-team">

        <p class="um-card-sub">Admin &amp; Staff accounts.</p>

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
                    <?php figurify_render_user_rows($teamUsers, "No admin or staff accounts yet."); ?>
                </tbody>
            </table>
        </div>

    </div>


    <!-- CUSTOMERS -->

    <div class="um-section" id="um-section-customers">

        <p class="um-card-sub">Customer accounts.</p>

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
                    <?php figurify_render_user_rows($customerUsers, "No customer accounts yet."); ?>
                </tbody>
            </table>
        </div>

    </div>


    <!-- ARCHIVED: hindi makakapag-login, puwedeng i-Restore -->

    <div class="um-section" id="um-section-archived">

        <p class="um-card-sub">Archived accounts can't log in. Their orders stay saved, and you can restore them anytime.</p>

        <div class="um-table-wrap">
            <table class="um-table um-table-users" id="archivedTable">
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
                    <?php figurify_render_user_rows($archivedUsers, "No archived accounts."); ?>
                </tbody>
            </table>
        </div>

    </div>


    <!-- BANNED EMAILS -->

    <div class="um-section" id="um-section-banned">

        <p class="um-card-sub">These emails can't log in or sign up again.</p>

        <div class="um-table-wrap">
            <table class="um-table um-table-banned">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Reason</th>
                        <th>Banned On</th>
                        <th class="um-col-actions"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bannedList)): ?>
                        <tr class="um-empty-row">
                            <td colspan="4">No banned emails.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bannedList as $banRow): ?>
                            <?php
                            $banTime = strtotime((string) $banRow["banned_at"]);
                            $banDate = $banTime ? date(date("Y", $banTime) === date("Y") ? "M j" : "M j, Y", $banTime) : "N/A";
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
                                        <button type="submit" class="um-btn um-btn-primary">Unban</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    </section>
    <!-- END ISANG CONTAINER -->

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
                <label for="cuFirstName">First Name</label>
                <input type="text" id="cuFirstName" name="first_name" maxlength="50" required>
            </div>

            <div class="um-field">
                <label for="cuMiddleName">Middle Name (optional)</label>
                <input type="text" id="cuMiddleName" name="middle_name" maxlength="50">
            </div>

            <div class="um-field">
                <label for="cuLastName">Last Name</label>
                <input type="text" id="cuLastName" name="last_name" maxlength="50" required>
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
                <button type="button" class="um-btn um-btn-danger" onclick="closeUserModal('createUserModal')">Cancel</button>
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
                <button type="button" class="um-btn um-btn-danger" onclick="closeUserModal('banModal')">Cancel</button>
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
                <button type="button" class="um-btn um-btn-danger" onclick="closeUserModal('roleModal')">Cancel</button>
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

    var position = menuPositionRule();
    if (position) {
        position.style.top = Math.max(8, top) + "px";
        position.style.left = Math.max(8, rect.right - menu.offsetWidth) + "px";
    }
}

/* posisyon ng bukas na menu: isinusulat sa sariling CSS rule ng page (.um-menu),
   hindi sa inline style ng element - walang inline style. Iisa lang ang bukas na menu. */
var umMenuRule = null;
function menuPositionRule() {
    if (umMenuRule) {
        return umMenuRule;
    }
    for (var s = 0; s < document.styleSheets.length; s++) {
        var sheet = document.styleSheets[s];
        var rules;
        try { rules = sheet.cssRules; } catch (e) { continue; }
        for (var r = 0; r < rules.length; r++) {
            if (rules[r].selectorText === ".um-menu") {
                umMenuRule = sheet.cssRules[sheet.insertRule(".um-menu {}", rules.length)];
                return umMenuRule;
            }
        }
    }
    return null;
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


/* SEARCH - iisang search para sa Team, Customers at Archived */

function figurifyFilterUsers(rawValue) {
    var search = rawValue.toLowerCase().trim();

    document.querySelectorAll(".um-table-users .user-row").forEach(function (row) {
        var text = (row.dataset.search || "").toLowerCase();
        row.hidden = text.indexOf(search) === -1;
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