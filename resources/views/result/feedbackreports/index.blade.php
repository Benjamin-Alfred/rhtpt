@extends('app')
@section('content')
<div class="row">
    <div class="col-sm-12">
        <ol class="breadcrumb">
            <li><a href="{!! url('home') !!}"><i class="fa fa-home"></i> {!! trans('messages.home') !!}</a></li>
            <li class="active"><i class="fa fa-cubes"></i> {!! trans('messages.pt') !!}</li>
            <li class="active"><i class="fa fa-cube"></i> {!! trans_choice('messages.result', 2) !!}</li>
            <li class="active">
                <a class="btn btn-sm btn-carrot" href="#" onclick="window.history.back();return false;" alt="{!! trans('messages.back') !!}" title="{!! trans('messages.back') !!}">
                    <i class="fa fa-step-backward"></i>
                        {!! trans('messages.back') !!}
                </a>
            </li>
        </ol>
    </div>
</div>
<table class="table" cellspacing="0" style="font-size:15px">
    <tr><td colspan="5"><center><img src="{{ asset('/img/coa.png') }}  "></center></td></tr>
    <tr style="text-align:center"><td colspan="5"><b>MINISTRY OF HEALTH</b></td></tr>
    <tr style="text-align:center"><td colspan="5"><b>NATIONAL PUBLIC HEALTH LABORATORY</b></td></tr>
    <tr style="text-align:center"><td colspan="5"><b>KENYA EXTERNAL QUALITY ASSESSMENT SCHEME (KNEQAS)</b></td></tr>
    <tr style="text-align:center"><td colspan="5"><b>HIV SEROLOGY PROFICIENCY TESTING</b></td></tr>
    <tr style="text-align:center"><td colspan="5"><b>P.O Box 20750 - 00202, NAIROBI, nphlpt@nphl.go.ke</b></td></tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>
    <tr style="text-align:center"><td colspan="5">
        <?php
            $title = "Preliminary Report";
            $controlNumber = "MOH/F/NPHL/KNEQAS/SER/HIV/32";
            if(count($data['amendments']) > 0){
                $title = "Amended Report";
                $controlNumber = "MOH/F/NPHL/KNEQAS/SER/HIV/34";

                foreach ($data['amendments'] as $amendment) {
                    if($amendment['status'] == 1){//Active
                        $amended = $amendment->toArray();
                        $amended['feedback'] = $amended['feedback'] == 1?'Unsatisfactory':'Satisfactory';
                    }
                }
            }else{ 
                $amended['feedback'] = $data['feedback'];
                $amended['incorrect_results'] = $data['incorrect_results'];
                $amended['wrong_algorithm'] = $data['wrong_algorithm'];
                $amended['use_of_expired_kits'] = $data['use_of_expired_kits'];
                $amended['incomplete_kit_data'] = $data['incomplete_kit_data'];
                $amended['incomplete_results'] = $data['incomplete_results'];
                $amended['dev_from_procedure'] = $data['dev_from_procedure'];
                $amended['incomplete_other_information'] = $data['incomplete_other_information'];

                if($data['round_published_at']){
                    $title = "Final Report";
                    $controlNumber = "MOH/F/NPHL/KNEQAS/SER/HIV/33";
                }
            } 
        ?>
        <b><?php echo $title; ?></b>
    </td></tr>
    <tr> <td colspan="5" style="border-top:solid 2px black;"> &nbsp;</td> </tr>
    <tr>
        <td><b>Round</b></td>
        <td colspan="2">{{$data['round_name']}}</td>
        <td><b>County</b></td>
        <td>{{$data['county']}}</td>
    </tr>
    <tr>
        <td><b>Tester ID</b></td>
        <td colspan="2">{{$data['tester_id']}}</td>
        <td><b>Sub County</b></td>
        <td>{{$data['sub_county']}}</td>
    </tr>
    <tr>
        <td><b>Tester Name</b></td>
        <td colspan="2">{{$data['user_name']}}</td>
        <td><b>Facility</b></td>
        <td>{{$data['facility']}}</td>
    </tr>
    <tr>
        <td><b>Program</b></td>
        <td colspan="2">{{$data['program_name']}}</td>
        <td><b>Facility MFL</b></td>
        <td>{{$data['mfl']}}</td>
    </tr>
    <tr> <td> &nbsp;</td> </tr>
    <tr class="text-center"> <td colspan="5"><u><b>RE: Proficiency Testing Results</b></u></td></tr>
    <tr class="text-center"> <td colspan="5">NPHL acknowledges receipt of your Proficiency Testing results for Round {{$data['round_name']}}.</td></tr>
    <tr class="text-center"> <td colspan="5">Your overall performance is <b>{{$amended['feedback']}}</b>.</td></tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>

    @if ($amended['feedback'] === 'Unsatisfactory')
    <tr class="text-center"> <td colspan="5">The reason/s for <b> {{$data['feedback']}}</b> is/are:</td></tr>    
    <tr >        
        <td style ="border:solid 1px black;"><input type="checkbox" style="display: inline"
        @if($amended['incorrect_results'] == 1)
        checked
        @endif /> Incorrect Result</td>
        <td style ="border:solid 1px black;"><input type="checkbox" style="display: inline"
        @if($amended['wrong_algorithm'] == 1)
         checked
        @endif /> Wrong Algorithm</td>
        <td style ="border:solid 1px black;"><input type="checkbox" style="display: inline"
        @if($amended['use_of_expired_kits'] == 1)
         checked
        @endif /> Use of Expired Kits</td>              
        <td style ="border:solid 1px black;"><input type="checkbox" width="200" style="display: inline"
        @if($amended['incomplete_kit_data'] == 1)
         checked
        @endif /> Incomplete Kit Data</td>
        <td></td>
    </tr>
    <tr >
       <td style ="border:solid 1px black;"><input type="checkbox" style="display: inline"
        @if($amended['incomplete_results'] == 1)
         checked
        @endif />Incomplete Results</td>  
        <td style ="border:solid 1px black;"><input type="checkbox" width="200" style="display: inline"
        @if($amended['dev_from_procedure'] == 1)
         checked
        @endif  /> Deviation From Procedure</td>
        <td colspan="2" style ="border:solid 1px black;"><input type="checkbox" width="200" style="display: inline"
        @if($amended['incomplete_other_information'] == 1)
         checked
        @endif /> Incomplete Other Information</td>
        <td></td>
    </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>  
   @endif


    <tr class="text-center">
        <td style ="border:solid 1px black;" rowspan="2">PT Sample ID</td>
        <td style ="border:solid 1px black;" colspan="3">Your Results</td>
        <td style ="border:solid 1px black;" rowspan="2">Expected Results</td>
    </tr>
    <tr>
        <th style ="border:solid 1px black;">{{$data['determine']}}<br>Kit Result</th>
        <th style ="border:solid 1px black;">{{$data['firstresponse']}}<br>Kit Result</th>
        <th style ="border:solid 1px black;">Final Result</th>
    </tr>
    <tr>
        <td style ="border:solid 1px black;">{{$data['sample_1']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_1_kit1_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_1_kit2_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_1_final_results']}}</td>
        <td style ="border:solid 1px black; text-transform: uppercase;">{{$data['expected_result_1']}}</td>
    </tr>
    <tr>
        <td style ="border:solid 1px black;">{{$data['sample_2']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_2_kit1_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_2_kit2_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_2_final_results']}}</td>
        <td  style ="border:solid 1px black; text-transform: uppercase">{{$data['expected_result_2']}}</td>
    </tr>
    <tr>
        <td style ="border:solid 1px black;">{{$data['sample_3']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_3_kit1_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_3_kit2_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_3_final_results']}}</td>
        <td style ="border:solid 1px black; text-transform: uppercase;">{{$data['expected_result_3']}}</td>
    </tr>
    <tr>
        <td style ="border:solid 1px black;">{{$data['sample_4']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_4_kit1_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_4_kit2_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_4_final_results']}}</td>
        <td style ="border:solid 1px black; text-transform: uppercase;">{{$data['expected_result_4']}}</td>
    </tr>
    <tr>
        <td style ="border:solid 1px black;">{{$data['sample_5']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_5_kit1_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_5_kit2_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_5_final_results']}}</td>
        <td style ="border:solid 1px black; text-transform: uppercase;">{{$data['expected_result_5']}}</td>
    </tr>
    <tr>
        <td style ="border:solid 1px black;">{{$data['sample_6']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_6_kit1_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_6_kit2_results']}}</td>
        <td style ="border:solid 1px black;">{{$data['pt_panel_6_final_results']}}</td>
        <td style ="border:solid 1px black; text-transform: uppercase;">{{$data['expected_result_6']}}</td>
    </tr> 

    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr>
        <td colspan="5"><b>Expert Comment:</b></td>
    </tr>
    <tr>
        <td colspan="5">{{$data['pt_approved_comment']}}</td>
    </tr>
    @if(isset($amended['reason_for_amendment']))
    <tr>
        <td colspan="5"><b>Amendment Comment:</b> {{$amended['reason_for_amendment']}}</td>
    </tr>
    @endif
     <tr>
        <td colspan="5"><i>Please institute the necessary corrective measures before the next round of PT.</i></td>
    </tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    
    <tr>
        <td colspan="3"><?php echo $controlNumber; ?> &nbsp;&nbsp;&nbsp;&nbsp;Ver. 2</td>
        <td> Page 1</td>
        <td style="text-align:right"><img src="{{ asset('/img/ukas.png')}}" alt="" border="1" height="55" width="100" /></td> 
    </tr>    
    <tr><td><div style="page-break-before: always"></div></td></tr>
    <tr><td colspan="5"><b><div style="text-align:center">Testing Scheme Information</div></b></td></tr>
    <tr class="text-center"><td style ="border:solid 2px black;" colspan="5">
            <p>1.  The HIV-PT is a Qualitative scheme.</p>
            <p>2.  The panel samples come as a green pellet in 2 ml vials.</p>
            <p>3.  The PT panel samples have been fully characterized for the assigned HIV Sero-status.</p>
            <p>4.  The panel samples have been tested for stability and are stable.</p>
            <p>5.  Homogeneity was done using systematic random sampling and the results were the same as those of expected results.</p>
            <p>6.  Participant’s performance report is confidential and will ONLY be shared with responsible County Quality officers for purposes of corrective interventions</p>
            <p>7.  Subcontracted services: PT panel distribution, return of results.</p>
            <p>8. The scheme’s final report with summaries with overall performance analysis will be available on (www.rhtpt.or.ke) within one month of closure of the round.</p></td>
    </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>
    <tr style="text-align:center"><td colspan="5"><b>PT Scheme Summary Performance.</b></td></tr>
    <tr style="text-align:center"> <td colspan="5"> (Will be available on your final report)</td> </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr><td colspan="5">&nbsp;</td></tr>
    <tr>
        <td style ="border:solid 1px black;"><b>Date Authorized:</b></td>
        <td style ="border:solid 1px black;" colspan="4">{{$data['date_approved']}}</td>
    </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>

    <tr>
        <td colspan="4"><img src="{{ asset('/img/sign_kitheka.png')}}" alt="" height="20" width="80" /></td>
        <td colspan="2"><img src="{{ asset('/img/sophie.png')}}" alt="" height="20" width="80" /></td>
    </tr>
    <tr>
        <td colspan="4">Franklin Kitheka</td>
        <td colspan="2">Sophie Mwanyumba</td>        
    </tr>
    <tr>
        <td colspan="3">Manager,</td>
        <td colspan="2">Quality Manager,</td>        
    </tr>
    <tr>
        <td colspan="3">Kenya External Quality Assessment Scheme,</td>
        <td colspan="2">Kenya External Quality Assessment Scheme,</td>        
    </tr>
    <tr>
        <td colspan="4">Tel: 0722934622.</td>
        <td colspan="2">Tel: 0720203712.</td>        
    </tr>
    <tr> <td colspan="5"> &nbsp;</td> </tr>
    <tr style="text-align:center"><td colspan="5">Thank you for your participation.</td></tr>
    <tr style="text-align:center"><td colspan="5">End of the report.</td></tr>

    <tr>
        <td colspan="3"><?php echo $controlNumber; ?> &nbsp;&nbsp;&nbsp;&nbsp;Ver. 2</td>
        <td>Page 2</td>
        <td style="text-align:right"><img src="{{ asset('/img/ukas.png')}}" alt="" border="1" height="55" width="100" /></td> 
    </tr>    
</table>

@endsection
