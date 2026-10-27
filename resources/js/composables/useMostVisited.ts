import { nextTick, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

export interface RutaVisitada {
    path: string;
    title: string;
    count: number;
}

const CLAVE = 'maco-rutas-mas-visitadas';
const MAX_RUTAS = 25;

// Nombres de las rutas de web.php en español legible (p. ej. «Orden Producción»).
const PALABRAS_ES: Record<string, string> = {
    orden: 'Orden',
    ordenes: 'Órdenes',
    produccion: 'Producción',
    material: 'Material',
    materiales: 'Materiales',
    producto: 'Producto',
    productos: 'Productos',
    proveedor: 'Proveedor',
    proveedores: 'Proveedores',
    taller: 'Taller',
    talleres: 'Talleres',
    empresa: 'Empresa',
    externa: 'Externa',
    kit: 'Kit',
    proceso: 'Proceso',
    procesos: 'Procesos',
    estado: 'Estado',
    operacion: 'Operación',
    calidad: 'Calidad',
    recepcion: 'Recepción',
    cierre: 'Cierre',
    talla: 'Talla',
    tallas: 'Tallas',
    grupo: 'Grupo',
    grupos: 'Grupos',
    parametro: 'Parámetro',
    parametros: 'Parámetros',
    usuario: 'Usuario',
    usuarios: 'Usuarios',
    rol: 'Rol',
    roles: 'Roles',
    permiso: 'Permiso',
    permisos: 'Permisos',
    ficha: 'Ficha',
    fichas: 'Fichas',
    tecnica: 'Técnica',
    tecnico: 'Técnico',
    ensamble: 'Ensamble',
    merma: 'Merma',
    mermas: 'Mermas',
    pago: 'Pago',
    pagos: 'Pagos',
    desarrollo: 'Desarrollo',
    importar: 'Importar',
    excel: 'Excel',
    explicaciones: 'Explicaciones',
    dashboard: 'Dashboard',
    pagodesarrollo: 'Pago en desarrollo',
};

const humanizarRuta = (nombreRuta: string): string => {
    // De Ziggy llega p. ej. "OrdenProduccion.index": se queda la entidad.
    const entidad = nombreRuta.split('.')[0] ?? nombreRuta;
    if (!entidad) return '';

    const palabras = entidad
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/[_-]+/g, ' ')
        .trim()
        .split(/\s+/);

    return palabras.map((p) => PALABRAS_ES[p.toLowerCase()] ?? p).join(' ');
};

const etiquetaRutaActual = (): string => {
    try {
        const ziggy = (window as any).route;
        if (typeof ziggy !== 'function') return '';
        return humanizarRuta(String(ziggy().current() ?? ''));
    } catch {
        return '';
    }
};

const normalizarTitle = (title: string): string => {
    const appName = String(usePage().props.name ?? '');
    if (appName) {
        const sufijo = ' - ' + appName;
        if (title.endsWith(sufijo)) return title.slice(0, -sufijo.length);
    }
    return title.trim();
};

const normalizarPath = (url: string): string => {
    try {
        const parsed = new URL(String(url), window.location.origin);
        return parsed.pathname.replace(/\/+$/, '') || '/';
    } catch {
        return String(url);
    }
};

const leer = (): RutaVisitada[] => {
    try {
        const raw: unknown = JSON.parse(localStorage.getItem(CLAVE) ?? '[]');
        if (!Array.isArray(raw)) return [];
        return raw
            .filter((r: any) => typeof r?.path === 'string' && typeof r?.count === 'number')
            .map((r: any) => ({ path: r.path, title: String(r.title ?? r.path), count: r.count }));
    } catch {
        return [];
    }
};

const persistir = (rutas: RutaVisitada[]) => {
    try {
        localStorage.setItem(CLAVE, JSON.stringify(rutas));
    } catch {
        // localStorage lleno o bloqueado: el registro simplemente no persiste.
    }
};

const rutas = ref<RutaVisitada[]>(leer());

const registrar = (path: string, title: string) => {
    if (!path || path === '/') return;

    const existente = rutas.value.find((r) => r.path === path);
    if (existente) {
        existente.count += 1;
        if (title) existente.title = title;
    } else {
        rutas.value.push({ path, title: title || path, count: 1 });
    }

    rutas.value = [...rutas.value].sort((a, b) => b.count - a.count).slice(0, MAX_RUTAS);
    persistir(rutas.value);
};

let observando = false;

export function useMostVisited() {
    if (!observando) {
        observando = true;
        const page = usePage();
        watch(
            () => page.url,
            (url) => {
                nextTick(() => {
                    const etiqueta = etiquetaRutaActual() || normalizarTitle(document.title);
                    registrar(normalizarPath(url), etiqueta);
                });
            },
            { immediate: true },
        );
    }

    const top = (cantidad = 7): RutaVisitada[] =>
        [...rutas.value].sort((a, b) => b.count - a.count).slice(0, cantidad);

    const visitar = (path: string) => router.visit(path);

    const limpiar = () => {
        rutas.value = [];
        persistir(rutas.value);
    };

    return { top, visitar, limpiar };
}
