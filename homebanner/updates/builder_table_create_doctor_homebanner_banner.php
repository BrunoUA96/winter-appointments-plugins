<?php namespace Doctor\HomeBanner\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateDoctorHomebannerBanner extends Migration
{
    public function up()
    {
        Schema::create('doctor_homebanner_banner', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->text('image')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('doctor_homebanner_banner');
    }
}
