@extends('layouts.app-admin')

@section('content')

   @push('styles')
        <link rel="stylesheet" href="{{ asset('css/establecerCoordinadorTutorDocencia.css') }}">
    @endpush
    @push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('docenteModal', () => ({
            showModal: false,
            docenteInfo: {},
            isLoading: false,
            error: null,
            togglingAdmin: false,
            loadDocenteInfo(docenteId) {
                this.isLoading = true;
                this.error = null;
                fetch(`/admin/docentes/${docenteId}/info`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
                    const contentType = response.headers.get("content-type");
                    if (!contentType.includes("application/json")) throw new Error("Respuesta no JSON.");
                    return response.json();
                })
                .then(data => {
                    this.docenteInfo = data;
                    this.showModal = true;
                })
                .catch(error => {
                    console.error('Error al cargar la información del docente:', error);
                    this.error = error.message;
                    alert("No se pudo cargar la información del docente.");
                })
                .finally(() => {
                    this.isLoading = false;
                });
            },
            toggleAdmin(dni) {
                if (this.togglingAdmin) return;
                this.togglingAdmin = true;
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch(`/admin/docentes/${dni}/toggle-admin`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        this.docenteInfo.usuario_is_admin = data.is_admin;
                    } else {
                        alert(data.error ?? 'Error al cambiar el rol.');
                    }
                })
                .catch(() => alert('Error al cambiar el rol.'))
                .finally(() => { this.togglingAdmin = false; });
            }
        }));
    });
