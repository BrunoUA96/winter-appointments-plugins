<?php namespace Doctor\Appointments\Services;

use Illuminate\Support\Facades\Mail;
use Doctor\Appointments\Models\Appointment;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function sendAppointmentConfirmation(Appointment $appointment)
    {
        try {
            $data = [
                'patient_name' => $appointment->patient_name,
                'appointment_time' => $appointment->appointment_time->format('d.m.Y H:i'),
                'consultation_type' => $appointment->consultation_type->name,
                'description' => $appointment->description
            ];

            Mail::send('doctor.appointments::mail.appointment_confirmation', $data, function($message) use ($appointment) {
                $message->to($appointment->email)
                        ->subject('Подтверждение записи на прием');
            });

            Log::info("Appointment confirmation email sent to: {$appointment->email}");
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending appointment confirmation email: ' . $e->getMessage());
            return false;
        }
    }

    public function sendAppointmentReminder(Appointment $appointment)
    {
        try {
            $data = [
                'patient_name' => $appointment->patient_name,
                'appointment_time' => $appointment->appointment_time->format('d.m.Y H:i'),
                'consultation_type' => $appointment->consultation_type->name
            ];

            Mail::send('doctor.appointments::mail.appointment_reminder', $data, function($message) use ($appointment) {
                $message->to($appointment->email)
                        ->subject('Напоминание о приеме');
            });

            Log::info("Appointment reminder email sent to: {$appointment->email}");
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending appointment reminder email: ' . $e->getMessage());
            return false;
        }
    }
} 