<?php
/**
 * Register application wide routes (APIs)
 */
$sh_app->group("/user", function() {
    $this->map(["GET", "OPTIONS"], "/login", \App\Controllers\LoginController::class . ":login");
    $this->map(["GET", "OPTIONS"], "/login-connect/{method}", \App\Controllers\LoginController::class . ":getLoginURL");
    $this->map(["POST", "OPTIONS"], "/loginwith/{method}", \App\Controllers\LoginController::class . ":loginWith");

    $this->map(["POST", "OPTIONS"], "/signup", \App\Controllers\SignupController::class . ":signup");
    $this->map(["GET", "OPTIONS"], "/verify/{code}", \App\Controllers\SignupController::class . ":verifyAccount");
    $this->map(["GET", "OPTIONS"], "/resend-verification/{code}", \App\Controllers\SignupController::class . ":resendVerificationCode");
    $this->map(["POST", "OPTIONS"], "/resend-verification", \App\Controllers\SignupController::class . ":resendVerification");
    $this->map(["GET", "OPTIONS"], "/account-exists/{email}", \App\Controllers\SignupController::class . ":accountExists");

    $this->map(["GET", "OPTIONS"], "/check-invite/{code}", \App\Controllers\SignupController::class . ":checkInvitation");
    $this->map(["POST", "OPTIONS"], "/accept-invite/{code}", \App\Controllers\SignupController::class . ":acceptInvitation");

    $this->map(["POST", "OPTIONS"], "/forgot-password", \App\Controllers\ResetPasswordController::class . ":forgotPassword");
    $this->map(["GET", "OPTIONS"], "/resend-password-reset/{code}", \App\Controllers\ResetPasswordController::class . ":resendResetPasswordCode");
    $this->map(["GET", "OPTIONS"], "/valid-code/{code}", \App\Controllers\ResetPasswordController::class . ":isValidRequest");
    $this->map(["POST", "OPTIONS"], "/reset-password/{code}", \App\Controllers\ResetPasswordController::class . ":resetPassword");

    $this->map(["POST", "OPTIONS"], "/expire-token", \App\Controllers\AdminController::class . ":expireAuthToken");
});

