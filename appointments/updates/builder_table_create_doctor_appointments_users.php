<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateDoctorAppointmentsUsers extends Migration
{
    public function up()
    {
        Schema::create('doctor_appointments_users', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('doctor_appointments_users');
    }
}
