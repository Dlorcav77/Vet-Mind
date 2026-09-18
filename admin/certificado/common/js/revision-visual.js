let nombresHighlights = [];
let datosRevisionActual = null;
let alertaAbierta = null;

function normalizar(valor) {
    return String(valor || '').trim().toLowerCase();
}

function obtenerRaizEditor() {
    return document.querySelector('#contenido_html_editor .ProseMirror');
}

function obtenerBloquesTexto() {
    const raiz = obtenerRaizEditor();
    if (!raiz) return [];

    return Array.from(raiz.querySelectorAll('p, h1, h2, h3, li')).map(el => ({
        el,
        texto: el.textContent || ''
    }));
}

function buscarBloqueOrgano(nombre) {
    const buscado = normalizar(nombre);
    return obtenerBloquesTexto().find(b => normalizar(b.texto).includes(buscado)) || null;
}

function buscarRangoDom(contenedor, textoBuscado) {
    const objetivo = normalizar(textoBuscado);
    if (!objetivo) return null;

    const walker = document.createTreeWalker(contenedor, NodeFilter.SHOW_TEXT);
    const nodos = [];
    let texto = '';
    let nodo;

    while ((nodo = walker.nextNode())) {
        const inicio = texto.length;
        texto += nodo.nodeValue || '';
        nodos.push({ nodo, inicio, fin: texto.length });
    }

    const inicio = texto.toLowerCase().indexOf(objetivo);
    if (inicio === -1) return null;

    const fin = inicio + textoBuscado.length;
    const nodoInicio = nodos.find(n => inicio >= n.inicio && inicio < n.fin);
    const nodoFin = nodos.find(n => (fin - 1) >= n.inicio && (fin - 1) < n.fin);

    if (!nodoInicio || !nodoFin) return null;

    const range = new Range();
    range.setStart(nodoInicio.nodo, inicio - nodoInicio.inicio);
    range.setEnd(nodoFin.nodo, fin - nodoFin.inicio);
    return range;
}

// function limpiarTarjetasOrganos() {
//     const raiz = obtenerRaizEditor();
//     if (!raiz) return;

//     raiz.querySelectorAll('.vm-revision-organo-card').forEach(el => {
//         el.classList.remove('vm-revision-organo-card');
//     });
// }

function limpiarHighlights() {
    if (!window.CSS || !CSS.highlights) return;

    nombresHighlights.forEach(nombre => CSS.highlights.delete(nombre));
    nombresHighlights = [];
}

function obtenerOverlay() {
    let overlay = document.getElementById('vm_revision_overlay');
    if (overlay) return overlay;

    const wrapper = document.getElementById('contenido_html_editor_wrapper');
    if (!wrapper) return null;

    if (getComputedStyle(wrapper).position === 'static') {
        wrapper.style.position = 'relative';
    }

    overlay = document.createElement('div');
    overlay.id = 'vm_revision_overlay';
    overlay.className = 'vm-revision-overlay';
    wrapper.appendChild(overlay);

    const editor = document.getElementById('contenido_html_editor');
    if (editor) {
        editor.addEventListener('scroll', actualizarPosicionAlertas);
    }

    window.addEventListener('resize', actualizarPosicionAlertas);
    return overlay;
}

function cerrarDetalleAlerta() {
    const detalle = document.getElementById('vm_revision_detalle');
    if (detalle) detalle.remove();

    document.querySelectorAll('.vm-revision-alerta-badge.active').forEach(el => {
        el.classList.remove('active');
    });

    alertaAbierta = null;
}

