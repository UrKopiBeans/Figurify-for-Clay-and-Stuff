<?php

// Shared notif bell for Owner + Staff panel. Just include it in
// ".top-actions" of the topbar. Relative path to staff_bell.php
// works kasi flat ang folder structure ng owner/staff pages.

?>

<style>

/* .notification-btn/.notification-badge are from the shared
   dashboard styles, dropdown styling lang dito */

.notif-wrap {
    position: relative;
}

/* SVG icon instead of emoji, better contrast */

#notifBtn {
    width: 46px;
    height: 46px;

    background: var(--white);

    border: 1.5px solid var(--gray-light);

    box-shadow: 0 2px 8px rgba(41, 37, 42, .08);

    display: flex;
    align-items: center;
    justify-content: center;

    transition: .2s ease;
}

#notifBtn:hover {
    background: var(--pink-soft);
    border-color: var(--pink-light);
}

#notifBtn svg {
    width: 21px;
    height: 21px;

    stroke: var(--pink-dark);
    fill: none;

    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.notification-badge {
    min-width: 19px !important;
    height: 19px !important;

    font-size: 10px !important;

    border: 2px solid var(--white) !important;

    background: var(--red, #d96b78) !important;

    box-shadow: 0 0 0 1px rgba(217, 107, 120, .35);

    animation: notifBadgePulse 1.8s ease-in-out infinite;
}

@keyframes notifBadgePulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(217, 107, 120, .45); }
    50% { box-shadow: 0 0 0 4px rgba(217, 107, 120, 0); }
}

