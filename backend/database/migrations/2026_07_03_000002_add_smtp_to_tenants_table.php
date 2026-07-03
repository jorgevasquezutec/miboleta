<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('mail_host')->nullable()->after('labor_regime')
                ->comment('Host SMTP propio de la empresa (ej. smtp.office365.com). Null = usa el mailer por defecto de la plataforma.');
            $table->unsignedInteger('mail_port')->nullable()->after('mail_host');
            $table->string('mail_username')->nullable()->after('mail_port');
            $table->text('mail_password')->nullable()->after('mail_username')
                ->comment('Cifrado con el cast "encrypted" de Laravel (encriptación de aplicación, APP_KEY).');
            $table->string('mail_encryption')->nullable()->after('mail_password')
                ->comment('tls o ssl. Determina el "scheme" del transporte SMTP (smtp vs smtps) en Laravel 12.');
            $table->string('mail_from_address')->nullable()->after('mail_encryption');
            $table->string('mail_from_name')->nullable()->after('mail_from_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_from_address',
                'mail_from_name',
            ]);
        });
    }
};
