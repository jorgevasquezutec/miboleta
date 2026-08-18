<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Closure;

/**
 * Unicidad de correo y documento contra usuarios ELIMINADOS.
 *
 * El borrado de usuarios es lógico (SoftDeletes) y los índices
 * `users_email_unique` / `users_document_text_unique` son únicos simples: la
 * fila borrada sigue ocupando el correo y el documento. Pero el usuario
 * eliminado no aparece en ningún listado, así que el administrador veía un
 * "Este email ya está registrado" señalando a un registro invisible.
 *
 * Estas reglas mantienen el bloqueo —reutilizar los datos de un usuario
 * eliminado está fuera de alcance por decisión del cliente— y solo explican el
 * motivo: quién es el usuario eliminado y cuándo se eliminó.
 *
 * No se usa `Rule::unique()->withoutTrashed()`: eso dejaría pasar la validación
 * y el INSERT reventaría contra el índice UNIQUE de la base de datos (500).
 */
trait ValidatesUserIdentityUniqueness
{
    /**
     * Regla de unicidad para un campo de identidad del usuario.
     *
     * @param  string      $column     Columna en `users` ('email' o 'document_text')
     * @param  string      $label      Cómo nombrar el dato en el mensaje ('correo', 'número de documento')
     * @param  string      $duplicate  Mensaje cuando choca con un usuario ACTIVO
     * @param  int|null    $ignoreId   Usuario que se está editando (se excluye de la comprobación)
     */
    protected function uniqueUserIdentityRule(string $column, string $label, string $duplicate, ?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $label, $duplicate, $ignoreId) {
            if ($value === null || $value === '') {
                return;
            }

            $existing = User::withTrashed()
                ->where($column, $value)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->first();

            if (!$existing) {
                return;
            }

            if (!$existing->trashed()) {
                $fail($duplicate);

                return;
            }

            $fullName = trim($existing->name . ' ' . ($existing->last_name ?? ''));
            $deletedAt = $existing->deleted_at?->format('d/m/Y');

            $fail(sprintf(
                'Este %s pertenece a un usuario eliminado (%s, eliminado el %s) y no puede reutilizarse. Usa otro dato o contacta al administrador de la plataforma.',
                $label,
                $fullName !== '' ? $fullName : 'sin nombre',
                $deletedAt ?? 'fecha desconocida'
            ));
        };
    }

    /** Regla para el correo. */
    protected function uniqueEmailRule(?int $ignoreId = null): Closure
    {
        return $this->uniqueUserIdentityRule('email', 'correo', 'Este email ya está registrado', $ignoreId);
    }

    /** Regla para el número de documento. */
    protected function uniqueDocumentRule(?int $ignoreId = null): Closure
    {
        return $this->uniqueUserIdentityRule(
            'document_text',
            'número de documento',
            'Este número de documento ya está registrado',
            $ignoreId
        );
    }
}
