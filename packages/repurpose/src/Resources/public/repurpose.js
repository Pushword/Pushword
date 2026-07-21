/**
 * Repurpose studio — an Alpine component with two interchangeable editors over one
 * spec, plus a live preview and export. All vanilla + the project's own Alpine
 * bundle (no new dependency, no build step for this package).
 *
 *  - Visual editor: form controls bound to the spec; a deep $watch re-previews on
 *    every change (debounced).
 *  - Source editor: the spec as JSON in a plain textarea. Toggling views syncs the two
 *    through the spec object.
 *  - Live preview: POST the spec to the preview endpoint (validate + render, no
 *    persist); swap the returned SVG deck in, or list violations.
 *  - Export: rasterise each on-screen self-contained SVG to a PNG and post them back
 *    for a .zip (+ .pdf on document networks).
 */
(function () {
  var PREVIEW_DEBOUNCE_MS = 450

  // Tailwind's default palette (v3 hexes), one row per hue × these shades, plus a
  // neutral ramp — the swatch suggestions offered by the colour popover.
  var TW_SHADES = [300, 400, 500, 600, 700, 800]
  var TW_HUES = {
    neutral: ['#d4d4d4', '#a3a3a3', '#737373', '#525252', '#404040', '#262626'],
    slate: ['#cbd5e1', '#94a3b8', '#64748b', '#475569', '#334155', '#1e293b'],
    red: ['#fca5a5', '#f87171', '#ef4444', '#dc2626', '#b91c1c', '#991b1b'],
    orange: ['#fdba74', '#fb923c', '#f97316', '#ea580c', '#c2410c', '#9a3412'],
    amber: ['#fcd34d', '#fbbf24', '#f59e0b', '#d97706', '#b45309', '#92400e'],
    yellow: ['#fde047', '#facc15', '#eab308', '#ca8a04', '#a16207', '#854d0e'],
    lime: ['#bef264', '#a3e635', '#84cc16', '#65a30d', '#4d7c0f', '#3f6212'],
    green: ['#86efac', '#4ade80', '#22c55e', '#16a34a', '#15803d', '#166534'],
    emerald: ['#6ee7b7', '#34d399', '#10b981', '#059669', '#047857', '#065f46'],
    teal: ['#5eead4', '#2dd4bf', '#14b8a6', '#0d9488', '#0f766e', '#115e59'],
    cyan: ['#67e8f9', '#22d3ee', '#06b6d4', '#0891b2', '#0e7490', '#155e75'],
    sky: ['#7dd3fc', '#38bdf8', '#0ea5e9', '#0284c7', '#0369a1', '#075985'],
    blue: ['#93c5fd', '#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8', '#1e40af'],
    indigo: ['#a5b4fc', '#818cf8', '#6366f1', '#4f46e5', '#4338ca', '#3730a3'],
    violet: ['#c4b5fd', '#a78bfa', '#8b5cf6', '#7c3aed', '#6d28d9', '#5b21b6'],
    purple: ['#d8b4fe', '#c084fc', '#a855f7', '#9333ea', '#7e22ce', '#6b21a8'],
    fuchsia: ['#f0abfc', '#e879f9', '#d946ef', '#c026d3', '#a21caf', '#86198f'],
    pink: ['#f9a8d4', '#f472b6', '#ec4899', '#db2777', '#be185d', '#9d174d'],
    rose: ['#fda4af', '#fb7185', '#f43f5e', '#e11d48', '#be123c', '#9f1239'],
  }
  var TW_SWATCHES = Object.keys(TW_HUES).reduce(function (out, hue) {
    TW_HUES[hue].forEach(function (hex, i) {
      out.push({ name: hue + '-' + TW_SHADES[i], hex: hex })
    })

    return out
  }, [])

  var HEX6 = /^#[0-9a-f]{6}$/i

  /** WCAG relative luminance of a #rrggbb colour, or null when it is not a hex. */
  function luminance(hex) {
    if (!HEX6.test(hex || '')) {
      return null
    }
    var channel = function (i) {
      var c = parseInt(hex.substr(1 + i * 2, 2), 16) / 255

      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4)
    }

    return 0.2126 * channel(0) + 0.7152 * channel(1) + 0.0722 * channel(2)
  }

  /** WCAG contrast ratio (1..21) between two hex colours, or null if either is not hex. */
  function contrastRatio(a, b) {
    var la = luminance(a)
    var lb = luminance(b)
    if (null === la || null === lb) {
      return null
    }

    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05)
  }

  function jsonPost(body) {
    return {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    }
  }

  function svgToPng(svg) {
    return new Promise(function (resolve, reject) {
      var width = Number(svg.getAttribute('width')) || svg.viewBox.baseVal.width || 1080
      var height = Number(svg.getAttribute('height')) || svg.viewBox.baseVal.height || 1350
      var xml = new XMLSerializer().serializeToString(svg)
      var url = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(xml)))

      var img = new Image()
      img.onload = function () {
        var canvas = document.createElement('canvas')
        canvas.width = width
        canvas.height = height
        canvas.getContext('2d').drawImage(img, 0, 0, width, height)
        resolve(canvas.toDataURL('image/png'))
      }
      img.onerror = reject
      img.src = url
    })
  }

  function download(blob, filename) {
    var url = URL.createObjectURL(blob)
    var a = document.createElement('a')
    a.href = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  }

  /** Deep-clone a spec and drop empty objects/strings so the stored JSON stays clean. */
  function prune(spec) {
    var s = JSON.parse(JSON.stringify(spec))
    var dropEmptyStrings = function (obj, keys) {
      keys.forEach(function (k) {
        if ('' === obj[k] || null === obj[k] || undefined === obj[k]) {
          delete obj[k]
        }
      })
    }
    var dropEmptyObject = function (obj, key) {
      if (obj[key] && Object.values(obj[key]).every(function (v) { return '' === v || null === v || undefined === v })) {
        delete obj[key]
      }
    }

    var dropDefaults = function (obj, defaults) {
      Object.keys(defaults).forEach(function (k) {
        if (obj[k] === defaults[k]) {
          delete obj[k]
        }
      })
    }

    dropEmptyObject(s, 'palette')
    dropEmptyObject(s, 'counter')
    if (Array.isArray(s.hashtags) && 0 === s.hashtags.length) {
      delete s.hashtags
    }
    dropEmptyStrings(s, ['caption', 'creator', 'fontPairing', 'creatorOnSlides', 'creatorOrientation'])
    // Deck-wide effect: 'none' is the factory default.
    dropDefaults(s, { background: 'none' })
    if ('' === s.plannedAt) {
      s.plannedAt = null
    }
    ;(s.slides || []).forEach(function (slide) {
      dropEmptyObject(slide, 'palette')
      dropEmptyStrings(slide, ['tagline', 'title', 'paragraph'])
      // '' means "inherit the deck effect" — store nothing; an explicit effect
      // (including 'none') is a real per-slide override and is kept.
      if ('' === slide.background) {
        delete slide.background
      }
      // Defaults the factory would apply anyway — keep the stored spec minimal.
      dropDefaults(slide, { layout: 'bottom', align: 'left', textScale: 1, swipe: false })
      // The factory defaults overlay to 0.35 when the slide keeps an image (0 otherwise),
      // so only that value is prunable — an explicit 0 over an image must be stored.
      dropDefaults(slide, { overlay: slide.image && slide.image.media ? 0.35 : 0 })
      if (slide.image) {
        dropDefaults(slide.image, { focusX: 0.5, focusY: 0.5, zoom: 1 })
        if (!slide.image.media) {
          delete slide.image
        }
      }
    })

    return s
  }

  function studio() {
    var cfg = window.RP_STUDIO || {}

    return {
      spec: cfg.spec || { slides: [] },
      vocab: cfg.vocab || {},
      slides: cfg.slides || [],
      backgroundEffects: cfg.backgroundEffects || [],
      tailwind: TW_SWATCHES,
      defaults: cfg.defaults || {},
      urls: cfg.urls || {},
      network: cfg.network || '',
      networkUrls: cfg.networkUrls || {},
      filename: cfg.filename || 'carousel',
      view: 'visual',
      live: { state: 'ok', text: 'in sync' },
      violations: [],
      slidesOpen: [],
      deckOpen: true,
      dragIndex: null,
      dropIndex: null,
      colorPop: { open: false, top: 0, left: 0 },
      _colorObj: null,
      _colorKey: '',
      effectModal: { open: false, target: 'deck', tab: 'All' },
      mediaModal: { open: false, src: '' },
      dirty: false,
      saving: false,
      saveLabel: 'Save',
      exporting: false,
      exportLabel: 'Export .zip',
      _timer: null,
      _sourceReady: false,
      _pickSlide: null,

      init: function () {
        this.normalize()
        this.deckOpen = '0' !== this._store('rp-studio-deckOpen')
        this.$watch('spec', function () {
          this.dirty = true
          if ('visual' === this.view) {
            this.schedulePreview()
          }
        }.bind(this))
        window.addEventListener('message', this.onMediaMessage.bind(this))
      },

      normalize: function () {
        var def = function (obj, key, value) {
          if (undefined === obj[key]) {
            obj[key] = value
          }
        }
        var s = this.spec
        s.palette = s.palette || {}
        s.counter = s.counter || {}
        s.hashtags = Array.isArray(s.hashtags) ? s.hashtags : []
        def(s, 'background', 'none')
        s.slides = Array.isArray(s.slides) ? s.slides : []
        s.slides.forEach(function (slide) {
          slide.image = slide.image || {}
          slide.palette = slide.palette || {}
          // Show the model defaults in the controls rather than a blank/min slider.
          def(slide, 'layout', 'bottom')
          def(slide, 'align', 'left')
          // '' = inherit the deck effect (the control shows "Inherit — <deck>").
          def(slide, 'background', '')
          def(slide, 'overlay', slide.image.media ? 0.35 : 0)
          def(slide, 'textScale', 1)
          def(slide, 'swipe', false)
          def(slide.image, 'focusX', 0.5)
          def(slide.image, 'focusY', 0.5)
          def(slide.image, 'zoom', 1)
        })
        // UI-only: first slide expanded, the rest collapsed (kept out of the spec).
        this.slidesOpen = s.slides.map(function (_, i) { return 0 === i })
      },

      get hashtagsText() {
        return (this.spec.hashtags || []).join(', ')
      },
      set hashtagsText(value) {
        this.spec.hashtags = value.split(',').map(function (t) { return t.trim() }).filter(Boolean)
      },

      // Page link. Standalone carousels carry a generated `standalone/<token>` slug
      // so they stay keyable and flat-syncable; the field shows it as blank, and
      // clearing it (or Detach) keeps/mints one. Linking = typing/picking a real slug.
      get isStandalone() {
        return 0 === String(this.spec.page || '').indexOf('standalone/')
      },
      get pageLabel() {
        return this.isStandalone ? 'Standalone' : (this.spec.page || '—')
      },
      get pageField() {
        return this.isStandalone ? '' : (this.spec.page || '')
      },
      set pageField(value) {
        var v = (value || '').trim()
        this.spec.page = '' === v ? this._standaloneSlug() : v
      },
      detachPage: function () {
        if (! this.isStandalone) {
          this.spec.page = this._standaloneSlug()
        }
      },
      _standaloneSlug: function () {
        return this.isStandalone ? this.spec.page : 'standalone/' + Math.random().toString(36).slice(2, 10)
      },

      /** Raw text of the source editor textarea. */
      sourceText: function () {
        return this.$refs.source ? this.$refs.source.value : ''
      },

      /** The spec of the active view; may throw when the source JSON is malformed. */
      activeSpec: function () {
        if ('source' === this.view) {
          return JSON.parse(this.sourceText())
        }

        return this.spec
      },

      payload: function () {
        return prune(this.activeSpec())
      },

      schedulePreview: function () {
        this.live = { state: 'busy', text: 'editing…' }
        clearTimeout(this._timer)
        this._timer = setTimeout(this.preview.bind(this), PREVIEW_DEBOUNCE_MS)
      },

      preview: async function () {
        var spec
        try {
          spec = this.payload()
        } catch (error) {
          this.badJson(error)
          return
        }

        this.live = { state: 'busy', text: 'rendering…' }
        try {
          var response = await fetch(this.urls.preview, jsonPost(spec))
          var body = await response.json()
          if (response.ok && Array.isArray(body.slides)) {
            this.slides = body.slides
            this.violations = []
            this.live = { state: 'ok', text: 'preview live' }
          } else if (body && Array.isArray(body.violations)) {
            this.violations = body.violations
            this.live = { state: 'bad', text: body.violations.length + ' issue(s)' }
          } else {
            this.live = { state: 'bad', text: (body && body.error) || 'preview failed' }
          }
        } catch (error) {
          console.error('[repurpose] preview failed', error)
          this.live = { state: 'bad', text: 'preview failed' }
        }
      },

      save: async function () {
        var spec
        try {
          spec = this.payload()
        } catch (error) {
          this.badJson(error)
          return
        }

        this.saving = true
        this.saveLabel = 'Saving…'
        try {
          var response = await fetch(this.urls.save, jsonPost(spec))
          if (response.ok) {
            this.violations = []
            this.dirty = false
            this.saveLabel = 'Saved ✓'
            this.live = { state: 'ok', text: 'saved' }
          } else {
            var body = await response.json()
            if (body && Array.isArray(body.violations)) {
              this.violations = body.violations
              this.live = { state: 'bad', text: body.violations.length + ' issue(s) — not saved' }
            }
            this.saveLabel = 'Not saved'
          }
        } catch (error) {
          console.error('[repurpose] save failed', error)
          this.saveLabel = 'Save failed'
        } finally {
          var self = this
          setTimeout(function () { self.saveLabel = 'Save' }, 2000)
          this.saving = false
        }
      },

      addSlide: function () {
        this.spec.slides.push({ layout: 'bottom', align: 'left', title: 'New slide', image: {}, palette: {} })
        this.slidesOpen.push(true)
      },
      removeSlide: function (index) {
        this.spec.slides.splice(index, 1)
        this.slidesOpen.splice(index, 1)
      },
      toggleSlide: function (index) {
        this.slidesOpen[index] = !this.slidesOpen[index]
      },

      // Drag-to-reorder the slide list (native HTML5 DnD, grabbed from the ⠿ handle).
      // The slide's open/collapsed state travels with it so the panel stays stable.
      onDragStart: function (index, event) {
        this.dragIndex = index
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/plain', String(index))
        var row = event.target.closest('.slide-item')
        if (row) {
          event.dataTransfer.setDragImage(row, 16, 16)
        }
      },
      onDragOver: function (index) {
        if (null !== this.dragIndex) {
          this.dropIndex = index
        }
      },
      onDrop: function (index) {
        this.moveSlideTo(this.dragIndex, index)
        this.onDragEnd()
      },
      onDragEnd: function () {
        this.dragIndex = null
        this.dropIndex = null
      },
      moveSlideTo: function (from, to) {
        if (null === from || from === to) {
          return
        }
        this.spec.slides.splice(to, 0, this.spec.slides.splice(from, 1)[0])
        this.slidesOpen.splice(to, 0, this.slidesOpen.splice(from, 1)[0])
      },

      // Switch the studio to another network's carousel for this page. Navigation
      // (not an in-place re-key): the server finds or clones the sibling post.
      switchNetwork: function (event) {
        var target = event.target.value
        if (target === this.network) {
          return
        }
        var url = this.networkUrls[target]
        if (url && (!this.dirty || window.confirm('Discard unsaved changes and switch to ' + target + '?'))) {
          window.location.href = url
          return
        }
        event.target.value = this.network
      },

      thumbUrl: function (filename) {
        return (this.urls.mediaThumb || '').replace('RPFILENAME', encodeURIComponent(filename))
      },
      pickMedia: function (index, mode) {
        this._pickSlide = index
        this.mediaModal = { open: true, src: 'upload' === mode ? this.urls.mediaUpload : this.urls.mediaIndex }
      },
      clearMedia: function (index) {
        var slide = this.spec.slides[index]
        if (slide && slide.image) {
          slide.image.media = ''
        }
      },
      closeMediaModal: function () {
        this.mediaModal = { open: false, src: '' }
        this._pickSlide = null
      },
      // Receives the picked media from the /admin/media picker iframe and drops its
      // filename onto the slide whose "Choose"/"Upload" opened the modal.
      onMediaMessage: function (event) {
        if (event.origin !== window.location.origin) {
          return
        }
        var data = event.data
        if (!data || 'pw-media-picker-select' !== data.type || !data.media) {
          return
        }
        var slide = this.spec.slides[this._pickSlide]
        if (!slide) {
          return
        }
        var name = data.media.fileName || data.media.name || ''
        if (name) {
          slide.image = slide.image || {}
          slide.image.media = name
        }
        this.closeMediaModal()
      },

      // Colour popover — one shared instance, anchored under the clicked field. It
      // edits a (object, key) pair so any palette field can reuse it; writing back to
      // the reactive spec triggers the same live preview as a plain input would.
      openColor: function (obj, key, event) {
        this._colorObj = obj
        this._colorKey = key
        var rect = event.currentTarget.getBoundingClientRect()
        var width = 240
        var height = 300
        var left = Math.min(rect.left, window.innerWidth - width - 8)
        // Flip above the field when it would overflow the viewport bottom.
        var top = rect.bottom + 6 + height > window.innerHeight ? rect.top - height - 6 : rect.bottom + 6
        this.colorPop = { open: true, top: Math.max(8, top), left: Math.max(8, left) }
      },
      get colorValue() {
        return (this._colorObj && this._colorObj[this._colorKey]) || ''
      },
      set colorValue(value) {
        if (this._colorObj) {
          this._colorObj[this._colorKey] = value
        }
      },
      pickSwatch: function (hex) {
        this.colorValue = hex
        this.closeColor()
      },
      clearColor: function () {
        this.colorValue = ''
        this.closeColor()
      },
      closeColor: function () {
        this.colorPop = { open: false, top: 0, left: 0 }
      },

      // Quick picks shown above the Tailwind grid: the deck's own palette (or the
      // site default it inherits) so "reuse a colour already in play" is one click.
      get suggestedColors() {
        var self = this
        var seen = {}
        var out = []
        ;['bg', 'text', 'accent'].forEach(function (role) {
          var hex = (self.spec.palette && self.spec.palette[role]) || self.defaults[role]
          if (hex && HEX6.test(hex) && ! seen[hex]) {
            seen[hex] = true
            out.push({ name: 'deck ' + role, hex: hex })
          }
        })

        return out
      },

      // Live WCAG readout while editing bg/text: the ratio between the colour being
      // set (or the value it inherits) and the resolved other one. Null for accent.
      get colorContrast() {
        var key = this._colorKey
        if ('bg' !== key && 'text' !== key) {
          return null
        }
        var self = this
        var resolve = function (role, primary) {
          return primary
            || (self._colorObj && self._colorObj[role])
            || (self.spec.palette && self.spec.palette[role])
            || self.defaults[role]
        }
        var ratio = contrastRatio(resolve(key, this.colorValue), resolve('bg' === key ? 'text' : 'bg', null))
        if (null === ratio) {
          return null
        }

        return { ratio: ratio.toFixed(2), pass: ratio >= 4.5, passLarge: ratio >= 3 }
      },

      // Collapse of the Deck panel, remembered across reloads (UI-only, not in the spec).
      toggleDeck: function () {
        this.deckOpen = ! this.deckOpen
        this._store('rp-studio-deckOpen', this.deckOpen ? '1' : '0')
      },
      _store: function (key, value) {
        try {
          if (undefined === value) {
            return window.localStorage.getItem(key)
          }
          window.localStorage.setItem(key, value)
        } catch (error) {
          return null
        }
      },

      // Background-effect picker. Tabs and thumbnails come from the server-rendered
      // catalogue (backgroundEffects), so the previews always match the real output.
      get effectTabs() {
        return this.backgroundEffects.reduce(function (tabs, effect) {
          if (tabs.indexOf(effect.category) < 0) {
            tabs.push(effect.category)
          }

          return tabs
        }, ['All'])
      },
      // The modal targets the deck ('deck') or a slide index; a slide additionally
      // offers an "Inherit deck" tile (key '') as the first choice.
      get isDeckEffect() {
        return 'deck' === this.effectModal.target
      },
      get effectsInTab() {
        var tab = this.effectModal.tab
        var tiles = 'All' === tab
          ? this.backgroundEffects
          : this.backgroundEffects.filter(function (effect) { return effect.category === tab })

        if (! this.isDeckEffect && ('All' === tab || 'Basic' === tab)) {
          return [{ key: '', label: 'Inherit deck', category: 'Basic', preview: this.effectPreviewSvg(this.spec.background || 'none') }].concat(tiles)
        }

        return tiles
      },
      get currentEffectKey() {
        if (this.isDeckEffect) {
          return this.spec.background || 'none'
        }
        var slide = this.spec.slides[this.effectModal.target]

        return slide ? (slide.background || '') : ''
      },
      effectByKey: function (key) {
        return this.backgroundEffects.find(function (effect) { return effect.key === key }) || null
      },
      effectLabel: function (key) {
        var effect = this.effectByKey(key)

        return effect ? effect.label : key
      },
      effectPreviewSvg: function (key) {
        var effect = this.effectByKey(key)

        return effect ? effect.preview : ''
      },
      // The per-slide control: the slide's own effect, or the inherited deck effect
      // prefixed with "Inherit —".
      slideEffectLabel: function (slide) {
        return slide.background ? this.effectLabel(slide.background) : 'Inherit — ' + this.effectLabel(this.spec.background || 'none')
      },
      slideEffectPreview: function (slide) {
        return this.effectPreviewSvg(slide.background || this.spec.background || 'none')
      },
      openEffect: function (target) {
        this.effectModal = { open: true, target: target, tab: 'All' }
      },
      chooseEffect: function (key) {
        if (this.isDeckEffect) {
          this.spec.background = key
        } else {
          var slide = this.spec.slides[this.effectModal.target]
          if (slide) {
            slide.background = key
          }
        }
        this.closeEffect()
      },
      closeEffect: function () {
        this.effectModal = { open: false, target: 'deck', tab: 'All' }
      },

      setView: function (view) {
        if (view === this.view) {
          return
        }

        if ('source' === view) {
          // Snapshot the visual spec as JSON *before* flipping the view: payload() reads the
          // active view, so once view is 'source' it would parse the still-empty textarea and throw.
          var text = JSON.stringify(this.payload(), null, 2)
          this.view = 'source'
          var self = this
          this.$nextTick(function () {
            self.ensureSourceEditor()
            self.setSource(text)
          })
          return
        }

        // Source → visual: adopt whatever the source now holds.
        try {
          this.spec = JSON.parse(this.sourceText())
          this.normalize()
        } catch (error) {
          this.badJson(error)
          return
        }
        this.view = 'visual'
      },

      // Wire the source textarea's live preview once. Kept a plain textarea on purpose:
      // a heavyweight embedded code editor (Monaco) pegged the main thread and froze the
      // studio here — its worst case was a hidden-init + a resize loop that wedged the tab.
      // The server still validates the JSON on every preview, so editing stays guided.
      ensureSourceEditor: function () {
        if (this._sourceReady) {
          return
        }
        var textarea = this.$refs.source
        if (!textarea) {
          return
        }
        this._sourceReady = true
        var self = this
        textarea.addEventListener('input', function () {
          if ('source' === self.view) {
            self.dirty = true
            self.schedulePreview()
          }
        })
      },
      setSource: function (text) {
        if (this.$refs.source) {
          this.$refs.source.value = text
        }
      },

      badJson: function (error) {
        this.violations = [{ path: 'json', message: (error && error.message) || 'invalid JSON' }]
        this.live = { state: 'bad', text: 'invalid JSON' }
      },

      exportZip: async function () {
        var svgs = Array.prototype.slice.call(document.querySelectorAll('.slide svg'))
        if (0 === svgs.length) {
          return
        }

        this.exporting = true
        this.exportLabel = 'Rendering…'
        try {
          var pngs = []
          for (var i = 0; i < svgs.length; i++) {
            pngs.push(await svgToPng(svgs[i]))
          }
          var response = await fetch(this.urls.export, jsonPost({ slides: pngs }))
          if (!response.ok) {
            throw new Error('Export failed (' + response.status + ')')
          }
          download(await response.blob(), this.filename + '.zip')
          this.exportLabel = 'Downloaded ✓'
        } catch (error) {
          console.error('[repurpose] export failed', error)
          this.exportLabel = 'Export failed'
        } finally {
          var self = this
          setTimeout(function () { self.exportLabel = 'Export .zip' }, 2000)
          this.exporting = false
        }
      },
    }
  }

  document.addEventListener('alpine:init', function () {
    window.Alpine.data('repurposeStudio', studio)
  })
})()
