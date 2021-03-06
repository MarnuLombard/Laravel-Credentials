@extends(Config::get('graham-campbell/core::email'))

@section('content')
<p>The email for your account on <a href="{!! $url !!}">{!! Config::get('graham-campbell/core::name') !!}</a> has just been changed from "{{ $old }}" to "{{ $new }}".</p>
<p>If this was not you, please contact us immediately.</p>
@stop
