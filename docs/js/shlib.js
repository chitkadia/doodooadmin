/**
 * Library for handling statistics
 */
var API_ENDPOINT = "https://api.cultofpassion.com";
var REQUEST_HEADERS = {
    "Content-Type": "application/json;charset=UTF-8",
    "Accept": "application/json",
    "X-SH-Source": "WEB_APP",
    "X-Requested-With": "XMLHttpRequest"
};

/**
 * Perform GET request
 */
function do_get_request(url, success_callback) {
    var xhr = new XMLHttpRequest();

    // Initialize request
    xhr.open("GET", API_ENDPOINT + url);

    // Pass required headers
    for (var key in REQUEST_HEADERS) {
        xhr.setRequestHeader(key, REQUEST_HEADERS[key]);
    }

    // Set callback function
    xhr.onload = success_callback;

    // Send request
    xhr.send();
}

/**
 * Perform POST request
 */
function do_post_request(url, payload, success_callback) {
    var xhr = new XMLHttpRequest();

    // Initialize request
    xhr.open("POST", API_ENDPOINT + url);

    // Pass required headers
    for (var key in REQUEST_HEADERS) {
        xhr.setRequestHeader(key, REQUEST_HEADERS[key]);
    }

    // Set callback function
    xhr.onload = success_callback;

    // Send request
    xhr.send(payload);
}