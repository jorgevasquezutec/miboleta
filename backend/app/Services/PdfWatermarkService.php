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

        // Watermark configuration - simple text without box
        $textWidth = 50;
        $margin = 10;
        $totalTextHeight = 10; // Total height needed for both lines

        // Position: bottom right corner with enough space for both lines
        $x = $pageWidth - $textWidth - $margin;
        $y = $pageHeight - $margin - $totalTextHeight;

        // Format timestamp
        $timestamp = $signatureData['timestamp'] ?? now()->toISOString();
        $formattedDate = $this->formatTimestamp($timestamp);

        // Set text color to black
        $pdf->SetTextColor(0, 0, 0);

        // Add "FIRMADO CONFORME" text (top line, bold)
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($x, $y);
        $pdf->Cell($textWidth, 4, 'FIRMADO CONFORME', 0, 0, 'C', false, '', 0, false, 'T', 'M');

        // Add date text (bottom line, smaller)
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetXY($x, $y + 5);
        $pdf->Cell($textWidth, 4, $formattedDate, 0, 0, 'C', false, '', 0, false, 'T', 'M');
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
