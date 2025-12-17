<?php

namespace App\Jobs;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     */
    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('[SendWelcomeEmailJob] User not found', [
                'user_id' => $this->userId,
            ]);
            return;
        }

        try {
            Mail::to($user->email)->send(new WelcomeUserMail($user, $this->temporaryPassword));
            
            Log::info('[SendWelcomeEmailJob] Welcome email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
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
