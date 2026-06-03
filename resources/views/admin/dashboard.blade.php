@extends('layouts.app-admin')

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="panel-container">
                <div class="panel-header">
                    <h3 class="alta-title">Panel de Administración</h3>
                    <p class="alta-subtitle">
                        Bienvenido {{ optional(auth()->user())->nombre ?? 'Admin' }} aquí podrás gestionar la administración.
                    </p>
                </div>

                <div class="panel-buttons">
                    <a href="{{ route('admin.docentes') }}" class="panel-button">
                        <i class="fas fa-users icon-alineado"></i>
                        Ver docentes
                    </a>
                    <a href="{{ route('admin.centros') }}" class="panel-button">
                        <i class="fas fa-building icon-alineado"></i>
                        Ver centros
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection