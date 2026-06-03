<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AltaDocenteController;
use App\Http\Controllers\EstablecerCoordinadorController;
use App\Http\Controllers\EstablecerTutorController;
use App\Http\Controllers\EstablecerDocenciaController;
use App\Http\Controllers\BajaDocenteController;
use App\Http\Controllers\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\DocenteController;
use App\Http\Controllers\Admin\CentroController;
use App\Http\Controllers\Admin\AltaPlataformaController;

Route::redirect('/', '/login');

// Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Perfiles
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Docente
Route::middleware(['auth'])->group(function () {
    Route::get('/alta-docente', [AltaDocenteController::class, 'create'])
        ->name('alta_docente');

    Route::post('/alta-docente', [AltaDocenteController::class, 'store'])
        ->name('alta_docente.store');
    Route::get('/comprobar-docente/{dni}', [AltaDocenteController::class, 'comprobarDocente']);
    Route::get('/alta-docente/preview-email', [AltaDocenteController::class, 'previewEmail'])
        ->name('alta_docente.preview_email');

    // Cambiamos a POST para que sea más fácil de usar en botones simples
    Route::post('/docentes/baja/{dni}', [BajaDocenteController::class, 'destroy'])->name('docente.baja');
    Route::post('/docentes/reactivar/{dni}', [BajaDocenteController::class, 'reactivar'])->name('docente.reactivar');
    
    Route::get('/docentes/baja', [BajaDocenteController::class, 'index'])->name('docentes.index');
    Route::delete('/docentes/baja/{dni}', [BajaDocenteController::class, 'destroy'])->name('docentes.destroy');
});

// Coordinador
Route::middleware(['auth'])->group(function () {
    Route::get('/establecer-coordinador', [EstablecerCoordinadorController::class, 'index'])
        ->name('establecer_coordinador.index');

    Route::post('/establecer-coordinador', [EstablecerCoordinadorController::class, 'store'])
        ->name('establecer_coordinador.store');

    Route::delete('/establecer-coordinador/{id}', [EstablecerCoordinadorController::class, 'destroy'])
        ->name('establecer_coordinador.destroy');
});


// Tutor
Route::middleware(['auth'])->group(function () {
    Route::get('/establecer-tutor', [EstablecerTutorController::class, 'index'])
        ->name('establecer_tutor.index');

    Route::post('/establecer-tutor', [EstablecerTutorController::class, 'store'])
        ->name('establecer_tutor.store');

    Route::delete('/tutor/{id}', [EstablecerTutorController::class, 'destroy'])
        ->name('tutor.destroy');
});

// Docencia
Route::middleware(['auth'])->group(function () {
    Route::get('/establecer-docencia', [EstablecerDocenciaController::class, 'index'])
        ->name('establecer_docencia.index');

    Route::post('/establecer-docencia', [EstablecerDocenciaController::class, 'store'])
        ->name('establecer_docencia.store');

    Route::delete('/establecer-docencia/{id}', [EstablecerDocenciaController::class, 'destroy'])
        ->name('establecer_docencia.destroy');

    Route::get('/modulos-por-ciclo/{id}', [EstablecerDocenciaController::class, 'getModulosPorCiclo']);

});


// Admin
Route::middleware('web')->prefix('admin')->name('admin.')->group(function () {
    // Ruta /admin que redirige al dashboard si está autenticado como admin, o al login
    Route::get('/', function () {
        if (auth()->check() && auth()->user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('admin.login');
    })->name('home');

    // Login admin
    Route::get('login', [AdminLoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AdminLoginController::class, 'login'])->name('login.submit');
    Route::post('logout', [AdminLoginController::class, 'logout'])->name('logout');

    // Rutas protegidas para admin (accesibles con login web si el usuario es admin)
    Route::middleware(['auth', 'es_admin'])->group(function () {
        Route::get('dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');

        Route::get('docentes', [DocenteController::class, 'index'])->name('docentes');
        Route::get('docentes/{dni}/info', [DocenteController::class, 'info'])->name('docentes.info');
        Route::get('docentes/exportar-csv', [DocenteController::class, 'exportDocentesCSV'])
            ->name('docentes.export.csv');

        Route::get('centros', [CentroController::class, 'index'])->name('centros');
        Route::get('centros/{id_centro}/info', [CentroController::class, 'info'])->name('centros.info');
        Route::get('centros/exportar-csv', [CentroController::class, 'exportCentrosCSV'])
            ->name('centros.export.csv');

        Route::get('alta-plataforma', [AltaPlataformaController::class, 'index'])
            ->name('alta-plataforma');
        Route::get('alta-plataforma/{id}/preview', [AltaPlataformaController::class, 'preview']);
        Route::post('alta-plataforma/procesar', [AltaPlataformaController::class, 'procesarAltas'])
            ->name('alta-plataforma.procesar');
    });
});


require __DIR__.'/auth.php';

