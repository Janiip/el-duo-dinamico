function updateStockTabs() {
    const sabRadio = document.getElementById('stk-tab-sab');
    const accRadio = document.getElementById('stk-tab-acc');
    const sabSection = document.getElementById('stk-sab');
    const accSection = document.getElementById('stk-acc');
    const sabLabel = document.querySelector('label[for="stk-tab-sab"]');
    const accLabel = document.querySelector('label[for="stk-tab-acc"]');

    if (sabRadio.checked) {
        sabSection.style.display = 'block';
        accSection.style.display = 'none';
        sabLabel.classList.add('activo');
        accLabel.classList.remove('activo');
    } else {
        sabSection.style.display = 'none';
        accSection.style.display = 'block';
        sabLabel.classList.remove('activo');
        accLabel.classList.add('activo');
    }
}

function filterSabores() {
    const select = document.getElementById('stock-category-filter');
    const search = document.getElementById('search-sabores');
    const category = select.value.toLowerCase();
    const query = search.value.trim().toLowerCase();
    const rows = document.querySelectorAll('#stk-sab tbody tr');

    rows.forEach(row => {
        const name = row.cells[0]?.textContent.toLowerCase() || '';
        const type = row.dataset.categoria?.toLowerCase() || '';
        const matchesCategory = !category || type === category;
        const matchesSearch = !query || name.includes(query) || type.includes(query);
        row.style.display = matchesCategory && matchesSearch ? '' : 'none';
    });
}

function filterVentas() {
    const searchInput = document.getElementById('search-ventas');
    const dateInput = document.getElementById('filter-fecha');
    const query = searchInput.value.trim().toLowerCase();
    const filterDate = dateInput.value;
    const rows = document.querySelectorAll('#s-historial table tbody tr');

    rows.forEach(row => {
        if (row.querySelector('td strong') === null) {
            return;
        }

        const cells = Array.from(row.querySelectorAll('td'));
        const dateText = cells[1]?.textContent.trim() || '';
        const employee = cells[4]?.textContent.trim().toLowerCase() || '';
        const payment = cells[3]?.textContent.trim().toLowerCase() || '';
        const total = cells[2]?.textContent.trim().toLowerCase() || '';
        const idText = cells[0]?.textContent.trim().toLowerCase() || '';
        const searchText = [idText, dateText.toLowerCase(), employee, payment, total].join(' ');

        const matchesQuery = !query || searchText.includes(query);
        const matchesDate = !filterDate || dateText.split('/').reverse().join('-') === filterDate;

        row.style.display = matchesQuery && matchesDate ? '' : 'none';
    });
}

function clearVentasFilters() {
    const searchInput = document.getElementById('search-ventas');
    const dateInput = document.getElementById('filter-fecha');
    searchInput.value = '';
    dateInput.value = '';
    filterVentas();
}

function updateFlavorSection(section) {
    const tabs = document.querySelectorAll('#sabores-tabs .pestana-categoria');
    const rows = document.querySelectorAll('#s-sabores tbody tr');

    tabs.forEach(tab => {
        tab.classList.toggle('activo', tab.dataset.section === section);
    });

    rows.forEach(row => {
        const rowSection = row.dataset.section?.toLowerCase();
        if (section === 'all' || rowSection === section) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function setupFlavorTabs() {
    const tabs = document.querySelectorAll('#sabores-tabs .pestana-categoria');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            updateFlavorSection(tab.dataset.section);
        });
    });
    const initialTab = document.querySelector('#sabores-tabs .pestana-categoria.activo');
    if (initialTab) {
        updateFlavorSection(initialTab.dataset.section || 'all');
    }
}

function toggleInactiveFlavorSection() {
    const button = document.getElementById('toggle-inactive-flavors');
    const section = document.getElementById('inactive-flavor-section');
    if (!button || !section) return;
    button.addEventListener('click', () => {
        const visible = section.style.display === 'block';
        section.style.display = visible ? 'none' : 'block';
        button.textContent = visible ? 'Mostrar/Ocultar' : 'Ocultar';
    });
}

