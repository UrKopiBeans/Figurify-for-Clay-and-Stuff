import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";

// DOM elements

const canvas = document.getElementById("figureViewer");
const figureArea = document.querySelector(".figure-area");
const dressUpTotalPrice = document.getElementById("dressUpTotalPrice");
const summaryTotalPrice = document.getElementById("summaryTotalPrice");
const resetBtn = document.getElementById("resetBtn");
const continueBtn = document.getElementById("continueBtn");
const productDetailsPanel = document.getElementById("productDetailsPanel");
const figureDetailsContainer = document.getElementById("figureDetailsContainer");
const sizeChoiceGrid = document.getElementById("sizeChoiceGrid");
const boxChoiceGrid = document.getElementById("boxChoiceGrid");
const boxNameInput = document.getElementById("boxNameInput");
const boxNumberInput = document.getElementById("boxNumberInput");
const boxColorInput = document.getElementById("boxColorInput");
const COMMISSION_ORDER_TYPE_KEY = "figurifyCommissionOrderType";
const COMMISSION_BOOKING_DATE_KEY = "figurifyCommissionBookingDate";
const COMMISSION_FIGURE_CATEGORY_KEY = "figurifyCommissionFigureCategory";
const CUSTOMIZATION_STORAGE_KEY = "figurifyCustomization";
const pageParams = new URLSearchParams(window.location.search);
const isEditPage = pageParams.get("edit") === "1";

// "?resume=1" - ibalik ang design sa eksaktong step (hindi mag-reset pabalik sa Figure Style)
const isResumePage = pageParams.get("resume") === "1";

// "?summary=1" - ibalik ang design at buksan agad ang Order Summary
// (hal. pagkatapos mag-edit ng naka-save na figure)
const isSummaryRequest = pageParams.get("summary") === "1";

// bukas ba ang Order Summary (nasa page na ito; dating finalsummary.php)
let isSummaryOpen = false;
let editingCurrentFigure = false; // Edit sa Order Summary ng figure na hindi pa naka-save sa cart
let backToFirstStep = false; // pagsara ng Order Summary dahil sa Edit: bumalik sa Figure Details
const styleCards = Array.from(document.querySelectorAll('[data-tab="figure"]'));
const itemCards = Array.from(document.querySelectorAll("[data-slot]"));
const funkoPanel = document.getElementById("funkoPanel");
const hironoPanel = document.getElementById("hironoPanel");
const chibiPanel = document.getElementById("chibiPanel");
const figureStyleSection = document.getElementById("figureStyleSection");

const sectionIds = {
    funko: {
        skin: "funkoSkinSection",
        hair: "funkoHairSection",
        girlHair: "funkoGirlHairSection",
        girlTop: "funkoGirlTopSection",
        girlTopColor: "funkoGirlTopColorSection",
        girlBottom: "funkoGirlBottomSection",
        girlBottomColor: "funkoGirlBottomColorSection",
        bottomColor: "funkoBottomPantsColorSection",
        hairColor: "funkoHairColorSection",
        top: "funkoTopSection",
        topColor: "funkoTopColorSection",
        bottom: "funkoBottomSection",
        pantsColor: "funkoBottomPantsColorSection",
        shoes: "funkoShoesSection",
        // Funko Girl: sariling shoes section (hindi na gagamitin ang shoes ng boy)
        girlShoes: "funkoGirlShoesSection"
    },
    hirono: {
        type: "hironoTypeSection",
        standee: "hironoStandeeSection",
        keychain: "hironoKeychainSection",
        skin: "hironoSkinSection",
        hair: "hironoHairSection",
        hairColor: "hironoHairColorSection",
        outfit: "hironoOutfitSection",
        outfitColor: "hironoOutfitColorSection",
        pants: "hironoPantsSection",
        pantsColor: "hironoPantsColorSection",
        shoes: "hironoShoesSection",
        shoesColor: "hironoShoesColorSection",
        keychainHair: "hironoKeychainHairSection",
        keychainHairColor: "hironoKeychainHairColorSection",
        keychainHat: "hironoKeychainHatSection"
    },
    chibi: {
        type: "chibiTypeSection",
        skin: "chibiSkinSection",
        hair: "chibiHairSection",
        girlHair: "chibiGirlHairSection",
        hairColor: "chibiHairColorSection",
        girlHairColor: "chibiGirlHairColorSection",
        top: "chibiTopSection",
        girlTop: "chibiGirlTopSection",
        bottom: "chibiBottomSection",
        girlBottom: "chibiGirlBottomSection",
        shoes: "chibiShoesSection",
        // Chibi Boy (standee) - sariling shoes ng bagong Chibi Boy set
        boyShoes: "chibiBoyShoesSection",
        // Keychain may sariling top/bottom models (galing sa DressUp)
        keychainBoyHair: "chibiBoyKeychainHairSection",
        keychainBoyTop: "chibiBoyKeychainTopSection",
        keychainBoyBottom: "chibiBoyKeychainBottomSection",
        keychainBoyShoes: "chibiBoyKeychainShoesSection",
        keychainGirlHair: "chibiGirlKeychainHairSection",
        keychainGirlShoes: "chibiGirlKeychainShoesSection",
        keychainGirlTop: "chibiGirlKeychainTopSection",
        keychainGirlBottom: "chibiGirlKeychainBottomSection"
    }
};

const categoryPanels = { funko: funkoPanel, hirono: hironoPanel, chibi: chibiPanel };

/* =====================================================
   2. NAKA-SAVE NA DATA (sessionStorage - para sa order na ito lang, nabubura kapag sinara ang tab o nag log out)
   Order type, ship out date at napiling figure galing Commission page
   ===================================================== */

function getStorageValue(key) {
    try {
        return sessionStorage.getItem(key);
    } catch (error) {
        console.warn("Unable to read storage key:", key, error);
        return null;
    }
}

function getCustomizationState() {
    const raw = getStorageValue(CUSTOMIZATION_STORAGE_KEY);
    if (!raw) {
        return null;
    }

    try {
        return JSON.parse(raw);
    } catch (error) {
        console.warn("Unable to parse customization state:", error);
        return null;
    }
}

function formatMoney(amount) {
    return "₱" + Number(amount || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatBookingDate(value) {
    if (!value) {
        return "Not selected";
    }

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return "Not selected";
    }

    return date.toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" });
}

function getCommissionOrderTypeValue() {
    return getStorageValue(COMMISSION_ORDER_TYPE_KEY);
}

function getCommissionOrderTypeLabel() {
    const value = getCommissionOrderTypeValue();
    if (value === "rush") {
        return "Rush Order";
    }

    if (value === "nonrush") {
        return "Non-Rush Order";
    }

    return "Not selected";
}

function getCommissionRushFee() {
    if (getCommissionOrderTypeValue() !== "rush") {
        return 0;
    }

    // Kapareho ng server (helpers/booking_helper.php): ₱200 ang rush fee ng
    // "Head Only" na produkto, ₱500 ang iba - para tugma ang total dito at sa
    // Payment page.
    if (state.currentCategory === "hirono" && state.hirono.mode === "headKeychain") {
        return 200;
    }

    return 500;
}

function getCommissionBookingDateValue() {
    return getStorageValue(COMMISSION_BOOKING_DATE_KEY);
}

function getCommissionBookingDateLabel() {
    return formatBookingDate(getCommissionBookingDateValue());
}

function getCommissionBookingStatusLabel() {
    const value = getCommissionBookingDateValue();
    if (!value) {
        return "Choose a date from the calendar";
    }

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return "Choose a date from the calendar";
    }

    return date.toLocaleDateString("en-PH", { weekday: "long" });
}

function getCommissionFigureCategoryValue() {
    return getStorageValue(COMMISSION_FIGURE_CATEGORY_KEY);
}

/* =====================================================
   3. STATE
   Lahat ng napili ng customer (hair, top, bottom, shoes, skin, ...)
   ===================================================== */

// state

function createCategoryState() {
    return {
        mode: null,
        productType: null,
        model: null,
        skin: null,
        hair: null,
        girlHair: null,
        hairColor: null,
        girlHairColor: null,
        top: null,
        girlTop: null,
        topColor: null,
        girlTopColor: null,
        bottom: null,
        bottomColor: null,
        girlBottom: null,
        girlBottomColor: null,
        outfit: null,
        outfitColor: null,
        pants: null,
        pantsColor: null,
        shoes: null,
        shoesColor: null
    };
}

const state = {
    currentCategory: null,
    funko: createCategoryState(),
    hirono: createCategoryState(),
    chibi: createCategoryState()
};

// Presyo galing sa owner (Content Management > Prices) - <script id="figurifyPrices"> ng dress-up.php
// (helpers/content_helper.php SECTION 8). Kapareho ng Image Submission at ng server (submit_order.php).
const PRICES = JSON.parse(document.getElementById("figurifyPrices").textContent);

// presyo ng bawat size ng isang product type: { "3 inches": 900, "4 inches": 1200, ... }
function sizePrices(style, type) {
    const sizes = (PRICES.products[style][type] || {}).sizes || {};
    return Object.fromEntries(Object.entries(sizes).map(([size, price]) => [size + " inches", price]));
}

const PRODUCT_SIZE_PRICES = {
    funkoBoy: sizePrices("Funko Pop", "Full Body Standee"),
    funkoGirl: sizePrices("Funko Pop", "Full Body Standee"),
    chibiBoy: sizePrices("Chibi", "Full Body Standee"),
    chibiGirl: sizePrices("Chibi", "Full Body Standee"),
    chibiBoyKeychain: sizePrices("Chibi", "Full Body Keychain"),
    chibiGirlKeychain: sizePrices("Chibi", "Full Body Keychain"),
    hironoStandee: sizePrices("Hirono", "Full Body Standee"),
    hironoKeychain: sizePrices("Hirono", "Full Body Keychain")
};

// walang Size: iisang presyo ng product type (kapareho ng Image Submission)
const PRODUCT_FIXED_PRICES = {
    hironoHeadKeychain: PRICES.products.Hirono["Head Only Keychain"].price
};

// Funko Boy at Girl: parehong box
const FUNKO_BOXES = [
    { id: "solo", label: "Solo Box", price: PRICES.funko_box.solo, details: true, image: "boxes/funko-solo-box.jpg" },
    { id: "couple", label: "Couple Box", price: PRICES.funko_box.couple, details: true, image: "boxes/funko-couple-box.jpg" }
];

// Hirono Blind Box at Optional Extras (key = pangalan na ipinapasa sa server)
const HIRONO_BLIND_BOXES = [
    { id: "regular", label: "Regular Blind Box", price: PRICES.blind_box.regular, image: "figures/hirono/hirono-style.jpg" },
    {
        id: "set",
        label: "Blind Box Set",
        price: PRICES.blind_box.set,
        image: "boxes/blind-box-set.jpg",
        note: "Includes: Tear Blind Paper & Digital Art only"
    }
];

const HIRONO_ADDONS = [
    { id: "tearPaper", key: "tear_blind_paper", label: "Tear Blind Paper", price: PRICES.blind_extras.tear_blind_paper, image: "boxes/tear-blind-paper.jpg" },
    { id: "pouch", key: "pouch", label: "Pouch", price: PRICES.blind_extras.pouch, image: "boxes/pouch.jpg" },
    { id: "digitalArt", key: "digital_art", label: "Digital Art w/ Photo Card", price: PRICES.blind_extras.digital_art, image: "boxes/photocard.jpg" }
];

const PRODUCT_DETAIL_CONFIGS = {
    funkoBoy: {
        title: "Funko Pop Boy Details",
        description: "Custom Funko box for your figure.",
        sizes: Object.keys(PRODUCT_SIZE_PRICES.funkoBoy),
        boxes: FUNKO_BOXES,
        boxLabel: "Funko Box"
    },
    funkoGirl: {
        title: "Funko Pop Girl Details",
        description: "Custom Funko box for your figure.",
        sizes: Object.keys(PRODUCT_SIZE_PRICES.funkoBoy),
        boxes: FUNKO_BOXES,
        boxLabel: "Funko Box"
    },
    hironoStandee: {
        title: "Hirono Standee Details",
        description: "Full body standee with blind box options and add-ons.",
        sizes: Object.keys(PRODUCT_SIZE_PRICES.hironoStandee),
        boxes: [],
        hirono: true
    },
    hironoKeychain: {
        title: "Hirono Keychain Details",
        description: "Keychain with blind box options and add-ons.",
        sizes: Object.keys(PRODUCT_SIZE_PRICES.hironoKeychain),
        boxes: [],
        hirono: true
    },
    hironoHeadKeychain: {
        title: "Hirono Head Keychain Details",
        description: "Head-only keychain with blind box options and add-ons.",
        sizes: [],
        boxes: [],
        hirono: true
    },
    chibiBoy: {
        title: "Chibi Boy Details",
        description: "Chibi figures do not have box choices.",
        sizes: Object.keys(PRODUCT_SIZE_PRICES.chibiBoy),
        boxes: []
    },
    chibiBoyKeychain: {
        title: "Chibi Boy Keychain Details",
        description: "Chibi keychains do not have box choices.",
        sizes: Object.keys(PRODUCT_SIZE_PRICES.chibiBoyKeychain),
        boxes: []
    },
    chibiGirl: {
        title: "Chibi Girl Details",
        description: "Chibi figures do not have box choices.",
        sizes: Object.keys(PRODUCT_SIZE_PRICES.chibiBoy),
        boxes: []
    },
    chibiGirlKeychain: {
        title: "Chibi Girl Keychain Details",
        description: "Chibi keychains do not have box choices.",
        sizes: Object.keys(PRODUCT_SIZE_PRICES.chibiGirlKeychain),
        boxes: []
    }
};

// Size at Box ng figure (walang laman sa simula). Iisang gawaan para sa bago at naka-save.
function emptyProductDetails(productKey = "", size = "", sizeSelected = false) {
    return {
        productKey,
        size,
        sizeSelected,
        figureName: "", // walang Figure Name sa Dress Up
        box: "",
        boxSelected: false,
        boxName: "",
        boxNumber: "",
        boxColor: "",
        blindBox: "regular",
        blindBoxSelected: false,
        hironoAddons: [],
        boxDesign: "checkered",
        boxDesignSelected: false,
        boxNickname: "",
        boxLetter: "",
        boxDateYmd: ""
    };
}

// ibinabalik ang Size/Box at ang napili sa bawat figure mula sa naka-save na design
function applySavedDetailsAndState(saved) {
    const sizeSelected = Boolean(saved.figureSizeSelected);
    productDetails = {
        ...emptyProductDetails(saved.productKey || "", sizeSelected ? saved.figureSize || "" : "", sizeSelected),
        box: saved.boxSelected ? saved.boxType || "" : "",
        boxSelected: Boolean(saved.boxSelected),
        boxName: saved.boxName || "",
        boxNumber: saved.boxNumber || "",
        boxColor: saved.boxColor || "",
        blindBox: saved.blindBoxSelected ? saved.blindBox || "regular" : "regular",
        blindBoxSelected: Boolean(saved.blindBoxSelected),
        hironoAddons: Array.isArray(saved.hironoAddons) ? saved.hironoAddons : [],
        boxDesign: saved.boxDesignSelected ? saved.boxDesign || "checkered" : "checkered",
        boxDesignSelected: Boolean(saved.boxDesignSelected),
        boxNickname: saved.boxNickname || "",
        boxLetter: saved.boxLetter || "",
        boxDateYmd: saved.boxDateYmd || ""
    };
    Object.keys(categoryPanels).forEach(function (category) {
        if (saved[category]) {
            state[category] = Object.assign(createCategoryState(), saved[category]);
        }
    });
}

let productDetails = emptyProductDetails();
let navigationSlot = null;

// Figure Details = isang screen: Figure Style, Product Type, Size, Gender, Skin Tone.
// Parts = Hair, Top, Bottom, Shoes lang (wala nang Gender / Skin Tone doon).
const FIRST_GROUP_SLOTS = ["productType", "mode", "size", "model", "skin"];

// WIZARD SHORTCUT (pills sa taas: Figure Details / Parts / Box / Review)
// wizardMaxStep = pinakamalayong step na naabot na at hindi pa nababago ang pinili.
// Clickable ang mga pill hanggang doon. Kapag may binago sa naunang step, ibabalik
// ito sa step na yun at ulit ang susunod na steps (Next ulit).
let wizardMaxStep = 1;
let wizardSigs = null;
let wizardArmed = false;
let activeSequentialSlots = [];
let onFirstStep = true;
let currentDesignSlot = null;
const pickedSlots = new Set();

const ACCESSORY_COLOR_SLOT = {
    hair: "hairColor",
    girlHair: "girlHairColor",
    girlTop: "girlTopColor",
    girlBottom: "girlBottomColor",
    top: "topColor",
    outfit: "outfitColor",
    pants: "pantsColor",
    shoes: "shoesColor",
    keychainHair: "keychainHairColor"
};

// pagkakasunod ng steps; ang magkaparehong figure ay iisang listahan ang gamit
const GIRL_SLOT_ORDER = [
    "model",
    "skin",
    "girlHair",
    "girlHairColor",
    "girlTop",
    "girlTopColor",
    "girlBottom",
    "girlBottomColor",
    "shoes",
    "shoesColor"
];
const CHIBI_BOY_SLOT_ORDER = [
    "model",
    "skin",
    "hair",
    "hairColor",
    "top",
    "topColor",
    "bottom",
    "bottomColor",
    "shoes",
    "shoesColor"
];
const HIRONO_SLOT_ORDER = [
    "model",
    "skin",
    "hair",
    "hairColor",
    "outfit",
    "outfitColor",
    "pants",
    "pantsColor",
    "shoes",
    "shoesColor"
];

const SLOT_ORDER = {
    funkoBoy: ["model", "skin", "hair", "hairColor", "top", "topColor", "bottom", "pantsColor", "shoes", "shoesColor"],
    funkoGirl: GIRL_SLOT_ORDER,
    chibiBoy: CHIBI_BOY_SLOT_ORDER,
    chibiGirl: GIRL_SLOT_ORDER,
    // Chibi keychain: same hair/top/bottom/shoes set ng standee
    chibiBoyKeychain: CHIBI_BOY_SLOT_ORDER,
    chibiGirlKeychain: GIRL_SLOT_ORDER,
    hironoStandee: HIRONO_SLOT_ORDER,
    hironoKeychain: HIRONO_SLOT_ORDER,
    hironoHeadKeychain: ["model", "skin", "keychainHair", "keychainHairColor", "keychainHat"]
};

/* =====================================================
   4. THREE.JS SETUP
   Scene, camera, lights at controls ng 3D viewer
   ===================================================== */

// three.js setup

const loader = new GLTFLoader();
let scene = null;
let camera = null;
let renderer = null;
let controls = null;
let figureResizeObserver = null;
let bodyModel = null;
let figureShadow = null;

// mga parte na may sariling 3D model (bukod sa katawan)
const ACCESSORY_SLOTS = [
    "hair",
    "girlHair",
    "top",
    "girlTop",
    "bottom",
    "girlBottom",
    "outfit",
    "pants",
    "shoes",
    "keychainHair",
    "keychainHat"
];
const currentObjects = Object.fromEntries(ACCESSORY_SLOTS.map((slot) => [slot, null]));

// Ano na ang naka-render ngayon (para hindi ulit-ulitin ang buong figure
// tuwing may pipiliin - body at ang mga hindi nagbago ay hindi ni-re-reload)
let renderedBodyKey = null;
const renderedSlotSig = {};
const loadTokens = { body: 0, ...Object.fromEntries(ACCESSORY_SLOTS.map((slot) => [slot, 0])) };
let renderToken = 0;

/* =====================================================
   5. BASIC HELPERS
   Maliliit na function na ginagamit ng buong file
   ===================================================== */

// basic helpers

function getActiveState() {
    if (!state.currentCategory) {
        return null;
    }

    return state[state.currentCategory];
}

function getHironoMode() {
    const hironoState = state.hirono;
    return hironoState.mode || "standee";
}

function getActiveSlotOrder() {
    if (state.currentCategory === "hirono") {
        if (getHironoMode() === "headKeychain") {
            return SLOT_ORDER.hironoHeadKeychain;
        }

        return getHironoMode() === "keychain" ? SLOT_ORDER.hironoKeychain : SLOT_ORDER.hironoStandee;
    }

    if (state.currentCategory === "funko") {
        return isGirlModel(state.funko.model) ? SLOT_ORDER.funkoGirl : SLOT_ORDER.funkoBoy;
    }

    if (state.currentCategory === "chibi") {
        if (state.chibi.productType === "keychain") {
            return isGirlModel(state.chibi.model) ? SLOT_ORDER.chibiGirlKeychain : SLOT_ORDER.chibiBoyKeychain;
        }

        return isGirlModel(state.chibi.model) ? SLOT_ORDER.chibiGirl : SLOT_ORDER.chibiBoy;
    }

    return SLOT_ORDER[state.currentCategory] || [];
}

function getSlotColorSlot(slot) {
    const category = state.currentCategory;
    if (category === "funko" && isGirlModel(state.funko.model)) {
        if (slot === "hairColor") {
            return "girlHair";
        }

        if (slot === "topColor") {
            return "girlTop";
        }

        if (slot === "bottomColor") {
            return "girlBottom";
        }
    }

    // Funko bottom may sariling pantsColor handling (applyBottomPartColors)
    if (slot === "bottom" && category !== "funko") {
        return "bottomColor";
    }

    return ACCESSORY_COLOR_SLOT[slot] || null;
}

// Girl model = may "girl" sa pangalan ng model (Funko, Chibi, at iba pa)
function isGirlModel(modelItem) {
    return Boolean(modelItem && /girl/i.test(String(modelItem.name || "")));
}

/* =====================================================
   6. PRODUCT DETAILS
   Size at Box / Add-ons panel
   ===================================================== */

function getProductDetailsKey() {
    if (state.currentCategory === "hirono") {
        if (getHironoMode() === "headKeychain") {
            return "hironoHeadKeychain";
        }

        return getHironoMode() === "keychain" ? "hironoKeychain" : "hironoStandee";
    }

    if (state.currentCategory === "funko") {
        return isGirlModel(state.funko.model) ? "funkoGirl" : "funkoBoy";
    }

    if (state.currentCategory === "chibi") {
        if (state.chibi.productType === "keychain") {
            return isGirlModel(state.chibi.model) ? "chibiGirlKeychain" : "chibiBoyKeychain";
        }

        return isGirlModel(state.chibi.model) ? "chibiGirl" : "chibiBoy";
    }

    return "";
}

function getProductDetailsConfig() {
    return PRODUCT_DETAIL_CONFIGS[getProductDetailsKey()] || null;
}

// presyo ng size, o iisang presyo ng product type na walang size (hal. Hirono Head Only Keychain)
function getProductSizePrice() {
    const productKey = getProductDetailsKey();
    if (PRODUCT_FIXED_PRICES[productKey] !== undefined) {
        return PRODUCT_FIXED_PRICES[productKey];
    }

    return (PRODUCT_SIZE_PRICES[productKey] || {})[productDetails.size] || 0;
}

// napiling Hirono Blind Box (Regular kung hindi kilala), o null kung wala pa
function getSelectedBlindBox() {
    if (!productDetails.blindBoxSelected) {
        return null;
    }

    return HIRONO_BLIND_BOXES.find((box) => box.id === productDetails.blindBox) || HIRONO_BLIND_BOXES[0];
}

function getSelectedHironoAddons() {
    return HIRONO_ADDONS.filter((addon) => (productDetails.hironoAddons || []).includes(addon.id));
}

// box / add-ons (walang Figure Name sa Dress Up)
function getProductDetailsAddon() {
    const config = getProductDetailsConfig();
    if (!config) {
        return 0;
    }

    if (config.hirono) {
        const blindBox = getSelectedBlindBox();
        return (blindBox ? blindBox.price : 0) + getSelectedHironoAddons().reduce((total, addon) => total + addon.price, 0);
    }

    const selectedBox = config.boxes.find((box) => box.id === productDetails.box);
    return selectedBox ? selectedBox.price : 0;
}

function saveProductDetails() {
    saveCustomizationSnapshot(false, false);
    updatePriceDisplay();
    syncWizardPills();
}

function renderSizeSection() {
    const sizeSection = document.getElementById("figureSizeSection");
    if (!sizeSection || !sizeChoiceGrid) {
        return;
    }

    const activeState = getActiveState();
    const config = getProductDetailsConfig();
    const productKey = getProductDetailsKey();
    if (!activeState || !config) {
        return;
    }

    // Size ay kasama na sa Figure Details screen: lalabas agad (hindi na hinihintay
    // ang Gender/Skin). Kailangan lang ang Product Type (Chibi / Hirono).

    if (productDetails.productKey !== productKey) {
        // Lumipat lang ng Gender / Type sa loob ng parehong figure: panatilihin ang
        // napiling Size kung available pa rin sa bagong option.
        const keepSize =
            Boolean(productDetails.productKey) &&
            productDetails.productKey.startsWith(state.currentCategory) &&
            productDetails.sizeSelected &&
            config.sizes.includes(productDetails.size);
        productDetails = emptyProductDetails(productKey, keepSize ? productDetails.size : "", keepSize);
    }

    // Keep size empty until the customer explicitly chooses one. The first
    // size must not be treated as an automatic selection.
    if (productDetails.size && !config.sizes.includes(productDetails.size)) {
        productDetails.size = "";
    }

    sizeChoiceGrid.innerHTML = config.sizes.length
        ? config.sizes
              .map(
                  (size) => `
        <button type="button" class="detail-choice-card${productDetails.sizeSelected && productDetails.size === size ? " selected" : ""}" data-size="${size}">
            <strong>${size}</strong>
            <small>${formatMoney(PRODUCT_SIZE_PRICES[productKey]?.[size])}</small>
        </button>
    `
              )
              .join("")
        : `<p class="detail-hint">No size requirement for this product type.${
              PRODUCT_FIXED_PRICES[productKey] ? " Price: " + formatMoney(PRODUCT_FIXED_PRICES[productKey]) : ""
          }</p>`;
    sizeChoiceGrid.querySelectorAll("[data-size]").forEach((button) => {
        button.addEventListener("click", () => {
            productDetails.size = button.dataset.size;
            productDetails.sizeSelected = true;
            // Manatili sa Step 1 pagkapili ng Size; Next ang magdadala sa susunod na step
            navigationSlot = "size";
            renderSizeSection();
            saveProductDetails();

            // saveProductDetails alone doesn't recalculate Next's disabled state -
            // need updateSectionVisibility too, or Next stays disabled after picking Size.
            updateSectionVisibility();
        });
    });
}

// Box card na kapareho ng Custom Box sa image-submission.php (custom-box.css)
function boxCardHtml(dataAttr, selected, image, name, price, note) {
    return `
        <button type="button" class="box-type-option${selected ? " selected" : ""}" ${dataAttr}>
            <span class="image-check">✓</span>
            <img class="box-type-image" src="../Image/${image}" alt="${name}">
            <div class="box-type-info">
                <span class="box-type-name">${name}</span>
                ${price ? `<span class="box-type-price">${price}</span>` : ""}
                ${note ? `<span class="box-type-note">${note.replace("&", "&amp;")}</span>` : ""}
            </div>
        </button>
    `;
}

