<?php
/* ==========================================================================
   FIGURIFY - ALL-IN-ONE   (Shared/all-in-one.php)
   ==========================================================================
   Iisang file para sa mga bagay na PAREHO sa BUONG system (customer, owner,
   staff). Pinagsama dito ang dating:
       - Shared/all-in-one.css   (font + background)
       - Shared/cs-popup.php     (pop up / toast / confirm / CSRF)
       - Shared/saved-toast.php  ("Changes saved" pop up)

   LAMAN (hanapin ang [1], [2] ... para makita agad):
       [1] PHP  - POP UP FUNCTIONS   figurify_flash / figurify_toast_now /
                                     figurify_saved_toast
       [2] CSS  - FONT               Fraunces (headings) + Plus Jakarta Sans (body)
       [3] CSS  - BACKGROUND         cream #FFF8F6 sa lahat ng page
       [4] CSS  - POP UP DESIGN      toast, confirm, decline/refund pop up, buttons
       [5] JS   - POP UP             csToast(), csConfirm(), csConfirmSubmit()
       [6] JS   - LINIS NG URL       tinatanggal ang ?saved=1, ?sent=1, ?progress_error=
       [7] JS   - CSRF               security token sa bawat POST form (HUWAG ALISIN)
       [8] JS   - FLASH MESSAGE      pop up na galing sa redirect (figurify_flash)

   PAANO GAMITIN
       Sa <head> ng bawat page (isang linya lang):
           <?php include __DIR__ . "/../Shared/all-in-one.php"; ?>
       (sa mga file na nasa Shared folder:  __DIR__ . "/all-in-one.php")

       Sa redirect / action file (walang HTML, functions lang):
           define("ALL_IN_ONE_FUNCTIONS_ONLY", true);
           require_once __DIR__ . "/../Shared/all-in-one.php";
           figurify_flash("Order approved.", "success");

       Sa JS:
           csToast("Saved!", "success");                     // success | error | info
           csConfirm("Delete this?").then(ok => { ... });
           <form onsubmit="return csConfirmSubmit(this, 'Delete this?');">
   ========================================================================== */


/* ==========================================================================
   [1] PHP - POP UP FUNCTIONS
       figurify_flash($message, $type)    - pop up sa SUSUNOD na page (bago mag-redirect)
       figurify_take_flashes()            - kinukuha at binubura ang naka-save na flash
       figurify_toast_now($message, $type)- pop up agad sa kasalukuyang page
       figurify_saved_toast($message)     - "Changes saved" pop up (Content / Collection)
   ========================================================================== */

if (!function_exists("figurify_flash")) {

    function figurify_flash(string $message, string $type = "success"): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!in_array($type, ["success", "error", "info"], true)) {
            $type = "success";
        }

        if (!isset($_SESSION["cs_flash"]) || !is_array($_SESSION["cs_flash"])) {
            $_SESSION["cs_flash"] = [];
        }

        $_SESSION["cs_flash"][] = [
            "message" => $message,
            "type"    => $type,
        ];
    }

}

if (!function_exists("figurify_take_flashes")) {

    function figurify_take_flashes(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return [];
        }

        $flashes = (isset($_SESSION["cs_flash"]) && is_array($_SESSION["cs_flash"]))
            ? $_SESSION["cs_flash"]
            : [];

        unset($_SESSION["cs_flash"]);

        return $flashes;
    }

}

