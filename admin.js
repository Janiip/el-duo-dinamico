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

function handleFlavorEditButtons() {
    const editButtons = document.querySelectorAll('.edit-sabor-btn');
    const editPanel = document.getElementById('edit-sabor-panel');
    const cancelButton = document.getElementById('cancel-edit-sabor');

    if (cancelButton) {
        cancelButton.addEventListener('click', () => {
            if (editPanel) {
                editPanel.style.display = 'none';
            }
        });
    }

    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row || !editPanel) return;
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
            editPanel.style.display = 'block';
            editPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
}

function handleFlavorDeleteButtons() {
    const deleteButtons = document.querySelectorAll('.delete-sabor-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            const row = button.closest('tr');
            if (!row) return;
            const id = row.dataset.id;
            const nombre = row.dataset.nombre || '';
            if (!confirm(`¿Eliminar el sabor "${nombre}"? Esta acción no se puede deshacer.`)) return;

            const deleteForm = document.getElementById('flavor-delete-form');
            if (!deleteForm) return;
            document.getElementById('delete-sabor-id').value = id;
            deleteForm.submit();
        });
    });
}

function toggleSectionManager() {
    const panel = document.getElementById('section-manager');
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
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
        editSectionsButton.addEventListener('click', toggleSectionManager);
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
    toggleInactiveFlavorSection();
});