function renderProductDetailsPanel() {
    if (!productDetailsPanel || !boxChoiceGrid) {
        return;
    }

    if (navigationSlot !== "productDetails") {
        productDetailsPanel.hidden = true;
        return;
    }

    const activeState = getActiveState();
    const config = getProductDetailsConfig();
    const productKey = getProductDetailsKey();
    if (!activeState || !activeState.model || !activeState.skin || !config) {
        productDetailsPanel.hidden = true;
        return;
    }

    const requiredSlots =
        state.currentCategory === "chibi"
            ? ["productType", "model", "skin"]
            : state.currentCategory === "hirono"
              ? ["mode", "model", "skin"]
              : ["model", "skin"];
    if (!requiredSlots.every((slot) => Boolean(activeState[slot]))) {
        productDetailsPanel.hidden = true;
        return;
    }

    productDetailsPanel.hidden = false;
    if (productDetails.productKey !== productKey) {
        productDetails = emptyProductDetails(productKey);
    }

    if (productDetails.box && !config.boxes.some((box) => box.id === productDetails.box)) {
        productDetails.box = "";
    }

    // Walang box (Chibi): itago BUONG panel at alisin ang .box-mode
    // na posibleng naiwan galing sa Funko/Hirono na naunang na-render.
    if (!config.hirono && !config.boxes.length) {
        productDetailsPanel.hidden = true;
        productDetailsPanel.classList.remove("box-mode");
        return;
    }

    const title = document.getElementById("productDetailsTitle");
    const description = document.getElementById("productDetailsDescription");
    if (title) title.textContent = config.title;
    if (description) description.textContent = config.description;

    boxChoiceGrid.innerHTML = config.boxes
        .map((box) =>
            boxCardHtml(
                `data-box="${box.id}"`,
                productDetails.boxSelected && productDetails.box === box.id,
                box.image,
                box.label,
                box.price ? formatMoney(box.price) : "No additional fee"
            )
        )
        .join("");
    const hasBoxOptions = Boolean(config.hirono) || config.boxes.length > 0;

    // Funko/Hirono: "BOX / ADD-ONS" header lang, tago ang title/description (.box-mode)
    // gamitin ang productDetailsPanel sa labas, huwag mag-declare ulit ng const dito
    if (productDetailsPanel) {
        productDetailsPanel.classList.toggle("box-mode", hasBoxOptions);
    }

    [title, description].forEach((element) => {
        if (!element) return;
        element.hidden = hasBoxOptions;
    });
    const productDetailsKicker = document.getElementById("productDetailsKicker");
    if (productDetailsKicker) {
        productDetailsKicker.textContent = hasBoxOptions ? "BOX & ADD-ONS (OPTIONAL)" : "FIGURE DETAILS";
    }

    const boxChoiceGroup = document.getElementById("boxChoiceGroup");
    if (boxChoiceGroup) {
        boxChoiceGroup.hidden = config.hirono || !config.boxes.length;
        const boxLabel = boxChoiceGroup.querySelector(".box-title");
        if (boxLabel) {
            boxLabel.textContent = config.boxLabel || "Box";
        }
    }

    const boxDetailFields = document.getElementById("boxDetailFields");
    const selectedBox = config.boxes.find((box) => box.id === productDetails.box);
    const canEditFunkoBoxDetails = Boolean(selectedBox?.details);
    if (boxDetailFields) {
        // Chibi (walang box): itago ang buong fields
        boxDetailFields.hidden = config.hirono || !config.boxes.length;
    }

    const boxLockedHint = document.getElementById("boxLockedHint");
    if (boxLockedHint) {
        boxLockedHint.hidden = config.hirono || !config.boxes.length || canEditFunkoBoxDetails;
    }

    [boxNameInput, boxNumberInput, boxColorInput].forEach((input) => {
        if (input) input.disabled = !canEditFunkoBoxDetails;
    });
    const hironoFields = document.getElementById("hironoDetailFields");
    const hironoBlindBoxGrid = document.getElementById("hironoBlindBoxGrid");
    const hironoAddonGrid = document.getElementById("hironoAddonGrid");
    const hironoBoxDesignGrid = document.getElementById("hironoBoxDesignGrid");
    const hironoBoxFields = document.getElementById("hironoBoxFields");
    if (hironoFields) hironoFields.hidden = !config.hirono;

    // Regular / Set muna -> saka lalabas ang Optional Extras at Box Design
    // -> pagkapili ng Box Design saka lalabas ang Box Color, Nickname, Month, Day, Letter
    setVisibleById("hironoAddonSection", Boolean(config.hirono && productDetails.blindBoxSelected));
    setVisibleById("hironoDesignSection", Boolean(config.hirono && productDetails.blindBoxSelected));
    setVisibleById(
        "hironoBoxFields",
        Boolean(config.hirono && productDetails.blindBoxSelected && productDetails.boxDesignSelected)
    );
    if (config.hirono && hironoBlindBoxGrid && hironoAddonGrid && hironoBoxDesignGrid && hironoBoxFields) {
        hironoBlindBoxGrid.innerHTML = HIRONO_BLIND_BOXES
            .map((box) =>
                boxCardHtml(
                    `data-blind-box="${box.id}"`,
                    productDetails.blindBoxSelected && productDetails.blindBox === box.id,
                    box.image,
                    box.label,
                    formatMoney(box.price),
                    box.note
                )
            )
            .join("");
        hironoAddonGrid.innerHTML = HIRONO_ADDONS
            .map(
                ({ id, label, price, image }) => `
            <button type="button" class="blind-set-item${productDetails.hironoAddons.includes(id) ? " selected" : ""}" data-addon="${id}">
                <span class="image-check">✓</span>
                <img src="../Image/${image}" alt="${label}">
                <span class="blind-set-item-name">${label}</span>
                <span class="blind-set-item-price">${formatMoney(price)}</span>
            </button>
        `
            )
            .join("");
        hironoBoxDesignGrid.innerHTML = [
            { id: "checkered", label: "Checkered", image: "boxes/hirono-box-checkered.jpg" },
            { id: "peek", label: "Hirono Peek", image: "boxes/hirono-box-peek.jpg" }
        ]
            .map((box) =>
                boxCardHtml(
                    `data-box-design="${box.id}"`,
                    productDetails.boxDesignSelected && productDetails.boxDesign === box.id,
                    box.image,
                    box.label
                )
            )
            .join("");
        const colorField =
            productDetails.boxDesign === "peek"
                ? `<select class="field" data-detail-field="boxColor"><option value="">Select Box Color</option><option value="Wood">Wood</option><option value="Black & White">Black &amp; White</option></select>`
                : `<input class="field" data-detail-field="boxColor" value="${productDetails.boxColor}" maxlength="30" placeholder="Example: Cream White">`;
        const formattedBoxDate = formatMonthDay(productDetails.boxDateYmd);
        const [selectedMonth = "", selectedDay = ""] = formattedBoxDate.split("/");
        const monthOptions = [
            ["01", "January"],
            ["02", "February"],
            ["03", "March"],
            ["04", "April"],
            ["05", "May"],
            ["06", "June"],
            ["07", "July"],
            ["08", "August"],
            ["09", "September"],
            ["10", "October"],
            ["11", "November"],
            ["12", "December"]
        ]
            .map(
                ([value, label]) =>
                    `<option value="${value}"${selectedMonth === value ? " selected" : ""}>${label}</option>`
            )
            .join("");
        const dayOptions = Array.from({ length: 31 }, (_, index) => {
            const value = String(index + 1).padStart(2, "0");
            return `<option value="${value}"${selectedDay === value ? " selected" : ""}>${index + 1}</option>`;
        }).join("");
        hironoBoxFields.innerHTML = `
            <div class="field-group"><label>Box Color</label>${colorField}</div>
            <div class="field-group"><label>Nickname</label><input class="field" data-detail-field="boxNickname" value="${productDetails.boxNickname}" maxlength="30" placeholder="Example: Bubbles"></div>
            <div class="field-group"><label>Month</label><select class="field" id="boxDateMonth"><option value="">Month</option>${monthOptions}</select></div>
            <div class="field-group"><label>Day</label><select class="field" id="boxDateDay"><option value="">Day</option>${dayOptions}</select></div>
            <div class="field-group field-group-full"><label>Letter (Short Love Letter)</label><textarea class="field" data-detail-field="boxLetter" rows="4" placeholder="Write a short love letter/message here...">${productDetails.boxLetter}</textarea></div>
        `;

        // Month / Day -> "MM/DD" (isang beses lang naka-kabit dahil bagong render ito)
        const boxDateMonth = document.getElementById("boxDateMonth");
        const boxDateDay = document.getElementById("boxDateDay");
        boxDateMonth.onchange = boxDateDay.onchange = () => {
            productDetails.boxDateYmd =
                boxDateMonth.value && boxDateDay.value ? `${boxDateMonth.value}/${boxDateDay.value}` : "";
            saveProductDetails();
        };
        hironoAddonGrid.querySelectorAll("[data-addon]").forEach((button) => {
            button.addEventListener("click", () => {
                const addon = button.dataset.addon;
                productDetails.hironoAddons = productDetails.hironoAddons.includes(addon)
                    ? productDetails.hironoAddons.filter((item) => item !== addon)
                    : [...productDetails.hironoAddons, addon];
                renderProductDetailsPanel();
                saveProductDetails();
            });
        });
        hironoBlindBoxGrid.querySelectorAll("[data-blind-box]").forEach((button) => {
            button.addEventListener("click", () => {
                productDetails.blindBox = button.dataset.blindBox;
                productDetails.blindBoxSelected = true;
                renderProductDetailsPanel();
                saveProductDetails();
            });
        });
        hironoBoxDesignGrid.querySelectorAll("[data-box-design]").forEach((button) => {
            button.addEventListener("click", () => {
                productDetails.boxDesign = button.dataset.boxDesign;
                productDetails.boxDesignSelected = true;
                if (
                    productDetails.boxDesign === "peek" &&
                    !["Wood", "Black & White"].includes(productDetails.boxColor)
                ) {
                    productDetails.boxColor = "";
                }
                renderProductDetailsPanel();
                saveProductDetails();
            });
        });
    }

    boxChoiceGrid.querySelectorAll("[data-box]").forEach((button) => {
        button.addEventListener("click", () => {
            const boxId = button.dataset.box;

            // Toggle: pag-click ulit sa parehong selected box, i-unselect ito
            // (kapareho ng toggle behavior ng Optional Extras).
            const isAlreadySelected = productDetails.boxSelected && productDetails.box === boxId;
            if (isAlreadySelected) {
                productDetails.box = "";
                productDetails.boxSelected = false;
            } else {
                productDetails.box = boxId;
                productDetails.boxSelected = true;
            }

            renderProductDetailsPanel();
            saveProductDetails();
        });
    });
    if (boxNameInput) boxNameInput.value = productDetails.boxName;
    if (boxNumberInput) boxNumberInput.value = productDetails.boxNumber;
    if (boxColorInput) boxColorInput.value = productDetails.boxColor;
    productDetailsPanel.querySelectorAll("[data-detail-field]").forEach((input) => {
        input.value = productDetails[input.dataset.detailField] || "";
        input.oninput = () => {
            productDetails[input.dataset.detailField] = input.value;
            saveProductDetails();
        };
        input.onchange = input.oninput;
    });
}

/* =====================================================
   7. UI HELPERS
   Pagpakita / pagtago at pag-highlight ng cards
   ===================================================== */

function setVisibleById(id, visible) {
    const element = document.getElementById(id);
    if (element) {
        element.hidden = !visible;
    }
}

/* =====================================================
   LOADING NG 3D FIGURE
   - Wala pang figure: "Loading your figure…" sa gitna ng preview
   - May figure na (hal. pumili ng bagong Hair): maliit na "Loading Hair…" sa taas,
     nakikita pa rin ang figure habang naglo-load ang bago
   - Lumalabas lang kapag lagpas 250ms (walang kislap kapag mabilis / naka-cache)
   ===================================================== */
const figureLoadingBox = document.getElementById("figureLoading");
const figureLoadingText = document.getElementById("figureLoadingText");
// shown: true sa simula - nakalabas na ang loading sa HTML habang dina-download ang 3D
const figureLoading = { count: 0, label: "", timer: 0, shown: true };

function startFigureLoading(label) {
    figureLoading.count += 1;
    figureLoading.label = label || "";
    if (figureLoading.shown) {
        updateFigureLoadingView();
    } else if (!figureLoading.timer) {
        figureLoading.timer = setTimeout(function () {
            figureLoading.timer = 0;
            if (figureLoading.count > 0) {
                figureLoading.shown = true;
                updateFigureLoadingView();
            }
        }, 250);
    }
}

function endFigureLoading() {
    figureLoading.count = Math.max(0, figureLoading.count - 1);
    if (figureLoading.count === 0) {
        clearTimeout(figureLoading.timer);
        figureLoading.timer = 0;
        figureLoading.shown = false;
    }
    updateFigureLoadingView();
}

// pangalan ng parte para sa "Loading Hair…" (girlHair / keychainHair = Hair, outfit = Top ...)
function figurePartLabel(slot) {
    const name = String(slot || "").replace(/^(girl|keychain)/, "").toLowerCase();
    const labels = { hair: "Hair", top: "Top", outfit: "Top", bottom: "Bottom", pants: "Bottom", shoes: "Shoes", hat: "Hat" };
    return labels[name] || "";
}

function updateFigureLoadingView() {
    if (!figureLoadingBox) {
        return;
    }
    const hasFigure = Boolean(bodyModel);
    figureLoadingBox.hidden = !figureLoading.shown;
    figureLoadingBox.classList.toggle("is-small", hasFigure);
    if (figureLoadingText) {
        figureLoadingText.textContent =
            !hasFigure ? "Loading your figure…" : figureLoading.label ? "Loading " + figureLoading.label + "…" : "Loading…";
    }
}
/* ===== END LOADING NG 3D FIGURE ===== */

function setFigurePromptVisible(visible) {
    if (figureArea) {
        figureArea.dataset.promptVisible = visible ? "true" : "false";
    }
}

function clearSelectedClasses(selector) {
    document.querySelectorAll(selector).forEach(function (card) {
        card.classList.remove("selected");
    });
}

function markSelectedCard(card) {
    const figure = card.dataset.figure;
    const slot = card.dataset.slot;
    clearSelectedClasses(`[data-figure="${figure}"][data-slot="${slot}"]`);
    card.classList.add("selected");
    scheduleClothingUI();
}

/* =====================================================
   8. 3D MODEL: PAGLINIS, KULAY AT PAGLOAD
   Pagtanggal ng lumang model, pagkulay, at pag-load ng .glb file
   ===================================================== */

function disposeObject3D(object3D) {
    if (!object3D) {
        return;
    }

    object3D.traverse(function (node) {
        if (!node.isMesh) {
            return;
        }

        if (node.geometry) {
            node.geometry.dispose();
        }

        if (!node.material) {
            return;
        }

        const materials = Array.isArray(node.material) ? node.material : [node.material];
        materials.forEach(function (material) {
            if (material.map) {
                material.map.dispose();
            }

            if (material.normalMap) {
                material.normalMap.dispose();
            }

            if (material.roughnessMap) {
                material.roughnessMap.dispose();
            }

            if (material.metalnessMap) {
                material.metalnessMap.dispose();
            }

            material.dispose();
        });
    });
}

function removeFromScene(object3D) {
    if (!object3D) {
        return;
    }

    if (object3D.parent) {
        object3D.parent.remove(object3D);
    } else if (scene) {
        scene.remove(object3D);
    }

    disposeObject3D(object3D);
}

function clearCurrentScene() {
    removeFromScene(bodyModel);
    bodyModel = null;
    renderedBodyKey = null;
    Object.keys(renderedSlotSig).forEach(function (slot) {
        delete renderedSlotSig[slot];
    });
    if (figureShadow) {
        figureShadow.visible = false;
    }

    Object.keys(currentObjects).forEach(function (slot) {
        removeFromScene(currentObjects[slot]);
        currentObjects[slot] = null;
    });
}

function configureModel(model) {
    model.traverse(function (node) {
        if (!node.isMesh) {
            return;
        }

        // Preserve the GLB's original acrylic stand appearance.
        if (
            String(node.name || "")
                .toLowerCase()
                .includes("acrylic_stand")
        ) {
            return;
        }

        node.castShadow = true;
        node.receiveShadow = true;
        if (!node.material) {
            return;
        }

        const materials = Array.isArray(node.material) ? node.material : [node.material];
        materials.forEach(function (material) {
            material.transparent = false;
            material.needsUpdate = true;
        });
    });
}

function hasRenderableMeshes(object3D) {
    if (!object3D) {
        return false;
    }

    let meshCount = 0;
    object3D.traverse(function (node) {
        if (node.isMesh) {
            meshCount += 1;
        }
    });
    return meshCount > 0;
}

function shouldSkipMaterial(material, skipFragments) {
    if (!material || !skipFragments.length) {
        return false;
    }

    const materialName = String(material.name || "").toLowerCase();
    return skipFragments.some(function (fragment) {
        return materialName.includes(fragment);
    });
}

function clearMaterialTextureMaps(material) {
    if (!material) {
        return;
    }

    [
        "map",
        "alphaMap",
        "aoMap",
        "bumpMap",
        "displacementMap",
        "emissiveMap",
        "envMap",
        "lightMap",
        "metalnessMap",
        "normalMap",
        "roughnessMap",
        "specularMap",
        "clearcoatMap",
        "clearcoatNormalMap",
        "clearcoatRoughnessMap",
        "sheenColorMap",
        "sheenRoughnessMap",
        "iridescenceMap",
        "iridescenceThicknessMap",
        "transmissionMap",
        "thicknessMap"
    ].forEach(function (mapKey) {
        if (material[mapKey]) {
            material[mapKey] = null;
        }
    });
}

function applyColorToModel(model, color, options) {
    if (!model) {
        return false;
    }

    const settings = options || {};
    const lower = (list) => (Array.isArray(list) ? list.map((fragment) => String(fragment).toLowerCase()) : []);
    const skipNodeFragments = lower(settings.skipNodeFragments);

    // Katulad ng skipNodeFragments pero tinitingnan din ang mga parent group ng mesh
    // (hal. group na "Shorts" na ang laman ay mga mesh na "Object_3"). Hindi kasama ang
    // mismong root ng model para hindi madamay ang buong figure.
    const skipAncestorFragments = lower(settings.skipAncestorFragments);
    const isUnderSkippedGroup = function (node) {
        for (let current = node; current && current !== model; current = current.parent) {
            const name = String(current.name || "").toLowerCase();
            if (skipAncestorFragments.some((fragment) => name.includes(fragment))) {
                return true;
            }
        }
        return false;
    };

    // kunin ang mga mesh na kukulayan, tapos iisang paraan ng pagkulay (applyColorToMeshList)
    const meshes = [];
    model.traverse(function (node) {
        const nodeName = String(node.name || "").toLowerCase();
        if (skipNodeFragments.some((fragment) => nodeName.includes(fragment)) || isUnderSkippedGroup(node)) {
            return;
        }
        if (node.isMesh && node.material) {
            meshes.push(node);
        }
    });
    return applyColorToMeshList(meshes, color, settings);
}

function applyColorToMeshList(meshes, color, options) {
    if (!Array.isArray(meshes) || !meshes.length) {
        return false;
    }

    const settings = options || {};
    const targetColor = new THREE.Color(color);
    const skipFragments = Array.isArray(settings.skipMaterialFragments)
        ? settings.skipMaterialFragments.map(function (fragment) {
              return String(fragment).toLowerCase();
          })
        : ["design"];
    let changed = false;
    meshes.forEach(function (mesh) {
        if (!mesh || !mesh.material) {
            return;
        }

        const materials = Array.isArray(mesh.material) ? mesh.material : [mesh.material];
        const nextMaterials = materials.map(function (material) {
            if (shouldSkipMaterial(material, skipFragments)) {
                return material;
            }

            const newMaterial = material.clone();
            if (settings.clearTextureMaps) {
                clearMaterialTextureMaps(newMaterial);
            }

            if (newMaterial.color) {
                newMaterial.color.copy(targetColor);
            }
            newMaterial.needsUpdate = true;
            changed = true;
            return newMaterial;
        });
        mesh.material = Array.isArray(mesh.material) ? nextMaterials : nextMaterials[0];
    });
    return changed;
}

function isShoeLikeMesh(mesh) {
    if (!mesh) {
        return false;
    }

    const searchableText = [
        mesh.name,
        mesh.parent && mesh.parent.name,
        mesh.material && !Array.isArray(mesh.material) && mesh.material.name
    ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();
    return /shoe|sneaker|boot|foot|sole/.test(searchableText);
}

function getBottomModelParts(model) {
    const meshes = [];
    if (!model) {
        return { pants: [], shoes: [] };
    }

    model.traverse(function (node) {
        if (node.isMesh) {
            meshes.push(node);
        }
    });
    if (!meshes.length) {
        return { pants: [], shoes: [] };
    }

    const indexedMeshes = meshes
        .map(function (mesh, index) {
            const box = new THREE.Box3().setFromObject(mesh);
            return { mesh, index, centerY: box.getCenter(new THREE.Vector3()).y };
        })
        .sort(function (a, b) {
            return b.centerY - a.centerY;
        });
    const shoeTaggedMeshes = indexedMeshes.filter(function (item) {
        return isShoeLikeMesh(item.mesh);
    });
    const pantsTaggedMeshes = indexedMeshes.filter(function (item) {
        return !isShoeLikeMesh(item.mesh);
    });
    if (shoeTaggedMeshes.length && pantsTaggedMeshes.length) {
        return {
            pants: pantsTaggedMeshes.map(function (item) {
                return item.mesh;
            }),
            shoes: shoeTaggedMeshes.map(function (item) {
                return item.mesh;
            })
        };
    }

    let splitIndex = 1;
    let largestGap = 0;
    for (let index = 0; index < indexedMeshes.length - 1; index += 1) {
        const currentMesh = indexedMeshes[index];
        const nextMesh = indexedMeshes[index + 1];
        const gap = currentMesh.centerY - nextMesh.centerY;
        if (gap > largestGap) {
            largestGap = gap;
            splitIndex = index + 1;
        }
    }

    if (largestGap <= 0) {
        splitIndex = Math.max(1, Math.ceil(indexedMeshes.length / 2));
    } else {
        splitIndex = Math.min(Math.max(1, splitIndex), indexedMeshes.length - 1);
    }

    return {
        pants: indexedMeshes.slice(0, splitIndex).map(function (item) {
            return item.mesh;
        }),
        shoes: indexedMeshes.slice(splitIndex).map(function (item) {
            return item.mesh;
        })
    };
}

function applyBottomPartColors(model, pantsColor) {
    const parts = getBottomModelParts(model);
    if (pantsColor) {
        applyColorToMeshList(parts.pants, pantsColor, {
            clearTextureMaps: true,
            skipMaterialFragments: ["design", "logo", "print"]
        });
    }
}

/* =====================================================
   GLB PATHS - lugar ng 3D files (dressup-assets/GLB_files/)
     Funko Pop Standee Boy / Funko Pop Standee Girl
     Hirono Standee Boy / Hirono Standee Girl   (kasama na ang keychain na figure)
     Chibi Standee Boy / Chibi Standee Girl     (kasama na ang keychain na figure)
     Hirono Head Keychain                       (mga sombrero)
   Sa loob ng bawat figure: Hairstyle / Tops / Bottoms / Shoes
   Ang lumang path (naka-save sa lumang order o draft) ay itinuturo sa bagong lugar.
   Pareho nito: dress-up.js, Shared/dressup-viewer.js, helpers/dressup_helper.php
   ===================================================== */
// lumang file na walang folder -> folder niya (para sa lumang order lang)
const GLB_FOLDER_BY_FILE = {
    "Funko pop-default.glb": "Funko Pop Standee Boy",
    "funko boy.glb": "Funko Pop Standee Boy",
    "Hair_01.glb": "Funko Pop Standee Boy",
    "Hair02.glb": "Funko Pop Standee Boy",
    "Hoodie.glb": "Funko Pop Standee Boy",
    "T-Shirt.glb": "Funko Pop Standee Boy",
    "Pants with Shoes.glb": "Funko Pop Standee Boy",
    "Funko pop-girl.glb": "Funko Pop Standee Girl",
    "Funko GirlHair01.glb": "Funko Pop Standee Girl",
    "Funko GirlHair02.glb": "Funko Pop Standee Girl",
    "Funko GirlHair03.glb": "Funko Pop Standee Girl",
    "Funko Girl T-Shirt.glb": "Funko Pop Standee Girl",
    "Funko Girl T-Shirt1.glb": "Funko Pop Standee Girl",
    "Funko Girl T-Shirt2.glb": "Funko Pop Standee Girl",
    "Funko Girl Bottom.glb": "Funko Pop Standee Girl",
    "Funko Girl Bottom1.glb": "Funko Pop Standee Girl",
    "Hirono default.glb": "Hirono Standee Boy",
    "Hirono Hair01.glb": "Hirono Standee Boy",
    "Hirono Polo.glb": "Hirono Standee Boy",
    "Hirono Polo1.glb": "Hirono Standee Boy",
    "Hirono Jersey.glb": "Hirono Standee Boy",
    "Hirono Coat.glb": "Hirono Standee Boy",
    "Hirono Pants.glb": "Hirono Standee Boy",
    "Hirono Pants1.glb": "Hirono Standee Boy",
    "Hirono Shoes.glb": "Hirono Standee Boy",
    "Hirono Keychain.glb": "Hirono Keychain Boy",
    "Hirono Girl default.glb": "Hirono Standee Girl",
    "Hirono Girl Hair.glb": "Hirono Standee Girl",
    "Hirono Girl Hair1.glb": "Hirono Standee Girl",
    "Hirono Girl Hair2.glb": "Hirono Standee Girl",
    "Hirono Girl Hair3.glb": "Hirono Standee Girl",
    "Hirono Girl Coat.glb": "Hirono Standee Girl",
    "Hirono Girl Dress.glb": "Hirono Standee Girl",
    "Hirono Girl Pants.glb": "Hirono Standee Girl",
    "Hirono Girl Shoes.glb": "Hirono Standee Girl",
    "Hirono Girl Keychain.glb": "Hirono Keychain Girl",
    "Hirono KeyChain Cap.glb": "Hirono Head Keychain",
    "Hirono SpidermanHat.glb": "Hirono Head Keychain",
    "Hirono VenomxSpidermanHat.glb": "Hirono Head Keychain",
    "Chibi Boy Keychain.glb": "Chibi Keychain Boy",
    "Chibi T-Shirt Keychain.glb": "Chibi Keychain Boy",
    "Chibi Polo Keychain.glb": "Chibi Keychain Boy",
    "Chibi Shorts Keychain.glb": "Chibi Keychain Boy",
    "Chibi Pants Keychain.glb": "Chibi Keychain Boy",
    "Chibi Pants Keychain1.glb": "Chibi Keychain Boy",
    "Chibi Girl.glb": "Chibi Standee Girl",
    "Chibi GirlHair1.glb": "Chibi Standee Girl",
    "Chibi GirlHair2.glb": "Chibi Standee Girl",
    "Chibi Girl Coat.glb": "Chibi Standee Girl",
    "Chibi Girl Hoodie.glb": "Chibi Standee Girl",
    "Chibi Girl Sweater.glb": "Chibi Standee Girl",
    "Chibi Girl T-Shirt.glb": "Chibi Standee Girl",
    "Chibi Girl Pants.glb": "Chibi Standee Girl",
    "Chibi Girl Pants2.glb": "Chibi Standee Girl",
    "Chibi Girl Keychain.glb": "Chibi Keychain Girl"
};
// lumang folder -> bagong folder (pinagsama ang keychain sa standee)
const GLB_FOLDER_ALIASES = {
    "Chibi Boy/": "Chibi Standee Boy/",
    "Chibi Keychain Boy/": "Chibi Standee Boy/",
    "Chibi Keychain Girl/": "Chibi Standee Girl/",
    "Hirono Keychain Boy/": "Hirono Standee Boy/",
    "Hirono Keychain Girl/": "Hirono Standee Girl/",
    "Funko Pop Boy/": "Funko Pop Standee Boy/",
    "Funko Pop Girl/": "Funko Pop Standee Girl/"
};

// lumang pangalan ng subfolder -> bago
const GLB_SUBFOLDER_NAMES = { hair: "Hairstyle", top: "Tops", tops: "Tops", topss: "Tops", bottom: "Bottoms", pants: "Bottoms", shoes: "Shoes" };

// file na inilipat sa subfolder o pinalitan ng pangalan
const GLB_MOVED_FILES = {
    "Funko Pop Standee Girl/Funko pop-girl.glb": "Funko Pop Standee Girl/funko girl.glb",
    "Funko Pop Standee Girl/Funko_pop-girl.glb": "Funko Pop Standee Girl/funko girl.glb",
    "Funko Pop Standee Girl/Funko GirlHair01.glb": "Funko Pop Standee Girl/Hairstyle/Funko GirlHair01.glb",
    "Funko Pop Standee Girl/Funko GirlHair02.glb": "Funko Pop Standee Girl/Hairstyle/Funko GirlHair02.glb",
    "Funko Pop Standee Girl/Funko GirlHair03.glb": "Funko Pop Standee Girl/Hairstyle/Funko GirlHair03.glb",
    "Funko Pop Standee Girl/Funko Girl T-Shirt.glb": "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt.glb",
    "Funko Pop Standee Girl/Funko Girl T-Shirt1.glb": "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt1.glb",
    "Funko Pop Standee Girl/Funko Girl T-Shirt2.glb": "Funko Pop Standee Girl/Tops/Funko Girl T-Shirt2.glb",
    "Funko Pop Standee Girl/Funko Girl Bottom.glb": "Funko Pop Standee Girl/Bottoms/Funko Girl Bottom.glb",
    "Funko Pop Standee Girl/Funko Girl Bottom1.glb": "Funko Pop Standee Girl/Bottoms/Funko Girl Bottom1.glb",
    "Hirono Standee Boy/Hirono default.glb": "Hirono Standee Boy/Hirono Standee-boy.glb",
    "Hirono Standee Boy/Hirono Hair01.glb": "Hirono Standee Boy/Hairstyle/Hirono Hair01.glb",
    "Hirono Standee Boy/Hirono Coat.glb": "Hirono Standee Boy/Tops/Hirono Coat.glb",
    "Hirono Standee Boy/Hirono Jersey.glb": "Hirono Standee Boy/Tops/Hirono Jersey.glb",
    "Hirono Standee Boy/Hirono Polo.glb": "Hirono Standee Boy/Tops/Hirono Polo.glb",
    "Hirono Standee Boy/Hirono Polo1.glb": "Hirono Standee Boy/Tops/Hirono Polo1.glb",
    "Hirono Standee Boy/Hirono Pants.glb": "Hirono Standee Boy/Bottoms/Hirono Pants.glb",
    "Hirono Standee Boy/Hirono Pants1.glb": "Hirono Standee Boy/Bottoms/Hirono Pants1.glb",
    "Hirono Standee Boy/Hirono Shoes.glb": "Hirono Standee Boy/Shoes/Hirono Shoes.glb",
    "Hirono Standee Girl/Girl Keychain.glb": "Hirono Standee Girl/Hirono Girl Keychain.glb",
    "Hirono Standee Girl/Hirono Girl Hair.glb": "Hirono Standee Girl/Hairstyle/Hirono Girl Hair.glb",
    "Hirono Standee Girl/Hirono Girl Hair1.glb": "Hirono Standee Girl/Hairstyle/Hirono Girl Hair1.glb",
    "Hirono Standee Girl/Hirono Girl Hair2.glb": "Hirono Standee Girl/Hairstyle/Hirono Girl Hair2.glb",
    "Hirono Standee Girl/Hirono Girl Hair3.glb": "Hirono Standee Girl/Hairstyle/Hirono Girl Hair3.glb",
    "Hirono Standee Girl/Hirono Girl Coat.glb": "Hirono Standee Girl/Tops/Hirono Girl Coat.glb",
    "Hirono Standee Girl/Hirono Girl Dress.glb": "Hirono Standee Girl/Tops/Hirono Girl Dress.glb",
    "Hirono Standee Girl/Hirono Girl Pants.glb": "Hirono Standee Girl/Bottoms/Hirono Girl Pants.glb",
    "Hirono Standee Girl/Hirono Girl Shoes.glb": "Hirono Standee Girl/Shoes/Hirono Girl Shoes.glb",
    "Chibi Standee Girl/chibi_girl.glb": "Chibi Standee Girl/chibi girl.glb"
};

// lumang path -> tamang path ngayon
function glbPath(path) {
    const value = String(path || "")
        .replace(/(chibi(?:%20| )keychain)-(?:light|dark)\.glb$/i, "$1.glb")
        .replace(/girl-keychain-(?:light|dark)\.glb$/i, "Chibi Girl Keychain.glb");
    const marker = "GLB_files/";
    const at = value.indexOf(marker);
    if (at === -1) {
        return value;
    }
    const head = value.slice(0, at + marker.length);
    let rest = value.slice(at + marker.length);
    try {
        rest = decodeURIComponent(rest);
    } catch (error) {
        // iwan kung hindi ma-decode
    }

    // 1) lumang file na walang folder
    if (rest.indexOf("/") === -1 && GLB_FOLDER_BY_FILE[rest]) {
        rest = GLB_FOLDER_BY_FILE[rest] + "/" + rest;
    }
    // 2) lumang folder
    for (const oldFolder in GLB_FOLDER_ALIASES) {
        if (rest.startsWith(oldFolder)) {
            rest = GLB_FOLDER_ALIASES[oldFolder] + rest.slice(oldFolder.length);
            break;
        }
    }
    // 3) lumang subfolder (hair -> Hairstyle ...)
    const parts = rest.split("/");
    const subfolder = parts.length === 3 ? GLB_SUBFOLDER_NAMES[parts[1].toLowerCase()] : "";
    if (subfolder) {
        parts[1] = subfolder;
        rest = parts.join("/");
    }
    // 4) file na inilipat / pinalitan ng pangalan
    return head + (GLB_MOVED_FILES[rest] || rest);
}
/* ===== END GLB PATHS ===== */

function loadGLTF(path) {
    path = glbPath(path);
    if (!path) {
        const error = new Error("Model path is missing.");
        console.error("[Figurify 3D] Model path is missing.", error);
        return Promise.reject(error);
    }

    return new Promise(function (resolve, reject) {
        loader.load(path, function (gltf) {
            resolve(gltf.scene);
        }, undefined, function (error) {
            console.error(`[Figurify 3D] Failed to load: ${path}`, error);
            reject(error);
        });
    });
}

/* =====================================================
   9. 3D VIEWER: CAMERA, ANINO AT LAKI
   Pag-frame ng figure sa camera at pag-resize ng viewer
   ===================================================== */

function getLoadedObject(slot) {
    return currentObjects[slot] || null;
}

// Soft contact shadow sa ilalim ng figure (radial gradient plane, hindi
// nakadepende sa lights kaya pare-pareho sa lahat ng models).
function updateFigureShadow(box, size, center) {
    if (!scene) {
        return;
    }

    if (!figureShadow) {
        const shadowCanvas = document.createElement("canvas");
        shadowCanvas.width = 256;
        shadowCanvas.height = 256;
        const context = shadowCanvas.getContext("2d");
        const gradient = context.createRadialGradient(128, 128, 0, 128, 128, 128);
        gradient.addColorStop(0, "rgba(0, 0, 0, 0.45)");
        gradient.addColorStop(0.5, "rgba(0, 0, 0, 0.2)");
        gradient.addColorStop(1, "rgba(0, 0, 0, 0)");
        context.fillStyle = gradient;
        context.fillRect(0, 0, 256, 256);
        figureShadow = new THREE.Mesh(
            new THREE.PlaneGeometry(1, 1),
            new THREE.MeshBasicMaterial({
                map: new THREE.CanvasTexture(shadowCanvas),
                transparent: true,
                depthWrite: false
            })
        );
        figureShadow.rotation.x = -Math.PI / 2;
        figureShadow.renderOrder = -1;
        scene.add(figureShadow);
    }

    const footprint = Math.max(size.x, size.z) * 1.3;
    figureShadow.scale.set(footprint, footprint * 0.75, 1);
    figureShadow.position.set(center.x, box.min.y + 0.001, center.z);
    figureShadow.visible = true;
}

// Camera focus: sa Hair step, lumalapit ang camera sa ulo/buhok.
// Sa ibang step (Top, Bottom, Shoes, atbp.) buong figure. Walang zoom kahit saan.
const HEAD_FOCUS_SLOTS = ["hair", "girlHair", "keychainHair"];
let cameraFocus = "full";

function setCameraFocus(focus) {
    const next = focus === "head" ? "head" : "full";
    if (next === cameraFocus) {
        return;
    }

    cameraFocus = next;
    frameFigure();
}

// Keychain: kasama sa bounding box ang ring/chain sa itaas ng ulo, kaya mali ang "ulo"
// (nakatutok sa keychain). Kunin ang box ng figure LANG, hindi kasama ang ring.
function getFigureOnlyBox(root, fullBox) {
    const parts = [];
    root.traverse(function (node) {
        if (!node.isMesh || !node.geometry || !node.geometry.attributes.position) {
            return;
        }

        // singsing ng keychain lang ang tinatanggal (hindi ang "keychain figure" ng Hirono)
        let named = false;
        for (let n = node; n && n !== root.parent; n = n.parent) {
            if (/key/i.test(n.name || "") && !/figure/i.test(n.name || "")) {
                named = true;
                break;
            }
        }

        parts.push({
            node,
            named,
            count: node.geometry.attributes.position.count,
            box: new THREE.Box3().setFromObject(node)
        });
    });
    if (parts.length < 2) {
        return fullBox;
    }

    const maxCount = Math.max(...parts.map((part) => part.count));
    const kept = parts.filter((part) => {
        const smallTopPart = part.count < maxCount * 0.05 && part.box.max.y >= fullBox.max.y - 0.0001;
        return !part.named && !smallTopPart;
    });
    if (!kept.length || kept.length === parts.length) {
        return fullBox;
    }

    const box = new THREE.Box3();
    kept.forEach((part) => box.union(part.box));
    return box.isEmpty() ? fullBox : box;
}

function getHeadFocusBox(fullBox) {
    const height = fullBox.max.y - fullBox.min.y;

    // itaas na ~33% ng figure = ulo
    const headBox = new THREE.Box3(
        new THREE.Vector3(fullBox.min.x, fullBox.max.y - height * 0.4, fullBox.min.z),
        fullBox.max.clone()
    );

    // fixed ang frame ng ulo (hindi nagbabago kahit anong buhok ang pinili)

    return headBox;
}

let framedBodyModel = null; // huling figure na naka-frame (para malaman kung bagong figure)

function frameFigure() {
    if (!bodyModel || !camera || !controls) {
        return;
    }

    const box = new THREE.Box3();
    box.expandByObject(bodyModel);

    // body lang ang basehan ng frame - para hindi tumatalon/nagzo-zoom ang figure
    // tuwing may papalitang damit o buhok

    if (box.isEmpty()) {
        return;
    }

    const size = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());
    updateFigureShadow(box, size, center);

    // Buong figure: ang buhok ay nasa labas ng body box (kaya putol sa taas), kaya dinagdagan
    // ng espasyo sa TAAS ang frame at itinaas ang gitna ng camera, tapos nilayo pa.
    // Chibi keychain (boy/girl): mas malapit ang camera pero kita pa rin ang keychain sa taas.
    // Ang ring ay kasama na sa body box, kaya maliit na headroom lang ang kailangan.
    const isChibiKeychainFrame = state.currentCategory === "chibi" && state.chibi.productType === "keychain";
    const hairHeadroom = size.y * (isChibiKeychainFrame ? 0.08 : 0.2);
    // makitid na lalagyan (hal. Your Figure sa Order Summary): ang lapad ang basehan para hindi maputol sa gilid
    let focusSize = Math.max(size.x / Math.min(1, camera.aspect), size.y + hairHeadroom, size.z) * 0.95;
    center.y += hairHeadroom / 2;
    if (cameraFocus === "head") {
        const figureBox = getFigureOnlyBox(bodyModel, box);
        const headOnlyModel = state.currentCategory === "hirono" && getHironoMode() === "headKeychain";
        const headBox = headOnlyModel ? figureBox : getHeadFocusBox(figureBox);

        // dagdag na espasyo sa TAAS para hindi putol ang mataas na buhok
        // (ang buhok ay nasa labas ng body box kaya hindi ito nabibilang sa frame)
        const headHeight = headBox.max.y - headBox.min.y;
        headBox.max.y += headHeight * (headOnlyModel ? 0.18 : 0.32);
        const headSize = headBox.getSize(new THREE.Vector3());
        headBox.getCenter(center);
        focusSize = Math.max(headSize.x, headSize.y, headSize.z) * 1.3;
    }

    const distance = focusSize / (2 * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)));

    // panatilihin ang pag-ikot habang pareho ang figure; bagong figure (ibang style/gender/type) = nakaharap
    let azimuth = 0;
    if (bodyModel !== framedBodyModel) {
        framedBodyModel = bodyModel;
        figureRotatePending = 0;
    } else if (controls.target) {
        const prev = camera.position.clone().sub(controls.target);
        if (Math.abs(prev.x) + Math.abs(prev.z) > 0.0001) {
            azimuth = Math.atan2(prev.x, prev.z);
        }
    }

    // Keychain (Chibi at Hirono): mas malayo at mas mataas para kita ang buong figure,
    // pati paa, at hindi natatakpan ng 360 control
    const isKeychainFrame =
        isChibiKeychainFrame || (state.currentCategory === "hirono" && getHironoMode() === "keychain");
    const distanceScale = isKeychainFrame && cameraFocus !== "head" ? 1.5 : 1.35;
    // itaas ang figure sa canvas para hindi matakpan ng 360 control ang bilog (anino) sa paa
    if (cameraFocus !== "head") {
        center.y -= focusSize * distanceScale * (isKeychainFrame ? 0.11 : 0.07);
    }
    camera.position.set(
        center.x + Math.sin(azimuth) * distance * distanceScale,
        center.y,
        center.z + Math.cos(azimuth) * distance * distanceScale
    );
    camera.lookAt(center);
    controls.target.copy(center);
    // naka-lock ang layo ng camera (walang zoom) - laging sakto ang figure sa view
    const lockedDistance = camera.position.distanceTo(center);
    controls.minDistance = lockedDistance;
    controls.maxDistance = lockedDistance;
    controls.update();
}

