<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'প্রিন্ট')</title>
    <style>
        @unless(request()->query('download') === 'pdf')
            @font-face {
                font-family: 'Noto Sans Bengali';
                font-style: normal;
                font-weight: 400;
                src: url('{{ asset('fonts/NotoSansBengali-Regular.ttf') }}') format('truetype');
            }
        @endunless

        @if(request()->query('download') === 'pdf')
            h1, h2, h3, th, strong, b {
                font-weight: normal;
            }
        @endif

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'noto sans bengali', 'Noto Sans Bengali', 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.45;
            color: #111;
            margin: 0;
            padding: 24px;
        }

        h1, h2, h3 {
            margin: 0 0 8px;
            line-height: 1.25;
        }

        h1 { font-size: 20px; }
        h2 { font-size: 16px; margin-top: 20px; }
        h3 { font-size: 14px; }

        .muted { color: #555; }
        .meta { font-size: 11px; color: #666; margin-bottom: 16px; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            font-weight: normal;
        }

        @if(request()->query('download') !== 'pdf')
            th {
                font-weight: 600;
            }
        @endif

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .no-border td, .no-border th { border: none; padding: 4px 0; }

        .toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 8px 14px;
            border: 1px solid #333;
            background: #fff;
            color: #111;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
        }

        .btn-primary {
            background: #111;
            color: #fff;
            border-color: #111;
        }

        .page-break { page-break-before: always; }
        .section { margin-bottom: 24px; }

        @media print {
            body { padding: 0; }
            .toolbar { display: none !important; }
            .page-break { page-break-before: always; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @unless(request()->query('download') === 'pdf')
        <div class="toolbar">
            <button type="button" class="btn btn-primary" onclick="window.print()">প্রিন্ট করুন</button>
            @isset($pdf_download_url)
                <a href="{{ $pdf_download_url }}" class="btn">PDF ডাউনলোড</a>
            @endisset
        </div>
    @endunless

    @yield('content')
</body>
</html>
