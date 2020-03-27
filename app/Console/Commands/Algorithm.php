<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use DB;
use Hash;

use App\Dump;
use App\Round;
use App\User;
use App\Pt;
use App\Result;
use App\Facility;
use App\Program;
use App\Registration;
use App\Role;
use App\Nonperformance;
use App\Field;
use App\Option;
use App\Enrol;
use App\Shipment;
use App\SubCounty;
use App\County;

use App\Http\Controllers\ResultController;

use Jenssegers\Date\Date as Carbon;

class Algorithm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'algorithm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Log instance
     *
     * @var Monolog\Logger
     */

    protected $log;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->log = new Logger('Algorithm');
        $this->log->pushHandler(new StreamHandler('storage/logs/algorithm.log', Logger::INFO));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /**
        * 1. Get all results in the pt-dump and add them to pt table.
        *
        */
        //  Fetch all pending records
        // $counter = Dump::count();
        // if($counter > 0)
        // {
        //     $dumps = Dump::all();
        //     $this->moveDump($dumps);
        // }
        /**
        * 2. Get all unchecked results and execute the algorithm.
        *
        */
        $counter = Pt::where('panel_status', Pt::CHECKED)->count();
        if($counter > 0);
        {
            $pts = Pt::where('panel_status', Pt::CHECKED)->get();
            $this->runAlgorithm($pts);
        }
        $this->info("Command run successfully! $counter records evaluated. See storage/logs/algorithm.log for details");
    }
    /**
    * Function to move data from pt-dump to pt table
    *
    * @return \Illuminate\Http\Response
    */
    public function moveDump($dumps)
    {
        foreach($dumps as $dump)
        {        
            //  Check if new tester
            $userId = NULL;
            if(!empty($dump->Participant_Full_Names))
            {
                $usr = User::idByName($dump->Participant_Full_Names);
                if($usr)
                {
                    $userId = $usr;
                }
                else
                {
                    $user = new User;
                    $user->name = $dump->Participant_Full_Names;
                    $user->email = $dump->NT_Email;
                    $user->password = Hash::make(User::DEFAULT_PASSWORD);
                    $user->address = NULL;
                    $user->gender = User::gender($dump->NTester_gender);
                    $user->phone = "+254$dump->NTMobile_Number";
                    $user->username = substr(str_replace(' ','',strtolower($dump->Participant_Full_Names)), 0, 5).rand(1,3);
                    $user->save();
                    //  Update username
                    $user->username = $user->id;
                    $user->uid = $user->id;
                    $user->save();
                    $userId = $user->id;
                }
                //  Creare role-user
                $role = Role::idByName('Participant');
                if($dump->MFL_Code)
                {
                    $facility = Facility::idByCode($dump->MFL_Code);
                }
                else
                {
                    if($dump->Facility_Name)
                        $facility = Facility::idByName($dump->Facility_Name);
                    if(!$facility)
                    {
                        $facility = new Facility;
                        $facility->name = strtoupper($dump->Facility_Name);
                        $sub = SubCounty::idByName($dump->SUBCOUNTY);
                        if(!$sub)
                            $facility->sub_county_id = SubCounty::idByName($dump->SUBCOUNTY);
                        else
                        {
                            $sb = new SubCounty;
                            $sb->name = $dump->SUBCOUNTY;
                            $sb->county_id = County::idByName($dump->COUNTY);
                            $sb->save();
                            $facility->sub_county_id = $sb->id;                        
                        }
                        $facility->save();
                    }
                }
                if($dump->NT_Program == "LVCT")
                    $program_id = Program::idByTitle("VCT");
                else
                    $program_id = Program::idByTitle($dump->NT_Program);
                //  Check if role-user exists
                $count = DB::table('role_user')->where('user_id', $userId)->where('role_id', $role)->count();
                if($count == 0)
                {
                    DB::table('role_user')->insert(["user_id" => $userId, "role_id" => $role, "tier" => $facility, "program_id" => $program_id]);
                }
                //  Reasons for non-performance to registration
                //  Check if user already registered
                $regCount = Registration::where('user_id', $userId)->where('uid', $dump->ID_No)->count();
                if($regCount == 0)
                {
                    $reg = new Registration;
                    $reg->user_id = $userId;
                    $reg->uid = $dump->ID_No;
                    if(!empty($dump->Transferred_County) || !empty($dump->Transferred_Facility))
                    {
                        $reg->nonperformance_id = Nonperformance::idByTitle('Transferred');
                        $reg->comment = $dump->Transferred_Facility;
                    }
                    else if(!empty($dump->Tester_Off_Duty))
                        $reg->nonperformance_id = Nonperformance::idByTitle('Off Duty');
                    else
                        $reg->nonperformance_id = Nonperformance::idByTitle('Other');
                    $reg->save();
                }
            }
            else
            {
                //  check if the user with the given uID exists
                if($dump->ID_No)
                    $userId = User::idByUID($dump->ID_No);
                else
                    $userId = User::idByName($dump->Tester_Name);
            }
            //  Enrol user to the available round
            $round = Round::idByTitle($dump->Round);
            //  Check if record already exists
            $enrolment = null;
            if(Enrol::where('user_id', $userId)->where('round_id', $round)->count() != 0)
            {
                $enrolment = Enrol::where('user_id', $userId)->where('round_id', $round)->first();
            }
            else // Add new enrolment record
            {
                $enrolment = new Enrol;
                $enrolment->user_id = $userId;
                $enrolment->round_id = $round;
                $enrolment->save();
            }
            //  Check if record exists
            $pt = null;
            if(count($enrolment->pt) != 0)
            {
                $pt = $enrolment->pt->first();
            }
            else
            {
                //  Save to pt table
                $pt = new Pt;
                $pt->enrolment_id = $enrolment->id;
                $pt->panel_status = Pt::NOT_CHECKED;
                $pt->comment = $dump->Comments;
                $pt->save();
            }

            //  Save results - one by one for the whole form
            //  Date received
            $dateReceived = $this->saveToPt($pt->id, 'Date PT Panel Received', $dump->Panel_recv_Date);
            //  Date constituted
            $dateConstituted = $this->saveToPt($pt->id, 'Date PT Panel Constituted', $dump->Panel_Const_Date);
            //  Date tested
            $dateTested = $this->saveToPt($pt->id, 'Date PT Panel Tested', $dump->Panel_Tested_Date);
            //  Test 1 kit name
            $test1kitName = $this->saveToPt($pt->id, 'Test 1 Kit Name', $dump->Test1_Name);
            //  Test 1 kit lot no
            $test1kitLotNo = $this->saveToPt($pt->id, 'Test 1 Lot No.', $dump->Kit1_Lot_No);
            //  Test 1 kit expiry date
            $test1kitExpiryDate = $this->saveToPt($pt->id, 'Test 1 Expiry Date', $dump->Kit1_Exp_Date);
            //  Test 2 kit name
            $test2kitName = $this->saveToPt($pt->id, 'Test 2 Kit Name', $dump->Test2_Name);
            //  Test 2 kit lot no
            $test2kitLotNo = $this->saveToPt($pt->id, 'Test 2 Lot No.', $dump->Kit2_Lot_No);
            //  Test 2 kit expiry date
            $test2kitExpiryDate = $this->saveToPt($pt->id, 'Test 2 Expiry Date', $dump->Kit2_Exp_Date);
            //  Test 3 kit name
            $test3kitName = $this->saveToPt($pt->id, 'Test 3 Kit Name', $dump->Test3_Name);
            //  Test 3 kit lot no
            $test3kitLotNo = $this->saveToPt($pt->id, 'Test 3 Lot No.', $dump->Kit3_Lot_No);
            //  Test 3 kit expiry date
            $test3kitExpiryDate = $this->saveToPt($pt->id, 'Test 3 Expiry Date', $dump->Kit3_Exp_Date);
            //  Panel 1 test 1 results
            $ptPanel1Test1Result = $this->saveToPt($pt->id, 'PT Panel 1 Test 1 Results', $dump->PT1TEST1Results);
            //  Panel 1 test 2 results
            $ptPanel1Test2Result = $this->saveToPt($pt->id, 'PT Panel 1 Test 2 Results', $dump->PT1TEST2Results);
            //  Panel 1 test 3 results
            $ptPanel1Test3Result = $this->saveToPt($pt->id, 'PT Panel 1 Test 3 Results', $dump->PT1TEST3Results);
            //  Panel 1 final results
            $ptPanel1FinalResult = $this->saveToPt($pt->id, 'PT Panel 1 Final Results', $dump->PT1FinalResults);
            //  Panel 2 test 1 results
            $ptPanel2Test1Result = $this->saveToPt($pt->id, 'PT Panel 2 Test 1 Results', $dump->PT2TEST1Results);
            //  Panel 2 test 2 results
            $ptPanel2Test2Result = $this->saveToPt($pt->id, 'PT Panel 2 Test 2 Results', $dump->PT2TEST2Results);
            //  Panel 2 test 3 results
            $ptPanel2Test3Result = $this->saveToPt($pt->id, 'PT Panel 2 Test 3 Results', $dump->PT2TEST3Results);
            //  Panel 2 final results
            $ptPanel2FinalResult = $this->saveToPt($pt->id, 'PT Panel 2 Final Results', $dump->PT2FinalResults);
            //  Panel 3 test 1 results
            $ptPanel3Test1Result = $this->saveToPt($pt->id, 'PT Panel 3 Test 1 Results', $dump->PT3TEST1Results);
            //  Panel 3 test 2 results
            $ptPanel3Test2Result = $this->saveToPt($pt->id, 'PT Panel 3 Test 2 Results', $dump->PT3TEST2Results);
            //  Panel 3 test 3 results
            $ptPanel3Test3Result = $this->saveToPt($pt->id, 'PT Panel 3 Test 3 Results', $dump->PT3TEST3Results);
            //  Panel 3 final results
            $ptPanel3FinalResult = $this->saveToPt($pt->id, 'PT Panel 3 Final Results', $dump->PT3FinalResults);
            //  Panel 4 test 1 results
            $ptPanel4Test1Result = $this->saveToPt($pt->id, 'PT Panel 4 Test 1 Results', $dump->PT4TEST1Results);
            //  Panel 4 test 2 results
            $ptPanel4Test2Result = $this->saveToPt($pt->id, 'PT Panel 4 Test 2 Results', $dump->PT4TEST2Results);
            //  Panel 4 test 3 results
            $ptPanel4Test3Result = $this->saveToPt($pt->id, 'PT Panel 4 Test 3 Results', $dump->PT4TEST3Results);
            //  Panel 4 final results
            $ptPanel4FinalResult = $this->saveToPt($pt->id, 'PT Panel 4 Final Results', $dump->PT4FinalResults);
            //  Panel 5 test 1 results
            $ptPanel5Test1Result = $this->saveToPt($pt->id, 'PT Panel 5 Test 1 Results', $dump->PT5TEST1Results);
            //  Panel 5 test 2 results
            $ptPanel5Test2Result = $this->saveToPt($pt->id, 'PT Panel 5 Test 2 Results', $dump->PT5TEST2Results);
            //  Panel 5 test 3 results
            $ptPanel5Test3Result = $this->saveToPt($pt->id, 'PT Panel 5 Test 3 Results', $dump->PT5TEST3Results);
            //  Panel 5 final results
            $ptPanel5FinalResult = $this->saveToPt($pt->id, 'PT Panel 5 Final Results', $dump->PT5FinalResults);
            //  Panel 6 test 1 results
            $ptPanel6Test1Result = $this->saveToPt($pt->id, 'PT Panel 6 Test 1 Results', $dump->PT6TEST1Results);
            //  Panel 6 test 2 results
            $ptPanel6Test2Result = $this->saveToPt($pt->id, 'PT Panel 6 Test 2 Results', $dump->PT6TEST2Results);
            //  Panel 6 test 3 results
            $ptPanel6Test3Result = $this->saveToPt($pt->id, 'PT Panel 6 Test 3 Results', $dump->PT6TEST3Results);
            //  Panel 6 final results
            $ptPanel6FinalResult = $this->saveToPt($pt->id, 'PT Panel 6 Final Results', $dump->PT6FinalResults);
            //  Soft-delete the saved dump
            $dump->delete();
        }
    }
    /**
    * Function to save data to the pt table
    *
    * $field = name of field, $dmp = corresponding value in pt_dump
    */
    public function saveToPt($ptId, $field, $dmp = NULL, $comment = NULL)
    {
        if($field_id = Field::idByUID($field))
        {
            if($field_id == Field::idByUID("Test 1 Kit Name") && $dmp == "KHB")
            {
                $comment = $dmp;
                $dmp = "Other";
            }
            if (strpos($dmp, '/') !== false)
            {
                if(empty($this->strip($this->strip($dmp))))
                    $dmp = NULL;
                else{
                    $dmp = Carbon::createFromFormat('d/m/Y', $dmp)->toDateString();
                }
            }
            if(!empty($dmp))
            {
                if(Result::where('pt_id', $ptId)->where('field_id', $field_id)->count() == 0)
                    $result = new Result;
                else
                    $result = Result::where('pt_id', $ptId)->where('field_id', $field_id)->first();
                $fld = Field::find($field_id);
                $result->pt_id = $ptId;
                $result->field_id = $field_id;
                if (preg_match('/_/', $dmp))
                    $dmp = str_replace('_', ' ', $dmp);
                if($fld->tag == 5)
                    $result->response = Option::idByTitle($dmp);
                else
                    $result->response = $dmp;
                if(!empty($comment))
                    $result->comment = $comment;
                $result->save();
            }
        }
        else
        {
            print($field);
        }
        return response()->json('Saved.');
    }
    /**
     * Remove the specified begining of text to get Id alone.
     *
     * @param  int  $id
     * @return Response
     */
    public function strip($field)
    {
        if(($pos = strpos($field, '/')) !== FALSE)
            return substr($field, $pos+1);
    }

    public function is_a_date($str){ 
        if (strpos($str, '/') !== false){
            $str = str_replace('/', '-', $str);
        }
        $stamp = strtotime($str);
        if (is_numeric($stamp) ){  
            $month = date( 'm', $stamp ); 
            $day   = date( 'd', $stamp ); 
            $year  = date( 'Y', $stamp ); 
            return checkdate($month, $day, $year); 
        } 
        return false; 
    }
    public function dates($date){
        $value = '';        

        if($this->is_a_date($date) ==true){
            if (strpos($date, '/') !== false)
            {
            if ($date == date('d/m/Y',strtotime(str_replace('/', '-', $date))) ) {
                   
                    $value = Carbon::createFromFormat ('d/m/Y', $date)->toDateString();
                }            
                else if ($date == date('Y/m/d',strtotime(str_replace('/', '-', $date)))) {
                
                    $value = Carbon::createFromFormat ('Y/m/d', $date)->toDateString();
                }
                else if ($date == date('m/d/Y',strtotime(str_replace('/', '-', $date)))) {                

                    $value = Carbon::createFromFormat ('m/d/Y', $date)->toDateString();      

                } 
                else{

                    $value = '';
                }   
            }else{
                $value = $date;
            }
        }        
        return $value;
    }
    /**
     * Begin algorithm to mark test results
     */
    /**
     * Function to check dates - received, constituted, tested
     *
     * @param  $date_pt_panel_received, $date_consituted, $date_pt_panel_tested, $date_panels_shipped
     * @return Deviation from procedure.
     */
     public function check_dates($date_pt_panel_received, $date_pt_panel_constituted, $date_pt_panel_tested, $date_panels_shipped)
     {
         // Check Dates
         $dev_from_procedure = 0;

        $dt_shipped = Carbon::parse($date_panels_shipped);
        $dt_constituted = Carbon::parse($date_pt_panel_constituted);
        $dt_tested = Carbon::parse($date_pt_panel_tested);
        if(strtotime($date_pt_panel_tested) == strtotime($date_pt_panel_constituted) || $dt_constituted->diffInDays($dt_tested) > 1 || $dt_tested->lte($dt_shipped)){
            $dev_from_procedure = 1;
        }

        return $dev_from_procedure;
     }
    /**
     * Function to check other info - received, constituted, tested
     *
     * @param  $date_pt_panel_received, $date_consituted, $date_pt_panel_tested
     * @return Deviation from procedure.
     */
     public function check_other_info($date_pt_panel_received, $date_pt_panel_constituted, $date_pt_panel_tested)
     {
         // Check Dates
         $incomplete_other_info = 0;
         if($date_pt_panel_received === NULL || $date_pt_panel_constituted === NULL || $date_pt_panel_tested === NULL)
            $incomplete_other_info = 1;
         return $incomplete_other_info;
     }
    /**
     * Function to completeness of HIV test kits info
     *
     * @param  $test_1_kit_name, $test_2_kit_name, $test_1_kit_lot_no, $test_2_kit_lot_no, $test_1_expiry_date, $test_2_expiry_date, $test_kit_2_required
     * @return Incomplete kit data.
     */
     public function check_kit_info($test_1_kit_name, $test_2_kit_name, $test_1_kit_lot_no, $test_2_kit_lot_no, $test_1_expiry_date, $test_2_expiry_date, $test_kit_2_required = false)
     {
         // Check kit info
         $incomplete_kit_info = 0;
         if(empty($test_1_kit_name) || empty($test_1_kit_lot_no) || empty($test_1_expiry_date))
             $incomplete_kit_info = 1;
         if($test_kit_2_required && (empty($test_2_kit_name) || empty($test_2_kit_lot_no) || empty($test_2_expiry_date)))
             $incomplete_kit_info = 1;
         return $incomplete_kit_info;
     }
    /**
     * Function to check kit expiry against date tested
     *
     * @param  $date_pt_panel_tested, $date_consituted, $date_pt_panel_tested, $test_kit_2_required
     * @return $use_of_expired_kits.
     */
     public function check_expiry($date_pt_panel_tested, $test_1_expiry_date, $test_2_expiry_date, $test_3_expiry_date, $test_kit_2_required)
     {
         $use_of_expired_kits = 0;
         $dt_tested = Carbon::parse($date_pt_panel_tested);
         $dt_1_expiry = Carbon::parse($test_1_expiry_date);
         $dt_2_expiry = Carbon::parse($test_2_expiry_date);
         $dt_3_expiry = Carbon::parse($test_3_expiry_date);
         if($dt_tested->gt($dt_1_expiry))
            $use_of_expired_kits = 1;
         if($test_kit_2_required && $dt_tested->gt($dt_2_expiry))
            $use_of_expired_kits = 1;
         return $use_of_expired_kits;
     }
    /**
     * Function to check completeness of results
     *
     * @param  Test results, Final results
     * @return Incomplete Results.
     */
     public function check_complete_results($panel1Test1, $panel1Final, $panel2Test1, $panel2Final, $panel3Test1, $panel3Final, $panel4Test1, $panel4Final, $panel5Test1, $panel5Final, $panel6Test1, $panel6Final)
     {
         $incomplete_results = 0;
         $reactive = Option::idByTitle('Reactive');
         $non_reactive = Option::idByTitle('Non Reactive');
         $not_done = Option::idByTitle('Not Done');
         if(
             (($panel1Test1 == $reactive || $panel1Final == $non_reactive) && (empty($panel1Final) || $panel1Final == $not_done)) ||
             (($panel2Test1 == $reactive || $panel2Final == $non_reactive) && (empty($panel2Final) || $panel2Final == $not_done)) ||
             (($panel3Test1 == $reactive || $panel3Final == $non_reactive) && (empty($panel3Final) || $panel3Final == $not_done)) ||
             (($panel4Test1 == $reactive || $panel4Final == $non_reactive) && (empty($panel4Final) || $panel4Final == $not_done)) ||
             (($panel5Test1 == $reactive || $panel5Final == $non_reactive) && (empty($panel5Final) || $panel5Final == $not_done)) ||
             (($panel6Test1 == $reactive || $panel6Final == $non_reactive) && (empty($panel6Final) || $panel6Final == $not_done))
         )
            $incomplete_results = 1;
         return $incomplete_results;
     }
    /**
     * Function to check correctness of results
     *
     * @param  $date_pt_panel_received, $date_consituted, $date_pt_panel_tested
     * @return Incorrect results.
     */
    public function check_correct_results($panel1Final, $panel2Final, $panel3Final, $panel4Final, $panel5Final, $panel6Final, $expectedResult1, $expectedResult2, $expectedResult3, $expectedResult4, $expectedResult5, $expectedResult6)
    {
         // Check correctness
        $incorrectResults = 1;
        $either = Option::idByTitle('Either'); //Added to resolve Round 19 evaluation problems
        $positive = Option::idByTitle('Positive');
        $negative = Option::idByTitle('Negative');
        $inconclusive = Option::idByTitle('Inconclusive');

        if(
            ($panel1Final == $expectedResult1 || ($expectedResult1 == $either && ($panel1Final == $positive ||
                $panel1Final == $negative || $panel1Final == $inconclusive))) &&
            ($panel2Final == $expectedResult2 || ($expectedResult2 == $either && ($panel2Final == $positive ||
                $panel2Final == $negative || $panel2Final == $inconclusive))) &&
            ($panel3Final == $expectedResult3 || ($expectedResult3 == $either && ($panel3Final == $positive ||
                $panel3Final == $negative || $panel3Final == $inconclusive))) &&
            ($panel4Final == $expectedResult4 || ($expectedResult4 == $either && ($panel4Final == $positive ||
                $panel4Final == $negative || $panel4Final == $inconclusive))) &&
            ($panel5Final == $expectedResult5 || ($expectedResult5 == $either && ($panel5Final == $positive ||
                $panel5Final == $negative || $panel5Final == $inconclusive))) &&
            ($panel6Final == $expectedResult6 || ($expectedResult6 == $either && ($panel6Final == $positive ||
                $panel6Final == $negative || $panel6Final == $inconclusive)))
        )
            $incorrectResults = 0;
         return $incorrectResults;
    }
    /**
     * Function to check if results satisfactory
     *
     * @param  $incorrect_results
     * @return Unsatisfactory results.
     */
     public function check_satisfaction($incorrect_results)
     {
         $unsatisfactory = 0;
         if($incorrect_results == 1)
            $unsatisfactory = 1;
         return $unsatisfactory;
     }
    /**
     * Function to check if results are valid
     *
     * @param  Test results
     * @return Invalid results.
     */
     public function check_validity($pt_panel_1_test_1_results, $pt_panel_1_test_2_results, $pt_panel_1_test_3_results, $pt_panel_1_final_results, $pt_panel_2_test_1_results, $pt_panel_2_test_2_results, $pt_panel_2_test_3_results, $pt_panel_2_final_results, $pt_panel_3_test_1_results, $pt_panel_3_test_2_results, $pt_panel_3_test_3_results, $pt_panel_3_final_results, $pt_panel_4_test_1_results, $pt_panel_4_test_2_results, $pt_panel_4_test_3_results, $pt_panel_4_final_results, $pt_panel_5_test_1_results, $pt_panel_5_test_2_results, $pt_panel_5_test_3_results, $pt_panel_5_final_results, $pt_panel_6_test_1_results, $pt_panel_6_test_2_results, $pt_panel_6_test_3_results, $pt_panel_6_final_results)
    {
        $invalid_results = 0;
        $invalid = Option::idByTitle('Invalid');
        if(
             ($pt_panel_1_test_1_results == $invalid || $pt_panel_1_test_2_results == $invalid || $pt_panel_1_test_3_results == $invalid || $pt_panel_1_final_results == $invalid) || 
             ($pt_panel_2_test_1_results == $invalid || $pt_panel_2_test_2_results == $invalid || $pt_panel_2_test_3_results == $invalid || $pt_panel_2_final_results == $invalid) || 
             ($pt_panel_3_test_1_results == $invalid || $pt_panel_3_test_2_results == $invalid || $pt_panel_3_test_3_results == $invalid || $pt_panel_3_final_results == $invalid) || 
             ($pt_panel_4_test_1_results == $invalid || $pt_panel_4_test_2_results == $invalid || $pt_panel_4_test_3_results == $invalid || $pt_panel_4_final_results == $invalid) || 
             ($pt_panel_5_test_1_results == $invalid || $pt_panel_5_test_2_results == $invalid || $pt_panel_5_test_3_results == $invalid || $pt_panel_5_final_results == $invalid) || 
             ($pt_panel_6_test_1_results == $invalid || $pt_panel_6_test_2_results == $invalid || $pt_panel_6_test_3_results == $invalid || $pt_panel_6_final_results == $invalid)
        )
            $invalid_results = 1;
         return $invalid_results;
    }
    /**
     * Function to check if algorithm followed
     *
     * @param  Test results
     * @return Wrong algorithm.
     */
     public function check_algorithm($panel1Test1, $panel1Test2, $panel1Test3, $panel1Final, $panel2Test1, $panel2Test2, $panel2Test3, $panel2Final, $panel3Test1, $panel3Test2, $panel3Test3, $panel3Final, $panel4Test1, $panel4Test2, $panel4Test3, $panel4Final, $panel5Test1, $panel5Test2, $panel5Test3, $panel5Final, $panel6Test1, $panel6Test2, $panel6Test3, $panel6Final, $kit1, $kit2)
     {
        $wrongAlgorithm = [];
        $reactive = Option::idByTitle('Reactive');
        $nonReactive = Option::idByTitle('Non Reactive');
        $notDone = Option::idByTitle('Not Done');
        $firstResponse = Option::idByTitle('First Response');
        $determine = Option::idByTitle('Determine');
        $bioline = Option::idByTitle('SDBioline');
        $positive = Option::idByTitle('Positive');
        $negative = Option::idByTitle('Negative');
        $inconclusive = Option::idByTitle('Inconclusive');

        // For each panel:
        // If test 1 is "non reactive", test 2 should be "not done" and the final result should be "non reactive"
        // Or If test 1 is "reactive", test 2 should not be "not done" and the final result should be "reactive" or "inconclusive"
        // Kit 1 should be "determine" or "bioline" and kit 2 should be "first response"

        if (!(($panel1Test1 == $nonReactive && $panel1Test2 == $notDone && $panel1Final == $negative) ||
                ($panel1Test1 == $reactive && $panel1Test2 == $reactive && $panel1Final == $positive) ||
                ($panel1Test1 == $reactive && $panel1Test2 == $nonReactive && $panel1Final == $inconclusive))) {
            $wrongAlgorithm[] = 1;
        }

        if (!(($panel2Test1 == $nonReactive && $panel2Test2 == $notDone && $panel2Final == $negative) ||
                ($panel2Test1 == $reactive && $panel2Test2 == $reactive && $panel2Final == $positive) ||
                ($panel2Test1 == $reactive && $panel2Test2 == $nonReactive && $panel2Final == $inconclusive))) {
            $wrongAlgorithm[] = 1;
        }

        if (!(($panel3Test1 == $nonReactive && $panel3Test2 == $notDone && $panel3Final == $negative) ||
                ($panel3Test1 == $reactive && $panel3Test2 == $reactive && $panel3Final == $positive) ||
                ($panel3Test1 == $reactive && $panel3Test2 == $nonReactive && $panel3Final == $inconclusive))) {
            $wrongAlgorithm[] = 1;
        }

        if (!(($panel4Test1 == $nonReactive && $panel4Test2 == $notDone && $panel4Final == $negative) ||
                ($panel4Test1 == $reactive && $panel4Test2 == $reactive && $panel4Final == $positive) ||
                ($panel4Test1 == $reactive && $panel4Test2 == $nonReactive && $panel4Final == $inconclusive))) {
            $wrongAlgorithm[] = 1;
        }

        if (!(($panel5Test1 == $nonReactive && $panel5Test2 == $notDone && $panel5Final == $negative) ||
                ($panel5Test1 == $reactive && $panel5Test2 == $reactive && $panel5Final == $positive) ||
                ($panel5Test1 == $reactive && $panel5Test2 == $nonReactive && $panel5Final == $inconclusive))) {
            $wrongAlgorithm[] = 1;
        }

        if (!(($panel6Test1 == $nonReactive && $panel6Test2 == $notDone && $panel6Final == $negative) ||
                ($panel6Test1 == $reactive && $panel6Test2 == $reactive && $panel6Final == $positive) ||
                ($panel6Test1 == $reactive && $panel6Test2 == $nonReactive && $panel6Final == $inconclusive))) {
            $wrongAlgorithm[] = 1;
        }

        if (!($kit1 == $determine || $kit1 == $bioline)) {
            $wrongAlgorithm[] = 1;
        }

        if (($panel1Test1 == $reactive || $panel2Test1 == $reactive || $panel3Test1 == $reactive || $panel4Test1 == $reactive || $panel5Test1 == $reactive || $panel6Test1 == $reactive) && $kit2 != $firstResponse) {
            $wrongAlgorithm[] = 1;
        }

        if(array_sum($wrongAlgorithm) > 0) return 1; //TRUE
        else return 0; //FALSE
     }
    /**
     * Function to set overall result - satisfactory/unsatisfactory
     *
     * @param  $dev_from_procedure, $incomplete_other_info, $incomplete_kit_info, $use_of_expired_kits, $incomplete_results, $incorrect_results, $unsatisfactory, $invalid, $wrong_algorithm
     * @return Unsatisfactory results.
     */
     public function check_overall($dev_from_procedure, $incomplete_other_info, $incomplete_kit_info, $use_of_expired_kits, $incomplete_results, $incorrect_results, $unsatisfactory, $invalid, $wrong_algorithm)
     {
         $overall = 1;
         if($dev_from_procedure == 0 && $incomplete_other_info == 0 && $incomplete_kit_info == 0 && $use_of_expired_kits == 0 && $incomplete_results == 0 && $incorrect_results == 0 && $unsatisfactory == 0 && $invalid == 0 && $wrong_algorithm == 0)
            $overall = 0;
         return $overall;
     }
     /**
     * Begin background processing
     */
     public function runAlgorithm($pts)
     {
        $reactive = Option::idByTitle('Reactive');
        foreach($pts as $pt)
        {
            //  Fetch expected results
            $round = $pt->enrolment->round_id;
            $user = $pt->enrolment->user;
            
            if ($user){
                if($pt->enrolment->user->registration)
                    $user = User::where('uid', $user->registration->uid)->first();

                try {
                    $countyID = $pt->enrolment->facility->subCounty->county_id;
                    $shipment = Shipment::where('county_id', $countyID)->where('round_id', $round)->first();
                    $date_panels_shipped = $shipment->date_shipped;
                } catch (\Exception $e) {
                    $date_panels_shipped = NULL;
                }

                $lot = User::lot($round, $user->uid);
                $res_1 = $lot->panels()->where('panel', 1)->first();
                $res_2 = $lot->panels()->where('panel', 2)->first();
                $res_3 = $lot->panels()->where('panel', 3)->first();
                $res_4 = $lot->panels()->where('panel', 4)->first();
                $res_5 = $lot->panels()->where('panel', 5)->first();
                $res_6 = $lot->panels()->where('panel', 6)->first();
            
                $expectedResult1ID = Option::idByTitle($res_1->result($res_1->result));
                $expectedResult2ID = Option::idByTitle($res_2->result($res_2->result));
                $expectedResult3ID = Option::idByTitle($res_3->result($res_3->result));
                $expectedResult4ID = Option::idByTitle($res_4->result($res_4->result));
                $expectedResult5ID = Option::idByTitle($res_5->result($res_5->result));
                $expectedResult6ID = Option::idByTitle($res_6->result($res_6->result));
                //  End fetch
                $rs = $pt->results;
                $date_pt_panel_received = NULL;
                $date_pt_panel_constituted = NULL;
                $date_pt_panel_tested = NULL;
                $test_1_kit_name = NULL;
                $test_2_kit_name = NULL;
                $test_1_kit_lot_no = NULL;
                $test_2_kit_lot_no = NULL;
                $test_1_expiry_date = NULL;
                $test_2_expiry_date = NULL;
                $test_3_expiry_date = NULL;
                //  Kit names
                $kit_1 = NULL;
                $kit_2 = NULL;

                $pt_panel_1_test_1_results = NULL;
                $pt_panel_1_test_2_results = NULL;
                $pt_panel_1_test_3_results = NULL;
                $pt_panel_1_final_results = NULL;
                $pt_panel_2_test_1_results = NULL;
                $pt_panel_2_test_2_results = NULL;
                $pt_panel_2_test_3_results = NULL;
                $pt_panel_2_final_results = NULL;
                $pt_panel_3_test_1_results = NULL;
                $pt_panel_3_test_2_results = NULL;
                $pt_panel_3_test_3_results = NULL;
                $pt_panel_3_final_results = NULL;
                $pt_panel_4_test_1_results = NULL;
                $pt_panel_4_test_2_results = NULL;
                $pt_panel_4_test_3_results = NULL;
                $pt_panel_4_final_results = NULL;
                $pt_panel_5_test_1_results = NULL;
                $pt_panel_5_test_2_results = NULL;
                $pt_panel_5_test_3_results = NULL;
                $pt_panel_5_final_results = NULL;
                $pt_panel_6_test_1_results = NULL;
                $pt_panel_6_test_2_results = NULL;
                $pt_panel_6_test_3_results = NULL;
                $pt_panel_6_final_results = NULL;

                foreach($rs as $rss)
                {
                    //  Get all variables first to be used after the loop
                    if($rss->field_id == Field::idByUID('Date PT Panel Received'))
                        $date_pt_panel_received = $this->dates($rss->response);
                    if($rss->field_id == Field::idByUID('Date PT Panel Constituted'))
                        $date_pt_panel_constituted = $this->dates($rss->response);
                    if($rss->field_id == Field::idByUID('Date PT Panel Tested'))
                        $date_pt_panel_tested = $this->dates($rss->response);
                    if($rss->field_id == Field::idByUID('Test 1 Kit Name')){
                        $test_1_kit_name = $rss->response;
                        $kit_1 = $rss->response;
                        if($kit_1 == Option::idByTitle("Other"))
                            $kit_1 = $rss->comment;
                    }
                    if($rss->field_id == Field::idByUID('Test 2 Kit Name')){
                        $test_2_kit_name = $rss->response;
                        $kit_2 = $rss->response;
                    }
                    if($rss->field_id == Field::idByUID('Test 1 Lot No.'))
                        $test_1_kit_lot_no = $rss->response;
                    if($rss->field_id == Field::idByUID('Test 2 Lot No.'))
                        $test_2_kit_lot_no = $rss->response;
                    
                    if($rss->field_id == Field::idByUID('Test 1 Expiry Date'))
                        $test_1_expiry_date = $this->dates($rss->response); 
                    if($rss->field_id == Field::idByUID('Test 2 Expiry Date'))
                        $test_2_expiry_date = $this->dates($rss->response);
                    if($rss->field_id == Field::idByUID('Test 3 Expiry Date'))
                        $test_3_expiry_date = $this->dates($rss->response);

                    if($rss->field_id == Field::idByUID('PT Panel 1 Test 1 Results'))
                        $pt_panel_1_test_1_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 1 Test 2 Results'))
                        $pt_panel_1_test_2_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 1 Test 3 Results'))
                        $pt_panel_1_test_3_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 1 Final Results'))
                        $pt_panel_1_final_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 2 Test 1 Results'))
                        $pt_panel_2_test_1_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 2 Test 2 Results'))
                        $pt_panel_2_test_2_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 2 Test 3 Results'))
                        $pt_panel_2_test_3_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 2 Final Results'))
                        $pt_panel_2_final_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 3 Test 1 Results'))
                        $pt_panel_3_test_1_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 3 Test 2 Results'))
                        $pt_panel_3_test_2_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 3 Test 3 Results'))
                        $pt_panel_3_test_3_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 3 Final Results'))
                        $pt_panel_3_final_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 4 Test 1 Results'))
                        $pt_panel_4_test_1_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 4 Test 2 Results'))
                        $pt_panel_4_test_2_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 4 Test 3 Results'))
                        $pt_panel_4_test_3_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 4 Final Results'))
                        $pt_panel_4_final_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 5 Test 1 Results'))
                        $pt_panel_5_test_1_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 5 Test 2 Results'))
                        $pt_panel_5_test_2_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 5 Test 3 Results'))
                        $pt_panel_5_test_3_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 5 Final Results'))
                        $pt_panel_5_final_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 6 Test 1 Results'))
                        $pt_panel_6_test_1_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 6 Test 2 Results'))
                        $pt_panel_6_test_2_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 6 Test 3 Results'))
                        $pt_panel_6_test_3_results = $rss->response;
                    if($rss->field_id == Field::idByUID('PT Panel 6 Final Results'))
                        $pt_panel_6_final_results = $rss->response;
                }
                $test_kit_2_required = ($pt_panel_1_test_1_results == $reactive || $pt_panel_2_test_1_results == $reactive || $pt_panel_3_test_1_results == $reactive || $pt_panel_4_test_1_results == $reactive || $pt_panel_5_test_1_results == $reactive || $pt_panel_6_test_1_results == $reactive);
                //  Fetch expected results
                $dev_from_procedure = $this->check_dates($date_pt_panel_received, $date_pt_panel_constituted, $date_pt_panel_tested, $date_panels_shipped);
                $incomplete_other_info = $this->check_other_info($date_pt_panel_received, $date_pt_panel_constituted, $date_pt_panel_tested);
                $incomplete_kit_info = $this->check_kit_info($test_1_kit_name, $test_2_kit_name, $test_1_kit_lot_no, $test_2_kit_lot_no, $test_1_expiry_date, $test_2_expiry_date, $test_kit_2_required);
                $use_of_expired_kits = $this->check_expiry($date_pt_panel_tested, $test_1_expiry_date, $test_2_expiry_date, $test_2_expiry_date, $test_kit_2_required);
                $incomplete_results = $this->check_complete_results($pt_panel_1_test_1_results, $pt_panel_1_final_results, $pt_panel_2_test_1_results, $pt_panel_2_final_results, $pt_panel_3_test_1_results, $pt_panel_3_final_results, $pt_panel_4_test_1_results, $pt_panel_4_final_results, $pt_panel_5_test_1_results, $pt_panel_5_final_results, $pt_panel_6_test_1_results, $pt_panel_6_final_results);
                $incorrect_results = $this->check_correct_results($pt_panel_1_final_results, $pt_panel_2_final_results, $pt_panel_3_final_results, $pt_panel_4_final_results, $pt_panel_5_final_results, $pt_panel_6_final_results,  $expectedResult1ID, $expectedResult2ID, $expectedResult3ID, $expectedResult4ID, $expectedResult5ID, $expectedResult6ID);
                $unsatisfactory = $this->check_satisfaction($incorrect_results);
                $invalid_results = $this->check_validity($pt_panel_1_test_1_results, $pt_panel_1_test_2_results, $pt_panel_1_test_3_results, $pt_panel_1_final_results, $pt_panel_2_test_1_results, $pt_panel_2_test_2_results, $pt_panel_2_test_3_results, $pt_panel_2_final_results, $pt_panel_3_test_1_results, $pt_panel_3_test_2_results, $pt_panel_3_test_3_results, $pt_panel_3_final_results, $pt_panel_4_test_1_results, $pt_panel_4_test_2_results, $pt_panel_4_test_3_results, $pt_panel_4_final_results, $pt_panel_5_test_1_results, $pt_panel_5_test_2_results, $pt_panel_5_test_3_results, $pt_panel_5_final_results, $pt_panel_6_test_1_results, $pt_panel_6_test_2_results, $pt_panel_6_test_3_results, $pt_panel_6_final_results);
                $wrong_algorithm = $this->check_algorithm($pt_panel_1_test_1_results, $pt_panel_1_test_2_results, $pt_panel_1_test_3_results, $pt_panel_1_final_results, $pt_panel_2_test_1_results, $pt_panel_2_test_2_results, $pt_panel_2_test_3_results, $pt_panel_2_final_results, $pt_panel_3_test_1_results, $pt_panel_3_test_2_results, $pt_panel_3_test_3_results, $pt_panel_3_final_results, $pt_panel_4_test_1_results, $pt_panel_4_test_2_results, $pt_panel_4_test_3_results, $pt_panel_4_final_results, $pt_panel_5_test_1_results, $pt_panel_5_test_2_results, $pt_panel_5_test_3_results, $pt_panel_5_final_results, $pt_panel_6_test_1_results, $pt_panel_6_test_2_results, $pt_panel_6_test_3_results, $pt_panel_6_final_results, $kit_1, $kit_2);
                $overall = $this->check_overall($dev_from_procedure, $incomplete_other_info, $incomplete_kit_info, $use_of_expired_kits, $incomplete_results, $incorrect_results, $unsatisfactory, $invalid_results, $wrong_algorithm);
                //  Update PT with the outcome of the algorithm.
                $pt->dev_from_procedure = $dev_from_procedure;
                $pt->incomplete_other_information = $incomplete_other_info;
                $pt->incomplete_kit_data = $incomplete_kit_info;
                $pt->use_of_expired_kits = $use_of_expired_kits;
                $pt->incomplete_results = $incomplete_results;
                $pt->incorrect_results = $incorrect_results;
                $pt->panel_result = $unsatisfactory;
                $pt->invalid_results = $invalid_results;
                $pt->wrong_algorithm = $wrong_algorithm;
                $pt->feedback = $overall;
                $pt->panel_status = Pt::EVALUATED;
                $pt->save();

                print(".");
                $this->log->info("PT ID: {$pt->id} => DFP: {$pt->dev_from_procedure} IOI:{$pt->incomplete_other_information} IKD: {$pt->incomplete_kit_data} UEK: {$pt->use_of_expired_kits} IR: {$pt->incomplete_results} WR: {$pt->incorrect_results} PR: {$pt->panel_result} INV: {$pt->invalid_results} WA: {$pt->wrong_algorithm} F: {$pt->feedback}");
            }
        }
        return response()->json('Done.');
    }
}
