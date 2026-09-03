/**
 * DIGITURNO UNAD - JavaScript: Seleccion de Perfil (login)
 * Maneja las tarjetas de perfil y el formulario de acceso.
 */

const API_BASE = 'api';
const FETCH_TIMEOUT = 15000;

let perfilSeleccionado = null;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.profile-card').forEach(card => {
        const abrir = () => seleccionarPerfil(card.dataset.perfil);
        card.addEventListener('click', abrir);
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                abrir();
            }
        });
    });

    // Enviar con Enter en el formulario
    document.getElementById('login-password').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') enviarLogin();
    });
    document.getElementById('login-usuario').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') enviarLogin();
    });
});

// Seleccion de perfil
async function seleccionarPerfil(perfil) {
    if (perfil === 'recepcion') {
        // Recepcion: acceso directo sin credenciales
        const btn = document.querySelector('.profile-card[data-perfil="recepcion"]');
        if (btn) btn.style.pointerEvents = 'none';
        try {
            const res = await fetchJSON(`${API_BASE}/login.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ perfil: 'recepcion' })
            });
            if (res.data && res.data.success) {
                window.location.href = res.data.redirect;
            } else {
                mostrarErrorLogin(res.data?.message || 'No se pudo ingresar');
            }
        } catch (e) {
            mostrarErrorLogin('Error de conexion');
        } finally {
            if (btn) btn.style.pointerEvents = '';
        }
        return;
    }

    // Dependencias / Administrador: abrir formulario de credenciales
    perfilSeleccionado = perfil;
    document.getElementById('login-titulo').textContent =
        perfil === 'admin' ? 'Administrador' : 'Ingreso de Dependencia';
    document.getElementById('login-usuario').value = '';
    document.getElementById('login-password').value = '';
    limpiarErrorLogin();
    document.getElementById('link-olvide').style.display =
        perfil === 'dependencia' ? '' : 'none';
    document.getElementById('modal-login').classList.add('active');
    setTimeout(() => document.getElementById('login-usuario').focus(), 100);
}

// Enviar credenciales
async function enviarLogin() {
    const usuario = document.getElementById('login-usuario').value.trim();
    const password = document.getElementById('login-password').value;

    if (!usuario || !password) {
        mostrarErrorLogin('Ingrese usuario y contrasena');
        return;
    }

    const btn = document.getElementById('btn-login');
    btn.disabled = true;
    btn.textContent = 'Ingresando...';
    limpiarErrorLogin();

    try {
        const res = await fetchJSON(`${API_BASE}/login.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ perfil: perfilSeleccionado, usuario, password })
        });
        if (res.data && res.data.success) {
            window.location.href = res.data.redirect;
        } else {
            mostrarErrorLogin(res.data?.message || 'No se pudo iniciar sesion');
        }
    } catch (e) {
        mostrarErrorLogin('Error de conexion');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Ingresar';
    }
}

// Olvidaste tu contrasena: aviso de gestion presencial
function mostrarOlvido() {
    cerrarLogin();
    document.getElementById('modal-olvido').classList.add('active');
}

function cerrarOlvido() {
    document.getElementById('modal-olvido').classList.remove('active');
}

function cerrarLogin() {
    document.getElementById('modal-login').classList.remove('active');
}

function mostrarErrorLogin(mensaje) {
    document.getElementById('login-error').innerHTML =
        `<div class="alert alert-error">${mensaje}</div>`;
}

function limpiarErrorLogin() {
    document.getElementById('login-error').innerHTML = '';
}

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