<?php

// static page, same pattern as privacy-policy.php
// covers the account, website use, and commission orders (payment,
// revision, shipping, refunds) - linked from signup and the payment page

require_once __DIR__ . "/../helpers/session_helper.php";
figurify_start_session();

?>
<!doctype html>
<html lang="en">

<head>
    <?php /* [ALL-IN-ONE] font, background at pop up ng buong system - Shared/all-in-one.php */ include __DIR__ . "/../Shared/all-in-one.php"; ?>
    <link rel="icon" type="image/png" href="../Image/brand/logo.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clay and Stuff — Terms and Conditions</title>


    <link rel="stylesheet" href="legal.css?v=3">
</head>

<body>

<?php include "../Shared/navbar.php"; ?>

<header class="page-hero">
    <div class="page-wrap">
        <span class="page-eyebrow">ACCOUNT, ORDERS &amp; PAYMENTS</span>
        <h1>Terms and Conditions</h1>
        <p>The rules for your account, using this website, and placing an order. Last updated: <?php echo date("M Y"); ?></p>
    </div>
</header>

<main class="legal-page page-wrap">

    <div class="legal-layout">

        <aside class="legal-toc" aria-label="Contents">
            <p class="legal-toc-title">Contents</p>
            <nav>
                <a href="#sec-1">1. Acceptance of Terms</a>
                <a href="#sec-2">2. Who Can Use This Website</a>
                <a href="#sec-3">3. Your Account</a>
                <a href="#sec-4">4. Account Security</a>
                <a href="#sec-5">5. Acceptable Use</a>
                <a href="#orders">6. Placing a Commission Order</a>
                <a href="#sec-7">7. Payment</a>
                <a href="#sec-8">8. Production, Progress Updates and Revision</a>
                <a href="#sec-9">9. Shipping and Receiving Your Order</a>
                <a href="#sec-10">10. Cancellations and Refunds</a>
                <a href="#sec-11">11. Reviews</a>
                <a href="#sec-12">12. Intellectual Property</a>
                <a href="#sec-13">13. Suspension of Accounts</a>
                <a href="#sec-14">14. Limitation of Liability</a>
                <a href="#sec-15">15. Governing Law</a>
                <a href="#sec-16">16. Changes to These Terms</a>
                <a href="#sec-17">17. Contact Us</a>
            </nav>
        </aside>

        <div class="legal-content">

            <p class="legal-intro">These terms cover your account, your use of this website, and every commission order you place with Clay and Stuff. How we handle your personal information is explained in our <a href="privacy-policy.php">Privacy Policy</a>.</p>

        <section class="legal-section" id="sec-1">
            <h2>1. Acceptance of Terms</h2>
        <p>
            By creating an account, placing an order, or otherwise using this
            website, you agree to these Terms and Conditions and to our
            <a href="privacy-policy.php">Privacy Policy</a>. If you do not
            agree, please do not create an account or use this website.
        </p>
        </section>
        <section class="legal-section" id="sec-2">
            <h2>2. Who Can Use This Website</h2>
        <p>
            This website is intended for individuals who are at least 18
            years old, or who have the consent of a parent or guardian to
            create an account and transact with us.
        </p>
        </section>
        <section class="legal-section" id="sec-3">
            <h2>3. Your Account</h2>
        <p>
            When you sign up, you agree to provide accurate information
            (your name and a valid email address) and to keep it up to date.
            You may only create and use one account for yourself.
        </p>
        </section>
        <section class="legal-section" id="sec-4">
            <h2>4. Account Security</h2>
        <p>
            You are responsible for keeping your account password
            confidential and for all activity that happens under your
            account. For your protection, an account is locked for a short
            time after several wrong password attempts. Notify us immediately
            if you suspect unauthorized access to your account.
        </p>
        </section>
        <section class="legal-section" id="sec-5">
            <h2>5. Acceptable Use</h2>
        <p>
            You agree not to misuse this website — for example, by
            attempting to access another user's account, sending repeated or
            fake orders, interfering with the website's normal operation, or
            submitting false information or altered proof of payment. To
            prevent spam, a customer may only have three (3) unpaid orders at
            a time.
        </p>
        </section>
        <section class="legal-section" id="orders">
            <h2>6. Placing a Commission Order</h2>
        <ul>
            <li>You first choose an order type: a <strong>Rush Order</strong> (any available date at least 7 days from today, with a rush fee) or a <strong>Non-Rush Order</strong> (scheduled on the last day of the month).</li>
            <li>You then order through <strong>Dress Up</strong> (design your figure on the 3D model; the price is computed right away) or <strong>Image Submission</strong> (upload reference images; our staff reviews your request).</li>
            <li>For Image Submission, our staff either sends a price quotation or declines the request with a reason. A quotation is valid for <strong>2 days</strong>; if you do not continue within that time, the order is cancelled.</li>
            <li>Booking slots are limited and are given on a first-come, first-served basis. A slot is only held once you submit your down payment.</li>
            <li>You may cancel an order yourself in <strong>My Orders</strong> while it has not been paid yet.</li>
        </ul>
        </section>
        <section class="legal-section" id="sec-7">
            <h2>7. Payment</h2>
        <ul>
            <li>A <strong>50% down payment</strong> is required before production starts. The <strong>remaining balance</strong> is computed by the system and must be paid before the order is shipped.</li>
            <li>Payments are made through GCash or BPI. Each payment needs the reference number and a clear screenshot or photo of the receipt.</li>
            <li>Our staff first checks that your details and payment information are complete and correct. If something is wrong, you will see the reason in My Orders and can submit it again.</li>
            <li>The owner then confirms that the payment was received before your order moves forward. Submitting false or altered proof of payment is grounds for cancellation.</li>
        </ul>
        </section>
        <section class="legal-section" id="sec-8">
            <h2>8. Production, Progress Updates and Revision</h2>
        <p>
            Each figure is handmade based on the references, options, and
            details you provide, so small differences from the reference
            (color shade, exact proportions, small details) are normal and are
            not defects. During production you will receive progress photos.
            You may approve a photo or request <strong>one (1) revision</strong>
            per order; our staff will accept the revision if the change can
            still be made. If you do not respond to a progress photo within
            <strong>72 hours</strong>, it is approved automatically. If your
            commission is based on a character or artwork you do not own, you
            confirm that it is for personal, non-commercial use.
        </p>
        </section>
        <section class="legal-section" id="sec-9">
            <h2>9. Shipping and Receiving Your Order</h2>
        <p>
            Orders are shipped through LBC or Lalamove. You will receive a
            final photo of your figure, the tracking number, the shipping fee,
            and the courier receipt in My Orders. Once your order arrives,
            please click <strong>Order Received</strong>. If you do not, the
            order is marked as completed automatically 7 days after it was
            shipped. Delivery time and handling after hand-over are subject to
            the courier's own service, but we will help you file a claim if
            your order is damaged during shipping.
        </p>
        </section>
        <section class="legal-section" id="sec-10">
            <h2>10. Cancellations and Refunds</h2>
        <ul>
            <li><strong>Before payment is approved:</strong> you can cancel unpaid orders anytime in My Orders. If a submitted payment cannot be accepted because the order cannot be made, the order is cancelled and moved for refund.</li>
            <li><strong>After production has started:</strong> orders can no longer be cancelled for a full refund. A partial refund may be given at our discretion, minus materials and work already done.</li>
            <li><strong>Defective or incorrect orders:</strong> contact us within 7 days of receiving your order, with photos. We may offer a repair, a replacement, or a refund.</li>
            <li><strong>Damage during shipping:</strong> contact us within 48 hours of delivery with photos of the package and the item.</li>
            <li>Refunds (full or partial) are sent to the refund account you gave on the payment page, together with a reference number and proof of refund, usually within <span class="legal-placeholder">7–14 business days</span>. We are not responsible for refunds sent to an incorrect account you provided.</li>
        </ul>
        </section>
        <section class="legal-section" id="sec-11">
            <h2>11. Reviews</h2>
        <p>
            Reviews may only be submitted by customers for their own
            completed orders, either in My Orders or through the review link
            sent to your email. Reviews should be honest and based on genuine
            experience. We may remove reviews that are abusive, fraudulent,
            or unrelated to an actual order.
        </p>
        </section>
        <section class="legal-section" id="sec-12">
            <h2>12. Intellectual Property</h2>
        <p>
            The Clay and Stuff name, logo, and the content on this website
            (including catalog photos, figure style designs, page text, and
            layout) belong to us or are used with permission. You may not
            copy or reuse them for commercial purposes without our written
            consent. Reference images you upload remain yours and are only
            used to make your order.
        </p>
        </section>
        <section class="legal-section" id="sec-13">
            <h2>13. Suspension of Accounts</h2>
        <p>
            We may suspend or ban an account that violates these Terms — for
            example, through fraud, spam orders, abuse, or false information.
            A banned email address cannot log in or create a new account.
            This does not cancel an order already in production except as
            described in Section 10.
        </p>
        </section>
        <section class="legal-section" id="sec-14">
            <h2>14. Limitation of Liability</h2>
        <p>
            To the extent permitted by law, our liability for any claim about
            an order is limited to the amount you paid for that order, and we
            are not liable for indirect or consequential damages.
        </p>
        </section>
        <section class="legal-section" id="sec-15">
            <h2>15. Governing Law</h2>
        <p>
            These Terms are governed by the laws of the Republic of the
            Philippines, including the Consumer Act of the Philippines
            (RA 7394) and the Electronic Commerce Act (RA 8792), without
            prejudice to your rights under the Data Privacy Act of 2012
            (RA 10173) as described in our
            <a href="privacy-policy.php">Privacy Policy</a>.
        </p>
        </section>
        <section class="legal-section" id="sec-16">
            <h2>16. Changes to These Terms</h2>
        <p>
            We may update these Terms from time to time. Continued use of
            this website after changes are posted means you accept the
            updated Terms.
        </p>
        </section>
        <section class="legal-section" id="sec-17">
            <h2>17. Contact Us</h2>
        <p>
            Questions about these Terms or about an order can be sent to
            <span class="legal-placeholder">clayandstuff@gmail.com</span>.
        </p>
        </section>

            <div class="legal-note">
            This page is provided for transparency and general compliance
            purposes. It is not a substitute for formal legal advice.
        </div>

            <a class="legal-back-link" href="../Home/Home.php">&larr; Back to Home</a>

        </div>

    </div>

</main>

<?php include "../Shared/footer.php"; ?>


<script>
// i-highlight ang kasalukuyang section sa Contents
(function () {
    var links = document.querySelectorAll(".legal-toc a");
    var sections = document.querySelectorAll(".legal-section");
    if (!links.length || !("IntersectionObserver" in window)) { return; }

    function setActive(id) {
        links.forEach(function (a) {
            a.classList.toggle("active", a.getAttribute("href") === "#" + id);
        });
    }

    var obs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { if (e.isIntersecting) { setActive(e.target.id); } });
    }, { rootMargin: "-15% 0px -70% 0px" });

    sections.forEach(function (sec) { obs.observe(sec); });
    setActive(sections[0].id);
})();
</script>

</body>
</html>
