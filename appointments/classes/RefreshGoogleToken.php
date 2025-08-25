<?php

namespace Doctor\Appointments\Classes;

use Illuminate\Console\Command;
use Doctor\Appointments\Services\GoogleCalendarService;

class RefreshGoogleToken extends Command
{
    protected $signature = 'doctor:refresh-google-token';
    protected $description = 'Refresh Google OAuth token';

    public function handle()
    {
        $this->info('Attempting to refresh Google OAuth token...');

        try {
            $calendarService = new GoogleCalendarService();
            
            if ($calendarService->isAuthenticated()) {
                $this->info('Google Calendar is authenticated and token is valid!');
                return 0;
            } else {
                $this->error('Google Calendar is not authenticated. Please visit the admin panel to re-authenticate.');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->info('Please visit the admin panel to re-authenticate with Google.');
            return 1;
        }
    }
}
