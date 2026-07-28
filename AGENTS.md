# AGENTS.md — Receitas CEI

## Visao Geral
Site de receitas para o curso de panificacao do CEI (Centro de Educacao Infantil).
Novo site com Vite + TailwindCSS 4 + Alpine.js 3 + PHP API.
Substitui o projeto antigo em `pao.50webs.org`.

## Stack
- **Frontend:** Vite 8, TailwindCSS 4 (`@tailwindcss/vite`), Alpine.js 3, mammoth.js (parse `.docx`)
- **Backend:** PHP 8+ (API REST, sessions, CSRF)
- **Banco:** MySQL (InfinityFree: `sql202.infinityfree.com`, `if0_42505744_receitas`)
- **Deploy:** FTP via GitHub Actions para InfinityFree (`ftpupload.net`)
- **URL:** `https://receitas.free.nf`
- **Template visual:** Chef's Kitchen (ThemeWagon)

## Estrutura do Projeto
```
receitas-cei/
├── public_html/                # Frontend + API (tudo junto)
│   ├── index.html              # SPA principal (source + build target)
│   ├── css/style.css           # Tema TailwindCSS
│   ├── js/
│   │   ├── app.js              # Alpine.js stores (router hash + receitas)
│   │   └── parse-recipe.js     # Parser de receitas (port JS do bash)
│   ├── data/receitas.json      # Receitas de exemplo
│   ├── admin/                  # Painel administrativo
│   │   ├── index.html          # Login + dropzone + import + CRUD
│   │   ├── main.js             # Alpine.js app (auth, CSRF, import)
│   │   └── style.css           # Estilos admin
│   ├── api/                    # PHP API (source code, versionado)
│   │   ├── index.php           # Router ( REQUEST_URI -> endpoint)
│   │   ├── config.php          # CSRF, session auth, CORS, env, DB helpers
│   │   ├── csrf.php            # GET /api/csrf
│   │   ├── auth.php            # POST login, GET session, DELETE logout
│   │   ├── receitas.php        # CRUD receitas
│   │   ├── import.php          # Upload .doc/.docx + parse server-side
│   ├── images.php          # Busca imagens (Unsplash/MealDB/Picsum)
│   ├── batch-import.php    # Importacao em lote via API
│   └── db.php              # Gerenciamento do banco (backup/restore/reset)
│   ├── favicon.svg
│   ├── favicon.png
│   ├── favicon-circle.png
│   ├── icons.svg
│   └── assets/                 # Build output (gitignored)
├── vite.config.js              # root: public_html, proxy /api -> :8080
├── package.json                # Scripts: dev, build
├── scripts/
│   ├── import.mjs          # Import .doc via Node.js (docToText + mysql2)
│   ├── add-receita.sh      # CLI bash para importar receitas
│   └── deploy.sh           # Deploy FTP manual
├── sqls/                    # Backups SQL
├── .env                        # Credenciais (gitignored)
└── .env.example                # Template de env
```

## Scripts NPM
- `npm run dev` — Vite (porta 3000) + PHP (porta 8080) via concurrently
- `npm run dev:vite` — Apenas Vite
- `npm run dev:php` — Apenas PHP (`php -S localhost:8080 public_html/api/index.php`)
- `npm run build` — Vite build (assets para public_html/assets/)
- `npm run preview` — Preview do build

## Arquitetura de Seguranca
- **Auth:** Session-based PHP (`$_SESSION['authenticated']`, `$_SESSION['admin_key']`)
- **CSRF:** Tokens server-side via `generateCsrfToken()`, header `X-CSRF-Token`
- **Headers:** X-Frame-Options DENY, nosniff, XSS-Protection, Referrer-Policy
- **CORS:** Restrito a `localhost:3000`, `receitas.free.nf` e `pao.50webs.org`
- **Upload:** Validacao MIME (finfo), tamanho max 5MB, magic bytes (.doc: D0CF, .docx: PK)

## API Endpoints
| Metodo | Rota             | Auth  | CSRF  | Descricao                     |
|--------|------------------|-------|-------|-------------------------------|
| GET    | /api/csrf        | Nao   | Nao   | CSRF token                    |
| POST   | /api/auth        | Nao   | Nao   | Login (session + CSRF)        |
| GET    | /api/auth        | Sim   | Nao   | Check session                 |
| DELETE | /api/auth        | Sim   | Nao   | Logout                        |
| GET    | /api/receitas    | Nao   | Nao   | Lista receitas                |
| POST   | /api/receitas    | Sim   | Sim   | Criar receita                 |
| PUT    | /api/receitas    | Sim   | Sim   | Atualizar receita             |
| DELETE | /api/receitas    | Sim   | Sim   | Deletar receita               |
| POST   | /api/import      | Sim   | Sim   | Upload/importar receita       |

## Convencoes
- CSS: Tema via CSS custom properties (`--color-primary`, `--color-accent`, `--color-cream`, `--color-warm-gray`)
- JS: Alpine.js para reatividade, sem framework build-time
- PHP: PDO para MySQL, funcoes helper em `config.php`
- `public_html/api/` e versionado (source code PHP)
- `public_html/assets/` e gitignored (build output do Vite)
- HTML source e build target no mesmo local (`public_html/`)

## Deploy
- Push de tag `v*` ou `workflow_dispatch` dispara GitHub Actions
- Build: `npm run build`
- Deploy: mirror `public_html/` via FTP para `ftpupload.net` (lftp com `set ftp:chmod ''` e `--no-perms` — InfinityFree nao aceita CHMOD)
- Credenciais no `.env` (FTP_HOST, FTP_USERNAME, FTP_PASSWORD)
- **GitHub Secrets:** `FTP_HOST`, `FTP_USER`, `FTP_PASS` (configurados via `gh secret set`)
- `public_html/assets/` e gitignored (build output do Vite)
- HTML source e build target no mesmo local (`public_html/`)

## Projeto Antigo
- `/home/isaacca/hd/Codigos/site_pessoal/pao.50webs.org`
- 41 arquivos `.doc` em `receitas/docs/Receitas/` (source para importacao)
