/* =====================================================================
   order-summary-pdf.js  -  DOWNLOAD NG ORDER BILANG PDF
   Ginagamit ng owner/active-booking.php at staff/active-booking.php.
   Kinukuha ang laman sa download_order_summary.php (JSON), iginuguhit ang
   bawat pahina sa canvas (kasama ang reference photos, custom box photos
   at 3D preview ng Dress Up), tapos binubuo ang PDF. Walang library.
   ===================================================================== */

var FIGURIFY_PDF = { pageW: 820, pageH: 1160, pad: 40, scale: 2, labelW: 170, priceW: 120, infoLeftW: 400, infoLabelW: 105, lineH: 21 };

/* anggulo ng 3D preview: harap, dalawang gilid, likod */
var FIGURIFY_PDF_VIEWS = [["Front", 0], ["Left Side", 90], ["Right Side", 270], ["Back", 180]];

function figurifyWrapText(ctx, text, maxWidth) {
    var lines = [];

    String(text).split("\n").forEach(function (paragraph) {
        var line = "";

        paragraph.split(" ").forEach(function (word) {
            var test = line ? line + " " + word : word;

            if (ctx.measureText(test).width <= maxWidth) {
                line = test;
                return;
            }

            if (line) { lines.push(line); }
            line = word;

            /* napakahabang salita: hatiin letra por letra */
            while (ctx.measureText(line).width > maxWidth && line.length > 1) {
                var cut = line.length - 1;
                while (cut > 1 && ctx.measureText(line.slice(0, cut)).width > maxWidth) { cut--; }
                lines.push(line.slice(0, cut));
                line = line.slice(cut);
            }
        });

        lines.push(line);
    });

    return lines;
}

function figurifyLoadImage(url) {
    return new Promise(function (resolve) {
        var img = new Image();
        img.onload = function () { resolve(img); };
        img.onerror = function () { resolve(null); };
        img.src = url;
    });
}

/* mga pahina: bawat isa ay canvas; may sariling y na tinatakbuhan pababa */
function FigurifyPages() {
    this.pages = [];
    this.newPage();
}

FigurifyPages.prototype.newPage = function () {
    var canvas = document.createElement("canvas");
    canvas.width = FIGURIFY_PDF.pageW * FIGURIFY_PDF.scale;
    canvas.height = FIGURIFY_PDF.pageH * FIGURIFY_PDF.scale;

    this.ctx = canvas.getContext("2d");
    this.ctx.scale(FIGURIFY_PDF.scale, FIGURIFY_PDF.scale);
    this.ctx.fillStyle = "#FFFFFF";
    this.ctx.fillRect(0, 0, FIGURIFY_PDF.pageW, FIGURIFY_PDF.pageH);
    this.ctx.textBaseline = "top";

    this.pages.push(canvas);
    this.y = FIGURIFY_PDF.pad;
};

/* kapag hindi na kasya ang susunod na bahagi, lipat sa bagong pahina */
FigurifyPages.prototype.need = function (height) {
    if (this.y + height > FIGURIFY_PDF.pageH - FIGURIFY_PDF.pad) { this.newPage(); }
};

FigurifyPages.prototype.text = function (value, x, font, color, offsetY) {
    this.ctx.font = font;
    this.ctx.fillStyle = color;
    this.ctx.fillText(value, x, this.y + (offsetY || 0));
};

/* itaas ng PDF: "Order #1" lang, sa ilalim nito ang petsa ng pag-generate. Ang customer, email at
   order method ay nasa order details sa baba, kaya hindi na inuulit sa pinakataas.
   Tapos dalawang column: kaliwa (customer, email, total items) | kanan (order type, method, ship out date) */
