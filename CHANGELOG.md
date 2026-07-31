# Changelog — Receitas CEI

## [Unreleased]

## [1.1.2] - 2026-07-31

### Bug Fixes
- Filtro por categoria na página de receitas não funcionava: o router tratava o hash `#/receitas?cat=X` como rota `receitas?cat=X`, não renderizando a página
- Router agora separa path de query string e popula `params` (`?cat=...`, etc.) com `decodeURIComponent`
- `getAll()` filtra a lista pela categoria da rota (`router.params.cat`), combinando com a busca
- Botões de filtro usam `$store.router.params.cat` para o estado ativo (antes comparavam `window.location.hash`)
- Hash de categorias usa `encodeURIComponent` (robusto para nomes com acento/espaço), inclusive nos cards de categoria da home
- Cards de categoria da home (`#/receitas?cat=...`) passam a abrir a lista já filtrada

### Interface
- Gradiente monochrome (marrom) na navbar, footer e botões; gradiente laranja (accent) em botões de destaque
- Ortografia corrigida na interface (index.html, admin) e no conteúdo das receitas (patch SQL `sqls/correcoes-ortografia.sql`)
- Nota "Todas as receitas do ano de 2009 são do livro de padeiro." adicionada na seção Sobre

### Scripts e Backup
- Novo `scripts/fix-ortografia.mjs`: gera correções ortográficas de acentuação nos textos e chaves de ingredientes (com `--dry-run`, `--verbose`, `--sql` e `--from` para dumps do `export-sql`)
- Gerado `sqls/correcoes-ortografia.sql` com 18 UPDATEs (aplicar via Admin → DB → Restaurar)
- Backup `sqls/receitas-backup-2026-07-31.sql` (estado local atual, 40 receitas)

## [1.1.1] - 2026-07-28

### Deploy: .env via GitHub Secret
- `DOTENV` secret criado no GitHub com credenciais de produção (DB, ADMIN_KEY, UNSPLASH_ACCESS_KEY)
- Deploy gera `.env` em `htdocs/.env` a partir do secret, resolvendo falta de suporte a env vars na InfinityFree
- `.htaccess` bloqueia acesso HTTP ao `.env`
- `lftp put` corrigido para usar `cd /htdocs` antes do upload (evita upload para home, bloqueado pela InfinityFree)
- `set ftp:chmod ''` substituído por `set ftp:chmod-default ''` (elimina warning)

### Bug Fixes
- `echo "$DOTENV"` substituído por `printf '%s\n' "$DOTENV"` para preservar quebras de linha
- `config.php` revertido para credenciais vazias (produção carregadas do `.env`)

## [1.1.0] - 2026-07-28

### Node.js Import Script (`scripts/import.mjs`)
- Script CLI para importacao de arquivos `.doc` via Node.js (sem LibreOffice)
- Usa `docToText.js` via `vm.runInContext` para extrair texto de `.doc` em contexto ESM
- Suporta `--dry-run`, `--file`, `--dir` para testes parciais
- Conexao direta com MySQL via `mysql2/promise`
- Limpa DB automaticamente antes de importar (com aviso)
- Batch com 41 receitas importadas em ~5s

### Multi-Receita (Sub-Grupos)
- Parser detecta multi-listas de ingredientes (ex: Pão doce + Creme, Panetone esponja + reforço)
- Sub-grupos extraídos de markers `ingredientes* para <nome>` (ex: "primeira-parte", "segunda-parte")
- Headers entre blocos (`Receita complementar`, `Creme para...`) usados como nomes de sub-grupos
- Metadata (datas, "Receita passada") automaticamente ignorada nos headers
- Resultado JSON aninhado: `{"pao-doce": {...}, "creme-para-pao-doce": {...}}`
- Sub-grupos só ativados quando há 2+ blocos COM ingredientes (evita falsos positivos de OBSERVAÇÃO)

### Parser - Novos Formatos Suportados
- Tab-separated completo: `Farinha\t100%\t5000\tg` (7 de 41 receitas usam este formato)
- `+/- 35%` e `5% (ou 1/3 se for fermento seco)` — percentuais com prefixo/sufixo extra
- Linhas sem marker `ingredientes` — auto-detecção quando há TABs com números (ex: Quindim)
- Filtro de valores `_______` (underline-only) em campos de ingredientes
- `Modo de preparo.` com ponto final — regex agora aceita pontuação

### Correções do Parser PHP (import.php)
- `parseSections()` reescrita com lógica de blocos para suportar multi-receita
- `parseIngredientLineFromText()` lida com `NameNN%` e `Name = NN%` sem tabs
- `cleanWikiMarkup()` limpa `<nowiki>=</nowiki>`, `'''bold'''`, `= heading =`

