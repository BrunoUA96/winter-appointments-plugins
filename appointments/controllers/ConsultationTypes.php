<?php namespace Doctor\Appointments\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;

class ConsultationTypes extends Controller
{
    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController',          ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Doctor.Appointments', 'main-menu-item', 'side-menu-consultation-types');
    }
}
