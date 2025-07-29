<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('type'); // 'reserve', 'release', 'reduce', 'restock', 'adjustment'
            $table->string('reason'); // 'cart_add', 'order_create', 'payment_confirm', 'order_cancel', 'return', 'manual'
            $table->integer('quantity'); // Puede ser negativo para reducciones
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->uuid('reference_id')->nullable(); // cart_id, order_id, etc.
            $table->string('reference_type')->nullable(); // 'cart', 'order', 'manual'
            $table->timestamp('expires_at')->nullable(); // Para reservas temporales
            $table->uuid('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['product_id', 'type']);
            $table->index(['reference_id', 'reference_type']);
            $table->index('expires_at');
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('cart_id')->nullable();
            $table->uuid('order_id')->nullable();
            $table->integer('quantity');
            $table->string('status'); // 'active', 'confirmed', 'expired', 'released'
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cart_id')->references('id')->on('carts')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            
            $table->index(['product_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_movements');
    }
};