$sh_app->group("/product", function() {
    $this->map(["GET", "OPTIONS"], "/category", \App\Controllers\ProductController::class . ":getProductCategoryList");
    $this->map(["POST", "OPTIONS"], "/add-category", \App\Controllers\ProductController::class . ":addProductCategoryList");
    $this->map(["POST", "OPTIONS"], "/{id}/category-update", \App\Controllers\ProductController::class . ":updateProductCategoryList");
    $this->map(["GET", "OPTIONS"], "/{id}/category-view", \App\Controllers\ProductController::class . ":viewProductCategoryList");
    $this->map(["DELETE", "OPTIONS"], "/{id}/category-delete", \App\Controllers\ProductController::class . ":deleteProductCategoryList");
    $this->map(["POST", "OPTIONS"], "/{id}/category-status-update", \App\Controllers\ProductController::class . ":updateProductCategoryListStatus");
    $this->map(["GET", "OPTIONS"], "/sub-category", \App\Controllers\ProductController::class . ":getProductSubCategoryList");
    $this->map(["POST", "OPTIONS"], "/add-sub-category", \App\Controllers\ProductController::class . ":addProductSubCategoryList");
    $this->map(["POST", "OPTIONS"], "/{id}/sub-category-update", \App\Controllers\ProductController::class . ":updateProductSubCategoryList");
    $this->map(["GET", "OPTIONS"], "/{id}/sub-category-view", \App\Controllers\ProductController::class . ":viewProductSubCategoryList");
    $this->map(["DELETE", "OPTIONS"], "/{id}/sub-category-delete", \App\Controllers\ProductController::class . ":deleteProductSubCategoryList");
    $this->map(["POST", "OPTIONS"], "/{id}/sub-category-status-update", \App\Controllers\ProductController::class . ":updateProductSubCategoryListStatus");
    $this->map(["GET", "OPTIONS"], "/category-list", \App\Controllers\ProductController::class . ":viewMainCategoryList");
    $this->map(["GET", "OPTIONS"], "/age", \App\Controllers\ProductController::class . ":getAgeCategoryList");
    $this->map(["POST", "OPTIONS"], "/add-age", \App\Controllers\ProductController::class . ":addAgeCategoryList");
    $this->map(["POST", "OPTIONS"], "/{id}/age-update", \App\Controllers\ProductController::class . ":updateAgeCategoryList");
    $this->map(["GET", "OPTIONS"], "/{id}/age-view", \App\Controllers\ProductController::class . ":viewAgeCategoryList");
    $this->map(["DELETE", "OPTIONS"], "/{id}/age-delete", \App\Controllers\ProductController::class . ":deleteAgeCategoryList");
    $this->map(["POST", "OPTIONS"], "/{id}/age-status-update", \App\Controllers\ProductController::class . ":updateAgeCategoryListStatus");
    $this->map(["GET", "OPTIONS"], "/category/list", \App\Controllers\ProductController::class . ":getAllCategoryList");
    $this->map(["GET", "OPTIONS"], "/sub-category/list", \App\Controllers\ProductController::class . ":getAllSubCategoryList");
    $this->map(["GET", "OPTIONS"], "/age-group/list", \App\Controllers\ProductController::class . ":getAllAgeGroupList");
    $this->map(["GET", "OPTIONS"], "/{id}/sub-category/list", \App\Controllers\ProductController::class . ":getSelectedSubCategoryList");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\ProductController::class . ":createProduct");
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\ProductController::class . ":getProductList");
    $this->map(["POST", "OPTIONS"], "/{id}/status-update", \App\Controllers\ProductController::class . ":updateProductStatus");
    $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\ProductController::class . ":deleteProduct");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\ProductController::class . ":updateProductList");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\ProductController::class . ":viewProductList");
    $this->map(["POST", "OPTIONS"], "/{id}/image-upload", \App\Controllers\ProductController::class . ":uploadImage");
    $this->map(["GET", "OPTIONS"], "/image-list", \App\Controllers\ProductController::class . ":viewProductImageList");
    $this->map(["GET", "OPTIONS"], "/{prod_id}/image-list", \App\Controllers\ProductController::class . ":viewProductImage");
    $this->map(["POST", "OPTIONS"], "/{id}/{img_id}/image-status-update", \App\Controllers\ProductController::class . ":updateImageStatus");
    $this->map(["POST", "OPTIONS"], "/{id}/{img_id}/image-default-update", \App\Controllers\ProductController::class . ":updateImageDefault");
    $this->map(["DELETE", "OPTIONS"], "/{id}/{img_id}/image-delete", \App\Controllers\ProductController::class . ":deleteImage");
    $this->map(["POST", "OPTIONS"], "/{id}/{img_id}/image-upload", \App\Controllers\ProductController::class . ":UpdateImage");
    $this->map(["POST", "OPTIONS"], "/{prod_id}/add-cart", \App\Controllers\ProductController::class . ":addCart");
    $this->map(["POST", "OPTIONS"], "/update-cart", \App\Controllers\ProductController::class . ":updateCart");
    $this->map(["DELETE", "OPTIONS"], "/{cart_id}/cart-delete", \App\Controllers\ProductController::class . ":deleteCart");
    $this->map(["GET", "OPTIONS"], "/view-cart", \App\Controllers\ProductController::class . ":viewCart");
    $this->map(["POST", "OPTIONS"], "/add-review", \App\Controllers\ProductController::class . ":addReview");
    $this->map(["GET", "OPTIONS"], "/{prod_id}/get-review", \App\Controllers\ProductController::class . ":viewReview");
});

$sh_app->map(["POST", "OPTIONS"], "/payment-process", \App\Controllers\ProductController::class . ":makePayment");
$sh_app->map(["POST", "OPTIONS"], "/payment-response", \App\Controllers\ProductController::class . ":paymentResponse");
$sh_app->map(["GET", "OPTIONS"], "/shipping-address", \App\Controllers\ProductController::class . ":getShippingAddress");
$sh_app->map(["POST", "OPTIONS"], "/add-shipping-address", \App\Controllers\ProductController::class . ":addShippingAddress");
$sh_app->map(["POST", "OPTIONS"], "/checkout-product", \App\Controllers\ProductController::class . ":checkoutProducts");

$sh_app->map(["GET", "OPTIONS"], "/order-list", \App\Controllers\ProductController::class . ":getOrderList");

