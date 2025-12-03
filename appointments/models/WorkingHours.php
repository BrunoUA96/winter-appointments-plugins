<?php namespace Doctor\Appointments\Models;

use Winter\Storm\Database\Model;
use Carbon\Carbon;

/**
 * WorkingHours Model
 */
class WorkingHours extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    
    /**
     * Инициализация модели
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        // Удаляем временные атрибуты перед сохранением
        $this->bindEvent('model.saveInternal', function() {
            unset($this->attributes['_create_date_range']);
            unset($this->attributes['_range_start']);
            unset($this->attributes['_range_end']);
        });
    }

    /**
     * @var string The database table used by the model.
     */
    public $table = 'doctor_appointments_working_hours';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'day_of_week' => 'nullable|integer|between:0,6',
        'date' => 'nullable|date',
        'end_date' => 'nullable|date|after_or_equal:date',
        'start_time' => ['nullable', 'regex:#^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$#'],
        'end_time' => ['nullable', 'regex:#^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$#'],
        'is_day_off' => 'boolean'
    ];

    /**
     * Преобразование времени перед валидацией
     */
    public function beforeValidate()
    {
        // Преобразуем формат времени из любого формата в H:i:s для валидации
        if ($this->start_time) {
            $this->start_time = $this->normalizeTime($this->start_time);
        }
        
        if ($this->end_time) {
            $this->end_time = $this->normalizeTime($this->end_time);
        }
    }

    /**
     * Нормализация формата времени
     */
    protected function normalizeTime($time)
    {
        if (empty($time)) {
            return null;
        }
        
        // Если время уже в формате H:i:s, возвращаем как есть
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $time)) {
            return $time;
        }
        
        // Если время в формате H:i, добавляем секунды
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return sprintf('%02d:%02d:00', (int)$matches[1], (int)$matches[2]);
        }
        
        // Пытаемся распарсить через Carbon
        try {
            $carbon = Carbon::parse($time);
            return $carbon->format('H:i:s');
        } catch (\Exception $e) {
            return $time;
        }
    }

    /**
     * Временные свойства для хранения информации о диапазоне дат
     */
    protected $dateRangeInfo = null;
    
    /**
     * Валидация перед сохранением
     */
    public function beforeSave()
    {
        // Удаляем поле type, так как оно используется только для UI
        if (isset($this->attributes['type'])) {
            unset($this->attributes['type']);
        }
        
        // Удаляем временные атрибуты, если они были установлены ранее
        if (isset($this->attributes['_create_date_range'])) {
            unset($this->attributes['_create_date_range']);
        }
        if (isset($this->attributes['_range_start'])) {
            unset($this->attributes['_range_start']);
        }
        if (isset($this->attributes['_range_end'])) {
            unset($this->attributes['_range_end']);
        }
        
        // Очищаем end_date перед сохранением (он используется только для создания диапазона)
        $endDateValue = $this->end_date;
        $this->end_date = null;
        
        // Если указан диапазон дат для отпуска, сохраняем информацию для afterSave()
        // Это работает только при создании новой записи (нет id)
        if (!$this->id && $this->is_day_off && $this->date && $endDateValue) {
            $startDate = Carbon::parse($this->date);
            $endDate = Carbon::parse($endDateValue);
            
            // Проверяем, что end_date >= date
            if ($endDate->lt($startDate)) {
                throw new \Winter\Storm\Exception\ValidationException([
                    'end_date' => 'The End Date must be after or equal to Start Date.'
                ]);
            }
            
            // Сохраняем информацию для создания остальных записей в afterSave()
            // Используем свойство класса, а не атрибуты модели
            $this->dateRangeInfo = [
                'create_range' => true,
                'range_start' => $startDate->format('Y-m-d'),
                'range_end' => $endDate->format('Y-m-d')
            ];
        }
        
        // Удаляем временные атрибуты, если они были установлены ранее
        if (isset($this->attributes['_create_date_range'])) {
            unset($this->attributes['_create_date_range']);
        }
        if (isset($this->attributes['_range_start'])) {
            unset($this->attributes['_range_start']);
        }
        if (isset($this->attributes['_range_end'])) {
            unset($this->attributes['_range_end']);
        }
        
        // Если указан только date без end_date, очищаем end_date
        if ($this->date && !$this->end_date) {
            $this->end_date = null;
        }
        
        // Если это выходной день, не требуем времени работы
        if ($this->is_day_off) {
            $this->start_time = null;
            $this->end_time = null;
        } else {
            // Преобразуем формат времени из HH:mm:ss в H:i для сохранения в БД
            if ($this->start_time) {
                try {
                    $time = Carbon::parse($this->start_time);
                    $this->start_time = $time->format('H:i');
                } catch (\Exception $e) {
                    // Если не удалось распарсить, пробуем извлечь часы и минуты
                    if (preg_match('/^(\d{1,2}):(\d{2})/', $this->start_time, $matches)) {
                        $this->start_time = sprintf('%02d:%02d', (int)$matches[1], (int)$matches[2]);
                    }
                }
            }
            
            if ($this->end_time) {
                try {
                    $time = Carbon::parse($this->end_time);
                    $this->end_time = $time->format('H:i');
                } catch (\Exception $e) {
                    // Если не удалось распарсить, пробуем извлечь часы и минуты
                    if (preg_match('/^(\d{1,2}):(\d{2})/', $this->end_time, $matches)) {
                        $this->end_time = sprintf('%02d:%02d', (int)$matches[1], (int)$matches[2]);
                    }
                }
            }
            
            // Проверяем, что end_time после start_time
            if ($this->start_time && $this->end_time) {
                $start = Carbon::parse($this->start_time);
                $end = Carbon::parse($this->end_time);
                
                if ($end <= $start) {
                    throw new \Winter\Storm\Exception\ValidationException([
                        'end_time' => 'The End Time must be after Start Time.'
                    ]);
                }
            }
        }
        
        // Если указана конкретная дата, очищаем day_of_week
        if ($this->date) {
            $this->day_of_week = null;
        }
        
        // Если указан day_of_week, очищаем date и end_date
        if ($this->day_of_week !== null) {
            $this->date = null;
            $this->end_date = null;
        }
        
        // В самом конце удаляем все временные атрибуты, чтобы они не попали в SQL
        unset($this->attributes['_create_date_range']);
        unset($this->attributes['_range_start']);
        unset($this->attributes['_range_end']);
    }
    
    /**
     * После сохранения создаем остальные записи для диапазона дат
     */
    public function afterSave()
    {
        // Проверяем, нужно ли создавать диапазон дат
        if ($this->dateRangeInfo && isset($this->dateRangeInfo['create_range']) && $this->dateRangeInfo['create_range']) {
            $startDate = Carbon::parse($this->dateRangeInfo['range_start']);
            $endDate = Carbon::parse($this->dateRangeInfo['range_end']);
            
            // Создаем записи для каждой даты в диапазоне (начиная со следующей после date)
            $currentDate = $startDate->copy()->addDay();
            
            while ($currentDate->lte($endDate)) {
                // Проверяем, не существует ли уже запись для этой даты
                $existing = self::whereDate('date', $currentDate->format('Y-m-d'))
                    ->where('is_day_off', true)
                    ->first();
                
                if (!$existing) {
                    $newRecord = new self();
                    $newRecord->date = $currentDate->format('Y-m-d');
                    $newRecord->is_day_off = true;
                    $newRecord->start_time = null;
                    $newRecord->end_time = null;
                    $newRecord->day_of_week = null;
                    $newRecord->end_date = null;
                    $newRecord->save();
                }
                
                $currentDate->addDay();
            }
            
            // Очищаем информацию о диапазоне
            $this->dateRangeInfo = null;
        }
    }

    /**
     * @var array Fillable fields
     */
    public $fillable = [
        'day_of_week',
        'date',
        'end_date',
        'start_time',
        'end_time',
        'is_day_off'
    ];

    /**
     * @var array Guarded fields (не сохраняются в БД)
     */
    public $guarded = ['type', 'create_range', '_create_date_range', '_range_start', '_range_end'];
    
    /**
     * Получить значение атрибута (исключаем type)
     */
    public function getAttribute($key)
    {
        if ($key === 'type') {
            // Определяем type на основе других полей
            if ($this->attributes['date'] ?? null) {
                return 'specific';
            } elseif (isset($this->attributes['day_of_week'])) {
                return 'regular';
            }
            return 'regular'; // по умолчанию
        }
        
        return parent::getAttribute($key);
    }
    
    /**
     * Установить значение атрибута (игнорируем type и временные атрибуты)
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'type' || 
            $key === '_create_date_range' || 
            $key === '_range_start' || 
            $key === '_range_end') {
            // Не сохраняем эти поля, они используются только для внутренней логики
            return $this;
        }
        
        return parent::setAttribute($key, $value);
    }
    
    /**
     * Получить атрибуты для сохранения (исключаем временные)
     */
    public function getAttributes()
    {
        $attributes = parent::getAttributes();
        
        // Удаляем временные атрибуты
        unset($attributes['_create_date_range']);
        unset($attributes['_range_start']);
        unset($attributes['_range_end']);
        
        return $attributes;
    }
    
    /**
     * Получить измененные атрибуты (исключаем временные)
     */
    public function getDirty()
    {
        $dirty = parent::getDirty();
        
        // Удаляем временные атрибуты из списка измененных
        unset($dirty['_create_date_range']);
        unset($dirty['_range_start']);
        unset($dirty['_range_end']);
        
        return $dirty;
    }

    /**
     * @var array Dates
     */
    protected $dates = ['date', 'end_date'];

    /**
     * Получить рабочие часы для конкретного дня недели
     */
    public static function getWorkingHoursForDayOfWeek($dayOfWeek)
    {
        return self::where('day_of_week', $dayOfWeek)
            ->whereNull('date')
            ->where('is_day_off', false)
            ->first();
    }

    /**
     * Получить рабочие часы для конкретной даты
     */
    public static function getWorkingHoursForDate(Carbon $date)
    {
        // Сначала проверяем, есть ли конкретная запись для этой даты
        $specific = self::whereDate('date', $date->format('Y-m-d'))
            ->first();
        
        if ($specific) {
            return $specific;
        }
        
        // Если нет конкретной записи, используем регулярные рабочие часы для дня недели
        $dayOfWeek = $date->dayOfWeek; // 0 = воскресенье, 6 = суббота
        return self::getWorkingHoursForDayOfWeek($dayOfWeek);
    }

    /**
     * Проверить, является ли дата выходным днем
     */
    public static function isDayOff(Carbon $date)
    {
        // Проверяем конкретную дату
        $specific = self::whereDate('date', $date->format('Y-m-d'))
            ->where('is_day_off', true)
            ->first();
        
        if ($specific) {
            return true;
        }
        
        // Проверяем регулярные выходные дни недели
        $dayOfWeek = $date->dayOfWeek;
        $regularDayOff = self::where('day_of_week', $dayOfWeek)
            ->whereNull('date')
            ->where('is_day_off', true)
            ->first();
        
        return $regularDayOff !== null;
    }

    /**
     * Получить время начала работы для даты
     */
    public static function getStartTimeForDate(Carbon $date)
    {
        $workingHours = self::getWorkingHoursForDate($date);
        
        if ($workingHours && !$workingHours->is_day_off && $workingHours->start_time) {
            return Carbon::parse($workingHours->start_time)->format('H:i');
        }
        
        // По умолчанию 9:00
        return '09:00';
    }

    /**
     * Получить время окончания работы для даты
     */
    public static function getEndTimeForDate(Carbon $date)
    {
        $workingHours = self::getWorkingHoursForDate($date);
        
        if ($workingHours && !$workingHours->is_day_off && $workingHours->end_time) {
            return Carbon::parse($workingHours->end_time)->format('H:i');
        }
        
        // По умолчанию 17:00
        return '17:00';
    }

    /**
     * Проверить, доступна ли дата для записи
     */
    public static function isDateAvailable(Carbon $date)
    {
        // Не доступна, если это выходной день
        if (self::isDayOff($date)) {
            return false;
        }
        
        // Доступна, если есть рабочие часы (или используются значения по умолчанию)
        return true;
    }

    /**
     * Форматирование дня недели для отображения в списке
     */
    public static function formatDayOfWeek($value, $column, $record)
    {
        if ($record->date) {
            return 'Specific Date';
        }
        
        $days = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday'
        ];
        
        return isset($days[$record->day_of_week]) ? $days[$record->day_of_week] : '';
    }
}

