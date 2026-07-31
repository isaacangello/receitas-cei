import Alpine from 'alpinejs'

window.Alpine = Alpine

Alpine.store('router', {
  current: 'home',
  params: {},

  init() {
    this.navigateFromHash()
    window.addEventListener('hashchange', () => this.navigateFromHash())
  },

  navigateFromHash() {
    const hash = window.location.hash.slice(1) || '/home'
    const [pathPart, queryPart] = hash.split('?')
    const parts = pathPart.split('/').filter(Boolean)

    const params = {}
    if (queryPart) {
      for (const pair of queryPart.split('&')) {
        const idx = pair.indexOf('=')
        if (idx === -1) continue
        const k = pair.slice(0, idx)
        params[k] = decodeURIComponent(pair.slice(idx + 1))
      }
    }

    if (parts[0] === 'receita' && parts[1]) {
      this.current = 'receita'
      params.id = parts[1]
    } else if (parts[0] === 'admin') {
      this.current = 'admin'
    } else {
      this.current = parts[0] || 'home'
    }
    this.params = params
  },

  navigate(page) {
    window.location.hash = '/' + page
  }
})

Alpine.store('receitas', {
  items: [],
  filtered: null,
  loaded: false,

  async init() {
    try {
      const res = await fetch('/api/receitas.php')
      if (res.ok) {
        this.items = await res.json()
      }
    } catch (e) {
      console.error('Falha ao carregar receitas:', e)
    }
    this.loaded = true
  },

  getAll() {
    const cat = Alpine.store('router').params.cat
    let list = this.filtered ?? this.items
    if (cat) list = list.filter(r => r.categoria === cat)
    return list
  },

  getByCategoria(cat) {
    return this.items.filter(r => r.categoria === cat)
  },

  getById(id) {
    return this.items.find(r => r.id === id)
  },

  getCategorias() {
    return [...new Set(this.items.map(r => r.categoria))]
  },

  search(query) {
    if (!query) {
      this.filtered = null
      return
    }
    const q = query.toLowerCase()
    this.filtered = this.items.filter(r =>
      r.titulo.toLowerCase().includes(q) ||
      r.descricao.toLowerCase().includes(q) ||
      r.categoria.toLowerCase().includes(q)
    )
  }
})

Alpine.start()
