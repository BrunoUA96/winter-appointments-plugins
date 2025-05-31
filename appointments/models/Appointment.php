<?php namespace Doctor\Appointments\Models;

use Winter\Storm\Database\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class Appointment extends Model
{
    use \Winter\Storm\Database\Traits\SoftDelete;

    protected $dates = ["deleted_at"];
    private static $isSyncing = false;

    public function getConsultationTypeOptions()
    {
        return ConsultationType::all()->lists("name", "id");
    }

    public $table = "doctor_appointments_appointments";

    protected $fillable = [
        "patient_name",
        "appointment_time",
        "consultation_type_id",
        "description",
        "google_event_id"
    ];

    public $belongsTo = [
        "consultation_type" => \Doctor\Appointments\Models\ConsultationType::class
    ];

    public function beforeSave()
    {
        // Преобразуем строку даты в объект DateTime только если это строка
        if ($this->appointment_time && !($this->appointment_time instanceof \DateTime)) {
            try {
                $this->appointment_time = new \DateTime($this->appointment_time);
            } catch (\Exception $e) {
                Log::error('Invalid date format: ' . $this->appointment_time);
                throw new \Exception('Invalid date format. Please use YYYY-MM-DD HH:mm:ss format.');
            }
        }
    }

    public function afterSave()
    {
        // Предотвращаем рекурсивные вызовы
        static $isSaving = false;
        if ($isSaving) {
            return;
        }
        $isSaving = true;

        try {
            // Создаем или обновляем событие в Google Calendar
            $calendarService = new \Doctor\Appointments\Services\GoogleCalendarService();
            $eventId = $calendarService->createEvent($this);
            
            // Сохраняем ID события в базе данных только если он изменился
            if ($eventId && $eventId !== $this->google_event_id) {
                // Используем update() вместо save() чтобы избежать рекурсии
                $this->update(['google_event_id' => $eventId]);
                $this->google_event_id = $eventId;
            }
            
            // Отправляем уведомления
            $notificationService = new \Doctor\Appointments\Services\NotificationService();
            
            // Отправляем подтверждение только для новых записей
            if ($this->wasRecentlyCreated) {
                $notificationService->sendAppointmentConfirmation($this);
            }
            
            // Планируем отправку напоминания
            $reminderTime = clone $this->appointment_time;
            $reminderTime->modify('-1 day');
            if ($reminderTime > new \DateTime()) {
                // Здесь можно использовать планировщик задач для отправки напоминания
                // Например, через Laravel's scheduler или через очередь
                Queue::later($reminderTime, function() use ($notificationService) {
                    $notificationService->sendAppointmentReminder($this);
                });
            }
            
            $isSaving = false;
        } catch (\Exception $e) {
            $isSaving = false;
            throw $e;
        }
    }
}
