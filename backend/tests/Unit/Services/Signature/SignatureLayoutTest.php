<?php

namespace Tests\Unit\Services\Signature;

use App\Services\PdfWatermarkService;
use App\Services\Signature\SignatureLayout;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class SignatureLayoutTest extends TestCase
{
    public function test_detecta_a4_a5_horizontal_y_carta(): void
    {
        $this->assertSame('a5', SignatureLayout::detectSizeKey(210, 148)); // media hoja horizontal
        $this->assertSame('a4', SignatureLayout::detectSizeKey(210, 297));
        $this->assertSame('letter', SignatureLayout::detectSizeKey(215.9, 279.4));
        $this->assertSame('a4', SignatureLayout::detectSizeKey(148, 210)); // A5 vertical ya no es un formato
    }

    public function test_el_tamano_real_gana_y_el_del_lote_es_el_respaldo(): void
    {
        $this->assertSame('a5', SignatureLayout::detectSizeKey(210, 148, 'a4'));
        $this->assertSame('letter', SignatureLayout::detectSizeKey(400, 400, 'letter'));
    }

    public function test_placement_por_formato(): void
    {
        $this->assertSame('footer', SignatureLayout::sidecarLayout('a4')['placement']);
        $this->assertSame('footer', SignatureLayout::sidecarLayout('letter')['placement']);
        $this->assertSame('footer', SignatureLayout::sidecarLayout('a5')['placement']);
        $this->assertEquals(119.0, SignatureLayout::sidecarLayout('a5')['name_y_mm']);
    }

    public function test_tolerancia_y_desconocido(): void
    {
        $this->assertSame('a4', SignatureLayout::detectSizeKey(209.8, 296.3));
        $this->assertSame('a4', SignatureLayout::detectSizeKey(214, 300));
        $this->assertSame('a4', SignatureLayout::detectSizeKey(400, 400)); // desconocido -> default
        $this->assertSame('a4', SignatureLayout::detectSizeKey(100, 100));
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
        $this->assertEquals(268.5, $service->nameY);
    }
}
