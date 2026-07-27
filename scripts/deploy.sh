#!/bin/bash
# deploy.sh - Build e deploy para InfinityFree via FTP
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Carregar .env
if [[ -f "$PROJECT_DIR/.env" ]]; then
    set -a
    source "$PROJECT_DIR/.env"
    set +a
fi

echo "=== Build do projeto ==="
cd "$PROJECT_DIR"
npm run build

echo ""
echo "=== Deploy para InfinityFree ==="
if command -v lftp &>/dev/null; then
    lftp -c "
        set ftp:ssl-allow no;
        open ftp://${FTP_USERNAME}:${FTP_PASSWORD}@${FTP_HOST};
        mirror --reverse --delete --verbose public_html/ /${DEPLOY_PATH};
        quit
    "
    echo "Deploy concluido!"
else
    echo "ERRO: lftp nao encontrado. Instale com: sudo apt install lftp"
    exit 1
fi
