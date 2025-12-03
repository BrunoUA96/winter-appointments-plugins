<?php namespace Doctor\Appointments\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use Doctor\Appointments\Models\WorkingHours;

class Schedule extends Controller
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
    ];
    
    public $listConfig = '$/doctor/appointments/controllers/schedule/config_list.yaml';
    public $formConfig = '$/doctor/appointments/controllers/schedule/config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Doctor.Appointments', 'main-menu-item', 'side-menu-working-hours');
    }
}

