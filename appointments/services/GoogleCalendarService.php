<?php namespace Doctor\Appointments\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    protected $client;
    protected $settings;

    public function __construct()
    {
        $this->settings = \Doctor\Appointments\Models\GoogleSettings::instance();
        $this->initializeClient();
    }

    protected function initializeClient()
    {
        try {
            $this->client = new Client();
            $this->client->setApplicationName('Winter CMS Doctor Booking');
            $this->client->setScopes([Calendar::CALENDAR_EVENTS]);
            
            // Используем сервисный аккаунт
            $credentialsPath = storage_path('app/google/service-account.json');
            Log::info('Loading credentials from: ' . $credentialsPath);
            
            if (!file_exists($credentialsPath)) {
                throw new \Exception('Credentials file does not exist at: ' . $credentialsPath);
            }
            
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse credentials JSON: ' . json_last_error_msg());
            }
            
            $this->client->setAuthConfig($credentials);
            Log::info('Google client initialized successfully');
        } catch (\Exception $e) {
            Log::error('Error initializing Google client: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getAccessToken()
    {
        try {
            return $this->client->fetchAccessTokenWithAssertion();
        } catch (\Exception $e) {
            Log::error('Error getting access token: ' . $e->getMessage());
            throw $e;
        }
    }

    public function createEvent($appointment)
    {
        try {
            // Получаем токен
            $token = $this->getAccessToken();
            if (!$token) {
                throw new \Exception('Failed to get access token');
            }

            $service = new Calendar($this->client);
            // Используем ID календаря из настроек
            $calendarId = $this->settings->google_calendar_id;

            // Используем существующий объект DateTime или создаем новый
            $startDateTime = $appointment->appointment_time instanceof \DateTime 
                ? $appointment->appointment_time 
                : new \DateTime($appointment->appointment_time);
            
            // Создаем новый объект DateTime для endDateTime
            $endDateTime = new \DateTime($startDateTime->format('Y-m-d H:i:s'));
            $endDateTime->modify("+{$appointment->consultation_type->duration} minutes");

            $event = new Event([
                'summary' => "Запись: {$appointment->patient_name} ({$appointment->consultation_type->name})",
                'description' => $appointment->description,
                'start' => [
                    'dateTime' => $startDateTime->format('c'),
                    'timeZone' => 'UTC',
                ],
                'end' => [
                    'dateTime' => $endDateTime->format('c'),
                    'timeZone' => 'UTC',
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60], // За день до события
                        ['method' => 'popup', 'minutes' => 30], // За 30 минут до события
                    ],
                ],
            ]);

            // Если у записи уже есть ID события в Google Calendar, обновляем его
            if ($appointment->google_event_id) {
                $event = $service->events->update($calendarId, $appointment->google_event_id, $event);
                Log::info("Updated Google Calendar event: {$event->id}");
            } else {
                // Создаем новое событие
                $event = $service->events->insert($calendarId, $event);
                Log::info("Created new Google Calendar event: {$event->id}");
            }
            
            return $event->id;
        } catch (\Exception $e) {
            Log::error('Error creating/updating calendar event: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteEvent($calendarId, $eventId)
    {
        try {
            $service = new Calendar($this->client);
            $service->events->delete($calendarId, $eventId);
            Log::info("Deleted Google Calendar event: {$eventId}");
            return true;
        } catch (\Exception $e) {
            Log::error('Error deleting calendar event: ' . $e->getMessage());
            throw $e;
        }
    }
} 