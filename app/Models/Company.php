<?php namespace App\Models;

use Eloquent;
use Utils;
use Session;
use DateTime;
use Event;
use Cache;
use App;
use Carbon;
use App\Events\UserSettingsChanged;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;
use App\Models\Traits\PresentsInvoice;
use App\Models\Traits\GeneratesNumbers;
use App\Models\Traits\SendsEmails;

/**
 * Class Company
 */
class Company extends Eloquent
{
    use PresentableTrait;
    use SoftDeletes;
    use PresentsInvoice;
    use GeneratesNumbers;
    use SendsEmails;

    protected $table = 'companies';

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\CompanyPresenter';

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @var array
     */
    protected $hidden = ['ip'];

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'id_number',
        'vat_number',
        'work_email',
        'website',
        'work_phone',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'size_id',
        'industry_id',
        'email_footer',
        'timezone_id',
        'date_format_id',
        'datetime_format_id',
        'currency_id',
        'language_id',
        'military_time',
        'invoice_taxes',
        'invoice_item_taxes',
        'show_item_taxes',
        'default_tax_rate_id',
        'enable_second_tax_rate',
        'include_item_taxes_inline',
        'start_of_week',
        'financial_year_start',
        'enable_client_portal',
        'enable_client_portal_dashboard',
        'enable_portal_password',
        'send_portal_password',
        'enable_buy_now_buttons',
        'show_accept_invoice_terms',
        'show_accept_quote_terms',
        'require_invoice_signature',
        'require_quote_signature',
        'pdf_email_attachment',
        'document_email_attachment',
        'email_design_id',
        'enable_email_markup',
        'domain_id',
        'payment_terms',
    ];

    /**
     * @var array
     */
    public static $basicSettings = [
        COMPANY_COMPANY_DETAILS,
        COMPANY_USER_DETAILS,
        COMPANY_LOCALIZATION,
        COMPANY_PAYMENTS,
        COMPANY_TAX_RATES,
        COMPANY_PRODUCTS,
        COMPANY_NOTIFICATIONS,
        COMPANY_IMPORT_EXPORT,
        COMPANY_MANAGEMENT,
    ];

    /**
     * @var array
     */
    public static $advancedSettings = [
        COMPANY_INVOICE_SETTINGS,
        COMPANY_INVOICE_DESIGN,
        COMPANY_CLIENT_PORTAL,
        COMPANY_EMAIL_SETTINGS,
        COMPANY_TEMPLATES_AND_REMINDERS,
        COMPANY_BANKS,
        //COMPANY_REPORTS,
        COMPANY_DATA_VISUALIZATIONS,
        COMPANY_API_TOKENS,
        COMPANY_USER_MANAGEMENT,
    ];

    public static $modules = [
        ENTITY_RECURRING_INVOICE => 1,
        ENTITY_CREDIT => 2,
        ENTITY_QUOTE => 4,
        ENTITY_TASK => 8,
        ENTITY_EXPENSE => 16,
        ENTITY_VENDOR => 32,
    ];

    public static $dashboardSections = [
        'total_revenue' => 1,
        'average_invoice' => 2,
        'outstanding' => 4,
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function acc_tokens()
    {
        return $this->hasMany('App\Models\AccountToken');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany('App\Models\User');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function relations()
    {
        return $this->hasMany('App\Models\Relation');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function contacts()
    {
        return $this->hasMany('App\Models\Contact');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoices()
    {
        return $this->hasMany('App\Models\Invoice');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function acc_gateways()
    {
        return $this->hasMany('App\Models\AccountGateway');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bank_accs()
    {
        return $this->hasMany('App\Models\BankAccount');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tax_rates()
    {
        return $this->hasMany('App\Models\TaxRate');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany('App\Models\Product');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        return $this->belongsTo('App\Models\Country');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function timezone()
    {
        return $this->belongsTo('App\Models\Timezone');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function language()
    {
        return $this->belongsTo('App\Models\Language');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function date_format()
    {
        return $this->belongsTo('App\Models\DateFormat');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function datetime_format()
    {
        return $this->belongsTo('App\Models\DatetimeFormat');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function size()
    {
        return $this->belongsTo('App\Models\Size');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function industry()
    {
        return $this->belongsTo('App\Models\Industry');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function default_tax_rate()
    {
        return $this->belongsTo('App\Models\TaxRate');
    }

    /**
     * @return mixed
     */
    public function expenses()
    {
        return $this->hasMany('App\Models\Expense','company_id','id')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function payments()
    {
        return $this->hasMany('App\Models\Payment','company_id','id')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function corporation()
    {
        return $this->belongsTo('App\Models\Corporation');
    }

    /**
     * @return mixed
     */
    public function expense_categories()
    {
        return $this->hasMany('App\Models\ExpenseCategory','company_id','id')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function projects()
    {
        return $this->hasMany('App\Models\Project','company_id','id')->withTrashed();
    }

    /**
     * @param $value
     */
    public function setIndustryIdAttribute($value)
    {
        $this->attributes['industry_id'] = $value ?: null;
    }

    /**
     * @param $value
     */
    public function setCountryIdAttribute($value)
    {
        $this->attributes['country_id'] = $value ?: null;
    }

    /**
     * @param $value
     */
    public function setSizeIdAttribute($value)
    {
        $this->attributes['size_id'] = $value ?: null;
    }

    /**
     * @param int $gatewayId
     * @return bool
     */
    public function isGatewayConfigured($gatewayId = 0)
    {
        if ( ! $this->relationLoaded('acc_gateways')) {
            $this->load('acc_gateways');
        }

        if ($gatewayId) {
            return $this->getGatewayConfig($gatewayId) != false;
        } else {
            return count($this->acc_gateways) > 0;
        }
    }

    /**
     * @return bool
     */
    public function isEnglish()
    {
        return !$this->language_id || $this->language_id == DEFAULT_LANGUAGE;
    }

    /**
     * @return bool
     */
    public function hasInvoicePrefix()
    {
        if ( ! $this->invoice_number_prefix && ! $this->quote_number_prefix) {
            return false;
        }

        return $this->invoice_number_prefix != $this->quote_number_prefix;
    }

    /**
     * @return mixed
     */
    public function getDisplayName()
    {
        if ($this->name) {
            return $this->name;
        }

        //$this->load('users');
        $user = $this->users()->first();

        return $user->getDisplayName();
    }

    /**
     * @return string
     */
    public function getCityState()
    {
        $swap = $this->country && $this->country->swap_postal_code;
        return Utils::cityStateZip($this->city, $this->state, $this->postal_code, $swap);
    }

    /**
     * @return mixed
     */
    public function getMomentDateTimeFormat()
    {
        $format = $this->datetime_format ? $this->datetime_format->format_moment : DEFAULT_DATETIME_MOMENT_FORMAT;

        if ($this->military_time) {
            $format = str_replace('h:mm:ss a', 'H:mm:ss', $format);
        }

        return $format;
    }

    /**
     * @return string
     */
    public function getMomentDateFormat()
    {
        $format = $this->getMomentDateTimeFormat();
        $format = str_replace('h:mm:ss a', '', $format);
        $format = str_replace('H:mm:ss', '', $format);

        return trim($format);
    }

    /**
     * @return string
     */
    public function getTimezone()
    {
        if ($this->timezone) {
            return $this->timezone->name;
        } else {
            return 'US/Eastern';
        }
    }

    public function getDate($date = 'now')
    {
        if ( ! $date) {
            return null;
        } elseif ( ! $date instanceof \DateTime) {
            $date = new \DateTime($date);
        }

        return $date;
    }

    /**
     * @param string $date
     * @return DateTime|null|string
     */
    public function getDateTime($date = 'now')
    {
        $date = $this->getDate($date);
        $date->setTimeZone(new \DateTimeZone($this->getTimezone()));

        return $date;
    }

    /**
     * @return mixed
     */
    public function getCustomDateFormat()
    {
        return $this->date_format ? $this->date_format->format : DEFAULT_DATE_FORMAT;
    }


    public function getSampleLink()
    {
        $invitation = new Invitation();
        $invitation->company = $this;
        $invitation->invitation_key = '...';

        return $invitation->getLink();
    }


    /**
     * @param $amount
     * @param null $relation
     * @param bool $hideSymbol
     * @return string
     */
    public function formatMoney($amount, $relation = null, $decorator = false)
    {
        if ($relation && $relation->currency_id) {
            $currencyId = $relation->currency_id;
        } elseif ($this->currency_id) {
            $currencyId = $this->currency_id;
        } else {
            $currencyId = DEFAULT_CURRENCY;
        }

        if ($relation && $relation->country_id) {
            $countryId = $relation->country_id;
        } elseif ($this->country_id) {
            $countryId = $this->country_id;
        } else {
            $countryId = false;
        }

        if ( ! $decorator) {
            $decorator = $this->show_currency_code ? CURRENCY_DECORATOR_CODE : CURRENCY_DECORATOR_SYMBOL;
        }

        return Utils::formatMoney($amount, $currencyId, $countryId, $decorator);
    }

    /**
     * @return mixed
     */
    public function getCurrencyId()
    {
        return $this->currency_id ?: DEFAULT_CURRENCY;
    }

    /**
     * @param $date
     * @return null|string
     */
    public function formatDate($date)
    {
        $date = $this->getDate($date);

        if ( ! $date) {
            return null;
        }

        return $date->format($this->getCustomDateFormat());
    }

    /**
     * @param $date
     * @return null|string
     */
    public function formatDateTime($date)
    {
        $date = $this->getDateTime($date);

        if ( ! $date) {
            return null;
        }

        return $date->format($this->getCustomDateTimeFormat());
    }

    /**
     * @param $date
     * @return null|string
     */
    public function formatTime($date)
    {
        $date = $this->getDateTime($date);

        if ( ! $date) {
            return null;
        }

        return $date->format($this->getCustomTimeFormat());
    }

    /**
     * @return string
     */
    public function getCustomTimeFormat()
    {
        return $this->military_time ? 'H:i' : 'g:i a';
    }

    /**
     * @return mixed
     */
    public function getCustomDateTimeFormat()
    {
        $format = $this->datetime_format ? $this->datetime_format->format : DEFAULT_DATETIME_FORMAT;

        if ($this->military_time) {
            $format = str_replace('g:i a', 'H:i', $format);
        }

        return $format;
    }

    /*
    public function defaultGatewayType()
    {
        $accGateway = $this->acc_gateways[0];
        $paymentDriver = $accGateway->paymentDriver();

        return $paymentDriver->gatewayTypes()[0];
    }
    */

    /**
     * @param bool $type
     * @return AccountGateway|bool
     */
    public function getGatewayByType($type = false)
    {
        if ( ! $this->relationLoaded('acc_gateways')) {
            $this->load('acc_gateways');
        }

        /** @var AccountGateway $accGateway */
        foreach ($this->acc_gateways as $accGateway) {
            if ( ! $type) {
                return $accGateway;
            }

            $paymentDriver = $accGateway->paymentDriver();

            if ($paymentDriver->handles($type)) {
                return $accGateway;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function availableGatewaysIds()
    {
        if ( ! $this->relationLoaded('acc_gateways')) {
            $this->load('acc_gateways');
        }

        $gatewayTypes = [];
        $gatewayIds = [];

        foreach ($this->acc_gateways as $accGateway) {
            $paymentDriver = $accGateway->paymentDriver();
            $gatewayTypes = array_unique(array_merge($gatewayTypes, $paymentDriver->gatewayTypes()));
        }

        foreach (Cache::get('gateways') as $gateway) {
            $paymentDriverClass = AccountGateway::paymentDriverClass($gateway->provider);
            $paymentDriver = new $paymentDriverClass();
            $available = true;

            foreach ($gatewayTypes as $type) {
                if ($paymentDriver->handles($type)) {
                    $available = false;
                    break;
                }
            }
            if ($available) {
                $gatewayIds[] = $gateway->id;
            }
        }

        return $gatewayIds;
    }

    /**
     * @param bool $invitation
     * @param mixed $gatewayTypeId
     * @return bool
     */
    public function paymentDriver($invitation = false, $gatewayTypeId = false)
    {
        /** @var AccountGateway $accGateway */
        if ($accGateway = $this->getGatewayByType($gatewayTypeId)) {
            return $accGateway->paymentDriver($invitation, $gatewayTypeId);
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function gatewayIds()
    {
        return $this->acc_gateways()->pluck('gateway_id')->toArray();
    }

    /**
     * @param $gatewayId
     * @return bool
     */
    public function hasGatewayId($gatewayId)
    {
        return in_array($gatewayId, $this->gatewayIds());
    }

    /**
     * @param $gatewayId
     * @return bool
     */
    public function getGatewayConfig($gatewayId)
    {
        foreach ($this->acc_gateways as $gateway) {
            if ($gateway->gateway_id == $gatewayId) {
                return $gateway;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function hasLogo()
    {
        if($this->logo == ''){
            $this->calculateLogoDetails();
        }

        return !empty($this->logo);
    }

    /**
     * @return mixed
     */
    public function getLogoDisk(){
        return Storage::disk(env('LOGO_FILESYSTEM', 'logos'));
    }

    protected function calculateLogoDetails(){
        $disk = $this->getLogoDisk();

        if($disk->exists($this->acc_key.'.png')){
            $this->logo = $this->acc_key.'.png';
        } else if($disk->exists($this->acc_key.'.jpg')) {
            $this->logo = $this->acc_key.'.jpg';
        }

        if(!empty($this->logo)){
            $image = imagecreatefromstring($disk->get($this->logo));
            $this->logo_width = imagesx($image);
            $this->logo_height = imagesy($image);
            $this->logo_size = $disk->size($this->logo);
        } else {
            $this->logo = null;
        }
        $this->save();
    }

    /**
     * @return null
     */
    public function getLogoRaw(){
        if(!$this->hasLogo()){
            return null;
        }

        $disk = $this->getLogoDisk();
        return $disk->get($this->logo);
    }

    /**
     * @param bool $cachebuster
     * @return null|string
     */
    public function getLogoURL($cachebuster = false)
    {
        if(!$this->hasLogo()){
            return null;
        }

        $disk = $this->getLogoDisk();
        $adapter = $disk->getAdapter();

        if($adapter instanceof \League\Flysystem\Adapter\Local) {
            // Stored locally
            $logoUrl = url('/logo/' . $this->logo);

            if ($cachebuster) {
                $logoUrl .= '?no_cache='.time();
            }

            return $logoUrl;
        }

        return Document::getDirectFileUrl($this->logo, $this->getLogoDisk());
    }

    public function getLogoPath()
    {
        if ( ! $this->hasLogo()){
            return null;
        }

        $disk = $this->getLogoDisk();
        $adapter = $disk->getAdapter();

        if ($adapter instanceof \League\Flysystem\Adapter\Local) {
            return $adapter->applyPathPrefix($this->logo);
        } else {
            return Document::getDirectFileUrl($this->logo, $this->getLogoDisk());
        }
    }

    /**
     * @return mixed
     */
    public function getPrimaryUser()
    {
        return $this->users()
                    ->orderBy('id')
                    ->first();
    }

    /**
     * @param $userId
     * @param $name
     * @return null
     */
    public function getToken($userId, $name)
    {
        foreach ($this->acc_tokens as $token) {
            if ($token->user_id == $userId && $token->name === $name) {
                return $token->token;
            }
        }

        return null;
    }

    /**
     * @return mixed|null
     */
    public function getLogoWidth()
    {
        if(!$this->hasLogo()){
            return null;
        }

        return $this->logo_width;
    }

    /**
     * @return mixed|null
     */
    public function getLogoHeight()
    {
        if(!$this->hasLogo()){
            return null;
        }

        return $this->logo_height;
    }

    /**
     * @param $entityType
     * @param null $relationId
     * @return mixed
     */
    public function createInvoice($entityType = ENTITY_INVOICE, $relationId = null)
    {
        $invoice = Invoice::createNew();

        $invoice->is_recurring = false;
        $invoice->invoice_type_id = INVOICE_TYPE_STANDARD;
        $invoice->invoice_date = Utils::today();
        $invoice->start_date = Utils::today();
        $invoice->invoice_design_id = $this->invoice_design_id;
        $invoice->relation_id = $relationId;

        if ($entityType === ENTITY_RECURRING_INVOICE) {
            $invoice->invoice_number = microtime(true);
            $invoice->is_recurring = true;
        } else {
            if ($entityType == ENTITY_QUOTE) {
                $invoice->invoice_type_id = INVOICE_TYPE_QUOTE;
            }

            if ($this->hasRelationNumberPattern($invoice) && !$relationId) {
                // do nothing, we don't yet know the value
            } elseif ( ! $invoice->invoice_number) {
                $invoice->invoice_number = $this->getNextNumber($invoice);
            }
        }

        if (!$relationId) {
            $invoice->relation = Relation::createNew();
            $invoice->relation->id = 0;
        }

        return $invoice;
    }

    /**
     * @param bool $relation
     */
    public function loadLocalizationSettings($relation = false)
    {
        $this->load('timezone', 'date_format', 'datetime_format', 'language');

        $timezone = $this->timezone ? $this->timezone->name : DEFAULT_TIMEZONE;
        Session::put(SESSION_TIMEZONE, $timezone);

        Session::put(SESSION_DATE_FORMAT, $this->date_format ? $this->date_format->format : DEFAULT_DATE_FORMAT);
        Session::put(SESSION_DATE_PICKER_FORMAT, $this->date_format ? $this->date_format->picker_format : DEFAULT_DATE_PICKER_FORMAT);

        $currencyId = ($relation && $relation->currency_id) ? $relation->currency_id : $this->currency_id ?: DEFAULT_CURRENCY;
        $locale = ($relation && $relation->language_id) ? $relation->language->locale : ($this->language_id ? $this->Language->locale : DEFAULT_LOCALE);

        Session::put(SESSION_CURRENCY, $currencyId);
        Session::put(SESSION_CURRENCY_DECORATOR, $this->show_currency_code ? CURRENCY_DECORATOR_CODE : CURRENCY_DECORATOR_SYMBOL);
        Session::put(SESSION_LOCALE, $locale);

        App::setLocale($locale);

        $format = $this->datetime_format ? $this->datetime_format->format : DEFAULT_DATETIME_FORMAT;
        if ($this->military_time) {
            $format = str_replace('g:i a', 'H:i', $format);
        }
        Session::put(SESSION_DATETIME_FORMAT, $format);

        Session::put('start_of_week', $this->start_of_week);
    }

    /**
     * @return bool
     */
    public function isNinjaAccount()
    {
        return $this->acc_key === NINJA_COMPANY_KEY;
    }

    /**
     * @param $plan
     */
    public function startTrial($plan)
    {
        if ( ! Utils::isNinja()) {
            return;
        }

        if ($this->corporation->trial_started && $this->corporation->trial_started != '0000-00-00') {
            return;
        }

        $this->corporation->trial_plan = $plan;
        $this->corporation->trial_started = date_create()->format('Y-m-d');
        $this->corporation->save();
    }

    /**
     * @param $feature
     * @return bool
     */
    public function hasFeature($feature)
    {
        if (Utils::isNinjaDev()) {
            return true;
        }

        $planDetails = $this->getPlanDetails();
        $selfHost = !Utils::isNinjaProd();

        if (!$selfHost && function_exists('ninja_account_features')) {
            $result = ninja_account_features($this, $feature);

            if ($result != null) {
                return $result;
            }
        }

        switch ($feature) {
            // Pro
            case FEATURE_TASKS:
            case FEATURE_EXPENSES:
                if (Utils::isNinja() && $this->corporation_id < EXTRAS_GRANDFATHER_COMPANY_ID) {
                    return true;
                }

            case FEATURE_CUSTOMIZE_INVOICE_DESIGN:
            case FEATURE_DIFFERENT_DESIGNS:
            case FEATURE_EMAIL_TEMPLATES_REMINDERS:
            case FEATURE_INVOICE_SETTINGS:
            case FEATURE_CUSTOM_EMAILS:
            case FEATURE_PDF_ATTACHMENT:
            case FEATURE_MORE_INVOICE_DESIGNS:
            case FEATURE_QUOTES:
            case FEATURE_REPORTS:
            case FEATURE_BUY_NOW_BUTTONS:
            case FEATURE_API:
            case FEATURE_CLIENT_PORTAL_PASSWORD:
            case FEATURE_CUSTOM_URL:
                return $selfHost || !empty($planDetails);

            // Pro; No trial allowed, unless they're trialing enterprise with an active pro plan
            case FEATURE_MORE_RELATIONS:
                return $selfHost || !empty($planDetails) && (!$planDetails['trial'] || !empty($this->getPlanDetails(false, false)));

            // White Label
            case FEATURE_WHITE_LABEL:
                if ($this->isNinjaAccount() || (!$selfHost && $planDetails && !$planDetails['expires'])) {
                    return false;
                }
                // Fallthrough
            case FEATURE_REMOVE_CREATED_BY:
                return !empty($planDetails);// A plan is required even for self-hosted users

            // Enterprise; No Trial allowed; grandfathered for old pro users
            case FEATURE_USERS:// Grandfathered for old Pro users
                if($planDetails && $planDetails['trial']) {
                    // Do they have a non-trial plan?
                    $planDetails = $this->getPlanDetails(false, false);
                }

                return $selfHost || !empty($planDetails) && ($planDetails['plan'] == PLAN_ENTERPRISE || $planDetails['started'] <= date_create(PRO_USERS_GRANDFATHER_DEADLINE));

            // Enterprise; No Trial allowed
            case FEATURE_DOCUMENTS:
            case FEATURE_USER_PERMISSIONS:
                return $selfHost || !empty($planDetails) && $planDetails['plan'] == PLAN_ENTERPRISE && !$planDetails['trial'];

            default:
                return false;
        }
    }

    /**
     * @param null $plan_details
     * @return bool
     */
    public function isPro(&$plan_details = null)
    {
        if (!Utils::isNinjaProd()) {
            return true;
        }

        if ($this->isNinjaAccount()) {
            return true;
        }

        $plan_details = $this->getPlanDetails();

        return !empty($plan_details);
    }

    /**
     * @param null $plan_details
     * @return bool
     */
    public function isEnterprise(&$plan_details = null)
    {
        if (!Utils::isNinjaProd()) {
            return true;
        }

        if ($this->isNinjaAccount()) {
            return true;
        }

        $plan_details = $this->getPlanDetails();

        return $plan_details && $plan_details['plan'] == PLAN_ENTERPRISE;
    }

    /**
     * @param bool $include_inactive
     * @param bool $include_trial
     * @return array|null
     */
    public function getPlanDetails($include_inactive = false, $include_trial = true)
    {
        if (!$this->corporation) {
            return null;
        }

        $plan = $this->corporation->plan;
        $price = $this->corporation->plan_price;
        $trial_plan = $this->corporation->trial_plan;

        if((!$plan || $plan == PLAN_FREE) && (!$trial_plan || !$include_trial)) {
            return null;
        }

        $trial_active = false;
        if ($trial_plan && $include_trial) {
            $trial_started = DateTime::createFromFormat('Y-m-d', $this->corporation->trial_started);
            $trial_expires = clone $trial_started;
            $trial_expires->modify('+2 weeks');

            if ($trial_expires >= date_create()) {
               $trial_active = true;
            }
        }

        $plan_active = false;
        if ($plan) {
            if ($this->corporation->plan_expires == null) {
                $plan_active = true;
                $plan_expires = false;
            } else {
                $plan_expires = DateTime::createFromFormat('Y-m-d', $this->corporation->plan_expires);
                if ($plan_expires >= date_create()) {
                    $plan_active = true;
                }
            }
        }

        if (!$include_inactive && !$plan_active && !$trial_active) {
            return null;
        }

        // Should we show plan details or trial details?
        if (($plan && !$trial_plan) || !$include_trial) {
            $use_plan = true;
        } elseif (!$plan && $trial_plan) {
            $use_plan = false;
        } else {
            // There is both a plan and a trial
            if (!empty($plan_active) && empty($trial_active)) {
                $use_plan = true;
            } elseif (empty($plan_active) && !empty($trial_active)) {
                $use_plan = false;
            } elseif (!empty($plan_active) && !empty($trial_active)) {
                // Both are active; use whichever is a better plan
                if ($plan == PLAN_ENTERPRISE) {
                    $use_plan = true;
                } elseif ($trial_plan == PLAN_ENTERPRISE) {
                    $use_plan = false;
                } else {
                    // They're both the same; show the plan
                    $use_plan = true;
                }
            } else {
                // Neither are active; use whichever expired most recently
                $use_plan = $plan_expires >= $trial_expires;
            }
        }

        if ($use_plan) {
            return [
                'corporation_id' => $this->corporation->id,
                'num_users' => $this->corporation->num_users,
                'plan_price' => $price,
                'trial' => false,
                'plan' => $plan,
                'started' => DateTime::createFromFormat('Y-m-d', $this->corporation->plan_started),
                'expires' => $plan_expires,
                'paid' => DateTime::createFromFormat('Y-m-d', $this->corporation->plan_paid),
                'term' => $this->corporation->plan_term,
                'active' => $plan_active,
            ];
        } else {
            return [
                'corporation_id' => $this->corporation->id,
                'num_users' => 1,
                'plan_price' => 0,
                'trial' => true,
                'plan' => $trial_plan,
                'started' => $trial_started,
                'expires' => $trial_expires,
                'active' => $trial_active,
            ];
        }
    }

    /**
     * @return bool
     */
    public function isTrial()
    {
        if (!Utils::isNinjaProd()) {
            return false;
        }

        $plan_details = $this->getPlanDetails();

        return $plan_details && $plan_details['trial'];
    }

    /**
     * @return int
     */
    public function getCountTrialDaysLeft()
    {
        $planDetails = $this->getPlanDetails(true);

        if(!$planDetails || !$planDetails['trial']) {
            return 0;
        }

        $today = new DateTime('now');
        $interval = $today->diff($planDetails['expires']);

        return $interval ? $interval->d : 0;
    }

    /**
     * @return mixed
     */
    public function getRenewalDate()
    {
        $planDetails = $this->getPlanDetails();

        if ($planDetails) {
            $date = $planDetails['expires'];
            $date = max($date, date_create());
        } else {
            $date = date_create();
        }

        return Carbon::instance($date);
    }

    /**
     * @return float|null
     */
    public function getLogoSize()
    {
        if(!$this->hasLogo()){
            return null;
        }

        return round($this->logo_size / 1000);
    }

    /**
     * @return bool
     */
    public function isLogoTooLarge()
    {
        return $this->getLogoSize() > MAX_LOGO_FILE_SIZE;
    }

    /**
     * @param $eventId
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getSubscription($eventId)
    {
        return Subscription::where('company_id', '=', $this->id)->where('event_id', '=', $eventId)->first();
    }

    /**
     * @return $this
     */
    public function hideFieldsForViz()
    {
        foreach ($this->relations as $relation) {
            $relation->setVisible([
                'public_id',
                'name',
                'balance',
                'paid_to_date',
                'invoices',
                'contacts',
            ]);

            foreach ($relation->invoices as $invoice) {
                $invoice->setVisible([
                    'public_id',
                    'invoice_number',
                    'amount',
                    'balance',
                    'invoice_status_id',
                    'invoice_items',
                    'created_at',
                    'is_recurring',
                    'invoice_type_id',
                ]);

                foreach ($invoice->invoice_items as $invoiceItem) {
                    $invoiceItem->setVisible([
                        'product_key',
                        'cost',
                        'qty',
                    ]);
                }
            }

            foreach ($relation->contacts as $contact) {
                $contact->setVisible([
                    'public_id',
                    'first_name',
                    'last_name',
                    'email', ]);
            }
        }

        return $this;
    }

    /**
     * @param $entityType
     * @return mixed
     */
    public function getDefaultEmailSubject($entityType)
    {
        if (strpos($entityType, 'reminder') !== false) {
            $entityType = 'reminder';
        }

        return trans("texts.{$entityType}_subject", ['invoice' => '$invoice', 'company' => '$company']);
    }

    /**
     * @param $entityType
     * @return mixed
     */
    public function getEmailSubject($entityType)
    {
        if ($this->hasFeature(FEATURE_CUSTOM_EMAILS)) {
            $field = "email_subject_{$entityType}";
            $value = $this->$field;

            if ($value) {
                return $value;
            }
        }

        return $this->getDefaultEmailSubject($entityType);
    }

    /**
     * @param $entityType
     * @param bool $message
     * @return string
     */
    public function getDefaultEmailTemplate($entityType, $message = false)
    {
        if (strpos($entityType, 'reminder') !== false) {
            $entityType = ENTITY_INVOICE;
        }

        $template = '<div>$relation,</div><br>';

        if ($this->hasFeature(FEATURE_CUSTOM_EMAILS) && $this->email_design_id != EMAIL_DESIGN_PLAIN) {
            $template .= '<div>' . trans("texts.{$entityType}_message_button", ['amount' => '$amount']) . '</div><br>' .
                         '<div style="text-align: center;">$viewButton</div><br>';
        } else {
            $template .= '<div>' . trans("texts.{$entityType}_message", ['amount' => '$amount']) . '</div><br>' .
                         '<div>$viewLink</div><br>';
        }

        if ($message) {
            $template .= "$message<p/>\r\n\r\n";
        }

        return $template . '$footer';
    }

    /**
     * @param $entityType
     * @param bool $message
     * @return mixed
     */
    public function getEmailTemplate($entityType, $message = false)
    {
        $template = false;

        if ($this->hasFeature(FEATURE_CUSTOM_EMAILS)) {
            $field = "email_template_{$entityType}";
            $template = $this->$field;
        }

        if (!$template) {
            $template = $this->getDefaultEmailTemplate($entityType, $message);
        }

        // <br/> is causing page breaks with the email designs
        return str_replace('/>', ' />', $template);
    }

    /**
     * @param string $view
     * @return string
     */
    public function getTemplateView($view = '')
    {
        return $this->getEmailDesignId() == EMAIL_DESIGN_PLAIN ? $view : 'design' . $this->getEmailDesignId();
    }

    /**
     * @return mixed|string
     */
    public function getEmailFooter()
    {
        if ($this->email_footer) {
            // Add line breaks if HTML isn't already being used
            return strip_tags($this->email_footer) == $this->email_footer ? nl2br($this->email_footer) : $this->email_footer;
        } else {
            return '<p><div>' . trans('texts.email_signature') . "\n<br>\$company</div></p>";
        }
    }

    /**
     * @param $reminder
     * @return bool
     */
    public function getReminderDate($reminder)
    {
        if ( ! $this->{"enable_reminder{$reminder}"}) {
            return false;
        }

        $numDays = $this->{"num_days_reminder{$reminder}"};
        $plusMinus = $this->{"direction_reminder{$reminder}"} == REMINDER_DIRECTION_AFTER ? '-' : '+';

        return date('Y-m-d', strtotime("$plusMinus $numDays days"));
    }

    /**
     * @param Invoice $invoice
     * @return bool|string
     */
    public function getInvoiceReminder(Invoice $invoice)
    {
        for ($i=1; $i<=3; $i++) {
            if ($date = $this->getReminderDate($i)) {
                $field = $this->{"field_reminder{$i}"} == REMINDER_FIELD_DUE_DATE ? 'due_date' : 'invoice_date';
                if ($invoice->$field == $date) {
                    return "reminder{$i}";
                }
            }
        }

        return false;
    }

    /**
     * @param null $storage_gateway
     * @return bool
     */
    public function showTokenCheckbox(&$storage_gateway = null)
    {
        if (!($storage_gateway = $this->getTokenGatewayId())) {
            return false;
        }

        return $this->token_billing_type_id == TOKEN_BILLING_OPT_IN
                || $this->token_billing_type_id == TOKEN_BILLING_OPT_OUT;
    }

    /**
     * @return bool
     */
    public function getTokenGatewayId() {
        if ($this->isGatewayConfigured(GATEWAY_STRIPE)) {
            return GATEWAY_STRIPE;
        } elseif ($this->isGatewayConfigured(GATEWAY_BRAINTREE)) {
            return GATEWAY_BRAINTREE;
        } elseif ($this->isGatewayConfigured(GATEWAY_WEPAY)) {
            return GATEWAY_WEPAY;
        } else {
            return false;
        }
    }

    /**
     * @return bool|void
     */
    public function getTokenGateway() {
        $gatewayId = $this->getTokenGatewayId();
        if (!$gatewayId) {
            return;
        }

        return $this->getGatewayConfig($gatewayId);
    }

    /**
     * @return bool
     */
    public function selectTokenCheckbox()
    {
        return $this->token_billing_type_id == TOKEN_BILLING_OPT_OUT;
    }

    /**
     * @return string
     */
    public function getSiteUrl()
    {
        $url = SITE_URL;
        $iframe_url = $this->iframe_url;

        if ($iframe_url) {
            return "{$iframe_url}/?";
        } else if ($this->subdomain) {
            $url = Utils::replaceSubdomain($url, $this->subdomain);
        }

        return $url;
    }

    /**
     * @param $host
     * @return bool
     */
    public function checkSubdomain($host)
    {
        if (!$this->subdomain) {
            return true;
        }

        $server = explode('.', $host);
        $subdomain = $server[0];

        if (!in_array($subdomain, ['app', 'www']) && $subdomain != $this->subdomain) {
            return false;
        }

        return true;
    }

    /**
     * @param $field
     * @param bool $entity
     * @return bool
     */
    public function showCustomField($field, $entity = false)
    {
        if ($this->hasFeature(FEATURE_INVOICE_SETTINGS) && $this->$field) {
            return true;
        }

        if (!$entity) {
            return false;
        }

        // convert (for example) 'custom_invoice_label1' to 'invoice.custom_value1'
        $field = str_replace(['invoice_', 'label'], ['', 'value'], $field);

        return Utils::isEmpty($entity->$field) ? false : true;
    }

    /**
     * @return bool
     */
    public function attachPDF()
    {
        return $this->hasFeature(FEATURE_PDF_ATTACHMENT) && $this->pdf_email_attachment;
    }

    /**
     * @return mixed
     */
    public function getEmailDesignId()
    {
        return $this->hasFeature(FEATURE_CUSTOM_EMAILS) ? $this->email_design_id : EMAIL_DESIGN_PLAIN;
    }

    /**
     * @return string
     */
    public function clientViewCSS(){
        $css = '';

        if ($this->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN)) {
            $bodyFont = $this->getBodyFontCss();
            $headerFont = $this->getHeaderFontCss();

            $css = 'body{'.$bodyFont.'}';
            if ($headerFont != $bodyFont) {
                $css .= 'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6{'.$headerFont.'}';
            }

            $css .= $this->client_view_css;
        }

        return $css;
    }

    /**
     * @param string $protocol
     * @return string
     */
    public function getFontsUrl($protocol = ''){
        $bodyFont = $this->getHeaderFontId();
        $headerFont = $this->getBodyFontId();

        $bodyFontSettings = Utils::getFromCache($bodyFont, 'fonts');
        $google_fonts = [$bodyFontSettings['google_font']];

        if($headerFont != $bodyFont){
            $headerFontSettings = Utils::getFromCache($headerFont, 'fonts');
            $google_fonts[] = $headerFontSettings['google_font'];
        }

        return ($protocol?$protocol.':':'').'//fonts.googleapis.com/css?family='.implode('|',$google_fonts);
    }

    /**
     * @return mixed
     */
    public function getHeaderFontId() {
        return ($this->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) && $this->header_font_id) ? $this->header_font_id : DEFAULT_HEADER_FONT;
    }

    /**
     * @return mixed
     */
    public function getBodyFontId() {
        return ($this->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN) && $this->body_font_id) ? $this->body_font_id : DEFAULT_BODY_FONT;
    }

    /**
     * @return null
     */
    public function getHeaderFontName(){
        return Utils::getFromCache($this->getHeaderFontId(), 'fonts')['name'];
    }

    /**
     * @return null
     */
    public function getBodyFontName(){
        return Utils::getFromCache($this->getBodyFontId(), 'fonts')['name'];
    }

    /**
     * @param bool $include_weight
     * @return string
     */
    public function getHeaderFontCss($include_weight = true){
        $font_data = Utils::getFromCache($this->getHeaderFontId(), 'fonts');
        $css = 'font-family:'.$font_data['css_stack'].';';

        if($include_weight){
            $css .= 'font-weight:'.$font_data['css_weight'].';';
        }

        return $css;
    }

    /**
     * @param bool $include_weight
     * @return string
     */
    public function getBodyFontCss($include_weight = true){
        $font_data = Utils::getFromCache($this->getBodyFontId(), 'fonts');
        $css = 'font-family:'.$font_data['css_stack'].';';

        if($include_weight){
            $css .= 'font-weight:'.$font_data['css_weight'].';';
        }

        return $css;
    }

    /**
     * @return array
     */
    public function getFonts(){
        return array_unique([$this->getHeaderFontId(), $this->getBodyFontId()]);
    }

    /**
     * @return array
     */
    public function getFontsData(){
        $data = [];

        foreach($this->getFonts() as $font){
            $data[] = Utils::getFromCache($font, 'fonts');
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getFontFolders(){
        return array_map(function($item){return $item['folder'];}, $this->getFontsData());
    }

    public function isModuleEnabled($entityType)
    {
        if ( ! in_array($entityType, [
            ENTITY_RECURRING_INVOICE,
            ENTITY_CREDIT,
            ENTITY_QUOTE,
            ENTITY_TASK,
            ENTITY_EXPENSE,
            ENTITY_VENDOR,
        ])) {
            return true;
        }

        return $this->enabled_modules & static::$modules[$entityType];
    }

    public function requiresAuthorization($invoice)
    {
        return $this->showAcceptTerms($invoice) || $this->showSignature($invoice);
    }

    public function showAcceptTerms($invoice)
    {
        if ( ! $this->isPro() || ! $invoice->terms) {
            return false;
        }

        return $invoice->isQuote() ? $this->show_accept_quote_terms : $this->show_accept_invoice_terms;
    }

    public function showSignature($invoice)
    {
        if ( ! $this->isPro()) {
            return false;
        }

        return $invoice->isQuote() ? $this->require_quote_signature : $this->require_invoice_signature;
    }

    public function emailMarkupEnabled()
    {
        if ( ! Utils::isNinja()) {
            return false;
        }

        return $this->enable_email_markup;
    }

    public function defaultDueDate()
    {
        $numDays = $this->payment_terms == -1 ? 0 : $this->payment_terms;

        return Carbon::now($this->getTimezone())->addDays($numDays)->format('Y-m-d');
    }
}

Company::updated(function ($company)
{
    // prevent firing event if the invoice/quote counter was changed
    // TODO: remove once counters are moved to separate table
    $dirty = $company->getDirty();
    if (isset($dirty['invoice_number_counter']) || isset($dirty['quote_number_counter'])) {
        return;
    }

    Event::fire(new UserSettingsChanged());
});
