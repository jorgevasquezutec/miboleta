<?php

namespace App\Jobs;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use App\Services\TenantMailerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public string $temporaryPassword
    ) {
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     *
     * El mailer del tenant se resuelve AQUÍ, dentro del handle() que ya
     * corre en el worker, no al momento de encolar este job. WelcomeUserMail
     * implementa ShouldQueue, así que TenantMailerService::send() usa
     * sendNow() para entregarlo de forma síncrona en este mismo proceso
     * (ver docblock de TenantMailerService) en vez de volver a encolarlo.
     */
    public function handle(TenantMailerService $tenantMailerService): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('[SendWelcomeEmailJob] User not found', [
                'user_id' => $this->userId,
            ]);
            return;
        }

        try {
            $tenant = $user->primaryTenant();

            $tenantMailerService->send($tenant, $user->email, new WelcomeUserMail($user, $this->temporaryPassword));

            Log::info('[SendWelcomeEmailJob] Welcome email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_id' => $tenant?->id,
            ]);
        } catch (\Exception $e) {
            Log::error('[SendWelcomeEmailJob] Failed to send welcome email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
