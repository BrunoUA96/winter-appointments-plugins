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
            
            // Загружаем и проверяем токен
            $this->loadAndValidateToken();
            
            Log::info('Google client initialized successfully');
        } catch (\Exception $e) {
            Log::error('Error initializing Google client: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Загружает и валидирует токен, автоматически обновляя его при необходимости
     */
    protected function loadAndValidateToken()
    {
        $tokenPath = storage_path('app/google/token.json');
        
        if (!file_exists($tokenPath)) {
            Log::info('No token file found, authentication required');
            return;
        }

        try {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            
            // Добавляем недостающие поля, если их нет
            $accessToken = $this->normalizeToken($accessToken);
            
            $this->client->setAccessToken($accessToken);
            
            // Проверяем, нужно ли обновить токен
            if ($this->shouldRefreshToken($accessToken)) {
                Log::info('Token needs refresh, attempting to refresh...');
                $this->refreshAccessToken();
            }
            
        } catch (\Exception $e) {
            Log::error('Error loading token: ' . $e->getMessage());
            $this->removeInvalidToken();
        }
    }

    /**
     * Нормализует токен, добавляя недостающие поля
     */
    protected function normalizeToken($token)
    {
        // Если нет поля expires_at, вычисляем его из expires_in
        if (!isset($token['expires_at']) && isset($token['expires_in'])) {
            $token['expires_at'] = time() + $token['expires_in'];
        }
        
        // Если нет поля created, добавляем текущее время
        if (!isset($token['created'])) {
            $token['created'] = time();
        }
        
        return $token;
    }

    /**
     * Проверяет, нужно ли обновить токен
     */
    protected function shouldRefreshToken($token)
    {
        // Если нет поля expires_at, считаем токен истекшим
        if (!isset($token['expires_at'])) {
            Log::warning('Token missing expires_at field, considering expired');
            return true;
        }
        
        // Проверяем, истек ли токен (с запасом в 5 минут)
        $expiresAt = $token['expires_at'];
        $currentTime = time();
        $bufferTime = 300; // 5 минут
        
        if ($currentTime >= ($expiresAt - $bufferTime)) {
            Log::info("Token expires at " . date('Y-m-d H:i:s', $expiresAt) . ", current time: " . date('Y-m-d H:i:s', $currentTime));
            return true;
        }
        
        return false;
    }

    /**
     * Обновляет access token используя refresh token
     */
    protected function refreshAccessToken()
    {
        try {
            Log::info('Attempting to refresh access token...');
            
            if (!$this->client->getRefreshToken()) {
                throw new \Exception('No refresh token available');
            }
            
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            $newAccessToken = $this->client->getAccessToken();
            
            if (isset($newAccessToken['access_token'])) {
                // Нормализуем новый токен
                $newAccessToken = $this->normalizeToken($newAccessToken);
                
                // Сохраняем обновленный токен
                $tokenPath = storage_path('app/google/token.json');
                file_put_contents($tokenPath, json_encode($newAccessToken));
                
                Log::info('Access token refreshed successfully');
                Log::info('New token expires at: ' . (isset($newAccessToken['expires_at']) ? date('Y-m-d H:i:s', $newAccessToken['expires_at']) : 'unknown'));
                
                return true;
            } else {
                throw new \Exception('No new access token received after refresh');
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to refresh access token: ' . $e->getMessage());
            $this->removeInvalidToken();
            throw new \Exception('Access token expired and refresh failed. Please re-authenticate with Google.');
        }
    }

    /**
     * Удаляет недействительный токен
     */
    protected function removeInvalidToken()
    {
        $tokenPath = storage_path('app/google/token.json');
        if (file_exists($tokenPath)) {
            unlink($tokenPath);
            Log::info('Invalid token removed');
        }
    }

    /**
     * Проверяет и обновляет токен перед API вызовом
     */
    protected function ensureValidToken()
    {
        $tokenPath = storage_path('app/google/token.json');
        if (file_exists($tokenPath)) {
            $token = json_decode(file_get_contents($tokenPath), true);
            if ($this->shouldRefreshToken($token)) {
                Log::info('Token needs refresh before API call');
                $this->refreshAccessToken();
            }
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
            
            // Нормализуем токен перед сохранением
            $token = $this->normalizeToken($token);
            
            file_put_contents(storage_path('app/google/token.json'), json_encode($token));
            
            Log::info('Authentication completed successfully');
            Log::info('Token expires at: ' . (isset($token['expires_at']) ? date('Y-m-d H:i:s', $token['expires_at']) : 'unknown'));
            
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
            // Убеждаемся, что токен действителен перед API вызовом
            $this->ensureValidToken();
            
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