<?php namespace Doctor\Appointments\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use Doctor\Appointments\Models\Appointment;
use Winter\Storm\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

class Appointments extends Controller
{
    public $implement = [        'Backend\Behaviors\ListController',        'Backend\Behaviors\FormController',            ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Doctor.Appointments', 'main-menu-item', 'side-menu-appointments');
        
        // Подключаем CSS для стилизации статусов
        $this->addCss('/plugins/doctor/appointments/assets/css/status-styles.css');
    }

    /**
     * AJAX обработчик для изменения статуса записи
     */
    public function onChangeStatus()
    {
        try {
            $appointmentId = Input::get('appointment_id');
            $newStatus = Input::get('status');

            if (!$appointmentId || !$newStatus) {
                throw new \Exception('Missing required parameters');
            }

            // Проверяем валидность статуса
            $validStatuses = ['pending', 'approved', 'cancelled'];
            if (!in_array($newStatus, $validStatuses)) {
                throw new \Exception('Invalid status');
            }

            $appointment = Appointment::find($appointmentId);
            if (!$appointment) {
                throw new \Exception('Appointment not found');
            }

            $oldStatus = $appointment->status;
            $appointment->status = $newStatus;
            $appointment->save();

            Log::info("Appointment status changed", [
                'appointment_id' => $appointmentId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error changing appointment status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    // /**
    //  * Обновить список записей
    //  */
    // public function onRefresh()
    // {
    //     return $this->listRefresh();
    // }
}
