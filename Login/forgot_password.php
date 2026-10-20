<?php
/* "Forgot password?" endpoint ng Shared/auth-modal.php. 3 actions
   (POST): send (padala 6-digit code via email), verify (check code,
   i-mark verified sa sariling session key), reset (palitan password,
   dapat na-verify na). Laging JSON ang response. */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../helpers/csrf_helper.php";

date_default_timezone_set("Asia/Manila");

require_once __DIR__ . "/../Admin/database.php";
require_once __DIR__ . "/../helpers/mailer_helper.php";
require_once __DIR__ . "/../helpers/resend_cooldown_helper.php";

function fp_respond($status, $message = "", $retryAfter = 0) {
    header("Content-Type: application/json");
    $payload = [
        "status" => $status,
        "message" => $message,
    ];

    if ($retryAfter > 0) {
        $payload["retry_after"] = $retryAfter;
    }

    echo json_encode($payload);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    fp_respond("error", "Invalid request.");
}

if (!figurify_verify_csrf($_POST["csrf_token"] ?? null)) {
    fp_respond("error", "Your session has expired. Please refresh the page and try again.");
}

$action = $_POST["action"] ?? "";

/* SEND - 6-digit code, pero dapat may account na ang email
   (kabaligtaran ng signup's send_code.php). */
if ($action === "send") {

    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fp_respond("error", "Please enter a valid email address.");
    }

    require_once __DIR__ . "/../helpers/ban_helper.php";
    require_once __DIR__ . "/../helpers/rate_limit_helper.php";

    if (figurify_is_email_banned($conn, $email)) {
        $conn->close();
        fp_respond("error", figurify_ban_message());
    }

    /* max 5 reset code kada IP sa isang oras */
    if (figurify_action_rate_limit_seconds_remaining($conn, "reset_code_ip", figurify_client_ip(), 5, 60) > 0) {
        $conn->close();
        fp_respond("error", "Too many requests from this device. Please try again later.");
    }

    figurify_record_action_attempt($conn, "reset_code_ip", figurify_client_ip());

    $check = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows !== 1) {
        $check->close();
        $conn->close();
        fp_respond("error", "No account found with that email.");
    }

    $user = $result->fetch_assoc();
    $check->close();

    $cooldownRemaining = figurify_resend_cooldown_seconds_remaining($conn, $email);

    if ($cooldownRemaining > 0) {
        $conn->close();
        fp_respond("cooldown", "Please wait before requesting another code.", $cooldownRemaining);
    }

    $code = str_pad(random_int(0, 999999), 6, "0", STR_PAD_LEFT);
    $expires = date("Y-m-d H:i:s", time() + (5 * 60));

    $delete = $conn->prepare("DELETE FROM email_verifications WHERE email = ?");
    $delete->bind_param("s", $email);
    $delete->execute();
    $delete->close();

    $stmt = $conn->prepare(
        "INSERT INTO email_verifications (name, email, code, expires_at)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("ssss", $user["full_name"], $email, $code, $expires);

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        fp_respond("error", "Failed to create verification code.");
    }
    $stmt->close();

    require_once __DIR__ . "/../helpers/email_template_helper.php";

    $codeParas = [
        "We received a request to reset the password of your Clay and Stuff account. Please use the code below to continue.",
        "<strong style=\"font-size:22px;letter-spacing:6px;\">" . $code . "</strong>",
        "This code will expire in <strong>5 minutes</strong>.",
        "If you did not request a password reset, you can safely ignore this email. Your password will not be changed.",
    ];

    $emailBody = figurify_email_plain_layout((string) $user["full_name"], $codeParas);
    $altBody   = figurify_email_plain_text((string) $user["full_name"], $codeParas);

    $sent = figurify_send_mail(
        $email,
        $user["full_name"],
        "Your Clay and Stuff password reset code",
        $emailBody,
        $altBody
    );

    $conn->close();

    if ($sent) {
        fp_respond("success", "A verification code has been sent to your email.");
    } else {
        fp_respond("error", "Email could not be sent. Please try again.");
    }
}

/* VERIFY - same checks as verify_code.php, hiwalay na session key
   (pw_reset_verified_email) para hindi magkasalubong sa signup flow. */
if ($action === "verify") {

    $email = trim($_POST["email"] ?? "");
    $code = trim($_POST["code"] ?? "");

    if (empty($email) || empty($code)) {
        fp_respond("error", "Please enter the verification code.");
    }

    /* 5 maling code kada 10 minuto lang, para hindi mahulaan ng bot */
    require_once __DIR__ . "/../helpers/rate_limit_helper.php";

    if (figurify_action_rate_limit_seconds_remaining($conn, "reset_verify", strtolower($email), 5, 10) > 0) {
        $conn->close();
        fp_respond("error", "Too many wrong codes. Please wait a few minutes and request a new code.");
    }

    $stmt = $conn->prepare(
        "SELECT id FROM email_verifications
         WHERE email = ? AND code = ? AND expires_at > NOW() AND verified = 0
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $row = $result->fetch_assoc();

        $update = $conn->prepare("UPDATE email_verifications SET verified = 1 WHERE id = ?");
        $update->bind_param("i", $row["id"]);
        $update->execute();
        $update->close();

        $_SESSION["pw_reset_verified_email"] = $email;

        $stmt->close();
        $conn->close();
        fp_respond("success");

    } else {

        figurify_record_action_attempt($conn, "reset_verify", strtolower($email));

        $stmt->close();
        $conn->close();
        fp_respond("error", "Invalid or expired verification code.");
    }
}

/* RESET - dapat na-verify muna ang email sa parehong session. */
if ($action === "reset") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    $verifiedEmail = $_SESSION["pw_reset_verified_email"] ?? "";

    if (empty($verifiedEmail) || strtolower($verifiedEmail) !== strtolower($email)) {
        fp_respond("error", "Please verify your email first.");
    }

    if (strlen($password) < 8) {
        fp_respond("error", "Password must be at least 8 characters.");
    }

    if ($password !== $confirmPassword) {
        fp_respond("error", "Passwords do not match.");
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashed, $email);

    if ($stmt->execute()) {

        $stmt->close();

        unset($_SESSION["pw_reset_verified_email"]);

        /* bagong password = burahin ang lahat ng "Remember me" ng account na ito */
        require_once __DIR__ . "/../helpers/remember_helper.php";
        $rmStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $rmStmt->bind_param("s", $email);
        $rmStmt->execute();
        $rmRow = $rmStmt->get_result()->fetch_assoc();
        $rmStmt->close();
        if ($rmRow) {
            figurify_remember_forget_user($conn, (int) $rmRow["user_id"]);
        }

        $del = $conn->prepare("DELETE FROM email_verifications WHERE email = ?");
        $del->bind_param("s", $email);
        $del->execute();
        $del->close();

        $conn->close();
        fp_respond("success", "Your password has been reset. You can now log in.");

    } else {

        $stmt->close();
        $conn->close();
        fp_respond("error", "Something went wrong. Please try again.");
    }
}

fp_respond("error", "Invalid request.");
