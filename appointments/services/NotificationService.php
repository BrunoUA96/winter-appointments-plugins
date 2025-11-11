<?php namespace Doctor\Appointments\Services;

use Illuminate\Support\Facades\Mail;
use Doctor\Appointments\Models\Appointment;
use Doctor\Appointments\Models\Settings;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Отправить email с подтверждением записи (статус: pending)
     *
     * @param Appointment $appointment
     * @return bool
     */
    public function sendAppointmentConfirmation(Appointment $appointment)
    {
        try {
            // Валидация email
            if (empty($appointment->email) || !filter_var($appointment->email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Invalid email address for appointment confirmation', [
                    'appointment_id' => $appointment->id,
                    'email' => $appointment->email
                ]);
                return false;
            }

            // Подготовка данных
            $data = $this->prepareAppointmentData($appointment);

            // Отправка email используя шаблон Winter CMS
            Mail::send('doctor.appointments::mail.appointment_pending', $data, function($message) use ($appointment) {
                $message->to($appointment->email)
                        ->subject('Confirmação de Agendamento - Consulta em Análise');
            });

            Log::info("Appointment confirmation email sent successfully", [
                'appointment_id' => $appointment->id,
                'email' => $appointment->email,
                'status' => 'pending'
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending appointment confirmation email', [
                'appointment_id' => $appointment->id ?? null,
                'email' => $appointment->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Отправить email об одобрении записи (статус: approved)
     *
     * @param Appointment $appointment
     * @return bool
     */
    public function sendAppointmentApproved(Appointment $appointment)
    {
        try {
            // Валидация email
            if (empty($appointment->email) || !filter_var($appointment->email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Invalid email address for appointment approved', [
                    'appointment_id' => $appointment->id,
                    'email' => $appointment->email
                ]);
                return false;
            }

            // Подготовка данных
            $data = $this->prepareAppointmentData($appointment);

            // Отправка email используя шаблон Winter CMS
            Mail::send('doctor.appointments::mail.appointment_approved', $data, function($message) use ($appointment) {
                $message->to($appointment->email)
                        ->subject('Consulta Aprovada - Confirmação');
            });

            Log::info("Appointment approved email sent successfully", [
                'appointment_id' => $appointment->id,
                'email' => $appointment->email,
                'status' => 'approved'
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending appointment approved email', [
                'appointment_id' => $appointment->id ?? null,
                'email' => $appointment->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Отправить email об отмене записи (статус: cancelled)
     *
     * @param Appointment $appointment
     * @return bool
     */
    public function sendAppointmentCancelled(Appointment $appointment)
    {
        try {
            // Валидация email
            if (empty($appointment->email) || !filter_var($appointment->email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Invalid email address for appointment cancelled', [
                    'appointment_id' => $appointment->id,
                    'email' => $appointment->email
                ]);
                return false;
            }

            // Подготовка данных
            $data = $this->prepareAppointmentData($appointment);

            // Отправка email используя шаблон Winter CMS
            Mail::send('doctor.appointments::mail.appointment_cancelled', $data, function($message) use ($appointment) {
                $message->to($appointment->email)
                        ->subject('Consulta Cancelada');
            });

            Log::info("Appointment cancelled email sent successfully", [
                'appointment_id' => $appointment->id,
                'email' => $appointment->email,
                'status' => 'cancelled'
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending appointment cancelled email', [
                'appointment_id' => $appointment->id ?? null,
                'email' => $appointment->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Отправить email администратору о том, что пациент отменил консультацию
     *
     * @param Appointment $appointment
     * @return bool
     */
    public function sendAdminCancellationNotification(Appointment $appointment)
    {
        try {
            // Получаем email администратора из настроек
            $settings = Settings::instance();
            $adminEmail = $settings->admin_email;

            // Проверяем, что email администратора настроен
            if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Admin email not configured or invalid', [
                    'admin_email' => $adminEmail
                ]);
                return false;
            }

            // Подготовка данных для администратора (включая email и phone пациента)
            $data = $this->prepareAdminAppointmentData($appointment);

            // Отправка email используя шаблон Winter CMS
            Mail::send('doctor.appointments::mail.appointment_admin_cancelled', $data, function($message) use ($adminEmail) {
                $message->to($adminEmail)
                        ->subject('Consulta Cancelada pelo Paciente');
            });

            Log::info("Admin cancellation notification email sent successfully", [
                'appointment_id' => $appointment->id,
                'admin_email' => $adminEmail
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending admin cancellation notification email', [
                'appointment_id' => $appointment->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Отправить email администратору о новой записи
     *
     * @param Appointment $appointment
     * @return bool
     */
    public function sendAdminNotification(Appointment $appointment)
    {
        try {
            // Получаем email администратора из настроек
            $settings = Settings::instance();
            $adminEmail = $settings->admin_email;

            // Проверяем, что email администратора настроен
            if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Admin email not configured or invalid', [
                    'admin_email' => $adminEmail
                ]);
                return false;
            }

            // Подготовка данных для администратора (включая email и phone пациента)
            $data = $this->prepareAdminAppointmentData($appointment);

            // Отправка email используя шаблон Winter CMS
            Mail::send('doctor.appointments::mail.appointment_admin_notification', $data, function($message) use ($adminEmail) {
                $message->to($adminEmail)
                        ->subject('Nova Consulta Agendada - Requer Aprovação');
            });

            Log::info("Admin notification email sent successfully", [
                'appointment_id' => $appointment->id,
                'admin_email' => $adminEmail
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending admin notification email', [
                'appointment_id' => $appointment->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Подготовить данные для email
     *
     * @param Appointment $appointment
     * @return array
     */
    protected function prepareAppointmentData(Appointment $appointment)
    {
        // Генерируем токен и ссылку для просмотра/отмены консультации
        $token = $appointment->generatePublicToken();
        $viewUrl = url('appointment/' . $appointment->id . '/' . $token);
        
        return [
            'patient_name' => $appointment->patient_name ?? 'Cliente',
            'appointment_time' => $appointment->appointment_time 
                ? $appointment->appointment_time->format('d/m/Y H:i') 
                : 'Não especificado',
            'consultation_type' => $appointment->consultation_type 
                ? $appointment->consultation_type->name 
                : 'Não especificado',
            'description' => $appointment->description ?? null,
            'view_url' => $viewUrl,
        ];
    }

    /**
     * Подготовить данные для email администратора (включая контактные данные)
     *
     * @param Appointment $appointment
     * @return array
     */
    protected function prepareAdminAppointmentData(Appointment $appointment)
    {
        return [
            'patient_name' => $appointment->patient_name ?? 'Cliente',
            'patient_email' => $appointment->email ?? 'Não especificado',
            'patient_phone' => $appointment->phone ?? 'Não especificado',
            'appointment_time' => $appointment->appointment_time 
                ? $appointment->appointment_time->format('d/m/Y H:i') 
                : 'Não especificado',
            'consultation_type' => $appointment->consultation_type 
                ? $appointment->consultation_type->name 
                : 'Não especificado',
            'description' => $appointment->description ?? null,
            'appointment_id' => $appointment->id ?? null,
        ];
    }

    /**
     * Отправить напоминание о приеме
     *
     * @param Appointment $appointment
     * @return bool
     */
    public function sendAppointmentReminder(Appointment $appointment)
    {
        try {
            // Валидация email
            if (empty($appointment->email) || !filter_var($appointment->email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Invalid email address for appointment reminder', [
                    'appointment_id' => $appointment->id,
                    'email' => $appointment->email
                ]);
                return false;
            }

            $data = [
                'patient_name' => $appointment->patient_name,
                'appointment_time' => $appointment->appointment_time->format('d.m.Y H:i'),
                'consultation_type' => $appointment->consultation_type->name
            ];

            Mail::send('doctor.appointments::mail.appointment_reminder', $data, function($message) use ($appointment) {
                $message->to($appointment->email)
                        ->subject('Напоминание о приеме');
            });

            Log::info("Appointment reminder email sent successfully", [
                'appointment_id' => $appointment->id,
                'email' => $appointment->email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error sending appointment reminder email', [
                'appointment_id' => $appointment->id ?? null,
                'email' => $appointment->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}