let nombresHighlights = [];
let datosRevisionActual = null;
let alertaAbierta = null;
let notaAbierta = null;
let notasOrganos = {};
let timerReaplicarRevision = null;
let editorRevisionEscuchado = null;
let observerRevisionEditor = null;
let observerNotasEditor = null;
let timerNotasEditor = null;

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

function obtenerNombreCard(el) {
    if (!el) return '';

    const texto = (el.textContent || '').trim();
    if (!texto) return '';

    const strong = el.querySelector('strong');
    if (strong) {
        return (strong.textContent || '').trim().replace(/:$/, '');
    }

    const match = texto.match(/^([^:]{2,60}):/);
    return match ? match[1].trim() : '';
}

function obtenerCardsInforme() {
    const raiz = obtenerRaizEditor();
    if (!raiz) return [];

    return Array.from(raiz.children)
        .filter(el => el.tagName === 'P')
        .map(el => ({
            el,
            organo: obtenerNombreCard(el)
        }))
        .filter(card => card.organo);
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

function claveNotaOrgano(organo) {
    return normalizar(organo || '');
}

function cerrarEditorNota() {
    document.querySelectorAll('.vm-revision-nota-editor').forEach(el => el.remove());
    document.querySelectorAll('.vm-revision-nota-btn.active').forEach(el => el.classList.remove('active'));

    const raiz = obtenerRaizEditor();
    if (raiz) {
        raiz.querySelectorAll('[data-vm-nota-editando="1"]').forEach(el => {
            el.removeAttribute('data-vm-nota-editando');
        });
    }

    notaAbierta = null;
}

function actualizarCardNota(organo) {
    const clave = claveNotaOrgano(organo);
    const overlay = document.getElementById('vm_revision_overlay');
    if (!overlay) return;

    const contenedor = Array.from(overlay.querySelectorAll('.vm-revision-organo-acciones'))
        .find(el => claveNotaOrgano(el.dataset.organo) === clave);

    const boton = contenedor?.querySelector('.vm-revision-nota-btn');
    if (!boton) return;

    boton.classList.toggle('tiene-nota', !!notasOrganos[clave]);
}

function mostrarEditorNota(boton, organo) {
    const clave = claveNotaOrgano(organo);
    const contenedor = boton.closest('.vm-revision-organo-acciones');
    if (!contenedor) return;

    const inputAbierto = document.querySelector('.vm-revision-nota-input');

    if (notaAbierta === clave && inputAbierto) {
        inputAbierto.focus();
        return;
    }

    if (notaAbierta && inputAbierto) {
        const textoAnterior = inputAbierto.value.trim();

        if (textoAnterior) {
            notasOrganos[notaAbierta] = textoAnterior;
        } else {
            delete notasOrganos[notaAbierta];
        }
    }

    cerrarEditorNota();

    notaAbierta = clave;

    clearTimeout(timerNotasEditor);
    clearTimeout(timerReaplicarRevision);

    boton.classList.add('active');

    const resumen = contenedor.querySelector('.vm-revision-nota-resumen');
    if (resumen) resumen.remove();

    const panel = document.createElement('div');
    panel.className = 'vm-revision-nota-editor';

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'vm-revision-nota-input';
    input.placeholder = 'Escribir observación...';
    input.value = notasOrganos[clave] || '';

    panel.appendChild(input);
    contenedor.appendChild(panel);

    ['pointerdown', 'mousedown', 'click'].forEach(evento => {
        input.addEventListener(evento, function (e) {
            e.stopPropagation();
        });
    });

    let finalizado = false;

    function guardar() {
        if (finalizado) return;
        finalizado = true;

        const texto = input.value.trim();

        if (texto) {
            notasOrganos[clave] = texto;
        } else {
            delete notasOrganos[clave];
        }

        cerrarEditorNota();
        pintarAlertas(datosRevisionActual);
    }

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            guardar();
        }

        if (e.key === 'Escape') {
            e.preventDefault();
            finalizado = true;
            cerrarEditorNota();
            pintarAlertas(datosRevisionActual);
        }
    });

    input.addEventListener('blur', function () {
        setTimeout(function () {
            if (document.body.contains(input) && notaAbierta === clave) {
                guardar();
            }
        }, 0);
    });

    setTimeout(() => {
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    }, 0);
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

