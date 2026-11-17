<?php
/* ==========================================================================
   SIGN UP   (Login/signup.php)
   Iisang file ng Sign Up: PHP backend, style, HTML ng pop up, script.
   (Pinagsama rito ang dating send_code.php at verify_code.php.)

   DALAWANG GAMIT:
     1. Direktang tinatawag (fetch ng pop up, POST na may "action")
        -> [PHP] BACKEND lang ang tatakbo, tapos exit. Plain text ang sagot:
           "success", "COOLDOWN|segundo|mensahe", o ang error message.
           action = send    -> padala ng 6-digit code sa Gmail
           action = verify  -> i-check ang code
           action = create  -> gawin ang customer account
     2. Ini-include ng Shared/navbar.php kapag hindi naka-login
        -> [STYLE] + [HTML] + [SCRIPT] ng Sign Up pop up.

   Bumubukas ang pop up kapag:
     - kinlick ang kahit anong may data-auth-open="signup" ("Sign up" link ng Log In)
     - may ?auth=signup sa URL

   LAMAN (taas pababa):
     [PHP]     [1] BACKEND (send / verify / create)  [2] POP UP SETUP
     [STYLE]   SIGN UP POP UP
     [HTML]    SIGN UP POP UP
     [SCRIPT]  [1] HELPERS  [2] VERIFICATION CODE  [3] BUKSAN / ISARA
               [4] SEND / VERIFY CODE  [5] CREATE ACCOUNT
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

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        exit("Invalid request.");
    }

    if (!figurify_verify_csrf($_POST["csrf_token"] ?? null)) {
        exit("Your session has expired. Please refresh the page and try again.");
    }

    $action = $_POST["action"] ?? "";


    /* ---------- [1a] SEND - padala ng 6-digit code sa Gmail ---------- */
    if ($action === "send") {

        require_once __DIR__ . "/../helpers/messaging_helper.php";

        $firstName = trim($_POST["first_name"] ?? "");
        $lastName  = trim($_POST["last_name"] ?? "");
        $email     = trim($_POST["email"] ?? "");

        if (empty($firstName) || empty($lastName) || empty($email)) {
            exit("Please enter your first name, last name and Gmail.");
        }

        $name = figurify_full_name($firstName, $_POST["middle_name"] ?? "", $lastName);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            exit("Please enter a valid email address.");
        }

        // maluwag na bantay lang laban sa bot (30 kada IP sa isang oras). Ang totoong limit ay kada email, sa ibaba.
        if (figurify_action_rate_limit_seconds_remaining($conn, "send_code_ip", figurify_client_ip(), 30, 60) > 0) {
            exit("Too many sign up attempts from this network. Please try again later.");
        }

        if (figurify_is_email_banned($conn, $email)) {
            exit("This email has been banned and can't be used to sign up.");
        }

        // may account na ba ang email?
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        $alreadyRegistered = $check->num_rows > 0;
        $check->close();

        if ($alreadyRegistered) {
            exit("This email is already registered.");
        }

        // resend cooldown (5 minuto, FIGURIFY_RESEND_COOLDOWN_SECONDS) - may valid pang code
        // Match the cooldown with the actual code expiry using PHP's Manila time.
        $cooldownStmt = $conn->prepare(
            "SELECT expires_at FROM email_verifications WHERE email = ? ORDER BY id DESC LIMIT 1"
        );
        $cooldownStmt->bind_param("s", $email);
        $cooldownStmt->execute();
        $cooldownRow = $cooldownStmt->get_result()->fetch_assoc();
        $cooldownStmt->close();
        $cooldownRemaining = $cooldownRow ? max(0, strtotime($cooldownRow["expires_at"]) - time()) : 0;

        if ($cooldownRemaining > 0) {
            exit("COOLDOWN|" . $cooldownRemaining . "|Please wait before requesting another code.");
        }

        // limit kada EMAIL: max 5 pindot ng Send Code sa loob ng 5 minuto, tapos hintay (hanggang 5 minuto)
        $emailKey  = strtolower($email);
        $emailWait = figurify_action_rate_limit_seconds_remaining($conn, "send_code_email", $emailKey, 5, 5);

        if ($emailWait > 0) {
            exit("Too many attempts for this email. Please try again in " . max(1, (int) ceil($emailWait / 60)) . " minute(s).");
        }

        figurify_record_action_attempt($conn, "send_code_email", $emailKey);
        figurify_record_action_attempt($conn, "send_code_ip", figurify_client_ip());

        // bagong 6-digit code, mag-e-expire sa loob ng 5 minuto
        $code    = str_pad(random_int(0, 999999), 6, "0", STR_PAD_LEFT);
        $expiresTimestamp = time() + (5 * 60);
        $expires = date("Y-m-d H:i:s", $expiresTimestamp);

        // burahin ang lumang code ng email na ito
        $delete = $conn->prepare("DELETE FROM email_verifications WHERE email = ?");
        $delete->bind_param("s", $email);
        $delete->execute();
        $delete->close();

        $stmt = $conn->prepare(
            "INSERT INTO email_verifications (name, email, code, expires_at)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $name, $email, $code, $expires);

        if (!$stmt->execute()) {
            $stmt->close();
            $conn->close();
            exit("Failed to create verification code.");
        }

        $stmt->close();

        // padala via Gmail (figurify_send_mail - Admin/mail_config.php ang credentials)
        $codeParagraphs = [
            "Your verification code:",
            "<strong class=\"cs-mail-code\">" . $code . "</strong>",
            "It expires in <strong>5 minutes</strong>.",
            "Didn’t sign up? Ignore this email.",
        ];

        $sent = figurify_send_mail(
            $email,
            $name,
            "Your Clay and Stuff verification code",
            figurify_email_plain_layout($name, $codeParagraphs),
            figurify_email_plain_text($name, $codeParagraphs)
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
        exit($sent ? "success|" . max(1, $expiresTimestamp - time()) : "Email could not be sent. Please try again.");
    }


    /* ---------- [1b] VERIFY - i-check ang code ---------- */
    if ($action === "verify") {

        $email = trim($_POST["email"] ?? "");
        $code  = trim($_POST["code"] ?? "");

        if (empty($email) || empty($code)) {
            exit("Please enter the verification code.");
        }

        // limit sa maling code: 5 kada 10 minuto (laban sa hula-hula ng bot)
        if (figurify_action_rate_limit_seconds_remaining($conn, "verify_code", strtolower($email), 5, 10) > 0) {
            exit("Too many wrong codes. Please wait a few minutes and request a new code.");
        }

        $now = date("Y-m-d H:i:s");
        $stmt = $conn->prepare(
            "SELECT id, name, email
             FROM email_verifications
             WHERE email = ?
             AND code = ?
             AND expires_at > ?
             AND verified = 0
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->bind_param("sss", $email, $code, $now);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $verification = $result->fetch_assoc();

            // markahan na verified
            $update = $conn->prepare("UPDATE email_verifications SET verified = 1 WHERE id = ?");
            $update->bind_param("i", $verification["id"]);
            $update->execute();
            $update->close();

            // tanda sa session na verified na ang email (kailangan ng create)
            $_SESSION["verified_email"] = $email;
            $_SESSION["verified_name"]  = $verification["name"];

            echo "success";

        } else {

            figurify_record_action_attempt($conn, "verify_code", strtolower($email));
            $checkCode = $conn->prepare(
                "SELECT code, expires_at FROM email_verifications WHERE email = ? AND verified = 0 ORDER BY id DESC LIMIT 1"
            );
            $checkCode->bind_param("s", $email);
            $checkCode->execute();
            $activeCode = $checkCode->get_result()->fetch_assoc();
            $checkCode->close();

            if ($activeCode && $activeCode["expires_at"] <= $now) {
                echo "Verification code has expired. Please request a new code.";
            } elseif ($activeCode) {
                echo "Incorrect verification code. Please try again.";
            } else {
                echo "Invalid verification code. Please request a new code.";
            }
        }

        $stmt->close();
        $conn->close();
        exit();
    }


    /* ---------- [1c] CREATE - gawin ang customer account ---------- */
    if ($action === "create") {

        $firstName       = trim($_POST["first_name"] ?? "");
        $middleName      = trim($_POST["middle_name"] ?? "");
        $lastName        = trim($_POST["last_name"] ?? "");
        $email           = trim($_POST["email"] ?? "");
        $password        = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";
        $agreeTerms      = $_POST["agree_terms"] ?? "";

        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
            exit("Please complete all fields.");
        }

        // pagsang-ayon sa Terms and Conditions + Privacy Policy
        if (empty($agreeTerms)) {
            exit("Please agree to the Terms and Conditions and Privacy Policy first.");
        }

        if (strlen($password) < 8) {
            exit("Password must be at least 8 characters.");
        }

        if ($password !== $confirmPassword) {
            exit("Passwords do not match.");
        }

        // dapat na-verify ang email sa parehong session
        $verifiedEmail = $_SESSION["verified_email"] ?? "";

        if (empty($verifiedEmail) || strtolower($verifiedEmail) !== strtolower($email)) {
            exit("Please verify your email first.");
        }

        // at verified din sa database
        $stmt = $conn->prepare(
            "SELECT id
             FROM email_verifications
             WHERE email = ?
             AND verified = 1
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $isVerified = $stmt->get_result()->num_rows === 1;
        $stmt->close();

        if (!$isVerified) {
            exit("Please verify your email first.");
        }

        if (figurify_is_email_banned($conn, $email)) {
            exit("This email has been banned and can't be used to sign up.");
        }

        // may account na ba ang email?
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        $alreadyRegistered = $check->num_rows > 0;
        $check->close();

        if ($alreadyRegistered) {
            exit("This email is already registered.");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (first_name, middle_name, last_name, email, password, role)
             VALUES (?, NULLIF(?, ''), ?, ?, ?, 'customer')"
        );
        $stmt->bind_param("sssss", $firstName, $middleName, $lastName, $email, $hashedPassword);

        if ($stmt->execute()) {
            // tapos na ang verification
            unset($_SESSION["verified_email"], $_SESSION["verified_name"]);
            echo "success";
        } else {
            echo "Something went wrong while creating the account.";
        }

        $stmt->close();
        $conn->close();
        exit();
    }

    exit("Invalid request.");
}
/* ====================== END BACKEND ====================== */


