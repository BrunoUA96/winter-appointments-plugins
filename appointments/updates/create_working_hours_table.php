<?php namespace Doctor\Appointments\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateWorkingHoursTable extends Migration
{
    public function up()
    {
        Schema::create('doctor_appointments_working_hours', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('day_of_week')->nullable()->comment('0 = Sunday, 6 = Saturday');
            $table->date('date')->nullable()->comment('Specific date for day off or override');
            $table->time('start_time')->nullable()->comment('Working hours start time');
            $table->time('end_time')->nullable()->comment('Working hours end time');
            $table->boolean('is_day_off')->default(false)->comment('Is this a day off');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            // Индексы для быстрого поиска
            $table->index('day_of_week');
            $table->index('date');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('doctor_appointments_working_hours');
    }
}