FigurifyPages.prototype.header = function (head) {
    var P = FIGURIFY_PDF;
    var self = this;

    this.text(head.title, P.pad, "bold 26px Arial, sans-serif", "#29252A");
    this.y += 36;
    this.text(head.generated, P.pad, "13px Arial, sans-serif", "#7A7479");
    this.y += 28;

    this.ctx.fillStyle = "#E5E0E3";
    this.ctx.fillRect(P.pad, this.y, P.pageW - P.pad * 2, 2);
    this.y += 18;

    var xs = [P.pad, P.pad + P.infoLeftW + 30];
    var widths = [P.infoLeftW, P.pageW - P.pad - xs[1]];
    var count = Math.max(head.left.length, head.right.length);

    for (var i = 0; i < count; i++) {
        var cells = [head.left[i], head.right[i]];
        var wrapped = cells.map(function (cell, c) {
            self.ctx.font = "15px Arial, sans-serif";
            return cell ? figurifyWrapText(self.ctx, cell[1], widths[c] - P.infoLabelW) : [];
        });
        var lines = Math.max(1, wrapped[0].length, wrapped[1].length);

        this.need(lines * P.lineH + 8);

        cells.forEach(function (cell, c) {
            if (!cell) { return; }

            self.text(cell[0], xs[c], "bold 14px Arial, sans-serif", "#7A7479");
            wrapped[c].forEach(function (line, k) {
                self.text(line, xs[c] + P.infoLabelW, "15px Arial, sans-serif", "#29252A", k * P.lineH);
            });
        });

        this.y += lines * P.lineH + 8;
    }

    this.y += 14;
};

FigurifyPages.prototype.heading = function (value) {
    this.need(120);
    this.text(value, FIGURIFY_PDF.pad, "bold 18px Arial, sans-serif", "#29252A");
    this.ctx.fillStyle = "#E5E0E3";
    this.ctx.fillRect(FIGURIFY_PDF.pad, this.y + 26, FIGURIFY_PDF.pageW - FIGURIFY_PDF.pad * 2, 2);
    this.y += 38;
};

/* bawat row: label | value | price (price ay optional, nasa pinakakanan) */
FigurifyPages.prototype.rows = function (rows) {
    var P = FIGURIFY_PDF;
    var valueX = P.pad + P.labelW + 14;
    var rightEdge = P.pageW - P.pad;
    var self = this;

    rows.forEach(function (row) {
        var valueW = rightEdge - valueX - (row[2] ? P.priceW + 14 : 0);

        self.ctx.font = "15px Arial, sans-serif";
        var lines = figurifyWrapText(self.ctx, row[1], valueW);
        var height = lines.length * P.lineH + 8;

        self.need(height);

        self.text(row[0], P.pad, "bold 14px Arial, sans-serif", "#7A7479");
        lines.forEach(function (line, i) {
            self.text(line, valueX, "15px Arial, sans-serif", "#29252A", i * P.lineH);
        });

        if (row[2]) {
            self.ctx.textAlign = "right";
            self.text(row[2], rightEdge, row[2] === "Included" ? "14px Arial, sans-serif" : "bold 15px Arial, sans-serif", row[2] === "Included" ? "#7A7479" : "#29252A");
            self.ctx.textAlign = "left";
        }

        self.y += height;
        self.ctx.fillStyle = "#F0EBEE";
        self.ctx.fillRect(P.pad, self.y - 4, P.pageW - P.pad * 2, 1);
    });

    this.y += 14;
};

/* maliliit na larawan sa loob ng summary; ang buong laki ay nasa sumusunod na mga pahina */
FigurifyPages.prototype.thumbs = function (images, caption) {
    var size = 150, gap = 12;
    var perRow = Math.floor((FIGURIFY_PDF.pageW - FIGURIFY_PDF.pad * 2 + gap) / (size + gap));

    this.need(size + 40);
    this.text(caption, FIGURIFY_PDF.pad, "bold 12px Arial, sans-serif", "#7A7479");
    this.y += 22;

    for (var i = 0; i < images.length; i++) {
        if (i > 0 && i % perRow === 0) { this.y += size + gap; }
        this.need(size + gap);

        var x = FIGURIFY_PDF.pad + (i % perRow) * (size + gap);

        this.ctx.fillStyle = "#F6F3F5";
        this.ctx.fillRect(x, this.y, size, size);

        if (images[i]) {
            var ratio = Math.min(size / images[i].naturalWidth, size / images[i].naturalHeight);
            var w = images[i].naturalWidth * ratio, h = images[i].naturalHeight * ratio;
            this.ctx.drawImage(images[i], x + (size - w) / 2, this.y + (size - h) / 2, w, h);
        }
    }

    this.y += size + 24;
};

