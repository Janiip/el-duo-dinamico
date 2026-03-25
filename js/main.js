/**
 * Listeners del DOM y arranque de sesión. Cargar al final.
 */
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) cerrarModal(m.id); });
});

const inpPLogin = document.getElementById('inp-p');
if (inpPLogin) inpPLogin.addEventListener('keydown', e => { if (e.key === 'Enter') login(); });

const stkCatEl = document.getElementById('stk-sab-cat');
const stkQEl = document.getElementById('stk-sab-q');
if (stkCatEl) stkCatEl.addEventListener('change', renderStockSab);
if (stkQEl) stkQEl.addEventListener('input', renderStockSab);

const histTxtEl = document.getElementById('ht');
const histFechaEl = document.getElementById('hf');
if (histTxtEl) {
    histTxtEl.addEventListener('input', apFilt);
    histTxtEl.addEventListener('change', apFilt);
}
if (histFechaEl) histFechaEl.addEventListener('change', apFilt);

initAuth();
