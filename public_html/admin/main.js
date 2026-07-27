import Alpine from 'alpinejs'
import mammoth from 'mammoth/mammoth.browser'
import { textToRecipe } from '../js/parse-recipe.js'

window.Alpine = Alpine

Alpine.data('adminApp', () => ({
  key: '',
  authenticated: false,
  csrfToken: '',
  receitas: [],
  error: '',
  message: '',
  showForm: false,
  editing: false,
  form: { id: '', titulo: '', categoria: 'Paes', data: '', descricao: '', ingredientes_json: '{}', modo_preparo: '', observacoes: '', image_url: '', image_search_query: '' },

  importState: 'idle',
  dragOver: false,
  importFile: null,
  importPreview: '',
  importRecipe: null,
  importError: '',

  API: '/api',

  async init() {
    try {
      const res = await fetch(this.API + '/auth.php', { credentials: 'same-origin' })
      if (res.ok) {
        const data = await res.json()
        this.authenticated = true
        this.csrfToken = data.csrf_token
        await this.loadReceitas()
      }
    } catch {}
  },

  async login() {
    this.error = ''
    try {
      const res = await fetch(this.API + '/auth.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: this.key }),
      })
      const data = await res.json()
      if (res.ok) {
        this.authenticated = true
        this.csrfToken = data.csrf_token
        this.key = ''
        await this.loadReceitas()
      } else {
        this.error = data.error || 'Chave invalida'
      }
    } catch (e) {
      this.error = 'Erro ao conectar: ' + e.message
    }
  },

  async logout() {
    await fetch(this.API + '/auth.php', {
      method: 'DELETE',
      credentials: 'same-origin',
    })
    this.authenticated = false
    this.csrfToken = ''
    this.receitas = []
  },

  authHeaders() {
    return {
      'Content-Type': 'application/json',
      'X-CSRF-Token': this.csrfToken,
    }
  },

  async loadReceitas() {
    try {
      const res = await fetch(this.API + '/receitas.php', { credentials: 'same-origin' })
      this.receitas = await res.json()
    } catch (e) {
      this.error = 'Erro ao carregar receitas: ' + e.message
    }
  },

  resetForm() {
    this.editing = false
    this.error = ''
    this.form = { id: '', titulo: '', categoria: 'Paes', data: '', descricao: '', ingredientes_json: '{}', modo_preparo: '', observacoes: '', image_url: '', image_search_query: '' }
  },

  editReceita(r) {
    this.editing = true
    this.error = ''
    this.form = {
      id: r.id,
      titulo: r.titulo,
      categoria: r.categoria,
      data: r.data_receita || r.data || '',
      descricao: r.descricao || '',
      ingredientes_json: JSON.stringify(r.ingredientes || {}, null, 2),
      modo_preparo: r.modo_preparo || '',
      observacoes: r.observacoes || '',
      image_url: r.image_url || '',
      image_search_query: r.image_search_query || '',
    }
    this.showForm = true
  },

  async saveReceita() {
    let ingredientes
    try {
      ingredientes = JSON.parse(this.form.ingredientes_json)
    } catch {
      this.error = 'JSON de ingredientes invalido'
      return
    }

    const payload = {
      id: this.form.id,
      titulo: this.form.titulo,
      categoria: this.form.categoria,
      data: this.form.data,
      descricao: this.form.descricao,
      ingredientes,
      modo_preparo: this.form.modo_preparo,
      observacoes: this.form.observacoes,
      image_url: this.form.image_url,
      image_search_query: this.form.image_search_query,
    }

    const method = this.editing ? 'PUT' : 'POST'
    try {
      const res = await fetch(this.API + '/receitas.php', {
        method,
        credentials: 'same-origin',
        headers: this.authHeaders(),
        body: JSON.stringify(payload),
      })
      const data = await res.json()
      if (res.ok) {
        this.message = this.editing ? 'Receita atualizada!' : 'Receita criada!'
        this.showForm = false
        await this.loadReceitas()
        setTimeout(() => (this.message = ''), 3000)
      } else {
        this.error = data.error || 'Erro ao salvar'
      }
    } catch (e) {
      this.error = 'Erro ao conectar: ' + e.message
    }
  },

  async deleteReceita(id) {
    if (!confirm('Tem certeza que deseja excluir esta receita?')) return
    try {
      const res = await fetch(this.API + '/receitas.php?id=' + encodeURIComponent(id), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: this.authHeaders(),
      })
      if (res.ok) {
        this.message = 'Receita excluida!'
        await this.loadReceitas()
        setTimeout(() => (this.message = ''), 3000)
      }
    } catch (e) {
      this.error = 'Erro ao excluir: ' + e.message
    }
  },

  onDragOver(e) {
    e.preventDefault()
    this.dragOver = true
  },

  onDragLeave() {
    this.dragOver = false
  },

  onDrop(e) {
    e.preventDefault()
    this.dragOver = false
    const file = e.dataTransfer.files[0]
    if (file) this.handleFile(file)
  },

  onFileSelect(e) {
    const file = e.target.files[0]
    if (file) this.handleFile(file)
    e.target.value = ''
  },

  async handleFile(file) {
    this.importError = ''
    this.importPreview = ''
    this.importRecipe = null

    const ext = file.name.split('.').pop().toLowerCase()
    if (!['doc', 'docx', 'txt', 'md'].includes(ext)) {
      this.importError = 'Formato nao suportado: .' + ext
      return
    }
    if (file.size > 5 * 1024 * 1024) {
      this.importError = 'Arquivo muito grande. Maximo: 5MB'
      return
    }

    this.importState = 'extracting'
    this.importFile = file

    try {
      let result

      if (ext === 'docx') {
        const arrayBuffer = await file.arrayBuffer()
        const mammothResult = await mammoth.extractRawText({ arrayBuffer })
        const text = mammothResult.value
        if (!text || !text.trim()) throw new Error('Nenhum texto extraido do .docx')

        const parseRes = await fetch(this.API + '/import.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: this.authHeaders(),
          body: JSON.stringify({ text, filename: file.name }),
        })
        result = await parseRes.json()
        if (!parseRes.ok) throw new Error(result.error || 'Erro ao parsear texto')
      } else if (ext === 'doc') {
        const arrayBuffer = await file.arrayBuffer()
        const text = docToText(arrayBuffer)
        if (!text || !text.trim()) throw new Error('Nenhum texto extraido do .doc')

        const parseRes = await fetch(this.API + '/import.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: this.authHeaders(),
          body: JSON.stringify({ text, filename: file.name }),
        })
        result = await parseRes.json()
        if (!parseRes.ok) throw new Error(result.error || 'Erro ao parsear texto')
      } else {
        const formData = new FormData()
        formData.append('file', file)

        const res = await fetch(this.API + '/import.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-CSRF-Token': this.csrfToken,
          },
          body: formData,
        })
        result = await res.json()
        if (!res.ok) throw new Error(result.error || 'Erro ao importar arquivo')
      }

      this.importRecipe = result.receita
      this.importPreview = result.texto_original || ''
      this.importState = 'preview'
    } catch (e) {
      this.importError = 'Erro ao importar: ' + e.message
      this.importState = 'idle'
    }
  },

  confirmImport() {
    if (!this.importRecipe) return
    this.fillFormFromRecipe(this.importRecipe)
    this.showForm = true
    this.importState = 'idle'
    this.importRecipe = null
    this.importPreview = ''
    this.message = 'Arquivo importado! Revise os dados e salve.'
    setTimeout(() => (this.message = ''), 5000)
  },

  cancelImport() {
    this.importState = 'idle'
    this.importRecipe = null
    this.importPreview = ''
    this.importFile = null
  },

  fillFormFromRecipe(recipe) {
    this.editing = false
    this.form = {
      id: recipe.id,
      titulo: recipe.titulo,
      categoria: recipe.categoria,
      data: recipe.data,
      descricao: recipe.descricao,
      ingredientes_json: JSON.stringify(recipe.ingredientes, null, 2),
      modo_preparo: recipe.modo_preparo,
      observacoes: recipe.observacoes || '',
      image_url: recipe.image_url || '',
      image_search_query: recipe.image_search_query || '',
    }
  },

  async dbAction(action) {
    this.error = ''
    this.message = ''
    try {
      const res = await fetch(this.API + '/db.php?action=' + action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: this.authHeaders(),
        body: JSON.stringify({ action }),
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || 'Erro ao executar acao')
      return data
    } catch (e) {
      this.error = 'Erro: ' + e.message
      return null
    }
  },

  async setupDb() {
    const result = await this.dbAction('setup')
    if (result) {
      this.message = result.message
      await this.loadReceitas()
      setTimeout(() => (this.message = ''), 5000)
    }
  },

  async backupDb() {
    const result = await this.dbAction('backup')
    if (!result) return

    const blob = new Blob([JSON.stringify(result.receitas, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `receitas-backup-${new Date().toISOString().slice(0, 10)}.json`
    a.click()
    URL.revokeObjectURL(url)

    this.message = `Backup baixado: ${result.total} receitas de ${result.database}`
    setTimeout(() => (this.message = ''), 5000)
  },

  async searchImage() {
    const query = this.form.image_search_query
    if (!query || !query.trim()) {
      this.error = 'Digite um texto para buscar'
      return
    }
    try {
      const res = await fetch(this.API + '/images.php?q=' + encodeURIComponent(query), { credentials: 'same-origin' })
      const data = await res.json()
      if (data.url) {
        this.form.image_url = data.url
        this.message = 'Imagem encontrada: ' + data.source
        setTimeout(() => (this.message = ''), 3000)
      } else {
        this.error = 'Nenhuma imagem encontrada'
      }
    } catch (e) {
      this.error = 'Erro ao buscar imagem: ' + e.message
    }
  },

  async freshDb() {
    if (!confirm('ATENCAO: Isso vai APAGAR TODAS as receitas do banco e importar as receitas do JSON inicial. Continuar?')) return
    const result = await this.dbAction('fresh')
    if (result) {
      this.message = result.message
      await this.loadReceitas()
      setTimeout(() => (this.message = ''), 5000)
    }
  },
}))

Alpine.start()
