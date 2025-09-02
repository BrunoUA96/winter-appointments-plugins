<?php namespace Doctor\Appointments;

use System\Classes\PluginBase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Winter\Storm\Support\Facades\Flash;
use Backend\Facades\Backend;
use Doctor\Appointments\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Event;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'Doctor Appointments',
            'description' => 'Plugin for managing doctor appointments',
            'author'      => 'Doctor',
            'icon'        => 'icon-calendar'
        ];
    }

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
                $calendarService->handleAuthCallback($code);

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

        // Регистрируем AJAX-обработчик для Google Auth
        Event::listen('backend.ajax.beforeRun', function ($handler) {
            if ($handler->getAjaxHandler() === 'onGoogleAuth') {
                try {
                    $calendarService = new GoogleCalendarService();
                    $authUrl = $calendarService->getAuthUrl();
                    
                    return redirect($authUrl);
                } catch (\Exception $e) {
                    Flash::error('Error: ' . $e->getMessage());
                    return redirect()->refresh();
                }
            }
        });

        // Обработка callback от Google
        Event::listen('cms.page.beforeDisplay', function ($controller, $url, $page) {
            if (strpos($url, 'google/auth/callback') !== false) {
                Log::info('Processing Google callback');
                Log::info('Request parameters: ' . json_encode($_GET));
                
                try {
                    $calendarService = new GoogleCalendarService();
                    $code = input('code');
                    
                    if ($code) {
                        $calendarService->handleAuthCallback($code);
                        Flash::success('Google Calendar successfully connected');
                    }
                } catch (\Exception $e) {
                    Log::error('Error: ' . $e->getMessage());
                    Flash::error('Error connecting to Google Calendar: ' . $e->getMessage());
                }
                
                return redirect(Backend::url('doctor/appointments/settings'));
            }
        });
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'Настройки',
                'description' => 'Настройки плагина',
                'category'    => 'Doctor Appointments',
                'icon'        => 'icon-cog',
                'class'       => 'Doctor\Appointments\Models\Settings',
                'order'       => 500,
                'keywords'    => 'doctor appointments settings'
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
            ],
            'doctor.appointments.users' => [
                'tab' => 'Appointments',
                'label' => 'Manage users'
            ]
        ];
    }

    public function registerConsoleCommands()
    {
        return [
            'Doctor\Appointments\Classes\RefreshGoogleToken'
        ];
    }     
}