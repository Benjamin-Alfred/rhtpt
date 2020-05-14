@extends('app')
@section('content')
<div class="row">
    <div class="col-sm-12">
        <ol class="breadcrumb">
            <li><a href="{!! url('home') !!}"><i class="fa fa-home"></i> {!! trans('messages.home') !!}</a></li>
            <li class="active"><i class="fa fa-cubes"></i> {!! trans('messages.pt') !!}</li>
            <li class="active"><i class="fa fa-cube"></i> {!! trans_choice('messages.panel', 2) !!}</li>
        </ol>
    </div>
</div>
<div class="" id="manage-panel">
    <!-- panel Listing -->
    <div class="row">
        <div class="col-lg-12 margin-tb">
            <div class="pull-left col-md-6">
                <h5><i class="fa fa-book"></i> {!! trans_choice('messages.panel', 2) !!}
        
                @permission('create-panel')
                    <button type="button" class="btn btn-sm btn-belize-hole" data-toggle="modal" data-target="#create-panel">
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
            <th>Lot</th>
            <th>PT Sample ID</th>
            <th>Expected Result</th>
            <th>Characterized By</th>
            <th>Verified By</th>
            <th>Action</th>
        </tr>
        <tr v-for="panel in panels">
            <td>@{{ panel.lt }}</td>
            <td>@{{ panel.panel_label }}</td>
            <td>@{{ panel.rslt }}</td>
            <td>@{{ panel.prepared_by }}</td>
            <td>@{{ panel.tested_by }}</td>
            <td>
            @permission('update-panel')	
                <button v-bind="{ 'disabled': panel.deleted_at}" class="btn btn-sm btn-primary" @click.prevent="editPanel(panel)"><i class="fa fa-edit"></i> Edit</button>
            @endpermission
                <button v-if="panel.deleted_at" class="btn btn-sm btn-success" @click.prevent="restorePanel(panel)"><i class="fa fa-toggle-on"></i> Enable</button>
            @permission('delete-panel')
                <button v-if="!panel.deleted_at" class="btn btn-sm btn-danger" @click.prevent="deletePanel(panel)"><i class="fa fa-power-off"></i> Disable</button>
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

    <!-- Create panel Modal -->
    <div class="modal fade" id="create-panel" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel">Create Panel</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="createPanel('create_panel')" data-vv-validate="create_panel" data-vv-scope="create_panel">

                            <div class="col-md-12">
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('create_panel.lot') }" for="lot">Lot:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <select v-validate="'required'" class="form-control c-select" name="lot" :class="{'input': true, 'is-danger': errors.has('create_panel.lot') }" v-model="newPanel.lot_id">
                                            <option selected></option>
                                            <option  v-for="lot in lots" :value="lot.id">@{{ lot.value }}</option>
                                        </select>
                                        <span v-show="errors.has('create_panel.lot')" class="help is-danger">@{{ errors.first('create_panel.lot') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('create_panel.panel') }" for="panel">Panel ID:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <select v-validate="'required'" class="form-control c-select" name="panel" :class="{'input': true, 'is-danger': errors.has('create_panel.panel') }" v-model="newPanel.panel">
                                            <option selected></option>
                                            <option  v-for="panel in [1,2,3,4,5,6]" :value="panel">@{{ panel }}</option>
                                        </select>
                                        <span v-show="errors.has('create_panel.panel')" class="help is-danger">@{{ errors.first('create_panel.panel') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label" :class="{'help is-danger': errors.has('create_panel.panel_label') }" for="characterized">Panel Label:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <input v-validate="'required|alpha'" class="form-control" :class="{'input': true, 'is-danger': errors.has('create_panel.panel_label') }" name="panel_label" type="text" placeholder="" v-model="newPanel.panel_label" />
                                        <span v-show="errors.has('create_panel.panel_label')" class="help is-danger">@{{ errors.first('create_panel.panel_label') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('create_panel.material') }" for="material">Material:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <select v-validate="'required'" class="form-control c-select" name="material" :class="{'input': true, 'is-danger': errors.has('create_panel.material') }" v-model="newPanel.material_id">
                                            <option selected></option>
                                            <option  v-for="material in materials" :value="material.id">@{{ material.value }}</option>
                                        </select>
                                        <span v-show="errors.has('create_panel.material')" class="help is-danger">@{{ errors.first('create_panel.material') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('create_panel.characterized_by') }" for="characterized">Characterized By:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <input v-validate="'required|alpha'" class="form-control" :class="{'input': true, 'is-danger': errors.has('create_panel.characterized_by') }" name="characterized_by" type="text" placeholder="" v-model="newPanel.prepared_by" />
                                        <span v-show="errors.has('create_panel.characterized_by')" class="help is-danger">@{{ errors.first('create_panel.characterized_by') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('create_panel.expected_result') }" for="expected">Expected Result:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <div class="form-radio radio-inline" v-for="option in options">
                                            <label class="form-radio-label">
                                                <input type="radio" v-validate="'required'" :value="option.id" v-model="newPanel.result" name="expected_result" :class="{'input': true, 'is-danger': errors.has('create_panel.expected_result') }" name="expected_result">
                                                @{{ option.value }}
                                            </label>
                                        </div>
                                        <span v-show="errors.has('create_panel.expected_result')" class="help is-danger">@{{ errors.first('create_panel.expected_result') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('create_panel.verified_by') }" for="verified">Verified By:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <input v-validate="'required|alpha'" class="form-control" :class="{'input': true, 'is-danger': errors.has('create_panel.verified_by') }" name="verified_by" type="text" placeholder="" v-model="newPanel.tested_by" />
                                        <span v-show="errors.has('create_panel.verified_by')" class="help is-danger">@{{ errors.first('create_panel.verified_by') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row col-sm-offset-4 col-sm-8">
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

    <!-- Edit Panel Modal -->
    <div class="modal fade" id="edit-panel" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <h4 class="modal-title" id="myModalLabel">Edit Panel</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <form method="POST" enctype="multipart/form-data" v-on:submit.prevent="updatePanel(fillPanel.id, 'update_panel')" data-vv-validate="update_panel">
                            <div class="col-md-12">
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('lot') }" for="lot">Lot:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <select v-validate="'required'" class="form-control c-select" name="lot" :class="{'input': true, 'is-danger': errors.has('lot') }" v-model="fillPanel.lot_id">
                                            <option selected></option>
                                            <option  v-for="lot in lots" :value="lot.id">@{{ lot.value }}</option>
                                        </select>
                                        <span v-show="errors.has('lot')" class="help is-danger">@{{ errors.first('lot') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('panel') }" for="panel">Panel ID:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <select v-validate="'required'" class="form-control c-select" name="panel" :class="{'input': true, 'is-danger': errors.has('panel') }" v-model="fillPanel.panel">
                                            <option selected></option>
                                            <option  v-for="panel in [1,2,3,4,5,6]" :value="panel">@{{ panel }}</option>
                                        </select>
                                        <span v-show="errors.has('panel')" class="help is-danger">@{{ errors.first('panel') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('panel_label') }" for="panel_label">Panel Label:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <input v-validate="'required'" class="form-control" :class="{'input': true, 'is-danger': errors.has('panel_label') }" name="panel_label" type="text" placeholder="" v-model="fillPanel.panel_label" />
                                        <span v-show="errors.has('panel_label')" class="help is-danger">@{{ errors.first('panel_label') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('material') }" for="material">Material:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <select v-validate="'required'" class="form-control c-select" name="material" :class="{'input': true, 'is-danger': errors.has('material') }" v-model="fillPanel.material_id">
                                            <option selected></option>
                                            <option  v-for="material in materials" :value="material.id">@{{ material.value }}</option>
                                        </select>
                                        <span v-show="errors.has('material')" class="help is-danger">@{{ errors.first('material') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('characterized_by') }" for="characterized_by">Characterized By:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <input v-validate="'required|alpha'" class="form-control" :class="{'input': true, 'is-danger': errors.has('characterized_by') }" name="characterized_by" type="text" placeholder="" v-model="fillPanel.prepared_by" />
                                        <span v-show="errors.has('characterized_by')" class="help is-danger">@{{ errors.first('characterized_by') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('expected_result') }" for="expected_result">Expected Result:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <div class="form-radio radio-inline" v-for="option in options">
                                            <label class="form-radio-label">
                                                <input type="radio" v-validate="'required'" :value="option.id" v-model="fillPanel.result" name="expected_result" :class="{'input': true, 'is-danger': errors.has('verified_by') }" name="expected_result">
                                                @{{ option.value }}
                                            </label>
                                        </div>
                                        <span v-show="errors.has('expected_result')" class="help is-danger">@{{ errors.first('expected_result') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-4 form-control-label"  :class="{'help is-danger': errors.has('verified_by') }" for="verified_by">Verified By:</label>
                                    <div class="col-sm-8" :class="{ 'control': true }">
                                        <input v-validate="'required|alpha'" class="form-control" :class="{'input': true, 'is-danger': errors.has('verified_by') }" name="verified_by" type="text" placeholder="" v-model="fillPanel.tested_by" />
                                        <span v-show="errors.has('verified_by')" class="help is-danger">@{{ errors.first('verified_by') }}</span>
                                    </div>
                                </div>
                                <div class="form-group row col-sm-offset-4 col-sm-8">
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
@endsection