### Bug Fixes
- SQL dump de backup tinha `)` extra na linha do CREATE TABLE, causando erro 1064 no import
- `receitas.php` agora retorna JSON quando tabela nao existe em vez de HTML fatal error
- `import-sql` auto-cria tabela antes de executar INSERTs (nao precisa clicar "Criar/Banco" antes)
- `ob_start/ob_end_clean` em receitas.php e db.php para suprimir warnings do config.php
- Export SQL: removido bug de CREATE TABLE duplicado
- Import SQL: parser multi-linha corrigido para acumular statementes ate `;`
- Import SQL: escapar newlines e null bytes no escapeSqlValue()
- batch-import.php: corrigido `use` em funcao normal para `global`
- Re-importacao completa: 41 receitas dos .doc originais com 100% ingredientes parseados (antes 11/47)

### Conhecido
- Quindim de coco fresco: ingrediente "Coco Fresco" parseado como `coco-fresco` (valor correto)
- Acentos preservados corretamente via `docToText.js` (UTF-8 nativo)

## [0.2.0] - 2026-07-27

### Estrutura: Separar Backend do Frontend
- Movidos arquivos fonte HTML/JS/CSS para `public_html/` (source = build target)
- Criado `public_html/api/index.php` como router para endpoints PHP
- Pasta `api/` do root removida — PHP agora vive em `public_html/api/`
- Vite config: `root: 'public_html'`, `outDir: '.'`, `emptyOutDir: false`
- PHP server roda com router: `php -S localhost:8080 public_html/api/index.php`
- Build script simplificado: apenas `vite build` (sem copia de api/)
- Deploy simplificado: sem step de copia, `public_html/` ja e auto-contido
- `.gitignore` atualizado: `public_html/assets/` ignorado, `public_html/api/` versionado
- `config.php` env path: fallback `__DIR__ . '/../../.env'` para resolver `.env` do project root

### Arquivos movidos
- `index.html` (root) → `public_html/index.html`
- `admin/` (root) → `public_html/admin/`
- `src/css/style.css` → `public_html/css/style.css`
- `src/js/app.js` → `public_html/js/app.js`
- `src/js/parse-recipe.js` → `public_html/js/parse-recipe.js`
- `src/data/receitas.json` → `public_html/data/receitas.json`
- `public/favicon.svg` → `public_html/favicon.svg`
- `public/icons.svg` → `public_html/icons.svg`
- Diretorios `src/`, `admin/`, `public/` removidos

## [0.1.0] - 2026-07-27 (Scaffold Inicial)

### Frontend
- Scaffold Vite + TailwindCSS 4 (`@tailwindcss/vite`) + Alpine.js 3
- SPA com hash router (home, receitas, receita, sobre, contato)
- CSS theme: `--color-primary` (#8B4513), `--color-accent` (#D2691E), `--color-cream`, `--color-warm-gray`
- Fonts: Playfair Display (display) + Inter (body)
- 8 receitas de exemplo em `receitas.json`

### Backend PHP API
- `config.php`: Session start, CSRF functions (generate/validate), security headers, CORS, env loading, DB helpers
- `csrf.php`: GET endpoint returning CSRF token
- `auth.php`: POST login (session + CSRF), GET check session, DELETE logout
- `receitas.php`: Full CRUD with `requireAuth()` + `requireCsrf()` on writes, PDO MySQL
- `import.php`: Dual mode (file upload + JSON text body), MIME validation, magic bytes, cascading text extraction (antiword -> catdoc -> strings)

### Admin Panel
- Login form with session-based auth
- Dropzone (drag & drop + file input) for .doc/.docx/.txt/.md
- 3 import states: idle -> extracting -> preview
- Recipe list table with edit/delete
- Edit/create modal with sticky header/footer
- mammoth.js for .docx client-side parsing

### Security
- Session-based PHP auth (`$_SESSION['authenticated']`)
- CSRF tokens via `X-CSRF-Token` header
- Security headers: X-Frame-Options DENY, nosniff, XSS-Protection
- CORS restricted to `localhost:3000` and `pao.50webs.org`
- Upload validation: finfo MIME, 5MB max, magic bytes check

### Build & Deploy
- Vite multi-page build (main + admin)
- `concurrently` for dev (Vite + PHP)
- Vite proxy `/api` -> `localhost:8080`
- GitHub Actions CI/CD (deploy.yml)
- FTP deploy script (deploy.sh)
- `.env` with FTP/DB credentials

### Scripts
- `scripts/add-receita.sh`: CLI bash for recipe import
- `scripts/parse-receita.sh`: Original bash parser (ported to JS and PHP)
