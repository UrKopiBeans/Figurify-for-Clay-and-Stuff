import * as THREE from "three";

import {
    GLTFLoader
} from "three/addons/loaders/GLTFLoader.js";

import {
    OrbitControls
} from "three/addons/controls/OrbitControls.js";


// DOM elements

const canvas =
    document.getElementById("figureViewer");

const figureArea =
    document.querySelector(".figure-area");

const selectedList =
    document.getElementById("selectedList");

const dressUpTotalPrice =
    document.getElementById("dressUpTotalPrice");

const resetBtn =
    document.getElementById("resetBtn");

const continueBtn =
    document.getElementById("continueBtn");

const productDetailsPanel =
    document.getElementById("productDetailsPanel");

const figureDetailsContainer =
    document.getElementById("figureDetailsContainer");

const sizeChoiceGrid =
    document.getElementById("sizeChoiceGrid");

const boxChoiceGrid =
    document.getElementById("boxChoiceGrid");

const figureNameInput =
    document.getElementById("figureNameInput");

const boxNameInput =
    document.getElementById("boxNameInput");

const boxNumberInput =
    document.getElementById("boxNumberInput");

const boxColorInput =
    document.getElementById("boxColorInput");

const COMMISSION_RETURN_KEY =
    "figurifyCommissionReturn";

const COMMISSION_ORDER_TYPE_KEY =
    "figurifyCommissionOrderType";

const COMMISSION_BOOKING_DATE_KEY =
    "figurifyCommissionBookingDate";

const COMMISSION_FIGURE_CATEGORY_KEY =
    "figurifyCommissionFigureCategory";

const CUSTOMIZATION_STORAGE_KEY =
    "figurifyCustomization";

const pageMode =
    document.body.dataset.page || "editor";

const isPreviewPage =
    pageMode === "preview";

const isDetailsPage =
    pageMode === "details";

const isFinalSummaryPage =
    pageMode === "finalSummary";

const isEditPage =
    new URLSearchParams(window.location.search).get("edit") === "1";

// "?resume=1" - dapat bumalik ang "Back" mula sa finalsummary.php sa eksaktong
// step kung saan pinindot ang "Next" (hindi mag-reset pabalik sa Figure Style).
const isResumePage =
    new URLSearchParams(window.location.search).get("resume") === "1";

const styleCards =
    Array.from(
        document.querySelectorAll(
            '[data-tab="figure"]'
        )
    );


const itemCards =
    Array.from(
        document.querySelectorAll(
            "[data-slot]"
        )
    );


const funkoPanel =
    document.getElementById("funkoPanel");

const hironoPanel =
    document.getElementById("hironoPanel");

const chibiPanel =
    document.getElementById("chibiPanel");

const figureStyleSection =
    document.getElementById("figureStyleSection");


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
        shoes: "funkoShoesSection"
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
        keychainBoyTop: "chibiBoyKeychainTopSection",
        keychainBoyBottom: "chibiBoyKeychainBottomSection",
        keychainGirlTop: "chibiGirlKeychainTopSection",
        keychainGirlBottom: "chibiGirlKeychainBottomSection"
    }
};


const categoryPanels = {
    funko: funkoPanel,
    hirono: hironoPanel,
    chibi: chibiPanel
};


function getStorageValue(key) {

    try {
        return localStorage.getItem(key);
    }
    catch (error) {
        console.warn("Unable to read storage key:", key, error);
        return null;
    }

}


function getCustomizationState() {

    const raw =
        getStorageValue(
            CUSTOMIZATION_STORAGE_KEY
        );


    if (!raw) {
        return null;
    }


    try {
        return JSON.parse(raw);
    }
    catch (error) {
        console.warn(
            "Unable to parse customization state:",
            error
        );
        return null;
    }

}


function formatMoney(amount) {

    return "₱" +
        Number(amount || 0)
            .toLocaleString("en-PH");

}


function formatBookingDate(value) {

    if (!value) {
        return "Not selected";
    }

    const date =
        new Date(
            `${value}T00:00:00`
        );

    if (Number.isNaN(date.getTime())) {
        return "Not selected";
    }

    return date.toLocaleDateString(
        "en-PH",
        {
            month: "long",
            day: "numeric",
            year: "numeric"
        }
    );

}


function getCommissionOrderTypeValue() {
    return getStorageValue(COMMISSION_ORDER_TYPE_KEY);
}


function getCommissionOrderTypeLabel() {

    const value =
        getCommissionOrderTypeValue();

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
    if (
        state.currentCategory === "hirono" &&
        state.hirono.mode === "headKeychain"
    ) {
        return 200;
    }

    return 500;
}


function getCommissionBookingDateValue() {
    return getStorageValue(COMMISSION_BOOKING_DATE_KEY);
}


function getCommissionBookingDateLabel() {
    return formatBookingDate(
        getCommissionBookingDateValue()
    );
}


function getCommissionBookingStatusLabel() {

    const value =
        getCommissionBookingDateValue();

    if (!value) {
        return "Choose a date from the calendar";
    }

    const date =
        new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return "Choose a date from the calendar";
    }

    return date.toLocaleDateString(
        "en-PH",
        { weekday: "long" }
    );

}


function getCommissionFigureCategoryValue() {
    return getStorageValue(COMMISSION_FIGURE_CATEGORY_KEY);
}




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


const PRODUCT_DETAIL_CONFIGS = {
    funkoBoy: {
        title: "Funko Pop Boy Details",
        description: "Custom Funko box for your figure.",
        sizes: ["3 inches", "4 inches", "5 inches"],
        boxes: [
            { id: "solo", label: "Solo Box", price: 500, details: true, swatch: "linear-gradient(145deg,#ffe0ef,#f6c6de)" },
            { id: "couple", label: "Couple Box", price: 800, details: true, swatch: "linear-gradient(145deg,#e8ddff,#d5c4f7)" }
        ],
        boxLabel: "Custom Funko Box"
    },
    funkoGirl: {
        title: "Funko Pop Girl Details",
        description: "Custom Funko box for your figure.",
        sizes: ["3 inches", "4 inches", "5 inches"],
        boxes: [
            { id: "solo", label: "Solo Box", price: 500, details: true, swatch: "linear-gradient(145deg,#ffe0ef,#f6c6de)" },
            { id: "couple", label: "Couple Box", price: 800, details: true, swatch: "linear-gradient(145deg,#e8ddff,#d5c4f7)" }
        ],
        boxLabel: "Custom Funko Box"
    },
    hironoStandee: {
        title: "Hirono Standee Details",
        description: "Full body standee with blind box options and add-ons.",
        sizes: ["2 inches", "3.5 inches"],
        boxes: [],
        hirono: true
    },
    hironoKeychain: {
        title: "Hirono Keychain Details",
        description: "Keychain with blind box options and add-ons.",
        sizes: ["2 inches"],
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
        sizes: ["2 inches", "3 inches", "4 inches", "5 inches"],
        boxes: []
    },
    chibiBoyKeychain: {
        title: "Chibi Boy Keychain Details",
        description: "Chibi keychains do not have box choices.",
        sizes: ["2 inches"],
        boxes: []
    },
    chibiGirl: {
        title: "Chibi Girl Details",
        description: "Chibi figures do not have box choices.",
        sizes: ["2 inches", "3 inches", "4 inches", "5 inches"],
        boxes: []
    },
    chibiGirlKeychain: {
        title: "Chibi Girl Keychain Details",
        description: "Chibi keychains do not have box choices.",
        sizes: ["2 inches"],
        boxes: []
    }
};


const PRODUCT_SIZE_PRICES = {
    funkoBoy: { "3 inches": 900, "4 inches": 1200, "5 inches": 1500 },
    funkoGirl: { "3 inches": 900, "4 inches": 1200, "5 inches": 1500 },
    chibiBoy: { "2 inches": 500, "3 inches": 680, "4 inches": 900, "5 inches": 1200 },
    chibiGirl: { "2 inches": 500, "3 inches": 680, "4 inches": 900, "5 inches": 1200 },
    chibiBoyKeychain: { "2 inches": 500 },
    chibiGirlKeychain: { "2 inches": 500 },
    hironoStandee: { "2 inches": 600, "3.5 inches": 950 },
    hironoKeychain: { "2 inches": 600 }
};


