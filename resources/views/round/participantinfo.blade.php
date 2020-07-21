@extends('app')
@section('content')
<div class="row">
    <div class="col-sm-12">
        <ol class="breadcrumb">
            <li><a href="{!! url('home') !!}"><i class="fa fa-home"></i> {!! trans('messages.home') !!}</a></li>
            <li class="active"><i class="fa fa-cubes"></i> {!! trans('messages.pt') !!}</li>
            <li class="active"><i class="fa fa-cube"></i> {!! trans_choice('messages.participant', 2) !!}</li>
        </ol>
    </div>
</div>
<div id="manage-participant-info">
    <!-- Round Listing -->
    <div class="row">
    @if (Session::has('message'))
            <div class="alert alert-info">{{ Session::get('message') }}</div>
        @endif
        <div class="col-lg-12 margin-tb">
            <div class="pull-left col-md-8">
                <h5><i class="fa fa-book"></i> {!! trans_choice('messages.pt-round', 2) !!}</h5>
            </div>
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" placeholder="Search for..." v-model="query" v-on:keyup.enter="search()">
                    <span class="input-group-btn">
                        <button class="btn btn-secondary" type="button" @click="search()" v-if="!loading"><i class="fa fa-search"></i></button>
                        <button class="btn btn-secondary" type="button" disabled="disabled" v-if="loading">Searching...</button>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <!-- <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="filter_by_region()"> -->
            <div class="col-lg-12 margin-tb">
                <div class="row">
                    <div v-if = "role == 1 || role == 3" class="col-sm-3">
                        <label class="col-sm-4 form-control-label" for="title">Counties:</label>
                        <div class="col-sm-6">
                            <select class="form-control" name="county" id="county_id" @change="loadSubcounties" v-model="county">
                                <option selected></option>
                               <option v-for="county in counties" :value="county.id">@{{ county.value }}</option>                         
                            </select>
                        </div>
                    </div>
                    <div v-if = "role == 1 || role == 3 || role == 4" class="col-sm-3">
                        <label class="col-sm-4 form-control-label" for="title">Sub Counties:</label>
                        <div class="col-sm-8">
                            <select class="form-control" name="sub_county" id="sub_id" @change="loadFacilities" v-model="sub_county">
                                <option selected></option>
                               <option  v-for="sub in subcounties" :value="sub.id">@{{ sub.value }}</option>                         
                            </select>
                        </div>
                    </div>
                    <div v-if = "role == 1 || role == 3 || role == 4 || role ==7" class="col-sm-3">
                        <label class="col-sm-4 form-control-label" for="title">Facilities:</label>
                        <div class="col-sm-8">
                            <select class="form-control" name="facility" v-model="facility">
                                <option selected></option>
                                <option v-for="facility in facilities" :value="facility.id">@{{ facility.value }}</option> 
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <button class="btn btn-sm btn-alizarin" type="submit" @click="filter_by_region()" v-if="!loading">Filter </button>
                        <button class="btn btn-sm btn-alizarin" type="button" disabled="disabled" v-if="loading">Searching...</button>
		    </div>
                </div>
		<div class="row">
                    <div class="col-sm-12">
                    @permission('enrol-participants')                               
                        <a class="btn btn-sm btn-wet-asphalt" :href="'/download/' + roundId" id="enrolled" ><i class="fa fa-level-down"></i> Participants List</a>
                    @endpermission
                    @permission('generate-pt-receipt-record')
                    <a class="btn btn-sm btn-wet-asphalt" href="#" @click="downloadForms('/download-receipt-record/' + roundId)">
                        <i class="fa fa-level-down"></i>Receipt Record</a>
                    @endpermission
                    @permission('generate-participant-result-form')
                    <a class="btn btn-sm btn-wet-asphalt" href="#" @click="downloadForms('/download-forms/' + roundId)">
                        <i class="fa fa-level-down"></i> Participant Forms</a>
		    @endpermission
                    <a class="btn btn-sm btn-carrot" href="#" onclick="window.history.back();return false;" alt="{!! trans('messages.back') !!}" title="{!! trans('messages.back') !!}">
                        <i class="fa fa-step-backward"></i>
                        {!! trans('messages.back') !!}
                    </a>  
		    </div>
                </div>
            </div>
        <!-- </form> -->
    </div>  

    <div class="row">
        <div class="col-lg-12 margin-tb">
            <div class="row">
                <table class="table table-responsive">
                    <tr>
                        <th>Enrolled Participants</th>
                        <td>@{{pagination.total}}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <input type="hidden" class="form-control" name="round_id" id="round-id" v-bind:value="roundId"/>
            <table class="table table-bordered table-responsive">
                <tr>
                    <th>#</th>
                    <th>Participant</th>
                    <th>PT Enrollment ID</th>
                    <th>Phone</th>
                    <th>Facility</th>
                    <th>Sub County</th>
                    <th>County</th>
                    <th>Status</th>
                </tr>
                <tr v-for="(participant, key) in participants">                                        
                    <td>@{{ key + 1 + ((pagination.current_page - 1) * pagination.per_page) }}</td>
                    <td>@{{ participant.first_name }} @{{ participant.middle_name }} @{{ participant.last_name }}</td>
                    <td>@{{ participant.uid }}</td>
                    <td>@{{ participant.phone }}</td>
                    <td>@{{ participant.facility_name }}</td>
                    <td>@{{ participant.sub_county_name}}</td>
                    <td>@{{ participant.county_name }}</td>                                                
                    <td>
                        <button v-if="!participant.result_status" class="mbtn mbtn-raised mbtn-primary mbtn-xs">No Result</button>
                        <button v-if="participant.result_status==0" class="mbtn mbtn-raised mbtn-danger mbtn-xs">Not Checked</button>
                        <button v-if="participant.result_status==1" class="mbtn mbtn-raised mbtn-warning mbtn-xs">Submitted</button>
                        <button v-if="participant.result_status==2" class="mbtn mbtn-raised mbtn-info mbtn-xs">Evaluated</button>
            			<button v-if="participant.result_status==3" class="mbtn mbtn-raised mbtn-inverse mbtn-xs">Verified</button>
                        @permission('generate-participant-result-form')
            			<a class="btn btn-sm btn-success" href="#"
            			   @click="downloadForms('/download-form/' + roundId + '/participant/' + participant.id)" ><i class="fa fa-level-down"></i> Form</a>
                        @endpermission
                    </td>
                </tr>
            </table>
        </div>
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

    </div>
</div>
@endsection