function resize3D() {
    if (!renderer || !camera || !figureArea) {
        return;
    }

    const width = figureArea.clientWidth;
    const height = figureArea.clientHeight;
    if (width <= 0 || height <= 0) {
        return;
    }

    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    frameFigure(); // bagong laki = i-sakto ulit ang buong figure
}

/* =====================================================
   10. PRESYO AT ORDER SUMMARY
   Total, listahan ng napili, at ang summary sa gilid
   ===================================================== */

function getCurrentOrderTotal(activeState) {
    const rushFee = getCommissionRushFee();
    if (!activeState || !activeState.model) {
        return rushFee;
    }

    return getActiveStateTotal(activeState) + getProductSizePrice() + getProductDetailsAddon() + rushFee;
}

function updatePriceDisplay() {
    const activeState = getActiveState();
    if (dressUpTotalPrice) {
        // habang may ibang figure sa preview, gamitin ang total ng kasalukuyang figure
        const total = previewLock ? currentTotalCache : getCurrentOrderTotal(activeState);
        if (!previewLock) {
            currentTotalCache = total;
        }

        dressUpTotalPrice.textContent = formatMoney(total);
        if (summaryTotalPrice) {
            summaryTotalPrice.textContent = formatMoney(total * getCurrentFigureQty() + getDressUpCartTotal());
        }
    }

    refreshOptionPriceLabels();
    renderPreviewSummary();
    if (isSummaryOpen && typeof renderDressUpCartList === "function") {
        renderDressUpCartList();
    }

    applyCartSelectionToDetails();
}

function buildFigureDetailRows(activeState, categoryValue) {
    const rows = [];
    // "group" = anong column sa Order Summary: "figure" (default) o "box".
    // Iisang listahan lang sa commission.php sidebar, kaya hindi ito ginagamit doon.
    const addRow = (header, value, price = null, group = "figure", colorValue = "", image = "") => {
        if (!header) {
            return;
        }

        rows.push({ header, value: value || "Not selected", price, group, colorValue, image });
    };

    // Bawat row (Figure Style, Product Type, Gender, Skin Tone, Size, Hair, Top,
    // Bottom, Shoes) lumalabas lang kapag may aktwal na napili na, hindi "Not selected" placeholder.
    if (categoryValue) {
        addRow("Figure Style", categoryValue);
    }

    // Personalization fields (Box Name/Number/Color, atbp.) ipinapakita sa Box/Add-ons
    // column kahit walang presyo (walang Figure Name sa Dress Up).
    if (state.currentCategory === "funko") {
        if (productDetails.boxName.trim()) {
            addRow("Box Name", productDetails.boxName.trim(), null, "box");
        }
        if (productDetails.boxNumber.trim()) {
            addRow("Box Number", productDetails.boxNumber.trim(), null, "box");
        }
        if (productDetails.boxColor.trim()) {
            addRow("Box Color", productDetails.boxColor.trim(), null, "box");
        }
    }

    // Sariling label bawat slot (kasama Gender/Skin). *Color slots (Hair Color,
    // Top Color, atbp.) tinatanggal - pangalan ng item na lang ang nagsasabi ano napili.
    if (state.currentCategory && activeState) {
        const slotLabels = {
            model: "Gender",
            skin: "Skin Tone",
            hair: "Hair",
            girlHair: "Hair",
            hairColor: "Hair Color",
            girlHairColor: "Hair Color",
            top: "Top",
            girlTop: "Top",
            outfit: "Top",
            topColor: "Top Color",
            girlTopColor: "Top Color",
            outfitColor: "Top Color",
            bottom: "Bottom",
            girlBottom: "Bottom",
            pants: "Bottom",
            bottomColor: "Pants Color",
            girlBottomColor: "Pants Color",
            pantsColor: "Pants Color",
            shoes: "Shoes",
            shoesColor: "Shoes Color",
            keychainHair: "Hirono Keychain Hair",
            keychainHairColor: "Hirono Keychain Hair Color",
            keychainHat: "Hat"
        };

        // Product Type row: Hirono/Chibi lang, kaagad pafter Figure Style, bago Gender/Skin.
        // Funko walang Product Type step kaya wala itong row.
        const productTypeLabels = {
            standee: "Full Body Standee",
            keychain: "Full Body Keychain",
            headKeychain: "Head Keychain"
        };
        if (state.currentCategory === "hirono") {
            const productTypeLabel = productTypeLabels[state.hirono.mode];
            if (productTypeLabel) {
                // walang Size (Head Keychain): dito nakalagay ang presyo ng product type
                addRow("Product Type", productTypeLabel, PRODUCT_FIXED_PRICES[getProductDetailsKey()] || null);
            }
        } else if (state.currentCategory === "chibi") {
            const productTypeLabel = productTypeLabels[state.chibi.productType];
            if (productTypeLabel) {
                addRow("Product Type", productTypeLabel);
            }
        }

        // I-splice ang Size row kaagad after Skin Tone (bago Hair/Top/Bottom/Shoes) para
        // sumunod sa aktwal na order ng steps, sa halip na palaging pinakababa.
        // Walang Size row kung walang Size step ang variant (hal. Hirono Head Keychain).
        const productKey = getProductDetailsKey();
        const knownConfig = PRODUCT_DETAIL_CONFIGS[productKey] || null;
        const sizeStepApplies = !knownConfig || knownConfig.sizes.length > 0;
        // Ayos: Figure Style, Product Type, Size, Gender, Skin Tone, tapos Hair / Top / Bottom / Shoes
        if (sizeStepApplies && productDetails.size) {
            addRow("Size", productDetails.size, getProductSizePrice() || null);
        }

        // Outfit Color na pinili (kulay ng bawat part) - galing sa parehong data na ipinapasa sa order
        const outfitColors = {};
        buildDressUpDesignData(activeState).slots.forEach((designSlot) => {
            outfitColors[designSlot.slot] = designSlot;
        });

        getActiveSlotOrder().forEach((slot) => {
            const item = activeState[slot];
            if (!item || slot.toLowerCase().endsWith("color")) {
                return;
            }

            const itemPrice = getItemPrice(item);

            if (slot === "model") {
                addRow("Gender", getDressUpGenderLabel(activeState));
            } else if (slot === "skin") {
                addRow("Skin Tone", item.name, itemPrice > 0 ? itemPrice : null, "figure", item.color || "");
            } else {
                // Hair / Top / Bottom / Shoes: larawan + Outfit Color (kung may pinili)
                const picked = outfitColors[slot];
                addRow(
                    slotLabels[slot] || getDesignStepLabel(slot),
                    picked && picked.colorName ? picked.colorName : "",
                    itemPrice > 0 ? itemPrice : null,
                    "figure",
                    picked && picked.colorName ? picked.colorValue : "",
                    getItemImage(state.currentCategory, slot, item.model)
                );
            }
        });
    }

    if (state.currentCategory === "hirono") {
        const blindBox = getSelectedBlindBox();
        if (blindBox) {
            addRow("Blind Box Type", blindBox.label, blindBox.price, "box");
        }

        getSelectedHironoAddons().forEach((addon) => {
            addRow("Add-on", addon.label, addon.price, "box");
        });

        // Box Design + Hirono personalization fields, makikita sa Box/Add-ons column, walang presyo
        if (productDetails.boxDesignSelected) {
            const boxDesignLabels = { checkered: "Checkered", peek: "Hirono Peek" };
            addRow("Box Design", boxDesignLabels[productDetails.boxDesign] || productDetails.boxDesign, null, "box");
        }

        if (productDetails.boxColor.trim()) {
            addRow("Box Color", productDetails.boxColor.trim(), null, "box");
        }

        if (productDetails.boxNickname.trim()) {
            addRow("Nickname", productDetails.boxNickname.trim(), null, "box");
        }

        if (productDetails.boxLetter.trim()) {
            addRow("Letter", productDetails.boxLetter.trim(), null, "box");
        }

        if (productDetails.boxDateYmd) {
            const formattedDate = formatMonthDay(productDetails.boxDateYmd);
            if (formattedDate) {
                addRow("Month / Day", formattedDate, null, "box");
            }
        }
    }

    const config = getProductDetailsConfig();
    const selectedBox = config?.boxes?.find((box) => box.id === productDetails.box);
    if (selectedBox) {
        addRow("Box Type", selectedBox.label, selectedBox.price, "box", "", "../Image/" + selectedBox.image);
    }

    return rows;
}

// Box & Add-ons ng Order Summary: lalabas lang ang container kapag may box / add-on na pinili
function renderBoxRows(boxRows) {
    const boxItems = document.getElementById("summaryBoxItems");
    boxItems.closest(".summary-card").hidden = boxRows.length === 0;
    renderRowsIntoContainer(boxItems, boxRows);
}

// Order Summary rows (listahan):
// Hair / Top / Bottom / Shoes / Custom Box = [larawan] Label ......... presyo (walang pangalan ng item)
// iba pa (Size, Gender, Box Name...)      = Label | napili (may tuldok ng kulay) | presyo
function renderRowsIntoContainer(container, rows, emptyMessage = "No customization selected yet.") {
    if (!container) {
        return;
    }

    // Laging linisin muna ang container bago mag-render, kung hindi dumodoble ang laman
    container.innerHTML = "";
    if (!rows.length) {
        container.innerHTML = `<div class="receipt-empty">${emptyMessage}</div>`;
        return;
    }

    const partHeaders = ["Hair", "Top", "Bottom", "Shoes", "Hat", "Box Type"];
    rows.forEach(({ header, value, price, colorValue, image }) => {
        const hasPrice = price !== null && price !== undefined && Number(price) > 0;
        const isPart = Boolean(image) || partHeaders.includes(header);
        const item = document.createElement("div");
        item.className = isPart ? "figure-summary-item is-part" : "figure-summary-item";

        // larawan ng napili (kapareho ng card sa Dress Up)
        if (image) {
            const thumb = document.createElement("img");
            thumb.className = "figure-summary-thumb";
            thumb.src = image;
            thumb.alt = "";
            thumb.loading = "lazy";
            item.appendChild(thumb);
        }

        const info = document.createElement("div");
        info.className = "figure-summary-info";
        const headerElement = document.createElement("div");
        headerElement.className = "figure-summary-name";
        headerElement.textContent = header;
        info.appendChild(headerElement);
        // Hair / Top / Bottom / Shoes: walang pangalan ng item, Outfit Color lang kung may pinili
        if (value && (!isPart || colorValue)) {
            const valueElement = document.createElement("div");
            valueElement.className = "figure-summary-description";
            if (colorValue) {
                valueElement.appendChild(makeColorDot("summary-color-dot", colorValue));
            }
            // sariling kulay (color picker) = tuldok + "Outfit Color"; nakahandang kulay = may pangalan (hal. Red)
            const colorText = /^(outfit|custom) color$/i.test(value) ? "Outfit Color" : "Outfit Color: " + value;
            valueElement.appendChild(document.createTextNode(isPart ? colorText : value));
            info.appendChild(valueElement);
        }

        item.appendChild(info);
        const priceElement = document.createElement("div");
        priceElement.className = "figure-summary-price";
        priceElement.textContent = hasPrice ? formatMoney(price) : "";
        item.appendChild(priceElement);
        container.appendChild(item);
    });
}

// larawan ng napiling item (hal. hair) - galing sa card mismo sa Dress Up
function getItemImage(category, slot, model) {
    if (!category || !slot || !model) {
        return "";
    }
    const card = document.querySelector(
        `[data-figure="${category}"][data-slot="${slot}"][data-model="${escapeSelectorValue(model)}"]`
    );
    const img = card ? card.querySelector("img") : null;
    return card ? card.dataset.image || (img ? img.getAttribute("src") : "") || "" : "";
}

function renderPreviewSummary() {
    const data = getCustomizationState();
    const activeState = getActiveState();
    const orderType = document.getElementById("dressUpSummaryOrderType");
    const bookingDate = document.getElementById("dressUpSummaryBookingDate");
    const bookingStatus = document.getElementById("dressUpSummaryBookingStatus");
    const receiptItems = document.getElementById("summaryReceiptItems");
    const boxItems = document.getElementById("summaryBoxItems");

    // walang currentCategory = walang Figure Style, huwag kunin ang naka-save sa sessionStorage
    const categoryValue = state.currentCategory ? getFigureLabel(state.currentCategory) : "";

    // Laging live ang kinukuha (getCommissionOrderTypeLabel), hindi ang naka-save na snapshot
    if (orderType) {
        orderType.textContent = getCommissionOrderTypeLabel();
    }

    // "(Rush fee: ₱500)" sa ilalim ng Rush Order (kasama na sa Total Price)
    const rushFeeNote = document.getElementById("dressUpSummaryRushFee");
    if (rushFeeNote) {
        const rushFee = getCommissionRushFee();
        rushFeeNote.textContent = rushFee > 0 ? `(Rush fee: ${formatMoney(rushFee)})` : "";
    }

    // araw + petsa sa iisang bold na text (hal. "Friday, November 20, 2026"), kagaya ng Commission;
    // ang maliit na text sa ilalim ay para lang sa "Choose a date from the calendar"
    const weekday = getCommissionBookingDateValue() ? getCommissionBookingStatusLabel() : "";

    if (bookingDate) {
        bookingDate.textContent = (weekday ? weekday + ", " : "") + (data?.bookingDateLabel || getCommissionBookingDateLabel());
    }

    if (bookingStatus) {
        bookingStatus.textContent = weekday ? "" : getCommissionBookingStatusLabel();
    }

    if (!receiptItems || !boxItems) {
        return;
    }

    // Figure Details column at Custom Box & Add-ons column
    const rows = buildFigureDetailRows(activeState, categoryValue);
    renderRowsIntoContainer(receiptItems, rows.filter((row) => row.group !== "box"));
    renderBoxRows(rows.filter((row) => row.group === "box"));
}

function formatMonthDay(value) {
    if (!value) {
        return "";
    }

    if (/^\d{1,2}\/\d{1,2}$/.test(value)) {
        const [month, day] = value.split("/").map(Number);
        return `${String(month).padStart(2, "0")}/${String(day).padStart(2, "0")}`;
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const parsed = new Date(value);
        if (!Number.isNaN(parsed.getTime())) {
            return `${String(parsed.getMonth() + 1).padStart(2, "0")}/${String(parsed.getDate()).padStart(2, "0")}`;
        }
        return value;
    }

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return `${String(date.getMonth() + 1).padStart(2, "0")}/${String(date.getDate()).padStart(2, "0")}`;
}

function collectClothingColors(activeState) {
    const colors = {};
    if (!activeState) {
        return colors;
    }

    Object.entries(activeState).forEach(function ([key, item]) {
        if (!key.endsWith("Color") || !item) {
            return;
        }

        colors[key] = { name: item.name || "", color: item.color || "" };
    });
    return colors;
}

/* =====================================================
   11. WIZARD AT STEPS
   Figure Details / Parts / Box / Review, at lock ng susunod na step
   ===================================================== */

// ilagay ang class na "is-grid-step" sa kanang panel kapag Hair/Top/Bottom/Shoes
// (may sariling nag-i-scroll na grid) - para sa shadow sa taas at baba
// Figure Details: lahat ng hakbang ay nakalabas, pero naka-LOCK hanggang hindi pa napipili
// ang nauna (Style -> Type -> Size -> Gender -> Skin Tone).
function applyFigureDetailsLocks() {
    document.querySelectorAll("#dressUpModule .step-locked").forEach((el) => {
        el.classList.remove("step-locked");
        el.removeAttribute("inert");
        el.removeAttribute("aria-disabled");
    });
    if (!onFirstStep || navigationSlot === "productDetails") {
        return;
    }

    const category = state.currentCategory;
    if (!category) {
        return;
    }

    const active = state[category];
    const typeSlot = category === "chibi" ? "productType" : category === "hirono" ? "mode" : null;
    const steps = [];
    if (typeSlot) {
        steps.push({ id: sectionIds[category].type, done: Boolean(active[typeSlot]) });
    }

    steps.push({ id: "figureSizeSection", done: Boolean(productDetails.sizeSelected) });
    steps.push({ id: `${category}ModelSection`, done: Boolean(active.model) });
    steps.push({ id: sectionIds[category].skin, done: Boolean(active.skin) });
    let previousDone = true; // Figure Style ay napili na

    steps.forEach((step) => {
        const section = document.getElementById(step.id);
        if (section && isElementShown(section)) {
            if (!previousDone) {
                section.classList.add("step-locked");
                section.setAttribute("inert", "");
                section.setAttribute("aria-disabled", "true");
            }
            previousDone = previousDone && step.done;
        }
    });
}

function syncGridStepClass() {
    const right = document.querySelector("#dressUpModule .dressup-right");
    if (!right) {
        return;
    }

    const grid = document.querySelector(
        "#dressUpModule .category-panel:not([hidden]) > .option-section:not([hidden]):not(#chibiTypeSection) > .option-grid"
    );
    right.classList.toggle("is-grid-step", Boolean(grid) && navigationSlot !== "productDetails");
    right.classList.toggle("is-details-step", onFirstStep && navigationSlot !== "productDetails");
}

// Parts step: Hair / Top / Bottom / Shoes tabs sa taas (kita agad kung ano na ang napili).
// Pindutin ang tab para lumipat; ang mga tab na hindi pa naaabot ay naka-lock.
const PART_TAB_LABELS = {
    hair: "Hair",
    girlHair: "Hair",
    keychainHair: "Hair",
    top: "Top",
    girlTop: "Top",
    outfit: "Top",
    bottom: "Bottom",
    girlBottom: "Bottom",
    pants: "Bottom",
    shoes: "Shoes",
    keychainHat: "Hat"
};

