<!DOCTYPE html>
<html>
<head>
    <title>Invitation email</title>
</head>
<div>
    <h1>You've been invited to join the {{ $invitation->organization->title }} team.</h1>
    <p>Join forces with your team inside RateStream.io.</p>

    <a href="{{ $invitation->url() }}">Join now</a>

    <p>
        You were invited by: <br>
        {{ $invitation->user->name }} <br>
        {{ $invitation->user->email }}
    </p>
</div>
</html>
