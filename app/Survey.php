<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
	public $fillable = ['round_id','question','question_type'];
	use SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
    * The database table used by the model.
    *
    * @var string
    */
    protected $table = 'surveys';

    /**
  	 * Question types
  	 *
  	 */
  	public $questionTypes =[0 => 'FREE TEXT', 1 => 'YES/NO', 2 => 'AGREE/.../DISAGREE - Categorical'];

     /**
      * Relationship with rounds.
      *
      */
    public function round()
    {
        return $this->belongsTo('App\Round');
    }

    /**
    * Survey Responses relationship
    *
    */
    public function surveyResponses(){

         return $this->hasMany('App\SurveyResponse');
    }

    public static function getQuestionTypes(){
    	return [['id' => 0, 'tag' => 'FREE TEXT'],
    			['id' => 1, 'tag' => 'YES/NO'],
    			['id' =>2, 'tag' => 'AGREE/.../DISAGREE - Categorical']
    		];
    }
}
