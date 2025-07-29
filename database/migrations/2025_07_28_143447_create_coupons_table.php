<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // Código del cupón (ej: SAVE20)
            $table->string('name'); // Nombre descriptivo
            $table->text('description')->nullable();
            $table->string('type'); // 'percentage', 'fixed_amount', 'free_shipping', 'category_discount'
            $table->decimal('discount_value', 10, 2)->nullable(); // Valor del descuento
            $table->decimal('minimum_amount', 10, 2)->nullable(); // Compra mínima requerida
            $table->decimal('maximum_discount', 10, 2)->nullable(); // Descuento máximo (para porcentajes)
            $table->integer('usage_limit')->nullable(); // Límite total de usos
            $table->integer('usage_limit_per_user')->nullable(); // Límite por usuario
            $table->integer('used_count')->default(0); // Veces usado
            $table->boolean('first_purchase_only')->default(false); // Solo primera compra
            $table->boolean('status')->default(true); // Activo/Inactivo
            $table->timestamp('starts_at')->nullable(); // Fecha de inicio
            $table->timestamp('expires_at')->nullable(); // Fecha de expiración
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            
            $table->index(['code', 'status']);
            $table->index(['type', 'status']);
            $table->index(['starts_at', 'expires_at']);
        });

        Schema::create('coupon_categories', function (Blueprint $table) {
            $table->uuid('coupon_id');
            $table->uuid('category_id');
            $table->timestamps();

            $table->primary(['coupon_id', 'category_id']);
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });

        Schema::create('coupon_products', function (Blueprint $table) {
            $table->uuid('coupon_id');
            $table->uuid('product_id');
            $table->timestamps();

            $table->primary(['coupon_id', 'product_id']);
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('coupon_id');
            $table->uuid('user_id');
            $table->uuid('order_id');
            $table->decimal('discount_amount', 10, 2); // Descuento aplicado
            $table->timestamps();

            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            
            $table->index(['coupon_id', 'user_id']);
            $table->index('order_id');
        });

        // Agregar campos a la tabla orders para cupones
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('coupon_id')->nullable()->after('shipping_address_id');
            $table->string('coupon_code')->nullable()->after('coupon_id');
            $table->decimal('coupon_discount', 10, 2)->default(0)->after('coupon_code');
            
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
        });

        // Agregar campos a la tabla carts para cupones aplicados
        Schema::table('carts', function (Blueprint $table) {
            $table->uuid('coupon_id')->nullable()->after('total');
            $table->string('coupon_code')->nullable()->after('coupon_id');
            $table->decimal('coupon_discount', 10, 2)->default(0)->after('coupon_code');
            $table->decimal('subtotal', 10, 2)->default(0)->after('coupon_discount');
            
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['coupon_id', 'coupon_code', 'coupon_discount', 'subtotal']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['coupon_id', 'coupon_code', 'coupon_discount']);
        });

        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupon_products');
        Schema::dropIfExists('coupon_categories');
        Schema::dropIfExists('coupons');
    }
};