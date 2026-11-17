<?php
/* ==========================================================================
   PROFILE PAGE   (Profile/profile.php)
   Style: Profile/profile.css      Script: nasa dulo ng file na ito ([4] SCRIPT)

   LAMAN (taas pababa):
     [1] SETUP       helpers, session, database, dapat naka-login
     [2] FORM SAVE   Change Photo, Account Information, Change Password,
                     Shipping & Refund Details (POST)
     [3] DATA        info ng user + naka-save na Shipping/Refund details
     [4] HTML        navbar, page hero, summary card, forms, footer, script
   ========================================================================== */


/* ==========================================================================
   [1] SETUP
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../helpers/content_helper.php";
require_once __DIR__ . "/../helpers/saved_details_helper.php";
require_once __DIR__ . "/../Admin/database.php";

// dapat naka-login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../Login/Login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$profileError  = "";
$passwordError = "";
$avatarError   = "";
$detailsError  = "";

// base path para tama ang avatar src kahit saan i-include ang page
$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";
/* ====================== END SETUP ====================== */


/* ==========================================================================
   [2] FORM SAVE  (POST galing sa 3 form ng page)
   ========================================================================== */

// CSRF - token galing sa Shared/popup-and-security.php

if ($_SERVER["REQUEST_METHOD"] === "POST" && !figurify_verify_csrf($_POST["csrf_token"] ?? null)) {
    $profileError = "Your session expired. Please try again.";
    $_POST = [];
    $_FILES = [];
}

// handle form submissions

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST)) {

    $formAction = $_POST["form_action"] ?? "";


    // upload / change profile photo

    if ($formAction === "upload_avatar") {

        $allowedExt  = ["jpg", "jpeg", "png", "gif", "webp"];
        $maxFileSize = 3 * 1024 * 1024; // 3MB

        if (!isset($_FILES["avatar_photo"]) || $_FILES["avatar_photo"]["error"] === UPLOAD_ERR_NO_FILE) {

            $avatarError = "Please choose a photo to upload.";

        } elseif ($_FILES["avatar_photo"]["error"] !== UPLOAD_ERR_OK) {

            $avatarError = "There was a problem uploading your photo. Please try again.";

        } elseif ($_FILES["avatar_photo"]["size"] > $maxFileSize) {

            $avatarError = "Photo is too large. Please choose a file under 3MB.";

        } else {

            $originalName = $_FILES["avatar_photo"]["name"];
            $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true) || !figurify_is_real_image($_FILES["avatar_photo"]["tmp_name"], $maxFileSize)) {

                $avatarError = "Unsupported file type. Please upload a JPG, PNG, GIF, or WEBP image.";

            } else {

                $uploadDir = __DIR__ . "/../uploads/avatars/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $safeName = "user-" . $user_id . "-" . time() . "." . $ext;
                $destPath = $uploadDir . $safeName;

                if (move_uploaded_file($_FILES["avatar_photo"]["tmp_name"], $destPath)) {

                    $relativePath = "uploads/avatars/" . $safeName;

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET profile_picture = ?
                         WHERE user_id = ?"
                    );

                    $stmt->bind_param("si", $relativePath, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION["profile_picture"] = $relativePath;

                    header("Location: profile.php?avatar_updated=1");
                    exit();

                } else {

                    $avatarError = "Could not save the uploaded photo. Please try again.";

                }

            }

        }

    }


    // Save Details = pangalan + Shipping & Refund Details sa isang button

    if ($formAction === "save_details") {

        $first_name  = trim($_POST["first_name"] ?? "");
        $middle_name = trim($_POST["middle_name"] ?? "");
        $last_name   = trim($_POST["last_name"] ?? "");

        if ($first_name === "" || $last_name === "") {

            $profileError = "First name and last name cannot be empty.";

        } elseif (mb_strlen($first_name) > 50 || mb_strlen($middle_name) > 50 || mb_strlen($last_name) > 50) {

            $profileError = "Name is too long.";

        } else {

            $stmt = $conn->prepare(
                "UPDATE users
                 SET first_name = ?, middle_name = NULLIF(?, ''), last_name = ?
                 WHERE user_id = ?"
            );

            $stmt->bind_param("sssi", $first_name, $middle_name, $last_name, $user_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION["full_name"] = figurify_full_name($first_name, $middle_name, $last_name);

        }

    }


    // change password

    if ($formAction === "change_password") {

        $current_password = $_POST["current_password"] ?? "";
        $new_password      = $_POST["new_password"] ?? "";
        $confirm_password  = $_POST["confirm_password"] ?? "";

        if ($current_password === "" || $new_password === "" || $confirm_password === "") {

            $passwordError = "Please fill out all password fields.";

        } elseif (strlen($new_password) < 8) {

            $passwordError = "New password must be at least 8 characters.";

        } elseif ($new_password !== $confirm_password) {

            $passwordError = "New passwords do not match.";

        } else {

            $stmt = $conn->prepare(
                "SELECT password
                 FROM users
                 WHERE user_id = ?
                 LIMIT 1"
            );

            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $row    = $result->fetch_assoc();

            $stmt->close();

            if (!$row || !password_verify($current_password, $row["password"])) {

                $passwordError = "Current password is incorrect.";

            } else {

                $hashed = password_hash($new_password, PASSWORD_DEFAULT);

                $updateStmt = $conn->prepare(
                    "UPDATE users
                     SET password = ?
                     WHERE user_id = ?"
                );

                $updateStmt->bind_param("si", $hashed, $user_id);
                $updateStmt->execute();
                $updateStmt->close();

                /* bagong password: burahin ang Remember me sa ibang device,
                   pero manatiling naka-remember sa device na ito */
                $rememberedHere = !empty($_COOKIE[figurify_remember_cookie_name("customer")]);
                figurify_remember_forget_user($conn, (int) $user_id);
                if ($rememberedHere) {
                    figurify_remember_issue($conn, (int) $user_id, "customer");
                }

                header("Location: profile.php?pw_updated=1");
                exit();

            }

        }

    }


    // Shipping & Refund Details - ito rin ang auto-fill ng Payment page (helpers/saved_details_helper.php)

    if ($formAction === "save_details" && $profileError === "") {

        $details = figurify_saved_details_from_post($_POST, false);

        if ($details["error"] !== "") {

            $detailsError = $details["error"];

        } else {

            figurify_saved_details_save($conn, (int) $user_id, $details["data"]);

            header("Location: profile.php?details_updated=1");
            exit();

        }

    }

}


