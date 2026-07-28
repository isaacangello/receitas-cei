#!/usr/bin/env node

import { readFileSync, readdirSync } from 'fs';
import { join, basename } from 'path';
import vm from 'vm';
import { pathToFileURL } from 'url';

function loadDocToText() {
  const src = readFileSync(new URL('../public_html/js/docToText.js', import.meta.url), 'utf8');
  const sandbox = { module: { exports: {} }, exports: {}, self: undefined };
  vm.createContext(sandbox);
  vm.runInContext(src, sandbox);
  return sandbox.module.exports;
}

const docToText = loadDocToText();

const DOCS_DIR = process.argv.includes('--dir')
  ? process.argv[process.argv.indexOf('--dir') + 1]
  : '/home/isaacca/hd/Codigos/site_pessoal/pao.50webs.org/receitas/docs/Receitas';

const DRY_RUN = process.argv.includes('--dry-run');
const SINGLE_FILE = process.argv.includes('--file')
  ? process.argv[process.argv.indexOf('--file') + 1]
  : null;

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_USER = 'sail';
const DB_PASS = 'password';
const DB_NAME = 'if0_42505744_receitas';

// ─── Text extraction ────────────────────────────────────────────

function extractText(docPath) {
  const buf = readFileSync(docPath);
  const text = docToText(buf);
  if (!text) throw new Error(`docToText returned null for ${docPath}`);
  return text.replace(/^\uFEFF/, '');
}

// ─── Slugify (preserva acentos no titulo visivel) ──────────────

