<?php
/* ==========================================================================
   LOG IN   (Login/Login.php)
   Iisang file ng Log In: PHP backend, style, HTML ng pop up, script.

   DALAWANG GAMIT:
     1. Direktang tinatawag (fetch ng pop up, o redirect ng protected page)
        -> [PHP] BACKEND lang ang tatakbo, tapos exit.
     2. Ini-include ng Shared/navbar.php kapag hindi naka-login
        -> [STYLE] + [HTML] + [SCRIPT] ng Log In pop up.

   Bumubukas ang pop up kapag:
     - kinlick ang kahit anong may data-auth-open="login" (Log In ng navbar,
       "Log in" link ng Sign Up at Forgot Password)
     - may ?auth=login sa URL (galing sa redirect)

   LAMAN (taas pababa):
     [PHP]     [1] BACKEND (POST = log in, GET = redirect)  [2] POP UP SETUP
     [STYLE]   LOG IN POP UP
     [HTML]    LOG IN POP UP
     [SCRIPT]  [1] HELPERS  [2] BUKSAN / ISARA  [3] LOG IN
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";


/* ==========================================================================
   [PHP] [1] BACKEND  (direktang tawag lang, hindi kapag ini-include)
   strcasecmp = hindi apektado ng laki/liit ng letra ng path (Windows / XAMPP)
   ========================================================================== */
if (strcasecmp((string) realpath($_SERVER["SCRIPT_FILENAME"]), __FILE__) === 0) {

    /* sagot sa pop up (JSON). retry_after = segundo ng lockout, para sa countdown */
    function login_respond(string $status, string $message = "", string $redirect = "", int $retryAfter = 0): void
    {
        header("Content-Type: application/json");

        $payload = [
            "status"   => $status,
            "message"  => $message,
            "redirect" => $redirect,
        ];

        if ($retryAfter > 0) {
            $payload["retry_after"] = $retryAfter;
        }

        echo json_encode($payload);
        exit();
    }

    /* ---------- POST galing sa pop up: i-check ang log in ---------- */
    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "login") {

        require_once __DIR__ . "/../Admin/database.php";

        // kailangan naka-start ang session para ma-check ang csrf_token
        figurify_start_session();

        // luma ang token: bigyan ng bago para kusang mag-retry ang pop up
        if (!figurify_verify_csrf($_POST["csrf_token"] ?? null)) {
            header("Content-Type: application/json");
            echo json_encode([
                "status"     => "csrf",
                "message"    => "Your session has expired. Please refresh the page and try again.",
                "csrf_token" => figurify_csrf_token(),
            ]);
            exit();
        }

        $email    = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($email === "" || $password === "") {
            login_respond("error", "Please enter your email and password.");
        }

        // 5 maling login ng email = lock: 5 min, tapos 10 min, tapos 20 min (max). O 20 mula sa iisang IP sa 10 minuto
        $retryAfterSeconds = max(
            figurify_login_lockout_seconds_remaining($conn, $email),
            figurify_action_rate_limit_seconds_remaining($conn, "login_fail_ip", figurify_client_ip(), 20, 10)
        );

        if ($retryAfterSeconds > 0) {
            $conn->close();
            login_respond("error", "Too many failed login attempts. Please try again later.", "", $retryAfterSeconds);
        }

        // banned o naka-archive na account (helpers/security_helper.php SECTION 6)
        $blockMessage = figurify_account_block_message($conn, $email);

        if ($blockMessage !== "") {
            $conn->close();
            login_respond("error", $blockMessage);
        }

        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email, password, role, profile_picture
             FROM users
             WHERE email = ?
             LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->num_rows === 1 ? $result->fetch_assoc() : null;
        $stmt->close();

        // walang account o maling password: itala ang palpak na subok
        if (!$user || !password_verify($password, $user["password"])) {
            $lockSeconds = figurify_record_failed_login($conn, $email);
            figurify_record_action_attempt($conn, "login_fail_ip", figurify_client_ip());
            $conn->close();

            // pang-5 na palpak: lock agad (may countdown)
            if ($lockSeconds > 0) {
                login_respond("error", "Too many failed login attempts. Please try again later.", "", $lockSeconds);
            }

            login_respond("error", "Invalid email or password.");
        }

        figurify_clear_login_attempts($conn, $email);

        // bawat role ay may sariling portal (session cookie) at sariling dashboard
        $rolePortal = [
            "admin"    => "owner",
            "staff"    => "staff",
            "customer" => "customer",
        ];

        if (!isset($rolePortal[$user["role"]])) {
            $conn->close();
            login_respond("error", "Invalid account role.");
        }

        $portal = $rolePortal[$user["role"]];

        // sariling session ng portal na ito lang (hindi naaapektuhan ang ibang naka-login na portal)
        session_write_close();
        figurify_start_session($portal);

        // bagong session ID pagkatapos mag-login (session fixation prevention)
        session_regenerate_id(true);

        $_SESSION["user_id"]         = $user["user_id"];
        $_SESSION["full_name"]       = $user["full_name"];
        $_SESSION["email"]           = $user["email"];
        $_SESSION["role"]            = $user["role"];
        $_SESSION["profile_picture"] = $user["profile_picture"];

        // Online / Offline sa User Management (owner)
        figurify_touch_last_seen();

        // Remember me - mananatiling naka-login sa device na ito (30 araw)
        if (($_POST["remember"] ?? "") === "1") {
            figurify_remember_issue($conn, (int) $user["user_id"], $portal);
        }

        $conn->close();

        // base path ng project para tama ang redirect kahit nasa subfolder
        $documentRoot = rtrim(str_replace("\\", "/", realpath($_SERVER["DOCUMENT_ROOT"])), "/");
        $projectRoot  = rtrim(str_replace("\\", "/", realpath(__DIR__ . "/..")), "/");
        $siteBase     = substr($projectRoot, strlen($documentRoot)) . "/";

        $portalDashboard = [
            "owner"    => "owner/ownerdashboard.php",
            "staff"    => "staff/staffdashboard.php",
            "customer" => "Home/Home.php",
        ];

        login_respond("success", "", $siteBase . $portalDashboard[$portal]);
    }

    /* ---------- GET (direktang link o redirect ng protected page) ----------
       Walang sariling login page - papunta sa Home, may ?auth=login para
       kusang bumukas ang pop up kung hindi pa naka-login. */
    figurify_start_session();

    header("Location: ../Home/Home.php" . (isset($_SESSION["user_id"]) ? "" : "?auth=login"));
    exit();
}
/* ====================== END BACKEND ====================== */


