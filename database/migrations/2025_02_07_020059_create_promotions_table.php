<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePromotionsTable extends Migration
{
    public function up()
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // 'discount', 'combo', 'seasonal'
            $table->string('discount_type'); // 'percentage', 'fixed'
            $table->decimal('discount_value', 10, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('status')->default(true);
            $table->integer('min_quantity')->nullable();
            $table->integer('max_quantity')->nullable();
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });

        Schema::create('promotion_product', function (Blueprint $table) {
            $table->uuid('promotion_id');
            $table->uuid('product_id');
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->integer('quantity_required')->default(1);
            $table->timestamps();

            $table->primary(['promotion_id', 'product_id']);
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('promotion_product');
        Schema::dropIfExists('promotions');
    }
}