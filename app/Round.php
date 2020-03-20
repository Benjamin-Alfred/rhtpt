<?php namespace App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Round extends Model
{
	public $fillable = ['name', 'description', 'start_date', 'end_date', 'user_id'];
  	/**
  	 * Enabling soft deletes for rounds.
  	 *
  	 */
  	use SoftDeletes;
  	protected $dates = ['deleted_at'];
    /**
   * Override parent boot and Call deleting event
   *
   * @return void
   */
   protected static function boot() 
    {
      parent::boot();

      static::deleting(function($rounds) {
         foreach ($rounds->lots()->get() as $lot) {
            $lot->delete();
         }
      });
      static::restoring(function ($rounds) {
        $rounds->lots()->restore();
        foreach ($rounds->lots()->get() as $lot) {
            $lot->panels()->restore();
         }
      });
    }

  	/**
  	 * The database table used by the model.
  	 *
  	 * @var string
  	 */
  	protected $table = 'rounds';
    /**
  	* Enrolment relationship
  	*
  	*/
    public function enrolments()
    {
         return $this->hasMany('App\Enrol');
    }

    /**
    * Shipment relationship
    *
    */
    public function shipments()
    {
         return $this->hasMany('App\Shipment');
    }

    /**
    * Lots relationship
    *
    */
    public function lots()
    {
         return $this->hasMany('App\Lot');
    }

    /**
    * Survey relationship
    *
    */
    public function surveys()
    {
         return $this->hasMany('App\Survey');
    }


  /**
  * Return round ID given the uid
  * @param $title the unique title of the round
  */
  public static function idByTitle($title=NULL)
  {
      if($title!=NULL)
      {
          try 
          {
              $round = Round::where('name', $title)->orderBy('name', 'asc')->firstOrFail();
              return $round->id;
          } 
          catch (ModelNotFoundException $e) 
          {
              Log::error("The round ` $title ` does not exist:  ". $e->getMessage());
              //TODO: send email?
              return null;
          }
      }
      else
      {
         return null;
      }
  }
  /**
  * Return round name given the id
  * @param $roundID the round primary key
  */
  public static function nameByID($roundID=0)
  {
      $roundName = "";
      if($roundID > 0)
      {
          try 
          {
              $round = Round::find($roundID);
              $roundName = $round->name;
          } 
          catch (ModelNotFoundException $e) 
          {
              Log::error("The round ` $roundID ` does not exist:  ". $e->getMessage());
          }
      }

      return $roundName;
  }
    /**
  	 * Constants for durations
  	 *
  	 */
  	const ONE = 1;
  	const TWO = 2;
  	const THREE = 3;
    const FOUR = 4;
  	const FIVE = 5;
  	const SIX = 6;
  	const SEVEN = 7;
  	const EIGHT = 8;
    /**
     * Function to check if round has enrolments
     *
     */

}
