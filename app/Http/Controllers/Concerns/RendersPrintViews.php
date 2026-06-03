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
            $pdf = Pdf::loadView($view, $data)->setPaper('a4');

            $this->registerBengaliFont($pdf);

            return $pdf->download($filename);
        }

        return view($view, $data);
    }

    private function registerBengaliFont(\Barryvdh\DomPDF\PDF $pdf): void
    {
        $fontPath = public_path('fonts/NotoSansBengali-Regular.ttf');

        if (! is_file($fontPath)) {
            return;
        }

        $fontDir = storage_path('fonts');

        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0755, true);
        }

        $dompdf = $pdf->getDomPDF();
        $options = $dompdf->getOptions();
        $options->setChroot(public_path());
        $options->setFontDir($fontDir);
        $options->setFontCache($fontDir);

        $dompdf->getFontMetrics()->registerFont([
            'family' => 'noto sans bengali',
            'style' => 'normal',
            'weight' => 'normal',
        ], $fontPath);
    }
}
