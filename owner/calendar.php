<?php

require_once "owner-header.php";

$figurifyRole    = "owner";
$figurifyPageDir = __DIR__;

/* Shared sa owner at staff calendar.php - set muna $figurifyRole at
   $figurifyPageDir bago mag-require nito. */
require_once __DIR__ . "/../helpers/booking_helper.php";

$bookedDates    = figurify_get_booked_dates($conn);
$bookingsByDate = figurify_get_bookings_by_date($conn);

/* Same rush/non-rush limit logic ng customer-facing commission.php
   calendar, para magkatugma kung anong araw puno na (2/2 rush, 15/15 non-rush). */
$rushDayCounts     = figurify_get_rush_day_counts($conn);
$nonrushDateCounts = figurify_get_nonrush_date_counts($conn);

?>

<!DOCTYPE html>

<html lang="en">

<head>
    <?php /* [POP UP AT SECURITY] pop up at security script lang (walang CSS) - Shared/popup-and-security.php */ include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Figurify | Calendar</title>

<!-- ===================== STYLE NG PAGE NA ITO: calendar.css ===================== -->
<link rel="stylesheet" href="calendar.css">


</head>


<body>

<div class="app">


<?php require_once __DIR__ . "/../Shared/sidebar.php"; ?>


<main class="main">


<header class="topbar">

    <div class="welcome">

        <h2 class="portal-title">
            Calendar
        </h2>

    </div>


        <div class="top-actions">

<!-- ==========================================================
     [TOPBAR-DATETIME]  (dating Shared/topbar-datetime.php - nasa page na ito na)
     ANO ITO: petsa at oras sa taas ng page, sa kaliwa ng notification bell.
     PAANO GUMAGANA: ang JS sa Shared/sidebar.php ([3] TOPBAR DATE / TIME) ang nagpapalit ng .cs-tb-date at .cs-tb-time
     kada segundo (en-PH). Self-contained: hindi kailangan ng #currentDate / #currentTime.
     ========================================================== -->


<div class="cs-tb-datetime" aria-label="Current date and time">
    <span class="cs-tb-date">Loading date...</span>
    <span class="cs-tb-dot">•</span>
    <span class="cs-tb-time">Loading time...</span>
</div>

<!-- [END TOPBAR-DATETIME] -->

            <?php include __DIR__ . "/../Shared/notification-bell.php"; ?>

        </div>

    </header>


<div class="calendar-page-layout">


    <!-- LEFT: CALENDAR -->

    <section class="card full-calendar-card">


        <div class="calendar-page-controls">

            <button
                type="button"
                id="fullPreviousMonth"
                class="calendar-arrow-btn"
                aria-label="Previous month"
            >
                <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>


            <h3 id="fullCalendarMonth">
                Loading...
            </h3>


            <button
                type="button"
                id="fullNextMonth"
                class="calendar-arrow-btn"
                aria-label="Next month"
            >
                <svg class="calendar-arrow-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

        </div>


        <div class="calendar-week full-week">

            <div>SUN</div>
            <div>MON</div>
            <div>TUE</div>
            <div>WED</div>
            <div>THU</div>
            <div>FRI</div>
            <div>SAT</div>

        </div>


        <div
            class="calendar-days full-calendar-days"
            id="fullCalendarDays"
        ></div>


        <div class="calendar-legend">
            <span><i class="legend-dot available-dot"></i> Available</span>
            <span><i class="legend-dot today-dot"></i> Today</span>
            <span><i class="legend-dot selected-dot"></i> Selected</span>
            <span><i class="legend-dot booked-dot"></i> Fully booked</span>
            <span><i class="legend-dot unavailable-dot"></i> Unavailable</span>
        </div>


    </section>


    <!-- RIGHT: BOOKED ORDERS LIST -->

    <section class="card" id="dayBookingsCard">

        <div class="card-header">

            <h3 id="dayBookingsTitle">
                Booked Orders
            </h3>

            <a
                href="#"
                id="clearDayFilter"
                class="clear-day-filter"
                hidden
            >
                Show all ×
            </a>

        </div>

        <div id="dayBookingsList"></div>

    </section>


