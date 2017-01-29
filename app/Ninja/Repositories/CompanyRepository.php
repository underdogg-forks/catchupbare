<?php namespace App\Ninja\Repositories;

use Auth;
use Request;
use Input;
use Session;
use Utils;
use URL;
use stdClass;
use Validator;
use Schema;
use App\Models\AccountGateway;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Modules\Relations\Models\Relation;
use App\Models\Credit;
use App\Models\Language;
use Modules\Relations\Models\Contact;
use App\Models\Company;
use App\Models\Corporation;
use App\Models\User;
use App\Models\UserAccount;
use App\Models\AccountToken;

class CompanyRepository
{
    public function create($firstName = '', $lastName = '', $email = '', $password = '')
    {
        $corporation = new Corporation();
        $corporation->utm_source = Input::get('utm_source');
        $corporation->utm_medium = Input::get('utm_medium');
        $corporation->utm_campaign = Input::get('utm_campaign');
        $corporation->utm_term = Input::get('utm_term');
        $corporation->utm_content = Input::get('utm_content');
        $corporation->save();

        $company = new Company();
        $company->ip = Request::getClientIp();
        $company->acc_key = str_random(RANDOM_KEY_LENGTH);
        $company->corporation_id = $corporation->id;

        // Track referal code
        if ($referralCode = Session::get(SESSION_REFERRAL_CODE)) {
            if ($user = User::whereReferralCode($referralCode)->first()) {
                $company->referral_user_id = $user->id;
            }
        }

        if ($locale = Session::get(SESSION_LOCALE)) {
            if ($language = Language::whereLocale($locale)->first()) {
                $company->language_id = $language->id;
            }
        }

        $company->save();

        $user = new User();
        if (!$firstName && !$lastName && !$email && !$password) {
            $user->password = str_random(RANDOM_KEY_LENGTH);
            $user->username = str_random(RANDOM_KEY_LENGTH);
        } else {
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->email = $user->username = $email;
            if (!$password) {
                $password = str_random(RANDOM_KEY_LENGTH);
            }
            $user->password = bcrypt($password);
        }

        $user->confirmed = !Utils::isNinja();
        $user->registered = !Utils::isNinja() || $email;

        if (!$user->confirmed) {
            $user->confirmation_code = str_random(RANDOM_KEY_LENGTH);
        }

        $company->users()->save($user);

        return $company;
    }

    public function getSearchData($user)
    {
        $data = $this->getAccountSearchData($user);

        $data['navigation'] = $user->is_admin ? $this->getNavigationSearchData() : [];

        return $data;
    }

    private function getAccountSearchData($user)
    {
        $company = $user->company;

        $data = [
            'relations' => [],
            'contacts' => [],
            'invoices' => [],
            'quotes' => [],
        ];

        // include custom relation fields in search
        if ($company->custom_client_label1) {
            $data[$company->custom_client_label1] = [];
        }
        if ($company->custom_client_label2) {
            $data[$company->custom_client_label2] = [];
        }

        if ($user->hasPermission('view_all')) {
            $relations = Relation::scope()
                        ->with('contacts', 'invoices')
                        ->get();
        } else {
            $relations = Relation::scope()
                        ->where('user_id', '=', $user->id)
                        ->with(['contacts', 'invoices' => function($query) use ($user) {
                            $query->where('user_id', '=', $user->id);
                        }])->get();
        }

        foreach ($relations as $relation) {
            if ($relation->name) {
                $data['relations'][] = [
                    'value' => $relation->name,
                    'tokens' => implode(',', [$relation->name, $relation->id_number, $relation->vat_number, $relation->work_phone]),
                    'url' => $relation->present()->url,
                ];
            }

            if ($relation->custom_value1) {
                $data[$company->custom_client_label1][] = [
                    'value' => "{$relation->custom_value1}: " . $relation->getDisplayName(),
                    'tokens' => $relation->custom_value1,
                    'url' => $relation->present()->url,
                ];
            }
            if ($relation->custom_value2) {
                $data[$company->custom_client_label2][] = [
                    'value' => "{$relation->custom_value2}: " . $relation->getDisplayName(),
                    'tokens' => $relation->custom_value2,
                    'url' => $relation->present()->url,
                ];
            }

            foreach ($relation->contacts as $contact) {
                $data['contacts'][] = [
                    'value' => $contact->getDisplayName(),
                    'tokens' => implode(',', [$contact->first_name, $contact->last_name, $contact->email, $contact->phone]),
                    'url' => $relation->present()->url,
                ];
            }

            foreach ($relation->invoices as $invoice) {
                $entityType = $invoice->getEntityType();
                $data["{$entityType}s"][] = [
                    'value' => $invoice->getDisplayName() . ': ' . $relation->getDisplayName(),
                    'tokens' => implode(',', [$invoice->invoice_number, $invoice->po_number]),
                    'url' => $invoice->present()->url,
                ];
            }
        }

        return $data;
    }

