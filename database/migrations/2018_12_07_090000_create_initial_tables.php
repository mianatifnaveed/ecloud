<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInitialTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ucs_datacentre', function (Blueprint $table) {
            $table->increments('ucs_datacentre_id');
            $table->string('ucs_datacentre_public_name');
            $table->string('ucs_datacentre_active');
        });

        Schema::create('ucs_reseller', function (Blueprint $table) {
            $table->increments('ucs_reseller_id');
            $table->integer('ucs_reseller_reseller_id');
            $table->integer('ucs_reseller_datacentre_id');
            $table->string('ucs_reseller_solution_name');
            $table->string('ucs_reseller_active');
            $table->string('ucs_reseller_status');
        });

        Schema::create('ucs_node', function (Blueprint $table) {
            $table->increments('ucs_node_id');
            $table->integer('ucs_node_reseller_id');
            $table->integer('ucs_node_ucs_reseller_id');
            $table->integer('ucs_node_datacentre_id');
            $table->integer('ucs_node_specification_id');
            $table->string('ucs_node_status');
        });

        Schema::create('ucs_specification', function (Blueprint $table) {
            $table->increments('ucs_specification_id');
            $table->string('ucs_specification_active');
            $table->string('ucs_specification_friendly_name');
            $table->integer('ucs_specification_cpu_qty');
            $table->integer('ucs_specification_cpu_cores');
            $table->string('ucs_specification_cpu_speed');
            $table->string('ucs_specification_ram');
        });

        DB::table('ucs_specification')->insert(
            array(
                'ucs_specification_id' => 1,
                'ucs_specification_active' => 'Yes',
                'ucs_specification_friendly_name' => '2 x Oct Core 2.7Ghz (E5-2680 v1) 128GB',
                'ucs_specification_cpu_qty' => 2,
                'ucs_specification_cpu_cores' => 8,
                'ucs_specification_cpu_speed' => '2.7Ghz',
                'ucs_specification_ram' => '128GB',
            )
        );


        Schema::create('servers', function (Blueprint $table) {
            $table->increments('servers_id');
            $table->integer('servers_reseller_id');
            $table->string('servers_type');
            $table->string('servers_subtype_id');
            $table->string('servers_active');
            $table->string('servers_ip');
            $table->string('servers_hostname');
            $table->string('servers_friendly_name');
            $table->integer('servers_ecloud_ucs_reseller_id');
            $table->string('servers_firewall_role');
        });

        Schema::create('server_subtype', function (Blueprint $table) {
            $table->increments('server_subtype_id');
            $table->string('server_subtype_parent_type');
            $table->string('server_subtype_name');
        });

        DB::table('server_subtype')->insert(
            array(
                'server_subtype_id' => 1,
                'server_subtype_parent_type' => 'ecloud vm',
                'server_subtype_name' => 'VMware',
            )
        );

        DB::table('server_subtype')->insert(
            array(
                'server_subtype_id' => 2,
                'server_subtype_parent_type' => 'virtual firewall',
                'server_subtype_name' => 'eCloud Dedicated',
            )
        );
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ucs_datacentre');
        Schema::dropIfExists('servers');
        Schema::dropIfExists('server_subtype');
    }
}
