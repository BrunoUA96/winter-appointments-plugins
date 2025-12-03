<?php namespace Doctor\Home\Controllers;

use Backend\Classes\Controller;
use Backend\Classes\NavigationManager;
use Doctor\Home\Models\HomeSettings;

class Settings extends Controller
{
    public $implement = [
        'Backend\Behaviors\FormController',
    ];
    
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        NavigationManager::instance()->setContext('Doctor.Home', 'main-menu-item');
    }
    
    public function index()
    {
        $this->pageTitle = 'Home Content Settings';
        $this->asExtension('FormController')->update();
    }
    
    public function index_onSave()
    {
        return $this->asExtension('FormController')->update_onSave(null);
    }
    
    public function formFindModelObject()
    {
        return HomeSettings::instance();
    }
}