function renderPartsTabs() {
    const box = document.getElementById("partsTabs");
    if (!box) {
        return;
    }

    const category = state.currentCategory;
    const active = category ? state[category] : null;
    const showTabs =
        Boolean(active) && !onFirstStep && navigationSlot !== "productDetails" && currentDesignSlot;
    const slots = showTabs ? activeSequentialSlots.filter((slot) => PART_TAB_LABELS[slot]) : [];
    if (!slots.length) {
        box.hidden = true;
        box.replaceChildren();
        return;
    }

    const isTabActive = (slot) =>
        [slot, getSlotColorSlot(slot), ACCESSORY_COLOR_SLOT[slot]].filter(Boolean).includes(currentDesignSlot);
    const currentIndex = Math.max(0, slots.findIndex(isTabActive));

    box.replaceChildren();
    slots.forEach((slot, index) => {
        const item = active[slot];
        const isActive = index === currentIndex;
        // picked = pinili mismo ng customer (hindi yung default na suot na)
        const picked = Boolean(item) && (pickedSlots.has(slot) || dressUpViewing);
        const reachable = index <= currentIndex || picked;
        // check: may napili na sa naabot na tab, kahit Default pa (hal. Default Hair)
        const done = Boolean(item) && reachable;

        const tab = document.createElement("button");
        tab.type = "button";
        tab.className = "parts-tab" + (isActive ? " is-active" : "") + (done ? " is-done" : "");
        tab.disabled = !reachable;
        tab.dataset.slot = slot;

        // larawan: ang napili; kung wala pa, ang Default card (hal. Default Hair) - hindi blangko
        const thumb = document.createElement("span");
        thumb.className = "parts-tab-thumb";
        const cardsForSlot = Array.from(
            document.querySelectorAll(`#dressUpModule .option-card[data-figure="${category}"][data-slot="${slot}"]`)
        );
        const pickedCard = picked
            ? cardsForSlot.find((card) => card.dataset.model === item.model && card.dataset.name === item.name) ||
              cardsForSlot.find((card) => card.dataset.model === item.model) ||
              cardsForSlot.find((card) => card.classList.contains("selected"))
            : null;
        const shownCard = pickedCard || cardsForSlot.find((card) => card.dataset.default === "true" && !card.hidden);
        const shownImg = shownCard ? shownCard.querySelector("img") : null;
        if (shownImg && shownImg.getAttribute("src")) {
            const img = document.createElement("img");
            img.src = shownImg.getAttribute("src");
            img.alt = "";
            thumb.appendChild(img);
        }

        const text = document.createElement("span");
        text.className = "parts-tab-text";
        const label = document.createElement("small");
        label.textContent = PART_TAB_LABELS[slot];
        text.append(label);

        // pangalan ng default (hal. "Default Hair"): kita habang wala pang napili at kapag Default ang pinili
        if (shownCard && (!pickedCard || pickedCard.dataset.default === "true")) {
            const defaultName = document.createElement("span");
            defaultName.className = "parts-tab-default";
            defaultName.textContent = shownCard.dataset.name || "Default";
            text.append(defaultName);
        }
        // wala nang pangalan ng item; presyo lang ang idadagdag kapag may bayad ang napili
        const partPrice = picked ? getItemPrice(item) : 0;
        if (partPrice > 0) {
            const priceTag = document.createElement("strong");
            priceTag.textContent = formatMoney(partPrice);
            text.append(priceTag);
        }

        tab.append(thumb, text);
        if (done) {
            const check = document.createElement("i");
            check.className = "parts-tab-check";
            check.textContent = "\u2713";
            tab.appendChild(check);
        }

        tab.addEventListener("click", () => {
            navigationSlot = slot;
            updateSectionVisibility();
            resetDressUpScroll();
        });
        box.appendChild(tab);
    });
    box.hidden = false;
}

// Wala pang napiling Figure Style: Back (balik sa Commission) lang ang kita sa baba
function syncStartNavigation() {
    if (state.currentCategory) {
        return;
    }
    const navigation = document.getElementById("designStepNavigation");
    const previousButton = document.getElementById("previousDesignStep");
    const nextButton = document.getElementById("nextDesignStep");
    if (!navigation || !previousButton) {
        return;
    }
    navigation.hidden = false;
    previousButton.hidden = false;
    if (nextButton) {
        nextButton.hidden = false;
        nextButton.disabled = false;
        nextButton.textContent = "Next";
        // wala pang Figure Style: pop up + highlight
        nextButton.onclick = () => showDressUpNotice("Please choose: Figure Style", "error", "figureStyleSection");
    }
    previousButton.onclick = () => {
        window.location.href = "commission.php";
    };
}

function updateSectionVisibility() {
    updateSectionVisibilityCore();
    syncGridStepClass();
    applyFigureDetailsLocks();
    syncSkinImages(state.currentCategory);
    renderPartsTabs();
    syncStartNavigation();
    syncWizardPills();
    scheduleClothingUI();
}

// Header ng wizard (pills). 1 = Figure Details (Style, Type, Size, Gender, Skin Tone),
// 2 = Parts (Hair, Top, Bottom, Shoes), 3 = Box/Details, 4 = Review (Order Summary).
function getCurrentWizardStep() {
    const detailsVisible = Boolean(productDetailsPanel && !productDetailsPanel.hidden);
    return !state.currentCategory || onFirstStep ? 1 : detailsVisible ? 3 : 2;
}

// Lagda ng bawat step: kapag nagbago ang lagda ng isang step, ibig sabihin may binago
// ang customer doon, kaya uulit ang mga susunod na step.
function getWizardSignatures() {
    const category = state.currentCategory;
    const active = category ? state[category] : null;
    if (!active) {
        return { 1: "", 2: "", 3: "" };
    }

    const pick = (item) => (item ? [item.name || "", item.color || "", item.model || ""].join("|") : "");
    const figureSig = JSON.stringify([
        category,
        active.productType || "",
        active.mode || "",
        productDetails.sizeSelected ? productDetails.size : "",
        pick(active.model),
        pick(active.skin)
    ]);
    const partsSig = JSON.stringify(
        getActiveSlotOrder()
            .filter((slot) => slot !== "model" && slot !== "skin")
            .map((slot) => [slot, pick(active[slot])])
    );
    const boxSig = JSON.stringify([
        productDetails.box,
        productDetails.boxSelected,
        productDetails.boxName,
        productDetails.boxNumber,
        productDetails.boxColor,
        productDetails.blindBox,
        productDetails.blindBoxSelected,
        productDetails.hironoAddons,
        productDetails.boxDesign,
        productDetails.boxDesignSelected,
        productDetails.boxNickname,
        productDetails.boxLetter,
        productDetails.boxDateYmd,
        productDetails.figureName
    ]);
    return { 1: figureSig, 2: partsSig, 3: boxSig };
}

function updateWizardProgress() {
    const sigs = getWizardSignatures();
    if (wizardSigs && wizardArmed && !dressUpViewing && !editingCurrentFigure) {
        for (let step = 1; step <= 3; step++) {
            if (sigs[step] !== wizardSigs[step]) {
                // may binago sa step na ito - ulit ang mga susunod
                wizardMaxStep = Math.min(wizardMaxStep, step);
                break;
            }
        }
    }

    wizardSigs = sigs;
    wizardMaxStep = Math.max(wizardMaxStep, getCurrentWizardStep());
}

function wizardHasBoxStep() {
    const config = getProductDetailsConfig();
    return Boolean(config && (config.hirono || config.boxes?.length));
}

function canJumpToWizardStep(step) {
    if (!state.currentCategory) {
        return false;
    }

    if (step === 3 && !wizardHasBoxStep()) {
        return false;
    }

    return step <= wizardMaxStep;
}

function jumpToWizardStep(step) {
    if (!canJumpToWizardStep(step) || step === getCurrentWizardStep()) {
        return;
    }

    if (step === 4) {
        const activeState = getActiveState();
        if (!activeState || !activeState.model) {
            return;
        }

        // gaya ng Next sa huling step
        if (dressUpViewing) {
            saveEditedCartFigure();
            return;
        }

        saveCustomizationSnapshot(true, false);
        openOrderSummary();
        return;
    }

    if (step === 1) {
        navigationSlot = "model";
    } else if (step === 2) {
        navigationSlot = activeSequentialSlots.find((slot) => !FIRST_GROUP_SLOTS.includes(slot)) || null;
    } else {
        navigationSlot = "productDetails";
    }

    updateSectionVisibility();
    resetDressUpScroll();
}

function initWizardPills() {
    const pillList = document.getElementById("wizardPills");
    if (!pillList || pillList.dataset.ready === "1") {
        return;
    }

    pillList.dataset.ready = "1";
    pillList.addEventListener("click", (event) => {
        const pill = event.target.closest && event.target.closest("li[data-wizard]");
        if (pill) {
            jumpToWizardStep(Number(pill.dataset.wizard));
        }
    });
    pillList.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") {
            return;
        }

        const pill = event.target.closest && event.target.closest("li[data-wizard]");
        if (pill) {
            event.preventDefault();
            jumpToWizardStep(Number(pill.dataset.wizard));
        }
    });

    // Bago pa mag-click ang customer sa mga pinipili, hindi pa binibilang ang pagbabago
    // (para hindi mabura ang progress dahil lang sa pag-load/restore ng design).
    const arm = () => {
        wizardArmed = true;
    };
    const scrollArea = document.getElementById("dressUpRightScroll");
    if (scrollArea) {
        scrollArea.addEventListener("click", arm, true);
        scrollArea.addEventListener("input", arm, true);
    }
}

function syncWizardPills() {
    const pillList = document.getElementById("wizardPills");
    if (!pillList) {
        return;
    }

    updateWizardProgress();
    const current = getCurrentWizardStep();
    pillList.querySelectorAll("li[data-wizard]").forEach((pill) => {
        const step = Number(pill.dataset.wizard);
        const clickable = step !== current && canJumpToWizardStep(step);
        pill.classList.toggle("is-active", step === current);
        pill.classList.toggle("is-done", step !== current && step <= wizardMaxStep);
        pill.classList.toggle("is-clickable", clickable);
        if (clickable) {
            pill.setAttribute("role", "button");
            pill.setAttribute("tabindex", "0");
            pill.removeAttribute("aria-disabled");
        } else {
            pill.removeAttribute("role");
            pill.setAttribute("tabindex", "-1");
            pill.setAttribute("aria-disabled", "true");
        }
    });
}

function updateSectionVisibilityCore() {
    const activeCategory = state.currentCategory;
    Object.entries(categoryPanels).forEach(function ([category, panel]) {
        if (panel) {
            panel.hidden = category !== activeCategory;
        }
    });
    if (!activeCategory) {
        if (continueBtn) {
            continueBtn.hidden = true;
        }

        if (productDetailsPanel) {
            productDetailsPanel.hidden = true;
        }

        // Size ay shared section, wala sa reset loop ng `sectionIds` sa ibaba (at
        // may `return` dito bago maabot yun) - i-reset dito para hindi manatiling
        // visible pagbalik sa Figure Style dropdown habang nasa Size step.
        setVisibleById("figureSizeSection", false);

        // No style chosen yet (or user came back to change it) - show the
        // Figure Style picker and keep every category panel hidden so it
        // occupies the same spot instead of stacking on top of one.
        if (figureStyleSection) {
            figureStyleSection.hidden = false;
        }

        const navigation = document.getElementById("designStepNavigation");
        if (navigation) {
            navigation.hidden = true;
        }

        onFirstStep = true;
        currentDesignSlot = null;
        return;
    }

    if (figureDetailsContainer) {
        figureDetailsContainer.hidden = false;
    }

    // A style is active - swap the Figure Style picker out for that
    // category's panel in the same spot, instead of leaving it visible
    // above the panel.
    if (figureStyleSection) {
        figureStyleSection.hidden = true;
    }

    // The model picker is the entry point for every category. Keep it visible
    // even though the dependent option sections are hidden until a model is
    // selected.
    ["funkoModelSection", "hironoModelSection", "chibiModelSection"].forEach(function (id) {
        setVisibleById(id, id === `${activeCategory}ModelSection`);
    });
    const activeState = state[activeCategory];
    if (continueBtn) {
        continueBtn.hidden = true;
    }

    Object.values(sectionIds).forEach(function (categorySections) {
        Object.values(categorySections).forEach(function (id) {
            setVisibleById(id, false);
        });
    });

    // The reset loop above only walks the per-style `sectionIds` map, which
    // doesn't include the shared Size section (it lives outside all three
    // style panels). Without this, Size stays visible after the user moves
    // past it to Hair/Top/etc. instead of being hidden like every other step.
    setVisibleById("figureSizeSection", false);
    const showSections = function (ids) {
        ids.forEach(function (id) {
            setVisibleById(id, true);
        });
    };
    let sequentialSlots = null;
    if (activeCategory === "funko") {
        sequentialSlots =
            activeState.model && isGirlModel(activeState.model)
                ? ["size", "model", "skin", "girlHair", "girlTop", "girlBottom", "shoes"]
                : ["size", "model", "skin", "hair", "top", "bottom", "shoes"];
    }

    if (activeCategory === "chibi") {
        // Keychain Boy: may Hair step na (same set ng standee); Keychain Girl: walang Hair step
        sequentialSlots = !activeState.productType
            ? ["productType", "size", "model", "skin", "hair", "top", "bottom", "shoes"]
            : activeState.productType === "keychain"
              ? activeState.model && isGirlModel(activeState.model)
                  ? ["productType", "size", "model", "skin", "girlHair", "girlTop", "girlBottom", "shoes"]
                  : ["productType", "size", "model", "skin", "hair", "top", "bottom", "shoes"]
              : activeState.model && isGirlModel(activeState.model)
                ? ["productType", "size", "model", "skin", "girlHair", "girlTop", "girlBottom", "shoes"]
                : ["productType", "size", "model", "skin", "hair", "top", "bottom", "shoes"];
    }

    if (activeCategory === "hirono") {
        if (!activeState.mode) {
            sequentialSlots = ["mode", "size", "model", "skin", "hair", "outfit", "pants", "shoes"];
        } else {
            const hironoMode = getHironoMode();
            sequentialSlots =
                hironoMode === "headKeychain"
                    ? ["mode", "size", "model", "skin", "keychainHair", "keychainHat"]
                    : ["mode", "size", "model", "skin", "hair", "outfit", "pants", "shoes"];
        }
    }

    if (sequentialSlots) {
        const sequentialSections = {
            productType: sectionIds.chibi.type,
            mode: sectionIds.hirono.type,
            model: `${activeCategory}ModelSection`,
            skin: sectionIds[activeCategory].skin,
            size: "figureSizeSection",
            hair: sectionIds.funko.hair,
            hairColor: sectionIds.funko.hairColor,
            top: sectionIds.funko.top,
            topColor: sectionIds.funko.topColor,
            bottom: sectionIds.funko.bottom,
            pantsColor: sectionIds.funko.pantsColor,
            girlHair: sectionIds.funko.girlHair,
            girlHairColor: sectionIds.funko.hairColor,
            girlTop: sectionIds.funko.girlTop,
            girlTopColor: sectionIds.funko.girlTopColor,
            girlBottom: sectionIds.funko.girlBottom,
            girlBottomColor: sectionIds.funko.girlBottomColor,
            keychainHair: sectionIds.hirono.keychainHair,
            keychainHairColor: sectionIds.hirono.keychainHairColor,
            keychainHat: sectionIds.hirono.keychainHat,
            outfit: sectionIds.hirono.outfit,
            outfitColor: sectionIds.hirono.outfitColor,
            pants: sectionIds.hirono.pants,
            shoes:
                activeCategory === "funko" && activeState.model && isGirlModel(activeState.model)
                    ? sectionIds.funko.girlShoes
                    : sectionIds.funko.shoes,
            shoesColor: sectionIds.hirono.shoesColor
        };
        if (activeCategory === "chibi") {
            Object.assign(sequentialSections, {
                skin: sectionIds.chibi.skin,
                hair: sectionIds.chibi.hair,
                hairColor: sectionIds.chibi.hairColor,
                girlHair: sectionIds.chibi.girlHair,
                girlHairColor: sectionIds.chibi.girlHairColor,
                top: sectionIds.chibi.top,
                girlTop: sectionIds.chibi.girlTop,
                bottom: sectionIds.chibi.bottom,
                girlBottom: sectionIds.chibi.girlBottom,
                // Chibi Boy standee: sariling shoes (bagong Chibi Boy set)
                shoes:
                    activeState.productType !== "keychain" && activeState.model && !isGirlModel(activeState.model)
                        ? sectionIds.chibi.boyShoes
                        : sectionIds.chibi.shoes
            });
            if (activeState.productType === "keychain") {
                Object.assign(sequentialSections, {
                    top: sectionIds.chibi.keychainBoyTop,
                    bottom: sectionIds.chibi.keychainBoyBottom,
                    girlTop: sectionIds.chibi.keychainGirlTop,
                    girlBottom: sectionIds.chibi.keychainGirlBottom
                });

                // Chibi Boy keychain: sariling hair at shoes (same set ng standee, gumagamit ng Chibi Standee Boy folder)
                if (activeState.model && !isGirlModel(activeState.model)) {
                    Object.assign(sequentialSections, {
                        hair: sectionIds.chibi.keychainBoyHair,
                        shoes: sectionIds.chibi.keychainBoyShoes
                    });
                } else if (activeState.model) {
                    // Chibi Girl keychain: same set ng Chibi Girl standee
                    Object.assign(sequentialSections, {
                        girlHair: sectionIds.chibi.keychainGirlHair,
                        shoes: sectionIds.chibi.keychainGirlShoes
                    });
                }
            }
        }

        if (activeCategory === "hirono") {
            [
                "hair",
                "hairColor",
                "outfit",
                "pants",
                "pantsColor",
                "shoes",
                "shoesColor",
                "keychainHair",
                "keychainHairColor",
                "keychainHat",
                "outfitColor"
            ].forEach((slot) => {
                sequentialSections[slot] = sectionIds.hirono[slot];
            });
        }

        const designSlotByColor =
            activeCategory === "funko"
                ? {
                      hairColor: isGirlModel(activeState.model) ? "girlHair" : "hair",
                      topColor: isGirlModel(activeState.model) ? "girlTop" : "top",
                      bottomColor: "girlBottom",
                      pantsColor: "bottom"
                  }
                : activeCategory === "chibi"
                  ? { hairColor: isGirlModel(activeState.model) ? "girlHair" : "hair", girlHairColor: "girlHair" }
                  : {
                        hairColor: "hair",
                        outfitColor: "outfit",
                        pantsColor: "pants",
                        shoesColor: "shoes",
                        keychainHairColor: "keychainHair"
                    };
        const requestedSlot = designSlotByColor[navigationSlot] || navigationSlot;
        // Size ay sa productDetails naka-save (hindi sa activeState), kaya laktawan kapag napili na
        const nextSlot = sequentialSlots.find(
            (slot) => !activeState[slot] && !(slot === "size" && productDetails.sizeSelected)
        );
        let slotToShow =
            requestedSlot && sequentialSlots.includes(requestedSlot)
                ? requestedSlot
                : nextSlot || sequentialSlots[sequentialSlots.length - 1];
        activeSequentialSlots = sequentialSlots.slice();

        // FIGURE DETAILS = IISANG screen: Figure Style, Product Type, Size, Gender, Skin.
        // Anumang slot sa grupong ito ay ang unang step ng sequence ang ipapakita.
        const firstGroup = [];
        for (const slot of sequentialSlots) {
            if (!FIRST_GROUP_SLOTS.includes(slot)) {
                break;
            }
            firstGroup.push(slot);
        }

        const firstStep = firstGroup.includes(slotToShow);

        // Step 1 (Figure Details) = Figure Style, Product Type, Size, Gender, Skin Tone.
        // Lahat kita agad (naka-lock hanggang mapili ang nauna). Parts = Hair/Top/Bottom/Shoes na lang.
        const detailGroup = firstGroup;
        if (firstStep) {
            slotToShow = firstGroup[0];
        }

        onFirstStep = firstStep;
        currentDesignSlot = slotToShow;
        if (figureStyleSection) {
            figureStyleSection.hidden = !firstStep;
            figureStyleSection.classList.remove("merged-with-type");
        }

        // Hair step: lapit sa ulo/buhok; ibang step: buong figure
        setCameraFocus(navigationSlot !== "productDetails" && HEAD_FOCUS_SLOTS.includes(slotToShow) ? "head" : "full");
        const slotBefore =
            slotToShow === "skin" || slotToShow.endsWith("Color")
                ? sequentialSlots[sequentialSlots.indexOf(slotToShow) - 1]
                : null;
        const colorSlotByDesign = {
            hair: "hairColor",
            girlHair: "girlHairColor",
            top: "topColor",
            girlTop: "girlTopColor",
            bottom: "pantsColor",
            girlBottom: "girlBottomColor",
            pants: "pantsColor",
            shoes: "shoesColor",
            outfit: "outfitColor",
            keychainHair: "keychainHairColor"
        };
        const slotAfter = colorSlotByDesign[slotToShow];

        // Gender at Skin Tone nasa Figure Details screen na (kasunod ng Size).
        const modelPairedSlots = slotToShow === "model" ? ["skin"] : firstStep ? detailGroup.slice(1) : [];
        const designsComplete = !nextSlot;
        const detailsStep = navigationSlot === "productDetails";
        renderSizeSection();
        if (continueBtn) {
            continueBtn.hidden = !detailsStep;
        }

        if (productDetailsPanel) {
            productDetailsPanel.hidden = !detailsStep;
        }

        if (figureDetailsContainer) {
            figureDetailsContainer.hidden = detailsStep;
        }

        if (detailsStep) {
            Object.values(sequentialSections).forEach((id) => {
                setVisibleById(id, false);
            });
            renderProductDetailsPanel();
            renderDesignNavigation(
                sequentialSlots,
                sequentialSections,
                activeState,
                sequentialSlots[sequentialSlots.length - 1]
            );
            return;
        }

        // Figure Details: sunod-sunod ang lalabas - Gender (at Size) pagkatapos ng Product Type,
        // Skin Tone pagkatapos ng Gender. Walang Product Type ang Funko kaya diretso.
        const typeSlot = sequentialSlots.find((slot) => slot === "productType" || slot === "mode");
        const typePicked = !typeSlot || Boolean(activeState[typeSlot]);
        const detailReady = { size: typePicked, model: typePicked, skin: typePicked && Boolean(activeState.model) };

        setVisibleById(sequentialSections.model, firstStep && detailGroup.includes("model") && detailReady.model);
        setVisibleById(sequentialSections[slotBefore], Boolean(slotBefore));
        setVisibleById(sequentialSections[slotToShow], Boolean(slotToShow));
        setVisibleById(sequentialSections[slotAfter], Boolean(slotAfter));

        // Parts: Hair, tapos Top, Bottom, Shoes (isa-isa, hindi nag-iipon). Wala nang Skin Tone dito.

        modelPairedSlots.forEach((slot) => {
            let show = true;
            if (slot === "size") {
                // walang Size na hihingin (hal. Hirono Head Keychain) = walang section
                const sizeConfig = getProductDetailsConfig();
                show = Boolean(sizeConfig?.sizes?.length) && Boolean(sizeChoiceGrid && sizeChoiceGrid.children.length);
            }
            setVisibleById(sequentialSections[slot], show && detailReady[slot] !== false);
        });
        if (designsComplete) {
            setVisibleById(sequentialSections[slotToShow], true);
        }

        renderDesignNavigation(sequentialSlots, sequentialSections, activeState, slotToShow, modelPairedSlots);
        return;
    }

    if (activeCategory === "funko") {
        if (activeState.model) {
            setVisibleById(sectionIds.funko.skin, true);
        }

        if (!activeState.skin) {
            return;
        }

        if (isGirlModel(activeState.model)) {
            showSections([
                sectionIds.funko.girlHair,
                sectionIds.funko.hairColor,
                sectionIds.funko.girlTop,
                sectionIds.funko.girlTopColor,
                sectionIds.funko.girlBottom,
                sectionIds.funko.girlBottomColor
            ]);
        } else {
            showSections([
                sectionIds.funko.hair,
                sectionIds.funko.hairColor,
                sectionIds.funko.top,
                sectionIds.funko.topColor,
                sectionIds.funko.bottom,
                sectionIds.funko.pantsColor
            ]);
        }

        return;
    }

    const hironoMode = getHironoMode();
    if (activeCategory === "hirono") {
        const hironoSequence =
            hironoMode === "headKeychain"
                ? ["model", "skin", "keychainHair", "keychainHairColor", "keychainHat"]
                : ["model", "skin", "hair", "hairColor", "outfit", "pants", "pantsColor", "shoes", "shoesColor"];
        const hironoSections = { model: "hironoModelSection", ...sectionIds.hirono };
        const nextSlot = hironoSequence.find((slot) => !activeState[slot]);
        const designsComplete = !nextSlot;
        if (productDetailsPanel) {
            productDetailsPanel.hidden = !designsComplete;
        }

        if (figureDetailsContainer) {
            figureDetailsContainer.hidden = designsComplete;
        }

        setVisibleById(hironoSections.model, nextSlot === "model");
        setVisibleById(hironoSections[nextSlot], Boolean(nextSlot));
        return;
    }

    if (hironoMode === "headKeychain") {
        if (activeState.model) {
            setVisibleById(sectionIds.hirono.skin, true);
        }

        if (!activeState.skin) {
            return;
        }

        showSections([
            sectionIds.hirono.keychainHair,
            sectionIds.hirono.keychainHairColor,
            sectionIds.hirono.keychainHat
        ]);
        return;
    }

    if (hironoMode === "keychain") {
        if (activeState.model) {
            setVisibleById(sectionIds.hirono.skin, true);
        }

        if (!activeState.skin) {
            return;
        }

        showSections([
            sectionIds.hirono.hair,
            sectionIds.hirono.hairColor,
            sectionIds.hirono.outfit,
            sectionIds.hirono.pants,
            sectionIds.hirono.pantsColor,
            sectionIds.hirono.shoes,
            sectionIds.hirono.shoesColor
        ]);
        return;
    }

    if (activeState.model) {
        setVisibleById(sectionIds.hirono.skin, true);
    }

    if (!activeState.skin) {
        return;
    }

    if (hironoMode === "keychain") {
        showSections([
            sectionIds.hirono.keychainHair,
            sectionIds.hirono.keychainHairColor,
            sectionIds.hirono.keychainHat
        ]);
        return;
    }

    showSections([
        sectionIds.hirono.hair,
        sectionIds.hirono.hairColor,
        sectionIds.hirono.outfit,
        sectionIds.hirono.pants,
        sectionIds.hirono.pantsColor,
        sectionIds.hirono.shoes,
        sectionIds.hirono.shoesColor
    ]);
}

/* =====================================================
   12. DEFAULT, KULAY AT DAMIT
   Default na napili, custom color, at grupo ng damit
   ===================================================== */

// keepProductType = true: iwan ang napiling Product Type (Standee / Keychain) at ang card nito
function clearCategoryState(category, keepProductType) {
    const keep = keepProductType ? { mode: state[category].mode, productType: state[category].productType } : {};
    Object.assign(state[category], createCategoryState(), keep);
    const typeCards = keepProductType ? ':not([data-slot="chibiType"]):not([data-slot="hironoType"])' : "";
    clearSelectedClasses(`[data-figure="${category}"][data-slot]${typeCards}`);
}

function isPlaceholderModelPath(path) {
    return !path || String(path).startsWith("placeholder:");
}

// Kapag pumili ng Gender (model), auto-select agad ang mga "Default" na item
// (Default Hair, Sando/Tank Top, Shorts, Shoes) para may laman na ang bawat
// step at makapag-Next agad. Puwede pa rin nilang palitan anytime.
function applyDefaultSelections(category) {
    const stateForCategory = state[category];
    if (!stateForCategory || !stateForCategory.model) {
        return;
    }

    getActiveSlotOrder().forEach(function (slot) {
        if (slot === "model" || slot === "skin" || slot.endsWith("Color") || stateForCategory[slot]) {
            return;
        }

        // Chibi keychain may sariling top/bottom sections - doon kunin ang Default card.
        // Chibi Boy standee may sariling shoes section (bagong Chibi Boy set).
        const keychainSectionId =
            category === "chibi" && stateForCategory.productType === "keychain"
                ? {
                      hair:
                          stateForCategory.model && !isGirlModel(stateForCategory.model)
                              ? sectionIds.chibi.keychainBoyHair
                              : null,
                      top: sectionIds.chibi.keychainBoyTop,
                      bottom: sectionIds.chibi.keychainBoyBottom,
                      shoes:
                          stateForCategory.model && !isGirlModel(stateForCategory.model)
                              ? sectionIds.chibi.keychainBoyShoes
                              : sectionIds.chibi.keychainGirlShoes,
                      girlHair: sectionIds.chibi.keychainGirlHair,
                      girlTop: sectionIds.chibi.keychainGirlTop,
                      girlBottom: sectionIds.chibi.keychainGirlBottom
                  }[slot]
                : category === "chibi" &&
                    slot === "shoes" &&
                    stateForCategory.model &&
                    !isGirlModel(stateForCategory.model)
                  ? sectionIds.chibi.boyShoes
                  : category === "funko" &&
                      slot === "shoes" &&
                      stateForCategory.model &&
                      isGirlModel(stateForCategory.model)
                    ? sectionIds.funko.girlShoes
                    : null;
        const defaultCard = (
            (keychainSectionId && document.getElementById(keychainSectionId)) ||
            document
        ).querySelector(`[data-figure="${category}"][data-slot="${slot}"][data-default="true"]`);
        if (!defaultCard) {
            return;
        }

        stateForCategory[slot] = buildItemFromCard(defaultCard);
        markSelectedCard(defaultCard);
    });
}

function buildItemFromCard(card) {
    return {
        name: card.dataset.name || "",
        model: card.dataset.model || "",
        color: card.dataset.color || "",
        price: Number(card.dataset.price || 0),
        billable: card.dataset.billable !== "false"
    };
}

function getColorAccessorySlot(category, slot) {
    let accessorySlot = slot.replace("Color", "");
    if (category === "funko" && slot === "pantsColor") {
        accessorySlot = "bottom";
    } else if (category === "funko" && slot === "hairColor" && isGirlModel(state[category].model)) {
        accessorySlot = "girlHair";
    } else if (category === "funko" && slot === "topColor" && isGirlModel(state[category].model)) {
        accessorySlot = "girlTop";
    } else if (category === "funko" && slot === "bottomColor" && isGirlModel(state[category].model)) {
        accessorySlot = "girlBottom";
    }

    return accessorySlot;
}

function applyCustomColor(category, slot, color, input) {
    const stateForCategory = state[category];
    const accessorySlot = getColorAccessorySlot(category, slot);
    const currentObject = getLoadedObject(accessorySlot);
    stateForCategory[slot] = { name: "Outfit Color", model: "", color, price: 0, billable: false };
    if (currentObject) {
        if (category === "funko" && (accessorySlot === "bottom" || slot === "bottomColor")) {
            applyBottomPartColors(currentObject, color);
        } else {
            applyColorToModel(currentObject, color, {
                clearTextureMaps: true,
                skipMaterialFragments: ["design", "logo", "print"]
            });
        }
    } else if (isSandoColorSlot(category, slot)) {
        applySandoColor(color);
    }

    state.currentCategory = category;
    navigationSlot = getColorAccessorySlot(category, slot);
    syncCustomColorPickers();
    updateSectionVisibility();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
}

