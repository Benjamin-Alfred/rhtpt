<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Survey;
use App\SurveyResponse;
use App\User;
use App\Round;

use Auth;
use DB;
use Jenssegers\Date\Date as Carbon;

class SurveyController extends Controller
{

    public function manageSurvey()
    {
        return view('survey.index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $ITEM_COUNT = 10;
        $error = ['error' => 'No results found, please try with different keywords.'];
        $pagination = [];

        if($request->has('round_id')) 
        {
            $roundID = $request->get('round_id');
            $surveys = Survey::where('round_id', $roundID)->get();
        }else {
            if($request->has('q')){
                $search = $request->get('q');
                $surveys = Survey::where('question', 'LIKE', "%{$search}%")->latest()->withTrashed()->paginate($ITEM_COUNT);
            }else{
                $surveys = Survey::with('round')->latest()->withTrashed()->paginate($ITEM_COUNT);
            }

            $pagination = [
                'total' => $surveys->total(),
                'per_page' => $surveys->perPage(),
                'current_page' => $surveys->currentPage(),
                'last_page' => $surveys->lastPage(),
                'from' => $surveys->firstItem(),
                'to' => $surveys->lastItem()
            ];
        }

        $response = [
            'pagination' => $pagination,
            'data' => $surveys
        ];

        return $surveys->count() > 0 ? response()->json($response) : $error;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $error = ['error' => 'No active round exists.'];
        //Get the currently active round
        $round = Round::where('end_date', '>', Carbon::today())
                        ->where('start_date', '<', Carbon::today())->where('status', '=', 0)->first();
        if ($round) {
            $survey = new Survey;
            $survey->round_id = $round->id;
            $survey->question = $request->question;
            $survey->question_type = $request->question_type;
            $survey->created_by = Auth::user()->id;
            $survey->save();

            \Log::info("Survey question added: ".json_encode($survey));
        }

        return ($round)?response()->json($survey):$error;
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
        $edit = Survey::find($id)->update($request->all());

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
        Survey::find($id)->delete();
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
        $survey = Survey::withTrashed()->find($id)->restore();
        return response()->json(['done']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSurveyResponses(Request $request, $roundName, $PTID)
    {
        $error = ['error' => 'No results found, please try with different keywords.'];
        $round = Round::where('name', $roundName)->first();
        $questions = Survey::where('round_id', $round->id);

        $response = [
            'data' => $questions->get(),
            'round_id' => $round->id,
            'round_name' => $round->name,
            'pt_id' => $PTID
        ];

        return $questions->count() > 0 ? response()->json($response) : $error;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeSurveyResponses(Request $request)
    {
        $error = ['error' => 'No active round exists.'];

        $PTID = $request->get('pt_id');
        foreach ($request->all() as $fieldKey => $fieldValue) {
            if (strcmp(substr($fieldKey, 0, 9), "question_") == 0 && strlen(trim($fieldValue)) > 0) {
                $surveyQuestionID = substr($fieldKey, 9);

                $surveyResponse = SurveyResponse::firstOrNew(['survey_id' => $surveyQuestionID, 'pt_id' => $PTID]);
                $surveyResponse->survey_id = $surveyQuestionID;
                $surveyResponse->pt_id = $PTID;
                $surveyResponse->response = $fieldValue;
                $surveyResponse->created_by = Auth::user()->id;
                $surveyResponse->save();
            }
        }
        $surveyResponses = SurveyResponse::where('pt_id', $PTID);
        if($surveyResponses->count() > 0){
            \Log::info("Survey question responses added: ".json_encode($surveyResponses->get()));
        }


        return $surveyResponses->count() > 0 ? response()->json(['replies' => $surveyResponses->count()]) : $error;
    }

    public function viewSurveyResponses(){

        return view('report.customersurveyresponses');
    }

    /**
     * Get answers to survey questions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getSurveyResponsesData(Request $request){

        $ITEMS_PER_PAGE = 50;
        $error = ['error' => 'No response found, please try with different filters.'];
        $tier = Auth::user()->ru()->tier;

        $data = DB::table('enrolments')
                    ->join('users', 'enrolments.tester_id', '=', 'users.id')
                    ->join('rounds', 'enrolments.round_id', '=', 'rounds.id')
                    ->join('facilities', 'enrolments.facility_id', '=', 'facilities.id')
                    ->join('sub_counties', 'facilities.sub_county_id', '=', 'sub_counties.id')
                    ->join('counties', 'sub_counties.county_id', '=', 'counties.id')
                    ->join('pt', 'enrolments.id', '=', 'pt.enrolment_id')
                    ->join('surveys', 'enrolments.round_id', '=', 'surveys.round_id')
                    ->join('survey_responses', function($join){
                        $join->on('pt.id', '=', 'survey_responses.pt_id')
                            ->on('surveys.id', '=', 'survey_responses.survey_id');
                    });

        if($request->has('round')) $data = $data->where('rounds.id', '=', $request->get('round'));
        if($request->has('county')) $data = $data->where('counties.id', '=', $request->get('county'));
        if($request->has('subcounty')) $data = $data->where('sub_counties.id', '=', $request->get('subcounty'));
        if($request->has('facility')) $data = $data->where('facilities.id', '=', $request->get('facility'));
        if($request->has('question')) $data = $data->where('surveys.id', '=', $request->get('question'));

        if(Auth::user()->isCountyCoordinator()) $data = $data->where('counties.id', '=', $tier);
        if(Auth::user()->isSubCountyCoordinator()) $data = $data->where('sub_counties.id', '=', $tier);

        $data = $data->selectRaw('counties.name AS county, sub_counties.name AS subcounty, facilities.name AS facility, users.uid, survey_responses.pt_id, survey_responses.survey_id, surveys.question, surveys.question_type, survey_responses.response')
                    ->orderBy('counties.name')
                    ->orderBy('sub_counties.name');

        $totalUsers = collect($data->pluck('pt_id'))->unique()->count();
        $questions = collect($data->pluck('question', 'survey_id'))->unique();

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
            'data' => $data,
            'questions' => $questions,
            'total_users' => $totalUsers
        ];

        return $data->count() > 0 ? response()->json($response) : $error;
    }
}