    private function getNavigationSearchData()
    {
        $entityTypes = [
            ENTITY_INVOICE,
            ENTITY_RELATION,
            ENTITY_QUOTE,
            ENTITY_TASK,
            ENTITY_EXPENSE,
            ENTITY_EXPENSE_CATEGORY,
            ENTITY_VENDOR,
            ENTITY_RECURRING_INVOICE,
            ENTITY_PAYMENT,
            ENTITY_CREDIT,
            ENTITY_PROJECT,
        ];

        foreach ($entityTypes as $entityType) {
            $features[] = [
                "new_{$entityType}",
                Utils::pluralizeEntityType($entityType) . '/create'
            ];
            $features[] = [
                'list_' . Utils::pluralizeEntityType($entityType),
                Utils::pluralizeEntityType($entityType)
            ];
        }

        $features = array_merge($features, [
            ['dashboard', '/dashboard'],
            ['reports', '/reports'],
            ['customize_design', '/settings/customize_design'],
            ['new_tax_rate', '/tax_rates/create'],
            ['new_product', '/products/create'],
            ['new_user', '/users/create'],
            ['custom_fields', '/settings/invoice_settings'],
            ['invoice_number', '/settings/invoice_settings'],
            ['buy_now_buttons', '/settings/client_portal#buy_now'],
            ['invoice_fields', '/settings/invoice_design#invoice_fields'],
        ]);

        $settings = array_merge(Company::$basicSettings, Company::$advancedSettings);

        if ( ! Utils::isNinjaProd()) {
            $settings[] = COMPANY_SYSTEM_SETTINGS;
        }

        foreach ($settings as $setting) {
            $features[] = [
                $setting,
                "/settings/{$setting}",
            ];
        }

        foreach ($features as $feature) {
            $data[] = [
                'value' => trans('texts.' . $feature[0]),
                'tokens' => trans('texts.' . $feature[0]),
                'url' => URL::to($feature[1])
            ];
        }

        return $data;
    }

    public function enablePlan($plan, $credit = 0)
    {
        $company = Auth::user()->company;
        $relation = $this->getNinjaClient($company);
        $invitation = $this->createNinjaInvoice($relation, $company, $plan, $credit);

        return $invitation;
    }

    public function createNinjaCredit($relation, $amount)
    {
        $company = $this->getNinjaAccount();

        $lastCredit = Credit::withTrashed()->whereCompanyId($company->id)->orderBy('public_id', 'DESC')->first();
        $publicId = $lastCredit ? ($lastCredit->public_id + 1) : 1;

        $credit = new Credit();
        $credit->public_id = $publicId;
        $credit->company_id = $company->id;
        $credit->user_id = $company->users()->first()->id;
        $credit->relation_id = $relation->id;
        $credit->amount = $amount;
        $credit->save();

        return $credit;
    }

