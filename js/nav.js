/**
 * Navegación entre pantallas (.pantalla) y callbacks al cambiar de vista.
 * Debe cargarse después de admin.js y empleado.js (usa renderPanel, initVenta, etc.).
 */
function irA(id, modo) {
    document.querySelectorAll('.pantalla').forEach(p => p.classList.remove('activa'));
    document.getElementById(id).classList.add('activa');
    if (id === 's-panel') renderPanel();
    if (id === 's-sabores') renderSaboresAdmin();
    if (id === 's-accesorios') renderAccesorios();
    if (id === 's-stock') { renderStockSab(); renderStockAcc(); }
    if (id === 's-historial') {
        if (modo) histModo = modo;
        else if (document.getElementById('s-panel')) histModo = 'admin';
        else if (document.getElementById('s-emp')) histModo = 'empleado';
        setupHist();
    }
    if (id === 's-venta') initVenta();
}

function volverHist() {
    irA(histModo === 'admin' ? 's-panel' : 's-emp');
}
