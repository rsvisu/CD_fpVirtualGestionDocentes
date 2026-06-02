<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Nombre de la tabla en la base de datos
     *
     * @var string
     */
    protected $table = 'usuario';  // Laravel busca "usuarios" por defecto, pero nuestra tabla se llama "usuario"

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<string>
     */
    protected $fillable = [
        'nombre',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * Los atributos que deben permanecer ocultos.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Los atributos que deben ser convertidos a otro tipo.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Relación: un usuario pertenece a un centro
     */
    public function centro()
    {
        return $this->belongsTo(Centro::class, 'id_centro', 'id_centro');
    }
    protected function nombre(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                // 1. Quitamos los caracteres prohibidos "º" y "."
                $limpio = str_replace(['º', '.'], '', $value);

                // 2. Ponemos la primera letra de cada palabra en mayúscula
                return Str::title($limpio);
            },
        );
    }
}

