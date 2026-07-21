/**
 * Repurpose studio — an Alpine component with two interchangeable editors over one
 * spec, plus a live preview and export. All vanilla + the project's own Alpine and
 * Monaco bundles (no new dependency, no build step for this package).
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
    if ('' === s.plannedAt) {
      s.plannedAt = null
    }
    ;(s.slides || []).forEach(function (slide) {
      dropEmptyObject(slide, 'palette')
      dropEmptyStrings(slide, ['tagline', 'title', 'paragraph'])
      // Defaults the factory would apply anyway — keep the stored spec minimal.
      dropDefaults(slide, { layout: 'bottom', align: 'left', background: 'none', textScale: 1, swipe: false })
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
      urls: cfg.urls || {},
      network: cfg.network || '',
      networkUrls: cfg.networkUrls || {},
      filename: cfg.filename || 'carousel',
      view: 'visual',
      live: { state: 'ok', text: 'in sync' },
      violations: [],
      slidesOpen: [],
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
        s.slides = Array.isArray(s.slides) ? s.slides : []
        s.slides.forEach(function (slide) {
          slide.image = slide.image || {}
          slide.palette = slide.palette || {}
          // Show the model defaults in the controls rather than a blank/min slider.
          def(slide, 'layout', 'bottom')
          def(slide, 'align', 'left')
          def(slide, 'background', 'none')
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
      moveSlide: function (index, direction) {
        var target = index + direction
        if (target < 0 || target >= this.spec.slides.length) {
          return
        }
        this.swap(this.spec.slides, index, target)
        this.swap(this.slidesOpen, index, target)
      },
      swap: function (list, a, b) {
        var moved = list[a]
        list[a] = list[b]
        list[b] = moved
      },
      toggleSlide: function (index) {
        this.slidesOpen[index] = !this.slidesOpen[index]
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