function mostrarDetalleAlerta(badge, organo) {
    if (alertaAbierta === organo.organo) {
        cerrarDetalleAlerta();
        return;
    }

    cerrarDetalleAlerta();
    alertaAbierta = organo.organo;
    badge.classList.add('active');

    const wrapper = document.getElementById('contenido_html_editor_wrapper');
    if (!wrapper) return;

    const detalle = document.createElement('div');
    detalle.id = 'vm_revision_detalle';
    detalle.className = 'vm-revision-detalle';

    const titulo = document.createElement('div');
    titulo.className = 'vm-revision-detalle-titulo';
    titulo.textContent = organo.organo;

    const cerrar = document.createElement('button');
    cerrar.type = 'button';
    cerrar.className = 'vm-revision-detalle-cerrar';
    cerrar.textContent = '×';
    cerrar.addEventListener('click', cerrarDetalleAlerta);

    titulo.appendChild(cerrar);
    detalle.appendChild(titulo);

    (organo.alertas || []).forEach(alerta => {
        const item = document.createElement('div');
        item.className = 'vm-revision-detalle-item vm-revision-severidad-' + (alerta.severidad || 'media');

        if (alerta.tipo) {
            const tipo = document.createElement('strong');
            tipo.textContent = alerta.tipo + ': ';
            item.appendChild(tipo);
        }

        item.appendChild(document.createTextNode(alerta.detalle || alerta.texto || 'Revisar.'));
        detalle.appendChild(item);
    });

    wrapper.appendChild(detalle);

    const wrapperRect = wrapper.getBoundingClientRect();
    const badgeRect = badge.getBoundingClientRect();

    detalle.style.top = (badgeRect.bottom - wrapperRect.top + 4) + 'px';
    detalle.style.right = '10px';
}

function pintarAlertas(datos) {
    const overlay = obtenerOverlay();
    if (!overlay) return;

    overlay.innerHTML = '';

    (datos.organos || []).forEach((organo, idx) => {
        const bloque = buscarBloqueOrgano(organo.organo);
        if (!bloque) return;

        const alertas = Array.isArray(organo.alertas) ? organo.alertas : [];
        if (!alertas.length) return;

        const badge = document.createElement('button');
        badge.type = 'button';
        badge.className = 'vm-revision-alerta-badge';
        badge.dataset.indice = idx;
        badge.textContent = '⚠ ' + alertas.length;
        badge.title = organo.organo + ': ' + alertas.length + ' observación' + (alertas.length === 1 ? '' : 'es');
        badge.addEventListener('click', function () {
            mostrarDetalleAlerta(badge, organo);
        });

        overlay.appendChild(badge);
    });

    actualizarPosicionAlertas();
}

function actualizarPosicionAlertas() {
    if (!datosRevisionActual) return;

    const wrapper = document.getElementById('contenido_html_editor_wrapper');
    const editor = document.getElementById('contenido_html_editor');
    const overlay = document.getElementById('vm_revision_overlay');

    if (!wrapper || !editor || !overlay) return;

    const wrapperRect = wrapper.getBoundingClientRect();
    const editorRect = editor.getBoundingClientRect();

    overlay.querySelectorAll('.vm-revision-alerta-badge').forEach(badge => {
        const indice = parseInt(badge.dataset.indice, 10);
        const organo = datosRevisionActual.organos[indice];
        const bloque = organo ? buscarBloqueOrgano(organo.organo) : null;

        if (!bloque) {
            badge.style.display = 'none';
            return;
        }

        const rect = bloque.el.getBoundingClientRect();
        const visible = rect.bottom >= editorRect.top && rect.top <= editorRect.bottom;

        badge.style.display = visible ? '' : 'none';
        badge.style.top = (rect.top - wrapperRect.top + 5) + 'px';
        badge.style.right = '20px';
    });

    cerrarDetalleAlerta();
}


