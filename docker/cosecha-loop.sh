#!/bin/sh
# Bucle de cosecha 24/7. Docker reinicia el contenedor si muere.
# El solape lo evita Cache::lock('cosecha:run') dentro de cosecha:ejecutar.
set -u

sleep 25

echo "[cosecha-loop] arranque $(date -u +%Y-%m-%dT%H:%M:%SZ)"

while true; do
  php artisan cosecha:ejecutar
  codigo=$?
  echo "[cosecha-loop] pasada terminada codigo=${codigo} $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  # Pausa corta entre pasadas (el comando ya hace pausas entre áreas).
  sleep 20
done