const customColorDesignSectionIds = {
    funkoHairColorSection: ["funkoHairSection", "funkoGirlHairSection"],
    funkoGirlTopColorSection: ["funkoGirlTopSection"],
    funkoTopColorSection: ["funkoTopSection"],
    funkoGirlBottomColorSection: ["funkoGirlBottomSection"],
    funkoBottomPantsColorSection: ["funkoBottomSection"],
    hironoHairColorSection: ["hironoHairSection"],
    hironoOutfitColorSection: ["hironoOutfitSection"],
    hironoPantsColorSection: ["hironoPantsSection"],
    hironoShoesColorSection: ["hironoShoesSection"],
    hironoKeychainHairColorSection: ["hironoKeychainHairSection"],
    funkoShoesColorSection: ["funkoShoesSection"],
    chibiHairColorSection: ["chibiHairSection", "chibiBoyKeychainHairSection"],
    chibiGirlHairColorSection: ["chibiGirlHairSection", "chibiGirlKeychainHairSection"],
    chibiTopColorSection: ["chibiTopSection", "chibiBoyKeychainTopSection"],
    chibiGirlTopColorSection: ["chibiGirlTopSection", "chibiGirlKeychainTopSection"],
    chibiBottomColorSection: ["chibiBottomSection", "chibiBoyKeychainBottomSection"],
    chibiGirlBottomColorSection: ["chibiGirlBottomSection", "chibiGirlKeychainBottomSection"],
    chibiShoesColorSection: [
        "chibiShoesSection",
        "chibiBoyShoesSection",
        "chibiBoyKeychainShoesSection",
        "chibiGirlKeychainShoesSection"
    ]
};

function getCustomColorMounts(section) {
    // Shoes: walang custom color / Reset (wala talagang mount = walang control)
    if (/ShoesColorSection$/.test(section.id) || section.dataset.slot === "shoesColor") {
        return [];
    }
    const siblings = Array.from(section.parentElement.children);
    const designSectionIds = customColorDesignSectionIds[section.id];
    if (designSectionIds) {
        return designSectionIds
            .map((id) => document.getElementById(id))
            .filter(Boolean)
            .map((designSection) => designSection.querySelector(".section-title"))
            .filter(Boolean);
    }

    const designSection = siblings
        .slice(0, siblings.indexOf(section))
        .reverse()
        .find((candidate) => candidate.classList.contains("option-section"));
    const mount = designSection?.querySelector(".section-title");
    return mount ? [mount] : [section];
}

function initializeCustomColorPickers() {
    document.querySelectorAll(".top-color-picker, .custom-color-only-section").forEach(function (section) {
        const mounts = getCustomColorMounts(section);
        if (mounts.every((mount) => mount.querySelector(".custom-color-control"))) {
            return;
        }

        const slotCard = section.querySelector("[data-slot]");
        const slot = (slotCard && slotCard.dataset.slot) || section.dataset.slot;
        const category = (slotCard && slotCard.dataset.figure) || section.dataset.figure;
        if (!slot || !category) {
            return;
        }

        mounts.forEach((mount) => {
            if (mount.querySelector(".custom-color-control")) {
                return;
            }

            const label = document.createElement("div");
            label.className = "custom-color-control";
            label.innerHTML =
                `<label class="custom-color-pick">` +
                `<svg class="custom-color-wheel" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="none"></circle></svg>` +
                `<span class="custom-color-text">` +
                `<span class="custom-color-label">Outfit color</span>` +
                `<span class="custom-color-hex">Tap to pick a color</span>` +
                `</span>` +
                `<input type="color" value="#ffffff" aria-label="Choose custom ${slot} color">` +
                `</label>` +
                `<button type="button" class="custom-color-reset" hidden aria-label="Reset ${slot} color">Reset</button>`;
            mount.appendChild(label);
            const input = label.querySelector("input");
            const wheel = label.querySelector(".custom-color-wheel");
            const resetColorBtn = label.querySelector(".custom-color-reset");
            input.addEventListener("input", function () {
                setWheelColor(wheel, input.value);
                applyCustomColor(category, slot, input.value, input);
            });
            resetColorBtn.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                resetPartColor(category, slot);
            });
        });
    });
}

/* =====================================================================
   COLOR DOT / COLOR WHEEL - kulay sa SVG "fill" (walang inline style)
   ===================================================================== */
function makeColorDot(className, color) {
    const svgNs = "http://www.w3.org/2000/svg";
    const svg = document.createElementNS(svgNs, "svg");
    svg.setAttribute("class", className);
    svg.setAttribute("viewBox", "0 0 10 10");
    svg.setAttribute("aria-hidden", "true");
    const circle = document.createElementNS(svgNs, "circle");
    circle.setAttribute("cx", "5");
    circle.setAttribute("cy", "5");
    circle.setAttribute("r", "5");
    circle.setAttribute("fill", color || "none");
    svg.appendChild(circle);
    return svg;
}

// walang kulay = makikita ang rainbow na background ng wheel
function setWheelColor(wheel, color) {
    const circle = wheel && wheel.querySelector("circle");
    if (circle) {
        circle.setAttribute("fill", color || "none");
    }
}
/* ===== END COLOR DOT / COLOR WHEEL ===== */

function syncCustomColorPickers() {
    document.querySelectorAll(".top-color-picker, .custom-color-only-section").forEach(function (section) {
        const slotCard = section.querySelector("[data-slot]");
        const slot = (slotCard && slotCard.dataset.slot) || section.dataset.slot;
        const category = (slotCard && slotCard.dataset.figure) || section.dataset.figure;
        const savedColor =
            category && slot && state[category] && state[category][slot] ? state[category][slot].color : "";
        const hasColor = /^#[0-9a-f]{6}$/i.test(savedColor || "");
        getCustomColorMounts(section).forEach((mount) => {
            const control = mount.querySelector(".custom-color-control");
            const input = control && control.querySelector("input[type='color']");
            if (!control || !input) {
                return;
            }

            const wheel = control.querySelector(".custom-color-wheel");
            const hex = control.querySelector(".custom-color-hex");
            const resetColorBtn = control.querySelector(".custom-color-reset");
            if (hasColor) {
                input.value = savedColor;
                setWheelColor(wheel, savedColor);
                if (hex) {
                    hex.textContent = savedColor.toUpperCase();
                }
            } else {
                input.value = "#ffffff";
                setWheelColor(wheel, "");
                if (hex) {
                    hex.textContent = "Tap to pick a color";
                }
            }

            control.classList.toggle("has-color", hasColor);
            if (resetColorBtn) {
                resetColorBtn.hidden = !hasColor;
            }
        });
    });
}

// Reset ng kulay ng ISANG part lang (hair / top / bottom / shoes): balik sa original na kulay
async function resetPartColor(category, slot) {
    if (!state[category] || !state[category][slot]) {
        return;
    }

    state[category][slot] = null;
    state.currentCategory = category;
    navigationSlot = getColorAccessorySlot(category, slot);
    await renderCurrentCategory();
    syncSelectedCardsFromState();
    syncCustomColorPickers();
    updateSectionVisibility();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
}

// DAMIT: kulay at grupo
// - Custom color: plain lang na damit (t-shirt, sando, hoodie, shorts, plain pants...)
// Walang kulay ang coat, suit, may design/pattern, cargo, denim at iba pa.
// - Mga magkakatulad na damit ay naka-grupo (Coats, Shirts, Pants...) - click ang
// header para buksan/isara.

const CLOTHING_SLOTS = ["top", "girlTop", "outfit", "bottom", "girlBottom", "pants"];
// may group bar (hal. "HAIR (11)") ang mga ito: damit + buhok + sapatos
const GROUPED_SLOTS = [...CLOTHING_SLOTS, "hair", "girlHair", "keychainHair", "shoes"];
const CLOTHING_TOP_SLOTS = ["top", "girlTop", "outfit"];
// Hair: walang dropdown/group header - lahat ng hair cards magkakatabi (4 sa isang row)
const HAIR_SLOTS = ["hair", "girlHair", "keychainHair", "shoes"];

function isItemColorable(slot, name) {
    if (!CLOTHING_SLOTS.includes(slot)) {
        return true;
    }
    const n = String(name || "");
    if (
        /graphic|pinstripe|coat|suit|jersey|cargo|denim|jacket|dress|tie|spider|venom|girl shirt|uniform|ccc|\bny\b|rl polo/i.test(
            n
        )
    ) {
        return false;
    }
    return /sando|tank|shirt|polo|hoodie|sweater|long sleeve|shorts|skirt|pants/i.test(n);
}

function getClothingGroup(slot, name) {
    const n = String(name || "");
    if (slot === "hair" || slot === "girlHair" || slot === "keychainHair") return [1, "Hair"];
    if (slot === "shoes") return [1, "Shoes"];
    if (CLOTHING_TOP_SLOTS.includes(slot)) {
        if (/coat|suit|jacket|blazer/i.test(n)) return [1, "Coats & Jackets"];
        if (/dress/i.test(n)) return [2, "Dresses"];
        if (/hoodie|sweater/i.test(n)) return [3, "Hoodies & Sweaters"];
        if (/uniform/i.test(n)) return [7, "Uniforms"];
        if (/long sleeve/i.test(n)) return [4, "Long Sleeves"];
        if (/shirt|polo|jersey/i.test(n)) return [5, "Shirts & Polos"];
        if (/sando|tank/i.test(n)) return [6, "Sando & Tank Tops"];
        return [9, "Others"];
    }
    if (/shorts/i.test(n)) return [1, "Shorts"];
    if (/skirt/i.test(n)) return [2, "Skirts"];
    if (/pants|slacks/i.test(n)) return [3, "Pants"];
    return [9, "Others"];
}

const clothingGroupOpen = {};
let clothingUITimer = null;

function scheduleClothingUI() {
    if (clothingUITimer) {
        return;
    }
    clothingUITimer = setTimeout(() => {
        clothingUITimer = null;
        try {
            refreshColorAvailability();
            refreshClothingGroups();
        } catch (error) {
            console.warn("clothing UI refresh failed", error);
        }
    }, 0);
}

// itago ang Custom color / color cards kapag hindi pwedeng kulayan ang napiling damit
function refreshColorAvailability() {
    document.querySelectorAll(".top-color-picker, .custom-color-only-section").forEach((section) => {
        const slotCard = section.querySelector("[data-slot]");
        const colorSlot = (slotCard && slotCard.dataset.slot) || section.dataset.slot;
        const category = (slotCard && slotCard.dataset.figure) || section.dataset.figure;
        if (!colorSlot || !category || !state[category]) {
            return;
        }

        const accessorySlot = getColorAccessorySlot(category, colorSlot);
        if (!CLOTHING_SLOTS.includes(accessorySlot)) {
            return;
        }

        const item = state[category][accessorySlot];
        const colorable = !item || isItemColorable(accessorySlot, item.name);
        section.classList.toggle("color-unavailable", !colorable);
        getCustomColorMounts(section).forEach((mount) => {
            const control = mount.querySelector(".custom-color-control");
            if (control) {
                control.classList.toggle("color-unavailable", !colorable);
            }
        });
    });
}

function refreshClothingGroups() {
    document.querySelectorAll(".option-section > .option-grid").forEach((grid) => {
        const cards = Array.from(grid.querySelectorAll(":scope > .option-card"));
        if (!cards.length || !GROUPED_SLOTS.includes(cards[0].dataset.slot)) {
            return;
        }

        const section = grid.parentElement;
        const slot = cards[0].dataset.slot;
        const keyBase = (section.id || slot) + ":";
        if (HAIR_SLOTS.includes(slot)) {
            grid.querySelectorAll(":scope > .option-group-head").forEach((el) => el.remove());
            grid.classList.remove("has-option-groups");
            grid.classList.add("is-flat-grid");
            cards.forEach((card) => card.classList.remove("group-collapsed"));
            return;
        }

        // ang default (Sando, Shorts, atbp.) ay laging nasa pinaka-taas, walang grupo/header
        const defaultCards = cards.filter((card) => !card.hidden && card.dataset.default === "true");
        const groups = new Map();
        cards.forEach((card) => {
            if (card.hidden || card.dataset.default === "true") {
                return;
            }
            const [order, label] = getClothingGroup(slot, card.dataset.name);
            if (!groups.has(label)) {
                groups.set(label, { order, label, cards: [] });
            }
            groups.get(label).cards.push(card);
        });
        const list = Array.from(groups.values()).sort((a, b) => a.order - b.order);

        // walang saysay ang grupo kung iisa lang
        // Kung pareho pa rin ang ayos ng mga grupo/card (pumili lang ng item), huwag nang galawin
        // ang DOM - ang pag-alis at pag-append ng lahat ng card ang nagre-reset ng scroll at nagba-blink.
        const layoutSig = [
            defaultCards.map((card) => card.dataset.name).join(","),
            list.map((group) => `${group.label}:${group.cards.map((card) => card.dataset.name).join(",")}`).join("|"),
            cards
                .filter((card) => card.hidden)
                .map((card) => card.dataset.name)
                .join(",")
        ].join("##");
        if (
            grid.dataset.layoutSig === layoutSig &&
            grid.classList.contains("has-option-groups") &&
            (!list.length || grid.querySelector(":scope > .option-group-head"))
        ) {
            return;
        }

        const gridScrollTop = grid.scrollTop;
        grid.querySelectorAll(":scope > .option-group-head").forEach((el) => el.remove());
        grid.classList.add("has-option-groups");
        grid.dataset.layoutSig = layoutSig;
        defaultCards.forEach((card) => {
            card.classList.remove("group-collapsed");
            grid.appendChild(card);
        });
        list.forEach((group, index) => {
            const key = keyBase + group.label;

            // default: bukas ang grupo ng napiling damit (o ang una kung wala pang napili);
            // kung nag-click na ang user sa header, siya ang masusunod
            const isOpen = () => (key in clothingGroupOpen ? clothingGroupOpen[key] : true);
            const head = document.createElement("button");
            head.type = "button";
            head.className = "option-group-head";
            head.setAttribute("aria-expanded", isOpen() ? "true" : "false");
            head.innerHTML = `<span class="option-group-title">${group.label} (${group.cards.length})</span><span class="option-group-chevron" aria-hidden="true"></span>`;
            const apply = () => {
                const open = Boolean(isOpen());
                head.setAttribute("aria-expanded", open ? "true" : "false");
                group.cards.forEach((card) => card.classList.toggle("group-collapsed", !open));
            };
            head.addEventListener("click", () => {
                clothingGroupOpen[key] = !isOpen();
                apply();
            });

            // ilagay ang header at ang mga card ng grupo nang magkakasunod
            grid.appendChild(head);
            group.cards.forEach((card) => grid.appendChild(card));
            apply();
        });

        // mga card na naka-hidden (hal. ibang gender) - iwan sa dulo
        cards.filter((card) => card.hidden).forEach((card) => grid.appendChild(card));

        // ibalik ang scroll position ng grid (nare-reset kapag inayos ang mga card)
        if (gridScrollTop > 0) {
            grid.scrollTop = gridScrollTop;
        }
    });
}

/* =====================================================
   13. LABELS AT PRESYO PER SIZE
   Pangalan at presyo ng napili, ayon sa size
   ===================================================== */

function getFigureLabel(category) {
    if (category === "funko") {
        return "Funko Pop";
    }

    if (category === "hirono") {
        return "Hirono";
    }

    if (category === "chibi") {
        return "Chibi";
    }

    return category || "";
}