function toggleInactiveAccesorioSection() {
    const button = document.getElementById('toggle-inactive-accesorios');
    const section = document.getElementById('inactive-accesorio-section');
    if (!button || !section) return;
    button.addEventListener('click', () => {
        const visible = section.style.display === 'block';
        section.style.display = visible ? 'none' : 'block';
        button.textContent = visible ? 'Mostrar/Ocultar' : 'Ocultar';
    });
}

function setupAddFlavorModal() {
    const openButton = document.getElementById('open-add-sabor-modal');
    const closeButton = document.getElementById('close-add-sabor-modal');
    const cancelButton = document.getElementById('cancel-add-sabor');
    const modal = document.getElementById('add-sabor-modal');

    if (!modal) return;

    const closeModal = () => {
        modal.style.display = 'none';
    };

    if (openButton) {
        openButton.addEventListener('click', () => {
            modal.style.display = 'flex';
        });
    }
    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }
    if (cancelButton) {
        cancelButton.addEventListener('click', closeModal);
    }
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });
}

function setupAddAccesorioModal() {
    const openButton = document.getElementById('open-add-accesorio-modal');
    const closeButton = document.getElementById('close-add-accesorio-modal');
    const cancelButton = document.getElementById('cancel-add-accesorio');
    const modal = document.getElementById('add-accesorio-modal');

    if (!modal) return;

    const closeModal = () => {
        modal.style.display = 'none';
    };

    if (openButton) {
        openButton.addEventListener('click', () => {
            modal.style.display = 'flex';
        });
    }
    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }
    if (cancelButton) {
        cancelButton.addEventListener('click', closeModal);
    }
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });
}

function handleFlavorEditButtons() {
    const editButtons = document.querySelectorAll('.edit-sabor-btn');
    const modal = document.getElementById('edit-sabor-modal');
    const closeButton = document.getElementById('close-edit-sabor-modal');
    const cancelButton = document.getElementById('cancel-edit-sabor');

    if (!modal) return;

    const closeModal = () => {
        modal.style.display = 'none';
    };

    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }
    if (cancelButton) {
        cancelButton.addEventListener('click', closeModal);
    }
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row) return;
            const id = row.dataset.id;
            const nombre = row.dataset.nombre || '';
            const tipo = row.dataset.tipo || '';
            const precio = row.dataset.precio || '';
            const stock = row.dataset.stock || '';

            document.getElementById('edit-sabor-id').value = id;
            document.getElementById('edit-sabor-nombre').value = nombre;
            document.getElementById('edit-sabor-tipo').value = tipo;
            document.getElementById('edit-sabor-precio').value = precio;
            document.getElementById('edit-sabor-stock').value = stock;
            modal.style.display = 'flex';
        });
    });
}

function handleFlavorDeleteButtons() {
    const deleteButtons = document.querySelectorAll('.delete-sabor-btn');
    const modal = document.getElementById('delete-sabor-modal');
    const closeButton = document.getElementById('close-delete-sabor-modal');
    const cancelButton = document.getElementById('cancel-delete-sabor');
    const confirmButton = document.getElementById('confirm-delete-sabor');
    const nameNode = document.getElementById('delete-sabor-nombre');
    const deleteForm = document.getElementById('flavor-delete-form');
    const deleteIdInput = document.getElementById('delete-sabor-id');

    if (!modal || !deleteForm || !deleteIdInput) return;

    const closeModal = () => {
        modal.style.display = 'none';
        modal.dataset.id = '';
    };

    if (closeButton) closeButton.addEventListener('click', closeModal);
    if (cancelButton) cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    if (confirmButton) {
        confirmButton.addEventListener('click', () => {
            const id = modal.dataset.id;
            if (!id) return;
            deleteIdInput.value = id;
            deleteForm.submit();
        });
    }

    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row) return;
            const id = row.dataset.id;
            const nombre = row.dataset.nombre || '';
            modal.dataset.id = id || '';
            if (nameNode) nameNode.textContent = nombre ? `"${nombre}"` : '—';
            modal.style.display = 'flex';
        });
    });
}

