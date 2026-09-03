/**
 * DIGITURNO UNAD - JavaScript: Recepcion
 * Maneja el registro de visitantes desde la recepcion
 */

const API_BASE = 'api';
const FETCH_TIMEOUT = 15000; // 15s por si el servidor tarda
const REFRESH_INTERVAL = 500; // 0.5 segundos

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    cargarDependencias();
    cargarHistorial();
    inicializarFormulario();

    // Refrescar "Turnos Registrados Hoy" cada 0.5s para que la recepcion
    // vea en tiempo real los cambios de estado (usuario atendido, finalizado, etc.)
    setInterval(() => cargarHistorial(true), REFRESH_INTERVAL);
});

// Normaliza un campo de texto a MAYUSCULAS y solo letras (sin numeros ni simbolos)
function normalizarTexto(input) {
    let valor = input.value.toUpperCase();
    // Permitir letras (incluidas tildes/ñ) y espacios, eliminar el resto
    valor = valor.replace(/[^A-ZÁÉÍÓÚÜÑ ]/g, '');
    if (input.value !== valor) {
        input.value = valor;
    }
}

// fetch con timeout y parseo tolerante de JSON
async function fetchJSON(url, options = {}) {
    const controlador = new AbortController();
    const timer = setTimeout(() => controlador.abort(), FETCH_TIMEOUT);

    try {
        const resp = await fetch(url, { ...options, signal: controlador.signal });

        // Intentar extraer JSON aunque venga con texto basura alrededor (warnings PHP)
        let texto = await resp.text();
        const inicio = texto.indexOf('{');
        let data = null;
        if (inicio >= 0) {
            try {
                // Buscar el ultimo '}' balanceado para extraer el JSON
                const fin = texto.lastIndexOf('}');
                if (fin >= inicio) {
                    data = JSON.parse(texto.substring(inicio, fin + 1));
                }
            } catch (e) {
                data = null;
            }
        }
        return { ok: resp.ok, status: resp.status, data };
    } finally {
        clearTimeout(timer);
    }
}

// Cargar dependencias en el select
async function cargarDependencias() {
    try {
        const res = await fetchJSON(`${API_BASE}/get_dependencies.php`);
        if (!res || !res.data || !res.data.success) return;

        const data = res.data;
        const select = document.getElementById('dependencia_id');
        data.dependencias.forEach(dependencia => {
            const opt = document.createElement('option');
            opt.value = dependencia.id;
            opt.textContent = `${dependencia.codigo} - ${dependencia.nombre}`;
            opt.dataset.usaListas = dependencia.usaListas;
            opt.dataset.codigo = dependencia.codigo;
            select.appendChild(opt);
        });

        // Mostrar/esconder selector de escuela segun la dependencia
        select.addEventListener('change', onCambioDependencia);
    } catch (error) {
        mostrarAlerta('Error al cargar dependencias', 'error');
        console.error(error);
    }
}

// Manejar cambio de dependencia (mostrar escuela si aplica)
async function onCambioDependencia() {
    const select = document.getElementById('dependencia_id');
    const grupoEscuela = document.getElementById('grupo-escuela');
    const selectEscuela = document.getElementById('lista_id');
    const opt = select.options[select.selectedIndex];

    selectEscuela.innerHTML = '<option value="">Seleccione la escuela...</option>';

    if (opt && opt.dataset.usaListas === '1') {
        grupoEscuela.classList.remove('hidden');

        try {
            const res = await fetchJSON(`${API_BASE}/get_lists.php?dependencia_id=${opt.value}`);
            if (res && res.data && res.data.success) {
                res.data.listas.forEach(escuela => {
                    const op = document.createElement('option');
                    op.value = escuela.id;
                    op.textContent = `${escuela.codigo} - ${escuela.nombre}`;
                    selectEscuela.appendChild(op);
                });
            }
        } catch (error) {
            console.error('Error cargando escuelas:', error);
        }
    } else {
        grupoEscuela.classList.add('hidden');
        selectEscuela.value = '';
    }
}

