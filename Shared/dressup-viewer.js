/* Dress Up 3D viewer para sa My Orders at sa mga order page ng staff/owner.
   Binubuo ulit ang figure gamit ang GLB files ng Dress Up (Commission/dressup-assets).
   Gumagawa lang ng 3D kapag nakikita na, para hindi maubos ang WebGL ng browser. */

import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";


const loader = new GLTFLoader();

const SKIP_FRAGMENTS = ["design", "logo", "print"];


function isPlaceholderPath(path) {
    return !path || String(path).startsWith("placeholder:");
}


// kapareho ng dress-up.js: ang lumang "funko girl.glb" ay ituturo sa bagong "Funko pop-girl.glb"

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

function resolveModelUrl(url) {
    return relocateGlbPath(String(url || "").replace(/funko(%20| )girl\.glb$/i, "Funko$1pop-girl.glb"));
}

function loadScene(url) {
    url = resolveModelUrl(url);
    return new Promise((resolve, reject) => {
        // subukan muna ang bagong folder, tapos ang mga lumang lugar
        const candidates = [url].concat(legacyGlbPaths(url));
        const tryLoad = index => loader.load(candidates[index], gltf => resolve(gltf.scene), undefined, error => {
            if (index + 1 < candidates.length) {
                tryLoad(index + 1);
                return;
            }
            reject(error);
        });
        tryLoad(0);
    });
}


function hasMeshes(object3D) {
    let count = 0;
    object3D.traverse(node => {
        if (node.isMesh) {
            count += 1;
        }
    });
    return count > 0;
}


function disposeObject(object3D) {
    if (!object3D) {
        return;
    }

    object3D.traverse(node => {
        if (!node.isMesh) {
            return;
        }

        if (node.geometry) {
            node.geometry.dispose();
        }

        const materials = Array.isArray(node.material) ? node.material : [node.material];

        materials.forEach(material => {
            if (!material) {
                return;
            }

            ["map", "normalMap", "roughnessMap", "metalnessMap"].forEach(key => {
                if (material[key]) {
                    material[key].dispose();
                }
            });

            material.dispose();
        });
    });
}


function configureModel(model) {
    model.traverse(node => {
        if (!node.isMesh || !node.material) {
            return;
        }

        const materials = Array.isArray(node.material) ? node.material : [node.material];

        materials.forEach(material => {
            material.transparent = false;
            material.needsUpdate = true;
        });
    });
}


function shouldSkipMaterial(material, skipFragments) {
    if (!material || !skipFragments.length) {
        return false;
    }

    const name = String(material.name || "").toLowerCase();

    return skipFragments.some(fragment => name.includes(fragment));
}


const TEXTURE_KEYS = [
    "map", "alphaMap", "aoMap", "bumpMap", "displacementMap", "emissiveMap",
    "envMap", "lightMap", "metalnessMap", "normalMap", "roughnessMap",
    "specularMap", "clearcoatMap", "clearcoatNormalMap", "clearcoatRoughnessMap",
    "sheenColorMap", "sheenRoughnessMap", "iridescenceMap",
    "iridescenceThicknessMap", "transmissionMap", "thicknessMap"
];


function recolorMesh(mesh, targetColor, clearTextures, skipFragments) {
    if (!mesh || !mesh.material) {
        return;
    }

    const materials = Array.isArray(mesh.material) ? mesh.material : [mesh.material];

    const next = materials.map(material => {
        if (shouldSkipMaterial(material, skipFragments)) {
            return material;
        }

        const copy = material.clone();

        if (clearTextures) {
            TEXTURE_KEYS.forEach(key => {
                if (copy[key]) {
                    copy[key] = null;
                }
            });
        }

        if (copy.color) {
            copy.color.copy(targetColor);
        }

        copy.needsUpdate = true;

        return copy;
    });

    mesh.material = Array.isArray(mesh.material) ? next : next[0];
}


// kapareho ng applyColorToModel sa dress-up.js
function applyColorToModel(model, color, clearTextures, skipFragments) {
    if (!model || !color) {
        return;
    }

    const targetColor = new THREE.Color(color);

    model.traverse(node => {
        if (node.isMesh) {
            recolorMesh(node, targetColor, clearTextures, skipFragments);
        }
    });
}


