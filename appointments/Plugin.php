<?php namespace Doctor\Appointments;

use System\Classes\PluginBase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Winter\Storm\Support\Facades\Flash;

class Plugin extends PluginBase
{
    public function registerComponents()
    {
        return [
            'Doctor\Appointments\Components\BookingForm' => 'BookingForm',

        ];
    }

    public function boot()
    {
        Route::get('/google-callback', function () {
            try {
                Log::info('Processing Google callback');
                Log::info('Request parameters:', request()->all());
                
                $code = request('code');
                if (!$code) {
                    Log::error('No code provided in Google callback');
                    throw new \Exception('No code provided in Google callback');
                }

                $calendarService = new \Doctor\Appointments\Services\GoogleCalendarService();
                $calendarService->handleAuthCode($code);

                // Показываем сообщение об успешной авторизации
                Flash::success('Авторизация Google успешно выполнена. Запись добавлена в календарь доктора.');

                return redirect('/booking');
            } catch (\Exception $e) {
                Log::error('Google Callback Error: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
                Flash::error('Ошибка при авторизации Google: ' . $e->getMessage());
                return redirect('/booking');
            }
        });

        Route::get('/clear-session', function () {
            session()->forget('google_access_token');
            return 'Session cleared';
        });
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'Google Settings',
                'description' => 'Manage Google Calendar integration settings',
                'category'    => 'Appointments',
                'icon'        => 'icon-cog',
                'class'       => 'Doctor\Appointments\Models\GoogleSettings',
                'order'       => 500,
                'keywords'    => 'google calendar settings',
                'permissions' => ['doctor.appointments.access_settings']
            ]
        ];
    }

    public function registerPermissions()
    {
        return [
            'doctor.appointments.appointments' => [
                'tab' => 'Appointments',
                'label' => 'Manage appointments'
            ],
            'doctor.appointments.consultation_types' => [
                'tab' => 'Appointments',
                'label' => 'Manage consultation types'
            ],
            'doctor.appointments.access_settings' => [
                'tab' => 'Appointments',
                'label' => 'Access settings'
            ]
        ];
    }
}