function pintarAlertas(datos = null) {
    const overlay = obtenerOverlay();
    if (!overlay) return;

    overlay.innerHTML = '';

    const revision = Array.isArray(datos?.organos) ? datos.organos : [];

    obtenerCardsInforme().forEach(card => {
        const clave = claveNotaOrgano(card.organo);

        const organoRevision = revision.find(item =>
            claveNotaOrgano(item.organo) === clave
        );

        const alertas = Array.isArray(organoRevision?.alertas)
            ? organoRevision.alertas
            : [];

        const contenedor = document.createElement('div');
        contenedor.className = 'vm-revision-organo-acciones';
        contenedor.dataset.organo = card.organo;

        if (alertas.length) {
            const badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'vm-revision-alerta-badge';
            badge.textContent = '⚠ ' + alertas.length;
            badge.title = card.organo + ': ' + alertas.length + ' observación' + (alertas.length === 1 ? '' : 'es');

            badge.addEventListener('click', function () {
                cerrarEditorNota();
                mostrarDetalleAlerta(badge, organoRevision);
            });

            contenedor.appendChild(badge);
        }

        const nota = document.createElement('button');
        nota.type = 'button';
        nota.className = 'vm-revision-nota-btn' + (notasOrganos[clave] ? ' tiene-nota' : '');
        nota.title = notasOrganos[clave] ? 'Editar nota' : 'Agregar nota';
        nota.setAttribute('aria-label', nota.title);
        nota.innerHTML = `
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path>
            </svg>
        `;

        nota.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            cerrarDetalleAlerta();
            mostrarEditorNota(nota, card.organo);
        });

        contenedor.appendChild(nota);

        if (notasOrganos[clave]) {
            const resumen = document.createElement('div');
            resumen.className = 'vm-revision-nota-resumen';
            resumen.textContent = notasOrganos[clave];
            resumen.title = notasOrganos[clave];
            contenedor.appendChild(resumen);
        }

        overlay.appendChild(contenedor);
    });

    actualizarPosicionAlertas();
}

function actualizarPosicionAlertas() {
    const wrapper = document.getElementById('contenido_html_editor_wrapper');
    const editor = document.getElementById('contenido_html_editor');
    const overlay = document.getElementById('vm_revision_overlay');

    if (!wrapper || !editor || !overlay) return;

    const wrapperRect = wrapper.getBoundingClientRect();
    const editorRect = editor.getBoundingClientRect();

    overlay.querySelectorAll('.vm-revision-organo-acciones').forEach(contenedor => {
        const organo = contenedor.dataset.organo || '';
        const bloque = buscarBloqueOrgano(organo);

        if (!bloque) {
            contenedor.style.display = 'none';
            return;
        }

        const rect = bloque.el.getBoundingClientRect();
        const visible = rect.bottom >= editorRect.top && rect.top <= editorRect.bottom;

        contenedor.style.display = visible ? '' : 'none';
        contenedor.style.top = (rect.top - wrapperRect.top) + 'px';
        contenedor.style.left = (rect.left - wrapperRect.left) + 'px';
        contenedor.style.right = 'auto';
        contenedor.style.width = rect.width + 'px';
        contenedor.style.height = rect.height + 'px';
    });

    cerrarDetalleAlerta();
}

function activarReaplicacionEnEdicion() {
    const raiz = obtenerRaizEditor();
    if (!raiz) return;

    if (editorRevisionEscuchado !== raiz) {
        if (editorRevisionEscuchado) {
            editorRevisionEscuchado.removeEventListener('input', programarReaplicacionRevision);
        }

        editorRevisionEscuchado = raiz;
        editorRevisionEscuchado.addEventListener('input', programarReaplicacionRevision);
    }

    if (observerRevisionEditor) observerRevisionEditor.disconnect();

    observerRevisionEditor = new MutationObserver(function () {
        programarReaplicacionRevision();
    });

    observerRevisionEditor.observe(raiz, {
        subtree: true,
        childList: true,
        characterData: true,
        attributes: true,
        attributeFilter: ['style', 'class']
    });
}

function programarReaplicacionRevision() {
    if (!datosRevisionActual || notaAbierta) return;

    clearTimeout(timerReaplicarRevision);

    timerReaplicarRevision = setTimeout(function () {
        if (!datosRevisionActual || notaAbierta) return;
        aplicar(datosRevisionActual);
    }, 120);
}

function programarPintadoNotas() {
    if (notaAbierta || datosRevisionActual) return;

    clearTimeout(timerNotasEditor);

    timerNotasEditor = setTimeout(function () {
        if (notaAbierta || datosRevisionActual) return;
        pintarAlertas(null);
    }, 100);
}

function inicializarNotasOrganos() {
    const conectar = function () {
        const raiz = obtenerRaizEditor();
        if (!raiz) return false;

        pintarAlertas(datosRevisionActual);

        if (observerNotasEditor) observerNotasEditor.disconnect();

        observerNotasEditor = new MutationObserver(function () {
            programarPintadoNotas();
        });

        observerNotasEditor.observe(raiz, {
            subtree: true,
            childList: true,
            characterData: true
        });

        return true;
    };

    if (conectar()) return;

    const observerInicio = new MutationObserver(function () {
        if (conectar()) observerInicio.disconnect();
    });

    observerInicio.observe(document.body, {
        childList: true,
        subtree: true
    });
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
    activarReaplicacionEnEdicion();

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

    clearTimeout(timerReaplicarRevision);

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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarNotasOrganos);
} else {
    inicializarNotasOrganos();
}

window.VetmindRevision = {
    aplicar,
    limpiar,
    prueba
};