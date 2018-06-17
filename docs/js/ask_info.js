// Make overlay visible all the time
document.getElementById("overlay").style.display = "block";

// Remove unwanted elements
document.getElementById("back-link").remove();
document.getElementById("full-screen").remove();
document.getElementById("thumbnails").remove();
document.getElementById("zoom-in").remove();
document.getElementById("zoom-out").remove();
document.getElementById("pdf-pager").remove();
document.getElementById("pdf-loader").remove();

// Validate the form
function validate_ask_info_page() {
    var email = "";
    var name = "";
    var company = "";
    var phone = "";
    var password = "";

    if (document.getElementById("email_address")) {
        email = document.getElementById("email_address").value;
        email = email.replace(/^\s+|\s+$/gm, "");

        if (email == "") {
            document.getElementById("email_address").focus();
            alert("Please enter email address.");
            return false;
        }

        var filter = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
        if (!filter.test(email)) {
            document.getElementById("email_address").focus();
            alert("Please enter valid email address.");
            return false;
        }
    }
    if (document.getElementById("full_name")) {
        name = document.getElementById("full_name").value;
        name = name.replace(/^\s+|\s+$/gm, "");
    }
    if (document.getElementById("company_name")) {
        company = document.getElementById("company_name").value;
        company = company.replace(/^\s+|\s+$/gm, "");
    }
    if (document.getElementById("contact_number")) {
        phone = document.getElementById("contact_number").value;
        phone = phone.replace(/^\s+|\s+$/gm, "");
    }
    if (document.getElementById("access_password")) {
        password = document.getElementById("access_password").value;
        password = password.replace(/^\s+|\s+$/gm, "");

        if (password == "") {
            document.getElementById("access_password").focus();
            alert("Please enter password.");
            return false;
        }
    }

    var post_data = {"email": email, "name": name, "company": company, "phone": phone, "password": password, r: _F, m: _M };
    do_post_request("/viewer/" + _A + "/verify-visitor", JSON.stringify(post_data), function() {
        var response = this.responseText;
        response = response.replace(/^\s+|\s+$/gm, "");

        try {
            var responseData = JSON.parse(response);

            if ("success" in responseData) {
                // Set cookie
                var t = parseInt(responseData["t"]);

                date = new Date();
                date.setTime(date.getTime() + (t * 1000));
                document.cookie = _C + "=" + encodeURIComponent(responseData["data"]) + "; expires=" + date.toGMTString() + "; path=/";
                
                window.location.href = window.location.href;

            } else if ("error_message" in responseData) {
                if (responseData["error_code"] == 2551) {
                    document.getElementById("access_password").focus();
                }
                alert(responseData["error_message"]);

            } else {
                alert("Something went wrong. Please try again later.");
            }
            
        } catch (e) {
            alert("Something went wrong. Please try again later.");
        }
    });

    return false;
}