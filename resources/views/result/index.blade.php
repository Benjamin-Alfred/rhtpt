@extends('app')
@section('content')
<div class="row">
    <div class="col-sm-12">
        <ol class="breadcrumb">
            <li><a href="{!! url('home') !!}"><i class="fa fa-home"></i> {!! trans('messages.home') !!}</a></li>
            <li class="active"><i class="fa fa-cubes"></i> {!! trans('messages.pt') !!}</li>
            <li class="active"><i class="fa fa-cube"></i> {!! trans_choice('messages.result', 2) !!}</li>
        </ol>
    </div>
</div>
<div class="" id="manage-result">
    <!-- Round Listing -->
    <div class="row">
        <div class="col-lg-12 margin-tb">
            <div class="pull-left col-md-3">
                <h5>
                    <i class="fa fa-book"></i> {!! trans_choice('messages.result', 2) !!}
                    @permission('create-result')
                        <button type="button" class="btn btn-sm btn-belize-hole" data-toggle="modal" data-target="#create-result"
                        :disabled="active_round==0">
                            <i class="fa fa-plus-circle"></i>
                            {!! trans('messages.enter-result') !!}
                        </button>
                    @endpermission
                </h5>
            </div>
            <div class="col-md-5">
                <form method="POST" class="form-inline" id="import_results" v-on:submit.prevent="importResults" enctype="multipart/form-data" v-if="active_round!=0">
                    @permission('import-results')
                        <label for="importResultFile">Import Results:</label>
                        <input type="file" class="form-control mb-3 mr-sm-3" id="importResultFile" name="importResultFile" />
                        <button type="submit" class="btn btn-primary mb-2">Import</button>
                    @endpermission
                </form>
                <a class="btn btn-sm btn-carrot" style="display: none;" href="#" onclick="window.history.back();return false;" alt="{!! trans('messages.back') !!}" title="{!! trans('messages.back') !!}">
                    <i class="fa fa-step-backward"></i>
                    {!! trans('messages.back') !!}
                </a>
            </div>
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" placeholder="Search for..." v-model="query" v-on:keyup.enter="search(1)">
                    <span class="input-group-btn">
                        <button class="btn btn-secondary" type="button" @click="search(1)" v-if="!loading"><i class="fa fa-search"></i></button>
                        <button class="btn btn-secondary" type="button" disabled="disabled" v-if="loading">Searching...</button>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="row" v-if = "role !=2" >
        <div class="col-lg-12 margin-tb">
            <!-- <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="search()"> -->
                <div class="row">
                    <div class="pull-left col-sm-12">
                        <label>Filter by: </label>
                        <button data-toggle="collapse" class="btn btn-success btn-sm" data-target="#round">Round</button>
                        <button data-toggle="collapse" class="btn btn-success btn-sm" data-target="#region">Region</button>
                        <button data-toggle="collapse" class="btn btn-success btn-sm" data-target="#result_status_">Submission Status</button>
                        <button data-toggle="collapse" class="btn btn-success btn-sm" data-target="#feedback_status_">Feedback</button>
                        <button data-toggle="collapse" class="btn btn-success btn-sm" data-target="#reason_for_failure_">Reason for failure</button>
                        <button data-toggle="collapse" v-if="role==1" class="btn btn-success btn-sm" data-target="#lot">Lot</button>
                        <button data-toggle="collapse" v-if="role==1" class="btn btn-success btn-sm" data-target="#results_order">Order By</button>
                        <button class="btn btn-sm btn-alizarin" type="submit" @click="search(1)" v-if="!loading">Filter </button>
                        <button class="btn btn-sm btn-alizarin" type="button" disabled="disabled" v-if="loading">Searching...</button>
                    </div>
                </div>
                <div id="round" class="collapse">
                    <div class="row">
                        <div class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="round">Round:</label>
                            <div class="col-sm-8">
                                <select class="form-control" name="round" id="round_id" v-model="round" @change="getLots()">
                                    <option selected></option>
                                    <option v-for="round in rounds" :value="round.id">@{{ round.value }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="region" class="collapse">
                    <div class="row">
                        <div v-if = "role == 1 || role ==3" class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="title">Counties:</label>
                            <div class="col-sm-6">
                                <select class="form-control" name="county" id="county_id" @change="loadSubcounties()" v-model="county">
                                    <option selected></option>
                                   <option v-for="county in counties" :value="county.id">@{{ county.value }}</option>                         
                                </select>
                            </div>
                        </div>
                        <div v-if = "role == 1 || role ==3 || role == 4" class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="title">Sub Counties:</label>
                            <div class="col-sm-8">
                                <select class="form-control" name="sub_county" id="sub_id" @change="loadFacilities()" v-model="sub_county">
                                    <option selected></option>
                                   <option  v-for="sub in subcounties" :value="sub.id">@{{ sub.value }}</option>                         
                                </select>
                            </div>
                        </div>
                        <div v-if = "role == 1 || role ==3 || role == 4 || role ==7" class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="title">Facilities:</label>
                            <div class="col-sm-8">
                                <select class="form-control" name="facility" v-model="facility">
                                    <option selected></option>
                                    <option v-for="facility in facilities" :value="facility.id">@{{ facility.value }}</option> 
                                </select>
                            </div>
                        </div>                                
                    </div>
                </div> 
                <div id="result_status_" class="collapse">
                    <div class="row">
                        <div class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="title">Submission Status:</label>
                            <div class="col-sm-6">
                                <select class="form-control" name="result_status" v-model = "result_status" id="result_status_id">
                                    <option selected></option>
                                    <option value="0">Not Checked</option>                         
                                    <option value="1">Submitted</option>                         
                                    <option value="2">Evaluated</option>                         
                                    <option value="3">Verified</option>                         
                                </select>
                            </div>
                        </div>        
                    </div>
                </div> 
                <div id="feedback_status_" class="collapse">
                    <div class="row">
                        <div class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="title">Feedback:</label>
                            <div class="col-sm-6">
                                <select class="form-control" name="feedback_status" v-model = "feedback_status" id="feedback_status_id">
                                    <option selected></option>
                                       <option value="0">Satisfactory</option>                         
                                       <option value="1">Unsatisfactory</option>                         
                                </select>
                            </div>
                        </div>                    
                    </div>
                </div>
                <div id="reason_for_failure_" class="collapse">
                    <div class="row">
                        <div class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="title">Reason for failure:</label>
                            <div class="col-sm-6">
                                <select class="form-control" name="reason_for_failure" v-model = "reason_for_failure" id="reason_for_failure_id">
                                    <option selected></option>
                                    <option value="1">Incorrect Result</option>
                                    <option value="2">Wrong Algorithm</option>
                                    <option value="3">Use of Expired Kits</option>
                                    <option value="4">Incomplete Kit Data</option>
                                    <option value="5">Incomplete Results</option>
                                    <option value="6">Deviation From Procedure</option>
                                    <option value="7">Incomplete Other Information</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="lot" class="collapse">
                    <div class="row">
                        <div class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="lot">Lot:</label>
                            <div class="col-sm-6">
                                <select class="form-control" name="lot" v-model="lot" id="lot_id">
                                    <option selected></option>
                                    <option v-for="alot in lots" v-bind:value="alot.lot">Lot @{{alot.lot}}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="results_order" class="collapse">
                    <div class="row">
                        <div class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="results_order_id">Order By:</label>
                            <div class="col-sm-6">
                                <select class="form-control" name="results_order" v-model="results_order" id="results_order_id">
                                    <option selected></option>
                                    <option v-for="ro in resultsOrder" v-bind:value="ro.id">@{{ro.title}}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- </form>  -->
        </div>
    </div>
    <table class="table table-bordered">
        <tr>
            <th>#</th>
            <th>PT Round</th>
            <th>Tester ID</th>
            <th>Participant</th>
            <th>Panel ID</th>
            <th>Status</th>
            <th>Performance Report</th>
            <th>Action</th>
        </tr>
        <tr v-for="(result, key) in results">
            <td>@{{ key + 1 + ((pagination.current_page - 1) * pagination.per_page) }}</td>
            <td>@{{ result.rnd }}</td>
            <td>@{{ result.uid }}</td>
            <td>@{{ result.tester }}</td>
            <td v-if="result.uid==result.panel_id">@{{ result.panel_id }}</td>
            <td v-if="result.uid!=result.panel_id"><span class="label label-danger" title="Tested another user's panel">@{{ result.panel_id }}</span></td>
            <td>
                <button v-if="result.panel_status==0" class="mbtn mbtn-raised mbtn-danger mbtn-xs">Not Checked</button>
                <button v-if="result.panel_status==1" class="mbtn mbtn-raised mbtn-warning mbtn-xs">Submitted</button>
                <button v-if="result.panel_status==2" class="mbtn mbtn-raised mbtn-info mbtn-xs">Evaluated</button>
                <button v-if="result.panel_status==3" class="mbtn mbtn-raised mbtn-inverse mbtn-xs">Verified</button>
            </td>
            <td>
                <button v-if="result.feedback==0" class="mbtn mbtn-raised mbtn-success mbtn-xs">Satisfactory</button>
                <button v-if="result.feedback==1" class="mbtn mbtn-raised mbtn-primary mbtn-xs">Unsatisfactory</button>
                <button v-if="result.feedback==2 || result.feedback==null" class="mbtn mbtn-raised mbtn-warning mbtn-xs">Pending</button>
            </td>
            <td>
            @permission('view-result')               
                <button class="btn btn-sm btn-secondary" v-if="(result.panel_status==0 && result.user_role !=2) || result.panel_status==1 " @click.prevent="viewResult(result)" ><i class="fa fa-reorder"></i> View Result</button>
                <button class="btn btn-sm btn-success" v-if="result.panel_status==0 && result.user_role==2" @click.prevent="viewResult(result)" ><i class="fa fa-check-circle"></i> Verify</button>
                <a v-if="result.panel_status==3 " class="btn btn-sm btn-secondary" :href="'print_result/' +result.id + '?type=' + result.feedback +'&view=1'"><i class="fa fa-reorder"></i> View Report</a>
            @endpermission
            @permission('update-result')
                <button  v-if="result.panel_status==0" class="btn btn-sm btn-primary" @click.prevent="editResult(result)" ><i class="fa fa-edit"></i> Edit</button>
            @endpermission
            @permission('delete-result')
                <button v-if="result.rnd==active_round && (result.panel_status!=3 || result.uid!=result.panel_id)" class="btn btn-sm btn-danger" @click.prevent="deleteResult(result)"><i class="fa fa-power-off"></i> Delete </button>
            @endpermission 

            @permission('verify-result')
            <button v-if="result.panel_status>=2" class="btn btn-sm btn-primary" @click.prevent="showEvaluatedResults(result)"><i class="fa fa-list"></i> Review</button>
            <button v-if="result.panel_status==2 && result.feedback==0" class="btn btn-sm btn-success" @click.prevent="quickVerifyEvaluatedResult(result.id)"><i class="fa fa-check-circle"></i> Verify</button>
            @endpermission
	   
	       @permission('print-results') 
            <a v-if="result.panel_status==3 && result.feedback !=null && result.download_status ==0" class="btn btn-sm btn-wisteria" :href="'print_result/' +result.id + '?type=' + result.feedback"><i class="fa fa-print"></i> Download</a>
             <a v-if="result.panel_status==3 && result.feedback !=null && result.download_status ==1" class="btn btn-sm btn-concrete" :href="'print_result/' +result.id + '?type=' + result.feedback"><i class="fa fa-print"></i> Download</a>
            @endpermission 
            @permission('create-customer-survey-response') 
                <button v-if="result.rnd==active_round && result.has_feedback==0" 
                    class="btn btn-sm btn-info" @click.prevent="enterFeedback(result)" >
                        <i class="fa fa-user"></i> Customer Survey</button>
            @endpermission 
            </td>
        </tr>
    </table>
    <!-- Pagination -->
    <nav>
        <ul class="pagination">
            <li v-if="pagination.current_page > 1" class="page-item">
                <a class="page-link" href="#" aria-label="Previous"
                    @click.prevent="changePage(pagination.current_page - 1)">
                    <span aria-hidden="true">«</span>
                </a>
            </li>
            <li v-for="page in pagesNumber" class="page-item"
                v-bind:class="[ page == isActived ? 'active' : '']">
                <a class="page-link" href="#"
                    @click.prevent="changePage(page)">@{{ page }}</a>
            </li>
            <li v-if="pagination.current_page < pagination.last_page" class="page-item">
                <a class="page-link" href="#" aria-label="Next"
                    @click.prevent="changePage(pagination.current_page + 1)">
                    <span aria-hidden="true">»</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Enter Result Modal -->
    <div class="modal fade" id="create-result" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="myModalLabel">Test Results</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="createResult" id="analysis_results">
                            <div class="col-md-12">
                                <div class="form-group row">
                                    <label class="col-sm-5 form-control-label" for="title">PT Round:</label>
                                    <div class="col-sm-7">
                                        <select class="form-control c-select" name="round_id" >
                                            <option selected></option>
                                            <option v-for="round in roundsDone" :value="round.id">@{{ round.value }}</option>   
                                        </select>
                                        <span v-if="formErrors['round_id']" class="error text-danger">@{{ formErrors['round_id'] }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-5 form-control-label" for="title">Tester ID on Form:</label>
                                    <div class="col-sm-7">
                                        <input type="number" name="tester_id" class="form-control" />
                                    </div>
                                </div>
                                <div v-for="frm in form">
                                    <p class="text-primary">@{{ frm.title }}</p>
                                    <hr>
                                    <div v-for="item in frm.fields">
                                        <div class="form-group row">
                                            <label class="col-sm-5 form-control-label" for="title">@{{ item.title }}:</label>
                                            <div class="col-sm-7">
                                                <div v-if="item.tag == 1">
                                                    <div class="form-checkbox checkbox-inline" v-for="option in item.options">
                                                        <label class="form-checkbox-label">
                                                            <input type="checkbox" :value="option.id" :name="'field_'+item.id">
                                                            @{{ option.title }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <div v-if="item.tag == 2">
                                                    <input type="hidden" :name="'field_'+item.id" :value="fieldvalues[item.id]">
                                                    <my-date-component :tag="'field_'+item.id" v-on:change="changedate"></my-date-component>
                                                </div>
                                                <div v-if="item.tag == 3">
                                                    <input type="email" :name="'field_'+item.id" class="form-control" />
                                                </div>
                                                <div v-if="item.tag == 4">
                                                    <input type="text" :name="'field_'+item.id" class="form-control" />
                                                </div>
                                                <div v-if="item.tag == 5">
                                                    <div class="form-radio radio-inline" v-for="option in item.options">
                                                        <label class="form-radio-label">
                                                            <input type="radio" :value="option.id" :name="'field_'+item.id" />
                                                            @{{ option.title }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <div v-if="item.tag == 6">
                                                    <select class="form-control c-select" :name="'field_'+item.id">
                                                        <option selected></option>
                                                        <option v-for="option in item.options" :value="option.id">@{{ round.title }}</option>   
                                                    </select>
                                                </div>
                                                <div v-if="item.tag == 7">
                                                    <textarea :name="'field_'+item.id" class="form-control"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row col-sm-offset-5 col-sm-7">
                                    <button class="btn btn-sm btn-success"><i class='fa fa-plus-circle'></i> Submit</button>
                                    <button type="button" class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"><i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}</span></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Test Results Modal -->
    <div class="modal fade" id="edit-result" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                    <h4 class="modal-title" id="myModalLabel">Test Results</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="updateResult(frmData.pt_id, 'update_results')" id="update_test_results" data-vv-validate="update_results">
                            <input type="hidden"  name="id" :value="frmData.pt"> 
                            <div class="col-md-12">
                                <div class="form-group row">
                                    <label class="col-sm-5 form-control-label" for="title">PT Round:</label>
                                    <div class="col-sm-7">
                                        <input type="hidden" name="round_id" :value="frmData.round_id" />
                                        <input type="text" class="form-control" name="round_name" :value="frmData.round_name" readonly />
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-5 form-control-label" for="title">Tester ID on Form:</label>
                                    <div class="col-sm-7">
                                        <input type="number" name="tester_id" class="form-control" :value="frmData.tester" readonly />
                                    </div>
                                </div>
                                <div v-for="frm in form">
                                    <p class="text-primary">@{{ frm.title }}</p>
                                    <hr>
                                    <div v-for="item in frm.fields">
                                        <div v-if="frmData" v-for="dt in frmData.results">
                                            <div class="form-group row" v-if="dt.field_id==item.id">
                                                <label class="col-sm-5 form-control-label" for="title">@{{ item.title }}:</label>
                                                <div class="col-sm-7">
                                                    <div v-if="item.tag == 1">
                                                        <div class="form-checkbox form-checkbox-inline" v-for="option in item.options">
                                                            <label class="form-checkbox-label">
                                                                <input type="checkbox" :value="option.id" :name="'field_'+item.id" >
                                                                @{{ option.title }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div v-if="item.tag == 2">
                                                        <input type="hidden" :id="'field_'+item.id" :name="'field_'+item.id" :value="fieldvalues[item.id]" required />
                                                        <my-date-component :tag="'field_'+item.id" @change="changedate" :date="fieldvalues[item.id]" :ref="'field_'+item.id"></my-date-component>
                                                    </div>
                                                    <div v-if="item.tag == 3">
                                                        <input type="email" :name="'field_'+item.id" class="form-control" :value="dt.response"  />
                                                    </div>
                                                    <div v-if="item.tag == 4">
                                                        <input type="text" :name="'field_'+item.id" class="form-control" :value="dt.response"  />
                                                    </div>
                                                    <div v-if="item.tag == 5">

                                                        <div class="form-radio radio-inline" v-for="option in item.options">
                                                            <label class="form-radio-label">
                                                                <input type="radio" :value="option.id" :name="'field_'+item.id" v-bind="{ 'checked': option.id==dt.response}" />
                                                                @{{ option.title }}
                                                            </label>
                                                        </div>
                                                        <input v-if="dt.response==4" type="text" :name="'comment_'+item.id" class="form-control" :value="dt.comment"  />
                                                    </div>
                                                    <div v-if="item.tag == 6">
                                                        <select class="form-control c-select" :name="'field_'+item.id" >
                                                            <option selected></option>
                                                            <option v-for="option in item.options" v-bind="{ 'selected': option.id==dt.response}" :value="option.id">@{{ round.title }}</option>   
                                                        </select>
                                                    </div>
                                                    <div v-if="item.tag == 7">
                                                        <textarea v-if="dt.field_id==item.id" :name="'field_'+item.id" class="form-control" >@{{dt.response}}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row col-sm-offset-5 col-sm-7">
                                    <button class="btn btn-sm btn-success"><i class='fa fa-plus-circle'></i> Submit</button>
                                    <button type="button" class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"><i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}</span></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Test Results Modal -->
    <div class="modal fade" id="view-result" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="myModalLabel">Test Results</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group row" v-if="viewFormData.round">
                                <label class="col-sm-5 form-control-label" for="title"><b>PT Round:</b></label>
                                <div class="col-sm-7">
                                    <div  v-for="round in rounds">
                                        <label class="form-label" v-if="round.id==viewFormData.round.id" >@{{ round.value }}</label>
                                    </div>
                                    <span v-if="formErrors['round_id']" class="error text-danger">@{{ formErrors['round_id'] }}</span>
                                </div>
                            </div>
                            <div v-for="frm in sets">
                                <p class="text-primary"><b>@{{ frm.title }}</b></p>
                                <hr>
                                <div v-if="viewFormData.results" v-for="dt in viewFormData.results">
                                    <div v-for="item in frm.fields">
                                        <div class="form-group row" v-if="dt.field_id==item.id">
                                            <label class="col-sm-5 form-control-label" for="title"><b>@{{ item.title }}:</b></label>
                                            <div class="col-sm-7">
                                                <div v-if="item.tag == 1">
                                                    <div class="form-checkbox form-checkbox-inline" v-for="option in item.options">
                                                        <label class="form-checkbox-label">
                                                            @{{ option.title }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <div v-if="item.tag == 2 ||item.tag == 3||item.tag == 4||item.tag == 7">
                                                    <label class="form-label" v-if="dt.field_id==item.id">@{{dt.response}}</label>
                                                </div>
                                                
                                                <div v-if="item.tag == 5||item.tag == 6">
                                                    <div v-if="dt.field_id==item.id"  v-for="option in item.options">
                                                        <label class="form-label" v-if="option.id==dt.response" >@{{ option.title }}<span v-if="dt.response == 4">@{{ '-'+dt.comment}}</span></label>
                                                    </div>
                                                </div>                                 
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="verifyResult" id="verify_test_results">
                                <div v-if="viewFormData.pt">
                                    <input type="hidden" class="form-control" name="pt_id" :value="viewFormData.pt.id">
                                    <div class="form-group row" v-if="viewFormData.pt.panel_status==0">
                                        <label class="col-sm-5 form-control-label" for="title"><b>Verification Comment:</b></label>
                                        <div class="col-sm-7">
                                            <textarea name="comment" class="form-control"> @{{dt.response}}</textarea>
                                        </div>
                                        <p class="form-control">Once you verify, the document will be submitted to NPHL and you will not be able to change the results</p>
                                    </div>
                                    <hr v-if="viewFormData.pt.panel_status==0">
                                    <div class="form-group row col-sm-offset-5 col-sm-7">
                                        <button v-if="viewFormData.pt.panel_status==0" class="btn btn-sm btn-success "><i class='fa fa-check-circle'></i> Submit</button>&nbsp;
                                        <button type="button" class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"><i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}</span></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Verify Evaluted Results Modal -->
    <div class="modal fade" id="view-evaluted-result" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title col-md-6" id="myModalLabel">Evaluated Test Results</h4>
                <button type="button" class="btn btn-sm btn-success col-md-4" v-if="" @click="open_old_results(evaluated_results.pt_id)">View Previous Result </button>
                </div>
                <div class="modal-body" id="printpdf">
                    <div class="row">
                        <div class="col-md-12">    
                            <!-- Round details -->
                            <div class="row">
                                <div class="col-md-12">
                                   <table class="table table-bordered" id="partinfo">
                                        <tr><td colspan="4"><b>Participant Information </b></td></tr>
                                        <tr>
                                            <td><b>Round</b></td>
                                            <td>@{{evaluated_results.round_name}}</td>
                                            <td><b>County</b></td>
                                            <td>@{{evaluated_results.county}}</td>
                                        </tr>
                                         <tr>
                                            <td><b>Tester ID</b></td>
                                            <td>@{{evaluated_results.tester_id}}</td>
                                            <td><b>Sub County</b></td>
                                            <td>@{{evaluated_results.sub_county}}</td>
                                        </tr>
                                         <tr>
                                            <td><b>Tester Name</b></td>
                                            <td>@{{evaluated_results.user_name}}</td>
                                            <td><b>Facility</b></td>
                                            <td>@{{evaluated_results.facility}}</td>
                                        </tr>
                                         <tr>
                                            <td><b>Program</b></td>
                                            <td>@{{evaluated_results.program_name}}</td>
                                            <td><b>Facility MFL</b></td>
                                            <td>@{{evaluated_results.mfl}}</td>
                                        </tr>
                                         <tr>
                                            <td><b>Designation</b></td>
                                            <td>@{{evaluated_results.designation}}</td>
                                            <td><b>Panel ID</b></td>
                                            <td>@{{evaluated_results.tester_id_on_panel}}</td>
                                        </tr>
                                   </table>   
                                </div>
                            </div>
                            <!-- panel details -->
                            <div class="row">
                                <div class="col-md-12">
                                   <table class="table table-bordered">
                                        <tr><td colspan="2"> <b>Panel Information</b></td></tr>
                                        <tr>
                                            <td><b>Receive Date</b></td>
                                            <td>@{{evaluated_results.date_received}}</td>                                            
                                        </tr>
                                         <tr>
                                            <td><b>Constituted Date</b></td>
                                            <td>@{{evaluated_results.date_constituted}}</td>
                                        </tr>
                                         <tr>
                                            <td><b>Tested Date</b></td>
                                            <td>@{{evaluated_results.date_tested}}</td>
                                        </tr>
                                   </table>   
                                </div>
                            </div>
                            <!-- kit details -->
                            <div class="row">
                                <div class="col-md-12">
                                   <table class="table table-bordered">
                                        <tr><td colspan="3"><b>Kit Information</b></td></tr>
                                        <tr>
                                            <th>Kit Name</th>                                           
                                            <th>Kit No</th>                                           
                                            <th>Kit Expiry Date</th>                                           
                                        </tr>
                                         <tr>
                                            <td>@{{evaluated_results.determine}}</td>
                                            <td>@{{evaluated_results.determine_lot_no}}</td>
                                            <td>@{{evaluated_results.determine_expiry_date}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.firstresponse}}</td>
                                            <td>@{{evaluated_results.firstresponse_lot_no}}</td>
                                            <td>@{{evaluated_results.firstresponse_expiry_date}}</td>
                                        </tr>
                                         
                                   </table>   
                                </div>
                            </div>
                            <!-- Results details -->                            
                            <div class="row">
                                <div class="col-md-12">
                                   <table class="table table-bordered">
                                        <tr>
                                            <th>PT Sample ID</th>
                                            <th>@{{evaluated_results.determine}} Kit Result</th>
                                            <th>@{{evaluated_results.firstresponse}} Kit Result</th>
                                            <th>Final Result</th>
                                            <th>Expected Result</th>
                                        </tr>                                        
                                        <tr>
                                            <td>@{{evaluated_results.sample_1}}</td>
                                            <td>@{{evaluated_results.pt_panel_1_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_1_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_1_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_1}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_2}}</td>
                                            <td>@{{evaluated_results.pt_panel_2_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_2_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_2_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_2}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_3}}</td>
                                            <td>@{{evaluated_results.pt_panel_3_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_3_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_3_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_3}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_4}}</td>
                                            <td>@{{evaluated_results.pt_panel_4_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_4_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_4_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_4}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_5}}</td>
                                            <td>@{{evaluated_results.pt_panel_5_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_5_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_5_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_5}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_6}}</td>
                                            <td>@{{evaluated_results.pt_panel_6_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_6_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_6_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_6}}</td>
                                        </tr>
                                        
                                   </table>
                                </div>
                            </div>  
                            <div class="row">
                                <div class="col-md-12">
                                   <table class="table table-bordered">
                                        <tr>
                                            <td><b>PT Participant's Comments</b></td>
                                            <td>@{{evaluated_results.tester_comments}}</td>
                                        </tr>
                                        <tr>
                                            <td><b>Panel Results</b></td>
                                            <td>@{{evaluated_results.feedback}}</td>
                                        </tr>
                                        <tr>
                                            <td><b>Overall Evaluation</b></td>
                                            <td>@{{evaluated_results.feedback}}</td>
                                        </tr>                                       
                                   </table>
                                </div>
                            </div>         
                            <div class="row">
                                <div class="col-md-12">
                                   <table class="table table-bordered">                                        
                                       <tr><td><b>Reasons For Unsatisfactory:   </b></td></tr>
                                       <tr>
                                           <td>@{{evaluated_results.remark}}</td>
                                       </tr>
                                   </table>
                                </div>
                            </div>                           
                            <div v-if="evaluated_results.panel_status==2">
                                <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="verifyEvaluatedResult(evaluated_results.pt_id)" id="verify_evaluated_test_results">
                                    <div v-if="evaluated_results.feedback">
                                        <input type="hidden" class="form-control" name="pt_id" :value="evaluated_results.pt_id">
                                        <div class="form-group row" v-if="evaluated_results.panel_status==2">
                                            <label class="col-sm-5 form-control-label" for="title"><b>Expert Comment:</b></label>
                                            <div class="col-sm-7">
                                                <textarea name="comment" class="form-control">@{{evaluated_results.feedback}}</textarea>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <center>
                                            <button  class="btn btn-sm btn-success " title="Verify Evaluated Results">
                                                <i class='fa fa-check-circle'></i> Verify</button>
                                            <button  class="btn btn-sm btn-wisteria" type="button" @click="show_update_evaluated_results()" title="Update Evaluated Results">
                                                <i class='fa fa-pencil-square-o'></i> Update
                                            </button>
                                            <button  class="btn btn-sm btn-warning " @click="submitForReevaluation(evaluated_results.pt_id)" title="Submit Result for Revaluation" data-dismiss="modal">
                                                <i class='fa fa-list'></i> Re-evaluate</button>
                                            <button type="button" class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"><i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}</span></button>
                                            </center>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div v-else-if="evaluated_results.panel_status==3">
                                <div class="row">
                                    <label class="col-sm-5"><b>Comments:</b></label>
                                    <div class="col-sm-7">
                                        <p>@{{evaluated_results.pt_approved_comment}}</p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <label class="col-sm-3"><b>Date Verified:</b></label>
                                    <div class="col-sm-3">
                                        <p>@{{evaluated_results.date_approved}}</p>
                                    </div>
                                    <label class="col-sm-3"><b>Verified By:</b></label>
                                    <div class="col-sm-3">
                                        <p>@{{evaluated_results.approved_by}}</p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row" v-if="evaluated_results.amendments.length>0">
                                    <div class="col-sm-12">
                                        <strong>Amendments</strong><br>
                                        <div class="table-responsive" v-for="amendment in evaluated_results.amendments">
                                            <table class="table table-bordered table-striped">
                                                <tr>
                                                    <td><strong>Date</strong></td>
                                                    <td>@{{amendment.created_at}}</td>
                                                    <td><strong>Amended By</strong></td>
                                                    <td>@{{amendment.amendor.first_name}} @{{amendment.amendor.last_name}} </td>
                                                </tr>
                                                    <td><strong>Reason</strong></td>
                                                    <td colspan="3">@{{amendment.reason_for_amendment}}</td>
                                                <tr>
                                                </tr>
                                                <tr>
                                                    <td><strong>Feedback</strong></td>
                                                    <td>@{{getFeedback(amendment.feedback)}}</td>
                                                    <td colspan="2">
                                                        @{{getReasonForUnsatisfactory('incorrect_results', amendment.incorrect_results)}}
                                                        @{{getReasonForUnsatisfactory('incomplete_kit_data', amendment.incomplete_kit_data)}}
                                                        @{{getReasonForUnsatisfactory('dev_from_procedure', amendment.dev_from_procedure)}}
                                                        @{{getReasonForUnsatisfactory('incomplete_other_information', amendment.incomplete_other_information)}}
                                                        @{{getReasonForUnsatisfactory('use_of_expired_kits', amendment.use_of_expired_kits)}}
                                                        @{{getReasonForUnsatisfactory('invalid_results', amendment.invalid_results)}}
                                                        @{{getReasonForUnsatisfactory('wrong_algorithm', amendment.wrong_algorithm)}}
                                                        @{{getReasonForUnsatisfactory('incomplete_results', amendment.incomplete_results)}}
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <center>
                                        <button  class="btn btn-sm btn-wisteria" type="button" @click="show_amend_evaluated_results()" title="Amend Evaluated Report">
                                            <i class='fa fa-pencil-square-o'></i> Amend
                                        </button>&nbsp;
                                        <button type="button" class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">
                                                <i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}
                                            </span>
                                        </button>
                                    </center>
                                </div>                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Evaluated Test Results Modal -->
    <div class="modal fade" id="update-evaluated-result" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="min-width: 800px;">
                <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
                <h4 class="modal-title" id="myModalLabel">Update Evaluated Test Results</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">  
                            <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="update_evaluated_results(evaluated_results.pt_id)" id="update_evaluated_results"> 

                                <input type="hidden" name="participant_id" id="participant_id" class="form-control" value="evaluated_results.participant_id" v-model="evaluated_results.participant_id"/>

                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label">
                                        Tester ID: @{{evaluated_results.tester_id}}
                                    </label>
                                    <label class="col-sm-8 form-control-label">
                                        Name:
                                        @{{evaluated_results.first_name}}
                                        @{{evaluated_results.middle_name}}
                                        @{{evaluated_results.last_name}}
                                    </label>                                    
                                </div>
                                <div class="form-group row" style="display: none;">
                                    <div class="col-sm-4" :class="{ 'control': true }">
                                        <input v-validate="'required|alpha_spaces'" class="form-control"
                                            :class="{'input': true,'is-danger': errors.has('first_name') }" name="first_name"
                                            type="text" placeholder=""
                                            v-model="evaluated_results.first_name" />
                                        <span v-show="errors.has('first_name')" class="help is-danger">
                                            @{{ errors.first('first_name') }}</span>
                                    </div>
                                    
                                    <div class="col-sm-4" :class="{ 'control': true }">
                                        <input v-validate="'alpha_spaces'" class="form-control"
                                            :class="{'input': true,'is-danger': errors.has('middle_name') }" name="middle_name"
                                            type="text" placeholder=""
                                            v-model="evaluated_results.middle_name" />
                                        <span v-show="errors.has('middle_name')" class="help is-danger">
                                            @{{ errors.first('middle_name') }}</span>
                                    </div>

                                    <div class="col-sm-4" :class="{ 'control': true }">
                                        <input v-validate="'required|alpha_spaces'" class="form-control"
                                            :class="{'input': true,'is-danger': errors.has('last_name') }" name="last_name"
                                            type="text" placeholder=""
                                            v-model="evaluated_results.last_name" />
                                        <span v-show="errors.has('last_name')" class="help is-danger">
                                            @{{ errors.first('last_name') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label">
                                        Phone: @{{evaluated_results.phone_no}}
                                    </label>
                                    <label class="col-sm-8 form-control-label">
                                        Facility: @{{evaluated_results.facility}}
                                    </label>
                                </div>
                                <div class="form-group row">
                                    <div class="col-sm-4" :class="{ 'control': true }" style="display: none;">
                                        <input v-validate="'required'" class="form-control" :class="{'input': true, 'is-danger': errors.has('phone number') }" name="phone number" type="text" v-model="evaluated_results.phone_no"/>
                                        <span v-show="errors.has('phone number')" class="help is-danger">@{{ errors.first('phone number') }}</span>
                                    </div>
                                    <label class="col-sm-4 form-control-label" :class="{'help is-danger': errors.has('program_id') }" for="title">Program:</label>
                                    <div class="col-sm-4" :class="{ 'control': true }">
                                        <select class="form-control c-select" :class="{'input': true, 'is-danger': errors.has('program_id') }" name="program_id" v-model="evaluated_results.program">
                                            <option selected></option>
                                            <option v-for="program in programs" :value="program.id">
                                                @{{ program.value }}
                                            </option>   
                                        </select>
                                        <span v-show="errors.has('program_id')" class="help is-danger">@{{ errors.first('program_id') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label" for="title">Transfer to Tester:</label>
                                    <div class="col-sm-4" :class="{ 'control': true }">
                                        <input v-validate="'required'" class="form-control" name="new_tester_id" type="text" v-model="evaluated_results.new_tester_id"/>
                                    </div>
                                </div>

                                <div class="form-group row" style="display: none;">
                                    <label class="col-sm-4 form-control-label" for="title">Unique ID:</label>
                                    <label class="col-sm-4 form-control-label" for="title">Facility:</label>
                                </div>
                                <div class="form-group row" style="display: none;">
                                    <div class="col-sm-4">
                                        <input type="text" name="username" class="form-control" v-model="evaluated_results.tester_id" readonly />
                                    </div>
                                    <div class="col-sm-6 input-group input-group-sm ">
                                        <input type="text" name="facility_name" id="facility_name" class="form-control" v-model="evaluated_results.facility" readonly />
                                        <input type="hidden" name="facility_id" id="facility_id" class="form-control" v-model="evaluated_results.facility_id"/>
                                        <span class="input-group-btn invisible">
                                            <button class="btn btn-primary btn-sm" type="button" id="change_facility" data-toggle="collapse" data-target="#set-facility">change</button>
                                        </span>
                                    </div>
                                </div>
                                <div class="collapse" id="set-facility">
                                    <div class="form-group row">
                                        <label class="col-sm-4 form-control-label" for="title">Counties:</label>
                                        <label class="col-sm-4 form-control-label" for="title">Sub Counties:</label>
                                        <label class="col-sm-4 form-control-label" for="title">Facilities:</label>
                                        
                                    </div>
                                    <div class="form-group row">
                                        <div class="col-sm-3">
                                            <select class="form-control" name="county" id="county_id_" @change="loadSubcounties()" v-model="county_">
                                                <option selected></option>
                                               <option v-for="county in counties_" :value="county.id">@{{ county.value }}</option>                         
                                            </select>
                                        </div>
                                        <div class="col-sm-3">
                                            <select class="form-control" name="sub_county" id="sub_id_" @change="loadFacilities()" v-model="sub_county_">
                                                <option selected></option>
                                               <option  v-for="sub in sub_counties" :value="sub.id">@{{ sub.value }}</option>                         
                                            </select>
                                        </div>
                                        <div class="col-sm-3">
                                            <select class="form-control" name="facility" id = "facility_id_list" v-model="facility_">
                                                <option selected></option>
                                                <option v-for="facility in facilities" :value="facility.id">@{{ facility.value }}</option> 
                                            </select>
                                        </div>
                                        <div class="input-group-btn">
                                        <button class="btn btn-success btn-sm" type="button" id="set_facility" @click="set_facility()">Set</button>
                                    </div>
                                    </div>
                                </div>   
                                <hr>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label" for="panel_received">Panel Received</label>
                                    <label class="col-sm-4 form-control-label" for="panel_constituted">Panel Constituted</label>
                                    <label class="col-sm-4 form-control-label" for="panel_tested">Panel Tested</label>
                                </div>
                                <div class="form-group row">
                                    <div class="input-group input-group-sm col-sm-4" :class="{ 'control': true }">
                                        <input type="text" name="field_1" id="date_received" class="form-control" v-model ="evaluated_results.date_received"/>
                                        <span class="input-group-btn">
                                            <button class="btn btn-primary btn-sm" @click="check_date_button(1)" type="button" id="change_date_received" data-toggle="collapse" data-target="#set-date">change</button> 
                                        </span>
                                    </div> 
                                    <div class="input-group input-group-sm col-sm-4" :class="{ 'control': true }">
                                        <input type="text" name="field_2" id="date_constituted" class="form-control" v-model ="evaluated_results.date_constituted"/>   
                                        <span class="input-group-btn">
                                            <button class="btn btn-primary btn-sm" @click="check_date_button(2)" type="button" id="change_date_constituted" data-toggle="collapse" data-target="#set-date">change</button>
                                        </span>
                                    </div>
                                    <div class="input-group input-group-sm col-sm-4" :class="{ 'control': true }">
                                        <input type="text" name="field_3" id="date_tested" class="form-control" v-model ="evaluated_results.date_tested"/>
                                        <span class="input-group-btn">
                                            <button class="btn btn-primary btn-sm" @click="check_date_button(3)" type="button" id="change_date_tested" data-toggle="collapse" data-target="#set-date">change</button>
                                        </span>
                                    </div>
                                </div>                                                                
                                <div class="form-group row collapse" id="set-date">
                                    <div class="col-sm-3">
                                        <select class="form-control" name="year" id="year">
                                            <?php 
                                              $year = date('Y');
                                              $min = $year - 60;
                                              $max = $year;
                                              for( $i=$max; $i>=$min; $i-- ) {
                                                echo '<option value='.$i.'>'.$i.'</option>';
                                              }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-3">
                                        <select class="form-control" name="month" id="month" @change="check_total_days()">
                                            <option selected></option>                                           
                                            <?php for( $m=1; $m<=12; ++$m ) { 
                                                $month_label = date('F', mktime(0, 0, 0, $m, 1));

                                                if (strlen((string)$m)<2) {
                                                    $m = '0'.$m;
                                                }
                                            ?>
                                                <option value="<?php echo $m; ?>"><?php echo $month_label; ?></option>
                                            <?php } ?>
                                        </select> 
                                    </div>
                                    <div class="col-sm-2">
                                        <select class="form-control" name="day" id="day">
                                            <option value=''></option>                                           
                                        </select>
                                    </div>                                    
                                    <div class="input-group-btn">
                                        <button class="btn btn-success btn-sm" type="button" id="set_date" @click="set_date()">Set</button>
                                    </div>
                                </div>
                                <hr>
                                <table class="table table-bordered">
                                    <tr>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="incorrect_results" v-bind="{ 'checked': evaluated_results.incorrect_results ==1}"> Incorrect Results</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="incomplete_kit_data" v-bind="{ 'checked': evaluated_results.incomplete_kit_data ==1}"> Incomplete Kit Data</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="dev_from_procedure" v-bind="{ 'checked': evaluated_results.dev_from_procedure ==1}"> Deviation from Procedure</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="incomplete_other_information" v-bind="{ 'checked': evaluated_results.incomplete_other_information ==1}"> Incomplete Other Information</td>
                                    </tr>
                                    <tr>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="use_of_expired_kits" v-bind="{ 'checked': evaluated_results.use_of_expired_kits ==1}"> Use of Expired Kits</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="invalid_results" v-bind="{ 'checked': evaluated_results.invalid_results ==1}"> Invalid Results</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="wrong_algorithm" v-bind="{ 'checked': evaluated_results.wrong_algorithm ==1}"> Wrong Algorithm</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="incomplete_results" v-bind="{ 'checked': evaluated_results.incomplete_results ==1}"> Incomplete Results</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><input type="radio" value="0" id="satisfactory" name="feedback"><b> Satisfactory</b></td>
                                        <td colspan="2"><input type="radio" value="1" id="unsatisfactory" name="feedback"><b> Unsatisfactory</b></td>                               
                                    </tr>
                                </table> 
                                <hr>
                                <div class="form-group row">
                                    <div class="col-sm-6">
                                        <label class="col-sm-3 form-control-label" for="kit_1">Kit 1</label>
                                        <div class="input-group input-group-sm col-sm-9">
                                            <input type="text" disabled  class="form-control" name="kit_1" id="kit_1" :value="evaluated_results.determine">
                                            <input type="hidden" name="field_4" id="kit_id_1" class="form-control" :value="evaluated_results.determine_value" />
                                             <span class="input-group-btn">
                                                <button class="btn btn-primary btn-sm" type="button" @click="set_kit(1)"><i class="fa fa-edit"></i></button>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="col-sm-5 form-control-label" for="kit_1">Expiry Date</label>
                                        <div class="input-group input-group-sm col-sm-7">
                                            <input type="text" name="field_6" id="kit_1_expiry_date" 
                                                class="form-control" v-model ="evaluated_results.determine_expiry_date"/>
                                            <span class="input-group-btn">
                                                <button class="btn btn-primary btn-sm" type="button" id="change_kit_1_expiry_date">
                                                    <i class="fa fa-calendar"></i>
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-sm-6">
                                        <label class="form-control-label col-sm-3" for="kit_2">Kit 2</label>
                                        <div class="input-group input-group-sm col-sm-9">
                                            <input type="text" disabled  class="form-control" name="kit_2" id="kit_2" :value="evaluated_results.firstresponse">
                                            <input type="hidden" name="field_7" id="kit_id_2" class="form-control" :value="evaluated_results.firstresponse_value" />
                                             <span class="input-group-btn">
                                                <button class="btn btn-primary btn-sm" type="button" @click="set_kit(2)"><i class="fa fa-edit"></i></button>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-control-label col-sm-5" for="kit_2_expiry_date">Expiry Date</label>
                                        <div class="input-group input-group-sm col-sm-7">
                                            <input type="text" name="field_9" id="kit_2_expiry_date" 
                                                class="form-control" v-model ="evaluated_results.firstresponse_expiry_date"/>
                                            <span class="input-group-btn">
                                                <button class="btn btn-primary btn-sm" type="button" id="change_kit_2_expiry_date">
                                                    <i class="fa fa-calendar"></i>
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-sm-6">
                                        <label class="form-control-label col-sm-5" for="determine_lot_no">Kit 1 Lot Number</label>
                                        <div class="col-sm-7">
                                            <input class="form-control" type="text" class="form-control" name="field_5" id="determine_lot_no" :value="evaluated_results.determine_lot_no">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-control-label col-sm-5" for="firstresponse_lot_no">Kit 2 Lot Number</label>
                                        <div class="col-sm-7">
                                            <input class="form-control" type="text" class="form-control" name="field_8" id="firstresponse_lot_no" :value="evaluated_results.firstresponse_lot_no">
                                        </div>
                                    </div>
                                </div>

                                <table class="table table-bordered">
                                    <tr>
                                        <td>Test 1</td>
                                        <td>Test 2</td>
                                        <td>Final</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_1_kit1_results" id="sample_test_1" :value="evaluated_results.pt_panel_1_kit1_results">
                                                <input type="hidden" name="field_13" id="sample_test_value_1" class="form-control" :value="evaluated_results.pt_panel_1_kit1_results_value"/>
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(1)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_1_kit2_results" id="sample_test_2":value="evaluated_results.pt_panel_1_kit2_results">
                                                <input type="hidden" name="field_14" id="sample_test_value_2" class="form-control"  :value="evaluated_results.pt_panel_1_kit2_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(2)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_1_final_results" id="sample_final_3":value="evaluated_results.pt_panel_1_final_results">
                                                <input type="hidden" name="field_16" id="sample_final_value_3" class="form-control"  :value="evaluated_results.pt_panel_1_final_results_value"/>
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_final_result(3)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_2_kit1_results" id="sample_test_4":value="evaluated_results.pt_panel_2_kit1_results">
                                                <input type="hidden" name="field_17" id="sample_test_value_4" class="form-control"  :value="evaluated_results.pt_panel_2_kit1_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(4)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_2_kit2_results" id="sample_test_5":value="evaluated_results.pt_panel_2_kit2_results">
                                                <input type="hidden" name="field_18" id="sample_test_value_5" class="form-control"  :value="evaluated_results.pt_panel_2_kit2_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(5)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_2_final_results" id="sample_final_6":value="evaluated_results.pt_panel_2_final_results">
                                                <input type="hidden" name="field_20" id="sample_final_value_6" class="form-control"  :value="evaluated_results.pt_panel_2_final_results_value"/>
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_final_result(6)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_3_kit1_results" id="sample_test_7":value="evaluated_results.pt_panel_3_kit1_results">
                                                <input type="hidden" name="field_21" id="sample_test_value_7" class="form-control"  :value="evaluated_results.pt_panel_3_kit1_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(7)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_3_kit2_results" id="sample_test_8":value="evaluated_results.pt_panel_3_kit2_results">
                                                <input type="hidden" name="field_22" id="sample_test_value_8" class="form-control"  :value="evaluated_results.pt_panel_3_kit2_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(8)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_3_final_results" id="sample_final_9":value="evaluated_results.pt_panel_3_final_results">
                                                <input type="hidden" name="field_24" id="sample_final_value_9" class="form-control"  :value="evaluated_results.pt_panel_3_final_results_value"/>
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_final_result(9)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_4_kit1_results" id="sample_test_10":value="evaluated_results.pt_panel_4_kit1_results">
                                                <input type="hidden" name="field_25" id="sample_test_value_10" class="form-control"  :value="evaluated_results.pt_panel_4_kit1_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(10)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_4_kit2_results" id="sample_test_11":value="evaluated_results.pt_panel_4_kit2_results">
                                                <input type="hidden" name="field_26" id="sample_test_value_11" class="form-control"  :value="evaluated_results.pt_panel_4_kit2_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(11)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_4_final_results" id="sample_final_12":value="evaluated_results.pt_panel_4_final_results">
                                                <input type="hidden" name="field_28" id="sample_final_value_12" class="form-control"  :value="evaluated_results.pt_panel_4_final_results_value"/>
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_final_result(12)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_5_kit1_results" id="sample_test_13":value="evaluated_results.pt_panel_5_kit1_results">
                                                <input type="hidden" name="field_29" id="sample_test_value_13" class="form-control"  :value="evaluated_results.pt_panel_5_kit1_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(13)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_5_kit2_results" id="sample_test_14":value="evaluated_results.pt_panel_5_kit2_results">
                                                <input type="hidden" name="field_30" id="sample_test_value_14" class="form-control"  :value="evaluated_results.pt_panel_5_kit2_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(14)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_5_final_results" id="sample_final_15":value="evaluated_results.pt_panel_5_final_results">
                                                <input type="hidden" name="field_32" id="sample_final_value_15" class="form-control"  :value="evaluated_results.pt_panel_5_final_results_value"/>
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_final_result(15)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_6_kit1_results" id="sample_test_16":value="evaluated_results.pt_panel_6_kit1_results">
                                                <input type="hidden" name="field_33" id="sample_test_value_16" class="form-control"  :value="evaluated_results.pt_panel_6_kit1_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(16)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_6_kit2_results" id="sample_test_17":value="evaluated_results.pt_panel_6_kit2_results">
                                                <input type="hidden" name="field_34" id="sample_test_value_17" class="form-control"  :value="evaluated_results.pt_panel_6_kit2_results_value">
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_test_result(17)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm ">
                                                <input type="text" disabled  class="form-control" name="pt_panel_6_final_results" id="sample_final_18":value="evaluated_results.pt_panel_6_final_results">
                                                <input type="hidden" name="field_36" id="sample_final_value_18" class="form-control"  :value="evaluated_results.pt_panel_6_final_results_value"/>
                                                 <span class="input-group-btn">
                                                    <button class="btn btn-primary btn-sm" type="button" @click="set_final_result(18)"><i class="fa fa-edit"></i></button>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>                                    
                                </table>  
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('reason_for_change') }" for="reason_for_change">
                                        Reason For Change
                                    </label>                                    
                                    <div class="col-sm-6" :class="{ 'control': true }">
                                        <textarea v-validate="'required'" class="form-control" :class="{'input': true, 'is-danger': errors.has('reason_for_change') }" name="reason_for_change" id="reason_for_change" type="text"/> </textarea>
                                        <span v-show="errors.has('reason_for_change')" class="help is-danger">@{{ errors.first('reason_for_change') }}</span>
                                    </div>
                                </div>                     
                                <div class="form-group row col-sm-offset-2">
                                    <button  class="btn btn-sm btn-success " title="Update Evaluated Results">
                                        <i class='fa fa-check-circle'></i> Update
                                    </button>&nbsp;
                                    <button  class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">
                                            <i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Amended Test Report Modal -->
    <div class="modal fade" id="amend-test-report" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="min-width: 800px;">
                <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
                <h4 class="modal-title" id="myModalLabel"><strong>Amend Test Report</strong></h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">  
                            <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="amendTestReport(evaluated_results.pt_id)" id="amend_test_report"> 

                                <div class="form-group row">
                                    <span class="col-sm-2 dialog-form-label">Tester ID: </span>
                                    <span class="col-sm-2">@{{evaluated_results.tester_id}}</span>
                                    <span class="col-sm-2 dialog-form-label">Name:</span>
                                    <span class="col-sm-6">
                                        @{{evaluated_results.first_name}}
                                        @{{evaluated_results.middle_name}}
                                        @{{evaluated_results.last_name}}
                                    </span>
                                </div>
                                <div class="form-group row">
                                    <span class="col-sm-2 dialog-form-label">Phone:</span>
                                    <span class="col-sm-2">@{{evaluated_results.phone_no}}</span>
                                    <span class="col-sm-2 dialog-form-label">Facility:</span>
                                    <span class="col-sm-6">@{{evaluated_results.facility}}</span>
                                </div>
                                <div class="form-group row">
                                    <span class="col-sm-2 dialog-form-label">Program:</span>
                                    <span class="col-sm-10">@{{getProgram(evaluated_results.program)}}</span>
                                </div>
                                <hr>
                                <div class="form-group row">
                                    <span class="col-sm-2 dialog-form-label">Panel Received:</span>
                                    <span class="col-sm-2">@{{evaluated_results.date_received}}</span>
                                    <span class="col-sm-2 dialog-form-label">Panel Constituted:</span>
                                    <span class="col-sm-2">@{{evaluated_results.date_constituted}}</span>
                                    <span class="col-sm-2 dialog-form-label">Panel Tested:</span>
                                    <span class="col-sm-2">@{{evaluated_results.date_tested}}</span>
                                </div>
                                <hr>
                                <table class="table table-bordered">
                                    <tr>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="incorrect_results" v-bind="{ 'checked': evaluated_results.incorrect_results ==1}"> Incorrect Results</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="incomplete_kit_data" v-bind="{ 'checked': evaluated_results.incomplete_kit_data ==1}"> Incomplete Kit Data</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="dev_from_procedure" v-bind="{ 'checked': evaluated_results.dev_from_procedure ==1}"> Deviation from Procedure</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="incomplete_other_information" v-bind="{ 'checked': evaluated_results.incomplete_other_information ==1}"> Incomplete Other Information</td>
                                    </tr>
                                    <tr>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="use_of_expired_kits" v-bind="{ 'checked': evaluated_results.use_of_expired_kits ==1}"> Use of Expired Kits</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="invalid_results" v-bind="{ 'checked': evaluated_results.invalid_results ==1}"> Invalid Results</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="wrong_algorithm" v-bind="{ 'checked': evaluated_results.wrong_algorithm ==1}"> Wrong Algorithm</td>
                                        <td><input type="checkbox" value="1" class="unsatisfactory_group" @click="toggle_checkboxes()" name="incomplete_results" v-bind="{ 'checked': evaluated_results.incomplete_results ==1}"> Incomplete Results</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><input type="radio" value="0" id="satisfactory" name="feedback"><b> Satisfactory</b></td>
                                        <td colspan="2"><input type="radio" value="1" id="unsatisfactory" name="feedback"><b> Unsatisfactory</b></td>                               
                                    </tr>
                                </table> 
                                <hr>
                                <div class="form-group row">
                                    <div class="col-sm-6">
                                        <span class="col-sm-3 dialog-form-label">Kit 1</span>
                                        <span>@{{evaluated_results.determine}}</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="col-sm-5 dialog-form-label">Expiry Date</span>
                                        <span>@{{evaluated_results.determine_expiry_date}}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-sm-6">
                                        <span class="col-sm-3 dialog-form-label">Kit 2</span>
                                        <span>@{{evaluated_results.firstresponse}}</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="col-sm-5 dialog-form-label">Expiry Date</span>
                                        <span>@{{evaluated_results.firstresponse_expiry_date}}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-sm-6">
                                        <span class="col-sm-5 dialog-form-label">Kit 1 Lot Number</span>
                                        <span>@{{evaluated_results.determine_lot_no}}</span>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="col-sm-5 dialog-form-label">Kit 2 Lot Number</span>
                                        <span>@{{evaluated_results.firstresponse_lot_no}}</span>
                                    </div>
                                </div>

                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Test 1</th>
                                            <th>Test 2</th>
                                            <th>Final</th>
                                            <th>Expected</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>@{{evaluated_results.pt_panel_1_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_1_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_1_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_1}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.pt_panel_2_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_2_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_2_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_2}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.pt_panel_3_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_3_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_3_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_3}}</td>
                                       </tr>
                                        <tr>
                                            <td>@{{evaluated_results.pt_panel_4_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_4_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_4_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_4}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.pt_panel_5_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_5_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_5_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_5}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.pt_panel_6_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_6_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_6_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_6}}</td>
                                        </tr>                                    
                                    </tbody>
                                </table>  
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('reason_for_amendment') }" for="reason_for_amendment">
                                        <strong>Reason for amendment</strong>
                                    </label>                                    
                                    <div class="col-sm-6" :class="{ 'control': true }">
                                        <textarea v-validate="'required'" class="form-control" :class="{'input': true, 'is-danger': errors.has('reason_for_amendment') }" name="reason_for_amendment" id="reason_for_amendment" type="text"/> </textarea>
                                        <span v-show="errors.has('reason_for_amendment')" class="help is-danger">@{{ errors.first('reason_for_amendment') }}</span>
                                    </div>
                                </div>                     
                                <div class="form-group row">
                                    <center>
                                    <button  class="btn btn-sm btn-success " title="Amend Test Report">
                                        <i class='fa fa-check-circle'></i> Amend
                                    </button>&nbsp;
                                    <button  class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">
                                            <i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}
                                        </span>
                                    </button>
                                    </center>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Compare Evaluted Results Modal -->
    <div class="modal fade" id="compare-evaluted-result" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="myModalLabel">Evaluated Test Results</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12"> 
                         <div class="row">
                                <div class="col-md-12">
                                   <table class="table table-bordered">
                                       <tr>
                                            <td><b> Changed By: </b> @{{updated_evaluated_results.editing_user_name}}</td>
                                            <td><b> On: </b> @{{updated_evaluated_results.editing_updated_at}}</td>
                                        </tr>
                                       <tr><td colspan="2"><b>Reasons For Change: </b>@{{updated_evaluated_results.reason_for_change}}</td></tr>
                                   </table>
                                </div>
                            </div>   
                            <!-- Round details -->
                            <div class="row">
                                <div class="col-md-12">
                                    <b>Participant Information </b><br>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tr>
                                                <td><b>Round</b></td>
                                                <td>@{{evaluated_results.round_name}}</td>
                                                <td><b>Tester ID</b></td>
                                                <td>@{{evaluated_results.tester_id}}</td>
                                            </tr>
                                             <tr>
                                                <td><b>Tester</b></td>
                                                <td>@{{evaluated_results.user_name}}</td>
                                                <td><b>Program</b></td>
                                                <td>@{{evaluated_results.program_name}}</td>
                                            </tr>
                                             <tr>
                                                <td><b>County</b></td>
                                                <td>@{{evaluated_results.county}}</td>
                                                <td><b>Sub County</b></td>
                                                <td>@{{evaluated_results.sub_county}}</td>
                                            </tr>
                                             <tr>
                                                <td><b>MFL Code</b></td>
                                                <td>@{{evaluated_results.mfl}}</td>
                                                <td><b>Facility</b></td>
                                                <td>@{{evaluated_results.facility}}</td>
                                            </tr>
                                       </table>
                                   </div>
                                </div>
                            </div>
                            <!-- panel details -->
                            <div class="row">
                                <div class="col-md-12">
                                    <b>Panel Information</b><br>
                                    <table class="table table-bordered">
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td><b>Previous</b></td>
                                            <td><b>Current</b></td>
                                        </tr>
                                         <tr>
                                            <td><b>Receive Date</b></td>
                                            <td>@{{updated_evaluated_results.date_received}}</td>
                                            <td>@{{evaluated_results.date_received}}</td>
                                        </tr>
                                         <tr>
                                            <td><b>Constituted Date</b></td>
                                            <td>@{{updated_evaluated_results.date_constituted}}</td>
                                            <td>@{{evaluated_results.date_constituted}}</td>
                                        </tr>
                                        <tr>
                                            <td><b>Tested Date</b></td>
                                            <td>@{{updated_evaluated_results.date_tested}}</td>
                                            <td>@{{evaluated_results.date_tested}}</td>
                                        </tr>
                                   </table>   
                                </div>
                            </div>
                            <!-- kit details -->
                            <div class="row">
                                <div class="col-md-12">
                                    <b>Kit Information</b><br>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>&nbsp;</th>                                           
                                            <th>Name</th>                                           
                                            <th>Number</th>                                           
                                            <th>Expiry Date</th>                                           
                                        </tr>
                                        <tr>
                                            <td><strong>Previous</strong></td>
                                            <td>@{{updated_evaluated_results.determine}}</td>
                                            <td>@{{updated_evaluated_results.determine_lot_no}}</td>
                                            <td>@{{updated_evaluated_results.determine_expiry_date}}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Current</strong></td>
                                            <td>@{{evaluated_results.determine}}</td>
                                            <td>@{{evaluated_results.determine_lot_no}}</td>
                                            <td>@{{evaluated_results.determine_expiry_date}}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Previous</strong></td>
                                            <td>@{{updated_evaluated_results.firstresponse}}</td>
                                            <td>@{{updated_evaluated_results.firstresponse_lot_no}}</td>
                                            <td>@{{updated_evaluated_results.firstresponse_expiry_date}}</td>
                                        </tr>                                         
                                        <tr>
                                            <td><strong>Current</strong></td>
                                            <td>@{{evaluated_results.firstresponse}}</td>
                                            <td>@{{evaluated_results.firstresponse_lot_no}}</td>
                                            <td>@{{evaluated_results.firstresponse_expiry_date}}</td>
                                        </tr>
                                   </table>   
                                </div>
                            </div>
                            <!-- Results details -->                            
                            <div class="row">
                                <div class="col-md-12">
                                   <table class="table table-bordered">
                                        <tr>
                                            <th>PT Sample ID</th>
                                            <th>Determine</th>
                                            <th>First Response</th>
                                            <th>Final Result</th>
                                            <th>Expected Result</th>
                                        </tr>                                        
                                        <tr>
                                            <td>@{{evaluated_results.sample_1}}</td>
                                            <td>@{{evaluated_results.pt_panel_1_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_1_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_1_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_1}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_1}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_1_kit1_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_1_kit2_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_1_final_results}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_2}}</td>
                                            <td>@{{evaluated_results.pt_panel_2_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_2_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_2_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_2}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_2}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_2_kit1_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_2_kit2_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_2_final_results}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_3}}</td>
                                            <td>@{{evaluated_results.pt_panel_3_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_3_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_3_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_3}}</td>
                                        </tr>
                                          <tr>
                                            <td>@{{evaluated_results.sample_3}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_3_kit1_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_3_kit2_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_3_final_results}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_4}}</td>
                                            <td>@{{evaluated_results.pt_panel_4_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_4_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_4_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_4}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_4}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_4_kit1_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_4_kit2_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_4_final_results}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_5}}</td>
                                            <td>@{{evaluated_results.pt_panel_5_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_5_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_5_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_5}}</td>
                                        </tr>
                                         <tr>
                                            <td>@{{evaluated_results.sample_5}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_5_kit1_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_5_kit2_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_5_final_results}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_6}}</td>
                                            <td>@{{evaluated_results.pt_panel_6_kit1_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_6_kit2_results}}</td>
                                            <td>@{{evaluated_results.pt_panel_6_final_results}}</td>
                                            <td class="text-uppercase">@{{evaluated_results.expected_result_6}}</td>
                                        </tr>
                                        <tr>
                                            <td>@{{evaluated_results.sample_6}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_6_kit1_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_6_kit2_results}}</td>
                                            <td>OLD- @{{updated_evaluated_results.pt_panel_6_final_results}}</td>
                                        </tr>
                                        
                                   </table>
                                </div>
                            </div>  
                            <div class="row">
                                <div class="col-md-12">
                                   <table class="table table-bordered">
                                        <tr>
                                            <td><b>PT Participant's Comments</b></td>
                                            <td>@{{evaluated_results.tester_comments}}</td>
                                        </tr>
                                        <tr>
                                            <td><b>Panel Results</b></td>
                                            <td>@{{evaluated_results.feedback}}</td>
                                            <td>OLD- @{{updated_evaluated_results.feedback}}</td>
                                        </tr>
                                        <tr>
                                            <td><b>Overall Evaluation</b></td>
                                            <td>@{{evaluated_results.feedback}}</td>
                                            <td>OLD- @{{updated_evaluated_results.feedback}}</td>
                                        </tr>                                       
                                   </table>
                                </div>
                            </div>         
                            <div class="row">
                                <div class="col-md-12">
                                   <table class="table table-bordered">                                        
                                       <tr><b>Reasons For Unsatisfactory:   </b></tr>
                                       <tr>
                                           @{{evaluated_results.remark}}
                                       </tr>
                                       <tr>
                                           OLD- @{{updated_evaluated_results.remark}}
                                       </tr>
                                   </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Feedback Modal -->
    <div class="modal fade" id="customer-feedback" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                    <h4 class="modal-title" id="myModalLabel">Customer Survey and Comments for PT Round @{{questions.round_name}}</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="updateCustomerFeedback()" id="customer_feedback_form">
                            <input type="hidden"  name="pt_id" :value="questions.pt_id"> 
                            <input type="hidden" name="round_id" :value="questions.round_id" />
                            <div class="col-md-12">
                                <div v-for="(question, index) in questions.data">
                                    <p>@{{parseInt(index) + 1}}. <strong>@{{ question.question }}</strong></p>
                                    <div class="form-group row">
                                        <label class="col-sm-1">&nbsp;</label>
                                        <div class="col-sm-10">
                                            <div v-if="question.question_type==0">
                                                <input type="text" :name="'question_'+question.id" class="form-control" />
                                            </div>
                                            <div v-if="question.question_type==1" >
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" :value="1" :name="'question_'+question.id" />
                                                    <label class="form-check-label"> Yes </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" :value="0" :name="'question_'+question.id" />
                                                    <label class="form-check-label"> No </label>
                                                </div>
                                            </div>
                                            <div v-if="question.question_type==2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" :value="4" :name="'question_'+question.id" />
                                                    <label class="form-check-label"> Strongly Agree </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" :value="3" :name="'question_'+question.id" />
                                                    <label class="form-check-label"> Agree </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" :value="2" :name="'question_'+question.id" />
                                                    <label class="form-check-label"> Neither agree nor disagree </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" :value="1" :name="'question_'+question.id" />
                                                    <label class="form-check-label"> Disagree </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" :value="0" :name="'question_'+question.id" />
                                                    <label class="form-check-label"> Strongly disagree </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row col-sm-offset-5 col-sm-7">
                                    <button class="btn btn-sm btn-success"><i class='fa fa-plus-circle'></i> Submit</button>
                                    <button type="button" class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"><i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}</span></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<style type="text/css">
    .sweet-alert input {
       display: initial;
       width: auto;
       height: auto;
       margin: auto;
}
</style>
@endsection
