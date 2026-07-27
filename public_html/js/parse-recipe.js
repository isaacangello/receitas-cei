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
    if (section === 'ingredientes') ingredientesLines.push({ raw, trimmed })
    else if (section === 'modo') modoLines.push(trimmed)
  }

  if (!modoLines.length && !ingredientesLines.length) {
    modoLines = lines.filter(l => l.trim()).map(l => l.trim())
  }

  const flat = {}
  let subGrupo = ''

  for (const { raw, trimmed } of ingredientesLines) {
    const line = trimmed

    const hasTab = /\t/.test(raw)
    const hasPct = /^\d+(?:[.,]\d+)?\s*%/.test(line)
    const hasUnit = /^\d+(?:[.,]\d+)?\s*(g|kg|ml|l)\b/i.test(line)
    const isQB = line.toUpperCase() === 'QB'

    if (hasTab || hasPct || hasUnit || isQB) {
      const colonMatch = line.match(/^(.+?)\s*[:=]\s*(.+)$/)
      let key, value
      if (colonMatch) {
        key = colonMatch[1].trim().toLowerCase()
          .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
          .replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
        value = colonMatch[2].trim()
      } else {
        key = line.toLowerCase()
          .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
          .replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
        value = line
      }
      const fullKey = subGrupo ? subGrupo + '/' + key : key
      flat[fullKey] = value
      continue
    }

    if (/^obs/i.test(line)) continue

    subGrupo = line.toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
  }

  const hasSlash = Object.keys(flat).some(k => k.includes('/'))
  let ingredientes
  if (hasSlash) {
    ingredientes = {}
    for (const [key, value] of Object.entries(flat)) {
      const parts = key.split('/')
      if (parts.length === 1) {
        ingredientes[parts[0]] = value
      } else {
        const group = parts[0]
        const item = parts[1]
        if (!ingredientes[group] || typeof ingredientes[group] !== 'object') {
          ingredientes[group] = {}
        }
        ingredientes[group][item] = value
      }
    }
  } else {
    ingredientes = flat
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
