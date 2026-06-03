@extends('prints.layout')

@section('title', 'পিকআপ হাব ডিস্প্যাচ লিস্ট — '.$event['title'])

@section('content')
    <header class="section">
        <h1>পিকআপ হাব ডিস্প্যাচ লিস্ট</h1>
        <p class="meta">
            ইভেন্ট: <strong>{{ $event['title'] }}</strong>
            @if($event['expected_delivery_date'])
                · ডেলিভারি: {{ $event['expected_delivery_date'] }}
            @endif
            · ফিল্টার: {{ $status_label }}
            · তৈরি: {{ $generated_at }}
        </p>
    </header>

    @foreach($sections as $index => $section)
        @if($index > 0)
            <div class="page-break"></div>
        @endif

        <section class="section">
            <h2>{{ $section['pickup_point']['name'] }}</h2>
            <p class="muted">
                @if($section['pickup_point']['area'])
                    {{ $section['pickup_point']['area'] }}
                @endif
                @if($section['pickup_point']['address'])
                    · {{ $section['pickup_point']['address'] }}
                @endif
                @if($section['pickup_point']['contact_person'])
                    · যোগাযোগ: {{ $section['pickup_point']['contact_person'] }}
                @endif
                @if($section['pickup_point']['phone'])
                    · {{ $section['pickup_point']['phone'] }}
                @endif
            </p>
            <p class="meta">
                অর্ডার: {{ $section['order_count'] }} · মোট বাকি: ৳ {{ $section['total_due_amount'] }}
            </p>

            @if(count($section['orders']) === 0)
                <p class="muted">এই হাবে কোনো অর্ডার নেই।</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>অর্ডার নং</th>
                            <th>গ্রাহক</th>
                            <th>ফোন</th>
                            <th>প্যাকেজ</th>
                            <th class="text-right">মোট</th>
                            <th class="text-right">বাকি</th>
                            <th>স্ট্যাটাস</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['orders'] as $order)
                            <tr>
                                <td>{{ $order['order_number'] }}</td>
                                <td>{{ $order['customer_name'] }}</td>
                                <td>{{ $order['customer_phone'] }}</td>
                                <td>{{ $order['items_label'] ?: '—' }}</td>
                                <td class="text-right">৳ {{ $order['total_amount'] }}</td>
                                <td class="text-right">৳ {{ $order['due_amount'] }}</td>
                                <td>{{ $order['status_label'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endforeach
@endsection
