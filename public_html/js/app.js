import Alpine from 'alpinejs'

window.Alpine = Alpine

const STATIC_ROUTES = ['home', 'receitas', 'sobre', 'contato']
const SITE_URL = 'https://receitas.free.nf'

function parseParams(search) {
  const params = {}
  if (search) {
    const usp = new URLSearchParams(search)
    for (const [k, v] of usp.entries()) params[k] = v
  }
  return params
}

Alpine.store('router', {
  current: 'home',
  params: {},

  init() {
    this.legacyHashRedirect()
    this.navigateFromPath()
    window.addEventListener('popstate', () => this.navigateFromPath())
    document.addEventListener('click', (e) => {
      const a = e.target.closest ? e.target.closest('a[href^="/"]') : null
      if (!a) return
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return
      const href = a.getAttribute('href')
      if (!href || !href.startsWith('/')) return
      e.preventDefault()
      this.navigate(href)
    })
  },

  legacyHashRedirect() {
    const m = window.location.hash.match(/^#\/(.+)$/)
    if (!m) return
    const clean = m[1].replace(/\/+$/, '')
    const base = window.location.pathname.replace(/\/+$/, '')
    history.replaceState(null, '', base + '/' + clean)
  },

  navigateFromPath() {
    const parts = window.location.pathname.split('/').filter(Boolean)
    const params = parseParams(window.location.search)

    if (parts[0] === 'receita' && parts[1]) {
      this.current = 'receita'
      params.id = decodeURIComponent(parts[1])
    } else {
      const route = parts[0] || 'home'
      this.current = STATIC_ROUTES.includes(route) ? route : 'home'
    }
    this.params = params
    this.updateMeta()
  },

  navigate(path) {
    if (window.location.pathname + window.location.search === path) return
    history.pushState(null, '', path)
    this.navigateFromPath()
    window.scrollTo(0, 0)
  },

  updateMeta() {
    const store = Alpine.store('receitas')
    let title = 'Receitas CEI - Curso de Panificação'
    let desc = 'Receitas do curso de panificação do CEI de Quintino. Aprenda a fazer pães, bolos, broas e doces artesanais em porcentagem de panificação.'
    let canonical = SITE_URL + '/'
    let image = ''

    if (this.current === 'receita') {
      const r = store.getById(this.params.id)
      if (r) {
        title = r.titulo + ' | Receitas CEI'
        desc = r.descricao || ('Receita de ' + r.titulo + ' do curso de panificação do CEI de Quintino.')
        canonical = SITE_URL + '/receita/' + encodeURIComponent(r.id)
        image = r.image_url || ''
      }
    } else if (this.current === 'receitas') {
      title = 'Receitas de Panificação | Receitas CEI'
      desc = 'Todas as receitas do curso de panificação do CEI de Quintino: pães, bolos, broas e doces artesanais.'
      canonical = SITE_URL + '/receitas'
    } else if (this.current === 'sobre') {
      title = 'Sobre o Curso | Receitas CEI'
      desc = 'Conheça o curso de panificação do CEI de Quintino: receitas em porcentagem para facilitar os cálculos.'
      canonical = SITE_URL + '/sobre'
    } else if (this.current === 'contato') {
      title = 'Contato | Receitas CEI'
      desc = 'Entre em contato com o desenvolvedor do site Receitas CEI.'
      canonical = SITE_URL + '/contato'
    }

    document.title = title
    this.setMeta('description', desc)
    this.setMeta('og:title', title)
    this.setMeta('og:description', desc)
    this.setMeta('og:url', canonical)
    if (image) {
      this.setMeta('og:image', image)
    } else {
      this.removeMeta('og:image')
    }
    this.setCanonical(canonical)
  },

  setMeta(name, content) {
    const attr = name.startsWith('og:') ? 'property' : 'name'
    let el = document.querySelector(`meta[${attr}="${name}"]`)
    if (!el) {
      el = document.createElement('meta')
      el.setAttribute(attr, name)
      document.head.appendChild(el)
    }
    el.setAttribute('content', content)
  },

  removeMeta(name) {
    const attr = name.startsWith('og:') ? 'property' : 'name'
    const el = document.querySelector(`meta[${attr}="${name}"]`)
    if (el) el.remove()
  },

  setCanonical(href) {
    let el = document.querySelector('link[rel="canonical"]')
    if (!el) {
      el = document.createElement('link')
      el.setAttribute('rel', 'canonical')
      document.head.appendChild(el)
    }
    el.setAttribute('href', href)
  },
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
    Alpine.store('router').updateMeta()
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