.notif-panel {
    display: none;

    position: absolute;
    top: 54px;
    right: 0;

    width: 420px;
    max-width: calc(100vw - 32px);
    max-height: 440px;

    overflow-y: auto;
    padding: 22px 16px 14px;

    background: #ffffff;
    border: 1px solid var(--p-line, #f3dce3);
    border-radius: 22px;
    box-shadow: 0 22px 48px -20px rgba(74, 29, 59, .38);

    z-index: 60;
    text-align: left;
}

.notif-panel.open {
    display: block;
}

/* title - Fraunces (Design System) */
.notif-panel-header {
    padding: 0 8px 10px;

    
    font-size: 21px;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;

    color: var(--p-plum, #4a1d3b);
}

/* isang linya: mensahe sa kaliwa, oras sa kanan */
.notif-item {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 14px;

    padding: 14px 14px;
    border-radius: 12px;

    text-decoration: none;
    color: var(--p-plum, #4a1d3b);
    transition: background .12s ease;
}

.notif-item:hover {
    background: #fff8fa;
}

.notif-item.unread {
    background: var(--p-blush, #ffedf2);
}

.notif-item p {
    flex: 1 1 auto;
    min-width: 0;
    margin: 0;
    font-size: 14.5px;
    font-weight: 600;
    line-height: 1.4;
}

.notif-item:not(.unread) p {
    font-weight: 500;
}

.notif-time {
    flex: 0 0 auto;
    font-size: 12px;
    white-space: nowrap;
    color: var(--p-muted, #7c6670);
}

.notif-empty {
    padding: 26px 16px;
    text-align: center;
    font-size: 14px;
    color: var(--p-muted, #7c6670);
}

</style>


<div class="notif-wrap">

    <button
        type="button"
        class="notification-btn"
        id="notifBtn"
        aria-haspopup="true"
        aria-expanded="false"
        title="Notifications"
    >

        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>

        <span
            class="notification-badge"
            id="notifBadge"
            style="display:none;"
        >
            0
        </span>

    </button>

    <div class="notif-panel" id="notifPanel">

        <div class="notif-panel-header">
            Notifications
        </div>

        <div class="notif-list" id="notifList">
            <div class="notif-empty">Loading...</div>
        </div>

    </div>

</div>


<script>

// works on any owner/staff page since the folder structure is flat
// (always ../Notification/staff_bell.php)

(function () {

    const notifBtn   = document.getElementById("notifBtn");
    const notifPanel = document.getElementById("notifPanel");
    const notifList  = document.getElementById("notifList");
    const notifBadge = document.getElementById("notifBadge");

    if (!notifBtn || !notifPanel || !notifList || !notifBadge) {
        return;
    }

    const ENDPOINT = "../Notification/staff_bell.php";

    let latestNotifications = [];

    // Auto-reloads the page kapag may bagong notification (bell
    // click not required). Tracks the highest notification_id we've
    // seen so far - new max means there's a real new notification.
    let knownMaxNotificationId = null;

    function isUserComposingRightNow() {

        const active = document.activeElement;
        const tag = active && active.tagName ? active.tagName.toLowerCase() : "";

        return (tag === "textarea" || tag === "input" || tag === "select");

    }


    function escapeHtml(text) {

        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;

    }


    function formatWhen(createdAt) {

        const date = new Date(
            (createdAt || "").replace(" ", "T")
        );

        if (isNaN(date.getTime())) {
            return "";
        }

        // "2m ago", "1h ago" - kapag lampas isang araw, petsa na lang (hal. "Oct 12")
        const minutes = Math.floor((Date.now() - date.getTime()) / 60000);

        if (minutes < 1) {
            return "Just now";
        }
        if (minutes < 60) {
            return minutes + "m ago";
        }
        if (minutes < 60 * 24) {
            return Math.floor(minutes / 60) + "h ago";
        }

        return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });

    }


    function renderNotifications() {

        if (!latestNotifications.length) {

            notifList.innerHTML =
                '<div class="notif-empty">No notifications yet.</div>';

            return;

        }

        notifList.innerHTML = latestNotifications.map(function (note) {

            const unreadClass = note.is_read ? "" : " unread";

            const href = note.order_id
                ? "allbookings.php?order_id=" + note.order_id
                : "allbookings.php";

            return (
                '<a class="notif-item' + unreadClass + '" href="' + href + '">' +
                    "<p>" + escapeHtml(note.message) + "</p>" +
                    '<span class="notif-time">' + formatWhen(note.created_at) + "</span>" +
                "</a>"
            );

        }).join("");

    }


    function updateBadge(count) {

        if (count > 0) {

            notifBadge.textContent = count > 9 ? "9+" : String(count);
            notifBadge.style.display = "flex";

        } else {

            notifBadge.style.display = "none";

        }

    }


    function fetchNotifications() {

        fetch(ENDPOINT + "?action=list")
            .then(function (res) { return res.json(); })
            .then(function (data) {

                latestNotifications = data.notifications || [];

                updateBadge(data.unread_count || 0);

                if (notifPanel.classList.contains("open")) {
                    renderNotifications();
                }

                // check for new notifications (see comment above)
                const currentMaxId = latestNotifications.reduce(
                    function (max, note) {
                        return note.notification_id > max ? note.notification_id : max;
                    },
                    0
                );

                if (knownMaxNotificationId === null) {
                    // first fetch, just set the baseline, no reload yet
                    knownMaxNotificationId = currentMaxId;
                    return;

                }

                if (currentMaxId <= knownMaxNotificationId) {
                    return; // wala namang bago
                }

                // may bago, but don't reload if tab is hidden or user
                // is typing something (will retry on next poll)
                if (document.hidden || isUserComposingRightNow()) {
                    return;
                }

                window.location.reload();

            })
            .catch(function () {});

    }



    function markAllAsRead() {

        fetch(ENDPOINT, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=mark_read"
        })
            .then(function () { updateBadge(0); })
            .catch(function () {});

    }


    notifBtn.addEventListener("click", function (e) {

        e.stopPropagation();

        const isOpen = notifPanel.classList.toggle("open");

        notifBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");

        if (isOpen) {

            renderNotifications();
            markAllAsRead();

        }

    });


    document.addEventListener("click", function (e) {

        if (
            !notifPanel.contains(e.target) &&
            e.target !== notifBtn
        ) {

            notifPanel.classList.remove("open");
            notifBtn.setAttribute("aria-expanded", "false");

        }

    });


    fetchNotifications();

    setInterval(fetchNotifications, 3000); // poll every 3s


})();

</script>
