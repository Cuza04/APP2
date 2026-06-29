#!/usr/bin/env bash

URL="${1:-http://127.0.0.1:8000/admin/login}"

open_with() {
    "$@" >/dev/null 2>&1
}

if open_with wslview "$URL"; then
    echo "Abierto en navegador externo: $URL"
    exit 0
fi

if open_with /mnt/c/Windows/System32/cmd.exe /c start "" "$URL"; then
    echo "Abierto en navegador externo: $URL"
    exit 0
fi

if open_with /mnt/c/Windows/System32/WindowsPowerShell/v1.0/powershell.exe -NoProfile -Command "Start-Process '$URL'"; then
    echo "Abierto en navegador externo: $URL"
    exit 0
fi

if open_with /mnt/c/Windows/explorer.exe "$URL"; then
    echo "Abierto en navegador externo: $URL"
    exit 0
fi

cat <<EOF

No se pudo abrir el navegador desde WSL (interop de Windows desactivado).

Opciones:

1) Cursor: pestaña PORTS → puerto 8000 → icono globo (abre Chrome/Edge en Windows)
2) Desde Windows, ejecuta:
   \\\\wsl.localhost\\Ubuntu\\home\\jeank\\projects\\APP2\\scripts\\open-browser.cmd
3) Copia y pega en Chrome/Edge:
   $URL

Para arreglar interop WSL (una sola vez, luego reinicia WSL desde PowerShell: wsl --shutdown):
   sudo tee /etc/wsl.conf << 'WSLCONF'
[interop]
enabled=true
appendWindowsPath=true
WSLCONF

EOF

exit 1