</div>


</main>

</div>


<script>
/* ==========================================================================
   OWNER CALENDAR SCRIPT
   1. Shared helpers (formatDate)
   2. Booking data from PHP + capacity rules
   3. Booked Orders list (right panel)
   4. Calendar rendering + month navigation
   ========================================================================== */

/* ---------- 1. SHARED HELPERS ---------- */
// calendar date format: formatDate(2026, 9, 21) => "2026-10-21"  (month ay 0-11)
function formatDate(year, month, day) {
    return year + "-" + String(month + 1).padStart(2, "0") + "-" + String(day).padStart(2, "0");
}

/* ---------- 2-4. CALENDAR ---------- */
const fullCalendarDays =
    document.getElementById(
        "fullCalendarDays"
    );

const fullCalendarMonth =
    document.getElementById(
        "fullCalendarMonth"
    );

const fullPreviousMonth =
    document.getElementById(
        "fullPreviousMonth"
    );

const fullNextMonth =
    document.getElementById(
        "fullNextMonth"
    );


let fullCalendarDate =
    new Date();


const demoBookings = <?php echo json_encode($bookedDates); ?>;

const bookingsByDate = <?php echo json_encode($bookingsByDate); ?>;


/* Capacity data, same source (booking_helper.php) ng commission.php
   calendar - para makita kung anong araw puno na, hindi lang may laman. */
const figurifyRushDayCounts     = <?php echo json_encode($rushDayCounts); ?>;
const figurifyNonrushDateCounts = <?php echo json_encode($nonrushDateCounts); ?>;

const FIGURIFY_RUSH_DAILY_CAP    = <?php echo (int) FIGURIFY_RUSH_DAILY_CAP; ?>;
const FIGURIFY_NONRUSH_DATE_CAP  = <?php echo (int) FIGURIFY_NONRUSH_DATE_CAP; ?>;

/* Ibinabalik ang capacity status ng isang petsa, gamit ang parehong
   panuntunan ng commission.js: puno na bang rush (2/2), o puno na
   bang non-rush (15/15, sa huling araw lang ng buwan may bisa ito). */
function getDayCapacityInfo(dateString) {

    const rushCount = figurifyRushDayCounts[dateString] || 0;

    if (rushCount >= FIGURIFY_RUSH_DAILY_CAP) {

        return {
            type: "rush-full",
            label: "Rush full (" + rushCount + "/" + FIGURIFY_RUSH_DAILY_CAP + ")"
        };

    }

    const dateObj = new Date(dateString + "T00:00:00");

    const lastDayOfMonth =
        new Date(
            dateObj.getFullYear(),
            dateObj.getMonth() + 1,
            0
        ).getDate();

    const isLastDayOfMonth =
        dateObj.getDate() === lastDayOfMonth;

    if (isLastDayOfMonth) {

        const nonrushCount =
            figurifyNonrushDateCounts[dateString] || 0;

        if (nonrushCount >= FIGURIFY_NONRUSH_DATE_CAP) {

            return {
                type: "nonrush-full",
                label: "Non-Rush full (" + nonrushCount + "/" + FIGURIFY_NONRUSH_DATE_CAP + ")"
            };

        }

    }

    return null;

}


const dayBookingsTitle =
    document.getElementById(
        "dayBookingsTitle"
    );

const dayBookingsList =
    document.getElementById(
        "dayBookingsList"
    );


const clearDayFilter =
    document.getElementById(
        "clearDayFilter"
    );

let activeDayFilter = null;


/* para ligtas ilagay sa HTML ang text galing sa database */
function calEscape(value) {

    return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");

}


function niceDateLabel(dateString) {

    return new Date(dateString + "T00:00:00")
        .toLocaleDateString(
            "en-US",
            {
                month: "long",
                day: "numeric",
                year: "numeric"
            }
        );

}


