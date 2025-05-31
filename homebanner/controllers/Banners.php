<?php namespace Doctor\HomeBanner\Controllers;

use Backend\Classes\Controller;
use Backend\Classes\NavigationManager;

class Banners extends Controller
{
    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController',           ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        NavigationManager::instance()->setContext('Doctor.HomeBanner', 'main-menu-item');
    }
}
