<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/


// Application setup
Route::get('/setup', 'AppController@showSetup');
Route::post('/setup', 'AppController@doSetup');
Route::get('/install', 'AppController@install');
Route::get('/update', 'AppController@update');

// Public pages
Route::get('/', 'HomeController@showIndex');
Route::get('/log_error', 'HomeController@logError');
Route::get('/invoice_now', 'HomeController@invoiceNow');
Route::get('/keep_alive', 'HomeController@keepAlive');
Route::post('/get_started', 'AccountController@getStarted');

// Client visible pages
Route::group(['middleware' => 'auth:client'], function () {
    Route::get('view/{invitation_key}', 'ClientPortalController@view');
    Route::get('download/{invitation_key}', 'ClientPortalController@download');
    Route::put('sign/{invitation_key}', 'ClientPortalController@sign');
    Route::get('view', 'HomeController@viewLogo');
    Route::get('approve/{invitation_key}', 'QuoteController@approve');
    Route::get('payment/{invitation_key}/{gateway_type?}/{source_id?}', 'OnlinePaymentController@showPayment');
    Route::post('payment/{invitation_key}', 'OnlinePaymentController@doPayment');
    Route::match(['GET', 'POST'], 'complete/{invitation_key?}/{gateway_type?}', 'OnlinePaymentController@offsitePayment');
    Route::get('bank/{routing_number}', 'OnlinePaymentController@getBankInfo');
    Route::get('relation/payment_methods', 'ClientPortalController@paymentMethods');
    Route::post('relation/payment_methods/verify', 'ClientPortalController@verifyPaymentMethod');
    //Route::get('relation/payment_methods/add/{gateway_type}/{source_id?}', 'ClientPortalController@addPaymentMethod');
    //Route::post('relation/payment_methods/add/{gateway_type}', 'ClientPortalController@postAddPaymentMethod');
    Route::post('relation/payment_methods/default', 'ClientPortalController@setDefaultPaymentMethod');
    Route::post('relation/payment_methods/{source_id}/remove', 'ClientPortalController@removePaymentMethod');
    Route::get('relation/quotes', 'ClientPortalController@quoteIndex');
    Route::get('relation/credits', 'ClientPortalController@creditIndex');
    Route::get('relation/invoices', 'ClientPortalController@invoiceIndex');
    Route::get('relation/invoices/recurring', 'ClientPortalController@recurringInvoiceIndex');
    Route::post('relation/invoices/auto_bill', 'ClientPortalController@setAutoBill');
    Route::get('relation/documents', 'ClientPortalController@documentIndex');
    Route::get('relation/payments', 'ClientPortalController@paymentIndex');
    Route::get('relation/dashboard/{contact_key?}', 'ClientPortalController@dashboard');
    Route::get('relation/documents/js/{documents}/{filename}', 'ClientPortalController@getDocumentVFSJS');
    Route::get('relation/documents/{invitation_key}/{documents}/{filename?}', 'ClientPortalController@getDocument');
    Route::get('relation/documents/{invitation_key}/{filename?}', 'ClientPortalController@getInvoiceDocumentsZip');

    Route::get('api/relation.quotes', ['as' => 'api.relation.quotes', 'uses' => 'ClientPortalController@quoteDatatable']);
    Route::get('api/relation.credits', ['as' => 'api.relation.credits', 'uses' => 'ClientPortalController@creditDatatable']);
    Route::get('api/relation.invoices', ['as' => 'api.relation.invoices', 'uses' => 'ClientPortalController@invoiceDatatable']);
    Route::get('api/relation.recurring_invoices', ['as' => 'api.relation.recurring_invoices', 'uses' => 'ClientPortalController@recurringInvoiceDatatable']);
    Route::get('api/relation.documents', ['as' => 'api.relation.documents', 'uses' => 'ClientPortalController@documentDatatable']);
    Route::get('api/relation.payments', ['as' => 'api.relation.payments', 'uses' => 'ClientPortalController@paymentDatatable']);
    Route::get('api/relation.activity', ['as' => 'api.relation.activity', 'uses' => 'ClientPortalController@activityDatatable']);
});


Route::get('license', 'NinjaController@show_license_payment');
Route::post('license', 'NinjaController@do_license_payment');
Route::get('claim_license', 'NinjaController@claim_license');

