<?php
/* Site-wide login/signup popup, included once via Shared/navbar.php
   (walang sariling page). Tumatawag lang ito sa existing endpoints
   (Login.php, send_code.php, verify_code.php, signup.php) via fetch
   - same backend, UI/popup na lang ang bago. Wala itong ilalabas kung
   naka-login na. */

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

if (isset($_SESSION["user_id"])) {
    return;
}

require_once __DIR__ . "/../helpers/csrf_helper.php";
$csAuthCsrfToken = figurify_csrf_token();
?>
<!-- LOGIN / SIGN UP MODAL CSS -->
<style>
/* Naka-scope lahat sa .cs-auth- prefix para hindi ma-clash
   sa CSS ng anumang page kung saan ito lumalabas.
   Redesign: card na may logo, serif heading, malalaking input at crimson button
   (kapareho ng Login / Sign up / Forgot Password design). */

.cs-auth-overlay {
    --csa-ink: #43142f;
    --csa-label: #2d1a27;
    --csa-muted: #6e5767;
    --csa-crimson: #c0325e;
    --csa-crimson-dark: #a82a52;
    --csa-line: #e9d5e1;
    --csa-soft: #fde6ee;

    position: fixed;
    inset: 0;
    z-index: 3000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(24, 14, 22, 0.6);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.22s ease, visibility 0.22s ease;
}

.cs-auth-overlay.active {
    opacity: 1;
    visibility: visible;
}

body.cs-auth-lock {
    overflow: hidden;
}

/* CARD */

.cs-auth-modal {
    position: relative;
    width: 100%;
    max-width: 460px;
    max-height: calc(100vh - 40px);
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #f1e4ec;
    border-radius: 28px;
    box-shadow: 0 30px 70px rgba(40, 12, 32, 0.35);
    
    transform: translateY(14px) scale(0.97);
    transition: transform 0.22s ease;
}

.cs-auth-overlay.active .cs-auth-modal {
    transform: translateY(0) scale(1);
}

.cs-auth-modal * {
    box-sizing: border-box;
    
}

/* Scrollable lang ang laman (forms), hindi ang buong modal - nakapirmi
   ang close button kahit anong scroll position. */
.cs-auth-scroll {
    overflow-y: auto;
    padding: 38px 30px 28px;
    scrollbar-width: thin;
    scrollbar-color: #e9d5e1 transparent;
}

