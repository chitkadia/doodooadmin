/* ----- Required default / configurable values ----- */
var PAGE_NUMBER = 1; // Current page number, defaults to 1
var TOTAL_PAGES = 1; // Total pages of PDF, defaults to 1
var TOTAL_PAGES_LOADED = 0; // Total number of pages loaded
var CURRENT_SCALE = 1.0;
var MAX_SCALE = 1.5;
var MIN_SCALE = 0.1;
var SCALE_INC = 0.1;
var THUMBNAIL_SCALE = 0.3;
var PDF_URL = _DOCFILE;
var USAGE_TRACK_INTERVAL = 1000; // Number of milliseconds of interval to track usage statistics
var POST_USAGE_INTERVAL = 10500; // Number of milliseconds of interval to post the usage statistics
var DEFAULT_USAGE = { "d":"", "p":0, "i":0, "o":0 };

/* ----- Maintain state of viewer ----- */
var STATE_FULL_SCREEN = false; // Whether full screen is enabled or not
var STATE_THUMB_VIEW = false; // Whether thumbnail view is on or not
var STATE_OVERLAY = false; // Whether overlay is enabled or not
var STATE_PDF_LOADED = false; // Whether PDF is loaded or not (????????? Could be removed)
var STATE_THUMBNAILS_LOADED = false; // Whether all thumbnails of PDF loaded or not
var RESET_PAGE_URL_ON_LOAD = true;
var PDF_PAGE_HEIGHT = 0; // Height of one page
var STATE_USER_ACTIVE = true; // Whether user is active on document or not

// Event listner elements
var ELEMENT_DOCUMENT = document.body;
var ELEMENT_HTML = document.getElementById("html");
var ELEMENT_FILE_VIEW = document.getElementById("render-file");
var ELEMENT_FULL_SCREEN = document.getElementById("full-screen");
var ELEMENT_PREV_PAGE = document.getElementById("prev-page");
var ELEMENT_NEXT_PAGE = document.getElementById("next-page");
var ELEMENT_THUMBNAILS = document.getElementById("thumbnails");
var ELEMENT_ZOOM_OUT = document.getElementById("zoom-out");
var ELEMENT_ZOOM_IN = document.getElementById("zoom-in");
var ELEMENT_OTHER_INFO = document.getElementById("other-info");
var ELEMENT_CURRENT_PAGE = document.getElementById("current-page");
var ELEMENT_TOTAL_PAGE = document.getElementById("total-pages");
var ELEMENT_OVERLAY = document.getElementById("overlay");
var ELEMENT_THUMBNAILS_VIEW = document.getElementById("thumbnail-view");
var PDF_LOADER = document.getElementById("pdf-loader");

// Object store reference to PDF file
var PDFDocument;

// PDF Worker loader to load PDF files
var PDFJS;
PDFJS.workerSrc = "/pdfjs/build-1.9.426/pdf.worker.js";

// Store user actions
var ACTIONS_PAYLOAD = [];
var TIMER_TRACK_USAGE = null;
var TIMER_POST_USAGE = null;

/* ---------- Register event listners ---------- */
window.onload = load_pdf_file;
window.onscroll = pdf_scroll;
window.onfocus = window_change_active;
window.onblur = window_change_inactive;

ELEMENT_FULL_SCREEN.addEventListener("click", function(e) {
    // Supports most browsers and their versions.
    if (ELEMENT_HTML.requestFullscreen) {
        ELEMENT_HTML.requestFullscreen();

    } else if (ELEMENT_HTML.mozRequestFullScreen) {
        ELEMENT_HTML.mozRequestFullScreen();

    } else if (ELEMENT_HTML.webkitRequestFullScreen) {
        ELEMENT_HTML.webkitRequestFullScreen();

    } else if (ELEMENT_HTML.msRequestFullscreen) {
        ELEMENT_HTML.msRequestFullscreen();

    } else if (typeof window.ActiveXObject !== "undefined") {
        // For older IE versions
        var wscript = new ActiveXObject("WScript.Shell");
        if (wscript !== null) {
            wscript.SendKeys("{F11}");
        }
    }
});
ELEMENT_HTML.addEventListener("webkitfullscreenchange", fullscreen_toggle);
ELEMENT_HTML.addEventListener("mozfullscreenchange", fullscreen_toggle);
ELEMENT_HTML.addEventListener("fullscreenchange", fullscreen_toggle);
ELEMENT_HTML.addEventListener("MSFullscreenChange", fullscreen_toggle);

