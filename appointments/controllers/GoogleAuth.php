<?php namespace Doctor\Appointments\Controllers;

use Backend\Classes\Controller;
use Doctor\Appointments\Services\GoogleCalendarService;
use Winter\Storm\Support\Facades\Flash;
use Backend\Facades\Backend;
use Illuminate\Support\Facades\Log;

class GoogleAuth extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        try {
            $calendarService = new GoogleCalendarService();
            
            if (!$calendarService->isAuthenticated()) {
                return redirect($calendarService->getAuthUrl());
            }
            
            return redirect(Backend::url('doctor/appointments/settings'));
        } catch (\Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
            return redirect(Backend::url('doctor/appointments/settings'));
        }
    }

    public function callback()
    {
        Log::info('Processing Google callback');
        Log::info('Request parameters: ' . json_encode($_GET));

        try {
            $code = input('code');
            if (!$code) {
                Flash::error('Authorization code not received');
                return redirect(Backend::url('doctor/appointments/settings'));
            }

            $calendarService = new GoogleCalendarService();
            $calendarService->handleAuthCallback($code);
            Flash::success('Google Calendar successfully connected');
        } catch (\Exception $e) {
            Log::error('Error connecting to Google Calendar: ' . $e->getMessage());
            Flash::error('Error connecting to Google Calendar: ' . $e->getMessage());
        }

        return redirect(Backend::url('doctor/appointments/settings'));
    }
} 