// kapareho ng applySkinColor sa dress-up.js: hindi kinukulayan ang stand, at sa
// Hirono / Funko ay may mga parte (sapatos, sando, shorts, medyas) na hindi kasama
function applySkinTint(model, color, category) {
    const standMaterial = ["stand", "pedestal", "support", "clear_acrylic"];
    const standNode = ["acrylic_stand", "stand", "pedestal", "support"];

    const skipMaterial = category === "hirono"
        ? [...standMaterial, "shoe", "foot", "sole"]
        : standMaterial;

    const skipNode = category === "hirono"
        ? [...standNode, "shoes", "foot", "sando"]
        : category === "funko"
            ? [...standNode, "sando", "shorts", "socks"]
            : standNode;

    const target = new THREE.Color(color);

    model.traverse(node => {
        const nodeName = String(node.name || "").toLowerCase();

        if (skipNode.some(fragment => nodeName.includes(fragment))) {
            return;
        }

        if (node.isMesh) {
            recolorMesh(node, target, false, skipMaterial);
        }
    });
}



function isShoeLikeMesh(mesh) {
    const text = [
        mesh.name,
        mesh.parent && mesh.parent.name,
        mesh.material && !Array.isArray(mesh.material) && mesh.material.name
    ].filter(Boolean).join(" ").toLowerCase();

    return /shoe|sneaker|boot|foot|sole/.test(text);
}


// kapareho ng getBottomModelParts sa dress-up.js: pantalon lang ang kukulayan,
// hindi ang sapatos na kasama sa "Pants + Shoes" na model
function getPantsMeshes(model) {
    const meshes = [];

    model.traverse(node => {
        if (node.isMesh) {
            meshes.push(node);
        }
    });

    if (!meshes.length) {
        return [];
    }

    const indexed = meshes.map((mesh, index) => ({
        mesh,
        index,
        centerY: new THREE.Box3().setFromObject(mesh).getCenter(new THREE.Vector3()).y
    })).sort((a, b) => b.centerY - a.centerY);

    const shoeTagged = indexed.filter(item => isShoeLikeMesh(item.mesh));
    const pantsTagged = indexed.filter(item => !isShoeLikeMesh(item.mesh));

    if (shoeTagged.length && pantsTagged.length) {
        return pantsTagged.map(item => item.mesh);
    }

    let splitIndex = 1;
    let largestGap = 0;

    for (let index = 0; index < indexed.length - 1; index += 1) {
        const gap = indexed[index].centerY - indexed[index + 1].centerY;

        if (gap > largestGap) {
            largestGap = gap;
            splitIndex = index + 1;
        }
    }

    if (largestGap <= 0) {
        splitIndex = Math.max(1, Math.ceil(indexed.length / 2));
    } else {
        splitIndex = Math.min(Math.max(1, splitIndex), indexed.length - 1);
    }

    return indexed.slice(0, splitIndex).map(item => item.mesh);
}


class DressUpViewer {

    constructor(stage) {
        this.stage = stage;
        this.canvas = stage.querySelector("canvas");
        this.statusText = stage.querySelector(".dressup-3d-status-text");
        this.assetBase = stage.dataset.assetBase || "";
        this.alive = false;
        this.frameId = 0;
        this.objects = [];

        try {
            this.design = JSON.parse(stage.dataset.design || "{}");
        } catch (error) {
            this.design = {};
        }
    }

    url(path) {
        return this.assetBase + path;
    }

    setStatus(text, isError) {
        if (this.statusText) {
            this.statusText.textContent = text;
        }

        this.stage.classList.toggle("is-error", Boolean(isError));
        this.stage.classList.remove("is-ready");
    }

    start() {
        if (this.alive || !this.canvas) {
            return;
        }

        const width = this.stage.clientWidth;
        const height = this.stage.clientHeight;

        if (width <= 0 || height <= 0) {
            return;
        }

        try {
            this.renderer = new THREE.WebGLRenderer({
                canvas: this.canvas,
                alpha: true,
                antialias: true
            });
        } catch (error) {
            this.setStatus("3D preview is not supported on this browser.", true);
            return;
        }

        this.alive = true;
        this.loadToken = (this.loadToken || 0) + 1;

        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        this.renderer.setClearColor(0x000000, 0);

        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(35, width / height, 0.1, 1000);
        this.camera.position.set(0, 1.1, 4.5);

        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.08;
        this.controls.enablePan = false;
        this.controls.minDistance = 1.5;
        this.controls.maxDistance = 12;
        // left/right lang ang ikot, hindi makikita ang ilalim/ibabaw
        this.controls.minPolarAngle = Math.PI / 2;
        this.controls.maxPolarAngle = Math.PI / 2;

        this.scene.add(new THREE.AmbientLight(0xffffff, 1.6));

        const key = new THREE.DirectionalLight(0xffffff, 1.3);
        key.position.set(4, 6, 6);
        this.scene.add(key);

        const fill = new THREE.DirectionalLight(0xfff0f7, 0.8);
        fill.position.set(-4, 3, 4);
        this.scene.add(fill);

        const rim = new THREE.DirectionalLight(0xffffff, 0.45);
        rim.position.set(0, 4, -5);
        this.scene.add(rim);

        this.resize();
        this.setup360();

        const loop = () => {
            if (!this.alive) {
                return;
            }

            this.frameId = requestAnimationFrame(loop);
            this.stepRotation();
            this.controls.update();
            this.renderer.render(this.scene, this.camera);
        };

        loop();

        void this.load(this.loadToken);
    }

