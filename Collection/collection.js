/* ==========================================================================
   COLLECTION PAGE - JS   (Collection/collection.js)
   Markup: Collection/collection.php      Style: Collection/collection.css
   Walang CSS dito - "hidden" at class lang ang pinapalitan.

   LAMAN (taas pababa):
     [1] ELEMENTS          mga element na ginagamit
     [2] SORT              ayos ng card (random / size / price)
     [3] FILTER + SEARCH   itago ang card na hindi tugma
     [4] CLICK AT TYPE     pills, search box, sort dropdown
   ========================================================================== */


/* ==========================================================================
   [1] ELEMENTS
   ========================================================================== */
const cards       = document.querySelectorAll(".card");
const blocks      = document.querySelectorAll(".collection-block");
const pills       = document.querySelectorAll("#tagFilters .pill");
const searchInput = document.getElementById("searchInput");
const emptyState  = document.getElementById("emptyState");

const sortDropdown    = document.getElementById("sortDropdown");
const sortButton      = document.getElementById("sortButton");
const sortLabel       = document.getElementById("sortLabel");
const sortMenuButtons = document.querySelectorAll("#sortMenu button");

// kasalukuyang napiling filter at sort
let activeFilter = "all";
let activeSort   = "random";
/* ====================== END ELEMENTS ====================== */


/* ==========================================================================
   [2] SORT
   ========================================================================== */

// kunin ang unang numero sa text ng element, hal. "3.5 in" -> 3.5, "₱1,500" -> 1500
function getNumber(card, selector) {
    const element = card.querySelector(selector);
    const match   = element ? element.textContent.replace(/,/g, "").match(/[\d.]+/) : null;
    return match ? parseFloat(match[0]) : 0;
}

// paghambingin ang 2 card ayon sa napiling sort
function compareCards(a, b) {
    if (activeSort === "size-asc")   return getNumber(a, ".meta-chip") - getNumber(b, ".meta-chip");
    if (activeSort === "size-desc")  return getNumber(b, ".meta-chip") - getNumber(a, ".meta-chip");
    if (activeSort === "price-asc")  return getNumber(a, ".price") - getNumber(b, ".price");
    if (activeSort === "price-desc") return getNumber(b, ".price") - getNumber(a, ".price");

    return Math.random() - 0.5;   // "random"
}

// ayusin ang mga card sa loob ng collection
function applySort() {
    blocks.forEach(function (block) {
        const grid = block.querySelector(".card-grid");
        const cardsInBlock = Array.from(grid.querySelectorAll(".card"));

        cardsInBlock.sort(compareCards);

        // ibalik sa grid ayon sa bagong ayos
        cardsInBlock.forEach(function (card) {
            grid.appendChild(card);
        });
    });
}
/* ====================== END SORT ====================== */


/* ==========================================================================
   [3] FILTER + SEARCH
   ========================================================================== */

// ipakita lang ang card na tugma sa filter AT sa search
function applyFilters() {
    const searchText = searchInput.value.trim().toLowerCase();

    cards.forEach(function (card) {
        // tag ng card galing sa data-filter (hal. "normal chibi bestseller")
        const tags = card.dataset.filter.split(" ");

        const matchesFilter = activeFilter === "all" || tags.includes(activeFilter);
        const matchesSearch = card.textContent.toLowerCase().includes(searchText);

        card.hidden = !(matchesFilter && matchesSearch);
    });

    // itago ang buong section kapag wala nang nakikitang card sa loob
    blocks.forEach(function (block) {
        block.hidden = !block.querySelector(".card:not([hidden])");
    });

    // "No figures match..." lalabas lang kapag wala talagang nakikitang card
    const anyVisible = document.querySelector(".card:not([hidden])") !== null;
    emptyState.hidden = anyVisible;
}
/* ====================== END FILTER + SEARCH ====================== */


/* ==========================================================================
   [4] CLICK AT TYPE
   ========================================================================== */

// pill (All, Best Sellers, Chibi ...)
pills.forEach(function (pill) {
    pill.addEventListener("click", function () {
        pills.forEach(function (other) {
            other.classList.remove("active");
        });
        pill.classList.add("active");

        activeFilter = pill.dataset.filter;
        applyFilters();
    });
});

// search box
searchInput.addEventListener("input", applyFilters);

// buksan / isara ang sort dropdown
sortButton.addEventListener("click", function (event) {
    event.stopPropagation();
    sortDropdown.classList.toggle("open");
});

// isara ang dropdown kapag cinlick ang ibang parte ng page
document.addEventListener("click", function (event) {
    if (!sortDropdown.contains(event.target)) {
        sortDropdown.classList.remove("open");
    }
});

// pinili sa sort dropdown
sortMenuButtons.forEach(function (button) {
    button.addEventListener("click", function () {
        sortMenuButtons.forEach(function (other) {
            other.classList.remove("active");
        });
        button.classList.add("active");

        activeSort = button.dataset.sort;
        sortLabel.textContent = button.textContent.trim();
        sortDropdown.classList.remove("open");

        applySort();
    });
});

// pagbukas ng page: random ang ayos ng mga card
applySort();
/* ====================== END CLICK AT TYPE ====================== */
