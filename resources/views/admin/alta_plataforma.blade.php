@extends('layouts.app-admin')

@section('content')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/establecerCoordinadorTutorDocencia.css') }}">
<style>
    .csv-preview { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.75rem 1rem; font-family:monospace; font-size:.75rem; word-break:break-all; color:#334155; margin-bottom:.5rem; }
    .tick-ok     { color:#16a34a; font-size:.88rem; font-weight:600; }
    .tick-no     { color:#dc2626; font-size:.88rem; font-weight:600; }
    .divider     { border:none; border-top:1px solid #e2e8f0; margin:1.25rem 0; }
    .button-success { background-color:#16a34a; color:white; }
    .button-success:hover { background-color:#15803d; transform:translateY(-2px); }
</style>
@endpush

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="altaPlataforma()">

        <div class="panel">
            <h3 class="title">Alta en Plataforma</h3>
            <p class="subtitle">Selecciona los docentes para generar los CSV de Google Workspace y Moodle.</p>

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.alta-plataforma') }}" class="flex flex-wrap gap-3 mb-5">
                <input type="text" name="buscar" value="{{ request('buscar') }}"
                       placeholder="Buscar por nombre, apellido o DNI..."
                       class="input" style="max-width:280px;">
                <select name="estado" class="select" style="max-width:220px;">
                    <option value="">— Todos los estados —</option>
                    <option value="pendiente" @selected(request('estado') === 'pendiente')>Pendiente de alta</option>
                    <option value="procesado" @selected(request('estado') === 'procesado')>Procesado</option>
                </select>
                <button type="submit" class="button button-secondary button-tiny">
                    <i class="fas fa-search mr-1"></i> Filtrar
                </button>
                <a href="{{ route('admin.alta-plataforma') }}" class="button button-secondary button-tiny">
                    <i class="fas fa-times mr-1"></i> Limpiar
                </a>
            </form>

            {{-- Tabla --}}
            <div class="table-container">
                <table class="table">
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
                            <td><input type="checkbox" value="{{ $docente->id }}" x-model="selected" @change="syncCheckAll"></td>
                            <td>{{ $docente->dni }}</td>
                            <td>{{ $docente->nombre }}</td>
                            <td>{{ $docente->apellido }}</td>
                            <td>{{ $docente->email_virtual }}</td>
                            <td>
                                <span x-show="getEstado({{ $docente->id }})" class="tick-ok"><i class="fas fa-check-circle"></i> Exportado</span>
                                <span x-show="!getEstado({{ $docente->id }})" class="tick-no"><i class="fas fa-times-circle"></i> Pendiente</span>
                            </td>
                            <td x-text="getFecha({{ $docente->id }})"></td>
                            <td>
                                <button class="button button-secondary button-tiny" @click="verDetalle({{ $docente->id }})">
                                    <i class="fas fa-eye mr-1"></i>Ver CSV
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="table-empty">No hay docentes que coincidan con los filtros.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $docentes->links() }}</div>

            {{-- Botón generar CSVs --}}
            <div class="mt-6 flex items-center gap-4">
                <span class="text-sm text-gray-500" x-text="selected.length + ' docente(s) seleccionado(s)'"></span>
                <button class="button button-primary"
                        :disabled="selected.length === 0"
                        :class="{ 'opacity-50 cursor-not-allowed': selected.length === 0 }"
                        @click="abrirExport()">
                    <i class="fas fa-file-csv mr-2"></i>Generar CSVs
                </button>
            </div>
        </div>

        {{-- Modal: previsualización CSV de un docente --}}
        <div class="modal" x-show="showPreview" x-cloak @click.self="showPreview = false">
            <div class="modal-content" style="max-width:680px;">
                <h4 class="modal-title">Previsualización CSV</h4>
                <template x-if="previewDocente">
                    <div>
                        <p class="modal-text">
                            <strong x-text="previewDocente.nombre + ' ' + previewDocente.apellido"></strong>
                            &nbsp;|&nbsp; <span x-text="previewDocente.dni"></span>
                        </p>
                        <p class="label">Google Workspace:</p>
                        <div class="csv-preview" x-text="csvLineGoogle(previewDocente)"></div>
                        <p class="label mt-3">Moodle:</p>
                        <div class="csv-preview" x-text="csvLineMoodle(previewDocente)"></div>
                    </div>
                </template>
                <div class="modal-actions">
                    <button class="button button-secondary" @click="showPreview = false">Cerrar</button>
                </div>
            </div>
        </div>

        {{-- Modal: exportar y actualizar estado --}}
        <div class="modal" x-show="showExport" x-cloak>
            <div class="modal-content" style="max-width:640px;">
                <h4 class="modal-title">Exportar CSVs</h4>
                <p class="modal-text">
                    Descarga los ficheros para
                    <strong x-text="selectedDocentes().length"></strong>
                    docente(s) seleccionado(s) y, cuando estés listo/a, cierra actualizando el estado.
                </p>

                <div class="flex flex-wrap gap-3 mb-2">
                    <button class="button button-primary" @click="downloadCSV('google')">
                        <i class="fab fa-google mr-1"></i>Descargar CSV Google Workspace
                    </button>
                    <button class="button button-primary" @click="downloadCSV('moodle')">
                        <i class="fas fa-graduation-cap mr-1"></i>Descargar CSV Moodle
                    </button>
                </div>

                <hr class="divider">

                <div class="modal-actions">
                    <button class="button button-secondary" @click="cerrarSinActualizar()">
                        <i class="fas fa-times mr-1"></i>Cerrar sin actualizar estado
                    </button>
                    <button class="button button-success"
                            :disabled="procesando"
                            :class="{ 'opacity-60 cursor-not-allowed': procesando }"
                            @click="cerrarActualizando()">
                        <span x-show="!procesando"><i class="fas fa-check-circle mr-1"></i>Cerrar y actualizar estado</span>
                        <span x-show="procesando"><i class="fas fa-spinner fa-spin mr-1"></i>Actualizando…</span>
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
        docenteMap: @json($docentesJson),

        getEstado(id) {
            return this.docenteMap.find(d => d.id === id)?.is_procesado ?? false;
        },
        getFecha(id) {
            return this.docenteMap.find(d => d.id === id)?.fecha_procesado ?? '—';
        },

        toggleAll(event) {
            this.selected = event.target.checked ? this.docenteMap.map(d => String(d.id)) : [];
        },
        syncCheckAll() {
            const allIds = this.docenteMap.map(d => String(d.id));
            this.$refs.checkAll.checked = allIds.length > 0 && allIds.every(id => this.selected.includes(id));
            this.$refs.checkAll.indeterminate = this.selected.length > 0 && !this.$refs.checkAll.checked;
        },
        selectedDocentes() {
            const nums = this.selected.map(Number);
            return this.docenteMap.filter(d => nums.includes(d.id));
        },

        verDetalle(id) {
            this.previewDocente = this.docenteMap.find(d => d.id === id) ?? null;
            this.showPreview = true;
        },
        abrirExport() {
            if (this.selected.length === 0) return;
            this.showExport = true;
        },

        csvLineGoogle(d) {
            const cols = [
                d.nombre         || '',
                d.apellido       || '',
                d.email_virtual  || '',
                'Cambiam3!_',
                '',
                '/Profesorado',
                '',
                d.email_personal || '',
                '',
                d.email_personal || '',
                '', '', '', '', '', '',
                d.dni            || '',
                '', '', '', '', '', '', '', '',
                'TRUE',
                'Active',
                '',
                'FALSE',
            ];
            return cols.join(',');
        },

        csvLineMoodle(d) {
            const cols = [
                d.nombre         || '',
                d.apellido       || '',
                d.email_virtual  || '',
                'Cambiam3!_',
                '',
                '/Profesorado',
                '',
                d.email_personal || '',
                '',
                '',
                '', '', '', '', '', '',
                d.dni            || '',
                '', '', '', '', '', '', '', '',
                'TRUE',
                '',
                '',
                'FALSE',
            ];
            return cols.join(',');
        },

        downloadCSV(tipo) {
            const docs  = this.selectedDocentes();
            let   lines = [];
            const header = 'First Name [Required],Last Name [Required],Email Address [Required],' +
                'Password [Required],Password Hash Function [UPLOAD ONLY],Org Unit Path [Required],' +
                'New Primary Email [UPLOAD ONLY],Recovery Email,Home Secondary Email,Work Secondary Email,' +
                'Recovery Phone [MUST BE IN THE E.164 FORMAT],Work Phone,Home Phone,Mobile Phone,' +
                'Work Address,Home Address,Employee ID,Employee Type,Employee Title,Manager Email,' +
                'Department,Cost Center,Building ID,Floor Name,Floor Section,' +
                'Change Password at Next Sign-In,New Status [UPLOAD ONLY],New Licenses [UPLOAD ONLY],' +
                'Advanced Protection Program enrollment';

            lines.push(header);
            docs.forEach(d => lines.push(tipo === 'google' ? this.csvLineGoogle(d) : this.csvLineMoodle(d)));

            const bom  = '﻿';
            const blob = new Blob([bom + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href     = url;
            a.download = tipo === 'google' ? 'alta_google_workspace.csv' : 'alta_moodle.csv';
            a.click();
            URL.revokeObjectURL(url);
        },

        cerrarSinActualizar() {
            this.showExport = false;
        },

        cerrarActualizando() {
            if (this.procesando) return;
            this.procesando = true;
            const token      = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const idsAmarcar = [...this.selected];

            fetch('{{ route("admin.alta-plataforma.procesar") }}', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body:    JSON.stringify({ ids: idsAmarcar }),
            })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(() => {
                const ahora = new Date().toLocaleString('es-ES', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit',
                });
                idsAmarcar.forEach(id => {
                    const d = this.docenteMap.find(d => d.id === Number(id));
                    if (d) { d.is_procesado = true; d.fecha_procesado = ahora; }
                });
                this.showExport = false;
                this.selected   = [];
                if (this.$refs.checkAll) this.$refs.checkAll.checked = false;
            })
            .catch(() => alert('Error al actualizar el estado. Inténtalo de nuevo.'))
            .finally(() => { this.procesando = false; });
        },
    }));
});
</script>
@endpush

@endsection
