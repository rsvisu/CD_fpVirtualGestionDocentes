<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/establecerCoordinadorTutorDocencia.css') }}">
    @endpush

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="panel">
                <h3 class="title">Establecer Coordinador/es</h3>
                <p class="subtitle">Complete el siguiente formulario para establecer o borrar un coordinador.</p>

                <!-- Formulario -->
                <form method="POST" action="{{ route('establecer_coordinador.store') }}" class="form">
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

                    <div class="form-group">
                        <label for="dni" class="label">
                            <i class="fas fa-user-tie mr-1"></i> Seleccionar Coordinador:
                        </label>
                        <select name="dni" id="dni" required class="select @error('dni') input-error @enderror">
                            <option value="">-- Selecciona un docente --</option>
                            @foreach ($docentes as $docente)
                                <option value="{{ $docente->dni }}" {{ (session('docente_dni') == $docente->dni || old('dni') == $docente->dni) ? 'selected' : '' }}>{{ $docente->nombre }} {{ $docente->apellido }} - {{ $docente->dni }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_ciclo" class="label">
                            <i class="fas fa-graduation-cap mr-1"></i> Seleccionar Ciclo:
                        </label>
                        <select name="id_ciclo" id="id_ciclo" required class="select @error('id_ciclo') input-error @enderror">
                            <option value="">-- Selecciona un ciclo --</option>
                            @foreach ($ciclos as $ciclo)
                                <option value="{{ $ciclo->id_ciclo }}">{{ $ciclo->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="checkbox-group">
                        <input type="hidden" name="es_tutor" value="0">
                        <input type="checkbox" name="es_tutor" id="es_tutor" value="1" class="checkbox">
                        <label for="es_tutor" class="checkbox-label">
                            <i class="fas fa-chalkboard-teacher mr-1"></i> También es tutor
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="button button-primary">
                            <i class="fas fa-user-plus mr-2"></i> Establecer
                        </button>
                    </div>
                </form>

                <!-- Tabla de coordinadores actuales -->
                <h4 class="table-title">
                    <i class="fas fa-list-ol mr-2"></i> Listado de Coordinadores Actuales
                </h4>

                <div class="table-container" 
                    x-data="{ 
                        search: '', 
                        count: {{ count($coordinadores) }},
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
                                placeholder="Buscar coordinadores..."
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
                            Mostrando <span x-text="count"></span> de {{ count($coordinadores) }} coordinadores
                        </div>

                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="{{ route('establecer_coordinador.index', ['sort' => 'nombre']) }}">
                                        <i class="fas fa-user mr-1"></i> Nombre
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('establecer_coordinador.index', ['sort' => 'apellido']) }}">
                                        <i class="fas fa-user-tag mr-1"></i> Apellidos
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('establecer_coordinador.index', ['sort' => 'ciclo']) }}">
                                        <i class="fas fa-graduation-cap mr-1"></i> Ciclo
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('establecer_coordinador.index', ['sort' => 'dni']) }}">
                                        <i class="fas fa-id-card mr-1"></i> DNI
                                    </a>
                                </th>
                                <th><i class="fas fa-cog mr-1"></i> Acción</th>
                            </tr>
                        </thead>
                        <tbody x-ref="tableBody">
                            @forelse($coordinadores as $coordinador)
                                <tr x-data="{ showModal: false }"
                                x-show="
                                (
                                    '{{ strtolower($coordinador->docente->nombre . ' ' . $coordinador->docente->apellido . ' ' . $coordinador->ciclo->nombre . ' ' . $coordinador->dni) }}'
                                ).includes(search.toLowerCase())
                                ">
                                    <td>{{ $coordinador->docente->nombre ?? 'No encontrado' }}</td>
                                    <td>{{ $coordinador->docente->apellido ?? 'No encontrado' }}</td>
                                    <td>{{ $coordinador->ciclo->nombre }}</td>
                                    <td class="uppercase">{{ $coordinador->dni }}</td>
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
                                                    <b>{{ $coordinador->docente->nombre }} {{ $coordinador->docente->apellido }}</b>
                                                    del ciclo <b>{{ $coordinador->ciclo->nombre }}</b>?
                                                </p>
                                                
                                                @php
                                                    $esTutor = \App\Models\Tutor::where('id_centro', $coordinador->id_centro)
                                                                ->where('id_ciclo', $coordinador->id_ciclo)
                                                                ->where('dni', $coordinador->dni)
                                                                ->exists();
                                                @endphp
                                                
                                                @if($esTutor)
                                                <div class="modal-warning mt-4">
                                                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                                                    Este coordinador también es tutor de este ciclo.
                                                </div>
                                                @endif
                                                
                                                <div class="modal-actions">
                                                    <button @click="showModal = false" class="button button-secondary">
                                                        <i class="fas fa-times mr-1"></i> Cancelar
                                                    </button>
                                                    
                                                    <div class="flex flex-col space-y-2">
                                                        <form method="POST" action="{{ route('establecer_coordinador.destroy', $coordinador->id) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="button button-danger">
                                                                <i class="fas fa-check mr-1"></i> Sí, borrar solo como coordinador
                                                            </button>
                                                        </form>
                                                        
                                                        @if($esTutor)
                                                        <form method="POST" action="{{ route('establecer_coordinador.destroy', $coordinador->id) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="eliminar_tutor" value="1">
                                                            <button type="submit" class="button button-danger">
                                                                <i class="fas fa-check-double mr-1"></i> Sí, borrar como coordinador y tutor
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
                                        <i class="fas fa-info-circle mr-2"></i> No hay coordinadores registrados
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