$sh_app->map(["GET", "OPTIONS"], "/contactus-leads", \App\Controllers\ContactusLeadsController::class . ":getContactusLeads");
$sh_app->map(["POST", "OPTIONS"], "/add-contactus", \App\Controllers\ContactusLeadsController::class . ":addContactus");

$sh_app->map(["GET", "OPTIONS"], "/dashboard", \App\Controllers\ProfileController::class . ":dashboard");
$sh_app->group("/me", function() {
    $this->map(["GET", "OPTIONS"], "", \App\Controllers\ProfileController::class . ":profile");
    $this->map(["POST", "OPTIONS"], "/update-profile", \App\Controllers\ProfileController::class . ":updateProfile");
    $this->map(["POST", "OPTIONS"], "/change-password", \App\Controllers\ProfileController::class . ":changePassword");
    $this->map(["POST", "OPTIONS"], "/set-password", \App\Controllers\ProfileController::class . ":setPassword");
    $this->map(["POST", "OPTIONS"], "/update-preference", \App\Controllers\ProfileController::class . ":updatePreferences");
    $this->map(["POST", "OPTIONS"], "/update-preferences-multi", \App\Controllers\ProfileController::class . ":updatePreferencesMulti");
    $this->map(["GET", "OPTIONS"], "/resend-verification", \App\Controllers\ProfileController::class . ":resendVerificationEmail");

    $this->map(["GET", "OPTIONS"], "/feed", \App\Controllers\ProfileController::class . ":feed");

    $this->map(["GET", "OPTIONS"], "/logout", \App\Controllers\ProfileController::class . ":logout");
});

$sh_app->group("/mail-accounts", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\EmailAccountsController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\EmailAccountsController::class . ":create");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\EmailAccountsController::class . ":view");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\EmailAccountsController::class . ":update");
    $this->map(["POST", "OPTIONS"], "/{id}/gmail-update", \App\Controllers\EmailAccountsController::class . ":GmailAccountUpdate");
    $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\EmailAccountsController::class . ":delete");
    $this->map(["GET", "OPTIONS"], "/{id}/copy", \App\Controllers\EmailAccountsController::class . ":copy");
    $this->map(["GET", "OPTIONS"], "/{id}/mark-as-public", \App\Controllers\EmailAccountsController::class . ":markAsPublic");
    $this->map(["POST", "OPTIONS"], "/{id}/status-update", \App\Controllers\EmailAccountsController::class . ":statusUpdate");
    $this->map(["GET", "OPTIONS"], "/connect", \App\Controllers\EmailAccountsController::class . ":connect");
    $this->map(["POST", "OPTIONS"], "/connect-verify", \App\Controllers\EmailAccountsController::class . ":connectVerify");
    $this->map(["GET", "OPTIONS"], "/check-restriction", \App\Controllers\EmailAccountsController::class . ":checkPlanRestriction");

});

$sh_app->group("/emails", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\EmailsController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\EmailsController::class . ":create");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\EmailsController::class . ":view");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\EmailsController::class . ":update");
    $this->map(["POST", "OPTIONS"], "/{id}/update-status", \App\Controllers\EmailsController::class . ":updateStatus");
    $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\EmailsController::class . ":delete");
    $this->map(["GET", "OPTIONS"], "/{id}/copy", \App\Controllers\EmailsController::class . ":copy");
    $this->map(["GET", "OPTIONS"], "/{id}/snooze", \App\Controllers\EmailsController::class . ":snooze");
    $this->map(["GET", "OPTIONS"], "/email-draft/{id}", \App\Controllers\EmailsController::class . ":viewScheduledEmail");
});

