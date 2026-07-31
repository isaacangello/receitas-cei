#!/usr/bin/env node

import { readFileSync, writeFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');

const DRY_RUN = process.argv.includes('--dry-run');
const VERBOSE = process.argv.includes('--verbose');
const FROM = process.argv.includes('--from')
  ? process.argv[process.argv.indexOf('--from') + 1]
  : null;
const SQL_OUT = process.argv.includes('--sql')
  ? process.argv[process.argv.indexOf('--sql') + 1]
  : null;

// ─── Correções ortográficas por palavra (com preservação de caixa) ──

const REPLACEMENTS = [
  [/\bnao\b/g, 'não'],
  [/\bvoce\b/g, 'você'],
  [/\bapos\b/g, 'após'],
  [/\bate\b/g, 'até'],
  [/\bso\b/g, 'só'],
  [/\btambem\b/g, 'também'],
  [/\bhomogenea\b/g, 'homogênea'],
  [/\bfaca\b/g, 'faça'],
  [/\bacucar\b/g, 'açúcar'],
  [/\bacucarado\b/g, 'açucarado'],
  [/\bfuba\b/g, 'fubá'],
  [/\boleo\b/g, 'óleo'],
  [/\bagua\b/g, 'água'],
  [/\bpedacos\b/g, 'pedaços'],
  [/\bporcoes\b/g, 'porções'],
  [/\besqueca\b/g, 'esqueça'],
  [/\bja\b/g, 'já'],
  [/\bmae\b/g, 'mãe'],
  [/\bmaes\b/g, 'mães'],
  [/\bultimo\b/g, 'último'],
  [/\bultima\b/g, 'última'],
  [/\bnumero\b/g, 'número'],
  [/\bpao\b/g, 'pão'],
  [/\bpaes\b/g, 'pães'],
  [/\bpo\b/g, 'pó'],
  [/\bsao\b/g, 'são'],
  [/\bserao\b/g, 'serão'],
  [/\bestao\b/g, 'estão'],
  [/\bfrances\b/g, 'francês'],
  [/\bsuico\b/g, 'suíço'],
  [/\balemao\b/g, 'alemão'],
  [/\brapido\b/g, 'rápido'],
  [/\brapida\b/g, 'rápida'],
  [/\bmarmore\b/g, 'mármore'],
  [/\bparmesao\b/g, 'parmesão'],
  [/\bdiametro\b/g, 'diâmetro'],
  [/\bquimico\b/g, 'químico'],
  [/\bgostovo\b/g, 'gostoso'],
  [/\bdipensa\b/g, 'dispensa'],
  [/\breforco\b/g, 'reforço'],
  [/\bgráus\b/g, 'graus'],
  [/\bpar\b/g, 'para'],
  [/\baacelerar\b/g, 'acelerar'],
  [/\bfrape\b/g, 'frapê'],
  [/\bcaiscais\b/g, 'Cascais'],
  [/\bhamburguer\b/g, 'hambúrguer'],
  [/\bpara por\b/g, 'para pôr'],
  [/\bpor (nas|na|no)\b/g, 'pôr $1'],
];

// ─── Helpers ────────────────────────────────────────────────────

function replacePreservingCase(text, regex, replacement) {
  return text.replace(regex, (match) => {
    if (match.length > 1 && match === match.toUpperCase()) {
      return replacement.toUpperCase();
    }
    if (/^[A-ZÀ-Ý]/.test(match)) {
      return replacement.charAt(0).toUpperCase() + replacement.slice(1);
    }
    return replacement;
  });
}

function fixText(text) {
  if (!text || typeof text !== 'string') return text;
  let out = text;
  for (const [regex, replacement] of REPLACEMENTS) {
    out = replacePreservingCase(out, regex, replacement);
  }
  return out;
}

function fixKeys(obj) {
  if (!obj || typeof obj !== 'object') return obj;
  const out = {};
  for (const [key, value] of Object.entries(obj)) {
    const newKey = key.replace(/[^\s_-]+/g, word => fixText(word));
    out[newKey] = value && typeof value === 'object' ? fixKeys(value) : value;
  }
  return out;
}

function buildChanges(recipe) {
  const changes = {};

  for (const field of ['titulo', 'descricao', 'modo_preparo', 'observacoes']) {
    const value = recipe[field];
    if (!value) continue;
    const fixed = fixText(value);
    if (fixed !== value) changes[field] = fixed;
  }

  let ingredientes = recipe.ingredientes;
  if (ingredientes && typeof ingredientes === 'object') {
    const fixed = fixKeys(ingredientes);
    if (JSON.stringify(fixed) !== JSON.stringify(ingredientes)) {
      changes.ingredientes = fixed;
    }
  }

  return changes;
}

function sqlEscape(value) {
  if (value === null || value === undefined) return 'NULL';
  let str = String(value).replace(/\\/g, '\\\\').replace(/'/g, "''");
  str = str.replace(/\n/g, '\\n').replace(/\r/g, '\\r').replace(/\0/g, '');
  return `'${str}'`;
}

// ─── Parser do dump gerado pelo db.php (export-sql) ──────────────

function splitSqlValues(tuple) {
  const values = [];
  let cur = '';
  let inStr = false;
  let i = 0;
  while (i < tuple.length) {
    const ch = tuple[i];
    if (inStr) {
      cur += ch;
      if (ch === "'") {
        if (tuple[i + 1] === "'") {
          cur += "'";
          i++;
        } else {
          inStr = false;
        }
      }
    } else if (ch === "'") {
      inStr = true;
      cur += ch;
    } else if (ch === ',') {
      values.push(cur.trim());
      cur = '';
    } else {
      cur += ch;
    }
    i++;
  }
  if (cur.trim() !== '') values.push(cur.trim());
  return values;
}

function unescapeSqlValue(val) {
  if (val === 'NULL') return null;
  let s = val.slice(1, -1);
  let out = '';
  for (let i = 0; i < s.length; i++) {
    const ch = s[i];
    if (ch === '\\') {
      const next = s[i + 1];
      if (next === 'n') {
        out += '\n';
        i++;
      } else if (next === 'r') {
        out += '\r';
        i++;
      } else {
        out += '\\';
        i++;
      }
    } else if (ch === "'" && s[i + 1] === "'") {
      out += "'";
      i++;
    } else {
      out += ch;
    }
  }
  return out;
}

function parseDump(sql) {
  const columns = ['id', 'titulo', 'categoria', 'data_receita', 'descricao', 'ingredientes', 'total_farinha', 'modo_preparo', 'observacoes', 'image_url', 'image_search_query'];
  const recipes = [];
  for (const line of sql.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed.startsWith('INSERT INTO `receitas`')) continue;
    const valuesMatch = trimmed.match(/VALUES\s*\((.*)\)\s*;\s*$/s);
    if (!valuesMatch) continue;
    const rawValues = splitSqlValues(valuesMatch[1]);
    const row = {};
    for (let i = 0; i < rawValues.length; i++) {
      row[columns[i]] = unescapeSqlValue(rawValues[i]);
    }
    recipes.push({
      id: row.id,
      titulo: row.titulo,
      descricao: row.descricao,
      modo_preparo: row.modo_preparo,
      observacoes: row.observacoes,
      ingredientes: JSON.parse(row.ingredientes || '{}'),
    });
  }
  return recipes;
}

// ─── Main ───────────────────────────────────────────────────────

async function main() {
  let recipes;
  let pool = null;

  if (FROM) {
    const dump = readFileSync(join(ROOT, FROM), 'utf8');
    recipes = parseDump(dump);
    console.log(`Dump: ${FROM} (${recipes.length} receitas)`);
  } else {
    const env = readFileSync(join(ROOT, '.env'), 'utf8');
    const get = key => {
      const m = env.match(new RegExp(`^${key}=(.*)$`, 'm'));
      return m ? m[1].trim().replace(/^["']|["']$/g, '') : '';
    };
    const mysql = await import('mysql2/promise');
    pool = await mysql.createPool({
      host: get('DB_HOST'),
      port: get('DB_PORT') ? Number(get('DB_PORT')) : 3306,
      user: get('DB_USERNAME'),
      password: get('DB_PASSWORD'),
      database: get('DB_NAME'),
      connectionLimit: 2,
    });
    const [rows] = await pool.query('SELECT id, titulo, descricao, modo_preparo, observacoes, ingredientes FROM receitas');
    recipes = rows.map(r => ({ ...r, ingredientes: JSON.parse(r.ingredientes || '{}') }));
    console.log(`Banco: ${get('DB_NAME')} (${recipes.length} receitas)`);
  }

  const updates = [];
  let changed = 0;

  for (const recipe of recipes) {
    const changes = buildChanges(recipe);
    if (Object.keys(changes).length === 0) continue;
    changed++;

    console.log(`\n[${recipe.id}] ${recipe.titulo}`);
    for (const [field, value] of Object.entries(changes)) {
      if (field === 'ingredientes') {
        console.log(`  ${field}:`);
        console.log(`    antes:  ${JSON.stringify(recipe.ingredientes)}`);
        console.log(`    depois: ${JSON.stringify(value)}`);
      } else {
        const limit = VERBOSE ? undefined : 140;
        const before = String(recipe[field] ?? '').slice(0, limit);
        const after = String(value).slice(0, limit);
        console.log(`  ${field}:`);
        console.log(`    antes:  ${before}`);
        console.log(`    depois: ${after}`);
      }
    }

    updates.push({
      id: recipe.id,
      titulo: changes.titulo ?? recipe.titulo,
      descricao: changes.descricao ?? recipe.descricao,
      modo_preparo: changes.modo_preparo ?? recipe.modo_preparo,
      observacoes: changes.observacoes ?? recipe.observacoes,
      ingredientes: JSON.stringify(changes.ingredientes ?? recipe.ingredientes),
    });
  }

  console.log(`\n=== RESUMO ===`);
  console.log(`Receitas: ${recipes.length} | Com correções: ${changed}`);

  if (DRY_RUN) {
    if (pool) await pool.end();
    return;
  }

  if (SQL_OUT) {
    const lines = [
      '-- Correções ortográficas automáticas (fix-ortografia.mjs)',
      `-- Fonte: ${FROM || 'banco'}`,
      `-- Receitas: ${recipes.length} | Com correções: ${changed}`,
      '',
    ];
    for (const u of updates) {
      lines.push(
        `UPDATE \`receitas\` SET`,
        `  \`titulo\` = ${sqlEscape(u.titulo)},`,
        `  \`descricao\` = ${sqlEscape(u.descricao)},`,
        `  \`modo_preparo\` = ${sqlEscape(u.modo_preparo)},`,
        `  \`observacoes\` = ${sqlEscape(u.observacoes)},`,
        `  \`ingredientes\` = ${sqlEscape(u.ingredientes)}`,
        `WHERE \`id\` = ${sqlEscape(u.id)};`,
        ''
      );
    }
    writeFileSync(join(ROOT, SQL_OUT), lines.join('\n'), 'utf8');
    console.log(`Arquivo SQL gerado: ${SQL_OUT} (${updates.length} UPDATEs)`);
    if (pool) await pool.end();
    return;
  }

  for (const u of updates) {
    await pool.execute(
      `UPDATE receitas
       SET titulo = ?, descricao = ?, modo_preparo = ?, observacoes = ?, ingredientes = ?
       WHERE id = ?`,
      [u.titulo, u.descricao, u.modo_preparo, u.observacoes, u.ingredientes, u.id]
    );
  }
  console.log(`Atualizadas: ${updates.length} receitas no banco`);

  await pool.end();
}

main().catch(err => {
  console.error('Erro fatal:', err.message);
  process.exit(1);
});
