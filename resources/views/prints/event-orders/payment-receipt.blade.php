@extends('prints.layout')

@section('title', 'Payment Receipt — '.$payment['reference'])

@section('content')
    <header class="section">
        <h1>Payment Receipt</h1>
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
                <th style="width: 140px;">Receipt No.</th>
                <td><strong>{{ $payment['reference'] }}</strong></td>
            </tr>
            <tr>
                <th>Order No.</th>
                <td>{{ $order['order_number'] }}</td>
            </tr>
            <tr>
                <th>Customer</th>
                <td>{{ $order['customer_name'] }}</td>
            </tr>
            <tr>
                <th>Phone</th>
                <td>{{ $order['customer_phone'] }}</td>
            </tr>
        </table>
    </section>

    <section class="section">
        <h2>Payment Details</h2>
        <table>
            <tbody>
                <tr>
                    <th style="width: 140px;">Amount</th>
                    <td><strong>Tk. {{ $payment['amount'] }}</strong></td>
                </tr>
                <tr>
                    <th>Type</th>
                    <td>{{ $payment['payment_type_label'] }}</td>
                </tr>
                <tr>
                    <th>Method</th>
                    <td>{{ $payment['payment_method'] ?? '—' }}</td>
                </tr>
                @if($payment['transaction_reference'])
                    <tr>
                        <th>Transaction ref.</th>
                        <td>{{ $payment['transaction_reference'] }}</td>
                    </tr>
                @endif
                @if($payment['paid_at'])
                    <tr>
                        <th>Paid at</th>
                        <td>{{ $payment['paid_at'] }}</td>
                    </tr>
                @endif
                @if($payment['verified_at'])
                    <tr>
                        <th>Verified at</th>
                        <td>{{ $payment['verified_at'] }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </section>

    <section class="section">
        <h2>Order Summary</h2>
        <table class="no-border">
            <tr>
                <th style="width: 140px;">Total</th>
                <td>Tk. {{ $order['total_amount'] }}</td>
            </tr>
            <tr>
                <th>Paid (verified)</th>
                <td>Tk. {{ $order['verified_paid_amount'] }}</td>
            </tr>
            <tr>
                <th>Due</th>
                <td><strong>Tk. {{ $order['due_amount'] }}</strong></td>
            </tr>
        </table>
    </section>
@endsection
