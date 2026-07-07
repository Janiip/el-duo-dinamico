(() => {
  const boot = window.EMPLEADO_BOOT || {};
  const accesoriosData = Array.isArray(boot.accesoriosData) ? boot.accesoriosData : [];
  const LOW_STOCK_THRESHOLD = Number(boot.LOW_STOCK_THRESHOLD ?? 3);

  function formatMoneyJS(value) {
    return (
      '$' +
      Number(value).toLocaleString('es-AR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      })
    );
  }

  function sanitizeJS(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function updateRowPrice(row) {
    const productSelect = row.querySelector('.tipo-producto');
    const quantityInput = row.querySelector('.cantidad-producto');
    const priceInput = row.querySelector('.precio-unitario');
    const selectedOption = productSelect?.selectedOptions?.[0];

    if (!selectedOption || !selectedOption.value) {
      if (priceInput) priceInput.value = '';
      recalculateTotal();
      return;
    }

    const price = parseFloat(selectedOption.dataset.price) || 0;
    const quantity = Math.max(1, parseInt(quantityInput?.value ?? '1', 10) || 1);
    if (priceInput) priceInput.value = formatMoneyJS(price);
    recalculateTotal();
  }

  function recalculateTotal() {
    const rows = document.querySelectorAll('.item-producto');
    let total = 0;
    rows.forEach((row) => {
      const productSelect = row.querySelector('.tipo-producto');
      const quantityInput = row.querySelector('.cantidad-producto');
      const selectedOption = productSelect?.selectedOptions?.[0];
      if (!selectedOption || !selectedOption.value) return;
      const price = parseFloat(selectedOption.dataset.price) || 0;
      const quantity = Math.max(1, parseInt(quantityInput?.value ?? '1', 10) || 1);
      total += price * quantity;
    });
    const totalNode = document.querySelector('.caja-total span');
    if (totalNode) totalNode.textContent = formatMoneyJS(total);
  }

  function updateFlavorInputs(row) {
    let hiddenContainer = row.querySelector('.hidden-flavors');
    if (!hiddenContainer) {
      hiddenContainer = document.createElement('div');
      hiddenContainer.className = 'hidden-flavors';
      hiddenContainer.style.display = 'none';
      row.appendChild(hiddenContainer);
    }
    hiddenContainer.innerHTML = '';
    const selected = row.querySelectorAll('.sabor-boton.seleccionado');
    selected.forEach((button) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'sabores[' + (row.dataset.index || 0) + '][]';
      input.value = button.dataset.sabor;
      hiddenContainer.appendChild(input);
    });
  }

  function setupFlavorButtons(row) {
    const flavorButtons = row.querySelectorAll('.sabor-boton');
    flavorButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (button.disabled) return;
        const selected = row.querySelectorAll('.sabor-boton.seleccionado');
        if (!button.classList.contains('seleccionado') && selected.length >= 3) {
          return;
        }
        button.classList.toggle('seleccionado');
        updateFlavorInputs(row);
      });
    });
    updateFlavorInputs(row);

    const toggleBtn = row.querySelector('.boton-ver-inactivos');
    const listaInactivos = row.querySelector('.sabores-inactivos-lista');
    if (toggleBtn && listaInactivos) {
      const baseLabel = toggleBtn.dataset.label || toggleBtn.textContent.trim().replace(/^[▼▲]\s*/, '');
      toggleBtn.dataset.label = baseLabel;
      toggleBtn.addEventListener('click', () => {
        const abierto = listaInactivos.style.display === 'block';
        listaInactivos.style.display = abierto ? 'none' : 'block';
        toggleBtn.textContent = (abierto ? '▼ ' : '▲ ') + baseLabel;
      });
      toggleBtn.textContent = '▼ ' + baseLabel;
    }
  }

  function setupProductRow(row) {
    const productSelect = row.querySelector('.tipo-producto');
    const quantityInput = row.querySelector('.cantidad-producto');
    const removeButton = row.querySelector('.boton-eliminar-item');

    if (productSelect) productSelect.addEventListener('change', () => updateRowPrice(row));
    if (quantityInput) {
      quantityInput.addEventListener('input', () => {
        if (quantityInput.value === '' || parseInt(quantityInput.value, 10) < 1) {
          quantityInput.value = '1';
        }
        updateRowPrice(row);
      });
    }
    setupFlavorButtons(row);

    if (removeButton) {
      removeButton.addEventListener('click', () => {
        const rows = document.querySelectorAll('.item-producto');
        if (rows.length === 1) return;
        row.remove();
        updateProductNumbers();
        recalculateTotal();
      });
    }
  }

  function updateProductNumbers() {
    document.querySelectorAll('.item-producto').forEach((row, index) => {
      row.dataset.index = String(index + 1);
    });
  }

  function setupInactiveToggle() {
    const toggleBtn = document.querySelector('.panel-sabores-inactivos .boton-ver-inactivos');
    const listaInactivos = document.querySelector('.panel-sabores-inactivos .sabores-inactivos-lista');
    if (!toggleBtn || !listaInactivos) return;

    const label = toggleBtn.dataset.label || toggleBtn.textContent.trim().replace(/^[▼▲]\s*/, '');
    toggleBtn.dataset.label = label;

    toggleBtn.addEventListener('click', () => {
      const abierto = listaInactivos.style.display === 'block';
      listaInactivos.style.display = abierto ? 'none' : 'block';
      toggleBtn.textContent = (abierto ? '▼ ' : '▲ ') + label;
    });
    toggleBtn.textContent = '▼ ' + label;
  }

  function addProductRow() {
    const container = document.getElementById('carrito');
    const template = document.querySelector('.item-producto');
    if (!container || !template) return;

    const newRow = template.cloneNode(true);
    newRow.dataset.index = String(container.querySelectorAll('.item-producto').length + 1);

    const productSelect = newRow.querySelector('.tipo-producto');
    if (productSelect) productSelect.selectedIndex = 0;
    const priceInput = newRow.querySelector('.precio-unitario');
    if (priceInput) priceInput.value = '';
    const quantityInput = newRow.querySelector('.cantidad-producto');
    if (quantityInput) quantityInput.value = '1';

    newRow.querySelectorAll('.sabor-boton').forEach((button) => button.classList.remove('seleccionado'));
    newRow.querySelectorAll('.sabor-boton').forEach((button) => {
      button.disabled = button.classList.contains('agotado');
    });

    const hiddenContainer = newRow.querySelector('.hidden-flavors');
    if (hiddenContainer) hiddenContainer.innerHTML = '';

    const toggleBtn = newRow.querySelector('.boton-ver-inactivos');
    const listaInactivos = newRow.querySelector('.sabores-inactivos-lista');
    if (toggleBtn) {
      const label = toggleBtn.dataset.label || toggleBtn.textContent.trim().replace(/^[▼▲]\s*/, '');
      toggleBtn.dataset.label = label;
      toggleBtn.textContent = label;
    }
    if (listaInactivos) {
      listaInactivos.style.display = 'none';
    }

    container.appendChild(newRow);
    setupProductRow(newRow);
    updateProductNumbers();
  }

  function renderTicket() {
    const rows = document.querySelectorAll('.item-producto');
    const items1 = document.getElementById('ticket-items-1');
    const items2 = document.getElementById('ticket-items-2');
    if (!items1 || !items2) return;

    items1.innerHTML = '';
    items2.innerHTML = '';
    let total = 0;

    rows.forEach((row) => {
      const productSelect = row.querySelector('.tipo-producto');
      const quantityInput = row.querySelector('.cantidad-producto');
      const selectedOption = productSelect?.selectedOptions?.[0];
      if (!selectedOption || !selectedOption.value) return;

      const quantity = Math.max(1, parseInt(quantityInput?.value ?? '1', 10) || 1);
      const price = parseFloat(selectedOption.dataset.price) || 0;
      const name = selectedOption.dataset.name || '';
      const subtotal = price * quantity;
      total += subtotal;

      const selectedFlavors = Array.from(row.querySelectorAll('.sabor-boton.seleccionado')).map(
        (btn) => btn.dataset.sabor
      );
      const flavorText = selectedFlavors.length ? ' — ' + sanitizeJS(selectedFlavors.join(' / ')) : '';
      const itemHtml = `
        <div class="item-ticket">
          <div class="nombre-item-ticket">${sanitizeJS(name)} x${quantity} ${formatMoneyJS(price)}</div>
          <div class="sabores-item-ticket">${sanitizeJS(selectedOption.textContent)}${flavorText}</div>
        </div>`;

      items1.insertAdjacentHTML('beforeend', itemHtml);
      items2.insertAdjacentHTML('beforeend', itemHtml);
    });

    const total1 = document.getElementById('ticket-total');
    const total2 = document.getElementById('ticket-total-client');
    if (total1) total1.textContent = formatMoneyJS(total);
    if (total2) total2.textContent = formatMoneyJS(total);

    const now = new Date();
    const fecha1 = document.getElementById('ticket-fecha');
    const fecha2 = document.getElementById('ticket-fecha-client');
    if (fecha1) fecha1.textContent = now.toLocaleDateString('es-AR');
    if (fecha2) fecha2.textContent = now.toLocaleDateString('es-AR');

    const ticketNumber = Math.floor(Math.random() * 9000) + 1000;
    const num1 = document.getElementById('ticket-number');
    const num2 = document.getElementById('ticket-number-client');
    if (num1) num1.textContent = '#' + ticketNumber;
    if (num2) num2.textContent = '#' + ticketNumber;

    const metodoPago = document.getElementById('metodo_pago')?.value || 'EFECTIVO';
    const pago1 = document.getElementById('ticket-pago');
    const pago2 = document.getElementById('ticket-pago-client');
    if (pago1) pago1.textContent = metodoPago;
    if (pago2) pago2.textContent = metodoPago;
  }

  function setPaymentMethod(method) {
    const input = document.getElementById('metodo_pago');
    if (input) input.value = method;
    document.querySelectorAll('.opcion-pago').forEach((button) => {
      button.classList.toggle('pago-activo', button.dataset.payment === method);
    });
  }

  // ── IMPRESIÓN TICKET CLIENTE ────────────────────────────────────────────
  /**
   * Rellena el overlay de impresión con los datos del ticket del cliente
   * y llama a window.print().
   *
   * @param {Object} opts
   *   opts.orden   string   Número de orden/venta
   *   opts.fecha   string   Fecha formateada
   *   opts.total   string   Total formateado (ya con $)
   *   opts.pago    string   Método de pago
   *   opts.itemsHtml string HTML con los items (usa clases tp-item-*)
   */
  function buildAndPrint({ orden, fecha, total, pago, itemsHtml }) {
    const overlay = document.getElementById('print-ticket-overlay');
    if (!overlay) return;

    document.getElementById('pt-orden').textContent  = 'Orden #' + orden;
    document.getElementById('pt-fecha').textContent  = fecha;
    document.getElementById('pt-total').textContent  = total;
    document.getElementById('pt-pago').textContent   = pago;
    document.getElementById('pt-items').innerHTML    = itemsHtml;

    window.print();
  }

  /** Genera el HTML de items a partir del carrito activo (pantalla #s-ticket) */
  function getItemsHtmlFromCart() {
    const rows = document.querySelectorAll('.item-producto');
    let html = '';
    rows.forEach((row) => {
      const productSelect  = row.querySelector('.tipo-producto');
      const quantityInput  = row.querySelector('.cantidad-producto');
      const selectedOption = productSelect?.selectedOptions?.[0];
      if (!selectedOption || !selectedOption.value) return;

      const quantity = Math.max(1, parseInt(quantityInput?.value ?? '1', 10) || 1);
      const price    = parseFloat(selectedOption.dataset.price) || 0;
      const name     = selectedOption.dataset.name || '';
      const subtotal = price * quantity;

      const selectedFlavors = Array.from(row.querySelectorAll('.sabor-boton.seleccionado'))
        .map((btn) => btn.dataset.sabor);
      const flavorText = selectedFlavors.length ? selectedFlavors.join(' / ') : '';

      html += `<div style="margin-bottom:2mm;">
        <div class="tp-item-name">${sanitizeJS(name)} x${quantity} — ${formatMoneyJS(subtotal)}</div>
        ${flavorText ? `<div class="tp-item-sabores">${sanitizeJS(flavorText)}</div>` : ''}
      </div>`;
    });
    return html;
  }

  /** Genera el HTML de items desde el detalle histórico (#s-venta-detalle) */
  function getItemsHtmlFromDetail() {
    const container = document.getElementById('detalle-items-2');
    if (!container) return '';
    let html = '';
    container.querySelectorAll('.item-ticket').forEach((item) => {
      const nameEl   = item.querySelector('.nombre-item-ticket');
      const saborEl  = item.querySelector('.sabores-item-ticket');
      html += `<div style="margin-bottom:2mm;">
        <div class="tp-item-name">${nameEl ? nameEl.innerHTML : ''}</div>
        ${saborEl ? `<div class="tp-item-sabores">${saborEl.textContent}</div>` : ''}
      </div>`;
    });
    return html;
  }

  document.addEventListener('DOMContentLoaded', () => {
    // Si no estamos en la pestaña de venta, igual no pasa nada.
    document.querySelectorAll('.item-producto').forEach(setupProductRow);

    const addItemBtn = document.querySelector('.boton-agregar-item');
    if (addItemBtn) addItemBtn.addEventListener('click', addProductRow);

    document.querySelectorAll('.opcion-pago').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        setPaymentMethod(button.dataset.payment);
      });
    });

    setupInactiveToggle();

    const viewTicketBtn = document.getElementById('view-ticket');
    if (viewTicketBtn) {
      viewTicketBtn.addEventListener('click', (event) => {
        event.preventDefault();
        renderTicket();
        location.hash = '#s-ticket';
      });
    }

    // ── CONFIRMAR VENTA: guardar ticket en sessionStorage e imprimir ────────
    const submitSaleBtn = document.getElementById('submit-sale');
    if (submitSaleBtn) {
      submitSaleBtn.addEventListener('click', (event) => {
        event.preventDefault();
        const rows = document.querySelectorAll('.item-producto');
        const valid = Array.from(rows).some((row) => row.querySelector('.tipo-producto')?.value);
        if (!valid) {
          alert('Seleccione al menos un producto antes de registrar la venta.');
          location.hash = '#s-venta';
          return;
        }
        // Guardar datos del ticket para poder reimprimir después del redirect
        const ticketData = {
          orden : document.getElementById('ticket-number-client')?.textContent?.replace('#', '') || '—',
          fecha : document.getElementById('ticket-fecha-client')?.textContent || '—',
          total : document.getElementById('ticket-total-client')?.textContent || '$0',
          pago  : document.getElementById('ticket-pago-client')?.textContent || 'EFECTIVO',
          items : getItemsHtmlFromCart(),
        };
        try { sessionStorage.setItem('dajana_last_ticket', JSON.stringify(ticketData)); } catch(e) {}

        // Imprimir primero, luego enviar
        buildAndPrint({
          orden     : ticketData.orden,
          fecha     : ticketData.fecha,
          total     : ticketData.total,
          pago      : ticketData.pago,
          itemsHtml : ticketData.items,
        });

        // Pequeña pausa para que el diálogo de impresión se abra antes del redirect
        setTimeout(() => { document.getElementById('sale-form')?.submit(); }, 400);
      });
    }
    // ────────────────────────────────────────────────────────────────────────

    // Recalcular al cargar.
    recalculateTotal();
    setPaymentMethod('EFECTIVO');

    // ── AL LLEGAR A #s-conf: recuperar ticket y activar REIMPRIMIR ──────────
    if (boot.ventaConfirmada) {
      location.hash = '#s-conf';

      // Recuperar datos del ticket guardados antes del POST
      let saved = null;
      try { saved = JSON.parse(sessionStorage.getItem('dajana_last_ticket') || 'null'); } catch(e) {}

      const btnReimprimirConf = document.getElementById('reimprimir-conf');
      if (btnReimprimirConf && saved) {
        btnReimprimirConf.addEventListener('click', () => {
          buildAndPrint({
            orden     : saved.orden,
            fecha     : saved.fecha,
            total     : saved.total,
            pago      : saved.pago,
            itemsHtml : saved.items,
          });
        });
      }
    }
    // ────────────────────────────────────────────────────────────────────────

    // ── REIMPRIMIR desde historial (#s-venta-detalle) ────────────────────────
    const btnReimprimirDetalle = document.getElementById('reimprimir-detalle');
    if (btnReimprimirDetalle) {
      btnReimprimirDetalle.addEventListener('click', () => {
        buildAndPrint({
          orden     : btnReimprimirDetalle.dataset.orden || '—',
          fecha     : btnReimprimirDetalle.dataset.fecha || '—',
          total     : btnReimprimirDetalle.dataset.total || '$0',
          pago      : btnReimprimirDetalle.dataset.pago  || 'EFECTIVO',
          itemsHtml : getItemsHtmlFromDetail(),
        });
      });
    }
    // ────────────────────────────────────────────────────────────────────────

    // ── CONFIRMAR CIERRE DE CAJA ──────────────────────────────────────────
    const formCerrarCaja = document.getElementById('form-cerrar-caja');
    if (formCerrarCaja) {
      formCerrarCaja.addEventListener('submit', (event) => {
        const confirmado = window.confirm('¿Confirmás el cierre de caja? Una vez cerrada no se puede volver a abrir en el mismo día.');
        if (!confirmado) {
          event.preventDefault();
        }
      });
    }
    // ────────────────────────────────────────────────────────────────────────
  });
})();