<?php namespace Doctor\Appointments\Components;

use Cms\Classes\ComponentBase;
use Doctor\Appointments\Models\Appointment;
use Doctor\Appointments\Models\ConsultationType;
use Doctor\Appointments\Models\User;
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

            // Google Calendar событие будет создано автоматически в модели Appointment
            // только когда статус записи изменится на "approved"
            Log::info('Appointment created with pending status');

            Flash::success('Запись успешно создана и отправлена на рассмотрение. Мы свяжемся с вами для подтверждения.');
            
            if ($redirect = $this->property('redirect')) {
                return redirect($redirect);
            }
        } catch (ValidationException $e) {
            Log::error('Validation exception: ' . json_encode($e->getErrors()));
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database error: ' . $e->getMessage());
            if (str_contains($e->getMessage(), 'doctor_appointments_users_email_unique')) {
                Flash::error('Пожалуйста, проверьте правильность email и телефона. Возможно, вы используете email или телефон, которые уже зарегистрированы в системе.');
            } else {
                Flash::error('Произошла ошибка при создании записи. Пожалуйста, попробуйте еще раз.');
            }
        } catch (\Exception $e) {
            Log::error('Error in onSaveBooking: ' . $e->getMessage());
            Flash::error('Ошибка при создании записи: ' . $e->getMessage());
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