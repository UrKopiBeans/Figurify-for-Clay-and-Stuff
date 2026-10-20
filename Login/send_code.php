<?php

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

require_once __DIR__ . "/../helpers/csrf_helper.php";

date_default_timezone_set("Asia/Manila");

require_once "../Admin/database.php";
require_once __DIR__ . "/../helpers/mailer_helper.php";
require_once __DIR__ . "/../helpers/resend_cooldown_helper.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Invalid request.");
}


if (!figurify_verify_csrf($_POST["csrf_token"] ?? null)) {
    exit("Your session has expired. Please refresh the page and try again.");
}


$name = trim($_POST["full_name"] ?? "");
$email = trim($_POST["email"] ?? "");


if (empty($name) || empty($email)) {
    exit("Please enter your name and Gmail.");
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Please enter a valid email address.");
}

/* max 5 code kada IP sa isang oras, para hindi makapag-spam ng email ang bot */
require_once __DIR__ . "/../helpers/rate_limit_helper.php";

if (figurify_action_rate_limit_seconds_remaining($conn, "send_code_ip", figurify_client_ip(), 5, 60) > 0) {
    exit("Too many sign up attempts from this device. Please try again later.");
}

require_once __DIR__ . "/../helpers/ban_helper.php";

if (figurify_is_email_banned($conn, $email)) {
    exit("This email has been banned and can't be used to sign up.");
}


/* Check if email already exists */

$check = $conn->prepare(
    "SELECT user_id
     FROM users
     WHERE email = ?"
);

$check->bind_param("s", $email);
$check->execute();
$check->store_result();


if ($check->num_rows > 0) {

    $check->close();

    exit("This email is already registered.");
}


$check->close();


/* Resend cooldown - 10 minutes bago puwede ulit magpadala ng bagong
    code sa parehong email */

$cooldownRemaining = figurify_resend_cooldown_seconds_remaining($conn, $email);

if ($cooldownRemaining > 0) {
    exit("COOLDOWN|" . $cooldownRemaining . "|Please wait before requesting another code.");
}


figurify_record_action_attempt($conn, "send_code_ip", figurify_client_ip());

/* Generate 6-digit verification code */

$code = str_pad(
    random_int(0, 999999),
    6,
    "0",
    STR_PAD_LEFT
);


/* Code expires after 5 minutes */

$expires = date(
    "Y-m-d H:i:s",
    time() + (5 * 60)
);


/* Delete previous code */

$delete = $conn->prepare(
    "DELETE FROM email_verifications
     WHERE email = ?"
);

$delete->bind_param("s", $email);
$delete->execute();
$delete->close();


/* Save new verification code */

$stmt = $conn->prepare(
    "INSERT INTO email_verifications
    (name, email, code, expires_at)
    VALUES (?, ?, ?, ?)"
);

$stmt->bind_param(
    "ssss",
    $name,
    $email,
    $code,
    $expires
);


if (!$stmt->execute()) {

    $stmt->close();
    $conn->close();

    exit("Failed to create verification code.");
}

$stmt->close();


/* Padala via Gmail gamit ang shared figurify_send_mail - Admin/mail_config.php ang credentials. */

require_once __DIR__ . "/../helpers/email_template_helper.php";

$codeParas = [
    "Thank you for signing up with Clay and Stuff! Please use the verification code below to verify your email address.",
    "<strong style=\"font-size:22px;letter-spacing:6px;\">" . $code . "</strong>",
    "This code will expire in <strong>5 minutes</strong>.",
    "If you did not create a Clay and Stuff account, you can safely ignore this email.",
];

$emailBody = figurify_email_plain_layout((string) $name, $codeParas);
$altBody   = figurify_email_plain_text((string) $name, $codeParas);

$sent = figurify_send_mail(
    $email,
    $name,
    "Your Clay and Stuff verification code",
    $emailBody,
    $altBody
);

echo $sent ? "success" : "Email could not be sent. Please try again.";

$conn->close();

?>