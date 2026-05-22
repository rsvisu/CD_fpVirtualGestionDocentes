<?php

namespace App\Services;

use App\Models\Docente;

/**
 * GeneradorEmailVirtualService
 *
 * Genera automáticamente el correo @fpvirtualaragon.es de un docente
 * siguiendo el algoritmo acordado:
 *
 *   local = iniciales(nombre) + primer_apellido + inicial(segundo_apellido)
 *   email = strtolower(transliterate(local)) + "@fpvirtualaragon.es"
 *
 * Ejemplos:
 *   "Dario Axel"          + "Ureña Garcia"  → daurenag@fpvirtualaragon.es
 *   "María Del Carmen Mónica" + "Royo Lupón" → mdcmroyol@fpvirtualaragon.es
 *
 * Colisiones: si el email ya existe en BD se añade sufijo numérico (…2, …3, …)
 */
class GeneradorEmailVirtualService
{
    public const DOMINIO = 'fpvirtualaragon.es';

    /**
     * Devuelve el email virtual del docente:
     * - Si ya tiene email_virtual en BD → lo devuelve sin modificar.
     * - Si es nuevo → genera y resuelve colisiones.
     *
     * @param  string      $nombre    Nombre(s) del docente (normalizado, con mayúsculas)
     * @param  string      $apellido  Apellidos separados por espacio
     * @param  string|null $dni       DNI del docente (para buscar si ya existe)
     * @return string                 Email completo @fpvirtualaragon.es
     */
    public function generarOObtenerExistente(string $nombre, string $apellido, ?string $dni = null): string
    {
        // Si el docente ya existe en BD con email_virtual, respetarlo
        if ($dni) {
            $emailExistente = Docente::where('dni', strtoupper($dni))->value('email_virtual');
            if (!empty($emailExistente)) {
                return $emailExistente;
            }
        }

        $base = $this->construirLocalPart($nombre, $apellido);

        return $this->resolverColision($base);
    }

    /**
     * Genera el email sin resolver colisiones — útil para preview en formulario.
     */
    public function previsualizarEmail(string $nombre, string $apellido): string
    {
        return $this->construirLocalPart($nombre, $apellido) . '@' . self::DOMINIO;
    }

    // ── Algoritmo ────────────────────────────────────────────────────────────

    /**
     * Construye la parte local del email:
     *   iniciales(nombre) + primer_apellido + inicial(segundo_apellido)
     *
     * Todo en minúsculas y transliterado (sin acentos, sin ñ, solo [a-z0-9]).
     */
    public function construirLocalPart(string $nombre, string $apellido): string
    {
        // 1. Iniciales de todos los nombres
        $palabrasNombre = preg_split('/\s+/', trim($nombre));
        $iniciales = implode('', array_map(
            fn(string $p) => mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8'),
            array_filter($palabrasNombre, fn($p) => $p !== '')
        ));

        // 2. Primer apellido completo + inicial del segundo (si lo hay)
        $palabrasApellido = preg_split('/\s+/', trim($apellido));
        $primerApellido   = $palabrasApellido[0] ?? '';
        $segundoApellido  = $palabrasApellido[1] ?? null;
        $inicialSegundo   = $segundoApellido
            ? mb_strtoupper(mb_substr($segundoApellido, 0, 1, 'UTF-8'), 'UTF-8')
            : '';

        // 3. Concatenar y normalizar
        $localRaw = $iniciales . $primerApellido . $inicialSegundo;

        return $this->normalizar($localRaw);
    }

    /**
     * Convierte a minúsculas, transliterada a ASCII y elimina chars no válidos.
     */
    public function normalizar(string $str): string
    {
        $str = mb_strtolower($str, 'UTF-8');

        // Mapa de transliteración (vocales acentuadas, ñ, ç, etc.)
        $mapa = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y',
            'ß' => 'ss',
        ];

        $str = strtr($str, $mapa);

        // Eliminar cualquier carácter no alfanumérico
        return preg_replace('/[^a-z0-9]/', '', $str);
    }

    // ── Colisiones ───────────────────────────────────────────────────────────

    /**
     * Comprueba si el email base ya está en uso y añade sufijo numérico si hace falta.
     *
     * Ejemplos:
     *   daurenag  → daurenag@fpvirtualaragon.es  (libre → lo usa)
     *   daurenag  → daurenag2@fpvirtualaragon.es (ocupado → prueba 2, 3, …)
     */
    private function resolverColision(string $base): string
    {
        $email = $base . '@' . self::DOMINIO;

        if (!Docente::where('email_virtual', $email)->exists()) {
            return $email;
        }

        $sufijo = 2;
        do {
            $email = $base . $sufijo . '@' . self::DOMINIO;
            $sufijo++;
        } while (Docente::where('email_virtual', $email)->exists() && $sufijo < 100);

        return $email;
    }
}
