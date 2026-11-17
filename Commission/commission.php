<?php
/* ==========================================================================
   COMMISSION PAGE   (Commission/commission.php)
   Style: Commission/commission.css      Script: Commission/commission.js
   Dito pinipili ang Order Type, Ship Out Date at kung paano gagawin ang figure.
   Ang "Image Submission" ay papunta sa Commission/image-submission.php
   (sariling page: image-submission.php / .css / .js).
   Ang "Create Your Design" ay papunta sa Commission/dress-up.php.

   LAMAN (taas pababa):
     [1] SETUP + DATA   helpers, session, database, booked dates / caps, info ng customer
     [2] HTML           navbar, hero, STEP 01 schedule, STEP 02 method, footer
     [3] SCRIPT         data galing sa PHP para sa commission.js, tapos commission.js
   ========================================================================== */


/* ==========================================================================
   [1] SETUP + DATA
   ========================================================================== */
require_once __DIR__ . "/../helpers/security_helper.php";
figurify_start_session();

require_once __DIR__ . "/../Admin/database.php";   // $conn (iisang koneksyon ng buong site)
require_once __DIR__ . "/../helpers/booking_helper.php";
require_once __DIR__ . "/../helpers/content_helper.php";

$bookedDates = figurify_get_booked_dates($conn);

/* Rush/Non-Rush booking limits, ginagamit ng calendar sa commission.js
   para i-enforce ang daily/monthly caps ng Rush Order. */
$rushDayCounts        = figurify_get_rush_day_counts($conn);
$nonrushDateCounts    = figurify_get_nonrush_date_counts($conn);

/* presyo ng Figure Name para sa Notes (galing sa owner: Content Management > Prices) */
$prices = figurify_site_prices();

/* ====================== END SETUP + DATA ====================== */


/* ==========================================================================
   [2] HTML
   ========================================================================== */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clay and Stuff | Commission</title>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">
    <?php include __DIR__ . "/../Shared/popup-and-security.php"; ?>
    <link rel="stylesheet" href="commission.css">
</head>

<body>

<!-- ====================== navbar ====================== -->
<?php include __DIR__ . "/../Shared/navbar.php"; ?>

<!-- ====================== hero ====================== -->

<header class="hero">

    <div class="hero-content">

        <span class="hero-small">
            LET'S MAKE SOMETHING PERSONAL
        </span>

        <h1>
            Commission a Figure,
            <br>
            Made Just for You
        </h1>

        <p>
            Turn your ideas and favorite people into a little clay figure.
        </p>

    </div>

</header>


<!-- ====================== main ====================== -->

<main class="page">


<!-- ====================== STEP 01 - order schedule ====================== -->

<section>

    <div class="cm-head">
        <div>
            <h2>When do you need your figure?</h2>
            <p>Pick your order type, then choose a ship out date.</p>
        </div>
    </div>

    <!-- Rush / Non-Rush -->

    <div class="cm-order-types">

        <!-- walang check / bilog: ang "Rush Fee" / "No Extra Fee" tag ang nagkukulay kapag napili -->
        <button type="button" class="cm-order-card" id="rushCard" onclick="selectOrderType('rush')">
            <span class="cm-order-title">Rush Order</span>
            <span class="cm-order-tag">Rush Fee</span>
            <span class="cm-order-desc">Pick any open date at least 7 days from today, with a rush fee of ₱500 per figure or ₱200 per Head Only Keychain. Max 2 orders per day and 10 per month.</span>
        </button>

        <button type="button" class="cm-order-card" id="nonrushCard" onclick="selectOrderType('nonrush')">
            <span class="cm-order-title">Non-Rush Order</span>
            <span class="cm-order-tag cm-free">No Extra Fee</span>
            <span class="cm-order-desc">Ships out on the last day of the month. Book at least 7 days before. Max 15 orders per ship out date.</span>
            <span class="cm-order-note">Most popular choice</span>
        </button>

    </div>

    <!-- Calendar + ship out date -->

    <div class="cm-schedule">

        <div class="cm-cal">

            <div class="cm-cal-head">
                <button type="button" class="cm-cal-arrow" id="prevMonth" onclick="changeMonth(-1)" aria-label="Previous month"><svg viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7"/></svg></button>
                <h3 id="calendarTitle">Calendar</h3>
                <button type="button" class="cm-cal-arrow" id="nextMonth" onclick="changeMonth(1)" aria-label="Next month"><svg viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg></button>
            </div>

            <div class="cm-weekdays">
                <span>SUN</span><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
            </div>

            <div class="cm-days" id="calendarDays"></div>

            <div class="cm-legend">
                <span class="cm-legend-item"><i class="cm-dot cm-dot-available"></i>Available</span>
                <span class="cm-legend-item"><i class="cm-dot cm-dot-today"></i>Today</span>
                <span class="cm-legend-item"><i class="cm-dot cm-dot-selected"></i>Your date</span>
                <span class="cm-legend-item" id="legendBookedItem" hidden><i class="cm-dot cm-dot-booked"></i>Fully booked</span>
                <span class="cm-legend-item"><i class="cm-dot cm-dot-unavailable"></i>Unavailable</span>
            </div>

        </div>

        <aside class="cm-ship">

            <dl class="cm-ship-list">
                <div class="cm-ship-row">
                    <dt>Order type</dt>
                    <dd id="shipTypeText">Not selected</dd>
                </div>
                <div class="cm-ship-row">
                    <dt>Ship out date</dt>
                    <dd id="shipDateText">No date picked yet</dd>
                </div>
            </dl>

            <div class="cm-ship-note">

                <p>
                    <b>Note:</b> The ship out date is the day we send out your figure. You can change it before you submit.
                </p>

                <b>Good to know</b>

                <!-- ang mga rule dito ay kapareho ng sinusunod ng calendar (commission.js) at ng submit_order.php -->
                <ul>
                    <li><b>Rush Order:</b> any open date at least 7 days from today. Max 2 rush orders per day and 10 per month. Rush fee: ₱500 per figure, or ₱200 per Head Only Keychain.</li>
                    <li><b>Non-Rush Order:</b> last day of the month only, booked at least 7 days before. Max 15 orders per ship out date. No extra fee.</li>
                    <li><b>Fully booked:</b> we only hold your date once your payment is approved, so a date can fill up first. If it does, pick a new date in My Orders.</li>
                    <li><b>Unpaid orders:</b> you can only have 3 unpaid orders at a time. Pay or cancel one in My Orders to order again.</li>
                    <li>Only current and upcoming months can be opened.</li>
                    <li><b>Figure Name</b> (Image Submission): ₱<?php echo number_format($prices["figure_name"], 2); ?> for up to 8 letters, plus ₱<?php echo number_format($prices["figure_name_letter"], 2); ?> for each extra letter.</li>
                </ul>

            </div>

        </aside>

    </div>