Route::post('signup/validate', 'AccountController@checkEmail');
Route::post('signup/submit', 'AccountController@submitSignup');

Route::get('/auth/{provider}', 'Auth\AuthController@authLogin');
Route::get('/auth_unlink', 'Auth\AuthController@authUnlink');
Route::match(['GET', 'POST'], '/buy_now/{gateway_type?}', 'OnlinePaymentController@handleBuyNow');

Route::post('/hook/email_bounced', 'AppController@emailBounced');
Route::post('/hook/email_opened', 'AppController@emailOpened');
Route::post('/hook/bot/{platform?}', 'BotController@handleMessage');
Route::post('/payment_hook/{accKey}/{gatewayId}', 'OnlinePaymentController@handlePaymentWebhook');


//Auth::routes();

// Laravel auth routes
// Authentication Routes...
Route::get('login', ['as' => 'login', 'uses' => 'Auth\LoginController@showLoginForm']);
Route::post('login', ['as' => 'login', 'uses' => 'Auth\LoginController@login']);
Route::get('logout', ['as' => 'logout', 'uses' => 'Auth\LoginController@logout']);

// Registration Routes...
Route::get('signup', ['as' => 'signup', 'uses' => 'Auth\RegisterController@showRegistrationForm']);
Route::post('signup', ['as' => 'signup', 'uses' => 'Auth\RegisterController@register']);

// Password Reset Routes...
Route::get('recover_password', ['as' => 'forgot', 'uses' => 'Auth\ForgotPasswordController@showLinkRequestForm']);
Route::post('recover_password', ['as' => 'forgot', 'uses' => 'Auth\ForgotPasswordController@sendResetLinkEmail']);
Route::get('password/reset/{token}', ['as' => 'forgot', 'uses' => 'Auth\ResetPasswordController@showResetForm']);
Route::post('password/reset', ['as' => 'forgot', 'uses' => 'Auth\ResetPasswordController@reset']);


Route::get('/user/confirm/{code}', 'UserController@confirm');


Route::get('/relation/login', ['as' => 'clientlogin', 'uses' => 'ClientAuth\LoginController@showLoginForm']);
Route::post('/relation/login', ['as' => 'clientlogin', 'uses' => 'ClientAuth\LoginController@login']);
Route::get('/relation/logout', ['as' => 'clientlogout', 'uses' => 'ClientAuth\LoginController@logout']);
Route::get('/relation/sessionexpired', ['as' => 'sessionexpired', 'uses' => 'ClientAuth\LoginController@getSessionExpired']);


// Password Reset Routes...
Route::get('/relation/recover_password', ['as' => 'clientforgot', 'uses' => 'ClientAuth\ForgotPasswordController@showLinkRequestForm']);
Route::post('/relation/recover_password', ['as' => 'clientforgot', 'uses' => 'ClientAuth\ForgotPasswordController@sendResetLinkEmail']);
Route::get('/relation/password/reset/{token}', ['as' => 'clientforgot', 'uses' => 'ClientAuth\ResetPasswordController@showResetForm']);
Route::post('/relation/password/reset', ['as' => 'clientforgot', 'uses' => 'ClientAuth\ResetPasswordController@reset']);


// Relation auth
/*
Route::get('/relation/login', ['as' => 'login', 'uses' => 'ClientAuth\AuthController@getLogin']);
Route::post('/relation/login', ['as' => 'login', 'uses' => 'ClientAuth\AuthController@postLogin']);
Route::get('/relation/logout', ['as' => 'logout', 'uses' => 'ClientAuth\AuthController@getLogout']);
Route::get('/relation/sessionexpired', ['as' => 'logout', 'uses' => 'ClientAuth\AuthController@getSessionExpired']);
Route::get('/relation/recover_password', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@getEmail']);
Route::post('/relation/recover_password', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@postEmail']);
Route::get('/relation/password/reset/{invitation_key}/{token}', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@getReset']);
Route::post('/relation/password/reset', ['as' => 'forgot', 'uses' => 'ClientAuth\PasswordController@postReset']);
*/


if (Utils::isNinja()) {
    Route::post('/signup/register', 'AccountController@doRegister');
    Route::get('/news_feed/{user_type}/{version}/', 'HomeController@newsFeed');
    Route::get('/demo', 'AccountController@demo');
}