    public function createNinjaInvoice($relation, $relationAccount, $plan, $credit = 0)
    {
        $term = $plan['term'];
        $plan_cost = $plan['price'];
        $num_users = $plan['num_users'];
        $plan = $plan['plan'];

        if ($credit < 0) {
            $credit = 0;
        }

        $company = $this->getNinjaAccount();
        $lastInvoice = Invoice::withTrashed()->whereCompanyId($company->id)->orderBy('public_id', 'DESC')->first();
        $renewalDate = $relationAccount->getRenewalDate();
        $publicId = $lastInvoice ? ($lastInvoice->public_id + 1) : 1;

        $invoice = new Invoice();
        $invoice->is_public = true;
        $invoice->company_id = $company->id;
        $invoice->user_id = $company->users()->first()->id;
        $invoice->public_id = $publicId;
        $invoice->relation_id = $relation->id;
        $invoice->invoice_number = $company->getNextNumber($invoice);
        $invoice->invoice_date = $renewalDate->format('Y-m-d');
        $invoice->amount = $invoice->balance = $plan_cost - $credit;
        $invoice->invoice_type_id = INVOICE_TYPE_STANDARD;

        // check for promo/discount
        $relationCorporation = $relationAccount->corporation;
        if ($relationCorporation->hasActivePromo() || $relationCorporation->hasActiveDiscount($renewalDate)) {
            $discount = $invoice->amount * $relationCorporation->discount;
            $invoice->discount = $relationCorporation->discount * 100;
            $invoice->amount -= $discount;
            $invoice->balance -= $discount;
        }

        $invoice->save();

        if ($credit) {
            $credit_item = InvoiceItem::createNew($invoice);
            $credit_item->qty = 1;
            $credit_item->cost = -$credit;
            $credit_item->notes = trans('texts.plan_credit_description');
            $credit_item->product_key = trans('texts.plan_credit_product');
            $invoice->invoice_items()->save($credit_item);
        }

        $item = InvoiceItem::createNew($invoice);
        $item->qty = 1;
        $item->cost = $plan_cost;
        $item->notes = trans("texts.{$plan}_plan_{$term}_description");

        if ($plan == PLAN_ENTERPRISE) {
            $min = Utils::getMinNumUsers($num_users);
            $item->notes .= "\n\n###" . trans('texts.min_to_max_users', ['min' => $min, 'max' => $num_users]);
        }

        // Don't change this without updating the regex in PaymentService->createPayment()
        $item->product_key = 'Plan - '.ucfirst($plan).' ('.ucfirst($term).')';
        $invoice->invoice_items()->save($item);

        $invitation = new Invitation();
        $invitation->company_id = $company->id;
        $invitation->user_id = $company->users()->first()->id;
        $invitation->public_id = $publicId;
        $invitation->invoice_id = $invoice->id;
        $invitation->contact_id = $relation->contacts()->first()->id;
        $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
        $invitation->save();

        return $invitation;
    }

    public function getNinjaAccount()
    {
        $company = Company::whereAccountKey(NINJA_COMPANY_KEY)->first();

        if ($company) {
            return $company;
        } else {
            $corporation = new Corporation();
            $corporation->save();

            $company = new Company();
            $company->name = 'Invoice Ninja';
            $company->work_email = 'contact@invoiceninja.com';
            $company->work_phone = '(800) 763-1948';
            $company->acc_key = NINJA_COMPANY_KEY;
            $company->corporation_id = $corporation->id;
            $company->save();

            $random = str_random(RANDOM_KEY_LENGTH);
            $user = new User();
            $user->registered = true;
            $user->confirmed = true;
            $user->email = 'contact@invoiceninja.com';
            $user->password = $random;
            $user->username = $random;
            $user->first_name = 'Invoice';
            $user->last_name = 'Ninja';
            $user->notify_sent = true;
            $user->notify_paid = true;
            $company->users()->save($user);

            if ($config = env(NINJA_GATEWAY_CONFIG)) {
                $accGateway = new AccountGateway();
                $accGateway->user_id = $user->id;
                $accGateway->gateway_id = NINJA_GATEWAY_ID;
                $accGateway->public_id = 1;
                $accGateway->setConfig(json_decode($config));
                $company->acc_gateways()->save($accGateway);
            }
        }

        return $company;
    }

    public function getNinjaClient($company)
    {
        $company->load('users');
        $ninjaAccount = $this->getNinjaAccount();
        $ninjaUser = $ninjaAccount->getPrimaryUser();
        $relation = Relation::whereCompanyId($ninjaAccount->id)
                    ->wherePublicId($company->id)
                    ->first();
        $relationExists = $relation ? true : false;

        if (!$relation) {
            $relation = new Relation();
            $relation->public_id = $company->id;
            $relation->company_id = $ninjaAccount->id;
            $relation->user_id = $ninjaUser->id;
            $relation->currency_id = 1;
        }

        foreach (['name', 'address1', 'address2', 'city', 'state', 'postal_code', 'country_id', 'work_phone', 'language_id', 'vat_number'] as $field) {
            $relation->$field = $company->$field;
        }

        $relation->save();

        if ($relationExists) {
            $contact = $relation->getPrimaryContact();
        } else {
            $contact = new Contact();
            $contact->user_id = $ninjaUser->id;
            $contact->company_id = $ninjaAccount->id;
            $contact->public_id = $company->id;
            $contact->is_primary = true;
        }

        $user = $company->getPrimaryUser();
        foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
            $contact->$field = $user->$field;
        }

