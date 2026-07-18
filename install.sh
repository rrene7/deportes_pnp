#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_ROOT"

FORCE=0
if [[ "${1:-}" == "--force" ]]; then
  FORCE=1
elif [[ -n "${1:-}" ]]; then
  echo "Uso: bash install.sh [--force]" >&2
  exit 2
fi

log() { printf '\n[INFO] %s\n' "$*"; }
ok() { printf '[OK] %s\n' "$*"; }
fail() { printf '\n[ERROR] %s\n' "$*" >&2; exit 1; }

find_executable() {
  local env_name="$1"
  shift
  local configured="${!env_name:-}"
  if [[ -n "$configured" && -x "$configured" ]]; then
    printf '%s' "$configured"
    return 0
  fi

  local candidate
  for candidate in "$@"; do
    if [[ "$candidate" == command:* ]]; then
      candidate="${candidate#command:}"
      if command -v "$candidate" >/dev/null 2>&1; then
        command -v "$candidate"
        return 0
      fi
    elif [[ -x "$candidate" ]]; then
      printf '%s' "$candidate"
      return 0
    fi
  done
  return 1
}

prompt_default() {
  local __var_name="$1"
  local label="$2"
  local default_value="$3"
  local value
  read -r -p "$label [$default_value]: " value
  printf -v "$__var_name" '%s' "${value:-$default_value}"
}

prompt_secret_optional() {
  local __var_name="$1"
  local label="$2"
  local value
  read -r -s -p "$label: " value
  printf '\n'
  printf -v "$__var_name" '%s' "$value"
}

