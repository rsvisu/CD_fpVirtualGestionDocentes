<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/establecerCoordinadorTutorDocencia.css') }}">
    @endpush
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="panel">
                <h3 class="title">Establecer Tutor/es</h3>
                <p class="subtitle">Complete el siguiente formulario para establecer o borrar un Tutor.</p>

                

                <!-- Formulario -->
                <form method="POST" action="{{ route('establecer_tutor.store') }}" class="form">
                    
                    @if(session('success'))
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i>
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('warning'))
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            {{ session('warning') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-error">
                            <strong><i class="fas fa-exclamation-circle mr-2"></i>Error:</strong>
                            <ul class="error-list">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @csrf

                    <!-- Seleccionar Ciclo -->
                    <div class="form-group">
                        <label for="id_ciclo" class="label">
                            <i class="fas fa-graduation-cap mr-1"></i>Seleccionar Ciclo:
                        </label>
                        <select name="id_ciclo" id="id_ciclo" required class="select @error('id_ciclo') input-error @enderror">
                            <option value="">-- Selecciona un ciclo --</option>
                            @foreach ($ciclos as $ciclo)
                                <option value="{{ $ciclo->id_ciclo }}">{{ $ciclo->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Docentes -->
                    <div class="form-group">
                        <label for="dni" class="label">
                            <i class="fas fa-chalkboard-teacher mr-1"></i>Seleccionar tutor:
                        </label>

                        <select name="dni" id="dni" required class="select @error('dni') input-error @enderror">
                        <option value="">-- Selecciona un docente --</option>
                        @foreach ($docentes as $docente)
                            <option value="{{ $docente->dni }}">{{ $docente->nombre }} {{ $docente->apellido }} - {{ $docente->dni }}</option>
                        @endforeach
                    </select>
                    </div>
                    
                    <!-- Botones -->
                    <div class="form-actions">
                        <button type="submit" class="button button-primary">
                            <i class="fas fa-user-plus mr-2"></i> Establecer
                        </button>
                    </div>
                </form>
                <br>
                <!-- Tabla de tutores actuales -->
                <h4 class="table-title">
                    <i class="fas fa-list-ol mr-2"></i> Listado de Tutores Actuales
                </h4>

                <div class="table-container" 
                    x-data="{ 
                        search: '', 
                        count: {{ count($tutores) }},
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
                                placeholder="Buscar tutores..."
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
                            Mostrando <span x-text="count"></span> de {{ count($tutores) }} tutores
                        </div>

                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="{{ route('establecer_tutor.index', ['sort' => 'nombre']) }}">
                                        <i class="fas fa-user mr-1"></i> Nombre
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('establecer_tutor.index', ['sort' => 'apellido']) }}">
                                        <i class="fas fa-user-tag mr-1"></i> Apellidos
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('establecer_tutor.index', ['sort' => 'ciclo']) }}">
                                        <i class="fas fa-graduation-cap mr-1"></i> Ciclo
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('establecer_tutor.index', ['sort' => 'dni']) }}">
                                        <i class="fas fa-id-card mr-1"></i> DNI
                                    </a>
                                </th>
                                <th><i class="fas fa-cog mr-1"></i> Acción</th>
                            </tr>
                        </thead>
                        <tbody x-ref="tableBody">
                            @forelse($tutores as $tutor)
                                <tr x-data="{ showModal: false }"
                                x-show="
                                (
                                    '{{ strtolower($tutor->docente->nombre . ' ' . $tutor->docente->apellido . ' ' . $tutor->ciclo->nombre . ' ' . $tutor->dni) }}'
                                ).includes(search.toLowerCase())
                                ">
                                    <td>{{ $tutor->docente->nombre ?? 'No encontrado' }}</td>
                                    <td>{{ $tutor->docente->apellido ?? 'No encontrado' }}</td>
                                    <td>{{ $tutor->ciclo->nombre }}</td>
                                    <td class="uppercase">{{ $tutor->dni }}</td>
                                    <td>
                                        <button @click="showModal = true" class="button-tiny button-danger">
                                            <i class="fas fa-trash-alt mr-1"></i> Borrar
                                        </button>

                                        <!-- Modal -->
                                        <div x-show="showModal" class="modal">
                                            <div class="modal-content">
                                                <h2 class="modal-title">
                                                    <i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i>
                                                    Confirmar eliminación
                                                </h2>
                                                <p class="modal-text">
                                                    ¿Estás seguro de que quieres borrar a
                                                    <b>{{ $tutor->docente->nombre }} {{ $tutor->docente->apellido }}</b>
                                                    del ciclo <b>{{ $tutor->ciclo->nombre }}</b>?
                                                </p>
                                                
                                                @php
                                                    $esCoordinador = \App\Models\Coordinador::where('id_centro', $tutor->id_centro)
                                                                ->where('id_ciclo', $tutor->id_ciclo)
                                                                ->where('dni', $tutor->dni)
                                                                ->exists();
                                                @endphp
                                                
                                                @if($esCoordinador)
                                                <div class="modal-warning mt-4">
                                                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                                                    Este tutor también es coordinador de este ciclo.
                                                </div>
                                                @endif
                                                
                                                <div class="modal-actions">
                                                    <button @click="showModal = false" class="button button-secondary  w-64">
                                                        <i class="fas fa-times mr-1"></i> Cancelar
                                                    </button>

                                                    <div class="flex flex-col space-y-2">
                                                        <form method="POST" action="{{ route('tutor.destroy', $tutor->id) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="button button-danger  w-64">
                                                                <i class="fas fa-check mr-1"></i> &nbsp;&nbsp;Sí, borrar solo como tutor
                                                            </button>
                                                        </form>

                                                        @if($esCoordinador)
                                                        <form method="POST" action="{{ route('tutor.destroy', $tutor->id) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="eliminar_coordinador" value="1">
                                                            <button type="submit" class="button button-danger w-64">
                                                                <i class="fas fa-check-double mr-1"></i> Sí, borrar como tutor y coordinador
                                                            </button>

                                                        </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="table-empty">
                                        <i class="fas fa-info-circle mr-2"></i> No hay tutores registrados
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Botón Volver -->
                <div class="back-button">
                    <a href="{{ route('dashboard') }}" class="button button-secondary">
                        <i class="fas fa-arrow-left mr-2"></i> Volver al panel
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
