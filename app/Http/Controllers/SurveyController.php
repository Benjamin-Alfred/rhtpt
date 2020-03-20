<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Survey;
use App\SurveyResponse;
use App\User;
use App\Round;

use Auth;
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
        $error = ['error' => 'No results found, please try with different keywords.'];
        $surveys = Survey::with('round')->latest()->withTrashed()->paginate(5);
        if($request->has('q')) 
        {
            $search = $request->get('q');
            $surveys = Survey::where('question', 'LIKE', "%{$search}%")->latest()->withTrashed()->paginate(5);
        }

        $response = [
            'pagination' => [
                'total' => $surveys->total(),
                'per_page' => $surveys->perPage(),
                'current_page' => $surveys->currentPage(),
                'last_page' => $surveys->lastPage(),
                'from' => $surveys->firstItem(),
                'to' => $surveys->lastItem()
            ],
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

}