let productDetails = {
    productKey: "",
    size: "",
    figureName: "",
    box: "",
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

let navigationSlot = isDetailsPage ? "productDetails" : null;

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
let currentStepNumber = null;
let currentDesignSlot = null;
const pickedSlots = new Set();

let pendingFigureCategory = null;


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


const SLOT_ORDER = {
    funkoBoy: [
        "model",
        "skin",
        "hair",
        "hairColor",
        "top",
        "topColor",
        "bottom",
        "pantsColor",
        "shoes",
        "shoesColor"
    ],
    funkoGirl: [
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
    ],
    chibiBoy: [
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
    ],
    chibiGirl: [
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
    ],
    // Chibi keychains use the same customizable parts as their standee figure.
    chibiBoyKeychain: [
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
    ],
    chibiGirlKeychain: [
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
    ],
    hironoStandee: [
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
    ],
    hironoKeychain: [
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
    ],
    hironoHeadKeychain: [
        "model",
        "skin",
        "keychainHair",
        "keychainHairColor",
        "keychainHat"
    ]
};


const PRICE_FALLBACKS = {
    funko: {
        hair: 0,
        girlHair: 0,
        top: {
            "T-Shirt": 80,
            Hoodie: 100
        },
        girlTop: {
            "Girl Shirt 01": 80,
            "Girl Shirt 02": 100,
            "Girl Shirt 03": 100
        },
        bottom: 90,
        girlBottom: 90
    },
    chibi: {
        hair: 0,
        girlHair: 50
    },
    hirono: {
        hair: 0,
        outfit: {
            "Hirono Polo": 80,
            "Hirono Jersey": 0
        },
        pants: 90,
        shoes: 80,
        keychainHair: 0,
        keychainHat: 0
    }
};


// three.js setup

const loader = new GLTFLoader();

let scene = null;
let camera = null;
let renderer = null;
let controls = null;
let figureResizeObserver = null;

let bodyModel = null;
let figureShadow = null;

const currentObjects = {
    hair: null,
    girlHair: null,
    top: null,
    girlTop: null,
    bottom: null,
    girlBottom: null,
    outfit: null,
    pants: null,
    shoes: null,
    keychainHair: null,
    keychainHat: null
};


const loadTokens = {
    body: 0,
    hair: 0,
    girlHair: 0,
    top: 0,
    girlTop: 0,
    bottom: 0,
    girlBottom: 0,
    outfit: 0,
    pants: 0,
    shoes: 0,
    keychainHair: 0,
    keychainHat: 0
};


let renderToken = 0;


// basic helpers

function getActiveState() {

    if (!state.currentCategory) {
        return null;
    }

    return state[state.currentCategory];

}


function getHironoMode() {

    const hironoState =
        state.hirono;


    return hironoState.mode || "standee";

}


function getActiveSlotOrder() {

    if (state.currentCategory === "hirono") {
        if (getHironoMode() === "headKeychain") {
            return SLOT_ORDER.hironoHeadKeychain;
        }

        return getHironoMode() === "keychain"
            ? SLOT_ORDER.hironoKeychain
            : SLOT_ORDER.hironoStandee;
    }

    if (state.currentCategory === "funko") {
        return isFunkoGirlModel(
            state.funko.model
        )
            ? SLOT_ORDER.funkoGirl
            : SLOT_ORDER.funkoBoy;
    }

    if (state.currentCategory === "chibi") {
        if (state.chibi.productType === "keychain") {
            return isChibiGirlModel(state.chibi.model)
                ? SLOT_ORDER.chibiGirlKeychain
                : SLOT_ORDER.chibiBoyKeychain;
        }

        return isChibiGirlModel(
            state.chibi.model
        )
            ? SLOT_ORDER.chibiGirl
            : SLOT_ORDER.chibiBoy;
    }


    return SLOT_ORDER[state.currentCategory] || [];

}


function getSlotColorSlot(slot) {

    const category =
        state.currentCategory;

    if (
        category === "funko" &&
        isFunkoGirlModel(state.funko.model)
    ) {
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


function isFunkoGirlModel(modelItem) {

    return Boolean(
        modelItem &&
        /girl/i.test(
            String(
                modelItem.name || ""
            )
        )
    );

}


// Chibi Girl (standee / keychain): "...-light.glb" at "...-dark.glb" ang katawan.
// Skin Light/Dark ang pumipili kung alin ang ilo-load.
function getChibiGirlBodyVariantPath(path, skin) {
    const pattern = /(chibi girl|girl-keychain|chibi keychain)-(light|dark)\.glb$/i;
    const value = String(path || "");

    if (!pattern.test(value)) {
        return null;
    }

    const tone = skin && /dark/i.test(String(skin.name || "")) ? "dark" : "light";

    return value.replace(pattern, (all, base) => base + "-" + tone + ".glb");
}

// Chibi Boy standee: Dark skin uses its own baked GLB instead of tinting
// the Light/default body. Keychains continue to use their existing models.
function getChibiBoyBodyVariantPath(path, skin) {
    const value = String(path || "");

    if (!/Chibi Standee Boy\/Chibi Boy\.glb$/i.test(value)) {
        return null;
    }

    return skin && /dark/i.test(String(skin.name || ""))
        ? value.replace(/Chibi Boy\.glb$/i, "Chibi Boy-Dark.glb")
        : null;
}



function isChibiGirlModel(modelItem) {

    return Boolean(
        modelItem &&
        /girl/i.test(
            String(
                modelItem.name || ""
            )
        )
    );

}


function getProductDetailsKey() {

    if (state.currentCategory === "hirono") {
        if (getHironoMode() === "headKeychain") {
            return "hironoHeadKeychain";
        }

        return getHironoMode() === "keychain"
            ? "hironoKeychain"
            : "hironoStandee";
    }

    if (state.currentCategory === "funko") {
        return isFunkoGirlModel(state.funko.model)
            ? "funkoGirl"
            : "funkoBoy";
    }

    if (state.currentCategory === "chibi") {
        if (state.chibi.productType === "keychain") {
            return isChibiGirlModel(state.chibi.model)
                ? "chibiGirlKeychain"
                : "chibiBoyKeychain";
        }

        return isChibiGirlModel(state.chibi.model)
            ? "chibiGirl"
            : "chibiBoy";
    }

    return "";
}


function getProductDetailsConfig() {
    return PRODUCT_DETAIL_CONFIGS[getProductDetailsKey()] || null;
}


function getProductSizePrice() {

    const prices =
        PRODUCT_SIZE_PRICES[getProductDetailsKey()] || {};

    return prices[productDetails.size] || 0;

}


function getProductDetailsAddon() {

    const config = getProductDetailsConfig();

    if (!config) {
        return 0;
    }

    const selectedBox =
        config.boxes.find(box => box.id === productDetails.box);

    if (config.hirono) {
        const blindBoxPrice = productDetails.blindBoxSelected
            ? (productDetails.blindBox === "set" ? 350 : 150)
            : 0;
        const addonPrices = {
            tearPaper: 50,
            pouch: 50,
            digitalArt: 150
        };

        return (productDetails.figureName.trim() ? 50 : 0) +
            blindBoxPrice +
            productDetails.hironoAddons.reduce(
                (total, addon) => total + (addonPrices[addon] || 0),
                0
            );
    }

    return (productDetails.figureName.trim() ? 50 : 0) +
        (selectedBox ? selectedBox.price : 0);
}


function saveProductDetails() {
    saveCustomizationSnapshot(false, false);
    updateSelectedItemsUI();
    updatePriceDisplay();
    syncWizardPills();
}


function renderSizeSection() {

    const sizeSection =
        document.getElementById("figureSizeSection");

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
        const keepSize = Boolean(productDetails.productKey) &&
            productDetails.productKey.startsWith(state.currentCategory) &&
            productDetails.sizeSelected &&
            config.sizes.includes(productDetails.size);

        productDetails = {
            productKey,
            size: keepSize ? productDetails.size : "",
            sizeSelected: keepSize,
            figureName: "",
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

    // Keep size empty until the customer explicitly chooses one. The first
    // size must not be treated as an automatic selection.
    if (productDetails.size && !config.sizes.includes(productDetails.size)) {
        productDetails.size = "";
    }

    sizeChoiceGrid.innerHTML = config.sizes.length
        ? config.sizes.map(size => `
        <button type="button" class="detail-choice-card${productDetails.sizeSelected && productDetails.size === size ? " selected" : ""}" data-size="${size}">
            <strong>${size.replace(" inches", "\"")}</strong>
            <span>₱${PRODUCT_SIZE_PRICES[productKey]?.[size] || 0}</span>
        </button>
    `).join("")
        : `<p class="detail-hint">No size requirement for this product type.</p>`;

    sizeChoiceGrid.querySelectorAll("[data-size]").forEach(button => {
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

    const requiredSlots = state.currentCategory === "chibi"
        ? ["productType", "model", "skin"]
        : state.currentCategory === "hirono"
            ? ["mode", "model", "skin"]
            : ["model", "skin"];

    if (!requiredSlots.every(slot => Boolean(activeState[slot]))) {
        productDetailsPanel.hidden = true;
        return;
    }

    productDetailsPanel.hidden = false;

    if (productDetails.productKey !== productKey) {
        productDetails = {
            productKey,
            size: "",
            sizeSelected: false,
            figureName: "",
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

    if (productDetails.box && !config.boxes.some(box => box.id === productDetails.box)) {
        productDetails.box = "";
    }

    // Walang box (Chibi): itago BUONG panel at linisin ang inline styles/.box-mode
    // na posibleng naiwan galing sa Funko/Hirono na naunang na-render.
    if (!config.hirono && !config.boxes.length) {
        productDetailsPanel.hidden = true;
        productDetailsPanel.style.display = "none";
        productDetailsPanel.classList.remove("box-mode");
        productDetailsPanel.style.background = "";
        productDetailsPanel.style.border = "";
        productDetailsPanel.style.padding = "";
        productDetailsPanel.style.borderRadius = "";
        return;
    }

    productDetailsPanel.style.display = "";

    const title = document.getElementById("productDetailsTitle");
    const description = document.getElementById("productDetailsDescription");
    const detailsSkinSwatch = document.getElementById("detailsSkinSwatch");

    if (title) title.textContent = config.title;
    if (description) description.textContent = config.description;

    if (detailsSkinSwatch) {
        const skinColor = activeState.skin?.color || "";
        detailsSkinSwatch.hidden = !skinColor;
        detailsSkinSwatch.style.backgroundColor = skinColor || "transparent";
    }

    boxChoiceGrid.innerHTML = config.boxes.map(box => `
        <button type="button" class="detail-choice-card box-choice-card${productDetails.boxSelected && productDetails.box === box.id ? " selected" : ""}" data-box="${box.id}">
            <span class="image-check">✓</span>
            <div class="detail-card-swatch" style="background:${box.swatch || "linear-gradient(145deg,#ffe0ef,#e8ddff)"};"></div>
            <strong>${box.label}</strong>
            <span>${box.price ? `₱${box.price}` : "No additional fee"}</span>
        </button>
    `).join("");

    const figureNameField = document.getElementById("figureNameField");
    // Walang "Figure Name" sa Dress Up - laging nakatago (at walang ₱50 fee)
    if (figureNameField) {
        figureNameField.hidden = true;
    }

    const hasBoxOptions = Boolean(config.hirono) || config.boxes.length > 0;

    // Funko/Hirono: "BOX / ADD-ONS" header lang, tago ang title/description (.box-mode)
    // gamitin ang productDetailsPanel sa labas, huwag mag-declare ulit ng const dito
    if (productDetailsPanel) {
        productDetailsPanel.classList.toggle("box-mode", hasBoxOptions);
        // Inline styles para hindi umasa sa commission.css (kung na-cache
        // ang CSS, dito pa rin mawawala ang pink background at border).
        productDetailsPanel.style.background = hasBoxOptions ? "none" : "";
        productDetailsPanel.style.border = hasBoxOptions ? "none" : "";
        productDetailsPanel.style.padding = hasBoxOptions ? "0" : "";
        productDetailsPanel.style.borderRadius = hasBoxOptions ? "0" : "";
    }

    // NOTE: gaya ng design-step-jump, may CSS rules dito na mas mataas ang
    // specificity kaysa sa default [hidden] ng browser, kaya inline display
    // ang ginagamit para siguradong matago talaga.
    [title, description].forEach(element => {
        if (!element) return;
        element.hidden = hasBoxOptions;
        element.style.display = hasBoxOptions ? "none" : "";
    });

    const productDetailsKicker = document.getElementById("productDetailsKicker");
    if (productDetailsKicker) {
        productDetailsKicker.textContent = hasBoxOptions
            ? "BOX / ADD-ONS"
            : "FIGURE DETAILS";
        // Parehong laki/kulay/font ang dalawang label na ito, hindi depende sa hasBoxOptions
        productDetailsKicker.style.display = "block";
        productDetailsKicker.style.fontSize = "20px";
        productDetailsKicker.style.letterSpacing = "1.5px";
        productDetailsKicker.style.lineHeight = "1.25";
        productDetailsKicker.style.margin = "0";
        // Inline color para hindi umasa sa cache ng CSS - may generic
        // rule (".section-title span") na gumagawa nito na gray kung
        // wala ito.
        productDetailsKicker.style.color = "#bd5c8d";
    }

    const boxChoiceGroup = document.getElementById("boxChoiceGroup");
    if (boxChoiceGroup) {
        boxChoiceGroup.hidden = config.hirono || !config.boxes.length;
        const boxLabel = boxChoiceGroup.querySelector(".detail-label");
        if (boxLabel) {
            boxLabel.textContent = config.boxLabel || "Box";
        }
    }

    const boxDetailFields = document.getElementById("boxDetailFields");
    const selectedBox = config.boxes.find(box => box.id === productDetails.box);
    const canEditFunkoBoxDetails = Boolean(selectedBox?.details);
    if (boxDetailFields) {
        // Chibi (walang box): itago ang buong fields. Hindi sapat ang .hidden lang -
        // may CSS rule (display:grid) na mas mataas ang specificity, kaya inline
        // display ang ginagamit para talagang matago.
        const hideBoxDetailFields = config.hirono || !config.boxes.length;
        boxDetailFields.hidden = hideBoxDetailFields;
        boxDetailFields.style.display = hideBoxDetailFields ? "none" : "";
    }

    const boxLockedHint = document.getElementById("boxLockedHint");
    if (boxLockedHint) {
        boxLockedHint.hidden = config.hirono || !config.boxes.length || canEditFunkoBoxDetails;
    }

    [boxNameInput, boxNumberInput, boxColorInput].forEach(input => {
        if (input) input.disabled = !canEditFunkoBoxDetails;
    });

    const hironoFields = document.getElementById("hironoDetailFields");
    const hironoBlindBoxGrid = document.getElementById("hironoBlindBoxGrid");
    const hironoAddonGrid = document.getElementById("hironoAddonGrid");
    const hironoBoxDesignGrid = document.getElementById("hironoBoxDesignGrid");
    const hironoBoxFields = document.getElementById("hironoBoxFields");

    if (hironoFields) hironoFields.hidden = !config.hirono;

    if (config.hirono && hironoBlindBoxGrid && hironoAddonGrid && hironoBoxDesignGrid && hironoBoxFields) {
        hironoBlindBoxGrid.innerHTML = [
            { id: "regular", label: "Regular Blind Box", price: 150, image: "figures/hirono/hirono-style.jpg" },
            { id: "set", label: "Blind Box Set", price: 350, image: "boxes/blind-box-set.jpg" }
        ].map(({ id, label, price, image }) => `
            <button type="button" class="detail-choice-card box-choice-card${productDetails.blindBoxSelected && productDetails.blindBox === id ? " selected" : ""}" data-blind-box="${id}">
                <span class="image-check">✓</span>
                <img class="detail-card-image" src="../Image/${image}" alt="${label}">
                <strong>${label}</strong>
                <span>₱${price}</span>
            </button>
        `).join("");

        const addons = [
            { id: "tearPaper", label: "Tear Blind Paper", price: 50 },
            { id: "pouch", label: "Pouch", price: 50 },
            { id: "digitalArt", label: "Digital Art (Soft Copy) w/ Photo Card", price: 150 }
        ].map(addon => ({ ...addon, image: "figures/hirono/hirono-style.jpg" }));

        hironoAddonGrid.innerHTML = addons.map(({ id, label, price, image }) => `
            <button type="button" class="detail-choice-card box-choice-card${productDetails.hironoAddons.includes(id) ? " selected" : ""}" data-addon="${id}">
                <span class="image-check">✓</span>
                <img class="detail-card-image" src="../Image/${image}" alt="${label}">
                <strong>${label}</strong>
                <span>₱${price}</span>
            </button>
        `).join("");

        hironoBoxDesignGrid.innerHTML = [
            { id: "checkered", label: "Checkered", image: "boxes/hirono-box-checkered.jpg" },
            { id: "peek", label: "Hirono Peek", image: "boxes/hirono-box-peek.jpg" }
        ].map(({ id, label, image }) => `
            <button type="button" class="detail-choice-card box-choice-card${productDetails.boxDesignSelected && productDetails.boxDesign === id ? " selected" : ""}" data-box-design="${id}">
                <span class="image-check">✓</span>
                <img class="detail-card-image" src="../Image/${image}" alt="${label}">
                <strong>${label}</strong>
                <span>Choose box design</span>
            </button>
        `).join("");

        const colorField = productDetails.boxDesign === "peek"
            ? `<select class="product-detail-input" data-detail-field="boxColor"><option value="">Select Box Color</option><option value="Wood">Wood</option><option value="Black & White">Black &amp; White</option></select>`
            : `<input class="product-detail-input" data-detail-field="boxColor" value="${productDetails.boxColor}" maxlength="30" placeholder="Example: Cream White">`;

        const formattedBoxDate = formatMonthDay(productDetails.boxDateYmd);
        const [selectedMonth = "", selectedDay = ""] = formattedBoxDate.split("/");
        const monthOptions = [
            ["01", "January"], ["02", "February"], ["03", "March"],
            ["04", "April"], ["05", "May"], ["06", "June"],
            ["07", "July"], ["08", "August"], ["09", "September"],
            ["10", "October"], ["11", "November"], ["12", "December"]
        ].map(([value, label]) => `<option value="${value}"${selectedMonth === value ? " selected" : ""}>${label}</option>`).join("");
        const dayOptions = Array.from({ length: 31 }, (_, index) => {
            const value = String(index + 1).padStart(2, "0");
            return `<option value="${value}"${selectedDay === value ? " selected" : ""}>${index + 1}</option>`;
        }).join("");

        hironoBoxFields.innerHTML = `
            <label><span class="detail-label">Box Color</span>${colorField}</label>
            <label><span class="detail-label">Nickname</span><input class="product-detail-input" data-detail-field="boxNickname" value="${productDetails.boxNickname}" maxlength="30" placeholder="Example: Bubbles"></label>
            <label><span class="detail-label">Letter (Short Love Letter)</span><textarea class="product-detail-input" data-detail-field="boxLetter" rows="4" placeholder="Write a short love letter/message here...">${productDetails.boxLetter}</textarea></label>
            <div class="product-detail-date-row">
                <label><span class="detail-label">Month</span><select class="product-detail-input" id="boxDateMonth"><option value="">Choose month</option>${monthOptions}</select></label>
                <label><span class="detail-label">Day</span><select class="product-detail-input" id="boxDateDay"><option value="">Choose day</option>${dayOptions}</select></label>
            </div>
        `;

        hironoAddonGrid.querySelectorAll("[data-addon]").forEach(button => {
            button.addEventListener("click", () => {
                const addon = button.dataset.addon;
                productDetails.hironoAddons = productDetails.hironoAddons.includes(addon)
                    ? productDetails.hironoAddons.filter(item => item !== addon)
                    : [...productDetails.hironoAddons, addon];
                renderProductDetailsPanel();
                saveProductDetails();
            });
        });

            hironoBlindBoxGrid.querySelectorAll("[data-blind-box]").forEach(button => {
            button.addEventListener("click", () => {
                productDetails.blindBox = button.dataset.blindBox;
                productDetails.blindBoxSelected = true;
                renderProductDetailsPanel();
                saveProductDetails();
            });
        });

        hironoBoxDesignGrid.querySelectorAll("[data-box-design]").forEach(button => {
            button.addEventListener("click", () => {
                productDetails.boxDesign = button.dataset.boxDesign;
                productDetails.boxDesignSelected = true;
                if (productDetails.boxDesign === "peek" && !["Wood", "Black & White"].includes(productDetails.boxColor)) {
                    productDetails.boxColor = "";
                }
                renderProductDetailsPanel();
                saveProductDetails();
            });
        });
    }

        boxChoiceGrid.querySelectorAll("[data-box]").forEach(button => {
        button.addEventListener("click", () => {
            const boxId = button.dataset.box;

            // Toggle: pag-click ulit sa parehong selected box, i-unselect ito
            // (kapareho ng toggle behavior ng Optional Extras).
            const isAlreadySelected =
                productDetails.boxSelected &&
                productDetails.box === boxId;

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

    if (figureNameInput) figureNameInput.value = productDetails.figureName;
    if (boxNameInput) boxNameInput.value = productDetails.boxName;
    if (boxNumberInput) boxNumberInput.value = productDetails.boxNumber;
    if (boxColorInput) boxColorInput.value = productDetails.boxColor;

        productDetailsPanel.querySelectorAll("[data-detail-field]").forEach(input => {
        input.value = productDetails[input.dataset.detailField] || "";
        input.oninput = () => {
            productDetails[input.dataset.detailField] = input.value;
            saveProductDetails();
        };
        input.onchange = input.oninput;
    });

    const detailsPrice = document.getElementById("productDetailsPrice");
    if (detailsPrice) {
        detailsPrice.textContent = `₱${getProductDetailsAddon()}`;
    }
}




function setVisibleById(id, visible) {

    const element =
        document.getElementById(id);


    if (element) {
        element.hidden = !visible;
    }

}


function setFigurePromptVisible(visible) {

    if (figureArea) {
        figureArea.dataset.promptVisible = visible ? "true" : "false";
    }

}


function clearSelectedClasses(selector) {

    document.querySelectorAll(selector).forEach(
        function(card) {
            card.classList.remove("selected");
        }
    );

}


function markSelectedCard(card) {

    const figure =
        card.dataset.figure;


    const slot =
        card.dataset.slot;


    clearSelectedClasses(
        `[data-figure="${figure}"][data-slot="${slot}"]`
    );


    card.classList.add(
        "selected"
    );

    scheduleClothingUI();

}


function disposeObject3D(object3D) {

    if (!object3D) {
        return;
    }


    object3D.traverse(
        function(node) {

            if (!node.isMesh) {
                return;
            }


            if (node.geometry) {
                node.geometry.dispose();
            }


            if (!node.material) {
                return;
            }


            const materials =
                Array.isArray(
                    node.material
                )
                    ? node.material
                    : [node.material];


            materials.forEach(
                function(material) {

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

                }
            );

        }
    );

}


function removeFromScene(object3D) {

    if (!object3D) {
        return;
    }


    if (object3D.parent) {
        object3D.parent.remove(
            object3D
        );
    }
    else if (scene) {
        scene.remove(
            object3D
        );
    }


    disposeObject3D(
        object3D
    );

}


function clearCurrentScene() {

    removeFromScene(
        bodyModel
    );

    bodyModel = null;


    if (figureShadow) {
        figureShadow.visible = false;
    }


    Object.keys(
        currentObjects
    ).forEach(
        function(slot) {

            removeFromScene(
                currentObjects[slot]
            );

            currentObjects[slot] = null;

        }
    );

}


function configureModel(model) {

    model.traverse(
        function(node) {

            if (!node.isMesh) {
                return;
            }

            // Preserve the GLB's original acrylic stand appearance.
            if (String(node.name || "").toLowerCase().includes("acrylic_stand")) {
                return;
            }


            node.castShadow = true;
            node.receiveShadow = true;


            if (!node.material) {
                return;
            }


            const materials =
                Array.isArray(
                    node.material
                )
                    ? node.material
                    : [node.material];


            materials.forEach(
                function(material) {

                    material.transparent = false;
                    material.needsUpdate = true;

                }
            );

        }
    );

}


function hasRenderableMeshes(object3D) {

    if (!object3D) {
        return false;
    }


    let meshCount = 0;


    object3D.traverse(
        function(node) {
            if (node.isMesh) {
                meshCount += 1;
            }
        }
    );


    return meshCount > 0;

}


function shouldSkipMaterial(material, skipFragments) {

    if (!material || !skipFragments.length) {
        return false;
    }


    const materialName =
        String(
            material.name || ""
        ).toLowerCase();


    return skipFragments.some(
        function(fragment) {
            return materialName.includes(
                fragment
            );
        }
    );

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
    ].forEach(
        function(mapKey) {
            if (material[mapKey]) {
                material[mapKey] = null;
            }
        }
    );

}


function applyColorToModel(model, color, options) {

    if (!model) {
        return false;
    }


    const settings =
        options || {};


    const targetColor =
        new THREE.Color(
            color
        );

    const skipFragments =
        Array.isArray(
            settings.skipMaterialFragments
        )
            ? settings.skipMaterialFragments.map(
                function(fragment) {
                    return String(fragment).toLowerCase();
                }
            )
            : [
                "design"
            ];

    const skipNodeFragments = Array.isArray(settings.skipNodeFragments)
        ? settings.skipNodeFragments.map(fragment => String(fragment).toLowerCase())
        : [];

    // Katulad ng skipNodeFragments pero tinitingnan din ang mga parent group ng mesh
    // (hal. group na "Shorts" na ang laman ay mga mesh na "Object_3"). Hindi kasama ang
    // mismong root ng model para hindi madamay ang buong figure.
    const skipAncestorFragments = Array.isArray(settings.skipAncestorFragments)
        ? settings.skipAncestorFragments.map(fragment => String(fragment).toLowerCase())
        : [];

    const isUnderSkippedGroup = function(node) {
        if (!skipAncestorFragments.length) {
            return false;
        }

        let current = node;

        while (current && current !== model) {
            const name = String(current.name || "").toLowerCase();

            if (skipAncestorFragments.some(fragment => name.includes(fragment))) {
                return true;
            }

            current = current.parent;
        }

        return false;
    };


    let changed =
        false;


    model.traverse(
        function(node) {

            const nodeName = String(node.name || "").toLowerCase();
            if (skipNodeFragments.some(fragment => nodeName.includes(fragment))) {
                return;
            }

            if (isUnderSkippedGroup(node)) {
                return;
            }

            if (
                !node.isMesh ||
                !node.material
            ) {
                return;
            }


            const materials =
                Array.isArray(
                    node.material
                )
                    ? node.material
                    : [node.material];


            const nextMaterials =
                materials.map(
                    function(material) {

                        if (shouldSkipMaterial(
                            material,
                            skipFragments
                        )) {
                            return material;
                        }


                        const newMaterial =
                            material.clone();

                        if (settings.clearTextureMaps) {
                            clearMaterialTextureMaps(
                                newMaterial
                            );
                        }

                        if (newMaterial.color) {
                            newMaterial.color.copy(
                                targetColor
                            );
                        }
                        newMaterial.needsUpdate = true;


                        changed = true;


                        return newMaterial;

                    }
                );


            node.material =
                Array.isArray(
                    node.material
                )
                    ? nextMaterials
                    : nextMaterials[0];

        }
    );


    return changed;

}


function applyColorToMeshList(meshes, color, options) {

    if (!Array.isArray(meshes) || !meshes.length) {
        return false;
    }


    const settings =
        options || {};


    const targetColor =
        new THREE.Color(
            color
        );


    const skipFragments =
        Array.isArray(
            settings.skipMaterialFragments
        )
            ? settings.skipMaterialFragments.map(
                function(fragment) {
                    return String(fragment).toLowerCase();
                }
            )
            : [
                "design"
            ];


    let changed =
        false;


    meshes.forEach(
        function(mesh) {

            if (!mesh || !mesh.material) {
                return;
            }


            const materials =
                Array.isArray(
                    mesh.material
                )
                    ? mesh.material
                    : [mesh.material];


            const nextMaterials =
                materials.map(
                    function(material) {

                        if (shouldSkipMaterial(
                            material,
                            skipFragments
                        )) {
                            return material;
                        }


                        const newMaterial =
                            material.clone();

                        if (settings.clearTextureMaps) {
                            clearMaterialTextureMaps(
                                newMaterial
                            );
                        }

                        if (newMaterial.color) {
                            newMaterial.color.copy(
                                targetColor
                            );
                        }
                        newMaterial.needsUpdate = true;


                        changed = true;


                        return newMaterial;

                    }
                );


            mesh.material =
                Array.isArray(
                    mesh.material
                )
                    ? nextMaterials
                    : nextMaterials[0];

        }
    );


    return changed;

}


function isShoeLikeMesh(mesh) {

    if (!mesh) {
        return false;
    }


    const searchableText =
        [
            mesh.name,
            mesh.parent && mesh.parent.name,
            mesh.material &&
                !Array.isArray(mesh.material) &&
                mesh.material.name
        ].filter(Boolean).join(" ").toLowerCase();


    return /shoe|sneaker|boot|foot|sole/.test(
        searchableText
    );

}


function getBottomModelParts(model) {

    const meshes = [];


    if (!model) {
        return {
            pants: [],
            shoes: []
        };
    }


    model.traverse(
        function(node) {
            if (node.isMesh) {
                meshes.push(
                    node
                );
            }
        }
    );


    if (!meshes.length) {
        return {
            pants: [],
            shoes: []
        };
    }


    const indexedMeshes =
        meshes.map(
            function(mesh, index) {
                const box =
                    new THREE.Box3().setFromObject(
                        mesh
                    );


                return {
                    mesh,
                    index,
                    centerY: box.getCenter(
                        new THREE.Vector3()
                    ).y
                };
            }
        ).sort(
            function(a, b) {
                return b.centerY - a.centerY;
            }
        );


    const shoeTaggedMeshes =
        indexedMeshes.filter(
            function(item) {
                return isShoeLikeMesh(
                    item.mesh
                );
            }
        );


    const pantsTaggedMeshes =
        indexedMeshes.filter(
            function(item) {
                return !isShoeLikeMesh(
                    item.mesh
                );
            }
        );


    if (
        shoeTaggedMeshes.length &&
        pantsTaggedMeshes.length
    ) {
        return {
            pants: pantsTaggedMeshes.map(
                function(item) {
                    return item.mesh;
                }
            ),
            shoes: shoeTaggedMeshes.map(
                function(item) {
                    return item.mesh;
                }
            )
        };
    }


    let splitIndex = 1;
    let largestGap = 0;


    for (let index = 0; index < indexedMeshes.length - 1; index += 1) {
        const currentMesh =
            indexedMeshes[index];
        const nextMesh =
            indexedMeshes[index + 1];
        const gap =
            currentMesh.centerY - nextMesh.centerY;


        if (gap > largestGap) {
            largestGap = gap;
            splitIndex = index + 1;
        }
    }


    if (largestGap <= 0) {
        splitIndex = Math.max(
            1,
            Math.ceil(
                indexedMeshes.length / 2
            )
        );
    }
    else {
        splitIndex = Math.min(
            Math.max(
                1,
                splitIndex
            ),
            indexedMeshes.length - 1
        );
    }


    return {
        pants: indexedMeshes
            .slice(
                0,
                splitIndex
            )
            .map(
                function(item) {
                    return item.mesh;
                }
            ),
        shoes: indexedMeshes
            .slice(
                splitIndex
            )
            .map(
                function(item) {
                    return item.mesh;
                }
            )
    };

}


function applyBottomPartColors(model, pantsColor) {

    const parts =
        getBottomModelParts(
            model
        );


    if (pantsColor) {
        applyColorToMeshList(
            parts.pants,
            pantsColor,
            {
                clearTextureMaps: true,
                skipMaterialFragments: [
                    "design",
                    "logo",
                    "print"
                ]
            }
        );
    }
}


// Ang Funko Girl ay may sarili na ngayong "Funko pop-girl.glb" (may sando at socks),
// kaya wala nang kailangang i-map. Ang lumang saved design na naka-"funko girl.glb"
// ay ituturo na rin sa bagong file.
const FUNKO_GIRL_GLB_ALIASES = {
    "funko girl.glb": "Funko pop-girl.glb"
};


// GLB folders: bawat figure / product type / gender may sariling folder sa
// dressup-assets/GLB_files/ (hal. "Hirono Standee Boy/"). Ang mga lumang path
// (naka-save sa lumang orders/drafts, walang folder o "Chibi Boy/") ay kusang
// itinuturo sa bagong folder. Kapag hindi pa nailipat ang file, sinusubukan
// din ang lumang lugar para walang masira habang naglilipat.
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
// lumang pangalan ng folder -> bagong pangalan
const GLB_FOLDER_ALIASES = {
    "Chibi Boy/": "Chibi Standee Boy/",
    "Funko Pop Boy/": "Funko Pop Standee Boy/",
    "Funko Pop Girl/": "Funko Pop Standee Girl/"
};

function relocateGlbPath(path) {
    const value = String(path || "");
    const marker = "GLB_files/";
    const at = value.indexOf(marker);
    if (at === -1) {
        return value;
    }
    const head = value.slice(0, at + marker.length);
    const rest = value.slice(at + marker.length);
    for (const oldFolder in GLB_FOLDER_ALIASES) {
        if (rest.startsWith(oldFolder)) {
            return head + GLB_FOLDER_ALIASES[oldFolder] + rest.slice(oldFolder.length);
        }
    }
    if (rest.indexOf("/") === -1) {
        const folder = GLB_FOLDER_BY_FILE[decodeURIComponent(rest)];
        if (folder) {
            return head + folder + "/" + rest;
        }
    }
    return value;
}

// mga lumang lugar ng file (bago/habang inililipat) - fallback lang kapag
// wala pa ang file sa bagong folder: walang folder, o lumang pangalan ng folder
function legacyGlbPaths(path) {
    const value = String(path || "");
    const marker = "GLB_files/";
    const at = value.indexOf(marker);
    if (at === -1) {
        return [];
    }
    const head = value.slice(0, at + marker.length);
    const rest = value.slice(at + marker.length);
    const slash = rest.indexOf("/");
    if (slash === -1) {
        return [];
    }
    const folder = rest.slice(0, slash + 1);
    const fileName = rest.slice(slash + 1);
    const candidates = [head + fileName];
    for (const oldFolder in GLB_FOLDER_ALIASES) {
        if (GLB_FOLDER_ALIASES[oldFolder] === folder) {
            candidates.push(head + oldFolder + fileName);
        }
    }
    return candidates;
}

function resolveModelPath(path) {
    let value = String(path || "");
    const fileName = value.split("/").pop();
    const alias = FUNKO_GIRL_GLB_ALIASES[fileName];
    if (alias) {
        value = value.slice(0, value.length - fileName.length) + alias;
    }
    return relocateGlbPath(value);
}


function loadGLTF(path) {

    path = resolveModelPath(path);

    if (!path) {
        const error =
            new Error("Model path is missing.");

        console.error(
            "[Figurify 3D] Model path is missing.",
            error
        );

        return Promise.reject(
            error
        );
    }

    return new Promise(
        function(resolve, reject) {

            const onLoaded = function(gltf) {
                resolve(
                    gltf.scene
                );
            };

            // subukan muna ang bagong folder, tapos ang mga lumang lugar
            // (kapag hindi pa nailipat ang file)
            const candidates = [path].concat(legacyGlbPaths(path));

            const tryLoad = function(index) {
                loader.load(
                    candidates[index],
                    onLoaded,
                    undefined,
                    function(error) {
                        if (index + 1 < candidates.length) {
                            tryLoad(index + 1);
                            return;
                        }

                        console.error(
                            `[Figurify 3D] Failed to load: ${candidates.join(" / ")}`,
                            error
                        );

                        reject(
                            error
                        );
                    }
                );
            };

            tryLoad(0);

        }
    );

}






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
        const shadowCanvas =
            document.createElement("canvas");

        shadowCanvas.width = 256;
        shadowCanvas.height = 256;

        const context =
            shadowCanvas.getContext("2d");

        const gradient =
            context.createRadialGradient(
                128, 128, 0,
                128, 128, 128
            );

        gradient.addColorStop(0, "rgba(0, 0, 0, 0.45)");
        gradient.addColorStop(0.5, "rgba(0, 0, 0, 0.2)");
        gradient.addColorStop(1, "rgba(0, 0, 0, 0)");

        context.fillStyle = gradient;
        context.fillRect(0, 0, 256, 256);

        figureShadow =
            new THREE.Mesh(
                new THREE.PlaneGeometry(1, 1),
                new THREE.MeshBasicMaterial(
                    {
                        map: new THREE.CanvasTexture(shadowCanvas),
                        transparent: true,
                        depthWrite: false
                    }
                )
            );

        figureShadow.rotation.x = -Math.PI / 2;
        figureShadow.renderOrder = -1;

        scene.add(
            figureShadow
        );
    }


    const footprint =
        Math.max(
            size.x,
            size.z
        ) * 1.3;


    figureShadow.scale.set(
        footprint,
        footprint * 0.75,
        1
    );

    figureShadow.position.set(
        center.x,
        box.min.y + 0.001,
        center.z
    );

    figureShadow.visible = true;

}


// Camera focus: sa Hair step, lumalapit ang camera sa ulo/buhok at
// naka-lock ang zoom (laging buo ang ulo at buhok sa view). Sa ibang step
// (Top, Bottom, Shoes, atbp.) buong figure at normal na zoom.
const HEAD_FOCUS_SLOTS = ["hair", "girlHair", "keychainHair"];
const DEFAULT_MIN_DISTANCE = 1.5;
const DEFAULT_MAX_DISTANCE = 12;
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

    root.traverse(function(node) {
        if (!node.isMesh || !node.geometry || !node.geometry.attributes.position) {
            return;
        }

        let named = false;
        for (let n = node; n && n !== root.parent; n = n.parent) {
            if (/key/i.test(n.name || "")) {
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

    const maxCount = Math.max(...parts.map(part => part.count));

    const kept = parts.filter(part => {
        const smallTopPart = part.count < maxCount * 0.05 &&
            part.box.max.y >= fullBox.max.y - 0.0001;
        return !part.named && !smallTopPart;
    });

    if (!kept.length || kept.length === parts.length) {
        return fullBox;
    }

    const box = new THREE.Box3();
    kept.forEach(part => box.union(part.box));

    return box.isEmpty() ? fullBox : box;

}

function getHeadFocusBox(fullBox) {

    const height =
        fullBox.max.y - fullBox.min.y;

    // itaas na ~33% ng figure = ulo
    const headBox =
        new THREE.Box3(
            new THREE.Vector3(fullBox.min.x, fullBox.max.y - height * 0.40, fullBox.min.z),
            fullBox.max.clone()
        );

    // fixed ang frame ng ulo (hindi nagbabago kahit anong buhok ang pinili)

    return headBox;

}

function frameFigure() {

    if (!bodyModel || !camera || !controls) {
        return;
    }


    const box =
        new THREE.Box3();


    box.expandByObject(
        bodyModel
    );


    // body lang ang basehan ng frame - para hindi tumatalon/nagzo-zoom ang figure
    // tuwing may papalitang damit o buhok

    if (box.isEmpty()) {
        return;
    }


    const size =
        box.getSize(
            new THREE.Vector3()
        );


    const center =
        box.getCenter(
            new THREE.Vector3()
        );


    const maxSize =
        Math.max(
            size.x,
            size.y,
            size.z
        );


    updateFigureShadow(
        box,
        size,
        center
    );


    // Buong figure: ang buhok ay nasa labas ng body box (kaya putol sa taas), kaya dinagdagan
    // ng espasyo sa TAAS ang frame at itinaas ang gitna ng camera, tapos nilayo pa.
    const hairHeadroom = size.y * 0.2;
    let focusSize = Math.max(size.x, size.y + hairHeadroom, size.z) * 0.95;
    center.y += hairHeadroom / 2;

    if (cameraFocus === "head") {
        const figureBox = getFigureOnlyBox(bodyModel, box);
        const headOnlyModel = state.currentCategory === "hirono" &&
            getHironoMode() === "headKeychain";
        const headBox = headOnlyModel ? figureBox : getHeadFocusBox(figureBox);

        // dagdag na espasyo sa TAAS para hindi putol ang mataas na buhok
        // (ang buhok ay nasa labas ng body box kaya hindi ito nabibilang sa frame)
        const headHeight = headBox.max.y - headBox.min.y;
        headBox.max.y += headHeight * (headOnlyModel ? 0.18 : 0.32);

        const headSize = headBox.getSize(new THREE.Vector3());

        headBox.getCenter(center);
        focusSize = Math.max(headSize.x, headSize.y, headSize.z) * 1.3;
    }


    const distance =
        focusSize /
        (
            2 *
            Math.tan(
                THREE.MathUtils.degToRad(
                    camera.fov / 2
                )
            )
        );


    // panatilihin ang kasalukuyang pag-ikot ng figure (huwag bumalik sa harap)
    let azimuth = 0;
    if (controls.target) {
        const prev = camera.position.clone().sub(controls.target);
        if (Math.abs(prev.x) + Math.abs(prev.z) > 0.0001) {
            azimuth = Math.atan2(prev.x, prev.z);
        }
    }

    camera.position.set(
        center.x + Math.sin(azimuth) * distance * 1.35,
        center.y,
        center.z + Math.cos(azimuth) * distance * 1.35
    );

    camera.lookAt(
        center
    );


    controls.target.copy(
        center
    );

    if (cameraFocus === "head") {
        // naka-lock ang zoom sa Hair step - laging buo ang ulo at buhok
        const lockedDistance = camera.position.distanceTo(center);
        controls.enableZoom = false;
        controls.minDistance = lockedDistance;
        controls.maxDistance = lockedDistance;
    }
    else {
        controls.enableZoom = false;
        controls.minDistance = Math.min(DEFAULT_MIN_DISTANCE, camera.position.distanceTo(center) * 0.6);
        controls.maxDistance = DEFAULT_MAX_DISTANCE;
    }

    controls.update();

}


function resize3D() {

    if (
        !renderer ||
        !camera ||
        !figureArea
    ) {
        return;
    }


    const width =
        figureArea.clientWidth;


    const height =
        figureArea.clientHeight;


    if (
        width <= 0 ||
        height <= 0
    ) {
        return;
    }


    renderer.setSize(
        width,
        height,
        false
    );


    camera.aspect =
        width / height;


    camera.updateProjectionMatrix();

}


function getCurrentOrderTotal(activeState) {

    const rushFee = getCommissionRushFee();

    if (!activeState || !activeState.model) {
        return rushFee;
    }

    return getActiveStateTotal(activeState) +
        getProductSizePrice() +
        getProductDetailsAddon() +
        rushFee;

}


function updatePriceDisplay() {

    const activeState =
        getActiveState();


    if (dressUpTotalPrice) {

        // habang may ibang figure sa preview, gamitin ang total ng kasalukuyang figure
        const total = previewLock
            ? currentTotalCache
            : getCurrentOrderTotal(
                activeState
            );

        if (!previewLock) {
            currentTotalCache = total;
        }

        const cartTotal =
            isFinalSummaryPage ? getDressUpCartTotal() : 0;

        dressUpTotalPrice.textContent =
            `₱${total + cartTotal}`;

    }

    refreshOptionPriceLabels();

    renderPreviewSummary();

    if (isFinalSummaryPage && typeof renderDressUpCartList === "function") {
        renderDressUpCartList();
    }

    applyCartSelectionToDetails();

}


function buildFigureDetailRows(activeState, categoryValue) {

    const rows = [];
    // "group" = anong column sa finalsummary.php: "figure" (default) o "box".
    // Iisang listahan lang sa commission.php sidebar, kaya hindi ito ginagamit doon.
    const addRow = (header, value, price = null, group = "figure", colorValue = "") => {
        if (!header) {
            return;
        }

        rows.push({ header, value: value || "Not selected", price, group, colorValue });
    };

    // Bawat row (Figure Style, Product Type, Gender, Skin Tone, Size, Hair, Top,
    // Bottom, Shoes) lumalabas lang kapag may aktwal na napili na, hindi "Not selected" placeholder.
    if (categoryValue) {
        addRow("Figure Style", categoryValue);
    }

    // Personalization fields (Box Name/Number/Color, atbp.) ipinapakita sa Box/Add-ons
    // column kahit walang presyo. Figure Name may ₱50 fee kaya nasa Figure Details pa rin.
    if (
        (state.currentCategory === "funko" || state.currentCategory === "chibi") &&
        productDetails.figureName.trim()
    ) {
        addRow("Figure Name", productDetails.figureName.trim(), 50);
    }

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
                addRow("Product Type", productTypeLabel);
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

        let skinRowIndex = -1;

        // kulay ng damit/hair/etc. na pinili (galing sa parehong data na ipinapasa sa order)
        const designColorBySlot = {};
        try {
            buildDressUpDesignData(activeState).slots.forEach(s => {
                designColorBySlot[s.slot] = s;
            });
        }
        catch (error) {
            // ignore
        }

        getActiveSlotOrder()
            .forEach(slot => {

                if (slot.toLowerCase().endsWith("color")) {
                    return;
                }

                // Skin Tone ay sa Figure Details (Gender) lang pinipili - hindi na ipinapakita sa listahan
                if (slot === "skin") {
                    return;
                }

                const item = activeState[slot];

                if (!item) {
                    return;
                }

                const slotLabel = slotLabels[slot] || getDesignStepLabel(slot);
                const itemPrice = getItemPrice(item);
                const picked = designColorBySlot[slot];
                addRow(
                    slotLabel,
                    picked && picked.colorName ? item.name + " • " + picked.colorName : item.name,
                    itemPrice > 0 ? itemPrice : null,
                    "figure",
                    picked ? picked.colorValue : (slot === "skin" && item.color ? item.color : "")
                );

                if (slot === "model") {
                    skinRowIndex = rows.length - 1;
                }
            });

        if (sizeStepApplies && productDetails.size) {
            const sizePrice = getProductSizePrice();
            const sizeRow = {
                header: "Size",
                value: productDetails.size,
                price: sizePrice || null
            };

            if (skinRowIndex >= 0) {
                rows.splice(skinRowIndex + 1, 0, sizeRow);
            } else {
                rows.push(sizeRow);
            }
        }
    }

    if (state.currentCategory === "hirono") {
        if (productDetails.blindBoxSelected && productDetails.blindBox === "set") {
            addRow("Blind Box Type", "Blind Box Set", 350, "box");
        } else if (productDetails.blindBoxSelected && productDetails.blindBox === "regular") {
            addRow("Blind Box Type", "Regular Blind Box", 150, "box");
        }

        const addonOptions = {
            tearPaper: { label: "Tear Blind Paper", price: 50 },
            pouch: { label: "Pouch", price: 50 },
            digitalArt: { label: "Digital Art (Soft Copy) w/ Photo Card", price: 150 }
        };

        productDetails.hironoAddons.forEach(addon => {
            const selectedAddon = addonOptions[addon];
            if (selectedAddon) {
                addRow("Add-on", selectedAddon.label, selectedAddon.price, "box");
            }
        });

        // Box Design + Hirono personalization fields, makikita sa Box/Add-ons column, walang presyo
        if (productDetails.boxDesignSelected) {
            const boxDesignLabels = {
                checkered: "Checkered",
                peek: "Hirono Peek"
            };
            addRow(
                "Box Design",
                boxDesignLabels[productDetails.boxDesign] || productDetails.boxDesign,
                null,
                "box"
            );
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
                addRow("Date", formattedDate, null, "box");
            }
        }

        const boxDateMonth = document.getElementById("boxDateMonth");
        const boxDateDay = document.getElementById("boxDateDay");
        const updateBoxDate = () => {
            productDetails.boxDateYmd = boxDateMonth?.value && boxDateDay?.value
                ? `${boxDateMonth.value}/${boxDateDay.value}`
                : "";
            saveProductDetails();
        };
        boxDateMonth?.addEventListener("change", updateBoxDate);
        boxDateDay?.addEventListener("change", updateBoxDate);
    }

    const config = getProductDetailsConfig();
    const selectedBox = config?.boxes?.find(box => box.id === productDetails.box);
    if (selectedBox) {
        addRow("Box", selectedBox.label, selectedBox.price, "box");
    }


    return rows;

}


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

    rows.forEach(({ header, value, price, colorValue }) => {
        const hasPrice = price !== null && price !== undefined && Number(price) > 0;

        const item = document.createElement("div");
        item.className = hasPrice
            ? "figure-summary-item"
            : "figure-summary-item summary-no-price";

        const info = document.createElement("div");
        info.className = "figure-summary-info";

        // Header word muna (bold, sa itaas) - tapos ang pinili sa
        // ibaba, hindi bold.
        const headerElement = document.createElement("div");
        headerElement.className = "figure-summary-name";
        headerElement.textContent = header;
        info.appendChild(headerElement);

        if (value) {
            const valueElement = document.createElement("div");
            valueElement.className = "figure-summary-description";
            if (colorValue) {
                const dot = document.createElement("i");
                dot.className = "summary-color-dot";
                dot.style.background = colorValue;
                valueElement.appendChild(dot);
            }
            valueElement.appendChild(document.createTextNode(value));
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


// Order Summary (Dress Up, commission.php) - simple at modern:
// Order info (listahan), bawat napili may larawan + presyo, Total sa ibaba.
function findDressUpCard(category, slot, model) {
    if (!category || !slot || !model) {
        return null;
    }
    return document.querySelector(
        `[data-figure="${category}"][data-slot="${slot}"][data-model="${escapeSelectorValue(model)}"]`
    );
}

function buildOrderSummaryItem(options) {
    const li = document.createElement("li");
    li.className = "os-item";

    const thumb = document.createElement("div");
    thumb.className = "os-thumb";
    if (options.image) {
        const img = document.createElement("img");
        img.src = options.image;
        img.alt = "";
        img.loading = "lazy";
        thumb.appendChild(img);
    } else if (options.icon) {
        thumb.textContent = options.icon;
        thumb.classList.add("os-thumb-icon");
    } else {
        thumb.classList.add("os-thumb-empty");
    }
    li.appendChild(thumb);

    const text = document.createElement("div");
    text.className = "os-text";
    const title = document.createElement("b");
    title.textContent = options.title || "";
    text.appendChild(title);
    if (options.subtitle) {
        const sub = document.createElement("span");
        sub.textContent = options.subtitle;
        text.appendChild(sub);
    }
    li.appendChild(text);

    const price = document.createElement("span");
    price.className = "os-price";
    if (options.price > 0) {
        price.textContent = formatMoney(options.price);
    } else if (options.included) {
        price.textContent = "Included";
        price.classList.add("os-price-muted");
    }
    li.appendChild(price);

    return li;
}

function renderDressUpOrderSummary(activeState, rows, list) {

    list.innerHTML = "";

    const category = state.currentCategory;

    // Size (nasa "Order" na listahan)
    const sizeRow = document.getElementById("dressUpSummarySizeRow");
    const sizeValue = document.getElementById("dressUpSummarySize");
    if (sizeRow && sizeValue) {
        sizeRow.hidden = !productDetails.size;
        sizeValue.textContent = productDetails.size || "";
    }

    if (category && activeState && activeState.model) {
        // 1) Figure: gender + product type + skin, presyo = size
        const productTypeLabels = { standee: "Full Body Standee", keychain: "Full Body Keychain", headKeychain: "Head Keychain" };
        const productType =
            category === "chibi" ? productTypeLabels[state.chibi.productType]
            : category === "hirono" ? productTypeLabels[state.hirono.mode]
            : getFigureLabel(category);
        const modelCard = findDressUpCard(category, "model", activeState.model.model);
        const figureSub = [productType].filter(Boolean).join(" · ");

        // lalabas lang ang figure kapag may presyo na (napili na ang Size)
        const sizePrice = productDetails.size ? getProductSizePrice() : 0;
        if (sizePrice > 0) {
            list.appendChild(buildOrderSummaryItem({
                image: modelCard ? modelCard.dataset.image : "",
                title: ({ funko: "Funko Pop", chibi: "Chibi", hirono: "Hirono" }[state.currentCategory] || "") + " " + (getDressUpGenderLabel(activeState) || activeState.model.name),
                subtitle: figureSub,
                price: sizePrice
            }));
        }

        // 2) Bawat napiling item (Hair, Top, Bottom, Shoes, atbp.)
        const slotLabels = {
            hair: "Hair", girlHair: "Hair", keychainHair: "Hair",
            top: "Top", girlTop: "Top", outfit: "Top",
            bottom: "Bottom", girlBottom: "Bottom", pants: "Bottom",
            shoes: "Shoes", keychainHat: "Hat"
        };

        getActiveSlotOrder().forEach(slot => {
            if (slot === "model" || slot === "skin" || slot.toLowerCase().endsWith("color")) {
                return;
            }
            const item = activeState[slot];
            if (!item) {
                return;
            }
            const card = findDressUpCard(category, slot, item.model);
            const iconEl = card ? card.querySelector(".placeholder-icon") : null;
            const colorSlot = getSlotColorSlot(slot);
            const colorItem = colorSlot ? activeState[colorSlot] : null;
            const label = slotLabels[slot] || getDesignStepLabel(slot);
            const price = getItemPrice(item);

            // walang presyo (hal. Bald, Barefoot) - hindi ipinapakita
            if (price <= 0) {
                return;
            }

            list.appendChild(buildOrderSummaryItem({
                image: card && card.dataset.image ? card.dataset.image : "",
                icon: iconEl ? iconEl.textContent.trim() : "",
                title: item.name,
                subtitle: colorItem && colorItem.name ? `${label} · ${colorItem.name}` : label,
                price: price,
                included: price <= 0
            }));
        });

        // 3) Figure Name / Box / Add-ons (galing sa dating listahan)
        rows
            .filter(row => (row.header === "Figure Name" || row.group === "box") && Number(row.price || 0) > 0)
            .forEach(row => {
                list.appendChild(buildOrderSummaryItem({
                    title: row.value,
                    subtitle: row.header,
                    price: Number(row.price || 0)
                }));
            });
    }

    // 4) Rush Fee - nasa "Order" na listahan (kasama ang presyo)
    const rushFee = getCommissionRushFee();
    const rushRow = document.getElementById("dressUpSummaryRushRow");
    const rushValue = document.getElementById("dressUpSummaryRush");
    if (rushRow && rushValue) {
        rushRow.hidden = !(rushFee > 0);
        rushValue.textContent = rushFee > 0 ? formatMoney(rushFee) : "";
    }

    if (!list.children.length) {
        const empty = document.createElement("li");
        empty.className = "os-empty";
        empty.textContent = "No customization selected yet.";
        list.appendChild(empty);
    }

}


function renderPreviewSummary() {

    const data =
        getCustomizationState();

    const activeState =
        getActiveState();

    // Sarili nang id ("dressUpSummary...") dahil ginagamit na ng Image Submission's
    // Order Summary ang "summaryOrderType"/"summaryBookingDate", at dapat unique ang id sa page.
    const orderType =
        document.getElementById(
            "dressUpSummaryOrderType"
        );

    // Kaparehong "order-type-price" (hal. "₱500" kung Rush Order) sa Image Submission side
    const orderTypePrice =
        document.getElementById(
            "dressUpSummaryOrderTypePrice"
        );

    const bookingDate =
        document.getElementById(
            "dressUpSummaryBookingDate"
        );

    // Kaparehong "Choose a date from the calendar" hint sa Image Submission side -
    // iisa lang ang aktwal na calendar (STEP 01, shared)
    const bookingStatus =
        document.getElementById(
            "dressUpSummaryBookingStatus"
        );

    const receiptItems =
        document.getElementById(
            "summaryReceiptItems"
        );

    // walang currentCategory = walang Figure Style, huwag kunin ang naka-save sa localStorage
    // (para walang mukhang naka-select agad)
    const categoryValue =
        state.currentCategory
            ? getFigureLabel(state.currentCategory)
            : "";

    // Laging live ang kinukuha (getCommissionOrderTypeLabel), hindi ang naka-save na
    // localStorage snapshot - yun kasi ang dahilan kung bakit stale minsan ang "Rush Order" label.
    const orderTypeValue =
        getCommissionOrderTypeLabel();

    const bookingDateValue =
        data?.bookingDateLabel ||
        getCommissionBookingDateLabel();

    if (orderType) {
        // walang icon (⚡/🌷) sa bagong Order Summary
        orderType.textContent =
            receiptItems && receiptItems.classList.contains("os-items")
                ? orderTypeValue.replace(/^[^A-Za-z]+/, "")
                : orderTypeValue;
    }

    if (orderTypePrice) {
        orderTypePrice.textContent =
            formatMoney(
                getCommissionRushFee()
            );
    }

    if (bookingDate) {
        bookingDate.textContent =
            bookingDateValue;
    }

    if (bookingStatus) {
        bookingStatus.textContent =
            getCommissionBookingStatusLabel();
    }

    if (!receiptItems) {
        return;
    }

    const rows =
        buildFigureDetailRows(
            activeState,
            categoryValue
        );

    // Bagong Order Summary sa Dress Up (commission.php): listahan na may larawan
    if (receiptItems.classList.contains("os-items")) {
        renderDressUpOrderSummary(activeState, rows, receiptItems);
        return;
    }

    // "summaryBoxItems" nasa finalsummary.php lang (Box/Add-ons column) - sa
    // commission.php sidebar, iisang listahan pa rin (figure + box magkasama)
    const boxItems =
        document.getElementById(
            "summaryBoxItems"
        );

    if (boxItems) {
        const figureRows = rows.filter(row => row.group !== "box");
        const boxRows = rows.filter(row => row.group === "box");

        renderRowsIntoContainer(
            receiptItems,
            figureRows
        );

        renderRowsIntoContainer(
            boxItems,
            boxRows,
            "No box selected yet."
        );
    } else {
        renderRowsIntoContainer(
            receiptItems,
            rows
        );
    }

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

    Object.entries(activeState).forEach(
        function([key, item]) {
            if (!key.endsWith("Color") || !item) {
                return;
            }

            colors[key] = {
                name: item.name || "",
                color: item.color || ""
            };
        }
    );

    return colors;

}


function updateSelectedItemsUI() {

    if (!selectedList) {
        return;
    }


    selectedList.innerHTML = "";


    const activeState =
        getActiveState();


    if (!activeState || !state.currentCategory) {
        const emptyText =
            document.createElement(
                "span"
            );

        emptyText.className =
            "empty-text";

        emptyText.textContent =
            "No customization selected yet.";

        selectedList.appendChild(
            emptyText
        );

        return;
    }


    const items =
        getActiveSlotOrder()
        .filter(slot => slot !== "skin" && !slot.endsWith("Color"))
        .map(
            function(slot) {
                return activeState[slot];
            }
        ).filter(
            item => item && item.name !== "Custom Color"
        );

    const detailItems = [];
    const config = getProductDetailsConfig();
    const selectedBox = config?.boxes?.find(box => box.id === productDetails.box);

    if (productDetails.size) {
        detailItems.push(
            `Size: ${productDetails.size} (₱${getProductSizePrice()})`
        );
    }

    if (selectedBox) {
        detailItems.push(selectedBox.label);
    }

    if (productDetails.figureName.trim()) {
        detailItems.push(`Figure name: ${productDetails.figureName.trim()}`);
    }

    if (productDetails.boxName.trim()) {
        detailItems.push(`Box name: ${productDetails.boxName.trim()}`);
    }

    if (productDetails.boxNumber.trim()) {
        detailItems.push(`Box number: ${productDetails.boxNumber.trim()}`);
    }

    if (productDetails.boxColor.trim()) {
        detailItems.push(`Box color: ${productDetails.boxColor.trim()}`);
    }

    if (productDetails.blindBox === "set") {
        detailItems.push("Blind Box Set");
    }

    if (Array.isArray(productDetails.hironoAddons)) {
        const addonLabels = {
            tearPaper: "Tear Paper",
            pouch: "Pouch",
            digitalArt: "Digital Art"
        };
        detailItems.push(...productDetails.hironoAddons.map(addon => addonLabels[addon] || addon));
    }


    if (!items.length && !detailItems.length) {
        const emptyText =
            document.createElement(
                "span"
            );

        emptyText.className =
            "empty-text";

        emptyText.textContent =
            "No customization selected yet.";

        selectedList.appendChild(
            emptyText
        );

        return;
    }


    items.forEach(
        function(item) {

            const tag =
                document.createElement(
                    "span"
                );


            tag.className =
                "selected-tag";


            const tagPrice = getItemPrice(item);

            if (tagPrice <= 0) {
                tag.textContent = item.name;
            }
            else {
                tag.textContent =
                    `${item.name} · ₱${tagPrice}`;
            }


            selectedList.appendChild(
                tag
            );

        }
    );

    detailItems.forEach(label => {
        const tag = document.createElement("span");
        tag.className = "selected-tag";
        tag.textContent = label;
        selectedList.appendChild(tag);
    });

}


// ilagay ang class na "is-grid-step" sa kanang panel kapag Hair/Top/Bottom/Shoes
// (may sariling nag-i-scroll na grid) - para sa shadow sa taas at baba
// Figure Details: lahat ng hakbang ay nakalabas, pero naka-LOCK hanggang hindi pa napipili
// ang nauna (Style -> Type -> Size -> Gender -> Skin Tone).
function applyFigureDetailsLocks() {
    document.querySelectorAll("#dressUpModule .step-locked").forEach(el => {
        el.classList.remove("step-locked");
        el.removeAttribute("inert");
        el.removeAttribute("aria-disabled");
    });

    if (!onFirstStep || isDetailsPage || navigationSlot === "productDetails") {
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

    steps.forEach(step => {
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

    right.classList.toggle("is-grid-step", Boolean(grid) && !isDetailsPage && navigationSlot !== "productDetails");
    right.classList.toggle(
        "is-details-step",
        onFirstStep && !isDetailsPage && navigationSlot !== "productDetails"
    );
    right.classList.toggle(
        "is-gender-step",
        !onFirstStep && !isDetailsPage && navigationSlot !== "productDetails" &&
        (currentDesignSlot === "model" || currentDesignSlot === "skin")
    );
}


// Figure Details: may numero (1, 2, 3...) ang bawat required na pipiliin; nagiging check kapag tapos na.
// ilang numero ang nakuha ng Figure Details screen (Style, Type, Size, Gender, Skin)
function getFigureDetailsStepCount() {
    const category = state.currentCategory;

    if (!category) {
        return 1;
    }

    const config = getProductDetailsConfig();
    let count = 1; // Figure Style

    if (category !== "funko") { count += 1; }               // Product Type
    if (!config || config.sizes?.length) { count += 1; }    // Size
    return count + 1;                                       // Gender (Skin Tone walang numero, hindi binibilang)
}


function updateFigureDetailNumbers() {
    document.querySelectorAll("#dressUpModule .fd-badge").forEach(el => el.remove());
    document.querySelectorAll("#dressUpModule [data-req]").forEach(el => el.removeAttribute("data-req"));

    if (!onFirstStep || isDetailsPage || navigationSlot === "productDetails") {
        return;
    }

    const category = state.currentCategory;
    const active = category ? state[category] : null;
    const typeSlot = category === "chibi" ? "productType" : category === "hirono" ? "mode" : null;
    const items = [
        { id: "figureStyleSection", done: Boolean(category), kicker: true }
    ];

    if (category) {
        if (typeSlot) {
            items.push({ id: sectionIds[category].type, done: Boolean(active[typeSlot]) });
        }
        items.push({ id: "figureSizeSection", done: Boolean(productDetails.sizeSelected) });
        items.push({ id: `${category}ModelSection`, done: Boolean(active.model) });
        items.push({ id: sectionIds[category].skin, done: Boolean(active.skin) });
    }

    let number = 0;

    items.forEach(item => {
        const section = document.getElementById(item.id);

        if (!section || section.hidden || section.style.display === "none") {
            return;
        }

        number += 1;

        // walang numero sa header: pulang * (required) lang. Ang numero ay nasa bilog ng card.
        const target = item.kicker
            ? section.querySelector(".figure-style-kicker")
            : section.querySelector(".section-title");

        if (target) {
            target.setAttribute("data-req", "1");
        }
    });
}


// Numero ng bawat hakbang (1..n) sa BILOG na nasa napiling card, at lock ng mga susunod
// na hakbang hanggang hindi pa napipili ang nauna (Style -> Type -> Size -> Gender -> Skin).
function updateStepBadgesAndLocks() {
    document.querySelectorAll("#dressUpModule .card-step-badge").forEach(el => el.remove());

    if (isDetailsPage || navigationSlot === "productDetails") {
        return;
    }

    const category = state.currentCategory;
    const active = category ? state[category] : null;

    const addBadge = (card, number) => {
        if (!card) {
            return;
        }
        const badge = document.createElement("i");
        badge.className = "card-step-badge";
        badge.textContent = String(number);
        card.appendChild(badge);
    };

    if (!onFirstStep) {
        // Hair ... Shoes: tuloy ang bilang mula sa Figure Details
        if (currentStepNumber === null) {
            return;
        }
        Array.from(document
            .querySelectorAll("#dressUpModule .category-panel:not([hidden]) .option-section:not([hidden]) .option-grid > .option-card.selected:not(.group-collapsed)"))
            .filter(card => card.offsetParent !== null)
            .filter(card => !card.classList.contains("is-default") || pickedSlots.has(card.dataset.slot))
            .forEach(card => addBadge(card, currentStepNumber));
        return;
    }

    const typeSlot = category === "chibi" ? "productType" : category === "hirono" ? "mode" : null;
    const config = getProductDetailsConfig();
    const needsSize = !config || Boolean(config.sizes?.length);

    const steps = [
        { id: "figureStyleSection", card: ".figure-style-card", done: Boolean(category) }
    ];

    if (category) {
        if (typeSlot) {
            steps.push({
                id: sectionIds[category].type,
                card: ".option-card, .figure-model-card",
                done: Boolean(active[typeSlot])
            });
        }
        if (needsSize) {
            steps.push({
                id: "figureSizeSection",
                card: ".detail-choice-card",
                done: Boolean(productDetails.sizeSelected)
            });
        }
        steps.push({
            id: `${category}ModelSection`,
            card: ".figure-model-card",
            done: Boolean(active.model)
        });
    }

    steps.forEach((step, index) => {
        const section = document.getElementById(step.id);

        if (!section) {
            return;
        }

        const selected = Array.from(section.querySelectorAll(step.card))
            .find(card => card.classList.contains("selected"));
        addBadge(selected, index + 1);
    });
}


function updateSectionVisibility() {
    updateSectionVisibilityCore();
    syncGridStepClass();
    updateFigureDetailNumbers();
    updateStepBadgesAndLocks();
    applyFigureDetailsLocks();
    syncWizardPills();
    scheduleClothingUI();
}

// Header ng wizard (pills). 1 = Figure Details (Style, Type, Size, Gender, Skin Tone),
// 2 = Parts (Hair, Top, Bottom, Shoes), 3 = Box/Details, 4 = Review (finalsummary.php).
function getCurrentWizardStep() {
    const detailsVisible = Boolean(productDetailsPanel && !productDetailsPanel.hidden);

    return !state.currentCategory || onFirstStep
        ? 1
        : (detailsVisible ? 3 : 2);
}


// Lagda ng bawat step: kapag nagbago ang lagda ng isang step, ibig sabihin may binago
// ang customer doon, kaya uulit ang mga susunod na step.
function getWizardSignatures() {
    const category = state.currentCategory;
    const active = category ? state[category] : null;

    if (!active) {
        return { 1: "", 2: "", 3: "" };
    }

    const pick = item => item
        ? [item.name || "", item.color || "", item.model || ""].join("|")
        : "";

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
            .filter(slot => slot !== "model" && slot !== "skin")
            .map(slot => [slot, pick(active[slot])])
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

    if (wizardSigs && wizardArmed) {
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
    if (isDetailsPage || !state.currentCategory) {
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
        window.location.href = "finalsummary.php";
        return;
    }

    if (step === 1) {
        navigationSlot = "model";
    }
    else if (step === 2) {
        navigationSlot = activeSequentialSlots.find(slot => !FIRST_GROUP_SLOTS.includes(slot)) || null;
    }
    else {
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

    pillList.addEventListener("click", event => {
        const pill = event.target.closest && event.target.closest("li[data-wizard]");

        if (pill) {
            jumpToWizardStep(Number(pill.dataset.wizard));
        }
    });

    pillList.addEventListener("keydown", event => {
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
    const arm = () => { wizardArmed = true; };
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

    pillList.querySelectorAll("li[data-wizard]").forEach(pill => {
        const step = Number(pill.dataset.wizard);
        const clickable = step !== current && canJumpToWizardStep(step);

        pill.classList.toggle("is-active", step === current);
        pill.classList.toggle("is-done", step !== current && step <= wizardMaxStep);
        pill.classList.toggle("is-clickable", clickable);

        if (clickable) {
            pill.setAttribute("role", "button");
            pill.setAttribute("tabindex", "0");
            pill.removeAttribute("aria-disabled");
        }
        else {
            pill.removeAttribute("role");
            pill.setAttribute("tabindex", "-1");
            pill.setAttribute("aria-disabled", "true");
        }
    });

}


function updateSectionVisibilityCore() {

    const activeCategory =
        state.currentCategory;


    Object.entries(
        categoryPanels
    ).forEach(
        function([category, panel]) {
            if (panel) {
                panel.hidden =
                    category !== activeCategory;
            }
        }
    );


    if (isDetailsPage) {
        // The details page is a focused review step: keep the preview and
        // order summary, but remove all figure-style/category controls.
        Object.values(categoryPanels).forEach(panel => {
            if (panel) {
                panel.hidden = true;
            }
        });

        if (figureDetailsContainer) {
            figureDetailsContainer.hidden = true;
        }

        if (!activeCategory) {
            if (productDetailsPanel) {
                productDetailsPanel.hidden = true;
            }
            return;
        }

        if (productDetailsPanel) {
            productDetailsPanel.hidden = false;
        }

        const navigation = document.getElementById("designStepNavigation");
        if (navigation) {
            navigation.hidden = true;
        }

        if (continueBtn) {
            continueBtn.hidden = false;
        }

        return;
    }


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
        placeFigureStylePicker();

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
    [
        "funkoModelSection",
        "hironoModelSection",
        "chibiModelSection"
    ].forEach(
        function(id) {
            setVisibleById(
                id,
                id === `${activeCategory}ModelSection`
            );
        }
    );


    const activeState =
        state[activeCategory];

    if (continueBtn) {
        continueBtn.hidden = true;
    }

    Object.values(
        sectionIds
    ).forEach(
        function(categorySections) {
            Object.values(
                categorySections
            ).forEach(
                function(id) {
                    setVisibleById(
                        id,
                        false
                    );
                }
            );
        }
    );


    // The reset loop above only walks the per-style `sectionIds` map, which
    // doesn't include the shared Size section (it lives outside all three
    // style panels). Without this, Size stays visible after the user moves
    // past it to Hair/Top/etc. instead of being hidden like every other step.
    setVisibleById("figureSizeSection", false);


    const showSections = function(ids) {
        ids.forEach(
            function(id) {
                setVisibleById(
                    id,
                    true
                );
            }
        );
    };


    let sequentialSlots = null;

    if (activeCategory === "funko") {
        sequentialSlots = activeState.model && isFunkoGirlModel(activeState.model)
            ? ["size", "model", "skin", "girlHair", "girlTop", "girlBottom", "shoes"]
            : ["size", "model", "skin", "hair", "top", "bottom", "shoes"];
    }

    if (activeCategory === "chibi") {
        sequentialSlots = !activeState.productType
            ? ["productType", "size", "model", "skin", "hair", "top", "bottom", "shoes"]
            : activeState.productType === "keychain"
                ? activeState.model && isChibiGirlModel(activeState.model)
                    ? ["productType", "size", "model", "skin", "girlHair", "girlTop", "girlBottom", "shoes"]
                    : ["productType", "size", "model", "skin", "hair", "top", "bottom", "shoes"]
                : activeState.model && isChibiGirlModel(activeState.model)
                    ? ["productType", "size", "model", "skin", "girlHair", "girlTop", "girlBottom", "shoes"]
                    : ["productType", "size", "model", "skin", "hair", "top", "bottom", "shoes"];
    }

    if (activeCategory === "hirono") {
        if (!activeState.mode) {
            sequentialSlots = ["mode", "size", "model", "skin", "hair", "outfit", "pants", "shoes"];
        }
        else {
            const hironoMode = getHironoMode();
            sequentialSlots = hironoMode === "headKeychain"
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
            shoes: sectionIds.funko.shoes,
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
                // Chibi Boy standee and keychain share the same shoes.
                shoes: (activeState.model && !isChibiGirlModel(activeState.model))
                    ? sectionIds.chibi.boyShoes
                    : sectionIds.chibi.shoes
            });
        }

        if (activeCategory === "hirono") {
            Object.assign(sequentialSections, {
                hair: sectionIds.hirono.hair,
                hairColor: sectionIds.hirono.hairColor,
                outfit: sectionIds.hirono.outfit,
                pants: sectionIds.hirono.pants,
                pantsColor: sectionIds.hirono.pantsColor,
                shoes: sectionIds.hirono.shoes,
                shoesColor: sectionIds.hirono.shoesColor,
                keychainHair: sectionIds.hirono.keychainHair,
                keychainHairColor: sectionIds.hirono.keychainHairColor,
                keychainHat: sectionIds.hirono.keychainHat,
                outfitColor: sectionIds.hirono.outfitColor
            });
        }

        const designSlotByColor = activeCategory === "funko"
            ? {
                hairColor: isFunkoGirlModel(activeState.model) ? "girlHair" : "hair",
                topColor: isFunkoGirlModel(activeState.model) ? "girlTop" : "top",
                bottomColor: "girlBottom",
                pantsColor: "bottom"
            }
            : activeCategory === "chibi"
                ? {
                    hairColor: isChibiGirlModel(activeState.model) ? "girlHair" : "hair",
                    girlHairColor: "girlHair"
                }
                : {
                    hairColor: "hair",
                    outfitColor: "outfit",
                    pantsColor: "pants",
                    shoesColor: "shoes",
                    keychainHairColor: "keychainHair"
                };
        const requestedSlot = designSlotByColor[navigationSlot] || navigationSlot;
        // Size ay sa productDetails naka-save (hindi sa activeState), kaya laktawan kapag napili na
        const nextSlot = sequentialSlots.find(slot =>
            !activeState[slot] && !(slot === "size" && productDetails.sizeSelected)
        );
        let slotToShow = requestedSlot && sequentialSlots.includes(requestedSlot)
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
        currentStepNumber = firstStep
            ? null
            : getFigureDetailsStepCount() + (sequentialSlots.indexOf(slotToShow) - firstGroup.length + 1);

        if (figureStyleSection) {
            figureStyleSection.hidden = !firstStep;
            figureStyleSection.classList.remove("merged-with-type");
        }

        // Hair step: lapit sa ulo/buhok; ibang step: buong figure
        setCameraFocus(
            navigationSlot !== "productDetails" && HEAD_FOCUS_SLOTS.includes(slotToShow)
                ? "head"
                : "full"
        );
        const slotBefore = slotToShow === "skin" || slotToShow.endsWith("Color")
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
        const modelPairedSlots = slotToShow === "model"
            ? ["skin"]
            : (firstStep ? detailGroup.slice(1) : []);

        const designsComplete = !nextSlot;
        const detailsStep = navigationSlot === "productDetails";

        renderSizeSection();

        if (continueBtn) {
            continueBtn.hidden = !detailsStep;
            continueBtn.textContent = dressUpViewing ? "Save Changes" : "Next";
        }

        if (productDetailsPanel) {
            productDetailsPanel.hidden = !detailsStep;
        }

        if (figureDetailsContainer) {
            figureDetailsContainer.hidden = detailsStep;
        }

        if (detailsStep) {
            Object.values(sequentialSections).forEach(id => {
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

        setVisibleById(
            sequentialSections.model,
            firstStep && detailGroup.includes("model")
        );

        setVisibleById(
            sequentialSections[slotBefore],
            Boolean(slotBefore)
        );

        setVisibleById(
            sequentialSections[slotToShow],
            Boolean(slotToShow)
        );

        setVisibleById(
            sequentialSections[slotAfter],
            Boolean(slotAfter)
        );

        // Parts: Hair, tapos Top, Bottom, Shoes (isa-isa, hindi nag-iipon). Wala nang Skin Tone dito.

        modelPairedSlots.forEach(slot => {
            let show = true;
            if (slot === "size") {
                // walang Size na hihingin (hal. Hirono Head Keychain) = walang section
                const sizeConfig = getProductDetailsConfig();
                show = Boolean(sizeConfig?.sizes?.length) &&
                    Boolean(sizeChoiceGrid && sizeChoiceGrid.children.length);
            }
            setVisibleById(sequentialSections[slot], show);
        });

        if (designsComplete) {
            setVisibleById(
                sequentialSections[slotToShow],
                true
            );
        }

        renderDesignNavigation(
            sequentialSlots,
            sequentialSections,
            activeState,
            slotToShow,
            modelPairedSlots
        );

        return;
    }


    if (activeCategory === "funko") {

        if (activeState.model) {
            setVisibleById(
                sectionIds.funko.skin,
                true
            );
        }


        if (!activeState.skin) {
            return;
        }


        if (isFunkoGirlModel(activeState.model)) {
            showSections(
                [
                    sectionIds.funko.girlHair,
                    sectionIds.funko.hairColor,
                    sectionIds.funko.girlTop,
                    sectionIds.funko.girlTopColor,
                    sectionIds.funko.girlBottom,
                    sectionIds.funko.girlBottomColor
                ]
            );
        }
        else {
            showSections(
                [
                    sectionIds.funko.hair,
                    sectionIds.funko.hairColor,
                    sectionIds.funko.top,
                    sectionIds.funko.topColor,
                    sectionIds.funko.bottom,
                    sectionIds.funko.pantsColor
                ]
            );
        }

        return;

    }


    const hironoMode =
        getHironoMode();


    if (activeCategory === "hirono") {

        const hironoSequence =
            hironoMode === "headKeychain"
                ? ["model", "skin", "keychainHair", "keychainHairColor", "keychainHat"]
                : ["model", "skin", "hair", "hairColor", "outfit", "pants", "pantsColor", "shoes", "shoesColor"];

        const hironoSections = {
            model: "hironoModelSection",
            skin: sectionIds.hirono.skin,
            hair: sectionIds.hirono.hair,
            hairColor: sectionIds.hirono.hairColor,
            outfit: sectionIds.hirono.outfit,
            pants: sectionIds.hirono.pants,
            pantsColor: sectionIds.hirono.pantsColor,
            shoes: sectionIds.hirono.shoes,
            shoesColor: sectionIds.hirono.shoesColor,
            keychainHair: sectionIds.hirono.keychainHair,
            keychainHairColor: sectionIds.hirono.keychainHairColor,
            keychainHat: sectionIds.hirono.keychainHat
        };

        const nextSlot =
            hironoSequence.find(slot => !activeState[slot]);

        const designsComplete = !nextSlot;

        if (productDetailsPanel) {
            productDetailsPanel.hidden = !designsComplete;
        }

        if (figureDetailsContainer) {
            figureDetailsContainer.hidden = designsComplete;
        }

        setVisibleById(
            hironoSections.model,
            nextSlot === "model"
        );

        setVisibleById(
            hironoSections[nextSlot],
            Boolean(nextSlot)
        );

        return;
    }


    if (hironoMode === "headKeychain") {

        if (activeState.model) {
            setVisibleById(
                sectionIds.hirono.skin,
                true
            );
        }


        if (!activeState.skin) {
            return;
        }


        showSections(
            [
                sectionIds.hirono.keychainHair,
                sectionIds.hirono.keychainHairColor,
                sectionIds.hirono.keychainHat
            ]
        );
        return;

    }


    if (hironoMode === "keychain") {

        if (activeState.model) {
            setVisibleById(
                sectionIds.hirono.skin,
                true
            );
        }


        if (!activeState.skin) {
            return;
        }


        showSections(
            [
                sectionIds.hirono.hair,
                sectionIds.hirono.hairColor,
                sectionIds.hirono.outfit,
                sectionIds.hirono.pants,
                sectionIds.hirono.pantsColor,
                sectionIds.hirono.shoes,
                sectionIds.hirono.shoesColor
            ]
        );

        return;

    }


    if (activeState.model) {
        setVisibleById(
            sectionIds.hirono.skin,
            true
        );
    }


    if (!activeState.skin) {
        return;
    }


    if (hironoMode === "keychain") {
        showSections(
            [
                sectionIds.hirono.keychainHair,
                sectionIds.hirono.keychainHairColor,
                sectionIds.hirono.keychainHat
            ]
        );
        return;
    }


    showSections(
        [
            sectionIds.hirono.hair,
            sectionIds.hirono.hairColor,
            sectionIds.hirono.outfit,
            sectionIds.hirono.pants,
            sectionIds.hirono.pantsColor,
            sectionIds.hirono.shoes,
            sectionIds.hirono.shoesColor
        ]
    );

}


function clearCategoryState(category) {

    Object.assign(
        state[category],
        createCategoryState()
    );


    const selector =
        `[data-figure="${category}"][data-slot]`;


    clearSelectedClasses(
        selector
    );

}






function isPlaceholderModelPath(path) {

    return !path || String(path).startsWith("placeholder:");

}


// Kapag pumili ng Gender (model), auto-select agad ang mga "Default" na item
// (Default Hair, Sando/Tank Top, Shorts, Shoes) para may laman na ang bawat
// step at makapag-Next agad. Puwede pa rin nilang palitan anytime.
function applyDefaultSelections(category) {

    const stateForCategory =
        state[category];

    if (!stateForCategory || !stateForCategory.model) {
        return;
    }

    getActiveSlotOrder().forEach(
        function(slot) {

            if (
                slot === "model" ||
                slot === "skin" ||
                slot.endsWith("Color") ||
                stateForCategory[slot]
            ) {
                return;
            }

            // Chibi Boy standees and keychains share the same shoe set.
            const keychainSectionId =
                category === "chibi" && slot === "shoes" && stateForCategory.model && !isChibiGirlModel(stateForCategory.model)
                    ? sectionIds.chibi.boyShoes
                    : null;

            const defaultCard =
                ((keychainSectionId && document.getElementById(keychainSectionId)) || document).querySelector(
                    `[data-figure="${category}"][data-slot="${slot}"][data-default="true"]`
                );

            if (!defaultCard) {
                return;
            }

            stateForCategory[slot] =
                buildItemFromCard(
                    defaultCard
                );

            markSelectedCard(
                defaultCard
            );

        }
    );

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
    }
    else if (
        category === "funko" &&
        slot === "hairColor" &&
        isFunkoGirlModel(state[category].model)
    ) {
        accessorySlot = "girlHair";
    }
    else if (
        category === "funko" &&
        slot === "topColor" &&
        isFunkoGirlModel(state[category].model)
    ) {
        accessorySlot = "girlTop";
    }
    else if (
        category === "funko" &&
        slot === "bottomColor" &&
        isFunkoGirlModel(state[category].model)
    ) {
        accessorySlot = "girlBottom";
    }


    return accessorySlot;

}


function applyCustomColor(category, slot, color, input) {

    const stateForCategory = state[category];
    const accessorySlot = getColorAccessorySlot(category, slot);
    const currentObject = getLoadedObject(accessorySlot);


    stateForCategory[slot] = {
        name: "Custom Color",
        model: "",
        color,
        price: 0,
        billable: false
    };


    if (currentObject) {
        if (
            category === "funko" &&
            (accessorySlot === "bottom" || slot === "bottomColor")
        ) {
            applyBottomPartColors(currentObject, color);
        }
        else {
            applyColorToModel(currentObject, color, {
                clearTextureMaps: true,
                skipMaterialFragments: ["design", "logo", "print"]
            });
        }
    }
    else if (isSandoColorSlot(category, slot)) {
        applySandoColor(color);
    }


    state.currentCategory = category;
    navigationSlot = getColorAccessorySlot(category, slot);
    syncCustomColorPickers();
    updateSectionVisibility();
    updateSelectedItemsUI();
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
    chibiHairColorSection: ["chibiHairSection"],
    chibiGirlHairColorSection: ["chibiGirlHairSection"],
    chibiTopColorSection: ["chibiTopSection", "chibiBoyKeychainTopSection"],
    chibiGirlTopColorSection: ["chibiGirlTopSection", "chibiGirlKeychainTopSection"],
    chibiBottomColorSection: ["chibiBottomSection", "chibiBoyKeychainBottomSection"],
    chibiGirlBottomColorSection: ["chibiGirlBottomSection", "chibiGirlKeychainBottomSection"],
    chibiShoesColorSection: ["chibiShoesSection", "chibiBoyShoesSection"]
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
            .map(id => document.getElementById(id))
            .filter(Boolean)
            .map(designSection => designSection.querySelector(".section-title"))
            .filter(Boolean);
    }

    const designSection = siblings
        .slice(0, siblings.indexOf(section))
        .reverse()
        .find(candidate => candidate.classList.contains("option-section"));

    const mount = designSection?.querySelector(".section-title");

    return mount ? [mount] : [section];
}


function initializeCustomColorPickers() {

    document.querySelectorAll(
        ".top-color-picker, .custom-color-only-section"
    ).forEach(
        function(section) {
            const mounts = getCustomColorMounts(section);

            if (mounts.every(mount => mount.querySelector(".custom-color-control"))) {
                return;
            }


            const slotCard = section.querySelector("[data-slot]");
            const slot =
                (slotCard && slotCard.dataset.slot) ||
                section.dataset.slot;

            const category =
                (slotCard && slotCard.dataset.figure) ||
                section.dataset.figure;


            if (!slot || !category) {
                return;
            }


            mounts.forEach(mount => {
                if (mount.querySelector(".custom-color-control")) {
                    return;
                }

                const label = document.createElement("div");
                label.className = "custom-color-control";
                label.innerHTML =
                    `<label class="custom-color-pick">` +
                        `<span class="custom-color-wheel" aria-hidden="true"></span>` +
                        `<span class="custom-color-text">` +
                            `<span class="custom-color-label">Custom color</span>` +
                            `<span class="custom-color-hex">Tap to pick a color</span>` +
                        `</span>` +
                        `<input type="color" value="#ffffff" aria-label="Choose custom ${slot} color">` +
                    `</label>` +
                    `<button type="button" class="custom-color-reset" hidden aria-label="Reset ${slot} color">Reset</button>`;

                mount.appendChild(label);

                const input = label.querySelector("input");
                const wheel = label.querySelector(".custom-color-wheel");
                const resetColorBtn = label.querySelector(".custom-color-reset");
                input.addEventListener(
                    "input",
                    function() {
                        if (wheel) {
                            wheel.style.background = input.value;
                        }
                        applyCustomColor(category, slot, input.value, input);
                    }
                );
                resetColorBtn.addEventListener(
                    "click",
                    function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        resetPartColor(category, slot);
                    }
                );
            });

        }
    );

}


function syncCustomColorPickers() {

    document.querySelectorAll(
        ".top-color-picker, .custom-color-only-section"
    ).forEach(
        function(section) {
            const slotCard =
                section.querySelector("[data-slot]");

            const slot =
                (slotCard && slotCard.dataset.slot) ||
                section.dataset.slot;

            const category =
                (slotCard && slotCard.dataset.figure) ||
                section.dataset.figure;

            const inputs = getCustomColorMounts(section)
                .map(mount => mount.querySelector(
                    ".custom-color-control input[type='color']"
                ))
                .filter(Boolean);

            const savedColor =
                category && slot && state[category] && state[category][slot]
                    ? state[category][slot].color
                    : "";

            const hasColor = /^#[0-9a-f]{6}$/i.test(savedColor || "");

            getCustomColorMounts(section).forEach(mount => {
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
                    if (wheel) { wheel.style.background = savedColor; }
                    if (hex) { hex.textContent = savedColor.toUpperCase(); }
                }
                else {
                    input.value = "#ffffff";
                    if (wheel) { wheel.style.background = ""; }
                    if (hex) { hex.textContent = "Tap to pick a color"; }
                }

                control.classList.toggle("has-color", hasColor);
                if (resetColorBtn) { resetColorBtn.hidden = !hasColor; }
            });
        }
    );

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
    updateSelectedItemsUI();
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
    if (/graphic|pinstripe|coat|suit|jersey|cargo|denim|jacket|dress|tie|spider|venom|girl shirt|uniform|ccc|\bny\b|rl polo/i.test(n)) {
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
        }
        catch (error) {
            console.warn("clothing UI refresh failed", error);
        }
    }, 0);
}

// itago ang Custom color / color cards kapag hindi pwedeng kulayan ang napiling damit
function refreshColorAvailability() {
    document.querySelectorAll(".top-color-picker, .custom-color-only-section").forEach(section => {
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

        getCustomColorMounts(section).forEach(mount => {
            const control = mount.querySelector(".custom-color-control");
            if (control) {
                control.classList.toggle("color-unavailable", !colorable);
            }
        });
    });
}

function refreshClothingGroups() {
    document.querySelectorAll(".option-section > .option-grid").forEach(grid => {
        const cards = Array.from(grid.querySelectorAll(":scope > .option-card"));

        if (!cards.length || !GROUPED_SLOTS.includes(cards[0].dataset.slot)) {
            return;
        }

        const section = grid.parentElement;
        const slot = cards[0].dataset.slot;
        const figure = cards[0].dataset.figure;
        const keyBase = (section.id || slot) + ":";

        if (HAIR_SLOTS.includes(slot)) {
            grid.querySelectorAll(":scope > .option-group-head").forEach(el => el.remove());
            grid.classList.remove("has-option-groups");
            grid.classList.add("is-flat-grid");
            cards.forEach(card => card.classList.remove("group-collapsed"));
            return;
        }

        // ang default (Sando, Shorts, atbp.) ay laging nasa pinaka-taas, walang grupo/header
        const defaultCards = cards.filter(card => !card.hidden && card.dataset.default === "true");

        const groups = new Map();
        cards.forEach(card => {
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
        const selectedCard = cards.find(card => card.classList.contains("selected"));
        const selectedLabel = selectedCard ? getClothingGroup(slot, selectedCard.dataset.name)[1] : "";

        const gridScrollTop = grid.scrollTop;
        grid.querySelectorAll(":scope > .option-group-head").forEach(el => el.remove());
        grid.classList.add("has-option-groups");

        defaultCards.forEach(card => {
            card.classList.remove("group-collapsed");
            grid.appendChild(card);
        });

        list.forEach((group, index) => {
            const key = keyBase + group.label;

            // default: bukas ang grupo ng napiling damit (o ang una kung wala pang napili);
            // kung nag-click na ang user sa header, siya ang masusunod
            const isOpen = () => (key in clothingGroupOpen)
                ? clothingGroupOpen[key]
                : true;

            const head = document.createElement("button");
            head.type = "button";
            head.className = "option-group-head";
            head.setAttribute("aria-expanded", isOpen() ? "true" : "false");
            head.innerHTML = `<span class="option-group-title">${group.label} (${group.cards.length})</span><span class="option-group-chevron" aria-hidden="true"></span>`;

            const apply = () => {
                const open = Boolean(isOpen());
                head.setAttribute("aria-expanded", open ? "true" : "false");
                group.cards.forEach(card => card.classList.toggle("group-collapsed", !open));
            };

            head.addEventListener("click", () => {
                clothingGroupOpen[key] = !isOpen();
                apply();
            });

            // ilagay ang header at ang mga card ng grupo nang magkakasunod
            grid.appendChild(head);
            group.cards.forEach(card => grid.appendChild(card));
            apply();
        });

        // mga card na naka-hidden (hal. ibang gender) - iwan sa dulo
        cards.filter(card => card.hidden).forEach(card => grid.appendChild(card));

        // ibalik ang scroll position ng grid (nare-reset kapag inayos ang mga card)
        if (gridScrollTop > 0) {
            grid.scrollTop = gridScrollTop;
        }
    });
}



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

    const stringValue =
        String(value || "");


    if (
        window.CSS &&
        typeof window.CSS.escape === "function"
    ) {
        return window.CSS.escape(stringValue);
    }


    return stringValue.replace(
        /["\\]/g,
        "\\$&"
    );

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

    document.querySelectorAll("[data-slot] .option-price[data-base-price]").forEach(label => {

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

    Object.keys(categoryPanels).forEach(category => {

        const stateForCategory = state[category];

        if (!stateForCategory) {
            return;
        }

        Object.keys(stateForCategory).forEach(slot => {

            const item = stateForCategory[slot];

            if (
                !item ||
                typeof item !== "object" ||
                !item.model ||
                slot === "model" ||
                slot.endsWith("Color")
            ) {
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


    return getActiveSlotOrder().reduce(
        function(total, slot) {

            const item =
                activeState[slot];


            if (
                !item ||
                item.billable === false
            ) {
                return total;
            }


            return total + getItemPrice(item);

        },
        0
    );

}


function getSelectedItemNames(activeState) {

    if (!activeState) {
        return [];
    }


    return getActiveSlotOrder().map(
        function(slot) {
            const item =
                activeState[slot];

            return item ? item.name : null;
        }
    ).filter(Boolean);

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

        const value = getSummarySelectionValue(
            slot,
            item,
            activeState
        );

        details.push({
            label: labels[slot],
            value
        });

        return details;
    }, []);

}


function getAccessoryNames(activeState) {

    if (!activeState) {
        return [];
    }


    return getActiveSlotOrder().filter(
        function(slot) {
            return slot !== "model" && slot !== "skin";
        }
    ).map(
        function(slot) {
            const item =
                activeState[slot];

            return item ? item.name : null;
        }
    ).filter(Boolean);

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

    return toSummarySlug(
        getFileBaseName(item.model || item.name || item.color || "")
    );
}


function getSummaryColorToken(item) {
    if (!item) {
        return "";
    }

    return toSummarySlug(
        item.name || item.color || ""
    );
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
    }
    else if (category === "hirono") {
        return modelName || (suffix ? `${category}-${suffix.replace(/^hirono-?/, "")}` : category);
    }
    else if (category === "chibi") {
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

    const color =
        colorSlot ? activeState?.[colorSlot] : null;

    const itemToken =
        getSummaryToken(item);

    const colorToken =
        color && color.name !== "Custom Color"
            ? getSummaryColorToken(color)
            : "";

    return colorToken
        ? `${itemToken}-${colorToken}`
        : itemToken;
}


function syncSelectedCardsFromState() {

    clearSelectedClasses(
        ".style-card, .figure-model-card, .skin-card, .option-card, .top-color-card"
    );


    if (!state.currentCategory) {
        return;
    }


    const activeState =
        getActiveState();


    if (!activeState) {
        return;
    }


    const activeCategory =
        state.currentCategory;


    // tamang Gender cards lang ang makikita pagbalik (hindi doble)
    if (activeCategory === "chibi") {
        syncChibiModelOptions();
    }
    else if (activeCategory === "hirono") {
        syncHironoGenderOptions();
    }


    const styleCard =
        document.querySelector(
            `[data-tab="figure"][data-figure="${activeCategory}"]`
        );


    if (styleCard) {
        styleCard.classList.add("selected");
    }


    if (activeState.model) {
        const modelCard =
            document.querySelector(
                `[data-figure="${activeCategory}"][data-slot="model"][data-model="${escapeSelectorValue(activeState.model.model)}"]`
            );


        if (modelCard) {
            modelCard.classList.add("selected");
        }
    }


    if (activeState.skin) {
        const skinCard =
            document.querySelector(
                `[data-figure="${activeCategory}"][data-slot="skin"][data-color="${escapeSelectorValue(activeState.skin.color)}"]`
            );


        if (skinCard) {
            skinCard.classList.add("selected");
        }
    }


    getActiveSlotOrder().forEach(
        function(slot) {

            const item =
                activeState[slot];


            if (!item || slot === "model" || slot === "skin") {
                return;
            }


            // querySelectorAll: iisang model minsan nasa standee at keychain section
            const cards =
                item.color
                    ? document.querySelectorAll(
                        `[data-figure="${activeCategory}"][data-slot="${slot}"][data-color="${escapeSelectorValue(item.color)}"]`
                    )
                    : document.querySelectorAll(
                        `[data-figure="${activeCategory}"][data-slot="${slot}"][data-model="${escapeSelectorValue(item.model)}"]`
                    );


            cards.forEach(card => card.classList.add("selected"));

        }
    );

}


function buildCustomizationSnapshot(isCompleted, isConfirmed) {

    const activeState =
        getActiveState();


    if (!activeState || !state.currentCategory) {
        return null;
    }


    const selectedItems =
        getSelectedItemNames(activeState);

    const orderTypeValue =
        getCommissionOrderTypeValue();

    const bookingDateValue =
        getCommissionBookingDateValue();

    const estimatedPrice =
        getCurrentOrderTotal(activeState);


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
        hair: activeState.hair ? activeState.hair.name : (activeState.girlHair ? activeState.girlHair.name : (activeState.keychainHair ? activeState.keychainHair.name : "")),
        top: activeState.top ? activeState.top.name : (activeState.girlTop ? activeState.girlTop.name : (activeState.outfit ? activeState.outfit.name : "")),
        bottom: activeState.bottom ? activeState.bottom.name : (activeState.girlBottom ? activeState.girlBottom.name : (activeState.pants ? activeState.pants.name : "")),
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

    const snapshot =
        buildCustomizationSnapshot(
            isCompleted,
            isConfirmed
        );


    if (!snapshot) {
        return;
    }


    try {

        localStorage.setItem(
            CUSTOMIZATION_STORAGE_KEY,
            JSON.stringify(
                snapshot
            )
        );

    }
    catch (error) {
        console.warn(
            "Unable to save customization:",
            error
        );
    }

    syncWizardPills();

}


function clearCustomizationSnapshot() {

    try {
        localStorage.removeItem(
            CUSTOMIZATION_STORAGE_KEY
        );
    }
    catch (error) {
        console.warn(
            "Unable to clear customization:",
            error
        );
    }

}


function restoreCustomizationFromStorage(navMode) {

    const restoreMode = navMode || (isEditPage ? "edit" : (isResumePage ? "resume" : ""));


    const saved =
        (() => {
            try {
                const raw =
                    localStorage.getItem(
                        CUSTOMIZATION_STORAGE_KEY
                    );

                return raw ? JSON.parse(raw) : null;
            }
            catch (error) {
                console.warn(
                    "Unable to restore customization:",
                    error
                );
                return null;
            }
        })();


    if (!saved) {
        return false;
    }


    const selectedCommissionCategory =
        getCommissionFigureCategoryValue();


    // A new category selected on the commission page must not be replaced
    // by an older customization saved for another category.
    if (
        selectedCommissionCategory &&
        selectedCommissionCategory !== saved.currentCategory
    ) {
        return false;
    }

    productDetails = {
        productKey: saved.productKey || "",
        size: saved.figureSizeSelected ? (saved.figureSize || "") : "",
        sizeSelected: Boolean(saved.figureSizeSelected),
        figureName: "", // walang Figure Name sa Dress Up
        box: saved.boxSelected ? (saved.boxType || "") : "",
        boxSelected: Boolean(saved.boxSelected),
        boxName: saved.boxName || "",
        boxNumber: saved.boxNumber || "",
        boxColor: saved.boxColor || "",
        blindBox: saved.blindBoxSelected ? (saved.blindBox || "regular") : "regular",
        blindBoxSelected: Boolean(saved.blindBoxSelected),
        hironoAddons: Array.isArray(saved.hironoAddons) ? saved.hironoAddons : [],
        boxDesign: saved.boxDesignSelected ? (saved.boxDesign || "checkered") : "checkered",
        boxDesignSelected: Boolean(saved.boxDesignSelected),
        boxNickname: saved.boxNickname || "",
        boxLetter: saved.boxLetter || "",
        boxDateYmd: saved.boxDateYmd || ""
    };


    Object.keys(
        categoryPanels
    ).forEach(
        function(category) {
            if (saved[category]) {
                state[category] = Object.assign(
                    createCategoryState(),
                    saved[category]
                );
            }
        }
    );


    state.currentCategory =
        saved.currentCategory ||
        Object.keys(
            categoryPanels
        ).find(
            function(category) {
                return saved[category] && saved[category].model;
            }
        ) ||
        null;


    syncItemPricesFromCards();

    syncCustomColorPickers();


    if (!state.currentCategory) {
        return true;
    }

    if (restoreMode === "edit") {
        if (state.currentCategory === "funko") {
            navigationSlot = isFunkoGirlModel(state.funko.model) ? "girlHair" : "hair";
        }
        else if (state.currentCategory === "chibi") {
            navigationSlot = isChibiGirlModel(state.chibi.model) ? "girlHair" : "hair";
        }
        else if (getHironoMode() === "headKeychain") {
            navigationSlot = "keychainHair";
        }
        else {
            navigationSlot = "hair";
        }
    }
    else if (restoreMode === "resume") {
        // Bumalik sa Box/Add-ons kung meron nito ang style (Funko/Hirono), kung wala
        // (Chibi) sa "shoes" - laging ito ang huling design step
        const detailsConfig = getProductDetailsConfig();
        const hasDetailsStep = Boolean(
            detailsConfig && (detailsConfig.hirono || detailsConfig.boxes?.length)
        );
        navigationSlot = hasDetailsStep ? "productDetails" : "shoes";
    }

    if (state.currentCategory === "hirono") {
        syncHironoGenderOptions();
    }

    // Bumalik mula sa Review / nag-e-edit ng naka-save na figure: kumpleto na ang design,
    // kaya clickable agad ang mga pill (shortcut). Review pill: sa resume lang;
    // sa edit mode ang "Save Changes" ang nagse-save, kaya hanggang Box/Parts lang.
    if (restoreMode === "resume" || restoreMode === "edit") {
        const restoredBox = (() => {
            const config = getProductDetailsConfig();
            return Boolean(config && (config.hirono || config.boxes?.length));
        })();

        wizardMaxStep = restoreMode === "resume"
            ? 4
            : (restoredBox ? 3 : 2);
        wizardSigs = null;
    }

    syncSelectedCardsFromState();


    updateSectionVisibility();
    updateSelectedItemsUI();
    updatePriceDisplay();
    renderProductDetailsPanel();


    void renderCurrentCategory();


    return true;

}


async function loadBodyModel(path, token) {

    const requestId =
        ++loadTokens.body;


    removeFromScene(
        bodyModel
    );


    bodyModel = null;


    let gltf = null;

    try {
        gltf =
            await loadGLTF(
                path
            );
    }
    catch (error) {
        // kung hindi ma-load ang Funko Girl, huwag masira ang buong figure
        if (!/Funko pop-girl\.glb$|funko girl\.glb$/i.test(path)) {
            throw error;
        }
        console.warn("Funko Girl GLB failed to load, falling back to Funko Pop Default.", error);
    }


    if (
        (!gltf || !hasRenderableMeshes(gltf)) &&
        /Funko pop-girl\.glb$|funko girl\.glb$/i.test(path)
    ) {
        console.warn(
            "Funko Girl GLB is empty, falling back to Funko Pop Default."
        );


        if (gltf) {
            disposeObject3D(
                gltf
            );
        }


        gltf =
            await loadGLTF(
                "dressup-assets/GLB_files/Funko Pop Standee Boy/funko boy.glb"
            );
    }


    if (token !== renderToken) {
        disposeObject3D(
            gltf
        );
        return null;
    }


    if (requestId !== loadTokens.body) {
        disposeObject3D(
            gltf
        );
        return null;
    }


    bodyModel = gltf;

    bodyModel.name = "BodyModel";


    configureModel(
        bodyModel
    );


    scene.add(
        bodyModel
    );


    return bodyModel;

}


// Funko Boy: itago ang default na "sando" at "shorts" mesh ng body GLB kapag
// may ibang top/bottom na napili (hindi na "Sando"/"Shorts" ang default card).
function updateDefaultClothesVisibility() {

    if (!bodyModel || state.currentCategory !== "funko") {
        return;
    }

    const top = state.funko.top;
    const bottom = state.funko.bottom;

    const showSando = !top || (top.name === "Sando" && isPlaceholderModelPath(top.model));
    const showShorts = !bottom || (bottom.name === "Shorts" && isPlaceholderModelPath(bottom.model));

    bodyModel.traverse(function(node) {
        const name = String(node.name || "").toLowerCase();
        if (name.includes("sando")) {
            node.visible = showSando;
        }
        else if (name.includes("shorts")) {
            node.visible = showShorts;
        }
    });

}


async function loadAccessory(slot, path, colorValue, token) {

    const requestId =
        ++loadTokens[slot];


    removeFromScene(
        currentObjects[slot]
    );


    currentObjects[slot] = null;


    // Default / placeholder items (Sando, Shorts, Shoes, Default Hair, etc.)
    // wala pang 3D file - naka-select lang sila, walang ilo-load sa viewer.
    if (isPlaceholderModelPath(path)) {
        updateDefaultClothesVisibility();
        return null;
    }


    const gltf =
        await loadGLTF(
            path
        );


    if (token !== renderToken) {
        disposeObject3D(
            gltf
        );
        return null;
    }


    if (requestId !== loadTokens[slot]) {
        disposeObject3D(
            gltf
        );
        return null;
    }


    gltf.name =
        `Selected_${slot}`;


    configureModel(
        gltf
    );


    scene.add(
        gltf
    );


    currentObjects[slot] =
        gltf;

    updateDefaultClothesVisibility();


    if (colorValue) {
        applyColorToModel(
            gltf,
            colorValue,
            {
                clearTextureMaps: true,
                skipMaterialFragments: [
                    "design",
                    "logo",
                    "print"
                ]
            }
        );
    }


    return gltf;

}


function applySkinColor(color) {

    if (!bodyModel) {
        return;
    }


    const isHirono = state.currentCategory === "hirono";
    const isFunko = state.currentCategory === "funko";

    // Stand (Acrylic_Stand / Clear_Acrylic) must never take the skin color, for every figure.
    const standMaterialFragments = ["stand", "pedestal", "support", "clear_acrylic"];
    const standNodeFragments = ["acrylic_stand", "stand", "pedestal", "support"];

    // Skin Tone ay para sa balat LANG. Buhok, mata, damit at sapatos na kasama sa body GLB
    // (default hair / sando / shorts / shoes) ay hindi dapat makuha ang kulay ng balat -
    // dati kasi lahat ng mesh (maliban sa stand) ay kinukulayan, kaya "lahat" may skin tone.
    const nonSkinFragments = [
        "hair", "eye", "brow", "lash", "teeth", "tongue",
        "sando", "tank", "shirt", "polo", "jacket", "hoodie", "sweater", "coat", "dress",
        "short", "pant", "slack", "skirt",
        "sock", "shoe", "sneaker", "boot", "sole", "croc"
    ];

    // Mga pangalang kadalasang balat. Kapag may nakitang ganito sa body GLB, ITO LANG ang
    // kukulayan (kaya hindi na madadamay ang default hair kahit kakaiba ang pangalan niya).
    const skinNameFragments = [
        "skin", "body", "face", "head", "arm", "leg", "hand", "neck", "flesh", "torso", "ear", "nose", "foot", "feet"
    ];

    const has = (text, fragments) => fragments.some(fragment => text.includes(fragment));
    const candidates = [];

    bodyModel.traverse(function(node) {
        if (!node.isMesh || !node.material) {
            return;
        }

        const materials = Array.isArray(node.material) ? node.material : [node.material];
        const materialText = materials.map(material => String(material.name || "").toLowerCase()).join(" ");

        // pangalan ng mesh + lahat ng parent group (hindi kasama ang root ng model)
        const chain = [];
        for (let current = node; current && current !== bodyModel; current = current.parent) {
            chain.push(String(current.name || "").toLowerCase());
        }
        const nameText = chain.join(" ");
        const allText = nameText + " " + materialText;

        const isStand = has(String(node.name || "").toLowerCase() + " " + materialText, [...standNodeFragments, ...standMaterialFragments]);
        const isShoeFoot = isHirono && /foot/.test(allText) && /shoe|sole/.test(allText);

        if (isStand || isShoeFoot || has(allText, nonSkinFragments)) {
            return;
        }

        candidates.push({ node, skinNamed: has(allText, skinNameFragments), label: allText });
    });

    const skinNamed = candidates.filter(item => item.skinNamed);
    const targets = (skinNamed.length ? skinNamed : candidates).map(item => item.node);

    console.info(
        "[DressUp] Skin tone applied to:",
        targets.map(node => node.name || "(no name)"),
        "| not tinted:",
        []
            .concat(candidates.filter(item => !targets.includes(item.node)).map(item => item.node.name || "(no name)"))
    );

    applyColorToMeshList(
        targets,
        color,
        {
            clearTextureMaps: false,
            skipMaterialFragments: [
                ...standMaterialFragments,
                ...nonSkinFragments
            ]
        }
    );

}


// Funko: top/topColor, Hirono: outfit/outfitColor. Ang default Sando ay
// part ng body GLB kaya dito lang nagbabago ang kulay niya, hindi sa skin.
const sandoSlotsByCategory = {
    funko: { top: "top", color: "topColor" },
    hirono: { top: "outfit", color: "outfitColor" }
};


function isSandoColorSlot(category, slot) {

    const slots = sandoSlotsByCategory[category];

    return Boolean(slots && (slot === slots.top || slot === slots.color));

}


function isDefaultSandoSelected() {

    const category = state.currentCategory;
    const slots = sandoSlotsByCategory[category];
    const top = slots && state[category] && state[category][slots.top];

    return Boolean(
        top &&
        top.name === "Sando" &&
        isPlaceholderModelPath(top.model)
    );

}


function applySandoColor(color) {

    if (!bodyModel || !isDefaultSandoSelected() || !color) {
        return;
    }

    const sandoMeshes = [];

    bodyModel.traverse(function(node) {
        if (node.isMesh && String(node.name || "").toLowerCase().includes("sando")) {
            sandoMeshes.push(node);
        }
    });

    applyColorToMeshList(sandoMeshes, color, {
        clearTextureMaps: true,
        skipMaterialFragments: ["design", "logo", "print"]
    });

}


async function renderCurrentCategory() {

    const activeCategory =
        state.currentCategory;


    const token =
        ++renderToken;


    clearCurrentScene();


    if (!activeCategory) {
        setFigurePromptVisible(
            true
        );
        updateSectionVisibility();
        updateSelectedItemsUI();
        updatePriceDisplay();
        return;
    }


    const activeState =
        state[activeCategory];


    if (!activeState.model) {
        setFigurePromptVisible(
            true
        );
        updateSectionVisibility();
        updateSelectedItemsUI();
        updatePriceDisplay();
        return;
    }


    setFigurePromptVisible(
        true
    );


    try {

        // Chibi Girl and the Chibi Boy standee have separate Dark body GLBs.
        const girlBodyVariant = getChibiGirlBodyVariantPath(activeState.model.model, activeState.skin);
        const boyBodyVariant = getChibiBoyBodyVariantPath(activeState.model.model, activeState.skin);
        const bodyVariant = girlBodyVariant || boyBodyVariant;

        const bodyPath =
            bodyVariant || activeState.model.model;


        if (!bodyPath) {
            return;
        }


        await loadBodyModel(
            bodyPath,
            token
        );


        if (token !== renderToken) {
            return;
        }

        // Frame the base figure first so the preview stays visible even if
        // an optional accessory asset cannot be loaded.
        frameFigure();

        setFigurePromptVisible(
            false
        );


        if (bodyVariant) {
            // Variant GLBs already contain their intended skin materials.
        }
        else if (activeState.skin && (activeCategory === "hirono" || activeCategory === "chibi") && activeState.skin.name === "Light") {
            // Light means the original GLB materials: do not apply a tint.
        }
        else if (activeState.skin) {
            applySkinColor(
                activeState.skin.color
            );
        }
        else if (activeCategory === "chibi") {
            // Chibi: original na kulay ng GLB hangga't wala pang skin tone
        }
        else if (activeCategory === "hirono") {
            // Hirono starts neutral grey until the customer chooses a skin tone.
            applySkinColor("#808080");
        }


        const order =
            getActiveSlotOrder();


        for (const slot of order) {

            const item =
                activeState[slot];


            if (!item) {
                continue;
            }


            const colorSlot =
                getSlotColorSlot(
                    slot
                );


            let colorItem =
                colorSlot
                    ? activeState[colorSlot]
                    : null;

            // coat / may design / pattern - walang custom color
            if (colorItem && !isItemColorable(slot, item.name)) {
                colorItem = null;
            }


            try {
                await loadAccessory(
                    slot,
                    item.model,
                    slot === "bottom" && activeCategory === "funko"
                        ? null
                        : (colorItem ? colorItem.color : null),
                    token
                );

                if (
                    isSandoColorSlot(activeCategory, slot) &&
                    colorItem
                ) {
                    applySandoColor(colorItem.color);
                }
            }
            catch (error) {
                console.warn(
                    `Unable to load ${slot} accessory in the preview:`,
                    error
                );
            }


            if (token !== renderToken) {
                return;
            }


            if (
                activeCategory === "funko" &&
                slot === "bottom"
            ) {
                applyBottomPartColors(
                    currentObjects.bottom,
                    activeState.pantsColor && activeState.pantsColor.color
                );
            }

        }


        updateDefaultClothesVisibility();

        frameFigure();

    }
    catch (error) {

        console.error(
            "Failed to render figure:",
            error
        );

        setFigurePromptVisible(
            true
        );

    }


    updateSectionVisibility();
    updateSelectedItemsUI();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}


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

    return labels[slot] || slot
        .replace(/([A-Z])/g, " $1")
        .replace(/^./, character => character.toUpperCase());
}


function placeDesignStepPicker(currentSlot, sections) {
    const jump = document.querySelector(".design-step-jump");
    const navigation = document.getElementById("designStepNavigation");

    if (!jump || !navigation) {
        return;
    }

    // Box/Add-ons step (Funko/Hirono, hindi kailanman ma-reach ng Chibi): walang
    // "DESIGN SECTION" jump dropdown, Previous/Next na lang. Inline style dahil may
    // CSS rule na mas mataas ang specificity kaysa default [hidden].
    if (navigationSlot === "productDetails") {
        restoreDesignSectionHeaders();
        navigation.prepend(jump);
        jump.hidden = true;
        jump.style.display = "none";
        return;
    }

    // Figure Details (Style, Type, Size, Gender, Skin) = iisang screen: walang pill/filter,
    // ang heading na "Figure Details" lang ang nasa itaas.
    if (onFirstStep && figureStyleSection && !figureStyleSection.hidden) {
        placeFigureStylePicker();
        return;
    }

    // Gender & Skin Tone, Hair, Top, Bottom, Shoes: may sariling header sa taas (tulad ng
    // "FIGURE STYLE"), walang dropdown. Ibalik ang lahat ng header na dating tinago.
    restoreDesignSectionHeaders();
    navigation.prepend(jump);
    jump.hidden = true;
    jump.style.display = "none";
}


function isElementShown(element) {
    return Boolean(element) && !element.hidden && element.style.display !== "none";
}


// ibalik ang mga header na tinago ng dropdown (pag lumipat ng step)
function restoreDesignSectionHeaders() {
    document
        .querySelectorAll('#dressUpModule [data-hidden-by-picker="true"]')
        .forEach(heading => {
            heading.style.display = "";
            delete heading.dataset.hiddenByPicker;
        });
}


// Figure Style step: dropdown din ang header (pumalit sa "FIGURE STYLE" kicker)
function placeFigureStylePicker() {
    const jump = document.querySelector(".design-step-jump");
    const navigation = document.getElementById("designStepNavigation");

    if (!jump) {
        return;
    }

    restoreDesignSectionHeaders();

    // walang pill sa Figure Details screen; itabi sa navigation (nakatago)
    if (navigation) {
        navigation.prepend(jump);
    }

    jump.hidden = true;
    jump.style.display = "none";
}


// Custom dropdown (kamukha ng "All Collections" dropdown sa Collection page).
// Naka-tago ang native <select>; ito ang nakikita, at sine-sync lang sa select.
function syncDesignStepDropdown() {
    const jump = document.querySelector(".design-step-jump");
    const picker = document.getElementById("designStepPicker");

    if (!jump || !picker) {
        return;
    }

    let dropdown = jump.querySelector(".ds-dropdown");

    if (!dropdown) {
        dropdown = document.createElement("div");
        dropdown.className = "ds-dropdown";
        dropdown.innerHTML =
            '<button type="button" class="ds-button" aria-haspopup="listbox" aria-expanded="false"></button>' +
            '<div class="ds-menu" role="listbox"></div>';
        jump.appendChild(dropdown);

        const button = dropdown.querySelector(".ds-button");

        button.addEventListener("click", event => {
            event.preventDefault();
            event.stopPropagation();
            const open = !dropdown.classList.contains("open");
            dropdown.classList.toggle("open", open);
            button.setAttribute("aria-expanded", open ? "true" : "false");
        });

        document.addEventListener("click", event => {
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove("open");
                button.setAttribute("aria-expanded", "false");
            }
        });

        document.addEventListener("keydown", event => {
            if (event.key === "Escape") {
                dropdown.classList.remove("open");
                button.setAttribute("aria-expanded", "false");
            }
        });
    }

    const button = dropdown.querySelector(".ds-button");
    const menu = dropdown.querySelector(".ds-menu");
    const selected = picker.options[picker.selectedIndex] || picker.options[0];

    button.textContent = selected ? selected.textContent : "Figure Style";
    dropdown.classList.remove("open");
    button.setAttribute("aria-expanded", "false");
    menu.replaceChildren();

    Array.from(picker.options).forEach(option => {
        const item = document.createElement("button");
        item.type = "button";
        item.setAttribute("role", "option");
        item.textContent = option.textContent;
        item.dataset.value = option.value;

        if (option === selected) {
            item.classList.add("active");
            item.setAttribute("aria-selected", "true");
        }

        item.addEventListener("click", event => {
            event.preventDefault();
            event.stopPropagation();
            dropdown.classList.remove("open");

            if (picker.value === option.value) {
                return;
            }

            picker.value = option.value;
            picker.dispatchEvent(new Event("change"));
        });

        menu.appendChild(item);
    });
}


// balik sa itaas ang scroll ng Figure Details tuwing lilipat ng step
function resetDressUpScroll() {
    const scrollArea = document.getElementById("dressUpRightScroll");

    if (scrollArea) {
        scrollArea.scrollTop = 0;
    }
}


function renderDesignNavigation(sequence, sections, activeState, currentSlot, pairedSlotInput) {

    const pairedSlots = Array.isArray(pairedSlotInput)
        ? pairedSlotInput
        : (pairedSlotInput ? [pairedSlotInput] : []);
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
    const stepPicker = document.getElementById("designStepPicker");
    const detailsStep = navigationSlot === "productDetails";

    if (!navigation || !previousButton || !nextButton || !sequence?.length || !currentSlot) {
        if (navigation) {
            navigation.hidden = true;
        }
        return;
    }

    const currentIndex = Math.max(
        0,
        sequence.indexOf(currentSlot)
    );
    const isLastDesignStep = currentIndex >= sequence.length - 1;

    if (stepPicker) {
        stepPicker.replaceChildren();

        const figureDetailsOption = document.createElement("option");
        figureDetailsOption.value = "figureDetails";
        figureDetailsOption.textContent = "Figure Details";
        figureDetailsOption.selected = currentIndex === 0;
        stepPicker.appendChild(figureDetailsOption);

        sequence
            .slice(1, currentIndex + 1)
            .filter(slot => !mergedWithModel.includes(slot))
            .forEach(slot => {
                const option = document.createElement("option");
                option.value = slot;
                option.textContent = getDesignStepLabel(slot);
                option.selected = slot === currentSlot;
                stepPicker.appendChild(option);
            });

        stepPicker.onchange = () => {
            if (stepPicker.value === "figureDetails") {
                navigationSlot = sequence[0];
                updateSectionVisibility();
                resetDressUpScroll();
                return;
            }

            navigationSlot = stepPicker.value;
            updateSectionVisibility();
        resetDressUpScroll();
        };
    }

    navigation.hidden = false;
    placeDesignStepPicker(currentSlot, sections);
    previousButton.disabled = false;

    // Last design step (e.g. Shoes for Chibi) goes straight to
    // finalsummary.php if walang Box/Add-ons. Kung meron (Funko/Hirono),
    // doon muna bago dito.
    const detailsConfig = getProductDetailsConfig();
    const hasDetailsStep = Boolean(
        detailsConfig && (detailsConfig.hirono || detailsConfig.boxes?.length)
    );

    // Itago ang Previous/Next pag nasa Box/Add-ons (detailsStep) na - continueBtn na ang "Next"
    nextButton.hidden = detailsStep;
    // Inline display dahil may CSS rule na puwedeng manalo laban sa default [hidden]
    nextButton.style.display = detailsStep ? "none" : "";
    nextButton.textContent = (dressUpViewing && !hasDetailsStep && isLastDesignStep) ? "Save Changes" : "Next";
    const canSkipCurrentSlot = !["model", "skin", "productType", "mode"].includes(currentSlot);

    // Size tracked separately (productDetails.size), hindi sa activeState - kaya sariling check
    const sizeStepConfig = (currentSlot === "size" || pairedSlots.includes("size")) ? getProductDetailsConfig() : null;
    const sizeStepRequired = (currentSlot === "size" || pairedSlots.includes("size")) && Boolean(sizeStepConfig?.sizes?.length);
    const sizeStepSatisfied = !sizeStepRequired || Boolean(productDetails.sizeSelected);
    // When a slot is shown paired with another one (e.g. Gender shown
    // together with Skin Tone), both must be selected before the user
    // can move on - picking only one of the two should not unlock Next.
    const pairedSlotSatisfied = pairedSlots.filter(slot => slot !== "size").every(slot => Boolean(activeState[slot]));
    nextButton.disabled =
        detailsStep ||
        (!activeState[currentSlot] && !canSkipCurrentSlot) ||
        !sizeStepSatisfied ||
        !pairedSlotSatisfied;

    previousButton.onclick = () => {
        if (detailsStep) {
            navigationSlot = sequence[sequence.length - 1];
            updateSectionVisibility();
        resetDressUpScroll();
            return;
        }

        if (currentIndex <= 0) {
            goToFigureStyle();
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
        if (!activeState[currentSlot] && !canSkipCurrentSlot) {
            return;
        }

        if (!sizeStepSatisfied) {
            return;
        }

        if (!pairedSlotSatisfied) {
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

            // May Box/Add-ons pa (Funko/Hirono) - doon muna, nasa
            // parehong page pa rin. Kung wala (Chibi), tapos na ang
            // design steps - dito na papunta sa sarili nang Final
            // Order Summary page (finalsummary.php) sa halip na isang
            // section na lang sa loob ng page ring ito.
            if (!hasDetailsStep) {
                window.location.href = "finalsummary.php";
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


function goToFigureStyle() {

    state.currentCategory = null;
    navigationSlot = null;

    resetFigureStyleSelection();

    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}




function resetFigureStyleSelection() {

    pendingFigureCategory = null;

    clearSelectedClasses(
        '[data-tab="figure"]'
    );

    const nextButton =
        document.getElementById("nextFigureStyle");

    if (nextButton) {
        nextButton.disabled = true;
    }

}


function selectCategory(category) {

    if (!categoryPanels[category]) {
        return;
    }

    pendingFigureCategory = null;
    pickedSlots.clear();

    // bagong Figure Style = bagong flow, ulit lahat ng step
    wizardMaxStep = 1;
    wizardSigs = null;

    try {
        localStorage.setItem(
            COMMISSION_FIGURE_CATEGORY_KEY,
            category
        );
    }
    catch (error) {
        console.warn(
            "Unable to persist figure category:",
            error
        );
    }


    // Choosing a style starts that category's flow from the beginning.
    // This prevents an old model selection from skipping the required type
    // choice (Funko boy/girl, Hirono standee/keychain, or Chibi product type).
    // lahat ng figure style nire-reset (hindi lang ang bagong pili),
    // para walang naiwang size/model/skin/kulay galing sa dating style.
    ["funko", "hirono", "chibi"].forEach(clearCategoryState);
    clearSelectedClasses("#dressUpModule [data-slot]");

    productDetails = {
        productKey: "",
        size: "",
        sizeSelected: false,
        figureName: "",
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

    [figureNameInput, boxNameInput, boxNumberInput, boxColorInput]
        .forEach(input => { if (input) { input.value = ""; } });

    if (sizeChoiceGrid) {
        sizeChoiceGrid.innerHTML = "";
    }

    clearCurrentScene();

    state.currentCategory =
        category;
    navigationSlot = null;

    syncCustomColorPickers();


    styleCards.forEach(
        function(card) {
            card.classList.toggle(
                "selected",
                card.dataset.figure === category
            );
        }
    );


    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);


    void renderCurrentCategory();

}


function selectModel(card) {

    const category =
        card.dataset.figure;


    const stateForCategory =
        state[category];


    const item =
        buildItemFromCard(
            card
        );


    // Hirono's Standee/Keychain mode is chosen in its own Product Type
    // step (selectHironoType) before Gender is shown, so it should carry
    // over as-is when a Gender card is picked here.
    const preservedHironoMode = category === "hirono"
        ? stateForCategory.mode
        : null;

    if (
        stateForCategory.model &&
        stateForCategory.model.model === item.model &&
        stateForCategory.model.name === item.name
    ) {
        return;
    }


    clearCategoryState(
        category
    );


    if (category === "hirono") {
        stateForCategory.mode =
            preservedHironoMode;
    }

    if (category === "chibi") {
        stateForCategory.productType =
            card.dataset.productType || "standee";
    }


    stateForCategory.model = item;


    markSelectedCard(
        card
    );


    state.currentCategory =
        category;
    navigationSlot = "model";


    if (category === "hirono") {
        syncHironoGenderOptions();
    }


    applyDefaultSelections(
        category
    );


    syncCustomColorPickers();

    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);


    void renderCurrentCategory();

}


function selectChibiType(card) {

    const type =
        card.dataset.productType || "standee";

    clearCategoryState("chibi");
    state.chibi.productType = type;
    state.currentCategory = "chibi";
    // Stay on the Product Type step after picking it - advancing to
    // Gender only happens when the required "Next" button is pressed.
    navigationSlot = "productType";

    syncChibiModelOptions();

    markSelectedCard(card);
    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
}


// Chibi: ipakita lang ang Gender cards ng napiling Product Type (Standee o
// Keychain). Tinatawag din kapag ibinabalik ang design (Back mula sa Final
// Summary), para hindi dumoble ang Gender (Boy/Girl + Keychain Boy/Girl).
function syncChibiModelOptions() {

    const type =
        state.chibi.productType === "keychain" ? "keychain" : "standee";

    document
        .querySelectorAll('[data-figure="chibi"][data-slot="model"]')
        .forEach(modelCard => {
            modelCard.hidden = (modelCard.dataset.productType || "standee") !== type;
            modelCard.style.display = modelCard.hidden ? "none" : "";
        });

}


function selectHironoType(card) {

    const mode =
        card.dataset.mode || "standee";

    clearCategoryState("hirono");
    state.hirono.mode = mode;
    state.currentCategory = "hirono";
    // Stay on the Product Type step after picking it - advancing to
    // Gender only happens when the required "Next" button is pressed.
    navigationSlot = "mode";

    syncHironoGenderOptions();
    markSelectedCard(card);
    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);
}


// Hirono (galing sa DressUp): magkaiba ang body ng Boy/Girl at ng Standee/Keychain.
// Ipakita lang ang Gender cards ng napiling Product Type, at ang hair / top /
// bottom / shoes na para sa napiling gender (data-hirono-gender).
function syncHironoGenderOptions() {

    const cardMode =
        getHironoMode() === "keychain" ? "keychain" : "standee";

    const model =
        state.hirono.model;

    const selectedGender = model
        ? (/girl/i.test(model.name || "") ? "girl" : "boy")
        : null;

    document
        .querySelectorAll('[data-figure="hirono"][data-slot="model"]')
        .forEach(card => {
            card.hidden = (card.dataset.mode || "standee") !== cardMode;
            // Inline display dahil may CSS rule (display:flex) na mananalo laban sa [hidden]
            card.style.display = card.hidden ? "none" : "";
        });

    document
        .querySelectorAll('[data-figure="hirono"][data-hirono-gender]')
        .forEach(card => {
            card.hidden = Boolean(selectedGender) && card.dataset.hironoGender !== selectedGender;
            // Some shared card styles set a display value, so clear it explicitly
            // for matching cards and keep filtered cards out of the layout.
            card.style.display = card.hidden ? "none" : "";
        });

}


function selectSkin(card) {

    const category =
        card.dataset.figure;


    const stateForCategory =
        state[category];


    const item =
        buildItemFromCard(
            card
        );


    if (
        stateForCategory.skin &&
        stateForCategory.skin.color === item.color
    ) {
        return;
    }


    stateForCategory.skin = item;


    markSelectedCard(
        card
    );


    state.currentCategory =
        category;
    navigationSlot = "skin";


    if (category === "hirono" || category === "chibi") {
        // Reload from the original GLB so choosing Light restores the model's
        // original skin material after the neutral-grey default.
        void renderCurrentCategory();
    }
    else {
        applySkinColor(item.color);
    }


    updateSectionVisibility();
    updateSelectedItemsUI();
    renderProductDetailsPanel();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}


async function selectAccessory(card) {

    const category =
        card.dataset.figure;


    const slot =
        card.dataset.slot;

    navigationSlot = slot;


    const item =
        buildItemFromCard(
            card
        );


    const stateForCategory =
        state[category];


    const currentItem =
        stateForCategory[slot];


    if (
        currentItem &&
        currentItem.model === item.model &&
        currentObjects[slot]
    ) {
        if (slot === "bottom" && category === "funko") {
            applyBottomPartColors(
                currentObjects.bottom,
                stateForCategory.pantsColor && stateForCategory.pantsColor.color
            );
        }
        else if (item.color) {
            const colorSlot =
                getSlotColorSlot(
                    slot
                );

            const colorItem =
                colorSlot
                    ? stateForCategory[colorSlot]
                    : null;

            if (colorItem) {
                applyColorToModel(
                    currentObjects[slot],
                    colorItem.color,
                    {
                        clearTextureMaps: true,
                        skipMaterialFragments: [
                            "design",
                            "logo",
                            "print"
                        ]
                    }
                );
            }
        }

        return;
    }


    stateForCategory[slot] =
        item;


    state.currentCategory =
        category;


    markSelectedCard(
        card
    );


    const colorSlot =
        getSlotColorSlot(
            slot
        );


    // pumili ng damit na hindi pwedeng kulayan → burahin ang naunang custom color
    if (colorSlot && stateForCategory[colorSlot] && !isItemColorable(slot, item.name)) {
        delete stateForCategory[colorSlot];
    }

    const colorItem =
        colorSlot
            ? stateForCategory[colorSlot]
            : null;


    try {
        const loaded =
            await loadAccessory(
                slot,
                item.model,
                colorItem ? colorItem.color : null,
                renderToken
            );

        if (
            isSandoColorSlot(category, slot) &&
            colorItem
        ) {
            applySandoColor(colorItem.color);
        }


        if (loaded) {
            frameFigure();
        }
    }
    catch (error) {
        console.error(
            `Failed to load ${slot}:`,
            error
        );
    }


    updateSectionVisibility();
    updateSelectedItemsUI();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}


function selectColor(card) {

    const category =
        card.dataset.figure;


    const slot =
        card.dataset.slot;


    const item =
        buildItemFromCard(
            card
        );


    const stateForCategory =
        state[category];


    const currentItem =
        stateForCategory[slot];


    if (
        currentItem &&
        currentItem.color === item.color
    ) {
        return;
    }


    stateForCategory[slot] =
        item;


    markSelectedCard(
        card
    );


    state.currentCategory =
        category;
    navigationSlot = slot;


    let accessorySlot =
        slot.replace(
            "Color",
            ""
        );

    if (category === "funko" && slot === "pantsColor") {
        accessorySlot = "bottom";
    }
    else if (
        category === "funko" &&
        slot === "bottomColor" &&
        isFunkoGirlModel(stateForCategory.model)
    ) {
        accessorySlot = "girlBottom";
    }


    const currentObject =
        getLoadedObject(
            accessorySlot
        );


    if (currentObject) {
        if (
            category === "funko" &&
            (accessorySlot === "bottom" || slot === "bottomColor")
        ) {
            applyBottomPartColors(
                currentObject,
                item.color
            );
        }
        else {
            applyColorToModel(
                currentObject,
                item.color,
                {
                    clearTextureMaps: true,
                    skipMaterialFragments: [
                        "design",
                        "logo",
                        "print"
                    ]
                }
            );
        }
    }
    else if (isSandoColorSlot(category, slot)) {
        applySandoColor(item.color);
    }


    updateSectionVisibility();
    updateSelectedItemsUI();
    updatePriceDisplay();
    saveCustomizationSnapshot(false, false);

}


function handleCardClick(card) {

    if (card.dataset.slot) {
        pickedSlots.add(card.dataset.slot);
    }

    // i-refresh ang numero sa bilog ng napiling card pagkatapos ng pili
    setTimeout(updateStepBadgesAndLocks, 80);
    setTimeout(updateStepBadgesAndLocks, 600);

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


    const slot =
        card.dataset.slot;


    if (slot === "chibiType") {
        selectChibiType(
            card
        );
        return;
    }


    if (slot === "hironoType") {
        selectHironoType(
            card
        );
        return;
    }


    if (slot === "model") {
        selectModel(
            card
        );
        return;
    }


    if (slot === "skin") {
        selectSkin(
            card
        );
        return;
    }


    if (slot.endsWith("Color")) {
        selectColor(
            card
        );
        return;
    }


    void selectAccessory(
        card
    );

}


// DRESS UP ORDER SUBMISSION (finalsummary.php -> submit_order.php -> payment)

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


function showDressUpNotice(message, type) {

    // shared pop up (Shared/all-in-one.php) - iisang itsura sa buong system
    if (window.csToast) {
        window.csToast(message, type || "error");
        return;
    }

    let container =
        document.getElementById("noticeContainer");

    if (!container) {
        container = document.createElement("div");
        container.id = "noticeContainer";
        container.className = "notice-container";
        document.body.appendChild(container);
    }

    container.innerHTML = "";

    const noticeType = type || "error";

    const notice = document.createElement("div");
    notice.className = "notice-toast notice-" + noticeType;
    notice.innerHTML =
        '<div class="notice-icon">' +
        (noticeType === "success" ? "✓" : (noticeType === "info" ? "✦" : "!")) +
        '</div><div class="notice-message"></div>';
    notice.querySelector(".notice-message").textContent = message;

    container.appendChild(notice);

    requestAnimationFrame(() => notice.classList.add("notice-show"));

    setTimeout(() => {
        notice.classList.remove("notice-show");
        notice.classList.add("notice-hide");
        setTimeout(() => notice.remove(), 250);
    }, 5000);

}


function getDressUpProductTypeLabel() {

    if (state.currentCategory === "hirono") {
        const mode = getHironoMode();

        if (mode === "headKeychain") {
            return "Head Only Keychain";
        }

        return mode === "keychain"
            ? "Full Body Keychain"
            : "Full Body Standee";
    }

    if (state.currentCategory === "chibi") {
        return state.chibi.productType === "keychain"
            ? "Full Body Keychain"
            : "Full Body Standee";
    }

    return "Full Body Standee";

}


function getDressUpGenderLabel(activeState) {

    // Male / Female lang (hindi Boy / Girl)
    if (!activeState || !activeState.model) {
        return "";
    }

    if (state.currentCategory === "funko") {
        return isFunkoGirlModel(activeState.model) ? "Female" : "Male";
    }

    if (state.currentCategory === "chibi") {
        return isChibiGirlModel(activeState.model) ? "Female" : "Male";
    }

    return /girl/i.test(activeState.model.name || "") ? "Female" : "Male";

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

    getActiveSlotOrder().forEach(slot => {

        if (
            slot === "model" ||
            slot === "skin" ||
            slot.toLowerCase().endsWith("color")
        ) {
            return;
        }

        const item = activeState[slot];

        if (!item) {
            return;
        }

        const isFunkoBottom =
            category === "funko" && slot === "bottom";

        let colorSlot = isFunkoBottom
            ? "pantsColor"
            : getSlotColorSlot(slot);

        // kung walang kulay sa inaasahang key (hal. shoesColor ng Hirono, o
        // hairColor na ginagamit din ng Funko Girl), hanapin ang color slot
        // na tumuturo sa accessory na ito
        if (!colorSlot || !activeState[colorSlot]) {
            const matchingColorSlot = Object.keys(activeState).find(key =>
                key.endsWith("Color") &&
                activeState[key] &&
                activeState[key].color &&
                getColorAccessorySlot(category, key) === slot
            );

            if (matchingColorSlot) {
                colorSlot = matchingColorSlot;
            }
        }

        const colorItem =
            colorSlot ? activeState[colorSlot] : null;

        const pricedItem = cloneDressUpItem(item);
        pricedItem.price = getItemPrice(item);
        pricedItem.billable = pricedItem.price > 0;

        slots.push({
            slot,
            label: DRESSUP_SLOT_LABELS[slot] || getDesignStepLabel(slot),
            item: pricedItem,
            colorName: colorItem ? (colorItem.name || "") : "",
            colorValue: colorItem ? (colorItem.color || "") : "",
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
        nameFee: productDetails.figureName && productDetails.figureName.trim() ? 50 : 0,
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

        const blindType =
            productDetails.blindBox === "set" ? "set" : "regular";

        const blindPrice = blindType === "set" ? 350 : 150;

        const addonMap = {
            tearPaper: ["tear_blind_paper", 50],
            pouch: ["pouch", 50],
            digitalArt: ["digital_art", 150]
        };

        const blindItems = {};
        let itemsTotal = 0;

        (productDetails.hironoAddons || []).forEach(addon => {
            if (addonMap[addon]) {
                blindItems[addonMap[addon][0]] = addonMap[addon][1];
                itemsTotal += addonMap[addon][1];
            }
        });

        const dateParts =
            String(productDetails.boxDateYmd || "").split("-");

        return {
            boxDetails: {
                addonType: "hirono_blind_box",
                blindType,
                boxDesign: productDetails.boxDesignSelected ? (productDetails.boxDesign || "") : "",
                boxColor: productDetails.boxColor || "",
                letter: productDetails.boxLetter || "",
                nickname: productDetails.boxNickname || "",
                dateMonth: dateParts.length === 3 ? dateParts[1] : "",
                dateDay: dateParts.length === 3 ? dateParts[2] : "",
                blindItems
            },
            boxAddonPrice: blindPrice + itemsTotal
        };

    }

    const selectedBox =
        (config.boxes || []).find(box => box.id === productDetails.box);

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


// DRESS UP "ADD ANOTHER FIGURE" - naka-save sa localStorage ang mga naunang
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
    productDetails = {
        productKey: "",
        size: "",
        sizeSelected: false,
        figureName: "",
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

    [figureNameInput, boxNameInput, boxNumberInput, boxColorInput]
        .forEach(input => { if (input) { input.value = ""; } });

    clearCurrentScene();

    clearSelectedClasses("#dressUpModule [data-slot]");
    resetFigureStyleSelection();

    try {
        localStorage.removeItem(COMMISSION_FIGURE_CATEGORY_KEY);
    }
    catch (error) {
        // ignore
    }

    updateSectionVisibility();
    updateSelectedItemsUI();
    updatePriceDisplay();
    renderProductDetailsPanel();
    setFigurePromptVisible(true);
    resetDressUpScroll();

    clearCustomizationSnapshot();
}

const DRESSUP_CART_KEY = "figurifyDressUpCart";
const DRESSUP_CART_MAX = 10;

// Edit ng naka-save na figure (galing sa Final Summary → dress-up.php?figure=N)
// null = bagong figure ang ginagawa; { index, mode: "edit" } = naka-save na figure ang ine-edit
let dressUpViewing = null;
const DRESSUP_DRAFT_KEY = "figurifyDressUpDraft";
const DRESSUP_NO_DRAFT = "__none__";

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
        const raw = localStorage.getItem(CUSTOMIZATION_STORAGE_KEY);
        localStorage.setItem(DRESSUP_DRAFT_KEY, raw || DRESSUP_NO_DRAFT);
    }
    catch (error) {
        // ignore
    }
}

// ibalik ang tinabing figure (tapos na mag-edit, na-cancel, o umalis sa gitna)
function restoreStashedDraft() {
    try {
        const raw = localStorage.getItem(DRESSUP_DRAFT_KEY);

        if (raw === null) {
            return;
        }

        if (raw === DRESSUP_NO_DRAFT) {
            localStorage.removeItem(CUSTOMIZATION_STORAGE_KEY);
        }
        else {
            localStorage.setItem(CUSTOMIZATION_STORAGE_KEY, raw);
        }

        localStorage.removeItem(DRESSUP_DRAFT_KEY);
    }
    catch (error) {
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
    restoreStashedDraft();
    window.location.href = "finalsummary.php";
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
        showDressUpNotice(built.error);
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
        snapshot: buildCartSnapshot()
    };

    if (!saveDressUpCart(cart)) {
        showDressUpNotice("Can't save this figure. Please try again.");
        return;
    }

    leaveEditMode();
}

// Edit mode: "Save Changes" kapalit ng "+ Add Another Order"; Back → Final Summary
function initDressUpEditMode() {
    if (isFinalSummaryPage || !dressUpViewing) {
        return;
    }

    const show = (id, visible) => {
        const el = document.getElementById(id);
        if (el) {
            el.hidden = !visible;
        }
    };

    show("dressUpAddFigureBtn", false);
    show("dressUpSaveFigureBtn", true);

    const note = document.getElementById("dressUpViewNote");
    if (note) {
        note.hidden = false;
        note.textContent = "Editing Figure " + (dressUpViewing.index + 1) + ". Press Save Changes when you're done.";
    }

    const back = document.getElementById("dressUpBackLink");
    if (back) {
        back.hidden = false; // Back to Final Summary lang sa edit mode
        back.setAttribute("href", "finalsummary.php");
        back.addEventListener("click", () => restoreStashedDraft());
    }

    const save = document.getElementById("dressUpSaveFigureBtn");
    if (save) {
        save.addEventListener("click", event => {
            event.preventDefault();
            saveEditedCartFigure();
        });
    }
}

function getDressUpCart() {
    try {
        const raw = localStorage.getItem(DRESSUP_CART_KEY);
        const list = raw ? JSON.parse(raw) : [];
        return Array.isArray(list) ? list.filter(item => item && item.payload) : [];
    }
    catch (error) {
        return [];
    }
}

function saveDressUpCart(list) {
    try {
        if (list && list.length) {
            localStorage.setItem(DRESSUP_CART_KEY, JSON.stringify(list));
        } else {
            localStorage.removeItem(DRESSUP_CART_KEY);
        }
        return true;
    }
    catch (error) {
        console.warn("Unable to save Dress Up cart:", error);
        return false;
    }
}

function getDressUpCartTotal() {
    return getDressUpCart().reduce(
        (sum, item) => sum + Number(item.total || 0) + Number(item.rushFee || 0),
        0
    );
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
        return { error: "Choose a size first." };
    }

    if (
        config &&
        config.hirono &&
        (productDetails.hironoAddons || []).length &&
        !productDetails.blindBoxSelected
    ) {
        return { error: "Choose a Blind Box type, or remove the add-ons." };
    }

    const design = buildDressUpDesignData(activeState);
    const box = buildDressUpBoxDetails();

    // presyo ng figure mismo (walang rush fee - sa server kinukuwenta iyon,
    // pareho ng Image Submission)
    const figureTotal =
        design.designTotal + design.sizePrice + design.nameFee + box.boxAddonPrice;

    const figureName =
        (productDetails.figureName || "").trim() ||
        (getFigureLabel(state.currentCategory) + " Dress Up");

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

    if (cart.length + 1 >= DRESSUP_CART_MAX) {
        showDressUpNotice("You can add up to " + DRESSUP_CART_MAX + " figures only.");
        return;
    }

    const built = buildCurrentDressUpFigure();

    if (built.error) {
        showDressUpNotice(built.error);
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
        snapshot: buildCartSnapshot()
    });

    if (!saveDressUpCart(cart)) {
        showDressUpNotice("Can't save this figure. Please try again.");
        return;
    }

    // bagong disenyo - burahin ang kasalukuyang draft pero HINDI ang
    // Order Type / Booking Date (hiwalay ang storage key ng mga iyon)
    clearCustomizationSnapshot();

    showDressUpNotice(
        "Figure " + cart.length + " added!",
        "success"
    );

    setTimeout(() => {
        window.location.href = "dress-up.php";
    }, 700);

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
    productDetails = {
        productKey: saved.productKey || "",
        size: saved.figureSizeSelected ? (saved.figureSize || "") : "",
        sizeSelected: Boolean(saved.figureSizeSelected),
        figureName: "",
        box: saved.boxSelected ? (saved.boxType || "") : "",
        boxSelected: Boolean(saved.boxSelected),
        boxName: saved.boxName || "",
        boxNumber: saved.boxNumber || "",
        boxColor: saved.boxColor || "",
        blindBox: saved.blindBoxSelected ? (saved.blindBox || "regular") : "regular",
        blindBoxSelected: Boolean(saved.blindBoxSelected),
        hironoAddons: Array.isArray(saved.hironoAddons) ? saved.hironoAddons : [],
        boxDesign: saved.boxDesignSelected ? (saved.boxDesign || "checkered") : "checkered",
        boxDesignSelected: Boolean(saved.boxDesignSelected),
        boxNickname: saved.boxNickname || "",
        boxLetter: saved.boxLetter || "",
        boxDateYmd: saved.boxDateYmd || ""
    };

    Object.keys(categoryPanels).forEach(function(category) {
        if (saved[category]) {
            state[category] = Object.assign(createCategoryState(), saved[category]);
        }
    });

    state.currentCategory = saved.currentCategory || state.currentCategory;
    syncItemPricesFromCards();
}

function readDraftSnapshot() {
    try {
        const raw = localStorage.getItem(CUSTOMIZATION_STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    }
    catch (error) {
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
    if (!isFinalSummaryPage) {
        return;
    }

    if (snap) {
        previewLock = true;
        applySnapshotToState(snap);
    }
    else if (!leaveFigurePreview()) {
        return;
    }

    void renderCurrentCategory();
}


// aling figure ng cart ang ipinapakita sa Figure Details (null = kasalukuyang figure)
var selectedCartIndex = null;

function applyCartSelectionToDetails() {
    if (!isFinalSummaryPage) {
        return;
    }

    const badge = document.getElementById("dressUpCurrentFigureBadge");
    const cart = getDressUpCart();
    const item = selectedCartIndex === null ? null : cart[selectedCartIndex];

    if (!item) {
        selectedCartIndex = null;
        if (badge) {
            badge.textContent = "FIGURE " + (cart.length + 1);
        }
        return;
    }

    if (badge) {
        badge.textContent = "FIGURE " + (selectedCartIndex + 1);
    }

    const payload = item.payload || {};
    const design = payload.dressup || {};
    const rows = [];
    const add = (header, value, price, colorValue) => {
        if (value) {
            rows.push({ header, value, price: Number(price) > 0 ? Number(price) : null, colorValue: colorValue || "" });
        }
    };

    let figureSum = 0;
    const slotPrice = (n) => { n = Number(n) || 0; figureSum += n; return n; };

    add("Figure Style", item.style);
    add("Product Type", item.product);
    add("Gender", item.gender);
    add("Skin Tone", design.skin && design.skin.name, slotPrice(design.skin && design.skin.price), design.skin && design.skin.color);
    add("Size", item.size, slotPrice(payload.productPrice));
    (design.slots || []).forEach(s => {
        const name = (s.item && s.item.name) || "";
        add(s.label, s.colorName ? name + " • " + s.colorName : name, slotPrice(s.item && s.item.price), s.colorValue);
    });
    if (payload.name && payload.name !== (item.style + " Dress Up")) {
        add("Figure Name", payload.name, slotPrice(50));
    }

    renderRowsIntoContainer(document.getElementById("summaryReceiptItems"), rows);

    const box = payload.boxDetails;
    const boxRows = [];
    if (box) {
        const addBox = (header, value, price) => {
            if (value) {
                boxRows.push({ header, value, price: Number(price) > 0 ? Number(price) : null });
            }
        };
        if (box.addonType === "hirono_blind_box") {
            addBox("Blind Box Type", box.blindType === "set" ? "Blind Box Set" : "Regular Blind Box", box.blindType === "set" ? 350 : 150);
            const labels = {
                tear_blind_paper: "Tear Blind Paper",
                pouch: "Pouch",
                digital_art: "Digital Art (Soft Copy) w/ Photo Card"
            };
            Object.keys(box.blindItems || {}).forEach(k => {
                addBox("Add-on", labels[k] || k, box.blindItems[k]);
            });
            addBox("Box Design", box.boxDesign);
            addBox("Box Color", box.boxColor);
            addBox("Nickname", box.nickname);
            addBox("Letter", box.letter);
            if (box.dateMonth && box.dateDay) {
                addBox("Date", box.dateMonth + "/" + box.dateDay);
            }
        }
        else {
            // presyo ng box = natitira sa total ng figure
            const boxPrice = Math.max(0, (Number(item.total) || 0) - figureSum);
            addBox("Box Type", box.boxType, boxPrice);
            addBox("Box Name", box.boxName);
            addBox("Box Number", box.boxNumber);
            addBox("Box Color", box.boxColor);
        }
    }
    renderRowsIntoContainer(
        document.getElementById("summaryBoxItems"),
        boxRows,
        "No box selected yet."
    );
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
    const built = previewLock
        ? (currentBuiltCache || { error: true })
        : buildCurrentDressUpFigure();
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
            rushFee: built.summary.rushFee
        });
    }

    const currentBadge = document.getElementById("dressUpCurrentFigureBadge");
    if (currentBadge) {
        currentBadge.textContent = "FIGURE " + (cart.length + 1);
    }

    list.innerHTML = "";

    wrap.hidden = false;

    if (!items.length) {
        const empty = document.createElement("div");
        empty.className = "receipt-empty";
        empty.textContent = 'No figure yet.';
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
        price.textContent = formatMoney(Number(item.total || 0) + Number(item.rushFee || 0));

        const actions = document.createElement("div");
        actions.className = "dressup-cart-actions";

        if (item.current) {
            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "dressup-cart-edit";
            edit.textContent = "Edit";
            edit.addEventListener("click", (event) => {
                event.stopPropagation();
                window.location.href = "dress-up.php?resume=1";
            });
            actions.appendChild(edit);
        }
        else if (item.snapshot) {
            const edit = document.createElement("button");
            edit.type = "button";
            edit.className = "dressup-cart-edit";
            edit.textContent = "Edit";
            edit.addEventListener("click", (event) => {
                event.stopPropagation();
                stashCurrentDraftForEdit();
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
            }
            else if (item.snapshot) {
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

// paalala sa Dress Up editor (commission.php) kapag may naunang figure na
function renderDressUpCartBanner() {
    return; 

    if (isFinalSummaryPage || dressUpViewing) {
        return;
    }

    const module = document.getElementById("dressUpModule");

    if (!module) {
        return;
    }

    let banner = document.getElementById("dressUpCartBanner");
    const cart = getDressUpCart();

    if (!cart.length) {
        if (banner) banner.remove();
        return;
    }

    if (!banner) {
        banner = document.createElement("div");
        banner.id = "dressUpCartBanner";
        banner.className = "dressup-cart-banner";
        const host = module.querySelector(".form-shell") || module;
        host.insertBefore(banner, host.firstChild);
    }

    banner.innerHTML = "";

    const text = document.createElement("span");
    text.textContent =
        "You already have " + cart.length + " figure" + (cart.length > 1 ? "s" : "") +
        " in this Dress Up order. You are now designing Figure " + (cart.length + 1) + ".";

    const clear = document.createElement("button");
    clear.type = "button";
    clear.textContent = "Remove saved figures";
    clear.addEventListener("click", () => {
        saveDressUpCart([]);
        renderDressUpCartBanner();
        showDressUpNotice("Saved figures cleared.", "info");
    });

    banner.appendChild(text);
    banner.appendChild(clear);

}


async function submitDressUpOrder(button) {

    if (dressUpOrderSubmitting) {
        return;
    }

    const orderType = getCommissionOrderTypeValue();
    const bookingDate = getCommissionBookingDateValue();

    if (!orderType && !bookingDate) {
        showDressUpNotice("Choose an order type and booking date first.");
        return;
    }

    if (!orderType) {
        showDressUpNotice("Choose an order type first.");
        return;
    }

    if (!bookingDate) {
        showDressUpNotice("Pick a booking date first.");
        return;
    }

    const built = buildCurrentDressUpFigure();

    if (built.error) {
        showDressUpNotice(built.error);
        return;
    }

    // lahat ng figure ng order: mga naunang naidagdag + ang kasalukuyan
    const allFigures =
        getDressUpCart().map(item => item.payload).concat([built.payload]);

    // Diretso na sa bayad - walang extra confirm popup
    if (dressUpOrderSubmitting) {
        return;
    }

    const formData = new FormData();
    formData.append("order_type", orderType);
    formData.append("order_method", "create_style");
    formData.append("booking_date", bookingDate);
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
        }
        catch (parseError) {
            console.error("submit_order.php raw response:", rawText);
        }

        if (!data || !data.success) {
            const message =
                (data && data.message) ||
                "Something went wrong while placing your order. Please try again.";

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

        // naka-save na ang order - linisin ang draft para hindi maulit ang order,
        // at huwag nang i-enable ulit ang button habang nagre-redirect
        orderPlaced = true;
        clearCustomizationSnapshot();
        saveDressUpCart([]);

        // Dress Up: walang quotation - diretso sa Payment & Shipping page
        // (payment_url); sa Verification na ito susuriin ng staff
        // Dress Up: laging diretso sa Payment & Shipping page, hindi sa My Orders
        window.location.href =
            data.payment_url ||
            ("../my-order/payment-shipping.php?order_id=" + data.order_id);

    }
    catch (error) {

        console.error("Dress Up order request failed:", error);
        showDressUpNotice("Can't connect right now. Please try again.");

    }
    finally {

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


function initializeInteractions() {

    initializeCustomColorPickers();

    initWizardPills();

    const nextFigureStyleButton =
        document.getElementById("nextFigureStyle");

    if (nextFigureStyleButton) {
        nextFigureStyleButton.addEventListener(
            "click",
            function() {
                if (!pendingFigureCategory) {
                    return;
                }

                selectCategory(
                    pendingFigureCategory
                );
            }
        );
    }

    styleCards.forEach(
        function(card) {
            card.addEventListener(
                "click",
                function() {
                    handleCardClick(
                        card
                    );
                }
            );
        }
    );


    itemCards.forEach(
        function(card) {
            card.addEventListener(
                "click",
                function() {
                    handleCardClick(
                        card
                    );
                }
            );
        }
    );


    if (resetBtn) {
        resetBtn.addEventListener("click", function() {
            resetDressUpDesign();
        });
    }

    [
        [figureNameInput, "figureName"],
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
        continueBtn.addEventListener(
            "click",
            async function(event) {
                const activeState =
                    getActiveState();

                if (!activeState || !activeState.model) {
                    event.preventDefault();
                    showDressUpNotice(
                        "Choose a figure model first."
                    );
                    return;
                }


                event.preventDefault();

                if (dressUpViewing) {
                    saveEditedCartFigure();
                    return;
                }

                await renderCurrentCategory();

                saveCustomizationSnapshot(
                    true,
                    false
                );

                // Habang nasa Box/Add-ons pa lang (Funko/Hirono), ang
                // "Next" na ito ay pumupunta na sa sarili nang page:
                // finalsummary.php - hindi na isang section lang sa
                // parehong page.
                window.location.href = "finalsummary.php";

            }
        );
    }

    // "Continue to Payment" - isinusumite na ang Dress Up order (diretso
    // "Awaiting Payment", walang quotation), tapos papunta sa Payment page.
    const finalSummaryContinueBtn =
        document.getElementById(
            "finalSummaryContinueBtn"
        );

    const finalSummaryAddFigureBtn =
        document.getElementById("finalSummaryAddFigureBtn");

    // "+ Add Another Order" sa ilalim ng Your Figure (Dress Up page)
    const dressUpAddFigureBtn =
        document.getElementById("dressUpAddFigureBtn");

    if (dressUpAddFigureBtn) {
        dressUpAddFigureBtn.addEventListener("click", function(event) {
            event.preventDefault();
            addCurrentFigureToDressUpCart();
        });
    }

    if (finalSummaryAddFigureBtn) {
        finalSummaryAddFigureBtn.addEventListener("click", function(event) {
            event.preventDefault();
            addCurrentFigureToDressUpCart();
        });
    }

    // "Reset" sa Final Summary - uulit sa pinakaunang step (Figure Style).
    // Hindi nabubura ang Order Type / Booking Date at ang ibang figure ng order.
    const finalSummaryResetBtn =
        document.getElementById("finalSummaryResetBtn");

    if (finalSummaryResetBtn) {
        finalSummaryResetBtn.addEventListener("click", async function(event) {
            event.preventDefault();

            const askReset = window.csConfirm
                ? window.csConfirm("Start over?\nYour current design will be cleared.", { okText: "Reset", cancelText: "Cancel" })
                : Promise.resolve(window.confirm("Start over? Your current design will be cleared."));

            const confirmedReset = await askReset;

            if (!confirmedReset) {
                return;
            }

            clearCustomizationSnapshot();
            try { localStorage.removeItem(COMMISSION_FIGURE_CATEGORY_KEY); } catch (e) { /* ignore */ }
            window.location.href = "dress-up.php";
        });
    }

    renderDressUpCartList();
    renderDressUpCartBanner();
    initDressUpEditMode();

    if (finalSummaryContinueBtn) {
        finalSummaryContinueBtn.addEventListener(
            "click",
            function(event) {
                event.preventDefault();
                void submitDressUpOrder(finalSummaryContinueBtn);
            }
        );
    }

}


function init3D() {

    if (!canvas) {
        console.error(
            "[Figurify 3D] Canvas #figureViewer was not found."
        );
        return;
    }


    scene = new THREE.Scene();
    figureShadow = null;

    camera = new THREE.PerspectiveCamera(
        35,
        1,
        0.1,
        1000
    );

    camera.position.set(
        0,
        1.1,
        4.5
    );

    try {
        renderer = new THREE.WebGLRenderer(
            {
                canvas,
                alpha: true,
                antialias: true,
                preserveDrawingBuffer: true
            }
        );
    }
    catch (error) {
        console.error(
            "[Figurify 3D] WebGL initialization failed.",
            error
        );
        return;
    }

    renderer.outputColorSpace =
        THREE.SRGBColorSpace;

    renderer.setPixelRatio(
        Math.min(
            window.devicePixelRatio || 1,
            2
        )
    );

    renderer.setClearColor(
        0x000000,
        0
    );

    renderer.shadowMap.enabled = true;

    controls = new OrbitControls(
        camera,
        renderer.domElement
    );

    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.enablePan = false;
    controls.minDistance = 1.5;
    controls.maxDistance = 12;

    // left/right lang ang ikot (horizontal) - naka-lock ang taas/baba
    // para hindi makita ang ilalim o ibabaw ng figure
    controls.minPolarAngle = Math.PI / 2;
    controls.maxPolarAngle = Math.PI / 2;
    controls.enableRotate = true;  // mouse-drag rotate (pahalang lang)
    controls.enableZoom = false;

    // 360° rotate control (← 360° →) sa gitna-baba ng viewer
    setup360Button(canvas);

    scene.add(
        new THREE.AmbientLight(
            0xffffff,
            1.6
        )
    );

    const keyLight =
        new THREE.DirectionalLight(
            0xffffff,
            1.3
        );

    keyLight.position.set(
        4,
        6,
        6
    );
    scene.add(
        keyLight
    );

    const fillLight =
        new THREE.DirectionalLight(
            0xfff0f7,
            0.8
        );

    fillLight.position.set(
        -4,
        3,
        4
    );
    scene.add(
        fillLight
    );

    const rimLight =
        new THREE.DirectionalLight(
            0xffffff,
            0.45
        );

    rimLight.position.set(
        0,
        4,
        -5
    );
    scene.add(
        rimLight
    );

    resize3D();

    if (figureResizeObserver) {
        figureResizeObserver.disconnect();
    }

    if (
        figureArea &&
        typeof ResizeObserver !== "undefined"
    ) {
        figureResizeObserver =
            new ResizeObserver(
                function() {
                    resize3D();
                }
            );

        figureResizeObserver.observe(
            figureArea
        );
    }

    requestAnimationFrame(
        resize3D
    );

    window.addEventListener(
        "resize",
        resize3D
    );

}


// 360° rotate control: left/right arrows
// Bawat click = eksaktong 90° papunta sa susunod na view:
// Front -> Right -> Back -> Left (isang click, nasa side na agad)
let figureRotatePending = 0;   // natitirang radians na iikutin
const FIGURE_ROTATE_STEP = Math.PI / 2;
const FIGURE_ROTATE_SPEED = 0.13; // radians per frame (mabilis pero smooth)

function getFigureViewAngle() {

    if (!camera || !controls) {
        return 0;
    }

    const offset =
        camera.position.clone().sub(controls.target);

    return Math.atan2(offset.x, offset.z);

}

function rotateFigureView(angle) {

    if (!camera || !controls) {
        return;
    }

    const offset =
        camera.position.clone().sub(controls.target);

    offset.applyAxisAngle(
        new THREE.Vector3(0, 1, 0),
        angle
    );

    camera.position.copy(
        controls.target.clone().add(offset)
    );

    camera.lookAt(
        controls.target
    );

}

// iikot papunta sa susunod na 90° na pwesto (naka-snap kahit na-drag na ng mouse)
function queueFigureRotation(dir) {

    const base =
        getFigureViewAngle() + figureRotatePending;

    const index =
        base / FIGURE_ROTATE_STEP;

    const nextIndex = dir > 0
        ? Math.floor(index + 1e-4) + 1
        : Math.ceil(index - 1e-4) - 1;

    figureRotatePending +=
        nextIndex * FIGURE_ROTATE_STEP - base;

}

function stepFigureRotation() {

    if (Math.abs(figureRotatePending) > 0.0001) {

        const step =
            Math.sign(figureRotatePending) *
            Math.min(Math.abs(figureRotatePending), FIGURE_ROTATE_SPEED);

        rotateFigureView(step);
        figureRotatePending -= step;
    }

}

function setup360Button(canvasEl) {

    const host =
        canvasEl && canvasEl.parentElement;

    if (!host || host.querySelector(".figure-360-control")) {
        return;
    }

    const wrap =
        document.createElement("div");

    wrap.className = "figure-360-control";

    const arrow = function(dir) {
        const pts = dir < 0 ? "15 5 8 12 15 19" : "9 5 16 12 9 19";
        return '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">' +
            '<polyline points="' + pts + '" fill="none" stroke="currentColor" stroke-width="2.6" ' +
            'stroke-linecap="round" stroke-linejoin="round"/></svg>';
    };

    wrap.innerHTML =
        '<button type="button" class="figure-360-arrow" data-dir="-1" aria-label="Rotate left" title="Rotate left">' + arrow(-1) + '</button>' +
        '<span class="figure-360-label">360&deg;</span>' +
        '<button type="button" class="figure-360-arrow" data-dir="1" aria-label="Rotate right" title="Rotate right">' + arrow(1) + '</button>';

    wrap.querySelectorAll(".figure-360-arrow").forEach(function(btn) {

        // right arrow = iikot ang figure pakanan (camera papunta sa kabila)
        const dir = parseInt(btn.dataset.dir, 10) * -1;

        btn.addEventListener("click", function(event) {
            event.preventDefault();
            queueFigureRotation(dir);
        });
    });

    host.appendChild(wrap);

}


function animate() {

    requestAnimationFrame(
        animate
    );


    stepFigureRotation();

    if (controls) {
        controls.update();
    }


    if (
        renderer &&
        scene &&
        camera
    ) {
        renderer.render(
            scene,
            camera
        );
    }

}


// bootstrap

// Walang Order Type / Booking Date? Bumalik muna sa Commission para pumili.
if (
    pageMode === "editor" &&
    (!getCommissionOrderTypeValue() || !getCommissionBookingDateValue())
) {
    window.location.replace("commission.php?need=schedule");
}

// ?figure=N → ine-edit ang naka-save na figure (galing Final Summary); kung hindi,
// ibalik ang tinabing kasalukuyang figure kung may naiwan
const editFigureIndex = isFinalSummaryPage ? null : getEditFigureIndex();

if (editFigureIndex !== null) {
    const savedItem = getDressUpCart()[editFigureIndex];

    if (savedItem && savedItem.snapshot) {
        dressUpViewing = { index: editFigureIndex, mode: "edit" };

        try {
            localStorage.removeItem(COMMISSION_FIGURE_CATEGORY_KEY);
            localStorage.setItem(CUSTOMIZATION_STORAGE_KEY, JSON.stringify(savedItem.snapshot));
        }
        catch (error) {
            // ignore
        }
    }
    else {
        restoreStashedDraft();
        window.location.replace("finalsummary.php");
    }
}
else {
    restoreStashedDraft();
}

refreshOptionPriceLabels();
initializeInteractions();
updateSectionVisibility();
updateSelectedItemsUI();
updatePriceDisplay();
setFigurePromptVisible(
    true
);
init3D();

// The editor must always start with no selected style. Saved customization is
// only restored by the preview/details pages, where an existing design is
// intentionally being reviewed.
const restoredCustomization = (isPreviewPage || isDetailsPage || isFinalSummaryPage || isEditPage || isResumePage || dressUpViewing)
    ? restoreCustomizationFromStorage(dressUpViewing ? "edit" : undefined)
    : false;

if (
    (isPreviewPage || isDetailsPage || isFinalSummaryPage) &&
    !restoredCustomization
) {

    // Walang naka-save na customization - ibalik sa commission.php (ang aktwal na editor page)
    window.location.replace(
        "dress-up.php"
    );

}
else {

    // The editor always starts at FIGURE STYLE. Do not restore the category
    // saved by the commission page here, otherwise FIGURE TYPE is shown
    // before the user chooses a style in this session.

    animate();

}

// dressup.js ay type="module" kaya hindi global ang functions dito by default -
// i-expose explicit para magamit ng commission.js pag nag-refresh ng Order Summary.
window.updatePriceDisplay = updatePriceDisplay;
window.renderPreviewSummary = renderPreviewSummary;


// huwag bumalik sa itaas ang scroll pag nag-click ng hair/damit/sapatos
// Kinukuha ang scroll position ng lahat ng scrollable na parent ng card bago mag-click,
// at ibinabalik kung may nag-reset (pag-ayos ng grupo, pag-load ng model, atbp.),
// maliban kung ang user mismo ang nag-scroll pagkatapos.
(function keepScrollOnPick() {
    let userScrolled = false;
    ["wheel", "touchmove", "keydown"].forEach(type => {
        window.addEventListener(type, () => { userScrolled = true; }, { passive: true, capture: true });
    });

    document.addEventListener("click", function(event) {
        const card = event.target.closest && event.target.closest("#dressUpModule .option-card, #dressUpModule .skin-card, #dressUpModule .top-color-card");
        if (!card) {
            return;
        }

        const saved = [];
        let el = card.parentElement;
        while (el) {
            if (el.scrollHeight > el.clientHeight + 2 && el.scrollTop > 0) {
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
            saved.forEach(([node, top]) => {
                if (node.scrollTop < top - 1) {
                    node.scrollTop = top;
                }
            });
            if (window.scrollY < winY - 1) {
                window.scrollTo(window.scrollX, winY);
            }
        };

        // sunod-sunod para mahuli ang sync, timeout (group refresh) at async (model load) na reset
        requestAnimationFrame(restore);
        [0, 60, 200, 500, 1000].forEach(ms => setTimeout(restore, ms));
    }, true);
})();


// header shadow lang kapag naka-scroll, Back/Next shadow lang kapag may laman pa sa baba,
// at ang scrollbar ay nagsisimula eksakto sa ilalim ng guhit ng nakadikit na header.
(function setupDressUpScrollShadows() {
    const right = document.querySelector("#dressUpModule .dressup-right");
    const scroller = document.getElementById("dressUpRightScroll");

    if (!right || !scroller) {
        return;
    }

    let frame = 0;

    function stickyBottom(el) {
        if (!el || !el.offsetParent) {
            return 0;
        }
        const style = getComputedStyle(el);
        if (style.position !== "sticky") {
            return 0;
        }
        const top = parseFloat(style.top) || 0;
        return top + el.offsetHeight;
    }

    function update() {
        frame = 0;

        const scrolled = scroller.scrollTop > 2;
        const moreBelow = scroller.scrollHeight - scroller.clientHeight - scroller.scrollTop > 2;

        right.classList.toggle("du-scrolled", scrolled);
        right.classList.toggle("du-more-below", moreBelow);

        let bottom = stickyBottom(scroller.querySelector("#figureDetailsContainer > h2"));

        scroller
            .querySelectorAll(".category-panel > .option-section:not([hidden]) > .section-title")
            .forEach((title) => {
                bottom = Math.max(bottom, stickyBottom(title));
            });

        const value = Math.max(0, Math.round(bottom)) + "px";

        if (scroller.style.getPropertyValue("--du-track-top") !== value) {
            scroller.style.setProperty("--du-track-top", value);
            // pilitin i-repaint ang scrollbar para makuha ang bagong margin
            scroller.style.overflowY = "hidden";
            void scroller.offsetHeight;
            scroller.style.overflowY = "";
        }
    }

    function schedule() {
        if (!frame) {
            frame = requestAnimationFrame(update);
        }
    }

    scroller.addEventListener("scroll", schedule, { passive: true });
    window.addEventListener("resize", schedule);

    if ("ResizeObserver" in window) {
        new ResizeObserver(schedule).observe(scroller);
    }

    new MutationObserver(schedule).observe(right, {
        attributes: true,
        subtree: true,
        childList: true,
        attributeFilter: ["class", "hidden", "style"]
    });

    schedule();
})();
