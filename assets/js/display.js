/**
 * DIGITURNO UNAD - JavaScript: Pantalla de Espera
 * Muestra turnos llamados y proximos en tiempo real
 */

const API_BASE = 'api';
const REFRESH_INTERVAL = 500; // 0.5 segundos

let refreshTimer = null;

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    cargarPantalla();
    refreshTimer = setInterval(cargarPantalla, REFRESH_INTERVAL);
});

function actualizarFecha() {
    const ahora = new Date();
    const opciones = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };
    const fecha = ahora.toLocaleDateString('es-CO', opciones);
    const hora = ahora.toLocaleTimeString('es-CO');
    document.getElementById('display-date').textContent = `${fecha} | ${hora}`;
}

// Cargar datos de la pantalla
async function cargarPantalla() {
    try {
        // Actualizar fecha/hora en curso en cada refresco (0.5s)
        actualizarFecha();

        const resp = await fetch(`${API_BASE}/get_display.php`);
        const data = await resp.json();

        if (!data.success) return;

        // Turnos en atencion (todos los llamados)
        actualizarEnAtencion(data.en_atencion);

        // Proximos turnos
        actualizarProximos(data.proximos);

        // Estadisticas
        if (data.estadisticas) {
            document.getElementById('stat-total').textContent = data.estadisticas.total_hoy || 0;
            document.getElementById('stat-en-cola').textContent = data.estadisticas.en_cola || 0;
            document.getElementById('stat-atendidos').textContent = data.estadisticas.atendidos || 0;
        }

    } catch (error) {
        console.error('Error actualizando pantalla:', error);
    }
}

function actualizarEnAtencion(lista) {
    const container = document.getElementById('turnos-atencion-container');

    if (!lista || lista.length === 0) {
        container.innerHTML = `
            <div class="turno-actual sin-turno">
                <div class="turno-label">Turno en Atencion</div>
                <div class="turno-numero">Sin turnos</div>
                <div class="turno-dependencia"></div>
                <div class="turno-nombre"></div>
            </div>
        `;
        return;
    }

    // Todos los turnos en atencion con el mismo llamativo y tamano
    let html = '';
    lista.forEach(turno => {
        let textoDependencia;
        // Para la dependencia ESC (escuela) mostramos la escuela junto al codigo
        if (turno.dependencia_codigo === 'ESC' && turno.lista_nombre) {
            textoDependencia = turno.dependencia_codigo + ' - ' + turno.lista_nombre;
        } else {
            textoDependencia = turno.dependencia_codigo + ' - ' + turno.dependencia_nombre;
        }
        html += `
            <div class="turno-actual">
                <div class="turno-label">Turno en Atencion</div>
                <div class="turno-numero">${turno.numero_turno}</div>
                <div class="turno-dependencia">${textoDependencia}</div>
                <div class="turno-nombre">${turno.nombres} ${turno.apellidos}</div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function actualizarProximos(proximos) {
    const container = document.getElementById('proximos-container');

    if (!proximos || proximos.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="color: rgba(255,255,255,0.5);">
                <div class="empty-icon">&#128203;</div>
                <h3 style="color: rgba(255,255,255,0.7);">No hay turnos pendientes</h3>
                <p style="color: rgba(255,255,255,0.4);">Esperando nuevos registros...</p>
            </div>
        `;
        return;
    }

    // Dos columnas: Turno | Dependencia (tabla)
    let html = `
        <div class="proximos-table">
            <div class="proximos-head">
                <div>Turno</div>
                <div>Dependencia</div>
            </div>
    `;
    proximos.forEach(proximoTurno => {
        html += crearTurnoItem(proximoTurno);
    });
    html += '</div>';
    container.innerHTML = html;
}

function crearTurnoItem(turno) {
    let textoDependencia;
    // Para la dependencia ESC (escuela) mostramos la escuela en lugar del nombre
    if (turno.dependencia_codigo === 'ESC' && turno.lista_nombre) {
        textoDependencia = turno.lista_nombre + ' (' + turno.dependencia_codigo + ')';
    } else {
        textoDependencia = turno.dependencia_nombre + ' (' + turno.dependencia_codigo + ')';
    }
    return `
        <div class="proximos-row">
            <div class="proximos-num">${turno.numero_turno}</div>
            <div class="proximos-dep">${textoDependencia}</div>
        </div>
    `;
}
