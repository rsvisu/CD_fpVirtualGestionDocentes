<?php

// app/Models/Docente.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    use HasFactory;

    // Especificamos qué campos son asignables masivamente
    protected $fillable = [
        'dni', 'nombre', 'apellido', 'email_virtual', 'formacion',
        'de_baja', 'is_procesado', 'fecha_procesado', 'moodle_user_id',
    ];

    protected $casts = [
        'de_baja'        => 'boolean',
        'is_procesado'   => 'boolean',
        'fecha_procesado' => 'datetime',
    ];


public function centros()
{
    return $this->belongsToMany(Centro::class, 'centro_docente', 'dni', 'id_centro')
                ->withPivot('email');
}


    // En Docente.php

    public function tutor()
    {
        return $this->hasOne(Tutor::class, 'dni', 'dni');
    }

    public function coordinador()
    {
        return $this->hasOne(Coordinador::class, 'dni', 'dni');
    }

    public function docencias()
    {
        return $this->hasMany(Docencia::class, 'dni', 'dni');
    }

    public function modulosImpartidos()
    {
        return $this->hasMany(DocenteCicloModulo::class, 'dni', 'dni');
    }

    // Relación con tutores
    public function tutorados()
    {
        return $this->hasMany(Tutor::class, 'dni', 'dni');
    }

    // Relación con coordinadores
    public function coordinaciones()
    {
        return $this->hasMany(Coordinador::class, 'dni', 'dni');
    }

    public function emailEnCentro($idCentro)
    {
        return CentroDocente::where('dni', $this->dni)
            ->where('id_centro', $idCentro)
            ->value('email');
    }


    // public function centroDocente()
    // {
    //     return $this->belongsTo(CentroDocente::class, 'dni', 'dni');
    // }

}