/* ====================== END FORM SAVE ====================== */


/* ==========================================================================
   [3] DATA
   ========================================================================== */

// info ng user

$stmt = $conn->prepare(
    "SELECT first_name, middle_name, last_name, full_name, email, role, created_at, profile_picture
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user   = $result->fetch_assoc();

$stmt->close();

/* I-sync ang session sa DB para pareho ang picture/pangalan sa navbar at sa page na ito */
if ($user) {
    $_SESSION["profile_picture"] = $user["profile_picture"];
    $_SESSION["full_name"]       = $user["full_name"];
}

// naka-save na Shipping & Refund details (galing dito o sa huling Payment)
$saved = figurify_saved_details_get($conn, (int) $user_id);

// may error sa pag-save: ipakita ulit ang tinype ng customer
if ($detailsError !== "" || ($profileError !== "" && ($_POST["form_action"] ?? "") === "save_details")) {
    $saved = figurify_saved_details_from_post($_POST, false)["data"];
}

$savedMethodIsOther = $saved["refund_method"] !== "" && !in_array($saved["refund_method"], ["gcash", "bpi"], true);
$conn->close();

if (!$user) {

    header("Location: ../Login/Login.php");
    exit();

}

$roleLabels = [
    "customer" => "Customer",
    "staff"    => "Staff",
    "admin"    => "Owner / Admin",
];

$roleLabel  = $roleLabels[$user["role"]] ?? ucfirst($user["role"]);
$memberSince = date("M Y", strtotime($user["created_at"]));

$profile_avatar_html = figurify_render_avatar(
    $user["full_name"],
    $user["profile_picture"],
    "Profile",
    $siteBase
);

/* ====================== END DATA ====================== */


/* ==========================================================================
   [4] HTML
   ========================================================================== */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile | Clay and Stuff</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="profile.css">
</head>

<body>

<!-- ====================== NAVBAR (Shared/navbar.php) ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>
<!-- ====================== END NAVBAR ====================== -->



<!-- ====================== PAGE HERO (css: profile.css [2]) ====================== -->
<header class="page-hero">
    <div class="page-wrap">
        <span class="page-eyebrow">YOUR ACCOUNT</span>
        <h1>Profile</h1>
        <p>View your account details, update your name, change your password, or save your shipping details.</p>
    </div>
</header>
<!-- ====================== END PAGE HERO ====================== -->


<!-- ====================== PROFILE (css: profile.css [3]-[5]) ====================== -->
<main class="profile-page page-wrap">

    <?php if (isset($_GET["updated"])) { echo figurify_toast_now("Profile updated.", "success"); } ?>
    <?php if (isset($_GET["pw_updated"])) { echo figurify_toast_now("Password changed.", "success"); } ?>
    <?php if (isset($_GET["avatar_updated"])) { echo figurify_toast_now("Profile photo updated.", "success"); } ?>
    <?php if (isset($_GET["details_updated"])) { echo figurify_toast_now("Details saved.", "success"); } ?>

    <div class="profile-layout">

        <!-- ====================== SUMMARY CARD (css: profile.css [4] | js: [4] SCRIPT) ====================== -->
        <div class="profile-card profile-summary-card">

            <div class="profile-avatar">
                <?php echo $profile_avatar_html; ?>
            </div>

            <?php if ($avatarError !== "") { echo figurify_toast_now($avatarError, "error"); } ?>

            <form
                method="POST"
                action="profile.php"
                enctype="multipart/form-data"
                class="profile-avatar-form"
            >
                <input type="hidden" name="form_action" value="upload_avatar">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(figurify_csrf_token(), ENT_QUOTES, "UTF-8"); ?>">

                <label for="avatar_photo" class="profile-avatar-upload-btn">Change Photo</label>

                <input
                    type="file"
                    id="avatar_photo"
                    name="avatar_photo"
                    accept=".jpg,.jpeg,.png,.gif,.webp"
                    hidden
                >
            </form>

            <?php if ($user["role"] !== "customer"): ?>
                <span class="profile-role-badge"><?php echo htmlspecialchars($roleLabel); ?></span>
            <?php endif; ?>

            <div class="profile-member-since">
                Member since <?php echo htmlspecialchars($memberSince); ?>
            </div>

        </div>

        <!-- ====================== END SUMMARY CARD ====================== -->

        <!-- ====================== FORMS (css: profile.css [5]) - katabi ng summary card ====================== -->

            <!-- EDIT NAME / EMAIL -->
            <div class="profile-card">

                <h2 class="profile-form-title">Account Information</h2>

                <?php if ($profileError !== "") { echo figurify_toast_now($profileError, "error"); } ?>

                <div class="profile-form">

                    <div class="field">
                        <label for="first_name">First Name</label>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            form="profileDetailsForm"
                            value="<?php echo htmlspecialchars($user["first_name"]); ?>"
                            required
                            maxlength="50"
                        >
                    </div>

                    <div class="field">
                        <label for="middle_name">Middle Name (optional)</label>
                        <input
                            type="text"
                            id="middle_name"
                            name="middle_name"
                            form="profileDetailsForm"
                            value="<?php echo htmlspecialchars((string) $user["middle_name"]); ?>"
                            maxlength="50"
                        >
                    </div>

                    <div class="field">
                        <label for="last_name">Last Name</label>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            form="profileDetailsForm"
                            value="<?php echo htmlspecialchars($user["last_name"]); ?>"
                            required
                            maxlength="50"
                        >
                    </div>

                    <div class="field">
                        <label for="email_display">Email</label>
                        <input
                            type="email"
                            id="email_display"
                            value="<?php echo htmlspecialchars($user["email"]); ?>"
                            disabled
                        >
                        <span class="profile-field-hint">
                            Email can't be changed here. Contact us if you need to update it.
                        </span>
                    </div>

                </div>

            </div>

            <!-- CHANGE PASSWORD -->
            <div class="profile-card">

                <h2 class="profile-form-title">Change Password</h2>

                <?php if ($passwordError !== "") { echo figurify_toast_now($passwordError, "error"); } ?>

                <form method="POST" action="profile.php" class="profile-form">

                    <input type="hidden" name="form_action" value="change_password">

                    <div class="profile-password-fields">

                        <div class="field">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="field">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="8">
                        </div>

                        <div class="field">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Save Password</button>
                    </div>

                </form>

            </div>

            <!-- SHIPPING & REFUND DETAILS (buong lapad sa ilalim) - auto-fill ng Payment page -->
            <div class="profile-card profile-details-card">

                <h2 class="profile-form-title">Shipping &amp; Refund Details</h2>

                <?php if ($detailsError !== "") { echo figurify_toast_now($detailsError, "error"); } ?>

                <form method="POST" action="profile.php" class="profile-form" id="profileDetailsForm">

                    <input type="hidden" name="form_action" value="save_details">

                    <p class="profile-field-hint">Saved automatically from your last payment. Edit them here so your next order is filled in for you.</p>

                    <div class="profile-details-grid">

                        <div class="profile-details-col">

                            <h3 class="profile-details-subtitle">Shipping</h3>

                            <div class="field">
                                <label for="shipping_address_line1">Full Address</label>
                                <input type="text" id="shipping_address_line1" name="shipping_address_line1" maxlength="150" placeholder="House No. / Building / Street / Brgy" value="<?php echo e($saved["address_line1"]); ?>">
                                <input type="text" name="shipping_address_line2" maxlength="150" placeholder="City / Province" aria-label="City / Province" value="<?php echo e($saved["address_line2"]); ?>">
                            </div>

                            <div class="field">
                                <label for="pin_address">Pin Address / Google Maps Link (Optional)</label>
                                <input type="text" id="pin_address" name="pin_address" maxlength="255" placeholder="Paste Google Maps pin link or landmark" value="<?php echo e($saved["pin_address"]); ?>">
                            </div>

                            <div class="field">
                                <label for="shipping_contact">Contact Number</label>
                                <input type="text" id="shipping_contact" name="shipping_contact" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" placeholder="e.g. 09171234567" data-digits-only value="<?php echo e($saved["shipping_contact"]); ?>">
                            </div>

                            <div class="field">
                                <label for="courier">Courier</label>
                                <select id="courier" name="courier">
                                    <option value="">Select courier</option>
                                    <option value="lbc"<?php echo $saved["courier"] === "lbc" ? " selected" : ""; ?>>LBC</option>
                                    <option value="lalamove"<?php echo $saved["courier"] === "lalamove" ? " selected" : ""; ?>>Lalamove</option>
                                </select>
                            </div>

                        </div>

                        <div class="profile-details-col">

                            <h3 class="profile-details-subtitle">Refund / Return</h3>

                            <div class="field">
                                <label for="refund_account_name">Account Name</label>
                                <input type="text" id="refund_account_name" name="refund_account_name" maxlength="150" value="<?php echo e($saved["refund_account_name"]); ?>">
                            </div>

                            <div class="field">
                                <label for="refund_account_number">Account Number / E-Wallet Number</label>
                                <input type="text" id="refund_account_number" name="refund_account_number" inputmode="numeric" pattern="[0-9]+" placeholder="e.g. 09171234567" data-digits-only value="<?php echo e($saved["refund_account_number"]); ?>">
                            </div>

                            <div class="field">
                                <label for="refund_method">Preferred Bank / Method</label>
                                <select id="refund_method" name="refund_method">
                                    <option value="">Select method</option>
                                    <option value="gcash"<?php echo $saved["refund_method"] === "gcash" ? " selected" : ""; ?>>GCash</option>
                                    <option value="bpi"<?php echo $saved["refund_method"] === "bpi" ? " selected" : ""; ?>>BPI</option>
                                    <option value="other"<?php echo $savedMethodIsOther ? " selected" : ""; ?>>Other</option>
                                </select>
                                <input type="text" id="refund_method_other" name="refund_method_other" maxlength="50" placeholder="Please specify bank/method (Other)" aria-label="Other bank / method" value="<?php echo $savedMethodIsOther ? e($saved["refund_method"]) : ""; ?>"<?php echo $savedMethodIsOther ? "" : " hidden"; ?>>
                            </div>

                        </div>

                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Save Details</button>
                    </div>

                </form>

            </div>

        <!-- ====================== END FORMS ====================== -->

    </div>

</main>
<!-- ====================== END PROFILE ====================== -->


<!-- ====================== FOOTER (Shared/footer.php) ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->


<!-- ====================== [4] SCRIPT ====================== -->
<script>
(function () {

    /* ---------- Change Photo: i-submit agad pagkapili ng larawan ---------- */
    document.getElementById("avatar_photo").addEventListener("change", function () {
        this.form.submit();
    });

    /* ---------- Refund method: "Other" field lang kapag Other ---------- */
    const refundMethod = document.getElementById("refund_method");
    const refundOther  = document.getElementById("refund_method_other");

    refundMethod.addEventListener("change", function () {
        refundOther.hidden = refundMethod.value !== "other";
        refundOther.required = !refundOther.hidden;
        if (refundOther.hidden) {
            refundOther.value = "";
        }
    });

    /* ---------- tanggalin ang ?updated=1 atbp. para hindi lumabas ulit ang pop up pag ni-reload ---------- */
    if (!window.history || !window.history.replaceState) {
        return;
    }

    const url = new URL(window.location.href);
    let changed = false;

    ["updated", "pw_updated", "avatar_updated", "details_updated"].forEach(function (key) {
        if (url.searchParams.has(key)) {
            url.searchParams.delete(key);
            changed = true;
        }
    });

    if (changed) {
        window.history.replaceState({}, document.title, url.toString());
    }

})();
</script>
<!-- ====================== END SCRIPT ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
