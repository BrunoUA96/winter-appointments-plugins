<?php namespace Doctor\Appointments\Models;

use Winter\Storm\Database\Model;
use Illuminate\Support\Facades\Log;
use Doctor\Appointments\Services\GoogleCalendarService;

class Appointment extends Model
{
    use \Winter\Storm\Database\Traits\SoftDelete;

    // Константы для статусов
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_CANCELLED = 'cancelled';

    protected $dates = ["deleted_at"];
    private static $isSyncing = false;
    
    /**
     * @var array Attribute names to encode and decode using JSON.
     */
    public $jsonable = [];

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
        "google_event_id",
        "email",
        "phone",
        'user_id',
        'status'
    ];

    public $belongsTo = [
        "consultation_type" => [
            'Doctor\Appointments\Models\ConsultationType',
            'key' => 'consultation_type_id'
        ],
        "user" => \Doctor\Appointments\Models\User::class
    ];

    public function getDisplayNameAttribute()
    {
        $type = ConsultationType::find($this->consultation_type_id);
        $typeName = $type ? $type->name : 'Нет типа';
        $time = $this->appointment_time ? $this->appointment_time : 'Нет времени';
        
        return $typeName . ' ' . $time;
    }

    /**
     * Получить опции статусов для формы
     */
    public function getStatusOptions()
    {
        return [
            self::STATUS_PENDING => 'На рассмотрении',
            self::STATUS_APPROVED => 'Одобрена',
            self::STATUS_CANCELLED => 'Отменена'
        ];
    }

    /**
     * Проверить, является ли запись одобренной
     */
    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Проверить, является ли запись отмененной
     */
    public function isCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Проверить, является ли запись на рассмотрении
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Получить цвет статуса для отображения
     */
    public function getStatusColorAttribute()
    {
        switch ($this->status) {
            case self::STATUS_PENDING:
                return 'warning';
            case self::STATUS_APPROVED:
                return 'success';
            case self::STATUS_CANCELLED:
                return 'danger';
            default:
                return 'secondary';
        }
    }

    /**
     * Получить текст статуса
     */
    public function getStatusTextAttribute()
    {
        $options = $this->getStatusOptions();
        return $options[$this->status] ?? 'Неизвестно';
    }

    public function scopeFilterByUser($query, $user)
    {
        return $query->where('email', $user->email)
                    ->orWhere('phone', $user->phone);
    }

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
            // Обрабатываем Google Calendar события в зависимости от статуса
            $calendarService = new \Doctor\Appointments\Services\GoogleCalendarService();
            
            if ($this->isApproved()) {
                // Создаем или обновляем событие в Google Calendar только для одобренных записей
                try {
                    $eventId = $calendarService->createEvent($this);
                    
                    // Сохраняем ID события в базе данных только если он изменился
                    if ($eventId && $eventId !== $this->google_event_id) {
                        // Используем update() вместо save() чтобы избежать рекурсии
                        $this->update(['google_event_id' => $eventId]);
                        $this->google_event_id = $eventId;
                        Log::info('Google Calendar event created/updated', ['event_id' => $eventId]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to create Google Calendar event: ' . $e->getMessage());
                }
            } elseif ($this->isCancelled() && $this->google_event_id) {
                // Удаляем событие из Google Calendar для отмененных записей
                try {
                    $calendarService->deleteEvent($this->google_event_id);
                    // Очищаем ID события
                    $this->update(['google_event_id' => null]);
                    $this->google_event_id = null;
                    Log::info('Google Calendar event deleted for cancelled appointment');
                } catch (\Exception $e) {
                    Log::warning('Failed to delete Google Calendar event: ' . $e->getMessage());
                }
            }
            
            // Отправляем уведомления (временно отключено)
            // $notificationService = new \Doctor\Appointments\Services\NotificationService();
            
            // Отправляем подтверждение только для новых записей
            if ($this->wasRecentlyCreated) {
                // $notificationService->sendAppointmentConfirmation($this);
                Log::info('Appointment confirmation would be sent here');
            }
            
            // Планируем отправку напоминания только для одобренных записей
            if ($this->isApproved()) {
                $reminderTime = clone $this->appointment_time;
                $reminderTime->modify('-1 day');
                if ($reminderTime > new \DateTime()) {
                    // TODO: Реализовать планировщик напоминаний через Laravel Scheduler
                    // или через отдельную команду консоли
                    Log::info('Reminder scheduled for appointment', [
                        'appointment_id' => $this->id,
                        'reminder_time' => $reminderTime->format('Y-m-d H:i:s')
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error in afterSave: ' . $e->getMessage());
        } finally {
            $isSaving = false;
        }
    }
} 