function slugify(text) {
  return text
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

// ─── Wiki markup cleanup ────────────────────────────────────────

function cleanWiki(line) {
  line = line.replace(/<nowiki>=<\/nowiki>/g, '=');
  line = line.replace(/<nowiki><\/nowiki>/g, '');
  line = line.replace(/'+/g, '');
  line = line.replace(/^=\s*/, '');
  line = line.replace(/\s*=\s*$/, '');
  return line.trim();
}

// ─── Detect category ────────────────────────────────────────────

function detectCategoria(text) {
  const t = text.toLowerCase();
  if (/pao|paes|brioche|hamburguer|frances|suico|forma/.test(t)) return 'Paes';
  if (/bolo|bola|cake/.test(t)) return 'Bolos';
  if (/broa|cavaca|corn/.test(t)) return 'Broas';
  if (/massa|pizza|folhad/.test(t)) return 'Massas';
  if (/doce|biscoito|cookie|sorvete|pudim|manjar|brigadeiro/.test(t)) return 'Doces';
  if (/salgado|coxinha|esfiha|empada|rissol/.test(t)) return 'Salgados';
  return 'Outros';
}

// ─── Extract date from filename ─────────────────────────────────

function extractDate(filename) {
  const m = filename.match(/(\d{2})[_-](\d{2})[_-](\d{4})/);
  if (m) return `${m[3]}-${m[2]}-${m[1]}`;
  return '';
}

// ─── Parse one ingredient line (tab-separated) ──────────────────

function parseIngredientLineTab(raw) {
  const tabs = raw.split('\t').map(t => t.trim()).filter(t => t !== '' && !/^_+$/.test(t));
  if (tabs.length < 2) return null;

  const name = tabs[0];
  let pct = '', qty = '', unit = '';

  for (let i = 1; i < tabs.length; i++) {
    const val = tabs[i];
    const valNorm = val.replace(',', '.');

    if (val.toUpperCase() === 'QB') {
      if (!qty) qty = 'QB'; else unit = 'QB';
    } else if (/\d+(?:[.,]\d+)?\s*%/.test(valNorm)) {
      const m = valNorm.match(/(\d+(?:[.,]\d+)?)\s*%/);
      pct = m[1].replace(',', '.') + '%';
    } else if (/^(\d+(?:\.\d+)?)$/.test(valNorm)) {
      qty = valNorm;
    } else if (/^(\d+(?:\.\d+)?)\s*(g|kg|ml|l|un|xic|colher|copo)$/i.test(valNorm)) {
      qty = RegExp.$1;
      unit = RegExp.$2;
    } else if (/^[a-zA-Z]+$/.test(val) && val.length <= 4) {
      unit = val;
    }
  }

  const parts = [];
  if (pct) parts.push(pct);
  if (qty && qty !== 'QB') parts.push(qty + (unit || 'g'));
  else if (qty === 'QB') parts.push('QB');

  const value = parts.length ? parts.join(' - ') : name;
  return { key: slugify(name), name, value };
}

// ─── Parse one ingredient line (no tabs, Name+Percent) ──────────

function parseIngredientLineText(line) {
  line = line.trim();
  if (!line) return null;
  if (line.toUpperCase() === 'QB') return { key: 'qb', name: 'QB', value: 'QB' };
  if (!/\d/.test(line)) return null;

  // Name%Quantity Unit
  let m = line.match(/^(.+?)\s*=?\s*(\d+(?:[.,]\d+)?)\s*%\s*(\d+(?:[.,]\d+)?)\s*(g|kg|ml|l)?\s*$/i);
  if (m) {
    return {
      key: slugify(m[1].trim()),
      name: m[1].trim(),
      value: `${m[2].replace(',', '.')}% - ${m[3].replace(',', '.')}${m[4] || 'g'}`,
    };
  }

  // Name%
  m = line.match(/^(.+?)\s*=?\s*(\d+(?:[.,]\d+)?)\s*%\s*$/i);
  if (m) {
    return {
      key: slugify(m[1].trim()),
      name: m[1].trim(),
      value: `${m[2].replace(',', '.')}%`,
    };
  }

  // Name = Quantity Unit
  m = line.match(/^(.+?)\s*=\s*(\d+(?:[.,]\d+)?)\s*(g|kg|ml|l)?\s*$/i);
  if (m) {
    return {
      key: slugify(m[1].trim()),
      name: m[1].trim(),
      value: `${m[2].replace(',', '.')}${m[3] || 'g'}`,
    };
  }

  return null;
}

// ─── Parse sections from text ───────────────────────────────────

function parseSections(text) {
  const lines = text.split('\n');

  // Phase 1: split into blocks of { header, ingredients[], modo[] }
  const blocks = [];
  let currentBlock = null;
  let section = '';

  for (const raw of lines) {
    const trimmed = raw.trim();

    if (/^fimIngredientes?\s*$/i.test(trimmed)) {
      section = '';
      continue;
    }

    // Match plain "ingredientes" / "ingredientes:" as section marker
    if (/^(ingredientes?|componentes?):?\s*$/i.test(trimmed)) {
      section = 'ingredientes';
      if (!currentBlock) currentBlock = { header: '', ingredientes: [], modo: [] };
      continue;
    }

    // Match "Ingredientes* para segunda parte" etc. as sub-group labels
    const subGroupMatch = trimmed.match(/^ingredientes?\*?\s+para\s+(.+)$/i);
    if (subGroupMatch) {
      section = 'ingredientes';
      if (!currentBlock) currentBlock = { header: '', ingredientes: [], modo: [] };
      if (currentBlock.ingredientes.length > 0) {
        blocks.push(currentBlock);
        currentBlock = { header: subGroupMatch[1].trim(), ingredientes: [], modo: [] };
      } else {
        currentBlock.header = subGroupMatch[1].trim();
      }
      continue;
    }

    if (/^(modo\s+(de\s+)?preparo|preparo|preparacao|instrucoes?):?\.?\s*$/i.test(trimmed)) {
      section = 'modo';
      if (!currentBlock) currentBlock = { header: '', ingredientes: [], modo: [] };
      continue;
    }

    if (!trimmed) {
      if (section === 'modo' && currentBlock && currentBlock.modo.length > 0) {
        section = '';
      }
      continue;
    }

    // Auto-detect: if no section and line has tab with % or quantity → start ingredientes
    if (section === '' && /\t/.test(raw) && /\d/.test(trimmed)) {
      section = 'ingredientes';
      if (!currentBlock) currentBlock = { header: '', ingredientes: [], modo: [] };
      currentBlock.ingredientes.push({ raw, trimmed });
      continue;
    }

    if (section === 'ingredientes') {
      if (!currentBlock) currentBlock = { header: '', ingredientes: [], modo: [] };
      currentBlock.ingredientes.push({ raw, trimmed });
    } else if (section === 'modo') {
      if (!currentBlock) currentBlock = { header: '', ingredientes: [], modo: [] };
      currentBlock.modo.push(trimmed);
    } else {
      // Between blocks: this text is a header for the NEXT ingredientes section
      if (currentBlock && currentBlock.ingredientes.length > 0) {
        blocks.push(currentBlock);
        currentBlock = { header: '', ingredientes: [], modo: [] };
      }
      if (!currentBlock) currentBlock = { header: '', ingredientes: [], modo: [] };
      const isMetadata = /receita\s+(passada|complementar|de\s+dia)/i.test(trimmed)
        || /^\d{2}\/\d{2}\/\d{4}/.test(trimmed);
      if (!isMetadata) {
        if (currentBlock.header) currentBlock.header += ' ';
        currentBlock.header += trimmed;
      }
    }
  }
  if (currentBlock && (currentBlock.ingredientes.length > 0 || currentBlock.modo.length > 0)) {
    blocks.push(currentBlock);
  }

  // Phase 2: parse each block's ingredients
  const allIngredientes = {};
  const allModo = [];
  let blockCount = 0;

  for (const block of blocks) {
    if (block.ingredientes.length === 0 && block.modo.length === 0) continue;

    const headerSlug = block.header ? slugify(block.header) : '';
    const blocksWithIngredients = blocks.filter(b => b.ingredientes.length > 0).length;
    const useSubGroup = blocksWithIngredients > 1 && headerSlug;
    blockCount++;

    for (const { raw, trimmed } of block.ingredientes) {
      const line = trimmed;

      if (/^total\b/i.test(line)) continue;
      if (/^(modo|preparo|obs)/i.test(line)) continue;
      if (/^=/.test(line)) continue;

      const hasTab = /\t/.test(raw);
      let parsed = null;

      if (hasTab) {
        parsed = parseIngredientLineTab(raw);
      } else if (/^\d+(?:[.,]\d+)?\s*%/.test(line)) {
        parsed = parseIngredientLineText(line);
      } else if (/^\d+(?:[.,]\d+)?\s*(g|kg|ml|l)\b/i.test(line)) {
        parsed = parseIngredientLineText(line);
      } else if (line.toUpperCase() === 'QB') {
        parsed = { key: 'qb', name: 'QB', value: 'QB' };
      } else {
        // Try Name% pattern
        const pctMatch = line.match(/^(.+?)\s*=?\s*(\d+(?:[.,]\d+)?)\s*%/);
        if (pctMatch && !/^(ingredientes?|fim|modo|preparo)/i.test(pctMatch[1].trim())) {
          parsed = parseIngredientLineText(line);
        }
      }

      if (parsed) {
        const key = useSubGroup ? `${headerSlug}/${parsed.key}` : parsed.key;
        allIngredientes[key] = parsed.value;
      }
    }

    if (block.modo.length > 0) {
      allModo.push(block.modo.join(' '));
    }
  }

  // Phase 3: convert flat to nested if has slashes
  let ingredientes;
  const hasSlash = Object.keys(allIngredientes).some(k => k.includes('/'));
  if (hasSlash) {
    ingredientes = {};
    for (const [key, value] of Object.entries(allIngredientes)) {
      const parts = key.split('/');
      if (parts.length === 1) {
        ingredientes[parts[0]] = value;
      } else {
        const group = parts[0];
        const item = parts.slice(1).join('/');
        if (!ingredientes[group] || typeof ingredientes[group] !== 'object') {
          ingredientes[group] = {};
        }
        ingredientes[group][item] = value;
      }
    }
  } else {
    ingredientes = allIngredientes;
  }

  return {
    ingredientes,
    modo_preparo: allModo.join('\n\n'),
  };
}

// ─── Full recipe parse ──────────────────────────────────────────

function parseRecipe(text, filename) {
  const lines = text.split('\n').filter(l => l.trim());
  let titulo = (lines[0] || '').trim();
  titulo = cleanWiki(titulo);

  if (!titulo) {
    titulo = filename.replace(/\.[^.]+$/, '').replace(/[_-]/g, ' ');
  }

  const id = slugify(titulo);
  const categoria = detectCategoria(text);
  const data = extractDate(filename);
  const { ingredientes, modo_preparo } = parseSections(text);

  return {
    id,
    titulo,
    categoria,
    data,
    descricao: `Receita importada de ${filename}`,
    ingredientes,
    modo_preparo,
    observacoes: '',
  };
}

// ─── Main ───────────────────────────────────────────────────────

async function main() {
  let docFiles;
  if (SINGLE_FILE) {
    docFiles = [join(DOCS_DIR, SINGLE_FILE)];
  } else {
    docFiles = readdirSync(DOCS_DIR)
      .filter(f => f.endsWith('.doc'))
      .map(f => join(DOCS_DIR, f));
  }

  console.log(`Encontrados ${docFiles.length} arquivos .doc`);

  let mysql;
  if (!DRY_RUN) {
    mysql = await import('mysql2/promise');
  }

  let pool;
  if (!DRY_RUN) {
    pool = await mysql.createPool({
      host: DB_HOST,
      port: DB_PORT,
      user: DB_USER,
      password: DB_PASS,
      database: DB_NAME,
    });

    // Ensure table exists
    await pool.execute(`CREATE TABLE IF NOT EXISTS receitas (
      id VARCHAR(100) PRIMARY KEY,
      titulo VARCHAR(255) NOT NULL,
      categoria VARCHAR(100) NOT NULL,
      data_receita DATE,
      descricao TEXT,
      ingredientes JSON,
      total_farinha VARCHAR(100),
      modo_preparo TEXT,
      observacoes TEXT,
      image_url VARCHAR(500),
      image_search_query VARCHAR(255),
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`);

    // Clear existing data
    await pool.execute('DELETE FROM receitas');
    console.log('Banco de dados zerado');
  }

  let imported = 0;
  let errors = 0;

  for (const docPath of docFiles) {
    const fname = basename(docPath);
    try {
      console.log(`\n--- ${fname} ---`);

      const text = extractText(docPath);
      const recipe = parseRecipe(text, fname);

      console.log(`  Titulo: ${recipe.titulo}`);
      console.log(`  ID: ${recipe.id}`);
      console.log(`  Categoria: ${recipe.categoria}`);

      const ingCount = typeof recipe.ingredientes === 'object'
        ? Object.keys(recipe.ingredientes).length
        : 0;
      console.log(`  Ingredientes: ${ingCount}`);

      if (DRY_RUN) {
        console.log(`  JSON: ${JSON.stringify(recipe.ingredientes, null, 2)}`);
        console.log(`  Modo: ${recipe.modo_preparo.substring(0, 120)}...`);
      } else {
        const ingJson = JSON.stringify(recipe.ingredientes);
        await pool.execute(
          `INSERT INTO receitas (id, titulo, categoria, data_receita, descricao, ingredientes, modo_preparo, observacoes)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE
             titulo=VALUES(titulo), categoria=VALUES(categoria), data_receita=VALUES(data_receita),
             descricao=VALUES(descricao), ingredientes=VALUES(ingredientes),
             modo_preparo=VALUES(modo_preparo), observacoes=VALUES(observacoes)`,
          [recipe.id, recipe.titulo, recipe.categoria, recipe.data || null,
           recipe.descricao, ingJson, recipe.modo_preparo, recipe.observacoes]
        );
      }

      imported++;
    } catch (err) {
      console.error(`  ERRO: ${err.message}`);
      errors++;
    }
  }

  console.log(`\n=== RELATORIO ===`);
  console.log(`Importadas: ${imported}`);
  console.log(`Erros: ${errors}`);

  if (pool) await pool.end();
}

main().catch(err => {
  console.error('Erro fatal:', err);
  process.exit(1);
});
