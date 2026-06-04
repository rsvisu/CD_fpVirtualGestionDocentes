<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */


public function run()
{
    DB::table('usuario')->insert([
        [
            'id_centro' => '50020125',
            'nombre' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' =>  Hash::make('12345678'),
            'is_admin' => true,

        ],
        [
            'id_centro' => '22002491',
            'nombre'    => '22002491',
            'email'     => 'cpifpmontearagon@educa.aragon.es',
            'password'  => Hash::make('22002491'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '22002521',
            'nombre'    => '22002521',
            'email'     => 'iessguhuesca@educa.aragon.es',
            'password'  => Hash::make('22002521'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '22004611',
            'nombre'    => '22004611',
            'email'     => 'iesmvbarbastro@educa.aragon.es',
            'password'  => Hash::make('22004611'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '22010712',
            'nombre'    => '22010712',
            'email'     => 'cpifppiramide@educa.aragon.es',
            'password'  => Hash::make('22010712'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '44003028',
            'nombre'    => '44003028',
            'email'     => 'ifpeteruel@educa.aragon.es',
            'password'  => Hash::make('44003028'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '44003211',
            'nombre'    => '44003211',
            'email'     => 'iessemteruel@educa.aragon.es',
            'password'  => Hash::make('44003211'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '44003235',
            'nombre'    => '44003235',
            'email'     => 'iesvtteruel@educa.aragon.es',
            'password'  => Hash::make('44003235'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '44010537',
            'nombre'    => '44010537',
            'email'     => 'cpifpbajoaragon@educa.aragon.es',
            'password'  => Hash::make('44010537'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '50008460',
            'nombre'    => '50008460',
            'email'     => 'ieslbuzaragoza@educa.aragon.es',
            'password'  => Hash::make('50008460'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '50008642',
            'nombre'    => '50008642',
            'email'     => 'iesmmozaragoza@educa.aragon.es',
            'password'  => Hash::make('50008642'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '50009348',
            'nombre'    => '50009348',
            'email'     => 'iesavempace@educa.aragon.es',
            'password'  => Hash::make('50009348'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '50009567',
            'nombre'    => '50009567',
            'email'     => 'iesrgazaragoza@educa.aragon.es',
            'password'  => Hash::make('50009567'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '50010144',
            'nombre'    => '50010144',
            'email'     => 'iespsezaragoza@educa.aragon.es',
            'password'  => Hash::make('50010144'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '50010156',
            'nombre'    => '50010156',
            'email'     => 'iesmirzaragoza@educa.aragon.es',
            'password'  => Hash::make('50010156'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '50010314',
            'nombre'    => '50010314',
            'email'     => 'cpilosenlaces@educa.aragon.es',
            'password'  => Hash::make('50010314'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '50010511',
            'nombre'    => '50010511',
            'email'     => 'iestiemposmodernos@educa.aragon.es',
            'password'  => Hash::make('50010511'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '50018829',
            'nombre'    => '50018829',
            'email'     => 'cpifpcorona@educa.aragon.es',
            'password'  => Hash::make('50018829'),
            'is_admin'  => false,
        ],
        [
            'id_centro' => '50020125',
            'nombre'    => '50020125',
            'email'     => 'campusdigital@aragon.es',
            'password'  => Hash::make('50020125'),
            'is_admin'  => false,
        ],

    ]);
}

}
