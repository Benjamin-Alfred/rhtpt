<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Material;

use Auth;

class MaterialController extends Controller
{

    public function manageMaterial()
    {
        return view('material.index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $error = ['error' => 'No results found, please try with different keywords.'];
        $materials = Material::latest()->withTrashed()->paginate(5);
        if($request->has('q')) 
        {
            $search = $request->get('q');
            $materials = Material::where('batch', 'LIKE', "%{$search}%")->latest()->withTrashed()->paginate(5);
        }

        foreach($materials as $material)
            $material->mt = $material->material($material->material_type);
        $response = [
            'pagination' => [
                'total' => $materials->total(),
                'per_page' => $materials->perPage(),
                'current_page' => $materials->currentPage(),
                'last_page' => $materials->lastPage(),
                'from' => $materials->firstItem(),
                'to' => $materials->lastItem()
            ],
            'data' => $materials
        ];
        $request->request->add(['user_id' => Auth::user()->id]);

        return $materials->count() > 0 ? response()->json($response) : $error;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->request->add(['user_id' => Auth::user()->id]);

        $create = Material::create($request->all());

        return response()->json($create);
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
        $request->request->add(['user_id' => Auth::user()->id]);

        $edit = Material::find($id)->update($request->all());

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
        Material::find($id)->delete();
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
        $material = Material::withTrashed()->find($id)->restore();
        return response()->json(['done']);
    }
    /**
     * Function to return list of shipper types.
     *
     */
    public function options()
    {
        $material_types = [
            Material::WHOLE_BLOOD => 'Whole Blood',
            Material::PLASMA => 'Plasma',
            Material::SLIDE => 'Slide',
            Material::SERUM => 'Serum'
        ];
        $categories = [];
        foreach($material_types as $key => $value)
        {
            $categories[] = ['title' => $value, 'name' => $key];
        }
        return $categories;
    }
}