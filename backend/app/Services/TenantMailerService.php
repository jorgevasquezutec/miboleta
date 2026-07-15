<?php

namespace App\Services;

use App\Models\PlatformSettings;
use App\Models\Tenant;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Resuelve y envía correos usando el servidor SMTP propio de una empresa
 * (tenant), con fallback transparente al mailer/from por defecto de la
 * plataforma (config/mail.php) cuando la empresa no tiene uno configurado
 * o la resolución falla por cualquier motivo.
 *
 * IMPORTANTE (los 9 Mailables de App\Mail implementan ShouldQueue): el
 * mailer dinámico se registra vía config() en el proceso PHP actual. Ese
 * registro NO viaja a otro proceso. Además, Illuminate\Mail\Mailer::send()
 * comprueba `$mailable instanceof ShouldQueue` y, si es así, NO envía nada:
 * llama a `$mailable->mailer($nombre)->queue(...)`, es decir vuelve a
 * encolar el propio Mailable (esta vez con el nombre del mailer ya fijado
 * como string). Ese job encolado puede ejecutarse en un worker distinto al
 * que registró `mail.mailers.tenant_{id}` en runtime, reproduciendo el
 * error "Mailer [tenant_x] is not defined".
 *
 * Por eso send() usa sendNow() en vez de send(): sendNow() ignora el
 * ShouldQueue del Mailable y entrega el correo de forma síncrona, en el
 * mismo proceso donde se acaba de registrar la config del mailer del
 * tenant. Este servicio está pensado para invocarse:
 *   - dentro del handle() de un job que ya corre en el worker en
 *     background (App\Jobs\SendWelcomeEmailJob, SendBatchNotifications), o
 *   - directamente desde un servicio síncrono del ciclo de request
 *     (VacationService, SignatureService) cuando el envío es puntual y no
 *     justifica un job dedicado; en ese caso el envío se vuelve síncrono
 *     (bloquea la respuesta HTTP el tiempo del handshake SMTP) en vez de
 *     quedar encolado de forma transparente como ocurría antes.
 *
 * NUNCA llames a Mail::mailer($resolved['mailer'])->send($mailable)
 * directamente con el nombre resuelto aquí desde otro proceso (por ejemplo
 * guardando el nombre y usándolo más tarde en un job distinto): el mailer
 * dinámico solo existe en el proceso que llamó a resolveMailer()/send().
 */
class TenantMailerService
{
    /**
     * Prefijo usado para nombrar los mailers dinámicos registrados en
     * runtime (config('mail.mailers.tenant_{id}')).
     */
    protected const MAILER_PREFIX = 'tenant_';

    /**
     * Nombre del mailer dinámico registrado en runtime para el SMTP global
     * de la plataforma (config('mail.mailers.platform_global')).
     */
    protected const PLATFORM_MAILER = 'platform_global';

