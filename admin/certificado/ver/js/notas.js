(function () {
    let certificadoActual = 0;
    let notasActuales = [];

    function normalizar(valor) {
        return String(valor || '')
            .trim()
            .toLowerCase();
    }

    function normalizarComparacion(valor) {
        return normalizar(valor)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ');
    }

    function limpiar() {
        certificadoActual = 0;
        notasActuales = [];

        $('#verInformeContenido .vm-ver-nota-btn').remove();
        $('#verInformeContenido .vm-ver-nota-panel').remove();
        $('#verInformeContenido .vm-ver-nota-seccion')
            .removeClass('vm-ver-nota-seccion');
    }

    function obtenerSecciones() {
        const raiz = document.getElementById('verInformeContenido');
        if (!raiz) return [];

        const candidatos = notasActuales
            .map(nota => ({
                clave: String(nota.organo_clave || '').trim(),
                nombre: String(nota.organo_nombre || '').trim()
            }))
            .filter(item => item.clave && item.nombre)
            .sort((a, b) => b.nombre.length - a.nombre.length);

        return Array.from(raiz.querySelectorAll('p')).map((el, indice) => {
            const texto = (el.textContent || '')
                .trim()
                .replace(/\s+/g, ' ');

            const textoNorm = normalizarComparacion(texto);

            for (const candidato of candidatos) {
                const nombreNorm = normalizarComparacion(candidato.nombre);

                if (!textoNorm.startsWith(nombreNorm)) continue;

                const siguiente = textoNorm.charAt(nombreNorm.length);

                if (!siguiente || /[\s:.,;-]/.test(siguiente)) {
                    return {
                        el,
                        indice,
                        clave: candidato.clave,
                        nombre: candidato.nombre
                    };
                }
            }

            const etiqueta = el.querySelector('strong, em');

            if (etiqueta) {
                const nombre = (etiqueta.textContent || '')
                    .trim()
                    .replace(/:$/, '')
                    .replace(/\s+/g, ' ');

                if (
                    nombre &&
                    normalizarComparacion(nombre) !== textoNorm &&
                    textoNorm.startsWith(normalizarComparacion(nombre))
                ) {
                    return {
                        el,
                        indice,
                        clave: normalizar(nombre),
                        nombre
                    };
                }
            }

            const match = texto.match(/^([^:]{2,100}):/);

            if (match) {
                const nombre = match[1].trim();

                return {
                    el,
                    indice,
                    clave: normalizar(nombre),
                    nombre
                };
            }

            return {
                el,
                indice,
                clave: 'parrafo:' + indice,
                nombre: 'Párrafo ' + (indice + 1)
            };
        });
    }

    function cerrarPaneles() {
        $('.vm-ver-nota-panel').remove();
    }

    function mostrarPanel(seccion, boton) {
        const panelActual = document.querySelector(
            '.vm-ver-nota-panel[data-clave="' +
            CSS.escape(seccion.clave) +
            '"]'
        );

        if (panelActual) {
            cerrarPaneles();
            return;
        }

        cerrarPaneles();

        const notas = notasActuales.filter(
            nota => String(nota.organo_clave) === seccion.clave
        );

        const notaMia = notas.find(nota => nota.es_mia) || null;

        const panel = document.createElement('div');
        panel.className = 'vm-ver-nota-panel';
        panel.dataset.clave = seccion.clave;

        const listado = document.createElement('div');
        listado.className = 'vm-ver-nota-listado';

        notas.forEach(nota => {
            const item = document.createElement('div');
            item.className = 'vm-ver-nota-item';

            const autor = document.createElement('div');
            autor.className = 'vm-ver-nota-autor';
            autor.textContent = nota.es_mia
                ? 'Tú'
                : (nota.autor || 'Usuario');

            const texto = document.createElement('div');
            texto.className = 'vm-ver-nota-texto';
            texto.textContent = nota.nota || '';

            item.appendChild(autor);
            item.appendChild(texto);
            listado.appendChild(item);
        });

        if (notas.length) {
            panel.appendChild(listado);
        }

        const editor = document.createElement('div');
        editor.className = 'vm-ver-nota-editor';

        const input = document.createElement('textarea');
        input.className = 'form-control form-control-sm';
        input.rows = 2;
        input.placeholder = 'Escribir comentario...';
        input.value = notaMia?.nota || '';

        const acciones = document.createElement('div');
        acciones.className = 'vm-ver-nota-editor-acciones';

        const guardar = document.createElement('button');
        guardar.type = 'button';
        guardar.className = 'vm-ver-nota-accion vm-ver-nota-guardar';
        guardar.title = notaMia ? 'Guardar cambios' : 'Guardar comentario';
        guardar.setAttribute('aria-label', guardar.title);
        guardar.innerHTML = '<i class="fas fa-check"></i>';

        guardar.addEventListener('click', function () {
            guardarNota(
                seccion,
                input.value.trim(),
                guardar
            );
        });

        acciones.appendChild(guardar);

        if (notaMia) {
            const eliminar = document.createElement('button');
            eliminar.type = 'button';
            eliminar.className = 'vm-ver-nota-accion vm-ver-nota-eliminar';
            eliminar.title = 'Eliminar comentario';
            eliminar.setAttribute('aria-label', eliminar.title);
            eliminar.innerHTML = '<i class="fas fa-trash-alt"></i>';

            eliminar.addEventListener('click', function () {
                guardarNota(seccion, '', eliminar);
            });

            acciones.appendChild(eliminar);
        }

        editor.appendChild(input);
        editor.appendChild(acciones);
        panel.appendChild(editor);

        document.body.appendChild(panel);

        const rect = boton.getBoundingClientRect();
        const ancho = panel.offsetWidth;
        const alto = panel.offsetHeight;
        const margen = 8;

        let left = rect.right - ancho;
        let top = rect.bottom + 6;

        left = Math.max(
            margen,
            Math.min(left, window.innerWidth - ancho - margen)
        );

        if (top + alto > window.innerHeight - margen) {
            top = rect.top - alto - 6;
        }

        top = Math.max(
            margen,
            Math.min(top, window.innerHeight - alto - margen)
        );

        panel.style.left = left + 'px';
        panel.style.top = top + 'px';

        setTimeout(function () {
            input.focus();
            input.setSelectionRange(
                input.value.length,
                input.value.length
            );
        }, 0);
    }

    function pintar() {
        cerrarPaneles();

        $('#verInformeContenido .vm-ver-nota-btn').remove();

        obtenerSecciones().forEach(seccion => {
            const notas = notasActuales.filter(
                nota => String(nota.organo_clave) === seccion.clave
            );

            seccion.el.classList.add('vm-ver-nota-seccion');

            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className =
                'vm-ver-nota-btn' +
                (notas.length ? ' tiene-notas' : '');

            boton.title = notas.length
                ? 'Ver comentarios (' + notas.length + ')'
                : 'Agregar comentario';

            boton.innerHTML =
                '<i class="far fa-comment"></i>' +
                (notas.length
                    ? '<span>' + notas.length + '</span>'
                    : '');

            boton.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                mostrarPanel(seccion, boton);
            });

            seccion.el.appendChild(boton);
        });
    }

    function cargar(certificadoId) {
        certificadoActual = parseInt(certificadoId, 10) || 0;

        if (!certificadoActual) {
            limpiar();
            return;
        }

        $.ajax({
            url: 'certificado/ver/notas.php',
            type: 'GET',
            dataType: 'json',
            data: {
                accion: 'listar',
                certificado_id: certificadoActual
            },
            success: function (response) {
                if (!response || response.status !== 'success') {
                    notasActuales = [];
                    pintar();
                    return;
                }

                notasActuales = Array.isArray(response.notas)
                    ? response.notas
                    : [];

                pintar();
            }
        });
    }

    function guardarNota(seccion, texto, boton) {
        if (!certificadoActual) return;

        $(boton).prop('disabled', true);

        $.ajax({
            url: 'certificado/ver/notas.php',
            type: 'POST',
            dataType: 'json',
            data: {
                accion: 'guardar',
                certificado_id: certificadoActual,
                organo_clave: seccion.clave,
                organo_nombre: seccion.nombre,
                nota: texto
            },
            success: function (response) {
                if (!response || response.status !== 'success') {
                    Swal.fire(
                        'Error',
                        response?.message || 'No se pudo guardar el comentario.',
                        'error'
                    );

                    $(boton).prop('disabled', false);
                    return;
                }

                cargar(certificadoActual);
            },
            error: function (xhr) {
                Swal.fire(
                    'Error',
                    xhr.responseJSON?.message ||
                        'No se pudo guardar el comentario.',
                    'error'
                );

                $(boton).prop('disabled', false);
            }
        });
    }

    $(document)
        .off('mousedown.verNotasCerrar')
        .on('mousedown.verNotasCerrar', function (e) {
            if (
                !$(e.target).closest(
                    '.vm-ver-nota-panel, .vm-ver-nota-btn'
                ).length
            ) {
                cerrarPaneles();
            }
        });

    window.VetmindVerNotas = {
        cargar,
        limpiar
    };
})();
