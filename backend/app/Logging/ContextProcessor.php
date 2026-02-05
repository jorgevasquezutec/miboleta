<?php

namespace App\Logging;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class ContextProcessor implements ProcessorInterface
{
    /**
     * Add application context to log records.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        // Request ID for tracing
        $extra['request_id'] = request()?->header('X-Request-ID') ?? Str::uuid()->toString();

        // User context
        if (Auth::check()) {
            $user = Auth::user();
            $extra['user_id'] = $user->id;
            $extra['user_email'] = $user->email;
            $extra['user_role'] = $user->getCurrentRole();
        }

        // Tenant context
        $tenantIds = request()?->header('X-Tenant-Ids');
        if ($tenantIds) {
            $extra['tenant_ids'] = $tenantIds;
        }

        // Request context
        if (request()) {
            $extra['ip'] = request()->ip();
            $extra['method'] = request()->method();
            $extra['url'] = request()->fullUrl();
            $extra['user_agent'] = request()->userAgent();
        }

        // Environment
        $extra['environment'] = config('app.env');
        $extra['app_name'] = config('app.name');

        return $record->with(extra: $extra);
    }
}
