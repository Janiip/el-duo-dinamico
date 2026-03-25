/**
 * Utilidades generales, fecha local, modales de confirmación.
 */
function fechaHoyLocal() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function getNroVentaDia() {
    const hoy = fechaHoyLocal();
    if (fechaUltimaVenta !== hoy) { nroVentaDia = 0; fechaUltimaVenta = hoy; }
    return nroVentaDia;
}

function incrementarNroVenta() {
    const hoy = fechaHoyLocal();
    if (fechaUltimaVenta !== hoy) { nroVentaDia = 0; fechaUltimaVenta = hoy; }
    nroVentaDia++;
    return nroVentaDia;
}

function ff(iso) {
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function mostrarErr(el, msg) {
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 3500);
}

function abrirModal(id) {
    document.getElementById(id).classList.add('abierto');
}

function cerrarModal(id) {
    document.getElementById(id).classList.remove('abierto');
}

function confirmar(msg, cb) {
    document.getElementById('m-conf-msg').textContent = msg;
    document.getElementById('m-conf-ok').onclick = () => { cb(); cerrarModal('m-confirm'); };
    abrirModal('m-confirm');
}
