<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use Response;
use Cache;
use Socialite;
use Exception;
use App\Services\AuthService;
use App\Models\Company;
use App\Ninja\Repositories\CompanyRepository;
use Illuminate\Http\Request;
use App\Ninja\Transformers\AccountTransformer;
use App\Ninja\Transformers\UserAccountTransformer;
use App\Events\UserSignedUp;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateAccountRequest;

class AccountApiController extends BaseAPIController
{
    protected $companyRepo;

    public function __construct(CompanyRepository $companyRepo)
    {
        parent::__construct();

        $this->companyRepo = $companyRepo;
    }

    public function ping(Request $request)
    {
        $headers = Utils::getApiHeaders();

        if(hash_equals(env(API_SECRET),$request->api_secret))
            return Response::make(RESULT_SUCCESS, 200, $headers);
        else
            return $this->errorResponse(['message'=>'API Secret does not match .env variable'], 400);
    }

    public function register(RegisterRequest $request)
    {

        $company = $this->companyRepo->create($request->first_name, $request->last_name, $request->email, $request->password);
        $user = $company->users()->first();

        Auth::login($user, true);
        event(new UserSignedUp());

        return $this->processLogin($request);
    }

    public function login(Request $request)
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return $this->processLogin($request);
        } else {
            sleep(ERROR_DELAY);
            return $this->errorResponse(['message'=>'Invalid credentials'],401);
        }
    }

    private function processLogin(Request $request)
    {
        // Create a new token only if one does not already exist
        $user = Auth::user();
        $this->companyRepo->createTokens($user, $request->token_name);

        $users = $this->companyRepo->findUsers($user, 'company.acc_tokens');
        $transformer = new UserAccountTransformer($user->company, $request->serializer, $request->token_name);
        $data = $this->createCollection($users, $transformer, 'user_account');

        return $this->response($data);
    }

    public function show(Request $request)
    {
        $company = Auth::user()->company;
        $updatedAt = $request->updated_at ? date('Y-m-d H:i:s', $request->updated_at) : false;

        $transformer = new AccountTransformer(null, $request->serializer);
        $company->load(array_merge($transformer->getDefaultIncludes(), ['projects.client']));
        $company = $this->createItem($company, $transformer, 'company');

        return $this->response($company);
    }

    public function getStaticData()
    {
        $data = [];

        $cachedTables = unserialize(CACHED_TABLES);
        foreach ($cachedTables as $name => $class) {
            $data[$name] = Cache::get($name);
        }

        return $this->response($data);
    }

    public function getUserAccounts(Request $request)
    {
        return $this->processLogin($request);
    }

    public function update(UpdateAccountRequest $request)
    {
        $company = Auth::user()->company;
        $this->companyRepo->save($request->input(), $company);

        $transformer = new AccountTransformer(null, $request->serializer);
        $company = $this->createItem($company, $transformer, 'company');

        return $this->response($company);
    }

    public function addDeviceToken(Request $request)
    {
        $company = Auth::user()->company;

        //scan if this user has a token already registered (tokens can change, so we need to use the users email as key)
        $devices = json_decode($company->devices,TRUE);


            for($x=0; $x<count($devices); $x++)
            {
                if ($devices[$x]['email'] == Auth::user()->username) {
                    $devices[$x]['token'] = $request->token; //update
                    $company->devices = json_encode($devices);
                    $company->save();
                    $devices[$x]['acc_key'] = $company->acc_key;

                    return $this->response($devices[$x]);
                }
            }

        //User does not have a device, create new record

        $newDevice = [
            'token' => $request->token,
            'email' => $request->email,
            'device' => $request->device,
            'acc_key' => $company->acc_key,
            'notify_sent' => TRUE,
            'notify_viewed' => TRUE,
            'notify_approved' => TRUE,
            'notify_paid' => TRUE,
        ];

        $devices[] = $newDevice;
        $company->devices = json_encode($devices);
        $company->save();

        return $this->response($newDevice);

    }

    public function updatePushNotifications(Request $request)
    {
        $company = Auth::user()->company;

        $devices = json_decode($company->devices, TRUE);

        if(count($devices) < 1)
            return $this->errorResponse(['message'=>'No registered devices.'], 400);

        for($x=0; $x<count($devices); $x++)
        {
            if($devices[$x]['email'] == Auth::user()->username)
            {

                $newDevice = [
                    'token' => $devices[$x]['token'],
                    'email' => $devices[$x]['email'],
                    'device' => $devices[$x]['device'],
                    'acc_key' => $company->acc_key,
                    'notify_sent' => $request->notify_sent,
                    'notify_viewed' => $request->notify_viewed,
                    'notify_approved' => $request->notify_approved,
                    'notify_paid' => $request->notify_paid,
                ];

                $devices[$x] = $newDevice;
                $company->devices = json_encode($devices);
                $company->save();

                return $this->response($newDevice);
            }
        }

    }

    public function oauthLogin(Request $request)
    {
        $user = false;
        $token = $request->input('token');
        $provider = $request->input('provider');

        try {
            $user = Socialite::driver($provider)->stateless()->userFromToken($token);
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()], 401);
        }

        if ($user) {
            $providerId = AuthService::getProviderId($provider);
            $user = $this->companyRepo->findUserByOauth($providerId, $user->id);
        }

        if ($user) {
            Auth::login($user);
            return $this->processLogin($request);
        } else {
            sleep(ERROR_DELAY);
            return $this->errorResponse(['message' => 'Invalid credentials'], 401);
        }
    }
}
