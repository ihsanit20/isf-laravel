<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Print')</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
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
            font-weight: 600;
        }

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
            <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            @isset($pdf_download_url)
                <a href="{{ $pdf_download_url }}" class="btn">Download PDF</a>
            @endisset
        </div>
    @endunless

    @yield('content')
</body>
</html>
