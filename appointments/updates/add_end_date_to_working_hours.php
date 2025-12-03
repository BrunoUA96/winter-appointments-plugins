<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddEndDateToWorkingHours extends Migration
{
    public function up()
    {
        Schema::table('doctor_appointments_working_hours', function($table)
        {
            $table->date('end_date')->nullable()->after('date')->comment('End date for vacation range');
        });
    }
    
    public function down()
    {
        Schema::table('doctor_appointments_working_hours', function($table)
        {
            $table->dropColumn('end_date');
        });
    }
}

