<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar si la tabla notifications ya existe
        if (!Schema::hasTable('notifications')) {
            // Si no existe, crearla completamente
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable'); // Esto crea notifiable_type, notifiable_id y su índice
                $table->json('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index('type');
                $table->index('read_at');
            });
        } else {
            // Si existe, modificarla cuidadosamente
            Schema::table('notifications', function (Blueprint $table) {
                // Agregar columnas que no existan
                if (!Schema::hasColumn('notifications', 'type')) {
                    $table->string('type')->after('id');
                }
                
                // Solo agregar columnas morphs si no existen
                if (!Schema::hasColumn('notifications', 'notifiable_type')) {
                    $table->string('notifiable_type');
                }
                
                if (!Schema::hasColumn('notifications', 'notifiable_id')) {
                    $table->string('notifiable_id');
                }
                
                if (!Schema::hasColumn('notifications', 'data')) {
                    $table->json('data');
                }
                
                if (!Schema::hasColumn('notifications', 'read_at')) {
                    $table->timestamp('read_at')->nullable();
                }
            });
            
            // Crear índices solo si no existen
            $this->createIndexIfNotExists('notifications', ['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
            $this->createIndexIfNotExists('notifications', ['type'], 'notifications_type_index');
            $this->createIndexIfNotExists('notifications', ['read_at'], 'notifications_read_at_index');
        }

        // Crear notification_preferences
        if (!Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('channel'); // 'email', 'sms', 'push', 'in_app'
                $table->string('type'); // 'coupon_notifications', 'order_updates', etc.
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['user_id', 'channel', 'type']);
            });
        }

        // Crear coupon_notifications
        if (!Schema::hasTable('coupon_notifications')) {
            Schema::create('coupon_notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('coupon_id')->nullable();
                $table->string('type'); // 'welcome', 'abandoned_cart', 'expiring_soon', 'birthday', etc.
                $table->string('channel'); // 'email', 'sms', 'push'
                $table->string('status'); // 'pending', 'sent', 'failed', 'clicked'
                $table->json('data')->nullable(); // Datos adicionales
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('clicked_at')->nullable();
                $table->string('failure_reason')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
                
                $table->index(['user_id', 'type']);
                $table->index(['status', 'scheduled_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_notifications');
        Schema::dropIfExists('notification_preferences');
        // Solo eliminar notifications si no es la tabla original de Laravel
        // Schema::dropIfExists('notifications'); // Comentado por seguridad
    }

    /**
     * Crear índice solo si no existe
     */
    private function createIndexIfNotExists(string $table, array $columns, string $indexName): void
    {
        $indexExists = DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
            [$table, $indexName]
        );

        if (empty($indexExists)) {
            $columnList = implode(', ', array_map(fn($col) => '"' . $col . '"', $columns));
            DB::statement("CREATE INDEX \"{$indexName}\" ON \"{$table}\" ({$columnList})");
        }
    }
};