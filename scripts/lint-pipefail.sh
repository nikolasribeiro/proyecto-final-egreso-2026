#!/usr/bin/env bash
# scripts/lint-pipefail.sh
# Lint ESTATICO: detecta asignaciones que pueden abortar el operador.
#
# POR QUE EXISTE
# --------------
# Todos los modulos corren con `set -euo pipefail`. Eso significa que CUALQUIER
# comando dentro de una sustitucion de comandos que devuelva != 0 ABORTA todo
# el operador. Y muchos comandos devuelven != 0 en situaciones NORMALES:
#
#   getent passwd <usuario>   -> 2 si el usuario no existe
#   grep <patron> <archivo>   -> 1 si no hay coincidencias
#   stat <archivo>            -> 1 si no existe
#   id <usuario>              -> 1 si no existe
#
# Ejemplo real que rompio un despliegue:
#
#   admin_home="$(getent passwd "$SB_ADMIN_USER" 2>/dev/null | cut -d: -f6)"
#
# En la primera corrida (el usuario todavia no existe) `getent` devuelve 2,
# `pipefail` lo propaga, `set -e` aborta TODO el operador con exit code 2 y un
# error generico. La solucion es `|| true` (el valor vacio se maneja despues).
#
# USO
#   bash scripts/lint-pipefail.sh          # escanea scripts/lib y operator.sh
#
# Salida: una linea por asignacion sospechosa. Si no imprime nada, esta todo
# protegido.

set -uo pipefail
IFS=$'\n\t'

cd "$(dirname "${BASH_SOURCE[0]}")" || exit 1

# Comandos que pueden devolver != 0 en condiciones normales.
# ARRAY (no string): con `IFS=$'\n\t'` una cadena NO se separa por espacios.
# Solo los que devuelven != 0 en condiciones NORMALES (no los que siempre
# funcionan como awk, sed, cut, date, mktemp, cat, printf, etc.).
readonly INSEC=(
    getent stat id grep docker jq curl ssh ip ss hostname journalctl
    df du useradd usermod groupadd chown chmod install
    openssl ssh-keygen runuser systemctl
)

encontrados=0

# Recorre los archivosHints las asignaciones logicas (une continuaciones con \).
for f in lib/*.sh operator.sh; do
    [[ -f "$f" ]] || continue

    # Unir lineas continuadas: awk bufferiza mientras la linea termine en backslash.
    while IFS= read -r logica; do
        [[ -z "$logica" ]] && continue
        [[ "$logica" == *'#'* ]] && continue

        # Solo asignaciones del tipo X="$(...)" o X=$(...)
        [[ "$logica" =~ ^[[:space:]]*[A-Za-z_][A-Za-z0-9_]*= ]] || continue
        [[ "$logica" == *'$('* ]] || continue

        # Ya esta protegido?
        case "$logica" in
            *'|| true'*|*'|| echo'*|*'|| :'*|*'|| printf'*|*'|| cat'*|*'|| true'*) continue;;
        esac

        inner="${logica#*\$\(}"
        inner="${inner%%\)*}"
        cmd="${inner//\"/}"
        cmd="${cmd//\$SB_[A-Z_]*/}"
        cmd="${cmd//\$[a-zA-Z_]*/}"
        cmd="$(printf '%s' "$cmd" | awk '{print $1}')"

        i=""
        for i in "${INSEC[@]}"; do
            if [[ "$cmd" == "$i" ]]; then
                printf '  %-28s [%s]\n      %s\n' "$f" "$cmd" \
                    "$(printf '%s' "$logica" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
                encontrados=$((encontrados + 1))
                break
            fi
        done
    done < <(awk '
        /\\$/ { buffer = buffer $0 " "; next }
        { print buffer $0; buffer = "" }
        END { if (buffer != "") print buffer }
    ' "$f")
done

if [[ $encontrados -eq 0 ]]; then
    echo "OK: ninguna asignacion desprotegida contra set -euo pipefail"
    exit 0
fi

echo ""
echo "Total: $encontrados asignacion(es) a proteger con '|| true' (o manejar el valor vacio)."
exit 1
