#!/bin/bash

# ============================================
# Script para construir imagen Docker
# ============================================

set -e

echo "============================================"
echo "Construyendo imagen de Price Monitor"
echo "============================================"

# Colores
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

# Ir al directorio raíz del proyecto
cd "$(dirname "$0")/.."

# Mostrar información
echo -e "${BLUE}Directorio:${NC} $(pwd)"
echo -e "${BLUE}Dockerfile:${NC} docker/Dockerfile"
echo ""

# Construir imagen
echo "Construyendo imagen..."
docker build -f docker/Dockerfile -t price-monitor:latest .

# Verificar tamaño
IMAGE_SIZE=$(docker images price-monitor:latest --format "{{.Size}}")
echo ""
echo -e "${GREEN}✓ Imagen construida exitosamente${NC}"
echo -e "${BLUE}Tamaño:${NC} $IMAGE_SIZE"
echo ""

# Listar imágenes
echo "Imágenes disponibles:"
docker images | grep -E "REPOSITORY|price-monitor"
echo ""

echo "============================================"
echo "Para ejecutar la imagen:"
echo "  cd docker"
echo "  docker-compose up -d"
echo "============================================"
