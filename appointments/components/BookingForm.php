<?php namespace Doctor\Appointments\Components;

use Cms\Classes\ComponentBase;
use Doctor\Appointments\Models\Appointment;
use Doctor\Appointments\Models\ConsultationType;
use Doctor\Appointments\Models\User;
use Doctor\Appointments\Models\WorkingHours;
use Illuminate\Support\Facades\Request;
use Winter\Storm\Support\Facades\Flash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
// Google Calendar теперь обрабатывается в модели Appointment
use Winter\Storm\Support\Facades\Input;
use Winter\Storm\Exception\ValidationException;
use Illuminate\Database\QueryException;

class BookingForm extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Booking Form',
            'description' => 'Form for booking doctor appointments'
        ];
    }

    public function defineProperties()
    {
        return [
            'redirect' => [
                'title'       => 'Redirect after booking',
                'description' => 'Page to redirect to after successful booking',
                'type'        => 'string',
                'default'     => ''
            ]
        ];
    }

    public function onRun()
    {
        $this->page['consultationTypes'] = ConsultationType::all()->pluck('name', 'id');
        $this->page['availableTimes'] = $this->getAvailableTimes();
        
        // Получаем ключ reCAPTCHA из настроек
        $settings = \Doctor\Appointments\Models\Settings::instance();
        $siteKey = $settings->get('recaptcha_site_key');
        
        if (empty($siteKey)) {
            Log::error('reCAPTCHA site key is not set in settings');
        } else {
            Log::info('reCAPTCHA site key: ' . $siteKey);
        }
        
        $this->page['recaptcha_site_key'] = $siteKey;
    }

    public function onSaveBooking()
    {
        try {
            $data = Input::all();
            Log::info('Form data: ' . json_encode($data));
            
            // Валидация
            $rules = [
                'patient_name' => 'required',
                'consultation_type_id' => 'required|exists:doctor_appointments_consultation_type,id',
                'appointment_time' => 'required|date',
                'email' => 'required|email',
                'phone' => 'required',
                'g-recaptcha-response' => 'required'
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                Log::error('Validation failed: ' . json_encode($validator->errors()));
                throw new ValidationException($validator);
            }

            // Проверка reCAPTCHA
            $recaptcha = $this->verifyRecaptcha($data['g-recaptcha-response']);
            if (!$recaptcha) {
                Log::error('reCAPTCHA verification failed');
                throw new ValidationException(['g-recaptcha-response' => 'reCAPTCHA verification failed']);
            }

            // Ищем пользователя с точно такими же email и телефоном
            $user = User::where('email', $data['email'])
                       ->where('phone', $data['phone'])
                       ->first();

            if ($user) {
                // Если нашли пользователя с точно такими же данными, обновляем только имя если нужно
                Log::info('Found existing user with matching email and phone: ' . $user->id);
                
                if ($user->name !== $data['patient_name']) {
                    $user->name = $data['patient_name'];
                    $user->save();
                    Log::info('Updated user name');
                }
            } else {
                // Если не нашли пользователя с такими же данными, создаем нового
                Log::info('Creating new user with different contact details');
                $user = new User();
                $user->name = $data['patient_name'];
                $user->email = $data['email'];
                $user->phone = $data['phone'];
                $user->save();
            }

            // Создание записи
            $appointment = new Appointment();
            $appointment->fill($data);
            $appointment->user_id = $user->id;
            $appointment->status = 'pending'; // Устанавливаем статус "на рассмотрении"
            $appointment->save();

            // Google Calendar событие и email уведомления будут созданы автоматически 
            // в модели Appointment через afterSave() в зависимости от статуса
            Log::info('Appointment created with pending status');

            Flash::success('Consulta agendada com sucesso. Em breve você receberá um e-mail de confirmação.');
            
            if ($redirect = $this->property('redirect')) {
                return redirect($redirect);
            }
        } catch (ValidationException $e) {
            Log::error('Validation exception: ' . json_encode($e->getErrors()));
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database error: ' . $e->getMessage());
            if (str_contains($e->getMessage(), 'doctor_appointments_users_email_unique')) {
                Flash::error('Por favor, verifique se o email e o telefone estão corretos. Talvez você esteja usando um email ou telefone que já está registrado no sistema.');
            } else {
                Flash::error('Ocorreu um erro ao criar a consulta. Por favor, tente novamente.');
            }
        } catch (\Exception $e) {
            Log::error('Error in onSaveBooking: ' . $e->getMessage());
            Flash::error('Erro ao criar a consulta: ' . $e->getMessage());
        }
    }

    protected function verifyRecaptcha($response)
    {
        try {
            $settings = \Doctor\Appointments\Models\Settings::instance();
            $secret = $settings->get('recaptcha_secret_key');
            
            if (empty($secret)) {
                Log::error('reCAPTCHA secret key is not set');
                return false;
            }

            Log::info('Verifying reCAPTCHA with secret: ' . $secret);
            
            $url = 'https://www.google.com/recaptcha/api/siteverify';
            $data = [
                'secret' => $secret,
                'response' => $response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ];

            $options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data)
                ]
            ];

            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            
            if ($result === false) {
                Log::error('Failed to verify reCAPTCHA: Could not connect to Google');
                return false;
            }

            $result = json_decode($result);
            
            if (!$result) {
                Log::error('Failed to verify reCAPTCHA: Invalid response from Google');
                return false;
            }

            if (!$result->success) {
                Log::error('reCAPTCHA verification failed: ' . json_encode($result->{'error-codes'}));
                return false;
            }

            Log::info('reCAPTCHA verification successful');
            return true;
        } catch (\Exception $e) {
            Log::error('Error verifying reCAPTCHA: ' . $e->getMessage());
            return false;
        }
    }

    // Google Calendar аутентификация теперь обрабатывается в отдельном сервисе

    protected function getAvailableTimes()
    {
        $times = [];
        $start = Carbon::today()->setHour(9)->setMinute(0);
        $end = Carbon::today()->setHour(17)->setMinute(0);

        while ($start <= $end) {
            if (!Appointment::where('appointment_time', $start)->exists()) {
                $times[] = $start->format('Y-m-d H:i');
            }
            $start->addMinutes(30);
        }

        return $times;
    }

    /**
     * Получить доступные временные слоты для конкретной даты
     */
    public function onGetAvailableTimeSlots()
    {
        $date = Input::get('date');
        $consultationTypeId = Input::get('consultation_type_id');
        
        if (!$date) {
            return response()->json([
                'success' => false,
                'message' => 'Date is required'
            ]);
        }

        try {
            $selectedDate = Carbon::parse($date);
            
            // Проверяем, что дата не в прошлом
            if ($selectedDate->isPast() && !$selectedDate->isToday()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível selecionar uma data no passado'
                ]);
            }
            
            // Получаем длительность консультации, если указан тип
            $duration = 30; // По умолчанию 30 минут
            if ($consultationTypeId) {
                $consultationType = ConsultationType::find($consultationTypeId);
                if ($consultationType && $consultationType->duration) {
                    $duration = (int)$consultationType->duration;
                }
            }
            
            $timeSlots = $this->getAvailableTimeSlotsForDate($selectedDate, $duration);
            
            // Проверяем, доступна ли дата (не является ли выходным днем)
            $isDateAvailable = WorkingHours::isDateAvailable($selectedDate);
            
            return response()->json([
                'success' => true,
                'timeSlots' => $timeSlots,
                'date' => $selectedDate->format('Y-m-d'),
                'isDateAvailable' => $isDateAvailable
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting available time slots: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter horários disponíveis'
            ]);
        }
    }

    /**
     * Получить список недоступных дат (выходные и отпуска)
     */
    public function onGetUnavailableDates()
    {
        try {
            $unavailableDates = [];
            
            // Получаем все выходные дни (конкретные даты)
            $dayOffs = WorkingHours::where('is_day_off', true)
                ->whereNotNull('date')
                ->get();
            
            foreach ($dayOffs as $dayOff) {
                $unavailableDates[] = $dayOff->date->format('Y-m-d');
            }
            
            // Получаем регулярные выходные дни недели на ближайшие 3 месяца
            $regularDayOffs = WorkingHours::where('is_day_off', true)
                ->whereNotNull('day_of_week')
                ->whereNull('date')
                ->pluck('day_of_week')
                ->toArray();
            
            if (!empty($regularDayOffs)) {
                $startDate = Carbon::today();
                $endDate = Carbon::today()->addMonths(3);
                
                $currentDate = $startDate->copy();
                while ($currentDate <= $endDate) {
                    if (in_array($currentDate->dayOfWeek, $regularDayOffs)) {
                        $unavailableDates[] = $currentDate->format('Y-m-d');
                    }
                    $currentDate->addDay();
                }
            }
            
            // Убеждаемся, что возвращаем массив и переиндексируем его
            $unavailableDates = array_values(array_unique($unavailableDates));
            
            return response()->json([
                'success' => true,
                'unavailableDates' => $unavailableDates
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting unavailable dates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter datas indisponíveis',
                'unavailableDates' => []
            ]);
        }
    }

    /**
     * Получить доступные временные слоты для конкретной даты с учетом длительности консультации
     */
    protected function getAvailableTimeSlotsForDate(Carbon $date, $duration = 30)
    {
        $timeSlots = [];
        
        // Проверяем, является ли дата выходным днем
        if (WorkingHours::isDayOff($date)) {
            return $timeSlots; // Возвращаем пустой массив для выходных дней
        }
        
        // Получаем рабочие часы для этой даты
        $startTime = WorkingHours::getStartTimeForDate($date);
        $endTime = WorkingHours::getEndTimeForDate($date);
        
        // Парсим время начала и окончания работы
        $startTimeParts = explode(':', $startTime);
        $endTimeParts = explode(':', $endTime);
        
        $start = $date->copy()->setHour((int)$startTimeParts[0])->setMinute((int)$startTimeParts[1]);
        $end = $date->copy()->setHour((int)$endTimeParts[0])->setMinute((int)$endTimeParts[1]);
        
        // Получаем все существующие записи на эту дату с их длительностью
        $existingAppointments = Appointment::whereDate('appointment_time', $date->format('Y-m-d'))
            ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_APPROVED])
            ->with('consultation_type')
            ->get()
            ->map(function($appointment) {
                $appointmentStart = Carbon::parse($appointment->appointment_time);
                $appointmentDuration = $appointment->consultation_type ? (int)$appointment->consultation_type->duration : 30;
                $appointmentEnd = $appointmentStart->copy()->addMinutes($appointmentDuration);
                
                return [
                    'start' => $appointmentStart,
                    'end' => $appointmentEnd,
                    'start_time' => $appointmentStart->format('H:i'),
                    'end_time' => $appointmentEnd->format('H:i')
                ];
            })
            ->toArray();

        while ($start <= $end) {
            $timeString = $start->format('H:i');
            $slotStart = $start->copy();
            $slotEnd = $start->copy()->addMinutes($duration);
            
            // Проверяем, что слот не выходит за рабочие часы
            if ($slotEnd > $end) {
                // Если слот выходит за рабочие часы, пропускаем его
                $start->addMinutes(30);
                continue;
            }
            
            // Проверяем, не перекрывается ли этот слот с существующими консультациями
            $isAvailable = true;
            
            foreach ($existingAppointments as $appointment) {
                $appointmentStart = $appointment['start'];
                $appointmentEnd = $appointment['end'];
                
                // Проверяем перекрытие: новый слот не должен перекрываться с существующими
                // Слот доступен, если он начинается после окончания существующей консультации
                // или заканчивается до начала существующей консультации
                // Если есть перекрытие (не выполняется условие выше), слот недоступен
                if (!($slotEnd <= $appointmentStart || $slotStart >= $appointmentEnd)) {
                    $isAvailable = false;
                    break;
                }
            }
            
            $timeSlots[] = [
                'time' => $timeString,
                'display' => $start->format('H:i'),
                'available' => $isAvailable
            ];
            
            $start->addMinutes(30);
        }

        return $timeSlots;
    }

    // Google Calendar события теперь создаются автоматически в модели Appointment
    // только когда статус записи изменяется на "approved"

    public function onGetConsultationFeatures()
    {
        // Добавляем логирование для отладки
        Log::info('onGetConsultationFeatures called', [
            'input' => Input::all(),
            'consultation_type_id' => Input::get('consultation_type_id')
        ]);
        
        $consultationTypeId = Input::get('consultation_type_id');
        
        if (!$consultationTypeId) {
            Log::warning('No consultation_type_id provided');
            return response()->json(['error' => 'Consultation type ID is required']);
        }
        
        $consultationType = ConsultationType::find($consultationTypeId);
        
        if (!$consultationType) {
            Log::warning('Consultation type not found', ['id' => $consultationTypeId]);
            return response()->json(['error' => 'Consultation type not found']);
        }
        
        $features = $consultationType->features_array;
        
        Log::info('Features retrieved successfully', [
            'consultation_type_id' => $consultationTypeId,
            'features_count' => count($features),
            'features' => $features
        ]);
        
        return response()->json([
            'success' => true,
            'features' => $features,
            'consultation_name' => $consultationType->name
        ]);
    }
}