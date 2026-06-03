@extends('prints.layout')

@section('title', 'প্যাকিং সারাংশ — '.$event['title'])

@section('content')
    <header class="section">
        <h1>প্যাকেজ প্যাকিং সারাংশ</h1>
        <p class="meta">
            ইভেন্ট: <strong>{{ $event['title'] }}</strong>
            @if($event['expected_delivery_date'])
                · ডেলিভারি: {{ $event['expected_delivery_date'] }}
            @endif
            · Confirmed অর্ডার অনুযায়ী
            · তৈরি: {{ $generated_at }}
        </p>
    </header>

    @if(count($packages) === 0)
        <p class="muted">Confirmed অর্ডারে কোনো প্যাকেজ নেই।</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>প্যাকেজ</th>
                    <th class="text-center">Confirmed অর্ডার</th>
                    <th class="text-center">মোট প্যাক</th>
                    <th>মোট পরিমাণ</th>
                    <th>বিস্তারিত</th>
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
