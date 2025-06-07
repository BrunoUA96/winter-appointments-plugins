<?php namespace Doctor\Appointments\Components;

use Cms\Classes\ComponentBase;
use Doctor\Appointments\Models\Appointment;
use Doctor\Appointments\Models\ConsultationType;
use Doctor\Appointments\Models\User;
use Illuminate\Support\Facades\Request;
use Winter\Storm\Support\Facades\Flash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Doctor\Appointments\Models\GoogleSettings;
use Doctor\Appointments\Services\GoogleCalendarService;
use Winter\Storm\Support\Facades\Input;
use Winter\Storm\Exception\ValidationException;

class BookingForm extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Booking Form',
            'description' => 'Form for booking doctor appointments'
        ];
    }

    public function defineProperties()
    {
        return [
            'redirect' => [
                'title'       => 'Redirect after booking',
                'description' => 'Page to redirect to after successful booking',
                'type'        => 'string',
                'default'     => ''
            ]
        ];
    }

    public function onRun()
    {
        $this->page['consultationTypes'] = ConsultationType::all()->pluck('name', 'id');
        $this->page['availableTimes'] = $this->getAvailableTimes();
        $this->page['recaptcha_site_key'] = \Doctor\Appointments\Models\GoogleSettings::get('recaptcha_site_key');
    }

    public function onSaveBooking()
    {
        try {
            $data = Input::all();
            
            // Валидация
            $rules = [
                'patient_name' => 'required',
                'consultation_type_id' => 'required|exists:doctor_appointments_consultation_type,id',
                'appointment_time' => 'required|date',
                'email' => 'required|email',
                'phone' => 'required',
                'g-recaptcha-response' => 'required'
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            // Проверка reCAPTCHA
            $recaptcha = $this->verifyRecaptcha($data['g-recaptcha-response']);
            if (!$recaptcha) {
                throw new ValidationException(['g-recaptcha-response' => 'reCAPTCHA verification failed']);
            }

            // Создание записи
            $appointment = new Appointment();
            $appointment->fill($data);
            $appointment->save();

            // Создание события в Google Calendar
            try {
                $calendarService = new GoogleCalendarService();
                $eventId = $calendarService->createEvent($appointment);
                $appointment->google_event_id = $eventId;
                $appointment->save();
            } catch (\Exception $e) {
                Log::error('Error creating Google Calendar event: ' . $e->getMessage());
            }

            Flash::success('Запись успешно создана');
            
            if ($redirect = $this->property('redirect')) {
                return redirect($redirect);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Flash::error('Ошибка при создании записи: ' . $e->getMessage());
        }
    }

    protected function verifyRecaptcha($response)
    {
        $settings = \Doctor\Appointments\Models\Settings::instance();
        $secret = $settings->recaptcha_secret_key;
        
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $secret,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $result = json_decode($result);

        return $result->success;
    }

    protected function checkGoogleAuth()
    {
        try {
            Log::info('Starting checkGoogleAuth');
            
            $settings = \Doctor\Appointments\Models\GoogleSettings::instance();
            $clientId = $settings->google_client_id;
            $clientSecret = $settings->google_client_secret;

            Log::info('Google settings loaded', [
                'has_client_id' => !empty($clientId),
                'has_client_secret' => !empty($clientSecret)
            ]);

            if (empty($clientId) || empty($clientSecret)) {
                throw new \Exception('Google Client ID or Secret is missing');
            }

            $client = new Client();
            $client->setApplicationName('Winter CMS Doctor Booking');
            $client->setScopes([Calendar::CALENDAR_EVENTS]);
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->setRedirectUri('http://127.0.0.1:8000/google-callback');
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');

            $accessToken = session('google_access_token');
            Log::info('Access token status', ['has_token' => !empty($accessToken)]);

            if (!$accessToken) {
                $authUrl = $client->createAuthUrl();
                Log::info('Created auth URL', ['url' => $authUrl]);
                return [
                    'redirect' => $authUrl
                ];
            }

            Log::info('No redirect needed, token exists');
            return [];
        } catch (\Exception $e) {
            Log::error('Google auth check error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    protected function getAvailableTimes()
    {
        $times = [];
        $start = Carbon::today()->setHour(9)->setMinute(0);
        $end = Carbon::today()->setHour(17)->setMinute(0);

        while ($start <= $end) {
            if (!Appointment::where('appointment_time', $start)->exists()) {
                $times[] = $start->format('Y-m-d H:i');
            }
            $start->addMinutes(30);
        }

        return $times;
    }

    protected function createGoogleCalendarEvent($appointment)
    {
        try {
            $client = $this->getGoogleClient();
            $service = new Calendar($client);

            // Получаем настройки календаря
            $calendarId = GoogleSettings::get('calendar_id');
            if (!$calendarId) {
                throw new \Exception('Calendar ID не настроен');
            }

            // Создаем событие
            $event = new \Google\Service\Calendar\Event([
                'summary' => 'Прием пациента: ' . $appointment->patient_name,
                'description' => 'Тип консультации: ' . $appointment->consultationType->name . "\n" .
                               'Телефон: ' . $appointment->phone . "\n" .
                               'Email: ' . $appointment->email,
                'start' => [
                    'dateTime' => $appointment->appointment_time->format('c'),
                    'timeZone' => 'Europe/Kiev',
                ],
                'end' => [
                    'dateTime' => $appointment->appointment_time->addMinutes(30)->format('c'),
                    'timeZone' => 'Europe/Kiev',
                ],
                'attendees' => [
                    ['email' => $appointment->email]
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60], // За день
                        ['method' => 'popup', 'minutes' => 30], // За 30 минут
                    ],
                ],
            ]);

            $event = $service->events->insert($calendarId, $event, [
                'sendUpdates' => 'all' // Отправлять уведомления всем участникам
            ]);

            Log::info('Google Calendar event created', ['eventId' => $event->getId()]);
            return $event->getId();
        } catch (\Exception $e) {
            Log::error('Error creating Google Calendar event: ' . $e->getMessage());
            throw $e;
        }
    }
}