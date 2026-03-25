/**
 * Estado y datos compartidos (admin + empleado + historial/ventas).
 * Debe cargarse primero.
 */
let sabCats = ['Chocolates', 'Dulces de leche', 'Frutas al agua', 'Frutas a la crema', 'Cremas', 'Cremas especiales'];
let accTipos = ['Pote 1/4 kg', 'Pote 1/2 kg', 'Pote 1 kg', 'Pote 2 kg', 'Cucurucho', 'Vaso milkshake', 'Caja cucharitas', 'Caja servilletas', 'Toppings', 'Otro'];
const TIPOS_CON_SABOR = ['Pote 1/4 kg', 'Pote 1/2 kg', 'Pote 1 kg', 'Pote 2 kg', 'Vaso milkshake', 'Cucurucho'];

let sabores = [
    { id: 1, nombre: 'Chocolate', cat: 'Chocolates', precio: 2800, stock: 12 },
    { id: 2, nombre: 'Chocolate amargo', cat: 'Chocolates', precio: 3000, stock: 8 },
    { id: 3, nombre: 'Chocolate blanco', cat: 'Chocolates', precio: 3000, stock: 5 },
    { id: 4, nombre: 'Dulce de leche', cat: 'Dulces de leche', precio: 2800, stock: 10 },
    { id: 5, nombre: 'Dulce de leche granizado', cat: 'Dulces de leche', precio: 2900, stock: 7 },
    { id: 6, nombre: 'Dulce de leche con nueces', cat: 'Dulces de leche', precio: 3200, stock: 4 },
    { id: 7, nombre: 'Limón', cat: 'Frutas al agua', precio: 2500, stock: 8 },
    { id: 8, nombre: 'Frutilla', cat: 'Frutas al agua', precio: 2500, stock: 6 },
    { id: 9, nombre: 'Mango', cat: 'Frutas al agua', precio: 2700, stock: 3 },
    { id: 10, nombre: 'Maracuyá', cat: 'Frutas al agua', precio: 2700, stock: 0 },
    { id: 11, nombre: 'Vainilla', cat: 'Frutas a la crema', precio: 2600, stock: 10 },
    { id: 12, nombre: 'Banana split', cat: 'Frutas a la crema', precio: 2800, stock: 4 },
    { id: 13, nombre: 'Crema tramontana', cat: 'Cremas', precio: 2800, stock: 6 },
    { id: 14, nombre: 'Menta granizada', cat: 'Cremas especiales', precio: 3100, stock: 5 },
    { id: 15, nombre: 'Tiramisú', cat: 'Cremas especiales', precio: 3500, stock: 2 },
    { id: 16, nombre: 'Pistacho', cat: 'Cremas especiales', precio: 3800, stock: 0 },
];

let accesorios = [
    { id: 101, nombre: 'Pote 1/4 kg', tipo: 'Pote 1/4 kg', precio: 2500, stock: 30, unidad: 'unidades' },
    { id: 102, nombre: 'Pote 1/2 kg', tipo: 'Pote 1/2 kg', precio: 4500, stock: 20, unidad: 'unidades' },
    { id: 103, nombre: 'Pote 1 kg', tipo: 'Pote 1 kg', precio: 8000, stock: 15, unidad: 'unidades' },
    { id: 105, nombre: 'Cucurucho', tipo: 'Cucurucho', precio: 800, stock: 100, unidad: 'unidades' },
    { id: 106, nombre: 'Vaso milkshake', tipo: 'Vaso milkshake', precio: 1200, stock: 50, unidad: 'unidades' },
    { id: 107, nombre: 'Caja cucharitas', tipo: 'Caja cucharitas', precio: 1500, stock: 12, unidad: 'cajas' },
    { id: 108, nombre: 'Caja servilletas', tipo: 'Caja servilletas', precio: 900, stock: 8, unidad: 'cajas' },
    { id: 109, nombre: 'Toppings variados', tipo: 'Toppings', precio: 500, stock: 25, unidad: 'unidades' },
];

let ventas = [
    {
        id: 1, nroVenta: 1, fecha: '2026-03-25', total: 5800, pago: 'Efectivo', empleado: 'Juan',
        items: [{ nombre: 'Pote 1/4 kg', sabores: ['Chocolate', 'Vainilla'], cant: 1, precio: 2500 },
            { nombre: 'Cucurucho', sabores: ['Dulce de leche'], cant: 2, precio: 800 }]
    },
    {
        id: 2, nroVenta: 2, fecha: '2026-03-25', total: 4500, pago: 'Transferencia', empleado: 'Ana',
        items: [{ nombre: 'Pote 1/4 kg', sabores: ['Frutilla', 'Limón'], cant: 1, precio: 2500 },
            { nombre: 'Caja servilletas', sabores: [], cant: 1, precio: 900 }]
    },
];

let nextVentaId = 3;
let nextSabId = 20, nextAccId = 120;
let usuarioActual = null;
let pagoSel = null;
let histModo = 'admin';
let ventaBorrador = null;
let carritoItems = [];
let sabCatActiva = {};

let nroVentaDia = 0;
let fechaUltimaVenta = '';

let catsEditando = [];
let sabAdminCatActiva = '';
