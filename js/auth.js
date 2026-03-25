/**
 * Login, logout y sesión (sessionStorage).
 */
const USUARIOS = [
    { user: 'admin', pass: 'admin', rol: 'admin', nombre: 'Administrador' },
    { user: 'empleado', pass: 'empleado', rol: 'empleado', nombre: 'Juan' },
    { user: 'ana', pass: 'ana', rol: 'empleado', nombre: 'Ana' },
];

function login() {
    const u = document.getElementById('inp-u').value.trim().toLowerCase();
    const p = document.getElementById('inp-p').value.trim();
    const err = document.getElementById('lg-err');
    const usr = USUARIOS.find(x => x.user === u && x.pass === p);
    if (!usr) { err.style.display = 'block'; return; }
    err.style.display = 'none';
    usuarioActual = usr;
    sessionStorage.setItem('dajana_user', JSON.stringify(usr));
    if (usr.rol === 'admin') {
        window.location.href = 'admin.html';
        return;
    }
    window.location.href = 'empleado.html';
}

function logout() {
    usuarioActual = null;
    sessionStorage.removeItem('dajana_user');
    const inpU = document.getElementById('inp-u');
    const inpPass = document.getElementById('inp-p');
    if (inpU) inpU.value = '';
    if (inpPass) inpPass.value = '';
    if (document.getElementById('s-login')) {
        irA('s-login');
    } else {
        window.location.href = 'index.html';
    }
}

function initAuth() {
    const loginScreen = document.getElementById('s-login');
    const adminPanel = document.getElementById('s-panel');
    const empHome = document.getElementById('s-emp');

    let saved = null;
    try {
        saved = JSON.parse(sessionStorage.getItem('dajana_user') || 'null');
    } catch (e) {
        saved = null;
    }

    if (loginScreen) return;

    if (adminPanel) {
        if (!saved || saved.rol !== 'admin') {
            window.location.replace('index.html');
            return;
        }
        usuarioActual = saved;
        document.querySelectorAll('.pantalla').forEach(p => p.classList.remove('activa'));
        document.getElementById('s-panel').classList.add('activa');
        renderPanel();
        return;
    }

    if (empHome) {
        if (!saved || saved.rol !== 'empleado') {
            window.location.replace('index.html');
            return;
        }
        usuarioActual = saved;
        const bv = document.getElementById('emp-bv');
        const hr = document.getElementById('emp-hrol');
        if (bv) bv.textContent = `¡Bienvenida/o, ${saved.nombre}! 🍦`;
        if (hr) hr.textContent = saved.nombre.toUpperCase();
        document.querySelectorAll('.pantalla').forEach(p => p.classList.remove('activa'));
        document.getElementById('s-emp').classList.add('activa');
    }
}
