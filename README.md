# Deportes PNP — Carrera 5K

Sistema web inicial para registrar participantes, recibir comprobantes de pago por Yappy, validar pagos, reservar cupos y controlar la entrega de kits.

## Datos iniciales

- Evento: Carrera 5K Policía Nacional.
- Fecha: sábado 25 de julio de 2026.
- Hora de salida: por confirmar desde el panel administrativo.
- Categorías: 18 a 39 años y 40 años en adelante.
- Entrega de kits: jueves 23 y viernes 24 de julio de 2026, Sede A.
- Yappy receptor: **63977539**.

## Funciones incluidas

- Página pública adaptable a celulares.
- Categoría automática según la edad el día del evento.
- Comprobantes JPG, PNG o PDF de hasta 5 MB.
- Validación manual del pago antes de reservar el cupo.
- Panel administrativo con control de pagos y kits.
- Consulta pública por código y últimos cuatro números de identificación.
- Exportación CSV compatible con Excel.
- Auditoría básica de operaciones.

## Instalación local con Git Bash y XAMPP

### 1. Iniciar XAMPP

Inicie **Apache** y **MySQL** desde el panel de XAMPP.

### 2. Abrir Git Bash

```bash
cd /c/xampp/htdocs
git clone https://github.com/rrene7/deportes_pnp.git
cd deportes_pnp
```

### 3. Ejecutar el instalador

```bash
bash install.sh
```

El instalador detecta automáticamente:

- `/c/xampp/php/php.exe`
- `/c/xampp/mysql/bin/mysql.exe`

Después solicita:

- Servidor, puerto, usuario y contraseña de MySQL.
- Nombre de la base de datos, cuyo valor recomendado es `deportes_pnp`.
- Nombre, usuario y contraseña del primer administrador.

Con una instalación normal de XAMPP puede aceptar estos valores presionando **Enter**:

- Host: `127.0.0.1`
- Puerto: `3306`
- Usuario: `root`
- Contraseña: vacía
- Base de datos: `deportes_pnp`

### 4. Abrir el sistema

- Página pública: `http://localhost/deportes_pnp/`
- Administración: `http://localhost/deportes_pnp/admin/`

## Volver a ejecutar el instalador

Para actualizar las tablas o restablecer la contraseña del administrador sin borrar inscripciones:

```bash
bash install.sh --force
```

El modo `--force` no elimina la base de datos. Actualiza el esquema y crea o actualiza el usuario administrativo indicado.

## Configuración y seguridad

El instalador crea automáticamente `config/config.php`. Ese archivo, los comprobantes y `storage/installed.lock` están excluidos por `.gitignore`.

Antes de publicar el sistema en internet:

- Use HTTPS.
- Use una cuenta MySQL con contraseña y permisos limitados.
- No suba `config/config.php` a GitHub.
- No publique directamente `storage/uploads`.
- Mantenga separados los usuarios de pagos, kits y consulta.
- Realice respaldos de la base de datos y de los comprobantes.
