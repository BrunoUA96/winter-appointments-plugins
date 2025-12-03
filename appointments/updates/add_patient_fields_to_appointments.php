<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddPatientFieldsToAppointments extends Migration
{
    public function up()
    {
        Schema::table('doctor_appointments_appointments', function($table)
        {
            $table->text('consultation_reason')->nullable()->after('description')->comment('Motivo da consulta');
            $table->string('sns_number', 50)->nullable()->after('consultation_reason')->comment('Número de utente (SNS)');
            $table->string('nif', 20)->nullable()->after('sns_number')->comment('NIF');
            $table->date('birth_date')->nullable()->after('nif')->comment('Data de nascimento');
            $table->string('health_insurance', 100)->nullable()->after('birth_date')->comment('Seguro de saúde');
        });
    }
    
    public function down()
    {
        Schema::table('doctor_appointments_appointments', function($table)
        {
            $table->dropColumn(['consultation_reason', 'sns_number', 'nif', 'birth_date', 'health_insurance']);
        });
    }
}

