<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfWatermarkService
{
    /**
     * Add signature watermark to PDF document
     *
     * @param string $filePath Path relative to storage disk
     * @param array $signatureData Signature information
     * @param string|null $pageSizeKey Tamaño de boleta (a4|a5|a10|letter) del
     *        batch al que pertenece el documento (ítem 36). Selecciona el
     *        sub-array de coordenadas correspondiente en
     *        config('signature.watermark.sizes'). Si es null o no existe en
     *        el config, cae a 'a10' (config('signature.watermark.default_size')),
     *        que es el comportamiento histórico para no romper lotes viejos
     *        sin page_size.
     * @return bool
     */
    public function addSignatureWatermark(string $filePath, array $signatureData, ?string $pageSizeKey = null): bool
    {
        try {
            $disk = Storage::disk('documents');

            if (!$disk->exists($filePath)) {
                Log::error('[PdfWatermarkService] File not found', ['path' => $filePath]);
                return false;
            }

            // Get full path
            $fullPath = $disk->path($filePath);

            // Create temporary file for output
            $tempPath = $fullPath . '.tmp';

            // Initialize FPDI with TCPDF
            $pdf = new Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // Get page count from source PDF
            $pageCount = $pdf->setSourceFile($fullPath);

            // Process all pages
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Import page
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                // Add page with same orientation and size
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);

                // Use imported page
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);

                // Add watermark only on the last page
                if ($pageNo === $pageCount) {
                    $this->addWatermarkToPage($pdf, $signatureData, $size, $pageSizeKey);
                }
            }

            // Save to temporary file
            $pdf->Output($tempPath, 'F');

            // Replace original with watermarked version
            if (file_exists($tempPath)) {
                rename($tempPath, $fullPath);
                Log::info('[PdfWatermarkService] Watermark added successfully', ['path' => $filePath]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('[PdfWatermarkService] Error adding watermark', [
                'path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Clean up temp file if exists
            if (isset($tempPath) && file_exists($tempPath)) {
                unlink($tempPath);
            }

            return false;
        }
    }

    /**
     * Add watermark elements to a page
     *
     * @param Fpdi $pdf
     * @param array $signatureData
     * @param array $pageSize Dimensiones REALES de la página del PDF (width/height/orientation), tal como las reporta FPDI — no confundir con $pageSizeKey.
     * @param string|null $pageSizeKey Tamaño de boleta (a4|a5|a10|letter) que selecciona el sub-array de coordenadas en config('signature.watermark.sizes'). Ver addSignatureWatermark().
     * @return void
     */
    protected function addWatermarkToPage(Fpdi $pdf, array $signatureData, array $pageSize, ?string $pageSizeKey = null): void
    {
        // Disable auto page break to prevent new pages from being created
        $pdf->SetAutoPageBreak(false, 0);

        // Get page dimensions
        $pageWidth = $pageSize['width'];
        $pageHeight = $pageSize['height'];

        // Read placement/size from config (config/signature.php) — adjustable
        // without code changes. Ítem 36: las coordenadas ahora viven en un
        // mapa por tamaño (signature.watermark.sizes.<key>); 'mode' sigue
        // siendo global (no depende del tamaño). Fallback a 'a10' si el
        // tamaño solicitado no existe en el config (defensivo).
        $sizesConfig = config('signature.watermark.sizes', []);
        $defaultSizeKey = config('signature.watermark.default_size', 'a10');
        $resolvedSizeKey = ($pageSizeKey && isset($sizesConfig[$pageSizeKey])) ? $pageSizeKey : $defaultSizeKey;
        $cfg = $sizesConfig[$resolvedSizeKey] ?? [];
        $mode = config('signature.watermark.mode', 'absolute');

        $textWidth = (float) ($cfg['width'] ?? 50);
        $align = $cfg['align'] ?? 'C';
        $nameHeight = (float) ($cfg['name_height'] ?? 8);
        $dateOffsetY = (float) ($cfg['date_offset_y'] ?? 8);
        $nameFontSize = (float) ($cfg['name_font_size'] ?? 12);
        $dateFontSize = (float) ($cfg['date_font_size'] ?? 7);

        if ($mode === 'absolute') {
            // Fixed position (mm from top-left) — fits the boleta's signature box
            $x = (float) ($cfg['x'] ?? ($pageWidth - $textWidth - 10));
            $y = (float) ($cfg['name_y'] ?? ($pageHeight - 33));
        } else {
            // Legacy "auto": bottom-right corner, proportional to page size
            $textWidth = min($textWidth, $pageWidth * 0.30);
            $margin = max(5, 10 * ($pageWidth / 210));
            $x = $pageWidth - $textWidth - $margin;
            $y = $pageHeight - $margin - 18 - 5;
        }

        // Format timestamp
        $timestamp = $signatureData['timestamp'] ?? now()->toISOString();
        $formattedDate = $this->formatTimestamp($timestamp);

        // Get user name for signature
        $userName = $signatureData['user_name'] ?? 'FIRMADO CONFORME';

        // Text only, no fill/background (transparent over the boleta content)
        $pdf->SetTextColor(0, 0, 0);

        // Signature name (cursive font), centered in the block
        $this->addSignatureText($pdf, $userName, $x, $y, $textWidth, $nameHeight, $nameFontSize, $align);

        // Date text (below the name, smaller) — no fill
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', $dateFontSize);
        $pdf->SetXY($x, $y + $dateOffsetY);
        $pdf->Cell($textWidth, 4, $formattedDate, 0, 0, $align, false, '', 0, false, 'T', 'M');
    }

    /**
     * Add signature text with elegant cursive style
     *
     * @param Fpdi $pdf
     * @param string $name
     * @param float $x
     * @param float $y
     * @param float $width
     * @return void
     */
    protected function addSignatureText(Fpdi $pdf, string $name, float $x, float $y, float $width, float $height = 8, float $fontSize = 12, string $align = 'C'): void
    {
        // Load cursive signature font at the configured size
        $this->loadSignatureFont($pdf, false, $fontSize);

        // Auto-shrink so long names always fit inside the block (avoid overflow)
        $stringWidth = $pdf->GetStringWidth($name);
        if ($stringWidth > 0 && $stringWidth > $width) {
            $fitSize = max(5, $fontSize * ($width / $stringWidth) * 0.96);
            $this->loadSignatureFont($pdf, false, $fitSize);
        }

        // Draw the signature text (no fill — transparent background)
        $pdf->SetXY($x, $y);
        $pdf->Cell($width, $height, $name, 0, 0, $align, false, '', 0, false, 'T', 'M');
    }

    /**
     * Load signature font (Segoe Script)
     *
     * @param Fpdi $pdf
     * @param bool $italic Apply italic style
     * @return void
     */
    protected function loadSignatureFont(Fpdi $pdf, bool $italic = false, float $fontSize = 12): void
    {
        $fontPath = resource_path('fonts/segoesc.ttf');
        $tcpdfFontsDir = base_path('vendor/tecnickcom/tcpdf/fonts/');
        $style = $italic ? 'I' : '';

        // Check if font is already converted in TCPDF fonts directory
        if (file_exists($tcpdfFontsDir . 'segoesc.php')) {
            $pdf->SetFont('segoesc', $style, $fontSize);
            return;
        }

        if (file_exists($fontPath)) {
            try {
                // Convert font and save directly to TCPDF fonts directory
                $fontName = \TCPDF_FONTS::addTTFfont($fontPath, 'TrueTypeUnicode', '', 32, $tcpdfFontsDir);

                if ($fontName) {
                    $pdf->SetFont($fontName, $style, $fontSize);
                    return;
                } else {
                    Log::warning('[PdfWatermarkService] addTTFfont returned false', ['fontPath' => $fontPath]);
                }
            } catch (\Exception $e) {
                Log::warning('[PdfWatermarkService] Could not load signature font', [
                    'error' => $e->getMessage(),
                    'fontPath' => $fontPath
                ]);
            }
        } else {
            Log::warning('[PdfWatermarkService] Font file not found', ['fontPath' => $fontPath]);
        }

        // Fallback to Times Italic
        $pdf->SetFont('times', 'I', $fontSize);
    }

    /**
     * Format timestamp for display
     *
     * @param string $timestamp
     * @return string
     */
    protected function formatTimestamp(string $timestamp): string
    {
        try {
            $date = new \DateTime($timestamp);
            // Set timezone to Lima, Peru
            $date->setTimezone(new \DateTimeZone('America/Lima'));
            return $date->format('Y-m-d\TH:i:sP');
        } catch (\Exception $e) {
            return $timestamp;
        }
    }
}
