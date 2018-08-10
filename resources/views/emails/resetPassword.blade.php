@component('mail::message')

Hi {{$user->name}},

Your secret code for reset password : {{$user->forgot_code}}



Thanks.<br>

{{config('app.name')}}

@endcomponent