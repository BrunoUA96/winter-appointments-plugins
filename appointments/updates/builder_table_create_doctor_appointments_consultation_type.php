<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateDoctorAppointmentsConsultationType extends Migration
{
    public function up()
    {
        Schema::create('doctor_appointments_consultation_type', function($table)
        {
            $table->engine = 'InnoDB';
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->text('name');
            $table->integer('duration');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('doctor_appointments_consultation_type');
    }
}
