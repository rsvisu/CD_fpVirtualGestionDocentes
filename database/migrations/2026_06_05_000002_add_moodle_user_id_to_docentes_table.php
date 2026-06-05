<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docentes', function (Blueprint $table) {
            // ID numérico del usuario en Moodle, devuelto por core_user_create_users.
            // Se usa en operaciones posteriores (matrícula, suspensión) evitando
            // depender de búsquedas por username que pueden fallar por permisos o caché.
            $table->unsignedInteger('moodle_user_id')->nullable()->after('fecha_procesado');
        });
    }

    public function down(): void
    {
        Schema::table('docentes', function (Blueprint $table) {
            $table->dropColumn('moodle_user_id');
        });
    }
};