function orderMethodLabel(orderMethod) {

    return (orderMethod === "create_style")
        ? "Dress Up"
        : "Image Submission";

}


/* Iisang pinagmumulan ng status label/kulay (helpers/order_status_helper.php)
   ang ginagamit din dito, para pare-pareho sa Owner/Staff Dashboard
   at sa My Orders ng customer. */
const FIGURIFY_STATUS_LABELS = <?php echo json_encode(array_map(
    static function ($s) { return $s["label"]; },
    FIGURIFY_ORDER_STATUSES
)); ?>;

function statusSlug(status) {

    return String(status || "pending")
        .toLowerCase()
        .trim()
        .replace(/[\s-]+/g, "_");

}

function statusBadgeLabel(status) {

    const slug = statusSlug(status);

    return FIGURIFY_STATUS_LABELS[slug] || String(status || "")
        .replace(/_/g, " ")
        .replace(/\b\w/g, function(c) {
            return c.toUpperCase();
        });

}


// Persistent na listahan ng LAHAT ng booked orders sa right side,
// grouped by date. Kapag may na-click na araw (activeDayFilter),
// highlight/scroll na lang papunta doon, hindi itinatago ang iba.

function renderBookingsList() {

    if (!dayBookingsList) {
        return;
    }

    let dateKeys =
        Object.keys(bookingsByDate)
            .sort();

    // Kapag may selected date, i-scope lang ang listahan doon -
    // kahit walang booking, "wala" dapat ang lalabas, hindi buong listahan.
    if (activeDayFilter) {
        dateKeys = dateKeys.filter(function(dateString) {
            return dateString === activeDayFilter;
        });
    }

    dayBookingsList.innerHTML = "";

    if (dayBookingsTitle) {

        dayBookingsTitle.textContent =
            activeDayFilter
            ? "Booked Orders · " + new Date(activeDayFilter + "T00:00:00").toLocaleDateString("en-US", { month: "short", day: "numeric" })
            : "Booked Orders";

    }

    if (dateKeys.length === 0) {

        dayBookingsList.innerHTML =
            "<div class='empty-state'>" +
            (
                activeDayFilter
                ? "No bookings on this date."
                : "No booked orders yet."
            ) +
            "</div>";

        if (clearDayFilter) {
            clearDayFilter.hidden = !activeDayFilter;
        }

        return;

    }

    dateKeys.forEach(function(dateString) {

        const orders =
            bookingsByDate[dateString] ||
            [];

        orders.forEach(function(order) {

            /* sariling card bawat booking (kapareho ng Upcoming Shipment):
               Order # + status, pangalan, Order Type / Order Method / Ship Out Date / Courier,
               at bawat figure sa sariling kahon (Figure Style / Product Type / Size) */
            const card =
                document.createElement("div");

            card.className =
                "cal-card" +
                (
                    activeDayFilter === dateString
                    ? " highlighted"
                    : ""
                );

            card.dataset.date = dateString;

            const isRush = String(order.order_type || "").toLowerCase() === "rush";
            const courier = String(order.courier || "").trim();
            const courierLabel = courier === ""
                ? "N/A"
                : (courier.toLowerCase() === "lbc" ? "LBC" : courier.charAt(0).toUpperCase() + courier.slice(1));

            const field = function(label, value) {
                return "<div class='cal-card-field'>" +
                    "<span class='cal-card-label'>" + label + "</span>" +
                    "<span class='cal-card-value'>" + calEscape(value) + "</span>" +
                "</div>";
            };

            /* bawat figure: sariling kahon na may Figure Style / Product Type / Size */
            const figures = (order.figures || []).map(function(fig, index) {
                return "<div class='cal-figure'>" +
                    "<div class='cal-figure-title'>Figure " + (index + 1) + "</div>" +
                    "<div class='cal-card-details'>" +
                        field("Figure Style", fig.style || "N/A") +
                        field("Product Type", fig.product || "N/A") +
                        field("Size", fig.size || "N/A") +
                    "</div>" +
                    /* Dress Up: mga kulay na pinili + color code (hal. Hair: Custom (#7A3B1F)) */
                    (fig.colors || []).map(function(color) {
                        return "<div class='cal-figure-color'>" +
                            "<svg class='cal-color-dot' viewBox='0 0 10 10' aria-hidden='true'><circle cx='5' cy='5' r='5' fill='" + calEscape(color.code) + "'></circle></svg>" +
                            "<span class='cal-card-label'>" + calEscape(color.label) + "</span> " +
                            calEscape((color.name ? color.name + " · " : "") + color.text) +
                        "</div>";
                    }).join("") +
                "</div>";
            }).join("");

            card.innerHTML =
                "<div class='cal-card-top'>" +
                    "<span class='cal-card-id'>Order #" + order.order_id + "</span>" +
                    "<span class='cal-chip'>" + calEscape(statusBadgeLabel(order.status)) + "</span>" +
                "</div>" +
                "<div class='cal-card-name'>" + calEscape(order.full_name || "N/A") + "</div>" +
                "<div class='cal-card-details'>" +
                    field("Order Type", isRush ? "Rush" : "Non-Rush") +
                    field("Order Method", orderMethodLabel(order.order_method)) +
                    field("Ship Out Date", niceDateLabel(dateString)) +
                    field("Courier", courierLabel) +
                "</div>" +
                (figures ? "<div class='cal-card-figures'>" + figures + "</div>" : "");

            dayBookingsList.appendChild(
                card
            );

        });

    });

    if (clearDayFilter) {

        clearDayFilter.hidden = !activeDayFilter;

    }

}


