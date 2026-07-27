#!/bin/bash
# ============================================================
# add-receita.sh - Importar receitas de .doc ou texto puro
# Uso:
#   ./scripts/add-receita.sh arquivo.doc
#   ./scripts/add-receita.sh arquivo.docx
#   echo "texto da receita" | ./scripts/add-receita.sh -
#   ./scripts/add-receita.sh --offline arquivo.doc  (gera JSON local)
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
API_URL="${RECEITAS_API_URL:-/api/receitas.php}"
ADMIN_KEY="${RECEITAS_ADMIN_KEY:-receitas-cei-2009}"
OFFLINE=false

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

usage() {
    echo "Uso: $0 [--offline] <arquivo.doc|.docx|->"
    echo ""
    echo "Opcoes:"
    echo "  --offline    Gera JSON local em instead de enviar para a API"
    echo ""
    echo "Exemplos:"
    echo "  $0 receita.doc"
    echo "  $0 receita.docx"
    echo "  echo 'Minha receita...' | $0 -"
    echo "  $0 --offline receita.doc"
    exit 1
}

# Parse opcoes
while [[ "${1:-}" == "--"* ]]; do
    case "$1" in
        --offline) OFFLINE=true; shift ;;
        --help|-h) usage ;;
        *) echo "Opcao desconhecida: $1"; usage ;;
    esac
done

