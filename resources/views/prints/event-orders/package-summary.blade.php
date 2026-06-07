@extends('prints.layout')

@section('title', 'Packing Summary — '.$event['title'])

@section('content')
    <header class="section">
        <h1>Package Packing Summary</h1>
        <p class="meta">
            Event: <strong>{{ $event['title'] }}</strong>
            @if($event['expected_delivery_date'])
                · Delivery: {{ $event['expected_delivery_date'] }}
            @endif
            · Based on confirmed orders
            · Generated: {{ $generated_at }}
        </p>
    </header>

    @if(count($packages) === 0)
        <p class="muted">No packages in confirmed orders.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th class="text-center">Confirmed Orders</th>
                    <th class="text-center">Total Packs</th>
                    <th>Total Quantity</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @foreach($packages as $pkg)
                    <tr>
                        <td>{{ $pkg['name'] }}</td>
                        <td class="text-center">{{ $pkg['order_count'] }}</td>
                        <td class="text-center">{{ $pkg['pack_count'] }}</td>
                        <td>{{ $pkg['physical_label'] ?? '—' }}</td>
                        <td>{{ $pkg['pack_line_label'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
