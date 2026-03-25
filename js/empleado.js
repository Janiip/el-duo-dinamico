/**
 * Flujo empleado: carrito, pago, ticket y confirmación de venta.
 */
function initVenta() {
    carritoItems = [];
    pagoSel = null;
    sabCatActiva = {};
    document.getElementById('v-hrol').textContent = usuarioActual ? usuarioActual.nombre.toUpperCase() : 'EMPLEADO';
    document.querySelectorAll('.p-op').forEach(el => el.classList.remove('sel'));
    document.getElementById('v-err').style.display = 'none';
    agregarItem();
}

function agregarItem() {
    const idx = carritoItems.length;
    carritoItems.push({ accId: null, nombre: '', sabores: [], cant: 1, precio: 0 });
    sabCatActiva[idx] = sabCats[0] || '';
    renderCarrito();
}

function renderCarrito() {
    const accsDisp = accesorios.filter(a => a.stock > 0);
    document.getElementById('carrito').innerHTML = carritoItems.map((it, i) => {
        const acc = accesorios.find(a => a.id === it.accId);
        const necesitaSab = acc && TIPOS_CON_SABOR.includes(acc.tipo);
        const catAct = sabCatActiva[i] || sabCats[0] || '';
        const sub = (it.precio || 0) * (it.cant || 1);

        const sabHtml = necesitaSab ? `
      <div class="sab-wrap">
        <div class="sab-tit">🍦 ELEGIR SABOR/ES</div>
        <div class="sab-cat-tabs">
          ${sabCats.map(c => `<button class="sct${c === catAct ? ' activo' : ''}" onclick="cambiarCatSab(${i},'${c.replace(/'/g, "\\'")}')">
            ${c}
          </button>`).join('')}
        </div>
        ${sabCats.map(c => `
          <div class="sab-panel${c === catAct ? ' visible' : ''}">
            ${sabores.filter(s => s.cat === c).map(s => `
              <div class="chip${it.sabores.includes(s.nombre) ? ' sel' : ''}${s.stock === 0 ? ' agotado' : ''}"
                onclick="${s.stock > 0 ? `toggleSabor(${i},'${s.nombre.replace(/'/g, "\\'")}')` : ''}">${s.nombre}${s.stock === 0 ? ' ✗' : ''}</div>
            `).join('') || '<span style="font-size:.75rem;color:#aaa;">Sin sabores en esta sección</span>'}
          </div>`).join('')}
        <div class="sab-sel">${it.sabores.length ? '✅ ' + it.sabores.join(' · ') : 'Ningún sabor seleccionado'}</div>
      </div>`: '';

        return `
    <div class="item-v" id="item-${i}">
      <div class="item-hd">
        <div class="item-num">PRODUCTO ${i + 1}</div>
        <div style="display:flex;align-items:center;gap:7px;">
          <div class="item-sub">$${sub.toLocaleString()}</div>
          ${carritoItems.length > 1 ? `<button class="ib" onclick="quitarItem(${i})" style="color:var(--rojo);font-weight:800;" title="Quitar">✕</button>` : ''}
        </div>
      </div>
      <div class="v-fila">
        <div class="v-lbl">PRODUCTO</div>
        <select class="v-inp" onchange="cambiarProd(${i},this)">
          <option value="">SELECCIONAR</option>
          ${accsDisp.map(a => `<option value="${a.id}"${it.accId === a.id ? ' selected' : ''}>${a.nombre} — $${a.precio.toLocaleString()}</option>`).join('')}
        </select>
      </div>
      ${necesitaSab ? sabHtml : ''}
      <div class="v-fila">
        <div class="v-lbl">CANTIDAD</div>
        <input class="v-inp" type="number" min="1" step="1" value="${it.cant}" oninput="cambiarCant(${i},this.value)">
      </div>
      <div class="v-fila">
        <div class="v-lbl">PRECIO UNIT.</div>
        <input class="v-inp" readonly value="${it.precio ? '$' + it.precio.toLocaleString() : '—'}">
      </div>
    </div>`;
    }).join('');
    actualizarTotal();
}

function cambiarProd(i, sel) {
    const id = parseInt(sel.value) || null;
    const acc = id ? accesorios.find(a => a.id === id) : null;
    carritoItems[i].accId = id;
    carritoItems[i].nombre = acc ? acc.nombre : '';
    carritoItems[i].precio = acc ? acc.precio : 0;
    carritoItems[i].sabores = [];
    renderCarrito();
}

function cambiarCant(i, val) {
    carritoItems[i].cant = parseInt(val) || 1;
    actualizarTotal();
    const el = document.getElementById('item-' + i);
    if (el) {
        const s = el.querySelector('.item-sub');
        if (s) s.textContent = '$' + ((carritoItems[i].precio || 0) * (carritoItems[i].cant || 1)).toLocaleString();
    }
}

function cambiarCatSab(i, cat) {
    sabCatActiva[i] = cat;
    renderCarrito();
}

function toggleSabor(i, nombre) {
    const item = carritoItems[i];
    if (item.sabores.includes(nombre)) item.sabores = item.sabores.filter(s => s !== nombre);
    else item.sabores.push(nombre);
    renderCarrito();
}

function quitarItem(i) {
    carritoItems.splice(i, 1);
    const nc = {};
    Object.keys(sabCatActiva).forEach(k => {
        const ki = parseInt(k);
        if (ki < i) nc[ki] = sabCatActiva[k];
        else if (ki > i) nc[ki - 1] = sabCatActiva[k];
    });
    sabCatActiva = nc;
    renderCarrito();
}

function actualizarTotal() {
    const t = carritoItems.reduce((a, it) => a + (it.precio || 0) * (it.cant || 1), 0);
    document.getElementById('v-total').textContent = '$' + t.toLocaleString();
}

function selPago(tipo) {
    pagoSel = tipo;
    document.getElementById('pago-ef').classList.toggle('sel', tipo === 'efectivo');
    document.getElementById('pago-tr').classList.toggle('sel', tipo === 'transferencia');
}

function irTicket() {
    const err = document.getElementById('v-err');
    if (!carritoItems.length) { mostrarErr(err, 'Agregá al menos un producto.'); return; }
    for (let i = 0; i < carritoItems.length; i++) {
        const it = carritoItems[i];
        if (!it.accId) { mostrarErr(err, `Seleccioná un producto en el item ${i + 1}.`); return; }
        if (!it.cant || it.cant < 1) { mostrarErr(err, `Cantidad inválida en item ${i + 1}.`); return; }
        const acc = accesorios.find(a => a.id === it.accId);
        if (it.cant > acc.stock) { mostrarErr(err, `Stock insuficiente para "${acc.nombre}". Disponible: ${acc.stock}`); return; }
        if (TIPOS_CON_SABOR.includes(acc.tipo) && it.sabores.length === 0) { mostrarErr(err, `Elegí al menos un sabor para "${acc.nombre}".`); return; }
    }
    if (!pagoSel) { mostrarErr(err, 'Seleccioná el método de pago.'); return; }

    const total = carritoItems.reduce((a, it) => a + (it.precio || 0) * (it.cant || 1), 0);
    const nroPreview = getNroVentaDia() + 1;

    const fechaVenta = fechaHoyLocal();
    ventaBorrador = {
        items: carritoItems.map(it => ({ ...it, sabores: [...it.sabores] })),
        total,
        pago: pagoSel === 'efectivo' ? 'Efectivo' : 'Transferencia',
        empleado: usuarioActual.nombre,
        fecha: fechaVenta,
        nroVenta: nroPreview
    };

    const itemsHtmlEmp = ventaBorrador.items.map(it => `
    <div class="tkt-item">
      <div class="tkt-item-n">${it.nombre} x${it.cant} — $${(it.precio * it.cant).toLocaleString()}</div>
      ${it.sabores && it.sabores.length ? `<div class="tkt-item-s">🍦 ${it.sabores.join(' · ')}</div>` : ''}
    </div>`).join('');

    document.getElementById('tkt-emp').innerHTML = `
    <div class="ticket-label tl-emp">📋 COPIA EMPLEADO</div>
    <div class="tkt-tit">🍦 DAJANA HELADOS</div>
    <div class="tkt-num">Venta del día #${nroPreview}</div>
    <div class="tkt-row"><span>Empleado:</span><span>${ventaBorrador.empleado}</span></div>
    <div class="tkt-row"><span>Fecha:</span><span>${ff(ventaBorrador.fecha)}</span></div>
    <div style="margin:9px 0;">${itemsHtmlEmp}</div>
    <div class="tkt-total">TOTAL: $${total.toLocaleString()}</div>
    <div class="tkt-row" style="margin-top:5px;"><span>Pago:</span><span>${ventaBorrador.pago}</span></div>
  `;

    document.getElementById('tkt-cli').innerHTML = `
    <div class="ticket-label tl-cli">🛒 TICKET CLIENTE</div>
    <div class="tkt-tit">🍦 DAJANA HELADOS</div>
    <div class="tkt-num">Orden #${nroPreview}</div>
    <div class="tkt-row"><span>Fecha:</span><span>${ff(ventaBorrador.fecha)}</span></div>
    <div style="margin:9px 0;">${itemsHtmlEmp}</div>
    <div class="tkt-total">TOTAL: $${total.toLocaleString()}</div>
    <div class="tkt-row" style="margin-top:5px;"><span>Pago:</span><span>${ventaBorrador.pago}</span></div>
  `;

    document.querySelectorAll('.pantalla').forEach(p => p.classList.remove('activa'));
    document.getElementById('s-ticket').classList.add('activa');
}

function confirmarVenta() {
    if (!ventaBorrador) return;
    const nro = incrementarNroVenta();
    const fechaRegistro = fechaHoyLocal();
    ventaBorrador.items.forEach(it => {
        accesorios.find(a => a.id === it.accId).stock -= it.cant;
    });
    ventas.push({
        id: nextVentaId++,
        nroVenta: nro,
        fecha: fechaRegistro,
        total: ventaBorrador.total,
        pago: ventaBorrador.pago,
        empleado: ventaBorrador.empleado,
        items: ventaBorrador.items.map(it => ({ nombre: it.nombre, sabores: it.sabores, cant: it.cant, precio: it.precio }))
    });
    document.getElementById('cf-num').textContent = `#${nro} del día`;
    document.getElementById('cf-total').textContent = '$' + ventaBorrador.total.toLocaleString();
    document.getElementById('cf-pago').textContent = ventaBorrador.pago;
    ventaBorrador = null;
    irA('s-conf');
}

function cancelarTicket() {
    ventaBorrador = null;
    irA('s-emp');
}

function nuevaVenta() {
    irA('s-venta');
}