function handleAccesorioEditButtons() {
    const editButtons = document.querySelectorAll('.edit-accesorio-btn');
    const modal = document.getElementById('edit-accesorio-modal');
    const closeButton = document.getElementById('close-edit-accesorio-modal');
    const cancelButton = document.getElementById('cancel-edit-accesorio');

    if (!modal) return;

    const closeModal = () => {
        modal.style.display = 'none';
    };

    if (closeButton) closeButton.addEventListener('click', closeModal);
    if (cancelButton) cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row) return;
            document.getElementById('edit-accesorio-id').value = row.dataset.id || '';
            document.getElementById('edit-accesorio-nombre').value = row.dataset.nombre || '';
            document.getElementById('edit-accesorio-descripcion').value = row.dataset.descripcion || '';
            document.getElementById('edit-accesorio-precio').value = row.dataset.precio || '';
            document.getElementById('edit-accesorio-stock').value = row.dataset.stock || '';
            modal.style.display = 'flex';
        });
    });
}

function handleAccesorioDeleteButtons() {
    const deleteButtons = document.querySelectorAll('.delete-accesorio-btn');
    const modal = document.getElementById('delete-accesorio-modal');
    const closeButton = document.getElementById('close-delete-accesorio-modal');
    const cancelButton = document.getElementById('cancel-delete-accesorio');
    const confirmButton = document.getElementById('confirm-delete-accesorio');
    const nameNode = document.getElementById('delete-accesorio-nombre');
    const deleteForm = document.getElementById('accesorio-delete-form');
    const deleteIdInput = document.getElementById('delete-accesorio-id');

    if (!modal || !deleteForm || !deleteIdInput) return;

    const closeModal = () => {
        modal.style.display = 'none';
        modal.dataset.id = '';
    };

    if (closeButton) closeButton.addEventListener('click', closeModal);
    if (cancelButton) cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    if (confirmButton) {
        confirmButton.addEventListener('click', () => {
            const id = modal.dataset.id;
            if (!id) return;
            deleteIdInput.value = id;
            deleteForm.submit();
        });
    }

    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row) return;
            const id = row.dataset.id;
            const nombre = row.dataset.nombre || '';
            modal.dataset.id = id || '';
            if (nameNode) nameNode.textContent = nombre ? `"${nombre}"` : '—';
            modal.style.display = 'flex';
        });
    });
}

function setupStockEditModal() {
    const modal = document.getElementById('edit-stock-modal');
    const closeButton = document.getElementById('close-edit-stock-modal');
    const cancelButton = document.getElementById('cancel-edit-stock');
    const form = document.getElementById('edit-stock-form');
    const kindInput = document.getElementById('edit-stock-kind');
    const idInput = document.getElementById('edit-stock-item-id');
    const cantidadInput = document.getElementById('edit-stock-cantidad');
    const titleEl = document.getElementById('edit-stock-modal-title');
    const labelProduct = document.getElementById('edit-stock-product-label');
    const labelText = document.getElementById('edit-stock-label-text');

    if (!modal || !form || !kindInput || !idInput || !cantidadInput) return;

    const closeModal = () => {
        modal.style.display = 'none';
    };

    if (closeButton) closeButton.addEventListener('click', closeModal);
    if (cancelButton) cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    const openForSabor = (row) => {
        kindInput.value = 'sabor';
        idInput.value = row.dataset.id || '';
        cantidadInput.value = row.dataset.stock || '0';
        cantidadInput.step = '0.1';
        cantidadInput.min = '0';
        if (titleEl) titleEl.textContent = 'Editar stock (sabor)';
        if (labelText) labelText.textContent = 'Stock (litros)';
        if (labelProduct) labelProduct.textContent = row.dataset.nombre || '';
        modal.style.display = 'flex';
    };

    const openForAccesorio = (row) => {
        kindInput.value = 'accesorio';
        idInput.value = row.dataset.id || '';
        cantidadInput.value = row.dataset.stock || '0';
        cantidadInput.step = '1';
        cantidadInput.min = '0';
        if (titleEl) titleEl.textContent = 'Editar stock (accesorio)';
        if (labelText) labelText.textContent = 'Stock (unidades)';
        if (labelProduct) labelProduct.textContent = row.dataset.nombre || '';
        modal.style.display = 'flex';
    };

    document.querySelectorAll('.edit-stock-sabor-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            if (row) openForSabor(row);
        });
    });
    document.querySelectorAll('.edit-stock-accesorio-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            if (row) openForAccesorio(row);
        });
    });
}

