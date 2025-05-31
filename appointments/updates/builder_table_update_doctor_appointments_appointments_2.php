<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateDoctorAppointmentsAppointments2 extends Migration
{
    public function up()
    {
        Schema::table('doctor_appointments_appointments', function($table)
        {
            $table->string('email', 255)->default('null')->change();
            $table->string('phone', 255)->default('null')->change();
        });
    }
    
    public function down()
    {
        Schema::table('doctor_appointments_appointments', function($table)
        {
            $table->string('email', 255)->default(null)->change();
            $table->string('phone', 255)->default(null)->change();
        });
    }
}
