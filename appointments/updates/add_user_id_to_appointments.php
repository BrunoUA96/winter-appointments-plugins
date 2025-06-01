<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddUserIdToAppointments extends Migration
{
    public function up()
    {
        Schema::table('doctor_appointments_appointments', function ($table) {
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('doctor_appointments_users');
        });
    }

    public function down()
    {
        Schema::table('doctor_appointments_appointments', function ($table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
}