function aplicar(datos) {
    limpiarHighlights();
    cerrarDetalleAlerta();

    if (!window.CSS || !CSS.highlights || typeof Highlight === 'undefined') {
        console.warn('VetmindRevision: CSS Highlights no está disponible.');
        return false;
    }

    if (!datos || !Array.isArray(datos.organos)) return false;

    datosRevisionActual = datos;

    const porOrigen = {
        plantilla: [],
        dictado: [],
        desconocido: []
    };

    datos.organos.forEach(organo => {
        const bloque = buscarBloqueOrgano(organo.organo);
        if (!bloque) return;

        (organo.atributos || []).forEach(atributo => {
            const origen = Object.prototype.hasOwnProperty.call(porOrigen, atributo.origen)
                ? atributo.origen
                : 'desconocido';

            const rango = buscarRangoDom(bloque.el, atributo.texto);
            if (rango) porOrigen[origen].push(rango);
        });
    });

    Object.entries(porOrigen).forEach(([origen, rangos]) => {
        if (!rangos.length) return;

        const nombre = 'vm-revision-' + origen;
        CSS.highlights.set(nombre, new Highlight(...rangos));
        nombresHighlights.push(nombre);
    });

    pintarAlertas(datos);
    return true;
}

function limpiar() {
    limpiarHighlights();
    cerrarDetalleAlerta();
    datosRevisionActual = null;

    const overlay = document.getElementById('vm_revision_overlay');
    if (overlay) overlay.remove();
}

function prueba() {
    return aplicar({
        organos: [
            {
                organo: 'Vejiga urinaria',
                atributos: [
                    { texto: 'poco distendida', origen: 'dictado' },
                    { texto: 'contenido anecoico, homogéneo', origen: 'plantilla' },
                    { texto: 'pared engrosada en 0.35 cm', origen: 'dictado' },
                    { texto: 'bordes irregulares', origen: 'dictado' }
                ],
                alertas: []
            },
            {
                organo: 'Riñón izquierdo',
                atributos: [
                    { texto: 'posición normal', origen: 'plantilla' },
                    { texto: 'forma redonda', origen: 'dictado' },
                    { texto: 'tamaño conservado de 3.7 cm', origen: 'mixto' },
                    { texto: 'bordes irregulares', origen: 'dictado' },
                    { texto: 'límite cortico medular poco definido', origen: 'desconocido' },
                    { texto: 'relación cortico medular disminuida', origen: 'dictado' },
                    { texto: 'ecogenicidad cortical aumentada', origen: 'dictado' },
                    { texto: 'ecogenicidad medular aumentada', origen: 'dictado' },
                    { texto: 'Vasculatura normal', origen: 'plantilla' },
                    { texto: 'Pelvis conservada', origen: 'plantilla' },
                    { texto: 'No se visualiza uréter', origen: 'plantilla' }
                ],
                alertas: [{
                    severidad: 'media',
                    tipo: 'Dato a revisar',
                    detalle: 'Observación simulada asociada al Riñón izquierdo.'
                }]
            },
            {
                organo: 'Riñón derecho',
                atributos: [
                    { texto: 'posición normal', origen: 'plantilla' },
                    { texto: 'forma redonda', origen: 'dictado' },
                    { texto: 'tamaño conservado de 4.1 cm', origen: 'mixto' },
                    { texto: 'bordes irregulares', origen: 'dictado' },
                    { texto: 'límite cortico medular poco definido', origen: 'dictado' },
                    { texto: 'relación cortico medular disminuida', origen: 'dictado' },
                    { texto: 'ecogenicidad cortical aumentada', origen: 'dictado' }
                ],
                alertas: [{
                    severidad: 'alta',
                    tipo: 'Revisar hallazgo',
                    detalle: 'Ejemplo de una observación importante asociada al Riñón derecho.'
                }]
            },
            {
                organo: 'Hígado',
                atributos: [
                    { texto: 'tamaño conservado', origen: 'dictado' },
                    { texto: 'parénquima homogéneo', origen: 'plantilla' },
                    { texto: 'ecogenicidad aumentada', origen: 'dictado' },
                    { texto: 'Sin lesiones focales', origen: 'plantilla' },
                    { texto: 'Vasculatura conservada', origen: 'dictado' }
                ],
                alertas: []
            }
        ]
    });
}

window.VetmindRevision = {
    aplicar,
    limpiar,
    prueba
};