// Inicializar formulario
function inicializarFormulario() {
    document.getElementById('form-registro').addEventListener('submit', (e) => {
        e.preventDefault();
        mostrarConfirmacion();
    });

    document.getElementById('btn-confirmar').addEventListener('click', () => {
        registrarTurno();
    });

    document.getElementById('btn-nuevo-registro').addEventListener('click', () => {
        nuevoRegistro();
    });

    // Solo numeros en cedula
    document.getElementById('cedula').addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
    });
}

// Mostrar modal de confirmacion
function mostrarConfirmacion() {
    const nombres = document.getElementById('nombres').value.trim();
    const apellidos = document.getElementById('apellidos').value.trim();
    const cedula = document.getElementById('cedula').value.trim();
    const depSelect = document.getElementById('dependencia_id');
    const depNombre = depSelect.options[depSelect.selectedIndex]?.text || '';

    // Escuela seleccionada (si aplica)
    const escuelaSelect = document.getElementById('lista_id');
    let escuelaNombre = '';
    if (!document.getElementById('grupo-escuela').classList.contains('hidden') &&
        escuelaSelect.selectedIndex > 0) {
        escuelaNombre = escuelaSelect.options[escuelaSelect.selectedIndex]?.text || '';
    }

    const datos = `
        <p><strong>Nombre:</strong> ${nombres} ${apellidos}</p>
        <p><strong>Cedula:</strong> ${cedula}</p>
        <p><strong>Dependencia:</strong> ${depNombre}</p>
        ${escuelaNombre ? `<p><strong>Escuela:</strong> ${escuelaNombre}</p>` : ''}
        <p><strong>Hora:</strong> ${new Date().toLocaleTimeString('es-CO')}</p>
    `;

    document.getElementById('modal-datos').innerHTML = datos;
    document.getElementById('modal-confirmacion').classList.add('active');
}

function cerrarModal() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
}

