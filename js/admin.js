/**
 * Panel admin: estadísticas, sabores, accesorios, stock, categorías.
 */
function renderPanel() {
    const hoy = fechaHoyLocal();
    const vh = ventas.filter(v => v.fecha === hoy);
    const sabBajo = sabores.filter(s => s.stock > 0 && s.stock < 3).length;
    const sabSin = sabores.filter(s => s.stock === 0).length;
    document.getElementById('panel-stats').innerHTML = `
    <div class="stat">💰 Ventas del día: <span>$${vh.reduce((a, v) => a + v.total, 0).toLocaleString()}</span></div>
    <div class="stat">🧾 Transacciones hoy: <span>${vh.length}</span></div>
    <div class="stat">🔢 Última venta del día: <span>#${nroVentaDia || '—'}</span></div>
    <div class="stat">⚠️ Sabores stock bajo (<3L): <span>${sabBajo}</span></div>
    <div class="stat">❌ Sabores sin stock: <span>${sabSin}</span></div>
    <div class="stat">📦 Total ventas registradas: <span>${ventas.length}</span></div>
  `;
}

function abrirModalCats(modo) {
    document.getElementById('m-cats-modo').value = modo;
    document.getElementById('m-cats-tit').textContent = modo === 'sabor' ? 'EDITAR SECCIONES DE SABORES' : 'EDITAR TIPOS DE ACCESORIOS';
    catsEditando = [...(modo === 'sabor' ? sabCats : accTipos)];
    document.getElementById('m-cats-nueva').value = '';
    document.getElementById('m-cats-err').style.display = 'none';
    renderCatList();
    abrirModal('m-cats');
}

function renderCatList() {
    document.getElementById('m-cats-list').innerHTML = catsEditando.map((c, i) => `
    <div class="cat-chip-edit">
      <span>${c}</span>
      <button onclick="quitarCat(${i})" title="Eliminar">✕</button>
    </div>`).join('');
}

function quitarCat(i) {
    catsEditando.splice(i, 1);
    renderCatList();
}

function agregarCat() {
    const inp = document.getElementById('m-cats-nueva');
    const val = inp.value.trim();
    const errEl = document.getElementById('m-cats-err');
    if (!val) { mostrarErr(errEl, 'Escribí un nombre.'); return; }
    if (catsEditando.includes(val)) { mostrarErr(errEl, 'Ya existe esa sección.'); return; }
    catsEditando.push(val);
    inp.value = '';
    errEl.style.display = 'none';
    renderCatList();
}

function guardarCats() {
    const modo = document.getElementById('m-cats-modo').value;
    if (catsEditando.length === 0) { mostrarErr(document.getElementById('m-cats-err'), 'Debe haber al menos una sección.'); return; }
    if (modo === 'sabor') sabCats = [...catsEditando];
    else accTipos = [...catsEditando];
    cerrarModal('m-cats');
    if (modo === 'sabor') renderSaboresAdmin();
    else renderAccesorios();
}

function renderSaboresAdmin(cat) {
    if (cat) sabAdminCatActiva = cat;
    if (!sabCats.includes(sabAdminCatActiva)) sabAdminCatActiva = sabCats[0] || '';
    document.getElementById('sab-admin-tabs').innerHTML = sabCats.map(c => `
    <button class="cat-tab${c === sabAdminCatActiva ? ' activo' : ''}" onclick="renderSaboresAdmin('${c}')">${c}</button>
  `).join('');
    const lista = sabores.filter(s => s.cat === sabAdminCatActiva);
    document.getElementById('sab-admin-body').innerHTML = `
    <div class="tw">
      <table>
        <thead><tr><th>NOMBRE</th><th>PRECIO/LITRO</th><th>STOCK (L)</th><th>✏️</th><th>🗑</th></tr></thead>
        <tbody>${lista.map(s => `
          <tr>
            <td>${s.nombre}</td>
            <td>$${s.precio.toLocaleString()}</td>
            <td>${s.stock} L</td>
            <td><button class="ib" onclick="abrirModalSabor(${s.id})">✏️</button></td>
            <td><button class="ib" onclick="eliminarSabor(${s.id})">🗑️</button></td>
          </tr>`).join('') || '<tr><td colspan="5" style="padding:15px;color:#999;">Sin sabores en esta sección.</td></tr>'}
        </tbody>
      </table>
    </div>`;
}