ELEMENT_PREV_PAGE.addEventListener("click", function(e) {
    if (PAGE_NUMBER > 1) {
        PAGE_NUMBER--;
    }

    window.location.href = "#page-" + PAGE_NUMBER;
    set_current_page_in_toolbar();
});

ELEMENT_NEXT_PAGE.addEventListener("click", function(e) {
    if (PAGE_NUMBER < TOTAL_PAGES) {
        PAGE_NUMBER++;
    }

    window.location.href = "#page-" + PAGE_NUMBER;
    set_current_page_in_toolbar();
});

ELEMENT_THUMBNAILS.addEventListener("click", show_thumbnails);

ELEMENT_ZOOM_OUT.addEventListener("click", function(e) {
    CURRENT_SCALE -= SCALE_INC;

    if (CURRENT_SCALE <= MIN_SCALE) {
        CURRENT_SCALE = MIN_SCALE;
    }

    set_zoom_level_in_toolbar();
    reload_pdf_file();
});

ELEMENT_ZOOM_IN.addEventListener("click", function(e) {
    CURRENT_SCALE += SCALE_INC;

    if (CURRENT_SCALE >= MAX_SCALE) {
        CURRENT_SCALE = MAX_SCALE;
    }

    set_zoom_level_in_toolbar();
    reload_pdf_file();
});

ELEMENT_OTHER_INFO.addEventListener("click", function(e) {
    // Implementation pending
});

ELEMENT_OVERLAY.addEventListener("click", function(e) {
    if (STATE_THUMB_VIEW) {
        close_thumbnails();
    }
});

/* ---------- Routines for Support ---------- */
/**
 * Toggle from fullscreen mode and maintain state
 */
function fullscreen_toggle() {
    STATE_FULL_SCREEN = !STATE_FULL_SCREEN;
}

/**
 * Toggle overaly mode and maintain state
 */
function overlay_toggle() {
    if (STATE_OVERLAY) {
        ELEMENT_OVERLAY.style.display = "none";
    } else {
        ELEMENT_OVERLAY.style.display = "block";
    }

    STATE_OVERLAY = !STATE_OVERLAY;
}

/**
 * Set current page number in toolbar
 */
function set_current_page_in_toolbar() {
    ELEMENT_CURRENT_PAGE.innerHTML = PAGE_NUMBER;

    // Set next page to disabled / enabled
    if (PAGE_NUMBER == TOTAL_PAGES) {
        ELEMENT_NEXT_PAGE.className = "next disabled";
    } else {
        ELEMENT_NEXT_PAGE.className = "next";
    }

    // Set previous page to disabled / enabled
    if (PAGE_NUMBER == 1) {
        ELEMENT_PREV_PAGE.className = "previous disabled";
    } else {
        ELEMENT_PREV_PAGE.className = "previous";
    }
}

/**
 * Set zoom level in toolbar
 */
function set_zoom_level_in_toolbar() {
    // Set zoom out to disabled / enabled
    if (CURRENT_SCALE == MIN_SCALE) {
        ELEMENT_ZOOM_OUT.className = "opt disabled";
    } else {
        ELEMENT_ZOOM_OUT.className = "opt";
    }

    // Set zoom in to disabled / enabled
    if (CURRENT_SCALE == MAX_SCALE) {
        ELEMENT_ZOOM_IN.className = "opt disabled";
    } else {
        ELEMENT_ZOOM_IN.className = "opt";
    }
}

/**
 * Check which page is being visited
 */
function pdf_scroll(e) {
    var window_scrolltop = window.pageYOffset || ELEMENT_FILE_VIEW.scrollTop;
    var rem = Math.floor(window_scrolltop / (PDF_PAGE_HEIGHT + 5));

    var to_page = rem + 1;
    if (to_page != PAGE_NUMBER) {
        PAGE_NUMBER = to_page;

        set_current_page_in_toolbar();
    }
}

/**
 * Set if window as active
 */
function window_change_active() {
    STATE_USER_ACTIVE = true;

    record_usage();
    clearInterval(TIMER_TRACK_USAGE);
    clearInterval(TIMER_POST_USAGE);
    TIMER_TRACK_USAGE = setInterval(record_usage, USAGE_TRACK_INTERVAL);
    TIMER_POST_USAGE = setInterval(post_usage, POST_USAGE_INTERVAL);
}

/**
 * Set if window as inactive
 */
function window_change_inactive() {
    STATE_USER_ACTIVE = false;

    record_usage();
    clearInterval(TIMER_TRACK_USAGE);
    clearInterval(TIMER_POST_USAGE);
}

/**
 * Record usage of file
 */
