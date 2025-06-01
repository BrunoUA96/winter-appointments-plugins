<?php namespace Doctor\Appointments\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use Doctor\Appointments\Models\User;
use Doctor\Appointments\Models\Appointment;

class Users extends Controller
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
        'Backend\Behaviors\RelationController'
    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';
    
    public $showCheckboxes = false;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Doctor.Appointments', 'main-menu-item', 'side-menu-users');
    }

    public function appointments($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $appointments = Appointment::where(function($query) use ($user) {
            $query->where('email', $user->email)
                  ->orWhere('phone', $user->phone);
        })->get();

        return $appointments;
    }
}