    // 360° rotate control (← 360° →) - kapareho ng Dress Up: bawat click = 90°
    setup360() {
        this.rotatePending = 0;

        if (this.stage.querySelector(".figure-360-control")) {
            return;
        }

        const arrow = dir => '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">' +
            '<polyline points="' + (dir < 0 ? "15 5 8 12 15 19" : "9 5 16 12 9 19") + '" fill="none" ' +
            'stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        const wrap = document.createElement("div");
        wrap.className = "figure-360-control";
        wrap.innerHTML =
            '<button type="button" class="figure-360-arrow" data-dir="-1" aria-label="Rotate left" title="Rotate left">' + arrow(-1) + '</button>' +
            '<span class="figure-360-label">360&deg;</span>' +
            '<button type="button" class="figure-360-arrow" data-dir="1" aria-label="Rotate right" title="Rotate right">' + arrow(1) + '</button>';

        wrap.querySelectorAll(".figure-360-arrow").forEach(btn => {
            const dir = parseInt(btn.dataset.dir, 10) * -1;

            btn.addEventListener("click", event => {
                event.preventDefault();
                this.queueRotation(dir);
            });
        });

        this.stage.appendChild(wrap);
    }

    viewAngle() {
        const offset = this.camera.position.clone().sub(this.controls.target);
        return Math.atan2(offset.x, offset.z);
    }

    queueRotation(dir) {
        const step = Math.PI / 2;
        const base = this.viewAngle() + (this.rotatePending || 0);
        const index = base / step;
        const next = dir > 0
            ? Math.floor(index + 1e-4) + 1
            : Math.ceil(index - 1e-4) - 1;

        this.rotatePending = (this.rotatePending || 0) + next * step - base;
    }

    stepRotation() {
        if (!this.rotatePending || Math.abs(this.rotatePending) <= 0.0001) {
            return;
        }

        const amount = Math.sign(this.rotatePending) * Math.min(Math.abs(this.rotatePending), 0.13);
        const offset = this.camera.position.clone().sub(this.controls.target);

        offset.applyAxisAngle(new THREE.Vector3(0, 1, 0), amount);
        this.camera.position.copy(this.controls.target.clone().add(offset));
        this.camera.lookAt(this.controls.target);
        this.rotatePending -= amount;
    }

    async load(token) {
        const design = this.design || {};

        if (!design.model) {
            this.setStatus("No 3D design saved for this figure.", true);
            return;
        }

        this.setStatus("Loading 3D figure…", false);

        try {
            let body = null;

            // Chibi Girl and the Chibi Boy standee use separate Dark body GLBs.
            let modelPath = design.model;
            let bodyVariant = false;
            const girlPattern = /(chibi girl|girl-keychain|chibi keychain)-(light|dark)\.glb$/i;
            if (girlPattern.test(modelPath)) {
                const tone = /dark/i.test(String(design.skinName || "")) ? "dark" : "light";
                modelPath = modelPath.replace(girlPattern, (all, base) => base + "-" + tone + ".glb");
                bodyVariant = true;
            } else if (/Chibi Standee Boy\/Chibi Boy\.glb$/i.test(String(modelPath || "")) && /dark/i.test(String(design.skinName || ""))) {
                modelPath = modelPath.replace(/Chibi Boy\.glb$/i, "Chibi Boy-Dark.glb");
                bodyVariant = true;
            }

            try {
                body = await loadScene(this.url(modelPath));
            } catch (error) {
                if (!/Funko pop-girl\.glb$|funko girl\.glb$/i.test(design.model)) {
                    throw error;
                }
            }

            // kapareho ng dress-up.js: kung walang laman ang Funko Girl GLB, gamitin ang default
            if ((!body || !hasMeshes(body)) && /Funko pop-girl\.glb$|funko girl\.glb$/i.test(design.model)) {
                if (body) disposeObject(body);
                body = await loadScene(this.url("dressup-assets/GLB_files/Funko Pop Standee Boy/funko boy.glb"));
            }

            if (!this.alive || token !== this.loadToken) {
                disposeObject(body);
                return;
            }

            configureModel(body);

            const category = String(design.category || "").toLowerCase();

            if (bodyVariant) {
                // Variant GLBs already contain their intended skin materials.
            } else if (design.skin && (category === "hirono" || category === "chibi") && /^light$/i.test(String(design.skinName || ""))) {
                // Light = original na materials ng GLB, walang tint (kapareho ng dress-up.js)
            } else if (design.skin) {
                applySkinTint(body, design.skin, category);
            } else if (/Funko pop-girl\.glb$|funko girl\.glb$/i.test(design.model)) {
                applyColorToModel(body, "#F2CCB7", false, []);
            }

            this.scene.add(body);
            this.objects.push(body);
            this.frame();

            for (const part of (design.parts || [])) {

                if (isPlaceholderPath(part.model)) {
                    continue;
                }

                try {
                    const object3D = await loadScene(this.url(part.model));

                    if (!this.alive || token !== this.loadToken) {
                        disposeObject(object3D);
                        return;
                    }

                    configureModel(object3D);

                    if (part.isBottom) {
                        if (part.color) {
                            const target = new THREE.Color(part.color);
                            getPantsMeshes(object3D).forEach(mesh => {
                                recolorMesh(mesh, target, true, SKIP_FRAGMENTS);
                            });
                        }
                    } else if (part.color) {
                        applyColorToModel(object3D, part.color, true, SKIP_FRAGMENTS);
                    }

                    this.scene.add(object3D);
                    this.objects.push(object3D);
                } catch (partError) {
                    console.warn("[Dress Up viewer] Could not load part:", part.model, partError);
                }
            }

            this.frame();
            this.stage.classList.remove("is-error");
            this.stage.classList.add("is-ready");

        } catch (error) {
            console.error("[Dress Up viewer] Could not load figure:", error);

            if (this.alive && token === this.loadToken) {
                this.setStatus("3D preview could not be loaded.", true);
            }
        }
    }