if (!function_exists("figurify_toast_now")) {

    /* Pop up agad sa kasalukuyang page (hal. may mali sa form). Ilagay kahit saan sa <body>. */
    function figurify_toast_now(string $message, string $type = "error"): string
    {
        if (!in_array($type, ["success", "error", "info"], true)) {
            $type = "error";
        }

        $payload = json_encode(
            ["message" => $message, "type" => $type],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return "<script>(function(){var t=" . $payload . ";function s(){if(window.csToast){window.csToast(t.message,t.type);}}"
            . "if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',s);}else{s();}})();</script>";
    }

}

if (!function_exists("figurify_saved_toast")) {

    /* "Changes saved" pop up - berde (success). Ang ?saved=1 sa URL ay
       tinatanggal ng [6] LINIS NG URL para hindi na lumabas ulit pag ni-refresh. */
    function figurify_saved_toast(string $message = "Changes saved."): string
    {
        return figurify_toast_now($message, "success");
    }

}


/* redirect / action files - functions lang, walang HTML */
if (defined("ALL_IN_ONE_FUNCTIONS_ONLY") || defined("CS_POPUP_FUNCTIONS_ONLY")) {
    return;
}

/* isang beses lang ilalabas ang style/script kahit ilang beses ma-include */
if (defined("ALL_IN_ONE_INCLUDED")) {
    return;
}
define("ALL_IN_ONE_INCLUDED", true);

$csPopupFlashes = figurify_take_flashes();
?>
<style>
/* ==========================================================================
   [2] CSS - FONT
       Fraunces           = headings (page title, card title, malalaking numero)
       Plus Jakarta Sans  = body, forms, tables, buttons (lahat ng iba pa)
       Walang ibang page o CSS file na may sariling font-family - dito lang.
   ========================================================================== */

@import url('https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500;1,9..144,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

:root {
    --font-heading: 'Fraunces', Georgia, 'Times New Roman', serif;
    --font-body: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
    --page-bg: #FFF8F6;     /* [3] BACKGROUND - kulay ng lahat ng page */
}

html,
body {
    font-family: var(--font-body);
}

/* form controls - hindi sila nagmamana ng font by default */
button,
input,
select,
textarea,
optgroup {
    font-family: inherit;
}

/* lahat ng heading sa system */
.mo-eyebrow,
.mo-hero h1,
.orders-page .mo-card-title,
.orders-page .mo-sum-total span:last-child,
.hero h1,
.orders-header h1,
.progress-update-title,
.summary-title,
.ps-eyebrow,
.ps-hero h1,
.ps-card h2,
.page-eyebrow,
.page-hero h1,
.pay-card-header h2,
.payment-total-box strong,
#customReferenceForm .section-header h2,
#customReferenceForm .details-box h3,
#customReferenceForm .box-addon-box-heading h3,
#customReferenceForm .receipt-header h3,
#customReferenceForm .summary-total strong,
#dressUpModule .detail-choice-card strong,
#dressUpModule .figure-style-heading,
#dressUpModule .figure-style-card span,
#dressUpModule .preview-header h2,
#dressUpModule .page-title h1,
#dressUpModule .section-title h2,
#dressUpModule .figure-style-kicker,
#dressUpModule .figure-summary-description,
body[data-page="finalSummary"] #dressUpModule #summaryReceiptItems .figure-summary-description,
body[data-page="finalSummary"] #dressUpModule #summaryBoxItems .figure-summary-description,
#dressUpModule .dressup-cart-title,
#dressUpModule .section-title .design-step-jump select,
#dressUpModule .os-card .os-title,
#dressUpModule #chibiTypeSection .option-card > span,
#dressUpModule #hironoTypeSection .figure-model-card > span,
.section-header h2,
.method-content h3,
.form-title h3,
.order-card h4,
.details-box h3,
.box-addon-box-heading h3,
.upload-title,
.receipt-header h3,
.cart-item-name-sub,
.figure-summary-name,
.notice-message,
.receipt .cart-card-title,
.profile-form-title,
.legal-section h2,
body.cs-dash .portal-title,
body.cs-dash .card-header h3,
body.cs-dash .stat-card .stat-number,
body.cs-dash .ship-title,
.terms-modal-top h3,
body .app .calendar-page-controls h3,
body .app #dayBookingsTitle,
.um-card-title,
.um-modal-title,
body .app .op-title,
body .app .qv-right-col .qv-total-line-value,
body .app .cmx-title,
body .app .content-form .content-section-header h3,
.cs-confirm-title,
body .decline-modal-overlay .decline-modal-title,
body .portal-title,
body .app .qv-panel-title,
body .app .qv-total-row > strong,
body .decline-modal-title,
.cs-auth-modal .cs-auth-header h2,
body .app .qv-shell .qv-right-col .qv-total-line-value,
body .app .qv-shell .qv-right-col .qv-summary .qv-price-row > strong,
body .progress-modal-top h3,
body .decline-modal-eyebrow,
.cs-footer-cta h3,
.cs-footer-logo strong,
.navbar-wrapper .brand span,
.notif-panel-header,
.st-card-title,
.st-kpi-value,
.eyebrow,
.block-heading .eyebrow,
.block-heading h2,
.card-body h3,
.page-home h1,
.page-home h2,
.page-home h3,
.page-review h1,
body[data-page="finalSummary"] .selected-order-value {
    font-family: var(--font-heading);
}


/* maliit na badge sa loob ng heading - body font pa rin */

#dressUpModule .receipt-header .figure-index-badge {
    font-family: var(--font-body);
}


/* ==========================================================================
   [3] CSS - BACKGROUND
       Iisang kulay (cream) para sa lahat ng page - customer, owner at staff.
       Walang ibang page o CSS file na may sariling background ng page.
   ========================================================================== */

html,
body {
    background: var(--page-bg) !important;
    background-image: none !important;
}


/* ==========================================================================
   [4] CSS - POP UP DESIGN
       toast (maliit na mensahe sa itaas), confirm (Cancel / OK),
       Decline / Reject / Refund pop up ng owner at staff, at mga button nila
   ========================================================================== */

/* ===== Pop ups (toast + confirm + modals) — Figurify redesign =====
   Primary #C2325F · Plum #4A1D3B · Danger #B83227 · Success #2F8F67 */

/* ---------- toast ---------- */
.cs-toast-container{
    position:fixed;
    top:84px;
    left:50%;
    transform:translateX(-50%);
    z-index:10050;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:10px;
    width:auto;
    max-width:min(440px, calc(100% - 32px));
    pointer-events:none;
}

.cs-toast{
    display:flex;
    align-items:center;
    gap:12px;
    box-sizing:border-box;
    min-width:260px;
    max-width:100%;
    padding:16px 22px;
    background:#fff;
    border-radius:14px;
    border:1px solid #f3dde5;
    box-shadow:0 14px 34px -12px rgba(74, 29, 59, .28), 0 2px 6px rgba(74, 29, 59, .06);
    opacity:0;
    transform:translateY(-8px);
    transition:opacity .2s ease, transform .2s ease;
    pointer-events:auto;
    cursor:pointer;
    text-align:left;
    
}
.cs-toast.cs-toast-show{ opacity:1; transform:translateY(0); }
.cs-toast.cs-toast-hide{ opacity:0; transform:translateY(-6px); }

/* kulay na tuldok sa halip na icon */
.cs-toast-icon{
    flex-shrink:0;
    width:9px;
    height:9px;
    border-radius:50%;
    background:#c2325f;
    color:transparent;
    overflow:hidden;
}
.cs-toast-icon svg{ display:none; }
.cs-toast-success .cs-toast-icon{ background:#2f8f67; }
.cs-toast-error   .cs-toast-icon{ background:#b83227; }
.cs-toast-info    .cs-toast-icon{ background:#c2325f; }

.cs-toast-message{
    flex:1;
    color:#4a1d3b;
    font-size:14.5px;
    font-weight:700;
    line-height:1.45;
}

/* ---------- confirm dialog ---------- */
.cs-confirm-overlay{
    position:fixed;
    inset:0;
    z-index:10060;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:16px;
    background:rgba(45, 14, 32, .42);
    -webkit-backdrop-filter:blur(3px);
    backdrop-filter:blur(3px);
    opacity:0;
    transition:opacity .18s ease;
}
.cs-confirm-overlay.cs-confirm-show{ opacity:1; }

.cs-confirm{
    width:100%;
    max-width:400px;
    box-sizing:border-box;
    padding:30px 28px 26px;
    background:#fff;
    border:1px solid #f3dde5;
    border-radius:22px;
    box-shadow:0 28px 64px -20px rgba(45, 14, 32, .45);
    text-align:center;
    
    transform:translateY(10px) scale(.97);
    transition:transform .2s ease;
}
.cs-confirm-overlay.cs-confirm-show .cs-confirm{ transform:translateY(0) scale(1); }

/* walang icon sa design */
.cs-confirm-icon{ display:none; }

.cs-confirm-title{
    margin:0;
    
    color:#4a1d3b;
    font-size:22px;
    font-weight:600;
    line-height:1.3;
}
.cs-confirm.cs-confirm-danger .cs-confirm-title{ color:#b83227; }

.cs-confirm-message{
    margin:6px 0 0;
    color:#7a5a6c;
    font-size:14.5px;
    line-height:1.5;
    white-space:pre-line;
}

.cs-confirm-actions{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
    margin-top:22px;
}

.cs-confirm-btn{
    padding:13px 16px;
    border-radius:12px;
    font-size:15px;
    font-weight:700;
    
    cursor:pointer;
    transition:background-color .15s ease, border-color .15s ease, transform .1s ease;
}
.cs-confirm-btn:active{ transform:translateY(1px); }

/* Cancel / Keep = outline sa kaliwa, action = filled sa kanan */
.cs-confirm-cancel{
    order:1;
    background:#fff;
    color:#c2325f;
    border:1.5px solid #c2325f;
}
.cs-confirm-cancel:hover{ background:#ffedf2; }

.cs-confirm-ok{
    order:2;
    background:#c2325f;
    color:#fff;
    border:1.5px solid #c2325f;
}
.cs-confirm-ok:hover{ background:#a62a50; border-color:#a62a50; }

.cs-confirm.cs-confirm-danger .cs-confirm-ok{ background:#b83227; border-color:#b83227; }
.cs-confirm.cs-confirm-danger .cs-confirm-ok:hover{ background:#9a281f; border-color:#9a281f; }

.cs-confirm-btn:focus{ outline:none; box-shadow:none; }
.cs-confirm-btn:focus-visible{ outline:2px solid rgba(194, 50, 95, .4); outline-offset:2px; }

/* ---------- Decline / Reject / Refund pop up (owner + staff) ---------- */
body .decline-modal-overlay{
    background:rgba(45, 14, 32, .42) !important;
    -webkit-backdrop-filter:blur(3px);
    backdrop-filter:blur(3px);
    padding:16px !important;
}
body .decline-modal-overlay .decline-modal-box{
    max-width:440px !important;
    padding:28px 26px 24px !important;
    border-radius:22px !important;
    gap:12px !important;
    background:#fff !important;
    border:1px solid #f3dde5 !important;
    box-shadow:0 28px 64px -20px rgba(45, 14, 32, .45) !important;
    
    text-align:left;
}
body .decline-modal-overlay .decline-modal-box::before{ content:none !important; display:none !important; }
body .decline-modal-overlay .decline-modal-title{
    
    font-size:22px !important;
    font-weight:600 !important;
    color:#4a1d3b !important;
    margin:0 !important;
}
body .decline-modal-overlay .decline-modal-desc{
    font-size:14px !important;
    line-height:1.5 !important;
    color:#7a5a6c !important;
    margin:0 0 4px !important;
}
body .decline-modal-overlay .decline-reason-select,
body .decline-modal-overlay .decline-reason-other,
body .decline-modal-overlay select,
body .decline-modal-overlay textarea,
body .decline-modal-overlay input[type="text"],
body .decline-modal-overlay input[type="number"],
body .decline-modal-overlay input[type="file"]{
    width:100%;
    box-sizing:border-box;
    padding:11px 12px !important;
    border:1.5px solid #f0d3de !important;
    border-radius:12px !important;
    font-size:14px !important;
    
    color:#4a1d3b;
    background:#fff;
}
body .decline-modal-overlay select:focus,
body .decline-modal-overlay textarea:focus,
body .decline-modal-overlay input:focus{
    outline:none;
    border-color:#c2325f !important;
    box-shadow:0 0 0 3px rgba(194, 50, 95, .14);
}

/* ---------- action buttons (decline / progress pu-modal) ---------- */
body .pu-modal-actions,
body .decline-modal-overlay .decline-modal-actions{
    display:flex !important;
    justify-content:center !important;
    flex-wrap:wrap;
    gap:12px !important;
    margin-top:10px !important;
}
body .pu-modal-actions .pu-btn,
body .decline-modal-overlay .decline-modal-actions .decline-modal-btn{
    flex:0 0 auto !important;
    width:auto !important;
    min-width:132px;
    height:44px !important;
    padding:0 22px !important;
    border-radius:12px !important;
    font-size:14px !important;
    font-weight:700 !important;
    
    cursor:pointer;
    transition:background-color .15s ease;
}

/* Cancel = outline (kaliwa), Send/Confirm = filled (kanan) */
body .decline-modal-overlay .decline-modal-actions > .decline-modal-btn-cancel,
body .pu-modal-actions .pu-btn-cancel{
    order:1;
    background:#fff !important;
    color:#c2325f !important;
    border:1.5px solid #c2325f !important;
}
body .decline-modal-overlay .decline-modal-actions > .decline-modal-btn-cancel:hover,
body .pu-modal-actions .pu-btn-cancel:hover{ background:#ffedf2 !important; }

body .decline-modal-overlay .decline-modal-actions > .decline-modal-btn-send,
body .decline-modal-overlay .decline-modal-actions > form,
body .pu-modal-actions .pu-btn-send{
    order:2;
}
body .decline-modal-overlay .decline-modal-btn-send,
body .pu-modal-actions .pu-btn-send{
    background:#b83227 !important;
    border:1.5px solid #b83227 !important;
    color:#fff !important;
}
body .decline-modal-overlay .decline-modal-btn-send:hover,
body .pu-modal-actions .pu-btn-send:hover{ background:#9a281f !important; border-color:#9a281f !important; }

/* walang X sa owner/staff pop up - may Cancel na sa ilalim */
body .decline-modal-overlay .decline-modal-close,
body .decline-modal-overlay .modal-close-x,
body .progress-modal-close{ display:none !important; }

@media (max-width:520px){
    .cs-toast-container{ max-width:calc(100% - 24px); }
    .cs-toast{ min-width:0; }
}
</style>


<!-- ==========================================================================
     [5] JS - POP UP:  csToast(), csConfirm(), csConfirmSubmit()
     ========================================================================== -->
<script>
(function () {

    if (window.csToast) {
        return;
    }

    var SVG = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 6.5v7"/><circle cx="12" cy="18" r=".6" fill="currentColor"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><circle cx="12" cy="6.5" r=".6" fill="currentColor"/><path d="M12 11v7"/></svg>',
        ask: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9.2 9.3a2.9 2.9 0 0 1 5.6 1c0 1.9-2.8 2.5-2.8 4.2"/><circle cx="12" cy="18.2" r=".7" fill="currentColor"/></svg>',
        danger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5"/><circle cx="12" cy="16.8" r=".7" fill="currentColor"/><path d="M10.3 3.9 2.6 17.5A2 2 0 0 0 4.3 20.5h15.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>'
    };

    var DANGER_WORDS = /\b(decline|reject|delete|remove|cancel order|cancel this|reset|refund|start over|log out)\b/i;

    function getContainer() {
        var container = document.getElementById("csToastContainer");

        if (!container) {
            container = document.createElement("div");
            container.id = "csToastContainer";
            container.className = "cs-toast-container";
            (document.body || document.documentElement).appendChild(container);
        }

        return container;
    }

    /* csToast(message, type) - lalabas sa taas-gitna, kusang mawawala after 3 seconds (o i-click) */
    window.csToast = function (message, type, duration) {

        type = (type === "success" || type === "info") ? type : "error";

        var container = getContainer();

        // isa lang ang nakikita, papalitan ang luma para hindi magpatong
        container.innerHTML = "";

        var toast = document.createElement("div");
        toast.className = "cs-toast cs-toast-" + type;
        toast.setAttribute("role", "status");
        toast.setAttribute("aria-live", "polite");

        var icon = document.createElement("div");
        icon.className = "cs-toast-icon";
        icon.innerHTML = SVG[type];

        var text = document.createElement("div");
        text.className = "cs-toast-message";
        text.textContent = message;

        toast.appendChild(icon);
        toast.appendChild(text);
        container.appendChild(toast);

        function removeToast() {
            toast.classList.remove("cs-toast-show");
            toast.classList.add("cs-toast-hide");
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 250);
        }

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.classList.add("cs-toast-show");
            });
        });

        toast.addEventListener("click", removeToast);
        setTimeout(removeToast, duration || 3000);

        return toast;
    };

    /* hatiin ang mensahe: "Tanong?\nDetalye" o "Tanong? Detalye" -> title + body */
    function splitMessage(message) {
        message = String(message || "").trim();

        var nl = message.indexOf("\n");
        if (nl > 0) {
            return { title: message.slice(0, nl).trim(), body: message.slice(nl + 1).trim() };
        }

        var q = message.indexOf("? ");
        if (q > 0) {
            return { title: message.slice(0, q + 1).trim(), body: message.slice(q + 2).trim() };
        }

        return { title: message, body: "" };
    }

    /* csConfirm(message, {title, okText, cancelText, danger}) -> Promise<boolean> */
    window.csConfirm = function (message, options) {

        options = options || {};

        var parts = options.title
            ? { title: options.title, body: String(message || "") }
            : splitMessage(message);

        var okText = options.okText || "Confirm";
        var danger = (typeof options.danger === "boolean")
            ? options.danger
            : DANGER_WORDS.test(okText + " " + parts.title);

        return new Promise(function (resolve) {

            var overlay = document.createElement("div");
            overlay.className = "cs-confirm-overlay";
            overlay.setAttribute("role", "dialog");
            overlay.setAttribute("aria-modal", "true");

            var box = document.createElement("div");
            box.className = "cs-confirm" + (danger ? " cs-confirm-danger" : "");

            var icon = document.createElement("div");
            icon.className = "cs-confirm-icon";
            icon.innerHTML = danger ? SVG.danger : SVG.ask;

            var title = document.createElement("h3");
            title.className = "cs-confirm-title";
            title.textContent = parts.title;

            box.appendChild(icon);
            box.appendChild(title);

            if (parts.body) {
                var text = document.createElement("p");
                text.className = "cs-confirm-message";
                text.textContent = parts.body;
                box.appendChild(text);
            }

            var actions = document.createElement("div");
            actions.className = "cs-confirm-actions";

            var cancelBtn = document.createElement("button");
            cancelBtn.type = "button";
            cancelBtn.className = "cs-confirm-btn cs-confirm-cancel";
            cancelBtn.textContent = (options.cancelText === "Go Back") ? "Back" : (options.cancelText || "Cancel");

            var okBtn = document.createElement("button");
            okBtn.type = "button";
            okBtn.className = "cs-confirm-btn cs-confirm-ok";
            okBtn.textContent = okText;

            /* green na action sa kaliwa, red na Back/Cancel sa kanan */
            actions.appendChild(okBtn);
            actions.appendChild(cancelBtn);

            box.appendChild(actions);
            overlay.appendChild(box);
            document.body.appendChild(overlay);

            var done = false;

            function close(result) {
                if (done) {
                    return;
                }
                done = true;
                document.removeEventListener("keydown", onKey, true);
                overlay.classList.remove("cs-confirm-show");
                setTimeout(function () {
                    if (overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                }, 200);
                resolve(result);
            }

            function onKey(event) {
                if (event.key === "Escape") {
                    event.preventDefault();
                    close(false);
                }
            }

            cancelBtn.addEventListener("click", function () { close(false); });
            okBtn.addEventListener("click", function () { close(true); });
            overlay.addEventListener("click", function (event) {
                if (event.target === overlay) {
                    close(false);
                }
            });
            document.addEventListener("keydown", onKey, true);

            requestAnimationFrame(function () {
                overlay.classList.add("cs-confirm-show");
                okBtn.focus();
            });
        });
    };

    /* * Para sa <form onsubmit="return csConfirmSubmit(this, 'Sure?');">
     * Unang submit: pinipigilan at nagpapakita ng confirm pop up.
     * Pag OK: sinusubmit ulit ang form (kasama pa rin ang pinindot na button). */
    window.csConfirmSubmit = function (form, message, options) {

        if (!form) {
            return false;
        }

        if (form.dataset.csConfirmed === "1") {
            delete form.dataset.csConfirmed;
            return true;
        }

        var evt = window.event;
        var submitter = (evt && evt.submitter && evt.submitter.form === form) ? evt.submitter : null;

        window.csConfirm(message, options).then(function (ok) {

            if (!ok) {
                return;
            }

            form.dataset.csConfirmed = "1";

            if (typeof form.requestSubmit === "function") {
                if (submitter) {
                    form.requestSubmit(submitter);
                } else {
                    form.requestSubmit();
                }
            } else {
                delete form.dataset.csConfirmed;
                form.submit();
            }
        });

        return false;
    };

})();
</script>


<!-- ==========================================================================
     [6] JS - LINIS NG URL:  ?saved=1, ?sent=1, ?progress_error=
     ========================================================================== -->
<script>
/* tanggalin ang one-time na params (?progress_error=, ?sent=1, ?saved=1) para hindi lumabas ulit ang pop up pag ni-reload */
(function () {
    if (!window.history || !window.history.replaceState || !window.URL) { return; }
    var url = new URL(window.location.href), changed = false;
    ["progress_error", "sent", "saved"].forEach(function (k) {
        if (url.searchParams.has(k)) { url.searchParams.delete(k); changed = true; }
    });
    if (changed) { window.history.replaceState({}, document.title, url.toString()); }
})();
</script>


<!-- ==========================================================================
     [7] JS - CSRF (security token sa bawat POST form) - HUWAG ALISIN
     ========================================================================== -->
<?php
/* CSRF: bawat POST form sa site ay awtomatikong nilalagyan ng
   csrf_token (tinitingnan ng helpers/order_workflow.php sa lahat
   ng order action endpoints). Naka-capture listener din para pati
   ang mga form na idinagdag pagkatapos mag-load ay may token. */
if (session_status() === PHP_SESSION_ACTIVE):
    require_once __DIR__ . "/../helpers/csrf_helper.php";
    $csCsrfToken = figurify_csrf_token();
?>
<script>
(function () {
    var token = <?php echo json_encode($csCsrfToken); ?>;

    function stamp(form) {
        if (!form || !form.method || form.method.toLowerCase() !== "post") { return; }
        var field = form.querySelector('input[name="csrf_token"]');
        if (!field) {
            field = document.createElement("input");
            field.type = "hidden";
            field.name = "csrf_token";
            form.appendChild(field);
        }
        field.value = token;
    }

    function stampAll() {
        Array.prototype.forEach.call(document.querySelectorAll("form"), stamp);
    }

    window.csCsrfToken = token;
    document.addEventListener("submit", function (e) { stamp(e.target); }, true);

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", stampAll);
    } else {
        stampAll();
    }
})();
</script>
<?php endif; ?>


<!-- ==========================================================================
     [8] JS - FLASH MESSAGE (pop up na galing sa redirect - figurify_flash)
     ========================================================================== -->
<?php if (!empty($csPopupFlashes)): ?>
<script>
(function () {
    var flashes = <?php echo json_encode($csPopupFlashes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function showFlashes() {
        // isa lang ang pinapakita (ang huli), gaya ng ibang pop up
        var last = flashes[flashes.length - 1];
        if (last && window.csToast) {
            window.csToast(last.message, last.type);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", showFlashes);
    } else {
        showFlashes();
    }
})();
</script>
<?php endif; ?>