function abrirModalSabor(id) {
    const sel = document.getElementById('ms-cat');
    sel.innerHTML = sabCats.map(c => `<option value="${c}">${c}</option>`).join('');
    document.getElementById('m-sab-tit').textContent = id ? 'EDITAR SABOR' : 'AGREGAR SABOR';
    document.getElementById('ms-id').value = id || '';
    if (id) {
        const s = sabores.find(x => x.id === id);
        document.getElementById('ms-nombre').value = s.nombre;
        sel.value = s.cat;
        document.getElementById('ms-precio').value = s.precio;
        document.getElementById('ms-stock').value = s.stock;
    } else {
        document.getElementById('ms-nombre').value = '';
        document.getElementById('ms-precio').value = '';
        document.getElementById('ms-stock').value = '';
        sel.value = sabAdminCatActiva || sabCats[0] || '';
    }
    abrirModal('m-sabor');
}

function guardarSabor() {
    const id = document.getElementById('ms-id').value;
    const nombre = document.getElementById('ms-nombre').value.trim();
    const cat = document.getElementById('ms-cat').value;
    const precio = parseFloat(document.getElementById('ms-precio').value);
    const stock = parseFloat(document.getElementById('ms-stock').value);
    if (!nombre || !cat || isNaN(precio) || isNaN(stock)) { alert('Completá todos los campos.'); return; }
    if (id) Object.assign(sabores.find(s => s.id === parseInt(id)), { nombre, cat, precio, stock });
    else sabores.push({ id: nextSabId++, nombre, cat, precio, stock });
    cerrarModal('m-sabor');
    renderSaboresAdmin(cat);
}

function eliminarSabor(id) {
    const s = sabores.find(x => x.id === id);
    confirmar(`¿Eliminar el sabor "${s.nombre}"?`, () => {
        const cat = s.cat;
        sabores = sabores.filter(x => x.id !== id);
        renderSaboresAdmin(cat);
    });
}

function renderAccesorios() {
    document.getElementById('tabla-acc').innerHTML = accesorios.map(a => `
    <tr>
      <td>${a.nombre}</td><td>${a.tipo}</td>
      <td>$${a.precio.toLocaleString()}</td>
      <td>${a.stock}</td><td>${a.unidad}</td>
      <td><button class="ib" onclick="abrirModalAccesorio(${a.id})">✏️</button></td>
      <td><button class="ib" onclick="eliminarAccesorio(${a.id})">🗑️</button></td>
    </tr>`).join('');
}

function abrirModalAccesorio(id) {
    const sel = document.getElementById('ma-tipo');
    sel.innerHTML = accTipos.map(t => `<option value="${t}">${t}</option>`).join('');
    document.getElementById('m-acc-tit').textContent = id ? 'EDITAR ACCESORIO' : 'AGREGAR ACCESORIO';
    document.getElementById('ma-id').value = id || '';
    if (id) {
        const a = accesorios.find(x => x.id === id);
        document.getElementById('ma-nombre').value = a.nombre;
        sel.value = a.tipo;
        document.getElementById('ma-precio').value = a.precio;
        document.getElementById('ma-stock').value = a.stock;
        document.getElementById('ma-unidad').value = a.unidad;
    } else {
        document.getElementById('ma-nombre').value = '';
        document.getElementById('ma-precio').value = '';
        document.getElementById('ma-stock').value = '';
        document.getElementById('ma-unidad').value = 'unidades';
    }
    abrirModal('m-acc');
}

function guardarAccesorio() {
    const id = document.getElementById('ma-id').value;
    const nombre = document.getElementById('ma-nombre').value.trim();
    const tipo = document.getElementById('ma-tipo').value;
    const precio = parseFloat(document.getElementById('ma-precio').value);
    const stock = parseInt(document.getElementById('ma-stock').value);
    const unidad = document.getElementById('ma-unidad').value;
    if (!nombre || !tipo || isNaN(precio) || isNaN(stock)) { alert('Completá todos los campos.'); return; }
    if (id) Object.assign(accesorios.find(a => a.id === parseInt(id)), { nombre, tipo, precio, stock, unidad });
    else accesorios.push({ id: nextAccId++, nombre, tipo, precio, stock, unidad });
    cerrarModal('m-acc');
    renderAccesorios();
}

