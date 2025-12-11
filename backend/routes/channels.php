<?php

use App\Models\DocumentBatch;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Aquí se definen las rutas de autorización para canales broadcast.
| Los canales privados requieren autorización del usuario.
|
*/

// Canal privado del usuario (para notificaciones personales)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado del tenant (para admins del tenant)
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    // El usuario debe pertenecer al tenant y ser admin o root
    $role = $user->getCurrentRole();

    if ($role === 'root') {
        return true;
    }

    if ($role === 'admin') {
        return $user->tenants->contains('id', $tenantId);
    }

    return false;
});

// Canal privado de batch específico (para seguimiento en tiempo real)
Broadcast::channel('batch.{batchId}', function ($user, $batchId) {
    $role = $user->getCurrentRole();

    if ($role === 'client') {
        return false;
    }

    // Verificar que el batch pertenece a un tenant del usuario
    $batch = DocumentBatch::find($batchId);

    if (!$batch) {
        return false;
    }

    if ($role === 'root') {
        return true;
    }

    return $user->tenants->contains('id', $batch->tenant_id);
});
