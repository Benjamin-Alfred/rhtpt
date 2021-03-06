<?php

namespace App\Http\Controllers\Auth;

use App\Notifications\SendVerificationCode;
use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Notifications\Notification;

use App\Role;
use App\Facility;
use App\SubCounty;
use App\County;
use App\Program;
use App\Round;
use App\SmsHandler;

use Mail;
use DB;
use Hash;
use Auth;
use Jenssegers\Date\Date as Carbon;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'verification_code' => Str::random(60),
            'status' => 0
        ]);
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $usr = NULL;
        $validator = $this->validate($request, [
//            'email' => 'required|unique:users,email',
            'phone' => 'required|unique:users,phone',
        ]);
        $now = Carbon::now('Africa/Nairobi');
        //  Prepare to save user details
        //  Check if user exists
     //   $userId = User::idByEmail($request->email);
       // if(!$userId)
         //   $userId = User::idByUsername($request->username);
        //if(!$userId)
        //{
            $user = new User;
            $user->name = $request->surname." ".$request->fname." ".$request->oname;
            $user->first_name = $request->fname;
            $user->middle_name = $request->oname;
            $user->last_name = $request->surname;
            $user->gender = $request->gender;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->address = $request->address;
            $user->username = $request->phone;
            $user->password = Hash::make(User::DEFAULT_PASSWORD);
            $user->deleted_at = $now;
            $user->save();
            $userId = $user->id;
       // }
        //  Prepare to save facility details
        $facilityId = Facility::idByCode($request->mfl_code);
        
	//  Prepare to save role-user details
        $roleId = Role::idByName('Participant');
        DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $roleId, 'tier' => $facilityId, 'program_id' => $request->program, 'designation' => $request->designation]);
        /*
        *  Do SMS Verification for phone number
        */
        //  Bulk-sms settings
        $token = mt_rand(100000, 999999);
        $user->sms_code = $token;
        $user->save();
        $message    = "Your Verification Code is: ".$token;
        try 
        {
            $smsHandler = new SmsHandler();
            $smsHandler->sendMessage($user->phone, $message);
        }
        catch ( AfricasTalkingGatewayException $e )
        {
            DB::table('role_user')->where('user_id', $user->id)->forceDelete();
            $user->forceDelete();
            abort(500, 'Encountered an error while sending verification code. Please try again later.');
        }
        
        try
        {
            //  Do Email verification for email address
            $user->email_verification_code = Str::random(60);
            $user->save();

            event(new Registered($usr = $user));
        }
        catch(Exception $e)
        {
            DB::table('role_user')->where('user_id', $user->id)->forceDelete();
            $user->forceDelete();
            abort(500, 'Encountered an error while sending verification code. Please try again later.');
        }

        return $this->registered($request, $user)
            ?: redirect('/2fa');
    }

    /**
     * The user has been registered.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function registered(Request $request, $user)
    {
        $user->notify(new SendVerificationCode($user));
    }

    public function verify($code)
    {
        $user = User::whereEmailVerificationCode($code)->first();

        if(!$user)
        {
            return redirect('/login')->with('error', '<strong>Invalid Code</strong>: Your verification code has been expired or invalid. <a href="#">Resend</a> verification code to my email.');
        }

        $user->status = 1;

        $user->verification_code = null;

        if($user->save())
        {
            return redirect('/login')->with('message', 'Email Verification Successful. Please login using your credentials.');
        }

    }

    public function resend($request)
    {
        if(strlen($request) < 10 || strlen($request) > 10)
            return response()->json(["error" => "Enter a valid phone number."]);

        $phone = ltrim($request, '0');
        $user = User::withTrashed()->where('phone', 'LIKE', '%'.$phone.'%')->first();
        if(!$user)
            return response()->json(["error" => "Phone number not found."]);

        $token = mt_rand(100000, 999999);
        $user->sms_code = $token;
        $user->save();
        $message    = "Your Verification Code is: ".$token;

        $smsHandler = new SmsHandler();
        $SMSResponseMessage = $smsHandler->sendMessage($user->phone, $message);

        if($SMSResponseMessage === false){
            return response()->json(["error" => "'Encountered an error while sending verification code. Please try again later."], 500);
        }
        
        try
        {
            //  Do Email verification for email address
            $user->email_verification_code = Str::random(60);
            $user->save();

            event(new Registered($usr = $user));
        }
        catch(\Exception $e)
        {
            return response()->json(["error" => "'Encountered an error while sending verification code. Please try again later."]);
        }
    }
    /**
     * Redirect user for SMS verification
     *
     * @param  array  $data
     * @return User
     */
    protected function twoFa()
    {
        return view('auth.2fa');
    }
}