</script>
@endpush
<meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="panel">
                <h3 class="title">Gestión de Docentes</h3>
                <p class="subtitle">Listado completo de docentes registrados en el sistema.</p>
                <div class="text-right mb-4">
                    <a href="{{ route('admin.docentes.export.csv') }}" 
                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <i class="fas fa-file-csv mr-2"></i>
                        Exportar docentes a CSV
                    </a>
                </div>
                <!-- Tabla de docentes -->
                <div class="table-container" 
                    x-data="{ 
                        search: '', 
                        count: {{ $docentes ? count($docentes) : 0 }},
                        async updateCount() {
                            await this.$nextTick(); 
                            this.count = Array.from(this.$refs.tableBody.querySelectorAll('tr')).filter(tr => {
                                return tr.style.display !== 'none';
                            }).length;
                        }
                    }"
                    x-init="updateCount(); $watch('search', () => updateCount())"
                    x-ref="tableContainer"
                >
               
                    <!-- Buscador Mejorado -->
                    <div class="search-container">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input
                                x-model="search"
                                type="text"
                                placeholder="Buscar docentes..."
                                class="search-input"
                                @keyup.escape="search = ''"
                            />
                            <button 
                                x-show="search.length > 0"
                                @click="search = ''"
                                class="search-clear"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div x-show="search.length > 0" class="search-count">
                            Mostrando <span x-text="count"></span> de {{ count($docentes) }} docentes
                        </div>                        
                    </div>                   

                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="{{ route('admin.docentes', ['sort' => 'nombre']) }}">
                                        <i class="fas fa-user mr-1"></i> Nombre
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('admin.docentes', ['sort' => 'apellido']) }}">
                                        <i class="fas fa-user-tag mr-1"></i> Apellido
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('admin.docentes', ['sort' => 'dni']) }}">
                                        <i class="fas fa-id-card mr-1"></i> DNI
                                    </a>
                                </th>
                                <th>
                                    <i class="fas fa-chalkboard-teacher mr-1"></i> Es Tutor
                                </th>
                                <th>
                                    <i class="fas fa-user-tie mr-1"></i> Es Coordinador
                                </th>
                                <th>
                                    <i class="fas fa-info-circle mr-1"></i> Más Info
                                </th>
                            </tr>
                        </thead>
                        <tbody x-ref="tableBody">
                            @forelse($docentes as $docente)
                                <tr x-show="
                                (
                                    '{{ strtolower($docente->nombre . ' ' . $docente->apellido . ' ' . $docente->dni) }}'
                                ).includes(search.toLowerCase())
                                ">
                                    <td>{{ $docente->nombre }}</td>
                                    <td>{{ $docente->apellido }}</td>
                                    <td class="uppercase">{{ $docente->dni }}</td>
                                    <td class="text-center">
                                        @if($docente->es_tutor)
                                            <span class="text-green-500"><i class="fas fa-check-circle"></i></span>
                                        @else
                                            <span class="text-red-500"><i class="fas fa-times-circle"></i></span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($docente->es_coordinador)
                                            <span class="text-green-500"><i class="fas fa-check-circle"></i></span>
                                        @else
                                            <span class="text-red-500"><i class="fas fa-times-circle"></i></span>
                                        @endif
                                    </td>
                                    <td>
                                        <button 
                                            @click="$dispatch('open-modal', { docenteId: '{{ $docente->dni }}' })" 
                                            class="button-tiny button-primary"
                                        >
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="table-empty">
                                        <i class="fas fa-info-circle mr-2"></i> No hay docentes registrados
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    
                </div>

                <!-- Modal de información del docente -->
                <div x-data="{
                    docenteInfo: {},
                    showModal: false,
                    isLoading: false,
                    errorMessage: '',
                    loadDocenteInfo(dni) {
                        this.showModal = true;
                        this.isLoading = true;
                        this.errorMessage = '';

                        fetch(`/admin/docentes/${dni}/info`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                            }
                        })
                        .then(async response => {
                            if (!response.ok) {
                                const errorData = await response.json().catch(() => ({}));
                                throw new Error(errorData.message || 'Error al cargar los datos');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.error) throw new Error(data.error);
                            this.docenteInfo = data;
                            this.isLoading = false;
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            this.errorMessage = error.message || 'Error al cargar la información del docente';
                            this.isLoading = false;
                        });
                    }
                }"  x-show="showModal" 
                    x-cloak @click.away="showModal = false"
                    @open-modal.window="loadDocenteInfo($event.detail.docenteId)" 
                    x-transition.opacity
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true"
                    x-bind:aria-hidden="!showModal">
                    
                    <!-- Contenedor principal -->
                    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden relative">
                        <!-- Header -->
                        <div class="flex justify-between items-center bg-indigo-600 px-6 py-4">
                            <h2 class="text-xl font-bold text-white">
                                <i class="fas fa-user-tie mr-2"></i> Información del docente
                            </h2>
                            <button @click="showModal = false" class="text-white hover:text-gray-300 transition-colors"
                                aria-label="Cerrar modal">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>

                        <!-- Contenido -->
                        <div class="overflow-y-auto flex-1 px-6 py-4 space-y-6">
                            <!-- Estado de carga -->
                            <div x-show="isLoading" class="flex justify-center items-center py-12">
                                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-600"></div>
                            </div>
                            
                            <!-- Mensaje de error -->
                            <div x-show="errorMessage" class="bg-red-50 border-l-4 border-red-500 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700" x-text="errorMessage"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Datos del docente -->
                            <template x-if="!isLoading && !errorMessage && Object.keys(docenteInfo).length > 0">
                                <div>
                                    <!-- Tarjeta de información personal -->
                                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                                        <div class="flex flex-col md:flex-row gap-6">
                                            <div class="flex-shrink-0 mx-auto md:mx-0">
                                                <div class="h-24 w-24 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 text-3xl">
                                                    <i class="fas fa-user-tie"></i>
                                                </div>
                                            </div>
                                            
                                            <!-- Datos personales -->
                                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-800 mb-3 pb-2 border-b">Datos personales</h3>
                                                    <div class="space-y-2">
                                                        <p><span class="font-medium text-gray-700">Nombre:</span> <span class="text-gray-600"
                                                                x-text="docenteInfo.nombre + ' ' + docenteInfo.apellido"></span></p>
                                                        <p>
                                                            <span class="font-medium text-gray-700">Email:</span>
                                                            <template x-for="(item, index) in docenteInfo.email" :key="item.email">
                                                                <span>
                                                                    <a 
                                                                        :href="'mailto:' + item.email" 
                                                                        class="text-indigo-600 hover:underline"
                                                                        :title="'Centro: ' + item.centro"
                                                                        x-text="item.email"
                                                                    ></a>
                                                                    <span x-show="index < docenteInfo.email.length - 1"> | </span>
                                                                </span>
                                                            </template>
                                                        </p>         
                                                    </div>
                                                </div>
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-800 mb-3 pb-2 border-b">Identificación</h3>
                                                    <div class="space-y-2">
                                                        <p><span class="font-medium text-gray-700">DNI:</span>
                                                            <template x-for="dni in docenteInfo.dnis" :key="dni">
                                                                <span x-text="dni" class="uppercase mr-2 bg-gray-200 px-2 py-1 rounded text-sm"></span>
                                                            </template>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Módulos por centro -->
                                    <template x-if="docenteInfo.modulos_por_centro?.length">
                                        <section class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                                            <div class="bg-gray-50 px-4 py-3 border-b">
                                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                                    <i class="fas fa-book-open mr-2 text-indigo-600"></i>
                                                    Módulos que imparte
                                                    <span class="ml-auto text-sm font-normal text-gray-500">
                                                        <span 
                                                            x-text="docenteInfo.modulos_por_centro.length === 1 
                                                                ? '1 centro' 
                                                                : docenteInfo.modulos_por_centro.length + ' centros'">
                                                        </span>
                                                    </span>
                                                </h3>
                                            </div>
                                            <div class="divide-y divide-gray-200">
                                                <template x-for="centro in docenteInfo.modulos_por_centro" :key="centro.centro_nombre">
                                                    <div class="p-4">
                                                        <h4 class="font-medium text-indigo-700 mb-3 flex items-center">
                                                            <i class="fas fa-school mr-2"></i>
                                                            <span x-text="centro.centro_nombre"></span>
                                                        </h4>
                                                        <div class="overflow-x-auto">
                                                            <table class="min-w-full divide-y divide-gray-200">
                                                                <thead class="bg-gray-50">
                                                                    <tr>
                                                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ciclo</th>
                                                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Módulo</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="bg-white divide-y divide-gray-200">
                                                                    <template x-for="modulo in centro.modulos" :key="modulo.id_modulo">
                                                                        <tr>
                                                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600" x-text="modulo.ciclo_nombre"></td>
                                                                            <td class="px-4 py-3 text-sm text-gray-600" x-text="modulo.nombre"></td>
                                                                        </tr>
                                                                    </template>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </section>
                                    </template>

                                    <!-- Tutor -->
                                    <template x-if="docenteInfo.tutorias?.length">
                                        <section class="bg-white rounded-lg border border-gray-200 overflow-hidden mt-6">
                                            <div class="bg-gray-50 px-4 py-3 border-b">
                                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                                    <i class="fas fa-chalkboard-teacher mr-2 text-indigo-600"></i>
                                                    Tutor en:                                           
                                                </h3>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Centro</th>
                                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ciclo</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <template x-for="(tutoria, index) in docenteInfo.tutorias" :key="index">
                                                            <tr>
                                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600" x-text="tutoria.centro_nombre"></td>
                                                                <td class="px-4 py-3 text-sm text-gray-600" x-text="tutoria.ciclo_nombre"></td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </section>
                                    </template>

                                    <!-- Coordinador -->
                                    <template x-if="docenteInfo.coordinaciones?.length">
                                        <section class="bg-white rounded-lg border border-gray-200 overflow-hidden mt-6">
                                            <div class="bg-gray-50 px-4 py-3 border-b">
                                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                                    <i class="fas fa-user-tie mr-2 text-indigo-600"></i>
                                                    Coordinador en:                                          
                                                </h3>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Centro</th>
                                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ciclo</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <template x-for="(coordinacion, index) in docenteInfo.coordinaciones" :key="index">
                                                            <tr>
                                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600" x-text="coordinacion.centro_nombre"></td>
                                                                <td class="px-4 py-3 text-sm text-gray-600" x-text="coordinacion.ciclo_nombre"></td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </section>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <!-- Footer -->
                        <div class="flex justify-end bg-gray-100 px-6 py-4 border-t">
                            <button @click="showModal = false"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition-colors flex items-center">
                                <i class="fas fa-times mr-2"></i> Cerrar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Botón Volver -->
                <div class="mt-6 text-center">
                    <a href="{{ route('admin.dashboard') }}"
                        class="inline-flex items-center text-sm font-semibold text-black hover:text-gray-600 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Volver al panel
                    </a>
                    
                </div>
                 
                </div>
            </div>
