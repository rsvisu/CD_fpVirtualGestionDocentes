<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/establecerCoordinadorTutorDocencia.css') }}">
    @endpush

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="panel">
                <h3 class="title">Establecer Docencia</h3>
                <p class="subtitle">Complete el siguiente formulario para asignar docencia a un profesor.</p>

                <!-- Formulario -->
                <form method="POST" action="{{ route('establecer_docencia.store') }}" class="form">
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
                            <i class="fas fa-user-tie mr-1"></i> Seleccionar Docente:
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

                    <div class="form-group">
                        <label for="id_modulo" class="label">
                            <i class="fas fa-book mr-1"></i> Seleccionar Módulo:
                        </label>
                        <select name="id_modulo" id="id_modulo" required class="select @error('id_modulo') input-error @enderror">
                            <option value="">-- Selecciona un módulo --</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="button button-primary">
                            <i class="fas fa-user-plus mr-2"></i> Establecer
                        </button>
                    </div>
                </form>

                <!-- Tabla de docencias actuales -->
                <h4 class="table-title">
                    <i class="fas fa-list-ol mr-2"></i> Listado de Docencias Actuales
                </h4>

                <div class="table-container" 
                    x-data="{ 
                        search: '', 
                        count: {{ count($docencias) }},
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
                                placeholder="Buscar docencias..."
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
                            Mostrando <span x-text="count"></span> de {{ count($docencias) }} docencias
                        </div>
                    </div>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="{{ route('establecer_docencia.index', ['sort' => 'nombre']) }}">
                                        <i class="fas fa-user mr-1"></i> Nombre
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('establecer_docencia.index', ['sort' => 'apellido']) }}">
                                        <i class="fas fa-user-tag mr-1"></i> Apellidos
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('establecer_docencia.index', ['sort' => 'ciclo']) }}">
                                        <i class="fas fa-graduation-cap mr-1"></i> Ciclo
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('establecer_docencia.index', ['sort' => 'modulo']) }}">
                                        <i class="fas fa-book mr-1"></i> Módulo
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ route('establecer_docencia.index', ['sort' => 'dni']) }}">
                                        <i class="fas fa-id-card mr-1"></i> DNI
                                    </a>
                                </th>
                                <th><i class="fas fa-cog mr-1"></i> Acción</th>
                            </tr>
                        </thead>
                        <tbody x-ref="tableBody">
                            @forelse($docencias as $docencia)
                                <tr x-data="{ showModal: false }"
                                x-show="
                                (
                                    '{{ strtolower($docencia->docente->nombre . ' ' . $docencia->docente->apellido . ' ' . $docencia->ciclo->nombre . ' ' . $docencia->modulo->nombre . ' ' . $docencia->dni) }}'
                                ).includes(search.toLowerCase())
                                ">
                                    <td>{{ $docencia->docente->nombre ?? 'No encontrado' }}</td>
                                    <td>{{ $docencia->docente->apellido ?? 'No encontrado' }}</td>
                                    <td>{{ $docencia->ciclo->nombre }}</td>
                                    <td>{{ $docencia->modulo->nombre }}</td>
                                    <td class="uppercase">{{ $docencia->dni }}</td>
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
                                                    ¿Estás seguro de que quieres eliminar la docencia de
                                                    <b>{{ $docencia->docente->nombre }} {{ $docencia->docente->apellido }}?</b><br><br>
                                                    Módulo: <b>{{ $docencia->modulo->nombre }}</b><br>
                                                    Ciclo: <b>{{ $docencia->ciclo->nombre }}</b>
                                                </p>
                                                
                                                <div class="modal-actions">
                                                    <button @click="showModal = false" class="button button-secondary">
                                                        <i class="fas fa-times mr-1"></i> Cancelar
                                                    </button>
                                                    
                                                    <form method="POST" action="{{ route('establecer_docencia.destroy', $docencia->id) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="button button-danger">
                                                            <i class="fas fa-check mr-1"></i> Sí, borrar
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="table-empty">
                                            <i class="fas fa-info-circle mr-2"></i> No hay asignaciones de docencia registradas
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cicloSelect = document.getElementById('id_ciclo');
            const moduloSelect = document.getElementById('id_modulo');
            const modulosData = @json($modulos->map(function($modulo) {
                return [
                    'id' => $modulo->id_modulo,
                    'nombre' => $modulo->nombre,
                    'ciclos' => $modulo->ciclos->pluck('id_ciclo')->toArray()
                ];
            }));

            cicloSelect.addEventListener('change', function() {
                const cicloId = this.value;
                
                // Limpiar select
                moduloSelect.innerHTML = '<option value="">-- Selecciona un módulo --</option>';
                
                if (cicloId) {
                    // Filtrar módulos para este ciclo
                    const modulosFiltrados = modulosData.filter(modulo => 
                        modulo.ciclos.includes(cicloId)
                    );
                    
                    // Agregar opciones
                    modulosFiltrados.forEach(modulo => {
                        const option = document.createElement('option');
                        option.value = modulo.id;
                        option.textContent = modulo.nombre;
                        moduloSelect.appendChild(option);
                    });
                }
            });
        });
    </script>
</x-app-layout>