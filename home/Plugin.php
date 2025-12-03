<?php

namespace Doctor\Home;

use Backend;
use Backend\Models\UserRole;
use System\Classes\PluginBase;

/**
 * Home Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'doctor.home::lang.plugin.name',
            'description' => 'doctor.home::lang.plugin.description',
            'author'      => 'Doctor',
            'icon'        => 'icon-home'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     */
    public function register(): void
    {

    }

    /**
     * Boot method, called right before the request route.
     */
    public function boot(): void
    {

    }

    /**
     * Registers any frontend components implemented in this plugin.
     */
    public function registerComponents(): array
    {
        return [
            'Doctor\Home\Components\HomeContent' => 'homeContent'
        ];
    }

    /**
     * Registers any backend permissions used by this plugin.
     */
    public function registerPermissions(): array
    {
        return [
            'doctor.home.manage_content' => [
                'tab' => 'doctor.home::lang.plugin.name',
                'label' => 'doctor.home::lang.plugin.name',
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
            ],
        ];
    }
    
}