/* isang buong larawan kada pahina para malinaw na makita */
FigurifyPages.prototype.photoPage = function (img, caption) {
    this.newPage();
    this.text(caption, FIGURIFY_PDF.pad, "bold 16px Arial, sans-serif", "#29252A");
    this.y += 34;

    var maxW = FIGURIFY_PDF.pageW - FIGURIFY_PDF.pad * 2;
    var maxH = FIGURIFY_PDF.pageH - this.y - FIGURIFY_PDF.pad;

    if (!img) {
        this.text("This photo could not be loaded.", FIGURIFY_PDF.pad, "15px Arial, sans-serif", "#B42318");
        this.y = FIGURIFY_PDF.pageH;
        return;
    }

    var ratio = Math.min(maxW / img.naturalWidth, maxH / img.naturalHeight, 1);
    var w = img.naturalWidth * ratio, h = img.naturalHeight * ratio;

    this.ctx.drawImage(img, (FIGURIFY_PDF.pageW - w) / 2, this.y, w, h);

    /* susunod na bahagi ay sa bagong pahina, hindi sa ilalim ng larawan */
    this.y = FIGURIFY_PDF.pageH;
};

/* 3D preview ng Dress Up: magkakatabi ang iba't ibang anggulo */
FigurifyPages.prototype.views = function (images) {
    var gap = 10, count = FIGURIFY_PDF_VIEWS.length;
    var w = (FIGURIFY_PDF.pageW - FIGURIFY_PDF.pad * 2 - gap * (count - 1)) / count;
    var h = w * 1.45;

    this.need(h + 40);

    for (var i = 0; i < count; i++) {
        var x = FIGURIFY_PDF.pad + i * (w + gap);

        this.ctx.fillStyle = "#FFF3FA";
        this.ctx.fillRect(x, this.y, w, h);

        if (images[i]) {
            var ratio = Math.min(w / images[i].naturalWidth, h / images[i].naturalHeight);
            var iw = images[i].naturalWidth * ratio, ih = images[i].naturalHeight * ratio;
            this.ctx.drawImage(images[i], x + (w - iw) / 2, this.y + (h - ih) / 2, iw, ih);
        }

        this.ctx.font = "bold 12px Arial, sans-serif";
        this.ctx.fillStyle = "#7A7479";
        this.ctx.fillText(FIGURIFY_PDF_VIEWS[i][0], x, this.y + h + 6);
    }

    this.y += h + 34;
};

/* ---------- 3D PREVIEW (galing sa Shared/dressup-viewer.js) ---------- */

function figurifyCaptureViews(section) {
    if (!window.figurifyDressUpSnapshots) { return Promise.resolve([]); }

    var angles = FIGURIFY_PDF_VIEWS.map(function (view) { return view[1]; });

    return window.figurifyDressUpSnapshots(section.viewer, section.assets, angles)
        .then(function (urls) { return Promise.all((urls || []).map(figurifyLoadImage)); });
}

/* ---------- PDF: bawat pahina ay JPEG, nakalagay sa isang PDF file ---------- */

function figurifyDataUrlBytes(dataUrl) {
    var binary = atob(dataUrl.split(",")[1]);
    var bytes = new Uint8Array(binary.length);

    for (var i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i); }

    return bytes;
}

