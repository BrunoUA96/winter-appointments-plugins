<?php namespace Doctor\Appointments\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Illuminate\Support\Facades\Log;
use Backend\Models\User;

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
            $this->client->setScopes([
                Calendar::CALENDAR_EVENTS,
                Calendar::CALENDAR
            ]);
            
            // Используем OAuth credentials
            $credentialsPath = storage_path('app/google/credentials.json');
            Log::info('Loading OAuth credentials from: ' . $credentialsPath);
            
            if (!file_exists($credentialsPath)) {
                throw new \Exception('OAuth credentials file does not exist at: ' . $credentialsPath);
            }
            
            $this->client->setAuthConfig($credentialsPath);
            $this->client->setAccessType('offline');
            $this->client->setPrompt('consent');
            
            // Загружаем сохраненный токен
            $tokenPath = storage_path('app/google/token.json');
            if (file_exists($tokenPath)) {
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $this->client->setAccessToken($accessToken);
                
                // Если токен истек, обновляем его
                if ($this->client->isAccessTokenExpired()) {
                    Log::info('Access token expired, attempting to refresh...');
                    
                    if ($this->client->getRefreshToken()) {
                        try {
                            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                            $newAccessToken = $this->client->getAccessToken();
                            
                            // Проверяем, что обновление прошло успешно
                            if (isset($newAccessToken['access_token'])) {
                                file_put_contents($tokenPath, json_encode($newAccessToken));
                                Log::info('Access token refreshed successfully');
                            } else {
                                throw new \Exception('Failed to refresh access token - no new token received');
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to refresh access token: ' . $e->getMessage());
                            // Удаляем недействительный токен
                            unlink($tokenPath);
                            throw new \Exception('Access token expired and refresh failed. Please re-authenticate with Google.');
                        }
                    } else {
                        Log::error('No refresh token available');
                        // Удаляем недействительный токен
                        unlink($tokenPath);
                        throw new \Exception('No refresh token available. Please re-authenticate with Google.');
                    }
                }
            }
            
            Log::info('Google client initialized successfully');
        } catch (\Exception $e) {
            Log::error('Error initializing Google client: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getAuthUrl()
    {
        return $this->client->createAuthUrl();
    }

    public function handleAuthCallback($code)
    {
        Log::info('handleAuthCallback', ['code' => $code]);
        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            file_put_contents(storage_path('app/google/token.json'), json_encode($token));
            return true;
        } catch (\Exception $e) {
            Log::error('Error handling auth callback: ' . $e->getMessage());
            throw $e;
        }
    }

    public function isAuthenticated()
    {
        return file_exists(storage_path('app/google/token.json'));
    }

    public function createEvent($appointment)
    {
        try {
            if (!$this->isAuthenticated()) {
                throw new \Exception('Google Calendar not authenticated');
            }

            $service = new Calendar($this->client);
            $calendarId = $this->settings->google_calendar_id;

            if (!$calendarId) {
                throw new \Exception('Google Calendar ID not configured');
            }

            $startDateTime = $appointment->appointment_time instanceof \DateTime 
                ? $appointment->appointment_time 
                : new \DateTime($appointment->appointment_time);
            
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
                'attendees' => [
                    ['email' => $appointment->email]
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60],
                        ['method' => 'popup', 'minutes' => 30],
                    ],
                ],
            ]);

            if ($appointment->google_event_id) {
                $event = $service->events->update($calendarId, $appointment->google_event_id, $event, [
                    'sendUpdates' => 'all'
                ]);
                Log::info("Updated Google Calendar event: {$event->id}");
            } else {
                $event = $service->events->insert($calendarId, $event, [
                    'sendUpdates' => 'all'
                ]);
                Log::info("Created new Google Calendar event: {$event->id}");
            }
            
            return $event->id;
        } catch (\Google\Service\Exception $e) {
            $error = json_decode($e->getMessage(), true);
            
            // Проверяем, связана ли ошибка с аутентификацией
            if (isset($error['error']) && $error['error'] === 'invalid_grant') {
                Log::error('Google Calendar authentication error: ' . $e->getMessage());
                // Удаляем недействительный токен
                $tokenPath = storage_path('app/google/token.json');
                if (file_exists($tokenPath)) {
                    unlink($tokenPath);
                }
                throw new \Exception('Google Calendar authentication expired. Please re-authenticate.');
            }
            
            Log::error('Google Calendar API error: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error creating/updating calendar event: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteEvent($calendarId, $eventId)
    {
        try {
            if (!$this->isAuthenticated()) {
                throw new \Exception('Google Calendar not authenticated');
            }

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