function showDayBookings(dateString) {

    // Toggle: kapag pareho lang ng na-click at yung kasalukuyang
    // selected date, i-deselect (bumalik sa "show all" state).
    activeDayFilter =
        (activeDayFilter === dateString)
        ? null
        : dateString;

    // I-sync ang day-selected class sa calendar days imbes na
    // buuin ulit ang buong calendar.
    fullCalendarDays
        .querySelectorAll(".large-day")
        .forEach(function(dayEl) {
            dayEl.classList.remove("day-selected");
        });

    if (activeDayFilter) {

        const selectedDayEl =
            fullCalendarDays.querySelector(
                "[data-date='" + activeDayFilter + "']"
            );

        selectedDayEl?.classList.add("day-selected");

    }

    renderBookingsList();

    const firstMatch =
        dayBookingsList.querySelector(
            "[data-date='" + dateString + "']"
        );

    if (firstMatch) {

        firstMatch.scrollIntoView({
            behavior: "smooth",
            block: "nearest"
        });

    }

}


clearDayFilter?.addEventListener(
    "click",
    function(event) {

        event.preventDefault();

        activeDayFilter = null;

        document
            .querySelectorAll("#fullCalendarDays .large-day")
            .forEach(function(dayEl) {
                dayEl.classList.remove("day-selected");
            });

        renderBookingsList();

    }
);


