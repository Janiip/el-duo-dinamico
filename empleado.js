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

    const viewTicketBtn = document.getElementById('view-ticket');
    if (viewTicketBtn) {
      viewTicketBtn.addEventListener('click', (event) => {
        event.preventDefault();
        renderTicket();
        location.hash = '#s-ticket';
      });
    }

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
        document.getElementById('sale-form')?.submit();
      });
    }

    // Recalcular al cargar.
    recalculateTotal();
    setPaymentMethod('EFECTIVO');

    if (boot.ventaConfirmada) {
      location.hash = '#s-conf';
    }
  });
})();