if (Utils::isReseller()) {
    Route::post('/reseller_stats', 'AppController@stats');
}

Route::group(['middleware' => 'auth:user'], function () {
    Route::get('dashboard', 'DashboardController@index');
    Route::get('dashboard_chart_data/{group_by}/{start_date}/{end_date}/{currency_id}/{include_expenses}', 'DashboardController@chartData');
    Route::get('set_entity_filter/{entity_type}/{filter?}', 'AccountController@setEntityFilter');
    Route::get('hide_message', 'HomeController@hideMessage');
    Route::get('force_inline_pdf', 'UserController@forcePDFJS');
    Route::get('company/get_search_data', ['as' => 'get_search_data', 'uses' => 'AccountController@getSearchData']);
    Route::get('check_invoice_number/{invoice_id?}', 'InvoiceController@checkInvoiceNumber');
    Route::post('save_sidebar_state', 'UserController@saveSidebarState');
    Route::post('contact_us', 'HomeController@contactUs');

    Route::get('settings/user_details', 'AccountController@showUserDetails');
    Route::post('settings/user_details', 'AccountController@saveUserDetails');
    Route::post('settings/payment_gateway_limits', 'AccountController@savePaymentGatewayLimits');
    Route::post('users/change_password', 'UserController@changePassword');


    Route::resource('tasks', 'TaskController');
    Route::get('api/tasks/{relation_id?}', 'TaskController@getDatatable');
    Route::get('tasks/create/{relation_id?}/{project_id?}', 'TaskController@create');
    Route::post('tasks/bulk', 'TaskController@bulk');
    Route::get('projects', 'ProjectController@index');
    Route::get('api/projects', 'ProjectController@getDatatable');
    Route::get('projects/create/{relation_id?}', 'ProjectController@create');
    Route::post('projects', 'ProjectController@store');
    Route::put('projects/{projects}', 'ProjectController@update');
    Route::get('projects/{projects}/edit', 'ProjectController@edit');
    Route::post('projects/bulk', 'ProjectController@bulk');

    Route::get('api/recurring_invoices/{relation_id?}', 'InvoiceController@getRecurringDatatable');

    Route::get('invoices/invoice_history/{invoice_id}', 'InvoiceController@invoiceHistory');
    Route::get('quotes/quote_history/{invoice_id}', 'InvoiceController@invoiceHistory');

    Route::resource('invoices', 'InvoiceController');
    Route::get('api/invoices/{relation_id?}', 'InvoiceController@getDatatable');
    Route::get('invoices/create/{relation_id?}', 'InvoiceController@create');
    Route::get('recurring_invoices/create/{relation_id?}', 'InvoiceController@createRecurring');
    Route::get('recurring_invoices', 'RecurringInvoiceController@index');
    Route::get('recurring_invoices/{invoices}/edit', 'InvoiceController@edit');
    Route::get('invoices/{invoices}/clone', 'InvoiceController@cloneInvoice');
    Route::post('invoices/bulk', 'InvoiceController@bulk');
    Route::post('recurring_invoices/bulk', 'InvoiceController@bulk');

    Route::get('documents/{documents}/{filename?}', 'DocumentController@get');
    Route::get('documents/js/{documents}/{filename}', 'DocumentController@getVFSJS');
    Route::get('documents/preview/{documents}/{filename?}', 'DocumentController@getPreview');
    Route::post('documents', 'DocumentController@postUpload');
    Route::delete('documents/{documents}', 'DocumentController@delete');

    Route::get('quotes/create/{relation_id?}', 'QuoteController@create');
    Route::get('quotes/{invoices}/clone', 'InvoiceController@cloneInvoice');
    Route::get('quotes/{invoices}/edit', 'InvoiceController@edit');
    Route::put('quotes/{invoices}', 'InvoiceController@update');
    Route::get('quotes/{invoices}', 'InvoiceController@edit');
    Route::post('quotes', 'InvoiceController@store');
    Route::get('quotes', 'QuoteController@index');
    Route::get('api/quotes/{relation_id?}', 'QuoteController@getDatatable');
    Route::post('quotes/bulk', 'QuoteController@bulk');

    Route::resource('payments', 'PaymentController');
    Route::get('payments/create/{relation_id?}/{invoice_id?}', 'PaymentController@create');
    Route::get('api/payments/{relation_id?}', 'PaymentController@getDatatable');
    Route::post('payments/bulk', 'PaymentController@bulk');

    Route::resource('credits', 'CreditController');
    Route::get('credits/create/{relation_id?}/{invoice_id?}', 'CreditController@create');
    Route::get('api/credits/{relation_id?}', 'CreditController@getDatatable');
    Route::post('credits/bulk', 'CreditController@bulk');


    Route::get('/resend_confirmation', 'AccountController@resendConfirmation');
    Route::post('/update_setup', 'AppController@updateSetup');


    // vendor
    Route::resource('vendors', 'VendorController');
    Route::get('api/vendors', 'VendorController@getDatatable');
    Route::post('vendors/bulk', 'VendorController@bulk');

    // Expense
    Route::resource('expenses', 'ExpenseController');
    Route::get('expenses/create/{vendor_id?}/{relation_id?}/{category_id?}', 'ExpenseController@create');
    Route::get('api/expenses', 'ExpenseController@getDatatable');
    Route::get('api/expenses/{id}', 'ExpenseController@getDatatableVendor');
    Route::post('expenses/bulk', 'ExpenseController@bulk');
    Route::get('expense_categories', 'ExpenseCategoryController@index');
    Route::get('api/expense_categories', 'ExpenseCategoryController@getDatatable');
    Route::get('expense_categories/create', 'ExpenseCategoryController@create');
    Route::post('expense_categories', 'ExpenseCategoryController@store');
    Route::put('expense_categories/{expense_categories}', 'ExpenseCategoryController@update');
    Route::get('expense_categories/{expense_categories}/edit', 'ExpenseCategoryController@edit');
    Route::post('expense_categories/bulk', 'ExpenseCategoryController@bulk');

    // BlueVine
    Route::post('bluevine/signup', 'BlueVineController@signup');
    Route::get('bluevine/hide_message', 'BlueVineController@hideMessage');
    Route::get('bluevine/completed', 'BlueVineController@handleCompleted');
    Route::get('white_label/hide_message', 'NinjaController@hideWhiteLabelMessage');
});

