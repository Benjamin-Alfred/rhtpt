@extends('app')
@section('content')
<div class="row">
    <div class="col-sm-12">
        <ol class="breadcrumb">
            <li><a href="{!! url('home') !!}"><i class="fa fa-home"></i> {!! trans('messages.home') !!}</a></li>
            <li class="active"><i class="fa fa-users"></i> {!! trans('messages.user-management') !!}</li>
            <li class="active"><i class="fa fa-cube"></i> Participants</li>
        </ol>
    </div>
</div>
<div class="" id="survey-responses-report">
    <!-- User Listing -->
    <div class="row">
        <div class="col-lg-12 margin-tb">
            <h5><i class="fa fa-book"></i> Customer Survey Responses</h5>
        </div>
    </div>
    <br/>
    <div class="row">
        <div class="col-lg-12 margin-tb">
    		<span><b>Total respondents</b>: @{{total}}</span>
        </div>
    </div>
    <br/>
    @if(session()->has('error'))
        <div class="alert alert-info">{!! session()->get('error') !!}</div>
    @endif
     <div class="row">
            <div class="col-lg-12 margin-tb">
                <div class="row">
                    <div class="pull-left col-sm-12">
                        <label>Filter by: </label>
                        <button data-toggle="collapse" class="btn btn-success btn-sm" data-target="#round">Round</button>
                        <button data-toggle="collapse" class="btn btn-success btn-sm" data-target="#region">Region</button>
                        <button data-toggle="collapse" class="btn btn-success btn-sm" data-target="#question_">Question</button>
                        <button class="btn btn-sm btn-alizarin" type="submit" @click="getSurveyResponses(1)" v-if="!loading">Filter </button>
                        <button class="btn btn-sm btn-alizarin" type="button" disabled="disabled" v-if="loading">Searching...</button>
                    </div>
                </div>
                <form @submit.prevent="getSurveyResponses(1)">
                <div id="round" class="collapse">
                    <div class="row">
                        <div class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="round">Round:</label>
                            <div class="col-sm-8">
                                <select class="form-control" name="round" id="round_id" v-model="round" @change="getSurveyQuestions()">
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
                <div id="question_" class="collapse">
                    <div class="row">
                        <div class="col-sm-4">
                            <label class="col-sm-4 form-control-label" for="question">Question:</label>
                            <div class="col-sm-8">
                                <select class="form-control" name="question" id="question_id" v-model="question">
                                    <option selected></option>
                                    <option v-for="question in surveyQuestions" :value="question.id">@{{ question.question }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                </form>                
            </div>
    </div>

    <div class="my-loading-container" v-if="loading">
        <div class="loading">
            <i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
                <span class="sr-only">Loading...</span>
        </div>
    </div>

    <table class="table table-bordered">
        <tr>
            <th>#</th>
            <th>County</th>
            <th>Sub-county</th>
            <th>Tester ID</th>
            <th>Question</th>
            <th>Answer</th>
        </tr>
        <tr v-for="(surveyResponse, key) in surveyResponses">
            <td>@{{ key + 1 + ((pagination.current_page - 1) * pagination.per_page) }}</td>
            <td>@{{ surveyResponse.county }}</td>
            <td>@{{ surveyResponse.subcounty }}</td>
            <td>@{{ surveyResponse.uid}}</td>
            <td>@{{ surveyResponse.question}}</td>
            <td v-if="surveyResponse.question_type==0">@{{ surveyResponse.response}}</td>
            <td v-if="surveyResponse.question_type==1">@{{ yesNo[surveyResponse.response] }}</td>
            <td v-if="surveyResponse.question_type==2">@{{ agreement[surveyResponse.response] }}</td>
        </tr>
    </table>
    <!-- Pagination -->
    <nav>
        <ul class="pagination">
            <li v-if="pagination.current_page > 1"  class="page-item">
                <a class="page-link" href="#" aria-label="Previous"
                    @click.prevent="changePage(pagination.current_page - 1)">
                    <span aria-hidden="true">«</span>
                </a>
            </li>
            <li v-for="page in pagesNumber"  class="page-item"
                v-bind:class="[ page == isActived ? 'active' : '']">
                <a class="page-link" href="#"
                    @click.prevent="changePage(page)">@{{ page }}</a>
            </li>
            <li v-if="pagination.current_page < pagination.last_page"  class="page-item">
                <a class="page-link" href="#" aria-label="Next"
                    @click.prevent="changePage(pagination.current_page + 1)">
                    <span aria-hidden="true">»</span>
                </a>
            </li>
        </ul>
    </nav>
</div>
@endsection
