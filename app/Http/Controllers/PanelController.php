<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Panel;
use App\Material;
use App\Round;
use App\User;
use App\Lot;

use Auth;

class PanelController extends Controller
{

    public function managePanel()
    {
        return view('panel.index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $ITEMS_PER_PAGE = 30;
        $error = ['error' => 'No results found, please try with different keywords.'];
        $activeLots = Lot::pluck('id');
        $panels = Panel::whereIn('lot_id', $activeLots)->latest()->withTrashed()->paginate($ITEMS_PER_PAGE);
        if($request->has('q')) 
        {
            $search = $request->get('q');
            $panels = Panel::whereIn('lot_id', $activeLots)->where('pt_id', 'LIKE', "%{$search}%")->latest()->withTrashed()->paginate($ITEMS_PER_PAGE);
        }
        foreach($panels as $panel)
        {
            $panel->sample = $panel->panel_label;
            $panel->rslt = $panel->result($panel->result);
            $panel->lt = $panel->lot->lt();
        }
        $response = [
            'pagination' => [
                'total' => $panels->total(),
                'per_page' => $panels->perPage(),
                'current_page' => $panels->currentPage(),
                'last_page' => $panels->lastPage(),
                'from' => $panels->firstItem(),
                'to' => $panels->lastItem()
            ],
            'data' => $panels
        ];
        return $panels->count() > 0 ? response()->json($response) : $error;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {       
        $panels = Panel::where('lot_id', 'LIKE', $request->lot_id)->where('panel', 'LIKE', $request->panel)->withTrashed()->get();
        
        if ($panels->count() > 0) {

            return response()->json('error');

        }else
        {
            $this->validate($request, [
            'lot_id' => 'required',
            'panel' => 'required',
            'panel_label' => 'required',
            'material_id' => 'required',
            'result' => 'required',
            'prepared_by' => 'required',
            'tested_by' => 'required',
            ]);
            $request->request->add(['user_id' => Auth::user()->id]);

            $create = Panel::create($request->all());

            return response()->json($create);
        }
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
        $this->validate($request, [
            'lot_id' => 'required',
            'panel' => 'required',
            'panel_label' => 'required',
            'material_id' => 'required',
            'result' => 'required',
            'prepared_by' => 'required',
            'tested_by' => 'required',
        ]);
        $request->request->add(['user_id' => Auth::user()->id]);
        
        $edit = Panel::find($id)->update($request->all());

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
        Panel::find($id)->delete();
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
        $item = Panel::withTrashed()->find($id)->restore();
        return response()->json(['done']);
    }
    /**
     * Function to return list of materials.
     *
     */
    public function materials()
    {
        $materials = Material::pluck('batch', 'id');
        $categories = [];
        foreach($materials as $key => $value)
        {
            $categories[] = ['id' => $key, 'value' => $value];
        }
        return $categories;
    }
}