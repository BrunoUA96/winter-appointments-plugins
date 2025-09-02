<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddFeaturesToConsultationType extends Migration
{
    public function up()
    {
        Schema::table('doctor_appointments_consultation_type', function($table)
        {
            $table->json('features')->nullable()->after('duration');
        });
    }
    
    public function down()
    {
        Schema::table('doctor_appointments_consultation_type', function($table)
        {
            $table->dropColumn('features');
        });
    }
}
