<?php
/**
 * PΛRADISE OFFENSIVE FIREWALL - GZIP BOMB & TARPIT
 * ADVERTENCIA: Este script consume la RAM de los clientes que intentan leerlo.
 */

// 1. Mentir sobre el tamaño y el formato (obligamos al bot a descomprimir)
header("Content-Encoding: gzip");
header("Content-Type: text/html; charset=utf-8");
// Le decimos que el archivo pesa 10 Gigabytes, aunque enviaremos infinito
header("Content-Length: 10737418240"); 

// 2. Cabecera GZIP estándar para engañar al parser del atacante
echo "\x1f\x8b\x08\x00\x00\x00\x00\x00";

// Deshabilitar el límite de tiempo de ejecución de PHP para que la trampa sea eterna
set_time_limit(0); 

// 3. El Bucle de la Muerte (Tarpit)
// Generamos 1MB de ceros comprimibles
$chunk = str_repeat("0", 1024 * 1024); 

while (true) {
    // Enviamos la basura al atacante
    echo $chunk;
    
    // Forzamos a Apache a expulsar los datos hacia la red inmediatamente
    ob_flush();
    flush();
    
    // Pausa de 10 milisegundos para mantener la conexión viva eternamente (Tarpitting)
    // Esto agota los "hilos de conexión" del escáner del atacante.
    usleep(10000); 
    
    // Si el atacante aborta la conexión, terminamos el proceso limpiamente en nuestro servidor
    if (connection_aborted()) {
        break;
    }
}