#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TARGET="$PROJECT_ROOT/install.sh"
TMP_FILE="$TARGET.tmp"
BACKUP_FILE="$TARGET.bak"
CHANGED=0

fail() {
  printf '\n[ERROR] %s\n' "$*" >&2
  rm -f "$TMP_FILE"
  exit 1
}

[[ -f "$TARGET" ]] || fail "No se encontró install.sh en $PROJECT_ROOT"

while IFS= read -r line || [[ -n "$line" ]]; do
  case "$line" in
    '  read -r -p "$label [$default_value]: " value')
      cat <<'PATCH'
  if ! IFS= read -r -p "$label [$default_value]: " value </dev/tty; then
    value=""
  fi
PATCH
      CHANGED=$((CHANGED + 1))
      ;;

    '  read -r -s -p "$label: " value')
      cat <<'PATCH'
  if ! IFS= read -r -s -p "$label: " value </dev/tty; then
    value=""
  fi
PATCH
      CHANGED=$((CHANGED + 1))
      ;;

    '    read -r -s -p "Contraseña del administrador (mínimo 8 caracteres): " first')
      cat <<'PATCH'
    if ! IFS= read -r -s -p "Contraseña del administrador (mínimo 8 caracteres): " first </dev/tty; then
      printf '\n'
      fail "No fue posible leer la contraseña desde la terminal."
    fi
PATCH
      CHANGED=$((CHANGED + 1))
      ;;

    '    read -r -s -p "Repita la contraseña: " second')
      cat <<'PATCH'
    if ! IFS= read -r -s -p "Repita la contraseña: " second </dev/tty; then
      printf '\n'
      fail "No fue posible leer la confirmación de la contraseña desde la terminal."
    fi
PATCH
      CHANGED=$((CHANGED + 1))
      ;;

    *)
      printf '%s\n' "$line"
      ;;
  esac
done < "$TARGET" > "$TMP_FILE"

if [[ "$CHANGED" -eq 0 ]] && grep -q '</dev/tty' "$TARGET"; then
  rm -f "$TMP_FILE"
  printf '[OK] install.sh ya estaba corregido para Git Bash.\n'
elif [[ "$CHANGED" -eq 4 ]]; then
  cp "$TARGET" "$BACKUP_FILE"
  mv "$TMP_FILE" "$TARGET"
  chmod +x "$TARGET" 2>/dev/null || true
  bash -n "$TARGET" || fail "La versión corregida no pasó la validación de sintaxis."
  printf '[OK] install.sh corregido para leer desde /dev/tty.\n'
  printf '[OK] Copia anterior guardada en install.sh.bak\n'
else
  fail "Se esperaban 4 cambios y se aplicaron $CHANGED. No se modificó install.sh."
fi

exec bash "$TARGET" "$@"
