<?php

namespace App\Console\Commands;

use App\Services\PdfWatermarkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestWatermark extends Command
{
    protected $signature = 'watermark:test {file_path? : Relative path to PDF file in documents disk}';

    protected $description = 'Test watermark functionality on a PDF file';

    public function handle(PdfWatermarkService $watermarkService): int
    {
        $filePath = $this->argument('file_path');

        // If no file provided, list available files
        if (!$filePath) {
            $this->info('Available PDF files:');
            $files = Storage::disk('documents')->allFiles();
            $pdfFiles = array_filter($files, fn($f) => str_ends_with($f, '.pdf'));

            if (empty($pdfFiles)) {
                $this->error('No PDF files found in documents disk');
                return 1;
            }

            foreach (array_slice($pdfFiles, 0, 10) as $index => $file) {
                $this->line("  [{$index}] {$file}");
            }

            $filePath = $this->ask('Enter the file path or number');

            // If user entered a number, get the file from array
            if (is_numeric($filePath) && isset($pdfFiles[(int) $filePath])) {
                $filePath = array_values($pdfFiles)[(int) $filePath];
            }
        }

        // Check if file exists
        if (!Storage::disk('documents')->exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        // Create a backup first
        $backupPath = $filePath . '.backup';
        Storage::disk('documents')->copy($filePath, $backupPath);
        $this->info("Backup created: {$backupPath}");

        // Test signature data
        $signatureData = [
            'timestamp' => now()->toISOString(),
            'user_id' => 1,
            'user_name' => 'Luis Alberto Bermudo Cosar',
            'ip' => '127.0.0.1',
            'verification_method' => 'test',
        ];

        $this->info("Applying watermark to: {$filePath}");

        $result = $watermarkService->addSignatureWatermark($filePath, $signatureData);

        if ($result) {
            $this->info('✅ Watermark applied successfully!');
            $this->info('Check the PDF file to verify the watermark.');
            $this->line('To restore original: mv ' . Storage::disk('documents')->path($backupPath) . ' ' . Storage::disk('documents')->path($filePath));
        } else {
            $this->error('❌ Failed to apply watermark');
            // Restore backup
            Storage::disk('documents')->move($backupPath, $filePath);
            $this->info('Original file restored from backup');
        }

        return $result ? 0 : 1;
    }
}
