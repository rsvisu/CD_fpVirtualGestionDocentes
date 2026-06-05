<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            // Identificador numérico del centro en Moodle (campo "description" de la
            // categoría raíz del centro, ej: "22002491"). Se usa para construir el
            // shortname de los cursos: {moodle_codigo}-{id_ciclo}-{id_modulo}
            $table->string('moodle_codigo')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->dropColumn('moodle_codigo');
        });
    }
};