if [[ $# -lt 1 ]]; then
    usage
fi

INPUT_FILE="$1"

# Funcao para converter texto em JSON de receita
text_to_json() {
    local text="$1"
    local filename="$2"

    # Extrair titulo (primeira linha nao vazia)
    local titulo=$(echo "$text" | grep -m1 '.' | head -1 | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
    if [[ -z "$titulo" ]]; then
        titulo=$(basename "$filename" .doc | sed 's/_[0-9]*_[0-9]*_[0-9]*//; s/_/ /g; s/-/ /g')
    fi

    # Gerar ID a partir do titulo
    local id=$(echo "$titulo" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g; s/--*/-/g; s/^-//; s/-$//')

    # Detectar categoria
    local cat="Outros"
    local text_lower=$(echo "$text" | tr '[:upper:]' '[:lower:]')
    if echo "$text_lower" | grep -qE 'pao|paes|brioche|hamburguer|frances|suico|forma'; then
        cat="Paes"
    elif echo "$text_lower" | grep -qE 'bolo|bola|cake'; then
        cat="Bolos"
    elif echo "$text_lower" | grep -qE 'broa|cavaca|corn'; then
        cat="Broas"
    elif echo "$text_lower" | grep -qE 'massa|pizza|folhad'; then
        cat="Massas"
    elif echo "$text_lower" | grep -qE 'doce|biscoito|cookie|sorvete|pudim|manjar|brigadeiro'; then
        cat="Doces"
    elif echo "$text_lower" | grep -qE 'salgado|coxinha|esfiha|empada|rissol'; then
        cat="Salgados"
    fi

    # Extrair data do filename (se existir)
    local data=""
    if [[ "$filename" =~ ([0-9]{2})_([0-9]{2})_([0-9]{4}) ]]; then
        data="${BASH_REMATCH[3]}-${BASH_REMATCH[2]}-${BASH_REMATCH[1]}"
    fi

    # Extrair ingredientes e modo de preparo (simplificado)
    local ingredientes_text=""
    local modo_text=""
    local section=""
    while IFS= read -r line; do
        local trimmed=$(echo "$line" | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
        if echo "$trimmed" | grep -qiE '^(ingredientes?|componentes?):?$'; then
            section="ingredientes"
            continue
        elif echo "$trimmed" | grep -qiE '^(modo\s+(de\s+)?preparo|preparo|preparacao|instrucoes?):?$'; then
            section="modo"
            continue
        fi
        if [[ "$section" == "ingredientes" && -n "$trimmed" ]]; then
            ingredientes_text="${ingredientes_text}${trimmed}\n"
        elif [[ "$section" == "modo" && -n "$trimmed" ]]; then
            modo_text="${modo_text}${trimmed} "
        fi
    done <<< "$text"

    # Se nao encontrou secoes, colocar texto inteiro como modo
    if [[ -z "$modo_text" && -z "$ingredientes_text" ]]; then
        modo_text=$(echo "$text" | sed '/^$/d' | tr '\n' ' ')
    fi

    # Converter ingredientes para JSON simples
    local ing_json="{"
    local first=true
    while IFS= read -r line; do
        [[ -z "$line" ]] && continue
        # Tentar extrair "item: quantidade" ou "item = quantidade"
        local item=$(echo "$line" | sed 's/:.*//; s/=.*//' | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
        local qtd=$(echo "$line" | sed -n 's/.*[:=]\s*//p' | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//')
        if [[ -n "$item" ]]; then
            local key=$(echo "$item" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g; s/__*/_/g; s/^_//; s/_$//')
            [[ -z "$qtd" ]] && qtd="$item"
            [[ "$first" == "true" ]] && first=false || ing_json="${ing_json},"
            qtd=$(echo "$qtd" | sed 's/"/\\"/g')
            ing_json="${ing_json}\"${key}\": \"${qtd}\""
        fi
    done <<< "$(echo -e "$ingredientes_text")"
    ing_json="${ing_json}}"

    # Gerar JSON
    cat <<EOF
{
  "id": "${id}",
  "titulo": "${titulo}",
  "categoria": "${cat}",
  "data": "${data}",
  "descricao": "Receita importada de ${filename}",
  "ingredientes": ${ing_json},
  "modo_preparo": "$(echo "$modo_text" | sed 's/"/\\"/g' | sed 's/^ //; s/ $//')",
  "observacoes": ""
}
EOF
}

# Processar input
if [[ "$INPUT_FILE" == "-" ]]; then
    # Ler de stdin
    TEXT=$(cat)
    FILENAME="stdin"
else
    if [[ ! -f "$INPUT_FILE" ]]; then
        echo -e "${RED}Erro: Arquivo nao encontrado: $INPUT_FILE${NC}"
        exit 1
    fi

    FILENAME=$(basename "$INPUT_FILE")
    EXT="${FILENAME##*.}"
    EXT_LOWER=$(echo "$EXT" | tr '[:upper:]' '[:lower:]')

    case "$EXT_LOWER" in
        doc)
            # Tentar usar antiword, catdoc, ou strings
            if command -v antiword &>/dev/null; then
                TEXT=$(antiword "$INPUT_FILE" 2>/dev/null || strings "$INPUT_FILE")
            elif command -v catdoc &>/dev/null; then
                TEXT=$(catdoc "$INPUT_FILE" 2>/dev/null || strings "$INPUT_FILE")
            else
                echo -e "${YELLOW}Aviso: antiword/catdoc nao encontrado. Tentando strings...${NC}"
                TEXT=$(strings "$INPUT_FILE" | grep -v '^[[:space:]]*$')
            fi
            ;;
        docx)
            if command -v pandoc &>/dev/null; then
                TEXT=$(pandoc -t plain "$INPUT_FILE" 2>/dev/null)
            else
                # Tentar extrair texto do docx (zip)
                TEXT=$(unzip -p "$INPUT_FILE" word/document.xml 2>/dev/null | sed -e 's/<[^>]*>//g' | grep -v '^[[:space:]]*$')
                if [[ -z "$TEXT" ]]; then
                    echo -e "${RED}Erro: pandoc necessario para .docx. Instale: sudo apt install pandoc${NC}"
                    exit 1
                fi
            fi
            ;;
        txt|md)
            TEXT=$(cat "$INPUT_FILE")
            ;;
        *)
            echo -e "${RED}Erro: Formato nao suportado: .$EXT${NC}"
            echo "Formatos aceitos: .doc, .docx, .txt, .md"
            exit 1
            ;;
    esac
fi

if [[ -z "$TEXT" ]]; then
    echo -e "${RED}Erro: Nao foi possivel extrair texto do arquivo${NC}"
    exit 1
fi

echo -e "${GREEN}Texto extraido com sucesso!${NC}"
echo "---"

# Converter para JSON
JSON=$(text_to_json "$TEXT" "$FILENAME")

echo -e "${GREEN}JSON gerado:${NC}"
echo "$JSON" | python3 -m json.tool 2>/dev/null || echo "$JSON"
echo "---"

if [[ "$OFFLINE" == "true" ]]; then
    # Salvar localmente
    OUTPUT_DIR="$PROJECT_DIR/src/data/imported"
    mkdir -p "$OUTPUT_DIR"
    ID=$(echo "$JSON" | python3 -c "import sys,json; print(json.load(sys.stdin)['id'])" 2>/dev/null || echo "receita-$(date +%s)")
    OUTPUT_FILE="$OUTPUT_DIR/${ID}.json"
    echo "$JSON" > "$OUTPUT_FILE"
    echo -e "${GREEN}Salvo em: $OUTPUT_FILE${NC}"
    echo "Para importar para o banco, envie o JSON para a API."
else
    # Enviar para a API
    echo -e "${YELLOW}Enviando para a API...${NC}"
    RES=$(curl -s -w "\n%{http_code}" -X POST "$API_URL" \
        -H "Content-Type: application/json" \
        -H "X-Admin-Key: $ADMIN_KEY" \
        -d "$JSON")
    HTTP_CODE=$(echo "$RES" | tail -1)
    BODY=$(echo "$RES" | sed '$d')

    if [[ "$HTTP_CODE" == "200" ]]; then
        echo -e "${GREEN}Receita importada com sucesso!${NC}"
        echo "$BODY" | python3 -m json.tool 2>/dev/null || echo "$BODY"
    else
        echo -e "${RED}Erro ao importar (HTTP $HTTP_CODE):${NC}"
        echo "$BODY"
        exit 1
    fi
fi
