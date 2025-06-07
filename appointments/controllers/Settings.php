<?php namespace Doctor\Appointments\Controllers;

use Backend\Classes\Controller;
use Doctor\Appointments\Services\GoogleCalendarService;
use Winter\Storm\Support\Facades\Flash;

class Settings extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class
    ];

    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
    }

    public function onGoogleAuth()
    {
        try {
            $calendarService = new GoogleCalendarService();
            $authUrl = $calendarService->getAuthUrl();
            
            return redirect($authUrl);
        } catch (\Exception $e) {
            Flash::error('Error: ' . $e->getMessage());
            return redirect()->refresh();
        }
    }
} 