<?php namespace Doctor\Appointments\Updates;

use Illuminate\Support\Facades\Schema;
use Winter\Storm\Database\Updates\Migration;

class AddStatusToAppointments extends Migration
{
    public function up()
    {
        Schema::table('doctor_appointments_appointments', function($table)
        {
            $table->enum('status', ['pending', 'approved', 'cancelled'])
                  ->default('pending')
                  ->after('description');
        });
    }

    public function down()
    {
        Schema::table('doctor_appointments_appointments', function($table)
        {
            $table->dropColumn('status');
        });
    }
}
