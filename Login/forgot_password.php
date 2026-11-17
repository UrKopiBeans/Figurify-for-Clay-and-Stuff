<?php
/* ==========================================================================
   FORGOT PASSWORD   (Login/forgot_password.php)
   Iisang file ng Forgot Password: PHP backend, style, HTML ng pop up, script.

   DALAWANG GAMIT:
     1. Direktang tinatawag (fetch ng pop up, POST na may "action")
        -> [PHP] BACKEND lang ang tatakbo, tapos exit. Laging JSON ang sagot.
           action = send    -> padala ng 6-digit code (dapat may account na ang email)
           action = verify  -> i-check ang code (sariling session key)
           action = reset   -> palitan ang password (dapat na-verify na)
     2. Ini-include ng Shared/navbar.php kapag hindi naka-login
        -> [STYLE] + [HTML] + [SCRIPT] ng Forgot Password pop up.

   Bumubukas ang pop up kapag kinlick ang kahit anong may data-auth-open="forgot"
   ("Forgot password?" link ng Log In).

   LAMAN (taas pababa):
     [PHP]     [1] BACKEND (send / verify / reset)  [2] POP UP SETUP
     [STYLE]   FORGOT PASSWORD POP UP
     [HTML]    FORGOT PASSWORD POP UP
     [SCRIPT]  [1] HELPERS  [2] VERIFICATION CODE  [3] BUKSAN / ISARA
               [4] SEND / VERIFY CODE  [5] RESET PASSWORD
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";


/* ==========================================================================
   [PHP] [1] BACKEND  (direktang tawag lang, hindi kapag ini-include)
   strcasecmp = hindi apektado ng laki/liit ng letra ng path (Windows / XAMPP)
   ========================================================================== */
