@extends('emails.template')

@section('content')
    <h2 style="font-size: 22px;line-height: 28px;margin: 0 0 12px 0;">
        @lang("Please confirm your account
        ")
    </h2>
    Welcome to {{config('app.name')}}!
    <p>Your account has been registered but we need you to take one final step to insure
        someone else is not
        trying to sign up using your email.</p>
    <a href="{{ url('register/verify/' . $confirmation_code) }}" class="button">Verify Account</a>
    <br>
    <p>Or copy paste this link to your browser {{ url('register/verify/' . $confirmation_code) }}</p>


    If you did not sign up, please disregard this email or contact us at
    {{config('app.name')}}

@endsection
