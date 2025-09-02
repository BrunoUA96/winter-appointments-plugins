<?php namespace Doctor\Appointments\Models;

use Winter\Storm\Database\Model;

/**
 * Model
 */
class ConsultationType extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    
    use \Winter\Storm\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'doctor_appointments_consultation_type';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
    
    /**
     * @var array Attribute names to encode and decode using JSON.
     */
    public $jsonable = ['features'];

    /**
     * Получить список features как массив
     */
    public function getFeaturesListAttribute()
    {
        if (empty($this->features)) {
            return [];
        }
        
        // Если features уже массив, возвращаем как есть
        if (is_array($this->features)) {
            return $this->features;
        }
        
        // Если это строка, разбиваем по строкам
        if (is_string($this->features)) {
            return array_filter(array_map('trim', explode("\n", $this->features)));
        }
        
        return [];
    }

    /**
     * Получить features как простой массив строк для repeater
     */
    public function getFeaturesArrayAttribute()
    {
        $features = $this->features_list;
        
        if (empty($features)) {
            return [];
        }
        
        // Если это repeater данные, извлекаем значения
        if (is_array($features) && isset($features[0]['feature'])) {
            return array_map(function($item) {
                return $item['feature'] ?? '';
            }, $features);
        }
        
        return $features;
    }

    /**
     * Получить features как HTML список
     */
    public function getFeaturesHtmlAttribute()
    {
        $features = $this->features_list;
        
        if (empty($features)) {
            return '';
        }
        
        $html = '<ul class="consultation-features">';
        foreach ($features as $feature) {
            $html .= '<li>' . e($feature) . '</li>';
        }
        $html .= '</ul>';
        
        return $html;
    }
}