if (strcasecmp((string) realpath($_SERVER["SCRIPT_FILENAME"]), __FILE__) === 0) {

    figurify_start_session();
    date_default_timezone_set("Asia/Manila");

    require_once __DIR__ . "/../Admin/database.php";
    require_once __DIR__ . "/../helpers/messaging_helper.php";

    /* sagot sa pop up (JSON). retry_after = segundo ng natitirang cooldown */
    function forgot_respond(string $status, string $message = "", int $retryAfter = 0): void
    {
        header("Content-Type: application/json");

        $payload = [
            "status"  => $status,
            "message" => $message,
        ];

        if ($retryAfter > 0) {
            $payload["retry_after"] = $retryAfter;
        }

        echo json_encode($payload);
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        forgot_respond("error", "Invalid request.");
    }

    if (!figurify_verify_csrf($_POST["csrf_token"] ?? null)) {
        forgot_respond("error", "Your session has expired. Please refresh the page and try again.");
    }

    $action = $_POST["action"] ?? "";


    /* ---------- [1a] SEND - 6-digit code, pero dapat may account na ang email ---------- */
    if ($action === "send") {

        $email = trim($_POST["email"] ?? "");

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            forgot_respond("error", "Please enter a valid email address.");
        }

        if (figurify_is_email_banned($conn, $email)) {
            $conn->close();
            forgot_respond("error", figurify_ban_message());
        }

        // maluwag na bantay lang laban sa bot (30 kada IP sa isang oras). Ang totoong limit ay kada email, sa ibaba.
        if (figurify_action_rate_limit_seconds_remaining($conn, "reset_code_ip", figurify_client_ip(), 30, 60) > 0) {
            $conn->close();
            forgot_respond("error", "Too many requests from this network. Please try again later.");
        }

        figurify_record_action_attempt($conn, "reset_code_ip", figurify_client_ip());

        $check = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        $user   = $result->num_rows === 1 ? $result->fetch_assoc() : null;
        $check->close();

        if (!$user) {
            $conn->close();
            forgot_respond("error", "No account found with that email.");
        }

        // resend cooldown (5 minuto, FIGURIFY_RESEND_COOLDOWN_SECONDS) - may valid pang code
        $cooldownRemaining = figurify_resend_cooldown_seconds_remaining($conn, $email);

        if ($cooldownRemaining > 0) {
            $conn->close();
            forgot_respond("cooldown", "Please wait before requesting another code.", $cooldownRemaining);
        }

        // limit kada EMAIL: max 5 pindot ng Send Code sa loob ng 5 minuto, tapos hintay (hanggang 5 minuto)
        $emailKey  = strtolower($email);
        $emailWait = figurify_action_rate_limit_seconds_remaining($conn, "reset_code_email", $emailKey, 5, 5);

        if ($emailWait > 0) {
            $conn->close();
            forgot_respond("error", "Too many attempts for this email. Please try again in " . max(1, (int) ceil($emailWait / 60)) . " minute(s).");
        }

        figurify_record_action_attempt($conn, "reset_code_email", $emailKey);

        // bagong 6-digit code, mag-e-expire sa loob ng 5 minuto
        $code    = str_pad(random_int(0, 999999), 6, "0", STR_PAD_LEFT);
        $expires = date("Y-m-d H:i:s", time() + (5 * 60));

        // burahin ang lumang code ng email na ito
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
            forgot_respond("error", "Failed to create verification code.");
        }

        $stmt->close();

        // padala via Gmail (figurify_send_mail - Admin/mail_config.php ang credentials)
        $codeParagraphs = [
            "Your password reset code:",
            "<strong class=\"cs-mail-code\">" . $code . "</strong>",
            "It expires in <strong>5 minutes</strong>.",
            "Didn’t ask for this? Ignore this email.",
        ];

        $sent = figurify_send_mail(
            $email,
            $user["full_name"],
            "Your Clay and Stuff password reset code",
            figurify_email_plain_layout((string) $user["full_name"], $codeParagraphs),
            figurify_email_plain_text((string) $user["full_name"], $codeParagraphs)
        );

        // kapag hindi napadala ang email, burahin ang code para hindi mag-cooldown
        // (kung hindi, lalabas na may timer pero walang dumating na email)
        if (!$sent) {
            $undo = $conn->prepare("DELETE FROM email_verifications WHERE email = ?");
            $undo->bind_param("s", $email);
            $undo->execute();
            $undo->close();
        }

        $conn->close();

        if ($sent) {
            forgot_respond("success", "A verification code has been sent to your email.");
        }

        forgot_respond("error", "Email could not be sent. Please try again.");
    }


    /* ---------- [1b] VERIFY - i-check ang code ----------
       Hiwalay na session key (pw_reset_verified_email) para hindi magkasalubong sa Sign Up. */
    if ($action === "verify") {

        $email = trim($_POST["email"] ?? "");
        $code  = trim($_POST["code"] ?? "");

        if (empty($email) || empty($code)) {
            forgot_respond("error", "Please enter the verification code.");
        }

        // 5 maling code kada 10 minuto lang, para hindi mahulaan ng bot
        if (figurify_action_rate_limit_seconds_remaining($conn, "reset_verify", strtolower($email), 5, 10) > 0) {
            $conn->close();
            forgot_respond("error", "Too many wrong codes. Please wait a few minutes and request a new code.");
        }

        $stmt = $conn->prepare(
            "SELECT id FROM email_verifications
             WHERE email = ? AND code = ? AND expires_at > NOW() AND verified = 0
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param("ss", $email, $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->num_rows === 1 ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            figurify_record_action_attempt($conn, "reset_verify", strtolower($email));
            $conn->close();
            forgot_respond("error", "Invalid or expired verification code.");
        }

        // markahan na verified
        $update = $conn->prepare("UPDATE email_verifications SET verified = 1 WHERE id = ?");
        $update->bind_param("i", $row["id"]);
        $update->execute();
        $update->close();

        $_SESSION["pw_reset_verified_email"] = $email;

        $conn->close();
        forgot_respond("success");
    }


    /* ---------- [1c] RESET - dapat na-verify muna ang email sa parehong session ---------- */
    if ($action === "reset") {

        $email           = trim($_POST["email"] ?? "");
        $password        = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        $verifiedEmail = $_SESSION["pw_reset_verified_email"] ?? "";

        if (empty($verifiedEmail) || strtolower($verifiedEmail) !== strtolower($email)) {
            forgot_respond("error", "Please verify your email first.");
        }

        if (strlen($password) < 8) {
            forgot_respond("error", "Password must be at least 8 characters.");
        }

        if ($password !== $confirmPassword) {
            forgot_respond("error", "Passwords do not match.");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        $updated = $stmt->execute();
        $stmt->close();

        if (!$updated) {
            $conn->close();
            forgot_respond("error", "Something went wrong. Please try again.");
        }

        unset($_SESSION["pw_reset_verified_email"]);

        // bagong password = burahin ang lahat ng "Remember me" ng account na ito
        $userStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $userStmt->bind_param("s", $email);
        $userStmt->execute();
        $userRow = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();

        if ($userRow) {
            figurify_remember_forget_user($conn, (int) $userRow["user_id"]);
        }

        $delete = $conn->prepare("DELETE FROM email_verifications WHERE email = ?");
        $delete->bind_param("s", $email);
        $delete->execute();
        $delete->close();

        $conn->close();
        forgot_respond("success", "Your password has been reset. You can now log in.");
    }

    forgot_respond("error", "Invalid request.");
}
/* ====================== END BACKEND ====================== */


/* ==========================================================================
   [PHP] [2] POP UP SETUP  (kapag ini-include ng navbar)
   ========================================================================== */
// security token, kasama sa bawat submit
$forgotCsrfToken = figurify_csrf_token();

// mata ng password (show / hide) - pareho sa dalawang password field
$forgotEyeIcons = '<svg class="forgot-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>'
                . '<svg class="forgot-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
/* ====================== END POP UP SETUP ====================== */
?>

<style>
/* ==========================================================================
   [STYLE] FORGOT PASSWORD POP UP
   Lahat ay may forgot- prefix para hindi madamay ang page.
   ========================================================================== */

/* ---------- madilim na background + card ---------- */
.forgot-overlay {
    position: fixed;
    inset: 0;
    z-index: 3000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(24, 14, 22, .6);
    -webkit-backdrop-filter: blur(6px);
    backdrop-filter: blur(6px);
    opacity: 0;
    visibility: hidden;
    transition: opacity .22s ease, visibility .22s ease;
}

.forgot-overlay.active { opacity: 1; visibility: visible; }

/* hindi ma-scroll ang page habang bukas ang pop up */
body.forgot-popup-open { overflow: hidden; }

.forgot-modal {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 410px;
    max-height: calc(100vh - 40px);
    background: #fff;
    border: 1px solid #f1e4ec;
    border-radius: 28px;
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.18);
    transform: translateY(14px) scale(.97);
    transition: transform .22s ease;
}

