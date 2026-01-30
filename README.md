# SecureDesk DAM

Panel de administracion

## Requisitos

- XAMPP

## Estructura del proyecto

- app/: lógica del proyecto y configuración
- db/: base de datos SQLite 
- docs/: documentación 
- public/: punto de entrada 
- views/: vistas de la aplicación

## Base de datos

- La aplicación utiliza SQLite como base de datos local
- El archivo se encuentra en /db/securedesk.sqlite
- La conexión se realiza mediante PDO desde la clase Database

## Instalacion

1. Iniciar XAMPP
2. Ejecutar el servicio Apache
3. Clonar o copiar el proyecto dentro del directorio htdocs de XAMPP
4. Acceder al proyecto desde un navegador web:

   http://localhost/securedesk-dam/public

La base de datos SQLite se crea automáticamente en la carpeta /db al iniciar la aplicación

## Inicialización de la base de datos

La primera vez que se ejecuta el proyecto, es necesario crear las tablas iniciales. Para ello, acceder desde el navegador a:

    http://localhost/securedesk-dam/public/init_db.php

Este script crea las tablas base del sistema (users y tickets).
Este paso solo es necesario la primera vez.
Posteriormente, se puede acceder normalmente a la aplicación desde:

    http://localhost/securedesk-dam/public