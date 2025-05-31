<?php namespace Doctor\Appointments\Components;

use Cms\Classes\ComponentBase;
use Doctor\Appointments\Models\Appointment;
use Doctor\Appointments\Models\ConsultationType;
use Illuminate\Support\Facades\Request;
use Winter\Storm\Support\Facades\Flash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
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
    }

    public function onSaveBooking()
    {
        try {
            Log::info('Starting onSaveBooking');
            
            $appointment = new Appointment();
            $appointment->patient_name = Request::input('patient_name');
            $appointment->appointment_time = Carbon::parse(Request::input('appointment_time'));
            $appointment->consultation_type_id = Request::input('consultation_type_id');
            $appointment->description = Request::input('description');
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