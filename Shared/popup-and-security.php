<?php
/* ==========================================================================
   FIGURIFY - POP UP AT SECURITY   (Shared/popup-and-security.php)
   ==========================================================================
   Ginagamit ng LAHAT ng page (customer, owner at staff).
   Dito na rin ang itsura ng pop up (toast + confirm) - iisang CSS para sa buong system.

   LAMAN (hanapin ang [1], [2] ... para makita agad):
       [0] CSS - POP UP          itsura ng toast at confirm (iisa lang sa buong system)
       [1] JS - POP UP           csToast(), csConfirm(), csConfirmSubmit()
       [2] JS - LINIS NG URL     tinatanggal ang ?saved=1, ?sent=1, ?progress_error=
       [3] JS - CSRF             security token sa bawat POST form (HUWAG ALISIN)
       [4] JS - FLASH MESSAGE    pop up na galing sa redirect (figurify_flash)
       [5] JS - KULANG NA FIELD  pop up + scroll + highlight sa unang kulang na field ng form
       [6] JS - NUMBERS LANG     data-digits-only (0-9) at data-decimal-only (hal. 150.50) sa lahat ng page

   WALA NA DITO (inilipat):
       - PHP pop up functions (figurify_flash, figurify_take_flashes,
         figurify_toast_now, figurify_saved_toast)
             -> helpers/security_helper.php  (SECTION 10 - POP UP MESSAGES)
       - Previous / Next ng mga table ng owner/staff
             -> Shared/sidebar.php

   PAANO GAMITIN
       Sa <head> ng bawat page (isang linya lang):
           <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>

       Sa redirect / action file (walang HTML, functions lang):
           require_once __DIR__ . "/../helpers/security_helper.php";
           figurify_flash("Order approved.", "success");

       Sa JS:
           csToast("Saved!", "success");                     // success | error | info
           csConfirm("Delete this?").then(ok => { ... });
           <form onsubmit="return csConfirmSubmit(this, 'Delete this?');">
   ========================================================================== */

/* PHP pop up functions + CSRF token (helpers/security_helper.php) */
require_once __DIR__ . "/../helpers/security_helper.php";

/* isang beses lang ilalabas ang script kahit ilang beses ma-include */
if (defined("POPUP_AND_SECURITY_INCLUDED")) {
    return;
}
define("POPUP_AND_SECURITY_INCLUDED", true);

$csPopupFlashes = figurify_take_flashes();

/* customer = pula + puti na may border (oblong), owner/staff = berde + pula */
$csConfirmPortalClass = figurify_session_portal() === "customer" ? "" : " cs-confirm-admin";
?>
<style>
/* ==========================================================================
   [0] CSS - POP UP (toast) - iisang itsura sa buong system (customer, staff, owner)
   Kitang-kita: may kulay na border at background ayon sa uri, malaking text, walang bilog sa kaliwa.
   ========================================================================== */
.cs-toast-container {
    position: fixed;
    top: 72px;
    left: 50%;
    z-index: 10050;
    display: flex;
    justify-content: center;
    width: max-content;
    max-width: calc(100% - 24px);
    transform: translateX(-50%);
    pointer-events: none;
}

.cs-toast {
    box-sizing: border-box;
    min-width: min(420px, 100%);
    max-width: 580px;
    padding: 20px 28px;
    border: 2px solid #c2325f;
    border-radius: 18px;
    background: #fff5f8;
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.18);
    color: #4a1d3b;
    font-family: inherit;
    font-size: 18px;
    font-weight: 700;
    line-height: 1.45;
    text-align: center;
    cursor: pointer;
    opacity: 0;
    transform: translateY(-10px);
    transition: opacity .2s ease, transform .2s ease;
    pointer-events: auto;
}

.cs-toast.cs-toast-show { opacity: 1; transform: translateY(0); }
.cs-toast.cs-toast-hide { opacity: 0; transform: translateY(-6px); }