.forgot-overlay.active .forgot-modal { transform: translateY(0) scale(1); }

/* lipat galing Log In: walang animation, parang iisang card lang */
.forgot-overlay.forgot-overlay-instant,
.forgot-overlay.forgot-overlay-instant .forgot-modal { transition: none; }

.forgot-modal * { box-sizing: border-box; }

/* ang laman lang ang nag-i-scroll; nakapirmi ang X button */
.forgot-scroll {
    overflow-y: auto;
    padding: 28px 28px 22px;
    scrollbar-width: thin;
    scrollbar-color: #e9d5e1 transparent;
}

/* itinatago ang step na hindi kasalukuyang ginagamit (script [2]) */
.forgot-modal .forgot-hidden { display: none; }

/* ---------- X button ---------- */
.forgot-close {
    position: absolute;
    z-index: 5;
    top: 16px;
    right: 16px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid #efdfe8;
    border-radius: 50%;
    background: #fff;
    color: #6e5767;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, border-color .15s ease;
}

.forgot-close:hover { background: #fbf3f7; border-color: #f1cfdd; color: #c0325e; }

.forgot-close svg {
    width: 15px;
    height: 15px;
    stroke: currentColor;
    stroke-width: 2.4;
    stroke-linecap: round;
}

/* ---------- logo + title + subtitle ---------- */
.forgot-header { margin-bottom: 20px; text-align: center; }

.forgot-logo {
    display: block;
    width: 56px;
    height: 44px;
    margin: 0 auto 10px;
    object-fit: cover;   /* tinatanggal ang puting gilid ng logo.png */
}

.forgot-header h2 {
    margin: 0 0 6px;
    font-family: 'Fraunces', Georgia, 'Times New Roman', serif;
    font-size: 25px;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -.2px;
    color: #43142f;
}

.forgot-header p { margin: 0; font-size: 13.5px; font-weight: 500; color: #6e5767; }

/* ---------- field: label sa loob ng box, umaakyat sa border kapag may laman ---------- */
.forgot-field { position: relative; margin-bottom: 14px; }

.forgot-field > label {
    position: absolute;
    z-index: 2;
    top: 22px;
    left: 42px;
    max-width: calc(100% - 64px);
    margin: 0;
    padding: 0 5px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    transform: translateY(-50%);
    border-radius: 4px;
    background: transparent;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.2;
    color: #8a7683;
    pointer-events: none;
    transition: top .18s ease, left .18s ease, font-size .18s ease, color .18s ease, background-color .18s ease;
}

/* label sa border: naka-focus o may laman */
.forgot-field:focus-within > label,
.forgot-field:has(input:not(:placeholder-shown)) > label {
    top: 0;
    left: 12px;
    font-size: 11.5px;
    font-weight: 700;
    background: #fff;
}
.forgot-field:has(input:not(:placeholder-shown)) > label { color: #2d1a27; }

/* autofill ng browser - hiwalay na rule para hindi madamay ang nasa itaas sa browser na hindi ito kilala */
.forgot-field:has(input:-webkit-autofill) > label {
    top: 0;
    left: 12px;
    font-size: 11.5px;
    font-weight: 700;
    background: #fff;
    color: #2d1a27;
}

/* naka-focus: crimson ang label (pangalawang selector = mas malakas sa "may laman" na rule) */
.forgot-field:focus-within > label,
.forgot-field:focus-within:has(input:not(:placeholder-shown)) > label { color: #c0325e; }

/* naka-lock na field (code bago mag-Send Code) */
.forgot-field:has(input:disabled):not(:focus-within) > label { color: #a58f9d; }

/* ---------- icon sa kaliwa ng box ---------- */
.forgot-icon {
    position: absolute;
    z-index: 2;
    top: 0;
    left: 14px;
    height: 44px;
    display: flex;
    align-items: center;
    color: #a0808f;
    pointer-events: none;
    transition: color .18s ease;
}

.forgot-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.forgot-field:focus-within .forgot-icon { color: #c0325e; }

/* ---------- input (normal at verification code) ---------- */
.forgot-input-wrap { position: relative; }

.forgot-input-wrap input,
.forgot-verify-row input {
    width: 100%;
    height: 44px;
    padding: 0 14px 0 42px;
    border: 1px solid #e9d5e1;
    border-radius: 12px;
    background: #fff;
    font-size: 16px;
    font-weight: 500;
    color: #2d1a27;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
}

.forgot-input-wrap input:focus,
.forgot-verify-row input:focus {
    border-color: #c0325e;
    box-shadow: 0 0 0 4px rgba(192, 50, 94, .12);
}

/* naka-lock na field (code bago mag-Send Code) */
.forgot-input-wrap input:disabled,
.forgot-verify-row input:disabled {
    background: #fbf7f9;
    border-color: #f0e5ec;
    color: #a58f9d;
    cursor: not-allowed;
}

/* ---------- show / hide password (mata) ---------- */
.forgot-has-eye input { padding-right: 44px; }

.forgot-input-wrap .forgot-eye {
    position: absolute;
    top: 50%;
    right: 8px;
    transform: translateY(-50%);
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: #8d7584;
    cursor: pointer;
    transition: color .15s ease, background .15s ease;
}

.forgot-input-wrap .forgot-eye:hover { color: #c0325e; background: #fbf3f7; }

.forgot-eye svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* bukas na mata = nakatago ang password; may .is-visible = nakikita */
.forgot-eye .forgot-eye-closed { display: none; }
.forgot-eye.is-visible .forgot-eye-open { display: none; }
.forgot-eye.is-visible .forgot-eye-closed { display: block; }

/* ---------- verification code (input + Send Code) ---------- */
.forgot-verify-row {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 10px;
}

.forgot-verify-row input:not(:placeholder-shown) { letter-spacing: 4px; font-weight: 700; }

.forgot-code-timer { margin: 8px 2px 0; font-size: 12.5px; font-weight: 600; color: #8a6a7c; }
.forgot-code-timer.is-expired { color: #c2410c; }

/* ---------- buttons ---------- */
.forgot-button {
    width: 100%;
    height: 46px;
    border: none;
    border-radius: 999px;
    background: #c0325e;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: .1px;
    cursor: pointer;
    transition: background .18s ease, opacity .18s ease;
}

.forgot-button:hover { background: #a82a52; }
.forgot-button:disabled { opacity: .55; cursor: not-allowed; }

/* Reset Password */
.forgot-modal .forgot-submit-button {
    display: block;
    margin: 16px 0 0;
    padding: 0 20px;
}

/* Send Code / Verify Code / Verified */
.forgot-modal .forgot-verify-button {
    width: auto;
    min-width: 96px;
    height: 44px;
    padding: 0 14px;
    border-radius: 999px;
    background: #fde6ee;
    color: #c0325e;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.forgot-modal .forgot-verify-button:hover { background: #fbd3e1; }

.forgot-modal .forgot-verify-button[data-mode="verified"] {
    background: #22a35a;
    color: #fff;
    opacity: 1;
}

/* ---------- "Remembered your password? Log in" ---------- */
.forgot-switch {
    margin-top: 16px;
    text-align: center;
    font-size: 13px;
    font-weight: 500;
    color: #6e5767;
}

.forgot-switch a {
    margin-left: 2px;
    color: #c0325e;
    font-weight: 700;
    text-decoration: none;
}

/* underline kapag tinapatan ang link */
.forgot-switch a:hover { text-decoration: underline; }

/* ---------- lipat ng step (pagbukas ng pop up, at code -> password) ----------
   naka-.active para tumakbo ang animation pagbukas, hindi habang nakatago */
.forgot-overlay.active .forgot-step { animation: forgotStepIn .34s ease both; }
.forgot-overlay.active .forgot-step.forgot-step-leaving { animation: forgotStepOut .18s ease both; }

@keyframes forgotStepIn {
    from { opacity: 0; transform: translateX(22px); }
    to   { opacity: 1; transform: translateX(0); }
}

@keyframes forgotStepOut {
    from { opacity: 1; transform: translateX(0); }
    to   { opacity: 0; transform: translateX(-22px); }
}

/* ---------- phone: mas maliit na pop up ---------- */
@media (max-width: 480px) {
    .forgot-modal { border-radius: 22px; }
    .forgot-scroll { padding: 26px 18px 20px; }
    .forgot-header h2 { font-size: 22px; }
}
/* ====================== END FORGOT PASSWORD POP UP (STYLE) ====================== */
</style>


<!-- ==========================================================================
     [HTML] FORGOT PASSWORD POP UP
     Step 1: email + code   ->   Step 2: bagong password
     Tinatawag ng script ang Login/forgot_password.php (ito ring file) gamit ang fetch.
     ========================================================================== -->
<div class="forgot-overlay" id="forgotOverlay" data-auth-popup aria-hidden="true">
    <div class="forgot-modal" role="dialog" aria-modal="true" aria-labelledby="forgotHeading">

        <!-- security token, kasama sa bawat submit -->
        <input type="hidden" id="forgotCsrfToken" data-auth-csrf value="<?php echo htmlspecialchars($forgotCsrfToken); ?>">

        <button type="button" class="forgot-close" id="forgotCloseButton" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>
        </button>

        <div class="forgot-scroll">
            <form id="forgotForm" autocomplete="off">

                <div class="forgot-header">
                    <img class="forgot-logo" src="../Image/brand/logo.png" alt="Clay and Stuff">
                    <h2 id="forgotHeading">Reset your password</h2>
                    <p>We'll send a verification code to your email.</p>
                </div>

                <!-- ====================== STEP 1: email + code ====================== -->
                <div class="forgot-step" id="forgotStep1">

                    <div class="forgot-field">
                        <label for="forgotEmail">Email</label>
                        <div class="forgot-input-wrap">
                            <span class="forgot-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg></span>
                            <input type="email" id="forgotEmail" name="forgot_email" placeholder=" " autocomplete="username" required>
                        </div>
                    </div>

                    <!-- iisang button: Send Code -> Verify Code -> Verified -->
                    <div class="forgot-field">
                        <label for="forgotCode">Verification code</label>
                        <div class="forgot-verify-row">
                            <span class="forgot-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 4.5 6v5.5c0 4.6 3.1 8 7.5 9.5 4.4-1.5 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2.2 2.2L15.5 10"/></svg></span>
                            <input type="text" id="forgotCode" maxlength="6" inputmode="numeric" placeholder=" " autocomplete="off" disabled>
                            <button type="button" id="forgotCodeButton" class="forgot-button forgot-verify-button" data-mode="send">Send Code</button>
                        </div>
                        <p class="forgot-code-timer" id="forgotCodeTimer" hidden></p>
                    </div>

                </div>
                <!-- ====================== END STEP 1 ====================== -->

                <!-- ====================== STEP 2: bagong password ====================== -->
                <div class="forgot-step forgot-hidden" id="forgotStep2">

                    <div class="forgot-field">
                        <label for="forgotNewPassword">New password</label>
                        <div class="forgot-input-wrap forgot-has-eye">
                            <span class="forgot-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></svg></span>
                            <input type="password" id="forgotNewPassword" name="new_password" placeholder=" " autocomplete="new-password">
                            <button type="button" class="forgot-eye" data-target="forgotNewPassword" aria-label="Show password"><?php echo $forgotEyeIcons; ?></button>
                        </div>
                    </div>

                    <div class="forgot-field">
                        <label for="forgotConfirmNewPassword">Confirm new password</label>
                        <div class="forgot-input-wrap forgot-has-eye">
                            <span class="forgot-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></svg></span>
                            <input type="password" id="forgotConfirmNewPassword" name="confirm_new_password" placeholder=" " autocomplete="new-password">
                            <button type="button" class="forgot-eye" data-target="forgotConfirmNewPassword" aria-label="Show password"><?php echo $forgotEyeIcons; ?></button>
                        </div>
                    </div>

                    <button type="submit" class="forgot-button forgot-submit-button" id="forgotResetButton" disabled>Reset Password</button>

                </div>
                <!-- ====================== END STEP 2 ====================== -->

                <!-- bubuksan ang Log In pop up (Login/Login.php) -->
                <div class="forgot-switch">
                    Remembered your password? <a href="#" data-auth-open="login">Log in</a>
                </div>

            </form>
        </div>

    </div>
</div>
<!-- ====================== END FORGOT PASSWORD POP UP (HTML) ====================== -->


<script>
(function () {

    const overlay     = document.getElementById("forgotOverlay");
    const scrollBox   = overlay.querySelector(".forgot-scroll");
    const form        = document.getElementById("forgotForm");
    const csrfInput   = document.getElementById("forgotCsrfToken");
    const step1       = document.getElementById("forgotStep1");
    const step2       = document.getElementById("forgotStep2");
    const emailInput  = document.getElementById("forgotEmail");
    const codeInput   = document.getElementById("forgotCode");
    const codeButton  = document.getElementById("forgotCodeButton");
    const codeTimer   = document.getElementById("forgotCodeTimer");
    const resetButton = document.getElementById("forgotResetButton");


    /* ==========================================================================
       [SCRIPT] [1] HELPERS
       ========================================================================== */

    // error pop up (csToast galing sa Shared/popup-and-security.php). Walang pop up kapag success.
    function showError(message) {
        if (window.csToast) {
            window.csToast(message, "error");
        }
    }

    function setButton(button, text, disabled) {
        button.textContent = text;
        button.disabled = disabled;
    }

    // bilang pababa kada segundo; onTick(secondsLeft) bawat segundo, onDone() pagdating ng 0
    function startCountdown(seconds, onTick, onDone) {
        let secondsLeft = seconds;
        onTick(secondsLeft);

        const intervalId = setInterval(function () {
            secondsLeft--;

            if (secondsLeft <= 0) {
                clearInterval(intervalId);
                onDone();
                return;
            }

            onTick(secondsLeft);
        }, 1000);

        return intervalId;
    }

    // padala sa backend (ito ring file); JSON ang sagot
    async function sendForgot(fields) {
        const formData = new FormData();

        Object.keys(fields).forEach(function (key) {
            formData.append(key, fields[key]);
        });
        formData.append("csrf_token", csrfInput.value);

        const response = await fetch("../Login/forgot_password.php", { method: "POST", body: formData });
        return response.json();
    }
    /* ====================== END HELPERS ====================== */


    /* ==========================================================================
       [SCRIPT] [2] VERIFICATION CODE  (timer, button, lipat ng step)
       Iisang button: data-mode="send" -> "verify" -> "verified".
       ========================================================================== */
    let codeTimerId = null;

    function stopCodeTimer() {
        clearInterval(codeTimerId);
        codeTimerId = null;

        codeTimer.hidden = true;
        codeTimer.textContent = "";
        codeTimer.classList.remove("is-expired");
    }

    // "Code expires in N seconds"; pag naubos, puwedeng mag-Resend Code
    function startCodeTimer(seconds) {
        stopCodeTimer();
        codeTimer.hidden = false;

        codeTimerId = startCountdown(
            seconds,
            function (secondsLeft) {
                codeTimer.classList.remove("is-expired");
                codeTimer.textContent = "Code expires in " + secondsLeft + (secondsLeft === 1 ? " second" : " seconds");
            },
            function () {
                codeTimerId = null;
                if (codeButton.getAttribute("data-mode") !== "verify") return;

                codeTimer.classList.add("is-expired");
                codeTimer.textContent = "Code expired. You can send a new code.";
                codeInput.value = "";
                codeInput.disabled = true;
                codeButton.setAttribute("data-mode", "send");
                setButton(codeButton, "Resend Code", false);
            }
        );
    }

    // naipadala ang code: buksan ang input at gawing "Verify Code" ang button
    function openCodeInput(seconds) {
        codeInput.disabled = false;
        codeInput.focus();
        codeButton.setAttribute("data-mode", "verify");
        setButton(codeButton, "Verify Code", false);
        startCodeTimer(seconds);
    }

    // tama ang code: i-lock ang input, "Verified" na ang button
    function markCodeVerified() {
        codeInput.disabled = true;
        codeButton.setAttribute("data-mode", "verified");
        stopCodeTimer();
        setButton(codeButton, "Verified", true);
    }

    // ibalik sa simula (step 1, "Send Code")
    function resetCodeStep() {
        stopCodeTimer();
        codeButton.setAttribute("data-mode", "send");
        setButton(codeButton, "Send Code", false);
        codeInput.disabled = true;
        step2.classList.add("forgot-hidden");
        step1.classList.remove("forgot-hidden");
    }

    // lipat ng step na may animation (step 1 palabas, step 2 papasok)
    function swapStep(fromStep, toStep) {
        fromStep.classList.add("forgot-step-leaving");

        setTimeout(function () {
            fromStep.classList.add("forgot-hidden");
            fromStep.classList.remove("forgot-step-leaving");
            toStep.classList.remove("forgot-hidden");
            scrollBox.scrollTop = 0;
        }, 180);
    }

    // Reset Password: bukas lang kapag verified na ang code
    function updateResetButtonState() {
        resetButton.disabled = codeButton.getAttribute("data-mode") !== "verified";
    }
    /* ====================== END VERIFICATION CODE ====================== */


    /* ==========================================================================
       [SCRIPT] [3] BUKSAN / ISARA
       Iisang event ("figurify:auth-popup") ang gamit ng Log In, Sign Up at
       Forgot Password: bubukas ang may tamang pangalan, sasara ang iba.
       switching = may bukas nang pop up -> lipat agad, walang animation.
       ========================================================================== */
    function setOpen(isOpen, instant) {
        if (instant) {
            overlay.classList.add("forgot-overlay-instant");
        }

        overlay.classList.toggle("active", isOpen);
        overlay.setAttribute("aria-hidden", isOpen ? "false" : "true");
        document.body.classList.toggle("forgot-popup-open", isOpen);

        if (instant) {
            void overlay.offsetWidth;   // ilapat agad bago ibalik ang animation
            overlay.classList.remove("forgot-overlay-instant");
        }
    }

    // "login" / "signup" / "forgot"
    function requestAuthPopup(name) {
        const switching = document.querySelector("[data-auth-popup].active") !== null;
        document.dispatchEvent(new CustomEvent("figurify:auth-popup", { detail: { name: name, switching: switching } }));
    }

    document.addEventListener("figurify:auth-popup", function (event) {
        if (event.detail.name === "forgot") {
            setOpen(true, event.detail.switching);
        } else if (overlay.classList.contains("active")) {
            setOpen(false, true);
        }
    });

    // kahit anong may data-auth-open="forgot" ("Forgot password?" link ng Log In)
    document.addEventListener("click", function (event) {
        const trigger = event.target.closest('[data-auth-open="forgot"]');
        if (!trigger) return;

        event.preventDefault();
        requestAuthPopup("forgot");
    });

    // X button lang ang nagsasara (hindi click sa labas o Escape)
    document.getElementById("forgotCloseButton").addEventListener("click", function () {
        setOpen(false, false);
    });

    // show / hide password
    overlay.addEventListener("click", function (event) {
        const eyeButton = event.target.closest(".forgot-eye");
        if (!eyeButton) return;

        const input = document.getElementById(eyeButton.getAttribute("data-target"));
        const nowVisible = input.type === "password";

        input.type = nowVisible ? "text" : "password";
        eyeButton.classList.toggle("is-visible", nowVisible);
        eyeButton.setAttribute("aria-label", nowVisible ? "Hide password" : "Show password");
    });
    /* ====================== END BUKSAN / ISARA ====================== */


    /* ==========================================================================
       [SCRIPT] [4] SEND / VERIFY CODE
       ========================================================================== */
    codeButton.addEventListener("click", async function () {
        const mode  = codeButton.getAttribute("data-mode");
        const email = emailInput.value.trim();

        if (mode === "send") {
            if (!email) {
                showError("Please enter your email.");
                return;
            }

            setButton(codeButton, "Sending...", true);

            try {
                const data = await sendForgot({ action: "send", email: email });

                if (data.status === "success") {
                    openCodeInput(300);
                } else if (data.status === "cooldown" && data.retry_after) {
                    // may valid pang code na naipadala na - ilagay na lang iyon
                    openCodeInput(data.retry_after);
                } else {
                    showError(data.message || "Unable to send verification code.");
                    setButton(codeButton, "Send Code", false);
                }
            } catch (error) {
                showError("Unable to send verification code.");
                setButton(codeButton, "Send Code", false);
            }

        } else if (mode === "verify") {
            const code = codeInput.value.trim();

            if (code.length !== 6) {
                showError("Please enter the 6-digit verification code.");
                return;
            }

            setButton(codeButton, "Verifying...", true);

            try {
                const data = await sendForgot({ action: "verify", email: email, code: code });

                if (data.status === "success") {
                    markCodeVerified();
                    updateResetButtonState();
                    swapStep(step1, step2);
                    setTimeout(function () { document.getElementById("forgotNewPassword").focus(); }, 260);
                } else {
                    showError(data.message || "Invalid or expired verification code.");
                    setButton(codeButton, "Verify Code", false);
                }
            } catch (error) {
                showError("Unable to verify the code.");
                setButton(codeButton, "Verify Code", false);
            }
        }
    });
    /* ====================== END SEND / VERIFY CODE ====================== */


    /* ==========================================================================
       [SCRIPT] [5] RESET PASSWORD
       ========================================================================== */
    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        const email           = emailInput.value.trim();
        const password        = document.getElementById("forgotNewPassword").value;
        const confirmPassword = document.getElementById("forgotConfirmNewPassword").value;

        if (codeButton.getAttribute("data-mode") !== "verified") {
            showError("Please verify your email first.");
            return;
        }

        if (!password || !confirmPassword) {
            showError("Please complete all fields.");
            return;
        }

        if (password.length < 8) {
            showError("Password must be at least 8 characters.");
            return;
        }

        if (password !== confirmPassword) {
            showError("Passwords do not match.");
            return;
        }

        try {
            const data = await sendForgot({
                action: "reset",
                email: email,
                password: password,
                confirm_password: confirmPassword
            });

            if (data.status === "success") {
                // balik sa simula ng Forgot Password, tapos lipat sa Log In
                setTimeout(function () {
                    form.reset();
                    resetCodeStep();
                    updateResetButtonState();
                    requestAuthPopup("login");
                }, 1400);
            } else {
                showError(data.message || "Unable to reset password.");
            }
        } catch (error) {
            showError("Unable to reset password. Please try again.");
        }
    });
    /* ====================== END RESET PASSWORD ====================== */

})();
</script>
<!-- ====================== END FORGOT PASSWORD (Login/forgot_password.php) ====================== -->
