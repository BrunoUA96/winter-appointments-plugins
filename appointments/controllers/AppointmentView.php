<?php namespace Doctor\Appointments\Controllers;

use Doctor\Appointments\Models\Appointment;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Winter\Storm\Support\Facades\Flash;
use System\Traits\SecurityController;

class AppointmentView extends Controller
{
    use SecurityController;
    
    public function __construct()
    {
        // Применяем CSRF защиту только для POST запросов
        $this->middleware('web');
    }
    /**
     * Показать детали консультации по токену
     */
    public function show($id, $token)
    {
        try {
            $appointment = Appointment::findOrFail($id);
            
            // Проверяем токен
            if (!$appointment->verifyPublicToken($token)) {
                return response()->view('doctor.appointments::appointment.invalid', [], 403);
            }
            
            // Загружаем связи
            $appointment->load('consultation_type');
            
            return view('doctor.appointments::appointment.view', [
                'appointment' => $appointment,
                'token' => $token,
                'csrf_token' => Session::token()
            ]);
        } catch (\Exception $e) {
            Log::error('Error showing appointment: ' . $e->getMessage());
            return response()->view('doctor.appointments::appointment.not_found', [], 404);
        }
    }

    /**
     * Отменить консультацию
     */
    public function cancel($id, $token)
    {
        try {
            // Проверяем CSRF токен
            if (!$this->verifyCsrfToken()) {
                Flash::error('Token de segurança inválido. Por favor, tente novamente.');
                return redirect()->route('appointment.view', ['id' => $id, 'token' => $token]);
            }
            
            $appointment = Appointment::findOrFail($id);
            
            // Проверяем токен
            if (!$appointment->verifyPublicToken($token)) {
                return response()->view('doctor.appointments::appointment.invalid', [], 403);
            }
            
            // Проверяем, можно ли отменить
            if (!$appointment->canBeCancelled()) {
                Flash::error('Esta consulta não pode ser cancelada.');
                return redirect()->route('appointment.view', ['id' => $id, 'token' => $token]);
            }
            
            // Отменяем консультацию
            $appointment->status = Appointment::STATUS_CANCELLED_BY_PATIENT;
            $appointment->save();
            
            Flash::success('Consulta cancelada com sucesso.');
            
            return redirect()->route('appointment.view', ['id' => $id, 'token' => $token]);
        } catch (\Exception $e) {
            Log::error('Error cancelling appointment: ' . $e->getMessage());
            Flash::error('Erro ao cancelar a consulta. Por favor, tente novamente.');
            return redirect()->route('appointment.view', ['id' => $id, 'token' => $token]);
        }
    }
}

