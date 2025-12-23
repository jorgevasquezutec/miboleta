<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {email? : Email address to send test to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email to verify SMTP configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?? 'jorgeluisvasquez1996@gmail.com';

        $this->info('Sending test email to: ' . $email);
        $this->line('SMTP Configuration:');
        $this->line('  Host: ' . config('mail.mailers.smtp.host'));
        $this->line('  Port: ' . config('mail.mailers.smtp.port'));
        $this->line('  Encryption: ' . config('mail.mailers.smtp.encryption'));
        $this->line('  From: ' . config('mail.from.address'));
        $this->line('');

        try {
            Mail::raw('Este es un correo de prueba desde MiBoleta. Si recibiste este correo, la configuración SMTP está funcionando correctamente.', function ($message) use ($email) {
                $message
                    ->to($email)
                    ->subject('Prueba de Correo - MiBoleta')
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $this->info('✅ Email sent successfully!');
            $this->line('Check your inbox at: ' . $email);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to send email');
            $this->error('Error: ' . $e->getMessage());

            Log::error('[TestEmail] Failed to send test email', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return self::FAILURE;
        }
    }
}
