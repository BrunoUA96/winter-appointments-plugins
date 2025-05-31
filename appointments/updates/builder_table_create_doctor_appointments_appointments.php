<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateDoctorAppointmentsAppointments extends Migration
{
    public function up()
    {
        Schema::create('doctor_appointments_appointments', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->text('patient_name');
            $table->dateTime('appointment_time');
            $table->integer('consultation_type_id');
            $table->timestamp('created_at');
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->text('description')->nullable();
            $table->text('google_event_id')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('doctor_appointments_appointments');
    }
}
