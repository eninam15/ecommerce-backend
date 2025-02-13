<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
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

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->string('code')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('weight', 8, 3)->nullable();
            $table->decimal('volume', 8, 3)->nullable();
            $table->string('flavor')->nullable();
            $table->string('presentation')->nullable();
            $table->integer('stock');
            $table->integer('min_stock')->default(0);
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('featured')->default(false);
            $table->boolean('is_seasonal')->default(false);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();

            $table->json('nutritional_info')->nullable();
            $table->json('ingredients')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('categories');

            // Índices
            $table->index(['category_id']);
            $table->index(['slug']);
            $table->index(['status']);
            $table->index(['sku']);
        });

         // Rendimiento de ventas
         Schema::create('product_sales_performance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');

            // Contadores básicos
            $table->integer('views_count')->default(0);
            $table->integer('times_promoted')->default(0);
            $table->integer('total_units_sold')->default(0);
            $table->integer('review_count')->default(0);
            $table->integer('wishlist_count')->default(0);
            $table->integer('cart_abandonment_count')->default(0);

            // Campos para análisis de precios y promociones
            $table->decimal('regular_price', 10, 2)->after('price');
            $table->decimal('lowest_price_ever', 10, 2)->nullable();
            $table->decimal('highest_price_ever', 10, 2)->nullable();
            $table->timestamp('last_promotion_date')->nullable();
            $table->decimal('average_promotional_price', 10, 2)->nullable();
            $table->integer('total_units_sold_in_promotions')->default(0);
            $table->integer('total_units_sold_regular')->default(0);

            // Campos para análisis de rendimiento
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->decimal('average_units_per_order', 5, 2);
            $table->integer('add_to_cart_count');
            $table->decimal('conversion_rate', 5, 2);
            $table->integer('purchase_count');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');
        });

        // Tabla de Historial de Precios
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->decimal('price', 10, 2);
            $table->timestamp('date_recorded');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        Schema::create('product_associations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('associated_product_id');
            $table->integer('frequency');
            $table->decimal('correlation_score', 4, 3);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('associated_product_id')->references('id')->on('products');

            // Evita duplicados
            $table->unique(['product_id', 'associated_product_id']);
        });

         // Seguimiento promocional
         Schema::create('product_promotional_performance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('promotion_id');
            $table->integer('units_sold');
            $table->decimal('revenue', 12, 2);
            $table->decimal('discount_amount', 10, 2);
            $table->integer('new_customers');
            $table->integer('returning_customers');
            $table->decimal('conversion_rate', 5, 2);
            $table->timestamps();
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('promotion_id')->references('id')->on('promotions');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->integer('order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
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
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_promotional_performance');
        Schema::dropIfExists('product_price_history');
        Schema::dropIfExists('product_associations');
        Schema::dropIfExists('product_sales_performance');
        Schema::dropIfExists('products');
    }
};