function setupCajaCerrarConfirm() {
    const form = document.getElementById('form-cerrar-caja');
    if (!form) return;
    form.addEventListener('submit', (event) => {
        const confirmado = window.confirm('¿Confirmás el cierre de caja? Una vez cerrada no se puede volver a abrir en el mismo día.');
        if (!confirmado) {
            event.preventDefault();
        }
    });
}

function setupCajaReabrirConfirm() {
    const form = document.getElementById('form-reabrir-caja');
    if (!form) return;
    form.addEventListener('submit', (event) => {
        const confirmado = window.confirm('¿Reabrir la caja de hoy? Se van a borrar los datos del cierre anterior (efectivo contado, diferencia, observaciones) para volver a cerrarla más tarde.');
        if (!confirmado) {
            event.preventDefault();
        }
    });
}

function setupSectionManagerModal() {
    const openButton = document.getElementById('edit-sections-button');
    const modal = document.getElementById('section-manager');
    const closeButton = document.getElementById('close-section-manager');

    if (!modal) return;
    const closeModal = () => {
        modal.style.display = 'none';
    };
    const openModal = () => {
        modal.style.display = 'flex';
    };

    if (openButton) {
        openButton.addEventListener('click', openModal);
    }
    if (closeButton) {
        closeButton.addEventListener('click', closeModal);
    }
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (new URLSearchParams(window.location.search).get('focus') === 'stock') {
        window.location.hash = '#s-stock';
    }

    const sabRadio = document.getElementById('stk-tab-sab');
    const accRadio = document.getElementById('stk-tab-acc');
    const select = document.getElementById('stock-category-filter');
    const search = document.getElementById('search-sabores');
    const editSectionsButton = document.getElementById('edit-sections-button');
    const ventasSearch = document.getElementById('search-ventas');
    const ventasDate = document.getElementById('filter-fecha');
    const ventasClear = document.getElementById('clear-ventas-filters');

    if (sabRadio) {
        sabRadio.addEventListener('change', updateStockTabs);
    }
    if (accRadio) {
        accRadio.addEventListener('change', updateStockTabs);
    }
    if (select) {
        select.addEventListener('change', filterSabores);
    }
    if (search) {
        search.addEventListener('input', filterSabores);
    }
    if (editSectionsButton) {
        editSectionsButton.addEventListener('click', () => {});
    }
    if (ventasSearch) {
        ventasSearch.addEventListener('input', filterVentas);
    }
    if (ventasDate) {
        ventasDate.addEventListener('change', filterVentas);
    }
    if (ventasClear) {
        ventasClear.addEventListener('click', clearVentasFilters);
    }
    updateStockTabs();
    filterSabores();
    filterVentas();
    setupFlavorTabs();
    handleFlavorEditButtons();
    handleFlavorDeleteButtons();
    handleAccesorioEditButtons();
    handleAccesorioDeleteButtons();
    toggleInactiveFlavorSection();
    toggleInactiveAccesorioSection();
    setupAddFlavorModal();
    setupAddAccesorioModal();
    setupStockEditModal();
    setupSectionManagerModal();
    setupCajaCerrarConfirm();
    setupCajaReabrirConfirm();
});