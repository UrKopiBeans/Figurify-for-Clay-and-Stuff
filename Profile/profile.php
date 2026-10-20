<?php

require_once __DIR__ . "/../helpers/upload_helper.php";
require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../helpers/avatar_helper.php";
require_once __DIR__ . "/../Admin/database.php";


// must be logged in

if (!isset($_SESSION["user_id"])) {

    header("Location: ../Login/Login.php");
    exit();

}

$user_id = $_SESSION["user_id"];

$profileError  = "";
$passwordError = "";
$avatarError   = "";

// base path para tama ang avatar src kahit saan i-include ang page
$documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
$projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
$siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";


// CSRF - token galing sa Shared/all-in-one.php
require_once __DIR__ . "/../helpers/csrf_helper.php";

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


    // update full name

    if ($formAction === "update_profile") {

        $full_name = trim($_POST["full_name"] ?? "");

        if ($full_name === "") {

            $profileError = "Full name cannot be empty.";

        } elseif (mb_strlen($full_name) > 100) {

            $profileError = "Full name is too long.";

        } else {

            $stmt = $conn->prepare(
                "UPDATE users
                 SET full_name = ?
                 WHERE user_id = ?"
            );

            $stmt->bind_param("si", $full_name, $user_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION["full_name"] = $full_name;

            header("Location: profile.php?updated=1");
            exit();

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
                require_once __DIR__ . "/../helpers/remember_helper.php";
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

}


// get current user info

$stmt = $conn->prepare(
    "SELECT full_name, email, role, created_at, profile_picture
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile — Clay and Stuff</title>


    <link rel="stylesheet" href="profile.css?v=3">
</head>
<body>

<?php include "../Shared/navbar.php"; ?>

<header class="page-hero">
    <div class="page-wrap">
        <span class="page-eyebrow">YOUR ACCOUNT</span>
        <h1>Profile</h1>
        <p>View your account details, update your name, or change your password.</p>
    </div>
</header>

<main class="profile-page page-wrap">

    <?php if (isset($_GET["updated"])) { echo figurify_toast_now("Profile updated.", "success"); } ?>
    <?php if (isset($_GET["pw_updated"])) { echo figurify_toast_now("Password changed.", "success"); } ?>
    <?php if (isset($_GET["avatar_updated"])) { echo figurify_toast_now("Profile photo updated.", "success"); } ?>

    <script>
    /* tanggalin ang ?updated=1 atbp. para hindi lumabas ulit ang pop up pag ni-reload */
    (function () {
        if (!window.history || !window.history.replaceState) { return; }
        var url = new URL(window.location.href), changed = false;
        ["updated", "pw_updated", "avatar_updated"].forEach(function (k) {
            if (url.searchParams.has(k)) { url.searchParams.delete(k); changed = true; }
        });
        if (changed) { window.history.replaceState({}, document.title, url.toString()); }
    })();
    </script>

    <div class="profile-layout">

        <!-- profile summary -->
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

                <label for="avatar_photo" class="profile-avatar-upload-btn">Change Photo</label>

                <input
                    type="file"
                    id="avatar_photo"
                    name="avatar_photo"
                    accept=".jpg,.jpeg,.png,.gif,.webp"
                    onchange="this.form.submit()"
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

        <!-- edit forms -->
        <div class="profile-forms-col">

            <!-- EDIT NAME / EMAIL -->
            <div class="profile-card">

                <h2 class="profile-form-title">Account Information</h2>

                <?php if ($profileError !== "") { echo figurify_toast_now($profileError, "error"); } ?>

                <form method="POST" action="profile.php" class="profile-form">

                    <input type="hidden" name="form_action" value="update_profile">

                    <div class="field">
                        <label for="full_name">Full Name</label>
                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?php echo htmlspecialchars($user["full_name"]); ?>"
                            required
                            maxlength="100"
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

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>

                </form>

            </div>

            <!-- CHANGE PASSWORD -->
            <div class="profile-card">

                <h2 class="profile-form-title">Change Password</h2>

                <?php if ($passwordError !== "") { echo figurify_toast_now($passwordError, "error"); } ?>

                <form method="POST" action="profile.php" class="profile-form">

                    <input type="hidden" name="form_action" value="change_password">

                    <div class="field-row-3">

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
                        <button type="submit" class="btn-outline">Update Password</button>
                    </div>

                </form>

            </div>

        </div>

    </div>

</main>

<?php include "../Shared/footer.php"; ?>

</body>
</html>