function figurifyBuildPdf(canvases) {
    var ptW = 595, ptH = Math.round(595 * FIGURIFY_PDF.pageH / FIGURIFY_PDF.pageW);
    var encoder = new TextEncoder();
    var chunks = [], offsets = [], length = 0;

    function push(part) {
        var bytes = typeof part === "string" ? encoder.encode(part) : part;
        chunks.push(bytes);
        length += bytes.length;
    }

    function object(id, body) {
        offsets[id] = length;
        push(id + " 0 obj\n");
        body();
        push("\nendobj\n");
    }

    var kids = canvases.map(function (c, i) { return (3 + i * 3) + " 0 R"; }).join(" ");

    push("%PDF-1.4\n");
    object(1, function () { push("<< /Type /Catalog /Pages 2 0 R >>"); });
    object(2, function () { push("<< /Type /Pages /Kids [" + kids + "] /Count " + canvases.length + " >>"); });

    canvases.forEach(function (canvas, i) {
        var pageId = 3 + i * 3, contentId = pageId + 1, imageId = pageId + 2;
        var jpeg = figurifyDataUrlBytes(canvas.toDataURL("image/jpeg", 0.92));
        var content = "q " + ptW + " 0 0 " + ptH + " 0 0 cm /Im0 Do Q";

        object(pageId, function () {
            push("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " + ptW + " " + ptH + "] " +
                "/Resources << /XObject << /Im0 " + imageId + " 0 R >> >> /Contents " + contentId + " 0 R >>");
        });

        object(contentId, function () {
            push("<< /Length " + content.length + " >>\nstream\n" + content + "\nendstream");
        });

        object(imageId, function () {
            push("<< /Type /XObject /Subtype /Image /Width " + canvas.width + " /Height " + canvas.height +
                " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " + jpeg.length + " >>\nstream\n");
            push(jpeg);
            push("\nendstream");
        });
    });

    var total = 3 + canvases.length * 3;
    var xrefAt = length;

    push("xref\n0 " + total + "\n0000000000 65535 f \n");
    for (var id = 1; id < total; id++) {
        push(("0000000000" + offsets[id]).slice(-10) + " 00000 n \n");
    }
    push("trailer\n<< /Size " + total + " /Root 1 0 R >>\nstartxref\n" + xrefAt + "\n%%EOF");

    return new Blob(chunks, { type: "application/pdf" });
}

/* ---------- ANG BUTTON ---------- */

function figurifyDownloadOrderSummary(orderId, button) {
    var originalText = button ? button.textContent : "";

    if (button) {
        button.disabled = true;
        button.textContent = "Preparing PDF…";
    }

    fetch("download_order_summary.php?order_id=" + encodeURIComponent(orderId), { credentials: "same-origin" })
        .then(function (res) {
            if (!res.ok) { throw new Error("Order not found in Active Booking."); }
            return res.json();
        })
        .then(function (data) {
            var pages = new FigurifyPages();
            var chain = Promise.resolve();

            pages.header(data.header);

            data.sections.forEach(function (section) {
                chain = chain.then(function () {
                    return section.viewer ? figurifyCaptureViews(section) : [];
                }).then(function (views) {
                    pages.heading(section.heading);

                    if (section.viewer && views.length) { pages.views(views); }

                    pages.rows(section.rows);

                    /* Image Submission: Notes ay nasa itaas mismo ng Reference Photo */
                    if (section.notes) { pages.rows([["Notes", section.notes]]); }

                    return Promise.all((section.images || []).map(figurifyLoadImage));
                }).then(function (photos) {
                    if (photos && photos.length) {
                        pages.thumbs(photos, section.caption + "s (full size on the next pages)");
                    }

                    (photos || []).forEach(function (img, i) {
                        pages.photoPage(img, section.caption + " " + (i + 1));
                    });
                });
            });

            return chain.then(function () { return pages.pages; });
        })
        .then(function (canvases) {
            var link = document.createElement("a");
            link.href = URL.createObjectURL(figurifyBuildPdf(canvases));
            link.download = "order-" + orderId + ".pdf";
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        })
        .catch(function (error) { csToast(error.message || "Could not download the order summary.", "error"); })
        .then(function () {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        });
}