function eliminarAccesorio(id) {
    confirmar(`¿Eliminar "${accesorios.find(a => a.id === id).nombre}"?`, () => {
        accesorios = accesorios.filter(a => a.id !== id);
        renderAccesorios();
    });
}

function stockTab(btn, secId) {
    document.querySelectorAll('#s-stock .cat-tab').forEach(b => b.classList.remove('activo'));
    btn.classList.add('activo');
    document.querySelectorAll('#s-stock .sec').forEach(s => s.classList.remove('visible'));
    document.getElementById(secId).classList.add('visible');
}

function renderStockSab() {
    const sel = document.getElementById('stk-sab-cat');
    const selectedValue = sel.value; // Preservar la selección actual
    sel.innerHTML = '<option value="">Ver todos</option>' +
        sabCats.map(c => `<option value="${c}">${c}</option>`).join('');
    sel.value = selectedValue; // Restaurar la selección
    const catFilt = sel.value;
    const q = (document.getElementById('stk-sab-q').value || '').toLowerCase();
    let data = sabores;
    if (catFilt) data = data.filter(s => s.cat === catFilt);
    if (q) data = data.filter(s => s.nombre.toLowerCase().includes(q));
    document.getElementById('stk-tb-sab').innerHTML = data.map(s => {
        const cls = s.stock === 0 ? 'b-no' : s.stock < 3 ? 'b-low' : 'b-ok';
        const lbl = s.stock === 0 ? 'SIN STOCK' : s.stock < 3 ? 'BAJO ⚠️' : 'NORMAL ✓';
        return `<tr>
      <td>${s.nombre}</td><td>${s.cat}</td><td>${s.stock} L</td>
      <td><span class="badge ${cls}">${lbl}</span></td>
      <td><button class="ib" onclick="abrirEditStock(${s.id},'sabor')">✏️</button></td>
    </tr>`;
    }).join('') || '<tr><td colspan="5" style="padding:14px;color:#999;text-align:center;">Sin resultados.</td></tr>';
}

function renderStockAcc() {
    document.getElementById('stk-tb-acc').innerHTML = accesorios.map(a => {
        const cls = a.stock === 0 ? 'b-no' : a.stock < 5 ? 'b-low' : 'b-ok';
        const lbl = a.stock === 0 ? 'SIN STOCK' : a.stock < 5 ? 'BAJO ⚠️' : 'NORMAL ✓';
        return `<tr>
      <td>${a.nombre}</td><td>${a.tipo}</td>
      <td>${a.stock}</td><td>${a.unidad}</td>
      <td><span class="badge ${cls}">${lbl}</span></td>
      <td><button class="ib" onclick="abrirEditStock(${a.id},'acc')">✏️</button></td>
    </tr>`;
    }).join('');
}

function abrirEditStock(id, tipo) {
    const item = tipo === 'sabor' ? sabores.find(s => s.id === id) : accesorios.find(a => a.id === id);
    document.getElementById('mst-id').value = id;
    document.getElementById('mst-tipo').value = tipo;
    document.getElementById('mst-nombre').value = item.nombre;
    document.getElementById('mst-val').value = item.stock;
    document.getElementById('mst-ulbl').textContent = tipo === 'sabor' ? 'NUEVO STOCK (litros)' : 'NUEVO STOCK';
    abrirModal('m-stock');
}

function guardarStock() {
    const id = parseInt(document.getElementById('mst-id').value);
    const tipo = document.getElementById('mst-tipo').value;
    const val = parseFloat(document.getElementById('mst-val').value);
    if (isNaN(val) || val < 0) { alert('Valor inválido.'); return; }
    if (tipo === 'sabor') sabores.find(s => s.id === id).stock = val;
    else accesorios.find(a => a.id === id).stock = val;
    cerrarModal('m-stock');
    renderStockSab(); renderStockAcc();
}

// Event listeners para filtros de stock (sabores)
document.addEventListener('DOMContentLoaded', function() {
    const buscadorSabores = document.getElementById('stk-sab-q');
    const filtroCategoria = document.getElementById('stk-sab-cat');
    
    if (buscadorSabores) {
        buscadorSabores.addEventListener('input', renderStockSab);
    }
    if (filtroCategoria) {
        filtroCategoria.addEventListener('change', renderStockSab);
    }
});
