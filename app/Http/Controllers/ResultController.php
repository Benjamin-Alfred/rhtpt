<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\AmendedPT;
use App\Pt;
use App\Result;
use App\Expected;
use App\Field;
use App\Option;
use App\User;
use App\Notification;
use App\Enrol;
use App\Round;
use App\County;
use App\SubCounty;
use App\Facility;
use App\Program;
use App\Panel;
use App\Material;
use App\EvaluatedResult;
use App\ImplementingPartner;
use App\SmsHandler;

use Auth;
use Jenssegers\Date\Date as Carbon;
use DB;
use PDF;

class ResultController extends Controller
{

    public function manageResult()
    {
        return view('result.index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $error = ['error' => 'No results found, please try with different keywords.'];
        $items_per_page = 100;

        $searchString = null;
        $roundID = $lotID = $countyID = $subCountyID = $facilityID = $resultsOrderIndex = 0;
        
        if($request->has('round')) $roundID = $request->get('round');

        if($request->has('lot')) $lotID = $request->get('lot');

        if($request->has('q')) $searchString = ['search'=>$request->get('q')];

        if($request->has('county')) $countyID = $request->get('county');

        if($request->has('sub_county')) $subCountyID = $request->get('sub_county');

        if($request->has('facility')) $facilityID = $request->get('facility');

        if($request->has('results_order')) $resultsOrderIndex = $request->get('results_order');
    
        if(Auth::user()->isCountyCoordinator())
        {
            $results = County::find(Auth::user()->ru()->tier)->results($searchString, $roundID, $countyID, $subCountyID, $facilityID);
        }
        else if(Auth::user()->isSubCountyCoordinator())
        {
            $results = SubCounty::find(Auth::user()->ru()->tier)->results($searchString, $roundID, $countyID, $subCountyID, $facilityID);
        }
        else if(Auth::user()->isFacilityInCharge())
        {
           $results = Facility::find(Auth::user()->ru()->tier)->results($searchString, $roundID, $countyID, $subCountyID, $facilityID);
        }
        else if(Auth::user()->isParticipant())
        {
            $results = Auth::user()->results($roundID);
        }
        else if(Auth::user()->isPartner())
        {
           $results = ImplementingPartner::find(Auth::user()->ru()->tier)->results($searchString, $roundID, $countyID, $subCountyID, $facilityID);
        }else if(Auth::user()->isSuperAdministrator())
        {
            //Get all participants
            $users = User::select('users.id')
                        ->join('role_user', 'users.id', '=', 'role_user.user_id')
                        ->join('facilities', 'role_user.tier', '=', 'facilities.id')
                        ->join('sub_counties', 'facilities.sub_county_id', '=', 'sub_counties.id')
                        ->join('counties', 'sub_counties.county_id', '=', 'counties.id')
                        ->where('role_id', 2);

            if($countyID > 0) $users = $users->where('county_id', $countyID);
            if($subCountyID > 0) $users = $users->where('sub_county_id', $subCountyID);
            if($facilityID > 0) $users = $users->where('facilities.id', $facilityID);

            $enrolments = $users->join('enrolments', 'users.id', 'enrolments.tester_id')
                                ->join('users AS panels', 'enrolments.user_id', 'panels.id');

            if(!is_null($searchString)){

                $enrolments = $enrolments->where(function($query) use ($searchString){

                            $query->where('users.name', 'LIKE', "%{$searchString['search']}%")
                                ->orWhere('users.first_name', 'LIKE', "%{$searchString['search']}%")
                                ->orWhere('users.middle_name', 'LIKE', "%{$searchString['search']}%")
                                ->orWhere('users.last_name', 'LIKE', "%{$searchString['search']}%")
                                ->orWhere('users.email', 'LIKE', "%{$searchString['search']}%")
                                ->orWhere('users.phone', 'LIKE', "%{$searchString['search']}%")
                                ->orWhere('users.uid', 'LIKE', "%{$searchString['search']}%")
                                ->orWhere('panels.uid', 'LIKE', "%{$searchString['search']}%");
                        });
            }

            if($roundID > 0) $enrolments = $enrolments->where('enrolments.round_id', $roundID);

            if($lotID > 0){
                $enrolments = $enrolments->join('lots', 'enrolments.round_id', 'lots.round_id')
                                ->where('lots.lot', '=', $lotID)
                                ->where(\DB::raw("lots.tester_id LIKE CONCAT('%', SUBSTR(panels.uid, -1), '%') AND enrolments.deleted_at "));
            }

            $results = $enrolments->join('pt','enrolments.id', '=', 'pt.enrolment_id')
                            ->whereNull('pt.deleted_at')
                            ->select(["users.*", "enrolments.*", "pt.*", "panels.uid AS panel_id"]);
        }

        // Additional result filters 
        if($request->has('result_status')) 
        {
            $results = $results->where('panel_status', $request->get('result_status'));
        }
        if($request->has('feedback_status')) 
        {     
            $results = $results->where('feedback', $request->get('feedback_status'));
        }
        if($request->has('reason_for_failure'))
        {
            $failureReason = '';
            switch(intval($request->get('reason_for_failure'))){
                case 1: $failureReason = 'pt.incorrect_results'; break;
                case 2: $failureReason = 'pt.wrong_algorithm'; break;
                case 3: $failureReason = 'pt.use_of_expired_kits'; break;
                case 4: $failureReason = 'pt.incomplete_kit_data'; break;
                case 5: $failureReason = 'pt.incomplete_results'; break;
                case 6: $failureReason = 'pt.dev_from_procedure'; break;
                case 7: $failureReason = 'pt.incomplete_other_information'; break;
            }

            $results = $results->where($failureReason, '=', '1')->where('pt.feedback', '=', '1');
        }

        $resultsOrder = ['pt.id', 'users.first_name', 'users.uid'];
        $results = $results->withTrashed()->orderBy('round_id', 'desc')->orderBy($resultsOrder[$resultsOrderIndex])->paginate($items_per_page);

        foreach($results as $result)
        {
            
            try {$result->tester = $result->first_name . " ";} catch (\Exception $e) {\Log::error($e);}
            try {$result->tester .= $result->middle_name . " ";} catch (\Exception $e) {\Log::error($e);}
            try {$result->tester .= $result->last_name;} catch (\Exception $e) {\Log::error($e);}
            
            try {$result->uid = $result->uid;} catch (\Exception $e) {\Log::error($e);}
            try {$result->rnd = Round::nameByID($result->round_id);} catch (\Exception $e) {\Log::error($e);}

            //particpants should not see the result feedback until it has been verified by the admin             
            if(!Auth::user()->isSuperAdministrator()){
                if ($result->panel_status != 3) {
                    $result->feedback = 2;
                }                
            }

            $result->user_role = Auth::user()->ru()->role_id;

            $pt = Pt::find($result->id);
            if(isset($pt)){
                $result->has_feedback = $pt->surveyResponses()->count();
            }else{
                $result->has_feedback = 0;
            }
        }

        $response = [
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'from' => $results->firstItem(),
                'to' => $results->lastItem()
            ],
            'user_role' => Auth::user()->ru()->role_id,
            'data' => $results
        ]; 

        return $results->count() > 0 ? response()->json($response) : $error;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {        
        //Check if round has been         
        \Log::info("Check if round has been");
        if ($request->get('tester_id') == "") {
            \Log::info("No tester_id");
            return response()->json(['1']);            
        } else
        {   
            $testerIDOnForm = "";

            if ($request->get('round_id') != "") {
                $round_id = $request->get('round_id');
            }else{
                //Get the latest round
                $round = Round::where('end_date', '>', Carbon::today())
                                ->where('start_date', '<', Carbon::today())->where('status', '=', 0)->first();
                if(isset($round->id)){
                    $round_id = $round->id;
                }else{
                    \Log::info("No active round");
                    return response()->json(['1']); // No active round
                }
            }

            $testUser = User::where('uid', $request->get('tester_id'))->first();
            if(isset($testUser->id)){
                $testerIDOnForm = $testUser->id;
            }else{
                \Log::info("The provided uid is faulty");
                return response()->json(['2']); // The provided uid is faulty
            }


            //Validation: Check if the user already submitted another result
            $multipleSubmissionAttempts = Enrol::where('tester_id', Auth::user()->id)
                                            ->where('status', '>', 0)->where('round_id', $round_id)->count();

            if ($multipleSubmissionAttempts > 0) {
                return response()->json(['4']);
            }

            $enrolment = Enrol::where('user_id', $testerIDOnForm)->where('round_id', $round_id)->first();
            
            //Validation: Check if the enrolment results have been submitted
            if ($enrolment->status ==1) {
                \Log::info("Enrolment status is 1");
                return response()->json(['3']);
            }else
            {
                //  If Pt entry exists reuse it
                $pt = Pt::firstOrNew(['enrolment_id' => $enrolment->id]);
                $pt->enrolment_id = $enrolment->id;
                $pt->panel_status = Pt::NOT_CHECKED;
                $pt->save();

                $enrolment->tester_id = Auth::user()->id;
                //update enrollment status to 1
                $enrolment->status = Enrol::DONE;
                $enrolment->save();
               
                //  Proceed to form-fields
                // get all fields and insert into results
                $fields = Field::all();
                $response = '';

                foreach ($fields as $field) {
                    $result = new Result;
                    $result->pt_id = $pt->id;
                    $result->field_id = $field->id;
                   
                   //loop through the results entered and get the response for each field
                    foreach ($request->all() as $key => $value)
                    {
                        if((stripos($key, 'token') !==FALSE) || (stripos($key, 'method') !==FALSE))
                            continue;
                        else if(stripos($key, 'field') !==FALSE)
                        {
                            $fieldId = (int)$this->strip($key);
                            if(is_array($value))
                              $value = implode(', ', $value);
                            

                            if ($field->id == $fieldId) {
                                $response = $value;
                                break;
                            }else if ($field->id != $fieldId) {
                                $response = '';

                            }                      
                        }                        
                    } 
                    // save the response for respective field
                    $result->response = $response;
                    $result->save();   
                }

                //  Send SMS
                $round = Round::find($pt->enrolment->round->id)->description;
                $message = Notification::where('template', Notification::RESULTS_RECEIVED)->first()->message;
                $message = $this->replace_between($message, '[', ']', $round);
                $message = str_replace(' [', ' ', $message);
                $message = str_replace(']', ' ', $message);

                $created = Carbon::today()->toDateTimeString();
                $updated = Carbon::today()->toDateTimeString();
                //  Time
                $now = Carbon::now('Africa/Nairobi');
                $bulk = DB::table('bulk')->insert(['notification_id' => Notification::RESULTS_RECEIVED, 'round_id' => $pt->enrolment->round->id, 'text' => $message, 'user_id' => $pt->enrolment->user->id, 'date_sent' => $now, 'created_at' => $created, 'updated_at' => $updated]);

                \Log::info("Saved result. Done By (users.id): ".Auth::user()->id." for PTID: {$pt->id}");
                \Log::info($request);
                return response()->json('Saved.');

             }
         }
    }

    /**
     * Fetch pt with related components for editing
     *
     * @param ID of the selected pt -  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $pt = Pt::find($id);
        //Get the currently active round
        $round = Round::where('end_date', '>', Carbon::today())
                                ->where('start_date', '<', Carbon::today())->where('status', '=', 0)->first();
        $tester = User::withTrashed()->find($pt->enrolment->user_id)->uid;
        $results = $pt->results;
        $response = [
            'pt' => $pt,
            'round' => $round,
            'tester' => $tester,
            'results' => $results,
            'pt_id'=>$pt->id
        ];
        return response()->json($response);
    }

    /*
    *   verify the result after reviewing
    */
    public function verify(Request $request)
    {
        $id = $request->pt_id;
        $user_id = Auth::user()->id;

        $result = Pt::where('id', $id)->where('panel_status', Pt::NOT_CHECKED)->first();

        if($result){

            $result->verified_by = $user_id;
            $result->panel_status = Pt::CHECKED;
            if($request->comment)
                $result->comment = $request->comment;
            $result->save();

            \Log::info("Verifying result. Done By (users.id): $user_id for PTID: $id");
            \Log::info($request);
    
            // Send SMS
            $round = Round::find($result->enrolment->round->id)->description;
            $message = Notification::where('template', Notification::RESULTS_RECEIVED)->first()->message;
            $message = $this->replace_between($message, '[', ']', $round);
            $message = str_replace(' [', ' ', $message);
            $message = str_replace(']', ' ', $message);
            
            $created = Carbon::today()->toDateTimeString();
            $updated = Carbon::today()->toDateTimeString();
            //  Time
            $now = Carbon::now('Africa/Nairobi');
            $bulk = DB::table('bulk')->insert(['notification_id' => Notification::RESULTS_RECEIVED, 'round_id' => $result->enrolment->round->id, 'text' => $message, 'user_id' => $result->enrolment->performer->id, 'date_sent' => $now, 'created_at' => $created, 'updated_at' => $updated]);
            
            //get the last id inserted and use it in the broadcast table
            $bulk_id = DB::getPdo()->lastInsertId(); 

            $recipients = NULL;
            $recipients = User::find($result->enrolment->performer->id)->phone;

            if($recipients)
            {
                $sms = new SmsHandler;

                // foreach($recipients as $recipient)
                // {
                    $responseMessage = $sms->sendMessage($recipients, $message);
                    //  Save the results
                    DB::table('broadcast')->insert(['number' => $recipients, 'bulk_id' => $bulk_id]);
                // }
     
            }
        }
        return response()->json($result);
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
        $pt = Pt::find($id);
        \Log::info("Update result. Done By (users.id): ".Auth::user()->id. " for PTID: $id");
        \Log::info($request);
        //  Proceed to form-fields
        foreach ($request->all() as $key => $value)
        {
            if((stripos($key, 'token') !==FALSE) || (stripos($key, 'method') !==FALSE))
                continue;
            else if(stripos($key, 'field') !==FALSE)
            {
                $fieldId = $this->strip($key);
                if(is_array($value))
                  $value = implode(', ', $value);

                $results = Result::where('pt_id', $pt->id)->get();

                foreach ($results as $result_key => $result) {

                   if ($result->field_id ==$fieldId) {
                        $result->response = $value;
                        $result->save();
                   }
                }
            }
            else if(stripos($key, 'comment') !==FALSE)
            {
                if($value)
                {
                    $result = Result::where('field_id', $key)->first();
                    $result->comment = $value;
                    $result->save();
                }
            }
        }    

        return response()->json(["Done"]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $pt = Pt::find($id);
        if($pt && ($pt->panel_status != 3 || ($pt->panel_status == 3 && $pt->enrolment->tester_id != $pt->enrolment->user_id))){
            $pt->enrolment->status = 0;
            $pt->enrolment->tester_id = $pt->enrolment->user_id;
            $pt->enrolment->save();
            $pt->delete();
            \Log::info("Result entry PTID $id deleted by USER ID ". Auth::user()->id);
        }else{
            \Log::info("Failed attempt by USER ID ".Auth::user()->id." to delete result entry PTID $id");
        }
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
        return response()->json(['done']);
    }

    /**
     * Sets the status of a panel to Submitted thus setting it up for re-evaluation.
     *
     * @param  int  $PTID
     * @return \Illuminate\Http\Response
     */
    public function reevaluate($PTID)
    {
        \Log::info("Submitting a panel (PTID: $PTID) for re-evaluation. Done by ". Auth::user()->id);

        $pt = Pt::find($PTID);

        \Log::info("Initial outcome:\n". json_encode($pt));

        $pt->panel_status = Pt::CHECKED;
        $pt->save();

        return response()->json(['done']);
    }

    /**
     * Receive a shipment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function receive(Request $request)
    {
        $this->validate($request, [
            'date_received' => 'required',
            'panels_received' => 'required',
            'condition' => 'required',
            'receiver' => 'required'
        ]);

        $create = Receipt::create($request->all());

        return response()->json($create);
    }
    /**
     * Remove the specified begining of text to get Id alone.
     *
     * @param  int  $id
     * @return Response
     */
    public function strip($field)
    {
            if(($pos = strpos($field, '_')) !== FALSE)
            return substr($field, $pos+1);
    }
    /**
     * Replace strings between two characters.
     *
     */
    function replace_between($str, $needle_start, $needle_end, $replacement) 
    {
        $pos = strpos($str, $needle_start);
        $start = $pos === false ? 0 : $pos + strlen($needle_start);

        $pos = strpos($str, $needle_end, $start);
        $end = $pos === false ? strlen($str) : $pos;

        return substr_replace($str, $replacement, $start, $end - $start);
    }

     /**
     * Verify results evaluted by a NON-Participant
     *
     * @param ID of the selected pt -  $id
     */
    public function evaluated_results($id)
    {
        //get actual results
        $pt = Pt::find($id);
        \Log::info("PT: ".json_encode($pt));
        $round_id = $pt->enrolment->round->id;
        $pt_results = $pt->results;
        $option = new Option;

        $pt_panel_1_kit1_results = '';
        $pt_panel_2_kit1_results = '';
        $pt_panel_3_kit1_results = '';;
        $pt_panel_4_kit1_results = '';
        $pt_panel_5_kit1_results = '';
        $pt_panel_6_kit1_results = '';

        $pt_panel_1_kit2_results = '';
        $pt_panel_2_kit2_results = '';
        $pt_panel_3_kit2_results = '';;
        $pt_panel_4_kit2_results = '';
        $pt_panel_5_kit2_results = '';
        $pt_panel_6_kit2_results = '';

        $determine = '';
        $determine_value = '';
        $determine_lot_no = '';
        $determine_expiry_date = '';

        $firstresponse = '';
        $firstresponse_value = '';
        $firstresponse_lot_no = '';
        $firstresponse_expiry_date = '';

        $date_received = '';
        $date_constituted = '';
        $date_tested = '';

        $expected_result_1 = '';
        $expected_result_2 = '';
        $expected_result_3 = '';
        $expected_result_4 = '';
        $expected_result_5 = '';
        $expected_result_6 = '';



        foreach ($pt_results as $rss) {
            //test kit 1 results
            if($rss->field_id == Field::idByUID('PT Panel 1 Test 1 Results')){
                $pt_panel_1_kit1_results = $option::nameByID($rss->response);
                $pt_panel_1_kit1_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 2 Test 1 Results')){
                $pt_panel_2_kit1_results = $option::nameByID($rss->response);
                $pt_panel_2_kit1_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 3 Test 1 Results')){
                $pt_panel_3_kit1_results = $option::nameByID($rss->response);
                $pt_panel_3_kit1_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 4 Test 1 Results')){
                $pt_panel_4_kit1_results = $option::nameByID($rss->response);
                $pt_panel_4_kit1_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 5 Test 1 Results')){
                $pt_panel_5_kit1_results = $option::nameByID($rss->response);
                $pt_panel_5_kit1_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 6 Test 1 Results')){
                $pt_panel_6_kit1_results = $option::nameByID($rss->response);
                $pt_panel_6_kit1_results_value = $rss->response;
            }

            //test kit 2 results
            if($rss->field_id == Field::idByUID('PT Panel 1 Test 2 Results')){
                $pt_panel_1_kit2_results = $option::nameByID($rss->response);
                $pt_panel_1_kit2_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 2 Test 2 Results')){
                $pt_panel_2_kit2_results = $option::nameByID($rss->response);
                $pt_panel_2_kit2_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 3 Test 2 Results')){
                $pt_panel_3_kit2_results = $option::nameByID($rss->response);
                $pt_panel_3_kit2_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 4 Test 2 Results')){
                $pt_panel_4_kit2_results = $option::nameByID($rss->response);
                $pt_panel_4_kit2_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 5 Test 2 Results')){
                $pt_panel_5_kit2_results = $option::nameByID($rss->response);
                $pt_panel_5_kit2_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 6 Test 2 Results')){
                $pt_panel_6_kit2_results = $option::nameByID($rss->response);
                $pt_panel_6_kit2_results_value = $rss->response;
            }
           
            //final results
            if($rss->field_id == Field::idByUID('PT Panel 1 Final Results')){
                $pt_panel_1_final_results = $option::nameByID($rss->response);
                $pt_panel_1_final_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 2 Final Results')){
                $pt_panel_2_final_results = $option::nameByID($rss->response);
                $pt_panel_2_final_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 3 Final Results')){
                $pt_panel_3_final_results = $option::nameByID($rss->response);
                $pt_panel_3_final_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 4 Final Results')){
                $pt_panel_4_final_results = $option::nameByID($rss->response);
                $pt_panel_4_final_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 5 Final Results')){
                $pt_panel_5_final_results = $option::nameByID($rss->response);
                $pt_panel_5_final_results_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('PT Panel 6 Final Results')){
                $pt_panel_6_final_results = $option::nameByID($rss->response);
                $pt_panel_6_final_results_value = $rss->response;
            }
           
            // //test kit 1 results
            if($rss->field_id == Field::idByUID('Test 1 Kit Name')){
                $determine = $option::nameByID($rss->response);
                $determine_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('Test 1 Lot No.'))
                $determine_lot_no = $rss->response;
            if($rss->field_id == Field::idByUID('Test 1 Expiry Date'))
                $determine_expiry_date = $rss->response;

            // //test kit 2 results
            if($rss->field_id == Field::idByUID('Test 2 Kit Name')){
                $firstresponse = $option::nameByID($rss->response);
                $firstresponse_value = $rss->response;
            }
            if($rss->field_id == Field::idByUID('Test 2 Lot No.'))
                $firstresponse_lot_no = $rss->response;
            if($rss->field_id == Field::idByUID('Test 2 Expiry Date'))
                $firstresponse_expiry_date = $rss->response;  

            //dates
            if($rss->field_id == Field::idByUID('Date PT Panel Received'))
                $date_received = $rss->response;
            if($rss->field_id == Field::idByUID('Date PT Panel Constituted'))
                $date_constituted = $rss->response;
            if($rss->field_id == Field::idByUID('Date PT Panel Tested'))
                $date_tested = $rss->response;

            //comment
             if($rss->field_id == Field::idByUID('Comments'))
                $tester_comments = $rss->response;
        }
        $actual_results = array();

        //get expected results
        $round = Round::find($round_id);
        $sample_1 = "PT-".$round->name."-S1";
        $sample_2 = "PT-".$round->name."-S2";
        $sample_3 = "PT-".$round->name."-S3";
        $sample_4 = "PT-".$round->name."-S4";
        $sample_5 = "PT-".$round->name."-S5";
        $sample_6 = "PT-".$round->name."-S6";

        $user = $pt->enrolment->user()->withTrashed()->first();
        $performer = $pt->enrolment->performer()->withTrashed()->first();

        $lot = User::lot($round_id, $user->uid);
        $expected_results = $lot->panels()->get();
        $reactive = Option::idByTitle('Reactive');
        $positive = "POSITIVE";
        $negative = "NEGATIVE";

        foreach ($expected_results as $ex_rslts) {

            if($ex_rslts->panel == 1){
                if($ex_rslts->result == Expected::EITHER){
                    if(Option::idByTitle($pt_panel_1_kit1_results) == $reactive){
                        $expected_result_1 = $positive;
                    }else{
                        $expected_result_1 = $negative;
                    }
                }else
                    $expected_result_1 = $ex_rslts->result($ex_rslts->result);
            }

            if($ex_rslts->panel == 2){
                if($ex_rslts->result == Expected::EITHER)
                    if(Option::idByTitle($pt_panel_2_kit1_results) == $reactive){
                        $expected_result_2 = $positive;
                    }else{
                        $expected_result_2 = $negative;
                    }
                else
                    $expected_result_2 = $ex_rslts->result($ex_rslts->result);
            }

            if($ex_rslts->panel == 3){
                if($ex_rslts->result == Expected::EITHER)
                    if(Option::idByTitle($pt_panel_3_kit1_results) == $reactive){
                        $expected_result_3 = $positive;
                    }else{
                        $expected_result_3 = $negative;
                    }
                else
                    $expected_result_3 = $ex_rslts->result($ex_rslts->result);
            }

            if($ex_rslts->panel == 4){
                if($ex_rslts->result == Expected::EITHER)
                    if(Option::idByTitle($pt_panel_4_kit1_results) == $reactive){
                        $expected_result_4 = $positive;
                    }else{
                        $expected_result_4 = $negative;
                    }
                else
                    $expected_result_4 = $ex_rslts->result($ex_rslts->result);
            }

            if($ex_rslts->panel == 5){
                if($ex_rslts->result == Expected::EITHER)
                    if(Option::idByTitle($pt_panel_5_kit1_results) == $reactive){
                        $expected_result_5 = $positive;
                    }else{
                        $expected_result_5 = $negative;
                    }
                else
                    $expected_result_5 = $ex_rslts->result($ex_rslts->result);
            }

            if($ex_rslts->panel == 6){
                if($ex_rslts->result == Expected::EITHER)
                    if(Option::idByTitle($pt_panel_6_kit1_results) == $reactive){
                        $expected_result_6 = $positive;
                    }else{
                        $expected_result_6 = $negative;
                    }
                else
                    $expected_result_6 = $ex_rslts->result($ex_rslts->result);
            }

        }

        //get participant details
        $participant_id = $performer->id;
        $user_name = $performer->name;
        $first_name = $performer->first_name;
        $middle_name = $performer->middle_name;
        $last_name = $performer->last_name;
        $phone_no = $performer->phone;
        $tester_id = $performer->username;
        $roleUser = $performer->ru();
        $facility = Facility::find($roleUser->tier);
        try{$designation = $performer->designation($roleUser->designation);}catch(\Exception $ex){$designation = "";}
        try{$program = Program::find($roleUser->program_id);}catch(\Exception $ex){$program = ['name' => ""];}

        $county = strtoupper($facility->subCounty->county->name);
        $sub_county = $facility->subCounty->name;
        $mfl = $facility->code;
        $facility_name = $facility->name;
        $facility_id = $facility->id;
        
        //combine expected and actual result into one array
        $all_results = array();
        $round_name = $round->name;
        $round_status = $round->status;
        $feedback = $pt->outcome($pt->feedback);
        $panel_status = $pt->panel_status;

        //get reasons for unsatisfactory
        $incorrect_results = $pt->incorrect_results;
        $incomplete_kit_data = $pt->incomplete_kit_data;
        $dev_from_procedure = $pt->dev_from_procedure;
        $incomplete_other_information = $pt->incomplete_other_information;
        $use_of_expired_kits = $pt->use_of_expired_kits;
        $invalid_results = $pt->invalid_results;
        $wrong_algorithm = $pt->wrong_algorithm;
        $incomplete_results = $pt->incomplete_results;

        $approver = User::find($pt->approved_by);
        $approvedBy = "";

        if(isset($approver->first_name)) $approvedBy = "{$approver->first_name} {$approver->last_name}";

        if($pt->feedback == Pt::UNSATISFACTORY)
            $remark = $pt->unsatisfactory(); 
        else
            $remark = 'None';
        
         $all_results = array( 
            //user details
            'round_name'=> $round_name,
            'round_status'=>$round_status, 
            'round_published_at'=>$round->published_at, 
            'feedback' => $feedback, 
            'remark' => $remark, 
            'panel_status' => $panel_status, 
            'pt_id' => $pt->id,
            'pt_approved_comment' => $pt->approved_comment,
            'date_approved' => $pt->date_approved,
            'approved_by' => $approvedBy,
            'participant_id' => $participant_id,
            'user_name' => $user_name,
            'first_name' => $first_name,
            'middle_name' => $middle_name,
            'last_name' => $last_name,
            'phone_no' => $phone_no,
            'tester_id' => $tester_id,
            'new_tester_id' => $tester_id,
            'tester_id_on_panel' => $user->uid,
            'designation' => $designation,
            'program' => $program,
            'program_name' => isset($program->name)?$program->name:"",
            'county' => $county,
            'sub_county' => $sub_county,
            'facility' => $facility_name,
            'facility_id' => $facility_id,
            'mfl' => $mfl,

            //material details
            'date_received' =>$date_received,
            'date_constituted' =>$date_constituted,
            'date_tested' =>$date_tested,

            //panel info
            'determine' =>$determine,
            'determine_value' =>$determine_value,
            'determine_lot_no' =>$determine_lot_no,
            'determine_expiry_date' =>$determine_expiry_date,
            'firstresponse' =>$firstresponse,
            'firstresponse_value' =>$firstresponse_value,
            'firstresponse_lot_no' =>$firstresponse_lot_no,
            'firstresponse_expiry_date' =>$firstresponse_expiry_date,

            //results
            //test 1 results
            //name
            "pt_panel_1_kit1_results"=>$pt_panel_1_kit1_results, 
            "pt_panel_2_kit1_results"=>$pt_panel_2_kit1_results,
            "pt_panel_3_kit1_results"=>$pt_panel_3_kit1_results,
            "pt_panel_4_kit1_results"=>$pt_panel_4_kit1_results,
            "pt_panel_5_kit1_results"=>$pt_panel_5_kit1_results,
            "pt_panel_6_kit1_results"=>$pt_panel_6_kit1_results,

            //value 
            "pt_panel_1_kit1_results_value"=>$pt_panel_1_kit1_results_value, 
            "pt_panel_2_kit1_results_value"=>$pt_panel_2_kit1_results_value,
            "pt_panel_3_kit1_results_value"=>$pt_panel_3_kit1_results_value,
            "pt_panel_4_kit1_results_value"=>$pt_panel_4_kit1_results_value,
            "pt_panel_5_kit1_results_value"=>$pt_panel_5_kit1_results_value,
            "pt_panel_6_kit1_results_value"=>$pt_panel_6_kit1_results_value,

            //test 2 results
            //name
            "pt_panel_1_kit2_results"=>$pt_panel_1_kit2_results, 
            "pt_panel_2_kit2_results"=>$pt_panel_2_kit2_results,
            "pt_panel_3_kit2_results"=>$pt_panel_3_kit2_results,
            "pt_panel_4_kit2_results"=>$pt_panel_4_kit2_results,
            "pt_panel_5_kit2_results"=>$pt_panel_5_kit2_results,
            "pt_panel_6_kit2_results"=>$pt_panel_6_kit2_results,

            //value
            "pt_panel_1_kit2_results_value"=>$pt_panel_1_kit2_results_value, 
            "pt_panel_2_kit2_results_value"=>$pt_panel_2_kit2_results_value,
            "pt_panel_3_kit2_results_value"=>$pt_panel_3_kit2_results_value,
            "pt_panel_4_kit2_results_value"=>$pt_panel_4_kit2_results_value,
            "pt_panel_5_kit2_results_value"=>$pt_panel_5_kit2_results_value,
            "pt_panel_6_kit2_results_value"=>$pt_panel_6_kit2_results_value,

            //final tested results
            //name
            "pt_panel_1_final_results"=>$pt_panel_1_final_results, 
            "pt_panel_2_final_results"=>$pt_panel_2_final_results,
            "pt_panel_3_final_results"=>$pt_panel_3_final_results,
            "pt_panel_4_final_results"=>$pt_panel_4_final_results,
            "pt_panel_5_final_results"=>$pt_panel_5_final_results,
            "pt_panel_6_final_results"=>$pt_panel_6_final_results,

            //value
            "pt_panel_1_final_results_value"=>$pt_panel_1_final_results_value, 
            "pt_panel_2_final_results_value"=>$pt_panel_2_final_results_value,
            "pt_panel_3_final_results_value"=>$pt_panel_3_final_results_value,
            "pt_panel_4_final_results_value"=>$pt_panel_4_final_results_value,
            "pt_panel_5_final_results_value"=>$pt_panel_5_final_results_value,
            "pt_panel_6_final_results_value"=>$pt_panel_6_final_results_value,

            //expected results
            "expected_result_1"=>$expected_result_1, 
            "expected_result_2"=>$expected_result_2,
            "expected_result_3"=>$expected_result_3,
            "expected_result_4"=>$expected_result_4,
            "expected_result_5"=>$expected_result_5,
            "expected_result_6"=>$expected_result_6,
            "tester_comments"=>$tester_comments,
            
            //sample name
            "sample_1"=>$sample_1, 
            "sample_2"=>$sample_2,
            "sample_3"=>$sample_3,
            "sample_4"=>$sample_4,
            "sample_5"=>$sample_5,
            "sample_6"=>$sample_6,

            'incorrect_results' => $incorrect_results,
            'incomplete_kit_data' => $incomplete_kit_data,
            'dev_from_procedure' => $dev_from_procedure,
            'incomplete_other_information' => $incomplete_other_information,
            'use_of_expired_kits' => $use_of_expired_kits,
            'invalid_results' => $invalid_results,
            'wrong_algorithm' => $wrong_algorithm,
            'incomplete_results' => $incomplete_results,

            'amendments' => $pt->amendments()->with('amendor')->get()
        );

        return $all_results;       
    }

    /**
     * Verify results evaluted by a NON-Participant
     *
     * @param ID of the selected pt -  $id
     */
    public function show_evaluated_results($id)
    {
        $data = $this->evaluated_results($id);
        return response()->json($data);

    }    

     /**
     * Save the verification of evaluated results
     *
     * @param ID of the selected pt -  $id
     */
    public function verify_evaluated_results(Request $request, $id )
    {
        $pt_id = $request->pt_id;
        $user_id = Auth::user()->id; 

        $result = Pt::find($id);
        $result->panel_status = Pt::VERIFIED;
        $result->date_approved = Carbon::today()->toDateTimeString();
        $result->approved_by = $user_id;
        if ($request) {
            $result->approved_comment = $request->comment;            
        }
        $result->save();

         //  Send SMS
        $round = Round::find($result->enrolment->round->id)->description;
        $message = Notification::where('template', Notification::FEEDBACK_RELEASE)->first()->message;
        $message = $this->replace_between($message, '[', ']', $round);
        $message = str_replace(' [', ' ', $message);
        $message = str_replace(']', ' ', $message);

        $created = Carbon::today()->toDateTimeString();
        $updated = Carbon::today()->toDateTimeString();
        //  Time
        $now = Carbon::now('Africa/Nairobi');
        $bulk = DB::table('bulk')->insert(['notification_id' => Notification::FEEDBACK_RELEASE, 'round_id' => $result->enrolment->round->id, 'text' => $message, 'user_id' => $result->enrolment->user->id, 'date_sent' => $now, 'created_at' => $created, 'updated_at' => $updated]);
        $recipients = NULL;
        $recipients = User::find($result->enrolment->performer->id)->value('phone');
        $ptUser = User::find($result->enrolment->performer->id);
        $ptUserName = $ptUser->first_name . " " . $ptUser->last_name;
        $message = str_replace("PT Participant", $ptUserName, $message);

        $smsHandler = new SmsHandler();
        $smsHandler->sendMessage($ptUser->phone, $message);
        \Log::info("Verified pt and sent feedback report sms to $ptUserName ".$ptUser->phone." -- Performed by $user_id");

        return response()->json($result);
    }

     /**
     * Update the evaluated results
     *
     * @param ID of the selected pt -  $id
     */
    public function update_evaluated_results(Request $request, $id )
    {
        //Get participant

        $participant = User::find($request->participant_id);

        DB::table('role_user')->where('user_id', $request->participant_id)->update(['tier' => $request->facility_id, 'program_id' => $request->program_id]);
        
        $pt = Pt::find($id);

        \Log::info("Update Evaluated Results: PTID - $id");
        \Log::info("From:");
        \Log::info($pt);
        \Log::info("To:");
        \Log::info($request);

        $enrolment = Enrol::find($pt->enrolment_id);
        $enrolment->tester_id = User::idByUID($request->new_tester_id);
        $enrolment->save();

        //save previous data

        $evaluated = $this->evaluated_results($pt->id);
        $user_id = Auth::user()->id;

        $old_details = new EvaluatedResult; 
        $old_details->pt_id = $pt->id; 
        $old_details->participant_id = $evaluated['participant_id'];         
        $old_details->user_id = $user_id;         
        $old_details->reason_for_change = $request->reason_for_change;         
        $old_details->results = json_encode($evaluated); 
        $old_details->save();
        
        //save updated pt details

        $pt->incorrect_results = isset($request->incorrect_results)?$request->incorrect_results:0;

        $pt->incomplete_kit_data = isset($request->incomplete_kit_data)?$request->incomplete_kit_data:0;

        $pt->dev_from_procedure = isset($request->dev_from_procedure)?$request->dev_from_procedure:0;

        $pt->incomplete_other_information = isset($request->incomplete_other_information)?$request->incomplete_other_information:0;

        $pt->use_of_expired_kits = isset($request->use_of_expired_kits)?$request->use_of_expired_kits:0;

        $pt->invalid_results = isset($request->invalid_results)?$request->invalid_results:0;

        $pt->wrong_algorithm = isset($request->wrong_algorithm)?$request->wrong_algorithm:0;

        $pt->incomplete_results = isset($request->incomplete_results)?$request->incomplete_results:0;

        $pt->feedback = $request->feedback == 1?$request->feedback:0;
        
        $pt->save();

        //save result details

        foreach ($request->all() as $key => $value)
        {
            if((stripos($key, 'token') !==FALSE) || (stripos($key, 'method') !==FALSE))
                continue;
            else if(stripos($key, 'field') !==FALSE)
            { 
                $fieldId = $this->strip($key);
                if(is_array($value))
                  $value = implode(', ', $value);

                $results = Result::where('pt_id', $pt->id)->get();

                foreach ($results as $result_key => $result) {

                   if ($result->field_id ==$fieldId) {
                        $result->response = $value;
                        $result->save();
                   }
                }
            }            
        }

        
        return response()->json($result);
    }
        
     /**
     * Amend Test Report
     *
     * @param ID of the selected pt -  $id
     */
    public function amendTestReport(Request $request, $id )
    {

        $pt = Pt::find($id);

        \Log::info("Amending PT Report: PTID - $id");
        \Log::info("From:");
        \Log::info($pt);
        \Log::info("To:");
        \Log::info($request);

        // Deactive previous amended reports
        $amendedReports = AmendedPT::where('pt_id', $id)->where('status', AmendedPT::ACTIVE)->get();
        foreach ($amendedReports as $amendedReport) {
            $amendedReport->status = AmendedPT::DEACTIVATED;
            $amendedReport->save();
        }

        // Save amended PT report
        $amendPTReport = new AmendedPT;

        $amendPTReport->pt_id = $id;
        $amendPTReport->feedback = $request->feedback == 1?$request->feedback:0;
        $amendPTReport->incorrect_results = isset($request->incorrect_results)?$request->incorrect_results:0;
        $amendPTReport->incomplete_kit_data = isset($request->incomplete_kit_data)?$request->incomplete_kit_data:0;
        $amendPTReport->dev_from_procedure = isset($request->dev_from_procedure)?$request->dev_from_procedure:0;
        $amendPTReport->incomplete_other_information = isset($request->incomplete_other_information)?$request->incomplete_other_information:0;
        $amendPTReport->use_of_expired_kits = isset($request->use_of_expired_kits)?$request->use_of_expired_kits:0;
        $amendPTReport->invalid_results = isset($request->invalid_results)?$request->invalid_results:0;
        $amendPTReport->wrong_algorithm = isset($request->wrong_algorithm)?$request->wrong_algorithm:0;
        $amendPTReport->incomplete_results = isset($request->incomplete_results)?$request->incomplete_results:0;
        $amendPTReport->reason_for_amendment = $request->reason_for_amendment;
        $amendPTReport->amended_by = Auth::user()->id;
        $amendPTReport->save();

        $returnValue = response()->json($amendPTReport);
        \Log::info($returnValue);
        
        //Update feedback in PT table
        \Log::info("PT ID $id result amended by User ID: ". Auth::user()->id . " from " . $pt->feedback . " to ". $amendPTReport->feedback);
        $pt->feedback = $amendPTReport->feedback;
        $pt->save();

        // Notify the user via SMS
        $smsHandler = new SmsHandler;
        $ptUser = $pt->enrolment->user;
        $round = $pt->enrolment->round;
        $message = "Dear {$ptUser->name}, following a review of your results, your Round {$round->name} PT report has been amended. Kindly login to your account to download it.";
        $smsHandler->sendMessage($ptUser->phone, $message, true);
        \Log::info("Amended report sms sent to User ID:{$ptUser->id}:{$ptUser->name} {$ptUser->phone}. -- Performed by ".Auth::user()->id);


        return $returnValue;
    }
        
    /**
     * Fetch feedback for the given id
     *
     * @param ID of the selected pt -  $id
     */

    public function show_updated_evaluated_results($id){

        $current_evaluated = $this->evaluated_results($id);
        $old = EvaluatedResult::select('evaluated_results.*')->where('pt_id', $id)->orderBy('id', 'DESC')->first();

        if(isset($old) && count($old->get()) > 0){
            $old_results = json_decode($old->results, true);
            $old_results['reason_for_change'] = $old->reason_for_change;
            $old_results['editing_user_name'] = User::find($old->user_id)->name;
            $old_results['editing_updated_at'] = date($old->updated_at);
        }else{
            $old_results['response'] = "No previous results found!";
        }

        return response()->json($old_results);
    }

    /**
     * Fetch feedback for the given id
     *
     * @param ID of the selected pt -  $id
     */

    public function print_result($id){
      $data = $this->evaluated_results($id);
      $pt = Pt::find($id);
      $round = $pt->enrolment->round->id;

      $summaries = DB::select("SELECT r.id, r.description AS 'round', COUNT(DISTINCT e.user_id) AS 'enrolment', COUNT(DISTINCT pt.id) AS 'response', SUM(feedback=0) AS 'satisfactory', SUM(feedback=1) AS 'unsatisfactory' FROM rounds r INNER JOIN enrolments e ON r.id=e.round_id LEFT JOIN pt ON e.id=pt.enrolment_id WHERE ISNULL(r.deleted_at) AND ISNULL(e.deleted_at) AND e.round_id = '$round' GROUP BY r.id");

      $tally = [];
      foreach ($summaries as $summary) {
          $tally['enrolment'] = $summary->enrolment;
          $tally['response'] = $summary->response;
          $tally['satisfactory'] = $summary->satisfactory;
          $tally['unsatisfactory'] = $summary->unsatisfactory;
      }

        $unsperf = DB::select("SELECT r.id, r.description AS 'round', COUNT(DISTINCT pt.id) AS 'response', SUM(feedback=0) AS 'total_unsatisfactory', concat(round(( SUM(feedback=1)/COUNT(DISTINCT pt.id) * 100 ),2),'%') AS 'unsatisfactory', SUM(incomplete_kit_data=1) AS 'incomplete_kit_data', SUM(incorrect_results=1) AS 'incorrect_results', SUM(wrong_algorithm=1) AS 'wrong_algorithm', SUM(dev_from_procedure=1) AS 'deviation_from_procedure', SUM(incomplete_other_information=1) AS 'incomplete_other_information', SUM(use_of_expired_kits=1) AS 'use_of_expired_kits', SUM(invalid_results=1) AS 'invalid_results', SUM(incomplete_results=1) AS 'incomplete_results' FROM rounds r INNER JOIN enrolments e ON r.id=e.round_id LEFT JOIN pt ON e.id=pt.enrolment_id WHERE ISNULL(r.deleted_at) AND ISNULL(e.deleted_at) AND e.round_id = '$round' GROUP BY r.id;");

      $reasons = [];
      foreach ($unsperf as $performance) {
          $reasons['response'] = $performance->response;
          $reasons['incomplete_kit_data'] = $performance->incomplete_kit_data;
          $reasons['incorrect_results'] = $performance->incorrect_results;
          $reasons['wrong_algorithm'] = $performance->wrong_algorithm;
          $reasons['deviation_from_procedure'] = $performance->deviation_from_procedure;
          $reasons['incomplete_other_information'] = $performance->incomplete_other_information;
          $reasons['use_of_expired_kits'] = $performance->use_of_expired_kits;
          $reasons['invalid_results'] = $performance->invalid_results;
          $reasons['incomplete_results'] = $performance->incomplete_results;
      }

      //display final report when the round is over
      if ($data['round_status'] ==0) {      
          if(\request('type') == 0){//satisfactory

              $pdf = PDF::loadView('result/feedbackreports/final/satisfactory', compact('data','tally','reasons'));
          }

            if(\request('type') == 1){//unsatisfactory

                $pt = Pt::where('id',$id)->first();

                $pdf = PDF::loadView('result/feedbackreports/final/unsatisfactory', compact('data','pt', 'tally', 'reasons'));
            }
        }

        //display preliminary results
        else{
            if(\request('type') == 0){//satisfactory

              $pdf = PDF::loadView('result/feedbackreports/preliminary/satisfactory', compact('data'));
          }

            if(\request('type') == 1){//unsatisfactory

                $pt = Pt::where('id',$id)->first();

                $pdf = PDF::loadView('result/feedbackreports/preliminary/unsatisfactory', compact('data','pt'));
            }
        }

        $pt = Pt::find($id);
        $user = User::find($pt->enrolment->tester_id);
        \Log::info("PT Report for UID: ".$user->uid." downloaded by User ID: ". Auth::user()->id);
        if ($pt->download_status == 0 && Auth::user()->id==$user->id) {
            $pt->download_status = Pt::DOWNLOAD_STATUS;
            $pt->save();
        }

        //if request is a view report
        if (\request('view') == 1) {
            return view('result/feedbackreports/index', compact('data', 'pt'));

        }else{
        //if request is a download
            return $pdf->download('Round '.$data['round_name'].' Results.pdf');
        }

    }

    public function feedback($id)
    {
        $pt = Pt::find($id);
        $usr = User::find($pt->enrolment->tester_id);
        $pt->uid = (string)$usr->uid;
        $pt->tester = $usr->first_name . " " . $usr->middle_name . " " . $usr->last_name;
        try {
            $ru = $usr->ru();
        } catch (Exception $e) {
            $ru['tier'] = "";
            $ru['program_id'] = "";
        }
        $pt->program = Program::find($ru->program_id)->name;
        $facility = Facility::find($ru->tier);
        $pt->county = strtoupper($facility->subCounty->county->name);
        $pt->sub_county = $facility->subCounty->name;
        $pt->facility = $facility->name;
        $pt->round = $pt->enrolment->round->name;
        $pt->verdict = $pt->outcome($pt->feedback);
        $pt->remark = '';
        if($pt->feedback == Pt::UNSATISFACTORY)
            $pt->remark = 'Reason: '.$pt->unsatisfactory();
        $pt->date_authorized = Carbon::parse($pt->updated_at)->toFormattedDateString();
        $response = [
            'data' => $pt
        ];

        return response()->json($response);
    }

    public function importResults(Request $request){

        // Expected CSV file headers: 
        // Round, ID_No, Form_Number, Panel_Tested_Date, Panel_recv_Date, Panel_Const_Date,
        // Test2_Name, Test3_Name, Test1_Name, Kit1_Lot_No, Kit2_Lot_No, Kit3_Lot_No, Kit1_Exp_Date, Kit3_Exp_Date, 
        // Kit2_Exp_Date, PT1FinalResults, PT1TEST1Results, PT1TEST2Results, PT1TEST3Results, PT2FinalResults, 
        // PT2TEST1Results, PT2TEST2Results, PT2TEST3Results, PT3TEST1Results, PT3TEST2Results, PT3TEST3Results, 
        // PT3FinalResults, PT4FinalResults, PT4TEST1Results, PT4TEST2Results, PT4TEST3Results, PT5FinalResults, 
        // PT5TEST1Results, PT5TEST2Results, PT5TEST3Results, PT6FinalResults, PT6TEST1Results, PT6TEST3Results, 
        // PT6TEST2Results

        \Log::info("--- Importing PT results --- (System UID: ".Auth::user()->id." Name: ".Auth::user()->first_name.")");

        $fileName = $request->importResultFile;
        $handle = @fopen($fileName, "r");
        $reply = ['total' => 0, 'passed' => 0, 'failed' => 0, 'exist' => 0, 'not_enrolled' => 0, 'no_user' => 0];

        $headers = [];
        $data = [];

        if ($handle) {

            while (($buffer = fgets($handle, 4096)) !== false) {

                $buffer = str_replace(", ", "|||", $buffer);
                $reply['total']++;

                if ($reply['total'] == 1) {
                    $headers = explode(",", $buffer);
                }else{
                    $data = explode(",", $buffer);
                }

                if (count($headers) == count($data) && count($headers) > 0) {
                    $fullResultSet = [];
                    for ($i=0; $i < count($headers); $i++) { 
                        $fullResultSet[str_replace("\"", "", $headers[$i])] = str_replace("\"", "", str_replace("NULL", "", str_replace("|||", ", ", $data[$i])));
                    }

                    $resultSet['uid'] = $fullResultSet["ID_No"];
                    $resultSet['round_id'] = Round::idByTitle($fullResultSet['Round']);
                    $resultSet['field_1'] = $this->reformat($fullResultSet['Panel_recv_Date']);
                    $resultSet['field_2'] = $this->reformat($fullResultSet['Panel_Const_Date']);
                    $resultSet['field_3'] = $this->reformat($fullResultSet['Panel_Tested_Date']);

                    $resultSet['field_4'] = $this->getResultOptionID($fullResultSet['Test1_Name']);
                    $resultSet['field_5'] = $fullResultSet['Kit1_Lot_No'];
                    $resultSet['field_6'] = $this->reformat($fullResultSet['Kit1_Exp_Date']);

                    $resultSet['field_7'] = $this->getResultOptionID($fullResultSet['Test2_Name']);
                    $resultSet['field_8'] = $fullResultSet['Kit2_Lot_No'];
                    $resultSet['field_9'] = $this->reformat($fullResultSet['Kit2_Exp_Date']);

                    $resultSet['field_10'] = $this->getResultOptionID($fullResultSet['Test3_Name']);
                    $resultSet['field_11'] = $fullResultSet['Kit3_Lot_No'];
                    $resultSet['field_12'] = $this->reformat($fullResultSet['Kit3_Exp_Date']);

                    $resultSet['field_13'] = $this->getResultOptionID($fullResultSet['PT1TEST1Results']);
                    $resultSet['field_14'] = $this->getResultOptionID($fullResultSet['PT1TEST2Results']);
                    $resultSet['field_15'] = $this->getResultOptionID($fullResultSet['PT1TEST3Results']);
                    $resultSet['field_16'] = $this->getResultOptionID($fullResultSet['PT1FinalResults']);

                    $resultSet['field_17'] = $this->getResultOptionID($fullResultSet['PT2TEST1Results']);
                    $resultSet['field_18'] = $this->getResultOptionID($fullResultSet['PT2TEST2Results']);
                    $resultSet['field_19'] = $this->getResultOptionID($fullResultSet['PT2TEST3Results']);
                    $resultSet['field_20'] = $this->getResultOptionID($fullResultSet['PT2FinalResults']);

                    $resultSet['field_21'] = $this->getResultOptionID($fullResultSet['PT3TEST1Results']);
                    $resultSet['field_22'] = $this->getResultOptionID($fullResultSet['PT3TEST2Results']);
                    $resultSet['field_23'] = $this->getResultOptionID($fullResultSet['PT3TEST3Results']);
                    $resultSet['field_24'] = $this->getResultOptionID($fullResultSet['PT3FinalResults']);

                    $resultSet['field_25'] = $this->getResultOptionID($fullResultSet['PT4TEST1Results']);
                    $resultSet['field_26'] = $this->getResultOptionID($fullResultSet['PT4TEST2Results']);
                    $resultSet['field_27'] = $this->getResultOptionID($fullResultSet['PT4TEST3Results']);
                    $resultSet['field_28'] = $this->getResultOptionID($fullResultSet['PT4FinalResults']);

                    $resultSet['field_29'] = $this->getResultOptionID($fullResultSet['PT5TEST1Results']);
                    $resultSet['field_30'] = $this->getResultOptionID($fullResultSet['PT5TEST2Results']);
                    $resultSet['field_31'] = $this->getResultOptionID($fullResultSet['PT5TEST3Results']);
                    $resultSet['field_32'] = $this->getResultOptionID($fullResultSet['PT5FinalResults']);

                    $resultSet['field_33'] = $this->getResultOptionID($fullResultSet['PT6TEST1Results']);
                    $resultSet['field_34'] = $this->getResultOptionID($fullResultSet['PT6TEST2Results']);
                    $resultSet['field_35'] = $this->getResultOptionID($fullResultSet['PT6TEST3Results']);
                    $resultSet['field_36'] = $this->getResultOptionID($fullResultSet['PT6FinalResults']);

                    $resultSet['field_37'] = $fullResultSet['Comments'];

                    $stored = $this->storeImportedResult($resultSet);
                    switch ($stored) {
                        case 1:
                            $reply['passed']++; break;
                        case -1:
                            $reply['failed']++; break;
                        case -2:
                            $reply['not_enrolled']++; break;
                        case -3:
                            $reply['exist']++; break;
                        case -5:
                            $reply['no_user']++; break;
                    }

                }else{
                    $reply['failed']++;
                }
            }

            if($reply['total'] > 1)$reply['total']--;

            if (!feof($handle)) {
                $reply['failed']++;
                \Log::info("Error: unexpected fgets() fail\n");
            }
            fclose($handle);
        }

        return response()->json($reply);
    }

    function reformat($date){
        $returnDate = ""; 
        $parts = explode("/", $date);

        try {
            $returnDate = $parts[2]."-".str_pad($parts[1], 2, "0", STR_PAD_LEFT)."-".str_pad($parts[0], 2, "0", STR_PAD_LEFT);
        } catch (\ErrorException $e) {}

        return $returnDate;
    }

    function getResultOptionID($result){
        // Should really read from options db table
        $options = ['determine' => 1, 'first_response' => 2, 'sdbioline' => 3, 'other' => 4, 'reactive' => 5,
                'non_reactive' => 6, 'invalid' => 7, 'not_done' => 8, 'positive' => 9, 'negative' => 10, 'inconclusive' => 11
            ];
        $result = strtolower($result);
        $resultID = "";

        if (strlen($result) > 0) {
            try {
                $resultID = $options[$result];
            } catch (\ErrorException $e) {}
        }

        return $resultID;
    }

    /**
     * Store imported result in storage.
     *
     * @param  $resultSet
     * @return \Illuminate\Http\Response
     */
    function storeImportedResult($resultSet)
    {
        $success = -4; //error parsing the data

        try {
            
            //Check if round is set         
            if ($resultSet['round_id'] =="") {
                $success = -1;            
                \Log::info("UID: ".$resultSet['uid']." - Round information missing!");
            } else {   
                //  Save pt first then proceed to save form fields
                $roundID = $resultSet['round_id'];
                $user = User::where('uid', $resultSet['uid'])->first();

                if (!isset($user->id)) {
                    \Log::info("UID: ".$resultSet['uid']." - User does not exist in the database! Check soft deletes / disabled user!");
                    $success = -5;
                }else{
                    $userID = $user->id;
                    $enrolment = Enrol::where('user_id', $userID)->where('round_id', $roundID)->first();
                
                    //Validation: Check if the enrolment results have been submitted
                    if (!isset($enrolment->id)) {
                        $success = -2;
                        \Log::info("UID: ".$resultSet['uid']." - User is not enrolled to specified round ($roundID)!");
                    }else{
                        // Check if a result already exists
                        $pt = Pt::where('enrolment_id', $enrolment->id)->first();

                        if (!isset($pt->enrolment_id)) {
                            $pt = new Pt;
                            $pt->enrolment_id = $enrolment->id;
                            $pt->panel_status = Pt::NOT_CHECKED;
                            $pt->save();

                            //update enrollment status to 1
                            $enrolment->status = Enrol::DONE;
                            $enrolment->save();
                           
                            //  Proceed to form-fields
                            // get all fields and insert into results
                            $fields = Field::all();
                            $response = '';

                            foreach ($fields as $field) {
                                $result = new Result;
                                $result->pt_id = $pt->id;
                                $result->field_id = $field->id;
                               
                               //loop through the results entered and get the response for each field
                                foreach ($resultSet as $key => $value)
                                {
                                    if((stripos($key, 'token') !==FALSE) || (stripos($key, 'method') !==FALSE))
                                        continue;
                                    else if(stripos($key, 'field') !==FALSE)
                                    {
                                        $fieldId = (int)$this->strip($key);
                                        if(is_array($value))
                                          $value = implode(', ', $value);

                                        if ($field->id == $fieldId) {
                                            $response = $value;
                                            break;
                                        }else if ($field->id != $fieldId) {
                                            $response = '';
                                        }                      
                                    }                        
                                } 
                                // save the response for respective field
                                $result->response = $response;
                                $result->save();   
                            }

                            $success = 1;
                        }else{
                            $success = -3;
                            \Log::info("UID: ".$resultSet['uid']." - User already has a result for this round ($roundID)!");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::info("Error parsing data row:");
            \Log::error($e);
            \Log::info(json_encode($resultSet));
        }

        return $success;
    }
}