$sh_app->group("/campaigns", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\CampaignsController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\CampaignsController::class . ":create");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\CampaignsController::class . ":view");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\CampaignsController::class . ":update");
    $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\CampaignsController::class . ":delete");
    $this->map(["GET", "OPTIONS"], "/{id}/copy", \App\Controllers\CampaignsController::class . ":copy");
    $this->map(["GET", "OPTIONS"], "/{id}/snooze", \App\Controllers\CampaignsController::class . ":snooze");
    $this->map(["POST", "OPTIONS"], "/{id}/status-update", \App\Controllers\CampaignsController::class . ":statusUpdate");
    $this->map(["GET", "OPTIONS"], "/{id}/view-recipient", \App\Controllers\CampaignsController::class . ":viewRecipient");
    $this->map(["GET", "OPTIONS"], "/{stage_id}/view-recipient-click-count/{seq_id}", \App\Controllers\CampaignsController::class . ":viewRecipientClickCount");
    $this->map(["GET", "OPTIONS"], "/{id}/view-stage/{stage_id}", \App\Controllers\CampaignsController::class . ":viewStage");
    $this->map(["GET", "OPTIONS"], "/{id}/view-stage-contacts/{stage_id}", \App\Controllers\CampaignsController::class . ":viewStageContacts");
    $this->map(["GET", "OPTIONS"], "/{id}/sequence-delete/{seq_id}", \App\Controllers\CampaignsController::class . ":sequenceDelete");
    $this->map(["GET", "OPTIONS"], "/{id}/sequence-mail/{seq_id}", \App\Controllers\CampaignsController::class . ":sequenceMail");
    $this->map(["GET", "OPTIONS"], "/{id}/export-data/{stage_id}", \App\Controllers\CampaignsController::class . ":exportData");
    $this->map(["POST", "OPTIONS"], "/preview-csv", \App\Controllers\CampaignsController::class . ":previewCsvData");
    $this->map(["GET", "OPTIONS"], "/{id}/import-csv-data", \App\Controllers\CampaignsController::class . ":importCsvData");
    $this->map(["GET", "POST", "OPTIONS"], "/{id}/status-pause-resume", \App\Controllers\CampaignsController::class . ":statusPauseResume");
    $this->map(["POST", "OPTIONS"], "/send-test-mail", \App\Controllers\CampaignsController::class . ":sendTestMail");
    $this->map(["GET", "OPTIONS"], "/{id}/reply-check/{stage_id}", \App\Controllers\CampaignsController::class . ":replyCheck");
    $this->map(["GET", "OPTIONS"], "/domain-block/list", \App\Controllers\CampaignsController::class . ":listsDomainBlock");
    $this->map(["POST", "OPTIONS"], "/domain-block/create", \App\Controllers\CampaignsController::class . ":createDomainBlocklist");
    $this->map(["DELETE", "OPTIONS"], "/domain-block/{id}/delete", \App\Controllers\CampaignsController::class . ":deleteDomain");
});

$sh_app->group("/documents", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\DocumentsController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\DocumentsController::class . ":create");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\DocumentsController::class . ":view");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\DocumentsController::class . ":update");
    $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\DocumentsController::class . ":delete");
    $this->map(["GET", "OPTIONS"], "/{id}/{folder_id}/copy", \App\Controllers\DocumentsController::class . ":copy");
    $this->map(["GET", "OPTIONS"], "/{id}/mark-as-public", \App\Controllers\DocumentsController::class . ":markAsPublic");
    $this->map(["POST", "OPTIONS"], "/{id}/status-update", \App\Controllers\DocumentsController::class . ":statusUpdate");
    $this->map(["POST", "OPTIONS"], "/{id}/move", \App\Controllers\DocumentsController::class . ":move");
    $this->map(["POST", "OPTIONS"], "/{id}/rename", \App\Controllers\DocumentsController::class . ":rename");
    $this->map(["POST", "OPTIONS"], "/{id}/share", \App\Controllers\DocumentsController::class . ":share");
    $this->map(["GET", "OPTIONS"], "/{id}/space", \App\Controllers\DocumentsController::class . ":getDocLinks");
    $this->map(["GET", "OPTIONS"], "/{id}/performance", \App\Controllers\DocumentsController::class . ":getDocPerformance");
    $this->map(["GET", "OPTIONS"], "/{id}/{doc_id}/visit", \App\Controllers\DocumentsController::class . ":getDocVisit");

    $this->group("/folders", function() {
        $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\DocsFoldersController::class . ":lists");
        $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\DocsFoldersController::class . ":create");
        $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\DocsFoldersController::class . ":view");
        $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\DocsFoldersController::class . ":update");
        $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\DocsFoldersController::class . ":delete");
        $this->map(["POST", "OPTIONS"], "/{id}/share", \App\Controllers\DocsFoldersController::class . ":share");
    });
});

