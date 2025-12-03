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
    const STATUS_CANCELLED_BY_PATIENT = 'cancelled_by_patient';

    protected $dates = ["deleted_at", "birth_date"];
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
        "consultation_reason",
        "sns_number",
        "nif",
        "birth_date",
        "health_insurance",
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
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_APPROVED => 'Aprovado',
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_CANCELLED_BY_PATIENT => 'Cancelado pelo Paciente'
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
        return $this->status === self::STATUS_CANCELLED || $this->status === self::STATUS_CANCELLED_BY_PATIENT;
    }

    /**
     * Проверить, является ли запись отмененной пациентом
     */
    public function isCancelledByPatient()
    {
        return $this->status === self::STATUS_CANCELLED_BY_PATIENT;
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
            case self::STATUS_CANCELLED_BY_PATIENT:
                return 'danger';
            default:
                return 'secondary';
        }
    }

    /**
     * Генерировать токен для публичной ссылки
     */
    public function generatePublicToken()
    {
        // Используем хеш от ID и email для безопасности
        return md5($this->id . $this->email . config('app.key'));
    }

    /**
     * Проверить токен для публичной ссылки
     */
    public function verifyPublicToken($token)
    {
        return $this->generatePublicToken() === $token;
    }

    /**
     * Проверить, можно ли отменить консультацию (еще не прошла)
     */
    public function canBeCancelled()
    {
        if ($this->isCancelled() || $this->isCancelledByPatient()) {
            return false;
        }
        
        if (!$this->appointment_time) {
            return false;
        }
        
        $now = new \DateTime();
        $appointmentTime = $this->appointment_time instanceof \DateTime 
            ? $this->appointment_time 
            : new \DateTime($this->appointment_time);
        
        return $appointmentTime > $now;
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
                // Отменяем событие в Google Calendar для отмененных записей
                try {
                    $calendarService->cancelEvent($this);
                    Log::info('Google Calendar event cancelled for appointment');
                } catch (\Exception $e) {
                    Log::warning('Failed to cancel Google Calendar event: ' . $e->getMessage());
                }
            }
            
            // Отправляем email уведомления в зависимости от статуса
            $notificationService = new \Doctor\Appointments\Services\NotificationService();
            
            // Загружаем связи для email шаблонов
            $this->load('consultation_type');
            
            // Отправляем email при создании новой записи (статус: pending)
            if ($this->wasRecentlyCreated && $this->isPending()) {
                try {
                    // Отправляем email пациенту
                    $notificationService->sendAppointmentConfirmation($this);
                } catch (\Exception $e) {
                    Log::error('Failed to send appointment confirmation email: ' . $e->getMessage());
                }
                
                try {
                    // Отправляем email администратору
                    $notificationService->sendAdminNotification($this);
                } catch (\Exception $e) {
                    Log::error('Failed to send admin notification email: ' . $e->getMessage());
                }
            }
            
            // Отправляем email при изменении статуса на approved
            if ($this->isApproved() && $this->wasChanged('status')) {
                try {
                    $notificationService->sendAppointmentApproved($this);
                } catch (\Exception $e) {
                    Log::error('Failed to send appointment approved email: ' . $e->getMessage());
                }
            }
            
            // Отправляем email при изменении статуса на cancelled (администратором)
            if ($this->status === self::STATUS_CANCELLED && $this->wasChanged('status')) {
                try {
                    $notificationService->sendAppointmentCancelled($this);
                } catch (\Exception $e) {
                    Log::error('Failed to send appointment cancelled email: ' . $e->getMessage());
                }
            }
            
            // Отправляем email при отмене пациентом (cancelled_by_patient)
            if ($this->isCancelledByPatient() && $this->wasChanged('status')) {
                try {
                    // Отправляем email пациенту о подтверждении отмены
                    $notificationService->sendAppointmentCancelled($this);
                } catch (\Exception $e) {
                    Log::error('Failed to send appointment cancelled by patient email: ' . $e->getMessage());
                }
                
                try {
                    // Отправляем email администратору о том, что пациент отменил консультацию
                    $notificationService->sendAdminCancellationNotification($this);
                } catch (\Exception $e) {
                    Log::error('Failed to send admin notification about patient cancellation: ' . $e->getMessage());
                }
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