    /**
     * Resuelve el mailer y el remitente (from) a usar para un tenant.
     *
     * Precedencia: si el tenant tiene servidor propio configurado
     * (Tenant::hasCustomMailer()), se usa ese; en cualquier otro caso (tenant
     * null o sin SMTP propio) se cae al default de plataforma, que a su vez
     * prefiere el SMTP global de PlatformSettings y, si no está configurado,
     * el mailer/from de .env/config/mail.php (ver platformDefault()).
     *
     * @param Tenant|null $tenant
     * @return array{mailer: string, from: array{address: string, name: string}}
     */
    public function resolveMailer(?Tenant $tenant): array
    {
        if (!$tenant || !$tenant->hasCustomMailer()) {
            return $this->platformDefault();
        }

        $platformFrom = $this->envFrom();

        try {
            $mailerName = self::MAILER_PREFIX . $tenant->id;
            $encryption = $tenant->mail_encryption ? strtolower($tenant->mail_encryption) : null;

            config(["mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                // Laravel 12 resuelve el transporte SMTP por "scheme":
                // 'smtps' = TLS implícito (típicamente puerto 465),
                // 'smtp' = negociación oportunista de STARTTLS (587/25).
                'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'host' => $tenant->mail_host,
                'port' => $tenant->mail_port ?? 587,
                'username' => $tenant->mail_username,
                'password' => $tenant->mail_password,
                'encryption' => $encryption,
                'timeout' => null,
            ]]);

            return [
                'mailer' => $mailerName,
                'from' => [
                    'address' => $tenant->mail_from_address ?: $platformFrom['address'],
                    'name' => $tenant->mail_from_name ?: ($tenant->name ?: $platformFrom['name']),
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('[TenantMailerService] No se pudo registrar el mailer del tenant, usando el mailer por defecto', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return $this->platformDefault();
        }
    }

    /**
     * Resuelve el mailer/from por defecto de la plataforma con la precedencia:
     * SMTP global de PlatformSettings -> .env/config/mail.php.
     *
     * Si root configuró un SMTP global (PlatformSettings::hasCustomMailer()),
     * lo registra en runtime bajo el nombre "platform_global" y lo devuelve;
     * si no, o si el registro falla por cualquier motivo, cae al mailer/from
     * de .env (config('mail.default') + config('mail.from.*')).
     *
     * @return array{mailer: string, from: array{address: string, name: string}}
     */
    protected function platformDefault(): array
    {
        $envDefault = [
            'mailer' => config('mail.default'),
            'from' => $this->envFrom(),
        ];

        try {
            $settings = PlatformSettings::current();
        } catch (\Throwable $e) {
            // Sin tabla/fila disponible (p. ej. migración no corrida): usar .env.
            return $envDefault;
        }

        if (!$settings->hasCustomMailer()) {
            return $envDefault;
        }

        try {
            $encryption = $settings->mail_encryption ? strtolower($settings->mail_encryption) : null;

            config(["mail.mailers." . self::PLATFORM_MAILER => [
                'transport' => 'smtp',
                'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'host' => $settings->mail_host,
                'port' => $settings->mail_port ?? 587,
                'username' => $settings->mail_username,
                'password' => $settings->mail_password,
                'encryption' => $encryption,
                'timeout' => null,
            ]]);

            return [
                'mailer' => self::PLATFORM_MAILER,
                'from' => [
                    'address' => $settings->mail_from_address ?: $envDefault['from']['address'],
                    'name' => $settings->mail_from_name ?: $envDefault['from']['name'],
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('[TenantMailerService] No se pudo registrar el SMTP global, usando el mailer por defecto de .env', [
                'error' => $e->getMessage(),
            ]);

            return $envDefault;
        }
    }

    /**
     * Remitente (from) por defecto definido en .env/config/mail.php.
     *
     * @return array{address: string, name: string}
     */
    protected function envFrom(): array
    {
        return [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ];
    }

    /**
     * Envía un Mailable usando el mailer del tenant (o el de la plataforma
     * si el tenant es null o no tiene configuración propia).
     *
     * Aplica el "from" resuelto al mailable antes de enviarlo, para que el
     * remitente coincida con el mailer usado. Usa sendNow() a propósito
     * (ver docblock de la clase): evita que Mailer::send() vuelva a encolar
     * el Mailable (todos implementan ShouldQueue) con un nombre de mailer
     * que no existiría en el proceso que finalmente lo procese.
     *
     * @param Tenant|null $tenant
     * @param string|array<int, string> $to
     * @param Mailable $mailable
     * @param array<int, string> $cc
     */
    public function send(?Tenant $tenant, string|array $to, Mailable $mailable, array $cc = []): void
    {
        $resolved = $this->resolveMailer($tenant);

        $mailable->from($resolved['from']['address'], $resolved['from']['name']);

        $pendingMail = Mail::mailer($resolved['mailer'])->to($to);

        if (!empty($cc)) {
            $pendingMail->cc($cc);
        }

        $pendingMail->sendNow($mailable);
    }

    /**
     * Prueba una configuración SMTP intentando abrir y cerrar la conexión.
     * Pensado para un futuro botón "Probar conexión" en el formulario de
     * configuración de correo por empresa (endpoint aún no expuesto).
     *
     * @param array{host: string, port?: int, username?: string, password?: string, encryption?: string|null} $config
     * @return bool true si la conexión se estableció correctamente
     */
    public function testConnection(array $config): bool
    {
        $tempMailerName = 'tenant_test_' . uniqid();
        $encryption = isset($config['encryption']) ? strtolower((string) $config['encryption']) : null;

        try {
            config(["mail.mailers.{$tempMailerName}" => array_merge([
                'transport' => 'smtp',
                'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'port' => 587,
                'timeout' => 10,
            ], $config)]);

            $transport = Mail::mailer($tempMailerName)->getSymfonyTransport();
            $transport->start();
            $transport->stop();

            return true;
        } catch (\Throwable $e) {
            Log::warning('[TenantMailerService] testConnection falló', [
                'host' => $config['host'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
