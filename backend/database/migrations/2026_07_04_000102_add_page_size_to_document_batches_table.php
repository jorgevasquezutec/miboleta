<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ítem 36 del sprint-fix: permite elegir el tamaño de página de las
     * boletas ANTES de la carga masiva, para que la firma (watermark, ver
     * config/signature.php y PdfWatermarkService) se posicione en el lugar
     * correcto según el tamaño real del PDF. 'a10' es el formato calibrado
     * históricamente (comportamiento por defecto, sin cambios) — ver
     * signature.watermark.sizes.a10 en config/signature.php.
     *
     * default('a10') aplica tanto a filas nuevas como, en la mayoría de
     * motores (MySQL/Postgres), a las filas existentes al momento del
     * ALTER TABLE. Aun así, el código que lee este campo (SignatureService)
     * hace un fallback explícito a 'a10' por si algún registro quedara en
     * null (defensivo, no depende del comportamiento del motor de BD).
     */
    public function up(): void
    {
        Schema::table('document_batches', function (Blueprint $table) {
            $table->string('page_size', 10)->nullable()->default('a10')
                ->after('requires_signature')
                ->comment('Tamaño de página de las boletas del lote: a4|a5|a10|letter. a10 = formato calibrado por defecto.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_batches', function (Blueprint $table) {
            $table->dropColumn('page_size');
        });
    }
};
