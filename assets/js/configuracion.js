/**
 * DIGITURNO UNAD - JavaScript: Configuracion (gestion de usuarios)
 * Solo accesible con perfil Administrador.
 */

const API_BASE = 'api';
const FETCH_TIMEOUT = 15000;

let usuarioEditandoId = null;

document.addEventListener('DOMContentLoaded', () => {
    cargarUsuarios();
    cargarDependencias();
});

// fetch con timeout y parseo tolerante de JSON
async function fetchJSON(url, options = {}) {
    const controlador = new AbortController();
    const timer = setTimeout(() => controlador.abort(), FETCH_TIMEOUT);

    try {
        const resp = await fetch(url, { ...options, signal: controlador.signal });
        let texto = await resp.text();
        const inicio = texto.indexOf('{');
        let data = null;
        if (inicio >= 0) {
            try {
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

// === USUARIOS ===

async function cargarUsuarios() {
    const container = document.getElementById('usuarios-container');

    try {
        const res = await fetchJSON(`${API_BASE}/get_usuarios.php`);
        const data = res && res.data ? res.data : null;

        if (!data || !data.success) {
            container.innerHTML = '<div class="alert alert-error">No pudo cargarse la lista de usuarios</div>';
            return;
        }

        const usuarios = data.usuarios || [];
        if (usuarios.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">&#128100;</div>
                    <h3>Sin usuarios</h3>
                    <p>Cree el primer funcionario de dependencia</p>
                </div>
            `;
            return;
        }

        let html = `
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Funcionario</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Dependencia</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        usuarios.forEach(u => {
            const nombre = $escapeHtml(
                (u.nombre || '') + ((u.apellido || '').trim() ? ' ' + u.apellido : '')
            );
            const dep = u.dependencia_codigo
                ? `${u.dependencia_codigo} - ${u.dependencia_nombre}`
                : '-';
            const estado = parseInt(u.activo) === 1
                ? '<span class="user-estado activo">Activo</span>'
                : '<span class="user-estado inactivo">Inactivo</span>';
            const rol = u.rol === 'admin' ? 'Administrador' : 'Dependencia';
            const acciones = u.rol === 'dependencia'
                ? `\
                    <button class="btn btn-sm btn-outline" onclick="abrirModalEditar(${u.id})">Editar</button>\
                    <button class="btn btn-sm btn-danger" onclick="eliminarUsuario(${u.id})">Eliminar</button>`
                : '<small style="color:var(--unad-text-light)">-</small>';

            html += `
                <tr>
                    <td><strong>${nombre}</strong></td>
                    <td>${$escapeHtml(u.usuario)}</td>
                    <td>${rol}</td>
                    <td>${dep}</td>
                    <td>${estado}</td>
                    <td><div class="config-table-actions">${acciones}</div></td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    } catch (error) {
        container.innerHTML = '<div class="alert alert-error">Error al cargar usuarios</div>';
        console.error(error);
    }
}

// === FORMULARIO NUEVO / EDITAR ===

async function cargarDependencias() {
    try {
        const res = await fetchJSON(`${API_BASE}/get_dependencies.php`);
        const data = res && res.data ? res.data : null;
        if (!data || !data.success) return;

        const select = document.getElementById('usr-dependencia');
        data.dependencias.forEach(dep => {
            const opt = document.createElement('option');
            opt.value = dep.id;
            opt.textContent = `${dep.codigo} - ${dep.nombre}`;
            select.appendChild(opt);
        });
    } catch (error) {
        console.error('Error cargando dependencias:', error);
    }
}

function abrirModalNuevo() {
    usuarioEditandoId = null;
    document.getElementById('usuario-modal-titulo').textContent = 'Nuevo Funcionario';
    document.getElementById('usr-nombre').value = '';
    document.getElementById('usr-apellido').value = '';
    document.getElementById('usr-usuario').value = '';
    document.getElementById('usr-password').value = '';
    document.getElementById('usr-password').removeAttribute('required');
    document.getElementById('usr-pass-label').innerHTML = 'Contraseña <span class="required">*</span>';
    document.getElementById('usr-dependencia').value = '';
    document.getElementById('usr-activo').checked = true;
    document.getElementById('usr-activo-grupo').classList.add('hidden');
    limpiarErrorModal();
    document.getElementById('modal-usuario').classList.add('active');
    setTimeout(() => document.getElementById('usr-nombre').focus(), 100);
}

function abrirModalEditar(id) {
    usuarioEditandoId = id;

    // Buscar el usuario en la lista cargada
    fetchJSON(`${API_BASE}/get_usuarios.php`).then(res => {
        const data = res && res.data ? res.data : null;
        if (!data || !data.success) return;
        const u = (data.usuarios || []).find(x => parseInt(x.id) === parseInt(id));
        if (!u) {
            mostrarToast('Usuario no encontrado', 'error');
            return;
        }

        // Separar nombre y apellido. Si no hay apellido almacenado (dato legacy),
        // se intenta extraer del nombre completo (ej. "LUIS GARCIA").
        let nombre = u.nombre || '';
        let apellido = (u.apellido || '').trim();
        if (!apellido) {
            const partes = nombre.trim().split(/\s+/);
            if (partes.length > 1) {
                apellido = partes.slice(1).join(' ');
                nombre = partes[0];
            }
        }

        document.getElementById('usuario-modal-titulo').textContent = 'Editar Funcionario';
        document.getElementById('usr-nombre').value = nombre;
        document.getElementById('usr-apellido').value = apellido;
        document.getElementById('usr-usuario').value = u.usuario;
        document.getElementById('usr-password').value = '';
        document.getElementById('usr-pass-label').innerHTML = 'Nueva contraseña (opcional)';
        document.getElementById('usr-password').placeholder = 'Deje vacio para mantener la actual';
        document.getElementById('usr-dependencia').value = u.dependencia_id || '';
        document.getElementById('usr-activo').checked = parseInt(u.activo) === 1;
        document.getElementById('usr-activo-grupo').classList.remove('hidden');
        limpiarErrorModal();
        document.getElementById('modal-usuario').classList.add('active');
        setTimeout(() => document.getElementById('usr-nombre').focus(), 100);
    });
}

async function guardarUsuario() {
    const nombre = document.getElementById('usr-nombre').value.trim();
    const apellido = document.getElementById('usr-apellido').value.trim();
    const usuario = document.getElementById('usr-usuario').value.trim();
    const password = document.getElementById('usr-password').value;
    const dependencia_id = document.getElementById('usr-dependencia').value;
    const activo = document.getElementById('usr-activo').checked ? 1 : 0;

    if (!nombre || !apellido || !usuario || !dependencia_id) {
        mostrarErrorModal('Nombre, apellido, usuario y dependencia son obligatorios');
        return;
    }
    if (!usuarioEditandoId && !password) {
        mostrarErrorModal('La contraseña es obligatoria para un funcionario nuevo');
        return;
    }
    if (password && password.length < 6) {
        mostrarErrorModal('La contraseña debe tener al menos 6 caracteres');
        return;
    }

    const btn = document.getElementById('btn-guardar-usuario');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const payload = {
        id: usuarioEditandoId,
        nombre,
        apellido,
        usuario,
        password,
        dependencia_id,
        activo
    };

    try {
        const res = await fetchJSON(`${API_BASE}/save_usuario.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (res.data && res.data.success) {
            cerrarModal();
            mostrarToast(res.data.message, 'success');
            cargarUsuarios();
        } else {
            mostrarErrorModal(res.data?.message || 'No se pudo guardar');
        }
    } catch (error) {
        mostrarErrorModal('Error de conexion');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar';
    }
}

async function eliminarUsuario(id) {
    if (!confirm('Eliminar este funcionario? Perdera el acceso al sistema.')) return;

    try {
        const res = await fetchJSON(`${API_BASE}/delete_usuario.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        if (res.data && res.data.success) {
            mostrarToast(res.data.message, 'success');
            cargarUsuarios();
        } else {
            mostrarToast(res.data?.message || 'No se pudo eliminar', 'error');
        }
    } catch (error) {
        mostrarToast('Error de conexion', 'error');
    }
}

// === CONTRASENA DEL ADMINISTRADOR ===

async function cambiarPasswordAdmin() {
    const actual = document.getElementById('adm-pass-actual').value;
    const nueva = document.getElementById('adm-pass-nueva').value;
    const confirm = document.getElementById('adm-pass-confirm').value;

    if (!actual || !nueva || !confirm) {
        mostrarAlertaAdmin('Complete todos los campos', 'error');
        return;
    }
    if (nueva !== confirm) {
        mostrarAlertaAdmin('La contraseña nueva no coincide con la confirmacion', 'error');
        return;
    }
    if (nueva.length < 6) {
        mostrarAlertaAdmin('La contraseña nueva debe tener al menos 6 caracteres', 'error');
        return;
    }

    try {
        const res = await fetchJSON(`${API_BASE}/cambiar_password_admin.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password_actual: actual, password_nueva: nueva })
        });
        if (res.data && res.data.success) {
            mostrarAlertaAdmin(res.data.message, 'success');
            document.getElementById('adm-pass-actual').value = '';
            document.getElementById('adm-pass-nueva').value = '';
            document.getElementById('adm-pass-confirm').value = '';
        } else {
            mostrarAlertaAdmin(res.data?.message || 'No se pudo cambiar la contraseña', 'error');
        }
    } catch (error) {
        mostrarAlertaAdmin('Error de conexion', 'error');
    }
}

// === UTILIDADES ===

function cerrarModal() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
}

function mostrarErrorModal(mensaje) {
    document.getElementById('usuario-modal-error').innerHTML =
        `<div class="alert alert-error">${mensaje}</div>`;
}

function limpiarErrorModal() {
    document.getElementById('usuario-modal-error').innerHTML = '';
}

function mostrarAlertaAdmin(mensaje, tipo) {
    const container = document.getElementById('alert-admin');
    container.innerHTML = `<div class="alert alert-${tipo}">${mensaje}</div>`;
    clearTimeout(container._timer);
    container._timer = setTimeout(() => container.innerHTML = '', 6000);
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
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function $escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto || '';
    return div.innerHTML;
}