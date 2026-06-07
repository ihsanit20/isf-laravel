@extends('prints.layout')

@section('title', 'Pickup Hub Dispatch List — '.$event['title'])

@section('content')
    <header class="section">
        <h1>Pickup Hub Dispatch List</h1>
        <p class="meta">
            Event: <strong>{{ $event['title'] }}</strong>
            @if($event['expected_delivery_date'])
                · Delivery: {{ $event['expected_delivery_date'] }}
            @endif
            · Filter: {{ $status_label }}
            · Generated: {{ $generated_at }}
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
                    · Contact: {{ $section['pickup_point']['contact_person'] }}
                @endif
                @if($section['pickup_point']['phone'])
                    · {{ $section['pickup_point']['phone'] }}
                @endif
            </p>
            <p class="meta">
                Orders: {{ $section['order_count'] }} · Total due: Tk. {{ $section['total_due_amount'] }}
            </p>

            @if(count($section['orders']) === 0)
                <p class="muted">No orders at this hub.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Order No.</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Packages</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['orders'] as $order)
                            <tr>
                                <td>{{ $order['order_number'] }}</td>
                                <td>{{ $order['customer_name'] }}</td>
                                <td>{{ $order['customer_phone'] }}</td>
                                <td>{{ $order['items_label'] ?: '—' }}</td>
                                <td class="text-right">Tk. {{ $order['total_amount'] }}</td>
                                <td class="text-right">Tk. {{ $order['due_amount'] }}</td>
                                <td>{{ $order['status_label'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endforeach
@endsection
