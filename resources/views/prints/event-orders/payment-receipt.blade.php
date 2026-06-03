@extends('prints.layout')

@section('title', 'পেমেন্ট রিসিপ্ট — '.$payment['reference'])

@section('content')
    <header class="section">
        <h1>পেমেন্ট রিসিপ্ট</h1>
        <p class="meta">
            ইভেন্ট: <strong>{{ $event['title'] }}</strong>
            @if($event['expected_delivery_date'])
                · প্রত্যাশিত ডেলিভারি: {{ $event['expected_delivery_date'] }}
            @endif
            · তৈরি: {{ $generated_at }}
        </p>
    </header>

    <section class="section">
        <table class="no-border">
            <tr>
                <th style="width: 140px;">রিসিপ্ট নং</th>
                <td><strong>{{ $payment['reference'] }}</strong></td>
            </tr>
            <tr>
                <th>অর্ডার নং</th>
                <td>{{ $order['order_number'] }}</td>
            </tr>
            <tr>
                <th>গ্রাহক</th>
                <td>{{ $order['customer_name'] }}</td>
            </tr>
            <tr>
                <th>মোবাইল</th>
                <td>{{ $order['customer_phone'] }}</td>
            </tr>
        </table>
    </section>

    <section class="section">
        <h2>পেমেন্ট বিবরণ</h2>
        <table>
            <tbody>
                <tr>
                    <th style="width: 140px;">পরিমাণ</th>
                    <td><strong>৳ {{ $payment['amount'] }}</strong></td>
                </tr>
                <tr>
                    <th>ধরন</th>
                    <td>{{ $payment['payment_type_label'] }}</td>
                </tr>
                <tr>
                    <th>মাধ্যম</th>
                    <td>{{ $payment['payment_method'] ?? '—' }}</td>
                </tr>
                @if($payment['transaction_reference'])
                    <tr>
                        <th>ট্রানজেকশন রেফ</th>
                        <td>{{ $payment['transaction_reference'] }}</td>
                    </tr>
                @endif
                @if($payment['paid_at'])
                    <tr>
                        <th>পরিশোধের তারিখ</th>
                        <td>{{ $payment['paid_at'] }}</td>
                    </tr>
                @endif
                @if($payment['verified_at'])
                    <tr>
                        <th>যাচাইকৃত</th>
                        <td>{{ $payment['verified_at'] }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </section>

    <section class="section">
        <h2>অর্ডার সারাংশ</h2>
        <table class="no-border">
            <tr>
                <th style="width: 140px;">মোট</th>
                <td>৳ {{ $order['total_amount'] }}</td>
            </tr>
            <tr>
                <th>পরিশোধিত (verified)</th>
                <td>৳ {{ $order['verified_paid_amount'] }}</td>
            </tr>
            <tr>
                <th>বাকি</th>
                <td><strong>৳ {{ $order['due_amount'] }}</strong></td>
            </tr>
        </table>
    </section>
@endsection
