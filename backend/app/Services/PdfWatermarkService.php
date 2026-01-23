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
     * @return bool
     */
    public function addSignatureWatermark(string $filePath, array $signatureData): bool
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
                    $this->addWatermarkToPage($pdf, $signatureData, $size);
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
     * @param array $pageSize
     * @return void
     */
    protected function addWatermarkToPage(Fpdi $pdf, array $signatureData, array $pageSize): void
    {
        // Disable auto page break to prevent new pages from being created
        $pdf->SetAutoPageBreak(false, 0);

        // Get page dimensions
        $pageWidth = $pageSize['width'];
        $pageHeight = $pageSize['height'];

        // Watermark configuration
        $textWidth = 60;
        $margin = 10;
        $totalTextHeight = 18; // Height needed for signature + date

        // Position: bottom right corner
        $x = $pageWidth - $textWidth - $margin;
        $y = $pageHeight - $margin - $totalTextHeight - 5; // Slightly raised to avoid page edge

        // Format timestamp
        $timestamp = $signatureData['timestamp'] ?? now()->toISOString();
        $formattedDate = $this->formatTimestamp($timestamp);

        // Get user name for signature
        $userName = $signatureData['user_name'] ?? 'FIRMADO CONFORME';

        // Set text color to black
        $pdf->SetTextColor(0, 0, 0);

        // Add signature name with italic slant effect (simulating handwritten signature)
        $this->addSignatureText($pdf, $userName, $x, $y, $textWidth);

        // Add date text (below signature, smaller, black)
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetXY($x, $y + 6);
        $pdf->Cell($textWidth, 4, $formattedDate, 0, 0, 'C', false, '', 0, false, 'T', 'M');
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
    protected function addSignatureText(Fpdi $pdf, string $name, float $x, float $y, float $width): void
    {
        // Load cursive signature font
        $this->loadSignatureFont($pdf);

        // Draw the signature text centered
        $pdf->SetXY($x, $y);
        $pdf->Cell($width, 8, $name, 0, 0, 'C', false, '', 0, false, 'T', 'M');
    }

    /**
     * Load signature font (Segoe Script)
     *
     * @param Fpdi $pdf
     * @param bool $italic Apply italic style
     * @return void
     */
    protected function loadSignatureFont(Fpdi $pdf, bool $italic = false): void
    {
        $fontPath = resource_path('fonts/segoesc.ttf');
        $tcpdfFontsDir = base_path('vendor/tecnickcom/tcpdf/fonts/');
        $style = $italic ? 'I' : '';

        // Check if font is already converted in TCPDF fonts directory
        if (file_exists($tcpdfFontsDir . 'segoesc.php')) {
            $pdf->SetFont('segoesc', $style, 12);
            return;
        }

        if (file_exists($fontPath)) {
            try {
                // Convert font and save directly to TCPDF fonts directory
                $fontName = \TCPDF_FONTS::addTTFfont($fontPath, 'TrueTypeUnicode', '', 32, $tcpdfFontsDir);

                if ($fontName) {
                    $pdf->SetFont($fontName, $style, 12);
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
        $pdf->SetFont('times', 'I', 11);
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
