<?php

namespace App\Http\Controllers\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

trait RendersPrintViews
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderPrint(Request $request, string $view, array $data, string $filename): View|Response
    {
        $data['pdf_download_url'] = $request->fullUrlWithQuery([
            ...$request->query(),
            'download' => 'pdf',
        ]);

        if ($request->query('download') === 'pdf') {
            return Pdf::loadView($view, $data)
                ->setPaper('a4')
                ->download($filename);
        }

        return view($view, $data);
    }
}
