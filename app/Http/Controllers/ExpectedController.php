<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Expected;
use App\Item;

use Auth;

class ExpectedController extends Controller
{

    public function manageExpected()
    {
        return view('expected.index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $error = ['error' => 'No results found, please try with different keywords.'];
        $expecteds = Expected::latest()->withTrashed()->paginate(5);
        if($request->has('q')) 
        {
            $search = $request->get('q');
            $expected = Expected::join('items', 'items.id', '=', 'expected_results.item_id')->where('items.pt_id', 'LIKE', "%{$search}%")->latest()->withTrashed()->paginate(5);
        }
        foreach($expecteds as $expected)
        {
            $expected->itm = $expected->item->pt_id;
            $expected->rslt = $expected->result($expected->result);
        }
        $response = [
            'pagination' => [
                'total' => $expecteds->total(),
                'per_page' => $expecteds->perPage(),
                'current_page' => $expecteds->currentPage(),
                'last_page' => $expecteds->lastPage(),
                'from' => $expecteds->firstItem(),
                'to' => $expecteds->lastItem()
            ],
            'data' => $expecteds
        ];

        return $expecteds->count() > 0 ? response()->json($response) : $error;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'item_id' => 'required',
            'result' => 'required',
            'tested_by' => 'required',
        ]);
        $request->request->add(['user_id' => Auth::user()->id]);

        $create = Expected::create($request->all());

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
        $this->validate($request, [
            'item_id' => 'required',
            'result' => 'required',
            'tested_by' => 'required',
        ]);
        $request->request->add(['user_id' => Auth::user()->id]);

        $edit = Expected::find($id)->update($request->all());

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
        Expected::find($id)->delete();
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
        $expected = Expected::withTrashed()->find($id)->restore();
        return response()->json(['done']);
    }
    /**
     * Function to return list of rounds.
     *
     */
    public function items()
    {
        $items = Item::pluck('pt_id', 'id');
        $categories = [];
        foreach($items as $key => $value)
        {
            $categories[] = ['id' => $key, 'value' => $value];
        }
        return $categories;
    }
    /**
     * Function to return list of options.
     *
     */
    public function options()
    {
        $results = [
            Expected::NEGATIVE => 'Negative',
            Expected::POSITIVE => 'Positive',
            Expected::EITHER => 'Either'
        ];
        $categories = [];
        foreach($results as $key => $value)
        {
            $categories[] = ['id' => $key, 'value' => $value];
        }
        return $categories;
    }
}