    frame() {
        if (!this.objects.length || !this.camera) {
            return;
        }

        const box = new THREE.Box3();
        this.objects.forEach(object3D => box.expandByObject(object3D));

        if (box.isEmpty()) {
            return;
        }

        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        const maxSize = Math.max(size.x, size.y, size.z);
        const distance = maxSize / (2 * Math.tan(THREE.MathUtils.degToRad(this.camera.fov / 2)));

        this.camera.position.set(center.x, center.y, center.z + distance * 1.35);
        this.camera.lookAt(center);
        this.controls.target.copy(center);
        this.controls.update();
    }

    resize() {
        if (!this.alive) {
            return;
        }

        const width = this.stage.clientWidth;
        const height = this.stage.clientHeight;

        if (width <= 0 || height <= 0) {
            return;
        }

        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
    }

    stop() {
        if (!this.alive) {
            return;
        }

        this.alive = false;
        this.loadToken = (this.loadToken || 0) + 1;

        cancelAnimationFrame(this.frameId);

        this.objects.forEach(object3D => {
            this.scene.remove(object3D);
            disposeObject(object3D);
        });

        this.objects = [];

        if (this.controls) {
            this.controls.dispose();
        }

        if (this.renderer) {
            this.renderer.dispose();
            this.renderer.forceContextLoss();
        }

        // bagong canvas - hindi na magagamit ulit ang canvas na na-"context loss"
        if (this.canvas) {
            const freshCanvas = this.canvas.cloneNode(false);
            this.canvas.replaceWith(freshCanvas);
            this.canvas = freshCanvas;
        }

        this.renderer = null;
        this.scene = null;
        this.camera = null;
        this.controls = null;

        this.setStatus("Loading 3D figure…", false);
    }

    // tinatawag tuwing nagbabago ang laki (kasama ang pagbukas/pagsara ng order)
    sync() {
        const visible = this.stage.clientWidth > 0 && this.stage.clientHeight > 0;

        if (visible && !this.alive) {
            this.start();
        } else if (!visible && this.alive) {
            this.stop();
        } else if (visible) {
            this.resize();
        }
    }
}


const viewers = new Map();

const resizeObserver = typeof ResizeObserver !== "undefined"
    ? new ResizeObserver(entries => {
        entries.forEach(entry => {
            const viewer = viewers.get(entry.target);
            if (viewer) {
                viewer.sync();
            }
        });
    })
    : null;


function setupViewers(root) {
    (root || document).querySelectorAll("[data-dressup-viewer]").forEach(stage => {
        if (viewers.has(stage)) {
            return;
        }

        const viewer = new DressUpViewer(stage);
        viewers.set(stage, viewer);

        if (resizeObserver) {
            resizeObserver.observe(stage);
        }

        viewer.sync();
    });
}


// fallback para sa lumang browser na walang ResizeObserver
if (!resizeObserver) {
    setInterval(() => viewers.forEach(viewer => viewer.sync()), 800);
}


if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => setupViewers(document));
} else {
    setupViewers(document);
}

window.figurifySetupDressUpViewers = setupViewers;