Route::group([
    'middleware' => ['auth:user', 'permissions.required'],
    'permissions' => 'admin',
], function () {
    Route::get('api/users', 'UserController@getDatatable');
    Route::resource('users', 'UserController');
    Route::post('users/bulk', 'UserController@bulk');
    Route::get('send_confirmation/{user_id}', 'UserController@sendConfirmation');
    Route::get('/switch_account/{user_id}', 'UserController@switchAccount');
    Route::get('/unlink_account/{user_account_id}/{user_id}', 'UserController@unlinkAccount');
    Route::get('/manage_companies', 'UserController@manageCompanies');

    Route::get('api/tokens', 'TokenController@getDatatable');
    Route::resource('tokens', 'TokenController');
    Route::post('tokens/bulk', 'TokenController@bulk');

    Route::get('api/tax_rates', 'TaxRateController@getDatatable');
    Route::resource('tax_rates', 'TaxRateController');
    Route::post('tax_rates/bulk', 'TaxRateController@bulk');

    Route::get('settings/email_preview', 'AccountController@previewEmail');
    Route::post('settings/client_portal', 'AccountController@saveClientPortalSettings');
    Route::post('settings/email_settings', 'AccountController@saveEmailSettings');
    Route::get('corporation/{section}/{subSection?}', 'AccountController@redirectLegacy');
    Route::get('settings/data_visualizations', 'ReportController@d3');
    Route::get('reports', 'ReportController@showReports');
    Route::post('reports', 'ReportController@showReports');

    Route::post('settings/change_plan', 'AccountController@changePlan');
    Route::post('settings/cancel_account', 'AccountController@cancelAccount');
    Route::post('settings/company_details', 'AccountController@updateDetails');
    Route::post('settings/{section?}', 'AccountController@doSection');

    Route::post('user/setTheme', 'UserController@setTheme');
    Route::post('remove_logo', 'AccountController@removeLogo');

    Route::post('/export', 'ExportController@doExport');
    Route::post('/import', 'ImportController@doImport');
    Route::post('/import_csv', 'ImportController@doImportCSV');

    Route::get('gateways/create/{show_wepay?}', 'AccountGatewayController@create');
    Route::resource('gateways', 'AccountGatewayController');
    Route::get('gateways/{public_id}/resend_confirmation', 'AccountGatewayController@resendConfirmation');
    Route::get('api/gateways', 'AccountGatewayController@getDatatable');
    Route::post('acc_gateways/bulk', 'AccountGatewayController@bulk');

    Route::get('bank_accs/import_ofx', 'BankAccountController@showImportOFX');
    Route::post('bank_accs/import_ofx', 'BankAccountController@doImportOFX');
    Route::resource('bank_accs', 'BankAccountController');
    Route::get('api/bank_accs', 'BankAccountController@getDatatable');
    Route::post('bank_accs/bulk', 'BankAccountController@bulk');
    Route::post('bank_accs/validate', 'BankAccountController@validateAccount');
    Route::post('bank_accs/import_expenses/{bank_id}', 'BankAccountController@importExpenses');
    Route::get('self-update', 'SelfUpdateController@index');
    Route::post('self-update', 'SelfUpdateController@update');
    Route::get('self-update/download', 'SelfUpdateController@download');
});

