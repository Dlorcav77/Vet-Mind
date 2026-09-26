<?php
// funciones/GPT/lib/stt_keyterms.php
// Lista única de términos veterinarios para sesgar la transcripción (keyterms/word_boost).
// La usan los motores SOLO si la llamada manda usar_keyterms=1.
// Probado: a veces empeora; por eso va OFF por defecto y se activa por llamada.
return [
    'Bazo','Yeyuno','Íleon','Duodeno','Páncreas','Colon','Estómago',
    'Riñón','Vejiga','Próstata','Hígado','Vesícula biliar','Linfonódulos',
    'Adrenal','Ciego','Peritoneo','ecogenicidad','anecoico','hipoecoico',
    'hiperecoico','parénquima','estratificación','esplénico','felino',
    'aguzados','engrosado','engrosada','mucoso','ovario','cuerpo uterino',
    'corticomedular','vasculatura','homogéneo','lóbulo','reactivo',
    'distendida','redondeados',
];