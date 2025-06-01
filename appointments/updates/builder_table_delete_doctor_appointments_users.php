<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableDeleteDoctorAppointmentsUsers extends Migration
{
    public function up()
    {
        Schema::dropIfExists('doctor_appointments_users');
    }
    
    public function down()
    {
        Schema::create('doctor_appointments_users', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('phone', 255);
        });
    }
}
