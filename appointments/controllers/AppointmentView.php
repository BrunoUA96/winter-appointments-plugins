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
            // Получаем активную тему
            $theme = \Cms\Classes\Theme::getActiveTheme();
            
            if (!$theme) {
                throw new \Exception('Active theme not found');
            }
            
            // Загружаем страницу из темы (используем loadCached для лучшей производительности)
            $page = \Cms\Classes\Page::loadCached($theme, 'appointment-view');
            
            // Если не найдена в кеше, загружаем напрямую
            if (!$page) {
                $page = \Cms\Classes\Page::load($theme, 'appointment-view');
            }
            
            if (!$page) {
                throw new \Exception('Page appointment-view not found in theme: ' . $theme->getDirName());
            }
            
            // Создаем CMS контроллер
            $controller = new \Cms\Classes\Controller($theme);
            
            // Устанавливаем параметры маршрута
            $controller->getRouter()->setParameters([
                'id' => $id,
                'token' => $token
            ]);
            
            // Рендерим страницу
            return $controller->runPage($page, false);
        } catch (\Exception $e) {
            Log::error('Error showing appointment: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Fallback на страницу ошибки из темы, если она есть
            try {
                $theme = \Cms\Classes\Theme::getActiveTheme();
                $errorPage = \Cms\Classes\Page::load($theme, 'error');
                if ($errorPage) {
                    $controller = new \Cms\Classes\Controller($theme);
                    return $controller->runPage($errorPage, false);
                }
            } catch (\Exception $e2) {
                Log::error('Error loading error page: ' . $e2->getMessage());
            }
            
            return response('Error loading appointment page: ' . $e->getMessage(), 500);
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
                Flash::error('O link de acesso é inválido ou expirado.');
                return redirect()->route('appointment.view', ['id' => $id, 'token' => $token]);
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

