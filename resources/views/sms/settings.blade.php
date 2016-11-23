@extends("app")

@section("content")
<div class="row">
    <div class="col-sm-12">
        <ol class="breadcrumb">
            <li><a href="{!! url('home') !!}"><i class="fa fa-home"></i> {!! trans('messages.home') !!}</a></li>
            <li class="active"><i class="fa fa-cubes"></i> {!! trans('messages.bulk-sms') !!}</li>
            <li><a href="{!! route('bulk.key') !!}"><i class="fa fa-cube"></i> {!! trans('messages.api-key') !!}</a></li>
        </ol>
    </div>
</div>
<!-- if there are creation errors, they will show here -->
@if (Session::has('message'))
  <div class="alert alert-info">{!! Session::get('message') !!}</div>
@endif
@if($errors->all())
  <div class="alert alert-danger alert-dismissible" role="alert">
      <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">{!! trans('messages.close') !!}</span></button>
      {!! HTML::ul($errors->all(), array('class'=>'list-unstyled')) !!}
  </div>
@endif
<div class="card">
	<div class="card-header">
	    <i class="fa fa-edit"></i> {!! trans('messages.edit') !!}
	    <span>
			<a class="btn btn-sm btn-carrot" href="#" onclick="window.history.back();return false;" alt="{!! trans('messages.back') !!}" title="{!! trans('messages.back') !!}">
				<i class="fa fa-step-backward"></i>
				{!! trans('messages.back') !!}
			</a>
		</span>
	</div>
  	<div class="card-block">
		<div class="row">
			{!! Form::model($api, array('route' => array('bulk.api'), 'method' => 'POST', 'id' => 'form-bulk-settings', 'class' => 'form-horizontal')) !!}
			<!-- CSRF Token -->
            <input type="hidden" name="_token" value="{{{ csrf_token() }}}" />
            <!-- ./ csrf token -->
			<div class="col-md-8">
				<div class="form-group row">
					{!! Form::label('username', trans('messages.username'), array('class' => 'col-sm-4 form-control-label')) !!}
					<div class="col-sm-6">
						{!! Form::text('username', old('username'), array('class' => 'form-control')) !!}
					</div>
				</div>
        <div class="form-group row">
            {!! Form::label('api-key', trans('messages.api-key'), array('class' => 'col-sm-4 form-control-label')) !!}
            <div class="col-sm-6">
                {!! Form::textarea('api-key', $api->api_key, array('class' => 'form-control', 'rows' => '3')) !!}
            </div>
        </div>
				<div class="form-group row col-sm-offset-4 col-sm-8">
					{!! Form::button("<i class='fa fa-check-circle'></i> ".trans('messages.update'),
					array('class' => 'btn btn-primary btn-sm', 'onclick' => 'submit()')) !!}
					<a href="#" class="btn btn-sm btn-silver"><i class="fa fa-times-circle"></i> {!! trans('messages.cancel') !!}</a>
				</div>
			</div>
			{!! Form::close() !!}
		</div>
  	</div>
</div>
{!! session(['SOURCE_URL' => URL::full()]) !!}
@endsection
