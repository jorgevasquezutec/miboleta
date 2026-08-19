<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            // Marca de "esta sesión es una impersonación", en la fila del
            // refresh token y no solo en el name del access token.
            //
            // El motivo es que la cookie access_token se emite con maxAge =
            // ACCESS_TOKEN_EXPIRY, el MISMO minuto en que caduca el token que
            // contiene: cuando el frontend recibe el 401 y llama a /refresh,
            // el navegador ya la descartó y solo manda refresh_token. Si la
            // propagación de la marca dependiera de esa cookie, cada refresh
            // real la perdería —la sesión seguiría viva pero la auditoría
            // dejaría de registrar al root— y encima caería en el
            // tokens()->delete() que mata la sesión real del empleado.
            //
            // El refresh token SÍ viaja siempre, y es estado de servidor: no
            // se puede falsificar desde el cliente.
            $table->foreignId('impersonator_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropForeign(['impersonator_id']);
            $table->dropColumn('impersonator_id');
        });
    }
};
