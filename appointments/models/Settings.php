<?php namespace Doctor\Appointments\Models;

use Winter\Storm\Database\Model;
use Winter\Storm\Database\Traits\Validation;

class Settings extends Model
{


    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'doctor_appointments_settings';

    public $settingsFields = 'fields.yaml';

    public $rules = [
        'recaptcha_site_key' => 'required',
        'recaptcha_secret_key' => 'required',
        'google_client_id' => 'required',
        'google_client_secret' => 'required',
        'google_redirect_uri' => 'required|url'
    ];

    public function getRecaptchaSiteKeyAttribute()
    {
        return $this->get('recaptcha_site_key');
    }

    public function getRecaptchaSecretKeyAttribute()
    {
        return $this->get('recaptcha_secret_key');
    }

    public function getGoogleClientIdAttribute()
    {
        return $this->get('google_client_id');
    }

    public function getGoogleClientSecretAttribute()
    {
        return $this->get('google_client_secret');
    }

    public function getGoogleRedirectUriAttribute()
    {
        return $this->get('google_redirect_uri');
    }
} 