$sh_app->group("/links", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\LinksController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\LinksController::class . ":create");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\LinksController::class . ":view");
    $this->map(["GET", "OPTIONS"], "/{id}/oldview", \App\Controllers\LinksController::class . ":oldview");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\LinksController::class . ":update");
    $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\LinksController::class . ":delete");
    $this->map(["GET", "OPTIONS"], "/{id}/copy", \App\Controllers\LinksController::class . ":copy");
    $this->map(["POST", "OPTIONS"], "/{id}/status-update", \App\Controllers\LinksController::class . ":statusUpdate");
    $this->map(["POST", "OPTIONS"], "/{id}/{link}/doc-status-update", \App\Controllers\LinksController::class . ":docStatusUpdate");
    $this->map(["GET", "OPTIONS"], "/{id}/performance", \App\Controllers\LinksController::class . ":getSpacePerformance");
    $this->map(["GET", "OPTIONS"], "/{id}/{link_id}/visit", \App\Controllers\LinksController::class . ":getSpaceVisit");
});

$sh_app->group("/templates", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\TemplatesController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\TemplatesController::class . ":create");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\TemplatesController::class . ":view");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\TemplatesController::class . ":update");
    $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\TemplatesController::class . ":delete");
    $this->map(["GET", "OPTIONS"], "/{id}/{folder_id}/copy", \App\Controllers\TemplatesController::class . ":copy");
    $this->map(["POST", "OPTIONS"], "/{id}/status-update", \App\Controllers\TemplatesController::class . ":statusUpdate");
    $this->map(["POST", "OPTIONS"], "/{id}/move", \App\Controllers\TemplatesController::class . ":move");
    $this->map(["POST", "OPTIONS"], "/{id}/share", \App\Controllers\TemplatesController::class . ":share");
    $this->map(["GET", "OPTIONS"], "/{id}/get", \App\Controllers\TemplatesController::class . ":getTemplateData");

    $this->group("/folders", function() {
        $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\TempFoldersController::class . ":lists");
        $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\TempFoldersController::class . ":create");
        $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\TempFoldersController::class . ":view");
        $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\TempFoldersController::class . ":update");
        $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\TempFoldersController::class . ":delete");
        $this->map(["POST", "OPTIONS"], "/{id}/share", \App\Controllers\TempFoldersController::class . ":share");
    });
});

$sh_app->group("/contacts", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\ContactsController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\ContactsController::class . ":create");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\ContactsController::class . ":view");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\ContactsController::class . ":update");
    $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\ContactsController::class . ":delete");
    $this->map(["GET", "OPTIONS"], "/{id}/copy", \App\Controllers\ContactsController::class . ":copy");
    $this->map(["GET", "OPTIONS"], "/{id}/feed", \App\Controllers\ContactsController::class . ":feed");
    $this->map(["POST", "OPTIONS"], "/{id}/status-update", \App\Controllers\ContactsController::class . ":statusUpdate");
});

$sh_app->group("/company", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\CompanyController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\CompanyController::class . ":create");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\CompanyController::class . ":view");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\CompanyController::class . ":update");
    $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\CompanyController::class . ":delete");
    $this->map(["GET", "OPTIONS"], "/{id}/copy", \App\Controllers\CompanyController::class . ":copy");
    $this->map(["GET", "OPTIONS"], "/{id}/feed", \App\Controllers\CompanyController::class . ":feed");
    $this->map(["POST", "OPTIONS"], "/{id}/status-update", \App\Controllers\CompanyController::class . ":statusUpdate");
    $this->map(["GET", "OPTIONS"], "/{id}/contacts", \App\Controllers\CompanyController::class . ":getContacts");
});

$sh_app->group("/teams", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\TeamsController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\TeamsController::class . ":create");
    $this->group("/{id}", function() {
        $this->map(["GET", "OPTIONS"], "/view", \App\Controllers\TeamsController::class . ":view");
        $this->map(["POST", "OPTIONS"], "/update", \App\Controllers\TeamsController::class . ":update");
        $this->map(["DELETE", "OPTIONS"], "/delete", \App\Controllers\TeamsController::class . ":delete");
        $this->map(["GET", "OPTIONS"], "/copy", \App\Controllers\TeamsController::class . ":copy");
    });
});