function record_usage() {
    var d = new Date();

    var last_usage = ACTIONS_PAYLOAD.pop() || DEFAULT_USAGE;
    last_usage.o = d.toUTCString();

    // If current page doesn't match last page, add new page stats
    if ((last_usage.p != PAGE_NUMBER || _B != last_usage.d) && STATE_USER_ACTIVE) {
        ACTIONS_PAYLOAD.push(last_usage);

        var new_usage = {
            "d": _B,
            "p": PAGE_NUMBER,
            "i": d.toUTCString(),
            "o": d.toUTCString()
        };
        ACTIONS_PAYLOAD.push(new_usage);

    } else {
        ACTIONS_PAYLOAD.push(last_usage);
    }

    // If user has left the window then 
    if (!STATE_USER_ACTIVE) {
        ACTIONS_PAYLOAD.push(DEFAULT_USAGE);
    }
}

/**
 * Post usage of file and set 
 */
function post_usage() {
    if (ACTIONS_PAYLOAD.length == 0 || parseInt(_M) == 1) {
        ACTIONS_PAYLOAD = [];
        return;
    }
    
    do_post_request("/viewer/" + _D + "/" + _A, JSON.stringify(ACTIONS_PAYLOAD), function() {
        // Clear current payload and start a new one
        ACTIONS_PAYLOAD = [];

        record_usage();
    });
}

/**
 * Show PDF loading
 */
function show_pdf_loader() {
    if (TOTAL_PAGES == 0) return;

    PDF_LOADER.style.display = 'show';
    document.getElementById("load-progress").innerHTML = Math.ceil((TOTAL_PAGES_LOADED * 100) / TOTAL_PAGES);
}

/**
 * Hide PDF loading
 */
function hide_pdf_loader() {
    PDF_LOADER.style.display = 'none';
}

/* ---------- PDF Render Routines ---------- */
/**
 * Load PDF file
 */
function load_pdf_file() {
    overlay_toggle();

    var page_url = window.location.href;
    var url_parts = page_url.split("#");
    if (url_parts.length > 1) {
        var page_num = url_parts[1].replace("page-", "");

        if (!isNaN(page_num)) {
            PAGE_NUMBER = parseInt(page_num);
        }
    }

    PDFJS.getDocument(PDF_URL).then(function(pdfDoc) {
        PDFDocument = pdfDoc;
        TOTAL_PAGES = PDFDocument.numPages;

        if (PAGE_NUMBER > TOTAL_PAGES) {
            PAGE_NUMBER = 1;
        }

        // Set current page number and total pages
        set_current_page_in_toolbar();
        ELEMENT_TOTAL_PAGE.innerHTML = TOTAL_PAGES;

        // Show PDF loader
        show_pdf_loader();

        // Load whole PDF file
        for (i=1; i<=PDFDocument.numPages; i++) {
            render_page(i);
        }

        // Load thumbnails of PDF
        for (i=1; i<=PDFDocument.numPages; i++) {
            render_thumbnail(i);
        }
    });
}

/**
 * Show thumbnails view of PDF
 */
function show_thumbnails() {
    overlay_toggle();

    // Show thumbnails
    ELEMENT_THUMBNAILS_VIEW.style.display = "block";
    ELEMENT_DOCUMENT.className = "viewer bodyfixed";
    STATE_THUMB_VIEW = true;
    ELEMENT_OVERLAY.className = "overlay withthumbs";
}

/**
 * Close thumbnails view
 */
function close_thumbnails() {
    overlay_toggle();

    ELEMENT_THUMBNAILS_VIEW.style.display = "none";
    ELEMENT_DOCUMENT.className = "viewer";
    STATE_THUMB_VIEW = false;
    ELEMENT_OVERLAY.className = "overlay";
}

/**
 * Select page from thumbnails
 */
function select_thumbnail(p) {
    if (PAGE_NUMBER != p) {
        PAGE_NUMBER = p;
        window.location.href = "#page-" + PAGE_NUMBER;
        set_current_page_in_toolbar();

        close_thumbnails();
    }
}

function reload_pdf_file() {
    // Render every page again with new scalling
    for (i = 1; i <= TOTAL_PAGES; i++) {
        PDFDocument.getPage(i).then(function(page) {
            var viewport = page.getViewport(CURRENT_SCALE);
            var canvas = document.getElementById("cpage-" + (page.pageIndex + 1));
            var context = canvas.getContext("2d");

            canvas.height = viewport.height;
            canvas.width = viewport.width;

            var render_context = {
                canvasContext: context,
                viewport: viewport
            };
            var task_renderer = page.render(render_context);

            PDF_PAGE_HEIGHT = canvas.height;
        });
    }
}

