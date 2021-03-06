@extends('layouts.app')
@section('content')
    <div class="resetpassword" style="padding-top:20px;">
        <div class="card col-md-5" style="margin:auto; float:none">
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif
            <form class="form-signin"  role="form" method="post" action="{{ url('/password/email') }}">
                {{ csrf_field() }}
                <div style="text-align:center;">
                    <img src="{{ '../'.Config::get('cms.logo') }}" style="max-height:120px;margin:0 auto !important;">
                    <h3 class="form-signin-heading">
                        <span>Kenya Serology Rapid HIV PT</span>
                    </h3>
                </div>
                <div class="bs-callout bs-callout-info text-left">
                    <h4 class="md-18">Password Recovery</h4>
                    <p> If you have forgotten your password, fill in your PT Enrollment ID or username below and click send button. You will receive a verification code on your mobile phone.</p>
                </div>
                <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                    <label for="username">PT Enrollment ID/Username</label>
                    <input id="username" type="text" class="form-control" name="username" placeholder="eg. 12345" value="{{ old('username') }}" required>
                    @if ($errors->has('username'))
                        <span class="help-block">
                        <strong>{{ $errors->first('username') }}</strong>
                    </span>
                    @endif
                </div>
                <button style="background-color: #3498db;border-color: #3498db" type="submit" class="btn btn-md btn-info btn-block">Send</button>
                <br><a href="/login" style="color:#18bc9c">No, I remember my password.</a>
            </form>
        </div>
    </div>
@endsection
