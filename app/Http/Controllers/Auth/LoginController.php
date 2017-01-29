<?php

namespace App\Http\Controllers\Auth;

use Auth;
use Event;
use Utils;
use Session;
use Illuminate\Http\Request;
use App\Models\User;
use App\Events\UserLoggedIn;
use App\Http\Controllers\Controller;
use App\Ninja\Repositories\CompanyRepository;
use App\Services\AuthService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Contracts\Auth\Guard;

//use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * @var AuthService
     */
    protected $authService;

    /**
     * @var CompanyRepository
     */
    protected $companyRepo;

    /**
     * Create a new authentication controller instance.
     *
     * @param CompanyRepository $repo
     * @param AuthService $authService
     * @internal param \Illuminate\Contracts\Auth\Guard $auth
     * @internal param \Illuminate\Contracts\Auth\Registrar $registrar
     */
    public function __construct(CompanyRepository $repo, AuthService $authService)
    {
        $this->companyRepo = $repo;
        $this->authService = $authService;
        $this->middleware('guest', ['except' => 'logout']);
    }

    public function postLogin(Request $request)
    {

        //dd( $this->getCredentials($request) );
        $authenticated = false;

        $this->validate($request, [
            $this->loginUsername() => 'required',
            'password' => 'required'
        ]);

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        //$throttles = $this->isUsingThrottlesLoginsTrait();


        /*if ($throttles && $this->hasTooManyLoginAttempts($request)) {
            return $this->sendLockoutResponse($request);
        }*/

        $credentials = $this->getCredentials($request);


        if (Auth::attempt($credentials, $request->has('remember'))) {
            $authenticated = true;
        }

        //$this->saveAttemptRecord($request, $authenticated);

        //, $throttles
        if ($authenticated) {
            return $this->handleUserWasAuthenticated($request);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        /*if ($throttles) {
            $this->incrementLoginAttempts($request);
        }*/

        return redirect($this->loginPath())
            ->withInput($request->only($this->loginUsername(), 'remember'))
            ->withErrors([
                $this->loginUsername() => $this->getFailedLoginMessage(),
            ]);
    }

    //v
    protected function handleUserWasAuthenticated(Request $request)
    {

        /*
            * Once this is ok then you have to store the following info in a table
            *   - Username
            *   - User IP address
            *   - Total Attempts so far          *
            */
        /*if ($throttles) {
            $this->clearLoginAttempts($request);
        }*/
        Event::fire(new UserLoggedIn()); 


        $users = $this->companyRepo->loadAccounts(Auth::user()->id);
        Session::put(SESSION_USERACCS, $users);



        if (method_exists($this, 'authenticated')) {
            return $this->authenticated($request, Auth::user());
        }

        return redirect()->intended($this->redirectPath());
    }


    /**
     * Store the login attempt in the database
     *
     * @param  Request $request
     * @param  Boolean $authenticated
     * @return void
     */
    private function saveAttemptRecord(Request $request, $authenticated)
    {

        $log = new ThrottleLog;
        $log->username = $this->getUserName($request);
        $log->attempts = $this->getLoginAttempts($request);
        $log->ip_address = ip2long($request->ip());
        $log->user_agent = $request->header('User-Agent') !== null ? $request->header('User-Agent') : '';
        $log->result = $authenticated ? 'Pass' : 'Fail';

        $log->save();

    }

    /**
     * Get the userName that is attempting to login
     *
     * @param  Request $request
     * @return string username
     */
    private function getUserName(Request $request)
    {
        return isset($request->only($this->loginUsername())[$this->loginUsername()]) ? $request->only($this->loginUsername())[$this->loginUsername()] : '';
    }


}