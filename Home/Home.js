/* ==========================================================================
   HOME PAGE - JS   (Home/Home.js)
   LAMAN:  [1] HERO CAROUSEL
           [2] ABOUT US VIDEO
   ========================================================================== */


/* ====================== [1] HERO CAROUSEL ======================
   Awtomatikong lilipat ng slide kada 4.5 seconds, laging pakanan:
   slide 1 -> 2 -> 3 -> 1 -> 2 ... (hindi umaatras pabalik sa una).
   Paano: may kopya ng slide 1 sa dulo ng track. Pagdating doon,
   tahimik na ibinabalik ang track sa totoong slide 1 (walang animation).
   May arrow (kaliwa/kanan) at tuldok din. Ang arrow na kaliwa sa slide 1
   ay tahimik na tumatalon muna sa kopya sa dulo, tapos aatras.
   Markup: Home.php > "HERO CAROUSEL"   |   Style: Home.css [2]
   ================================================================ */
/* ====================== [0] HOME.CSS RULE ======================
   Para sa mga nagbabagong value (posisyon ng carousel, haba ng video bar):
   isinusulat sa sariling rule sa Home.css (hal. "#carouselTrack {}"),
   hindi sa inline style ng element - walang inline style.
   ================================================================ */
function homeCssRule(selector) {
    for (const sheet of document.styleSheets) {
        if (!sheet.href || sheet.href.indexOf("Home.css") === -1) {
            continue;
        }
        return sheet.cssRules[sheet.insertRule(selector + " {}", sheet.cssRules.length)];
    }
    return null;
}
/* ====================== END [0] HOME.CSS RULE ====================== */


document.addEventListener("DOMContentLoaded", function () {

    const track = document.getElementById("carouselTrack");
    const trackRule = homeCssRule("#carouselTrack");
    const slides = track ? track.querySelectorAll(".carousel-slide") : [];
    const dots = document.querySelectorAll("#carouselDots .dot");
    const prevButton = document.getElementById("carouselPrev");
    const nextButton = document.getElementById("carouselNext");
    const total = slides.length;

    if (total < 2) {
        return;
    }

    // kopya ng unang slide sa dulo (para tuloy-tuloy ang galaw pakanan)
    const firstCopy = slides[0].cloneNode(true);
    firstCopy.setAttribute("aria-hidden", "true");
    track.appendChild(firstCopy);

    let current = 0;   // anong slide ang nakikita ngayon (0 = una, total = kopya ng una)
    let timer;

    // ilipat ang track; animate = false para sa tahimik na pagbalik sa una
    function moveTo(index, animate) {
        current = index;
        track.classList.toggle("no-anim", !animate);
        if (trackRule) {
            trackRule.style.transform = "translateX(-" + (current * 100) + "%)";
        }

        // i-highlight ang tamang tuldok (ang kopya ay bilang slide 1)
        dots.forEach(function (dot, i) {
            dot.classList.toggle("active", i === current % total);
        });
    }

    // susunod na slide - laging pakanan
    function nextSlide() {
        if (current >= total) {
            return;   // hinihintay pa ang tahimik na pagbalik
        }
        moveTo(current + 1, true);
    }

    // nakaraang slide - kung nasa una, tumalon muna sa kopya sa dulo (walang animation)
    function prevSlide() {
        if (current >= total) {
            return;   // hinihintay pa ang tahimik na pagbalik
        }
        if (current === 0) {
            moveTo(total, false);
            void track.offsetWidth;          // ilapat agad bago umatras
        }
        moveTo(current - 1, true);
    }

    // pagkatapos makarating sa kopya ng slide 1: balik sa totoong slide 1 nang walang animation
    track.addEventListener("transitionend", function (event) {
        if (event.target !== track || current !== total) {
            return;
        }
        moveTo(0, false);
        void track.offsetWidth;          // ilapat agad bago ibalik ang animation
        track.classList.remove("no-anim");
    });

    // simulan (o i-restart) ang auto-slide
    function startTimer() {
        clearInterval(timer);
        timer = setInterval(nextSlide, 4500);
    }

    // kapag cinlick ang tuldok, pumunta sa slide na iyon
    dots.forEach(function (dot, i) {
        dot.addEventListener("click", function () {
            moveTo(i, true);
            startTimer();
        });
    });

    // arrow
    if (prevButton) {
        prevButton.addEventListener("click", function () {
            prevSlide();
            startTimer();
        });
    }

    if (nextButton) {
        nextButton.addEventListener("click", function () {
            nextSlide();
            startTimer();
        });
    }

    startTimer();

});
/* ====================== END HERO CAROUSEL ====================== */


/* ====================== [2] ABOUT US VIDEO ======================
   Sariling play button, progress bar at oras ng video.
   - click ang play button o ang video = play / pause
   - click ang progress bar = lumipat sa parteng iyon
   Markup: Home.php > "ABOUT US"   |   Style: Home.css [3]
   ================================================================ */
document.addEventListener("DOMContentLoaded", function () {

    const phone = document.getElementById("aboutPhone");
    const video = document.getElementById("aboutVideo");
    const playButton = document.getElementById("aboutPlay");
    const bar = document.getElementById("aboutBar");
    const barRule = homeCssRule("#aboutBarFill");
    const timeText = document.getElementById("aboutTime");

    if (!phone || !video) {
        return;
    }

    // 75 seconds -> "1:15"
    function formatTime(seconds) {
        const whole = Math.floor(seconds || 0);
        return Math.floor(whole / 60) + ":" + String(whole % 60).padStart(2, "0");
    }

    function showProgress() {
        const duration = video.duration || 0;
        if (barRule) {
            barRule.style.width = duration ? (video.currentTime / duration * 100) + "%" : "0";
        }
        timeText.textContent = formatTime(video.currentTime) + " / " + formatTime(duration);
    }

    function togglePlay() {
        if (video.paused) {
            video.play();
        } else {
            video.pause();
        }
    }

    playButton.addEventListener("click", togglePlay);
    video.addEventListener("click", togglePlay);

    video.addEventListener("play", function () { phone.classList.add("is-playing"); });
    video.addEventListener("pause", function () { phone.classList.remove("is-playing"); });
    video.addEventListener("ended", function () { phone.classList.remove("is-playing"); });

    video.addEventListener("loadedmetadata", showProgress);
    video.addEventListener("timeupdate", showProgress);

    bar.addEventListener("click", function (event) {
        const box = bar.getBoundingClientRect();
        video.currentTime = ((event.clientX - box.left) / box.width) * (video.duration || 0);
    });

    showProgress();

});
/* ====================== END ABOUT US VIDEO ====================== */