        $relation->contacts()->save($contact);

        return $relation;
    }

    public function findByKey($key)
    {
        $company = Company::whereAccountKey($key)
                    ->with('relations.invoices.invoice_items', 'relations.contacts')
                    ->firstOrFail();

        return $company;
    }

    public function unlinkUserFromOauth($user)
    {
        $user->oauth_provider_id = null;
        $user->oauth_user_id = null;
        $user->save();
    }

    public function updateUserFromOauth($user, $firstName, $lastName, $email, $providerId, $oauthUserId)
    {
        if (!$user->registered) {
            $rules = ['email' => 'email|required|unique:users,email,'.$user->id.',id'];
            $validator = Validator::make(['email' => $email], $rules);
            if ($validator->fails()) {
                $messages = $validator->messages();
                return $messages->first('email');
            }

            $user->email = $email;
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->registered = true;

            $user->company->startTrial(PLAN_PRO);
        }

        $user->oauth_provider_id = $providerId;
        $user->oauth_user_id = $oauthUserId;
        $user->save();

        return true;
    }

    public function registerNinjaUser($user)
    {
        if ($user->email == TEST_USERNAME) {
            return false;
        }

        $url = (Utils::isNinjaDev() ? SITE_URL : NINJA_APP_URL) . '/signup/register';
        $data = '';
        $fields = [
            'first_name' => urlencode($user->first_name),
            'last_name' => urlencode($user->last_name),
            'email' => urlencode($user->email),
        ];

        foreach ($fields as $key => $value) {
            $data .= $key.'='.$value.'&';
        }
        rtrim($data, '&');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function findUserByOauth($providerId, $oauthUserId)
    {
        return User::where('oauth_user_id', $oauthUserId)
                    ->where('oauth_provider_id', $providerId)
                    ->first();
    }

    public function findUsers($user, $with = null)
    {
        $companies = $this->findUserAccounts($user->id);

        if ($companies) {
            return $this->getUserAccounts($companies, $with);
        } else {
            return [$user];
        }
    }

    public function findUserAccounts($userId1, $userId2 = false)
    {
        if (!Schema::hasTable('user_companies')) {
            return false;
        }

        $query = UserAccount::where('user_id1', '=', $userId1)
                                ->orWhere('user_id2', '=', $userId1)
                                ->orWhere('user_id3', '=', $userId1)
                                ->orWhere('user_id4', '=', $userId1)
                                ->orWhere('user_id5', '=', $userId1);

        if ($userId2) {
            $query->orWhere('user_id1', '=', $userId2)
                    ->orWhere('user_id2', '=', $userId2)
                    ->orWhere('user_id3', '=', $userId2)
                    ->orWhere('user_id4', '=', $userId2)
                    ->orWhere('user_id5', '=', $userId2);
        }

        return $query->first(['id', 'user_id1', 'user_id2', 'user_id3', 'user_id4', 'user_id5']);
    }

    public function getUserAccounts($record, $with = null)
    {
        if (!$record) {
            return false;
        }

        $userIds = [];
        for ($i=1; $i<=5; $i++) {
            $field = "user_id$i";
            if ($record->$field) {
                $userIds[] = $record->$field;
            }
        }

        $users = User::with('company')
                    ->whereIn('id', $userIds);

        if ($with) {
            $users->with($with);
        }

        return $users->get();
    }

    public function prepareUsersData($record)
    {
        if (!$record) {
            return false;
        }

        $users = $this->getUserAccounts($record);

        $data = [];
        foreach ($users as $user) {
            $item = new stdClass();
            $item->id = $record->id;
            $item->user_id = $user->id;
            $item->public_id = $user->public_id;
            $item->user_name = $user->getDisplayName();
            $item->company_id = $user->company->id;
            $item->acc_name = $user->company->getDisplayName();
            $item->logo_url = $user->company->hasLogo() ? $user->company->getLogoUrl() : null;
            $data[] = $item;
        }

        return $data;
    }

    public function loadAccounts($userId) {
        $record = self::findUserAccounts($userId);
        return self::prepareUsersData($record);
    }

    public function associateAccounts($userId1, $userId2) {

        $record = self::findUserAccounts($userId1, $userId2);

        if ($record) {
            foreach ([$userId1, $userId2] as $userId) {
                if (!$record->hasUserId($userId)) {
                    $record->setUserId($userId);
                }
            }
        } else {
            $record = new UserAccount();
            $record->user_id1 = $userId1;
            $record->user_id2 = $userId2;
        }

        $record->save();

        $users = $this->getUserAccounts($record);

        // Pick the primary user
        foreach ($users as $user) {
            if (!$user->public_id) {
                $useAsPrimary = false;
                if(empty($primaryUser)) {
                    $useAsPrimary = true;
                }

                $planDetails = $user->company->getPlanDetails(false, false);
                $planLevel = 0;

                if ($planDetails) {
                    $planLevel = 1;
                    if ($planDetails['plan'] == PLAN_ENTERPRISE) {
                        $planLevel = 2;
                    }

                    if (!$useAsPrimary && (
                        $planLevel > $primaryUserPlanLevel
                        || ($planLevel == $primaryUserPlanLevel && $planDetails['expires'] > $primaryUserPlanExpires)
                    )) {
                        $useAsPrimary = true;
                    }
                }

                if  ($useAsPrimary) {
                    $primaryUser = $user;
                    $primaryUserPlanLevel = $planLevel;
                    if ($planDetails) {
                        $primaryUserPlanExpires = $planDetails['expires'];
                    }
                }
            }
        }

        // Merge other corporations into the primary user's corporation
        if (!empty($primaryUser)) {
            foreach ($users as $user) {
                if ($user == $primaryUser || $user->public_id) {
                    continue;
                }

                if ($user->company->corporation_id != $primaryUser->company->corporation_id) {
                    foreach ($user->company->corporation->companies as $company) {
                        $company->corporation_id = $primaryUser->company->corporation_id;
                        $company->save();
                    }
                    $user->company->corporation->forceDelete();
                }
            }
        }

        return $users;
    }

    public function unlinkAccount($company) {
        foreach ($company->users as $user) {
            if ($userAccount = self::findUserAccounts($user->id)) {
                $userAccount->removeUserId($user->id);
                $userAccount->save();
            }
        }
    }

    public function unlinkUser($userAccountId, $userId) {
        $userAccount = UserAccount::whereId($userAccountId)->first();
        if ($userAccount->hasUserId($userId)) {
            $userAccount->removeUserId($userId);
            $userAccount->save();
        }

        $user = User::whereId($userId)->first();

        if (!$user->public_id && $user->company->corporation->companies->count() > 1) {
            $corporation = Corporation::create();
            $corporation->save();
            $user->company->corporation_id = $corporation->id;
            $user->company->save();
        }
    }

    public function findWithReminders()
    {
        return Company::whereRaw('enable_reminder1 = 1 OR enable_reminder2 = 1 OR enable_reminder3 = 1')->get();
    }

    public function getReferralCode()
    {
        do {
            $code = strtoupper(str_random(8));
            $match = User::whereReferralCode($code)
                        ->withTrashed()
                        ->first();
        } while ($match);

        return $code;
    }

    public function createTokens($user, $name)
    {
        $name = trim($name) ?: 'TOKEN';
        $users = $this->findUsers($user);

        foreach ($users as $user) {
            if ($token = AccountToken::whereUserId($user->id)->whereName($name)->first()) {
                continue;
            }

            $token = AccountToken::createNew($user);
            $token->name = $name;
            $token->token = str_random(RANDOM_KEY_LENGTH);
            $token->save();
        }
    }

    public function getUserAccountId($company)
    {
        $user = $company->users()->first();
        $userAccount = $this->findUserAccounts($user->id);

        return $userAccount ? $userAccount->id : false;
    }

    public function save($data, $company)
    {
        $company->fill($data);
        $company->save();
    }
}