Route::group(['middleware' => 'auth:user'], function () {
    Route::get('settings/{section?}', 'AccountController@showSection');
});

// Route groups for API
Route::group(['middleware' => 'api', 'prefix' => 'api/v1'], function () {
    Route::get('ping', 'AccountApiController@ping');
    Route::post('login', 'AccountApiController@login');
    Route::post('oauth_login', 'AccountApiController@oauthLogin');
    Route::post('register', 'AccountApiController@register');
    Route::get('static', 'AccountApiController@getStaticData');
    Route::get('companies', 'AccountApiController@show');
    Route::put('companies', 'AccountApiController@update');

    Route::get('quotes', 'QuoteApiController@index');
    Route::get('invoices', 'InvoiceApiController@index');
    Route::get('download/{invoice_id}', 'InvoiceApiController@download');
    Route::resource('invoices', 'InvoiceApiController');
    Route::resource('payments', 'PaymentApiController');
    Route::get('tasks', 'TaskApiController@index');
    Route::resource('tasks', 'TaskApiController');
    Route::post('hooks', 'IntegrationController@subscribe');
    Route::post('email_invoice', 'InvoiceApiController@emailInvoice');
    Route::get('user_companies', 'AccountApiController@getUserAccounts');

    Route::resource('tax_rates', 'TaxRateApiController');
    Route::resource('users', 'UserApiController');
    Route::resource('expenses', 'ExpenseApiController');
    Route::post('add_token', 'AccountApiController@addDeviceToken');
    Route::post('update_notifications', 'AccountApiController@updatePushNotifications');
    Route::get('dashboard', 'DashboardApiController@index');
    Route::resource('documents', 'DocumentAPIController');
    Route::resource('vendors', 'VendorApiController');
    Route::resource('expense_categories', 'ExpenseCategoryApiController');
});

// Redirects for legacy links
Route::get('/rocksteady', function () {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/about', function () {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/contact', function () {
    return Redirect::to(NINJA_WEB_URL . '/contact', 301);
});
Route::get('/plans', function () {
    return Redirect::to(NINJA_WEB_URL . '/pricing', 301);
});
Route::get('/faq', function () {
    return Redirect::to(NINJA_WEB_URL . '/how-it-works', 301);
});
Route::get('/features', function () {
    return Redirect::to(NINJA_WEB_URL . '/features', 301);
});
Route::get('/testimonials', function () {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/compare-online-invoicing{sites?}', function () {
    return Redirect::to(NINJA_WEB_URL, 301);
});
Route::get('/forgot', function () {
    return Redirect::to(NINJA_APP_URL . '/recover_password', 301);
});
Route::get('/feed', function () {
    return Redirect::to(NINJA_WEB_URL . '/feed', 301);
});
Route::get('/comments/feed', function () {
    return Redirect::to(NINJA_WEB_URL . '/comments/feed', 301);
});

/*
if (Utils::isNinjaDev())
{
  //ini_set('memory_limit','1024M');
  //set_time_limit(0);
  Auth::loginUsingId(1);
}
*/

// Include static app constants
require_once app_path() . '/Constants.php';
