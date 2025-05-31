<?php namespace Doctor\Appointments\Models;

use Winter\Storm\Database\Model;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Illuminate\Support\Facades\Log;

class Appointment extends Model
{
    
    use \Winter\Storm\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    public function getConsultationTypeOptions()
    {
        return ConsultationType::all()->lists('name', 'id');
    }


    /**
     * @var string The database table used by the model.
     */
    public $table = 'doctor_appointments_appointments';

    protected $fillable = ['patient_name', 'appointment_time', 'consultation_type_id', 'description', 'google_event_id'];

    public $belongsTo = [
        'consultation_type' => \Doctor\Appointments\Models\ConsultationType::class
    ];

    public function afterSave()
    {
        Log::info('Appointment saved, starting Google Calendar sync for ID: ' . $this->id);
        $result = $this->syncWithGoogleCalendar();
        if ($result instanceof \Illuminate\Http\RedirectResponse) {
            Log::info('Redirecting to Google auth for appointment: ' . $this->id);
            return $result; // Возвращаем перенаправление
        }
    }

    protected function syncWithGoogleCalendar()
    {
        try {
            Log::info('Starting Google Calendar sync for appointment: ' . $this->id);

            $settings = \Doctor\Appointments\Models\GoogleSettings::instance();
            $clientId = $settings->google_client_id;
            $clientSecret = $settings->google_client_secret;

            if (empty($clientId) || empty($clientSecret)) {
                Log::error('Google Client ID or Secret is missing for appointment: ' . $this->id);
                throw new \Exception('Google Client ID or Secret is missing');
            }

            Log::info('Client ID and Secret loaded for appointment: ' . $this->id);

            $client = new Client();
            $client->setApplicationName('Winter CMS Doctor Booking');
            $client->setScopes([\Google\Service\Calendar::CALENDAR_EVENTS]);
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $redirectUri = 'http://127.0.0.1:8000/google-callback'; // Явно указываем для локальной разработки
            $client->setRedirectUri($redirectUri);
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');

            Log::info('Redirect URI set to: ' . $redirectUri);

            $accessToken = session('google_access_token');
            if (!$accessToken) {
                Log::info('No access token, generating auth URL for appointment: ' . $this->id);
                $authUrl = $client->createAuthUrl();
                return redirect($authUrl);
            }

            Log::info('Setting access token for appointment: ' . $this->id);
            $client->setAccessToken($accessToken);

            if ($client->isAccessTokenExpired()) {
                Log::info('Access token expired, refreshing for appointment: ' . $this->id);
                $client->refreshToken($client->getRefreshToken());
                session(['google_access_token' => $client->getAccessToken()]);
            }

            $service = new Calendar($client);
            $calendarId = 'primary';

            $endDateTime = clone $this->appointment_time;
            $endDateTime->modify("+{$this->consultation_type->duration} minutes");

            $event = new Event([
                'summary' => "Запись: {$this->patient_name} ({$this->consultation_type->name})",
                'description' => $this->description,
                'start' => [
                    'dateTime' => $this->appointment_time->toRfc3339String(),
                    'timeZone' => 'UTC',
                ],
                'end' => [
                    'dateTime' => $endDateTime->toRfc3339String(),
                    'timeZone' => 'UTC',
                ],
            ]);

            Log::info('Inserting event to Google Calendar for appointment: ' . $this->id);
            $event = $service->events->insert($calendarId, $event);
            $this->google_event_id = $event->id;
            $this->save();
            Log::info('Event created successfully with ID: ' . $event->id . ' for appointment: ' . $this->id);
        } catch (\Exception $e) {
            Log::error('Google Calendar Sync Error for appointment ' . $this->id . ': ' . $e->getMessage());
            throw $e; // Для отладки
        }
    }
}