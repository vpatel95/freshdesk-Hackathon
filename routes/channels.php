<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('hospitalEmergencyAccident.{hId}', function($user, $hId) {
	if($user->role == 'hospital' && $user->id == $hId)
		return true;
	else
		return false;
});

Broadcast::channel('hospitalEmergencyPersonal.{hId}', function($user, $hId) {
	if($user->role == 'hospital' && $user->id == $hId)
		return true;
	else
		return false;
});

Broadcast::channel('hospitalNearBy.{hId}', function($user, $hId) {
	if($user->role == 'hospital' && $user->id == $hId)
		return true;
	else
		return false;
});

Broadcast::channel('policeEmergencyAccident.{pId}', function($user, $pId) {
	if($user->role == 'police_station' && $user->id == $pId)
		return true;
	else
		return false;
});

Broadcast::channel('policeFir.{pId}', function($user, $pId) {
	if($user->role == 'police_station' && $user->id == $pId)
		return true;
	else
		return false;
});

Broadcast::channel('fireEmergency.{fId}', function($user, $fId) {
	if($user->role == 'fire_station' && $user->id == $fId)
		return true;
	else
		return false;
});
