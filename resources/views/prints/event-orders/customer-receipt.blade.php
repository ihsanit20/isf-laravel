@extends('prints.layout')

@section('title', 'Customer Copy — '.$order['order_number'])

@section('content')
    <header class="section">
        <h1>Order Receipt</h1>
        <p class="meta">
            Event: <strong>{{ $event['title'] }}</strong>
            @if($event['expected_delivery_date'])
                · Expected delivery: {{ $event['expected_delivery_date'] }}
            @endif
            · Generated: {{ $generated_at }}
        </p>
    </header>

    <section class="section">
        <table class="no-border">
            <tr>
                <th style="width: 140px;">Order No.</th>
                <td><strong>{{ $order['order_number'] }}</strong></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ $order['status_label'] }}</td>
            </tr>
            <tr>
                <th>Customer</th>
                <td>{{ $order['customer_name'] }}</td>
            </tr>
            <tr>
                <th>Phone</th>
                <td>{{ $order['customer_phone'] }}</td>
            </tr>
            @if($order['customer_address'])
                <tr>
                    <th>Address</th>
                    <td>{{ $order['customer_address'] }}</td>
                </tr>
            @endif
            <tr>
                <th>Order date</th>
                <td>{{ $order['created_at'] ?? '—' }}</td>
            </tr>
            @if($order['confirmed_at'])
                <tr>
                    <th>Confirmed</th>
                    <td>{{ $order['confirmed_at'] }}</td>
                </tr>
            @endif
        </table>
    </section>

    <section class="section">
        <h2>Order Items</h2>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Quantity</th>
                    <th class="text-right">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order['items'] as $item)
                    <tr>
                        <td>{{ $item['package_name'] }}</td>
                        <td>{{ $item['quantity_label'] }}</td>
                        <td class="text-right">Tk. {{ $item['line_total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Total</th>
                    <td class="text-right"><strong>Tk. {{ $order['total_amount'] }}</strong></td>
                </tr>
                <tr>
                    <th colspan="2" class="text-right">Advance (required)</th>
                    <td class="text-right">Tk. {{ $order['advance_amount'] }}</td>
                </tr>
                <tr>
                    <th colspan="2" class="text-right">Paid (verified)</th>
                    <td class="text-right">Tk. {{ $order['verified_paid_amount'] }}</td>
                </tr>
                <tr>
                    <th colspan="2" class="text-right">Due</th>
                    <td class="text-right"><strong>Tk. {{ $order['due_amount'] }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </section>

    @if($order['pickup_point'])
        <section class="section">
            <h2>Pickup Point</h2>
            <p><strong>{{ $order['pickup_point']['name'] }}</strong></p>
            @if($order['pickup_point']['area'])
                <p class="muted">{{ $order['pickup_point']['area'] }}</p>
            @endif
            @if($order['pickup_point']['address'])
                <p class="muted">{{ $order['pickup_point']['address'] }}</p>
            @endif
            @if($order['pickup_point']['contact_person'] || $order['pickup_point']['phone'])
                <p class="muted">
                    {{ $order['pickup_point']['contact_person'] }}
                    @if($order['pickup_point']['phone'])
                        · {{ $order['pickup_point']['phone'] }}
                    @endif
                </p>
            @endif
        </section>
    @endif

    <section class="section">
        <h2>Track Order</h2>
        <p class="muted">Use this link to check order status:</p>
        <p><strong>{{ $order['tracking_url'] }}</strong></p>
    </section>
@endsection
