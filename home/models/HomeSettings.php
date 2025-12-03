<?php namespace Doctor\Home\Models;

use Winter\Storm\Database\Model;

/**
 * HomeSettings Model
 */
class HomeSettings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'doctor_home_settings';

    public $settingsFields = 'fields.yaml';
    
    /**
     * Get consultation types
     */
    public function getConsultationTypesAttribute()
    {
        $ids = $this->get('consultation_type_ids', []);
        
        if (empty($ids)) {
            return collect([]);
        }
        
        // Ensure it's an array
        if (!is_array($ids)) {
            $ids = json_decode($ids, true) ?: [];
        }
        
        if (empty($ids) || !is_array($ids)) {
            return collect([]);
        }
        
        // Convert string IDs to integers
        $ids = array_map('intval', $ids);
        
        if (empty($ids)) {
            return collect([]);
        }
        
        return \Doctor\Appointments\Models\ConsultationType::whereIn('id', $ids)
            ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')')
            ->get();
    }
    
    /**
     * Get consultation type options for checkboxlist
     */
    public function getConsultationTypeOptions()
    {
        return \Doctor\Appointments\Models\ConsultationType::all()->pluck('name', 'id')->toArray();
    }
    
    /**
     * Get banner title
     */
    public function getBannerTitleAttribute()
    {
        return $this->get('banner_title');
    }
    
    /**
     * Get banner subtitle
     */
    public function getBannerSubtitleAttribute()
    {
        return $this->get('banner_subtitle');
    }
    
    /**
     * Get banner image
     */
    public function getBannerImageAttribute()
    {
        return $this->get('banner_image');
    }
    
    /**
     * Get consultation title
     */
    public function getConsultationTitleAttribute()
    {
        return $this->get('consultation_title');
    }
    
    /**
     * Get consultation subtitle
     */
    public function getConsultationSubtitleAttribute()
    {
        return $this->get('consultation_subtitle');
    }
}