prompt_admin_password() {
  local first second
  while true; do
    read -r -s -p "Contraseña del administrador (mínimo 8 caracteres): " first
    printf '\n'
    if (( ${#first} < 8 )); then
      echo "La contraseña debe tener al menos 8 caracteres."
      continue
    fi
    read -r -s -p "Repita la contraseña: " second
    printf '\n'
    if [[ "$first" != "$second" ]]; then
      echo "Las contraseñas no coinciden."
      continue
    fi
    ADMIN_PASSWORD="$first"
    return 0
  done
}

printf '%s\n' "============================================================"
printf '%s\n' " Instalador Bash - Deportes PNP / Carrera 5K"
printf '%s\n' "============================================================"
printf 'Proyecto: %s\n' "$PROJECT_ROOT"

LOCK_FILE="$PROJECT_ROOT/storage/installed.lock"
CONFIG_FILE="$PROJECT_ROOT/config/config.php"
SCHEMA_FILE="$PROJECT_ROOT/database/schema.sql"

[[ -f "$SCHEMA_FILE" ]] || fail "No existe database/schema.sql."

if [[ -f "$LOCK_FILE" && "$FORCE" -ne 1 ]]; then
  fail "El sistema ya aparece instalado. Use 'bash install.sh --force' para actualizar el esquema o restablecer el administrador sin borrar los datos."
fi

PHP_BIN="$(find_executable PHP_BIN \
  /c/xampp/php/php.exe \
  /d/xampp/php/php.exe \
  /opt/lampp/bin/php \
  command:php)" || fail "No se encontró PHP. Inicie desde Git Bash con XAMPP instalado o defina PHP_BIN."

MYSQL_BIN="$(find_executable MYSQL_BIN \
  /c/xampp/mysql/bin/mysql.exe \
  /d/xampp/mysql/bin/mysql.exe \
  /opt/lampp/bin/mysql \
  command:mysql)" || fail "No se encontró el cliente MySQL. Inicie desde Git Bash con XAMPP instalado o defina MYSQL_BIN."

ok "PHP detectado: $PHP_BIN"
ok "MySQL detectado: $MYSQL_BIN"

if ! "$PHP_BIN" -m 2>/dev/null | tr -d '\r' | grep -qi '^pdo_mysql$'; then
  fail "PHP no tiene habilitada la extensión pdo_mysql. Revise php.ini de XAMPP."
fi

prompt_default DB_HOST "Servidor MySQL" "127.0.0.1"
prompt_default DB_PORT "Puerto MySQL" "3306"
prompt_default DB_NAME "Nombre de la base de datos" "deportes_pnp"
prompt_default DB_USER "Usuario MySQL" "root"
prompt_secret_optional DB_PASSWORD "Contraseña MySQL (Enter si está vacía)"

[[ "$DB_PORT" =~ ^[0-9]{1,5}$ ]] || fail "El puerto MySQL no es válido."
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || fail "El nombre de la base solo puede contener letras, números y guion bajo."
[[ -n "$DB_USER" ]] || fail "El usuario MySQL no puede estar vacío."

prompt_default ADMIN_NAME "Nombre del administrador" "Administrador"
prompt_default ADMIN_USER "Usuario administrador" "admin"
[[ -n "$ADMIN_NAME" ]] || fail "El nombre del administrador no puede estar vacío."
[[ "$ADMIN_USER" =~ ^[A-Za-z0-9._-]{3,80}$ ]] || fail "El usuario administrador debe tener entre 3 y 80 caracteres y usar letras, números, punto, guion o guion bajo."
prompt_admin_password

MYSQL_ARGS=(--protocol=tcp -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --default-character-set=utf8mb4)

log "Comprobando conexión con MySQL..."
if ! MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" "${MYSQL_ARGS[@]}" -e "SELECT 1;" >/dev/null 2>&1; then
  fail "No fue posible conectarse a MySQL. Verifique que MySQL esté iniciado en XAMPP y que las credenciales sean correctas."
fi
ok "Conexión con MySQL correcta."

log "Creando la base de datos si todavía no existe..."
MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" "${MYSQL_ARGS[@]}" \
  -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
ok "Base de datos preparada: $DB_NAME"

log "Creando el archivo de configuración local..."
mkdir -p "$PROJECT_ROOT/config" "$PROJECT_ROOT/storage/uploads"

if [[ -f "$CONFIG_FILE" ]]; then
  cp "$CONFIG_FILE" "$CONFIG_FILE.bak.$(date +%Y%m%d_%H%M%S)"
  ok "Se guardó una copia de la configuración anterior."
fi

CONFIG_TMP="$CONFIG_FILE.tmp"
DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASSWORD="$DB_PASSWORD" \
  "$PHP_BIN" -r '
$export = static fn(string $name): string => var_export((string) getenv($name), true);
echo "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n";
echo "    \"db\" => [\n";
echo "        \"host\" => " . $export("DB_HOST") . ",\n";
echo "        \"port\" => " . (int) getenv("DB_PORT") . ",\n";
echo "        \"name\" => " . $export("DB_NAME") . ",\n";
echo "        \"user\" => " . $export("DB_USER") . ",\n";
echo "        \"pass\" => " . $export("DB_PASSWORD") . ",\n";
echo "        \"charset\" => \"utf8mb4\",\n";
echo "    ],\n";
echo "    \"app\" => [\n";
echo "        \"name\" => \"Deportes PNP - Carrera 5K\",\n";
echo "        \"base_url\" => \"\",\n";
echo "        \"timezone\" => \"America/Panama\",\n";
echo "        \"max_upload_bytes\" => 5 * 1024 * 1024,\n";
echo "    ],\n";
echo "];\n";
' > "$CONFIG_TMP"

"$PHP_BIN" -l "$CONFIG_TMP" >/dev/null
mv "$CONFIG_TMP" "$CONFIG_FILE"
ok "Configuración creada en config/config.php"

log "Cargando tablas y datos iniciales..."
MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" < "$SCHEMA_FILE"
ok "Esquema actualizado sin borrar inscripciones existentes."

log "Creando o actualizando el usuario administrador..."
ADMIN_HELPER="$PROJECT_ROOT/.install_admin.php"
cat > "$ADMIN_HELPER" <<'PHP'
<?php

declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';
$db = $config['db'];
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'],
    $db['port'],
    $db['name'],
    $db['charset']
);

$pdo = new PDO($dsn, $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$name = (string) getenv('INSTALL_ADMIN_NAME');
$user = (string) getenv('INSTALL_ADMIN_USER');
$password = (string) getenv('INSTALL_ADMIN_PASSWORD');

$stmt = $pdo->prepare(
    "INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo)
     VALUES (:nombre, :usuario, :password_hash, 'administrador', 1)
     ON DUPLICATE KEY UPDATE
       nombre = VALUES(nombre),
       password_hash = VALUES(password_hash),
       rol = 'administrador',
       activo = 1"
);
$stmt->execute([
    'nombre' => $name,
    'usuario' => $user,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);
PHP

cleanup() {
  rm -f "$ADMIN_HELPER" "$CONFIG_TMP"
  unset ADMIN_PASSWORD DB_PASSWORD INSTALL_ADMIN_PASSWORD MYSQL_PWD || true
}
trap cleanup EXIT

INSTALL_ADMIN_NAME="$ADMIN_NAME" \
INSTALL_ADMIN_USER="$ADMIN_USER" \
INSTALL_ADMIN_PASSWORD="$ADMIN_PASSWORD" \
  "$PHP_BIN" "$ADMIN_HELPER"
ok "Administrador preparado: $ADMIN_USER"

log "Aplicando permisos locales..."
touch "$PROJECT_ROOT/storage/uploads/.gitkeep"
printf '%s\n' "$(date -Iseconds)" > "$LOCK_FILE"
chmod 775 "$PROJECT_ROOT/storage" "$PROJECT_ROOT/storage/uploads" 2>/dev/null || true
chmod 640 "$CONFIG_FILE" 2>/dev/null || true
ok "Carpeta de comprobantes y bloqueo del instalador preparados."

PROJECT_SLUG="$(basename "$PROJECT_ROOT")"
printf '\n%s\n' "============================================================"
printf '%s\n' " INSTALACIÓN COMPLETADA"
printf '%s\n' "============================================================"
printf 'Página pública:       http://localhost/%s/\n' "$PROJECT_SLUG"
printf 'Panel administrativo: http://localhost/%s/admin/\n' "$PROJECT_SLUG"
printf 'Usuario:               %s\n' "$ADMIN_USER"
printf 'Base de datos:         %s\n' "$DB_NAME"
printf '\nNo comparta config/config.php ni los comprobantes subidos.\n'
