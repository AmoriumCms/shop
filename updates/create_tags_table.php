<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Tags group products for the product sections, like "New season" or "Campaign".
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('amoriumcms_shop_tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 64)->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('amoriumcms_shop_tag_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('tag_id')->unsigned();
            $table->string('locale', 16);
            $table->string('name');
            $table->unique(['tag_id', 'locale'], 'shop_tag_trans_unique');
        });

        Schema::create('amoriumcms_shop_group_tag', function (Blueprint $table) {
            $table->integer('tag_id')->unsigned();
            $table->integer('group_id')->unsigned()->index();
            $table->primary(['tag_id', 'group_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('amoriumcms_shop_group_tag');
        Schema::dropIfExists('amoriumcms_shop_tag_translations');
        Schema::dropIfExists('amoriumcms_shop_tags');
    }
};
