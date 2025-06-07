<?php namespace Doctor\Appointments\Models;

use Winter\Storm\Database\Model;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'doctor_appointments_settings';

    public $settingsFields = 'fields.yaml';

    protected $jsonable = ['google_calendar_id', 'recaptcha_site_key', 'recaptcha_secret_key'];
} 