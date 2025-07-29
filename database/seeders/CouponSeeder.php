<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coupon;
use App\Models\Category;
use App\Models\Product;
use App\Enums\CouponType;
use Carbon\Carbon;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        // ID del usuario admin existente
        $adminUserId = '9f07a2ac-6eac-47ae-86c0-4727dffe893e';

        // 1. CUPÓN DE BIENVENIDA (Primera compra)
        $welcomeCoupon = Coupon::create([
            'code' => 'WELCOME20',
            'name' => 'Cupón de Bienvenida',
            'description' => '20% de descuento en tu primera compra. ¡Bienvenido a nuestra tienda!',
            'type' => CouponType::FIRST_PURCHASE->value,
            'discount_value' => 20.00,
            'minimum_amount' => 50.00,
            'maximum_discount' => 100.00,
            'usage_limit' => 1000,
            'usage_limit_per_user' => 1,
            'first_purchase_only' => true,
            'status' => true,
            'starts_at' => now(),
            'expires_at' => now()->addMonths(3),
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId
        ]);

        // 2. CUPÓN DE DESCUENTO FIJO
        $fixedCoupon = Coupon::create([
            'code' => 'SAVE50',
            'name' => 'Ahorra $50',
            'description' => '$50 de descuento en compras mayores a $200',
            'type' => CouponType::FIXED_AMOUNT->value,
            'discount_value' => 50.00,
            'minimum_amount' => 200.00,
            'usage_limit' => 500,
            'usage_limit_per_user' => 3,
            'first_purchase_only' => false,
            'status' => true,
            'starts_at' => now(),
            'expires_at' => now()->addMonths(2),
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId
        ]);

        // 3. CUPÓN DE ENVÍO GRATIS
        $shippingCoupon = Coupon::create([
            'code' => 'FREESHIP',
            'name' => 'Envío Gratis',
            'description' => 'Envío gratis en compras mayores a $100',
            'type' => CouponType::FREE_SHIPPING->value,
            'minimum_amount' => 100.00,
            'usage_limit' => null, // Sin límite
            'usage_limit_per_user' => 5,
            'first_purchase_only' => false,
            'status' => true,
            'starts_at' => now(),
            'expires_at' => now()->addMonths(6),
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId
        ]);

        // 4. CUPÓN POR CATEGORÍA (Ejemplo: Electrónicos)
        $category = Category::where('name', 'like', '%electr%')->first() 
                   ?? Category::first();
        
        if ($category) {
            $categoryCoupon = Coupon::create([
                'code' => 'TECH15',
                'name' => 'Descuento en Tecnología',
                'description' => '15% de descuento en productos de tecnología',
                'type' => CouponType::CATEGORY_DISCOUNT->value,
                'discount_value' => 15.00,
                'minimum_amount' => 75.00,
                'maximum_discount' => 200.00,
                'usage_limit' => 200,
                'usage_limit_per_user' => 2,
                'first_purchase_only' => false,
                'status' => true,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(1),
                'created_by' => $adminUserId,
                'updated_by' => $adminUserId
            ]);

            // Asociar categoría
            $categoryCoupon->categories()->attach($category->id);
        }

        // 5. CUPÓN POR PRODUCTO ESPECÍFICO
        $products = Product::where('status', true)->limit(3)->get();
        
        if ($products->count() > 0) {
            $productCoupon = Coupon::create([
                'code' => 'SPECIAL25',
                'name' => 'Productos Especiales',
                'description' => '25% de descuento en productos seleccionados',
                'type' => CouponType::PRODUCT_DISCOUNT->value,
                'discount_value' => 25.00,
                'maximum_discount' => 150.00,
                'usage_limit' => 100,
                'usage_limit_per_user' => 1,
                'first_purchase_only' => false,
                'status' => true,
                'starts_at' => now(),
                'expires_at' => now()->addWeeks(2),
                'created_by' => $adminUserId,
                'updated_by' => $adminUserId
            ]);

            // Asociar productos
            $productCoupon->products()->attach($products->pluck('id'));
        }

        // 6. CUPÓN DE FIN DE SEMANA
        $weekendCoupon = Coupon::create([
            'code' => 'WEEKEND10',
            'name' => 'Descuento de Fin de Semana',
            'description' => '10% de descuento especial para fin de semana',
            'type' => CouponType::PERCENTAGE->value,
            'discount_value' => 10.00,
            'minimum_amount' => 30.00,
            'maximum_discount' => 50.00,
            'usage_limit' => 300,
            'usage_limit_per_user' => 1,
            'first_purchase_only' => false,
            'status' => true,
            'starts_at' => now()->next('Friday'),
            'expires_at' => now()->next('Sunday')->endOfDay(),
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId
        ]);

        // 7. CUPÓN EXPIRADO (Para testing)
        $expiredCoupon = Coupon::create([
            'code' => 'EXPIRED30',
            'name' => 'Cupón Expirado',
            'description' => 'Este cupón ya expiró - para pruebas',
            'type' => CouponType::PERCENTAGE->value,
            'discount_value' => 30.00,
            'usage_limit' => 100,
            'usage_limit_per_user' => 1,
            'first_purchase_only' => false,
            'status' => true,
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subDays(1),
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId
        ]);

        // 8. CUPÓN AGOTADO (Para testing)
        $exhaustedCoupon = Coupon::create([
            'code' => 'EXHAUSTED',
            'name' => 'Cupón Agotado',
            'description' => 'Cupón con límite agotado - para pruebas',
            'type' => CouponType::FIXED_AMOUNT->value,
            'discount_value' => 25.00,
            'usage_limit' => 5,
            'used_count' => 5, // Ya agotado
            'usage_limit_per_user' => 1,
            'first_purchase_only' => false,
            'status' => true,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId
        ]);

        // 9. CUPÓN VIP (Alto valor)
        $vipCoupon = Coupon::create([
            'code' => 'VIP100',
            'name' => 'Cupón VIP Exclusivo',
            'description' => '$100 de descuento para compras VIP mayores a $500',
            'type' => CouponType::FIXED_AMOUNT->value,
            'discount_value' => 100.00,
            'minimum_amount' => 500.00,
            'usage_limit' => 50,
            'usage_limit_per_user' => 1,
            'first_purchase_only' => false,
            'status' => true,
            'starts_at' => now(),
            'expires_at' => now()->addMonths(12),
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId
        ]);

        // 10. CUPÓN ESTACIONAL (Black Friday)
        $blackFridayCoupon = Coupon::create([
            'code' => 'BLACKFRIDAY40',
            'name' => 'Black Friday Mega Descuento',
            'description' => '40% de descuento especial Black Friday',
            'type' => CouponType::PERCENTAGE->value,
            'discount_value' => 40.00,
            'minimum_amount' => 100.00,
            'maximum_discount' => 500.00,
            'usage_limit' => 1000,
            'usage_limit_per_user' => 2,
            'first_purchase_only' => false,
            'status' => false, // Inactivo hasta la fecha
            'starts_at' => Carbon::create(now()->year, 11, 24), // 24 de noviembre
            'expires_at' => Carbon::create(now()->year, 11, 30), // 30 de noviembre
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId
        ]);

        $this->command->info('✅ Cupones de ejemplo creados exitosamente:');
        $this->command->line('   - WELCOME20 (Bienvenida - 20%)');
        $this->command->line('   - SAVE50 (Descuento fijo - $50)');
        $this->command->line('   - FREESHIP (Envío gratis)');
        $this->command->line('   - TECH15 (Categoría - 15%)');
        $this->command->line('   - SPECIAL25 (Productos - 25%)');
        $this->command->line('   - WEEKEND10 (Fin de semana - 10%)');
        $this->command->line('   - VIP100 (VIP - $100)');
        $this->command->line('   - BLACKFRIDAY40 (Black Friday - 40%)');
        $this->command->line('   - EXPIRED30, EXHAUSTED (Para testing)');
    }
}