@extends('app')
@section('content')
<div class="row">
    <div class="col-sm-12">
        <ol class="breadcrumb">
            <li><a href="{!! url('home') !!}"><i class="fa fa-home"></i> {!! trans('messages.home') !!}</a></li>
            <li class="active"><i class="fa fa-group"></i> {!! trans('messages.user-management') !!}</li>
            <li class="active"><i class="fa fa-cube"></i> {!! trans_choice('messages.survey', 2) !!}</li>
        </ol>
    </div>
</div>
<div class="" id="manage-survey">
    <!-- Survey Listing -->
    <div class="row">
        <div class="col-lg-12 margin-tb">
            <div class="pull-left col-md-6">
                <h5><i class="fa fa-book"></i> Survey Questions
        
                @permission('create-survey-question')
                    <button type="button" class="btn btn-sm btn-belize-hole" data-toggle="modal" data-target="#create-survey">
                        <i class="fa fa-plus-circle"></i>
                        {!! trans('messages.add') !!}
                    </button>
                @endpermission
                    <a class="btn btn-sm btn-carrot" href="#" onclick="window.history.back();return false;" alt="{!! trans('messages.back') !!}" title="{!! trans('messages.back') !!}">
                        <i class="fa fa-step-backward"></i>
                        {!! trans('messages.back') !!}
                    </a>
                </h5>
            </div>
            <div class="col-md-2"></div>
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
    <table class="table table-bordered">
        <tr>
            <th>Round</th>
            <th>Question</th>
            <th>Question Type</th>
            <th>Action</th>
        </tr>
        <tr v-for="survey in surveys" v-bind:class="{ 'text-muted': survey.deleted_at}">
            <td>@{{ survey.round.name }}</td>
            <td>@{{ survey.question }}</td>
            <td>@{{ getQuestionTypeTag(survey.question_type) }}</td>
            <td>	
            @permission('update-survey-question')
                <button v-bind="{ 'disabled': survey.deleted_at}" class="btn btn-sm btn-primary" @click.prevent="editSurvey(survey)"><i class="fa fa-edit"></i> Edit</button>
            @endpermission
            @permission('create-survey-question')
                <button v-if="survey.deleted_at" class="btn btn-sm btn-success" @click.prevent="restoreSurvey(survey)"><i class="fa fa-toggle-on"></i> Enable</button>
            @endpermission
            @permission('delete-survey-question')
                <button v-if="!survey.deleted_at" class="btn btn-sm btn-alizarin" @click.prevent="deleteSurvey(survey)"><i class="fa fa-power-off"></i> Disable</button>
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
    <!-- Create Survey Modal -->
    <div id="create-survey" class="modal fade" tabindex="-1" survey="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" survey="document">
            <div class="modal-content">
                <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="myModalLabel">Add Survey Question</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="createSurvey('create_survey')" data-vv-validate="create_survey" data-vv-scope="create_survey">
                            <div class="col-md-12">
                                <div class="form-group row">
                                    <label class="col-sm-12">This question will be added to the currently active round!</label>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label" for="title">Question:</label>
                                    <div class="col-sm-8">
                                        <textarea name="question" class="form-control" v-model="newSurvey.question"></textarea>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label" for="question-type">Question Type:</label>
                                    <div class="col-sm-8">
                                        <select class="form-control c-select" name="question_type" id="question-type" >
                                            <option selected></option>
                                            <option v-for="questionType in questionTypes" :value="questionType.id">@{{ questionType.tag }}</option>   
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group row col-sm-offset-4 col-sm-8">
                                    <button class="btn btn-sm btn-success"><i class='fa fa-plus-circle'></i> Save</button>
                                    <button type="button" class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"><i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}</span></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Survey Modal -->
    <div class="modal fade" id="edit-survey" tabindex="-1" survey="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" survey="document">
        <div class="modal-content">
            <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
            <h4 class="modal-title" id="myModalLabel">Edit Survey Question</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="updateSurvey(fillSurvey.id)">
                        <div class="col-md-12">
                            <div class="form-group row">
                                <label class="col-sm-4 form-control-label" for="title">Question:</label>
                                <div class="col-sm-8">
                                    <textarea name="question" class="form-control" v-model="fillSurvey.question"></textarea>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-4 form-control-label" for="question-type-2">Question Type:</label>
                                <div class="col-sm-8">
                                    <select class="form-control c-select" name="question_type" id="question-type-2" v-model="fillSurvey.question_type">
                                        <option v-for="questionType in questionTypes" :value="questionType.id">@{{ questionType.tag }}</option>   
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row col-sm-offset-4 col-sm-8">
                                <button class="btn btn-sm btn-success"><i class='fa fa-plus-circle'></i> Save</button>
                                <button type="button" class="btn btn-sm btn-silver" data-dismiss="modal" aria-label="Close"><span aria-hidden="true"><i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}</span></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection