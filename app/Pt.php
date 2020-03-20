<?php namespace App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Pt extends Model
{
  	public $fillable = ['round_id', 'user_id'];
  	/**
  	* Enabling soft deletes for pt survey.
  	*
  	*/
  	use SoftDeletes;
  	protected $dates = ['deleted_at'];
	  /**
  	* Status of result
  	*
  	*/
  	const NOT_CHECKED = 0; // results just added by participant but not checked and submitted for evaluation
	  const CHECKED = 1; // once the participant has checked that the results are as they should be (verified)
  	const EVALUATED = 2; // the results have been evaluated by the system
  	const VERIFIED = 3; // the admin or County Coordinator verifies the results and can be printed
	  /**
  	* Status of result
  	*
  	*/
  	const SATISFACTORY = 0;
  	const UNSATISFACTORY = 1;
    const DOWNLOAD_STATUS = 1;

  	/**
  	* The database table used by the model.
  	*
  	* @var string
  	*/
  	protected $table = 'pt';
    /**
  	* Result relationship
  	*
  	*/
    public function results()
    {
        return $this->hasMany('App\Result');
    }
    /**
  	* Enrolment relationship
  	*
  	*/
    public function enrolment()
    {
        return $this->belongsTo('App\Enrol');
    }
    /**
    * Check pt outcome
    *
    */
    public function outcome($feedback)
    {
        $outcome = "";
        if($feedback == Pt::SATISFACTORY) $outcome = 'Satisfactory';
        if($feedback == Pt::UNSATISFACTORY) $outcome = 'Unsatisfactory';

        return $outcome;
    }
    /**
    * Get reason for unsatisfaction
    *
    */
    public function unsatisfactory()
    {
      $remark = '';
        if($this->incorrect_results == 1){
            $remark = $remark.'Incorrect Results ';

        }
        if($this->incomplete_kit_data == 1){
            $remark = $remark.'Incomplete Kit Data ';

        }
        if($this->dev_from_procedure == 1){
            $remark = $remark.'Deviation from Procedure ';

        }
        if($this->incomplete_other_information == 1){
            $remark = $remark.'Incomplete Other Information ';

        }
        if($this->use_of_expired_kits == 1){
            $remark = $remark.'Use of Expired Kits ';

        }
        if($this->invalid_results == 1){
            $remark = $remark.'Invalid Results ';

        }
        if($this->wrong_algorithm == 1){          
            $remark = $remark.'Wrong Algorithm ';
        }
        if($this->incomplete_results == 1){
            $remark = $remark.'Incomplete Results ';

        }
        return $remark;
    }
    /**
    * User relationship
    *
    */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    /**
    * Amended PT Reports relationship
    *
    */
    public function amendments()
    {
        return $this->hasMany('App\AmendedPT');
    }

    /**
    * Survey Responses relationship
    *
    */
    public function surveyResponses(){

         return $this->hasMany('App\SurveyResponse');
    }

}