<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('sales', function (Blueprint $table) {
            // Thay đổi kiểu dữ liệu của cột start_time thành VARCHAR
            $table->string('start_time')->change();
            $table->string('end_time')->change();
        });
    }
    
    public function down()
    {
        Schema::table('sales', function (Blueprint $table) {

        });
    }
};