/* ==========================================================================
   [PHP] [2] POP UP SETUP  (kapag ini-include ng navbar)
   ========================================================================== */
// security token, kasama sa bawat submit
$loginCsrfToken = figurify_csrf_token();
/* ====================== END POP UP SETUP ====================== */
?>

<style>
/* ==========================================================================
   [STYLE] LOG IN POP UP
   Lahat ay may login- prefix para hindi madamay ang page.
   ========================================================================== */

/* ---------- madilim na background + card ---------- */
.login-overlay {
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

.login-overlay.active { opacity: 1; visibility: visible; }

/* hindi ma-scroll ang page habang bukas ang pop up */
body.login-popup-open { overflow: hidden; }

.login-modal {
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

.login-overlay.active .login-modal { transform: translateY(0) scale(1); }

/* lipat galing Sign Up / Forgot Password: walang animation, parang iisang card lang */
.login-overlay.login-overlay-instant,
.login-overlay.login-overlay-instant .login-modal { transition: none; }

.login-modal * { box-sizing: border-box; }

/* ang laman lang ang nag-i-scroll; nakapirmi ang X button */
.login-scroll {
    overflow-y: auto;
    padding: 28px 28px 22px;
    scrollbar-width: thin;
    scrollbar-color: #e9d5e1 transparent;
}

/* ---------- X button ---------- */
.login-close {
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

.login-close:hover { background: #fbf3f7; border-color: #f1cfdd; color: #c0325e; }

.login-close svg {
    width: 15px;
    height: 15px;
    stroke: currentColor;
    stroke-width: 2.4;
    stroke-linecap: round;
}

/* ---------- logo + title + subtitle ---------- */
.login-header { margin-bottom: 20px; text-align: center; }

.login-logo {
    display: block;
    width: 56px;
    height: 44px;
    margin: 0 auto 10px;
    object-fit: cover;   /* tinatanggal ang puting gilid ng logo.png */
}

.login-header h2 {
    margin: 0 0 6px;
    font-family: 'Fraunces', Georgia, 'Times New Roman', serif;
    font-size: 25px;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -.2px;
    color: #43142f;
}

.login-header p { margin: 0; font-size: 13.5px; font-weight: 500; color: #6e5767; }

/* ---------- field: label sa loob ng box, umaakyat sa border kapag may laman ---------- */
.login-field { position: relative; margin-bottom: 14px; }

.login-field > label {
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
.login-field:focus-within > label,
.login-field:has(input:not(:placeholder-shown)) > label {
    top: 0;
    left: 12px;
    font-size: 11.5px;
    font-weight: 700;
    background: #fff;
}
.login-field:has(input:not(:placeholder-shown)) > label { color: #2d1a27; }

/* autofill ng browser - hiwalay na rule para hindi madamay ang nasa itaas sa browser na hindi ito kilala */
.login-field:has(input:-webkit-autofill) > label {
    top: 0;
    left: 12px;
    font-size: 11.5px;
    font-weight: 700;
    background: #fff;
    color: #2d1a27;
}

/* naka-focus: crimson ang label (pangalawang selector = mas malakas sa "may laman" na rule) */
.login-field:focus-within > label,
.login-field:focus-within:has(input:not(:placeholder-shown)) > label { color: #c0325e; }

/* ---------- icon sa kaliwa ng box ---------- */
.login-icon {
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

.login-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.login-field:focus-within .login-icon { color: #c0325e; }

/* ---------- input ---------- */
.login-input-wrap { position: relative; }

.login-input-wrap input {
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

.login-input-wrap input:focus {
    border-color: #c0325e;
    box-shadow: 0 0 0 4px rgba(192, 50, 94, .12);
}

/* ---------- show / hide password (mata) ---------- */
.login-has-eye input { padding-right: 44px; }

.login-input-wrap .login-eye {
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

.login-input-wrap .login-eye:hover { color: #c0325e; background: #fbf3f7; }

.login-eye svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* bukas na mata = nakatago ang password; may .is-visible = nakikita */
.login-eye .login-eye-closed { display: none; }
.login-eye.is-visible .login-eye-open { display: none; }
.login-eye.is-visible .login-eye-closed { display: block; }

/* ---------- Remember me + Forgot password ---------- */
.login-row-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 4px 0 0;
}

.login-remember {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #4a3a45;
    cursor: pointer;
    user-select: none;
}

.login-remember input {
    width: 16px;
    height: 16px;
    margin: 0;
    accent-color: #c0325e;
    cursor: pointer;
}

.login-forgot-link {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #c0325e;
    text-decoration: none;
}

/* ---------- Log In button ---------- */
.login-submit-button {
    display: block;
    width: 100%;
    height: 46px;
    margin: 16px 0 0;
    padding: 0 20px;
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

.login-submit-button:hover { background: #a82a52; }
.login-submit-button:disabled { opacity: .55; cursor: not-allowed; }

/* ---------- "Don't have an account? Sign up" ---------- */
.login-switch {
    margin-top: 16px;
    text-align: center;
    font-size: 13px;
    font-weight: 500;
    color: #6e5767;
}

.login-switch a {
    margin-left: 2px;
    color: #c0325e;
    font-weight: 700;
    text-decoration: none;
}

/* underline kapag tinapatan ang link */
.login-forgot-link:hover,
.login-switch a:hover { text-decoration: underline; }

/* ---------- phone: mas maliit na pop up ---------- */
@media (max-width: 480px) {
    .login-modal { border-radius: 22px; }
    .login-scroll { padding: 26px 18px 20px; }
    .login-header h2 { font-size: 22px; }
}
/* ====================== END LOG IN POP UP (STYLE) ====================== */
</style>


<!-- ==========================================================================
     [HTML] LOG IN POP UP
     Tinatawag ng script ang Login/Login.php (ito ring file) gamit ang fetch.
     ========================================================================== -->
<div class="login-overlay" id="loginOverlay" data-auth-popup aria-hidden="true">
    <div class="login-modal" role="dialog" aria-modal="true" aria-labelledby="loginHeading">

        <!-- security token, kasama sa bawat submit -->
        <input type="hidden" id="loginCsrfToken" data-auth-csrf value="<?php echo htmlspecialchars($loginCsrfToken); ?>">

        <button type="button" class="login-close" id="loginCloseButton" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>
        </button>

        <div class="login-scroll">
            <form id="loginForm" autocomplete="on">

                <div class="login-header">
                    <img class="login-logo" src="../Image/brand/logo.png" alt="Clay and Stuff">
                    <h2 id="loginHeading">Welcome</h2>
                    <p>Log in to continue your Clay &amp; Stuff journey.</p>
                </div>

                <div class="login-field">
                    <label for="loginEmail">Email</label>
                    <div class="login-input-wrap">
                        <span class="login-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg></span>
                        <input type="email" id="loginEmail" name="email" placeholder=" " autocomplete="username" required>
                    </div>
                </div>

                <div class="login-field">
                    <label for="loginPassword">Password</label>
                    <div class="login-input-wrap login-has-eye">
                        <span class="login-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></svg></span>
                        <input type="password" id="loginPassword" name="password" placeholder=" " autocomplete="current-password" required>
                        <button type="button" class="login-eye" data-target="loginPassword" aria-label="Show password">
                            <svg class="login-eye-open" viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="login-eye-closed" viewBox="0 0 24 24"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.06 21.06 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.06 21.06 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>

                <div class="login-row-between">
                    <label class="login-remember" for="loginRemember">
                        <input type="checkbox" id="loginRemember" name="remember" value="1">
                        <span>Remember me</span>
                    </label>
                    <!-- bubuksan ang Forgot Password pop up (Login/forgot_password.php) -->
                    <a href="#" class="login-forgot-link" data-auth-open="forgot">Forgot password?</a>
                </div>

                <button type="submit" class="login-submit-button" id="loginSubmitButton">Log In</button>

                <!-- bubuksan ang Sign Up pop up (Login/signup.php) -->
                <div class="login-switch">
                    Don't have an account? <a href="#" data-auth-open="signup">Sign up</a>
                </div>

            </form>
        </div>

    </div>
</div>
<!-- ====================== END LOG IN POP UP (HTML) ====================== -->


<script>
(function () {

    const overlay      = document.getElementById("loginOverlay");
    const form         = document.getElementById("loginForm");
    const csrfInput    = document.getElementById("loginCsrfToken");
    const submitButton = document.getElementById("loginSubmitButton");


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

    // segundo -> "m:ss" (hal. 75 -> "1:15")
    function formatTime(totalSeconds) {
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
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
    }
    /* ====================== END HELPERS ====================== */


    /* ==========================================================================
       [SCRIPT] [2] BUKSAN / ISARA
       Iisang event ("figurify:auth-popup") ang gamit ng Log In, Sign Up at
       Forgot Password: bubukas ang may tamang pangalan, sasara ang iba.
       switching = may bukas nang pop up -> lipat agad, walang animation.
       ========================================================================== */
    function setOpen(isOpen, instant) {
        if (instant) {
            overlay.classList.add("login-overlay-instant");
        }

        overlay.classList.toggle("active", isOpen);
        overlay.setAttribute("aria-hidden", isOpen ? "false" : "true");
        document.body.classList.toggle("login-popup-open", isOpen);

        if (instant) {
            void overlay.offsetWidth;   // ilapat agad bago ibalik ang animation
            overlay.classList.remove("login-overlay-instant");
        }
    }

    // "login" / "signup" / "forgot"
    function requestAuthPopup(name) {
        const switching = document.querySelector("[data-auth-popup].active") !== null;
        document.dispatchEvent(new CustomEvent("figurify:auth-popup", { detail: { name: name, switching: switching } }));
    }

    document.addEventListener("figurify:auth-popup", function (event) {
        if (event.detail.name === "login") {
            setOpen(true, event.detail.switching);
        } else if (overlay.classList.contains("active")) {
            setOpen(false, true);
        }
    });

    // kahit anong may data-auth-open="login" (Log In ng navbar, "Log in" link ng ibang pop up)
    document.addEventListener("click", function (event) {
        const trigger = event.target.closest('[data-auth-open="login"]');
        if (!trigger) return;

        event.preventDefault();
        requestAuthPopup("login");
    });

    // Submit Order / Proceed to Payment na hindi pa naka-login: bubukas agad ang Log In (o Sign Up).
    // Pagka-login, bubuksan ulit ang returnUrl (hal. Order Summary) na naka-login na at
    // nandoon pa rin ang napili, at tuloy-tuloy na ang Submit Order / Proceed to Payment.
    let returnUrl = "";

    window.figurifyRequireLogin = function (url) {
        returnUrl = url || "";
        requestAuthPopup("login");
    };

    // X button lang ang nagsasara (hindi click sa labas o Escape)
    document.getElementById("loginCloseButton").addEventListener("click", function () {
        returnUrl = "";
        setOpen(false, false);
    });

    // show / hide password
    overlay.addEventListener("click", function (event) {
        const eyeButton = event.target.closest(".login-eye");
        if (!eyeButton) return;

        const input = document.getElementById(eyeButton.getAttribute("data-target"));
        const nowVisible = input.type === "password";

        input.type = nowVisible ? "text" : "password";
        eyeButton.classList.toggle("is-visible", nowVisible);
        eyeButton.setAttribute("aria-label", nowVisible ? "Hide password" : "Show password");
    });

    // ?auth=login (o kahit anong ?auth= maliban sa signup) = buksan agad; ?auth_error= = ipakita ang error.
    // Tinatanggal sa URL pagkatapos para hindi bumukas ulit kapag nag-refresh.
    const params = new URLSearchParams(window.location.search);

    if (params.has("auth") && params.get("auth") !== "signup") {
        requestAuthPopup("login");

        if (params.get("auth_error")) {
            showError(params.get("auth_error"));
        }

        params.delete("auth");
        params.delete("auth_error");

        const cleanQuery = params.toString();
        window.history.replaceState({}, document.title, window.location.pathname + (cleanQuery ? "?" + cleanQuery : "") + window.location.hash);
    }
    /* ====================== END BUKSAN / ISARA ====================== */


    /* ==========================================================================
       [SCRIPT] [3] LOG IN
       ========================================================================== */

    // kapag luma na ang token (hal. may ginawa sa ibang tab), kukuha ng bago at uulitin nang isang beses
    function sendLogin(email, password, isRetry) {
        const formData = new FormData();
        formData.append("action", "login");
        formData.append("email", email);
        formData.append("password", password);
        if (document.getElementById("loginRemember").checked) {
            formData.append("remember", "1");
        }
        formData.append("csrf_token", csrfInput.value);

        return fetch("../Login/Login.php", { method: "POST", body: formData })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.status === "csrf" && data.csrf_token && !isRetry) {
                    // bagong token sa lahat ng pop up (Log In, Sign Up, Forgot Password)
                    document.querySelectorAll("[data-auth-csrf]").forEach(function (input) {
                        input.value = data.csrf_token;
                    });
                    return sendLogin(email, password, true);
                }
                return data;
            });
    }

    form.addEventListener("submit", function (event) {
        event.preventDefault();

        const email = document.getElementById("loginEmail").value.trim();
        const password = document.getElementById("loginPassword").value.trim();

        if (!email || !password) {
            showError("Please enter your email and password.");
            return;
        }

        setButton(submitButton, "Logging in...", true);

        sendLogin(email, password, false)
            .then(function (data) {
                if (data.status === "success" && /Home\/Home\.php$/.test(data.redirect)) {
                    // customer: buksan ulit ang parehong page (o ang returnUrl) na naka-login na - hindi nabubura ang napili
                    window.location.href = returnUrl || window.location.href;

                } else if (data.status === "success") {
                    // staff / owner: papunta sa dashboard
                    window.location.href = data.redirect;

                } else if (data.retry_after) {
                    // sobrang daming maling subok: naka-lock, may bilang pababa sa button
                    submitButton.disabled = true;

                    // isang pop up lang habang may countdown: text lang ang inuupdate, hindi na ginagawa ulit
                    let lockToast = null;

                    startCountdown(
                        data.retry_after,
                        function (secondsLeft) {
                            const timeText = formatTime(secondsLeft);
                            const message = "Too many failed login attempts. Try again in " + timeText + ".";

                            if (!lockToast && window.csToast) {
                                lockToast = window.csToast(message, "error", data.retry_after * 1000);
                            } else if (lockToast && lockToast.isConnected) {
                                lockToast.querySelector(".cs-toast-message").textContent = message;
                            }

                            submitButton.textContent = "Try again in " + timeText;
                        },
                        function () {
                            if (lockToast && lockToast.isConnected) {
                                lockToast.click();
                            }
                            setButton(submitButton, "Log In", false);
                        }
                    );

                } else {
                    showError(data.message || "Invalid email or password.");
                    setButton(submitButton, "Log In", false);
                }
            })
            .catch(function () {
                showError("Unable to log in right now. Please try again.");
                setButton(submitButton, "Log In", false);
            });
    });
    /* ====================== END LOG IN ====================== */

})();
</script>
<!-- ====================== END LOG IN (Login/Login.php) ====================== -->
