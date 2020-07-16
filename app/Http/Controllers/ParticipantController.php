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
use App\ImplementingPartner;
use App\Program;
use App\Round;
use App\SmsHandler;
use App\Enrol;

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
use App\Notifications\RegretNote;
class ParticipantController extends Controller
{

    public function manageParticipant()
    {
        return view('participant.index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $error = ['error' => 'No results found, please try with different keywords.'];
        $active_users = 0;
        $inactive_users = 0;
        $users_without_mfl = 0;
        $ITEMS_PER_PAGE = 100;

        if(Auth::user()->isSuperAdministrator()){
            $users = User::leftJoin('role_user', 'users.id', '=', 'role_user.user_id')->whereNotNull('uid');
        
            //user statistics
            $users_stats = User::leftJoin('role_user', 'users.id', '=', 'role_user.user_id')->whereNotNull('uid')->get();
        }

        if(Auth::user()->isCountyCoordinator())
        {
            $users = County::find(Auth::user()->ru()->tier)->users();
            
            //user statistics
            $users_stats = County::find(Auth::user()->ru()->tier)->users();
        }
        
        if(Auth::user()->isSubCountyCoordinator())
        {
           $users = SubCounty::find(Auth::user()->ru()->tier)->users();
           //user statistics
            $users_stats = SubCounty::find(Auth::user()->ru()->tier)->users();
        }
        
        if(Auth::user()->isPartner())
        {
           $users = ImplementingPartner::find(Auth::user()->implementing_partner_id)->users();
           //user statistics
            $users_stats = ImplementingPartner::find(Auth::user()->implementing_partner_id)->users();
        }
        
        if(Auth::user()->isFacilityInCharge())
        {
           $users = Facility::find(Auth::user()->ru()->tier)->users();
           //user statistics
            $users_stats = Facility::find(Auth::user()->ru()->tier)->users();
        }

        foreach ($users_stats as  $user_stat) {
            if ($user_stat->deleted_at == NULL) {
                $active_users = $active_users + 1;
            }
            else{
                $inactive_users = $inactive_users + 1;
            }
        }

        if($request->has('filter')) 
        {
            $users = $users->whereNotNull('sms_code');
        }

        //search users by user details
        if($request->has('q')) 
        {
            $search = $request->get('q');

            $users = $users->where(
                function($query) use ($search){
                    $query->where('users.name', 'LIKE', "%{$search}%");
                    $query->orWhere('first_name', 'LIKE', "%{$search}%");
                    $query->orWhere('middle_name', 'LIKE', "%{$search}%");
                    $query->orWhere('last_name', 'LIKE', "%{$search}%");
                    $query->orWhere('phone', 'LIKE', "%{$search}%");
                    $query->orWhere('email', 'LIKE', "%{$search}%");
                    $query->orWhere('uid', 'LIKE', "%{$search}%");
                });
        }

        //filter users by region
        $PARTICIPANT_ROLE_ID = Role::idByName('Participant');
        if($request->has('facility')) 
        {
            $users = $users->where('role_id', $PARTICIPANT_ROLE_ID)->where('tier', '=', $request->get('facility'));
        }
        else if($request->has('sub_county')) 
        {
            $facilities = SubCounty::find($request->get('sub_county'))->facilities()->pluck('id')->toArray();
            $users = $users->where('role_id', $PARTICIPANT_ROLE_ID)->whereIn('tier', $facilities);
        }
        else if($request->has('county')) 
        {
            $facilities = County::find($request->get('county'))->facilities()->pluck('id')->toArray();
            $users = $users->where('role_id', $PARTICIPANT_ROLE_ID)->whereIn('tier', $facilities);
        }

        $all_users = $users->distinct()->latest()->withTrashed()->paginate($ITEMS_PER_PAGE);
        if($request->has('no_mfl')) 
        {
            $hanging_users = User::select('users.*')->join('role_user', 'users.id', '=', 'role_user.user_id')->where('role_id', '2')->where('tier', null);

            if($request->has('q')) 
            {
                $search = $request->get('q');

                $hanging_users = $hanging_users->where(
                    function($query) use ($search){
                        $query->where('users.name', 'LIKE', "%{$search}%");
                        $query->orWhere('first_name', 'LIKE', "%{$search}%");
                        $query->orWhere('middle_name', 'LIKE', "%{$search}%");
                        $query->orWhere('last_name', 'LIKE', "%{$search}%");
                        $query->orWhere('phone', 'LIKE', "%{$search}%");
                        $query->orWhere('email', 'LIKE', "%{$search}%");
                        $query->orWhere('uid', 'LIKE', "%{$search}%");
                    });
            }

            foreach ($hanging_users->latest()->withTrashed()->paginate($ITEMS_PER_PAGE) as $user) {
                $all_users->push($user);
            }
        }

        foreach($all_users as $user){
          
            if(!empty($user->ru()->tier))
            {
                $facility = Facility::find($user->ru()->tier);
                $user->facility = $user->ru()->tier;
                try{
                    $user->sub_county = $facility->subCounty->id;
                    $user->county = $facility->subCounty->county->id;

                    $user->mfl = $facility->code;
                    $user->fac = $facility->name;

                    $user->sub = $facility->subCounty->name;
                    $user->kaunti = $facility->subCounty->county->name;
                }catch(\Exception $exp){
                    \Log::error("Facility information not found!");
                    \Log::error($user);
                    \Log::error($exp->getMessage());
                }
            }else{
                $user->facility = '';
            }

            try{
                $user->gndr = $user->maleOrFemale((int)$user->gender);
                $user->program = $user->ru()->program_id;
                $user->prog = Program::find($user->ru()->program_id)->name;
            }catch(\Exception $exp){
                $user->program = '';
                \Log::error("User has no program.");
                \Log::error($user);
                \Log::error($exp->getMessage());
            }

            try{
                $user->des = $user->designation($user->ru()->designation);
                $user->designation = $user->ru()->designation;
            }catch(\Exception $exp){
                \Log::error("User has no designation.");
                \Log::error($user);
                \Log::error($exp->getMessage());
            }

            !empty($user->ru())?$user->role = $user->ru()->role_id:$user->role = '';
            !empty($user->ru())?$user->rl = Role::find($user->ru()->role_id)->name:$user->rl = '';

        }        
        $response = [
            'pagination' => [
                'total' => $all_users->total(),
                'per_page' => $all_users->perPage(),
                'current_page' => $all_users->currentPage(),
                'last_page' => $all_users->lastPage(),
                'from' => $all_users->firstItem(),
                'to' => $all_users->lastItem()
            ],
            'data' => $all_users,
            'role' => Auth::user()->ru()->role_id,
            'tier' => Auth::user()->ru()->tier,
            'total_users' => $all_users->total(),
            'active_users' => $active_users,
            'inactive_users' => $inactive_users,
            'users_without_mfl' => $users_without_mfl,
        ];

        return $all_users->count() > 0 ? response()->json($response) : $error;
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
            'phone' => 'required',
            'email' => 'required',
            'username' => 'required'
        ]);
        $request->merge(['password' => Hash::make(User::DEFAULT_PASSWORD)]);
        $create = User::create($request->all());
        if($request->role)
        {
            $role = $request->role;
            $tier = NULL;
            $program_id = NULL;
            if($role == Role::idByName("Partner"))
            {
                $tier = implode(", ", $request->jimbo);
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
        }
        \Log::info("New user created by: USER ID: ".Auth::user()->id);
        \Log::info($create);
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
        $user = User::find($id);
        \Log::info("User updated by: USER ID: ".Auth::user()->id);
        \Log::info("Previous details: ");
        \Log::info($user);

        $user->first_name = $request->first_name;
        $user->middle_name = $request->middle_name;
        $user->last_name = $request->last_name;
        $user->name = trim($request->first_name) . " " . trim($request->middle_name) . " " . trim($request->last_name);
        $user->phone = $request->phone;
        $user->email = $request->email;
        $user->gender = $request->gender;

        try{
            $user->save();
            $role = $request->role;
            $tier = Facility::idByCode($request->mfl_code);
            $program_id = $request->program_id;
            $designation = $request->designation;
            DB::table('role_user')->where('user_id', $id)->where('role_id', $role)->delete();
            $ru = DB::table('role_user')->insert(["user_id" => $id, "role_id" => $role, "tier" => $tier, "program_id" => $program_id, "designation" => $designation]);
            \Log::info("New details: ");
            \Log::info($request);
        }
        catch(Exception $e)
        {
            abort(404);
        }
        
        return response()->json($user);
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
        \Log::info("User (USER ID: $id {$user->name}) deleted by: USER ID: ".Auth::user()->id);
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
        $user = User::withTrashed()->where('id', $id)->restore();
        $user = User::find($id);
        \Log::info("User (USER ID: $id {$user->name}) restored by: USER ID: ".Auth::user()->id);
        $message    = "Dear ".$user->name.", NPHL has enabled your account. Once enrolled, you’ll receive a tester ID";

        $smsHandler = new SmsHandler();
        $smsHandler->sendMessage($user->phone, $message);

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
                        ->orWhere('phone', 'LIKE', "%{$search}%")->latest()->paginate(50);
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
     * Transfer the specified participant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function transfer(Request $request, $id)
    {
        $this->validate($request, [
            'facility_id' => 'required',
            'program_id' => 'required',
        ]);
        \Log::info("User (USER ID: $id) transfered by: USER ID: ".Auth::user()->id);
        \Log::info("From: ");
        $tier = Tier::where('user_id', $id)->first();
        \Log::info($tier);
        $prog = NULL;
        $fac = NULL;
        if($request->facility_id)
        {
            $fac = $request->facility_id;
            $tier->tier = $fac;
        }
        if($request->program_id)
        {
            $prog = $request->program_id;
            $tier->program_id = $prog;
        }
        $response = $tier->save();
        \Log::info("To:");
        \Log::info($request);

        return response()->json($response);
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
        $designations = [
            0 => '',
            User::NURSE => 'Nurse',
            User::LABTECH => 'Lab Tech.',
            User::COUNSELLOR => 'Counsellor',
            User::RCO => 'RCO',
        ];
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
            $user->first_name = $request->first_name;
            $user->middle_name = $request->middle_name;
            $user->last_name = $request->last_name;
            $user->name = $request->first_name . " " . $request->middle_name . "" . $request->last_name;
            $user->gender = $request->gender;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->designation = $request->designation;
            $user->username = $request->name;
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
        
        $token = mt_rand(100000, 999999);
        $user->sms_code = $token;
        $user->save();
        $message    = "Your Verification Code is: ".$token;

        $smsHandler = new SmsHandler();
        $smsHandler->sendMessage($user->phone, $message);

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

            return response()->json(["success" => "Phone number successfully verified. Your ID will be sent to you shortly."]);
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
        $check = User::where('email_verification_code', $token)->first();

        if(!is_null($check)){
            $user = User::find($check->id);

            if($user->email_verified == 1){
                return redirect()->to('login')
                    ->with('success', "Your email is already verified."); 
            }

            $user->update(['email_verified' => 1]);

            return redirect()->to('login')
                ->with('success', "Email successfully verified.");
        }
        return redirect()->to('login')
                ->with('warning', "Your token is invalid.");
    }
    /**
     *   Function to approve participant
     */
    public function approve($id)
    {
        $user = User::withTrashed()->where('id', $id)->first();
        $max = DB::table('users')->max('uid');
        $m = $max+1;
        if ($user->uid==null ||$user->uid==''){
            $user->uid = $m; //pick sequential unique ids
            $user->username = $m;
        }

        $user->deleted_at = null;
        $user->status = '';
        $user->save();
        \Log::info("User (USER ID: $id) enabled by: USER ID: ".Auth::user()->id);

        //send mail
        $token = app('auth.password.broker')->createToken($user);
        $user->token = $token;
        $user->notify(new WelcomeNote($user));
        
        $message    = "Dear ".$user->name.", your Sub-county Coordinator has approved your request to participate in PT. Your tester ID is ".$user->uid.". Use the link sent to your email to get started.";
       
        $smsHandler = new SmsHandler();
        $smsHandler->sendMessage($user->phone, $message);
    }

    public function denyUserVerification(Request $request, $id){
       
        $user = User::withTrashed()->find($id);
        $user->status = User::REJECTED;
        $user->reason = $request->reason;
        $now = Carbon::now('Africa/Nairobi')->toDateString();
        $user->status_date = $now;
        $user->save();
        $user->delete();
        \Log::info("User (USER ID $id) deleted by: USER ID: ".Auth::user()->id);
        
        $message = "Dear ".$user->name.", NPHL has rejected your request to participate in PT because ".$request->reason;
        return response()->json($message);
        
    }

    /**
     * Function to download all participants - batch + self-enrolled
     *
     */
    public function testerSummary()
    {
        // $data = Program::get()->toArray();
        $suffix = "PARTICIPANTS SUMMARY";
        $title = "";
        $users = NULL;
        $roleId = Role::idByName('Participant');
        $counter = 0;
        //  workbook title
        if(Auth::user()->isCountyCoordinator())
        {
            $title = County::find(Auth::user()->ru()->tier)->name." COUNTY ".$suffix;
            $counter = County::find(Auth::user()->ru()->tier)->users->count();
        }
        else
        {
            $title = "KENYA RAPID HIV PT ".$suffix;
            $counter = DB::table('role_user')->where('role_id', $roleId)->count();
        }
        if($counter > 0)
        {
            return Excel::create($title, function($excel) use ($users, $roleId) 
            {
                if(Auth::user()->isCountyCoordinator())
                {
                    $countyId = Auth::user()->ru()->tier;
                    $county = County::find($countyId)->name;
                    //  sub-counties and facilities
                    $fIds = County::find($countyId)->facilities()->pluck('id');
                    $ids = DB::table('role_user')->where('role_id', $roleId)->whereIn('tier', $fIds)->pluck('user_id')->toArray();
                    $testers = $ids;
                    $testers = implode(",", $testers);

                    $summary = [];

                    if (empty($testers)) {
                       $summary[] = ['TESTER NAME' => '', 'TESTER UNIQUE ID' => '', 'TESTER PHONE' => '', 'TESTER EMAIL' => '', 'PROGRAM' => '', 'DESIGNATION' => '', 'FACILITY' => '', 'MFL CODE' => '', 'IN CHARGE' => '', 'IN CHARGE PHONE' => '', 'IN CHARGE EMAIL' => '']; 
                    }else{
                        $data = DB::select("SELECT u.name AS 'TESTER NAME', u.uid AS 'TESTER UNIQUE ID', u.phone AS 'TESTER PHONE', u.email AS 'TESTER EMAIL', p.name AS 'PROGRAM', ru.designation AS 'DESIGNATION', f.name AS 'FACILITY', f.code AS 'MFL CODE', f.in_charge AS 'IN CHARGE', f.in_charge_phone AS 'IN CHARGE PHONE', f.in_charge_email AS 'IN CHARGE EMAIL' FROM users u, facilities f, role_user ru, programs p WHERE u.id = ru.user_id AND ru.tier = f.id AND ru.program_id = p.id AND u.id IN (".$testers.") ORDER BY u.uid ASC;");
                        
                        foreach($data as $key => $value)
                        {
                            $tname = NULL;
                            $tuid = NULL;
                            $tname = NULL;
                            $tphone = NULL;
                            $temail = NULL;
                            $tprog = NULL;
                            $tdes = NULL;
                            $facility = NULL;
                            $mfl = NULL;
                            $icharge = NULL;
                            $iphone = NULL;
                            $iemail = NULL;
                            foreach($value as $mike => $ross)
                            {
                                if(strcasecmp("TESTER NAME", $mike) == 0)
                                    $tname = $ross;
                                if(strcasecmp("TESTER UNIQUE ID", $mike) == 0)
                                    $tuid = $ross;
                                if(strcasecmp("TESTER PHONE", $mike) == 0)
                                    $tphone = $ross;
                                if(strcasecmp("TESTER EMAIL", $mike) == 0)
                                    $temail = $ross;
                                if(strcasecmp("PROGRAM", $mike) == 0)
                                    $tprog = $ross;
                                if(strcasecmp("DESIGNATION", $mike) == 0)
                                    $tdes = User::des($ross);
                                if(strcasecmp("FACILITY", $mike) == 0)
                                    $facility = $ross;
                                if(strcasecmp("MFL CODE", $mike) == 0)
                                    $mfl = $ross;
                                if(strcasecmp("IN CHARGE", $mike) == 0)
                                    $icharge = $ross;
                                if(strcasecmp("IN CHARGE PHONE", $mike) == 0)
                                    $iphone = $ross;
                                if(strcasecmp("IN CHARGE EMAIL", $mike) == 0)
                                    $iemail = $ross;
                            }
                            $summary[] = ['TESTER NAME' => $tname, 'TESTER UNIQUE ID' => $tuid, 'TESTER PHONE' => $tphone, 'TESTER EMAIL' => $temail, 'PROGRAM' => $tprog, 'DESIGNATION' => $tdes, 'FACILITY' => $facility, 'MFL CODE' => $mfl, 'IN CHARGE' => $icharge, 'IN CHARGE PHONE' => $iphone, 'IN CHARGE EMAIL' => $iemail];    
                        }
                    }
                    $excel->sheet($sheetTitle, function($sheet) use ($summary) {
                        $sheet->fromArray($summary);
                    });
                }
                else
                {
                    $counties = County::all();
                    foreach($counties as $county)
                    {
                        $sheetTitle = $county->name;
                        $fIds = $county->facilities()->pluck('id');
                        $ids = DB::table('role_user')->where('role_id', $roleId)->whereIn('tier', $fIds)->pluck('user_id')->toArray();
                        $testers = $ids;
                   
                        $testers = implode(",", $testers);

                        if (empty($testers)) {
                           $summary[] = ['TESTER NAME' => '', 'TESTER UNIQUE ID' => '', 'TESTER PHONE' => '', 'TESTER EMAIL' => '', 'PROGRAM' => '', 'DESIGNATION' => '', 'FACILITY' => '', 'MFL CODE' => '', 'IN CHARGE' => '', 'IN CHARGE PHONE' => '', 'IN CHARGE EMAIL' => '']; 
                        }else{
                            $data = DB::select("SELECT u.name AS 'TESTER NAME', u.uid AS 'TESTER UNIQUE ID', u.phone AS 'TESTER PHONE', u.email AS 'TESTER EMAIL', p.name AS 'PROGRAM', ru.designation AS 'DESIGNATION', f.name AS 'FACILITY', f.code AS 'MFL CODE', f.in_charge AS 'IN CHARGE', f.in_charge_phone AS 'IN CHARGE PHONE', f.in_charge_email AS 'IN CHARGE EMAIL' FROM users u, facilities f, role_user ru, programs p WHERE u.id = ru.user_id AND ru.tier = f.id AND ru.program_id = p.id AND u.id IN (".$testers.") ORDER BY u.uid ASC;");
                            // dd($data);
                            //  create assotiative array
                            $summary = [];
                            foreach($data as $key => $value)
                            {
                                $tname = NULL;
                                $tuid = NULL;
                                $tname = NULL;
                                $tphone = NULL;
                                $temail = NULL;
                                $tprog = NULL;
                                $tdes = NULL;
                                $facility = NULL;
                                $mfl = NULL;
                                $icharge = NULL;
                                $iphone = NULL;
                                $iemail = NULL;
                                foreach($value as $mike => $ross)
                                {
                                    if(strcasecmp("TESTER NAME", $mike) == 0)
                                        $tname = $ross;
                                    if(strcasecmp("TESTER UNIQUE ID", $mike) == 0)
                                        $tuid = $ross;
                                    if(strcasecmp("TESTER PHONE", $mike) == 0)
                                        $tphone = $ross;
                                    if(strcasecmp("TESTER EMAIL", $mike) == 0)
                                        $temail = $ross;
                                    if(strcasecmp("PROGRAM", $mike) == 0)
                                        $tprog = $ross;
                                    if(strcasecmp("DESIGNATION", $mike) == 0)
                                        $tdes = User::des($ross);
                                    if(strcasecmp("FACILITY", $mike) == 0)
                                        $facility = $ross;
                                    if(strcasecmp("MFL CODE", $mike) == 0)
                                        $mfl = $ross;
                                    if(strcasecmp("IN CHARGE", $mike) == 0)
                                        $icharge = $ross;
                                    if(strcasecmp("IN CHARGE PHONE", $mike) == 0)
                                        $iphone = $ross;
                                    if(strcasecmp("IN CHARGE EMAIL", $mike) == 0)
                                        $iemail = $ross;
                                }
                                $summary[] = ['TESTER NAME' => $tname, 'TESTER UNIQUE ID' => $tuid, 'TESTER PHONE' => $tphone, 'TESTER EMAIL' => $temail, 'PROGRAM' => $tprog, 'DESIGNATION' => $tdes, 'FACILITY' => $facility, 'MFL CODE' => $mfl, 'IN CHARGE' => $icharge, 'IN CHARGE PHONE' => $iphone, 'IN CHARGE EMAIL' => $iemail];    
                            }
                        }
                        $excel->sheet($sheetTitle, function($sheet) use ($summary) {
                            $sheet->fromArray($summary);
                        });
                    }
                }
            })->download('xlsx');
        }
        else
        {
            return redirect()->back()->with('error', 'No data for PT participants found');
        }
    }

    public function participantCounts(){

        return view('report.participantregistrationcount');
    }

    public function getParticipantCounts(Request $request){

        $ITEMS_PER_PAGE = 50;
        $error = ['error' => 'No results found, please try with different keywords.'];
        $PARTICIPANT_ROLE_ID = Role::idByName('Participant');
        $role = Auth::user()->ru()->role_id;
        $tier = Auth::user()->ru()->tier;

        $round = 1;
        if(strcmp($request->round, '') != 0) $round = $request->round;
        
        $data = DB::table('users')
                    ->join('role_user', function($join) use ($PARTICIPANT_ROLE_ID){
                        $join->on('users.id', '=', 'role_user.user_id')
                            ->where('role_user.role_id', '=', $PARTICIPANT_ROLE_ID);
                    })
                    ->join('facilities', 'role_user.tier', '=', 'facilities.id')
                    ->join('sub_counties', 'facilities.sub_county_id', '=', 'sub_counties.id')
                    ->join('counties', 'sub_counties.county_id', '=', 'counties.id')
                    ->leftJoin(
                        DB::raw('(SELECT enrolments.user_id, enrolments.deleted_at, pt.id AS pt_id FROM enrolments INNER JOIN rounds ON enrolments.round_id = rounds.id LEFT JOIN pt ON enrolments.id = pt.enrolment_id WHERE rounds.id = '.$round.') AS live_round'),
                        function($join){
                            $join->on('users.id', '=', 'live_round.user_id')
                                ->whereNull('live_round.deleted_at');
                    });

        if(strcmp($request->county, '') != 0) $data = $data->where('counties.id', '=', $request->county);
        if(strcmp($request->subcounty, '') != 0) $data = $data->where('sub_counties.id', '=', $request->subcounty);
        if(strcmp($request->facility, '') != 0) $data = $data->where('facilities.id', '=', $request->facility);

        if(Auth::user()->isCountyCoordinator()) $data = $data->where('counties.id', '=', $tier);
        if(Auth::user()->isSubCountyCoordinator()) $data = $data->where('sub_counties.id', '=', $tier);

        $data = $data->selectRaw('counties.name AS county, sub_counties.name AS subcounty, count(DISTINCT users.id) AS total, count(DISTINCT IF(ISNULL(users.deleted_at),users.id,NULL)) AS active, count(DISTINCT IF(ISNULL(users.deleted_at),live_round.user_id,NULL)) AS current_enrolment, count(DISTINCT IF(ISNULL(live_round.pt_id),NULL,users.id)) AS replied')
                    ->groupBy('counties.id', 'sub_counties.id')
                    ->orderBy('counties.name')
                    ->orderBy('sub_counties.name');

        $totalUsers = collect($data->pluck('total'))->sum();
        $activeUsers = collect($data->pluck('active'))->sum();
        $enrolledUsers = collect($data->pluck('current_enrolment'))->sum();
        $repliedUsers = collect($data->pluck('replied'))->sum();

        $data = $data->paginate($ITEMS_PER_PAGE);

        $response = [
            'pagination' => [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ],
            'role' => $role,
            'round' => $round,
            'data' => $data,
            'replied_users' => $repliedUsers,
            'active_users' => $activeUsers,
            'enrolled_users' => $enrolledUsers,
            'total_users' => $totalUsers
        ];

        return response()->json($response);
    }
}
$excel = App::make('excel');
