<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Role;
use App\User;
use App\Tier;
use App\County;
use Input;


class AssignmentController extends Controller
{

    public function manageAssignments()
    {
        return view('assign.index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $users = User::latest()->paginate(10);
        $roles = Role::all();
        foreach($users as $user)
        {
            foreach($roles as $role)
            {
                $checks[$user->id][$role->id]['checked'] = $user->hasRole($role->name);
            }
        }
        $response = [
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem()
            ],
            'roles' => $roles,
            'users' => $users,
            'checks' => $checks
        ];
        return response()->json($response);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $arrayUserRoleMapping = $request->get('userRoles');
        $counties = $request->get('county');
        $users = User::all();
        $roles = Role::all();

        foreach ($users as $userkey => $user)
        {
      		foreach ($roles as $roleKey => $role)
            {
                $county = $request->get('county_'.$user->id.'_'.$role->id);
                $facility = $request->get('facility_'.$user->id.'_'.$role->id);
                $partner = $request->get('partner_'.$user->id.'_'.$role->id);
                $program = $request->get('program_'.$user->id.'_'.$role->id);

                dd($arrayUserRoleMapping[$userkey][$roleKey]);
                
                //If checkbox is clicked attach the role
                if(!empty($arrayUserRoleMapping[$userkey][$roleKey]))
                {
                    $user->detachRole($role);
                    $user->attachRole($role);
                    if(($county || $facility || $partner) && $role != Role::getAdminRole())
                    {
                        $program_id = NULL;
                        if($county && $role->id ==4)
                            $tier_id = $county;
                        else if($partner&& $role->id ==3 )
                            $tier_id = $partner;
                        else if($facility && $role->id ==2)
                        {
                            $tier_id = $facility;
                            $program_id = $program;
                        }
                        $tier = Tier::where('user_id', $user->id)->first();
                        if($tier)
                        {
                            $userTier = Tier::find($tier->id);
                            $userTier->user_id = $user->id;
                            $userTier->role_id = $role->id;
                            $userTier->tier = $tier_id;
                            $userTier->program_id = $program_id;
                            $userTier->save();
                        }
                        else
                        {
                            $userTier = new Tier;
                            $userTier->user_id = $user->id;
                            $userTier->role_id = $role->id;
                            $userTier->tier = $tier_id;
                            $userTier->program_id = $program_id;
                            $userTier->save();
                        }
                    }
                }
                // //If checkbox is NOT clicked detatch the role
                else if(empty($arrayUserRoleMapping[$userkey][$roleKey]))
                {
                    $tier = Tier::where('user_id', $user->id)->where('role_id', $role->id)->first();
                    if($tier)
                        $tier->delete();
                    $user->detachRole($role);
                }
            }
    	}
        return response()->json($arrayUserRoleMapping);
    }
    /**
    *   Controller function for making view for assigning roles to users
    *
    *   @return Response
    */
    Public function assign()
    {
        $users = User::all();
        $roles = Role::all();
        $userRoleData = array('users'=>$users, 'roles'=>$roles);
        $counties = County::lists('name', 'id')->toArray();
        $subCounties = SubCounty::lists('name', 'id')->toArray();
        $facilities = Facility::lists('name', 'id')->toArray();
        $partners = Shipper::where('shipper_type', Shipper::PARTNER)->lists('name', 'id')->toArray();
        $programs = Program::lists('name', 'id')->toArray();

        return view('role.assign', $userRoleData, compact('counties', 'subCounties', 'facilities', 'partners', 'programs'));
    }
}