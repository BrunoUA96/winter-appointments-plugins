<?php namespace Doctor\Appointments\Updates;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Winter\Storm\Database\Updates\Migration;

class AddCancelledByPatientStatus extends Migration
{
    public function up()
    {
        // Изменяем enum для добавления нового статуса
        DB::statement("ALTER TABLE `doctor_appointments_appointments` MODIFY COLUMN `status` ENUM('pending', 'approved', 'cancelled', 'cancelled_by_patient') DEFAULT 'pending'");
    }

    public function down()
    {
        // Возвращаем обратно к старому enum (но сначала нужно убедиться, что нет записей с новым статусом)
        DB::statement("UPDATE `doctor_appointments_appointments` SET `status` = 'cancelled' WHERE `status` = 'cancelled_by_patient'");
        DB::statement("ALTER TABLE `doctor_appointments_appointments` MODIFY COLUMN `status` ENUM('pending', 'approved', 'cancelled') DEFAULT 'pending'");
    }
}

