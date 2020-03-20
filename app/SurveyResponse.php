<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyResponse extends Model
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
    protected $table = 'survey_responses';

     /**
      * Relationship with survey.
      *
      */
    public function survey()
    {
        return $this->belongsTo('App\Survey');
    }

     /**
      * Relationship with pt.
      *
      */
    public function pt()
    {
        return $this->belongsTo('App\Pt');
    }

}
