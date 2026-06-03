@extends('prints.layout')

@section('title', 'কাস্টমার কপি — '.$order['order_number'])

@section('content')
    <header class="section">
        <h1>অর্ডার/বুকিং রিসিপ্ট</h1>
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
                <th style="width: 140px;">অর্ডার নং</th>
                <td><strong>{{ $order['order_number'] }}</strong></td>
            </tr>
            <tr>
                <th>স্ট্যাটাস</th>
                <td>{{ $order['status_label'] }}</td>
            </tr>
            <tr>
                <th>গ্রাহক</th>
                <td>{{ $order['customer_name'] }}</td>
            </tr>
            <tr>
                <th>মোবাইল</th>
                <td>{{ $order['customer_phone'] }}</td>
            </tr>
            @if($order['customer_address'])
                <tr>
                    <th>ঠিকানা</th>
                    <td>{{ $order['customer_address'] }}</td>
                </tr>
            @endif
            <tr>
                <th>অর্ডার তারিখ</th>
                <td>{{ $order['created_at'] ?? '—' }}</td>
            </tr>
            @if($order['confirmed_at'])
                <tr>
                    <th>কনফার্ম</th>
                    <td>{{ $order['confirmed_at'] }}</td>
                </tr>
            @endif
        </table>
    </section>

    <section class="section">
        <h2>অর্ডার আইটেম</h2>
        <table>
            <thead>
                <tr>
                    <th>প্যাকেজ</th>
                    <th>পরিমাণ</th>
                    <th class="text-right">লাইন মোট</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order['items'] as $item)
                    <tr>
                        <td>{{ $item['package_name'] }}</td>
                        <td>{{ $item['quantity_label'] }}</td>
                        <td class="text-right">৳ {{ $item['line_total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right">মোট</th>
                    <td class="text-right"><strong>৳ {{ $order['total_amount'] }}</strong></td>
                </tr>
                <tr>
                    <th colspan="2" class="text-right">অগ্রিম (প্রয়োজন)</th>
                    <td class="text-right">৳ {{ $order['advance_amount'] }}</td>
                </tr>
                <tr>
                    <th colspan="2" class="text-right">পরিশোধিত (verified)</th>
                    <td class="text-right">৳ {{ $order['verified_paid_amount'] }}</td>
                </tr>
                <tr>
                    <th colspan="2" class="text-right">বাকি</th>
                    <td class="text-right"><strong>৳ {{ $order['due_amount'] }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </section>

    @if($order['pickup_point'])
        <section class="section">
            <h2>পিকআপ পয়েন্ট</h2>
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
        <h2>ট্র্যাক করুন</h2>
        <p class="muted">অর্ডার স্ট্যাটাস দেখতে এই লিংক ব্যবহার করুন:</p>
        <p><strong>{{ $order['tracking_url'] }}</strong></p>
    </section>
@endsection
