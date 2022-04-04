<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('ecloud')->table('load_balancers', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('availability_zone_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('ecloud')->table('load_balancers', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('availability_zone_id')->nullable(false)->change();
        });
    }
};
