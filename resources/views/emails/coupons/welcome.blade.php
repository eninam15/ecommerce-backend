<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>¡Bienvenido! Tu cupón te espera</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
        .container { max-width: 600px; margin: 0 auto; background: white; }
        .header { background: #007bff; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .coupon-box { background: #e7f3ff; border: 2px dashed #007bff; padding: 20px; margin: 20px 0; text-align: center; border-radius: 8px; }
        .coupon-code { font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 2px; }
        .discount-value { font-size: 24px; color: #28a745; margin: 10px 0; }
        .btn { display: inline-block; background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 14px; }
        .expires { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>¡Bienvenido a {{ config('app.name') }}!</h1>
            <p>{{ $data['user_name'] }}, estamos emocionados de tenerte con nosotros</p>
        </div>
        
        <div class="content">
            <h2>🎉 ¡Tienes un regalo de bienvenida!</h2>
            <p>Para celebrar tu registro, te damos un cupón especial para tu primera compra:</p>
            
            <div class="coupon-box">
                <div class="coupon-code">{{ $data['coupon_code'] }}</div>
                <div class="discount-value">{{ $data['discount_value'] }}% de descuento</div>
                <p>En tu primera compra</p>
                @if($data['expires_at'])
                <p class="expires">⏰ Válido hasta: {{ \Carbon\Carbon::parse($data['expires_at'])->format('d/m/Y') }}</p>
                @endif
            </div>
            
            <p>Solo copia el código en el checkout y disfruta de tu descuento especial.</p>
            
            <div style="text-align: center;">
                <a href="{{ config('app.frontend_url') }}/products" class="btn">¡Empezar a Comprar!</a>
            </div>
            
            <hr style="margin: 30px 0;">
            
            <h3>¿Por qué elegirnos?</h3>
            <ul>
                <li>✅ Productos de alta calidad</li>
                <li>✅ Envío rápido y seguro</li>
                <li>✅ Atención al cliente excepcional</li>
                <li>✅ Garantía de satisfacción</li>
            </ul>
        </div>
        
        <div class="footer">
            <p>Este es un correo automático, por favor no responder.</p>
            <p>Si tienes dudas, contáctanos en {{ config('mail.from.address') }}</p>
            <p>© {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
            
            @if(isset($tracking_url))
            <img src="{{ $tracking_url }}" width="1" height="1" style="display:none;">
            @endif
        </div>
    </div>
</body>
</html>

{{-- 
OTROS TEMPLATES PARA CREAR:

resources/views/emails/coupons/abandoned-cart.blade.php
resources/views/emails/coupons/expiring-soon.blade.php  
resources/views/emails/coupons/birthday.blade.php
resources/views/emails/coupons/loyalty-reward.blade.php
resources/views/emails/coupons/seasonal.blade.php
resources/views/emails/coupons/generic.blade.php

Todos seguirían una estructura similar con variaciones en contenido y diseño.
--}}