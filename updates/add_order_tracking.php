<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * The order keeps a copy of the tracking code, the tracking link and the carrier status.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('amoriumcms_shop_orders', function (Blueprint $table) {
            $table->string('tracking_code', 64)->nullable();
            $table->string('tracking_url', 512)->nullable();
            $table->string('shipment_status', 32)->nullable();
        });
    }

    public function down()
    {
        Schema::table('amoriumcms_shop_orders', function (Blueprint $table) {
            $table->dropColumn(['tracking_code', 'tracking_url', 'shipment_status']);
        });
    }
};
