<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateDoctorAppointmentsConsultationType extends Migration
{
    public function up()
    {
        Schema::table('doctor_appointments_consultation_type', function($table)
        {
            $table->increments('id')->unsigned();
        });
    }
    
    public function down()
    {
        Schema::table('doctor_appointments_consultation_type', function($table)
        {
            $table->dropColumn('id');
        });
    }
}
