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

class BookingForm extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Booking Form',
            'description' => 'Form for booking doctor appointments'
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
            Log::info('Starting onSaveBooking');
            
            // Валидация reCAPTCHA
            $recaptchaResponse = Request::input('g-recaptcha-response');
            if (!$recaptchaResponse) {
                throw new \Exception('Пожалуйста, подтвердите, что вы не робот');
            }

            $recaptchaSecret = \Doctor\Appointments\Models\GoogleSettings::get('recaptcha_secret_key');
            $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $recaptchaSecret . '&response=' . $recaptchaResponse);
            $responseData = json_decode($verifyResponse);
            
            if (!$responseData->success) {
                throw new \Exception('Ошибка проверки reCAPTCHA');
            }
            
            // Валидация входных данных
            $rules = [
                'patient_name' => 'required',
                'appointment_time' => 'required|date',
                'consultation_type_id' => 'required|exists:doctor_appointments_consultation_type,id',
                'email' => 'required|email',
                'phone' => 'required|regex:/^\+?[0-9\s\-\(\)]+$/'
            ];

            $validation = Validator::make(Request::all(), $rules);

            if ($validation->fails()) {
                throw new \Exception($validation->errors()->first());
            }
            
            $appointment = new Appointment();
            $appointment->patient_name = Request::input('patient_name');
            $appointment->phone = Request::input('phone');

            $user = User::firstOrCreate(
                ['email' => Request::input('email')],
                [
                    'name' => Request::input('patient_name'),
                    'phone' => Request::input('phone')
                ]
            );

            if (Request::input('phone') && $user->phone !== Request::input('phone')) {
                $user->phone = Request::input('phone');
                $user->save();
            }

            $appointment->appointment_time = Carbon::parse(Request::input('appointment_time'));
            $appointment->consultation_type_id = Request::input('consultation_type_id');
            $appointment->description = Request::input('description');
            $appointment->email = Request::input('email');
            $appointment->user_id = $user->id;
            $appointment->save();

            Log::info('Appointment saved successfully');
            
            // Показываем сообщение об успешном создании
            Flash::success('Запись на прием успешно создана!');

            return [
                '@default' => $this->renderPartial('@default')
            ];
        } catch (\Exception $e) {
            Log::error('Booking Form Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            Flash::error('Ошибка при создании записи: ' . $e->getMessage());
            return [
                '@default' => $this->renderPartial('@default')
            ];
        }
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
}