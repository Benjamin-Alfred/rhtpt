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
<div id="manage-participant-round">
    <!-- Round Listing -->
    <div class="row">
    @if (Session::has('message'))
            <div class="alert alert-info">{{ Session::get('message') }}</div>
        @endif
        <div class="col-lg-12 margin-tb">
            <div class="pull-left col-md-8">
                <h5><i class="fa fa-book"></i> {!! trans_choice('messages.pt-round', 2) !!}
        
                    <a class="btn btn-sm btn-carrot" href="#" onclick="window.history.back();return false;" alt="{!! trans('messages.back') !!}" title="{!! trans('messages.back') !!}">
                        <i class="fa fa-step-backward"></i>
                        {!! trans('messages.back') !!}
                    </a>  
                        <button class="btn btn-sm btn-primary" type="submit" @click="filter_enrolled_participants()" v-if="!loading">Enrolled Participants </button>
                        <button class="btn btn-sm btn-alizarin" type="button" disabled="disabled" v-if="loading">Searching...</button>
                </h5>
            </div>
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" placeholder="Search for..." v-model="query">
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
                    <div v-if = "role == 1" class="col-sm-3">
                        <label class="col-sm-4 form-control-label" for="title">Counties:</label>
                        <div class="col-sm-6">
                            <select class="form-control" name="county" id="county_id" @change="fetchSubs()" v-model="county">
                                <option selected></option>
                               <option v-for="county in counties" :value="county.id">@{{ county.value }}</option>                         
                            </select>
                        </div>
                    </div>
                    <div v-if = "role == 1 || role == 4" class="col-sm-3">
                        <label class="col-sm-4 form-control-label" for="title">Sub Counties:</label>
                        <div class="col-sm-8">
                            <select class="form-control" name="sub_county" id="sub_id" @change="fetchFacilities" v-model="sub_county">
                                <option selected></option>
                               <option  v-for="sub in subs" :value="sub.id">@{{ sub.value }}</option>                         
                            </select>
                        </div>
                    </div>
                    <div v-if = "role == 1 || role == 4 || role ==7" class="col-sm-3">
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
            </div>
        <!-- </form> -->
    </div>
    <div class="row">
        <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="enrolParticipants" id="partFrms">
            <div class="col-md-12">
                <input type="hidden" class="form-control" name="round_id" id="round-id" v-bind:value="roundId"/>
                <table class="table table-bordered table-responsive">
                    <tr>
                        <th>#</th>
                        <th><input type="checkbox" name="select_all" title="Select All" v-model="selectAll" @click="toggleCheck" /> Remove</th>
                        <th>Participant</th>
                        <th>UID</th>
                        <th>Facility</th>
                        <th>Phone</th>
                        <th>Program</th>
                    </tr>
                    <tr v-for="(participant, key, index) in testerparticipants" :key="participant.id" >
                        <td>@{{listNumber(key, index)}}</td>
                        <td>
                            <input type="checkbox" :value="participant.id" name="selectedParticipants[]" v-model="selectedParticipants"/>
                        </td>
                        <td>@{{ participant.first_name }} @{{ participant.middle_name }} @{{ participant.last_name }}</td>
                        <td>@{{ participant.uid }}</td>
                        <td>@{{ participant.fac }}</td>
                        <td>@{{ participant.phone }}</td>
                        <td>@{{ participant.prog }}</td>
                    </tr>
                </table>
                <div class="form-group row col-sm-offset-4 col-sm-8">
                    <input type="hidden" name="view" v-model="view" />
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class='fa fa-plus-circle'></i> 
                        <span v-if = "view=='enrol'">Enrol</span>
                        <span v-if = "view=='unenrol'">Unenrol</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true"><i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

