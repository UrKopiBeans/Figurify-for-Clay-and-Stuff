/* =========================================================
   image-submission.js  -  IMAGE SUBMISSION PAGE (image-submission.php)

   Hinango ito sa dating commission.js. Ang Order Type at Ship Out Date
   ay galing sa commission.php (naka-save sa sessionStorage), kaya kung
   wala pang napili ay ibabalik ang customer sa commission.php.

    1. Mga variable at product config   (presyo galing sa Content Management > Prices)
    2. Order type at ship out date      (galing sa Commission page)
    3. Figure style at add-ons          (Funko Box, Hirono Blind Box)
    4. Product type, size at pangalan
    5. Presyo                           (calculatePrice)
    6. Order summary                    (kanang panel)
    7. Maliliit na helper               (money, showNotice)
    8. Figure cart                      (add / edit / remove)
    9. Reference images ng figure
   10. Reference images ng Hirono box
   11. Image modal
   12. Submit ng order                  (submitOrder)
    12b. Save at restore ng cart         (IndexedDB)
   13. Pagsimula ng page                (DOMContentLoaded)
   ========================================================= */

/* =====================================================
   1. MGA VARIABLE AT PRODUCT CONFIG
   ===================================================== */
    let orderType = "";

    let selectedDate = null;

    let selectedFigureStyle = "";

    let selectedProductType = "";

    let selectedProductPrice = 0;

    let selectedSize = "";

    let selectedSizePrice = 0;

    // style add-on variables (Custom Funko Box / Hirono Blind Box)

    let selectedBoxType = "";

    let selectedBoxPrice = 0;

    let selectedBlindType = "";

    let selectedBlindPrice = 0;

    // box design for Hirono: "checkered" (any color) or "hirono_peek" (Wood or B&W only)
    let selectedBoxDesign = "";

    // optional Hirono extras (Tear Blind Paper, Pouch, Digital Art), each independently
    // selectable now. Shape: { itemKey: price, ... }
    let selectedBlindItems = {};

    // rush fee kada figure, depende sa product type:
    // Head Only Keychain = 200, lahat ng iba = 500
    // dapat pareho ito sa helpers/booking_helper.php

    function getRushFeeForProductType(productType){

        if(!productType){

            return 0;

        }

        const normalized =
            productType.toLowerCase().trim();

        if(normalized.indexOf("head only") !== -1){

            return 200;

        }

        return 500;

    }

    // multiple image variables

    const MAX_IMAGES = 5;

    let uploadedImages = [];

    // box reference images for Hirono - limit depende sa Box Design (see getHironoBoxImageLimit)
    let hironoBoxImages = [];

    // multi order (figure cart) variables - para makapag-add ng ilang figure sa isang order

    let figuresCart = [];

    let editingIndex = -1;

    // figure na kakaidagdag lang ng Done sa cart - ibabalik sa form kapag nag-Back mula sa Order Summary
    let autoAddedIndex = -1;

    let cartCounter = 0;

    // Max na bilang ng piraso (lahat ng quantity pinagsama) sa isang order

    const MAX_ORDER_UNITS = 100;

    // quantity ng figure na ginagawa pa (hindi pa nasa cart)

    let draftQuantity = 1;

    // quantity ng isang cart item (laging at least 1)

    function getCartQuantity(item){

        return Math.max(1,Number(item && item.quantity) || 1);

    }

    function getCartUnits(){

        return figuresCart.reduce(function(sum,item){

            return sum + getCartQuantity(item);

        },0);

    }

    // presyo ng bawat Figure Style > Product Type > Size, at Figure Name - galing sa owner
    // (Content Management > Prices). Nasa <script id="figurifyPrices"> ng image-submission.php
    // (helpers/content_helper.php SECTION 8). Walang laman ang type = hindi available (hal. walang Funko keychain).

    const PRICES = JSON.parse(document.getElementById("figurifyPrices").textContent);

    const productConfig = PRICES.products;

    /* =====================================================
       2. ORDER TYPE AT SHIP OUT DATE
       Pinili na sa commission.php - binabasa lang dito
       ===================================================== */

    function loadSavedSchedule(){

        let savedOrderType = "";
        let savedBookingDate = "";

        try{
            savedOrderType = sessionStorage.getItem("figurifyCommissionOrderType") || "";
            savedBookingDate = sessionStorage.getItem("figurifyCommissionBookingDate") || "";
        }
        catch(error){
            return false;
        }

        if(savedOrderType !== "rush" && savedOrderType !== "nonrush"){
            return false;
        }

        if(!/^\d{4}-\d{2}-\d{2}$/.test(savedBookingDate)){
            return false;
        }

        const restoredDate =
            new Date(savedBookingDate + "T00:00:00");

        if(Number.isNaN(restoredDate.getTime())){
            return false;
        }

        orderType = savedOrderType;

        selectedDate = restoredDate;

        return true;

    }

    // figure style

    /* =====================================================
       3. FIGURE STYLE AT ADD-ONS
       Funko / Hirono / Chibi, Custom Funko Box, Hirono Blind Box
       ===================================================== */

    function selectFigureStyle(button){

        document
            .querySelectorAll(".style-option")
            .forEach(function(item){

                item.classList.remove("selected");

            });

        button.classList.add("selected");

        selectedFigureStyle =
            button.dataset.style;

        selectedProductType = "";

        selectedProductPrice = 0;

        selectedSize = "";

        selectedSizePrice = 0;

        document
            .querySelectorAll(".product-option")
            .forEach(function(item){

                item.classList.remove("selected");

                item.classList.remove("disabled");

                item.disabled = false;

            });

        updateProductOptions();

        if(selectedFigureStyle === "Funko Pop"){

            const onlyProduct =
                document.querySelector(
                    '.product-style-group[data-style-group="Funko Pop"] .product-option[data-product="Full Body Standee"]'
                );

            if(onlyProduct){

                selectProductType(onlyProduct);

            }

        }

        updateSizeOptions();

        resetBoxAddonState();

        updateStyleAddonSections();

        updateAllSummary();

    }

    // style add-on sections (Custom Funko Box / Hirono Blind Box) -
    // shows/hides the matching box depending on the selected Figure Style

    function updateStyleAddonSections(){

        const funkoSection =
            document.getElementById("funkoBoxSection");

        const hironoSection =
            document.getElementById("hironoBlindSection");

        const boxAddonBox =
            document.querySelector(".box-addon-box");

        const boxSectionDivider =
            document.getElementById("boxSectionDivider");

        const isFunko =
            selectedFigureStyle === "Funko Pop";

        const isHirono =
            selectedFigureStyle === "Hirono";

        if(funkoSection){

            funkoSection.classList.toggle("show", isFunko);

        }

        if(hironoSection){

            hironoSection.classList.toggle("show", isHirono);

        }

        /* Box / Add-ons container lumalabas lang kung Funko Pop o Hirono ang style */
        if(boxAddonBox){

            boxAddonBox.classList.toggle(
                "show",
                isFunko || isHirono
            );

        }

        /* guhit-guhit na "Custom Box" label, sabay lumalabas sa box container */
        if(boxSectionDivider){

            boxSectionDivider.classList.toggle(
                "show",
                isFunko || isHirono
            );

        }

    }

    // reset style add-on state - called when Figure Style changes or draft is cleared

    function resetBoxAddonState(){

        selectedBoxType = "";

        selectedBoxPrice = 0;

        selectedBlindType = "";

        selectedBlindPrice = 0;

        selectedBlindItems = {};

        resetBoxDesignState();

        document
            .querySelectorAll(".box-type-option")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });

        document
            .querySelectorAll(".blind-set-item")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });

        /* Funko box fields laging naka-show; Hirono fields naka-hide hanggang mapili sa selectBlindType */

        const funkoFields =
            document.getElementById("funkoBoxFields");

        if(funkoFields){

            funkoFields.classList.add("show");

        }

        const boxLockedMessage =
            document.getElementById("boxLockedMessage");

        if(boxLockedMessage){

            boxLockedMessage.classList.add("hide");

        }

        syncHironoBoxSections();

        [
            "funkoBoxName",
            "funkoBoxNumber",
            "funkoBoxColor",
            "hironoBoxColor",
            "hironoLetter",
            "hironoNickname",
            "hironoDateMonth",
            "hironoDateDay"
        ].forEach(function(id){

            const el =
                document.getElementById(id);

            if(el){

                el.value = "";

            }

        });

    }

    // select Funko box type (Solo / Couple)

    function selectBoxType(button){

        // click ulit sa napiling box = unselect
        if(button.classList.contains("selected")){

            button.classList.remove("selected");

            selectedBoxType = "";

            selectedBoxPrice = 0;

            updateAllSummary();

            return;

        }

        document
            .querySelectorAll("[data-box-type]")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });

        button.classList.add("selected");

        selectedBoxType =
            button.dataset.boxType;

        selectedBoxPrice =
            Number(button.dataset.boxPrice || 0);

        document
            .getElementById("boxLockedMessage")
            .classList.add("hide");

        document
            .getElementById("funkoBoxFields")
            .classList.add("show");

        updateAllSummary();

    }

    /* Hirono Custom Box - ano ang kita, depende sa napili (iisang lugar lang):
       - wala pang Regular / Set    -> Blind Box Type lang
       - may Regular / Set          -> + Optional Extras at Box Design
       - may Box Design na          -> + Box Color, Nickname, Month, Day, Letter, Box Reference Images */

    function syncHironoBoxSections(){

        const hasBlindType = selectedBlindType !== "";

        document.getElementById("blindSetContents").classList.toggle("show",hasBlindType);

        document.getElementById("hironoBlindFields").classList.toggle("show",hasBlindType);

        document.getElementById("hironoDesignFields").classList.toggle("show",hasBlindType && selectedBoxDesign !== "");

    }

    // pili / tanggal ng blind box extra, may sariling presyo bawat isa

    function selectBlindSetItem(button){

        const itemKey =
            button.dataset.blindItem;

        if(!itemKey){
            return;
        }

        const itemPrice =
            Number(button.dataset.blindItemPrice || 0);

        const isNowSelected =
            button.classList.toggle("selected");

        if(isNowSelected){

            selectedBlindItems[itemKey] = itemPrice;

        }

        else{

            delete selectedBlindItems[itemKey];

        }

        updateAllSummary();

    }

    // total price of currently selected blind box extras

    function getCurrentBlindItemsTotal(){

        return Object
            .keys(selectedBlindItems)
            .reduce(function(sum,key){

                return sum + Number(selectedBlindItems[key] || 0);

            },0);

    }

    // select Hirono blind box type (Regular / Set) - clicking toggles it,
    // clicking the same one again unselects it

    function selectBlindType(button){

        const isAlreadySelected =
            button.classList.contains("selected");

        document
            .querySelectorAll("[data-blind-type]")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });

        if(isAlreadySelected){

            // unclick: walang napiling blind box type.

            selectedBlindType = "";

            selectedBlindPrice = 0;

            resetBoxDesignState();

            updateAllSummary();

            return;

        }

        button.classList.add("selected");

        selectedBlindType =
            button.dataset.blindType;

        selectedBlindPrice =
            Number(button.dataset.blindPrice || 0);

        updateAllSummary();

    }

    // box design (Checkered / Hirono Peek) - sets the image upload limit
    // (Checkered = 5, Hirono Peek = 3). Toggle rin ito, kagaya ng selectBlindType.

    function selectBoxDesign(button){

        const isAlreadySelected =
            button.classList.contains("selected");

        document
            .querySelectorAll("[data-box-design]")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });

        if(isAlreadySelected){

            // unclick: walang napiling box design.

            resetBoxDesignState();

            updateAllSummary();

            return;

        }

        button.classList.add("selected");

        selectedBoxDesign =
            button.dataset.boxDesign;

        renderHironoBoxColorField(selectedBoxDesign);

        trimHironoImagesToLimit();

        updateHironoImageUploadUI();

        updateAllSummary();

    }

    // box color field for Hirono - Checkered is free text, Hirono Peek is a
    // Wood/Black & White dropdown. Same id="hironoBoxColor" both ways so other code still works.

    function renderHironoBoxColorField(design, presetValue){

        const wrap =
            document.getElementById("hironoBoxColorWrap");

        if(!wrap){

            return;

        }

        if(design === "hirono_peek"){

            wrap.innerHTML =
                '<select class="field" id="hironoBoxColor" onchange="updateAllSummary()">' +
                    '<option value="">Select Box Color</option>' +
                    '<option value="Wood">Wood</option>' +
                    '<option value="Black & White">Black &amp; White</option>' +
                '</select>';

        }
        else{

            wrap.innerHTML =
                '<input class="field" type="text" id="hironoBoxColor" maxlength="30" placeholder="Example: Cream White" oninput="updateAllSummary()">';

        }

        document.getElementById("hironoBoxColor").value =
            presetValue || "";

        updateAllSummary();

    }

    // reset box design state - clears uploaded Box Reference Images too since
    // their limit depends on the design

    function resetBoxDesignState(){

        selectedBoxDesign = "";

        document
            .querySelectorAll("[data-box-design]")
            .forEach(function(btn){

                btn.classList.remove("selected");

            });

        renderHironoBoxColorField("");

        clearHironoImages();

        updateHironoImageUploadUI();

    }

    // current box add-on price (draft figure): Funko = Solo/Couple box price,
    // Hirono = Regular/Set blind box price, Chibi = none

    function getCurrentBoxAddonPrice(){

        if(selectedFigureStyle === "Funko Pop"){

            return Number(selectedBoxPrice || 0);

        }

        if(selectedFigureStyle === "Hirono"){

            return (
                Number(selectedBlindPrice || 0) +
                getCurrentBlindItemsTotal()
            );

        }

        return 0;

    }

    // product type

    /* =====================================================
       4. PRODUCT TYPE, SIZE AT PANGALAN
       Standee / Keychain, laki ng figure, at name fee
       ===================================================== */

    function selectProductType(button){

        if(button.disabled){
            return;
        }

        if(button.classList.contains("disabled")){
            return;
        }

        document
            .querySelectorAll(".product-option")
            .forEach(function(item){

                item.classList.remove("selected");

            });

        button.classList.add("selected");

        selectedProductType =
            button.dataset.product;

        selectedProductPrice = 0;

        selectedSize = "";

        selectedSizePrice = 0;

        updateSizeOptions();

        updateAllSummary();

    }

    // update product options

    // update product options - bawat Figure Style may sariling grid sa PHP
    // (".product-style-group"), dito lang ito ipinapakita/itinatago + disabled state

    function updateProductOptions(){

        const productTypeFieldGroup =
            document.getElementById("productTypeFieldGroup");

        // Funko Pop: iisa lang ang product type (Full Body Standee) kaya itinatago
        // ang Product Type at diretso na itong napipili.
        productTypeFieldGroup.classList.toggle(
            "show",
            !!selectedFigureStyle && selectedFigureStyle !== "Funko Pop"
        );

        // ipakita lang ang grid ng napiling style

        document
            .querySelectorAll(".product-style-group")
            .forEach(function(group){

                const isMatch =
                    group.dataset.styleGroup === selectedFigureStyle;

                group.hidden = !isMatch;

            });

        // i-enable/disable ang mga button base sa productConfig

        const products =
            document.querySelectorAll(".product-option");

        const config =
            productConfig[selectedFigureStyle];

        products.forEach(function(button){

            const product =
                button.dataset.product;

            button.classList.remove("disabled");

            button.disabled = false;

            if(!config || !config[product]){

                button.classList.add("disabled");

                button.disabled = true;

            }

        });

    }

    // update size options

    function updateSizeOptions(){

        const sizeButtons =
            document.querySelectorAll(".size-option");

        const sizeFieldGroup =
            document.getElementById("sizeFieldGroup");

        const noSizeMessage =
            document.getElementById("noSizeMessage");

        sizeButtons.forEach(function(button){

            button.classList.remove("selected");

            button.classList.remove("disabled");

            button.disabled = false;

            button.hidden = true;

        });

        sizeFieldGroup.classList.remove("show");

        noSizeMessage.classList.remove("show");

        if(!selectedFigureStyle){

            selectedSize = "";

            selectedSizePrice = 0;

            selectedProductPrice = 0;

            updateAllSummary();

            return;

        }

        if(!selectedProductType){

            selectedSize = "";

            selectedSizePrice = 0;

            selectedProductPrice = 0;

            updateAllSummary();

            return;

        }

        const styleConfig =
            productConfig[selectedFigureStyle];

        if(!styleConfig){

            selectedSize = "";

            selectedSizePrice = 0;

            selectedProductPrice = 0;

            updateAllSummary();

            return;

        }

        const config =
            styleConfig[selectedProductType];

        if(!config){

            selectedSize = "";

            selectedSizePrice = 0;

            selectedProductPrice = 0;

            updateAllSummary();

            return;

        }

        if(!config.sizes){

            selectedSize = "";

            selectedSizePrice =
                Number(config.price || 0);

            selectedProductPrice =
                Number(config.price || 0);

            noSizeMessage.classList.add("show");

            sizeFieldGroup.classList.add("show");

            updateAllSummary();

            return;

        }

        const availableSizes =
            Object.keys(config.sizes);

        sizeButtons.forEach(function(button){

            const size =
                button.dataset.size;

            if(availableSizes.includes(size)){

                button.hidden = false;

                button.disabled = false;

                button.classList.remove("disabled");

            }

        });

        sizeFieldGroup.classList.add("show");

        selectedSize = "";

        selectedSizePrice = 0;

        selectedProductPrice = 0;

        updateAllSummary();

    }

    // size

    function selectSize(button){

        if(button.disabled){
            return;
        }

        if(button.hidden){
            return;
        }

        document
            .querySelectorAll(".size-option")
            .forEach(function(item){

                item.classList.remove("selected");

            });

        button.classList.add("selected");

        selectedSize =
            button.dataset.size;

        const config =
            productConfig[selectedFigureStyle]
                [selectedProductType];

        if(
            config &&
            config.sizes &&
            config.sizes[selectedSize] !== undefined
        ){

            selectedSizePrice =
                Number(config.sizes[selectedSize]);

        }

        else{

            selectedSizePrice = 0;

        }

        selectedProductPrice = 0;

        updateAllSummary();

    }

    // figure name

    function updateFigureName(){

        const input =
            document.getElementById("figureName");

        const value =
            input.value;

        const validCharacters =
            value.match(/[A-Za-z0-9]/g);

        const count =
            validCharacters
                ? validCharacters.length
                : 0;

        const counterEl =
            document.getElementById("nameCounter");

        if(counterEl){

            counterEl.textContent =
                count +
                (
                    count === 1
                        ? " letter/number"
                        : " letters/numbers"
                );

        }

        updateAllSummary();

    }

    // get current product price

    /* =====================================================
       5. PRESYO
       Pagkuwenta ng presyo ng isang figure at ng buong order
       ===================================================== */

    function getCurrentProductPrice(){

        if(
            !selectedFigureStyle ||
            !selectedProductType
        ){

            return 0;

        }

        const config =
            productConfig[selectedFigureStyle]
                [selectedProductType];

        if(!config){

            return 0;

        }

        if(config.sizes){

            return Number(
                selectedSizePrice || 0
            );

        }

        return Number(
            config.price || 0
        );

    }

    // current figure name fee (draft figure)

    function getCurrentNameFee(){

        const figureNameInput =
            document.getElementById("figureName");

        const figureName =
            figureNameInput
                ? figureNameInput.value.trim()
                : "";

        const hasFigureName =
            /[A-Za-z0-9]/.test(figureName);

        // hanggang 8 letra = presyo ng Figure Name; bawat sobrang letra (walang space) = dagdag
        const letters = figureName.replace(/\s/g,"").length;

        return hasFigureName
            ? PRICES.figure_name + Math.max(0,letters - 8) * PRICES.figure_name_letter
            : 0;

    }

    // price calculation - cart + figure na ginagawa pa (kasama ang quantity at rush fee)

    function calculatePrice(){

        const draftTotal =
            (
                getCurrentProductPrice() +
                getCurrentNameFee() +
                getCurrentBoxAddonPrice()
            ) * draftQuantity;

        let cartSubtotal = 0;

        let totalRushFee = 0;

        // skip ang ini-edit na figure sa cart, ang live na bersyon niya ay ang draft

        figuresCart.forEach(function(item,itemIndex){

            if(itemIndex === editingIndex){
                return;
            }

            cartSubtotal += item.total * getCartQuantity(item);

            if(orderType === "rush"){
                totalRushFee += getRushFeeForProductType(item.product) * getCartQuantity(item);
            }

        });

        if(orderType === "rush" && selectedProductType){
            totalRushFee += getRushFeeForProductType(selectedProductType) * draftQuantity;
        }

        const total =
            cartSubtotal +
            draftTotal +
            totalRushFee;

        document.getElementById("totalPrice").textContent =
            money(total);

        return total;

    }

    // update summary

    /* =====================================================
       6. ORDER SUMMARY
       Ang kanang panel na nagpapakita ng napili at ng total
       ===================================================== */

    function updateSummary(){

        // persist order type / ship out date to sessionStorage kasi dressup.js
        // nagbabasa doon - ibinabalik lang dito ang parehong napili sa commission.php

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

        // I-refresh din ang Dress Up module's Order Summary. dressup.js ay type="module"
        // kaya hindi global ang functions dito (e.g. renderPreviewSummary) - gamitin na
        // lang ang window.updatePriceDisplay na inilagay ni dressup.js sa window mismo.
        if(typeof window.updatePriceDisplay === "function"){

            window.updatePriceDisplay();

        }

        // order type

        const orderTypeText =
            document.getElementById(
                "summaryOrderType"
            );

        if(orderType === "rush"){

            orderTypeText.textContent =
                "Rush Order";

        }

        else if(orderType === "nonrush"){

            orderTypeText.textContent =
                "Non-Rush Order";

        }

        else{

            orderTypeText.textContent =
                "Not selected";

        }

        // ship out date

        const bookingDate =
            document.getElementById(
                "summaryBookingDate"
            );

        const bookingStatus =
            document.getElementById(
                "summaryBookingStatus"
            );

        // araw + petsa sa iisang bold na text (hal. "Friday, November 20, 2026"), kagaya ng Commission
        if(selectedDate){

            bookingDate.textContent =
                selectedDate.toLocaleDateString(
                    "en-US",
                    {
                        weekday:"long",
                        month:"long",
                        day:"numeric",
                        year:"numeric"
                    }
                );

            bookingStatus.textContent = "";

        }

        else{

            bookingDate.textContent =
                "Not selected";

            bookingStatus.textContent =
                "Choose a date from the calendar";

        }

        // Figure Order ng Your Order (Figure Style, Product Type, Size, Figure Name, Custom Box)

        calculatePrice();

        renderFigureCart();

    }

    // update everything

    function updateAllSummary(){

        syncHironoBoxSections();

        updateSummary();

        saveCart();

    }

    // money

    /* =====================================================
       7. MALILIIT NA HELPER
       money() para sa ₱, showNotice() para sa pop-up message
       ===================================================== */

    function money(value){

        return "₱" +
            Number(value)
                .toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    }

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

    // multi order (figure cart) logic

    // validate current (draft) figure - returns an error message, or "" if valid

    /* =====================================================
       8. FIGURE CART
       Pag-check, pagdagdag, pag-edit at pagtanggal ng figure sa order
       ===================================================== */

    // pag may kulang: sasabihin ng pop up kung ano, tapos iscroll at i-highlight ang kulang na field

    let missingFieldId = "";

    function missing(message,fieldId){

        missingFieldId = fieldId;

        return message;

    }

    function showMissing(message){

        showNotice(message);

        const field =
            document.getElementById(missingFieldId);

        if(!field){
            return;
        }

        field.scrollIntoView({
            behavior:"smooth",
            block:"center"
        });

        field.classList.add("field-missing");

        setTimeout(function(){
            field.classList.remove("field-missing");
        },3500);

    }

    function validateDraftFigure(){

        if(!selectedFigureStyle){

            return missing("Choose a figure style.","styleFieldGroup");

        }

        if(!selectedProductType){

            return missing("Choose a product type.","productTypeFieldGroup");

        }

        const config =
            productConfig[selectedFigureStyle]
                [selectedProductType];

        if(!config){

            return missing("This product is not available for the selected figure style.","productTypeFieldGroup");

        }

        if(config.sizes && !selectedSize){

            return missing("Choose a size.","sizeFieldGroup");

        }

        // Figure Name ay optional na - hindi na required.

        if(uploadedImages.length === 0){

            return missing("Upload at least 1 reference image.","uploadFieldGroup");

        }

        if(selectedFigureStyle === "Funko Pop"){

            // Custom Funko Box fields optional na - puwedeng walang piliin ang customer

        }

        if(selectedFigureStyle === "Hirono"){

            // Blind Box fields optional pa rin kung untouched, pero minsang may
            // nafill-upan na, dapat kumpletuhin lahat - di pwede kalahati lang

            const hironoBoxColorField =
                document.getElementById("hironoBoxColor");

            const hironoBoxColorVal =
                hironoBoxColorField
                    ? hironoBoxColorField.value.trim()
                    : "";

            const hironoNicknameVal =
                document.getElementById("hironoNickname").value.trim();

            const hironoLetterVal =
                document.getElementById("hironoLetter").value.trim();

            const hironoDateMonthVal =
                document.getElementById("hironoDateMonth").value;

            const hironoDateDayVal =
                document.getElementById("hironoDateDay").value;

            const hironoBoxEngaged =
                !!selectedBlindType ||
                !!selectedBoxDesign ||
                !!hironoBoxColorVal ||
                !!hironoNicknameVal ||
                !!hironoLetterVal ||
                !!hironoDateMonthVal ||
                !!hironoDateDayVal;

            if(hironoBoxEngaged){

                if(!selectedBlindType){

                    return missing("Pick a Blind Box type (Regular or Set), or clear the Blind Box to skip it.","hironoBlindSection");

                }

                if(!selectedBoxDesign){

                    return missing("Pick a Box Design (Checkered or Hirono Peek), or clear the Blind Box to skip it.","hironoBlindFields");

                }

                if(!hironoBoxColorVal){

                    return missing("Pick a Box Color, or clear the Blind Box to skip it.","hironoBoxColorGroup");

                }

                if(!hironoNicknameVal){

                    return missing("Enter a nickname, or clear the Blind Box to skip it.","hironoNickname");

                }

                if(!hironoLetterVal){

                    return missing("Write a letter or message, or clear the Blind Box to skip it.","hironoLetter");

                }

                if(!hironoDateMonthVal || !hironoDateDayVal){

                    return missing("Pick a full date (month and day), or clear the Blind Box to skip it.","hironoDateMonth");

                }

            }

        }

        const notesValue =
            (document.getElementById("notes")
                ? document.getElementById("notes").value.trim()
                : "");

        if(!notesValue){

            return missing("Add a short note about your figure.","notesFieldGroup");

        }

        return "";

    }

    // build a figure object from current draft fields

    function buildFigureFromDraft(){

        const figureName =
            document
                .getElementById("figureName")
                .value
                .trim();

        const notes =
            document
                .getElementById("notes")
                .value
                .trim();

        const config =
            productConfig[selectedFigureStyle]
                [selectedProductType];

        const productPrice =
            getCurrentProductPrice();

        const nameFee =
            getCurrentNameFee();

        const boxAddonPrice =
            getCurrentBoxAddonPrice();

        let boxDetails = null;

        if(selectedFigureStyle === "Funko Pop"){

            boxDetails = {

                addonType:"funko_box",

                boxType:selectedBoxType,

                boxPrice:selectedBoxPrice,

                boxName:
                    document.getElementById("funkoBoxName").value.trim(),

                boxNumber:
                    document.getElementById("funkoBoxNumber").value.trim(),

                boxColor:
                    document.getElementById("funkoBoxColor").value.trim()

            };

        }

        else if(selectedFigureStyle === "Hirono"){

            boxDetails = {

                addonType:"hirono_blind_box",

                blindType:selectedBlindType,

                blindPrice:selectedBlindPrice,

                blindItems:
                    Object.assign({},selectedBlindItems),

                blindItemsTotal:
                    getCurrentBlindItemsTotal(),

                boxDesign:selectedBoxDesign,

                boxColor:
                    document.getElementById("hironoBoxColor").value.trim(),

                letter:
                    document.getElementById("hironoLetter").value.trim(),

                nickname:
                    document.getElementById("hironoNickname").value.trim(),

                dateMonth:
                    document.getElementById("hironoDateMonth").value,

                dateDay:
                    document.getElementById("hironoDateDay").value,

                boxImages:
                    hironoBoxImages.map(function(imageObject){

                        return {
                            file:imageObject.file,
                            url:imageObject.url
                        };

                    })

            };

        }

        const total =
            productPrice +
            nameFee +
            boxAddonPrice;

        let sizeLabel = "No size required";

        if(config && config.sizes){

            sizeLabel =
                selectedSize
                    ? selectedSize + '"'
                    : "";

        }

        return {

            style:selectedFigureStyle,
            product:selectedProductType,
            size:selectedSize,
            sizeLabel:sizeLabel,
            hasSizes: !!(config && config.sizes),
            name:figureName,
            notes:notes,
            productPrice:productPrice,
            nameFee:nameFee,
            boxAddonPrice:boxAddonPrice,
            boxDetails:boxDetails,
            total:total,

            images:
                uploadedImages.map(function(imageObject){

                    return {
                        file:imageObject.file,
                        url:imageObject.url
                    };

                })

        };

    }

    // reset draft figure fields (after adding to cart, or cancel-edit)

    function resetDraftFigureFields(){

        draftQuantity = 1;

        selectedFigureStyle = "";

        selectedProductType = "";

        selectedProductPrice = 0;

        selectedSize = "";

        selectedSizePrice = 0;

        document
            .querySelectorAll(".style-option")
            .forEach(function(item){

                item.classList.remove("selected");

            });

        document
            .querySelectorAll(".product-option")
            .forEach(function(item){

                item.classList.remove("selected");

                item.classList.remove("disabled");

                item.disabled = false;

            });

        document
            .querySelectorAll(".size-option")
            .forEach(function(item){

                item.classList.remove("selected");

                item.hidden = true;

            });

        document
            .getElementById("noSizeMessage")
            .classList.remove("show");

        resetBoxAddonState();

        updateStyleAddonSections();

        const nameInput =
            document.getElementById("figureName");

        nameInput.value = "";

        const nameCounterReset =
            document.getElementById("nameCounter");

        if(nameCounterReset){

            nameCounterReset.textContent =
                "0 letters/numbers";

        }

        document
            .getElementById("notes")
            .value = "";

        uploadedImages.forEach(function(imageObject){

            if(imageObject.url){

                URL.revokeObjectURL(
                    imageObject.url
                );

            }

        });

        uploadedImages = [];

        renderImagePreviews();

        updateImageUploadUI();

        updateProductOptions();

        updateSizeOptions();

    }

    // add (or save edit of) current figure to the cart

    function addFigureToCart(){

        const error =
            validateDraftFigure();

        if(error){

            showMissing(error);

            return;

        }

        const figureObject =
            buildFigureFromDraft();

        if(
            editingIndex === -1 &&
            getCartUnits() + draftQuantity > MAX_ORDER_UNITS
        ){

            showNotice(
                "An order can have up to " +
                MAX_ORDER_UNITS +
                " pieces only."
            );

            return;

        }

        if(editingIndex === -1){

            cartCounter += 1;

            figureObject.cartId =
                "fig_" + cartCounter;

            figureObject.quantity = draftQuantity;

            figuresCart.push(figureObject);

        }

        else{

            const existing =
                figuresCart[editingIndex];

            figureObject.cartId =
                existing
                    ? existing.cartId
                    : "fig_" + (++cartCounter);

            figureObject.quantity =
                getCartQuantity(existing);

            figuresCart[editingIndex] =
                figureObject;

            editingIndex = -1;

        }

        resetDraftFigureFields();

        autoAddedIndex = -1;

        updateAllSummary();

        showCurrentFigure();

    }

    // edit a figure from the cart - loads its values back into the draft fields

    function editFigureInCart(index){

        const figureObject =
            figuresCart[index];

        if(!figureObject){
            return;
        }

        editingIndex = index;

        autoAddedIndex = -1;

        draftQuantity = getCartQuantity(figureObject);

        // style

        selectedFigureStyle =
            figureObject.style;

        document
            .querySelectorAll(".style-option")
            .forEach(function(item){

                item.classList.toggle(
                    "selected",
                    item.dataset.style === figureObject.style
                );

            });

        updateProductOptions();

        updateStyleAddonSections();

        // custom Funko box / Hirono blind box

        resetBoxAddonState();

        if(figureObject.boxDetails){

            if(figureObject.boxDetails.addonType === "funko_box"){

                selectedBoxType =
                    figureObject.boxDetails.boxType;

                selectedBoxPrice =
                    Number(figureObject.boxDetails.boxPrice || 0);

                document
                    .querySelectorAll("[data-box-type]")
                    .forEach(function(btn){

                        btn.classList.toggle(
                            "selected",
                            btn.dataset.boxType === selectedBoxType
                        );

                    });

                document
                    .getElementById("boxLockedMessage")
                    .classList.add("hide");

                document
                    .getElementById("funkoBoxFields")
                    .classList.add("show");

                document.getElementById("funkoBoxName").value =
                    figureObject.boxDetails.boxName || "";

                document.getElementById("funkoBoxNumber").value =
                    figureObject.boxDetails.boxNumber || "";

                document.getElementById("funkoBoxColor").value =
                    figureObject.boxDetails.boxColor || "";

            }

            else if(figureObject.boxDetails.addonType === "hirono_blind_box"){

                selectedBlindType =
                    figureObject.boxDetails.blindType;

                selectedBlindPrice =
                    Number(figureObject.boxDetails.blindPrice || 0);

                document
                    .querySelectorAll("[data-blind-type]")
                    .forEach(function(btn){

                        btn.classList.toggle(
                            "selected",
                            btn.dataset.blindType === selectedBlindType
                        );

                    });

                // restore selected blind box extras

                selectedBlindItems =
                    Object.assign(
                        {},
                        figureObject.boxDetails.blindItems || {}
                    );

                document
                    .querySelectorAll(".blind-set-item")
                    .forEach(function(btn){

                        btn.classList.toggle(
                            "selected",
                            Object.prototype.hasOwnProperty.call(
                                selectedBlindItems,
                                btn.dataset.blindItem
                            )
                        );

                    });

                // restore box design (checkered / Hirono peek)

                resetBoxDesignState();

                selectedBoxDesign =
                    figureObject.boxDetails.boxDesign || "";

                if(selectedBoxDesign){

                    document
                        .querySelectorAll("[data-box-design]")
                        .forEach(function(btn){

                            btn.classList.toggle(
                                "selected",
                                btn.dataset.boxDesign === selectedBoxDesign
                            );

                        });

                }

                // restore box reference images

                hironoBoxImages =
                    (figureObject.boxDetails.boxImages || [])
                        .map(function(imageObject){

                            return {

                                id:
                                    Date.now() +
                                    "_" +
                                    Math.random()
                                        .toString(36)
                                        .substring(2),

                                file:imageObject.file,

                                url:imageObject.url

                            };

                        });

                renderHironoImagePreviews();

                updateHironoImageUploadUI();

                renderHironoBoxColorField(
                    selectedBoxDesign,
                    figureObject.boxDetails.boxColor || ""
                );

                document.getElementById("hironoLetter").value =
                    figureObject.boxDetails.letter || "";

                document.getElementById("hironoNickname").value =
                    figureObject.boxDetails.nickname || "";

                document.getElementById("hironoDateMonth").value =
                    figureObject.boxDetails.dateMonth || "";

                document.getElementById("hironoDateDay").value =
                    figureObject.boxDetails.dateDay || "";

            }

        }

        // product

        selectedProductType =
            figureObject.product;

        document
            .querySelectorAll(".product-option")
            .forEach(function(item){

                item.classList.toggle(
                    "selected",
                    item.dataset.product === figureObject.product
                );

            });

        updateSizeOptions();

        // size

        if(figureObject.hasSizes && figureObject.size){

            selectedSize =
                figureObject.size;

            document
                .querySelectorAll(".size-option")
                .forEach(function(item){

                    if(item.dataset.size === figureObject.size){

                        item.classList.add("selected");

                    }

                });

            const config =
                productConfig[selectedFigureStyle]
                    [selectedProductType];

            if(
                config &&
                config.sizes &&
                config.sizes[selectedSize] !== undefined
            ){

                selectedSizePrice =
                    Number(config.sizes[selectedSize]);

            }

        }

        // name

        const nameInput =
            document.getElementById("figureName");

        nameInput.value =
            figureObject.name;

        updateFigureName();

        // notes

        document
            .getElementById("notes")
            .value =
            figureObject.notes || "";

        // images

        uploadedImages.forEach(function(imageObject){

            if(imageObject.url){

                URL.revokeObjectURL(
                    imageObject.url
                );

            }

        });

        uploadedImages =
            figureObject.images.map(function(image){

                return {

                    id:
                        Date.now() +
                        "_" +
                        Math.random()
                            .toString(36)
                            .substring(2),

                    file:image.file,

                    url:
                        image.file
                            ? URL.createObjectURL(image.file)
                            : image.url

                };

            });

        renderImagePreviews();

        updateImageUploadUI();

        updateAllSummary();

        showCurrentFigure();

    }

    // remove a figure from the cart

    function removeFigureFromCart(index){

        if(!figuresCart[index]){
            return;
        }

        // shared confirm pop up (Shared/popup-and-security.php)
        if(window.csConfirm){

            window.csConfirm(
                "Remove this figure?\nIt will be taken out of your order.",
                { okText:"Remove", cancelText:"Keep" }
            ).then(function(ok){

                if(ok){
                    doRemoveFigureFromCart(index);
                }

            });

            return;

        }

        if(!confirm("Remove this figure from your order?")){
            return;
        }

        doRemoveFigureFromCart(index);

    }

    function doRemoveFigureFromCart(index){

        if(!figuresCart[index]){
            return;
        }

        figuresCart[index].images.forEach(function(image){

            if(image.url){

                URL.revokeObjectURL(
                    image.url
                );

            }

        });

        figuresCart.splice(index,1);

        autoAddedIndex = -1;

        if(editingIndex === index){

            editingIndex = -1;

            resetDraftFigureFields();

        }

        else if(editingIndex > index){

            editingIndex -= 1;

        }

        renderFigureCart();

        updateAllSummary();

        // kung nasa Order Summary, i-refresh ito (o bumalik sa edit kung wala nang figure)
        if(document.querySelector(".form-shell").classList.contains("show-summary")){

            if(figuresCart.length === 0){
                closeFinalSummary();
            }
            else{
                renderFinalSummary();
            }

        }


    }

    // cancel edit mode (keep figure unchanged in cart)

    function cancelEditFigure(){

        editingIndex = -1;

        autoAddedIndex = -1;

        resetDraftFigureFields();

        renderFigureCart();

        updateAllSummary();

    }

    // label lookups (for the receipt breakdown)

    const BLIND_ITEM_LABELS = {
        tear_blind_paper:"Tear Blind Paper",
        pouch:"Pouch",
        digital_art:"Digital Art (Soft Copy) w/ Photo Card"
    };

    const BOX_DESIGN_LABELS = {
        checkered:"Checkered",
        hirono_peek:"Hirono Peek"
    };

    const MONTH_LABELS = {
        "01":"January","02":"February","03":"March","04":"April",
        "05":"May","06":"June","07":"July","08":"August",
        "09":"September","10":"October","11":"November","12":"December"
    };

    // change quantity ng isang cart item (+ / -), hanggang MAX_ORDER_UNITS lang lahat

    // ilang piraso na ang laman ng order (cart + figure na ginagawa pa)

    function getOrderUnits(){

        return getCartUnits() + (editingIndex === -1 ? draftQuantity : 0);

    }

    // quantity: index -1 = figure na ginagawa pa (draft), iba = figure na nasa cart

    function changeQuantity(index,delta){

        const isDraft = index === -1;

        const current =
            isDraft ? draftQuantity : getCartQuantity(figuresCart[index]);

        if(current + delta < 1){
            return;
        }

        if(delta > 0 && getOrderUnits() >= MAX_ORDER_UNITS){

            showNotice(
                "An order can have up to " +
                MAX_ORDER_UNITS +
                " pieces only."
            );

            return;

        }

        if(isDraft){
            draftQuantity = current + delta;
        }

        else{

            figuresCart[index].quantity = current + delta;

            // ang ini-edit na figure ay naka-draft, sundan ang quantity
            if(index === editingIndex){
                draftQuantity = current + delta;
            }

        }

        updateAllSummary();

    }

    // maliit na helper: gumawa ng button na may class at text

    function buildCartButton(className,text,label,onClick){

        const button =
            document.createElement("button");

        button.type = "button";

        button.className = className;

        button.innerHTML = text;

        if(label){
            button.setAttribute("aria-label",label);
        }

        button.addEventListener("click",function(event){

            event.stopPropagation();

            onClick();

        });

        return button;

    }

    // isang container ng Figure Order: "Figure 1", qty, Edit / Remove, rows ng order, subtotal
    // index -1 = figure na ginagawa pa (draft), figureObject = null kung wala pang napipili

    function buildFigureCard(figureObject,index,position){

        const isDraft = index === -1;

        const isEditing = !isDraft && editingIndex === index;

        const isEmpty = !figureObject;

        const quantity =
            isDraft ? draftQuantity : getCartQuantity(figuresCart[index]);

        const card =
            document.createElement("div");

        card.className = "figure-order-item" + (isEditing || isDraft ? " is-current" : "");

        card.dataset.index = String(index);

        // Rush Fee ng figure (₱500, ₱200 sa Head Only Keychain) at Subtotal = (presyo + rush fee) x qty
        const rushFee =
            !isEmpty && orderType === "rush"
                ? getRushFeeForProductType(figureObject.product)
                : 0;

        card.innerHTML =
            '<div class="cart-card">' +
                '<span class="figure-order-number">Figure ' + (position + 1) + '</span>' +
                '<div class="cart-card-rows">' +
                    buildFigureRows(figureObject) +
                    (rushFee > 0 ? buildRow("Rush Fee","",rushFee) : "") +
                '</div>' +
                '<div class="cart-qty"></div>' +
                '<div class="cart-card-price">' +
                    '<span>Subtotal</span>' +
                    '<span>' + money(isEmpty ? 0 : (figureObject.total + rushFee) * quantity) + '</span>' +
                '</div>' +
                '<div class="cart-item-actions"></div>' +
            '</div>';

        const value =
            document.createElement("span");

        value.className = "cart-qty-value";

        value.textContent = quantity;

        const minus =
            buildCartButton(
                "cart-qty-btn",'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>',"Decrease quantity",
                function(){ changeQuantity(index,-1); }
            );

        const plus =
            buildCartButton(
                "cart-qty-btn",'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>',"Increase quantity",
                function(){ changeQuantity(index,1); }
            );

        minus.disabled = isEmpty || quantity <= 1;

        plus.disabled = isEmpty || getOrderUnits() >= MAX_ORDER_UNITS;

        card.querySelector(".cart-qty").append(minus,value,plus);

        // Edit: figure sa cart = i-load sa form, Save habang ine-edit, draft = punta sa form
        const edit =
            buildCartButton(
                "cart-item-edit",
                "Edit",
                "",
                function(){

                    if(isEditing || isDraft){
                        document.querySelector(".form-card").scrollIntoView({ behavior:"smooth", block:"start" });
                    }

                    else{
                        editFigureInCart(index);
                    }

                }
            );

        // Remove: figure sa cart = alisin, Cancel habang ine-edit, draft = i-reset ang form
        const remove =
            buildCartButton(
                "cart-item-remove",
                "Remove",
                "",
                function(){

                    if(isDraft){
                        cancelEditFigure();
                    }

                    else{
                        removeFigureFromCart(index);
                    }

                }
            );

        edit.disabled = isEmpty;

        remove.disabled = isEmpty;

        card.querySelector(".cart-item-actions").append(edit,remove);

        return card;

    }

    // isang linya ng rows: Label | Value | Price (pantay-pantay ang columns)

    function buildRow(label,value,price){

        return '<div class="figure-summary-item">' +
            '<span class="figure-summary-label">' + label + '</span>' +
            '<span class="figure-summary-name">' + escapeHtml(value) + '</span>' +
            '<span class="figure-summary-price">' + (price ? money(price) : "") + '</span>' +
        '</div>';

    }

    // rows ng isang figure: Figure Style, Product Type, Size, Figure Name, Custom Box

    function buildFigureRows(figureObject){

        if(!figureObject){
            return buildRow("Figure Style","Not selected yet",0);
        }

        const hasSizes = figureObject.hasSizes;

        const box = figureObject.boxDetails;

        let html = buildRow("Figure Style",figureObject.style,0);

        if(figureObject.product){

            html += buildRow("Product Type",figureObject.product,hasSizes ? 0 : figureObject.productPrice);

            html += buildRow("Size",hasSizes ? figureObject.sizeLabel : "No size required",hasSizes ? figureObject.productPrice : 0);

        }

        html += buildRow("Figure Name",figureObject.name || "None",figureObject.name ? figureObject.nameFee : 0);

        if(box && box.addonType === "funko_box" && box.boxType){

            html += buildRow("Box Type",box.boxType === "solo" ? "Solo Box" : "Couple Box",figureObject.boxAddonPrice);

        }

        else if(box && box.addonType === "hirono_blind_box" && box.blindType){

            html += buildRow("Box Type",box.blindType === "regular" ? "Regular Blind Box" : "Blind Box Set",figureObject.boxAddonPrice);

            Object.keys(box.blindItems || {}).forEach(function(itemKey){

                html += buildRow("Extra",BLIND_ITEM_LABELS[itemKey] || itemKey,box.blindItems[itemKey]);

            });

            if(box.boxDesign){
                html += buildRow("Box Design",BOX_DESIGN_LABELS[box.boxDesign] || box.boxDesign,0);
            }

        }

        else{
            html += buildRow("Box Type","None",0);
        }

        return html;

    }

    // render ng Figure Order: lahat ng figure sa cart + ang ginagawa pa (draft) sa dulo,
    // isa-isang container pababa (scroll). Habang ine-edit ang isang figure, live ang itsura niya galing sa form.

    function renderFigureCart(){

        const list =
            document.getElementById("figureCartList");

        if(!list){
            return;
        }

        const cards =
            figuresCart.map(function(item,index){

                return {
                    figure: index === editingIndex ? buildFigureFromDraft() : item,
                    index: index
                };

            });

        // container ng bagong figure: lalabas lang kapag may napili na (o kung wala pang figure sa cart),
        // para walang "Figure 2" hangga't hindi ka pa gumagawa ng isa pa
        if(editingIndex === -1 && (selectedFigureStyle || figuresCart.length === 0)){

            cards.push({
                figure: selectedFigureStyle ? buildFigureFromDraft() : null,
                index: -1
            });

        }

        list.innerHTML = "";

        cards.forEach(function(entry,position){

            list.appendChild(
                buildFigureCard(entry.figure,entry.index,position)
            );

        });

        updateFigureOrderView();

    }

    // may scroll na ba ang Figure Order? (lalabas ang shadow sa taas at baba, kagaya ng Dress Up)
    // at "1 of 2" = ang figure na nasa taas ng listahan ngayon

    function updateFigureOrderView(){

        const list =
            document.getElementById("figureCartList");

        const cards =
            list.querySelectorAll(".figure-order-item");

        document
            .querySelector(".receipt")
            .classList.toggle("can-scroll",list.scrollHeight > list.clientHeight + 1);

        let current = 0;

        cards.forEach(function(card,position){

            if(card.offsetTop + card.offsetHeight / 2 <= list.scrollTop + 1){
                current = position + 1;
            }

        });

        document.getElementById("figureOrderCount").textContent =
            Math.min(current + 1,Math.max(cards.length,1)) + " of " + Math.max(cards.length,1);

    }

    // i-scroll ang Figure Order (sa loob lang ng list, hindi ang page) papunta sa figure na ginagawa / ine-edit;
    // kapag wala (bagong add), sa pinakababa

    function showCurrentFigure(){

        const list =
            document.getElementById("figureCartList");

        const card =
            list.querySelector(".figure-order-item.is-current");

        list.scrollTop = card ? card.offsetTop : list.scrollHeight;

        updateFigureOrderView();

    }

    // small HTML escape helper (for cart text)

    function escapeHtml(text){

        const div =
            document.createElement("div");

        div.textContent =
            text == null
                ? ""
                : String(text);

        return div.innerHTML;

    }

    // image upload init

    /* =====================================================
       9. REFERENCE IMAGES NG FIGURE
       Upload, preview at pagtanggal ng larawan
       ===================================================== */

    function initializeImageUpload(){

        const input =
            document.getElementById("referenceImages");

        const dropzone =
            document.getElementById("uploadDropzone");

        if(!input || !dropzone){
            return;
        }

        // walang hiwalay na "+ Add Images" button - buong dropzone box na mismo
        // ang pwedeng i-click, bukod sa drag & drop na gumagana rin dito
        dropzone.addEventListener(
            "click",
            function(event){

                // pag thumbnail ang na-click, preview lang (hindi bubuksan ang file picker)
                if(event.target.closest(".uploaded-image-card")){
                    return;
                }

                if(uploadedImages.length >= MAX_IMAGES){

                    showNotice(
                        "You can upload up to 5 images only."
                    );

                    return;

                }

                input.click();

            }
        );

        input.addEventListener(
            "change",
            function(event){

                addImageFiles(
                    Array.from(event.target.files)
                );

                input.value = "";

            }
        );

        dropzone.addEventListener(
            "dragover",
            function(event){

                event.preventDefault();

                if(uploadedImages.length >= MAX_IMAGES){
                    return;
                }

                dropzone.classList.add("dragover");

            }
        );

        dropzone.addEventListener(
            "dragleave",
            function(){

                dropzone.classList.remove("dragover");

            }
        );

        dropzone.addEventListener(
            "drop",
            function(event){

                event.preventDefault();

                dropzone.classList.remove("dragover");

                if(uploadedImages.length >= MAX_IMAGES){

                    showNotice(
                        "You can upload up to 5 images only."
                    );

                    return;

                }

                const files =
                    Array.from(
                        event.dataTransfer.files
                    );

                addImageFiles(files);

            }
        );

        updateImageUploadUI();

    }

    // add image files

    function addImageFiles(files){

        if(!files || !files.length){
            return;
        }

        const remainingSlots =
            MAX_IMAGES - uploadedImages.length;

        if(remainingSlots <= 0){

            showNotice(
                "You can upload up to 5 images only."
            );

            return;

        }

        const imageFiles =
            files.filter(function(file){

                return file.type &&
                    file.type.startsWith("image/");

            });

        if(!imageFiles.length){

            showNotice(
                "Please upload image files only."
            );

            return;

        }

        const filesToAdd =
            imageFiles.slice(
                0,
                remainingSlots
            );

        if(imageFiles.length > remainingSlots){

            showNotice(
                "Only " +
                remainingSlots +
                " more image(s) can be added. Maximum is 5."
            );

        }

        filesToAdd.forEach(function(file){

            const imageObject = {

                id:
                    Date.now() +
                    "_" +
                    Math.random()
                        .toString(36)
                        .substring(2),

                file:file,

                url:
                    URL.createObjectURL(file)

            };

            uploadedImages.push(
                imageObject
            );

        });

        renderImagePreviews();

        updateImageUploadUI();

    }

    // render image previews (figure reference images)

    function renderImagePreviews(){

        renderImageCards(
            document.getElementById("imagePreviewGrid"),
            uploadedImages,
            "Reference Image ",
            deleteImage
        );

    }

    /* iisang gawa ng image card para sa Reference Images at Box Reference Images:
       larawan + numero + X (delete); click sa card = palakihin */

    function renderImageCards(grid,images,altPrefix,onDelete){

        if(!grid){
            return;
        }

        grid.innerHTML = "";

        images.forEach(function(imageObject,index){

            const card = document.createElement("div");
            card.className = "uploaded-image-card";
            card.addEventListener("click",function(){
                openImageModal(imageObject.url,index + 1);
            });

            const image = document.createElement("img");
            image.src = imageObject.url;
            image.alt = altPrefix + (index + 1);

            const number = document.createElement("div");
            number.className = "image-number";
            number.textContent = index + 1;

            const deleteButton = document.createElement("button");
            deleteButton.type = "button";
            deleteButton.className = "image-delete";
            deleteButton.title = "Delete image";
            deleteButton.setAttribute("aria-label","Delete image");
            deleteButton.addEventListener("click",function(event){
                event.stopPropagation();
                onDelete(imageObject.id);
            });

            card.append(image,number,deleteButton);
            grid.appendChild(card);

        });

    }

    // delete image

    function deleteImage(id){

        const index =
            uploadedImages.findIndex(
                function(item){

                    return item.id === id;

                }
            );

        if(index === -1){
            return;
        }

        const imageObject =
            uploadedImages[index];

        if(imageObject.url){

            URL.revokeObjectURL(
                imageObject.url
            );

        }

        uploadedImages.splice(
            index,
            1
        );

        renderImagePreviews();

        updateImageUploadUI();

    }

    // update image upload UI

    function updateImageUploadUI(){

        const count =
            uploadedImages.length;

        const counter =
            document.getElementById(
                "imageCount"
            );

        const dropzone =
            document.getElementById(
                "uploadDropzone"
            );

        const uploadDragText =
            document.getElementById(
                "uploadDragText"
            );

        counter.textContent =
            count + " / " + MAX_IMAGES;

        if(count >= MAX_IMAGES){

            dropzone.classList.add(
                "disabled"
            );

        }

        else{

            dropzone.classList.remove(
                "disabled"
            );

        }

        // "Drag & drop" text shares the thumbnail spot - hide it once an image is added
        if(uploadDragText){

            uploadDragText.hidden = count !== 0;

        }

        // Dropzone stays clickable kahit maxed out, para lumabas yung "maximum 5 images" notice sa halip na walang mangyari.

    }

    // box reference images (Hirono) - kapareho ng logic ng main uploader pero
    // hiwalay ang list (hironoBoxImages), limit depende sa Box Design

    /* =====================================================
       10. REFERENCE IMAGES NG HIRONO BOX
       Pareho ng sa taas pero para sa box design
       ===================================================== */

    function getHironoBoxImageLimit(){

        if(selectedBoxDesign === "checkered"){
            return 5;
        }

        if(selectedBoxDesign === "hirono_peek"){
            return 3;
        }

        return 0;

    }

    function initializeHironoImageUpload(){

        const input =
            document.getElementById("hironoImages");

        const dropzone =
            document.getElementById("hironoImageDropzone");

        if(!input || !dropzone){
            return;
        }

        // walang hiwalay na "+ Add Images" button - buong dropzone box ang
        // clickable, kapareho ng Reference Images sa Figure Details
        dropzone.addEventListener(
            "click",
            function(event){

                if(event.target.closest(".uploaded-image-card")){
                    return;
                }

                const limit =
                    getHironoBoxImageLimit();

                if(limit === 0){

                    showNotice(
                        "Choose a box design first."
                    );

                    return;

                }

                if(hironoBoxImages.length >= limit){

                    showNotice(
                        "You can upload a maximum of " +
                        limit +
                        " image(s) for this Box Design."
                    );

                    return;

                }

                input.click();

            }
        );

        input.addEventListener(
            "change",
            function(event){

                addHironoImageFiles(
                    Array.from(event.target.files)
                );

                input.value = "";

            }
        );

        dropzone.addEventListener(
            "dragover",
            function(event){

                event.preventDefault();

                const limit =
                    getHironoBoxImageLimit();

                if(limit === 0 || hironoBoxImages.length >= limit){
                    return;
                }

                dropzone.classList.add("dragover");

            }
        );

        dropzone.addEventListener(
            "dragleave",
            function(){

                dropzone.classList.remove("dragover");

            }
        );

        dropzone.addEventListener(
            "drop",
            function(event){

                event.preventDefault();

                dropzone.classList.remove("dragover");

                const limit =
                    getHironoBoxImageLimit();

                if(limit === 0){

                    showNotice(
                        "Choose a box design first."
                    );

                    return;

                }

                if(hironoBoxImages.length >= limit){

                    showNotice(
                        "You can upload a maximum of " +
                        limit +
                        " image(s) for this Box Design."
                    );

                    return;

                }

                const files =
                    Array.from(
                        event.dataTransfer.files
                    );

                addHironoImageFiles(files);

            }
        );

        updateHironoImageUploadUI();

    }

    // add box reference image files

    function addHironoImageFiles(files){

        if(!files || !files.length){
            return;
        }

        const limit =
            getHironoBoxImageLimit();

        if(limit === 0){

            showNotice(
                "Choose a box design first."
            );

            return;

        }

        const remainingSlots =
            limit - hironoBoxImages.length;

        if(remainingSlots <= 0){

            showNotice(
                "You can upload a maximum of " +
                limit +
                " image(s) for this Box Design."
            );

            return;

        }

        const imageFiles =
            files.filter(function(file){

                return file.type &&
                    file.type.startsWith("image/");

            });

        if(!imageFiles.length){

            showNotice(
                "Please upload image files only."
            );

            return;

        }

        const filesToAdd =
            imageFiles.slice(
                0,
                remainingSlots
            );

        if(imageFiles.length > remainingSlots){

            showNotice(
                "Only " +
                remainingSlots +
                " more image(s) can be added. Maximum is " +
                limit +
                "."
            );

        }

        filesToAdd.forEach(function(file){

            const imageObject = {

                id:
                    Date.now() +
                    "_" +
                    Math.random()
                        .toString(36)
                        .substring(2),

                file:file,

                url:
                    URL.createObjectURL(file)

            };

            hironoBoxImages.push(
                imageObject
            );

        });

        renderHironoImagePreviews();

        updateHironoImageUploadUI();

    }

    // render box reference image previews

    function renderHironoImagePreviews(){

        renderImageCards(
            document.getElementById("hironoImagePreviewGrid"),
            hironoBoxImages,
            "Box Reference Image ",
            deleteHironoImage
        );

    }

    // delete a box reference image

    function deleteHironoImage(id){

        const index =
            hironoBoxImages.findIndex(
                function(item){

                    return item.id === id;

                }
            );

        if(index === -1){
            return;
        }

        const imageObject =
            hironoBoxImages[index];

        if(imageObject.url){

            URL.revokeObjectURL(
                imageObject.url
            );

        }

        hironoBoxImages.splice(
            index,
            1
        );

        renderHironoImagePreviews();

        updateHironoImageUploadUI();

    }

    // clear all box reference images (when Box Design is unselected/reset)

    function clearHironoImages(){

        hironoBoxImages.forEach(function(imageObject){

            if(imageObject.url){

                URL.revokeObjectURL(
                    imageObject.url
                );

            }

        });

        hironoBoxImages = [];

        renderHironoImagePreviews();

    }

    // trim box reference images to the new design's limit (e.g. Checkered 5 -> Hirono Peek 3)

    function trimHironoImagesToLimit(){

        const limit =
            getHironoBoxImageLimit();

        if(hironoBoxImages.length <= limit){
            return;
        }

        const removed =
            hironoBoxImages.splice(limit);

        removed.forEach(function(imageObject){

            if(imageObject.url){

                URL.revokeObjectURL(
                    imageObject.url
                );

            }

        });

        renderHironoImagePreviews();

    }

    // update box reference image upload UI

    function updateHironoImageUploadUI(){

        const limit =
            getHironoBoxImageLimit();

        const count =
            hironoBoxImages.length;

        const counter =
            document.getElementById(
                "hironoImageCount"
            );

        const dropzone =
            document.getElementById(
                "hironoImageDropzone"
            );

        const limitText =
            document.getElementById(
                "hironoImageLimitText"
            );

        const dragText =
            document.getElementById(
                "hironoUploadDragText"
            );

        if(!counter || !dropzone){
            return;
        }

        counter.textContent =
            count + " / " + limit;

        if(limitText){

            limitText.textContent =
                limit === 0
                    ? "(Select a Box Design first)"
                    : "(Maximum " + limit + " images)";

        }

        // walang Box Design pa = naka-disable (malabo) gaya ng maxed-out na uploader
        dropzone.classList.toggle(
            "disabled",
            limit === 0 || count >= limit
        );

        // "Drag & drop" text nasa pwesto ng thumbnails - nawawala pag may image na
        if(dragText){

            dragText.textContent =
                limit === 0
                    ? "Select a Box Design above first"
                    : "Drag and drop your images here";

            dragText.hidden = count !== 0;

        }

    }

    // image modal

    /* =====================================================
       11. IMAGE MODAL
       Pag-click ng larawan para lumaki
       ===================================================== */

    function openImageModal(
        imageUrl,
        imageNumber
    ){

        const modal =
            document.getElementById(
                "imageModal"
            );

        const image =
            document.getElementById(
                "modalImage"
            );

        const label =
            document.getElementById(
                "modalLabel"
            );

        image.src =
            imageUrl;

        label.textContent =
            "Reference Image " +
            imageNumber;

        modal.classList.add(
            "show"
        );

        document.body.classList.add("no-scroll");

    }

    // close image modal

    function closeImageModal(event){

        if(event){

            if(
                event.target &&
                !event.target.classList.contains(
                    "image-modal"
                )
            ){

                return;

            }

        }

        const modal =
            document.getElementById(
                "imageModal"
            );

        const image =
            document.getElementById(
                "modalImage"
            );

        modal.classList.remove(
            "show"
        );

        image.src = "";

        document.body.classList.remove("no-scroll");

    }

    // esc key closes modal

    document.addEventListener(
        "keydown",
        function(event){

            if(
                event.key === "Escape"
            ){

                closeImageModal();

            }

        }
    );

    /* =====================================================
       12. DONE (FINAL SUMMARY) AT SUBMIT NG ORDER
       Done = ipakita ang Final Summary, doon na ang Submit Order
       ===================================================== */

    function openFinalSummary(){

        if(orderType === ""){

            showNotice(
                "Choose Rush or Non-Rush first."
            );

            return;

        }

        if(!selectedDate){

            showNotice(
                "Pick a ship out date first."
            );

            return;

        }

        // awtomatikong idagdag ang figure na ginagawa pa kung kumpleto na

        const draftHasData =
            selectedFigureStyle ||
            selectedProductType ||
            uploadedImages.length > 0 ||
            document.getElementById("figureName").value.trim() !== "";

        if(draftHasData){

            const error =
                validateDraftFigure();

            if(!error){

                const draftIndex =
                    editingIndex === -1 ? figuresCart.length : editingIndex;

                addFigureToCart();

                // nadagdag / na-save nga ba ang figure (hindi lumampas sa limit)
                if(editingIndex === -1 && figuresCart[draftIndex]){

                    autoAddedIndex = draftIndex;

                    saveCart();

                }

            }

            else if(figuresCart.length === 0){

                showMissing(error);

                return;

            }

        }

        if(figuresCart.length === 0){

            missingFieldId = "styleFieldGroup";

            showMissing(
                "Add at least 1 figure first."
            );

            return;

        }

        renderFinalSummary();

        document
            .querySelector(".form-shell")
            .classList.add("show-summary");

        document.getElementById("finalHero").classList.add("show");

        window.scrollTo({top:0,behavior:"smooth"});

        // sariling history entry ang Order Summary, kaya Back lang ng Chrome ang magsasara nito
        history.pushState({finalSummary:true},"");

    }

    // Back = Back ng browser; ang popstate sa ibaba ang magsasara ng summary

    function closeFinalSummary(){

        history.back();

    }

    window.addEventListener("popstate",hideFinalSummary);

    function hideFinalSummary(){

        clearFinalUrls();

        document
            .querySelector(".form-shell")
            .classList.remove("show-summary");

        document.getElementById("finalHero").classList.remove("show");

        // Back mula sa Order Summary: ibalik sa form ang napili (style, pangalan, images, atbp.)
        if(autoAddedIndex !== -1){

            editFigureInCart(autoAddedIndex);

        }

    }

    // ang url ng image sa cart ay binabawi (revoke) pagkatapos i-add ang figure,
    // kaya gumagawa ng bagong url galing sa file para sa Order Summary

    let finalUrls = [];

    function makeFinalUrl(file){

        const url = URL.createObjectURL(file);

        finalUrls.push(url);

        return url;

    }

    function clearFinalUrls(){

        finalUrls.forEach(function(url){
            URL.revokeObjectURL(url);
        });

        finalUrls = [];

    }

    // Figure Details (sariling container): Figure Style | Product Type | Size | Figure Name | Reference Images | Notes
    // bawat linya: Label | Laman | Presyo (kapag may presyo)

    function buildFigureDetails(item){

        return buildDetailItem("Figure Style",escapeHtml(item.style),0) +
            buildDetailItem("Product Type",escapeHtml(item.product),item.hasSizes ? 0 : item.productPrice) +
            buildDetailItem("Size",escapeHtml(item.hasSizes ? item.sizeLabel : "No size needed"),item.hasSizes ? item.productPrice : 0) +
            buildDetailItem("Figure Name",escapeHtml(item.name || "None"),item.name ? item.nameFee : 0) +
            buildDetailItem("Reference Images",buildThumbs(item.images),0) +
            buildDetailItem("Notes",escapeHtml(item.notes || "None"),0,true);

    }

    // Custom Box Details: walang laman ("") kapag walang piniling custom box

    function buildBoxDetails(item){

        const box = item.boxDetails;

        let rows = [];

        if(box && box.addonType === "funko_box" && box.boxType){

            rows = [
                ["Box Type", box.boxType === "solo" ? "Solo Box" : "Couple Box", item.boxAddonPrice],
                ["Box Name", box.boxName],
                ["Box Number", box.boxNumber],
                ["Box Color", box.boxColor]
            ];

        }

        else if(box && box.addonType === "hirono_blind_box" && box.blindType){

            // bawat extra ay sariling linya (may sariling presyo)
            const extras = Object.keys(box.blindItems || {}).map(function(itemKey){
                return ["Extra", BLIND_ITEM_LABELS[itemKey] || itemKey, box.blindItems[itemKey]];
            });

            rows = [
                ["Box Type", box.blindType === "regular" ? "Regular Blind Box" : "Blind Box Set", item.boxAddonPrice]
            ].concat(extras, [
                ["Box Design", BOX_DESIGN_LABELS[box.boxDesign] || box.boxDesign],
                ["Box Color", box.boxColor],
                ["Letter", box.letter],
                ["Nickname", box.nickname],
                ["Date", box.dateMonth && box.dateDay ? MONTH_LABELS[box.dateMonth] + " " + box.dateDay : ""]
            ]);

        }

        if(rows.length === 0){
            return "";
        }

        let html = rows.filter(function(row){
            return row[1];
        }).map(function(row){
            return buildDetailItem(row[0],escapeHtml(row[1]),row[2] || 0,row[0] === "Letter");
        }).join("");

        if(box.boxImages && box.boxImages.length > 0){
            html += buildDetailItem("Box Reference Images",buildThumbs(box.boxImages),0);
        }

        return html;

    }

    // isang linya: Label | Laman | Presyo (blangko kapag walang presyo)
    // stacked = true: buong lapad ang laman sa ilalim ng label (Notes, Letter)
    function buildDetailItem(label,valueHtml,price,stacked){

        return '<div class="final-detail-item' + (stacked ? ' final-detail-stack' : '') + '">' +
            '<span class="final-detail-label">' + label + '</span>' +
            '<span class="final-detail-value">' + valueHtml + '</span>' +
            '<span class="final-detail-price">' + (price ? money(price) : '') + '</span>' +
        '</div>';

    }

    function buildThumbs(images){

        if(images.length === 0){
            return "None";
        }

        return '<span class="final-thumbs">' +
            images.map(function(image,position){

                return '<img class="final-photo" data-number="' + (position + 1) + '" src="' + makeFinalUrl(image.file) + '" alt="Reference image">';

            }).join("") +
        '</span>';

    }

    // laman ng Final Summary: order type, ship out date, bawat figure = isang container (malaking "Figure 1/2...",
    // Figure Details sa kaliwa, Box & Add-ons sa kanan, presyo sa baba), total. Walang - / + dito (sa Your Order lang ang quantity).

    function renderFinalSummary(){

        let subtotal = 0;

        let rushFee = 0;

        clearFinalUrls();

        const figureHtml =
            figuresCart.map(function(item,index){

                const quantity = getCartQuantity(item);

                subtotal += item.total * quantity;

                const figureRushFee =
                    orderType === "rush"
                        ? getRushFeeForProductType(item.product) * quantity
                        : 0;

                rushFee += figureRushFee;

                const boxHtml = buildBoxDetails(item);

                return '<div class="final-row">' +
                    '<div class="final-row-top">' +
                        '<span class="final-figure-number">Figure ' + (index + 1) + '</span>' +
                        '<div class="final-row-actions">' +
                            '<button type="button" class="cart-item-edit" data-action="edit" data-index="' + index + '">Edit</button>' +
                            '<button type="button" class="cart-item-remove" data-action="remove" data-index="' + index + '">Remove</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="final-cols' + (boxHtml ? '' : ' is-single') + '">' +
                        '<div class="final-col">' +
                            '<h4 class="final-col-title">Figure Details</h4>' +
                            buildFigureDetails(item) +
                        '</div>' +
                        (boxHtml
                            ? '<div class="final-col final-col-box">' +
                                  '<h4 class="final-col-title">Box &amp; Add-ons</h4>' +
                                  boxHtml +
                              '</div>'
                            : '') +
                    '</div>' +
                    '<div class="final-prices">' +
                        '<div class="final-price-line"><span>Price per figure</span><span>' + money(item.total) + '</span></div>' +
                        '<div class="final-price-line"><span>Quantity</span><span>' + quantity + '</span></div>' +
                        (figureRushFee > 0
                            ? '<div class="final-price-line"><span>Rush Fee</span><span>' + money(figureRushFee) + '</span></div>'
                            : '') +
                        '<div class="final-price-line final-subtotal"><span>Subtotal</span><span>' + money(item.total * quantity + figureRushFee) + '</span></div>' +
                    '</div>' +
                '</div>';

            }).join("");

        document.getElementById("finalFigureList").innerHTML = figureHtml;

        const shipStatus =
            document.getElementById("summaryBookingStatus").textContent;

        document.getElementById("finalOrderType").textContent =
            document.getElementById("summaryOrderType").textContent;

        document.getElementById("finalShipDate").textContent =
            document.getElementById("summaryBookingDate").textContent +
            (shipStatus ? " · " + shipStatus : "");

        document.getElementById("finalTotal").textContent =
            money(subtotal + rushFee);

    }

    // submit order - sends the whole cart to submit_order.php (galing sa Final Summary)

    function submitOrder(){

        // hindi pa naka-login: bumukas agad ang Log In pop up; pagka-login babalik sa Order Summary
        // na ito (naka-login na, nandoon pa rin ang mga figure). HINDI awtomatikong nagsa-submit:
        // pipindutin ulit ng customer ang Submit Order kapag sigurado na siya.

        if(window.figurifyRequireLogin){

            window.figurifyRequireLogin("image-submission.php?summary=1");

            return;

        }

        if(orderType === ""){

            showNotice(
                "Choose Rush or Non-Rush first."
            );

            return;

        }

        if(!selectedDate){

            showNotice(
                "Pick a ship out date first."
            );

            return;

        }

        if(figuresCart.length === 0){

            missingFieldId = "styleFieldGroup";

            showMissing(
                "Add at least 1 figure first."
            );

            return;

        }

        // build FormData - metadata (JSON) + actual image files

        const submitButton =
            document.getElementById("finalSubmitButton");

        if(submitButton){

            submitButton.disabled = true;

            submitButton.textContent =
                "Submitting...";

        }

        const formData =
            new FormData();

        formData.append(
            "order_type",
            orderType
        );

        formData.append(
            "order_method",
            "reference"
        );

        formData.append(
            "ship_out_date",
            formatDateForServer(selectedDate)
        );

        const figuresMeta =
            figuresCart.map(function(figureObject,index){

                figureObject.images.forEach(
                    function(imageObject){

                        if(imageObject.file){

                            formData.append(
                                "figure_" + index + "_images[]",
                                imageObject.file
                            );

                        }

                    }
                );

                // append Box Reference Images separately, at tanggalin ang raw File
                // objects sa metadata muna bago i-JSON.stringify (di ito valid JSON)

                let boxDetailsForMeta = null;

                if(figureObject.boxDetails){

                    boxDetailsForMeta =
                        Object.assign({},figureObject.boxDetails);

                    if(Array.isArray(boxDetailsForMeta.boxImages)){

                        boxDetailsForMeta.boxImages.forEach(
                            function(imageObject){

                                if(imageObject.file){

                                    formData.append(
                                        "figure_" + index + "_box_images[]",
                                        imageObject.file
                                    );

                                }

                            }
                        );

                        boxDetailsForMeta.boxImagesCount =
                            boxDetailsForMeta.boxImages.length;

                        delete boxDetailsForMeta.boxImages;

                    }

                }

                return {

                    style:figureObject.style,
                    product:figureObject.product,
                    size:figureObject.size,
                    sizeLabel:figureObject.sizeLabel,
                    hasSizes:figureObject.hasSizes,
                    name:figureObject.name,
                    notes:figureObject.notes,
                    productPrice:figureObject.productPrice,
                    nameFee:figureObject.nameFee,
                    boxAddonPrice:figureObject.boxAddonPrice || 0,
                    boxDetails:boxDetailsForMeta,
                    total:figureObject.total,
                    quantity:getCartQuantity(figureObject)

                };

            });

        formData.append(
            "figures",
            JSON.stringify(figuresMeta)
        );

        // send to backend - basahin muna bilang text (hindi diretso .json) para
        // kung may PHP error o mali ang path, makita ang totoong error sa console

        function restoreSubmitButton(){

            if(submitButton){

                submitButton.disabled = false;

                submitButton.textContent =
                    "Submit Order";

            }

        }

        fetch(
            "submit_order.php",
            {
                method:"POST",
                body:formData
            }
        )

        .then(function(response){

            return response.text().then(function(rawText){

                return {
                    status:response.status,
                    rawText:rawText
                };

            });

        })

        .then(function(result){

            let data;

            try{

                data = JSON.parse(result.rawText);

            }

            catch(parseError){

                console.error(
                    "Hindi JSON ang sagot ng server (HTTP " +
                    result.status +
                    "). Raw response:",
                    result.rawText
                );

                throw new Error(
                    "Something went wrong while sending your order. Please try again."
                );

            }

            if(data.success){

                // tapos na ang order - burahin agad ang lahat ng napili (Order Type, Ship Out Date,
                // figures) para bagong simula ang susunod na order
                clearSavedCart();

                // papunta sa My Orders (nandoon na ang bagong order). Ang pop up na
                // "Order #N submitted!" ay galing sa server (submit_order.php) at lalabas doon
                const ordersUrl = data.orders_url || "../my-order/my-orders.php?order_id=" + data.order_id;

                window.location.href =
                    ordersUrl + (ordersUrl.includes("?") ? "&" : "?") + "fresh=1";

            }

            else{

                showNotice(
                    data.message ||
                    "Something went wrong. Please try again."
                );

                restoreSubmitButton();

            }

        })

        .catch(function(error){

            console.error(error);

            showNotice(
                error.message ||
                "Can't submit right now. Check your connection and try again."
            );

            restoreSubmitButton();

        });

    }

    // format a JS date as "YYYY-MM-DD" for the server (avoids timezone issues from .toISOString)

    function formatDateForServer(date){

        const year =
            date.getFullYear();

        const month =
            String(date.getMonth() + 1).padStart(2,"0");

        const day =
            String(date.getDate()).padStart(2,"0");

        return year + "-" + month + "-" + day;

    }


    /* =====================================================
       12b. SAVE AT RESTORE NG CART
       Ang mga figure sa cart (pati ang mga reference image) ay naka-save sa IndexedDB
       para hindi mawala kapag umalis at bumalik sa page. Ang mismong File ang
       sine-save (hindi data URL), kaya buo at hindi nasisira ang mga image.
       Para sa order na ito lang: nabubura kapag sinara ang tab, nag log out,
       lumipat sa Dress Up, o na-submit na ang order.
       ===================================================== */
    const CART_DB_NAME = "figurifyImageSubmission";
    const CART_TAB_KEY = "figurifyCartSession";

    // false hangga't hindi pa tapos ang restore, para hindi mapalitan ng walang laman ang naka-save
    let cartReady = false;

    // aling figure ang nasa form (kaliwa) nung umalis; ibabalik sa form pagbalik
    let savedActiveIndex = -1;

    function openCartDb(){

        return new Promise(function(resolve,reject){

            const request = indexedDB.open(CART_DB_NAME,1);

            request.onupgradeneeded = function(){
                request.result.createObjectStore("cart");
            };

            request.onsuccess = function(){
                resolve(request.result);
            };

            request.onerror = function(){
                reject(request.error);
            };

        });

    }

    // File lang ang itinatabi; ang object url ay ginagawa ulit pagka-restore
    function keepFiles(images){

        return (images || []).map(function(image){
            return { file:image.file };
        });

    }

    function giveUrls(images){

        return (images || []).map(function(image){
            return { file:image.file, url:URL.createObjectURL(image.file) };
        });

    }

    // kopya ng figure kung saan dumaan sa convert ang mga image (pati ang sa box)
    function withImages(item,convert){

        const copy = Object.assign({},item,{ images:convert(item.images) });

        if(item.boxDetails && item.boxDetails.boxImages){
            copy.boxDetails = Object.assign({},item.boxDetails,{
                boxImages:convert(item.boxDetails.boxImages)
            });
        }

        return copy;

    }

    function saveCart(){

        if(!cartReady){
            return;
        }

        const figures = figuresCart.map(function(item){
            return withImages(item,keepFiles);
        });

        try{
            sessionStorage.setItem(CART_TAB_KEY,"1");
        }
        catch(error){}

        openCartDb().then(function(db){

            const transaction = db.transaction("cart","readwrite");

            transaction.objectStore("cart").put({
                figures:figures,
                cartCounter:cartCounter,
                activeIndex:(editingIndex !== -1 ? editingIndex : autoAddedIndex)
            },"cart");

            transaction.oncomplete = function(){
                db.close();
            };

        }).catch(function(error){

            console.warn("Unable to save cart:",error);

        });

    }

    function restoreSavedCart(){

        return openCartDb().then(function(db){

            return new Promise(function(resolve){

                const request = db.transaction("cart").objectStore("cart").get("cart");

                request.onsuccess = function(){
                    db.close();
                    resolve(request.result || null);
                };

                request.onerror = function(){
                    db.close();
                    resolve(null);
                };

            });

        }).then(function(saved){

            // walang marka ng tab na ito = galing sa nasarang tab / lumang order, huwag ibalik
            if(saved && sessionStorage.getItem(CART_TAB_KEY) !== "1"){
                clearSavedCart(["imageSubmission"]);
                return;
            }

            if(!saved){
                return;
            }

            figuresCart = saved.figures.map(function(item){
                return withImages(item,giveUrls);
            });

            cartCounter = saved.cartCounter || figuresCart.length;

            savedActiveIndex = Number.isInteger(saved.activeIndex) ? saved.activeIndex : -1;

        }).catch(function(error){

            console.warn("Unable to restore cart:",error);

        }).then(function(){

            cartReady = true;

        });

    }

    // parts (optional) = ["imageSubmission"] lang ang buburahin; walang parts = buong order (Shared/navbar.php)
    function clearSavedCart(parts){

        cartReady = false;

        window.figurifyClearOrder(parts);

    }


    /* =====================================================
       13. PAGSIMULA NG PAGE
       Tumatakbo ito pagkabukas ng page
       ===================================================== */

    // start

    document.addEventListener(
        "DOMContentLoaded",
        function(){

            // Walang naka-save na Order Type / Ship Out Date - bumalik muna sa Commission page
            if(!loadSavedSchedule()){

                window.location.replace("commission.php?need=schedule");

                return;

            }

            // lumipat sa Image Submission (galing Commission, navbar o footer): burahin ang napili sa Dress Up.
            // Ang napili dito ay nananatili hanggang mag log out o lumipat sa Dress Up.
            window.figurifyClearOrder(["dressUp"]);

            updateProductOptions();

            updateSizeOptions();

            // ibalik ang mga figure na nasa cart kanina (Back / balik sa page), tapos i-render
            restoreSavedCart().then(function(){

                // ibalik sa form ang napili (naka-click ulit sa kaliwa) para walang dagdag na "Figure 2"
                if(figuresCart[savedActiveIndex]){
                    editFigureInCart(savedActiveIndex);
                }

                renderFigureCart();

                updateAllSummary();

                // ?summary=1 (pagka-login galing sa Submit Order): buksan ulit ang Order Summary lang.
                // Hindi nagsa-submit nang kusa; ang customer ang magpipindot ulit ng Submit Order.
                const loginParams = new URLSearchParams(window.location.search);
                if(loginParams.get("summary") === "1"){
                    history.replaceState(null,"","image-submission.php");
                    if(figuresCart.length){
                        openFinalSummary();
                    }
                }

            });

            initializeImageUpload();

            initializeHironoImageUpload();

            // Figure Order: "1 of 2" at shadow habang nag-i-scroll / nagbabago ang laki ng screen
            document.getElementById("figureCartList").addEventListener("scroll",updateFigureOrderView);

            window.addEventListener("resize",updateFigureOrderView);

            // Edit / Remove ng figure; ang image = palakihin (may close button)
            document
                .getElementById("finalFigureList")
                .addEventListener("click",function(event){

                    const action = event.target.closest("[data-action]");

                    const photo = event.target.closest(".final-photo");

                    if(action){

                        const index = Number(action.dataset.index);

                        if(action.dataset.action === "edit"){
                            editFigureInCart(index);
                            closeFinalSummary();
                        }

                        else{
                            removeFigureFromCart(index);
                        }

                    }

                    else if(photo){
                        openImageModal(photo.src,Number(photo.dataset.number));
                    }

                });

        }
    );
