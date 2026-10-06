let nombresHighlights = [];
let datosRevisionActual = null;
let alertaAbierta = null;
let notaAbierta = null;
let notasOrganos = {};
let nombresNotasOrganos = {};
let timerReaplicarRevision = null;
let editorRevisionEscuchado = null;
let observerRevisionEditor = null;
let observerNotasEditor = null;
let timerNotasEditor = null;

function sincronizarRevisionHidden(datos, notificar = true) {
    const hidden = document.getElementById('revision_visual');
    if (!hidden) return;

    const nuevoValor = datos && Array.isArray(datos.organos)
        ? JSON.stringify(datos)
        : '';

    if (hidden.value === nuevoValor) return;

    hidden.value = nuevoValor;

    if (notificar) {
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function cargarRevisionInicialDesdeHidden() {
    if (datosRevisionActual) return false;

    const hidden = document.getElementById('revision_visual');
    const raw = String(hidden?.value || '').trim();

    if (!raw) return false;

    try {
        const datos = JSON.parse(raw);

        if (
            !datos ||
            !Array.isArray(datos.organos)
        ) {
            return false;
        }

        return aplicar(datos, false);
    } catch (e) {
        console.warn('VetmindRevision: revisión guardada inválida.');
        return false;
    }
}

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
    const buscado = normalizarComparacion(nombre);
    if (!buscado) return null;

    const bloques = obtenerBloquesTexto();

    /*
     * Primero buscar por el nombre real detectado para la card.
     * Esto evita falsos positivos con órganos cortos como AI / AD.
     */
    const exacto = bloques.find(b => {
        const nombreCard = obtenerNombreCard(b.el);

        return nombreCard
            && normalizarComparacion(nombreCard) === buscado;
    });

    if (exacto) return exacto;

    /*
     * Fallback conservador:
     * el nombre debe estar al comienzo del párrafo y terminar
     * en un límite razonable, no simplemente aparecer dentro
     * de cualquier palabra.
     */
    return bloques.find(b => {
        const texto = normalizarComparacion(b.texto);

        if (!texto.startsWith(buscado)) {
            return false;
        }

        const siguiente = texto.charAt(buscado.length);

        return !siguiente || /[\s:.,;-]/.test(siguiente);
    }) || null;
}

function normalizarComparacion(valor) {
    return normalizar(valor)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ');
}

function obtenerNombreCard(el) {
    if (!el) return '';

    const texto = (el.textContent || '').trim().replace(/\s+/g, ' ');
    if (!texto) return '';

    const textoNorm = normalizarComparacion(texto);
    const candidatos = [];

    if (Array.isArray(datosRevisionActual?.organos)) {
        datosRevisionActual.organos.forEach(item => {
            const nombre = String(item?.organo || '').trim();
            if (nombre) candidatos.push(nombre);
        });
    }

    Object.values(nombresNotasOrganos).forEach(nombre => {
        nombre = String(nombre || '').trim();
        if (nombre) candidatos.push(nombre);
    });

    const candidatosUnicos = Array.from(new Set(candidatos))
        .sort((a, b) => b.length - a.length);

    for (const nombre of candidatosUnicos) {
        const nombreNorm = normalizarComparacion(nombre);

        if (!textoNorm.startsWith(nombreNorm)) continue;

        const siguiente = textoNorm.charAt(nombreNorm.length);

        if (!siguiente || /[\s:.,;-]/.test(siguiente)) {
            return nombre;
        }
    }

    const etiqueta = el.querySelector('strong, em');

    if (etiqueta) {
        const nombre = (etiqueta.textContent || '')
            .trim()
            .replace(/:$/, '')
            .replace(/\s+/g, ' ');

        if (
            nombre.length >= 2 &&
            nombre.length <= 100 &&
            normalizarComparacion(nombre) !== textoNorm &&
            textoNorm.startsWith(normalizarComparacion(nombre))
        ) {
            return nombre;
        }
    }

    const match = texto.match(/^([^:]{2,100}):/);
    return match ? match[1].trim() : '';
}

function obtenerCardsInforme() {
    const raiz = obtenerRaizEditor();
    if (!raiz) return [];

    return Array.from(raiz.children)
        .filter(el => el.tagName === 'P')
        .map((el, indice) => {
            const organo = obtenerNombreCard(el);

            return {
                el,
                indice,
                organo: organo || ('Párrafo ' + (indice + 1)),
                clave: organo
                    ? claveNotaOrgano(organo)
                    : 'parrafo:' + indice
            };
        });
}

function buscarRangoDom(contenedor, textoBuscado, ocurrencia = 0) {
    const buscado = String(textoBuscado || '').trim();
    if (!buscado) return null;

    const objetivo = buscado.toLowerCase();

    /*
     * Ignorar el texto de los <sup class="flag">.
     *
     * Si el HTML contiene:
     *   cotextura<sup class="flag">...</sup> granular fina
     *
     * para búsqueda debe comportarse como:
     *   cotextura granular fina
     */
    const walker = document.createTreeWalker(
        contenedor,
        NodeFilter.SHOW_TEXT,
        {
            acceptNode(node) {
                const padre = node.parentElement;

                if (padre && padre.closest('sup.flag')) {
                    return NodeFilter.FILTER_REJECT;
                }

                return NodeFilter.FILTER_ACCEPT;
            }
        }
    );

    const nodos = [];
    let texto = '';
    let nodo;

    while ((nodo = walker.nextNode())) {
        const inicio = texto.length;
        const valor = nodo.nodeValue || '';

        texto += valor;

        nodos.push({
            nodo,
            inicio,
            fin: texto.length
        });
    }

    const textoComparacion = texto.toLowerCase();

    let inicio = -1;
    let desde = 0;

    for (let i = 0; i <= ocurrencia; i++) {
        inicio = textoComparacion.indexOf(objetivo, desde);

        if (inicio === -1) {
            return null;
        }

        desde = inicio + objetivo.length;
    }

    const fin = inicio + buscado.length;

    const nodoInicio = nodos.find(n =>
        inicio >= n.inicio && inicio < n.fin
    );

    const nodoFin = nodos.find(n =>
        (fin - 1) >= n.inicio && (fin - 1) < n.fin
    );

    if (!nodoInicio || !nodoFin) {
        return null;
    }

    const range = new Range();

    range.setStart(
        nodoInicio.nodo,
        inicio - nodoInicio.inicio
    );

    range.setEnd(
        nodoFin.nodo,
        fin - nodoFin.inicio
    );

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

function cerrarDetalleAlertaClickFuera(e) {
    const detalle = document.getElementById('vm_revision_detalle');
    if (!detalle) return;

    if (detalle.contains(e.target)) return;

    if (
        e.target.closest &&
        e.target.closest('.vm-revision-alerta-badge')
    ) {
        return;
    }

    cerrarDetalleAlerta();
}

function posicionarDetalleAlerta(detalle, badge) {
    if (!detalle || !badge) return;

    const margen = 10;
    const separacion = 6;
    const badgeRect = badge.getBoundingClientRect();
    const detalleRect = detalle.getBoundingClientRect();

    let left = badgeRect.right - detalleRect.width;

    left = Math.max(
        margen,
        Math.min(
            left,
            window.innerWidth - detalleRect.width - margen
        )
    );

    let top = badgeRect.bottom + separacion;

    if (
        top + detalleRect.height >
        window.innerHeight - margen
    ) {
        top = badgeRect.top - detalleRect.height - separacion;
    }

    top = Math.max(
        margen,
        Math.min(
            top,
            window.innerHeight - detalleRect.height - margen
        )
    );

    detalle.style.left = left + 'px';
    detalle.style.top = top + 'px';
}

function cerrarDetalleAlerta() {
    const detalle = document.getElementById('vm_revision_detalle');
    if (detalle) detalle.remove();

    document.querySelectorAll('.vm-revision-alerta-badge.active').forEach(el => {
        el.classList.remove('active');
    });

    document.removeEventListener(
        'pointerdown',
        cerrarDetalleAlertaClickFuera,
        true
    );

    alertaAbierta = null;
}

function claveNotaOrgano(organo) {
    return normalizar(organo || '');
}

function cargarNotasDesdeHidden() {
    const hidden = document.getElementById('notas_organos');
    if (!hidden || !hidden.value.trim()) return;

    try {
        const datos = JSON.parse(hidden.value);

        if (!datos || typeof datos !== 'object' || Array.isArray(datos)) {
            return;
        }

        Object.entries(datos).forEach(([clave, valor]) => {
            const claveNormalizada = claveNotaOrgano(clave);
            const nota = typeof valor === 'string'
                ? valor.trim()
                : String(valor?.nota || '').trim();

            const organo = typeof valor === 'object' && valor
                ? String(valor.organo || '').trim()
                : '';

            if (nota) {
                notasOrganos[claveNormalizada] = nota;
            }

            if (organo) {
                nombresNotasOrganos[claveNormalizada] = organo;
            }
        });
    } catch (e) {
        notasOrganos = {};
    }
}

function sincronizarNotasHidden() {
    const hidden = document.getElementById('notas_organos');
    if (!hidden) return;

    const nombres = {};

    obtenerCardsInforme().forEach(card => {
        nombres[card.clave] = card.organo;
    });

    const salida = {};

    Object.entries(notasOrganos).forEach(([clave, valor]) => {
        const nota = String(valor || '').trim();
        if (!nota) return;

        salida[clave] = {
            organo: nombres[clave] || nombresNotasOrganos[clave] || clave,
            nota: nota
        };
    });

    const nuevoValor = JSON.stringify(salida);

    if (hidden.value === nuevoValor) return;

    hidden.value = nuevoValor;
    hidden.dispatchEvent(new Event('input', { bubbles: true }));
}

cargarNotasDesdeHidden();

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

function mostrarEditorNota(boton, organo, claveCard = '') {
    const clave = claveCard || claveNotaOrgano(organo);
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

        sincronizarNotasHidden();
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

    input.addEventListener('input', function () {
        const texto = input.value.trim();

        if (texto) {
            notasOrganos[clave] = texto;
        } else {
            delete notasOrganos[clave];
        }

        sincronizarNotasHidden();
        actualizarCardNota(organo);
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

        sincronizarNotasHidden();
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

function obtenerTipoBaseAlerta(tipo) {
    const valor = normalizarComparacion(
        String(tipo || '')
    );

    const tipos = [
        'valor_sospechoso',
        'falta_unidad',
        'termino_confuso',
        'incongruencia',
        'medida_ilegible',
        'valor_faltante',

        'hallazgo_bajado',
        'inventado',
        'cambio_lateralidad',
        'cambio_medida',
        'omitido',
        'discrepancia_negacion',
        'organo_sin_dictado',
        'mismas_caracteristicas_literal',
        'organo_omitido',
        'incoherencia_homogeneo',
        'atributo_no_reemplazado',
        'atributo_plantilla_omitido',
        'discrepancia_stt'
    ];

    return tipos.find(tipoBase =>
        valor.includes(tipoBase)
    ) || valor;
}

function obtenerDetalleVisibleAlerta(alerta) {
    const tipo = obtenerTipoBaseAlerta(
        alerta?.tipo
    );

    const limpiarTexto = function (valor, maximo = 260) {
        let texto = String(valor || '')
            .replace(/\s+/g, ' ')
            .trim();

        if (!texto) {
            return '';
        }

        /*
         * Quitar etiquetas internas que no aportan información
         * clínica al veterinario.
         */
        texto = texto
            .replace(
                /^(?:valor_sospechoso|falta_unidad|termino_confuso|incongruencia|medida_ilegible|valor_faltante)\s*(?:→|:|-)\s*/iu,
                ''
            )
            .replace(
                /^(?:hallazgo_bajado|inventado|cambio_lateralidad|cambio_medida|omitido|organo_omitido|atributo_no_reemplazado|atributo_plantilla_omitido)\s*(?:→|:|-)\s*/iu,
                ''
            );

        if (texto.length > maximo) {
            texto =
                texto.slice(0, maximo - 1)
                    .trimEnd()
                + '…';
        }

        return texto;
    };

    const detalle = limpiarTexto(
        alerta?.detalle
    );

    const objetivo = limpiarTexto(
        alerta?.objetivo,
        120
    );

    const dictado = limpiarTexto(
        alerta?.dictado,
        120
    );

    const informe = limpiarTexto(
        alerta?.informe,
        120
    );

    /*
     * REGLA PRINCIPAL:
     * si backend ya entregó una explicación concreta,
     * esa explicación tiene prioridad para GEN y revisor.
     */
    if (detalle) {
        return detalle;
    }

    /*
     * Si por algún motivo no existe detalle,
     * intentamos construir un mensaje contextual
     * con los datos disponibles.
     */
    if (tipo === 'discrepancia_negacion') {
        if (dictado && informe) {
            return 'Confirmar esta afirmación. Se registró «'
                + dictado
                + '», mientras el informe indica «'
                + informe
                + '».';
        }

        if (informe) {
            return 'Confirmar si corresponde «'
                + informe
                + '».';
        }

        return 'Confirmar si este hallazgo está presente o ausente.';
    }

    const conObjetivo = function (inicio, final = '') {
        if (!objetivo) {
            return '';
        }

        return inicio
            + '«'
            + objetivo
            + '»'
            + final;
    };

    const mensajesContextuales = {
        valor_sospechoso:
            conObjetivo(
                'Confirmar ',
                ': el valor podría no corresponder.'
            ),

        falta_unidad:
            conObjetivo(
                'Confirmar la unidad de ',
                '.'
            ),

        termino_confuso:
            conObjetivo(
                'Confirmar ',
                ': el término no pudo determinarse con seguridad.'
            ),

        incongruencia:
            conObjetivo(
                'Confirmar ',
                ': existen datos clínicos contradictorios.'
            ),

        medida_ilegible:
            conObjetivo(
                'Confirmar la medida de ',
                ': no pudo determinarse con claridad.'
            ),

        valor_faltante:
            conObjetivo(
                'Completar ',
                ': falta este valor.'
            ),

        hallazgo_bajado:
            conObjetivo(
                'Revisar ',
                ': existe evidencia de un hallazgo que podría haberse dejado como normal.'
            ),

        inventado:
            conObjetivo(
                'Revisar ',
                ': este dato no está respaldado por la evidencia disponible.'
            ),

        cambio_lateralidad:
            conObjetivo(
                'Confirmar la lateralidad de ',
                '.'
            ),

        cambio_medida:
            conObjetivo(
                'Confirmar la medida de ',
                '.'
            ),

        omitido:
            conObjetivo(
                'Revisar ',
                ': este hallazgo podría faltar en el informe.'
            ),

        organo_sin_dictado:
            conObjetivo(
                'Confirmar ',
                ': no queda claro si esta estructura fue evaluada.'
            ),

        mismas_caracteristicas_literal:
            conObjetivo(
                'Revisar ',
                ': faltan detallar las características heredadas del órgano de referencia.'
            ),

        organo_omitido:
            conObjetivo(
                'Revisar ',
                ': esta estructura fue mencionada pero podría faltar en el informe.'
            ),

        incoherencia_homogeneo:
            conObjetivo(
                'Revisar ',
                ': existen descripciones incompatibles sobre su homogeneidad.'
            ),

        atributo_no_reemplazado:
            conObjetivo(
                'Revisar ',
                ': podría haberse conservado un valor anterior de la plantilla.'
            ),

        atributo_plantilla_omitido:
            conObjetivo(
                'Revisar ',
                ': este atributo de la plantilla podría haberse eliminado sin ser reemplazado.'
            ),

        discrepancia_stt:
            conObjetivo(
                'Confirmar ',
                ': existen dos interpretaciones posibles de este dato.'
            )
    };

    if (mensajesContextuales[tipo]) {
        return mensajesContextuales[tipo];
    }

    /*
     * Último respaldo. Solo debería aparecer cuando backend
     * no entregó detalle ni objetivo clínico.
     */
    const mensajesGenericos = {
        valor_sospechoso:
            'Confirmar este valor.',

        falta_unidad:
            'Confirmar la unidad de esta medida.',

        termino_confuso:
            'Confirmar este término.',

        incongruencia:
            'Hay información clínica contradictoria. Confirmar cuál corresponde.',

        medida_ilegible:
            'La medida no es clara. Revisar el audio.',

        valor_faltante:
            'Completar este valor.',

        hallazgo_bajado:
            'Confirmar este hallazgo.',

        inventado:
            'Confirmar este dato.',

        cambio_lateralidad:
            'Confirmar el lado indicado.',

        cambio_medida:
            'Confirmar esta medida.',

        omitido:
            'Revisar este hallazgo omitido.',

        discrepancia_negacion:
            'Confirmar si este hallazgo está presente o ausente.',

        organo_sin_dictado:
            'Confirmar si esta estructura fue evaluada.',

        mismas_caracteristicas_literal:
            'Revisar las características de esta estructura.',

        organo_omitido:
            'Revisar esta estructura omitida.',

        incoherencia_homogeneo:
            'Revisar la homogeneidad descrita.',

        atributo_no_reemplazado:
            'Revisar este atributo de la plantilla.',

        atributo_plantilla_omitido:
            'Revisar este atributo omitido de la plantilla.',

        discrepancia_stt:
            'Confirmar este dato.'
    };

    return mensajesGenericos[tipo]
        || 'Revisar este punto.';
}

function mostrarDetalleAlerta(badge, organo) {
    if (alertaAbierta === organo.organo) {
        cerrarDetalleAlerta();
        return;
    }

    cerrarDetalleAlerta();
    alertaAbierta = organo.organo;
    badge.classList.add('active');

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

    (organo.alertas || []).forEach((alerta, indiceAlerta) => {
        const item = document.createElement('div');
        item.className = 'vm-revision-detalle-item vm-revision-severidad-' + (alerta.severidad || 'media');

        const contenido = document.createElement('div');
        contenido.className = 'vm-revision-detalle-item-contenido';

        contenido.appendChild(
            document.createTextNode(
                obtenerDetalleVisibleAlerta(alerta)
            )
        );

        const origenAlerta = String(alerta?.origen || '')
            .trim()
            .toLowerCase();

        const etiquetaOrigen = document.createElement('span');
        etiquetaOrigen.className =
            'vm-revision-alerta-origen ' +
            (
                origenAlerta === 'generador'
                    ? 'vm-revision-alerta-origen-gen'
                    : origenAlerta === 'revisor'
                        ? 'vm-revision-alerta-origen-rev'
                        : ''
            );

        if (origenAlerta === 'generador') {
            etiquetaOrigen.textContent = 'GEN';
            etiquetaOrigen.title = 'Alerta del generador';
        } else if (origenAlerta === 'revisor') {
            etiquetaOrigen.textContent = 'REV';
            etiquetaOrigen.title = 'Alerta del revisor';
        } else {
            etiquetaOrigen.textContent = 'IA';
            etiquetaOrigen.title = 'Alerta de IA';
        }

        const eliminar = document.createElement('button');
        eliminar.type = 'button';
        eliminar.className = 'vm-revision-alerta-eliminar';
        eliminar.title = 'Eliminar alerta';
        eliminar.setAttribute('aria-label', 'Eliminar alerta');
        eliminar.innerHTML = `
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 6h18"></path>
                <path d="M8 6V4h8v2"></path>
                <path d="M19 6l-1 14H6L5 6"></path>
                <path d="M10 10v6"></path>
                <path d="M14 10v6"></path>
            </svg>
        `;

        eliminar.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (!Array.isArray(organo.alertas)) return;

            organo.alertas.splice(indiceAlerta, 1);

            sincronizarRevisionHidden(datosRevisionActual, true);

            /*
            * Cerramos explícitamente el detalle anterior antes de repintar.
            * Así alertaAbierta también vuelve a null.
            */
            cerrarDetalleAlerta();

            /*
            * Reconstruir también los highlights.
            * Así, al eliminar una alerta desaparece inmediatamente
            * su subrayado del informe.
            */
            aplicar(datosRevisionActual, false);

            /*
            * Si eliminamos la última alerta, simplemente queda cerrado.
            */
            if (!organo.alertas.length) return;

            /*
            * Si todavía quedan alertas, buscamos el badge recién creado
            * por pintarAlertas() y volvemos a abrir el mismo órgano.
            */
            const clave = claveNotaOrgano(organo.organo);
            const overlay = document.getElementById('vm_revision_overlay');

            const contenedor = Array.from(
                overlay?.querySelectorAll('.vm-revision-organo-acciones') || []
            ).find(function (el) {
                return claveNotaOrgano(el.dataset.organo) === clave;
            });

            const nuevoBadge = contenedor?.querySelector('.vm-revision-alerta-badge');

            if (nuevoBadge) {
                mostrarDetalleAlerta(nuevoBadge, organo);
            }
        });

        const acciones = document.createElement('div');
        acciones.className = 'vm-revision-alerta-acciones';

        acciones.appendChild(etiquetaOrigen);
        acciones.appendChild(eliminar);

        item.appendChild(contenido);
        item.appendChild(acciones);
        detalle.appendChild(item);
    });

    document.body.appendChild(detalle);

    posicionarDetalleAlerta(detalle, badge);

    document.removeEventListener(
        'pointerdown',
        cerrarDetalleAlertaClickFuera,
        true
    );

    document.addEventListener(
        'pointerdown',
        cerrarDetalleAlertaClickFuera,
        true
    );
}

function pintarAlertas(datos = null) {
    const overlay = obtenerOverlay();
    if (!overlay) return;

    overlay.innerHTML = '';

    const revision = Array.isArray(datos?.organos) ? datos.organos : [];

    obtenerCardsInforme().forEach(card => {
        const clave = card.clave;

        const organoRevision = revision.find(item =>
            claveNotaOrgano(item.organo) === clave
        );

        const alertas = Array.isArray(organoRevision?.alertas)
            ? organoRevision.alertas
            : [];

        const contenedor = document.createElement('div');
        contenedor.className = 'vm-revision-organo-acciones';
        contenedor.dataset.organo = card.organo;

        contenedor.dataset.clave = clave;
        contenedor.dataset.indice = String(card.indice);

        if (alertas.length) {
            const badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'vm-revision-alerta-badge';
            badge.title = card.organo + ': ' + alertas.length + ' observación' + (alertas.length === 1 ? '' : 'es');
            badge.setAttribute('aria-label', badge.title);
            badge.innerHTML = `
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3L2.8 20h18.4L12 3z"></path>
                    <path d="M12 9v5"></path>
                    <circle cx="12" cy="17" r=".8"></circle>
                </svg>
            `;

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
            mostrarEditorNota(nota, card.organo, clave);
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
    const cards = obtenerCardsInforme();

    overlay.querySelectorAll('.vm-revision-organo-acciones').forEach(contenedor => {
        const indice = Number(contenedor.dataset.indice);
        const card = Number.isInteger(indice) ? cards[indice] : null;

        if (!card?.el) {
            contenedor.style.display = 'none';
            return;
        }

        const rect = card.el.getBoundingClientRect();

        const centroY = rect.top + (rect.height / 2);

        const margenIcono = 10;

        const visible =
            centroY >= (editorRect.top + margenIcono) &&
            centroY <= (editorRect.bottom - margenIcono);

        contenedor.style.display = visible ? '' : 'none';
        contenedor.style.top = (rect.top - wrapperRect.top) + 'px';
        contenedor.style.left = (rect.left - wrapperRect.left) + 'px';
        contenedor.style.right = 'auto';
        contenedor.style.width = rect.width + 'px';
        contenedor.style.height = rect.height + 'px';
    });
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
        characterData: true
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

        if (cargarRevisionInicialDesdeHidden()) {
            return;
        }

        pintarAlertas(null);
    }, 100);
}

function inicializarNotasOrganos() {
    let editorConectado = null;
    let hiddenNotasConectado = null;
    let hiddenRevisionConectado = null;

    function conectar() {
        const raiz = obtenerRaizEditor();
        if (!raiz) return;

        const hiddenNotas = document.getElementById('notas_organos');
        const hiddenRevision = document.getElementById('revision_visual');

        if (
            raiz === editorConectado &&
            hiddenNotas === hiddenNotasConectado &&
            hiddenRevision === hiddenRevisionConectado
        ) return;

        const cambioFormulario =
            hiddenNotas !== hiddenNotasConectado ||
            hiddenRevision !== hiddenRevisionConectado;

        if (observerNotasEditor) {
            observerNotasEditor.disconnect();
            observerNotasEditor = null;
        }

        editorConectado = raiz;
        hiddenNotasConectado = hiddenNotas;
        hiddenRevisionConectado = hiddenRevision;

        if (cambioFormulario) {
            notaAbierta = null;
            notasOrganos = {};
            nombresNotasOrganos = {};
            datosRevisionActual = null;

            limpiarHighlights();
            cerrarDetalleAlerta();
            cargarNotasDesdeHidden();
        }

        if (!cargarRevisionInicialDesdeHidden()) {
            pintarAlertas(datosRevisionActual);
        }

        observerNotasEditor = new MutationObserver(function () {
            programarPintadoNotas();
        });

        observerNotasEditor.observe(raiz, {
            subtree: true,
            childList: true,
            characterData: true
        });
    }

    const observerInicio = new MutationObserver(conectar);

    observerInicio.observe(document.body, {
        childList: true,
        subtree: true
    });

    conectar();
}

function aplicar(datos, persistir = true) {
    limpiarHighlights();
    cerrarDetalleAlerta();

    if (!window.CSS || !CSS.highlights || typeof Highlight === 'undefined') {
        console.warn('VetmindRevision: CSS Highlights no está disponible.');
        return false;
    }

    if (!datos || !Array.isArray(datos.organos)) return false;

    datosRevisionActual = datos;
    sincronizarRevisionHidden(datos, persistir);

    activarReaplicacionEnEdicion();

    const porOrigen = {
        plantilla: [],
        dictado: [],
        desconocido: []
    };

    const rangosDudaSTT = [];

    const ocurrenciasUsadas = new WeakMap();

    const buscarRangoSinRepetir = function (contenedor, texto) {
        let mapa = ocurrenciasUsadas.get(contenedor);

        if (!mapa) {
            mapa = new Map();
            ocurrenciasUsadas.set(contenedor, mapa);
        }

        const clave = normalizarComparacion(texto);
        const ocurrencia = mapa.get(clave) || 0;

        const rango = buscarRangoDom(
            contenedor,
            texto,
            ocurrencia
        );

        if (rango) {
            mapa.set(clave, ocurrencia + 1);
        }

        return rango;
    };

    datos.organos.forEach(organo => {
        const bloque = buscarBloqueOrgano(organo.organo);
        if (!bloque) return;

        (organo.atributos || []).forEach(atributo => {
            const origen = Object.prototype.hasOwnProperty.call(porOrigen, atributo.origen)
                ? atributo.origen
                : 'desconocido';

            const textoAtributo = String(atributo.texto || '');

            /*
            * Los XX tienen su propio resaltado amarillo persistente.
            * No superponer gris/azul porque dificulta ver el dato faltante.
            */
            if (/\bXX\b/i.test(textoAtributo)) {
                return;
            }

            const rango = buscarRangoSinRepetir(
                bloque.el,
                textoAtributo
            );

            if (rango) porOrigen[origen].push(rango);
        });
        (organo.dudasTranscripcion || []).forEach(frase => {
            const rango = buscarRangoDom(bloque.el, frase);

            if (rango) {
                rangosDudaSTT.push(rango);
            }
        });

        (organo.alertas || []).forEach(alerta => {
            const tipo = normalizarComparacion(
                String(alerta.tipo || '')
            );

            /*
            * Los XX ya tienen su propio resaltado amarillo persistente.
            * No necesitan además el subrayado de revisión.
            */
            if (tipo.includes('valor_faltante')) {
                return;
            }

            const objetivo = String(
                alerta.objetivo || ''
            ).trim();

            if (!objetivo) {
                return;
            }

            const rango = buscarRangoDom(
                bloque.el,
                objetivo
            );

            if (rango) {
                rangosDudaSTT.push(rango);
            }
        });
    });

    Object.entries(porOrigen).forEach(([origen, rangos]) => {
        if (!rangos.length) return;

        const nombre = 'vm-revision-' + origen;
        CSS.highlights.set(nombre, new Highlight(...rangos));
        nombresHighlights.push(nombre);
    });

    if (rangosDudaSTT.length) {
        const nombre = 'vm-revision-discrepancia';

        CSS.highlights.set(
            nombre,
            new Highlight(...rangosDudaSTT)
        );

        nombresHighlights.push(nombre);
    }

    pintarAlertas(datos);
    return true;
}

function limpiar() {
    limpiarHighlights();
    cerrarDetalleAlerta();
    datosRevisionActual = null;
    sincronizarRevisionHidden(null, true); 

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


function importarInformeCopiado(paquete) {
    if (
        paquete?.tipo !== 'vetmind-informe' ||
        paquete.version !== 1 ||
        typeof paquete.html !== 'string'
    ) {
        return false;
    }

    // Cerrar cualquier nota o advertencia que esté abierta.
    cerrarEditorNota();
    cerrarDetalleAlerta();

    // El informe copiado sustituye las notas anteriores.
    notasOrganos = {};
    nombresNotasOrganos = {};

    const notas = paquete.notas || {};

    Object.entries(notas).forEach(([clave, entrada]) => {
        const texto = typeof entrada === 'string'
            ? entrada.trim()
            : String(entrada?.nota || '').trim();

        if (!texto) return;

        const organo = typeof entrada === 'object' && entrada
            ? String(entrada.organo || '').trim()
            : '';

        const claveDestino = clave.startsWith('parrafo:')
            ? clave
            : claveNotaOrgano(clave);

        notasOrganos[claveDestino] = texto;

        if (organo) {
            nombresNotasOrganos[claveDestino] = organo;
        }
    });

    sincronizarNotasHidden();

    // Restaurar colores y advertencias, si existen.
    if (Array.isArray(paquete.revision?.organos)) {
        aplicar(paquete.revision);
    } else {
        limpiarHighlights();
        datosRevisionActual = null;
        sincronizarRevisionHidden(null);
        pintarAlertas(null);
    }

    return true;
}


window.VetmindRevision = {
    aplicar,
    limpiar,
    prueba,
    importar: importarInformeCopiado,

    exportar() {
        // Guardar en el campo oculto las notas actuales.
        sincronizarNotasHidden();

        let revision = null;
        let notas = {};

        try {
            const raw = document
                .getElementById('revision_visual')
                ?.value;

            if (raw) {
                const datos = JSON.parse(raw);

                if (Array.isArray(datos?.organos)) {
                    revision = datos;
                }
            }

            if (
                !revision &&
                Array.isArray(datosRevisionActual?.organos)
            ) {
                revision = datosRevisionActual;
            }
        } catch (error) {
            console.error(
                'Error al exportar la revisión:',
                error
            );
        }

        try {
            const raw = document
                .getElementById('notas_organos')
                ?.value;

            if (raw) {
                const datos = JSON.parse(raw);

                if (
                    datos &&
                    typeof datos === 'object' &&
                    !Array.isArray(datos)
                ) {
                    notas = datos;
                }
            }
        } catch (error) {
            console.error(
                'Error al exportar las notas:',
                error
            );
        }

        return {
            version: 1,
            revision,
            notas
        };
    }
};


/*
 * Pegado de informes copiados desde VetMind.
 * Los pegados normales siguen funcionando sin cambios.
 */
if (window.__vmPasteConRevision) {
    document.removeEventListener(
        'paste',
        window.__vmPasteConRevision,
        true
    );
}

window.__vmPasteConRevision = function (evento) {
    const contenedor = document.getElementById(
        'contenido_html_editor'
    );

    if (
        !contenedor ||
        !contenedor.contains(evento.target)
    ) {
        return;
    }

    const html = evento.clipboardData?.getData('text/html') || '';

    if (!html.includes('data-vetmind-informe')) {
        return; // Pegado normal, incluido contenido de Word.
    }

    const plantilla = document.createElement('template');
    plantilla.innerHTML = html;

    const nodo = plantilla.content.querySelector(
        '[data-vetmind-informe]'
    );

    if (!nodo) return;

    // Evita que Tiptap pegue los comentarios como párrafos.
    evento.preventDefault();
    evento.stopImmediatePropagation();

    let paquete;

    try {
        paquete = JSON.parse(
            decodeURIComponent(
                nodo.getAttribute('data-vetmind-informe')
            )
        );
    } catch (error) {
        console.error('Contenido VetMind inválido:', error);
        alert('No se pudieron recuperar los datos del informe.');
        return;
    }

    if (
        paquete?.tipo !== 'vetmind-informe' ||
        paquete.version !== 1 ||
        typeof paquete.html !== 'string' ||
        !paquete.html.trim()
    ) {
        alert('El informe copiado no tiene un formato válido.');
        return;
    }

    const tiptap = window.VetmindTiptap;
    const revision = window.VetmindRevision;
    const editor = tiptap?.getMainEditor?.();

    if (
        !editor ||
        typeof tiptap.setMainEditorHTML !== 'function' ||
        typeof revision?.importar !== 'function'
    ) {
        alert('El editor todavía no está preparado.');
        return;
    }

    /*
     * Esta operación importa un informe completo.
     * No mezcla automáticamente dos informes diferentes.
     */
    if (
        !editor.isEmpty &&
        !confirm(
            'Este pegado reemplazará el informe actual, ' +
            'incluidas sus notas y advertencias. ¿Continuar?'
        )
    ) {
        return;
    }

    try {
        // Usar el HTML ORIGINAL, no el HTML preparado para Word.
        tiptap.setMainEditorHTML(paquete.html);

        // Reconstruir notas, colores y advertencias.
        if (!revision.importar(paquete)) {
            throw new Error('No se pudo restaurar la revisión.');
        }

        tiptap.syncMainEditorToTextarea?.();

        console.info('Informe VetMind importado correctamente.', {
            comentarios: Object.keys(paquete.notas || {}).length,
            organosConRevision:
                paquete.revision?.organos?.length ?? 0
        });
    } catch (error) {
        console.error('Error al importar informe:', error);
        alert('No se pudo completar la importación.');
    }
};

document.addEventListener(
    'paste',
    window.__vmPasteConRevision,
    true
);



/*
 * Copiar informe completo:
 * - HTML enriquecido para Word.
 * - Datos estructurados para restaurar la revisión en VetMind.
 */
function prepararInformeParaPortapapeles() {
    const htmlOriginal = window.VetmindTiptap?.getMainEditorHTML();

    if (!htmlOriginal || !htmlOriginal.trim()) {
        throw new Error('No hay contenido en el informe.');
    }

    const datos = window.VetmindRevision.exportar();

    const paquete = {
        tipo: 'vetmind-informe',
        version: 1,
        html: htmlOriginal,
        revision: datos.revision,
        notas: datos.notas
    };

    // Trabajar sobre una copia, sin modificar el editor.
    const contenido = document.createElement('div');
    contenido.innerHTML = htmlOriginal;

    const parrafos = Array.from(contenido.children)
        .filter(el => el.tagName === 'P');

    const cards = obtenerCardsInforme();

    cards.forEach((card, indice) => {
        const parrafo = parrafos[indice];
        if (!parrafo) return;

        const organoRevision = (datos.revision?.organos || [])
            .find(item =>
                claveNotaOrgano(item.organo) === card.clave
            );

        // Incorporar los colores directamente al HTML de Word.
        const colores = {
            plantilla: '#f8fafc',
            dictado: '#dbeafe',
            desconocido: '#dbeafe'
        };

        (organoRevision?.atributos || []).forEach(atributo => {
            if (!atributo.texto) return;

            const rango = buscarRangoDom(
                parrafo,
                atributo.texto
            );

            if (!rango) return;

            const marca = document.createElement('span');

            marca.style.backgroundColor =
                colores[atributo.origen] ||
                colores.desconocido;

            marca.appendChild(rango.extractContents());
            rango.insertNode(marca);
        });

        let ultimo = parrafo;

        // Advertencias visibles debajo del órgano.
        (organoRevision?.alertas || []).forEach(alerta => {
            const bloque = document.createElement('p');

            bloque.style.cssText = [
                'margin:3px 0 3px 18px',
                'padding:5px 8px',
                'background-color:#fffbeb',
                'border-left:3px solid #f59e0b',
                'color:#92400e',
                'font-size:11px'
            ].join(';');

            const titulo = document.createElement('strong');
            titulo.textContent = 'Advertencia: ';

            bloque.appendChild(titulo);
            bloque.appendChild(
                document.createTextNode(
                    alerta.detalle ||
                    alerta.texto ||
                    'Revisar.'
                )
            );

            ultimo.after(bloque);
            ultimo = bloque;
        });

        // Comentario guardado del órgano.
        const entradaNota = datos.notas?.[card.clave];

        const textoNota = typeof entradaNota === 'string'
            ? entradaNota.trim()
            : String(entradaNota?.nota || '').trim();

        if (textoNota) {
            const bloque = document.createElement('p');

            bloque.style.cssText = [
                'margin:3px 0 5px 18px',
                'padding:5px 8px',
                'background-color:#ecfdf5',
                'border-left:3px solid #0f766e',
                'color:#0f766e',
                'font-size:11px'
            ].join(';');

            const titulo = document.createElement('strong');
            titulo.textContent = 'Comentario: ';

            bloque.appendChild(titulo);
            bloque.appendChild(
                document.createTextNode(textoNota)
            );

            ultimo.after(bloque);
        }
    });

    // El atributo permite reconocer posteriormente
    // que el HTML procede de VetMind.
    const envoltorio = document.createElement('div');

    envoltorio.setAttribute(
        'data-vetmind-informe',
        encodeURIComponent(JSON.stringify(paquete))
    );

    while (contenido.firstChild) {
        envoltorio.appendChild(contenido.firstChild);
    }

    const textoPlano = Array.from(envoltorio.children)
        .map(el => (el.textContent || '').trim())
        .filter(Boolean)
        .join('\n');

    return {
        html: envoltorio.outerHTML,
        textoPlano,
        paquete
    };
}

// Evitar registrar dos veces el controlador.
$(document)
    .off('click.vmCopiarInforme', '#vm_copiar_informe_revision')
    .on(
        'click.vmCopiarInforme',
        '#vm_copiar_informe_revision',
        async function (e) {
            e.preventDefault();

            const boton = this;

            try {
                const copia =
                    prepararInformeParaPortapapeles();

                if (
                    !navigator.clipboard?.write ||
                    typeof ClipboardItem === 'undefined'
                ) {
                    throw new Error(
                        'Este navegador no admite copia HTML enriquecida.'
                    );
                }

                await navigator.clipboard.write([
                    new ClipboardItem({
                        'text/html': new Blob(
                            [copia.html],
                            { type: 'text/html' }
                        ),
                        'text/plain': new Blob(
                            [copia.textoPlano],
                            { type: 'text/plain' }
                        )
                    })
                ]);

                boton.title = 'Informe copiado';

                // Eliminar un aviso anterior si se vuelve a copiar.
                document.getElementById('vm-copia-aviso')?.remove();

                if (boton._vmCopyFeedbackTimer) {
                    clearTimeout(boton._vmCopyFeedbackTimer);
                }

                // Mostrar confirmación junto al botón.
                const aviso = document.createElement('div');

                aviso.id = 'vm-copia-aviso';
                aviso.className = 'vm-copy-toast';
                aviso.setAttribute('role', 'status');
                aviso.textContent = '✓ Informe copiado';

                const rect = boton.getBoundingClientRect();

                aviso.style.top = (rect.bottom + 8) + 'px';
                aviso.style.right =
                    Math.max(8, window.innerWidth - rect.right) + 'px';

                document.body.appendChild(aviso);

                boton.classList.add('is-copied');
                boton.title = 'Informe copiado';

                boton._vmCopyFeedbackTimer = setTimeout(() => {
                    aviso.remove();
                    boton.classList.remove('is-copied');

                    boton.title =
                        'Copiar informe con revisión para VetMind o Word';
                }, 1800);
            } catch (error) {
                console.error(
                    'Error al copiar el informe:',
                    error
                );

                alert(
                    'No se pudo copiar el informe: ' +
                    error.message
                );
            }
        }
    );
