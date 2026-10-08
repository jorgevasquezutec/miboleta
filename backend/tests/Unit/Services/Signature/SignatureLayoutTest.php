<?php

namespace Tests\Unit\Services\Signature;

use App\Services\PdfWatermarkService;
use App\Services\Signature\SignatureLayout;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class SignatureLayoutTest extends TestCase
{
    public function test_detecta_los_cuatro_formatos(): void
    {
        $this->assertSame('a10', SignatureLayout::detectSizeKey(210, 148));
        $this->assertSame('a4', SignatureLayout::detectSizeKey(210, 297));
        $this->assertSame('a5', SignatureLayout::detectSizeKey(148, 210));
        $this->assertSame('letter', SignatureLayout::detectSizeKey(215.9, 279.4));
    }

    public function test_tolerancia_y_desconocido(): void
    {
        $this->assertSame('a4', SignatureLayout::detectSizeKey(209.8, 296.3));
        $this->assertSame('a4', SignatureLayout::detectSizeKey(214, 300));
        $this->assertSame('a10', SignatureLayout::detectSizeKey(400, 400)); // desconocido -> default
        $this->assertSame('a10', SignatureLayout::detectSizeKey(100, 100));
    }

    public function test_fpdi_sin_page_size_detecta_a4_y_dibuja_abajo(): void
    {
        Storage::fake('documents');
        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage('P', 'A4');
        $pdf->Cell(50, 10, 'boleta');
        Storage::disk('documents')->put('a4.pdf', $pdf->Output('', 'S'));

        $service = new class extends PdfWatermarkService {
            public ?float $nameY = null;

            protected function addSignatureText(Fpdi $pdf, string $name, float $x, float $y, float $width, float $height = 8, float $fontSize = 12, string $align = 'C'): void
            {
                $this->nameY = $y;
            }
        };

        $this->assertTrue($service->addSignatureWatermark('a4.pdf', ['user_name' => 'Ana', 'timestamp' => now()->toISOString()], null));
        $this->assertEquals(238.8, $service->nameY);
    }
}
