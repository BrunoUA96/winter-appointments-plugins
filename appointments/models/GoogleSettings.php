<?php namespace Doctor\Appointments\Models;

use Winter\Storm\Database\Model;

/**
 * Model
 */
class GoogleSettings extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'doctor_appointments_google_settings';
    public $settingsFields = '~/plugins/doctor/appointments/models/settings/fields.yaml';

    public $rules = [
        'google_calendar_id' => 'required'
    ];
}
