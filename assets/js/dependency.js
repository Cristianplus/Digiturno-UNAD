/**
 * DIGITURNO UNAD - JavaScript: Panel de Dependencia
 * Maneja la atencion de visitantes desde cada dependencia
 */

const API_BASE = 'api';
const REFRESH_INTERVAL = 500; // 0.5 segundos

let dependenciaActual = null;
let refreshTimer = null;
let gridSelectTimer = null;
let gridEscTimer = null;
let escActual = null;

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    // Delegacion de eventos: el listener vive en el contenedor fijo, asi sobrevive
    // al re-render de los turnos (0.5s) y el clic nunca se pierde.
    document.getElementById('lista-pendientes').addEventListener('click', onListaPendientesClick);

    // Al hacer clic en "Dependencia" de la barra, volver al listado de dependencias
    // si ya se esta dentro de un panel de gestion (solo admin).
    const btnVolver = document.getElementById('volver-dependencias');
    if (btnVolver) {
        btnVolver.addEventListener('click', volverAlListado);
    }

    // Rol dependencia: la dependencia viene fija del perfil del usuario (admin la asigna).
    if (typeof DEP_FIJA !== 'undefined' && DEP_FIJA !== null && DEP_FIJA !== '') {
        ingresarDependenciaFija(DEP_FIJA, DEP_FIJA_NOMBRE || 'Dependencia');
    } else {
        // Rol admin: selector de dependencia
        cargarDependencias();
    }
});

// Handler delegado para los botones de los turnos pendientes
function onListaPendientesClick(e) {
    const btn = e.target.closest('button[data-accion]');
    if (!btn) return;

    const accion = btn.dataset.accion;
    const turnoId = btn.dataset.id;

    if (accion === 'llamar') {
        // Evitar dobles envíos mientras se procesa
        btn.disabled = true;
        btn.textContent = '...';
        llamarTurno(turnoId, btn);
    } else if (accion === 'finalizar') {
        abrirModalFinalizar(turnoId, btn.dataset.numero, btn.dataset.nombre);
    } else if (accion === 'noasistio') {
        btn.disabled = true;
        marcarNoAsistio(turnoId, btn);
    }
}

// Ingresar con la dependencia asignada al usuario (rol dependencia)
function ingresarDependenciaFija(depId, nombre) {
    dependenciaActual = depId;
    document.getElementById('dep-badge').textContent = DEP_FIJA_NOMBRE || 'Dependencia';
    document.getElementById('dep-titulo').textContent = 'Panel de Dependencia';
    document.getElementById('panel-gestion').classList.remove('hidden');
    refrescarTurnos();
    refreshTimer = setInterval(refrescarTurnos, REFRESH_INTERVAL);
}

