<?php namespace Doctor\Appointments\Controllers;

use Backend\Classes\Controller;
use Backend\Classes\NavigationManager;

class ConsultationTypes extends Controller
{
    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController',          ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['doctor.appointments.consultation_types'];

    public function __construct()
    {
        parent::__construct();
        NavigationManager::instance()->setContext('Doctor.Appointments', 'main-menu-item');
    }
}
