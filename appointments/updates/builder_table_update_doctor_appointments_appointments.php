<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateDoctorAppointmentsAppointments extends Migration
{
    public function up()
    {
        Schema::table('doctor_appointments_appointments', function($table)
        {
            $table->string('email');
            $table->string('phone');
        });
    }
    
    public function down()
    {
        Schema::table('doctor_appointments_appointments', function($table)
        {
            $table->dropColumn('email');
            $table->dropColumn('phone');
        });
    }
}
