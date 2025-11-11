<?php

use Illuminate\Support\Facades\Route;

// Backend routes
Route::group(['prefix' => 'backend/doctor/appointments', 'middleware' => ['web', 'backend.auth']], function() {
    Route::get('google/auth', 'Doctor\Appointments\Controllers\GoogleAuth@index')->name('doctor.appointments.google.auth');
    Route::get('google/auth/callback', 'Doctor\Appointments\Controllers\GoogleAuth@callback')->name('doctor.appointments.google.auth.callback');
});

// Public routes for appointment viewing and cancellation
Route::group(['prefix' => 'appointment', 'middleware' => ['web']], function() {
    Route::get('{id}/{token}', 'Doctor\Appointments\Controllers\AppointmentView@show')->name('appointment.view');
    Route::post('{id}/{token}/cancel', 'Doctor\Appointments\Controllers\AppointmentView@cancel')->name('appointment.cancel');
}); 