<script>
   async function exportToCSV() {
    const rows = document.querySelectorAll('tbody tr:not([x-show="false"])');
    let csvContent = "Nombre,Apellido,DNI,Email\n";
    
    // Mostrar indicador de carga
    Alpine.store('isExporting', true);
    
    try {
        for (const row of rows) {
            const columns = row.querySelectorAll('td');
            if (columns.length >= 3) {
                const dni = columns[2].textContent.trim();
                
                // Obtener email via API
                const email = await fetchEmail(dni);
                
                csvContent += `"${columns[0].textContent.trim()}","${columns[1].textContent.trim()}","${dni}","${email}"\n`;
            }
        }
        
        // Descargar archivo
        downloadCSV(csvContent, 'docentes.csv');
    } catch (error) {
        console.error("Error al exportar:", error);
        alert("Ocurrió un error al generar el CSV");
    } finally {
        Alpine.store('isExporting', false);
    }
}

async function fetchEmail(dni) {
    try {
        const response = await fetch(`/docentes/info/${dni}`);
        const data = await response.json();
        
        if (data.email && data.email.length > 0) {
            return data.email.map(e => e.email).join(', ');
        }
        return '';
    } catch (error) {
        console.error(`Error obteniendo email para DNI ${dni}:`, error);
        return '';
    }
}

function downloadCSV(content, filename) {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

@endsection