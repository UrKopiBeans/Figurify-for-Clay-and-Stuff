/* =========================================================
   commission.js  -  COMMISSION PAGE (commission.php)

   Schedule at pagpili ng method lang ang nandito:
    1. Mga variable                      (orderType, selectedDate)
    2. Order type at order method        (Rush / Non-Rush, Image Submission / Dress Up)
    3. Calendar                          (ship out date)
    4. Pagsimula ng page                 (DOMContentLoaded)

   Ang Image Submission form (figure cart, upload, presyo, submit)
   ay nasa image-submission.js na.
   ========================================================= */

/* =====================================================
   1. MGA VARIABLE
   ===================================================== */
    let orderType = "";

    let selectedDate = null;

    let orderMethod = "";

    let calendarDate = new Date();

    // styled notice/toast - ginamit ito sa halip ng alert para di na "localhost says..."
    // ang lumabas. type: "error" (default), "success", or "info"

    function showNotice(message,type){

        type = type || "error";

        // shared pop up (Shared/popup-and-security.php) - iisang itsura sa buong system
        if(window.csToast){

            window.csToast(message,type);

            return;

        }

        let container =
            document.getElementById("noticeContainer");

        if(!container){

            container =
                document.createElement("div");

            container.id = "noticeContainer";

            container.className = "notice-container";

            document.body.appendChild(container);

        }

        // clear any showing notice (and its auto-dismiss timer) so the new one
        // appears in the same spot instead of stacking
        container.innerHTML = "";

        const notice =
            document.createElement("div");

        notice.className =
            "notice-toast notice-" + type;

        const icon =
            type === "success"
                ? "✓"
                : (type === "info" ? "✦" : "!");

        notice.innerHTML =
            '<div class="notice-icon">' +
                icon +
            '</div>' +
            '<div class="notice-message"></div>';

        notice.querySelector(".notice-message").textContent =
            message;

        function removeNotice(){

            notice.classList.remove("notice-show");

            notice.classList.add("notice-hide");

            setTimeout(function(){

                if(notice.parentNode){

                    notice.parentNode.removeChild(notice);

                }

            },250);

        }

        container.appendChild(notice);

        requestAnimationFrame(function(){

            notice.classList.add("notice-show");

        });

        setTimeout(
            removeNotice,
            5000
        );

    }


    /* =====================================================
       2. ORDER TYPE AT ORDER METHOD
       Rush / Non-Rush, at pagpili kung Image Submission o Dress Up
       ===================================================== */

    /* ORDER TYPE + DATE MUNA: hindi makakapili ng Order Method
       hangga't walang napiling Rush/Non-Rush at ship out date - para
       makita agad ng customer ang available dates bago mag-customize. */
    function requireScheduleFirst(){

        if(orderType && selectedDate){
            return true;
        }

        showNotice(
            !orderType
                ? "Choose Rush or Non-Rush first."
                : "Pick a ship out date first.",
            "info"
        );

        // iiscroll at ihi-highlight ang kulang (kapareho ng Image Submission at Dress Up)
        const target =
            orderType
                ? document.getElementById("calendarDays")
                : document.querySelector(".cm-order-types");

        if(target){
            target.scrollIntoView({ behavior:"smooth", block:"center" });
            target.classList.add("field-missing");
            setTimeout(function(){
                target.classList.remove("field-missing");
            },3500);
        }

        return false;
    }

    // dress up

    function openDressUp(){

        if(!requireScheduleFirst()){
            return;
        }

        // ang dress-up.php na ang nagbubura ng napili sa Image Submission
        saveMethod("dressUp");

        // Dress Up ay may sarili nang page (dress-up.php). Naka-save na sa
        // sessionStorage ang Order Type at Ship Out Date (saveSchedule), kaya
        // doon na lang sila babasahin.
        window.location.href = "dress-up.php";

    }

    // image submission - may sarili nang page (image-submission.php).
    // Naka-save na sa sessionStorage ang Order Type at Ship Out Date (saveSchedule),
    // kaya doon na lang sila babasahin.

    function selectMethod(method){

        if(method !== "reference"){
            return;
        }

        if(!requireScheduleFirst()){
            return;
        }

        saveSchedule();

        // ang image-submission.php na ang nagbubura ng napili sa Dress Up
        saveMethod("reference");

        window.location.href = "image-submission.php";

    }

    // order type

    function selectOrderType(type){

        orderType = type;

        // "Fully Booked" legend: lalabas lang kapag may napili nang Order Type
        const legendBookedItem = document.getElementById("legendBookedItem");
        if(legendBookedItem){
            legendBookedItem.hidden = !orderType;
        }

        document
            .getElementById("rushCard")
            .classList.remove("selected");

        document
            .getElementById("nonrushCard")
            .classList.remove("selected");

        if(type === "rush"){

            document
                .getElementById("rushCard")
                .classList.add("selected");

        }

        else{

            document
                .getElementById("nonrushCard")
                .classList.add("selected");

        }

        selectedDate = null;

        calendarDate = getFirstOpenMonth(type);

        saveSchedule();

        renderCalendar();

    }

    // i-save at i-highlight ang napiling Order Method (reference = Image Submission, dressUp = Dress Up)

    function saveMethod(method){

        orderMethod = method;

        showMethod();

        try{

            sessionStorage.setItem(
                "figurifyCommissionMethod",
                orderMethod
            );

        }
        catch(error){

            console.warn("Unable to persist order method:", error);

        }

    }

    function showMethod(){

        document
            .getElementById("referenceCard")
            .classList.toggle("selected",orderMethod === "reference");

        document
            .getElementById("styleCard")
            .classList.toggle("selected",orderMethod === "dressUp");

    }

    // ibalik ang napiling Order Method galing sessionStorage

    function restoreSavedMethod(){

        try{
            orderMethod = sessionStorage.getItem("figurifyCommissionMethod") || "";
        }
        catch(error){
            return;
        }

        if(orderMethod === "reference" || orderMethod === "dressUp"){
            showMethod();
        }

    }

    // i-save ang Order Type at Ship Out Date sa sessionStorage (para sa order na ito lang,
    // nabubura kapag sinara ang tab o nag log out) - dito babasahin
    // ng image-submission.js at dress-up.js ang napili sa calendar

    function saveSchedule(){

        try{

            sessionStorage.setItem(
                "figurifyCommissionOrderType",
                orderType || ""
            );

            sessionStorage.setItem(
                "figurifyCommissionBookingDate",
                selectedDate
                    ? (selectedDate.getFullYear() + "-" +
                        String(selectedDate.getMonth() + 1).padStart(2,"0") + "-" +
                        String(selectedDate.getDate()).padStart(2,"0"))
                    : ""
            );

        }
        catch(error){

            console.warn("Unable to persist order type/date:", error);

        }

    }

    // calendar note

    /* =====================================================
       3. CALENDAR
       Pagpili ng ship out date
       ===================================================== */

    function updateScheduleUI(){

        const typeText =
            document.getElementById("shipTypeText");

        const dateText =
            document.getElementById("shipDateText");

        typeText.textContent =
            orderType === "rush"
                ? "Rush Order"
                : (orderType === "nonrush" ? "Non-Rush Order" : "Not selected");

        dateText.textContent =
            selectedDate
                ? selectedDate.toLocaleDateString(
                    "en-US",
                    {
                        weekday:"long",
                        month:"long",
                        day:"numeric",
                        year:"numeric"
                    }
                )
                : "No date picked yet";

    }

    // unang buwan na may pwedeng pilian: Rush = buwan ng (ngayon + 7 days);
    // Non-Rush = buwang ito, pero kung lampas na sa (last day - 7) ay susunod na buwan

    function getFirstOpenMonth(type){

        const target = new Date();

        target.setHours(0,0,0,0);

        if(type === "rush"){

            target.setDate(target.getDate() + 7);

        }

        else{

            const lastDay =
                new Date(target.getFullYear(),target.getMonth() + 1,0).getDate();

            if(target.getDate() > lastDay - 7){

                target.setMonth(target.getMonth() + 1,1);

            }

        }

        return new Date(target.getFullYear(),target.getMonth(),1);

    }

    // calendar init

    function initializeCalendar(){

        calendarDate = new Date();

        calendarDate.setDate(1);

        renderCalendar();

    }

    // render calendar

    function renderCalendar(){

        const container =
            document.getElementById("calendarDays");

        const title =
            document.getElementById("calendarTitle");

        container.innerHTML = "";

        const year =
            calendarDate.getFullYear();

        const month =
            calendarDate.getMonth();

        title.textContent =
            new Date(year,month,1)
                .toLocaleDateString(
                    "en-PH",
                    {
                        month:"long",
                        year:"numeric"
                    }
                );

        const firstDay =
            new Date(
                year,
                month,
                1
            ).getDay();

        const daysInMonth =
            new Date(
                year,
                month + 1,
                0
            ).getDate();

        for(let i=0;i<firstDay;i++){

            const empty =
                document.createElement("div");

            empty.className = "day day-blank";

            container.appendChild(empty);

        }

        const today =
            new Date();

        today.setHours(
            0,
            0,
            0,
            0
        );

        const minimumRushDate =
            new Date(today);

        minimumRushDate.setDate(
            minimumRushDate.getDate() + 7
        );

        for(
            let day=1;
            day<=daysInMonth;
            day++
        ){

            const button =
                document.createElement("button");

            button.type = "button";

            button.className = "day";

            button.textContent = day;

            const date =
                new Date(
                    year,
                    month,
                    day
                );

            date.setHours(
                0,
                0,
                0,
                0
            );

            if(date.getTime() === today.getTime()){

                button.classList.add("today");

            }

            let unavailable = false;

            const dateString =
                year +
                "-" +
                String(month + 1).padStart(2, "0") +
                "-" +
                String(day).padStart(2, "0");

            // capacity caps (rush: 2/day, non-rush: 15/date) lang ang basehan ng "full" -
            // ang petsang may 1 order pa lang ay hindi kinukulayan

            let isBooked = false;
            let unavailableReason = null;

            if(orderType === ""){

                unavailable = true;

            }

            if(orderType === "rush"){

                if(date < minimumRushDate){

                    unavailable = true;

                }

                // rush - daily cap (max 2 orders/day, puwedeng magkaibang customer)
                const rushDayCounts =
                    (typeof figurifyRushDayCounts !== "undefined")
                        ? figurifyRushDayCounts
                        : {};

                const rushDailyCap =
                    (typeof FIGURIFY_RUSH_DAILY_CAP !== "undefined")
                        ? FIGURIFY_RUSH_DAILY_CAP
                        : 2;

                const rushCountThisDay =
                    rushDayCounts[dateString] || 0;

                if(rushCountThisDay >= rushDailyCap){

                    unavailable = true;
                    isBooked = true;
                    unavailableReason = "Already has the maximum of " + rushDailyCap + " rush orders for this day";

                }

                /* RUSH - MONTHLY CAP (max 10 orders/month), kino-
                   compute mula sa parehong rushDayCounts (isinasama
                   ang lahat ng petsa sa parehong taon-buwan) */
                const rushMonthlyCap =
                    (typeof FIGURIFY_RUSH_MONTHLY_CAP !== "undefined")
                        ? FIGURIFY_RUSH_MONTHLY_CAP
                        : 10;

                const monthPrefix =
                    year + "-" + String(month + 1).padStart(2, "0");

                let rushMonthTotal = 0;

                for(const d in rushDayCounts){

                    if(d.indexOf(monthPrefix) === 0){

                        rushMonthTotal += rushDayCounts[d];

                    }

                }

                if(rushMonthTotal >= rushMonthlyCap){

                    unavailable = true;
                    unavailableReason = "Rush order slots for this month are already full (" + rushMonthlyCap + "/month)";

                }

            }

            if(orderType === "nonrush"){

                const lastDay =
                    new Date(
                        year,
                        month + 1,
                        0
                    ).getDate();

                if(day !== lastDay){

                    unavailable = true;

                }

                const bookingDeadline =
                    new Date(
                        year,
                        month,
                        lastDay - 7
                    );

                bookingDeadline.setHours(
                    0,
                    0,
                    0,
                    0
                );

                if(today > bookingDeadline){

                    unavailable = true;

                }

                /* Non-rush: max 15 order/last-day-of-month, block kapag puno. */
                if(day === lastDay){

                    const nonrushDateCounts =
                        (typeof figurifyNonrushDateCounts !== "undefined")
                            ? figurifyNonrushDateCounts
                            : {};

                    const nonrushCap =
                        (typeof FIGURIFY_NONRUSH_DATE_CAP !== "undefined")
                            ? FIGURIFY_NONRUSH_DATE_CAP
                            : 15;

                    const nonrushCountThisDate =
                        nonrushDateCounts[dateString] || 0;

                    if(nonrushCountThisDate >= nonrushCap){

                        unavailable = true;
                        isBooked = true;
                        unavailableReason = "This date is already fully booked (" + nonrushCap + " orders). Try next month";

                    }

                }

            }

            /* Hindi na kinukulayan ang petsang may 1 order lang - "booked" (red)
               ay para lang sa FULL na petsa (nakalagay na sa isBooked sa taas). */

            if(
                selectedDate &&
                sameDate(date,selectedDate)
            ){

                button.classList.add("selected");

            }

            if(unavailable){

                button.classList.add("unavailable");

                button.disabled = true;

                if(isBooked){

                    button.classList.add("booked");

                    button.title = unavailableReason || "Already booked";

                } else if(unavailableReason){

                    button.title = unavailableReason;

                }

            }

            else{

                button.classList.add("available");

                /* Walang "Limited" na kulay - available pa rin ang petsa hanggang hindi pa full */

                button.addEventListener(
                    "click",
                    function(){

                        selectDate(date);

                    }
                );

            }

            container.appendChild(button);

        }

        // hindi na makakabalik sa buwang tapos na (previous month disabled sa kasalukuyang buwan)
        document
            .getElementById("prevMonth")
            .disabled =
            isCurrentOrPastMonth();

        updateScheduleUI();

    }

    // same date

    function sameDate(a,b){

        return(
            a.getFullYear() === b.getFullYear() &&
            a.getMonth() === b.getMonth() &&
            a.getDate() === b.getDate()
        );

    }

    // select date

    function selectDate(date){

        selectedDate =
            new Date(date);

        selectedDate.setHours(
            0,
            0,
            0,
            0
        );

        renderCalendar();

        saveSchedule();

    }

    // true kapag ang ipinapakitang buwan ay ang kasalukuyan (o nauna pa)

    function isCurrentOrPastMonth(){

        const now = new Date();

        return (
            calendarDate.getFullYear() * 12 + calendarDate.getMonth() <=
            now.getFullYear() * 12 + now.getMonth()
        );

    }

    // change month

    let calendarMoving = false;

    function changeMonth(amount){

        if(calendarMoving || (amount < 0 && isCurrentOrPastMonth())){

            return;

        }

        calendarMoving = true;

        const days =
            document.getElementById("calendarDays");

        const slide =
            amount > 0 ? "cm-days-next" : "cm-days-prev";

        // mabagal na fade-out, tapos lipat ng buwan, tapos fade-in

        days.classList.add(slide);

        setTimeout(function(){

            calendarDate.setMonth(
                calendarDate.getMonth() + amount
            );

            renderCalendar();

            days.classList.remove(slide);

            calendarMoving = false;

        },350);

    }


    /* =====================================================
       4. PAGSIMULA NG PAGE
       Tumatakbo ito pagkabukas ng page
       ===================================================== */

    // ibalik ang dating Order Type at Ship Out Date galing sessionStorage

    function restoreSavedSchedule(){

        let savedOrderType = "";
        let savedBookingDate = "";

        try{
            savedOrderType = sessionStorage.getItem("figurifyCommissionOrderType") || "";
            savedBookingDate = sessionStorage.getItem("figurifyCommissionBookingDate") || "";
        }
        catch(error){
            return;
        }

        if(savedOrderType !== "rush" && savedOrderType !== "nonrush"){
            return;
        }

        selectOrderType(savedOrderType);

        if(/^\d{4}-\d{2}-\d{2}$/.test(savedBookingDate)){

            const restoredDate =
                new Date(savedBookingDate + "T00:00:00");

            if(!Number.isNaN(restoredDate.getTime())){

                calendarDate = new Date(restoredDate);
                calendarDate.setDate(1);

                renderCalendar();

                // ibalik lang kung available pa rin sa calendar (7 days para sa Rush,
                // last day ng buwan para sa Non-Rush, hindi pa puno) - kung hindi na, walang napili
                const stillAvailable =
                    Array.from(document.querySelectorAll("#calendarDays .day.available"))
                        .some(function(button){
                            return Number(button.textContent) === restoredDate.getDate();
                        });

                if(stillAvailable){

                    selectDate(restoredDate);

                }
                else{

                    calendarDate = getFirstOpenMonth(savedOrderType);

                    renderCalendar();

                }

            }

        }

    }

    // start

    document.addEventListener(
        "DOMContentLoaded",
        function(){

            initializeCalendar();

            // laging ibalik ang napili na (Back ng Chrome, "Change", refresh)
            restoreSavedSchedule();

            restoreSavedMethod();

            if(window.location.hash === "#createStyleForm"){

                openDressUp();

            }
            else if(window.location.hash === "#customReferenceForm"){

                // lumang link - Image Submission ay may sarili nang page
                window.location.replace("image-submission.php");

            }
            else if(/[?&]need=schedule\b/.test(window.location.search)){
                // galing sa dress-up.php / image-submission.php na walang naka-save na Order Type / Ship Out Date
                requireScheduleFirst();
            }

        }
    );
