/**
 * Historial de ventas (admin y empleado) + detalle.
 */
function setupHist() {
    document.getElementById('hist-rol').textContent = histModo === 'admin' ? 'ADMIN' : usuarioActual.nombre.toUpperCase();
    document.getElementById('hist-tit').textContent = histModo === 'admin' ? 'HISTORIAL DE VENTAS' : 'MIS VENTAS';
    limpFilt();
}

function apFilt() {
    const htEl = document.getElementById('ht');
    const hfEl = document.getElementById('hf');
    const tb = document.getElementById('hist-tb');
    if (!htEl || !hfEl || !tb) return;

    const txt = (htEl.value || '').toLowerCase().trim();
    const fecha = hfEl.value;
    let data = ventas;

    const modoEmp = histModo === 'empleado' || (document.getElementById('s-emp') && !document.getElementById('s-panel'));
    if (modoEmp && usuarioActual) {
        data = data.filter(v => v.empleado === usuarioActual.nombre);
    }

    if (txt) {
        data = data.filter(v => {
            const emp = (v.empleado || '').toLowerCase();
            const pago = (v.pago || '').toLowerCase();
            const nro = String(v.nroVenta != null ? v.nroVenta : v.id);
            return emp.includes(txt) || (v.fecha && v.fecha.includes(txt)) || pago.includes(txt) || nro.includes(txt);
        });
    }
    if (fecha) data = data.filter(v => v.fecha === fecha);
    renderHist(data);
}

function limpFilt() {
    const htEl = document.getElementById('ht');
    const hfEl = document.getElementById('hf');
    if (htEl) htEl.value = '';
    if (hfEl) hfEl.value = '';
    apFilt();
}

function renderHist(data) {
    const tb = document.getElementById('hist-tb');
    if (!data.length) {
        tb.innerHTML = '<tr><td colspan="7" style="padding:16px;color:#999;text-align:center;">Sin ventas.</td></tr>';
        return;
    }
    tb.innerHTML = data.map(v => `
    <tr>
      <td><strong>#${v.nroVenta || v.id}</strong></td>
      <td>${ff(v.fecha)}</td><td>$${v.total.toLocaleString()}</td>
      <td>${v.pago}</td><td>${v.empleado}</td>
      <td><button class="ib" onclick="verDetalle(${v.id})">👁️</button></td>
      <td><button class="ib" onclick="elimVenta(${v.id})">🗑️</button></td>
    </tr>`).join('');
}

function elimVenta(id) {
    confirmar(`¿Eliminar venta #${id}?`, () => { ventas = ventas.filter(v => v.id !== id); apFilt(); });
}

function verDetalle(id) {
    const v = ventas.find(x => x.id === id);
    const filas = v.items.map(it => `
    <tr>
      <td style="text-align:left;padding:8px 9px;">
        ${it.nombre}
        ${it.sabores && it.sabores.length ? '<br><small style="color:#888;font-style:italic;">' + it.sabores.join(' · ') + '</small>' : ''}
      </td>
      <td>${it.cant}</td>
      <td>$${it.precio.toLocaleString()}</td>
      <td>$${(it.precio * it.cant).toLocaleString()}</td>
    </tr>`).join('');
    document.getElementById('det-cont').innerHTML = `
    <div class="det-info">Venta del día <span>#${v.nroVenta || v.id}</span></div>
    <div class="det-info">Fecha: <span>${ff(v.fecha)}</span></div>
    <div class="det-info">Empleado: <span>${v.empleado}</span></div>
    <div class="tw" style="margin:12px 0;">
      <table><thead><tr><th style="text-align:left;">PRODUCTO</th><th>CANT.</th><th>PRECIO</th><th>SUBTOTAL</th></tr></thead>
      <tbody>${filas}</tbody></table>
    </div>
    <div class="det-info" style="font-size:1rem;">Total: <span>$${v.total.toLocaleString()}</span></div>
    <div class="det-info">Pago: <span>${v.pago}</span></div>
  `;
    document.getElementById('det-rol').textContent = histModo === 'admin' ? 'ADMIN' : usuarioActual.nombre.toUpperCase();
    document.querySelectorAll('.pantalla').forEach(p => p.classList.remove('activa'));
    document.getElementById('s-detalle').classList.add('activa');
}
