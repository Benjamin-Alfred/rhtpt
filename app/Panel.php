<?php namespace App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Panel extends Model
{
  	public $fillable = ['lot_id', 'panel', 'panel_label', 'material_id', 'result', 'prepared_by', 'tested_by', 'user_id'];
  	/**
  	 * Enabling soft deletes for items.
  	 *
  	 */
  	use SoftDeletes;
  	protected $dates = ['deleted_at'];
	  //	Constants for panel IDs
    const S1 = 1;
    const S2 = 2;
	  const S3 = 3;
    const S4 = 4;
	  const S5 = 5;
    const S6 = 6;

  	/**
  	 * The database table used by the model.
  	 *
  	 * @var string
  	 */
  	protected $table = 'panels';
    /**
  	 * User relationship
  	 *
  	 */
     public function user()
     {
          return $this->belongsTo('App\User');
	 }
    /**
  	 * Material relationship
  	 *
  	 */
     public function material()
     {
          return $this->belongsTo('App\Material');
     }
    /**
  	 * Lot relationship
  	 *
  	 */
     public function lot()
     {
          return $this->belongsTo('App\Lot');
     }
    /**
  	 * Return readable result
  	 *
  	 */
     public function result($result)
     {
          $resultValue = "";

          if($result == Expected::NEGATIVE) $resultValue = 'Negative';
          if($result == Expected::POSITIVE) $resultValue = 'Positive';
          if($result == Expected::EITHER) $resultValue = 'Either';
              
          return $resultValue;
     }
}