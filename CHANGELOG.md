# Changelog — Receitas CEI

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
