<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Orden</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .email-container {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }
        .content {
            padding: 30px 20px;
        }
        .order-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .order-items {
            margin: 20px 0;
        }
        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .item:last-child {
            border-bottom: none;
        }
        .item-details {
            flex: 1;
        }
        .item-name {
            font-weight: 600;
            color: #333;
        }
        .item-quantity {
            color: #666;
            font-size: 14px;
        }
        .item-price {
            font-weight: 600;
            color: #28a745;
        }
        .totals {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
        }
        .total-row.final {
            font-weight: bold;
            font-size: 18px;
            color: #28a745;
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            margin-top: 15px;
        }
        .shipping-info {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
        }
        .footer {
            background-color: #343a40;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        .support-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>¡Gracias por tu Compra!</h1>
            <p>Tu orden #{{ $order->id }} ha sido confirmada</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p>Hola <strong>{{ $user->name }}</strong>,</p>
            
            <p>¡Excelente! Hemos recibido tu orden y la hemos confirmado. A continuación encontrarás todos los detalles:</p>

            <!-- Order Info -->
            <div class="order-info">
                <h3>📋 Información de la Orden</h3>
                <p><strong>Número de Orden:</strong> #{{ $order->id }}</p>
                <p><strong>Fecha:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</p>
                <p><strong>Estado:</strong> {{ $order->status->label() }}</p>
                @if($coupon)
                    <p><strong>Cupón Aplicado:</strong> {{ $coupon->code }} (-{{ $coupon->discount_type === 'percentage' ? $coupon->discount_value.'%' : '$'.number_format($coupon->discount_value, 2) }})</p>
                @endif
            </div>

            <!-- Order Items -->
            <div class="order-items">
                <h3>🛍️ Productos Ordenados</h3>
                @foreach($items as $item)
                    <div class="item">
                        <div class="item-details">
                            <div class="item-name">{{ $item->product->name }}</div>
                            <div class="item-quantity">Cantidad: {{ $item->quantity }}</div>
                        </div>
                        <div class="item-price">${{ number_format($item->subtotal, 2) }}</div>
                    </div>
                @endforeach
            </div>

            <!-- Totals -->
            <div class="totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>${{ number_format($order->subtotal, 2) }}</span>
                </div>
                @if($order->tax > 0)
                    <div class="total-row">
                        <span>Impuestos:</span>
                        <span>${{ number_format($order->tax, 2) }}</span>
                    </div>
                @endif
                @if($order->shipping_cost > 0)
                    <div class="total-row">
                        <span>Envío:</span>
                        <span>${{ number_format($order->shipping_cost, 2) }}</span>
                    </div>
                @endif
                @if($coupon && $order->coupon_discount > 0)
                    <div class="total-row" style="color: #28a745;">
                        <span>Descuento ({{ $coupon->code }}):</span>
                        <span>-${{ number_format($order->coupon_discount, 2) }}</span>
                    </div>
                @endif
                <div class="total-row final">
                    <span>Total:</span>
                    <span>${{ number_format($order->total, 2) }}</span>
                </div>
            </div>

            <!-- Shipping Address -->
            @if($shippingAddress)
                <div class="shipping-info">
                    <h3>🚚 Dirección de Envío</h3>
                    <p>
                        {{ $shippingAddress->first_name }} {{ $shippingAddress->last_name }}<br>
                        {{ $shippingAddress->address_line_1 }}<br>
                        @if($shippingAddress->address_line_2)
                            {{ $shippingAddress->address_line_2 }}<br>
                        @endif
                        {{ $shippingAddress->city }}, {{ $shippingAddress->state }} {{ $shippingAddress->postal_code }}<br>
                        {{ $shippingAddress->country }}
                        @if($shippingAddress->phone)
                            <br>Tel: {{ $shippingAddress->phone }}
                        @endif
                    </p>
                </div>
            @endif

            <!-- Track Order Button -->
            <div style="text-align: center;">
                <a href="{{ $trackingUrl }}" class="button">📦 Seguir mi Orden</a>
            </div>

            <!-- Support Info -->
            <div class="support-info">
                <h4>💡 ¿Necesitas Ayuda?</h4>
                <p>Si tienes alguna pregunta sobre tu orden, no dudes en contactarnos:</p>
                <p><strong>Email:</strong> {{ $supportEmail }}</p>
            </div>

            <p>¡Gracias por confiar en nosotros! Te mantendremos informado sobre el estado de tu orden.</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
            <p>Este es un email automático, por favor no respondas a este mensaje.</p>
        </div>
    </div>
</body>
</html>