// Registrar turno via API
async function registrarTurno() {
    const btnConfirmar = document.getElementById('btn-confirmar');
    btnConfirmar.disabled = true;
    btnConfirmar.textContent = 'Registrando...';

    const usaEscuela = !document.getElementById('grupo-escuela').classList.contains('hidden');

    const payload = {
        nombres: document.getElementById('nombres').value.trim(),
        apellidos: document.getElementById('apellidos').value.trim(),
        cedula: document.getElementById('cedula').value.trim(),
        dependencia_id: document.getElementById('dependencia_id').value,
        tipo_visitante: document.getElementById('tipo_visitante').value,
        lista_id: usaEscuela ? document.getElementById('lista_id').value : null
    };

    try {
        const res = await fetchJSON(`${API_BASE}/register.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        // Si el servidor no devolvio JSON valido, refrescar para confirmar si se registro.
        const data = res && res.data ? res.data : null;

        cerrarModal();

        if (data && data.success) {
            mostrarToast('Turno registrado exitosamente', 'success');

            // Mostrar turno generado en ventana emergente (modal)
            const turno = data.turno;
            document.getElementById('turno-numero-display').textContent = turno.numero_turno;
            document.getElementById('turno-dep-display').textContent =
                `${turno.dependencia_codigo} - ${turno.dependencia_nombre}`;
            document.getElementById('turno-hora-display').textContent =
                `Registrado: ${turno.fecha_ingreso}`;
            document.getElementById('modal-turno-generado').classList.add('active');
            document.getElementById('btn-nuevo-registro').focus();

            // Limpiar formulario
            document.getElementById('form-registro').reset();

            // Punto 3: actualizar la tabla "Turnos Registrados Hoy"
            // automaticamente 0.5s despues de registrar
            setTimeout(() => cargarHistorial(), 500);
        } else if (data && !data.success) {
            mostrarAlerta(data.message || 'No se pudo registrar el turno', 'error');
        } else {
            // Respuesta sin JSON valido: la accion pudo ejecutarse igual.
            setTimeout(() => cargarHistorial(), 800);
        }
    } catch (error) {
        cerrarModal();
        console.error(error);
        // Posible timeout: refrescar para verificar en lugar de alarmar.
        setTimeout(() => cargarHistorial(), 800);
    } finally {
        btnConfirmar.disabled = false;
        btnConfirmar.textContent = 'Confirmar';
    }
}

// Nuevo registro (cerrar modal de turno generado y volver al formulario)
function nuevoRegistro() {
    cerrarModal();
    document.getElementById('nombres').focus();
}

// Cargar historial del dia
async function cargarHistorial(silent = false) {
    const container = document.getElementById('historial-container');
    if (!silent) {
        container.innerHTML = '<div class="loading"><div class="spinner"></div><p>Cargando turnos...</p></div>';
    }

    // Mostrar la fecha actual en el titulo "Turnos Registrados Hoy"
    try {
        const fechaEl = document.getElementById('historial-fecha');
        if (fechaEl) {
            const hoy = new Date();
            const fechaTexto = hoy.toLocaleDateString('es-CO', {
                year: 'numeric', month: 'long', day: 'numeric'
            });
            fechaEl.textContent = '| ' + fechaTexto;
        }
    } catch (e) { /* ignorar */ }

    try {
        const hoy = new Date().toISOString().split('T')[0];
        const res = await fetchJSON(`${API_BASE}/get_history.php?fecha_desde=${hoy}&fecha_hasta=${hoy}`);
        const data = res && res.data ? res.data : null;

        if (data && data.success && data.turnos.length > 0) {
            let html = `
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Turno</th>
                                <th>Nombre</th>
                                <th>Cedula</th>
                                <th>Dependencia</th>
                                <th>Estado</th>
                                <th>Hora</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            data.turnos.forEach(turno => {
                const nombre = `${turno.nombres} ${turno.apellidos}`;
                const estadoBadge = getBadgeEstado(turno.estado);
                const hora = turno.fecha_ingreso ? turno.fecha_ingreso.split(' ')[1] || '' : '';
                const detalleDep = turno.lista_codigo
                    ? `${turno.dependencia_codigo} <small style="color:var(--unad-text-light)">(${turno.lista_codigo})</small>`
                    : `${turno.dependencia_codigo}`;

                html += `
                    <tr>
                        <td><strong>${turno.numero_turno}</strong></td>
                        <td>${nombre}</td>
                        <td>${turno.cedula}</td>
                        <td>${detalleDep}</td>
                        <td>${estadoBadge}</td>
                        <td>${hora}</td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">&#128203;</div>
                    <h3>Sin turnos hoy</h3>
                    <p>Registre el primer visitante del dia</p>
                </div>
            `;
        }
    } catch (error) {
        container.innerHTML = '<div class="alert alert-error">Error al cargar historial</div>';
        console.error(error);
    }
}

// Utilidades
function mostrarAlerta(mensaje, tipo) {
    const container = document.getElementById('alert-container');
    container.innerHTML = `<div class="alert alert-${tipo}">${mensaje}</div>`;
    // El mensaje dura 6s para que sea visible y no desaparezca antes de la accion
    clearTimeout(container._timer);
    container._timer = setTimeout(() => container.innerHTML = '', 6000);
}

function getBadgeEstado(estado) {
    const badges = {
        'registrado': '<span class="badge badge-registrado">Registrado</span>',
        'llamado': '<span class="badge badge-llamado">Llamado</span>',
        'en_atencion': '<span class="badge badge-en_atencion">En Atencion</span>',
        'finalizado': '<span class="badge badge-finalizado">Finalizado</span>',
        'no_asistio': '<span class="badge badge-no_asistio">No Asistio</span>'
    };
    return badges[estado] || estado;
}

function mostrarToast(mensaje, tipo = 'success') {
    showToast(mensaje, tipo);
}

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
    // El toast dura 5s para que sea legible
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}