/* ==========================================================================
   [PHP] [2] POP UP SETUP  (kapag ini-include ng navbar)
   ========================================================================== */
// security token, kasama sa bawat submit
$signupCsrfToken = figurify_csrf_token();

// mata ng password (show / hide) - pareho sa dalawang password field
$signupEyeIcons = '<svg class="signup-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>'
                . '<svg class="signup-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
/* ====================== END POP UP SETUP ====================== */
?>

<style>
/* ==========================================================================
   [STYLE] SIGN UP POP UP
   Lahat ay may signup- prefix para hindi madamay ang page.
   ========================================================================== */

/* ---------- madilim na background + card ---------- */
.signup-overlay {
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

.signup-overlay.active { opacity: 1; visibility: visible; }

/* hindi ma-scroll ang page habang bukas ang pop up */
body.signup-popup-open { overflow: hidden; }

.signup-modal {
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

.signup-overlay.active .signup-modal { transform: translateY(0) scale(1); }

/* lipat galing Log In: walang animation, parang iisang card lang */
.signup-overlay.signup-overlay-instant,
.signup-overlay.signup-overlay-instant .signup-modal { transition: none; }

.signup-modal * { box-sizing: border-box; }

/* ang laman lang ang nag-i-scroll; nakapirmi ang X button */
.signup-scroll {
    overflow-y: auto;
    padding: 28px 28px 22px;
    scrollbar-width: thin;
    scrollbar-color: #e9d5e1 transparent;
}

/* itinatago ang step na hindi kasalukuyang ginagamit (script [2]) */
.signup-modal .signup-hidden { display: none; }

/* ---------- X button ---------- */
.signup-close {
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

.signup-close:hover { background: #fbf3f7; border-color: #f1cfdd; color: #c0325e; }

.signup-close svg {
    width: 15px;
    height: 15px;
    stroke: currentColor;
    stroke-width: 2.4;
    stroke-linecap: round;
}

/* ---------- logo + title + subtitle ---------- */
.signup-header { margin-bottom: 20px; text-align: center; }

.signup-logo {
    display: block;
    width: 56px;
    height: 44px;
    margin: 0 auto 10px;
    object-fit: cover;   /* tinatanggal ang puting gilid ng logo.png */
}

.signup-header h2 {
    margin: 0 0 6px;
    font-family: 'Fraunces', Georgia, 'Times New Roman', serif;
    font-size: 25px;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -.2px;
    color: #43142f;
}

.signup-header p { margin: 0; font-size: 13.5px; font-weight: 500; color: #6e5767; }

/* ---------- field: label sa loob ng box, umaakyat sa border kapag may laman ---------- */
.signup-field { position: relative; margin-bottom: 14px; }

.signup-field > label {
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
.signup-field:focus-within > label,
.signup-field:has(input:not(:placeholder-shown)) > label {
    top: 0;
    left: 12px;
    font-size: 11.5px;
    font-weight: 700;
    background: #fff;
}
.signup-field:has(input:not(:placeholder-shown)) > label { color: #2d1a27; }

/* autofill ng browser - hiwalay na rule para hindi madamay ang nasa itaas sa browser na hindi ito kilala */
.signup-field:has(input:-webkit-autofill) > label {
    top: 0;
    left: 12px;
    font-size: 11.5px;
    font-weight: 700;
    background: #fff;
    color: #2d1a27;
}

/* naka-focus: crimson ang label (pangalawang selector = mas malakas sa "may laman" na rule) */
.signup-field:focus-within > label,
.signup-field:focus-within:has(input:not(:placeholder-shown)) > label { color: #c0325e; }

/* naka-lock na field (code bago mag-Send Code) */
.signup-field:has(input:disabled):not(:focus-within) > label { color: #a58f9d; }

/* ---------- icon sa kaliwa ng box ---------- */
.signup-icon {
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

.signup-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.signup-field:focus-within .signup-icon { color: #c0325e; }

/* ---------- input (normal at verification code) ---------- */
.signup-input-wrap { position: relative; }

.signup-input-wrap input,
.signup-verify-row input {
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

.signup-input-wrap input:focus,
.signup-verify-row input:focus {
    border-color: #c0325e;
    box-shadow: 0 0 0 4px rgba(192, 50, 94, .12);
}

/* naka-lock na field (code bago mag-Send Code) */
.signup-input-wrap input:disabled,
.signup-verify-row input:disabled {
    background: #fbf7f9;
    border-color: #f0e5ec;
    color: #a58f9d;
    cursor: not-allowed;
}

/* ---------- show / hide password (mata) ---------- */
.signup-has-eye input { padding-right: 44px; }

.signup-input-wrap .signup-eye {
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

.signup-input-wrap .signup-eye:hover { color: #c0325e; background: #fbf3f7; }

.signup-eye svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* bukas na mata = nakatago ang password; may .is-visible = nakikita */
.signup-eye .signup-eye-closed { display: none; }
.signup-eye.is-visible .signup-eye-open { display: none; }
.signup-eye.is-visible .signup-eye-closed { display: block; }

/* ---------- verification code (input + Send Code) ---------- */
.signup-verify-row {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 10px;
}

.signup-verify-row input:not(:placeholder-shown) { letter-spacing: 4px; font-weight: 700; }

.signup-code-timer { margin: 8px 2px 0; font-size: 12.5px; font-weight: 600; color: #8a6a7c; }
.signup-code-timer.is-expired { color: #c2410c; }

/* ---------- buttons ---------- */
.signup-button {
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

.signup-button:hover { background: #a82a52; }
.signup-button:disabled { opacity: .55; cursor: not-allowed; }

/* Create Account */
.signup-modal .signup-submit-button {
    display: block;
    margin: 16px 0 0;
    padding: 0 20px;
}

/* Send Code / Verify Code / Verified */
.signup-modal .signup-verify-button {
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

.signup-modal .signup-verify-button:hover { background: #fbd3e1; }

.signup-modal .signup-verify-button[data-mode="verified"] {
    background: #22a35a;
    color: #fff;
    opacity: 1;
}

/* ---------- "Already have an account? Log in" ---------- */
.signup-switch {
    margin-top: 16px;
    text-align: center;
    font-size: 13px;
    font-weight: 500;
    color: #6e5767;
}

.signup-switch a {
    margin-left: 2px;
    color: #c0325e;
    font-weight: 700;
    text-decoration: none;
}

/* ---------- Terms and Conditions checkbox ---------- */
.signup-terms {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 4px;
    text-align: left;
}

.signup-terms input[type="checkbox"] {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    margin-top: 3px;
    accent-color: #c0325e;
    cursor: pointer;
}

.signup-terms label {
    font-size: 12.5px;
    font-weight: 500;
    line-height: 1.5;
    color: #4a3a45;
    cursor: pointer;
}

.signup-terms a {
    color: #c0325e;
    font-weight: 700;
    text-decoration: none;
}

/* underline kapag tinapatan ang link */
.signup-switch a:hover,
.signup-terms a:hover { text-decoration: underline; }

/* ---------- lipat ng step (pagbukas ng pop up, at code -> password) ----------
   naka-.active para tumakbo ang animation pagbukas, hindi habang nakatago */
.signup-overlay.active .signup-step { animation: signupStepIn .34s ease both; }
.signup-overlay.active .signup-step.signup-step-leaving { animation: signupStepOut .18s ease both; }

@keyframes signupStepIn {
    from { opacity: 0; transform: translateX(22px); }
    to   { opacity: 1; transform: translateX(0); }
}

@keyframes signupStepOut {
    from { opacity: 1; transform: translateX(0); }
    to   { opacity: 0; transform: translateX(-22px); }
}

/* ---------- phone: mas maliit na pop up ---------- */
@media (max-width: 480px) {
    .signup-modal { border-radius: 22px; }
    .signup-scroll { padding: 26px 18px 20px; }
    .signup-header h2 { font-size: 22px; }
}
/* ====================== END SIGN UP POP UP (STYLE) ====================== */
</style>


<!-- ==========================================================================
     [HTML] SIGN UP POP UP
     Step 1: pangalan + Gmail + code   ->   Step 2: password + Terms
     Tinatawag ng script ang Login/signup.php (ito ring file) gamit ang fetch.
     ========================================================================== -->
<div class="signup-overlay" id="signupOverlay" data-auth-popup aria-hidden="true">
    <div class="signup-modal" role="dialog" aria-modal="true" aria-labelledby="signupHeading">

        <!-- security token, kasama sa bawat submit -->
        <input type="hidden" id="signupCsrfToken" data-auth-csrf value="<?php echo htmlspecialchars($signupCsrfToken); ?>">

        <button type="button" class="signup-close" id="signupCloseButton" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>
        </button>

        <div class="signup-scroll">
            <form id="signupForm" autocomplete="off">

                <div class="signup-header">
                    <img class="signup-logo" src="../Image/brand/logo.png" alt="Clay and Stuff">
                    <h2 id="signupHeading">Create your account</h2>
                    <p>Join Figurify and start creating.</p>
                </div>

                <!-- ====================== STEP 1: pangalan + Gmail + code ====================== -->
                <div class="signup-step" id="signupStep1">

                    <div class="signup-field">
                        <label for="signupFirstName">First name</label>
                        <div class="signup-input-wrap">
                            <span class="signup-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20.5c0-3.9 3.6-6.5 8-6.5s8 2.6 8 6.5"/></svg></span>
                            <input type="text" id="signupFirstName" name="signup_first_name" placeholder=" " autocomplete="off" required>
                        </div>
                    </div>

                    <div class="signup-field">
                        <label for="signupMiddleName">Middle name (optional)</label>
                        <div class="signup-input-wrap">
                            <span class="signup-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20.5c0-3.9 3.6-6.5 8-6.5s8 2.6 8 6.5"/></svg></span>
                            <input type="text" id="signupMiddleName" name="signup_middle_name" placeholder=" " autocomplete="off">
                        </div>
                    </div>

                    <div class="signup-field">
                        <label for="signupLastName">Last name</label>
                        <div class="signup-input-wrap">
                            <span class="signup-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20.5c0-3.9 3.6-6.5 8-6.5s8 2.6 8 6.5"/></svg></span>
                            <input type="text" id="signupLastName" name="signup_last_name" placeholder=" " autocomplete="off" required>
                        </div>
                    </div>

                    <div class="signup-field">
                        <label for="signupEmail">Gmail</label>
                        <div class="signup-input-wrap">
                            <span class="signup-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg></span>
                            <input type="email" id="signupEmail" name="signup_email" placeholder=" " autocomplete="off" required>
                        </div>
                    </div>

                    <!-- iisang button: Send Code -> Verify Code -> Verified -->
                    <div class="signup-field">
                        <label for="signupCode">Verification code</label>
                        <div class="signup-verify-row">
                            <span class="signup-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 4.5 6v5.5c0 4.6 3.1 8 7.5 9.5 4.4-1.5 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2.2 2.2L15.5 10"/></svg></span>
                            <input type="text" id="signupCode" maxlength="6" inputmode="numeric" placeholder=" " autocomplete="off" disabled>
                            <button type="button" id="signupCodeButton" class="signup-button signup-verify-button" data-mode="send">Send Code</button>
                        </div>
                        <p class="signup-code-timer" id="signupCodeTimer" hidden></p>
                    </div>

                </div>
                <!-- ====================== END STEP 1 ====================== -->

                <!-- ====================== STEP 2: password + Terms ====================== -->
                <div class="signup-step signup-hidden" id="signupStep2">

                    <div class="signup-field">
                        <label for="signupPassword">Password</label>
                        <div class="signup-input-wrap signup-has-eye">
                            <span class="signup-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></svg></span>
                            <input type="password" id="signupPassword" name="signup_password" placeholder=" " autocomplete="new-password">
                            <button type="button" class="signup-eye" data-target="signupPassword" aria-label="Show password"><?php echo $signupEyeIcons; ?></button>
                        </div>
                    </div>

                    <div class="signup-field">
                        <label for="signupConfirmPassword">Confirm password</label>
                        <div class="signup-input-wrap signup-has-eye">
                            <span class="signup-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></svg></span>
                            <input type="password" id="signupConfirmPassword" name="signup_confirm_password" placeholder=" " autocomplete="new-password">
                            <button type="button" class="signup-eye" data-target="signupConfirmPassword" aria-label="Show password"><?php echo $signupEyeIcons; ?></button>
                        </div>
                    </div>

                    <div class="signup-terms">
                        <input type="checkbox" id="signupAgreeTerms" name="agree_terms">
                        <label for="signupAgreeTerms">
                            I have read and agree to the
                            <a href="../Legal/terms-and-conditions.php" target="_blank" rel="noopener">Terms and Conditions</a>
                            and
                            <a href="../Legal/privacy-policy.php" target="_blank" rel="noopener">Privacy Policy</a>.
                        </label>
                    </div>

                    <button type="submit" class="signup-button signup-submit-button" id="signupCreateAccountButton" disabled>Create Account</button>

                </div>
                <!-- ====================== END STEP 2 ====================== -->

                <!-- bubuksan ang Log In pop up (Login/Login.php) -->
                <div class="signup-switch">
                    Already have an account? <a href="#" data-auth-open="login">Log in</a>
                </div>

            </form>
        </div>

    </div>
</div>
<!-- ====================== END SIGN UP POP UP (HTML) ====================== -->


<script>
(function () {

    const overlay             = document.getElementById("signupOverlay");
    const scrollBox           = overlay.querySelector(".signup-scroll");
    const form                = document.getElementById("signupForm");
    const csrfInput           = document.getElementById("signupCsrfToken");
    const step1               = document.getElementById("signupStep1");
    const step2               = document.getElementById("signupStep2");
    const firstNameInput      = document.getElementById("signupFirstName");
    const middleNameInput     = document.getElementById("signupMiddleName");
    const lastNameInput       = document.getElementById("signupLastName");
    const emailInput          = document.getElementById("signupEmail");
    const codeInput           = document.getElementById("signupCode");
    const codeButton          = document.getElementById("signupCodeButton");
    const codeTimer           = document.getElementById("signupCodeTimer");
    const agreeTermsInput     = document.getElementById("signupAgreeTerms");
    const createAccountButton = document.getElementById("signupCreateAccountButton");


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

    // padala sa backend (ito ring file); plain text ang sagot
    async function sendSignup(fields) {
        const formData = new FormData();

        Object.keys(fields).forEach(function (key) {
            formData.append(key, fields[key]);
        });
        formData.append("csrf_token", csrfInput.value);

        const response = await fetch("../Login/signup.php", { method: "POST", body: formData });
        return (await response.text()).trim();
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
        step2.classList.add("signup-hidden");
        step1.classList.remove("signup-hidden");
    }

    // lipat ng step na may animation (step 1 palabas, step 2 papasok)
    function swapStep(fromStep, toStep) {
        fromStep.classList.add("signup-step-leaving");

        setTimeout(function () {
            fromStep.classList.add("signup-hidden");
            fromStep.classList.remove("signup-step-leaving");
            toStep.classList.remove("signup-hidden");
            scrollBox.scrollTop = 0;
        }, 180);
    }

    // Create Account: bukas lang kapag verified na ang code AT naka-check ang Terms
    function updateCreateAccountState() {
        const verified = codeButton.getAttribute("data-mode") === "verified";
        createAccountButton.disabled = !(verified && agreeTermsInput.checked);
    }

    agreeTermsInput.addEventListener("change", updateCreateAccountState);
    /* ====================== END VERIFICATION CODE ====================== */


    /* ==========================================================================
       [SCRIPT] [3] BUKSAN / ISARA
       Iisang event ("figurify:auth-popup") ang gamit ng Log In, Sign Up at
       Forgot Password: bubukas ang may tamang pangalan, sasara ang iba.
       switching = may bukas nang pop up -> lipat agad, walang animation.
       ========================================================================== */
    function setOpen(isOpen, instant) {
        if (instant) {
            overlay.classList.add("signup-overlay-instant");
        }

        overlay.classList.toggle("active", isOpen);
        overlay.setAttribute("aria-hidden", isOpen ? "false" : "true");
        document.body.classList.toggle("signup-popup-open", isOpen);

        if (instant) {
            void overlay.offsetWidth;   // ilapat agad bago ibalik ang animation
            overlay.classList.remove("signup-overlay-instant");
        }
    }

    // "login" / "signup" / "forgot"
    function requestAuthPopup(name) {
        const switching = document.querySelector("[data-auth-popup].active") !== null;
        document.dispatchEvent(new CustomEvent("figurify:auth-popup", { detail: { name: name, switching: switching } }));
    }

    document.addEventListener("figurify:auth-popup", function (event) {
        if (event.detail.name === "signup") {
            setOpen(true, event.detail.switching);
        } else if (overlay.classList.contains("active")) {
            setOpen(false, true);
        }
    });

    // kahit anong may data-auth-open="signup" ("Sign up" link ng Log In)
    document.addEventListener("click", function (event) {
        const trigger = event.target.closest('[data-auth-open="signup"]');
        if (!trigger) return;

        event.preventDefault();
        requestAuthPopup("signup");
    });

    // X button lang ang nagsasara (hindi click sa labas o Escape)
    document.getElementById("signupCloseButton").addEventListener("click", function () {
        setOpen(false, false);
    });

    // show / hide password
    overlay.addEventListener("click", function (event) {
        const eyeButton = event.target.closest(".signup-eye");
        if (!eyeButton) return;

        const input = document.getElementById(eyeButton.getAttribute("data-target"));
        const nowVisible = input.type === "password";

        input.type = nowVisible ? "text" : "password";
        eyeButton.classList.toggle("is-visible", nowVisible);
        eyeButton.setAttribute("aria-label", nowVisible ? "Hide password" : "Show password");
    });

    // ?auth=signup sa URL = buksan agad. Tinatanggal sa URL pagkatapos para hindi bumukas ulit kapag nag-refresh.
    const params = new URLSearchParams(window.location.search);

    if (params.get("auth") === "signup") {
        requestAuthPopup("signup");

        params.delete("auth");
        params.delete("auth_error");

        const cleanQuery = params.toString();
        window.history.replaceState({}, document.title, window.location.pathname + (cleanQuery ? "?" + cleanQuery : "") + window.location.hash);
    }
    /* ====================== END BUKSAN / ISARA ====================== */


    /* ==========================================================================
       [SCRIPT] [4] SEND / VERIFY CODE
       ========================================================================== */
    codeButton.addEventListener("click", async function () {
        const mode  = codeButton.getAttribute("data-mode");
        const email = emailInput.value.trim();

        if (mode === "send") {
            const firstName = firstNameInput.value.trim();
            const lastName  = lastNameInput.value.trim();

            if (!firstName || !lastName || !email) {
                showError("Please enter your first name, last name and Gmail.");
                return;
            }

            setButton(codeButton, "Sending...", true);

            try {
                const result = await sendSignup({
                    action: "send",
                    first_name: firstName,
                    middle_name: middleNameInput.value.trim(),
                    last_name: lastName,
                    email: email
                });

                if (result.indexOf("success|") === 0) {
                    openCodeInput(Math.max(1, parseInt(result.split("|")[1], 10) || 1));
                } else if (result.indexOf("COOLDOWN|") === 0) {
                    // may valid pang code na naipadala na - ilagay na lang iyon
                    openCodeInput(parseInt(result.split("|")[1], 10) || 300);
                } else {
                    showError(result);
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
                const result = await sendSignup({ action: "verify", email: email, code: code });

                if (result === "success") {
                    markCodeVerified();
                    updateCreateAccountState();
                    swapStep(step1, step2);
                    setTimeout(function () { document.getElementById("signupPassword").focus(); }, 260);
                } else {
                    showError(result);
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
       [SCRIPT] [5] CREATE ACCOUNT
       ========================================================================== */
    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        const firstName       = firstNameInput.value.trim();
        const lastName        = lastNameInput.value.trim();
        const email           = emailInput.value.trim();
        const password        = document.getElementById("signupPassword").value;
        const confirmPassword = document.getElementById("signupConfirmPassword").value;

        if (!firstName || !lastName || !email || !password || !confirmPassword) {
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

        if (!agreeTermsInput.checked) {
            showError("Please agree to the Terms and Conditions and Privacy Policy first.");
            return;
        }

        try {
            const result = await sendSignup({
                action: "create",
                first_name: firstName,
                middle_name: middleNameInput.value.trim(),
                last_name: lastName,
                email: email,
                password: password,
                confirm_password: confirmPassword,
                agree_terms: "1"
            });

            if (result === "success") {
                // balik sa simula ng Sign Up, tapos lipat sa Log In
                setTimeout(function () {
                    form.reset();
                    resetCodeStep();
                    updateCreateAccountState();
                    requestAuthPopup("login");
                }, 1400);
            } else {
                showError(result);
            }
        } catch (error) {
            showError("Unable to create account.");
        }
    });
    /* ====================== END CREATE ACCOUNT ====================== */

})();
</script>
<!-- ====================== END SIGN UP (Login/signup.php) ====================== -->