</section>


<!-- ====================== STEP 02 - pagpili kung paano gagawin ang figure (Image Submission o Dress Up) ====================== -->

<section>

    <div class="cm-head">
        <div>
            <h2>How would you like to create it?</h2>
            <p>Choose one. You can still go back and switch.</p>
        </div>
    </div>

    <!-- walang larawan: text lang, may maikling paliwanag kung ano ang laman ng bawat isa -->
    <div class="cm-methods">

        <!-- IMAGE SUBMISSION -->

        <div class="cm-method" id="referenceCard">

            <span class="cm-pill">STAFF QUOTATION · MOST REQUESTED</span>
            <h3>Image Submission</h3>
            <p>Send us photos of the person or character, and our staff will check them and send you a price quotation.</p>
            <b class="cm-inside-title">What's inside</b>
            <ul class="cm-checks">
                <li>Choose a figure style (Funko Pop, Hirono or Chibi), product type and size</li>
                <li>Upload up to 5 reference images per figure and add your notes</li>
                <li>Add a figure name and a custom box (optional)</li>
                <li>Add more figures and set the quantity of each</li>
                <li>Approve the quotation before you pay</li>
            </ul>
            <button type="button" class="cm-btn cm-btn-fill" onclick="selectMethod('reference')">Start with Image Submission</button>

        </div>

        <!-- DRESS UP -->

        <div class="cm-method" id="styleCard">

            <span class="cm-pill cm-pill-alt">CREATE YOUR DESIGN</span>
            <h3>Dress Up</h3>
            <p>Design your own figure on a 3D model. No photos needed, and you see the price right away.</p>
            <b class="cm-inside-title">What's inside</b>
            <ul class="cm-checks">
                <li>Choose a figure style, product type and size</li>
                <li>Pick the gender, skin tone, hair, top, bottom and shoes, and change their colors</li>
                <li>Turn and preview your figure in 3D</li>
                <li>Add a custom box (optional)</li>
                <li>See the total price before you submit</li>
            </ul>
            <button type="button" class="cm-btn cm-btn-fill" onclick="openDressUp()">Open Dress Up</button>

        </div>

    </div>

</section>


<!-- ====================== Dress Up ay may sarili nang page: dress-up.php (pinipili dito ang Order Type at Ship Out Date, tapos click ang Dress Up card) ====================== -->


</main>


<!-- ====================== footer ====================== -->
<?php include __DIR__ . "/../Shared/footer.php"; ?>
<!-- ====================== END FOOTER ====================== -->


<!-- ====================== [3] SCRIPT  (data galing sa PHP, tapos commission.js) ====================== -->
<script>
    const figurifyBookedDates = <?php echo json_encode($bookedDates); ?>;

    /* Rush: { "YYYY-MM-DD": count } - para sa daily/monthly rush cap (JS-computed) */
    const figurifyRushDayCounts = <?php echo json_encode($rushDayCounts); ?>;

    /* Non-Rush: { "YYYY-MM-DD": count } - para sa 15-order-per-date cap */
    const figurifyNonrushDateCounts = <?php echo json_encode($nonrushDateCounts); ?>;

    const FIGURIFY_RUSH_DAILY_CAP    = <?php echo (int) FIGURIFY_RUSH_DAILY_CAP; ?>;
    const FIGURIFY_RUSH_MONTHLY_CAP  = <?php echo (int) FIGURIFY_RUSH_MONTHLY_CAP; ?>;
    const FIGURIFY_NONRUSH_DATE_CAP  = <?php echo (int) FIGURIFY_NONRUSH_DATE_CAP; ?>;

</script>
<script src="commission.js"></script>
<!-- ====================== END SCRIPT ====================== -->

</body>
</html>
<!-- ====================== END HTML ====================== -->
