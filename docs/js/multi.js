// Add visitor entry
if (parseInt(_M) == 0) {
    date = new Date();
    date.setTime(date.getTime() + (parseInt(_T) * 1000));
    document.cookie = _F + "=" + encodeURIComponent(_D) + "; expires=" + date.toGMTString() + "; path=/";
}

// Remove unwanted elements
document.getElementById("back-link").remove();
document.getElementById("full-screen").remove();
document.getElementById("thumbnails").remove();
document.getElementById("zoom-in").remove();
document.getElementById("zoom-out").remove();
document.getElementById("pdf-pager").remove();
document.getElementById("pdf-loader").remove();