// Cargar dependencias (listado completo visible a la vez, rol admin)
async function cargarDependencias() {
    try {
        const resp = await fetch(`${API_BASE}/get_dependencies.php`);
        const data = await resp.json();

        if (data.success) {
            // Ocultar placeholder de carga
            const grid = document.getElementById('deps-grid');
            grid.innerHTML = '';

            if (!data.dependencias || data.dependencias.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state">
                        <p>No hay dependencias activas</p>
                    </div>
                `;
                return;
            }

            data.dependencias.forEach(dependencia => {
                // La unica dependencia con sub-listado (escuelas) es ESC
                const esEsc = dependencia.codigo.toUpperCase() === 'ESC';
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'dep-card' + (esEsc ? ' dep-card-esc' : '');
                card.dataset.id = dependencia.id;
                card.dataset.codigo = dependencia.codigo;
                card.dataset.nombre = dependencia.nombre;

                const enEspera = parseInt(dependencia.en_espera || 0, 10);
                const esperaHtml = enEspera > 0
                    ? `<span class="dep-waiting">&#128100; ${enEspera} en espera</span>`
                    : '';

                card.innerHTML = `
                    <span class="dep-card-codigo">${dependencia.codigo}</span>
                    <span class="dep-card-nombre">${dependencia.nombre}</span>
                    ${esperaHtml}
                `;
                card.addEventListener('click', () => seleccionarDependencia(dependencia));
                grid.appendChild(card);
            });

            // Refrescar el conteo de espera periodicamente mientras se muestra el selector
            if (!gridSelectTimer) {
                gridSelectTimer = setInterval(actualizarEsperaGrid, REFRESH_INTERVAL);
            }

            // Restaurar de sessionStorage cuando no es ESC
            const guardada = sessionStorage.getItem('dependencia_id');
            if (guardada) {
                const depGuardada = data.dependencias.find(d => String(d.id) === String(guardada));
                if (depGuardada && depGuardada.codigo.toUpperCase() !== 'ESC') {
                    ingresarAlPanel(depGuardada);
                }
            }
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Actualizar solo el indicador de espera del grid sin re-renderizar (mantiene estado visual)
async function actualizarEsperaGrid() {
    const grid = document.getElementById('deps-grid');
    if (!grid || grid.classList.contains('hidden') || !document.getElementById('card-seleccion')) return;

    // No actualizar si el panel de gestion ya esta visible
    if (!document.getElementById('panel-gestion').classList.contains('hidden')) {
        if (gridSelectTimer) { clearInterval(gridSelectTimer); gridSelectTimer = null; }
        return;
    }

    try {
        const resp = await fetch(`${API_BASE}/get_dependencies.php`);
        const data = await resp.json();
        if (!data.success) return;

        data.dependencias.forEach(dependencia => {
            const enEspera = parseInt(dependencia.en_espera || 0, 10);
            const card = grid.querySelector(`.dep-card[data-id="${dependencia.id}"]`);
            if (!card) return;

            let indicadorEspera = card.querySelector('.dep-waiting');
            if (enEspera > 0) {
                if (!indicadorEspera) {
                    indicadorEspera = document.createElement('span');
                    indicadorEspera.className = 'dep-waiting';
                    card.appendChild(indicadorEspera);
                }
                indicadorEspera.innerHTML = `&#128100; ${enEspera} en espera`;
            } else if (indicadorEspera) {
                indicadorEspera.remove();
            }
        });
    } catch (error) {
        console.error('Error:', error);
    }
}

// Seleccionar una dependencia del grid
function seleccionarDependencia(dependencia) {
    if (dependencia.codigo.toUpperCase() === 'ESC') {
        // ESC: desplegar las escuelas almacenadas
        cargarEscuelas(dependencia);
        return;
    }
    ingresarAlPanel(dependencia);
}

// Cargar y mostrar las escuelas de ESC
async function cargarEscuelas(dependencia) {
    escActual = dependencia;

    try {
        const resp = await fetch(`${API_BASE}/get_lists.php?dependencia_id=${dependencia.id}`);
        const data = await resp.json();

        const bloque = document.getElementById('escuelas-block');
        const gridEsc = document.getElementById('escs-grid');
        gridEsc.innerHTML = '';

        if (data.success && data.listas && data.listas.length > 0) {
            data.listas.forEach(escuela => {
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'escs-card';
                card.dataset.id = escuela.id;
                card.dataset.nombre = escuela.nombre;

                const enEspera = parseInt(escuela.en_espera || 0, 10);
                const esperaHtml = enEspera > 0
                    ? `<span class="dep-waiting">&#128100; ${enEspera} en espera</span>`
                    : '';

                card.innerHTML = `
                    <span class="escs-card-codigo">${escuela.codigo || ''}</span>
                    <span class="escs-card-nombre">${escuela.nombre}</span>
                    ${esperaHtml}
                `;
                card.addEventListener('click', () => ingresarAlPanel(dependencia, escuela));
                gridEsc.appendChild(card);
            });
        } else {
            gridEsc.innerHTML = `
                <div class="empty-state">
                    <p>No hay escuelas registradas para ESC</p>
                </div>
            `;
        }

        // Ocultar grid de dependencias y mostrar escuelas
        document.getElementById('deps-grid').classList.add('hidden');
        document.querySelector('.selector-hint').classList.add('hidden');
        bloque.classList.remove('hidden');

        // Refrescar conteo de espera de las escuelas periodicamente
        if (gridEscTimer) { clearInterval(gridEscTimer); }
        gridEscTimer = setInterval(actualizarEsperaEscuelas, REFRESH_INTERVAL);
    } catch (error) {
        console.error('Error:', error);
        mostrarToast('Error al cargar las escuelas', 'error');
    }
}

// Volver de la seleccion de escuelas al listado completo de dependencias
function volverADependencias() {
    document.getElementById('escuelas-block').classList.add('hidden');
    document.getElementById('deps-grid').classList.remove('hidden');
    document.querySelector('.selector-hint').classList.remove('hidden');

    if (gridEscTimer) { clearInterval(gridEscTimer); gridEscTimer = null; }
}

// Actualizar solo el indicador de espera del grid de escuelas sin re-renderizar
async function actualizarEsperaEscuelas() {
    const bloque = document.getElementById('escuelas-block');
    const gridEsc = document.getElementById('escs-grid');
    if (!bloque || !gridEsc || bloque.classList.contains('hidden')) return;

    // No actualizar si el panel de gestion ya esta visible
    if (!document.getElementById('panel-gestion').classList.contains('hidden')) {
        if (gridEscTimer) { clearInterval(gridEscTimer); gridEscTimer = null; }
        return;
    }

    if (!escActual) return;

    try {
        const resp = await fetch(`${API_BASE}/get_lists.php?dependencia_id=${escActual.id}`);
        const data = await resp.json();
        if (!data.success) return;

        data.listas.forEach(escuela => {
            const enEspera = parseInt(escuela.en_espera || 0, 10);
            const card = gridEsc.querySelector(`.escs-card[data-id="${escuela.id}"]`);
            if (!card) return;

            let indicadorEspera = card.querySelector('.dep-waiting');
            if (enEspera > 0) {
                if (!indicadorEspera) {
                    indicadorEspera = document.createElement('span');
                    indicadorEspera.className = 'dep-waiting';
                    card.appendChild(indicadorEspera);
                }
                indicadorEspera.innerHTML = `&#128100; ${enEspera} en espera`;
            } else if (indicadorEspera) {
                indicadorEspera.remove();
            }
        });
    } catch (error) {
        console.error('Error:', error);
    }
}

// Ingresar al panel de la dependencia (opcionalmente con una escuela seleccionada)
function ingresarAlPanel(dependencia, escuela) {
    dependenciaActual = dependencia.id;
    sessionStorage.setItem('dependencia_id', dependencia.id);

    const depTexto = `${dependencia.codigo} - ${dependencia.nombre}`;
    document.getElementById('dep-badge').textContent = depTexto;
    document.getElementById('dep-titulo').textContent =
        escuela ? `Panel - ESC - ${escuela.nombre}` : `Panel - ${depTexto}`;

    document.getElementById('card-seleccion').classList.add('hidden');
    document.getElementById('panel-gestion').classList.remove('hidden');

    // Detener refresco de los selectores ya no visibles
    if (gridSelectTimer) { clearInterval(gridSelectTimer); gridSelectTimer = null; }
    if (gridEscTimer) { clearInterval(gridEscTimer); gridEscTimer = null; }

    refrescarTurnos();
    refreshTimer = setInterval(refrescarTurnos, REFRESH_INTERVAL);
}

// Volver desde un panel de gestion al listado completo de dependencias (solo admin).
// Se dispara al hacer clic en el enlace "Dependencia" de la barra superior.
function volverAlListado(e) {
    // Rol dependencia (DEP_FIJA): no hay selector al que volver.
    if (typeof DEP_FIJA !== 'undefined' && DEP_FIJA) return;

    const panel = document.getElementById('panel-gestion');
    // Si aun no se esta en un panel, dejar que el enlace recargue la pagina normalmente.
    if (!panel || panel.classList.contains('hidden')) return;

    e.preventDefault();

    // Limpiar la dependencia guardada para que no se restaure tras la vista.
    sessionStorage.removeItem('dependencia_id');
    dependenciaActual = null;

    // Detener el refresco del panel de gestion.
    if (refreshTimer) { clearInterval(refreshTimer); refreshTimer = null; }

    // Regresar al selector de dependencias.
    panel.classList.add('hidden');
    document.getElementById('card-seleccion').classList.remove('hidden');

    // Si se estaba en la vista de escuelas de ESC, volver al grid de dependencias.
    const bloqueEsc = document.getElementById('escuelas-block');
    if (bloqueEsc && !bloqueEsc.classList.contains('hidden')) {
        bloqueEsc.classList.add('hidden');
        document.querySelector('.selector-hint').classList.remove('hidden');
    }
    const gridDeps = document.getElementById('deps-grid');
    if (gridDeps) gridDeps.classList.remove('hidden');

    // Recargar el listado (y reactivar el refresco del conteo de espera).
    cargarDependencias();
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
// Solo reemplaza el DOM si el contenido realmente cambio (evita recrear botones
// sobre los que el usuario esta a punto de hacer click durante el auto-refresh).
function renderPendientes(pendientes) {
    const container = document.getElementById('lista-pendientes');

    let html;
    if (!pendientes || pendientes.length === 0) {
        html = `
            <div class="empty-state">
                <div class="empty-icon">&#128203;</div>
                <h3>Sin turnos pendientes</h3>
                <p>No hay visitantes en cola o llamados para esta dependencia</p>
            </div>
        `;
    } else {
        html = '';
        pendientes.forEach(turno => {
            const esRegistrado = turno.estado === 'registrado';
            const esLlamado = turno.estado === 'llamado' || turno.estado === 'en_atencion';
            const detalle = turno.lista_codigo ? `${turno.lista_codigo} - ${turno.lista_nombre}` : turno.tipo_visitante || 'visitante';

            // Botones segun estado:
            // - registrado (en cola): solo "Llamar"
            // - llamado/en_atencion: "Finalizar" y "No Asistio" (sin boton "Atender")
            // Se usan data-* + delegacion de eventos (no onclick) para no perder el
            // manejador cuando el panel se re-renderiza por el auto-refresh de 0.5s.
            let acciones = '';
            if (esRegistrado) {
                acciones = `
                    <button class="btn btn-sm btn-warning" data-accion="llamar" data-id="${turno.id}">
                        Llamar
                    </button>
                `;
            } else {
                acciones = `
                    <button class="btn btn-sm btn-danger" data-accion="finalizar" data-id="${turno.id}"
                            data-numero="${turno.numero_turno}" data-nombre="${turno.nombres} ${turno.apellidos}">
                        Finalizar
                    </button>
                    <button class="btn btn-sm btn-outline" data-accion="noasistio" data-id="${turno.id}">
                        No Asistio
                    </button>
                `;
            }

            html += `
                <div class="turno-card ${esLlamado ? 'turno-urgente' : ''}">
                    <div class="turno-card-info">
                        <div class="turno-card-numero">${turno.numero_turno}</div>
                        <div class="turno-card-nombre">${turno.nombres} ${turno.apellidos}</div>
                        <div class="turno-card-meta">
                            C.C. ${turno.cedula} | ${detalle}
                        </div>
                        <div class="turno-card-meta">
                            Llegada: ${turno.fecha_ingreso || '-'}
                            ${turno.fecha_llamada ? ' | Llamado: ' + turno.fecha_llamada : ''}
                        </div>
                    </div>
                    <div class="turno-card-actions">
                        ${acciones}
                    </div>
                </div>
            `;
        });
    }

    // Solo tocar el DOM si el HTML cambio
    if (container.innerHTML.trim() !== html.trim()) {
        container.innerHTML = html;
    }
}

// Renderizar historial del dia
// Solo reemplaza el DOM si el contenido cambio (misma logica anti-re-render que renderPendientes).
function renderHistorial(atendidos) {
    const container = document.getElementById('lista-historial');

    let html;
    if (!atendidos || atendidos.length === 0) {
        html = `
            <div class="empty-state">
                <div class="empty-icon">&#9989;</div>
                <h3>Sin atenciones hoy</h3>
                <p>Aun no se han atendido visitantes hoy</p>
            </div>
        `;
    } else {
        html = `
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

        atendidos.forEach(turno => {
            const badge = turno.estado === 'finalizado'
                ? '<span class="badge badge-finalizado">Atendido</span>'
                : '<span class="badge badge-no_asistio">No Asistio</span>';

            const horaFin = turno.fecha_fin ? turno.fecha_fin.split(' ')[1] || '-' : '-';

            html += `
                <tr>
                    <td><strong>${turno.numero_turno}</strong></td>
                    <td>${turno.nombres} ${turno.apellidos}</td>
                    <td>${turno.cedula}</td>
                    <td>${badge}</td>
                    <td>${turno.fecha_ingreso ? turno.fecha_ingreso.split(' ')[1] || '-' : '-'}</td>
                    <td>${horaFin}</td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
    }

    if (container.innerHTML.trim() !== html.trim()) {
        container.innerHTML = html;
    }
}

// === ACCIONES ===

// Reproducir el sonido de aviso al llamar un turno.
// Se crea un Audio nuevo por llamada para permitir sonidos superpuestos.
let sonidoTurno = null;
function reproducirSonidoTurno() {
    if (!sonidoTurno) {
        sonidoTurno = new Audio('assets/audio/sonido_turno.mp3');
    } else {
        sonidoTurno.currentTime = 0;
    }
    sonidoTurno.play().catch(() => {});
}

// Llamar un turno
async function llamarTurno(turnoId, boton) {
    try {
        const resp = await fetch(`${API_BASE}/call_turn.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ turno_id: turnoId })
        });
        const data = await resp.json();

        if (data.success) {
            mostrarToast('Turno llamado', 'success');
            reproducirSonidoTurno();
            refrescarTurnos();
        } else {
            mostrarToast(data.message, 'error');
            // Rehabilitar el boton si fallo y aun esta en el DOM
            if (boton && boton.isConnected) {
                boton.disabled = false;
                boton.textContent = 'Llamar';
            }
        }
    } catch (error) {
        mostrarToast('Error de conexion', 'error');
        if (boton && boton.isConnected) {
            boton.disabled = false;
            boton.textContent = 'Llamar';
        }
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
async function marcarNoAsistio(turnoId, boton) {
    if (!confirm('Marcar este turno como no asistido?')) {
        if (boton && boton.isConnected) {
            boton.disabled = false;
        }
        return;
    }

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
            if (boton && boton.isConnected) boton.disabled = false;
        }
    } catch (error) {
        mostrarToast('Error de conexion', 'error');
        if (boton && boton.isConnected) boton.disabled = false;
    }
}

// Cambiar tab
function cambiarTab(btn) {
    document.querySelectorAll('.dependency-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(contenido => contenido.classList.remove('active'));

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
