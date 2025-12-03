<?php namespace Doctor\Home\Components;

use Cms\Classes\ComponentBase;
use Doctor\Home\Models\HomeSettings;

class HomeContent extends ComponentBase
{
    public $settings;
    public $banner;
    public $consultationTypes;

    public function componentDetails()
    {
        return [
            'name'        => 'Home Content',
            'description' => 'Displays all home page content (banner and consultations)'
        ];
    }

    public function onRun()
    {
        $this->settings = HomeSettings::instance();
        
        // Load consultation types if IDs are set
        $consultationTypeIds = $this->settings->get('consultation_type_ids', []);
        if (!empty($consultationTypeIds)) {
            $this->consultationTypes = $this->settings->consultation_types;
        } else {
            $this->consultationTypes = collect([]);
        }
        
        // Make data available to page
        $this->page['homeSettings'] = $this->settings;
        $this->page['banner'] = [
            'title' => $this->settings->banner_title ?: null,
            'subtitle' => $this->settings->banner_subtitle ?: null,
            'image' => $this->settings->banner_image ?: null,
        ];
        $this->page['consultationTypes'] = $this->consultationTypes;
        $this->page['consultationSection'] = [
            'title' => $this->settings->consultation_title ?: null,
            'subtitle' => $this->settings->consultation_subtitle ?: null,
        ];
    }
}

