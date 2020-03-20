<?php

namespace App\Http\Controllers;
set_time_limit(0);
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Notifications\Notification;
use App\Http\Controllers\Controller;
use App\User;
use App\Role;
use App\Facility;
use App\SubCounty;
use App\County;
use App\Program;
use App\Round;
use App\SmsHandler;
use App\Designation;
use App\ImplementingPartner;

use DB;
use Hash;
use Auth;
use Mail;

//  Carbon - for use with dates
use Jenssegers\Date\Date as Carbon;
use Excel;
use App;
use File;
//  Notification
use App\Notifications\WelcomeNote;
use App\Notifications\AccountNote;

class UserController extends Controller
{

    public function manageUser()
    {
        return view('user.index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $error = ['error' => 'No results found, please try with different keywords.'];
        $users = User::whereNull('uid')->whereNull('sms_code')->latest()->paginate(100);

        $total_users =$users->total();
        $counties ='';
        $subcounties ='';
        $partners ='';

        if(Auth::user()->isCountyCoordinator())
        {
            $users = County::find(Auth::user()->ru()->tier)->users('User')->whereNull('uid')->whereNull('sms_code')->latest()->withTrashed()->paginate(100);
        }
        else if(Auth::user()->isSubCountyCoordinator())
        {
           $users = SubCounty::find(Auth::user()->ru()->tier)->users('User')->whereNull('uid')->whereNull('sms_code')->latest()->withTrashed()->paginate(100);
        }
        else if(Auth::user()->isFacilityInCharge())
        {
           $users = Facility::find(Auth::user()->ru()->tier)->users('User')->whereNull('uid')->whereNull('sms_code')->latest()->withTrashed()->paginate(100);
        }
        else if(Auth::user()->isPartner())
        {
           $users = ImplementingPartner::find(Auth::user()->ru()->tier)->users('User')->whereNull('uid')->latest()->withTrashed()->paginate(100);           
        }
        if($request->has('q')) 
        {
            $search = $request->get('q');
            $users = User::where('name', 'LIKE', "%{$search}%")->orWhere('email', 'LIKE', "%{$search}%")->whereNull('uid')->whereNull('sms_code')->latest()->withTrashed()->paginate(100);
            if(Auth::user()->isCountyCoordinator())
            {
                $users = County::find(Auth::user()->ru()->tier)->users('User')->where('users.name', 'LIKE', "%{$search}%")->whereNull('uid')->latest()->withTrashed()->paginate(100);
            }
            else if(Auth::user()->isSubCountyCoordinator())
            {
                $users = SubCounty::find(Auth::user()->ru()->tier)->users('User')->where('users.name', 'LIKE', "%{$search}%")->whereNull('uid')->latest()->withTrashed()->paginate(100);
            }
            else if(Auth::user()->isFacilityInCharge())
            {
               $users = Facility::find(Auth::user()->ru()->tier)->users('User')->where('users.name', 'LIKE', "%{$search}%")->whereNull('uid')->latest()->withTrashed()->paginate(100);
            }
            else if(Auth::user()->isPartner())
            {
               $users = ImplementingPartner::find(Auth::user()->ru()->tier)->users()->whereNotNull('sms_code')->latest()->withTrashed()->paginate(100);
            }
        }

        //filter users by region
        if($request->has('county')) 
        {            
            $users = County::find($request->get('county'))->users('User')->whereNull('uid')->latest()->withTrashed()->paginate(100);               
        }
         if($request->has('sub_county')) 
        {
            $users = SubCounty::find($request->get('sub_county'))->users('User')->whereNull('uid')->latest()->withTrashed()->paginate(100);

        }
        if($request->has('facility')) 
        {
            $users = Facility::find($request->get('facility'))->users('User')->whereNull('uid')->latest()->withTrashed()->paginate(100);

        }
        if($request->has('partner')) 
        {
            $users = ImplementingPartner::find($request->get('partner'))->users('User')->whereNull('uid')->latest()->withTrashed()->paginate(100);

        }

        foreach($users as $user)
        {
            !empty($user->ru())?$user->tier = $user->ru()->tier:$user->tier = '';
            !empty($user->ru())?$user->role = $user->ru()->role_id:$user->role = '';
	    !empty($user->ru())?$user->rl = Role::find($user->ru()->role_id)->name:$user->rl = '';
	    
 	    
	    $region = '';
            if ($user->tier>0) {

                if ($user->role ==3) {
                        $region = ImplementingPartner::find($user->tier)->name;
                   
                }
                elseif ($user->role ==4) {
                        $region = County::find($user->tier)->name;
                   
                }
                elseif ($user->role ==6) {
                        $region = Facility::find($user->tier)->name;
                   
                }
                elseif ($user->role ==7) {
                        $region = SubCounty::find($user->tier)->name;
                   
                }
                else{
                    $region = 'Not Specified';
                }
            }
            !empty($user->ru())?$user->region = $region:$user->region = 'Not Specified';
        }
        $response = [
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem()
            ],
            'data' => $users,
            'role' => Auth::user()->ru()->role_id,
            'tier' => Auth::user()->ru()->tier
        ];

        return $users->count() > 0 ? response()->json($response) : $error;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'first_name' => 'required',
            'last_name' => 'required',
            'gender' => 'required',
            'phone' => 'required|unique:users,phone',
            'email' => 'required',
            'role' => 'required',
            'username' => 'required|unique:users,username'
        ]);

        $request->merge(['password' => Hash::make(User::DEFAULT_PASSWORD)]);
        $request->merge(['name' => $request->first_name.' '.$request->middle_name.' '.$request->last_name]);
        $create = User::create($request->all());
        if($request->role)
        {
            $role = $request->role;
            $tier = NULL;
            $program_id = NULL;
            if($role == Role::idByName("Partner"))
            {
                $tier = $request->implementing_partner_id;
                // $tier = implode(", ", $request->implementing_partner_id);
            }
            else if($role == Role::idByName("County Coordinator"))
            {
                $tier = $request->county_id;
            }
            else if($role == Role::idByName("Sub-County Coordinator"))
            {
                $tier = $request->sub_id;
            }
            else if($role == Role::idByName("Participant"))
            {
                $tier = $request->facility_id;
                $program_id = $request->program_id;
            }
            else if($role == Role::idByName("Facility Incharge"))
            {
                $tier = $request->facility_id;
            }
            $ru = DB::table('role_user')->insert(["user_id" => $create->id, "role_id" => $role, "tier" => $tier, "program_id" => $program_id]);
            //  SMS and email notification
            $token = app('auth.password.broker')->createToken($create);
            $create->token = $token;
            $create->notify(new AccountNote($create));
            
            $message    = "Dear ".$create->name.", your PT system account has been created. Use the link sent to your email address to get started.";

            $smsHandler = new SmsHandler();
            $smsHandler->sendMessage($create->phone, $message);

            \Log::info("User ID {$create->id} {$create->name} {$create->phone} has been created. Corresponding SMS sent.");

        }
        return response()->json($create);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'first_name' => 'required',
            'last_name' => 'required',
            'gender' => 'required',
            'phone' => 'required',
            'email' => 'required',
            'username' => 'required'
        ]);

        $thisUser = User::find($id);
        \Log::info("User update -- performed by: ".Auth::user()->id);
        \Log::info("From: \n".json_encode($thisUser)."\nTo:\n".json_encode($request));

        $edit = $thisUser->update($request->all());
        if($request->role)
        {
            $role = $request->role;
            $tier = NULL;
            $program_id = NULL;
            if($role == Role::idByName("Partner"))
            {
                $tier = $request->implementing_partner_id;
                // $tier = implode(", ", $request->jimbo);
            }
            else if($role == Role::idByName("County Coordinator"))
            {
                $tier = $request->county_id;
            }
            else if($role == Role::idByName("Sub-County Coordinator"))
            {
                $tier = $request->sub_id;
            }
            else if($role == Role::idByName("Participant"))
            {
                $tier = $request->facility_id;
                $program_id = $request->program_id;
            }
            else if($role == Role::idByName("Facility Incharge"))
            {
                $tier = $request->facility_id;
            }
            $user = User::find($id);
            $user->detachAllRoles();
            $ru = DB::table('role_user')->insert(["user_id" => $id, "role_id" => $role, "tier" => $tier, "program_id" => $program_id]);
        }
        if($user)
        {
            //  send email and sms
            $token = app('auth.password.broker')->createToken($user);
            $user->token = $token;
            $user->notify(new WelcomeNote($user));
            
            $message    = "Dear ".$user->name.", Your HIV PT System account has been created. Your username is ".$user->username.". Use the link sent to your email to get started.";

            $smsHandler = new SmsHandler();
            $smsHandler->sendMessage($user->phone, $message);

            \Log::info("User ID {$user->id} {$user->name} {$user->phone} has been created. Corresponding SMS sent.");

        }
        return response()->json($edit);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::find($id);
        $message    = "Dear ".$user->name.", NPHL has disabled your account.";

        $smsHandler = new SmsHandler();
        $smsHandler->sendMessage($user->phone, $message);

        $user->delete();
        \Log::info("User ID $id {$user->name} deleted by User ID: ".Auth::user()->id.". Corresponding SMS sent.");

        return response()->json(['done']);
    }

    /**
     * enable soft deleted record.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function restore($id) 
    {
        $user = User::withTrashed()->find($id);
        $user->restore();
        $message    = "Dear ".$user->name.", NPHL has enabled your account.";

        $smsHandler = new SmsHandler();
        $smsHandler->sendMessage($user->phone, $message);

        \Log::info("User ID $id {$user->name} restored by User ID: ".Auth::user()->id.". Corresponding SMS sent.");

        return response()->json(['done']);
    }
    /**
     * Function to return list of tester-ranges.
     *
     */
    public function ranges()
    {
        $ranges = [
            User::ZERO_TO_TWO => '0 - 2',
            User::THREE_TO_FIVE => '3 - 5',
            User::SIX_TO_EIGHT => '6 - 8',
            User::NINE => '9'
        ];
        $categories = [];
        foreach($ranges as $key => $value)
        {
            $categories[] = ['id' => $key, 'value' => $value];
        }
        return $categories;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function participant(Request $request)
    {
        $error = ['error' => 'No results found, please try with different keywords.'];
        $participant = Role::idByName('Participant');
        $users = [];
        if($request->has('q')) 
        {
            $search = $request->get('q');
            $users = User::join('role_user', 'users.id', '=', 'role_user.user_id')->where('role_id', $participant)
                        ->where('name', 'LIKE', "%{$search}%")->orWhere('uid', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%")->latest()->paginate(5);
        }
        $response = [
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem()
            ],
            'data' => $users
        ];

        return $users->count() > 0 ? response()->json($response) : $error;
    }
    /**
     * Function for enrolling users to a round of testing
     *
     * @return \Illuminate\Http\Response
     */
    public function forEnrol(Request $request)
    {
        $error = ['error' => 'No results found, please try with different keywords.'];
        $role_id = Role::idByName('Participant');
        $ids = DB::table('role_user')->where('role_id', $role_id)->pluck('user_id');
        $usrs = User::whereIn('id', $ids)->whereNotNull('uid')->latest()->paginate(5);
        if($request->has('q')) 
        {
            $search = $request->get('q');
            $usrs = User::whereIn('id', $ids)->where('name', 'LIKE', "%{$search}%")->orWhere('uid', 'LIKE', "%{$search}%")->latest()->paginate(5);
        }
        if(count($usrs)>0)
        {
            foreach($usrs as $user)
            {
                //dd($user->ru());
                $user->facility = Facility::find($user->ru()->tier)->name;
                $user->program = Program::find($user->ru()->program_id)->name;
            }
        }
        $response = [
            'pagination' => [
                'total' => $usrs->total(),
                'per_page' => $usrs->perPage(),
                'current_page' => $usrs->currentPage(),
                'last_page' => $usrs->lastPage(),
                'from' => $usrs->firstItem(),
                'to' => $usrs->lastItem()
            ],
            'data' => $usrs
        ];

        return !empty($usrs) ? response()->json($response) : $error;
    }
    /**
     * Get enrolled user(s).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function enrolled($id)
    {
        $error = ['error' => 'No results found, please try with different keywords.'];
        $ids = Round::find($id)->enrolments->pluck('user_id')->toArray();
        $usrs = User::whereIn('id', $ids)->latest()->paginate(5);
        //dd($usrs);
        if(count($usrs)>0)
        {
            foreach($usrs as $enrol)
            {
                $facility = Facility::find($enrol->ru()->tier);
                $enrol->facility = $facility->name;
                $enrol->mfl = $facility->code;
                $enrol->program = Program::find($enrol->ru()->program_id)->name;
            }
        }
        $response = [
            'pagination' => [
                'total' => $usrs->total(),
                'per_page' => $usrs->perPage(),
                'current_page' => $usrs->currentPage(),
                'last_page' => $usrs->lastPage(),
                'from' => $usrs->firstItem(),
                'to' => $usrs->lastItem()
            ],
            'data' => $usrs
        ];
        return !empty($usrs) ? response()->json($response) : $error;
    }
    /**
     * Function to return list of sexes.
     *
     */
    public function sex()
    {
        $sexes = [
            User::MALE => 'Male',
            User::FEMALE => 'Female'
        ];
        $categories = [];
        foreach($sexes as $key => $value)
        {
            $categories[] = ['title' => $value, 'name' => $key];
        }
        return $categories;
    }
    /**
     * Function to return list of designations.
     *
     */
    public function designations()
    {
        $designations = Designation::pluck('name', 'id');
        $categories = [];
        foreach($designations as $key => $value)
        {
            $categories[] = ['title' => $value, 'name' => $key];
        }
        return $categories;
    }

    /**
     * Function to register new participants
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $now = Carbon::now('Africa/Nairobi');
        //  Prepare to save user details
        //  Check if user exists
        $userId = User::idByPhone($request->phone);
        if(!$userId)
        {
            $user = new User;
            $user->name = $request->name;
            $user->gender = $request->gender;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->address = $request->address;
            $user->designation = $request->designation;
            $user->username = $request->phone;
            $user->deleted_at = $now;
            $user->save();
            $userId = $user->id;
        }
        //  Prepare to save facility details
        $facilityId = Facility::idByCode($request->mfl_code);
        if(!$facilityId)
            $facilityId = Facility::idByName($request->facility);
        if($facilityId)
            $facility = Facility::find($facilityId);
        else
            $facility = new Facility;
        $facility->code = $request->mfl_code;
        $facility->name = $request->facility;
        $facility->in_charge = $request->in_charge;
        $facility->in_charge_phone = $request->in_charge_phone;
        $facility->in_charge_email = $request->in_charge_email;
        //  Get sub-county
        $sub_county = SubCounty::idByName($request->sub_county);
        if(!$sub_county)
        {
            $sb = new SubCounty;
            $sb->name = $request->sub_county;
            $sb->county_id = $request->county;
            $sb->save();
            $sub_county = $sb->id;
        }
        $facility->sub_county_id = $sub_county;
        $facility->save();
        $facilityId = $facility->id;
        //  Prepare to save role-user details
        $roleId = Role::idByName('Participant');
        DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $roleId, 'tier' => $facilityId, 'program_id' => $request->program]);
        
        // Generate code and store it in the database then send to participant
        $token = mt_rand(100000, 999999);
        $user->sms_code = $token;
        $user->save();
        $message    = "Your Verification Code is: ".$token;

        $smsHandler = new SmsHandler();
        $smsHandler->sendMessage($user->phone, $message);

        \Log::info("User ID {$user->id} {$user->name} {$user->phone} registered. Corresponding SMS with verification code ($token) sent.");

        //  Do Email verification for email address
        $user->email_verification_code = Str::random(60);
        $user->save();
        $user->notify(new SendVerificationCode($user));

        return response()->json(['phone' => $user->phone]);        
    }
    /**
     * Import the data in the worksheet
     *
     */
    public function importUserList(Request $request)
    {
        $exploded = explode(',', $request->list);
        $decoded = base64_decode($exploded[1]);
        if(str_contains($exploded[0], 'sheet'))
            $extension = 'xlsx';
        else
            $extension = 'xls';
        $fileName = uniqid().'.'.$extension;
        $county = County::find(1)->name;    // Remember to change this
        $folder = '/uploads/participants/';
        if(!is_dir(public_path().$folder))
            File::makeDirectory(public_path().$folder, 0777, true);
        file_put_contents(public_path().$folder.$fileName, $decoded);

        //  Handle the import
        //  Get the results
        //  Import a user provided file
        //  Convert file to csv
        $data = Excel::load('public/uploads/participants/'.$fileName, function($reader) {})->get();

        if(!empty($data) && $data->count())
        {
            foreach ($data->toArray() as $key => $value) 
            {
                if(!empty($value))
                {
                    $tname = NULL;
                    $tuid = NULL;
                    $tprogram = NULL;
                    $tphone = NULL;
                    $tfacility = NULL;
                    $temail = NULL;
                    foreach ($value as $mike => $ross) 
                    {
                        if(strcmp($mike, "tester_name") === 0)
                            $tname = $ross;
                        if(strcmp($mike, "bar_code") === 0)
                            $tuid = $ross;
                        if(strcmp($mike, "program") === 0)
                            $tprogram = $ross;
                        if(strcmp($mike, "testerphone") === 0)
                            $tphone = $ross;
                        if(strcmp($mike, "facility_name") === 0)
                            $tfacility = $ross;
                        if(strcmp($mike, "email") === 0)
                            $temail = $ross;
                    }
                    if(count($tphone) != 0)
                    {
                        $tphone = ltrim($tphone, '0');
                        $tphone = "+254".$tphone;
                        $tphone = trim($tphone);
                    }
                    if(!$tuid)
                        $tuid = uniqid();
                    $facility_id = Facility::idByName(trim($tfacility));
                    $program_id = Program::idByTitle(trim($tprogram));
                    $role_id = Role::idByName('Participant');
                    //  Prepare to save participant details
                    $tester = new User;
                    $tester->password = Hash::make(User::DEFAULT_PASSWORD);

                    $tester->name = $tname;
                    $tester->gender = User::MALE;
                    $tester->email = $temail;
                    $tester->phone = $tphone;
                    $tester->username = $tuid;
                    $tester->uid = $tuid;
                    $tester->save();
                    //  prepare to save role-user
                    $ru = DB::table('role_user')->where('user_id', $tester->id)->where('role_id', $role_id)->count();
                    if($ru == 0)
                    {
                        DB::table('role_user')->insert(["user_id" => $tester->id, "role_id" => $role_id, "tier" => $facility_id, "program_id" => $program_id]);
                    }
                }
            }
        }
    }
    /**
     * Batch registration
     *
     */
    public function batchRegistration(Request $request)
    {
        $exploded = explode(',', $request->excel);
        // dd($exploded);
        $decoded = base64_decode($exploded[1]);
        if(str_contains($exploded[0], 'sheet'))
            $extension = 'xlsx';
        else
            $extension = 'xls';
        $fileName = uniqid().'.'.$extension;
        $county = 0;
        if(Auth::user()->isCountyCoordinator())
        {
            $county = County::find(Auth::user()->ru()->tier)->name;
            $folder = '/batch/registration/'.$county.'/';
        }
        else
            $folder = '/batch/registration/nphls/';
        if(!is_dir(public_path().$folder))
            File::makeDirectory(public_path().$folder, 0777, true);
        file_put_contents(public_path().$folder.$fileName, $decoded);
        //  Handle the import
        //  Get the results
        //  Import a user provided file
        //  Convert file to csv
        if(Auth::user()->isCountyCoordinator())
            $data = Excel::load('public/batch/registration/'.$county.'/'.$fileName, function($reader) {$reader->ignoreEmpty();})->get();
        else
            $data = Excel::load('public/batch/registration/nphls/'.$fileName, function($reader) {$reader->ignoreEmpty();})->get();
        if(!empty($data) && $data->count())
        {

            foreach ($data->toArray() as $key => $value) 
            {
                foreach($value as $harvey => $specter)
                {
                    if(!empty($specter))
                    {
                        $county = NULL;
                        $sub_county = NULL;
                        $facility = NULL;
                        $mfl = NULL;
                        $tfname = NULL;
                        $tsname = NULL;
                        $toname = NULL;
                        $tgender = NULL;
                        $tphone = NULL;
                        $temail = NULL;
                        $taddress = NULL;
                        $tdes = NULL;
                        $tprog = NULL;
                        $incharge = NULL;
                        $iphone = NULL;
                        $iemail = NULL;
                        foreach ($specter as $mike => $ross) 
                        {
                            if(strcmp($mike, "county") === 0)
                                $county = $ross;
                            if(strcmp($mike, "sub_county") === 0)
                                $sub_county = $ross;
                            if(strcmp($mike, "facility") === 0)
                                $facility = $ross;
                            if(strcmp($mike, "mfl_code") === 0)
                                $mfl = $ross;
                            if(strcmp($mike, "tester_first_name") === 0)
                                $tfname = $ross;
                            if(strcmp($mike, "tester_surname") === 0)
                                $tsname = $ross;
                            if(strcmp($mike, "tester_other_name") === 0)
                                $toname = $ross;
                            if(strcmp($mike, "gender") === 0)
                                $tgender = $ross;
                            if(strcmp($mike, "tester_mobile_number") === 0)
                                $tphone = $ross;
                            if(strcmp($mike, "tester_email") === 0)
                                $temail = $ross;
                            if(strcmp($mike, "tester_address") === 0)
                                $taddress = $ross;
                            if(strcmp($mike, "designation") === 0)
                                $tdes = $ross;
                            if(strcmp($mike, "program") === 0)
                                $tprog = $ross;
                            if(strcmp($mike, "in_charge") === 0)
                                $incharge = $ross;
                            if(strcmp($mike, "in_charge_email") === 0)
                                $iemail = $ross;
                            if(strcmp($mike, "in_charge_phone") === 0)
                                $iphone = $ross;
                        }
                        //  clean phone
                        if(!empty($tphone))
                        {
                            $tphone = ltrim($tphone, '0');
                            $tphone = "+254".$tphone;
                            $tphone = trim($tphone);
                        }
                        //  process gender
                        if(strcmp($tgender, "Male") === 0)
                            $tgender = User::MALE;
                        else
                            $tgender = User::FEMALE;
                        //  process designation
                        if(strcmp($tdes, "Nurse") === 0)
                            $tdes = User::NURSE;
                        else if(strcmp($tdes, "Lab Tech.") === 0)
                            $tdes = User::LABTECH;
                        else if(strcmp($tdes, "Counsellor") === 0)
                            $tdes = User::COUNSELLOR;
                        else if(strcmp($tdes, "RCO") === 0)
                            $tdes = User::RCO;

                        //  process user details only if the user exists
                        if($tfname)
                        {
                            $userId = User::idByPhone($tphone);
                            if(!$userId)
                            {
                                $user = new User;
                                $user->name = $tsname." ".$tfname." ".$toname;
                                $user->gender = $tgender;
                                $user->email = $temail;
                                $user->phone = $tphone;
                                $user->address = $taddress;
                                $user->username = uniqid();
                                $user->save();
                                $user->username = $user->id;
                                $user->uid = $user->id;
                                $user->phone_verified = 1;
                                $user->email_verified = 1;
                                $user->password = Hash::make(User::DEFAULT_PASSWORD);
                                $user->save();
                                $userId = $user->id;
                            }
                            //  Prepare to save facility details
                            $facilityId = Facility::idByCode($mfl);
                            if(!$facilityId)
                                $facilityId = Facility::idByName(trim($facility));
                            if($facilityId)
                                $fc = Facility::find($facilityId);
                            else
                                $fc = new Facility;
                            $fc->code = $mfl;
                            $fc->name = $facility;
                            $fc->in_charge = $incharge;
                            $fc->in_charge_phone = $iphone;
                            $fc->in_charge_email = $iemail;
                            //  Get sub-county
                            $sub_county_id = SubCounty::idByName($sub_county);
                            if(!$sub_county_id)
                            {
                                $sb = new SubCounty;
                                $sb->name = $sub_county;
                                $sb->county_id = County::idByName($county);
                                $sb->save();
                                $sub_county_id = $sb->id;
                            }
                            $fc->sub_county_id = $sub_county_id;
                            $fc->save();
                            $facilityId = $fc->id;
                            //  Prepare to save role-user details
                            $roleId = Role::idByName('Participant');
                            DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $roleId, 'tier' => $facilityId, 'program_id' => Program::idByTitle($tprog), "designation" => $tdes]);
                            //  send email and sms
                            $token = app('auth.password.broker')->createToken($user);
                            $user->token = $token;
                            $user->notify(new WelcomeNote($user));
                            

                            $message    = "Dear ".$user->name.", NPHL has approved your request to participate in PT. Your tester ID is ".$user->uid.". Use the link sent to your email to get started.";

                            $smsHandler = new SmsHandler();
                            $smsHandler->sendMessage($user->phone, $message);

                            \Log::info("User ID {$user->id} {$user->name} {$user->phone} assigned a tester ID by ".Auth::user()->id.". Corresponding SMS with tester ID ({$user->uid}}) sent.");

                        }
                    }
                }
            }
        }
    }
    /**
     * Check for user phone verification code
     *
     * @param  array  $data
     * @return User
     */
    public function phoneVerification(Request $request)
    {
        $token = $request->code;
        // dd($token);
        $check = User::where("sms_code", $token)->withTrashed()->first();
        
        if(!is_null($check)){
            $user = User::withTrashed()->find($check->id);

            if($user->phone_verified == 1){
                return response()->json(["info" => "Your phone number is already verified."]);
            }

            $user->phone_verified = 1;
            $user->save();

            return response()->json(["success" => "Phone number successfully verified. Your credentials will be sent to you shortly."]);
        }
        return response()->json(["warning" => "Your token is invalid."]);
    }
    /**
     * Check for user Activation Code
     *
     * @param  array  $data
     * @return User
     */
    public function emailVerification($token)
    {
        $check = User::where('email_verification_code', $token)->withTrashed()->first();

        if(!is_null($check)){
            $user = User::withTrashed()->find($check->id);

            if($user->email_verified == 1){
                return redirect()->to('verified')
                    ->with('success', "Your email is already verified.");                
            }

            $user->email_verified = 1;
            $user->save();

            return redirect()->to('verified')
                ->with('success', "Email successfully verified. You will be notified when your request is approved. Click on the below button to get to the homepage.");
        }
        return redirect()->to('verified')
                ->with('warning', "Your token is invalid.");
    }
    
    /**
     * Get user's role ID, tier ID and active round ID
     *
     * @return array 
     */
    public function getRole(){

        $activeRound = 0;

        //Get active/current round
        try {
            
            $currentRound = Round::where('end_date', '>', Carbon::today())
                        ->where('start_date', '<', Carbon::today())->where('status', '=', 0)->first();

            if($currentRound){
                $activeRound = $currentRound->name;
            }
        } catch (\Exception $e) {
            \Log::info($e);
        }

        return ["role_id" => Auth::user()->ru()->role_id, "tier" => Auth::user()->ru()->tier, "active_round" => $activeRound];
    }

    public function assign_uid(){

        $response = '';
        $users = User::select('users.id')
                    ->where('uid', NULL)
                    ->where('users.deleted_at', NULL)
                    ->join('role_user', 'users.id', '=', 'role_user.user_id')
                    ->where('role_id', 2)->get();
         if ($users) {
        
             foreach ($users as $key => $user) {
                 $response = '';
                 $max = DB::table('users')->max('uid');
                 $m = $max+1;
                 if ($user->uid==null ||$user->uid==''){
                     $user->uid = $m; //pick sequential unique ids
                     $user->username = $m;
                 }

                 $user->deleted_at = null;
                 $user->status = '';
                 $user->save();
                 $response = 'done';
             }
         }else {
                 $response = 'no more';

         }
        return response()->json($response);
    }
    
    public function all_users(Request $request){
        $users = User::latest()->withTrashed()->paginate(100);
        if($request->has('q')) 
        {
            $search = $request->get('q');
            $users = User::where('name', 'LIKE', "%{$search}%")->orWhere('username', 'LIKE', "%{$search}%")->where('phone', 'LIKE', "%{$search}%")->latest()->paginate(100);
            
        }
        foreach($users as $user)
        {
            !empty($user->ru())?$user->tier = $user->ru()->tier:$user->tier = '';
            !empty($user->ru())?$user->role = $user->ru()->role_id:$user->role = '';
            !empty($user->ru())?$user->rl = Role::find($user->ru()->role_id)->name:$user->rl = '';
            
            
            $region = '';
            if ($user->tier>0) {
                if ($user->role ==2) {
                        $region = Facility::find($user->tier)->name;
                   
                }
                elseif ($user->role ==3) {
                        $region = ImplementingPartner::find($user->tier)->name;
                   
                }
                elseif ($user->role ==4) {
                        $region = County::find($user->tier)->name;
                   
                }
                elseif ($user->role ==6) {
                        $region = Facility::find($user->tier)->name;
                   
                }
                elseif ($user->role ==7) {
                        $region = SubCounty::find($user->tier)->name;
                   
                }
                else{
                    $region = 'Not Specified';
                }
            }
            !empty($user->ru())?$user->region = $region:$user->region = 'Not Specified';
        }
    }
}
$excel = App::make('excel');
