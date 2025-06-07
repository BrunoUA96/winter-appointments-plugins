<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'backend/doctor/appointments', 'middleware' => ['web', 'backend.auth']], function() {
    Route::get('google/auth', 'Doctor\Appointments\Controllers\GoogleAuth@index')->name('doctor.appointments.google.auth');
    Route::get('google/auth/callback', 'Doctor\Appointments\Controllers\GoogleAuth@callback')->name('doctor.appointments.google.auth.callback');
}); 