/**
 * DIGITURNO UNAD - JavaScript: Panel de Dependencia
 * Maneja la atencion de visitantes desde cada dependencia
 */

const API_BASE = 'api';
const REFRESH_INTERVAL = 500; // 0.5 segundos

let dependenciaActual = null;
let refreshTimer = null;

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    // Rol dependencia: la dependencia viene fija del perfil del usuario (admin la asigna).
    if (typeof DEP_FIJA !== 'undefined' && DEP_FIJA !== null && DEP_FIJA !== '') {
        ingresarDependenciaFija(DEP_FIJA, DEP_FIJA_NOMBRE || 'Dependencia');
    } else {
        // Rol admin: selector de dependencia
        cargarDependencias();
    }
});

// Ingresar con la dependencia asignada al usuario (rol dependencia)
function ingresarDependenciaFija(depId, nombre) {
    dependenciaActual = depId;
    document.getElementById('dep-badge').textContent = DEP_FIJA_NOMBRE || 'Dependencia';
    document.getElementById('dep-titulo').textContent = 'Panel de Dependencia';
    document.getElementById('panel-gestion').classList.remove('hidden');
    refrescarTurnos();
    refreshTimer = setInterval(refrescarTurnos, REFRESH_INTERVAL);
}

// Cargar dependencias
async function cargarDependencias() {
    try {
        const resp = await fetch(`${API_BASE}/get_dependencies.php`);
        const data = await resp.json();

        if (data.success) {
            const select = document.getElementById('sel-dependencia');
            data.dependencias.forEach(dep => {
                const opt = document.createElement('option');
                opt.value = dep.id;
                opt.textContent = `${dep.codigo} - ${dep.nombre}`;
                select.appendChild(opt);
            });

            // Restaurar de sessionStorage
            const guardada = sessionStorage.getItem('dependencia_id');
            if (guardada) {
                select.value = guardada;
                ingresarDependencia();
            }
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Ingresar al panel de la dependencia
function ingresarDependencia() {
    const sel = document.getElementById('sel-dependencia');
    const depId = sel.value;

    if (!depId) {
        mostrarToast('Seleccione una dependencia', 'error');
        return;
    }

    dependenciaActual = depId;
    sessionStorage.setItem('dependencia_id', depId);

    const depTexto = sel.options[sel.selectedIndex].text;
    document.getElementById('dep-badge').textContent = depTexto;
    document.getElementById('dep-titulo').textContent = `Panel - ${depTexto}`;

    document.getElementById('card-seleccion').classList.add('hidden');
    document.getElementById('panel-gestion').classList.remove('hidden');

    refrescarTurnos();
    refreshTimer = setInterval(refrescarTurnos, REFRESH_INTERVAL);
}

// Refrescar turnos de la dependencia
async function refrescarTurnos() {
    if (!dependenciaActual) return;

    try {
        const resp = await fetch(`${API_BASE}/get_dependency_turns.php?dependencia_id=${dependenciaActual}`);
        const data = await resp.json();

        if (!data.success) return;

        // Actualizar titulo y badge con la dependencia real consultada
        if (data.dependencia && data.dependencia.nombre) {
            const depTxt = `${data.dependencia.codigo} - ${data.dependencia.nombre}`;
            document.getElementById('dep-titulo').textContent = `Panel - ${depTxt}`;
            if (typeof DEP_FIJA !== 'undefined' && DEP_FIJA) {
                document.getElementById('dep-badge').textContent =
                    DEP_FIJA_NOMBRE ? `${DEP_FIJA_NOMBRE} | ${depTxt}` : depTxt;
            } else {
                document.getElementById('dep-badge').textContent = depTxt;
            }
        }

        renderPendientes(data.pendientes);
        renderHistorial(data.atendidos_hoy);

        // Actualizar contadores
        document.getElementById('count-pendientes').textContent = data.pendientes.length;
        document.getElementById('count-historial').textContent = data.atendidos_hoy.length;

        // Hora actual
        document.getElementById('dep-hora').textContent =
            new Date().toLocaleTimeString('es-CO');

    } catch (error) {
        console.error('Error:', error);
    }
}

// Renderizar turnos pendientes (en cola, llamados y en atencion, ordenados del mas antiguo al mas reciente)
function renderPendientes(pendientes) {
    const container = document.getElementById('lista-pendientes');

    if (!pendientes || pendientes.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">&#128203;</div>
                <h3>Sin turnos pendientes</h3>
                <p>No hay visitantes en cola o llamados para esta dependencia</p>
            </div>
        `;
        return;
    }

    let html = '';
    pendientes.forEach(t => {
        const esRegistrado = t.estado === 'registrado';
        const esLlamado = t.estado === 'llamado' || t.estado === 'en_atencion';
        const detalle = t.lista_codigo ? `${t.lista_codigo} - ${t.lista_nombre}` : t.tipo_visitante || 'visitante';

        // Botones segun estado:
        // - registrado (en cola): solo "Llamar"
        // - llamado/en_atencion: "Finalizar" y "No Asistio" (sin boton "Atender")
        let acciones = '';
        if (esRegistrado) {
            acciones = `
                <button class="btn btn-sm btn-warning" onclick="llamarTurno(${t.id})">
                    Llamar
                </button>
            `;
        } else {
            acciones = `
                <button class="btn btn-sm btn-danger" onclick="abrirModalFinalizar(${t.id}, '${t.numero_turno}', '${t.nombres} ${t.apellidos}')">
                    Finalizar
                </button>
                <button class="btn btn-sm btn-outline" onclick="marcarNoAsistio(${t.id})">
                    No Asistio
                </button>
            `;
        }

        html += `
            <div class="turno-card ${esLlamado ? 'turno-urgente' : ''}">
                <div class="turno-card-info">
                    <div class="turno-card-numero">${t.numero_turno}</div>
                    <div class="turno-card-nombre">${t.nombres} ${t.apellidos}</div>
                    <div class="turno-card-meta">
                        C.C. ${t.cedula} | ${detalle}
                    </div>
                    <div class="turno-card-meta">
                        Llegada: ${t.fecha_ingreso || '-'}
                        ${t.fecha_llamada ? ' | Llamado: ' + t.fecha_llamada : ''}
                    </div>
                </div>
                <div class="turno-card-actions">
                    ${acciones}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// Renderizar historial del dia
function renderHistorial(atendidos) {
    const container = document.getElementById('lista-historial');

    if (!atendidos || atendidos.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">&#9989;</div>
                <h3>Sin atenciones hoy</h3>
                <p>Aun no se han atendido visitantes hoy</p>
            </div>
        `;
        return;
    }

    let html = `
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Turno</th>
                        <th>Visitante</th>
                        <th>Cedula</th>
                        <th>Estado</th>
                        <th>Hora Llegada</th>
                        <th>Hora Salida</th>
                    </tr>
                </thead>
                <tbody>
    `;

    atendidos.forEach(t => {
        const badge = t.estado === 'finalizado'
            ? '<span class="badge badge-finalizado">Atendido</span>'
            : '<span class="badge badge-no_asistio">No Asistio</span>';

        const horaFin = t.fecha_fin ? t.fecha_fin.split(' ')[1] || '-' : '-';

        html += `
            <tr>
                <td><strong>${t.numero_turno}</strong></td>
                <td>${t.nombres} ${t.apellidos}</td>
                <td>${t.cedula}</td>
                <td>${badge}</td>
                <td>${t.fecha_ingreso ? t.fecha_ingreso.split(' ')[1] || '-' : '-'}</td>
                <td>${horaFin}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// === ACCIONES ===

// Llamar un turno
async function llamarTurno(turnoId) {
    try {
        const resp = await fetch(`${API_BASE}/call_turn.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ turno_id: turnoId })
        });
        const data = await resp.json();

        if (data.success) {
            mostrarToast('Turno llamado', 'success');
            refrescarTurnos();
        } else {
            mostrarToast(data.message, 'error');
        }
    } catch (error) {
        mostrarToast('Error de conexion', 'error');
    }
}

// Abrir modal para finalizar
function abrirModalFinalizar(turnoId, numero, nombre) {
    document.getElementById('modal-turno-num').textContent = numero;
    document.getElementById('modal-turno-nombre').textContent = nombre;
    document.getElementById('modal-obs').value = '';
    document.getElementById('modal-finalizar').classList.add('active');

    const btnFinalizar = document.getElementById('btn-confirmar-finalizar');
    btnFinalizar.onclick = () => finalizarAtencion(turnoId);
}

function cerrarModal() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
}

// Finalizar atencion
async function finalizarAtencion(turnoId) {
    const btnFinalizar = document.getElementById('btn-confirmar-finalizar');
    btnFinalizar.disabled = true;
    btnFinalizar.textContent = 'Procesando...';

    const obs = document.getElementById('modal-obs').value.trim();

    try {
        const resp = await fetch(`${API_BASE}/finish_turn.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ turno_id: turnoId, observaciones: obs })
        });
        const data = await resp.json();

        cerrarModal();

        if (data.success) {
            mostrarToast('Atencion finalizada. Visitante egresado.', 'success');
            refrescarTurnos();
        } else {
            mostrarToast(data.message, 'error');
        }
    } catch (error) {
        cerrarModal();
        mostrarToast('Error de conexion', 'error');
    } finally {
        btnFinalizar.disabled = false;
        btnFinalizar.textContent = 'Finalizar y Egresar';
    }
}

// Marcar no asistio
async function marcarNoAsistio(turnoId) {
    if (!confirm('Marcar este turno como no asistido?')) return;

    try {
        const resp = await fetch(`${API_BASE}/no_show.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ turno_id: turnoId })
        });
        const data = await resp.json();

        if (data.success) {
            mostrarToast('Turno marcado como no asistido', 'warning');
            refrescarTurnos();
        } else {
            mostrarToast(data.message, 'error');
        }
    } catch (error) {
        mostrarToast('Error de conexion', 'error');
    }
}

// Cambiar tab
function cambiarTab(btn) {
    document.querySelectorAll('.dependency-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));

    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');
}

// Toast
function showToast(mensaje, tipo = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const clase = tipo === 'error' ? 'toast-error' : tipo === 'warning' ? 'toast-warning' : '';
    toast.className = `toast ${clase}`;
    toast.innerHTML = `
        <span class="toast-message">${mensaje}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function mostrarToast(mensaje, tipo) {
    showToast(mensaje, tipo);
}