$sh_app->group("/members", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\MembersController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/invite", \App\Controllers\MembersController::class . ":invite");
    $this->group("/{id}", function() {
        $this->map(["GET", "OPTIONS"], "/view", \App\Controllers\MembersController::class . ":view");
        $this->map(["POST", "OPTIONS"], "/update", \App\Controllers\MembersController::class . ":update");
        $this->map(["DELETE", "OPTIONS"], "/delete", \App\Controllers\MembersController::class . ":delete");
        $this->map(["POST", "OPTIONS"], "/status-update", \App\Controllers\MembersController::class . ":statusUpdate");
        $this->map(["GET", "OPTIONS"], "/resend-invitation", \App\Controllers\MembersController::class . ":resendInvitation");
        $this->map(["GET", "OPTIONS"], "/activity", \App\Controllers\MembersController::class . ":activity");
        $this->map(["GET", "OPTIONS"], "/resources", \App\Controllers\MembersController::class . ":resources");
    });
});

$sh_app->group("/roles", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\RolesController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\RolesController::class . ":create");
    $this->group("/{id}", function() {
        $this->map(["GET", "OPTIONS"], "/view", \App\Controllers\RolesController::class . ":view");
        $this->map(["POST", "OPTIONS"], "/update", \App\Controllers\RolesController::class . ":update");
        $this->map(["DELETE", "OPTIONS"], "/delete", \App\Controllers\RolesController::class . ":delete");
        $this->map(["GET", "OPTIONS"], "/copy", \App\Controllers\RolesController::class . ":copy");
    });
    $this->map(["GET", "OPTIONS"], "/resources/list", \App\Controllers\RolesController::class . ":getResources");
});

$sh_app->group("/account", function() {
    $this->map(["GET", "OPTIONS"], "/plan/{code}/details", \App\Controllers\BillingController::class . ":planDetails");
    $this->map(["POST", "OPTIONS"], "/billing/check-coupon", \App\Controllers\BillingController::class . ":checkCoupon");
    $this->map(["POST", "OPTIONS"], "/buy", \App\Controllers\BillingController::class . ":buy");
    $this->map(["POST", "OPTIONS"], "/add-seat", \App\Controllers\BillingController::class . ":addSeat");
    $this->map(["POST", "OPTIONS"], "/upgrade", \App\Controllers\BillingController::class . ":upgrade");
    $this->map(["POST", "OPTIONS"], "/downgrade", \App\Controllers\BillingController::class . ":downgrade");
    $this->map(["GET", "OPTIONS"], "/subscription/cancel", \App\Controllers\BillingController::class . ":cancelSubscription");
    $this->map(["GET", "OPTIONS"], "/billing/history", \App\Controllers\BillingController::class . ":history");
    $this->map(["GET", "OPTIONS"], "/billing/{id}/view", \App\Controllers\BillingController::class . ":view");
    $this->map(["GET", "OPTIONS"], "/billing/{id}/invoice", \App\Controllers\BillingController::class . ":invoice");
    $this->map(["GET", "OPTIONS"], "/information", \App\Controllers\BillingController::class . ":activePlan");
    $this->map(["POST", "OPTIONS"], "/card-update", \App\Controllers\BillingController::class . ":cardUpdate");
    $this->map(["GET", "OPTIONS"], "/organisation/get", \App\Controllers\AccountController::class . ":getDetails");
    $this->map(["POST", "OPTIONS"], "/organisation/update", \App\Controllers\AccountController::class . ":update");
    $this->map(["GET", "OPTIONS"], "/billing-member-list", \App\Controllers\BillingController::class . ":getBillingMemberList");
    $this->map(["GET", "OPTIONS"], "/billing-emailacc-list", \App\Controllers\BillingController::class . ":getBillingEmailAccountList");
    $this->map(["GET", "OPTIONS"], "/subscription", \App\Controllers\BillingController::class . ":activePlan");
});

$sh_app->group("/branding", function() {
    $this->map(["GET", "OPTIONS"], "/get", \App\Controllers\BrandingController::class . ":getDetails");
    $this->map(["POST", "OPTIONS"], "/update", \App\Controllers\BrandingController::class . ":update");
    $this->map(["GET", "OPTIONS"], "/status-update", \App\Controllers\BrandingController::class . ":statusUpdate");
});

$sh_app->group("/web-hooks", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\WebhooksController::class . ":lists");
    $this->map(["POST", "OPTIONS"], "/create", \App\Controllers\WebhooksController::class . ":create");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\WebhooksController::class . ":view");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\WebhooksController::class . ":update");
    $this->map(["DELETE", "OPTIONS"], "/{id}/delete", \App\Controllers\WebhooksController::class . ":delete");
    $this->map(["GET", "OPTIONS"], "/{id}/copy", \App\Controllers\WebhooksController::class . ":copy");
    $this->map(["POST", "OPTIONS"], "/{id}/status-update", \App\Controllers\WebhooksController::class . ":statusUpdate");
});

