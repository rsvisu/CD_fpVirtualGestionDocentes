@extends('layouts.app-admin')

@section('content')

@push('styles')
<style>
    .ap-panel      { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); padding:2rem; margin:2rem auto; max-width:1100px; }
    .ap-title      { font-size:1.4rem; font-weight:700; color:#1e3a5f; margin-bottom:.25rem; }
    .ap-subtitle   { font-size:.9rem; color:#64748b; margin-bottom:1.5rem; }
    .ap-table      { width:100%; border-collapse:collapse; font-size:.9rem; }
    .ap-table th   { background:#f1f5f9; color:#475569; text-align:left; padding:.6rem .8rem; border-bottom:2px solid #e2e8f0; }
    .ap-table td   { padding:.55rem .8rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
    .ap-table tr:hover td { background:#f8fafc; }
    .btn-primary   { background:#4f46e5; color:#fff; border:none; border-radius:8px; padding:.5rem 1.2rem; cursor:pointer; font-size:.9rem; font-weight:600; transition:background .2s; }
    .btn-primary:hover   { background:#4338ca; }
    .btn-secondary { background:#e2e8f0; color:#334155; border:none; border-radius:8px; padding:.5rem 1.2rem; cursor:pointer; font-size:.9rem; font-weight:600; transition:background .2s; }
    .btn-secondary:hover { background:#cbd5e1; }
    .btn-success   { background:#16a34a; color:#fff; border:none; border-radius:8px; padding:.5rem 1.2rem; cursor:pointer; font-size:.9rem; font-weight:600; transition:background .2s; }
    .btn-success:hover   { background:#15803d; }
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:40; display:flex; align-items:center; justify-content:center; }
    .modal-box     { background:#fff; border-radius:12px; padding:2rem; max-width:560px; width:90%; box-shadow:0 8px 32px rgba(0,0,0,.18); }
    .modal-title   { font-size:1.1rem; font-weight:700; color:#1e3a5f; margin-bottom:1rem; }
    .csv-preview   { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.75rem 1rem; font-family:monospace; font-size:.8rem; word-break:break-all; color:#334155; margin-bottom:.5rem; }
    .divider       { border:none; border-top:1px solid #e2e8f0; margin:1.25rem 0; }
    .tick-ok       { color:#16a34a; font-size:.9rem; font-weight:600; }
    .tick-no       { color:#dc2626; font-size:.9rem; font-weight:600; }
</style>
@endpush

<div class="ap-panel" x-data="altaPlataforma()">

    <h3 class="ap-title"><i class="fas fa-cloud-upload-alt mr-2 text-indigo-600"></i>Alta en Plataforma</h3>
    <p class="ap-subtitle">Selecciona los docentes para descargar el CSV de Google Workspace y darlos de alta en Moodle vía API.</p>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('admin.alta-plataforma') }}" class="flex flex-wrap gap-3 mb-5">
        <input type="text" name="buscar" value="{{ request('buscar') }}"
               placeholder="Buscar por nombre, apellido o DNI..."
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 w-64">
        <select name="estado" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
            <option value="">— Todos los estados —</option>
            <option value="pendiente" @selected(request('estado') === 'pendiente')>Pendiente de alta</option>
            <option value="procesado" @selected(request('estado') === 'procesado')>Procesado</option>
        </select>
        <button type="submit" class="btn-secondary"><i class="fas fa-search mr-1"></i> Filtrar</button>
        <a href="{{ route('admin.alta-plataforma') }}" class="btn-secondary"><i class="fas fa-times mr-1"></i> Limpiar</a>
    </form>

    {{-- Tabla --}}
    <div class="overflow-x-auto">
        <table class="ap-table">
            <thead>
                <tr>
                    <th><input type="checkbox" @change="toggleAll($event)" x-ref="checkAll"></th>
                    <th>DNI</th>
                    <th>Nombre</th>
                    <th>Apellidos</th>
                    <th>Email @fpvirtualaragon.es</th>
                    <th>Estado exportación</th>
                    <th>Fecha exportación</th>
                    <th>Ver CSV</th>
                </tr>
            </thead>
            <tbody>
                @forelse($docentes as $docente)
                <tr>
                    <td>
                        <input type="checkbox"
                               value="{{ $docente->id }}"
                               x-model="selected"
                               @change="syncCheckAll">
                    </td>
                    <td class="font-mono text-xs">{{ $docente->dni }}</td>
                    <td>{{ $docente->nombre }}</td>
                    <td>{{ $docente->apellido }}</td>
                    <td class="text-indigo-700 font-medium text-xs">{{ $docente->email_virtual }}</td>

                    {{-- Estado: reactivo vía Alpine --}}
                    <td>
                        <span x-show="getEstado({{ $docente->id }})" class="tick-ok">
                            <i class="fas fa-check-circle"></i> Exportado
                        </span>
                        <span x-show="!getEstado({{ $docente->id }})" class="tick-no">
                            <i class="fas fa-times-circle"></i> Pendiente
                        </span>
                    </td>

                    {{-- Fecha: reactiva vía Alpine --}}
                    <td class="text-xs text-gray-500"
                        x-text="getFecha({{ $docente->id }})">
                    </td>

                    <td>
                        <button class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold"
                                @click="verDetalle({{ $docente->id }})">
                            <i class="fas fa-eye mr-1"></i>Ver CSV
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-gray-400 py-8">No hay docentes que coincidan con los filtros.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    <div class="mt-4">
        {{ $docentes->links() }}
    </div>

    {{-- Botón generar CSVs --}}
    <div class="mt-6 flex items-center gap-4">
        <span class="text-sm text-gray-500" x-text="selected.length + ' docente(s) seleccionado(s)'"></span>
        <button class="btn-primary"
                :disabled="selected.length === 0"
                :class="{ 'opacity-50 cursor-not-allowed': selected.length === 0 }"
                @click="abrirExport()">
            <i class="fas fa-file-csv mr-2"></i>Generar CSVs
        </button>
    </div>

    {{-- Modal: previsualización CSV de un docente --}}
    <div class="modal-overlay" x-show="showPreview" x-cloak @click.self="showPreview = false">
        <div class="modal-box">
            <h4 class="modal-title"><i class="fas fa-eye mr-2 text-indigo-500"></i>Previsualización CSV</h4>
            <template x-if="previewDocente">
                <div>
                    <p class="text-sm text-gray-600 mb-3">
                        <strong x-text="previewDocente.nombre + ' ' + previewDocente.apellido"></strong>
                        &nbsp;|&nbsp; <span class="font-mono text-xs" x-text="previewDocente.dni"></span>
                    </p>
                    <p class="text-xs font-semibold text-gray-500 mb-1">Google Workspace CSV:</p>
                    <div class="csv-preview" x-text="csvLineGoogle(previewDocente)"></div>
                    <p class="text-xs font-semibold text-gray-500 mb-1 mt-3">Username Moodle (creado vía API):</p>
                    <div class="csv-preview" x-text="moodleUsername(previewDocente)"></div>
                </div>
            </template>
            <div class="flex justify-end mt-4">
                <button class="btn-secondary" @click="showPreview = false">Cerrar</button>
            </div>
        </div>
    </div>

    {{-- Modal: exportar Google CSV + crear en Moodle vía API --}}
    <div class="modal-overlay" x-show="showExport" x-cloak>
        <div class="modal-box" style="max-width:640px;">
            <h4 class="modal-title">
                <i class="fas fa-file-download mr-2 text-indigo-500"></i>Alta en plataformas
            </h4>

            <p class="text-sm text-gray-600 mb-4">
                Para <strong x-text="selectedDocentes().length"></strong> docente(s) seleccionado(s):
                descarga el CSV para subirlo a Google Workspace y, después, crea los usuarios en Moodle vía API.
            </p>

            {{-- Paso 1: descarga Google CSV --}}
            <div class="flex flex-wrap gap-3 mb-2">
                <button class="btn-primary" @click="downloadCSV('google')">
                    <i class="fab fa-google mr-1"></i>Descargar CSV Google Workspace
                </button>
            </div>

            <hr class="divider">

            {{-- Paso 2: alta en Moodle vía API + resultado --}}
            <div x-show="!resultado">
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <button class="btn-secondary" @click="cerrarSinActualizar()" :disabled="procesando">
                        <i class="fas fa-times mr-1"></i>Cerrar
                    </button>
                    <button class="btn-success"
                            :disabled="procesando"
                            :class="{ 'opacity-60 cursor-not-allowed': procesando }"
                            @click="crearEnMoodle()">
                        <span x-show="!procesando">
                            <i class="fas fa-graduation-cap mr-1"></i>Crear en Moodle
                        </span>
                        <span x-show="procesando">
                            <i class="fas fa-spinner fa-spin mr-1"></i>Llamando a Moodle…
                        </span>
                    </button>
                </div>
            </div>

            {{-- Resumen del resultado --}}
            <div x-show="resultado" x-cloak>
                <h5 class="font-semibold text-sm text-gray-700 mb-2">Resultado</h5>

                <div class="mb-2 text-sm">
                    <span class="tick-ok"><i class="fas fa-check-circle"></i></span>
                    <strong>Creados en Moodle:</strong>
                    <span x-text="resultado?.created?.length ?? 0"></span>
                    <template x-if="resultado?.created?.length">
                        <div class="csv-preview" x-text="resultado.created.join(', ')"></div>
                    </template>
                </div>

                <div class="mb-2 text-sm">
                    <span class="text-amber-600"><i class="fas fa-info-circle"></i></span>
                    <strong>Ya existían (marcados como procesados):</strong>
                    <span x-text="resultado?.skipped?.length ?? 0"></span>
                    <template x-if="resultado?.skipped?.length">
                        <div class="csv-preview" x-text="resultado.skipped.join(', ')"></div>
                    </template>
                </div>

                <div class="mb-3 text-sm">
                    <span class="tick-no"><i class="fas fa-times-circle"></i></span>
                    <strong>Fallidos:</strong>
                    <span x-text="Object.keys(resultado?.failed ?? {}).length"></span>
                    <template x-for="[dni, err] in Object.entries(resultado?.failed ?? {})" :key="dni">
                        <div class="csv-preview">
                            <span class="font-semibold" x-text="dni"></span>: <span x-text="err"></span>
                        </div>
                    </template>
                </div>

                <div class="flex justify-end">
                    <button class="btn-secondary" @click="cerrarConResultado()">
                        <i class="fas fa-check mr-1"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('altaPlataforma', () => ({
        selected: [],
        showPreview: false,
        showExport: false,
        previewDocente: null,
        procesando: false,
        resultado: null,
        docenteMap: @json($docentesJson),

        // ── Helpers de estado reactivo ─────────────────────────────────
        getEstado(id) {
            return this.docenteMap.find(d => d.id === id)?.is_procesado ?? false;
        },

        getFecha(id) {
            return this.docenteMap.find(d => d.id === id)?.fecha_procesado ?? '—';
        },

        // ── Selección ──────────────────────────────────────────────────
        toggleAll(event) {
            if (event.target.checked) {
                this.selected = this.docenteMap.map(d => d.id);
            } else {
                this.selected = [];
            }
        },

        syncCheckAll() {
            const allIds = this.docenteMap.map(d => d.id);
            this.$refs.checkAll.checked = allIds.length > 0 && allIds.every(id => this.selected.includes(id));
            this.$refs.checkAll.indeterminate = this.selected.length > 0 && !this.$refs.checkAll.checked;
        },

        selectedDocentes() {
            return this.docenteMap.filter(d => this.selected.includes(d.id));
        },

        // ── Detalle / preview ──────────────────────────────────────────
        verDetalle(id) {
            this.previewDocente = this.docenteMap.find(d => d.id === id) ?? null;
            this.showPreview = true;
        },

        abrirExport() {
            if (this.selected.length === 0) return;
            this.resultado  = null;
            this.showExport = true;
        },

        // ── Generación CSV (solo Google) ───────────────────────────────
        csvLineGoogle(d) {
            const nombre = (d.nombre + ' ' + d.apellido).replace(/"/g, '""');
            return `"${nombre}","${d.email_virtual}",,`;
        },

        moodleUsername(d) {
            return 'prof' + (d.dni || '').toLowerCase();
        },

        downloadCSV(tipo) {
            // Solo Google Workspace; la subida a Moodle se hace vía API.
            const docs  = this.selectedDocentes();
            const lines = ['Nombre,Correo,Contraseña,Unidad organizativa'];
            docs.forEach(d => lines.push(this.csvLineGoogle(d)));

            const bom  = '﻿';
            const blob = new Blob([bom + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href     = url;
            a.download = 'alta_google_workspace.csv';
            a.click();
            URL.revokeObjectURL(url);
        },

        // ── Botones de cierre del modal ────────────────────────────────
        cerrarSinActualizar() {
            if (this.procesando) return;
            this.showExport = false;
            this.resultado  = null;
        },

        cerrarConResultado() {
            this.showExport = false;
            this.resultado  = null;
            this.selected   = [];
            if (this.$refs.checkAll) {
                this.$refs.checkAll.checked = false;
                this.$refs.checkAll.indeterminate = false;
            }
        },

        // ── Alta en Moodle vía API ─────────────────────────────────────
        crearEnMoodle() {
            if (this.procesando) return;
            this.procesando = true;

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const ids   = [...this.selected];

            fetch('{{ route("admin.alta-plataforma.procesar") }}', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept':       'application/json',
                },
                body: JSON.stringify({ ids }),
            })
            .then(async r => {
                const body = await r.json().catch(() => null);
                if (!r.ok) throw body || { failed: { '_': `HTTP ${r.status}` } };
                return body;
            })
            .then(body => {
                this.resultado = body;

                // Marcar como procesados en el estado local los que crearon u omitieron.
                const dnisOk = new Set([...(body.created || []), ...(body.skipped || [])]);
                if (dnisOk.size > 0) {
                    const ahora = new Date().toLocaleString('es-ES', {
                        day: '2-digit', month: '2-digit', year: 'numeric',
                        hour: '2-digit', minute: '2-digit',
                    });
                    this.docenteMap.forEach(d => {
                        if (dnisOk.has(d.dni)) {
                            d.is_procesado    = true;
                            d.fecha_procesado = ahora;
                        }
                    });
                }
            })
            .catch(err => {
                this.resultado = {
                    created: [],
                    skipped: [],
                    failed:  (err && err.failed) ? err.failed : { '_': 'Error de red llamando al servidor' },
                };
            })
            .finally(() => {
                this.procesando = false;
            });
        },
    }));
});
</script>
@endpush

@endsection
