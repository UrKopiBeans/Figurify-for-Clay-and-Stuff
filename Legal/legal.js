/* ==========================================================================
   LEGAL PAGES - JS   (Legal/legal.js)
   Iisang script ng Legal/privacy-policy.php at Legal/terms-and-conditions.php
   (dating nakakopya sa dalawang page).
   Style: Legal/legal.css [4] CONTENTS

   LAMAN:  [1] CONTENTS HIGHLIGHT
   ========================================================================== */


/* ====================== [1] CONTENTS HIGHLIGHT ======================
   Habang nag-i-scroll, naka-.active ang link ng section na nakikita.
   ==================================================================== */
(function () {
    const links    = document.querySelectorAll(".legal-toc a");
    const sections = document.querySelectorAll(".legal-section");

    if (!links.length || !("IntersectionObserver" in window)) {
        return;
    }

    function setActive(id) {
        links.forEach(function (link) {
            link.classList.toggle("active", link.getAttribute("href") === "#" + id);
        });
    }

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                setActive(entry.target.id);
            }
        });
    }, { rootMargin: "-15% 0px -70% 0px" });

    sections.forEach(function (section) {
        observer.observe(section);
    });

    setActive(sections[0].id);
})();
/* ====================== END CONTENTS HIGHLIGHT ====================== */
