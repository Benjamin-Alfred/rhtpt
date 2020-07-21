<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Round;
use App\Enrol;
use App\Program;
use App\Facility;
use App\Notification;
use App\User;
use App\County;
use App\Role;
use App\SmsHandler;
use App\SubCounty;
use App\Designation;
use App\Pt;
use App\ImplementingPartner;

use Auth;
use DB;
use Jenssegers\Date\Date as Carbon;
use Excel;
use App;
use File;
use Hash;
//  Notification
use App\Notifications\WelcomeNote;
use App\Notifications\EnrollmentNote;

class RoundController extends Controller
{

    public function manageRound()
    {
        return view('round.index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $error = ['error' => 'No results found, please try with different keywords.'];
        $today = Carbon::today();
        $rounds = Round::latest()->withTrashed()->paginate(5);
        if($request->has('q')) 
        {
            $search = $request->get('q');
            $rounds = Round::where('name', 'LIKE', "%{$search}%")->latest()->withTrashed()->paginate(5);
        }

        $response = [
            'pagination' => [
                'total' => $rounds->total(),
                'per_page' => $rounds->perPage(),
                'current_page' => $rounds->currentPage(),
                'last_page' => $rounds->lastPage(),
                'from' => $rounds->firstItem(),
                'to' => $rounds->lastItem()
            ],
            'data' => $rounds,
            'today'=>$today
        ];

        return $rounds->count() > 0 ? response()->json($response) : $error;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {      
        $rounds = Round::where('name', 'LIKE', "{$request->name}")->withTrashed()->get();

        if ($rounds->count() > 0) {

            return response()->json('1');

        }else if ($request->start_date > $request->end_date) {
           
            return response()->json('2');

        }else
        {        
            //save round
            $request->request->add(['user_id' => Auth::user()->id]);

            $round = new Round;
            $round->name =$request->name;
            $round->description =$request->description;
            $round->start_date =$request->start_date;
            $round->enrollment_date =$request->enrollment_date;
            $round->end_date =$request->end_date;
            $round->user_id =$request->user_id;
            $round->save();

            //send sms
            $users = new User;
            $county_coordinators = $users->county_coordinators()->pluck('phone')->toArray();
            $subcounty_coordinators = $users->sub_county_coordinators()->pluck('phone')->toArray();
            $phone_numbers = array_merge($county_coordinators, $subcounty_coordinators);
            
            $recipients = NULL;
            $recipients = implode(",", $phone_numbers);
            //  Send SMS
            $message = 'Dear County/SubCounty Coordinator, NPHL has created Round'.$round->name.'. You have until'.$round->enrollment_date.' to enrol participants into this round.';            

            if($recipients)
            {
                $sms = new SmsHandler;
                foreach ($recipients as $recipient) {
                    $sms->sendMessage($recipient, $message);
                }
            }
            return response()->json($round);
        }
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
        $request->request->add(['user_id' => Auth::user()->id]);

        $round = Round::find($id);
        $round->name =$request->name;
        $round->description =$request->description;
        $round->start_date =$request->start_date;
        $round->enrollment_date =$request->enrollment_date;
        $round->end_date =$request->end_date;
        $round->user_id =$request->user_id;
        $round->save();

        //send sms
        $users = new User;
        $county_coordinators = $users->county_coordinators()->pluck('phone')->toArray();
        $subcounty_coordinators = $users->sub_county_coordinators()->pluck('phone')->toArray();
        $phone_numbers = array_merge($county_coordinators, $subcounty_coordinators);
        
        $recipients = NULL;
        $recipients = implode(",", $phone_numbers);
        //  Send SMS
        $message = 'Dear County/SubCounty Coordinator, Round '.$round->name.' is now open for enrollment. Please enroll your participants between '.$round->start_date.' and '.$round->enrollment_date.'. Deadline is 5pm '.$round->enrollment_date;

        if($recipients)
        {
            // Send messages
            $sms = new SmsHandler;
            foreach ($recipients as $recipient) {
                $sms->sendMessage($recipient, $message);
            }
        }

        return response()->json($round);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Round::find($id)->delete();
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
        $round = Round::withTrashed()->find($id)->restore();
        return response()->json(['done']);
    }

    /**
     * Publish / unpublish a round.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function publish($id) 
    {
        $round = Round::find($id);
        if ($round->published_at) {
            $round->published_at = null;
        }else{
            date_default_timezone_set('Africa/Nairobi');
            $round->published_at = date("yyyy-mm-dd h:i:s");
        }
        $round->save();

        return response()->json(['done']);
    }

    /**
     * Function to return list of rounds.
     *
     */
    public function rounds()
    {
        $rounds = Round::pluck('description', 'id');
        $categories = [];
        foreach($rounds as $key => $value)
        {
            $categories[] = ['id' => $key, 'value' => $value];
        }
        return $categories;
    }

    /**
     * Function to return list of rounds already done by a user.
     *
     */
    public function roundsDone($status = 0)
    {
        // get enrolments with no submissions
        $ids = Auth::user()->enrol()->where('status', $status)->pluck('round_id');
        // fetch rounds details
        $rounds = Round::whereIn('id', $ids)->where('end_date', '>', Carbon::today())->pluck('description', 'id');
        // format to match dropdown values
        $categories = [];
        foreach($rounds as $key => $value)
        {
            $categories[] = ['id' => $key, 'value' => $value];
        }
        return $categories;
    }

    /**
     * Enrol a user(s).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function enrol(Request $request)
    {
        $roundId = $request->round_id;
        $phone_numbers = [];
        foreach($request->selectedParticipants as $key => $userID)
        {
            $enrolledUser = Enrol::where('round_id', $roundId)->where('user_id', $userID)->get();

            if(count($enrolledUser) == 0){
                $enrol = new Enrol;
                try {
                    $facilityID = User::find($userID)->ru()->tier;
                    $enrol->user_id = (int)$userID;
                    $enrol->round_id = $roundId;
                    $enrol->facility_id = $facilityID;
                    $enrol->tester_id = (int)$userID;
                    $enrol->save();
                    \Log::info("Participant (users.id: $userID) enrolled for round (rounds.id: $roundId) at facility ($facilityID) by (users.id: ".Auth::user()->id.")");
                        
                    $user = User::find($enrol->user_id);
                    if($user->phone)
                    {
                        array_push($phone_numbers, $user->phone);
                    }
                } catch (Exception $e) {
                    \Log::info($e);
                }
            }else if(strcmp($request->view, "unenrol") == 0){
                foreach ($enrolledUser as $etd) {
		    Enrol::find($etd['id'])->delete();
		    \Log::info("Participant (users.id: $userID) unenrolled (Enrolment ID: {$etd['id']} from round (rounds.id: $roundId) by (users.id: ".Auth::user()->id.")");
                }
            }
        }

	    if(count($phone_numbers) > 0){
            \Log::info(count($phone_numbers)." enrolled users.");
            $recipients = NULL;
            $recipients = implode(",", $phone_numbers);
            //  Send SMS
            $round = Round::find($roundId)->name;
            $message = Notification::where('template', Notification::ENROLMENT)->first()->message;
            $message = ApiController::replace_between($message, '[', ']', $round);
            $message = str_replace(' [', ' ', $message);
            $message = str_replace('] ', ' ', $message);

            if($phone_numbers) {

                $sms = new SmsHandler;
                foreach (array_unique($phone_numbers) as $recipient) {
                    $sms->sendMessage($recipient, $message);
                    \Log::info("Enrolment notification sent to $recipient");

                }
            }
        }

        return response()->json('Enrolled.');
    }


    /**
     * Function to return possible durations of rounds.
     *
     */
    public function durations()
    {
        $response = [];
        $data = [
                    Round::ONE => "1 Month", 
                    Round::TWO => "2 Months", 
                    Round::THREE => "3 Months", 
                    Round::FOUR => "4 Months", 
                    Round::FIVE => "5 Months", 
                    Round::SIX => "6 Months",
                    Round::SEVEN => "7 Months", 
                    Round::EIGHT => "8 Months"
                ];
        foreach($data as $key => $value)
        {
            $response[] = ['id' => $key, 'value' => $value];
        }
        return $response;
    }

    /**
     * Function for load the view of participants to be enrolled
     *
     */
    public function manageEnrolParticipant()
    {
        return view('round.enrolparticipantlist');
    }

    //get the list of users to be enrolled
     public function loadparticipants(Request $request, $round=null)
    {
        if ($request->has('round')) {
            $round = $request->get('round');
        }
        $enrol_status=0;
        $error = ['error' => 'No results found, please try with different keywords.'];
        $participants = User::whereNotNull('uid')->get();
        if(Auth::user()->isCountyCoordinator())
        {
            $participants = County::find(Auth::user()->ru()->tier)->users()->get();
        }
        else if(Auth::user()->isSubCountyCoordinator())
        {
           $participants = SubCounty::find(Auth::user()->ru()->tier)->users()->get();
        }
        else if(Auth::user()->isFacilityInCharge())
        {
           $participants = Facility::find(Auth::user()->ru()->tier)->users()->get();
        }

        if($request->has('q')) 
        {
            $search = $request->get('q');
            $participants = User::where('name', 'LIKE', "%{$search}%")->orWhere('uid', 'LIKE', "%{$search}%")->get();
            if(Auth::user()->isCountyCoordinator())
            {
                $participants = County::find(Auth::user()->ru()->tier)->users()->where('users.name', 'LIKE', "%{$search}%")->orWhere('uid', 'LIKE', "%{$search}%")->get();
            }
            else if(Auth::user()->isSubCountyCoordinator())
            {
                $participants = SubCounty::find(Auth::user()->ru()->tier)->users()->where('users.name', 'LIKE', "%{$search}%")->orWhere('uid', 'LIKE', "%{$search}%")->get();
            }
            else if(Auth::user()->isFacilityInCharge())
            {
               $participants = Facility::find(Auth::user()->ru()->tier)->users()->where('users.name', 'LIKE', "%{$search}%")->orWhere('uid', 'LIKE', "%{$search}%")->get();
            }
        }
        //filter users by region
        if($request->has('county')) 
        {            
            $participants = County::find($request->get('county'))->users()->whereNull('uid')->latest()->withTrashed()->paginate(100);             
        }
         if($request->has('sub_county')) 
        {
            $participants = SubCounty::find($request->get('sub_county'))->users()->whereNull('uid')->latest()->withTrashed()->paginate(100);
        }
        if($request->has('facility')) 
        {
            $participants = Facility::find($request->get('facility'))->users()->whereNull('uid')->latest()->withTrashed()->paginate(100);
        }

        $enrolled_users = Enrol::where('round_id', $round)->pluck('user_id')->toArray();
       
         \Log::info("Count of participants (pre-enrolment check): ".count($participants));
        if ($request->has('enrolled')) {
            $participants = $participants->filter(function ($participant) use($enrolled_users){
                if (in_array($participant->id, $enrolled_users) ) {  
                    return $participant;
                }
            });

            $enrol_status = 1;
        } else {
            $participants = $participants->filter(function ($participant) use($enrolled_users){
                if (!in_array($participant->id, $enrolled_users) ) {  
                    return $participant;
                }
            });
	    }

	    \Log::info("Count of participants (post-enrolment check): ".count($participants));
        
        foreach($participants as $participant)
        {   
            if(!empty($participant->ru()->tier))
            {
                $participant->facility = $participant->ru()->tier;
                $participant->program = $participant->ru()->program_id;
                $participant->gndr = $participant->maleOrFemale((int)$participant->gender);
                $facility = Facility::find($participant->ru()->tier);
                try{
                    $participant->mfl = $facility->code;
                    $participant->fac = $facility->name;

                    $participant->sub_county = $facility->subCounty->id;
                    $participant->county = $facility->subCounty->county->id;
                    $participant->sub = $facility->subCounty->name;
                    $participant->kaunti = $facility->subCounty->county->name;
                }catch(\Exception $ex){
                    \Log::error("Missing facility information!");
                    \Log::error($participant);
                    \Log::error($ex->getMessage());
                }
                try{
                    $participant->prog = Program::find($participant->ru()->program_id)->name;
                }catch(\Exception $ex){
                    \Log::error("Participant does not have a program!");
                    \Log::error($participant);
                    \Log::error($ex->getMessage());
                }
                try{
                    $participant->des = $participant->designation($participant->ru()->designation);
                    $participants->designation = $participant->ru()->designation;
                }catch(\Exception $ex){
                    \Log::error("Participant does not have a designation!");
                    \Log::error($participant);
                    \Log::error($ex->getMessage());
                }
            }
            else
            {
                $participant->facility = '';
                $participant->program = '';
            }
            !empty($participant->ru())?$participant->role = $participant->ru()->role_id:$participant->role = '';
            !empty($participant->ru())?$participant->rl = Role::find($participant->ru()->role_id)->name:$participant->rl = '';
            
        }
       
        $response = [           
            'data' => $participants,
            'role' => Auth::user()->ru()->role_id,
            'tier' => Auth::user()->ru()->tier,
            'enrol_status' =>$enrol_status
        ];

        return $participants->count() > 0 ? response()->json($response) : $error;   

    }

    /**
     * Function for load the view of participants to be enrolled
     *
     */
    public function manage_participant_info()
    {
        return view('round.participantinfo');
    }

    //get the list of users to be enrolled
    public function participants_info(Request $request, $round=null)
    {        
        $items_per_page = 100;
        $error = ['error' => 'No results found, please try with different keywords.'];
        
        //Get the enrolled users
        $testers = User::join('enrolments', 'users.id', '=', 'enrolments.user_id')
                ->join('facilities', 'enrolments.facility_id', '=', 'facilities.id')
                ->join('sub_counties', 'facilities.sub_county_id', '=', 'sub_counties.id')
                ->join('counties', 'sub_counties.county_id', '=', 'counties.id')
                ->leftJoin('pt', 'enrolments.id', '=', 'pt.enrolment_id')
                ->where('enrolments.round_id', '=', $round)
                ->whereNull('enrolments.deleted_at')
                ->select('users.*', 'facilities.name AS facility_name', 'sub_counties.name AS sub_county_name', 'counties.name AS county_name', 'pt.panel_status AS result_status');


        if($request->has('facility')) 
        {
            $testers = $testers->where('enrolments.facility_id', '=', $request->get('facility'));

        }else if($request->has('sub_county')){

            $testers = $testers->where('facilities.sub_county_id', '=', $request->get('sub_county'));
        }else if($request->has('county')) {

            $testers = $testers->where('sub_counties.county_id', '=', $request->get('county'));
        }

        if(Auth::user()->isCountyCoordinator())
        {
            $testers = $testers->where('sub_counties.county_id', '=', Auth::user()->ru()->tier);
        }

        if(Auth::user()->isSubCountyCoordinator())
        {
            $testers = $testers->where('facilities.sub_county_id', '=', Auth::user()->ru()->tier);
        }

        if(Auth::user()->isPartner())
        {
            $facilities = Auth::user()->implementingPartner->all_facilities()->pluck('id')->toArray();
            $testers = $testers->whereIn('enrolments.facility_id', $facilities);
        }

        if(Auth::user()->isFacilityInCharge())
        {
            $testers = $testers->where('enrolments.facility_id', '=', Auth::user()->ru()->tier);
        }

        if($request->has('q')) 
        {
            $search = $request->get('q');
            $testers = $testers->where(function($query) use ($search){
                return $query->where('users.name', 'LIKE', "%{$search}%")
                            ->orWhere('users.first_name', 'LIKE', "%{$search}%")
                            ->orWhere('users.middle_name', 'LIKE', "%{$search}%")
                            ->orWhere('users.last_name', 'LIKE', "%{$search}%")
                            ->orWhere('users.uid', 'LIKE', "%{$search}%");
            });
        }

        $testers = $testers->orderBy('county_name')->orderBy('sub_county_name')->orderBy('facility_name')->orderBy('name')->paginate($items_per_page);
        $response = [                      
            'data' => $testers,
            'role' => Auth::user()->ru()->role_id,
            'tier' => Auth::user()->ru()->tier,
            'pagination' => [
                'total' => $testers->total(),
                'per_page' => $testers->perPage(),
                'current_page' => $testers->currentPage(),
                'last_page' => $testers->lastPage(),
                'from' => $testers->firstItem(),
                'to' => $testers->lastItem()
            ],
        ];

        return $testers->count() > 0 ? response()->json($response) : $error;
    }

    /**
     * Function to download the participants in the past round
     *
     */
    public function testerSummary(Request $request)
    {
        $rId = $request->id;
        $roundId = Round::find($rId)->name;
        $suffix = "ROUND ".$roundId." PARTICIPANTS SUMMARY";
        $title = "";
        $users = NULL;
        $roleId = Role::idByName('Participant');        
        //check if anyone registered for that round is enrolled to tha round
        $p = Enrol::where('round_id',$rId)->get();
        if(count($p) == 0){
            Session()->flash ('message',"The round has no participants please add participants to the round");
            return back();
        }
        //  workbook title
        if(Auth::user()->isSubCountyCoordinator())
            $title = SubCounty::find(Auth::user()->ru()->tier)->name." SUBCOUNTY ".$suffix;
        else if(Auth::user()->isCountyCoordinator())
            $title = County::find(Auth::user()->ru()->tier)->name." COUNTY ".$suffix;
        else
            $title = "KENYA RAPID HIV PT ".$suffix;

        return Excel::create($title, function($excel) use ($rId, $roundId, $users, $roleId, $request) 
        {
            $round = Round::find($rId);
            $sheetTitle = "";
            $query = "SELECT
                        u.first_name AS 'Tester First Name',
                        u.last_name AS 'Tester Surname',
                        u.middle_name AS 'Tester Other Name',
                        u.uid AS 'Tester Enrollment ID',
                        u.gender AS 'Gender',
                        u.phone AS 'Tester Mobile Number',
                        u.email AS 'Tester Email',
                        u.address AS 'Tester Address',
                        p.name AS 'Program',
                        d.name AS 'Designation',
                        f.name AS 'Facility',
                        f.code AS 'MFL Code',
                        f.in_charge AS 'In Charge',
                        f.in_charge_phone AS 'In Charge Phone',
                        f.in_charge_email AS 'In Charge Email'
                        FROM users u
                            INNER JOIN role_user ru ON u.id = ru.user_id AND ru.role_id = $roleId
                            INNER JOIN facilities f ON ru.tier = f.id
                            LEFT JOIN programs p ON ru.program_id = p.id
                            LEFT JOIN designations d ON ru.designation = d.id
                        WHERE 
                            u.id IN (_TESTERS_) ORDER BY u.uid ASC;";

            if(Auth::user()->isSubCountyCoordinator())
            {
                $countyId = Auth::user()->ru()->tier;
                $sheetTitle = SubCounty::find($countyId)->name;
                //  sub-counties and facilities
                $fIds = SubCounty::find($countyId)->facilities()->pluck('id');

                $ids = DB::table('role_user')->where('role_id', $roleId)->whereIn('tier', $fIds)->pluck('user_id');

                if($request->status)
                    $ids = User::whereIn('id', $ids)->whereBetween('date_registered', [$round->start_date, $round->end_date])->pluck('id');

                $testers = Enrol::where('round_id', $rId)->whereIn('user_id', $ids)->pluck('user_id')->toArray();
                $testers = implode(",", $testers);               
                $summary = $this->getTesterSummary($testers, $query);

                $excel->sheet($sheetTitle, function($sheet) use ($summary) {
                    $sheet->fromArray($summary);
                });
            }
            else if(Auth::user()->isCountyCoordinator()){
                $countyId = Auth::user()->ru()->tier;
                $sheetTitle = County::find($countyId)->name;
                //  sub-counties and facilities
                $fIds = County::find($countyId)->facilities()->pluck('id');

                $ids = DB::table('role_user')->where('role_id', $roleId)->whereIn('tier', $fIds)->pluck('user_id');

                if($request->status)
                    $ids = User::whereIn('id', $ids)->whereBetween('date_registered', [$round->start_date, $round->end_date])->pluck('id');

                $testers = Enrol::where('round_id', $rId)->whereIn('user_id', $ids)->pluck('user_id')->toArray();
                $testers = implode(",", $testers);               
                $summary = $this->getTesterSummary($testers, $query);

                $excel->sheet($sheetTitle, function($sheet) use ($summary) {
                    $sheet->fromArray($summary);
                });
            }
            else if(Auth::user()->isSuperAdministrator())
            {
    
                $query = "SELECT
                        u.first_name,
                        u.last_name,
                        u.middle_name,
                        u.uid,
                        u.gender,
                        u.phone,
                        u.email,
                        u.address,
                        p.name AS 'program',
                        d.name AS 'designation',
                        f.name AS 'facility',
                        f.code AS 'MFL_Code',
                        f.in_charge,
                        f.in_charge_phone,
			f.in_charge_email,
                        s.name AS sub_county,
			c.name AS county,
                        s.county_id
			FROM users u
                            INNER JOIN enrolments e ON u.id = e.user_id
                            INNER JOIN role_user ru ON u.id = ru.user_id AND ru.role_id = $roleId
			    INNER JOIN facilities f ON e.facility_id = f.id
                            INNER JOIN sub_counties s ON f.sub_county_id = s.id
                            INNER JOIN counties c ON s.county_id = c.id
                            LEFT JOIN programs p ON ru.program_id = p.id
                            LEFT JOIN designations d ON ru.designation = d.id
                        WHERE 
			    e.deleted_at IS NULL AND e.round_id = $rId ORDER BY c.id, s.id, f.id, u.uid ASC;";

		$countyID = 0;
		$participants = DB::select($query);

		foreach($participants as $participant)
                {

                    if($countyID != $participant->county_id)
		    {
			if($countyID != 0){
		            $excel->sheet($sheetTitle, function($sheet) use ($summary) {
		                $sheet->fromArray($summary);
			    });
			}
			$countyID = $participant->county_id;
			$sheetTitle = $participant->county;
			$summary = [];
                    }
    
		    $summary[] = [

                        'County' => $participant->county,
                        'Sub County' => $participant->sub_county,
                        'Facility' => $participant->facility,
                        'MFL Code' => $participant->MFL_Code,
                        'Tester Enrollment ID' => $participant->uid,
                        'Tester First Name' => $participant->first_name,
                        'Tester Surname' => $participant->last_name,
                        'Tester Other Name' => $participant->middle_name,
                        'Gender' => $participant->gender,
                        'Tester Mobile Number' => $participant->phone,
                        'Tester Email' => $participant->email,
                        'Tester Address' => $participant->address,
                        'Designation' => $participant->designation,
                        'Program' => $participant->program,
                        'In Charge' => $participant->in_charge,
                        'In Charge Email' => $participant->in_charge_email,
                        'In Charge Phone' => $participant->in_charge_phone,
                    ];
		}
                $excel->sheet($sheetTitle, function($sheet) use ($summary) {
                    $sheet->fromArray($summary);
                });
            }
        })->download('xlsx');
    }
    
    /**
     * Helper function for the testerSummary function.
     * Returns an array of tester details
     *
     */
    public function getTesterSummary($testers, $query)
    {
        if (empty($testers)) {
            $summary[] = [
                'County' => '',
                'Sub County' => '',
                'Facility' => '',
                'MFL Code' => '',
                'Tester Enrollment ID' => '',
                'Tester First Name' => '',
                'Tester Surname' => '',
                'Tester Other Name' => '',
                'Gender' => '',
                'Tester Mobile Number' => '',
                'Tester Email' => '',
                'Tester Address' => '',
                'Designation' => '',
                'Program' => '',
                'In Charge' => '',
                'In Charge Email' => '',
                'In Charge Phone' => '',
            ];
        }else{
            $query = str_replace("_TESTERS_", $testers, $query);
            $data = DB::select($query);
            
            foreach($data as $key => $value)
            {
                $tfirst_name = NULL;
                $tmiddle_name = NULL;
                $tlast_name = NULL;
                $tuid = NULL;
                $tphone = NULL;
                $temail = NULL;
                $tprog = NULL;
                $tdes = NULL;
                $facility = NULL;
                $mfl = NULL;
                $icharge = NULL;
                $iphone = NULL;
                $iemail = NULL;
                $tcounty = NULL;
                $tsub_county = NULL;
                $tgender = NULL;
                $taddress = NULL;
                foreach($value as $key2 => $value2)
                {
                    if(strcasecmp("County", $key2) == 0)
                        $tcounty = $value2;
                    if(strcasecmp("Sub County", $key2) == 0)
                        $tsub_county = $value2;
                    if(strcasecmp("Facility", $key2) == 0)
                        $facility = $value2;
                    if(strcasecmp("MFL Code", $key2) == 0)
                        $mfl = $value2;
                    if(strcasecmp("Tester Enrollment ID", $key2) == 0)
                        $tuid = $value2;
                    if(strcasecmp("Tester First Name", $key2) == 0)
                        $tfirst_name = $value2;
                    if(strcasecmp("Tester Surname", $key2) == 0)
                        $tlast_name = $value2;
                    if(strcasecmp("Tester Other Name", $key2) == 0)
                        $tmiddle_name = $value2;
                    if(strcasecmp("Gender", $key2) == 0)
                        $tgender = $value2;
                    if(strcasecmp("Tester Mobile Number", $key2) == 0)
                        $tphone = $value2;
                    if(strcasecmp("Tester Email", $key2) == 0)
                        $temail = $value2;
                    if(strcasecmp("Tester Address", $key2) == 0)
                        $taddress = $value2;
                    if(strcasecmp("Designation", $key2) == 0)
                        $tdes = $value2;
                    if(strcasecmp("Program", $key2) == 0)
                        $tprog = $value2;
                    if(strcasecmp("In Charge", $key2) == 0)
                        $icharge = $value2;
                    if(strcasecmp("In Charge Email", $key2) == 0)
                        $iemail = $value2;
                    if(strcasecmp("In Charge Phone", $key2) == 0)
                        $iphone = $value2;
                }
                $summary[] = [
                    
                    'County' => Facility::where('code', $mfl)->orderBy('name', 'asc')->first()->subCounty->county->name,
                    'Sub County' => Facility::where('code', $mfl)->orderBy('name', 'asc')->first()->subCounty->name,
                    'Facility' => $facility,
                    'MFL Code' => $mfl,
                    'Tester Enrollment ID' => $tuid,
                    'Tester First Name' => $tfirst_name,
                    'Tester Surname' => $tlast_name,
                    'Tester Other Name' => $tmiddle_name,
                    'Gender' => $tgender,
                    'Tester Mobile Number' => $tphone,
                    'Tester Email' => $temail,
                    'Tester Address' => $taddress,
                    'Designation' => $tdes,
                    'Program' => $tprog,
                    'In Charge' => $icharge,
                    'In Charge Email' => $iemail,
                    'In Charge Phone' => $iphone,
                ];
            }
        }

        return $summary;
    }

    /**
     * Batch registration and enrollment
     *
     */
    public function batchRegisterAndEnrol(Request $request)
    {
        $rId = $request->id;
        $id = Round::find($rId)->name;
        $exploded = explode(',', $request->excel);
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
            $folder = '/batch/'.$id.'/'.$county.'/';
        }
        else
            $folder = '/batch/'.$id.'/nphls/';
        if(!is_dir(public_path().$folder))
            File::makeDirectory(public_path().$folder, 0777, true);
        file_put_contents(public_path().$folder.$fileName, $decoded);

        //  get today's date
        $today = Carbon::today();
        //  Handle the import
        //  Get the results
        //  Import a user provided file
        //  Convert file to csv
        if(Auth::user()->isCountyCoordinator())
            $loadedFile = Excel::load('public/batch/'.$id.'/'.$county.'/'.$fileName, function($reader) {$reader->ignoreEmpty();})->get();
        else
            $loadedFile = Excel::load('public/batch/'.$id.'/nphls/'.$fileName, function($reader) {$reader->ignoreEmpty();})->get();
        if(!empty($loadedFile) && $loadedFile->count())
        {
            $duplicates = array();
            foreach($loadedFile as $sheet){
                foreach ($sheet->toArray() as $harvey => $specter)
                {
                    if(!empty($specter))
                    {
                        $county = NULL;
                        $sub_county = NULL;
                        $facility = NULL;
                        $mfl = NULL;
                        $uid = NULL;
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
                            if(strcmp($mike, "tester_enrollment_id") === 0)
                                $uid = $ross;
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

                        $testerDes = $tdes;
                        if($testerDes)
                        {
                            $tdes = Designation::idByTitle($tdes);
                            if(!$tdes)
                            {
                                $des = new Designation;
                                $des->name = $testerDes;
                                $des->save();
                                $tdes = $des->id;
                            }
                        }

                        if(strcmp($county, "MURANGA") === 0)
                            $county = "Murang'a";
                        if(strcmp($county, "HOMABAY") === 0)
                            $county = "Homa Bay";
                        if(strcmp($county, "THARAKA-NITHI") === 0)
                            $county = "Tharaka Nithi";

                        if ($mfl == 'NULL'&& ''){
                            // $missing_facilities[] = array($tfname, $tsname, $mfl, 'Missing Facility');
                            $facilityId = 0;
                            $missing_facilities = array($tfname, $tsname, $mfl, 'Missing Facility');
                            array_push($duplicates, $missing_facilities);
                        }
                        else{
                            $facilityId = Facility::idByCode($mfl);
                        }

                            if($uid)
                            {
                                $user = User::find(User::idByUid($uid));
                                if(!$user)
                                {
                                    $user = new User;
                                    $user->uid = $uid;
                                }
                            }
                            else
                            {
                                $userId = User::idByPhone($tphone);
                                if($userId)
                                {
                                    $user = User::find($userId);
                                    $user->uid = $user->uid;
                                }
                                else
                                {
                                    $user = new User;
                                    $user->date_registered = $today;
                                    $count = count(User::where('uid', User::MIN_UNIQUE_ID)->get());
                                    if($count > 0)
                                        $user->uid = DB::table('users')->where('uid', '>=', User::MIN_UNIQUE_ID)->max('uid')+1;
                                    else
                                        $user->uid = User::MIN_UNIQUE_ID;
                                    
                                }
                            }
                            //  process user details
                            if($tfname && $tsname && $tphone && $temail && $tprog && $tdes)
                            {   
                                if ($tphone == 'NULL' || $tphone == '') {
                                    $duplicateParticapant = array($tfname, $tsname, $tphone, 'Missing Phone Number');
                                    $duplicates[] = $duplicateParticapant;
                                }
                                if(count(User::where('phone', $tphone)->get()) > 0){
                                    $duplicateParticapant = array($tfname, $tsname, $tphone, 'Phone already taken');
                                    $duplicates[] = $duplicateParticapant;
                                    continue;
                                }
                                $user->name = $tfname. " ".$toname. " ".$tsname;
                                $user->first_name = $tfname;
                                $user->middle_name = $toname;
                                $user->last_name = $tsname;
                                $user->gender = $tgender;
                                $user->email = $temail;
                                $user->phone = $tphone;
                                $user->address = $taddress;
                                $user->username = uniqid();
                                $user->phone_verified = 1;
                                $user->email_verified = 1;
                                $user->save();
                                $user->username = $user->uid;
                                $user->password = Hash::make(User::DEFAULT_PASSWORD);
                                $user->save();
                                $userId = $user->id;

                                //  Prepare to save facility details
                                if ($facilityId >0) {
                                    
                                    $fc = Facility::find($facilityId);
                                    $fc->in_charge = $incharge;
                                    $fc->in_charge_phone = $iphone;
                                    $fc->in_charge_email = $iemail;
                                    $fc->save();
                                }
                                //  Prepare to save role-user details
                                $roleId = Role::idByName('Participant');
                                $user->detachAllRoles();
                                DB::table('role_user')->insert(['user_id' => $userId, 'role_id' => $roleId, 'tier' => $facilityId, 'program_id' => Program::idByTitle($tprog), "designation" => $tdes]);
                        }                            
                    }
                }
            }
            return response()->json(array('errors' => $duplicates));
        }
    }

    /**
     * Get Lots for the specified round.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $roundID
     * @return \Illuminate\Http\Response
     */
    public function getLots(Request $request, $roundID)
    {
        $round = Round::find($roundID);
        return response()->json($round->lots->all());
    }

    public function addParticipantForm($pdf, $size, $roundID, $participantID){

    	$enrolment = Enrol::where('round_id', $roundID)->where('user_id', $participantID)->first();

    	$templatePage1 = $pdf->importPage(1);
    	$pdf->AddPage('P', [$size['w'], $size['h']]);
    	$pdf->useTemplate($templatePage1);

    	$pdf->SetFont('Helvetica', '', 7.5, '', 'default', true);
    	$pdf->SetTextColor(50, 50, 50);

    	$participant = $enrolment->user()->withTrashed()->get()[0];
        $pdf->SetXY(35, 20.5);
    	$pdf->Write(0, $participant['first_name']." ".$participant['middle_name']." ".$participant['last_name']);

    	$facility = $enrolment->facility()->get()[0];
    	$pdf->SetXY(35, 25.25);
        $pdf->Write(0, $facility['name']);

        $subCounty = SubCounty::find($facility['sub_county_id']);
        $pdf->SetXY(35, 31.25);
        $pdf->Write(0, $subCounty->name);

        $county = County::find($subCounty->county_id);
        $pdf->SetXY(35, 36.75);
        $pdf->Write(0, $county->name);

        $pdf->SetXY(35, 42.75);
        $pdf->Write(0, $participant['phone']);

    	$pdf->SetFont('Times', '', 13, '', 'default', true);
        $round = Round::find($enrolment->round_id);
        $pdf->SetXY(39, 75);
        $pdf->Write(0, $round->name);

    	if(User::find($participant->id) !== null){
            $program = Program::find(User::find($participant->id)->ru()->program_id);
    	    $pdf->SetXY(95, 75.25);
    	    $pdf->Write(0, isset($program)?$program->name:'');
    	}

    	$uid = str_pad($participant['uid'], 6, "0");
    	for($i=0;$i<strlen($uid);$i++){
            $pdf->SetXY(150 + $i * 7, 75.25);
            $pdf->Write(0, substr($uid, $i, 1));
    	}

        $enrolID = str_pad($enrolment->id, 6, "0");
        for($i=0;$i<strlen($enrolID);$i++){
            $pdf->SetXY(151 + $i * 6.5, 276.5);
            $pdf->Write(0, substr($enrolID, $i, 1));
        }

    	$templatePage2 = $pdf->importPage(2);
        $pdf->AddPage('P', [$size['w'], $size['h']]);
        $pdf->useTemplate($templatePage2);

        for($i=0;$i<strlen($uid);$i++){
            $pdf->SetXY(152 + $i * 7, 25.25);
            $pdf->Write(0, substr($uid, $i, 1));
        }

        for($i=0;$i<strlen($enrolID);$i++){
            $pdf->SetXY(151 + $i * 6.5, 276.5);
            $pdf->Write(0, substr($enrolID, $i, 1));
        }

    	$pdf->SetFont('Times', 'B', 7.75, '', 'default', true);
    	$yPos = 91.25;
    	for($i=0;$i<6;$i++){
            $yPos = $yPos + 4;
            $pdf->SetXY(17.5, $yPos);
    	    $pdf->Write(0, "KNEQAS");
    	    $yPos = $yPos + 4;
            $pdf->SetXY(15, $yPos);
    	    $pdf->Write(0,"HIVSER-{$round->name}-S".($i+1));
    	    $yPos = $yPos + 5.75;
    	}

    	return $pdf;
    }

    public function getParticipantForm(Request $request, $roundID, $participantID){
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->setSourceFile("img/Participant-Form.pdf");
        $size = ['w' => 209.97333686111, 'h' => 296.92599647222];

        $pdf = $this->addParticipantForm($pdf, $size, $roundID, $participantID);
        $pdf->Output();
    }

    public function getParticipantForms(Request $request, $roundID){
        $pdf = new \setasign\Fpdi\Fpdi();
    	$size = ['w' => 209.97333686111, 'h' => 296.92599647222];
    	$enrolments = [];

    	if (Auth::user()->can(['generate-participant-result-form'], true)){
    	    if($request->has('facility')){
                $enrolments = Enrol::where('round_id', $roundID)->where('facility_id', $request->get('facility'))->get();
            }else if($request->has('sub_county')){
                $facilities = SubCounty::find($request->get('sub_county'))->facilities()->get()->pluck('id');
                $enrolments = Enrol::where('round_id', $roundID)->whereIn('facility_id', $facilities)->get();
            }else if($request->has('county')){
                $subCounties = County::find($request->get('county'))->subCounties()->get()->pluck('id');
                $facilities = [];
                foreach($subCounties as $subCountyID){
            	    $subCountyFacilityIDs = SubCounty::find($subCountyID)->facilities()->get()->pluck('id')->toArray();
                    $facilities = array_merge($facilities, $subCountyFacilityIDs);
                }
                $enrolments = Enrol::where('round_id', $roundID)->whereIn('facility_id', $facilities)->get();
            }else{
    	        $enrolments = Enrol::where('round_id', $roundID)->get();
    	    }
    	}

    	if(count($enrolments) > 0){
            $pdf->setSourceFile("img/Participant-Form.pdf");
            foreach($enrolments as $enrolment){
                $pdf = $this->addParticipantForm($pdf, $size, $roundID, $enrolment['user_id']);
    	    }
    	}else{
    	    $pdf->setSourceFile("img/blank.pdf");
    	    $templatePage = $pdf->importPage(1);
    	    $pdf->AddPage('P', [$size['w'], $size['h']]);
    	    $pdf->useTemplate($templatePage);
    	    $pdf->SetFont('Times', 'B', 13, '', 'default', true);
    	    $pdf->SetTextColor(50, 50, 50);
    	    $pdf->SetXY(30, 50);
    	    if (Auth::user()->can(['generate-participant-result-form'], true)){
        		$pdf->Write(0, "Unfortunately, we've found no data meeting your set criteria!");
    	    }else{
                $pdf->Write(0, "Permission denied!");
    	    }
    	}
    	\Log::info("Attempt to generate PDF forms for ".count($enrolments)." participants by ". Auth::user()->id);

    	$pdf->Output();
    }

    public function getReceiptRecord(Request $request, $roundID){
    	$pdf = new App\MyPDF;
    	$pdf->AliasNbPages();
        $size = ['h' => 209.97333686111, 'w' => 296.92599647222];
        $enrolments = [];

        if (Auth::user()->can(['generate-pt-receipt-record'], true)){
            if($request->has('facility')){
                $enrolmentsRecord = Enrol::where('round_id', $roundID)->where('facility_id', $request->get('facility'));
            }else if($request->has('sub_county')){
                $facilities = SubCounty::find($request->get('sub_county'))->facilities()->get()->pluck('id');
                $enrolmentsRecord = Enrol::where('round_id', $roundID)->whereIn('facility_id', $facilities);
            }else if($request->has('county')){
                $subCounties = County::find($request->get('county'))->subCounties()->get()->pluck('id');
                $facilities = [];
                foreach($subCounties as $subCountyID){
                    $subCountyFacilityIDs = SubCounty::find($subCountyID)->facilities()->get()->pluck('id')->toArray();
                    $facilities = array_merge($facilities, $subCountyFacilityIDs);
                }
                $enrolmentsRecord = Enrol::where('round_id', $roundID)->whereIn('facility_id', $facilities);
            }else{
                $enrolmentsRecord = Enrol::where('round_id', $roundID);
            }

            if(Auth::user()->hasRole('County Coordinator')){
                $facilities = County::find(Auth::user()->ru()->tier)->facilities()->pluck('id');
        		$enrolmentsRecord = $enrolmentsRecord->whereIn('facility_id', $facilities);
    	    }

            if(Auth::user()->hasRole('Sub-County Coordinator')){
                $facilities = SubCounty::find(Auth::user()->ru()->tier)->facilities()->pluck('id');
                $enrolmentsRecord = $enrolmentsRecord->whereIn('facility_id', $facilities);
    	    }

            if(Auth::user()->hasRole('Partner')){
                $facilities = ImplementingPartner::find(Auth::user()->ru()->tier)->all_facilities()->pluck('id');
                $enrolmentsRecord = $enrolmentsRecord->whereIn('facility_id', $facilities);
            }
    	    $enrolments = $enrolmentsRecord->join('facilities','enrolments.facility_id', '=', 'facilities.id')
                            ->orderBy('facilities.sub_county_id')->orderBy('facilities.name')->get();
    	}

        $pdf->setSourceFile("img/PT-Receipt-Record.pdf");
        $templatePage1 = $pdf->importPage(1);
        $templatePage2 = $pdf->importPage(2);
    
    	if(count($enrolments) > 0){

            $pdf->AddPage('L', [$size['w'], $size['h']]);
            $pdf->useTemplate($templatePage1);

            $pdf->SetFont('Helvetica', 'B', 8.5, '', 'default', true);
            $pdf->SetTextColor(50, 50, 50);

    	    $row = 1;
    	    $currentY = 68;

            $round = Round::find($roundID);
            $pdf->SetXY(203, 50);
            $pdf->Write(0, $round->name);

    	    $this->writeReceiptRecordHeaders($pdf, 64);
    	    $pdf->SetFont('Helvetica', '', 7.5, '', 'default', true);

    	    $currentFacility = 0;

            foreach($enrolments as $enrolment){

                $facility = $enrolment->facility()->get()[0];
                $subCounty = SubCounty::find($facility['sub_county_id']);
                $county = County::find($subCounty->county_id);
        		$participant = $enrolment->user()->withTrashed()->get()[0];

        		if($currentFacility != $facility['id']){
                    if($currentFacility > 0){
        		        $pdf->SetXY(230, $currentY - 4);
        		        $pdf->Write(0, "_______________     ______________");
        		    }
                    $currentFacility = $facility['id'];
        		}

                $pdf->SetXY(20, $currentY);
                $pdf->Write(0, ($row++).".");

                $pdf->SetXY(26, $currentY);
                $pdf->Write(0, $county->name);

                $pdf->SetXY(56, $currentY);
                $pdf->Write(0, $subCounty->name);

                $pdf->SetXY(86, $currentY);
                $pdf->Write(0, $facility['name']);

                $pdf->SetXY(151, $currentY);
                $pdf->Write(0, $participant['first_name']." ".$participant['middle_name']." ".$participant['last_name']);

                $pdf->SetXY(206, $currentY);
        		$pdf->Write(0, $participant['phone']);

        		if($currentY > 184){
                    $pdf->AddPage('L', [$size['w'], $size['h']]);
        		    $pdf->useTemplate($templatePage2);
        		    $currentY = 27;
        		    $pdf->SetFont('Times', 'B', 13, '', 'default', true);
                    $pdf->SetXY(220, 15.5);
                    $pdf->Write(0, $round->name);
                    $pdf->SetFont('Helvetica', 'B', 8.5, '', 'default', true);
        		    $this->writeReceiptRecordHeaders($pdf, 23);
                    $pdf->SetFont('Helvetica', '', 7.5, '', 'default', true);
        		}else{
                    $currentY += 4;
        		}
            }
        }else{
            $pdf->AddPage('L', [$size['w'], $size['h']]);
            $pdf->useTemplate($templatePage1);
            $pdf->SetFont('Times', 'B', 13, '', 'default', true);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetXY(20, 66);
            if (Auth::user()->can(['generate-pt-receipt-record'], true)){
                $pdf->Write(0, "Unfortunately, we've found no data meeting your set criteria!");
            }else{
                $pdf->Write(0, "Permission denied!");
            }
        }
        \Log::info("Attempt to generate PDF forms for ".count($enrolments)." participants by ". Auth::user()->id);

        $pdf->Output();
    }

    public function writeReceiptRecordHeaders($file, $height){
    
        $file->SetXY(25, $height);
        $file->Write(0, "COUNTY");

        $file->SetXY(56, $height);
        $file->Write(0, "SUB-COUNTY");

        $file->SetXY(86, $height);
        $file->Write(0, "FACILITY");

        $file->SetXY(151, $height);
        $file->Write(0, "NAME");

        $file->SetXY(206, $height);
        $file->Write(0, "PHONE");

        $file->SetXY(230, $height);
        $file->Write(0, "RECEIVED BY");

        $file->SetXY(255, $height);
        $file->Write(0, "DATE");
    }
}
$excel = App::make('excel');