function escapeSelectorValue(value) {
    const stringValue = String(value || "");
    if (window.CSS && typeof window.CSS.escape === "function") {
        return window.CSS.escape(stringValue);
    }

    return stringValue.replace(/["\\]/g, "\\$&");
}

// PRESYO NG ITEM BASE SA SIZE
// Ang data-price ng bawat card ay presyo sa pinakamaliit na size. Tumataas
// ito kada size: 1st = 100%, 2nd = 125%, 3rd = 150%, 4th = 175% (naka-round
// sa ₱5). Kapareho ito ng kuwenta sa server (Commission/submit_order.php).

const DRESSUP_SIZE_PRICE_PERCENTS = [100, 125, 150, 175];

function getDressUpSizePercent() {
    const config = getProductDetailsConfig();
    if (!config || !config.sizes || !config.sizes.length || !productDetails.size) {
        return 100;
    }

    const index = config.sizes.indexOf(productDetails.size);
    if (index < 0) {
        return 100;
    }

    return DRESSUP_SIZE_PRICE_PERCENTS[Math.min(index, DRESSUP_SIZE_PRICE_PERCENTS.length - 1)];
}

function scaleDressUpPrice(basePrice) {
    const base = Number(basePrice || 0);
    if (base <= 0) {
        return 0;
    }

    return Math.round((base * getDressUpSizePercent()) / 500) * 5;
}

function getItemPrice(item) {
    if (!item || item.billable === false) {
        return 0;
    }

    return scaleDressUpPrice(item.price);
}

// presyo sa ilalim ng bawat card (nagbabago kapag pinalitan ang size)
function refreshOptionPriceLabels() {
    document.querySelectorAll("[data-slot] .option-price[data-base-price]").forEach((label) => {
        const card = label.closest("[data-slot]");
        if (!card) {
            return;
        }

        const billable = card.dataset.billable !== "false";
        const price = billable ? scaleDressUpPrice(card.dataset.price) : 0;
        label.textContent = price > 0 ? `₱${price}` : "";
    });
}

// kapag may lumang draft (bago nagbago ang presyo), kunin ulit ang presyo
// mula sa mismong card para tugma sa server
function syncItemPricesFromCards() {
    Object.keys(categoryPanels).forEach((category) => {
        const stateForCategory = state[category];
        if (!stateForCategory) {
            return;
        }

        Object.keys(stateForCategory).forEach((slot) => {
            const item = stateForCategory[slot];
            if (!item || typeof item !== "object" || !item.model || slot === "model" || slot.endsWith("Color")) {
                return;
            }

            const card = document.querySelector(
                `[data-figure="${category}"][data-slot="${slot}"][data-model="${escapeSelectorValue(item.model)}"]`
            );
            if (card) {
                item.price = Number(card.dataset.price || 0);
                item.billable = card.dataset.billable !== "false";
            }
        });
    });
}

function getActiveStateTotal(activeState) {
    if (!activeState) {
        return 0;
    }

    return getActiveSlotOrder().reduce(function (total, slot) {
        const item = activeState[slot];
        if (!item || item.billable === false) {
            return total;
        }

        return total + getItemPrice(item);
    }, 0);
}

function getSelectedItemNames(activeState) {
    if (!activeState) {
        return [];
    }

    return getActiveSlotOrder()
        .map(function (slot) {
            const item = activeState[slot];
            return item ? item.name : null;
        })
        .filter(Boolean);
}

function getSelectionDetails(activeState) {
    if (!activeState) {
        return [];
    }

    const labels = {
        model: "Figure type",
        skin: "Skin Tone",
        hair: "Hair",
        girlHair: "Hair",
        keychainHair: "Hair",
        top: "Top",
        girlTop: "Top",
        outfit: "Top",
        bottom: "Bottom",
        girlBottom: "Bottom",
        pants: "Bottom",
        shoes: "Shoes",
        hat: "Hat",
        accessory: "Accessory"
    };
    return getActiveSlotOrder().reduce((details, slot) => {
        if (slot === "skin" || slot.endsWith("Color")) {
            return details;
        }

        const item = activeState[slot];
        if (!item || !labels[slot]) {
            return details;
        }

        const value = getSummarySelectionValue(slot, item, activeState);
        details.push({ label: labels[slot], value });
        return details;
    }, []);
}

function getAccessoryNames(activeState) {
    if (!activeState) {
        return [];
    }

    return getActiveSlotOrder()
        .filter(function (slot) {
            return slot !== "model" && slot !== "skin";
        })
        .map(function (slot) {
            const item = activeState[slot];
            return item ? item.name : null;
        })
        .filter(Boolean);
}

function getPrimarySelectionName(activeState) {
    if (!activeState || !activeState.model) {
        return "";
    }

    return activeState.model.name || "";
}

function toSummarySlug(value) {
    return String(value || "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/-([0-9]+)/g, "$1")
        .replace(/^-+|-+$/g, "");
}

function getFileBaseName(value) {
    return String(value || "")
        .split(/[\\/]/)
        .pop()
        .replace(/\.[^.]+$/, "");
}

function getSummaryToken(item) {
    if (!item) {
        return "";
    }

    return toSummarySlug(getFileBaseName(item.model || item.name || item.color || ""));
}

function getSummaryColorToken(item) {
    if (!item) {
        return "";
    }

    return toSummarySlug(item.name || item.color || "");
}

function getFigureTypeSummaryValue(activeState) {
    const category = toSummarySlug(getFigureLabel(state.currentCategory));
    const model = getSummaryToken(activeState?.model);
    const modelName = toSummarySlug(activeState?.model?.name);
    if (!category) {
        return model;
    }

    let suffix = model;
    if (category === "funko-pop") {
        suffix = suffix
            .replace(/^funko-pop-?/, "")
            .replace(/^funko-?/, "")
            .replace(/^pop-?/, "");
        if (suffix === "default") {
            suffix = "boy";
        }
    } else if (category === "hirono") {
        return modelName || (suffix ? `${category}-${suffix.replace(/^hirono-?/, "")}` : category);
    } else if (category === "chibi") {
        return modelName || (suffix ? `${category}-${suffix.replace(/^chibi-?/, "")}` : category);
    }

    return suffix ? `${category}-${suffix}` : category;
}

function getSummarySelectionValue(slot, item, activeState) {
    if (!item) {
        return "";
    }

    if (slot === "model") {
        return getFigureTypeSummaryValue(activeState);
    }

    if (slot === "skin") {
        return getSummaryColorToken(item);
    }

    const colorSlot = {
        hair: "hairColor",
        girlHair: "girlHairColor",
        keychainHair: "keychainHairColor",
        top: "topColor",
        girlTop: "girlTopColor",
        bottom: "pantsColor",
        girlBottom: "girlBottomColor",
        pants: "pantsColor",
        outfit: "outfitColor",
        shoes: "shoesColor",
        hat: "hatColor",
        accessory: "accessoryColor"
    }[slot];
    const color = colorSlot ? activeState?.[colorSlot] : null;
    const itemToken = getSummaryToken(item);
    const colorToken = color && color.name !== "Custom Color" ? getSummaryColorToken(color) : "";
    return colorToken ? `${itemToken}-${colorToken}` : itemToken;
}

function syncSelectedCardsFromState() {
    clearSelectedClasses(".style-card, .figure-model-card, .skin-card, .option-card, .top-color-card");
    if (!state.currentCategory) {
        return;
    }

    const activeState = getActiveState();
    if (!activeState) {
        return;
    }

    const activeCategory = state.currentCategory;

    // tamang Gender cards lang ang makikita pagbalik (hindi doble)
    if (activeCategory === "chibi") {
        syncChibiModelOptions();
    } else if (activeCategory === "hirono") {
        syncHironoGenderOptions();
    }

    const styleCard = document.querySelector(`[data-tab="figure"][data-figure="${activeCategory}"]`);
    if (styleCard) {
        styleCard.classList.add("selected");
    }

    // napiling Product Type (Standee / Keychain)
    const typeCard = findProductTypeCard(activeCategory);
    if (typeCard) {
        typeCard.classList.add("selected");
    }

    if (activeState.model) {
        const modelCard = document.querySelector(
            `[data-figure="${activeCategory}"][data-slot="model"][data-model="${escapeSelectorValue(activeState.model.model)}"]`
        );
        if (modelCard) {
            modelCard.classList.add("selected");
        }
    }

    if (activeState.skin) {
        const skinCard = document.querySelector(
            `[data-figure="${activeCategory}"][data-slot="skin"][data-color="${escapeSelectorValue(activeState.skin.color)}"]`
        );
        if (skinCard) {
            skinCard.classList.add("selected");
        }
    }

    getActiveSlotOrder().forEach(function (slot) {
        const item = activeState[slot];
        if (!item || slot === "model" || slot === "skin") {
            return;
        }

        // querySelectorAll: iisang model minsan nasa standee at keychain section
        const cards = item.color
            ? document.querySelectorAll(
                  `[data-figure="${activeCategory}"][data-slot="${slot}"][data-color="${escapeSelectorValue(item.color)}"]`
              )
            : document.querySelectorAll(
                  `[data-figure="${activeCategory}"][data-slot="${slot}"][data-model="${escapeSelectorValue(item.model)}"]`
              );
        cards.forEach((card) => card.classList.add("selected"));
    });
}

/* =====================================================
   14. SAVE AT RESTORE NG DESIGN
   Pag-save sa sessionStorage at pagbalik ng design (hal. Back mula sa Final Summary)
   ===================================================== */

function buildCustomizationSnapshot(isCompleted, isConfirmed) {
    const activeState = getActiveState();
    if (!activeState || !state.currentCategory) {
        return null;
    }

    const selectedItems = getSelectedItemNames(activeState);
    const orderTypeValue = getCommissionOrderTypeValue();
    const bookingDateValue = getCommissionBookingDateValue();
    const estimatedPrice = getCurrentOrderTotal(activeState);
    return {
        creationMethod: "create",
        customizationCompleted: Boolean(isCompleted),
        customizationConfirmed: Boolean(isConfirmed),
        currentCategory: state.currentCategory,
        figureCategory: getFigureLabel(state.currentCategory),
        orderType: getCommissionOrderTypeLabel(),
        orderTypeValue: orderTypeValue || "",
        bookingDate: getCommissionBookingDateLabel(),
        bookingDateValue: bookingDateValue || "",
        figureModel: getPrimarySelectionName(activeState),
        skin: activeState.skin ? activeState.skin.name : "",
        hair: activeState.hair
            ? activeState.hair.name
            : activeState.girlHair
              ? activeState.girlHair.name
              : activeState.keychainHair
                ? activeState.keychainHair.name
                : "",
        top: activeState.top
            ? activeState.top.name
            : activeState.girlTop
              ? activeState.girlTop.name
              : activeState.outfit
                ? activeState.outfit.name
                : "",
        bottom: activeState.bottom
            ? activeState.bottom.name
            : activeState.girlBottom
              ? activeState.girlBottom.name
              : activeState.pants
                ? activeState.pants.name
                : "",
        shoes: activeState.shoes ? activeState.shoes.name : "",
        accessories: getAccessoryNames(activeState),
        selectedItems,
        selectionDetails: getSelectionDetails(activeState),
        previewImage: canvas ? canvas.toDataURL("image/png") : "",
        estimatedPrice,
        productKey: productDetails.productKey,
        figureSize: productDetails.size,
        figureSizeSelected: Boolean(productDetails.sizeSelected),
        figureName: productDetails.figureName,
        boxType: productDetails.box,
        boxSelected: Boolean(productDetails.boxSelected),
        boxName: productDetails.boxName,
        boxNumber: productDetails.boxNumber,
        boxColor: productDetails.boxColor,
        blindBox: productDetails.blindBox,
        blindBoxSelected: Boolean(productDetails.blindBoxSelected),
        hironoAddons: productDetails.hironoAddons,
        boxDesign: productDetails.boxDesign,
        boxDesignSelected: Boolean(productDetails.boxDesignSelected),
        boxNickname: productDetails.boxNickname,
        boxLetter: productDetails.boxLetter,
        boxDateYmd: productDetails.boxDateYmd,
        rushFee: getCommissionRushFee(),
        clothingColors: collectClothingColors(activeState),
        funko: state.funko,
        hirono: state.hirono,
        chibi: state.chibi
    };
}

function saveCustomizationSnapshot(isCompleted, isConfirmed = false) {
    // habang pinapakita ang ibang figure sa 3D (Final Summary), huwag palitan ang draft
    if (previewLock) {
        return;
    }

    const snapshot = buildCustomizationSnapshot(isCompleted, isConfirmed);
    if (!snapshot) {
        return;
    }

    try {
        sessionStorage.setItem(CUSTOMIZATION_STORAGE_KEY, JSON.stringify(snapshot));
    } catch (error) {
        console.warn("Unable to save customization:", error);
    }

    syncWizardPills();
}

function clearCustomizationSnapshot() {
    try {
        sessionStorage.removeItem(CUSTOMIZATION_STORAGE_KEY);
    } catch (error) {
        console.warn("Unable to clear customization:", error);
    }
}

function restoreCustomizationFromStorage(navMode) {
    const restoreMode = navMode || (isEditPage ? "edit" : isResumePage ? "resume" : "");
    const saved = (() => {
        try {
            const raw = sessionStorage.getItem(CUSTOMIZATION_STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            console.warn("Unable to restore customization:", error);
            return null;
        }
    })();
    if (!saved) {
        return false;
    }

    const selectedCommissionCategory = getCommissionFigureCategoryValue();

    // A new category selected on the commission page must not be replaced
    // by an older customization saved for another category.
    if (selectedCommissionCategory && selectedCommissionCategory !== saved.currentCategory) {
        return false;
    }

    applySavedDetailsAndState(saved);
    state.currentCategory =
        saved.currentCategory ||
        Object.keys(categoryPanels).find(function (category) {
            return saved[category] && saved[category].model;
        }) ||
        null;
    syncItemPricesFromCards();
    syncCustomColorPickers();
    if (!state.currentCategory) {
        return true;
    }

    if (restoreMode === "edit") {
        // Edit: laging sa Figure Details magsisimula, nandoon na lahat ng napili
        navigationSlot = "model";
    } else if (restoreMode === "resume") {
        // Bumalik sa Box/Add-ons kung meron nito ang style (Funko/Hirono), kung wala
        // (Chibi) sa "shoes" - laging ito ang huling design step
        const detailsConfig = getProductDetailsConfig();
        const hasDetailsStep = Boolean(detailsConfig && (detailsConfig.hirono || detailsConfig.boxes?.length));
        navigationSlot = hasDetailsStep ? "productDetails" : "shoes";
    }

    if (state.currentCategory === "hirono") {
        syncHironoGenderOptions();
    }

    // Bumalik mula sa Order Summary / nag-e-edit: kumpleto na ang design,
    // kaya clickable agad ang lahat ng pill (Figure Details, Parts, Box & Add-ons).
    if (restoreMode === "resume" || restoreMode === "edit") {
        wizardMaxStep = 4;
        wizardSigs = null;
    }

    syncSelectedCardsFromState();
    updateSectionVisibility();
    updatePriceDisplay();
    renderProductDetailsPanel();
    void renderCurrentCategory();
    return true;
}

/* =====================================================
   15. PAG-RENDER NG FIGURE
   Pag-load ng body at damit, skin color, at default na sando
   ===================================================== */

async function loadBodyModel(path, token) {
    const requestId = ++loadTokens.body;
    removeFromScene(bodyModel);
    bodyModel = null;
    let gltf = null;
    try {
        gltf = await loadGLTF(path);
    } catch (error) {
        // kung hindi ma-load ang Funko Girl, huwag masira ang buong figure
        if (!/Funko[ _]pop-girl\.glb$|funko girl\.glb$/i.test(path)) {
            throw error;
        }
        console.warn("Funko Girl GLB failed to load, falling back to Funko Pop Default.", error);
    }

    if ((!gltf || !hasRenderableMeshes(gltf)) && /Funko[ _]pop-girl\.glb$|funko girl\.glb$/i.test(path)) {
        console.warn("Funko Girl GLB is empty, falling back to Funko Pop Default.");
        if (gltf) {
            disposeObject3D(gltf);
        }

        gltf = await loadGLTF("dressup-assets/GLB_files/Funko Pop Standee Boy/funko boy.glb");
    }

    if (token !== renderToken) {
        disposeObject3D(gltf);
        return null;
    }

    if (requestId !== loadTokens.body) {
        disposeObject3D(gltf);
        return null;
    }

    bodyModel = gltf;
    bodyModel.name = "BodyModel";
    configureModel(bodyModel);
    scene.add(bodyModel);
    return bodyModel;
}

// Funko Boy: itago ang default na "sando" at "shorts" mesh ng body GLB kapag
// may ibang top/bottom na napili (hindi na "Sando"/"Shorts" ang default card).
function updateDefaultClothesVisibility() {
    // Chibi Girl keychain: may baked na puting sapatos ang body GLB (mesh "shoes").
    // Itago kapag may ibang sapatos na napili para hindi sumilip sa ilalim.
    if (
        bodyModel &&
        state.currentCategory === "chibi" &&
        state.chibi.productType === "keychain" &&
        isGirlModel(state.chibi.model)
    ) {
        const shoes = state.chibi.shoes;
        const showBakedShoes = !shoes || isPlaceholderModelPath(shoes.model);

        // Bagong Chibi Girl Keychain GLB: may baked na "sando" (Tank Top) at "shorts" din.
        // Itago kapag may ibang top / bottom na napili para hindi sumilip sa ilalim ng damit.
        const girlTop = state.chibi.girlTop;
        const girlBottom = state.chibi.girlBottom;
        const showBakedSando = !girlTop || isPlaceholderModelPath(girlTop.model);
        const showBakedShorts = !girlBottom || isPlaceholderModelPath(girlBottom.model);
        bodyModel.traverse(function (node) {
            const nodeName = String(node.name || "").toLowerCase();
            if (nodeName === "shoes") {
                node.visible = showBakedShoes;
            } else if (nodeName.includes("sando")) {
                node.visible = showBakedSando;
            } else if (nodeName.includes("shorts")) {
                node.visible = showBakedShorts;
            }
        });
    }

    if (!bodyModel || state.currentCategory !== "funko") {
        return;
    }

    // Funko Girl: girlTop / girlBottom ang slot (hindi top / bottom ng boy)
    const isGirl = isGirlModel(state.funko.model);
    const top = isGirl ? state.funko.girlTop : state.funko.top;
    const bottom = isGirl ? state.funko.girlBottom : state.funko.bottom;

    // Funko Girl: "Tank Top" ang pangalan ng default card (Sando sa boy) - pareho lang itong
    // default na sando ng body GLB, kaya dapat nakasuot hangga't hindi napapalitan.
    const showSando = !top || (isDefaultSandoName(top.name) && isPlaceholderModelPath(top.model));
    const showShorts = !bottom || (bottom.name === "Shorts" && isPlaceholderModelPath(bottom.model));
    bodyModel.traverse(function (node) {
        const name = String(node.name || "").toLowerCase();
        if (name.includes("sando")) {
            node.visible = showSando;
        } else if (name.includes("shorts")) {
            node.visible = showShorts;
        }
    });
}

async function loadAccessory(slot, path, colorValue, token) {
    const requestId = ++loadTokens[slot];

    // Default / placeholder items (Sando, Shorts, Shoes, Default Hair, etc.)
    // wala pang 3D file - naka-select lang sila, walang ilo-load sa viewer.
    if (isPlaceholderModelPath(path)) {
        removeFromScene(currentObjects[slot]);
        currentObjects[slot] = null;
        updateDefaultClothesVisibility();
        return null;
    }

    // loading: maliit na "Loading Hair…" sa taas, nakikita pa rin ang figure
    startFigureLoading(figurePartLabel(slot));
    let gltf;
    try {
        gltf = await loadGLTF(path);
    } finally {
        endFigureLoading();
    }
    if (token !== renderToken) {
        disposeObject3D(gltf);
        return null;
    }

    if (requestId !== loadTokens[slot]) {
        disposeObject3D(gltf);
        return null;
    }

    gltf.name = `Selected_${slot}`;

    // palitan lang ang lumang item kapag ready na ang bago - hindi nawawala/nagba-blink
    removeFromScene(currentObjects[slot]);
    currentObjects[slot] = null;
    configureModel(gltf);
    scene.add(gltf);
    currentObjects[slot] = gltf;
    updateDefaultClothesVisibility();
    if (colorValue) {
        applyColorToModel(gltf, colorValue, {
            clearTextureMaps: true,
            skipMaterialFragments: ["design", "logo", "print"]
        });
    }

    return gltf;
}

/* =====================================================
   KULAY NG BALAT - pareho sa lahat ng figure (Funko, Hirono, Chibi)
   Walang napili = puti (galing sa GLB). Light / Dark = itong mga kulay.
   ===================================================== */
const SKIN_TONES = { light: "#FFE1CA", dark: "#D19477" };

/* Balat na may "skin mask" sa texture (alpha 0.5 = balat, 1.0 = mata/kilay/sando):
   ang balat lang ang kinukulayan, hindi ang mata. Tanda galing sa GLB: material.extras.skinMask */
function tintSkinMask(material, color) {
    if (!(material.userData.skinTint && material.userData.skinTint.value && material.userData.skinTint.value.isColor)) {
        material.userData.skinTint = { value: new THREE.Color(1, 1, 1) };
        material.onBeforeCompile = function (shader) {
            shader.uniforms.skinTint = material.userData.skinTint;
            shader.fragmentShader = "uniform vec3 skinTint;\n" + shader.fragmentShader.replace(
                "#include <map_fragment>",
                "#include <map_fragment>\n" +
                    "float skinMask = 1.0 - step(0.75, diffuseColor.a);\n" +
                    "diffuseColor.rgb *= mix(vec3(1.0), skinTint, skinMask);\n" +
                    "diffuseColor.a = 1.0;"
            );
        };
        material.customProgramCacheKey = function () {
            return "skin-mask";
        };
        material.needsUpdate = true;
    }
    material.userData.skinTint.value.set(color || "#ffffff");
}

/* ===================== LARAWAN AYON SA KULAY NG BALAT =====================
   Card na may data-skin-image (Product Type, Gender, Hair):
   walang napiling balat = puti (orihinal na larawan), Light = folder "Light/", Dark = folder "Brown/" */
const SKIN_IMAGE_FOLDERS = { light: "Light/", dark: "Brown/" };

function syncSkinImages(category) {
    const skin = category && state[category] ? state[category].skin : null;
    const folder = SKIN_IMAGE_FOLDERS[String((skin && skin.name) || "").toLowerCase()] || "";
    document.querySelectorAll(`[data-figure="${category}"][data-skin-image]`).forEach((card) => {
        const base = card.dataset.skinImage;
        const cut = base.lastIndexOf("/") + 1;
        // Product Type at Gender: yung napili lang ang nakukulayan, puti ang iba
        const bodyCard = /\/Body\//.test(base);
        const tone = bodyCard && !isSelectedBodyCard(card, category) ? "" : folder;
        const src = base.slice(0, cut) + tone + base.slice(cut);
        const img = card.querySelector("img");
        if (img && img.getAttribute("src") !== src) {
            img.setAttribute("src", src);
        }
        if (card.dataset.image) {
            card.dataset.image = src;
        }
    });
}
// napiling Product Type / Gender card (batay sa state, hindi sa CSS class)
function isSelectedBodyCard(card, category) {
    const active = state[category];
    if (card.dataset.slot === "model") {
        return Boolean(active.model && active.model.model === card.dataset.model);
    }
    return card === findProductTypeCard(category);
}
/* ===================== END LARAWAN AYON SA KULAY NG BALAT ===================== */

function skinToneColor(skin) {
    const name = String((skin && skin.name) || "").toLowerCase();
    return SKIN_TONES[name] || (skin && skin.color) || "";
}

function applySkinColor(color) {
    if (!bodyModel) {
        return;
    }

    const isHirono = state.currentCategory === "hirono";

    // Stand (Acrylic_Stand / Clear_Acrylic) must never take the skin color, for every figure.
    const standMaterialFragments = ["stand", "pedestal", "support", "clear_acrylic"];
    const standNodeFragments = ["acrylic_stand", "stand", "pedestal", "support"];

    // Skin Tone ay para sa balat LANG. Buhok, mata, damit at sapatos na kasama sa body GLB
    // (default hair / sando / shorts / shoes) ay hindi dapat makuha ang kulay ng balat -
    // dati kasi lahat ng mesh (maliban sa stand) ay kinukulayan, kaya "lahat" may skin tone.
    const nonSkinFragments = [
        "hair",
        "eye",
        "brow",
        "lash",
        "teeth",
        "tongue",
        "sando",
        "tank",
        "shirt",
        "polo",
        "jacket",
        "hoodie",
        "sweater",
        "coat",
        "dress",
        "short",
        "pant",
        "slack",
        "skirt",
        "sock",
        "shoe",
        "sneaker",
        "boot",
        "sole",
        "croc"
    ];

    // Mga pangalang kadalasang balat. Kapag may nakitang ganito sa body GLB, ITO LANG ang
    // kukulayan (kaya hindi na madadamay ang default hair kahit kakaiba ang pangalan niya).
    const skinNameFragments = [
        "skin",
        "body",
        "face",
        "head",
        "arm",
        "leg",
        "hand",
        "neck",
        "flesh",
        "torso",
        "ear",
        "nose",
        "foot",
        "feet"
    ];
    const has = (text, fragments) => fragments.some((fragment) => text.includes(fragment));
    const candidates = [];
    bodyModel.traverse(function (node) {
        if (!node.isMesh || !node.material) {
            return;
        }

        const materials = Array.isArray(node.material) ? node.material : [node.material];
        const materialText = materials.map((material) => String(material.name || "").toLowerCase()).join(" ");

        // pangalan ng mesh + lahat ng parent group (hindi kasama ang root ng model)
        const chain = [];
        for (let current = node; current && current !== bodyModel; current = current.parent) {
            chain.push(String(current.name || "").toLowerCase());
        }
        const nameText = chain.join(" ");
        const allText = nameText + " " + materialText;
        // stand at singsing ng keychain - hindi balat ("standee figure" / "keychain figure" ay balat)
        const nodeName = String(node.name || "").toLowerCase();
        const isFigureMesh = /figure/.test(nodeName);
        const isStand =
            (!isFigureMesh && has(nodeName + " " + materialText, [...standNodeFragments, ...standMaterialFragments])) ||
            (!isFigureMesh && /key|ring/.test(nodeName));
        const isShoeFoot = isHirono && /foot/.test(allText) && /shoe|sole/.test(allText);
        if (isStand || isShoeFoot || has(allText, nonSkinFragments)) {
            return;
        }

        candidates.push({ node, skinNamed: has(allText, skinNameFragments), label: allText });
    });
    const skinNamed = candidates.filter((item) => item.skinNamed);
    const targets = (skinNamed.length ? skinNamed : candidates).map((item) => item.node);
    console.info(
        "[DressUp] Skin tone applied to:",
        targets.map((node) => node.name || "(no name)"),
        "| not tinted:",
        [].concat(
            candidates.filter((item) => !targets.includes(item.node)).map((item) => item.node.name || "(no name)")
        )
    );
    // may skin mask (Hirono): balat lang, hindi ang mata
    const maskTargets = targets.filter((node) => !Array.isArray(node.material) && node.material.userData.skinMask);
    maskTargets.forEach((node) => tintSkinMask(node.material, color));

    applyColorToMeshList(
        targets.filter((node) => !maskTargets.includes(node)),
        color,
        {
            clearTextureMaps: false,
            skipMaterialFragments: [...standMaterialFragments, ...nonSkinFragments]
        }
    );
}

// Funko: top/topColor, Hirono: outfit/outfitColor. Ang default Sando ay
// part ng body GLB kaya dito lang nagbabago ang kulay niya, hindi sa skin.
const sandoSlotsByCategory = {
    // Funko Boy: top/topColor, Funko Girl: girlTop/girlTopColor
    funko: [
        { top: "top", color: "topColor" },
        { top: "girlTop", color: "girlTopColor" }
    ],
    hirono: [{ top: "outfit", color: "outfitColor" }]
};

// Pangalan ng default na top card: "Sando" (boy / hirono) o "Tank Top" (Funko Girl)
function isDefaultSandoName(name) {
    return name === "Sando" || name === "Tank Top";
}

function isSandoColorSlot(category, slot) {
    const list = sandoSlotsByCategory[category];
    return Boolean(list && list.some((slots) => slot === slots.top || slot === slots.color));
}

function isDefaultSandoSelected() {
    const category = state.currentCategory;
    const list = sandoSlotsByCategory[category];
    if (!list || !state[category]) {
        return false;
    }

    const isGirl = category === "funko" && isGirlModel(state.funko.model);
    const slots = category === "funko" ? list[isGirl ? 1 : 0] : list[0];
    const top = state[category][slots.top];
    return Boolean(top && isDefaultSandoName(top.name) && isPlaceholderModelPath(top.model));
}

function applySandoColor(color) {
    if (!bodyModel || !isDefaultSandoSelected() || !color) {
        return;
    }

    const sandoMeshes = [];
    bodyModel.traverse(function (node) {
        if (
            node.isMesh &&
            String(node.name || "")
                .toLowerCase()
                .includes("sando")
        ) {
            sandoMeshes.push(node);
        }
    });
    applyColorToMeshList(sandoMeshes, color, {
        clearTextureMaps: true,
        skipMaterialFragments: ["design", "logo", "print"]
    });
}

function getRenderBodyKey() {
    const category = state.currentCategory;
    const categoryState = category ? state[category] : null;
    if (!categoryState || !categoryState.model) {
        return null;
    }

    const path = categoryState.model.model;
    if (!path) {
        return null;
    }

    // Funko: ang skin ay direktang ina-apply sa body (walang reload), kaya hindi kasama sa key.
    // Hirono / Chibi: nire-reload ang body kapag nagbago ang skin.
    const skinPart =
        category === "funko"
            ? ""
            : categoryState.skin
              ? `${categoryState.skin.name}|${categoryState.skin.color}`
              : "none";
    return `${category}|${path}|${skinPart}`;
}

async function renderCurrentCategory() {
    const activeCategory = state.currentCategory;
    const token = ++renderToken;

    // Kung pareho pa rin ang body (top / bottom / shoes / hair lang ang nagbago),
    // huwag nang burahin at i-load ulit ang buong figure.
    const bodyKey = getRenderBodyKey();
    const keepBody = Boolean(bodyKey && bodyKey === renderedBodyKey && bodyModel);
    if (!keepBody) {
        clearCurrentScene();
    }

    if (!activeCategory) {
        setFigurePromptVisible(true);
        updateSectionVisibility();
            updatePriceDisplay();
        return;
    }

    const activeState = state[activeCategory];
    if (!activeState.model) {
        setFigurePromptVisible(true);
        updateSectionVisibility();
            updatePriceDisplay();
        return;
    }

    if (!keepBody) {
        setFigurePromptVisible(true);
    }

    try {
        // Walang hiwalay na Light / Dark na katawan: puti ang balat sa texture ng Chibi Girl (standee at keychain), tint ang skin
        const bodyPath = activeState.model.model;
        if (!bodyPath) {
            return;
        }

        if (!keepBody) {
            startFigureLoading("");
            try {
                await loadBodyModel(bodyPath, token);
            } finally {
                endFigureLoading();
            }
            if (token !== renderToken) {
                return;
            }

            renderedBodyKey = bodyKey;

            // Frame the base figure first so the preview stays visible even if
            // an optional accessory asset cannot be loaded.
            frameFigure();
            setFigurePromptVisible(false);

            // Balat: PUTI ang lahat ng figure (pareho ng Funko Pop Boy). Light / Dark = parehong kulay sa lahat.
            if (activeState.skin) {
                applySkinColor(skinToneColor(activeState.skin));
            }
        }

        const order = getActiveSlotOrder();

        // alisin ang mga naiwang item na wala na sa napili
        Object.keys(currentObjects).forEach(function (slot) {
            if (currentObjects[slot] && (!order.includes(slot) || !activeState[slot])) {
                removeFromScene(currentObjects[slot]);
                currentObjects[slot] = null;
                delete renderedSlotSig[slot];
            }
        });
        for (const slot of order) {
            const item = activeState[slot];
            // model / skin / kulay ay hindi hiwalay na 3D file (body na iyon) - huwag i-download ulit
            if (!item || !ACCESSORY_SLOTS.includes(slot)) {
                continue;
            }

            const colorSlot = getSlotColorSlot(slot);
            let colorItem = colorSlot ? activeState[colorSlot] : null;

            // coat / may design / pattern - walang custom color
            if (colorItem && !isItemColorable(slot, item.name)) {
                colorItem = null;
            }

            const accessoryColor =
                slot === "bottom" && activeCategory === "funko" ? null : colorItem ? colorItem.color : null;
            const isFunkoBottom = slot === "bottom" && activeCategory === "funko";
            const slotSig = `${item.model}|${accessoryColor || ""}|${isFunkoBottom && activeState.pantsColor ? activeState.pantsColor.color : ""}`;

            // walang nagbago sa item na ito - iwanan sa scene, huwag i-reload
            if (
                keepBody &&
                !isPlaceholderModelPath(item.model) &&
                currentObjects[slot] &&
                renderedSlotSig[slot] === slotSig
            ) {
                continue;
            }

            try {
                await loadAccessory(slot, item.model, accessoryColor, token);
                if (token === renderToken) {
                    renderedSlotSig[slot] = isPlaceholderModelPath(item.model) ? null : slotSig;
                }

                if (isSandoColorSlot(activeCategory, slot) && colorItem) {
                    applySandoColor(colorItem.color);
                }
            } catch (error) {
                console.warn(`Unable to load ${slot} accessory in the preview:`, error);

                // hindi na-load ang bago: huwag iwan ang lumang item na hindi na napili
                removeFromScene(currentObjects[slot]);
                currentObjects[slot] = null;
                delete renderedSlotSig[slot];
            }

            if (token !== renderToken) {
                return;
            }

            if (activeCategory === "funko" && slot === "bottom") {
                applyBottomPartColors(currentObjects.bottom, activeState.pantsColor && activeState.pantsColor.color);
            }
        }

        updateDefaultClothesVisibility();
        if (!keepBody) {
            frameFigure();
        }
    } catch (error) {
        console.error("Failed to render figure:", error);
        setFigurePromptVisible(true);
    }

    updateSectionVisibility();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
}

/* =====================================================
   16. DESIGN NAVIGATION
   Dropdown ng steps at Back / Next
   ===================================================== */

function getDesignStepLabel(slot) {
    if (slot === "hair" || slot === "girlHair") {
        return "Hair";
    }

    if (slot === "keychainHair") {
        return "Hirono Keychain Hair";
    }

    const labels = {
        productType: "Product Type",
        mode: "Product Type",
        // Gender/Model and Skin Tone are shown on the same page, so the
        // jump dropdown lists them as a single combined entry.
        model: "Gender & Skin Tone",
        skin: "Gender & Skin Tone",
        size: "Figure Details",
        top: "Top",
        girlTop: "Top",
        outfit: "Top",
        bottom: "Bottom",
        girlBottom: "Bottom",
        pants: "Bottom",
        shoes: "Shoes",
        keychainHat: "Hat"
    };
    return labels[slot] || slot.replace(/([A-Z])/g, " $1").replace(/^./, (character) => character.toUpperCase());
}

function isElementShown(element) {
    return Boolean(element) && !element.hidden;
}

// balik sa itaas ang scroll ng Figure Details tuwing lilipat ng step
function resetDressUpScroll() {
    const scrollArea = document.getElementById("dressUpRightScroll");
    if (scrollArea) {
        scrollArea.scrollTop = 0;
    }
}

function renderDesignNavigation(sequence, sections, activeState, currentSlot, pairedSlotInput) {
    const pairedSlots = Array.isArray(pairedSlotInput) ? pairedSlotInput : pairedSlotInput ? [pairedSlotInput] : [];
    // Gender / Skin / Size: iisang step, kaya hindi sila hiwalay na entry/step
    // (Figure Style, Product Type, Size, Gender, Skin = iisang "Figure Details" step)
    const leadingGroup = [];
    for (const slot of sequence) {
        if (!FIRST_GROUP_SLOTS.includes(slot)) {
            break;
        }
        leadingGroup.push(slot);
    }
    const mergedWithModel = leadingGroup.slice(1);
    const navigation = document.getElementById("designStepNavigation");
    const previousButton = document.getElementById("previousDesignStep");
    const nextButton = document.getElementById("nextDesignStep");
    const detailsStep = navigationSlot === "productDetails";
    if (!navigation || !previousButton || !nextButton || !sequence?.length || !currentSlot) {
        if (navigation) {
            navigation.hidden = true;
        }
        return;
    }

    const currentIndex = Math.max(0, sequence.indexOf(currentSlot));
    const isLastDesignStep = currentIndex >= sequence.length - 1;
    navigation.hidden = false;
    previousButton.disabled = false;

    // Huling design step (hal. Shoes ng Chibi): diretso sa Order Summary kung walang
    // Box/Add-ons. Kung meron (Funko/Hirono), doon muna bago ang Order Summary.
    const detailsConfig = getProductDetailsConfig();
    const hasDetailsStep = Boolean(detailsConfig && (detailsConfig.hirono || detailsConfig.boxes?.length));

    // Itago ang Previous/Next pag nasa Box/Add-ons (detailsStep) na - continueBtn na ang "Next"
    nextButton.hidden = detailsStep;
    nextButton.textContent =
        dressUpViewing && !hasDetailsStep && isLastDesignStep ? "Done" : "Next";
    previousButton.hidden = false; // Step 1: Back = balik sa Commission
    const canSkipCurrentSlot = !["model", "skin", "productType", "mode"].includes(currentSlot);

    // Size tracked separately (productDetails.size), hindi sa activeState - kaya sariling check
    const sizeStepConfig = currentSlot === "size" || pairedSlots.includes("size") ? getProductDetailsConfig() : null;
    const sizeStepRequired =
        (currentSlot === "size" || pairedSlots.includes("size")) && Boolean(sizeStepConfig?.sizes?.length);
    const sizeStepSatisfied = !sizeStepRequired || Boolean(productDetails.sizeSelected);
    // When a slot is shown paired with another one (e.g. Gender shown
    // together with Skin Tone), both must be selected before the user
    // can move on - picking only one of the two should not unlock Next.
    // Next = laging puwedeng pindutin; kapag may kulang (hal. Size, Gender, Skin Tone),
    // pop up + iiscroll at ihi-highlight ang kulang (kapareho ng Image Submission)
    const missingSlot = [
        ...(canSkipCurrentSlot ? [] : [currentSlot]),
        ...(sizeStepRequired ? ["size"] : []),
        ...pairedSlots.filter((slot) => slot !== "size")
    ].find((slot) => (slot === "size" ? !sizeStepSatisfied : !activeState[slot]));
    nextButton.disabled = detailsStep;
    previousButton.onclick = () => {
        if (detailsStep) {
            navigationSlot = sequence[sequence.length - 1];
            updateSectionVisibility();
            resetDressUpScroll();
            return;
        }

        if (currentIndex <= 0 || onFirstStep) {
            window.location.href = "commission.php";
            return;
        }

        let previousIndex = currentIndex - 1;

        // Gender at Skin Tone = iisang step. Kapag nasa Skin na ang slot, ituring pa ring
        // Gender step para ang Back ay diretsong bumalik sa Figure Style / Size.
        if (currentSlot === "skin" && sequence.indexOf("model") !== -1) {
            previousIndex = sequence.indexOf("model") - 1;
        }

        while (previousIndex > 0 && mergedWithModel.includes(sequence[previousIndex])) {
            previousIndex--;
        }

        navigationSlot = sequence[previousIndex];
        updateSectionVisibility();
        resetDressUpScroll();
    };
    nextButton.onclick = async () => {
        if (missingSlot) {
            showMissingSlot(missingSlot);
            return;
        }

        if (isLastDesignStep) {
            // nag-e-edit ng naka-save na figure at walang Custom Box (Chibi) - i-save na
            if (dressUpViewing && !hasDetailsStep) {
                saveEditedCartFigure();
                return;
            }

            if (!dressUpViewing) {
                saveCustomizationSnapshot(true, false);
            }

            // May Box/Add-ons pa (Funko/Hirono) - doon muna. Kung wala (Chibi),
            // tapos na ang design - buksan na ang Order Summary.
            if (!hasDetailsStep) {
                openOrderSummary();
                return;
            }

            navigationSlot = "productDetails";
            updateSectionVisibility();
            resetDressUpScroll();
            return;
        }

        let nextIndex = currentIndex + 1;
        if (currentIndex === 0) {
            while (nextIndex < sequence.length - 1 && mergedWithModel.includes(sequence[nextIndex])) {
                nextIndex++;
            }
        }

        // Gender at Skin Tone = iisang step: mula sa Gender, laktawan ang Skin at dumiretso sa Hair
        if (currentSlot === "model" && sequence[nextIndex] === "skin" && nextIndex < sequence.length - 1) {
            nextIndex++;
        }

        navigationSlot = sequence[nextIndex];
        updateSectionVisibility();
        resetDressUpScroll();
    };
}

/* =====================================================
   17. PAGPILI NG CARD
   selectCategory, selectModel, selectSkin, selectColor, handleCardClick
   ===================================================== */

function resetFigureStyleSelection() {
    clearSelectedClasses('[data-tab="figure"]');
}

function selectCategory(category) {
    if (!categoryPanels[category]) {
        return;
    }

    pickedSlots.clear();

    // bagong Figure Style = bagong flow, ulit lahat ng step
    wizardMaxStep = 1;
    wizardSigs = null;
    try {
        sessionStorage.setItem(COMMISSION_FIGURE_CATEGORY_KEY, category);
    } catch (error) {
        console.warn("Unable to persist figure category:", error);
    }

    // Choosing a style starts that category's flow from the beginning.
    // This prevents an old model selection from skipping the required type
    // choice (Funko boy/girl, Hirono standee/keychain, or Chibi product type).
    // lahat ng figure style nire-reset (hindi lang ang bagong pili),
    // para walang naiwang size/model/skin/kulay galing sa dating style.
    ["funko", "hirono", "chibi"].forEach((category) => clearCategoryState(category)); // walang ibang argument: hindi naiiwan ang Product Type
    clearSelectedClasses("#dressUpModule [data-slot]");
    productDetails = emptyProductDetails();
    [boxNameInput, boxNumberInput, boxColorInput].forEach((input) => {
        if (input) {
            input.value = "";
        }
    });
    if (sizeChoiceGrid) {
        sizeChoiceGrid.innerHTML = "";
    }

    clearCurrentScene();
    state.currentCategory = category;
    navigationSlot = null;
    syncCustomColorPickers();
    styleCards.forEach(function (card) {
        card.classList.toggle("selected", card.dataset.figure === category);
    });
    updateSectionVisibility();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
    void renderCurrentCategory();
}

function selectModel(card) {
    const category = card.dataset.figure;
    const stateForCategory = state[category];
    const item = buildItemFromCard(card);

    if (
        stateForCategory.model &&
        stateForCategory.model.model === item.model &&
        stateForCategory.model.name === item.name
    ) {
        return;
    }

    // bagong Gender = bagong set ng damit; ang Product Type (Standee / Keychain) ay naiiwan
    clearCategoryState(category, true);

    if (category === "chibi") {
        stateForCategory.productType = card.dataset.productType || "standee";
    }

    stateForCategory.model = item;
    markSelectedCard(card);
    state.currentCategory = category;
    navigationSlot = "model";
    if (category === "hirono") {
        syncHironoGenderOptions();
    }

    applyDefaultSelections(category);
    syncCustomColorPickers();
    updateSectionVisibility();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
    void renderCurrentCategory();
}

// card ng kasalukuyang Product Type (Chibi / Hirono)
function findProductTypeCard(category) {
    if (category === "chibi" && state.chibi.productType) {
        return document.querySelector(`[data-figure="chibi"][data-slot="chibiType"][data-product-type="${state.chibi.productType}"]`);
    }
    if (category === "hirono" && state.hirono.mode) {
        const mode = state.hirono.mode === "headKeychain" ? "keychain" : state.hirono.mode;
        return document.querySelector(`[data-figure="hirono"][data-slot="hironoType"][data-mode="${mode}"]`);
    }
    return null;
}

/* Pinalitan ang Product Type (Standee <-> Keychain) habang may napili nang Gender:
   katawan (figure) lang ang papalitan - pareho pa rin ang Gender, kulay ng balat,
   buhok at damit (kung meron din sa bagong Product Type). Ibang Gender = bagong set (selectModel). */
function switchProductTypeKeepDesign(category, applyType, cardMatchesType) {
    const current = state[category];
    if (!current.model) {
        return false;
    }

    const wantGirl = isGirlModel(current.model);
    const newModelCard = Array.from(document.querySelectorAll(`[data-figure="${category}"][data-slot="model"]`)).find(
        (card) => cardMatchesType(card) && isGirlModel(buildItemFromCard(card)) === wantGirl
    );
    if (!newModelCard) {
        return false;
    }

    const previous = Object.assign({}, current);
    clearCategoryState(category);
    applyType();
    current.model = buildItemFromCard(newModelCard);
    current.skin = previous.skin;

    // iwan ang buhok / damit na meron din sa bagong Product Type
    const order = getActiveSlotOrder();
    order.forEach(function (slot) {
        const item = previous[slot];
        if (!item || slot === "model" || slot === "skin" || slot.endsWith("Color")) {
            return;
        }
        if (isPlaceholderModelPath(item.model) || slotCardExists(category, slot, item.model)) {
            current[slot] = item;
        }
    });
    // kulay ng parte: iwan lang kung naiwan din ang parte
    order.forEach(function (slot) {
        if (slot.endsWith("Color") && previous[slot] && current[getColorAccessorySlot(category, slot)]) {
            current[slot] = previous[slot];
        }
    });

    applyDefaultSelections(category);
    syncSelectedCardsFromState();
    return true;
}

// may card ba ang parteng ito sa kasalukuyang Product Type?
// (Chibi keychain: sariling sections na may "Keychain" sa id)
function slotCardExists(category, slot, model) {
    const cards = Array.from(
        document.querySelectorAll(`[data-figure="${category}"][data-slot="${slot}"][data-model="${escapeSelectorValue(model)}"]`)
    );
    if (category !== "chibi") {
        return cards.length > 0;
    }
    const wantKeychain = state.chibi.productType === "keychain";
    return cards.some(function (card) {
        const section = card.closest('[id$="Section"]');
        return /Keychain/.test(section ? section.id : "") === wantKeychain;
    });
}

function selectChibiType(card) {
    const type = card.dataset.productType || "standee";
    state.currentCategory = "chibi";
    const kept = switchProductTypeKeepDesign(
        "chibi",
        () => { state.chibi.productType = type; },
        (modelCard) => (modelCard.dataset.productType || "standee") === type
    );
    if (!kept) {
        clearCategoryState("chibi");
        state.chibi.productType = type;
    }
    // Stay on the Product Type step after picking it - advancing to
    // Gender only happens when the required "Next" button is pressed.
    navigationSlot = "productType";
    syncChibiModelOptions();
    markSelectedCard(card);
    updateSectionVisibility();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
    if (kept) {
        // bagong katawan, parehong damit
        void renderCurrentCategory();
    }
}

// Chibi: ipakita lang ang Gender cards ng napiling Product Type (Standee o
// Keychain). Tinatawag din kapag ibinabalik ang design (Back mula sa Final
// Summary), para hindi dumoble ang Gender (Boy/Girl + Keychain Boy/Girl).
function syncChibiModelOptions() {
    const type = state.chibi.productType === "keychain" ? "keychain" : "standee";
    document.querySelectorAll('[data-figure="chibi"][data-slot="model"]').forEach((modelCard) => {
        modelCard.hidden = (modelCard.dataset.productType || "standee") !== type;
    });
}

function selectHironoType(card) {
    const mode = card.dataset.mode || "standee";
    state.currentCategory = "hirono";
    const kept = switchProductTypeKeepDesign(
        "hirono",
        () => { state.hirono.mode = mode; },
        (modelCard) => (modelCard.dataset.mode || "standee") === mode
    );
    if (!kept) {
        clearCategoryState("hirono");
        state.hirono.mode = mode;
    }
    // Stay on the Product Type step after picking it - advancing to
    // Gender only happens when the required "Next" button is pressed.
    navigationSlot = "mode";
    syncHironoGenderOptions();
    markSelectedCard(card);
    updateSectionVisibility();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
    if (kept) {
        // bagong katawan, parehong damit
        void renderCurrentCategory();
    }
}

// Hirono (galing sa DressUp): magkaiba ang body ng Boy/Girl at ng Standee/Keychain.
// Ipakita lang ang Gender cards ng napiling Product Type, at ang hair / top /
// bottom / shoes na para sa napiling gender (data-hirono-gender).
function syncHironoGenderOptions() {
    const cardMode = getHironoMode() === "keychain" ? "keychain" : "standee";
    const model = state.hirono.model;
    const selectedGender = model ? (/girl/i.test(model.name || "") ? "girl" : "boy") : null;
    document.querySelectorAll('[data-figure="hirono"][data-slot="model"]').forEach((card) => {
        card.hidden = (card.dataset.mode || "standee") !== cardMode;
    });
    document.querySelectorAll('[data-figure="hirono"][data-hirono-gender]').forEach((card) => {
        card.hidden = Boolean(selectedGender) && card.dataset.hironoGender !== selectedGender;
    });
}

function selectSkin(card) {
    const category = card.dataset.figure;
    const stateForCategory = state[category];
    const item = buildItemFromCard(card);
    if (stateForCategory.skin && stateForCategory.skin.color === item.color) {
        return;
    }

    stateForCategory.skin = item;
    markSelectedCard(card);
    state.currentCategory = category;
    navigationSlot = "skin";
    // kulayan agad ang balat (hindi na ilo-load ulit ang buong figure)
    applySkinColor(skinToneColor(item));

    updateSectionVisibility();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
}

async function selectAccessory(card) {
    const category = card.dataset.figure;
    const slot = card.dataset.slot;
    navigationSlot = slot;
    const item = buildItemFromCard(card);
    const stateForCategory = state[category];
    const currentItem = stateForCategory[slot];
    if (currentItem && currentItem.model === item.model && currentObjects[slot]) {
        if (slot === "bottom" && category === "funko") {
            applyBottomPartColors(
                currentObjects.bottom,
                stateForCategory.pantsColor && stateForCategory.pantsColor.color
            );
        } else if (item.color) {
            const colorSlot = getSlotColorSlot(slot);
            const colorItem = colorSlot ? stateForCategory[colorSlot] : null;
            if (colorItem) {
                applyColorToModel(currentObjects[slot], colorItem.color, {
                    clearTextureMaps: true,
                    skipMaterialFragments: ["design", "logo", "print"]
                });
            }
        }

        return;
    }

    stateForCategory[slot] = item;
    state.currentCategory = category;
    markSelectedCard(card);
    const colorSlot = getSlotColorSlot(slot);

    // pumili ng damit na hindi pwedeng kulayan → burahin ang naunang custom color
    if (colorSlot && stateForCategory[colorSlot] && !isItemColorable(slot, item.name)) {
        delete stateForCategory[colorSlot];
    }

    const colorItem = colorSlot ? stateForCategory[colorSlot] : null;
    try {
        const loaded = await loadAccessory(slot, item.model, colorItem ? colorItem.color : null, renderToken);
        if (isSandoColorSlot(category, slot) && colorItem) {
            applySandoColor(colorItem.color);
        }

        if (loaded) {
            frameFigure();
        }
    } catch (error) {
        console.error(`Failed to load ${slot}:`, error);
    }

    updateSectionVisibility();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
}

function selectColor(card) {
    const category = card.dataset.figure;
    const slot = card.dataset.slot;
    const item = buildItemFromCard(card);
    const stateForCategory = state[category];
    const currentItem = stateForCategory[slot];
    if (currentItem && currentItem.color === item.color) {
        return;
    }

    stateForCategory[slot] = item;
    markSelectedCard(card);
    state.currentCategory = category;
    navigationSlot = slot;
    let accessorySlot = slot.replace("Color", "");
    if (category === "funko" && slot === "pantsColor") {
        accessorySlot = "bottom";
    } else if (category === "funko" && slot === "bottomColor" && isGirlModel(stateForCategory.model)) {
        accessorySlot = "girlBottom";
    }

    const currentObject = getLoadedObject(accessorySlot);
    if (currentObject) {
        if (category === "funko" && (accessorySlot === "bottom" || slot === "bottomColor")) {
            applyBottomPartColors(currentObject, item.color);
        } else {
            applyColorToModel(currentObject, item.color, {
                clearTextureMaps: true,
                skipMaterialFragments: ["design", "logo", "print"]
            });
        }
    } else if (isSandoColorSlot(category, slot)) {
        applySandoColor(item.color);
    }

    updateSectionVisibility();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
}

function handleCardClick(card) {
    if (card.dataset.slot) {
        pickedSlots.add(card.dataset.slot);
    }

    navigationSlot = null;
    if (card.dataset.tab === "figure") {
        // Only mark the style as chosen here. The actual switch into
        // that category's flow happens when the required "Next" button
        // in the Figure Style section is pressed, so clicking a card
        // never skips straight ahead on its own.
        const styleCategory = card.dataset.figure;

        // Lahat ng figure (Funko / Hirono / Chibi): pagpili ng Figure Style ay
        // diretso na sa iisang "Figure Details" screen (Style, Type, Size, Gender/Skin).
        if (state.currentCategory === styleCategory) {
            return;
        }

        selectCategory(styleCategory);
        return;
    }

    const slot = card.dataset.slot;
    if (slot === "chibiType") {
        selectChibiType(card);
        return;
    }

    if (slot === "hironoType") {
        selectHironoType(card);
        return;
    }

    if (slot === "model") {
        selectModel(card);
        return;
    }

    if (slot === "skin") {
        selectSkin(card);
        return;
    }

    if (slot.endsWith("Color")) {
        selectColor(card);
        return;
    }

    void selectAccessory(card);
}

/* =====================================================
   18. CART AT SUBMIT NG ORDER
   Mga naunang figure, pag-edit, at pagpapadala ng order sa submit_order.php
   ===================================================== */

// DRESS UP ORDER SUBMISSION (Order Summary -> submit_order.php -> payment)

const DRESSUP_SLOT_LABELS = {
    hair: "Hair",
    girlHair: "Hair",
    keychainHair: "Hair",
    top: "Top",
    girlTop: "Top",
    outfit: "Top",
    bottom: "Bottom",
    girlBottom: "Bottom",
    pants: "Bottom",
    shoes: "Shoes",
    keychainHat: "Hat"
};

// targetId (optional) = field na kulang: iiscroll at ihi-highlight para hindi na hanapin ng customer
function showDressUpNotice(message, type, targetId) {
    // shared pop up (Shared/popup-and-security.php) - iisang itsura sa buong system
    window.csToast(message, type || "error");

    const field = targetId ? document.getElementById(targetId) : null;
    if (!field) {
        return;
    }

    field.scrollIntoView({ behavior: "smooth", block: "center" });
    field.classList.add("field-missing");
    setTimeout(function () {
        field.classList.remove("field-missing");
    }, 3500);
}

// pop up kung ano ang kulang sa step + highlight ng section nito (Size, Gender, Skin Tone, atbp.)
function showMissingSlot(slot) {
    const labels = { size: "Size", model: "Gender", skin: "Skin Tone", productType: "Product Type", mode: "Product Type" };
    const card = slot === "size"
        ? null
        : [...document.querySelectorAll(`#dressUpRightScroll [data-slot="${slot}"]`)].find((element) => element.offsetParent !== null);
    const section = slot === "size" ? document.getElementById("figureSizeSection") : card && card.closest("[id]");
    showDressUpNotice("Please choose: " + (labels[slot] || getDesignStepLabel(slot)), "error", section ? section.id : "");
}

function getDressUpProductTypeLabel() {
    if (state.currentCategory === "hirono") {
        const mode = getHironoMode();
        if (mode === "headKeychain") {
            return "Head Only Keychain";
        }

        return mode === "keychain" ? "Full Body Keychain" : "Full Body Standee";
    }

    if (state.currentCategory === "chibi") {
        return state.chibi.productType === "keychain" ? "Full Body Keychain" : "Full Body Standee";
    }

    return "Full Body Standee";
}

function getDressUpGenderLabel(activeState) {
    // Boy / Girl (hindi ang pangalan ng model gaya ng "Funko Pop Default")
    if (!activeState || !activeState.model) {
        return "";
    }

    if (state.currentCategory === "funko" || state.currentCategory === "chibi") {
        return isGirlModel(activeState.model) ? "Girl" : "Boy";
    }

    return /girl/i.test(activeState.model.name || "") ? "Girl" : "Boy";
}

function cloneDressUpItem(item) {
    if (!item) {
        return null;
    }

    return {
        name: item.name || "",
        model: item.model || "",
        color: item.color || "",
        price: Number(item.price || 0),
        billable: item.billable !== false
    };
}

// buong design na kailangan para maipakita ulit ang 3D figure sa My Orders /
// staff / owner pages (tingnan ang Shared/dressup-viewer.js). Kapareho ng
// pagkakasunod at kulay na ginagamit ng renderCurrentCategory.
function buildDressUpDesignData(activeState) {
    const category = state.currentCategory;
    const slots = [];
    getActiveSlotOrder().forEach((slot) => {
        if (slot === "model" || slot === "skin" || slot.toLowerCase().endsWith("color")) {
            return;
        }

        const item = activeState[slot];
        if (!item) {
            return;
        }

        const isFunkoBottom = category === "funko" && slot === "bottom";
        let colorSlot = isFunkoBottom ? "pantsColor" : getSlotColorSlot(slot);

        // kung walang kulay sa inaasahang key (hal. shoesColor ng Hirono, o
        // hairColor na ginagamit din ng Funko Girl), hanapin ang color slot
        // na tumuturo sa accessory na ito
        if (!colorSlot || !activeState[colorSlot]) {
            const matchingColorSlot = Object.keys(activeState).find(
                (key) =>
                    key.endsWith("Color") &&
                    activeState[key] &&
                    activeState[key].color &&
                    getColorAccessorySlot(category, key) === slot
            );
            if (matchingColorSlot) {
                colorSlot = matchingColorSlot;
            }
        }

        const colorItem = colorSlot ? activeState[colorSlot] : null;
        const pricedItem = cloneDressUpItem(item);
        pricedItem.price = getItemPrice(item);
        pricedItem.billable = pricedItem.price > 0;
        slots.push({
            slot,
            label: DRESSUP_SLOT_LABELS[slot] || getDesignStepLabel(slot),
            item: pricedItem,
            // larawan ng card (para makita ng owner/staff at sa My Orders ang mismong napili)
            image: getItemImage(category, slot, item.model),
            colorName: colorItem ? colorItem.name || "" : "",
            colorValue: colorItem ? colorItem.color || "" : "",
            isBottom: isFunkoBottom
        });
    });
    return {
        category,
        productKey: getProductDetailsKey(),
        productType: getDressUpProductTypeLabel(),
        genderLabel: getDressUpGenderLabel(activeState),
        model: cloneDressUpItem(activeState.model),
        skin: cloneDressUpItem(activeState.skin),
        slots,
        size: productDetails.size || "",
        sizePrice: getProductSizePrice(),
        nameFee: 0, // walang Figure Name sa Dress Up
        designTotal: getActiveStateTotal(activeState)
    };
}

// box / add-ons -> parehong format na tinatanggap ng submit_order.php
// (kapareho ng Image Submission), para gumana agad ang "Custom Box" card
function buildDressUpBoxDetails() {
    const config = getProductDetailsConfig();
    if (!config) {
        return { boxDetails: null, boxAddonPrice: 0 };
    }

    if (config.hirono) {
        if (!productDetails.blindBoxSelected) {
            return { boxDetails: null, boxAddonPrice: 0 };
        }

        const blindBox = getSelectedBlindBox();
        const blindItems = {};
        getSelectedHironoAddons().forEach((addon) => {
            blindItems[addon.key] = addon.price;
        });
        const dateParts = formatMonthDay(productDetails.boxDateYmd).split("/");
        return {
            boxDetails: {
                addonType: "hirono_blind_box",
                blindType: blindBox.id,
                boxDesign: productDetails.boxDesignSelected ? productDetails.boxDesign || "" : "",
                boxColor: productDetails.boxColor || "",
                letter: productDetails.boxLetter || "",
                nickname: productDetails.boxNickname || "",
                dateMonth: dateParts.length === 2 ? dateParts[0] : "",
                dateDay: dateParts.length === 2 ? dateParts[1] : "",
                blindItems
            },
            boxAddonPrice: getProductDetailsAddon()
        };
    }

    const selectedBox = (config.boxes || []).find((box) => box.id === productDetails.box);
    if (!selectedBox) {
        return { boxDetails: null, boxAddonPrice: 0 };
    }

    return {
        boxDetails: {
            addonType: "funko_box",
            boxType: selectedBox.id,
            boxName: productDetails.boxName || "",
            boxNumber: productDetails.boxNumber || "",
            boxColor: productDetails.boxColor || ""
        },
        boxAddonPrice: selectedBox.price
    };
}

let dressUpOrderSubmitting = false;

// DRESS UP "ADD ANOTHER FIGURE" - naka-save sa sessionStorage ang mga naunang
// figure ng parehong order (kapareho ng "+ Add Another Order" ng Image
// Submission). Isang order lang ang gagawin sa dulo, lahat ng figure kasama.

// Reset = balik sa pinakaunang step (Figure Style) at walang kahit anong naka-select.
function resetDressUpDesign() {
    // Reset = balik sa pinakaunang step (Figure Style) at
    // walang kahit anong naka-select.
    wizardMaxStep = 1;
    wizardSigs = null;
    state.currentCategory = null;
    navigationSlot = null;
    state.funko = createCategoryState();
    state.hirono = createCategoryState();
    state.chibi = createCategoryState();
    productDetails = emptyProductDetails();
    [boxNameInput, boxNumberInput, boxColorInput].forEach((input) => {
        if (input) {
            input.value = "";
        }
    });
    clearCurrentScene();
    clearSelectedClasses("#dressUpModule [data-slot]");
    resetFigureStyleSelection();
    try {
        sessionStorage.removeItem(COMMISSION_FIGURE_CATEGORY_KEY);
    } catch (error) {
        // ignore
    }

    updateSectionVisibility();
    updatePriceDisplay();
    renderProductDetailsPanel();
    setFigurePromptVisible(true);
    resetDressUpScroll();
    clearCustomizationSnapshot();
}

const DRESSUP_CART_KEY = "figurifyDressUpCart";
const DRESSUP_CART_MAX = 10;   // hanggang 10 piraso (kasama ang qty) sa isang Dress Up order

// qty ng kasalukuyang figure (wala pa sa cart); ang naka-save na figure ay may sariling item.quantity
const DRESSUP_QTY_KEY = "figurifyDressUpQty";

function getCurrentFigureQty() {
    try {
        return Math.max(1, Number(sessionStorage.getItem(DRESSUP_QTY_KEY)) || 1);
    } catch (error) {
        return 1;
    }
}

function setCurrentFigureQty(quantity) {
    try {
        sessionStorage.setItem(DRESSUP_QTY_KEY, String(quantity));
    } catch (error) {
        // ignore
    }
}

function getCartItemQty(item) {
    return Math.max(1, Number(item.quantity) || 1);
}

// lahat ng piraso sa order (cart + kasalukuyang figure)
function getDressUpPieceCount() {
    return getDressUpCart().reduce((sum, item) => sum + getCartItemQty(item), 0) + getCurrentFigureQty();
}

// Edit ng naka-save na figure (galing sa Final Summary → dress-up.php?figure=N)
// null = bagong figure ang ginagawa; { index, mode: "edit" } = naka-save na figure ang ine-edit
let dressUpViewing = null;
const DRESSUP_DRAFT_KEY = "figurifyDressUpDraft";
const DRESSUP_NO_DRAFT = "__none__";

// Alin ang naka-save na figure na ine-edit pa (Edit sa Order Summary). Nananatili kahit lumipat
// ng page; nabubura kapag pinindot ang Done.
const DRESSUP_EDITING_KEY = "figurifyDressUpEditing";

function getEditingFigure() {
    try {
        const raw = sessionStorage.getItem(DRESSUP_EDITING_KEY);
        return raw === null || raw === "" ? null : raw;
    } catch (error) {
        return null;
    }
}

function setEditingFigure(index) {
    try {
        if (index === null) {
            sessionStorage.removeItem(DRESSUP_EDITING_KEY);
        } else {
            sessionStorage.setItem(DRESSUP_EDITING_KEY, String(index));
        }
    } catch (error) {
        // ignore
    }
}

// kopya ng design para sa cart (walang malaking preview image)
function buildCartSnapshot() {
    const snap = buildCustomizationSnapshot(true, false);
    if (!snap) {
        return null;
    }
    delete snap.previewImage;
    return JSON.parse(JSON.stringify(snap));
}

// itabi muna ang kasalukuyang figure bago i-edit ang naka-save (Final Summary → Edit)
function stashCurrentDraftForEdit() {
    try {
        const raw = sessionStorage.getItem(CUSTOMIZATION_STORAGE_KEY);
        sessionStorage.setItem(DRESSUP_DRAFT_KEY, raw || DRESSUP_NO_DRAFT);
    } catch (error) {
        // ignore
    }
}

// ibalik ang tinabing figure (tapos na mag-edit, na-cancel, o umalis sa gitna)
function restoreStashedDraft() {
    try {
        const raw = sessionStorage.getItem(DRESSUP_DRAFT_KEY);
        if (raw === null) {
            return;
        }

        if (raw === DRESSUP_NO_DRAFT) {
            sessionStorage.removeItem(CUSTOMIZATION_STORAGE_KEY);
        } else {
            sessionStorage.setItem(CUSTOMIZATION_STORAGE_KEY, raw);
        }

        sessionStorage.removeItem(DRESSUP_DRAFT_KEY);
    } catch (error) {
        // ignore
    }
}

function getEditFigureIndex() {
    const raw = new URLSearchParams(window.location.search).get("figure");
    if (raw === null || raw === "") {
        return null;
    }

    const index = Number(raw);
    return Number.isInteger(index) && index >= 0 ? index : null;
}

function leaveEditMode() {
    setEditingFigure(null);
    restoreStashedDraft();
    window.location.href = "dress-up.php?summary=1";
}

function saveEditedCartFigure() {
    if (!dressUpViewing) {
        return;
    }

    const index = dressUpViewing.index;
    const cart = getDressUpCart();
    if (!cart[index]) {
        leaveEditMode();
        return;
    }

    const built = buildCurrentDressUpFigure();
    if (built.error) {
        showDressUpNotice(built.error, "error", built.target);
        return;
    }

    cart[index] = {
        payload: built.payload,
        style: built.summary.style,
        product: built.summary.product,
        gender: built.summary.gender,
        size: built.summary.size,
        total: built.summary.total,
        rushFee: built.summary.rushFee,
        quantity: getCartItemQty(cart[index]),
        snapshot: buildCartSnapshot()
    };
    if (!saveDressUpCart(cart)) {
        showDressUpNotice("Can't save this figure. Please try again.");
        return;
    }

    leaveEditMode();
}

function getDressUpCart() {
    try {
        const raw = sessionStorage.getItem(DRESSUP_CART_KEY);
        const list = raw ? JSON.parse(raw) : [];
        return Array.isArray(list) ? list.filter((item) => item && item.payload) : [];
    } catch (error) {
        return [];
    }
}

function saveDressUpCart(list) {
    try {
        if (list && list.length) {
            sessionStorage.setItem(DRESSUP_CART_KEY, JSON.stringify(list));
        } else {
            sessionStorage.removeItem(DRESSUP_CART_KEY);
        }
        return true;
    } catch (error) {
        console.warn("Unable to save Dress Up cart:", error);
        return false;
    }
}

function getDressUpCartTotal() {
    return getDressUpCart().reduce((sum, item) => sum + (Number(item.total || 0) + Number(item.rushFee || 0)) * getCartItemQty(item), 0);
}

// buuin ang payload ng kasalukuyang figure (parehong format na tinatanggap
// ng submit_order.php). Ibinabalik ang { error } kapag may kulang pa.
function buildCurrentDressUpFigure() {
    // may ibang figure na naka-preview? ibalik muna ang kasalukuyang figure
    leaveFigurePreview();
    const activeState = getActiveState();
    if (!activeState || !activeState.model) {
        return { error: "Finish your design first." };
    }

    const config = getProductDetailsConfig();
    if (config && config.sizes && config.sizes.length && !productDetails.size) {
        return { error: "Choose a size first.", target: "sizeChoiceGrid" };
    }

    if (config && config.hirono && (productDetails.hironoAddons || []).length && !productDetails.blindBoxSelected) {
        return { error: "Choose a Blind Box type, or remove the add-ons.", target: "hironoBlindBoxGrid" };
    }

    const design = buildDressUpDesignData(activeState);
    const box = buildDressUpBoxDetails();

    // presyo ng figure mismo (walang rush fee - sa server kinukuwenta iyon,
    // pareho ng Image Submission)
    const figureTotal = design.designTotal + design.sizePrice + design.nameFee + box.boxAddonPrice;
    const figureName = (productDetails.figureName || "").trim() || getFigureLabel(state.currentCategory) + " Dress Up";
    const payload = {
        style: getFigureLabel(state.currentCategory),
        product: design.productType,
        size: productDetails.size || "",
        sizeLabel: productDetails.size || "",
        hasSizes: Boolean(config && config.sizes && config.sizes.length),
        name: figureName,
        notes: "",
        productPrice: design.sizePrice,
        nameFee: design.nameFee,
        boxAddonPrice: box.boxAddonPrice,
        boxDetails: box.boxDetails,
        total: figureTotal,
        dressup: design
    };
    return {
        payload,
        summary: {
            style: payload.style,
            product: design.productType,
            gender: design.genderLabel,
            size: productDetails.size || "",
            total: figureTotal,
            rushFee: getCommissionRushFee()
        }
    };
}

// "+ Add Another Figure" sa Final Summary - i-save ang kasalukuyang figure,
// tapos bumalik sa Dress Up para magdisenyo ng panibago
function addCurrentFigureToDressUpCart() {
    const cart = getDressUpCart();
    if (getDressUpPieceCount() + 1 > DRESSUP_CART_MAX) {
        showDressUpNotice("You can add up to " + DRESSUP_CART_MAX + " figures only.");
        return;
    }

    const built = buildCurrentDressUpFigure();
    if (built.error) {
        showDressUpNotice(built.error, "error", built.target);
        return;
    }

    cart.push({
        payload: built.payload,
        style: built.summary.style,
        product: built.summary.product,
        gender: built.summary.gender,
        size: built.summary.size,
        total: built.summary.total,
        rushFee: built.summary.rushFee,
        quantity: getCurrentFigureQty(),
        snapshot: buildCartSnapshot()
    });
    if (!saveDressUpCart(cart)) {
        showDressUpNotice("Can't save this figure. Please try again.");
        return;
    }
    setCurrentFigureQty(1);

    // bagong disenyo - burahin ang kasalukuyang draft pero HINDI ang
    // Order Type / Ship Out Date (hiwalay ang storage key ng mga iyon)
    clearCustomizationSnapshot();
    showDressUpNotice("Figure " + cart.length + " added!", "success");
    setTimeout(() => {
        window.location.href = "dress-up.php";
    }, 700);
}

// qty ng isang figure sa "Figures in this Order": − 1x +
function buildDressUpQtyControl(item, index) {
    const wrap = document.createElement("div");
    wrap.className = "dressup-cart-qty";
    const quantity = getCartItemQty(item);

    const makeButton = (label, text, step) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "dressup-qty-btn";
        button.setAttribute("aria-label", label);
        button.textContent = text;
        button.disabled = step < 0 ? quantity <= 1 : getDressUpPieceCount() >= DRESSUP_CART_MAX;
        button.addEventListener("click", (event) => {
            event.stopPropagation();
            changeDressUpQty(item, index, step);
        });
        return button;
    };

    const value = document.createElement("span");
    value.className = "dressup-qty-value";
    value.textContent = quantity + "x";

    wrap.append(makeButton("Decrease quantity", "−", -1), value, makeButton("Increase quantity", "+", 1));
    return wrap;
}

function changeDressUpQty(item, index, step) {
    const next = getCartItemQty(item) + step;
    if (next < 1 || (step > 0 && getDressUpPieceCount() >= DRESSUP_CART_MAX)) {
        return;
    }

    if (item.current) {
        setCurrentFigureQty(next);
    } else {
        const cart = getDressUpCart();
        if (!cart[index]) {
            return;
        }
        cart[index].quantity = next;
        saveDressUpCart(cart);
    }

    renderDressUpCartList();
    updatePriceDisplay();
}

function removeDressUpCartFigure(index) {
    const cart = getDressUpCart();
    if (index < 0 || index >= cart.length) {
        return;
    }
    selectedCartIndex = null;
    previewFigureInViewer(null);
    cart.splice(index, 1);
    saveDressUpCart(cart);
    renderDressUpCartList();
    updatePriceDisplay();
}

// listahan ng mga naunang figure (Final Summary page)
// 3D preview ng ibang figure sa Final Summary
var previewLock = false;
var currentBuiltCache = null;
var currentTotalCache = 0;

function applySnapshotToState(saved) {
    applySavedDetailsAndState(saved);
    state.currentCategory = saved.currentCategory || state.currentCategory;
    syncItemPricesFromCards();
}

function readDraftSnapshot() {
    try {
        const raw = sessionStorage.getItem(CUSTOMIZATION_STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch (error) {
        return null;
    }
}

// ibalik ang kasalukuyang figure sa state (walang render)
function leaveFigurePreview() {
    if (!previewLock) {
        return false;
    }
    previewLock = false;
    const draft = readDraftSnapshot();
    if (draft) {
        applySnapshotToState(draft);
    }
    return true;
}

// snap = naka-save na figure; null = ibalik ang kasalukuyang figure
function previewFigureInViewer(snap) {
    if (!isSummaryOpen) {
        return;
    }

    if (snap) {
        previewLock = true;
        applySnapshotToState(snap);
    } else if (!leaveFigurePreview()) {
        return;
    }

    void renderCurrentCategory();
}

/* ---------- ORDER SUMMARY (dating finalsummary.php, nasa dress-up.php na) ----------
   Done -> openOrderSummary(): nakatago ang editor, lalabas ang 4 na container, at ang
   3D preview (.figure-area) ay inililipat sa "Your Figure" container. Back to Edit /
   Back ng browser -> closeOrderSummary(): balik sa editor sa parehong step. */

function openOrderSummary(addHistory = true) {
    editingCurrentFigure = false; // Done na: tapos na ang edit

    const summary = document.getElementById("orderSummary");
    const workspace = document.querySelector("#dressUpModule .dressup-workspace");
    const slot = document.getElementById("summaryFigureSlot");
    if (!summary || !workspace || isSummaryOpen) {
        return;
    }

    isSummaryOpen = true;
    selectedCartIndex = null;
    if (figureArea && slot) {
        slot.appendChild(figureArea);
    }
    workspace.hidden = true;
    summary.hidden = false;
    document.body.classList.add("is-summary");

    updatePriceDisplay();
    renderDressUpCartList();
    resize3D();
    window.scrollTo(0, 0);

    // sariling history entry: ang Back ng browser ang magsasara ng summary
    if (addHistory) {
        history.pushState({ dressUpSummary: true }, "");
    }
}

function closeOrderSummary() {
    if (!isSummaryOpen) {
        return;
    }

    if (history.state && history.state.dressUpSummary) {
        history.back(); // ang popstate sa ibaba ang magsasara
        return;
    }

    hideOrderSummary();
}

function hideOrderSummary() {
    const summary = document.getElementById("orderSummary");
    const workspace = document.querySelector("#dressUpModule .dressup-workspace");
    const left = document.querySelector("#dressUpModule .dressup-left");
    if (!summary || !workspace || !isSummaryOpen) {
        return;
    }

    // ibalik muna ang kasalukuyang figure kung ibang figure ang naka-preview
    previewFigureInViewer(null);
    selectedCartIndex = null;
    isSummaryOpen = false;
    if (figureArea && left) {
        left.appendChild(figureArea);
    }
    summary.hidden = true;
    workspace.hidden = false;
    document.body.classList.remove("is-summary");

    updatePriceDisplay();
    resize3D();

    // Edit sa Order Summary: balik sa Figure Details, nandoon pa rin lahat ng napili
    if (backToFirstStep) {
        backToFirstStep = false;
        jumpToWizardStep(1);
    }
}

window.addEventListener("popstate", hideOrderSummary);

// "FIGURE N" badge ng Your Figure at Figure Details (Order Summary)
function setFigureBadges(number) {
    document.querySelectorAll(".figure-index-badge").forEach((badge) => {
        badge.textContent = "FIGURE " + number;
    });
}

// aling figure ng cart ang ipinapakita sa Figure Details (null = kasalukuyang figure)
var selectedCartIndex = null;

function applyCartSelectionToDetails() {
    if (!isSummaryOpen) {
        return;
    }

    const cart = getDressUpCart();
    const item = selectedCartIndex === null ? null : cart[selectedCartIndex];
    if (!item) {
        selectedCartIndex = null;
    }
    setFigureBadges(item ? selectedCartIndex + 1 : cart.length + 1);
    if (!item) {
        return;
    }

    const payload = item.payload || {};
    const design = payload.dressup || {};
    const rows = [];
    const add = (header, value, price, colorValue, image) => {
        if (value) {
            rows.push({
                header,
                value,
                price: Number(price) > 0 ? Number(price) : null,
                colorValue: colorValue || "",
                image: image || ""
            });
        }
    };
    let figureSum = 0;
    const slotPrice = (n) => {
        n = Number(n) || 0;
        figureSum += n;
        return n;
    };
    // Ayos: Figure Style, Product Type, Size, Gender, Skin Tone, tapos Hair / Top / Bottom / Shoes
    add("Figure Style", item.style);
    // walang Size (hal. Head Keychain): nasa Product Type ang presyo ng product
    add("Product Type", item.product, item.size ? 0 : slotPrice(payload.productPrice));
    add("Size", item.size, item.size ? slotPrice(payload.productPrice) : 0);
    add("Gender", /girl|female/i.test(item.gender || "") ? "Girl" : item.gender ? "Boy" : "");
    add(
        "Skin Tone",
        design.skin && design.skin.name,
        slotPrice(design.skin && design.skin.price),
        design.skin && design.skin.color
    );
    (design.slots || []).forEach((s) => {
        if (!s.item || !s.item.name) {
            return;
        }
        // larawan + Outfit Color (kung may pinili)
        rows.push({
            header: s.label,
            value: s.colorName || "",
            price: slotPrice(s.item.price) > 0 ? Number(s.item.price) : null,
            colorValue: s.colorName ? s.colorValue : "",
            image: getItemImage(design.category, s.slot, s.item.model)
        });
    });

    renderRowsIntoContainer(document.getElementById("summaryReceiptItems"), rows);
    const box = payload.boxDetails;
    const boxRows = [];
    if (box) {
        const addBox = (header, value, price, image = "") => {
            if (value) {
                boxRows.push({ header, value, price: Number(price) > 0 ? Number(price) : null, image });
            }
        };
        if (box.addonType === "hirono_blind_box") {
            const blindBox = HIRONO_BLIND_BOXES.find((b) => b.id === box.blindType) || HIRONO_BLIND_BOXES[0];
            addBox("Blind Box Type", blindBox.label, blindBox.price);
            Object.keys(box.blindItems || {}).forEach((k) => {
                const addon = HIRONO_ADDONS.find((a) => a.key === k);
                addBox("Add-on", addon ? addon.label : k, box.blindItems[k]);
            });
            addBox("Box Design", { checkered: "Checkered", peek: "Hirono Peek" }[box.boxDesign] || box.boxDesign);
            addBox("Box Color", box.boxColor);
            addBox("Nickname", box.nickname);
            addBox("Letter", box.letter);
            if (box.dateMonth && box.dateDay) {
                addBox("Month / Day", box.dateMonth + "/" + box.dateDay);
            }
        } else {
            // presyo ng box = natitira sa total ng figure
            const boxPrice = Math.max(0, (Number(item.total) || 0) - figureSum);
            const funkoBox = FUNKO_BOXES.find((b) => b.id === box.boxType);
            addBox("Box Type", box.boxType, boxPrice, funkoBox ? "../Image/" + funkoBox.image : "");
            addBox("Box Name", box.boxName);
            addBox("Box Number", box.boxNumber);
            addBox("Box Color", box.boxColor);
        }
    }
    renderBoxRows(boxRows);
}

function renderDressUpCartList() {
    const wrap = document.getElementById("dressUpCartSection");
    const list = document.getElementById("dressUpCartList");
    if (!wrap || !list) {
        return;
    }

    const cart = getDressUpCart();
    const items = cart.slice();

    // ang kasalukuyang figure ay kasama na rin sa listahan (Figure #N)
    const built = previewLock ? currentBuiltCache || { error: true } : buildCurrentDressUpFigure();
    if (!previewLock) {
        currentBuiltCache = built;
    }
    if (!built.error) {
        items.push({
            current: true,
            payload: built.payload,
            style: built.summary.style,
            product: built.summary.product,
            gender: built.summary.gender,
            size: built.summary.size,
            total: built.summary.total,
            rushFee: built.summary.rushFee,
            quantity: getCurrentFigureQty()
        });
    }

    setFigureBadges(cart.length + 1);

    list.innerHTML = "";
    wrap.hidden = false;
    if (!items.length) {
        const empty = document.createElement("div");
        empty.className = "receipt-empty";
        empty.textContent = "No figure yet.";
        list.appendChild(empty);
        return;
    }

    items.forEach((item, index) => {
        const row = document.createElement("div");
        row.className = "dressup-cart-item";
        row.setAttribute("role", "button");
        row.tabIndex = 0;
        row.setAttribute("aria-expanded", "false");
        const head = document.createElement("div");
        head.className = "dressup-cart-head";
        const info = document.createElement("div");
        info.className = "dressup-cart-info";
        const title = document.createElement("div");
        title.className = "dressup-cart-title";
        title.textContent = "Figure " + (index + 1) + " " + (item.style || "Dress Up");
        const meta = document.createElement("div");
        meta.className = "dressup-cart-meta";
        meta.textContent = [item.product, item.gender, item.size].filter(Boolean).join(" • ");
        info.appendChild(title);
        info.appendChild(meta);
        const price = document.createElement("div");
        price.className = "dressup-cart-price";
        price.textContent = formatMoney((Number(item.total || 0) + Number(item.rushFee || 0)) * getCartItemQty(item));
        const actions = document.createElement("div");
        actions.className = "dressup-cart-actions";
        actions.appendChild(buildDressUpQtyControl(item, index));
        if (item.current) {
            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "dressup-cart-edit";
            edit.textContent = "Edit";
            edit.addEventListener("click", (event) => {
                event.stopPropagation();
                editingCurrentFigure = true;
                backToFirstStep = true;
                closeOrderSummary();
            });
            actions.appendChild(edit);
        } else if (item.snapshot) {
            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "dressup-cart-edit";
            edit.textContent = "Edit";
            edit.addEventListener("click", (event) => {
                event.stopPropagation();
                if (getEditingFigure() === null) {
                    stashCurrentDraftForEdit();
                }
                setEditingFigure(index);
                window.location.href = "dress-up.php?figure=" + index;
            });
            actions.appendChild(edit);
        }

        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "dressup-cart-remove";
        remove.textContent = "Remove";
        remove.addEventListener("click", (event) => {
            event.stopPropagation();
            removeDressUpCartFigure(index);
        });
        if (!item.current) {
            actions.appendChild(remove);
        }

        head.appendChild(info);
        head.appendChild(price);
        head.appendChild(actions);
        row.appendChild(head);

        // click sa buong container → lumilipat ang Figure Details sa figure na ito
        const select = () => {
            selectedCartIndex = item.current ? null : index;
            if (item.current) {
                previewFigureInViewer(null);
            } else if (item.snapshot) {
                previewFigureInViewer(item.snapshot);
            }
            updatePriceDisplay();
            applyCartSelectionToDetails();
            list.querySelectorAll(".dressup-cart-item").forEach((el, i) => {
                const on = items[i].current ? selectedCartIndex === null : i === selectedCartIndex;
                el.classList.toggle("is-open", on);
                el.setAttribute("aria-expanded", on ? "true" : "false");
            });
        };
        if (item.current ? selectedCartIndex === null : selectedCartIndex === index) {
            row.classList.add("is-open");
            row.setAttribute("aria-expanded", "true");
        }

        row.addEventListener("click", select);
        row.addEventListener("keydown", (event) => {
            if ((event.key === "Enter" || event.key === " ") && event.target === row) {
                event.preventDefault();
                select();
            }
        });
        list.appendChild(row);
    });

}

async function submitDressUpOrder(button) {
    if (dressUpOrderSubmitting) {
        return;
    }

    // may ine-edit pang figure: sabihin kung bakit hindi pa tuloy (hindi tahimik na naka-disable ang button)
    if (getEditingFigure() !== null) {
        showDressUpNotice("Press Done on the figure you are editing first.");
        return;
    }

    // hindi pa naka-login: bumukas agad ang Log In pop up, pindutin ulit ang Submit Order pagka-login
    if (window.figurifyRequireLogin) {
        // pagka-login: babalik sa Order Summary na ito (naka-login na). HINDI awtomatikong tutuloy sa
        // Payment page: pipindutin ulit ng customer ang Proceed to Payment kapag sigurado na siya.
        window.figurifyRequireLogin("dress-up.php?summary=1");
        return;
    }

    const orderType = getCommissionOrderTypeValue();
    const bookingDate = getCommissionBookingDateValue();
    if (!orderType && !bookingDate) {
        showDressUpNotice("Choose an order type and ship out date first.");
        return;
    }

    if (!orderType) {
        showDressUpNotice("Choose an order type first.");
        return;
    }

    if (!bookingDate) {
        showDressUpNotice("Pick a ship out date first.");
        return;
    }

    const built = buildCurrentDressUpFigure();
    if (built.error) {
        showDressUpNotice(built.error, "error", built.target);
        return;
    }

    // lahat ng figure ng order: mga naunang naidagdag + ang kasalukuyan
    // kasama ang qty ng bawat figure (sa server, ang qty 2 = 2 piraso na parehong disenyo)
    const allFigures = getDressUpCart()
        .map((item) => Object.assign({}, item.payload, { quantity: getCartItemQty(item) }))
        .concat([Object.assign({}, built.payload, { quantity: getCurrentFigureQty() })]);

    // Diretso na sa bayad - walang extra confirm popup
    if (dressUpOrderSubmitting) {
        return;
    }

    const formData = new FormData();
    formData.append("order_type", orderType);
    formData.append("order_method", "create_style");
    formData.append("ship_out_date", bookingDate);
    formData.append("figures", JSON.stringify(allFigures));
    dressUpOrderSubmitting = true;
    let orderPlaced = false;
    const originalText = button ? button.textContent : "";
    if (button) {
        button.classList.add("is-loading");
        button.setAttribute("aria-disabled", "true");
        button.textContent = "Opening payment…";
    }

    try {
        const response = await fetch("submit_order.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        });
        const rawText = await response.text();
        let data = null;
        try {
            data = JSON.parse(rawText);
        } catch (parseError) {
            console.error("submit_order.php raw response:", rawText);
        }

        if (!data || !data.success) {
            const message =
                (data && data.message) || "Something went wrong while placing your order. Please try again.";

            // sa localhost lang ibinibigay ng server ang totoong dahilan -
            // ipakita na rin para madaling ma-trace habang dine-develop
            if (data && data.debug_reason) {
                console.error("Dress Up order failed:", data.debug_reason);
                showDressUpNotice(message + " (Details: " + data.debug_reason + ")");
                return;
            }

            showDressUpNotice(message);
            return;
        }

        // naka-save na ang order - burahin ang lahat ng napili (design, cart, Order Type, Ship Out Date)
        // para hindi maulit ang order, at huwag nang i-enable ulit ang button habang nagre-redirect
        orderPlaced = true;
        window.figurifyClearOrder();

        // Dress Up: walang quotation - diretso sa Payment & Shipping page
        // (payment_url); sa Verification na ito susuriin ng staff
        // Dress Up: laging diretso sa Payment & Shipping page, hindi sa My Orders
        const paymentUrl = data.payment_url || "../my-order/payment-shipping.php?order_id=" + data.order_id;
        window.location.href = paymentUrl + (paymentUrl.includes("?") ? "&" : "?") + "fresh=1";
    } catch (error) {
        console.error("Dress Up order request failed:", error);
        showDressUpNotice("Can't connect right now. Please try again.");
    } finally {
        if (orderPlaced) {
            return;
        }

        dressUpOrderSubmitting = false;
        if (button) {
            button.classList.remove("is-loading");
            button.removeAttribute("aria-disabled");
            button.textContent = originalText || "Submit Order";
        }
    }
}

/* =====================================================
   19. MGA CLICK AT EVENT
   Pagkabit ng click sa mga button at card
   ===================================================== */

function initializeInteractions() {
    initializeCustomColorPickers();
    initWizardPills();
    styleCards.forEach(function (card) {
        card.addEventListener("click", function () {
            handleCardClick(card);
        });
    });
    itemCards.forEach(function (card) {
        card.addEventListener("click", function () {
            handleCardClick(card);
        });
    });
    if (resetBtn) {
        resetBtn.addEventListener("click", function () {
            resetDressUpDesign();
        });
    }

    [
        [boxNameInput, "boxName"],
        [boxNumberInput, "boxNumber"],
        [boxColorInput, "boxColor"]
    ].forEach(([input, key]) => {
        if (!input) {
            return;
        }

        input.addEventListener("input", () => {
            productDetails[key] = input.value;
            renderProductDetailsPanel();
            saveProductDetails();
        });
    });
    if (continueBtn) {
        continueBtn.addEventListener("click", async function (event) {
            const activeState = getActiveState();
            if (!activeState || !activeState.model) {
                event.preventDefault();
                showDressUpNotice("Choose a figure model first.");
                return;
            }

            event.preventDefault();
            if (dressUpViewing) {
                saveEditedCartFigure();
                return;
            }

            await renderCurrentCategory();
            saveCustomizationSnapshot(true, false);

            // "Done" sa Box/Add-ons: buksan ang Order Summary (nasa page na ito)
            openOrderSummary();
        });
    }

    // "Proceed to Payment" - isinusumite na ang Dress Up order (diretso
    // "Awaiting Payment", walang quotation), tapos papunta sa Payment page.
    const finalSummaryContinueBtn = document.getElementById("finalSummaryContinueBtn");
    const finalSummaryAddFigureBtn = document.getElementById("finalSummaryAddFigureBtn");

    if (finalSummaryAddFigureBtn) {
        finalSummaryAddFigureBtn.addEventListener("click", function (event) {
            event.preventDefault();
            addCurrentFigureToDressUpCart();
        });
    }

    const summaryBackBtn = document.getElementById("summaryBackBtn");
    if (summaryBackBtn) {
        summaryBackBtn.addEventListener("click", closeOrderSummary);
    }

    renderDressUpCartList();
    if (finalSummaryContinueBtn) {
        finalSummaryContinueBtn.addEventListener("click", function (event) {
            event.preventDefault();
            void submitDressUpOrder(finalSummaryContinueBtn);
        });
    }
}

/* =====================================================
   20. 3D VIEWER SETUP AT ANIMATION
   Pagsimula ng three.js viewer
   ===================================================== */

function init3D() {
    if (!canvas) {
        console.error("[Figurify 3D] Canvas #figureViewer was not found.");
        return;
    }

    scene = new THREE.Scene();
    figureShadow = null;
    camera = new THREE.PerspectiveCamera(35, 1, 0.1, 1000);
    camera.position.set(0, 1.1, 4.5);
    try {
        renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true, preserveDrawingBuffer: true });
    } catch (error) {
        console.error("[Figurify 3D] WebGL initialization failed.", error);
        return;
    }

    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setClearColor(0x000000, 0);
    renderer.shadowMap.enabled = true;
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.enablePan = false;

    // drag (o ‹ 360° ›) para umikot, left/right lang - naka-lock ang taas/baba
    // para hindi makita ang ilalim o ibabaw ng figure. Walang zoom.
    controls.minPolarAngle = Math.PI / 2;
    controls.maxPolarAngle = Math.PI / 2;
    controls.enableZoom = false;
    // vertical swipe = scroll ng page (hindi nahaharang ng 3D preview sa phone); horizontal lang ang ikot
    // OrbitControls naglalagay ng inline "touch-action: none" - tanggalin; nasa CSS na (.figure-viewer).
    // (binabasa muna ang style para tuluyang mabura, hindi bumalik bilang walang lamang style attribute)
    if (renderer.domElement.getAttribute("style") !== null) {
        renderer.domElement.removeAttribute("style");
    }

    // 360° rotate control (‹ 360° ›) sa gitna-baba ng viewer
    setup360Button(canvas);
    scene.add(new THREE.AmbientLight(0xffffff, 1.6));
    const keyLight = new THREE.DirectionalLight(0xffffff, 1.3);
    keyLight.position.set(4, 6, 6);
    scene.add(keyLight);
    const fillLight = new THREE.DirectionalLight(0xfff0f7, 0.8);
    fillLight.position.set(-4, 3, 4);
    scene.add(fillLight);
    const rimLight = new THREE.DirectionalLight(0xffffff, 0.45);
    rimLight.position.set(0, 4, -5);
    scene.add(rimLight);
    resize3D();
    if (figureResizeObserver) {
        figureResizeObserver.disconnect();
    }

    if (figureArea && typeof ResizeObserver !== "undefined") {
        figureResizeObserver = new ResizeObserver(function () {
            resize3D();
        });
        figureResizeObserver.observe(figureArea);
    }

    requestAnimationFrame(resize3D);
    window.addEventListener("resize", resize3D);
}

/* =====================================================
   21. 360° ROTATE
   Mga arrow para umikot ang figure
   ===================================================== */

// 360° rotate control: left/right arrows
// Bawat click = eksaktong 90° papunta sa susunod na view:
// Front -> Right -> Back -> Left (isang click, nasa side na agad)
let figureRotatePending = 0; // natitirang radians na iikutin
const FIGURE_ROTATE_STEP = Math.PI / 2;
const FIGURE_ROTATE_SPEED = 0.13; // radians per frame (mabilis pero smooth)

function getFigureViewAngle() {
    if (!camera || !controls) {
        return 0;
    }

    const offset = camera.position.clone().sub(controls.target);
    return Math.atan2(offset.x, offset.z);
}

function rotateFigureView(angle) {
    if (!camera || !controls) {
        return;
    }

    const offset = camera.position.clone().sub(controls.target);
    offset.applyAxisAngle(new THREE.Vector3(0, 1, 0), angle);
    camera.position.copy(controls.target.clone().add(offset));
    camera.lookAt(controls.target);
}

// iikot papunta sa susunod na 90° na pwesto (naka-snap kahit na-drag na ng mouse)
function queueFigureRotation(dir) {
    const base = getFigureViewAngle() + figureRotatePending;
    const index = base / FIGURE_ROTATE_STEP;
    const nextIndex = dir > 0 ? Math.floor(index + 1e-4) + 1 : Math.ceil(index - 1e-4) - 1;
    figureRotatePending += nextIndex * FIGURE_ROTATE_STEP - base;
}

function stepFigureRotation() {
    if (Math.abs(figureRotatePending) > 0.0001) {
        const step = Math.sign(figureRotatePending) * Math.min(Math.abs(figureRotatePending), FIGURE_ROTATE_SPEED);
        rotateFigureView(step);
        figureRotatePending -= step;
    }
}

function setup360Button(canvasEl) {
    const host = canvasEl && canvasEl.parentElement;
    if (!host || host.querySelector(".figure-rotate")) {
        return;
    }

    // ISANG container: [ ‹  360°  › ] - arrow sa kaliwa/kanan, 360 sa gitna. Bawat click = 90°
    const arrowSvg = (dir) =>
        '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">' +
        '<polyline points="' + (dir < 0 ? "15 5 8 12 15 19" : "9 5 16 12 9 19") + '" fill="none" ' +
        'stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    const box = document.createElement("div");
    box.className = "figure-rotate";
    box.setAttribute("role", "group");
    box.setAttribute("aria-label", "Rotate figure");

    const makeButton = function (label, dir) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "figure-rotate-btn";
        button.title = label;
        button.setAttribute("aria-label", label);
        button.innerHTML = arrowSvg(dir);
        button.addEventListener("click", function () {
            queueFigureRotation(dir);
        });
        return button;
    };

    const center = document.createElement("span");
    center.className = "figure-rotate-label";
    center.innerHTML = "360<sup>&deg;</sup>";

    box.appendChild(makeButton("Rotate left", -1));
    box.appendChild(center);
    box.appendChild(makeButton("Rotate right", 1));
    host.appendChild(box);
}

function animate() {
    requestAnimationFrame(animate);
    stepFigureRotation();
    if (controls) {
        controls.update();
    }

    if (renderer && scene && camera) {
        renderer.render(scene, camera);
    }
}

/* =====================================================
   22. PAGSIMULA NG PAGE
   Dito nagsisimulang tumakbo ang lahat pagkabukas ng page
   ===================================================== */

// bootstrap

// Walang Order Type / Ship Out Date? Bumalik muna sa Commission para pumili.
if (!getCommissionOrderTypeValue() || !getCommissionBookingDateValue()) {
    window.location.replace("commission.php?need=schedule");
}

// lumipat sa Dress Up (galing Commission, navbar o footer): burahin ang napili sa Image Submission.
// Ang napili sa Dress Up ay nananatili hanggang mag log out o lumipat sa Image Submission.
window.figurifyClearOrder(["imageSubmission"]);

// ?figure=N → ine-edit ang naka-save na figure (galing Order Summary). Kung may iniwang edit
// (lumipat ng page habang nag-e-edit), ipagpatuloy iyon; kung wala, ibalik ang tinabing figure.
const editParam = getEditFigureIndex();
const editingSaved = getEditingFigure();
const editFigureIndex = editParam !== null ? editParam : editingSaved !== null ? Number(editingSaved) : null;

if (editFigureIndex !== null) {
    const savedItem = getDressUpCart()[editFigureIndex];
    if (savedItem && savedItem.snapshot) {
        dressUpViewing = { index: editFigureIndex, mode: "edit" };
        if (editParam !== null) {
            // bagong click ng Edit: kunin ang naka-save na design, tapos tanggalin ang ?figure sa link
            try {
                sessionStorage.removeItem(COMMISSION_FIGURE_CATEGORY_KEY);
                sessionStorage.setItem(CUSTOMIZATION_STORAGE_KEY, JSON.stringify(savedItem.snapshot));
                history.replaceState(null, "", "dress-up.php");
            } catch (error) {
                // ignore
            }
        }
    } else {
        setEditingFigure(null);
        restoreStashedDraft();
        if (editParam !== null) {
            window.location.replace("dress-up.php?summary=1");
        }
    }
} else {
    restoreStashedDraft();
}

refreshOptionPriceLabels();
initializeInteractions();
updateSectionVisibility();
updatePriceDisplay();
setFigurePromptVisible(true);
init3D();

// Kung tapos na ang design (pinindot na ang Done) at bumalik sa Dress Up, ibalik ang napili
// para hindi blangko ang kaliwa.
function hasFinishedDraft() {
    const draft = readDraftSnapshot();
    return Boolean(draft && draft.customizationCompleted);
}

// Laging ibinabalik ang naka-save na design, kahit hindi pa tapos (hal. nag Log In / Sign Up,
// nag-refresh, o lumipat ng page). Nawawala lang kapag nag log out, na-submit ang order,
// pinindot ang Reset Design, o lumipat sa Image Submission.
// Tapos na ang design (pinindot na ang Done) o ?summary = sa huling step (resume).
const restoredCustomization = restoreCustomizationFromStorage(
    dressUpViewing
        ? "edit"
        : isSummaryRequest || (!isEditPage && !isResumePage && hasFinishedDraft())
          ? "resume"
          : undefined
);

animate();

// ?summary=1 at may naka-save na design: buksan agad ang Order Summary (walang dagdag na history).
// (pagka-login galing sa Proceed to Payment) Order Summary lang ang babalik; hindi nagpapatuloy nang kusa.
if (isSummaryRequest && restoredCustomization && !dressUpViewing) {
    history.replaceState(null, "", "dress-up.php?resume=1");
    renderCurrentCategory().then(() => {
        openOrderSummary(false);
    });
}

// tapos na ang simula ng page: itago ang loading kung walang figure na nilo-load
if (figureLoading.count === 0) {
    figureLoading.shown = false;
}
updateFigureLoadingView();

// dressup.js ay type="module" kaya hindi global ang functions dito by default -
// i-expose explicit para magamit ng commission.js pag nag-refresh ng Order Summary.
window.updatePriceDisplay = updatePriceDisplay;
window.renderPreviewSummary = renderPreviewSummary;

/* =====================================================
   23. SCROLL
   Maliit na ayos sa scroll ng mga panel
   ===================================================== */

// huwag bumalik sa itaas ang scroll pag nag-click ng hair/damit/sapatos
// Kinukuha ang scroll position ng lahat ng scrollable na parent ng card bago mag-click,
// at ibinabalik kung may nag-reset (pag-ayos ng grupo, pag-load ng model, atbp.),
// maliban kung ang user mismo ang nag-scroll pagkatapos.
(function keepScrollOnPick() {
    let userScrolled = false;
    ["wheel", "touchmove", "keydown"].forEach((type) => {
        window.addEventListener(
            type,
            () => {
                userScrolled = true;
            },
            { passive: true, capture: true }
        );
    });
    document.addEventListener(
        "click",
        function (event) {
            const card =
                event.target.closest &&
                event.target.closest(
                    "#dressUpModule .option-card, #dressUpModule .skin-card, #dressUpModule .top-color-card"
                );
            if (!card) {
                return;
            }

            // kahit nasa pinakataas (scrollTop 0), itatabi rin - para hindi bumaba ang view pagka-click
            const saved = [];
            let el = card.parentElement;
            while (el) {
                if (el.scrollHeight > el.clientHeight + 2) {
                    saved.push([el, el.scrollTop]);
                }
                el = el.parentElement;
            }
            const winY = window.scrollY;
            userScrolled = false;
            const restore = () => {
                if (userScrolled) {
                    return;
                }
                // ibalik sa dating puwesto (pataas man o pababa ang nangyaring pagtalon)
                saved.forEach(([node, top]) => {
                    if (Math.abs(node.scrollTop - top) > 1) {
                        node.scrollTop = Math.min(top, node.scrollHeight - node.clientHeight);
                    }
                });
                if (Math.abs(window.scrollY - winY) > 1) {
                    window.scrollTo(window.scrollX, winY);
                }
            };

            // sunod-sunod para mahuli ang sync, timeout (group refresh) at async (model load) na reset
            requestAnimationFrame(restore);
            [0, 60, 200, 500, 1000].forEach((ms) => setTimeout(restore, ms));
        },
        true
    );
})();