.cs-toast-error   { border-color: #b83227; background: #fff1f0; color: #8e2219; }
.cs-toast-success { border-color: #2f8f67; background: #effaf4; color: #1f6b4b; }

/* ---------- confirm (csConfirm) ---------- */
.cs-confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 10060;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(45, 14, 32, .42);
    -webkit-backdrop-filter: blur(3px);
    backdrop-filter: blur(3px);
    opacity: 0;
    transition: opacity .18s ease;
}

.cs-confirm-overlay.cs-confirm-show { opacity: 1; }

.cs-confirm {
    box-sizing: border-box;
    width: 100%;
    max-width: 540px;
    padding: 42px 38px 36px;
    border: 1px solid #f3dde5;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.18);
    text-align: center;
    transform: translateY(10px) scale(.97);
    transition: transform .2s ease;
}

.cs-confirm-overlay.cs-confirm-show .cs-confirm { transform: translateY(0) scale(1); }

.cs-confirm-title { margin: 0; color: #4a1d3b; font-size: 29px; font-weight: 600; line-height: 1.3; }
.cs-confirm-danger .cs-confirm-title { color: #d92d20; }

.cs-confirm-message { margin: 6px 0 0; color: #7a5a6c; font-size: 18px; line-height: 1.5; white-space: pre-line; }

.cs-confirm-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 22px; }

/* customer: Back/Cancel = puti na may border (kaliwa), action = pula (kanan), oblong */
.cs-confirm-btn {
    padding: 17px 20px;
    border: 2px solid #c2325f;
    border-radius: 999px;
    font-family: inherit;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    transition: background-color .15s ease, border-color .15s ease;
}

.cs-confirm-btn:focus-visible { outline: 2px solid rgba(194, 50, 95, .4); outline-offset: 2px; }

.cs-confirm-cancel { order: 1; background: #fff; color: #c2325f; }
.cs-confirm-cancel:hover { background: #ffedf2; }

.cs-confirm-ok { order: 2; background: #c2325f; color: #fff; }
.cs-confirm-ok:hover { border-color: #a62a50; background: #a62a50; }

/* owner/staff: laging Cancel = pula, action = berde (kahit Decline/Reject) - hindi magkapareho ang kulay */
.cs-confirm-admin .cs-confirm-btn { border-radius: 999px; }

.cs-confirm-admin .cs-confirm-cancel { border-color: #d92d20; background: #d92d20; color: #fff; }
.cs-confirm-admin .cs-confirm-cancel:hover { border-color: #b42318; background: #b42318; }

.cs-confirm-admin .cs-confirm-ok { border-color: #1f9d55; background: #1f9d55; }
.cs-confirm-admin .cs-confirm-ok:hover { border-color: #187f44; background: #187f44; }
/* ====================== END [0] CSS - POP UP ====================== */
</style>
<script>
/* ==========================================================================
   [1] JS - POP UP:  csToast(), csConfirm(), csConfirmSubmit()
   ========================================================================== */
(function () {

    if (window.csToast) {
        return;
    }

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

        var text = document.createElement("div");
        text.className = "cs-toast-message";
        text.textContent = message;

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
            box.className = "cs-confirm<?php echo $csConfirmPortalClass; ?>" + (danger ? " cs-confirm-danger" : "");

            var title = document.createElement("h3");
            title.className = "cs-confirm-title";
            title.textContent = parts.title;

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

            /* Back/Cancel sa kaliwa, action sa kanan (CSS order ang nag-aayos) */
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
/* ==========================================================================
   END [1] POP UP
   ========================================================================== */


/* ==========================================================================
   [2] JS - LINIS NG URL:  ?saved=1, ?sent=1, ?progress_error=
   ========================================================================== */
/* tanggalin ang one-time na params (?progress_error=, ?sent=1, ?saved=1) para hindi lumabas ulit ang pop up pag ni-reload */
(function () {
    if (!window.history || !window.history.replaceState || !window.URL) { return; }
    var url = new URL(window.location.href), changed = false;
    ["progress_error", "sent", "saved"].forEach(function (k) {
        if (url.searchParams.has(k)) { url.searchParams.delete(k); changed = true; }
    });
    if (changed) { window.history.replaceState({}, document.title, url.toString()); }
})();
/* ==========================================================================
   END [2] LINIS NG URL
   ========================================================================== */


/* ==========================================================================
   [3] JS - CSRF (security token sa bawat POST form) - HUWAG ALISIN
   ========================================================================== */
<?php
/* CSRF: bawat POST form sa site ay awtomatikong nilalagyan ng
   csrf_token (tinitingnan ng helpers/order_helper.php sa lahat
   ng order action endpoints). Naka-capture listener din para pati
   ang mga form na idinagdag pagkatapos mag-load ay may token. */
if (session_status() === PHP_SESSION_ACTIVE):
    $csCsrfToken = figurify_csrf_token();
?>
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
<?php endif; ?>
/* ==========================================================================
   END [3] CSRF
   ========================================================================== */


/* ==========================================================================
   [4] JS - FLASH MESSAGE (pop up na galing sa redirect - figurify_flash)
   ========================================================================== */
<?php if (!empty($csPopupFlashes)): ?>
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
<?php endif; ?>
/* ==========================================================================
   END [4] FLASH MESSAGE
   ========================================================================== */


/* ==========================================================================
   [5] JS - KULANG NA FIELD
   Pag may kulang sa form at pinindot ang Submit: lalabas ang pop up kung ano ang kulang,
   iiscroll papunta roon at ihi-highlight, para hindi na maghanap ang user (lalo na sa phone).
   ========================================================================== */
(function () {
    var busy = false;

    document.addEventListener("invalid", function (e) {
        var field = e.target;

        // walang lalabas na tooltip ng browser, pop up natin ang gagamitin
        e.preventDefault();

        // lahat ng kulang ay sabay-sabay na tinatawag; unang kulang lang ang aasikasuhin
        if (busy) {
            return;
        }
        busy = true;
        setTimeout(function () { busy = false; }, 400);

        var label = field.labels && field.labels[0] ? field.labels[0].textContent : "";
        label = label.replace(/\*/g, "").replace(/\s+/g, " ").trim();

        var message = label ? "Please fill in: " + label : "Please complete the highlighted part first.";
        if (window.csToast) {
            window.csToast(message, "error");
        }

        // nakatago ang mismong input ng file / radio, kaya ang container niya ang ihi-highlight
        var box = field.offsetWidth > 20 ? field : field.parentElement;

        box.scrollIntoView({ behavior: "smooth", block: "center" });
        box.style.outline = "3px solid #c2325f";
        box.style.outlineOffset = "6px";
        setTimeout(function () {
            box.style.outline = "";
            box.style.outlineOffset = "";
        }, 3500);
    }, true);
})();
/* ==========================================================================
   END [5] KULANG NA FIELD
   ========================================================================== */


/* ==========================================================================
   [6] JS - NUMBERS LANG (iisang listener para sa buong system)
   data-digits-only  = 0-9 lang (Contact No., Account No., Tracking No., Reference No.)
                       Ang limit ay galing sa maxlength (hal. Contact No. = 11); wala = walang limit.
   data-decimal-only = numero na may isang tuldok (Shipping Fee, hal. 150.50)
   ========================================================================== */
document.addEventListener("input", function (event) {
    var input = event.target;

    if (input.matches("[data-digits-only]")) {
        input.value = input.value.replace(/[^0-9]/g, "");
    } else if (input.matches("[data-decimal-only]")) {
        input.value = input.value.replace(/[^0-9.]/g, "").replace(/(\..*)\./g, "$1");
    }
});

/* pagkatapos mag-type: laging may .00 ang halaga (hal. 150 -> 150.00) */
document.addEventListener("change", function (event) {
    var input = event.target;

    if (input.matches("[data-decimal-only]") && input.value !== "" && !isNaN(parseFloat(input.value))) {
        input.value = parseFloat(input.value).toFixed(2);
    }
});
/* ==========================================================================
   END [6] NUMBERS LANG
   ========================================================================== */
</script>
