<?php namespace Doctor\Appointments\Models;

use Winter\Storm\Database\Model;

/**
 * Model
 */
class User extends Model
{

    /**
     * @var string The database table used by the model.
     */
    public $table = 'doctor_appointments_users';

    protected $fillable = ['name', 'email', 'phone'];

    public $hasMany = [
        'appointments' => \Doctor\Appointments\Models\Appointment::class
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public function getAppointmentsCountAttribute()
    {
        return $this->appointments()->count();
    }
    
    /**
     * @var array Attribute names to encode and decode using JSON.
     */
    public $jsonable = [];

    public function getAppointmentsTestAttribute()
    {
        return \Doctor\Appointments\Models\Appointment::where(function($query) {
            $query->where('email', $this->email)
                  ->orWhere('phone', $this->phone);
        })->get();
    }
}
