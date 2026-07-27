export function slugify(text) {
  return text
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}

export function detectCategoria(text) {
  const t = text.toLowerCase()
  if (/pao|paes|brioche|hamburguer|frances|suico|forma/.test(t)) return 'Paes'
  if (/bolo|bola|cake/.test(t)) return 'Bolos'
  if (/broa|cavaca|corn/.test(t)) return 'Broas'
  if (/massa|pizza|folhad/.test(t)) return 'Massas'
  if (/doce|biscoito|cookie|sorvete|pudim|manjar|brigadeiro/.test(t)) return 'Doces'
  if (/salgado|coxinha|esfiha|empada|rissol/.test(t)) return 'Salgados'
  return 'Outros'
}

export function extractDateFromFilename(filename) {
  const m = filename.match(/(\d{2})[_-](\d{2})[_-](\d{4})/)
  if (m) return `${m[3]}-${m[2]}-${m[1]}`
  return ''
}

export function parseSections(text) {
  const lines = text.split('\n')
  let section = ''
  let ingredientesLines = []
  let modoLines = []

  for (const raw of lines) {
    const trimmed = raw.trim()
    if (/^(ingredientes?|componentes?):?\s*$/i.test(trimmed)) {
      section = 'ingredientes'
      continue
    }
    if (/^(modo\s+(de\s+)?preparo|preparo|preparacao|instrucoes?):?\s*$/i.test(trimmed)) {
      section = 'modo'
      continue
    }
    if (!trimmed) continue
    if (section === 'ingredientes') ingredientesLines.push(trimmed)
    else if (section === 'modo') modoLines.push(trimmed)
  }

  if (!modoLines.length && !ingredientesLines.length) {
    modoLines = lines.filter(l => l.trim()).map(l => l.trim())
  }

  const ingredientes = {}
  for (const line of ingredientesLines) {
    const colonMatch = line.match(/^(.+?)\s*[:=]\s*(.+)$/)
    if (colonMatch) {
      const key = colonMatch[1].trim().toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
      ingredientes[key] = colonMatch[2].trim()
    } else {
      const key = line.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
      ingredientes[key] = line
    }
  }

  const modo_preparo = modoLines.join(' ')

  return { ingredientes, modo_preparo }
}

export function textToRecipe(text, filename = '') {
  const lines = text.split('\n').filter(l => l.trim())
  let titulo = (lines[0] || '').trim()
  if (!titulo) {
    titulo = filename.replace(/\.[^.]+$/, '').replace(/[_-]/g, ' ')
  }

  const id = slugify(titulo)
  const categoria = detectCategoria(text)
  const data = extractDateFromFilename(filename)
  const { ingredientes, modo_preparo } = parseSections(text)

  return {
    id,
    titulo,
    categoria,
    data,
    descricao: `Receita importada de ${filename || 'texto'}`,
    ingredientes,
    modo_preparo,
    observacoes: ''
  }
}
