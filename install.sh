#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

FORCE=0
case "${1:-}" in
  "") ;;
  --force) FORCE=1 ;;
  *) echo "Uso: bash install.sh [--force]" >&2; exit 2 ;;
esac

log()  { printf '\n[INFO] %s\n' "$*"; }
ok()   { printf '[OK] %s\n' "$*"; }
fail() { printf '\n[ERROR] %s\n' "$*" >&2; exit 1; }

TTY_DEVICE="/dev/tty"
[[ -r "$TTY_DEVICE" ]] || TTY_DEVICE="/dev/stdin"

prompt_default() {
  local variable="$1" label="$2" default="$3" value=""
  if ! IFS= read -r -p "$label [$default]: " value < "$TTY_DEVICE"; then
    fail "No fue posible leer la respuesta para: $label"
  fi
  printf -v "$variable" '%s' "${value:-$default}"
}

prompt_secret_optional() {
  local variable="$1" label="$2" value=""
  if ! IFS= read -r -s -p "$label: " value < "$TTY_DEVICE"; then
    printf '\n'
    fail "No fue posible leer la contraseña de MySQL."
  fi
  printf '\n'
  printf -v "$variable" '%s' "$value"
}

prompt_admin_password() {
  local first="" second=""
  while true; do
    if ! IFS= read -r -s -p "Contraseña del administrador (mínimo 8 caracteres): " first < "$TTY_DEVICE"; then
      printf '\n'
      fail "No fue posible leer la contraseña del administrador."
    fi
    printf '\n'
    if (( ${#first} < 8 )); then
      echo "La contraseña debe tener al menos 8 caracteres."
      continue
    fi
    if ! IFS= read -r -s -p "Repita la contraseña: " second < "$TTY_DEVICE"; then
      printf '\n'
      fail "No fue posible leer la confirmación de la contraseña."
    fi
    printf '\n'
    if [[ "$first" != "$second" ]]; then
      echo "Las contraseñas no coinciden."
      continue
    fi
    ADMIN_PASSWORD="$first"
    break
  done
}

find_bin() {
  local configured="$1"; shift
  local candidate
  [[ -n "$configured" && -x "$configured" ]] && { printf '%s' "$configured"; return; }
  for candidate in "$@"; do
    if [[ "$candidate" == command:* ]]; then
      candidate="${candidate#command:}"
      command -v "$candidate" >/dev/null 2>&1 && { command -v "$candidate"; return; }
    elif [[ -x "$candidate" ]]; then
      printf '%s' "$candidate"; return
    fi
  done
  return 1
}

printf '%s\n' '============================================================'
printf '%s\n' ' Instalador Bash - Deportes PNP / Carrera 5K'
printf '%s\n' '============================================================'
printf 'Proyecto: %s\n' "$ROOT"

LOCK_FILE="$ROOT/storage/installed.lock"
CONFIG_FILE="$ROOT/config/config.php"
SCHEMA_FILE="$ROOT/database/schema.sql"
ADMIN_HELPER="$ROOT/.install_admin.php"
CONFIG_TMP="$CONFIG_FILE.tmp"

[[ -f "$SCHEMA_FILE" ]] || fail "No existe database/schema.sql."
if [[ -f "$LOCK_FILE" && "$FORCE" -ne 1 ]]; then
  fail "El sistema ya está instalado. Use: bash install.sh --force"
fi

PHP_BIN="$(find_bin "${PHP_BIN:-}" /c/xampp/php/php.exe /d/xampp/php/php.exe /opt/lampp/bin/php command:php)" \
  || fail "No se encontró PHP."
MYSQL_BIN="$(find_bin "${MYSQL_BIN:-}" /c/xampp/mysql/bin/mysql.exe /d/xampp/mysql/bin/mysql.exe /opt/lampp/bin/mysql command:mysql)" \
  || fail "No se encontró el cliente MySQL."

ok "PHP detectado: $PHP_BIN"
ok "MySQL detectado: $MYSQL_BIN"

if ! "$PHP_BIN" -m 2>/dev/null | tr -d '\r' | grep -qi '^pdo_mysql$'; then
  fail "PHP no tiene habilitada la extensión pdo_mysql."
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
[[ "$ADMIN_USER" =~ ^[A-Za-z0-9._-]{3,80}$ ]] || fail "El usuario administrador no es válido."
prompt_admin_password

MYSQL_ARGS=(--protocol=tcp -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --default-character-set=utf8mb4)

log "Comprobando conexión con MySQL..."
if ! MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" "${MYSQL_ARGS[@]}" -e "SELECT 1;" >/dev/null 2>&1; then
  fail "No fue posible conectarse a MySQL. Confirme que MySQL esté iniciado en XAMPP."
fi
ok "Conexión con MySQL correcta."

log "Preparando la base de datos..."
MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" "${MYSQL_ARGS[@]}" \
  -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$DB_NAME" < "$SCHEMA_FILE"
ok "Base de datos y tablas preparadas: $DB_NAME"

log "Creando configuración local..."
mkdir -p "$ROOT/config" "$ROOT/storage/uploads"
if [[ -f "$CONFIG_FILE" ]]; then
  cp "$CONFIG_FILE" "$CONFIG_FILE.bak.$(date +%Y%m%d_%H%M%S)"
fi

DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASSWORD="$DB_PASSWORD" \
"$PHP_BIN" -r '
$e = static fn(string $n): string => var_export((string) getenv($n), true);
echo "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n";
echo "    \"db\" => [\n";
echo "        \"host\" => " . $e("DB_HOST") . ",\n";
echo "        \"port\" => " . (int) getenv("DB_PORT") . ",\n";
echo "        \"name\" => " . $e("DB_NAME") . ",\n";
echo "        \"user\" => " . $e("DB_USER") . ",\n";
echo "        \"pass\" => " . $e("DB_PASSWORD") . ",\n";
echo "        \"charset\" => \"utf8mb4\",\n    ],\n";
echo "    \"app\" => [\n";
echo "        \"name\" => \"Deportes PNP - Carrera 5K\",\n";
echo "        \"base_url\" => \"\",\n";
echo "        \"timezone\" => \"America/Panama\",\n";
echo "        \"max_upload_bytes\" => 5 * 1024 * 1024,\n    ],\n];\n";
' > "$CONFIG_TMP"
"$PHP_BIN" -l "$CONFIG_TMP" >/dev/null
mv "$CONFIG_TMP" "$CONFIG_FILE"
ok "Configuración creada en config/config.php"

log "Creando o actualizando el administrador..."
cat > "$ADMIN_HELPER" <<'PHP'
<?php
declare(strict_types=1);
$config = require __DIR__ . '/config/config.php';
$db = $config['db'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']),
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$stmt = $pdo->prepare(
    "INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo)
     VALUES (:nombre, :usuario, :password_hash, 'administrador', 1)
     ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), password_hash=VALUES(password_hash), rol='administrador', activo=1"
);
$stmt->execute([
    'nombre' => (string) getenv('INSTALL_ADMIN_NAME'),
    'usuario' => (string) getenv('INSTALL_ADMIN_USER'),
    'password_hash' => password_hash((string) getenv('INSTALL_ADMIN_PASSWORD'), PASSWORD_DEFAULT),
]);
PHP

cleanup() {
  rm -f "$ADMIN_HELPER" "$CONFIG_TMP"
  unset ADMIN_PASSWORD DB_PASSWORD MYSQL_PWD INSTALL_ADMIN_PASSWORD || true
}
trap cleanup EXIT

INSTALL_ADMIN_NAME="$ADMIN_NAME" INSTALL_ADMIN_USER="$ADMIN_USER" INSTALL_ADMIN_PASSWORD="$ADMIN_PASSWORD" \
  "$PHP_BIN" "$ADMIN_HELPER"
ok "Administrador preparado: $ADMIN_USER"

touch "$ROOT/storage/uploads/.gitkeep"
printf '%s\n' "$(date -Iseconds)" > "$LOCK_FILE"
chmod 775 "$ROOT/storage" "$ROOT/storage/uploads" 2>/dev/null || true
chmod 640 "$CONFIG_FILE" 2>/dev/null || true

SLUG="$(basename "$ROOT")"
printf '\n%s\n' '============================================================'
printf '%s\n' ' INSTALACIÓN COMPLETADA'
printf '%s\n' '============================================================'
printf 'Página pública:       http://localhost/%s/\n' "$SLUG"
printf 'Panel administrativo: http://localhost/%s/admin/\n' "$SLUG"
printf 'Usuario:               %s\n' "$ADMIN_USER"
printf 'Base de datos:         %s\n' "$DB_NAME"