.cs-auth-close {
    position: absolute;
    top: 16px;
    right: 16px;
    z-index: 5;
    width: 36px;
    height: 36px;
    border: 1px solid #efdfe8;
    border-radius: 50%;
    background: #ffffff;
    color: var(--csa-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    box-shadow: none;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.cs-auth-close svg {
    width: 15px;
    height: 15px;
    stroke: currentColor;
    stroke-width: 2.4;
    stroke-linecap: round;
}

.cs-auth-close:hover {
    background: #fbf3f7;
    border-color: #f1cfdd;
    color: var(--csa-crimson);
}

/* HEADER (logo + title + subtitle) */

.cs-auth-header {
    text-align: center;
    margin-bottom: 24px;
}

.cs-auth-header .cs-auth-logo {
    width: 70px;
    height: 54px;
    margin: 0 auto 14px;
    display: block;
    object-fit: cover;   /* tinatanggal ang puting margin ng logo.png */
}

.cs-auth-modal .cs-auth-header h2 {
    
    font-size: 34px;
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -0.3px;
    color: var(--csa-ink);
    margin: 0 0 8px;
}

.cs-auth-header p {
    font-size: 15px;
    font-weight: 500;
    color: var(--csa-muted);
    margin: 0;
}

/* FIELDS */

.cs-auth-field {
    margin-bottom: 16px;
}

.cs-auth-field label {
    display: block;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--csa-label);
    margin-bottom: 8px;
}

.cs-auth-input-wrap {
    position: relative;
}

.cs-auth-input-wrap input,
.cs-auth-verify-row input {
    width: 100%;
    height: 48px;
    padding: 0 16px;
    border-radius: 12px;
    border: 1px solid var(--csa-line);
    background: #ffffff;
    font-size: 15px;
    font-weight: 500;
    color: var(--csa-label);
    outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}

.cs-auth-input-wrap input::placeholder,
.cs-auth-verify-row input::placeholder {
    color: #8a7683;
    font-weight: 400;
    letter-spacing: normal;
}

.cs-auth-input-wrap input:focus,
.cs-auth-verify-row input:focus {
    border-color: var(--csa-crimson);
    box-shadow: 0 0 0 4px rgba(192, 50, 94, 0.12);
}

/* Naka-lock na field (hal. Password bago pa ma-verify ang code) -
   dapat maliwanag na hindi pa ito pwedeng gamitin. */
.cs-auth-input-wrap input:disabled,
.cs-auth-verify-row input:disabled {
    background: #fbf7f9;
    border-color: #f0e5ec;
    color: #a58f9d;
    cursor: not-allowed;
}

.cs-auth-input-wrap input:disabled::placeholder,
.cs-auth-verify-row input:disabled::placeholder {
    color: #a58f9d;
}

.cs-auth-has-eye input {
    padding-right: 46px;
}

.cs-auth-input-wrap .cs-auth-eye {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    padding: 0;
    color: #8d7584;
    transition: color 0.15s ease, background 0.15s ease;
}

.cs-auth-input-wrap .cs-auth-eye:hover {
    color: var(--csa-crimson);
    background: #fbf3f7;
}

.cs-auth-eye svg {
    width: 19px;
    height: 19px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.cs-auth-eye .cs-auth-eye-closed {
    display: none;
}

.cs-auth-eye.is-visible .cs-auth-eye-open {
    display: none;
}

.cs-auth-eye.is-visible .cs-auth-eye-closed {
    display: block;
}

/* VERIFICATION CODE ROW (input + Send Code) */

.cs-auth-verify-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
    align-items: center;
}

.cs-auth-verify-row input:not(:placeholder-shown) {
    letter-spacing: 4px;
    font-weight: 700;
}

.cs-auth-code-timer {
    margin: 8px 2px 0;
    font-size: 12.5px;
    font-weight: 600;
    color: #8a6a7c;
}

.cs-auth-code-timer.is-expired { color: #c2410c; }

/* BUTTONS */

.cs-auth-btn {
    width: 100%;
    height: 50px;
    border: none;
    border-radius: 14px;
    background: var(--csa-crimson);
    color: #ffffff;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.1px;
    cursor: pointer;
    box-shadow: none !important;
    transform: none !important;
    transition: background 0.18s ease, opacity 0.18s ease;
}

.cs-auth-btn:hover {
    background: var(--csa-crimson-dark);
}

.cs-auth-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

/* Log In / Create Account / Reset Password: buong lapad ng card */
.cs-auth-modal .cs-auth-btn-submit {
    display: block;
    width: 100%;
    margin: 20px 0 0;
    padding: 0 20px;
}

.cs-auth-btn-secondary {
    background: var(--csa-soft);
    color: var(--csa-crimson);
    margin-top: 4px;
}

.cs-auth-btn-secondary:hover {
    background: #fbd3e1;
}

/* Send Code / Verify Code */
.cs-auth-modal .cs-auth-verify-btn {
    width: auto;
    min-width: 104px;
    height: 48px;
    padding: 0 18px;
    border-radius: 12px;
    background: var(--csa-soft);
    color: var(--csa-crimson);
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
}

.cs-auth-modal .cs-auth-verify-btn:hover {
    background: #fbd3e1;
}

.cs-auth-modal .cs-auth-verify-btn[data-mode="verified"] {
    background: #22a35a;
    color: #ffffff;
    opacity: 1;
}

/* REMEMBER ME + FORGOT PASSWORD */

.cs-auth-row-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 4px 0 0;
}

.cs-auth-remember {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14.5px;
    font-weight: 500;
    color: #4a3a45;
    cursor: pointer;
    user-select: none;
}

.cs-auth-remember input {
    width: 16px;
    height: 16px;
    margin: 0;
    accent-color: var(--csa-crimson);
    cursor: pointer;
}

.cs-auth-forgot {
    font-size: 14.5px;
    font-weight: 700;
    color: var(--csa-crimson);
    text-decoration: none;
    margin: 0;
}

.cs-auth-forgot:hover {
    text-decoration: underline;
}

.cs-auth-section-divider {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #f4e3ee;
}

/* SWITCH LINK (Log in / Sign up) */

.cs-auth-switch {
    text-align: center;
    font-size: 14.5px;
    font-weight: 500;
    color: var(--csa-muted);
    margin-top: 18px;
}

.cs-auth-switch a {
    color: var(--csa-crimson);
    font-weight: 700;
    text-decoration: none;
    margin-left: 2px;
}

.cs-auth-switch a:hover {
    text-decoration: underline;
}

/* TERMS */

.cs-auth-terms {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 4px;
    text-align: left;
}

.cs-auth-terms input[type="checkbox"] {
    width: 16px;
    height: 16px;
    margin-top: 3px;
    flex-shrink: 0;
    cursor: pointer;
    accent-color: var(--csa-crimson);
}

.cs-auth-terms label {
    font-size: 14.5px;
    font-weight: 500;
    color: #4a3a45;
    line-height: 1.5;
    cursor: pointer;
}

.cs-auth-terms a {
    color: var(--csa-crimson);
    font-weight: 700;
    text-decoration: none;
}

.cs-auth-terms a:hover {
    text-decoration: underline;
}

.cs-auth-hidden {
    display: none !important;
}

/* Two-step Sign up / Forgot password: step 1 (details + code) ->
   pag na-verify, napapalitan ng step 2 (password) na may transition. */
.cs-auth-step {
    animation: csAuthStepIn 0.34s ease both;
}

.cs-auth-step.cs-auth-step-leaving {
    animation: csAuthStepOut 0.18s ease both;
}

@keyframes csAuthStepIn {
    from { opacity: 0; transform: translateX(22px); }
    to   { opacity: 1; transform: translateX(0); }
}

@keyframes csAuthStepOut {
    from { opacity: 1; transform: translateX(0); }
    to   { opacity: 0; transform: translateX(-22px); }
}

/* Styled notice/toast, same disenyo ng Commission page (kapalit ng
   browser alert) - floating card sa itaas, nawawala sa sarili. */

.cs-auth-notice-container {
    position: fixed;
    top: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 4200;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    max-width: 400px;
    width: calc(100% - 32px);
    pointer-events: none;
}

.cs-auth-notice-toast {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 18px;
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid #f1e4ec;
    border-left: 4px solid #c0325e;
    box-shadow: 0 14px 32px rgba(67, 20, 47, 0.22);
    opacity: 0;
    transform: translateY(-10px) scale(0.96);
    transition: opacity 0.2s ease, transform 0.2s ease;
    pointer-events: auto;
    
}

.cs-auth-notice-toast.cs-notice-show {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.cs-auth-notice-toast.cs-notice-hide {
    opacity: 0;
    transform: translateY(-6px) scale(0.97);
}

.cs-auth-notice-toast.cs-notice-success {
    border-left-color: #1f9d55;
}

.cs-auth-notice-icon {
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 900;
    color: #ffffff;
    background: #c0325e;
}

.cs-auth-notice-toast.cs-notice-success .cs-auth-notice-icon {
    background: #1f9d55;
}

.cs-auth-notice-message {
    flex: 1;
    color: #43142f;
    font-size: 13.5px;
    font-weight: 600;
    line-height: 1.5;
    padding-top: 2px;
}

@media (max-width: 480px) {
    .cs-auth-modal {
        border-radius: 22px;
    }

    .cs-auth-scroll {
        padding: 32px 20px 22px;
    }

    .cs-auth-modal .cs-auth-header h2 {
        font-size: 28px;
    }
}
</style>

<!-- LOGIN / SIGN UP MODAL MARKUP -->

<div class="cs-auth-overlay" id="csAuthOverlay" aria-hidden="true">
    <div class="cs-auth-modal" role="dialog" aria-modal="true" aria-labelledby="csAuthHeading">

        <!-- CSRF token, isinasama sa bawat form submit -->
        <input type="hidden" id="csAuthCsrfToken" value="<?= htmlspecialchars($csAuthCsrfToken) ?>">

        <button type="button" class="cs-auth-close" id="csAuthClose" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>
        </button>

        <div class="cs-auth-scroll">

        <!-- LOGIN FORM -->
        <form id="csAuthLoginForm" autocomplete="on">

            <div class="cs-auth-header">
                <img class="cs-auth-logo" src="../Image/brand/logo.png" alt="Clay and Stuff">
                <h2 id="csAuthHeading">Welcome</h2>
                <p>Log in to continue your Clay &amp; Stuff journey.</p>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthEmail">Email</label>
                <div class="cs-auth-input-wrap">
                    <input type="email" id="csAuthEmail" name="email" placeholder="you@example.com" autocomplete="username" required>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthPassword">Password</label>
                <div class="cs-auth-input-wrap cs-auth-has-eye">
                    <input type="password" id="csAuthPassword" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                    <button type="button" class="cs-auth-eye" data-target="csAuthPassword" aria-label="Show password">
                        <svg class="cs-auth-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="cs-auth-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div class="cs-auth-row-between">
                <label class="cs-auth-remember" for="csAuthRemember">
                    <input type="checkbox" id="csAuthRemember" name="remember" value="1">
                    <span>Remember me</span>
                </label>
                <a href="#" class="cs-auth-forgot" id="csAuthGoForgot">Forgot password?</a>
            </div>

            <button type="submit" class="cs-auth-btn cs-auth-btn-submit" id="csAuthLoginSubmit">Log In</button>

            <div class="cs-auth-switch">
                Don't have an account? <a href="#" id="csAuthGoSignup">Sign up</a>
            </div>
        </form>

        <!-- SIGN UP FORM -->
        <form id="csAuthSignupForm" class="cs-auth-hidden" autocomplete="off">

            <div class="cs-auth-header">
                <img class="cs-auth-logo" src="../Image/brand/logo.png" alt="Clay and Stuff">
                <h2>Create your account</h2>
                <p>Join Figurify and start creating.</p>
            </div>

            <div class="cs-auth-step" id="csAuthSignupStep1">
            <div class="cs-auth-field">
                <label for="csAuthFullName">Full name</label>
                <div class="cs-auth-input-wrap">
                    <input type="text" id="csAuthFullName" name="signup_full_name" placeholder="Your full name" autocomplete="off" required>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthSignupEmail">Gmail</label>
                <div class="cs-auth-input-wrap">
                    <input type="email" id="csAuthSignupEmail" name="signup_email" placeholder="you@gmail.com" autocomplete="off" required>
                </div>
            </div>

            <!-- Isang form lang. Naka-disable ang Password hanggang ma-verify ang code,
                 at ang Create Account hanggang hindi naka-check ang Terms.
                 Iisang button lang ang Send Code / Verify Code. -->

            <div class="cs-auth-field">
                <label for="csAuthVerificationCode">Verification code</label>
                <div class="cs-auth-verify-row">
                    <input type="text" id="csAuthVerificationCode" maxlength="6" inputmode="numeric" placeholder="Enter 6-digit code" autocomplete="off" disabled>
                    <button type="button" id="csAuthSendCodeBtn" class="cs-auth-btn cs-auth-verify-btn" data-mode="send">Send Code</button>
                </div>
                <p class="cs-auth-code-timer" id="csAuthCodeTimer" hidden></p>
            </div>
            </div><!-- /step 1 -->

            <div class="cs-auth-step cs-auth-hidden" id="csAuthSignupStep2">

            <div class="cs-auth-field">
                <label for="csAuthSignupPassword">Password</label>
                <div class="cs-auth-input-wrap cs-auth-has-eye">
                    <input type="password" id="csAuthSignupPassword" name="signup_password" placeholder="Create a password" autocomplete="new-password">
                    <button type="button" class="cs-auth-eye" data-target="csAuthSignupPassword" aria-label="Show password">
                        <svg class="cs-auth-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="cs-auth-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthConfirmPassword">Confirm password</label>
                <div class="cs-auth-input-wrap cs-auth-has-eye">
                    <input type="password" id="csAuthConfirmPassword" name="signup_confirm_password" placeholder="Repeat your password" autocomplete="new-password">
                    <button type="button" class="cs-auth-eye" data-target="csAuthConfirmPassword" aria-label="Show password">
                        <svg class="cs-auth-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="cs-auth-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div class="cs-auth-terms">
                <input type="checkbox" id="csAuthAgreeTerms" name="agree_terms">
                <label for="csAuthAgreeTerms">
                    I have read and agree to the
                    <a href="../Legal/terms-and-conditions.php" target="_blank" rel="noopener">Terms and Conditions</a>
                    and
                    <a href="../Legal/privacy-policy.php" target="_blank" rel="noopener">Privacy Policy</a>.
                </label>
            </div>

            <button type="submit" class="cs-auth-btn cs-auth-btn-submit" id="csAuthCreateAccountBtn" disabled>Create Account</button>
            </div><!-- /step 2 -->

            <div class="cs-auth-switch">
                Already have an account? <a href="#" id="csAuthGoLogin">Log in</a>
            </div>
        </form>

        <!-- FORGOT PASSWORD FORM -->
        <form id="csAuthForgotForm" class="cs-auth-hidden" autocomplete="off">

            <div class="cs-auth-header">
                <img class="cs-auth-logo" src="../Image/brand/logo.png" alt="Clay and Stuff">
                <h2>Reset your password</h2>
                <p>We'll send a verification code to your email.</p>
            </div>

            <div class="cs-auth-step" id="csAuthForgotStep1">
            <div class="cs-auth-field">
                <label for="csAuthForgotEmail">Email</label>
                <div class="cs-auth-input-wrap">
                    <input type="email" id="csAuthForgotEmail" name="forgot_email" placeholder="you@example.com" autocomplete="username" required>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthForgotCode">Verification code</label>
                <div class="cs-auth-verify-row">
                    <input type="text" id="csAuthForgotCode" maxlength="6" inputmode="numeric" placeholder="Enter 6-digit code" autocomplete="off" disabled>
                    <button type="button" id="csAuthForgotSendBtn" class="cs-auth-btn cs-auth-verify-btn" data-mode="send">Send Code</button>
                </div>
                <p class="cs-auth-code-timer" id="csAuthForgotTimer" hidden></p>
            </div>
            </div><!-- /step 1 -->

            <div class="cs-auth-step cs-auth-hidden" id="csAuthForgotStep2">

            <div class="cs-auth-field">
                <label for="csAuthNewPassword">New password</label>
                <div class="cs-auth-input-wrap cs-auth-has-eye">
                    <input type="password" id="csAuthNewPassword" name="new_password" placeholder="Create a new password" autocomplete="new-password">
                    <button type="button" class="cs-auth-eye" data-target="csAuthNewPassword" aria-label="Show password">
                        <svg class="cs-auth-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="cs-auth-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div class="cs-auth-field">
                <label for="csAuthConfirmNewPassword">Confirm new password</label>
                <div class="cs-auth-input-wrap cs-auth-has-eye">
                    <input type="password" id="csAuthConfirmNewPassword" name="confirm_new_password" placeholder="Repeat your new password" autocomplete="new-password">
                    <button type="button" class="cs-auth-eye" data-target="csAuthConfirmNewPassword" aria-label="Show password">
                        <svg class="cs-auth-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="cs-auth-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="cs-auth-btn cs-auth-btn-submit" id="csAuthResetSubmit" disabled>Reset Password</button>
            </div><!-- /step 2 -->

            <div class="cs-auth-switch">
                Remembered your password? <a href="#" id="csAuthGoLoginFromForgot">Log in</a>
            </div>
        </form>

        </div>
    </div>
</div>

<!-- LOGIN / SIGN UP MODAL SCRIPT -->

<script>
(function () {

    var overlay = document.getElementById('csAuthOverlay');
    if (!overlay) return;

    // Isinasama sa bawat form submit, tinitingnan ng backend (csrf_helper.php).
    function csAuthCsrfToken() {
        var input = document.getElementById('csAuthCsrfToken');
        return input ? input.value : '';
    }

    // Countdown timer (mm:ss) para sa login lockout at resend-code cooldown.
    function csAuthFormatTime(totalSeconds) {
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;
        return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    }

    function csAuthStartCountdown(seconds, onTick, onDone) {
        var secondsLeft = seconds;
        onTick(secondsLeft);

        var intervalId = setInterval(function () {
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

    /* 5 minutong timer ng verification code. Pag naubos, puwede nang
       mag-Resend Code ulit (bumabalik sa "send" ang button). */
    var csAuthCodeTimers = {};

    function csAuthStopCodeTimer(key, timerEl) {
        if (csAuthCodeTimers[key]) {
            clearInterval(csAuthCodeTimers[key]);
            delete csAuthCodeTimers[key];
        }
        if (timerEl) {
            timerEl.hidden = true;
            timerEl.textContent = '';
            timerEl.classList.remove('is-expired');
        }
    }

    function csAuthStartCodeTimer(key, seconds, timerEl, button, codeInput) {
        csAuthStopCodeTimer(key, timerEl);
        if (!timerEl) {
            return;
        }
        timerEl.hidden = false;
        csAuthCodeTimers[key] = csAuthStartCountdown(
            seconds,
            function (secondsLeft) {
                timerEl.classList.remove('is-expired');
                timerEl.textContent = 'Code expires in ' + csAuthFormatTime(secondsLeft);
            },
            function () {
                delete csAuthCodeTimers[key];
                if (button.getAttribute('data-mode') !== 'verify') {
                    return;
                }
                timerEl.classList.add('is-expired');
                timerEl.textContent = 'Code expired. You can send a new code.';
                codeInput.value = '';
                codeInput.disabled = true;
                button.setAttribute('data-mode', 'send');
                button.textContent = 'Resend Code';
                button.disabled = false;
            }
        );
    }

    /* Styled notice/toast, same ginagamit ng commission.js. type: "error" (default) o "success". */
    function csAuthShowNotice(message, type) {

        /* walang laman = pang-clear lang (hal. pagpindot ng Log In) - walang pop up */
        if (!message) return;

        /* Login / Sign up / Forgot password: error lang ang may pop up
           (hal. maling password). Walang pop up pag success. */
        if (type === 'success' || type === 'info') return;

        /* shared pop up (Shared/all-in-one.php) - para pare-pareho ang itsura sa buong site */
        if (window.csToast) {
            window.csToast(message, 'error');
            return;
        }

        type = type || 'error';

        var container = document.getElementById('csAuthNoticeContainer');

        if (!container) {
            container = document.createElement('div');
            container.id = 'csAuthNoticeContainer';
            container.className = 'cs-auth-notice-container';
            document.body.appendChild(container);
        }

        // Laging isa lang ang lumalabas sa parehong lugar, hindi
        // nagtatambak-tambak pababa.
        container.innerHTML = '';

        var notice = document.createElement('div');
        notice.className = 'cs-auth-notice-toast cs-notice-' + type;

        var icon = document.createElement('div');
        icon.className = 'cs-auth-notice-icon';
        icon.textContent = type === 'success' ? '✓' : '!';

        var text = document.createElement('div');
        text.className = 'cs-auth-notice-message';
        text.textContent = message;

        notice.appendChild(icon);
        notice.appendChild(text);
        container.appendChild(notice);

        requestAnimationFrame(function () {
            notice.classList.add('cs-notice-show');
        });

        setTimeout(function () {
            notice.classList.remove('cs-notice-show');
            notice.classList.add('cs-notice-hide');
            setTimeout(function () {
                if (notice.parentNode) {
                    notice.parentNode.removeChild(notice);
                }
            }, 250);
        }, 5000);
    }

    var modalBox = overlay.querySelector('.cs-auth-modal');
    var closeBtn = document.getElementById('csAuthClose');
    var loginForm = document.getElementById('csAuthLoginForm');
    var signupForm = document.getElementById('csAuthSignupForm');

    /* OPEN / CLOSE */

    function openAuthModal(showSignup) {
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('cs-auth-lock');

        if (showSignup) {
            showSignupForm();
        } else {
            showLoginForm();
        }
    }

    function closeAuthModal() {
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('cs-auth-lock');
    }

    // Ginagawa itong global para magamit ng ibang script sa site
    // (hal. isang "Log In muna" prompt sa Commission page) kung
    // kailangan buksan ang modal mula sa ibang lugar.
    window.figurifyOpenAuthModal = openAuthModal;
    window.figurifyCloseAuthModal = closeAuthModal;

    /* "X" button lang ang pwedeng magsara ng popup (hindi click-outside o Escape). */
    if (closeBtn) {
        closeBtn.addEventListener('click', closeAuthModal);
    }

    /* Click sa "Log In" navbar button / [data-auth-open] = mag-popup na lang
       (walang JS = normal link pa rin papunta sa Login.php). */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.btn-login-only, [data-auth-open]');
        if (!trigger) return;

        event.preventDefault();
        openAuthModal(trigger.hasAttribute('data-auth-open-signup'));
    });

    /* ?auth=login sa URL (mula protected-page redirect, atbp.) = auto-open
       ang popup; ?auth_error= = ipapakita bilang error. Nililinis ang URL
       pagkatapos (history.replaceState) para hindi bumukas ulit sa refresh. */
    (function () {
        var params = new URLSearchParams(window.location.search);
        if (!params.has('auth')) return;

        var wantsSignup = params.get('auth') === 'signup';
        openAuthModal(wantsSignup);

        var authError = params.get('auth_error');
        if (authError && !wantsSignup) {
            setLoginError(authError);
        }

        params.delete('auth');
        params.delete('auth_error');

        var cleanQuery = params.toString();
        var cleanUrl = window.location.pathname + (cleanQuery ? '?' + cleanQuery : '') + window.location.hash;
        window.history.replaceState({}, document.title, cleanUrl);
    })();

    /* SWITCH LOGIN / SIGN UP / FORGOT */

    var forgotForm = document.getElementById('csAuthForgotForm');

    function showLoginForm() {
        signupForm.classList.add('cs-auth-hidden');
        if (forgotForm) forgotForm.classList.add('cs-auth-hidden');
        loginForm.classList.remove('cs-auth-hidden');
    }

    function showSignupForm() {
        loginForm.classList.add('cs-auth-hidden');
        if (forgotForm) forgotForm.classList.add('cs-auth-hidden');
        signupForm.classList.remove('cs-auth-hidden');
    }

    function showForgotForm() {
        loginForm.classList.add('cs-auth-hidden');
        signupForm.classList.add('cs-auth-hidden');
        if (forgotForm) forgotForm.classList.remove('cs-auth-hidden');
    }

    var goSignup = document.getElementById('csAuthGoSignup');
    var goLogin = document.getElementById('csAuthGoLogin');
    var goForgot = document.getElementById('csAuthGoForgot');
    var goLoginFromForgot = document.getElementById('csAuthGoLoginFromForgot');

    if (goSignup) {
        goSignup.addEventListener('click', function (e) {
            e.preventDefault();
            showSignupForm();
        });
    }

    if (goLogin) {
        goLogin.addEventListener('click', function (e) {
            e.preventDefault();
            showLoginForm();
        });
    }

    if (goForgot) {
        goForgot.addEventListener('click', function (e) {
            e.preventDefault();
            showForgotForm();
        });
    }

    if (goLoginFromForgot) {
        goLoginFromForgot.addEventListener('click', function (e) {
            e.preventDefault();
            showLoginForm();
        });
    }

    /* Generic show/hide-password handler para sa lahat ng eye buttons (SVG icon, hindi emoji). */

    overlay.addEventListener('click', function (event) {
        var eyeBtn = event.target.closest('.cs-auth-eye');
        if (!eyeBtn) return;

        var targetInput = document.getElementById(eyeBtn.getAttribute('data-target'));
        if (!targetInput) return;

        var nowVisible = targetInput.type === 'password';
        targetInput.type = nowVisible ? 'text' : 'password';
        eyeBtn.classList.toggle('is-visible', nowVisible);
        eyeBtn.setAttribute('aria-label', nowVisible ? 'Hide password' : 'Show password');
    });

    /* LOGIN (AJAX, JSON response) */

    function setLoginError(message) {
        csAuthShowNotice(message, 'error');
    }

    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            event.preventDefault();
            setLoginError('');

            var email = document.getElementById('csAuthEmail').value.trim();
            var password = document.getElementById('csAuthPassword').value.trim();

            if (!email || !password) {
                setLoginError('Please enter your email and password.');
                return;
            }

            var submitBtn = document.getElementById('csAuthLoginSubmit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';

            /* Kapag luma na ang token (hal. may ginawa sa ibang tab),
               kusang kukuha ng bago at uulitin ang login nang isang beses. */
            function csAuthSendLogin(isRetry) {
                var formData = new FormData();
                formData.append('action', 'login');
                formData.append('ajax', '1');
                formData.append('email', email);
                formData.append('password', password);
                var rememberBox = document.getElementById('csAuthRemember');
                if (rememberBox && rememberBox.checked) {
                    formData.append('remember', '1');
                }
                formData.append('csrf_token', csAuthCsrfToken());

                return fetch('../Login/Login.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.status === 'csrf' && data.csrf_token && !isRetry) {
                            var tokenInput = document.getElementById('csAuthCsrfToken');
                            if (tokenInput) { tokenInput.value = data.csrf_token; }
                            return csAuthSendLogin(true);
                        }
                        return data;
                    });
            }

            csAuthSendLogin(false)
                .then(function (data) {
                    if (data.status === 'success') {
                        window.location.href = data.redirect || window.location.href;
                    } else if (data.retry_after) {
                        /* Naka-lock muna: ipakita ang live countdown sa
                           toast at sa button mismo, hanggang matapos. */
                        submitBtn.disabled = true;

                        csAuthStartCountdown(
                            data.retry_after,
                            function (secondsLeft) {
                                var timeText = csAuthFormatTime(secondsLeft);
                                csAuthShowNotice('Too many failed login attempts. Try again in ' + timeText + '.', 'error');
                                submitBtn.textContent = 'Try again in ' + timeText;
                            },
                            function () {
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Log In';
                                csAuthShowNotice('You can try logging in again now.', 'success');
                            }
                        );
                    } else {
                        setLoginError(data.message || 'Invalid email or password.');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Log In';
                    }
                })
                .catch(function () {
                    setLoginError('Unable to log in right now. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Log In';
                });
        });
    }

    /* Step swap na may transition (fade-slide palabas, tapos fade-slide papasok). */
    function csAuthSwapStep(fromEl, toEl) {
        if (!fromEl || !toEl) return;
        fromEl.classList.add('cs-auth-step-leaving');
        setTimeout(function () {
            fromEl.classList.add('cs-auth-hidden');
            fromEl.classList.remove('cs-auth-step-leaving');
            toEl.classList.remove('cs-auth-hidden');
            var scroller = overlay.querySelector('.cs-auth-scroll');
            if (scroller) scroller.scrollTop = 0;
        }, 180);
    }

    function csAuthShowStepNow(showEl, hideEl) {
        if (hideEl) hideEl.classList.add('cs-auth-hidden');
        if (showEl) showEl.classList.remove('cs-auth-hidden');
    }

    /* SIGN UP */
    /* Iisang button (csAuthSendCodeBtn) para sa Send Code/Verify Code,
       nagpapalit lang ng mode/label (data-mode: send -> verify -> verified).
       Naka-lock ang code/password fields hanggat hindi pa na-verify. */

    var sendCodeBtn = document.getElementById('csAuthSendCodeBtn');
    var verificationCodeInput = document.getElementById('csAuthVerificationCode');
    var signupPasswordInput = document.getElementById('csAuthSignupPassword');
    var confirmPasswordInput = document.getElementById('csAuthConfirmPassword');
    var createAccountBtn = document.getElementById('csAuthCreateAccountBtn');
    var agreeTermsInput = document.getElementById('csAuthAgreeTerms');

    function setSignupError(message) {
        csAuthShowNotice(message, 'error');
    }

    function setSignupSuccess(message) {
        csAuthShowNotice(message, 'success');
    }

    /* Ang Create Account ay dapat naka-enable lang kapag PAREHONG
       (1) na-verify na ang code (bukas na ang password fields) AT
       (2) na-check na ang Terms and Conditions checkbox. */
    function updateCreateAccountState() {
        if (!createAccountBtn) return;
        var verified = sendCodeBtn && sendCodeBtn.getAttribute('data-mode') === 'verified';
        var agreed = agreeTermsInput && agreeTermsInput.checked;
        createAccountBtn.disabled = !(verified && agreed);
    }

    if (agreeTermsInput) {
        agreeTermsInput.addEventListener('change', updateCreateAccountState);
    }

    /* Ibinabalik ang Sign Up form sa naka-lock na state pagkatapos ng successful signup. */
    function resetSignupLockState() {
        csAuthStopCodeTimer('signup', document.getElementById('csAuthCodeTimer'));
        sendCodeBtn.setAttribute('data-mode', 'send');
        sendCodeBtn.disabled = false;
        sendCodeBtn.textContent = 'Send Code';
        verificationCodeInput.disabled = true;
        csAuthShowStepNow(document.getElementById('csAuthSignupStep1'), document.getElementById('csAuthSignupStep2'));
        updateCreateAccountState();
    }

    /* SEND CODE / VERIFY CODE (iisang button) */

    if (sendCodeBtn) {
        sendCodeBtn.addEventListener('click', async function () {
            setSignupError('');

            var mode = sendCodeBtn.getAttribute('data-mode');

            if (mode === 'send') {

                var name = document.getElementById('csAuthFullName').value.trim();
                var email = document.getElementById('csAuthSignupEmail').value.trim();

                if (!name || !email) {
                    setSignupError('Please enter your name and Gmail.');
                    return;
                }

                var formData = new FormData();
                formData.append('full_name', name);
                formData.append('email', email);
                formData.append('csrf_token', csAuthCsrfToken());

                sendCodeBtn.disabled = true;
                sendCodeBtn.textContent = 'Sending...';

                try {
                    var response = await fetch('../Login/send_code.php', { method: 'POST', body: formData });
                    var result = await response.text();
                    result = result.trim();

                    if (result === 'success') {
                        setSignupSuccess('Verification code has been sent.');
                        verificationCodeInput.disabled = false;
                        verificationCodeInput.focus();
                        sendCodeBtn.setAttribute('data-mode', 'verify');
                        sendCodeBtn.textContent = 'Verify Code';
                        sendCodeBtn.disabled = false;
                        csAuthStartCodeTimer('signup', 300, document.getElementById('csAuthCodeTimer'), sendCodeBtn, verificationCodeInput);
                    } else if (result.indexOf('COOLDOWN|') === 0) {
                        /* May naipadala nang code (valid pa) - ilagay na lang
                           ang code na iyon; may timer kung kailan ito mag-e-expire. */
                        var cooldownSeconds = parseInt(result.split('|')[1], 10) || 300;
                        setSignupSuccess('A code was already sent to your Gmail. Enter it below.');
                        verificationCodeInput.disabled = false;
                        verificationCodeInput.focus();
                        sendCodeBtn.setAttribute('data-mode', 'verify');
                        sendCodeBtn.textContent = 'Verify Code';
                        sendCodeBtn.disabled = false;
                        csAuthStartCodeTimer('signup', cooldownSeconds, document.getElementById('csAuthCodeTimer'), sendCodeBtn, verificationCodeInput);
                    } else {
                        setSignupError(result);
                        sendCodeBtn.textContent = 'Send Code';
                        sendCodeBtn.disabled = false;
                    }
                } catch (error) {
                    setSignupError('Unable to send verification code.');
                    sendCodeBtn.textContent = 'Send Code';
                    sendCodeBtn.disabled = false;
                }

            } else if (mode === 'verify') {

                var codeEmail = document.getElementById('csAuthSignupEmail').value.trim();
                var code = verificationCodeInput.value.trim();

                if (code.length !== 6) {
                    setSignupError('Please enter the 6-digit verification code.');
                    return;
                }

                var verifyFormData = new FormData();
                verifyFormData.append('email', codeEmail);
                verifyFormData.append('code', code);
                verifyFormData.append('csrf_token', csAuthCsrfToken());

                sendCodeBtn.disabled = true;
                sendCodeBtn.textContent = 'Verifying...';

                try {
                    var verifyResponse = await fetch('../Login/verify_code.php', { method: 'POST', body: verifyFormData });
                    var verifyResult = await verifyResponse.text();

                    if (verifyResult.trim() === 'success') {
                        setSignupSuccess('Email verified successfully! You can now set your password.');
                        verificationCodeInput.disabled = true;
                        sendCodeBtn.setAttribute('data-mode', 'verified');
                        sendCodeBtn.textContent = 'Verified';
                        csAuthStopCodeTimer('signup', document.getElementById('csAuthCodeTimer'));
                        sendCodeBtn.disabled = true;
                        updateCreateAccountState();
                        csAuthSwapStep(document.getElementById('csAuthSignupStep1'), document.getElementById('csAuthSignupStep2'));
                        setTimeout(function () { signupPasswordInput.focus(); }, 260);
                    } else {
                        setSignupError('Invalid or expired verification code.');
                        sendCodeBtn.disabled = false;
                        sendCodeBtn.textContent = 'Verify Code';
                    }
                } catch (error) {
                    setSignupError('Unable to verify the code.');
                    sendCodeBtn.disabled = false;
                    sendCodeBtn.textContent = 'Verify Code';
                }
            }
        });
    }

    /* CREATE ACCOUNT */

    if (signupForm) {
        signupForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            setSignupError('');

            var name = document.getElementById('csAuthFullName').value.trim();
            var email = document.getElementById('csAuthSignupEmail').value.trim();
            var password = document.getElementById('csAuthSignupPassword').value;
            var confirmPassword = document.getElementById('csAuthConfirmPassword').value;
            var agreeTerms = document.getElementById('csAuthAgreeTerms');

            if (!name || !email || !password || !confirmPassword) {
                setSignupError('Please complete all fields.');
                return;
            }

            if (password.length < 8) {
                setSignupError('Password must be at least 8 characters.');
                return;
            }

            if (password !== confirmPassword) {
                setSignupError('Passwords do not match.');
                return;
            }

            if (!agreeTerms || !agreeTerms.checked) {
                setSignupError('Please agree to the Terms and Conditions and Privacy Policy before creating an account.');
                return;
            }

            var formData = new FormData();
            formData.append('full_name', name);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('confirm_password', confirmPassword);
            formData.append('agree_terms', '1');
            formData.append('csrf_token', csAuthCsrfToken());

            try {
                var response = await fetch('../Login/signup.php', { method: 'POST', body: formData });
                var result = await response.text();

                if (result.trim() === 'success') {
                    setSignupSuccess('Account created successfully! You can now log in.');
                    setTimeout(function () {
                        signupForm.reset();
                        resetSignupLockState();
                        setSignupSuccess('');
                        showLoginForm();
                    }, 1400);
                } else {
                    setSignupError(result);
                }
            } catch (error) {
                setSignupError('Unable to create account.');
            }
        });
    }

    /* Forgot Password - same pattern ng Sign Up (data-mode button, naka-lock hanggat na-verify). */

    var forgotSendBtn = document.getElementById('csAuthForgotSendBtn');
    var forgotCodeInput = document.getElementById('csAuthForgotCode');
    var forgotEmailInput = document.getElementById('csAuthForgotEmail');
    var newPasswordInput = document.getElementById('csAuthNewPassword');
    var confirmNewPasswordInput = document.getElementById('csAuthConfirmNewPassword');
    var resetSubmitBtn = document.getElementById('csAuthResetSubmit');

    function updateResetSubmitState() {
        if (!resetSubmitBtn) return;
        var verified = forgotSendBtn && forgotSendBtn.getAttribute('data-mode') === 'verified';
        resetSubmitBtn.disabled = !verified;
    }

    function resetForgotLockState() {
        if (!forgotSendBtn) return;
        csAuthStopCodeTimer('forgot', document.getElementById('csAuthForgotTimer'));
        forgotSendBtn.setAttribute('data-mode', 'send');
        forgotSendBtn.disabled = false;
        forgotSendBtn.textContent = 'Send Code';
        forgotCodeInput.disabled = true;
        csAuthShowStepNow(document.getElementById('csAuthForgotStep1'), document.getElementById('csAuthForgotStep2'));
        updateResetSubmitState();
    }

    if (forgotSendBtn) {
        forgotSendBtn.addEventListener('click', async function () {

            var mode = forgotSendBtn.getAttribute('data-mode');

            if (mode === 'send') {

                var email = forgotEmailInput.value.trim();

                if (!email) {
                    csAuthShowNotice('Please enter your email.', 'error');
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'send');
                formData.append('email', email);
                formData.append('csrf_token', csAuthCsrfToken());

                forgotSendBtn.disabled = true;
                forgotSendBtn.textContent = 'Sending...';

                try {
                    var response = await fetch('../Login/forgot_password.php', { method: 'POST', body: formData });
                    var data = await response.json();

                    if (data.status === 'success') {
                        csAuthShowNotice(data.message || 'Verification code has been sent.', 'success');
                        forgotCodeInput.disabled = false;
                        forgotCodeInput.focus();
                        forgotSendBtn.setAttribute('data-mode', 'verify');
                        forgotSendBtn.textContent = 'Verify Code';
                        forgotSendBtn.disabled = false;
                        csAuthStartCodeTimer('forgot', 300, document.getElementById('csAuthForgotTimer'), forgotSendBtn, forgotCodeInput);
                    } else if (data.status === 'cooldown' && data.retry_after) {
                        /* May valid na code pa - ilagay na lang ito */
                        csAuthShowNotice('A code was already sent to your email. Enter it below.', 'success');
                        forgotCodeInput.disabled = false;
                        forgotCodeInput.focus();
                        forgotSendBtn.setAttribute('data-mode', 'verify');
                        forgotSendBtn.textContent = 'Verify Code';
                        forgotSendBtn.disabled = false;
                        csAuthStartCodeTimer('forgot', data.retry_after, document.getElementById('csAuthForgotTimer'), forgotSendBtn, forgotCodeInput);
                    } else {
                        csAuthShowNotice(data.message || 'Unable to send verification code.', 'error');
                        forgotSendBtn.textContent = 'Send Code';
                        forgotSendBtn.disabled = false;
                    }
                } catch (error) {
                    csAuthShowNotice('Unable to send verification code.', 'error');
                    forgotSendBtn.textContent = 'Send Code';
                    forgotSendBtn.disabled = false;
                }

            } else if (mode === 'verify') {

                var codeEmail = forgotEmailInput.value.trim();
                var code = forgotCodeInput.value.trim();

                if (code.length !== 6) {
                    csAuthShowNotice('Please enter the 6-digit verification code.', 'error');
                    return;
                }

                var verifyFormData = new FormData();
                verifyFormData.append('action', 'verify');
                verifyFormData.append('email', codeEmail);
                verifyFormData.append('code', code);
                verifyFormData.append('csrf_token', csAuthCsrfToken());

                forgotSendBtn.disabled = true;
                forgotSendBtn.textContent = 'Verifying...';

                try {
                    var verifyResponse = await fetch('../Login/forgot_password.php', { method: 'POST', body: verifyFormData });
                    var verifyData = await verifyResponse.json();

                    if (verifyData.status === 'success') {
                        csAuthShowNotice('Email verified! You can now set a new password.', 'success');
                        forgotCodeInput.disabled = true;
                        forgotSendBtn.setAttribute('data-mode', 'verified');
                        forgotSendBtn.textContent = 'Verified';
                        csAuthStopCodeTimer('forgot', document.getElementById('csAuthForgotTimer'));
                        forgotSendBtn.disabled = true;
                        updateResetSubmitState();
                        csAuthSwapStep(document.getElementById('csAuthForgotStep1'), document.getElementById('csAuthForgotStep2'));
                        setTimeout(function () { newPasswordInput.focus(); }, 260);
                    } else {
                        csAuthShowNotice(verifyData.message || 'Invalid or expired verification code.', 'error');
                        forgotSendBtn.disabled = false;
                        forgotSendBtn.textContent = 'Verify Code';
                    }
                } catch (error) {
                    csAuthShowNotice('Unable to verify the code.', 'error');
                    forgotSendBtn.disabled = false;
                    forgotSendBtn.textContent = 'Verify Code';
                }
            }
        });
    }

    if (forgotForm) {
        forgotForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            var email = forgotEmailInput.value.trim();
            var password = newPasswordInput.value;
            var confirmPassword = confirmNewPasswordInput.value;

            if (forgotSendBtn.getAttribute('data-mode') !== 'verified') {
                csAuthShowNotice('Please verify your email first.', 'error');
                return;
            }

            if (!password || !confirmPassword) {
                csAuthShowNotice('Please complete all fields.', 'error');
                return;
            }

            if (password.length < 8) {
                csAuthShowNotice('Password must be at least 8 characters.', 'error');
                return;
            }

            if (password !== confirmPassword) {
                csAuthShowNotice('Passwords do not match.', 'error');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'reset');
            formData.append('email', email);
            formData.append('password', password);
            formData.append('confirm_password', confirmPassword);
            formData.append('csrf_token', csAuthCsrfToken());

            try {
                var response = await fetch('../Login/forgot_password.php', { method: 'POST', body: formData });
                var data = await response.json();

                if (data.status === 'success') {
                    csAuthShowNotice(data.message || 'Your password has been reset. You can now log in.', 'success');
                    setTimeout(function () {
                        forgotForm.reset();
                        resetForgotLockState();
                        showLoginForm();
                    }, 1400);
                } else {
                    csAuthShowNotice(data.message || 'Unable to reset password.', 'error');
                }
            } catch (error) {
                csAuthShowNotice('Unable to reset password. Please try again.', 'error');
            }
        });
    }

})();
</script>