$sh_app->group("/reports", function() {
    $this->map(["GET", "OPTIONS"], "", \App\Controllers\ReportsController::class . ":index");
});

$sh_app->group("/list", function() {
    $this->map(["GET", "OPTIONS"], "/my-resources", \App\Controllers\ListController::class . ":getResources");
    $this->map(["GET", "OPTIONS"], "/my-members", \App\Controllers\ListController::class . ":getMembers");
    $this->map(["GET", "OPTIONS"], "/timezones", \App\Controllers\ListController::class . ":getTzList");
    $this->map(["GET", "OPTIONS"], "/all-members", \App\Controllers\ListController::class . ":getAllMembers");
    $this->map(["GET", "OPTIONS"], "/app-vars", \App\Controllers\ListController::class . ":getAppVars");
    $this->map(["GET", "OPTIONS"], "/all-roles", \App\Controllers\ListController::class . ":getAllRoles");
    $this->map(["GET", "OPTIONS"], "/all-teams", \App\Controllers\ListController::class . ":getAllTeams");
    $this->map(["GET", "OPTIONS"], "/all-contacts", \App\Controllers\ListController::class . ":getAllContacts");
    $this->map(["GET", "OPTIONS"], "/email-accounts", \App\Controllers\ListController::class . ":getMyEmailAccounts");
    $this->map(["GET", "OPTIONS"], "/all-templates", \App\Controllers\ListController::class . ":getAllTemplates");
    $this->map(["GET", "OPTIONS"], "/webhook-resources", \App\Controllers\ListController::class . ":getAllWebhookResources");
    $this->map(["GET", "OPTIONS"], "/all-role-resources", \App\Controllers\ListController::class . ":getResourcesForRoles");
    $this->map(["GET", "OPTIONS"], "/role/{id}/resources", \App\Controllers\ListController::class . ":getResourcesByRole");
    $this->map(["GET", "OPTIONS"], "/my-teams", \App\Controllers\ListController::class . ":getMyTeams");
    $this->map(["GET", "OPTIONS"], "/template-folders", \App\Controllers\ListController::class . ":getTemplateFolders");
    $this->map(["GET", "OPTIONS"], "/document-folders", \App\Controllers\ListController::class . ":getDocumentFolders");
    $this->map(["GET", "OPTIONS"], "/all/plan", \App\Controllers\ListController::class . ":getAllPlans");
});
$sh_app->group("/users", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\AdminController::class . ":lists");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\AdminController::class . ":viewByid");
    $this->map(["GET", "OPTIONS"], "/{id}/login", \App\Controllers\AdminController::class . ":makeAdminLogin");
});

$sh_app->group("/viewer", function() {
    $this->map(["POST", "OPTIONS"], "/{id}/verify-visitor", \App\Controllers\ViewerStatsController::class . ":verifyVisitor");
    $this->map(["POST", "OPTIONS"], "/{id}/{link_id}", \App\Controllers\ViewerStatsController::class . ":postUsage");
});

$sh_app->group("/appchangelog", function() {
    $this->map(["POST", "OPTIONS"], "/get-package-details", \App\Controllers\AppChangeLogController::class . ":getLatestPackage");  
});

$sh_app->group("/user-accounts", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\ManageAccountsController::class . ":lists");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\ManageAccountsController::class . ":view");
    $this->map(["POST", "OPTIONS"], "/{id}/update", \App\Controllers\ManageAccountsController::class . ":update");
});

$sh_app->group("/email-accounts", function() {
    $this->map(["GET", "OPTIONS"], "/list", \App\Controllers\ManageEmailAccountsController::class . ":lists");
    $this->map(["GET", "OPTIONS"], "/{id}/view", \App\Controllers\ManageEmailAccountsController::class . ":view");
    $this->map(["POST", "OPTIONS"], "/reset-quota/{id}/update", \App\Controllers\ManageEmailAccountsController::class . ":update");
});

$sh_app->group("/error-logs", function() {
    $this->map(["POST", "OPTIONS"], "/post", \App\Controllers\ErrorLogsController::class . ":storeLogs");
});