/**
 * Render PDF page
 */
function render_page(num) {
    PDFDocument.getPage(num).then(function(page) {
        var viewport = page.getViewport(CURRENT_SCALE);
        var canvas = document.createElement("canvas");
        var file_page_div = document.createElement("div");
        var file_page = document.createElement("a");
        var context = canvas.getContext("2d");

        // Set id attribute with page-#{pdf_page_number} format
        file_page_div.setAttribute("id", "page-" + (page.pageIndex + 1));

        // This will keep positions of child elements as per our needs
        file_page_div.setAttribute("style", "position: relative");

        // Add custom attributes
        var file_page_number = document.createAttribute("data-page-number");
        file_page_number.value = num;

        var file_page_class = document.createAttribute("class");
        file_page_class.value = "file-page";

        var file_page_name = document.createAttribute("name");
        file_page_name.value = "page-" + num;

        canvas.style.display = "block";
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        canvas.id = "cpage-" + num;
        PDF_PAGE_HEIGHT = canvas.height;

        file_page.setAttributeNode(file_page_number);
        file_page.setAttributeNode(file_page_class);
        file_page.setAttributeNode(file_page_name);

        file_page.appendChild(canvas);
        file_page_div.appendChild(file_page);
        ELEMENT_FILE_VIEW.appendChild(file_page_div);

        // Prepare renderer context
        var render_context = {
            canvasContext: context,
            viewport: viewport
        };
        
        page.render(render_context).then(function() {
            return page.getTextContent();

        }).then(function(textContent) {
            // Create div which will hold text-fragments
            var textLayerDiv = document.createElement("div");

            // Set it's class to textLayer which have required CSS styles
            textLayerDiv.setAttribute("class", "textLayer");

            // Append newly created div in `div#page-#{pdf_page_number}`
            file_page.appendChild(textLayerDiv);

            // Create new instance of TextLayerBuilder class
            var textLayer = new TextLayerBuilder({
                textLayerDiv: textLayerDiv, 
                pageIndex: page.pageIndex,
                viewport: viewport
            });

            // Set text-fragments
            textLayer.setTextContent(textContent);

            // Render text-fragments
            textLayer.render();
            
        }).then(function() {
            TOTAL_PAGES_LOADED++;
            show_pdf_loader();

            if (TOTAL_PAGES_LOADED == TOTAL_PAGES) {
                hide_pdf_loader();

                STATE_PDF_LOADED = true;
                STATE_THUMBNAILS_LOADED = true;
                window.focus();

                record_usage();
                TIMER_TRACK_USAGE = setInterval(record_usage, USAGE_TRACK_INTERVAL);
                TIMER_POST_USAGE = setInterval(post_usage, POST_USAGE_INTERVAL);

                overlay_toggle();
                set_current_page_in_toolbar();

                if (RESET_PAGE_URL_ON_LOAD) {
                    window.location.href = "#";
                    window.location.href = "#page-" + PAGE_NUMBER;
                }
            }
        });
    });
}

function render_thumbnail(num) {
    PDFDocument.getPage(num).then(function(page) {
        var viewport = page.getViewport(THUMBNAIL_SCALE);
        var canvas = document.createElement("canvas");
        var thumb_div = document.createElement("div");
        var label_div = document.createElement("div");
        var t = document.createTextNode("Page " + num);
        var context = canvas.getContext("2d");

        // Add custom attributes
        var div_class = document.createAttribute("class");
        div_class.value = "thumb-page";

        var div_name = document.createAttribute("data-thumb-page");
        div_name.value = num;

        var div_onclick = document.createAttribute("onclick");
        div_onclick.value = "select_thumbnail(" + num + ");";

        canvas.style.display = "block";
        canvas.height = viewport.height;
        canvas.width = viewport.width;

        thumb_div.setAttributeNode(div_class);
        thumb_div.setAttributeNode(div_name);
        thumb_div.setAttributeNode(div_onclick);

        // Prepare renderer context
        var render_context = {
            canvasContext: context,
            viewport: viewport
        };
        var task_renderer = page.render(render_context);

        label_div.appendChild(t);
        thumb_div.appendChild(label_div);
        thumb_div.appendChild(canvas);
        ELEMENT_THUMBNAILS_VIEW.appendChild(thumb_div);
    });
}

// Add visitor entry
if (parseInt(_M) == 0) {
    date = new Date();
    date.setTime(date.getTime() + (parseInt(_T) * 1000));
    document.cookie = _F + "=" + encodeURIComponent(_D) + "; expires=" + date.toGMTString() + "; path=/";
}
