# Receitas CEI

Site de receitas do curso de panificacao do CEI de Quintino.

[Como formatar receitas para importacao](RECEITAS.md)

---

## Sobre

Site para gerenciar e consultar receitas do curso de panificacao. As receitas sao armazenadas em banco de dados MySQL e podem ser importadas a partir de arquivos `.doc`, `.docx`, `.txt` e `.md`.

## Funcionamento

### Importacao de receitas

O painel administrativo permite importar receitas de tres formas:

1. **Arquivos `.docx`** - O texto e extraido no navegador usando a biblioteca [mammoth.js](https://github.com/mwilliamson/mammoth.js)
2. **Arquivos `.doc`** - O texto e extraido no navegador usando a biblioteca [JSDoc](https://github.com/Alpaq92/JSDoc) (pure JavaScript, zero dependencias no servidor)
3. **Arquivos `.txt` e `.md`** - Enviados direto ao servidor

Em todos os casos, o navegador extrai o texto plano e envia para a API PHP, que faz o parse dos ingredientes e modo de preparo.

Para melhor deteccao dos ingredientes, formate os arquivos `.doc`/`.docx` com TABs separando nome, porcentagem e quantidade. Veja o guia completo em [RECEITAS.md](RECEITAS.md).

### Gerenciamento do banco de dados

O painel administrativo oferece 3 acoes para o banco de dados:

| Botao | Funcao |
|-------|--------|
| **Criar/Banco** | Cria a tabela `receitas` e importa as receitas do JSON inicial se a tabela estiver vazia |
| **Backup** | Baixa um arquivo JSON com todas as receitas do banco |
| **Resetar** | Apaga todas as receitas e reimporta do JSON inicial |

### API

Endpoints da API PHP (em `public_html/api/`):

| Endpoint | Metodo | Funcao |
|----------|--------|--------|
| `/api/auth.php` | POST | Login com chave de acesso |
| `/api/auth.php` | GET | Verifica sessao ativa |
| `/api/auth.php` | DELETE | Logout |
| `/api/csrf.php` | GET | Retorna token CSRF |
| `/api/receitas.php` | GET | Lista todas as receitas |
| `/api/receitas.php` | POST | Cria uma receita |
| `/api/receitas.php` | PUT | Atualiza uma receita |
| `/api/receitas.php` | DELETE | Exclui uma receita |
| `/api/import.php` | POST | Importa receita de texto extraido |
| `/api/db.php` | POST | Gerencia o banco (setup, backup, fresh) |

### Seguranca

- Login por chave de acesso (sessao PHP)
- Tokens CSRF em todas as requisicoes POST/PUT/DELETE
- Headers de seguranca (X-Frame-Options, X-XSS-Protection, etc.)
- Protecao contra SQL injection via prepared statements
- CORS restrito a origens configuradas

## Stack

- **Frontend:** HTML + [TailwindCSS 4](https://tailwindcss.com/) + [Alpine.js 3](https://alpinejs.dev/)
- **Backend:** PHP 8 (API REST)
- **Banco:** MySQL 8
- **Build:** [Vite](https://vitejs.dev/)
- **Deploy:** InfinityFree (FTP)

## Estrutura do projeto

```
receitas-cei/
├── public_html/              # Document root (producao e dev)
│   ├── index.html            # SPA principal
│   ├── css/style.css         # Tema TailwindCSS
│   ├── js/
│   │   ├── app.js            # Alpine.js stores (router + receitas)
│   │   ├── parse-recipe.js   # Parser de receitas (port do bash script)
│   │   ├── docToText.js      # Extrator de texto .doc (JSDoc, 0BSD)
│   │   └── parse-recipe.js   # Parser de receitas
│   ├── admin/
│   │   ├── index.html        # Painel administrativo
│   │   ├── main.js           # Logica do admin (import, CRUD, DB)
│   │   └── style.css         # Estilos do admin
│   ├── data/receitas.json    # Receitas de exemplo (seed do DB)
│   └── api/
│       ├── index.php         # Router da API
│       ├── config.php        # Config, helpers, DB, CSRF, auth
│       ├── auth.php          # Login/logout/sessao
│       ├── csrf.php          # Token CSRF
│       ├── receitas.php      # CRUD de receitas
│       ├── import.php        # Parse de texto -> receita
│       └── db.php            # Gerenciamento do banco
├── .env                      # Credenciais (nao versionado)
├── .env.example              # Template do .env
├── .gitignore
├── vite.config.js            # Config do Vite
├── package.json
├── RECEITAS.md               # Guia de formatacao de receitas
└── CHANGELOG.md              # Historico de alteracoes
```

## Desenvolvimento

### Pre-requisitos

- Node.js 18+
- PHP 8.1+ com extensoes: pdo_mysql, mbstring, json, session
- MySQL 8 (Docker ou remoto)

### Setup local

```bash
# Clonar o repositorio
git clone <url>
cd receitas-cei

# Instalar dependencias
npm install

# Configurar o .env
cp .env.example .env
# Editar .env com as credenciais do banco local

# Subir Docker MySQL (opcional, se tiver Docker)
docker run -d --name mysql_db -p 3306:3306 \
  -e MYSQL_ROOT_PASSWORD=password \
  -e MYSQL_DATABASE=receitas \
  mysql:8.0

# Criar o database local
docker exec -i mysql_db mysql -u root -ppassword \
  -e "CREATE DATABASE IF NOT EXISTS receitas_cei CHARACTER SET utf8mb4;"
docker exec -i mysql_db mysql -u root -ppassword \
  -e "GRANT ALL ON receitas_cei.* TO 'sail'@'%'; FLUSH PRIVILEGES;"

# Iniciar o servidor de desenvolvimento
npm run dev
```

O site fica disponivel em `http://localhost:3000` e a API em `http://localhost:8080`.

### Deploy

O deploy e feito via FTP para a InfinityFree usando GitHub Actions. Ao fazer push na branch `main`, o workflow:

1. Instala as dependencias
2. Roda `npm run build`
3. Envia os arquivos via FTP para o servidor

## Credenciais

| Chave | Valor |
|-------|-------|
| Admin | Definida em `ADMIN_KEY` no `.env` |
| DB Local | `sail` / `password` / `receitas_cei` |
| DB Producao | Definido em `DB_*` no `.env` |