function renderFullCalendar() {

    if (!fullCalendarDays) {
        return;
    }


    const year =
        fullCalendarDate.getFullYear();

    const month =
        fullCalendarDate.getMonth();


    fullCalendarMonth.textContent =
        fullCalendarDate.toLocaleDateString(
            "en-US",
            {
                month: "long",
                year: "numeric"
            }
        );


    // Di pwede bumalik sa nakalipas na buwan - kasalukuyang buwan
    // na lang ang pinaka-maaga.
    const now = new Date();

    const isAtCurrentMonth =
        year === now.getFullYear() &&
        month === now.getMonth();

    if (fullPreviousMonth) {

        fullPreviousMonth.disabled = isAtCurrentMonth;

    }


    fullCalendarDays.innerHTML = "";

    // Right-side list persistent, pero i-clear ang day filter/highlight
    // pag nag-palit ng buwan.
    activeDayFilter = null;


    const jsFirstDay =
        new Date(
            year,
            month,
            1
        ).getDay();


    /* Sunday-first (SUN ... SAT) */
    const firstDay =
        jsFirstDay;


    const daysInMonth =
        new Date(
            year,
            month + 1,
            0
        ).getDate();


    for (
        let i = 0;
        i < firstDay;
        i++
    ) {

        const empty =
            document.createElement("div");

        empty.className =
            "day empty";

        fullCalendarDays.appendChild(
            empty
        );

    }


    const today =
        new Date();


    for (
        let day = 1;
        day <= daysInMonth;
        day++
    ) {

        const element =
            document.createElement("div");


        element.className =
            "day large-day";


        element.textContent =
            day;


        const dateString =
            year +
            "-" +
            String(month + 1)
                .padStart(2, "0") +
            "-" +
            String(day)
                .padStart(2, "0");

        element.dataset.date = dateString;


        if (
            today.getFullYear() === year &&
            today.getMonth() === month &&
            today.getDate() === day
        ) {

            element.classList.add(
                "today"
            );

        }


        /* nakalipas na araw: kulay abo at HINDI na clickable (kahit may booking) */
        const isPast =
            new Date(year, month, day) <
            new Date(today.getFullYear(), today.getMonth(), today.getDate());

        if (isPast) {

            element.classList.add(
                "unavailable"
            );

            fullCalendarDays.appendChild(
                element
            );

            continue;

        }


        /* may order sa araw na ito (approved na) - para sa click/listahan lang */
        const hasBookings =
            demoBookings.includes(
                dateString
            );

        /* Red/"booked" LANG kapag FULL na ang slot (rush 2/2, non-rush 15/15).
           Ang petsang may 1 order pa lang ay hindi kinukulayan. */
        const capacityInfo =
            getDayCapacityInfo(dateString);

        if (capacityInfo) {

            element.classList.add(
                "booked"
            );

            element.classList.add(
                capacityInfo.type
            );

            element.title =
                capacityInfo.label;

        }

        /* CAPACITY - ipakita ang bilang ng slot kahit hindi pa puno */
        if (!capacityInfo && hasBookings) {

            const rushUsed    = figurifyRushDayCounts[dateString] || 0;
            const nonrushUsed = figurifyNonrushDateCounts[dateString] || 0;
            const parts       = [];

            if (rushUsed > 0) {
                parts.push("Rush " + rushUsed + "/" + FIGURIFY_RUSH_DAILY_CAP);
            }
            if (nonrushUsed > 0) {
                parts.push("Non-Rush " + nonrushUsed + "/" + FIGURIFY_NONRUSH_DATE_CAP);
            }
            if (parts.length) {
                element.title = "Capacity: " + parts.join(" · ");
            }
        }

        if (!capacityInfo) {

            element.classList.add(
                "available"
            );

        }

        // Kapareho ng Dashboard calendar: gray/"Unavailable" ang
        if (
            activeDayFilter === dateString
        ) {

            element.classList.add(
                "day-selected"
            );

        }

        element.addEventListener(
            "click",
            function() {

                showDayBookings(
                    dateString
                );

            }
        );


        fullCalendarDays.appendChild(
            element
        );

    }

}


fullPreviousMonth?.addEventListener(
    "click",
    function() {

        const now = new Date();

        const isAtCurrentMonth =
            fullCalendarDate.getFullYear() === now.getFullYear() &&
            fullCalendarDate.getMonth() === now.getMonth();

        if (isAtCurrentMonth) {
            return;
        }

        slideFullCalendar(-1);

    }
);


fullNextMonth?.addEventListener(
    "click",
    function() {

        slideFullCalendar(1);

    }
);


/* paglipat ng buwan: fade + slide (kagaya ng Commission calendar) */
function slideFullCalendar(amount) {

    const slide = amount > 0 ? "days-next" : "days-prev";

    fullCalendarDays.classList.add(slide);

    setTimeout(function() {
        fullCalendarDate.setMonth(fullCalendarDate.getMonth() + amount);
        renderFullCalendar();
        fullCalendarDays.classList.remove(slide);
    }, 350);
}


renderFullCalendar();
renderBookingsList();
</script>


</body>

</html>