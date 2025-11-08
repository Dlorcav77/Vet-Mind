function checkRut(rut) {
    // Despejar Puntos
    var valor = rut.value.replace(/[^\dKk]/g, '');


    // Aislar Cuerpo y Dito Verificador
    cuerpo = valor.slice(0,-1);
    dv = valor.slice(-1).toUpperCase();
    
    // Formatear RUN
    rut.value = cuerpo + '-'+ dv
    
    // Si no cumple con el mimo ej. (n.nnn.nnn)
    if(cuerpo.length < 7) { rut.setCustomValidity("RUT Incompleto"); return false;}
    
    // Calcular Dito Verificador
    suma = 0;
    multiplo = 2;
    
    // Para cada dito del Cuerpo
    for(i=1;i<=cuerpo.length;i++) {
    
        // Obtener su Producto con el Mtiplo Correspondiente
        index = multiplo * valor.charAt(cuerpo.length - i);
        
        // Sumar al Contador General
        suma = suma + index;
        
        // Consolidar Mtiplo dentro del rango [2,7]
        if(multiplo < 7) { multiplo = multiplo + 1; } else { multiplo = 2; }
  
    }
    
    // Calcular Dito Verificador en base al Mulo 11
    dvEsperado = 11 - (suma % 11);
    
    // Casos Especiales (0 y K)
    dv = (dv == 'K')?10:dv;
    dv = (dv == 0)?11:dv;
    
    // Validar que el Cuerpo coincide con su Dito Verificador
    if(dvEsperado != dv) { rut.setCustomValidity("RUT Invalido"); return false; }
    
    // Si todo sale bien, eliminar errores (decretar que es vido)
    rut.setCustomValidity('');
}