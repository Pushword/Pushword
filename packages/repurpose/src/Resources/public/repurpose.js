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
  // A slider drag streams events, so a plain trailing debounce would only render
  // on release; the ceiling forces a preview at least this often mid-stream.
  var PREVIEW_MAX_WAIT_MS = 700
  var REVISION_CHECK_MS = 3000
  var PLACED_TEXT_WIDTH = 0.36
  var SNAP_DISTANCE_PX = 6
  var ALIGNMENT_ANCHORS = [0, 0.5, 1]
  var ARROW_DIRECTIONS = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(value, max))
  }

  function textPosition(value, limit) {
    return clamp(Number(value.toFixed(6)), 0, limit)
  }

  function hasText(text) {
    return Boolean(text && String(text.content || '').trim())
  }

  function snapPosition(value, limit, start, length, guides, distance) {
    var nearest = distance
    var snapped = value
    var active = null
    for (var [position, line] of guides) {
      for (var anchor of ALIGNMENT_ANCHORS) {
        var candidate = position - (start + length * anchor)
        var gap = Math.abs(value - candidate)
        if (gap < nearest && candidate >= 0 && candidate <= limit) {
          nearest = gap
          snapped = candidate
          active = line
        }
      }
    }
    for (var guide of guides.values()) {
      guide.classList.toggle('is-snapped', guide === active)
    }

    return snapped
  }

  function slidePoint(svg, event) {
    var matrix = svg.getScreenCTM()
    if (!matrix) {
      return null
    }
    var point = svg.createSVGPoint()
    point.x = event.clientX
    point.y = event.clientY
    var local = point.matrixTransform(matrix.inverse())
    var frame = svg.viewBox.baseVal
    return {
      x: clamp((local.x - frame.x) / frame.width, 0, 1),
      y: clamp((local.y - frame.y) / frame.height, 0, 1),
    }
  }

  function placedText(point, copied) {
    var box = copied ? Object.assign({}, copied) : {
      content: '', width: PLACED_TEXT_WIDTH, size: 0.05,
      align: point.x > 1 - PLACED_TEXT_WIDTH ? 'right' : 'left',
      font: 'body', color: '', highlight: '',
    }
    var anchor = { left: 0, center: 0.5, right: 1 }[box.align]
    var room = { left: 1 - point.x, center: 2 * Math.min(point.x, 1 - point.x), right: point.x }
    box.width = Math.min(box.width, Math.max(0.05, Number(room[box.align].toFixed(3))))
    box.x = clamp(Number((point.x - box.width * anchor).toFixed(3)), 0, 1 - box.width)
    box.y = Number(clamp(point.y, 0, 0.95).toFixed(3))
    return box
  }

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
    dropEmptyObject(s, 'fontPairing')
    if (Array.isArray(s.hashtags) && 0 === s.hashtags.length) {
      delete s.hashtags
    }
    dropEmptyStrings(s, ['video', 'caption', 'creator', 'fontPairing', 'creatorOnSlides', 'creatorOrientation'])
    if (s.audio) {
      ;['voice', 'music'].forEach(function (name) {
        if (!s.audio[name] || !s.audio[name].media) {
          delete s.audio[name]
        }
      })
      if (!s.audio.voice && !s.audio.music) {
        delete s.audio
      }
    }
    // Deck-wide effect: 'none' is the factory default.
    dropDefaults(s, { background: 'none' })
    if ('' === s.plannedAt) {
      s.plannedAt = null
    }
    ;(s.slides || []).forEach(function (slide) {
      dropEmptyObject(slide, 'palette')
      dropEmptyStrings(slide, ['tagline', 'title', 'paragraph', 'highlight'])
      // '' means "inherit the deck effect" — store nothing; an explicit effect
      // (including 'none') is a real per-slide override and is kept.
      if ('' === slide.background) {
        delete slide.background
      }
      // Free texts: contentless boxes are authoring leftovers; per-box defaults
      // match the factory so a stored spec stays minimal.
      var texts = (slide.texts || []).filter(hasText)
      texts.forEach(function (t) {
        dropDefaults(t, { x: 0.08, y: 0.08, width: 0.84, size: 0.05, align: 'left', font: 'body' })
        dropEmptyStrings(t, ['color', 'highlight'])
      })
      if (texts.length) {
        slide.texts = texts
      } else {
        delete slide.texts
      }
      // Defaults the factory would apply anyway — keep the stored spec minimal.
      dropDefaults(slide, { layout: 'bottom', align: 'left', textScale: 1, swipe: false })
      // Drop empty-media image slots, then normalise each kept one to the plural
      // `images` list the model expects. A single populated slot in `full` layout
      // still serialises as a one-element array — the canonical shape.
      var images = (slide.images || []).filter(function (img) { return img && img.media })
      images.forEach(function (img) { dropDefaults(img, { focusX: 0.5, focusY: 0.5, zoom: 1 }) })
      delete slide.image
      if (images.length) {
        slide.images = images
      } else {
        delete slide.images
      }
      // 'full' is the model default; a split layout is a real override worth storing.
      dropDefaults(slide, { imageLayout: 'full' })
      // The factory defaults overlay to 0.35 when the slide keeps an image (0 otherwise),
      // so only that value is prunable — an explicit 0 over an image must be stored.
      dropDefaults(slide, { overlay: images.length ? 0.35 : 0 })
    })

    return s
  }

  function studio() {
    var cfg = window.RP_STUDIO || {}
    var draft
    var initialSpec
    var lastDraftState
    var composing = false
    var switchingView = false

    return {
      spec: cfg.spec || { slides: [] },
      vocab: cfg.vocab || {},
      slides: cfg.slides || [],
      video: cfg.video || null,
      videoError: false,
      selectedVideo: (cfg.spec && cfg.spec.video) || '',
      previewMode: cfg.spec && cfg.spec.video ? 'video' : 'slides',
      messages: cfg.messages || {},
      backgroundEffects: cfg.backgroundEffects || [],
      tailwind: TW_SWATCHES,
      defaults: cfg.defaults || {},
      urls: cfg.urls || {},
      network: cfg.network || '',
      networkUrls: cfg.networkUrls || {},
      filename: cfg.filename || 'carousel',
      feedWidth: cfg.feedWidth || 390,
      slideLimit: cfg.slideLimit || null,
      zoom: 1,
      view: 'visual',
      live: { state: 'ok', text: 'in sync' },
      violations: [],
      warnings: [],
      slidesOpen: [],
      deckOpen: true,
      dragIndex: null,
      dropIndex: null,
      colorPop: { open: false, top: 0, left: 0 },
      _colorObj: null,
      _colorKey: '',
      textPop: { open: false, top: 0, left: 0, ref: '' },
      _editObj: null,
      _editKey: '',
      placingText: false,
      textDraft: null,
      textPopTrigger: null,
      textSelection: null,
      copiedText: null,
      removedText: null,
      actionMenu: null,
      crop: null,
      _previewTargets: null,
      effectModal: { open: false, target: 'deck', tab: 'All' },
      mediaModal: { open: false, src: '' },
      dirty: false,
      revision: cfg.revision,
      syncState: '',
      saving: false,
      saveLabel: 'Save',
      draftState: '',
      draftActive: false,
      exporting: false,
      exportLabel: 'Export .zip',
      exportingSvg: false,
      exportSvgLabel: 'Export .svg',
      exportingVideo: false,
      exportVideoLabel: 'Slideshow .mp4',
      pinning: false,
      pinLabel: '📌 Pin to Pinterest',
      notice: '',
      _timer: null,
      _previewQueuedAt: null,
      _previewSeq: 0,
      _drag: null,
      _dragEndedAt: 0,
      _hoverTimer: null,
      _sourceReady: false,
      _revisionTimer: null,
      _checkingRevision: false,
      _pickSlide: null,
      _pickCell: null,

      init: function () {
        this.normalize()
        initialSpec = this.payload()
        this._previewTargets = this.previewTargets()
        this._boundDragMove = this.deckTextMove.bind(this)
        this._boundDragUp = this.deckTextUp.bind(this)
        this._boundDragCancel = this.deckTextCancel.bind(this)
        this.deckOpen = '0' !== this._store('rp-studio-deckOpen')
        this.$watch('spec', function () {
          this.dirty = true
          draft?.capture()
          if ('visual' === this.view) {
            this.schedulePreview()
          }
        }.bind(this))
        window.addEventListener('message', this.onMediaMessage.bind(this))
        this._revisionTimer = setInterval(this.checkRevision.bind(this), REVISION_CHECK_MS)
        this._draftUnload = (event) => {
          if (!draft || !this.dirty || ['connected', 'updated'].includes(lastDraftState)) return
          event.preventDefault()
          event.returnValue = ''
        }
        window.addEventListener('beforeunload', this._draftUnload)
      },

      destroy: function () {
        clearInterval(this._revisionTimer)
        window.removeEventListener('beforeunload', this._draftUnload)
        draft?.close()
      },

      draftComposition(active) {
        composing = active
      },

      toggleDraft: async function () {
        if (this.saving) return
        if (draft) {
          this.saving = true
          try {
            if (this.draftState !== 'stale') await draft.flush()
            draft.close()
            draft = undefined
            this.draftActive = false
            this.draftState = ''
          } finally {
            this.saving = false
          }
          return
        }
        if (this.syncState || this.draftState === 'pending') return
        var self = this
        try {
          this.draftState = 'pending'
          var { carouselModel, draftCarousel, equalJson, openDraft, reconcileJson } = await import(cfg.draftModule)
          var read = function () {
            try { return draftCarousel(self.payload()) } catch { return undefined }
          }
          this.draftActive = true
          draft = openDraft({
            url: cfg.urls.draft,
            token: cfg.draftToken,
            revision: this.revision,
            model: carouselModel,
            initial: draftCarousel(initialSpec),
            view: {
              read,
              equal: equalJson,
              saved(revision, complete) {
                self.revision = revision
                if (complete && self.dirty) {
                  initialSpec = read()
                  self.dirty = false
                }
              },
              wait: () => composing || switchingView || self._drag !== null || read() === undefined,
              apply(spec) {
                if (self.view === 'source') {
                  self.setSource(JSON.stringify(spec, null, 2))
                  self.dirty = true
                  self.schedulePreview()
                  return
                }
                reconcileJson(self.spec, spec)
                self.normalize(false)
              },
            },
            status(state, violations) {
              if (violations) self.violations = violations
              if (state === 'connected' && self.draftState === 'updated') return
              lastDraftState = state
              self.draftState = state
              if (state === 'stale') self.syncState = 'changed'
            },
          })
        } catch {
          this.draftActive = false
          this.draftState = 'offline'
        }
      },

      checkRevision: async function () {
        if (draft || document.hidden || this.saving || this._checkingRevision) {
          return
        }

        var revision = this.revision
        this._checkingRevision = true
        try {
          var response = await fetch(this.urls.revision, { cache: 'no-store' })
          var current = response.ok ? (await response.json()).revision : null
          if (draft || this.saving || this.revision !== revision) {
            return
          }
          if (404 === response.status) {
            this.syncState = 'deleted'
          } else if (current && current !== revision) {
            this.syncState = 'changed'
          }
        } catch {
          // Keep the draft available and try again on the next check.
        } finally {
          this._checkingRevision = false
        }
      },

      normalize: function (resetSlides = true) {
        var self = this
        var def = function (obj, key, value) {
          if (undefined === obj[key]) {
            obj[key] = value
          }
        }
        var s = this.spec
        def(s, 'video', '')
        s.audio = s.audio || {}
        def(s.audio, 'ducking', true)
        ;['voice', 'music'].forEach(function (name) {
          s.audio[name] = s.audio[name] || {}
          def(s.audio[name], 'media', '')
          def(s.audio[name], 'volume', 'voice' === name ? 1 : 0.3)
          def(s.audio[name], 'fadeIn', 0)
          def(s.audio[name], 'fadeOut', 0)
        })
        s.palette = s.palette || {}
        s.counter = s.counter || {}
        s.hashtags = Array.isArray(s.hashtags) ? s.hashtags : []
        def(s, 'background', 'none')
        s.slides = Array.isArray(s.slides) ? s.slides : []
        s.slides.forEach(function (slide) {
          // Migrate the legacy singular `image` into the plural `images` list, then
          // work with `images` only. Keep at least one (possibly empty) slot so the
          // media picker and sliders always have something to bind to.
          if (slide.image && !Array.isArray(slide.images)) {
            slide.images = [slide.image]
          }
          delete slide.image
          slide.images = Array.isArray(slide.images) ? slide.images : []
          if (0 === slide.images.length) {
            slide.images.push({})
          }
          slide.palette = slide.palette || {}
          def(slide, 'imageLayout', 'full')
          // Show the model defaults in the controls rather than a blank/min slider.
          def(slide, 'layout', 'bottom')
          def(slide, 'align', 'left')
          // '' = inherit the deck effect (the control shows "Inherit — <deck>").
          def(slide, 'background', '')
          def(slide, 'overlay', self.slideHasMedia(slide) ? 0.35 : 0)
          def(slide, 'textScale', 1)
          def(slide, 'swipe', false)
          def(slide, 'highlight', '')
          slide.texts = Array.isArray(slide.texts) ? slide.texts : []
          slide.texts.forEach(function (t) {
            def(t, 'x', 0.08)
            def(t, 'y', 0.08)
            def(t, 'width', 0.84)
            def(t, 'size', 0.05)
            def(t, 'align', 'left')
            def(t, 'font', 'body')
            def(t, 'color', '')
            def(t, 'highlight', '')
          })
          self.ensureCells(slide)
          slide.images.forEach(function (img) {
            def(img, 'focusX', 0.5)
            def(img, 'focusY', 0.5)
            def(img, 'zoom', 1)
          })
        })
        // UI-only: first slide expanded, the rest collapsed (kept out of the spec).
        this.slidesOpen = s.slides.map((_, i) => resetSlides ? i === 0 : (this.slidesOpen[i] ?? i === 0))
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
        var now = Date.now()
        if (null === this._previewQueuedAt) {
          this._previewQueuedAt = now
        }
        clearTimeout(this._timer)
        var wait = Math.min(PREVIEW_DEBOUNCE_MS, this._previewQueuedAt + PREVIEW_MAX_WAIT_MS - now)
        this._timer = setTimeout(this.preview.bind(this), Math.max(wait, 0))
      },

      previewTargets: function () {
        return this.spec.slides.map(function (slide) {
          return {
            slide: slide,
            texts: (slide.texts || []).filter(hasText).map(text => ({
              text: text, x: text.x ?? 0.08, y: text.y ?? 0.08,
            })),
          }
        })
      },

      previewSlideIndex: function (index) {
        var target = this._previewTargets?.[index]
        return target ? this.spec.slides.indexOf(target.slide) : -1
      },
      previewMatches: function (index, slide) {
        return this._previewTargets?.[index]?.slide === slide
      },
      previewIndexOf: function (slide) {
        return this._previewTargets?.findIndex(item => item.slide === slide) ?? -1
      },

      previewTextIndex: function (index, slide, textIndex) {
        var target = this._previewTargets?.[index]
        return target ? slide.texts.indexOf(target.texts[textIndex]?.text) : -1
      },

      preview: async function () {
        this._previewQueuedAt = null
        var spec
        try {
          spec = this.payload()
        } catch (error) {
          this.badJson(error)
          return
        }

        // Mid-drag previews overlap; a response only lands if no newer one started.
        var seq = ++this._previewSeq
        var targets = 'visual' === this.view ? this.previewTargets() : null
        this.live = { state: 'busy', text: 'rendering…' }
        try {
          var response = await fetch(this.urls.preview, jsonPost(spec))
          var body = await response.json()
          if (seq !== this._previewSeq) {
            return
          }
          if (response.ok && Array.isArray(body.slides)) {
            var nextVideo = body.video || null
            if ((this.video && this.video.url) !== (nextVideo && nextVideo.url)) {
              this.videoError = false
            }
            this.video = nextVideo
            this.selectedVideo = spec.video || ''
            // Keep the SVG under the pointer until the drag ends.
            if (this._drag) {
              this._drag.previewSlides = body.slides
              this._drag.previewTargets = targets
            } else {
              this.slides = body.slides
              this._previewTargets = targets
            }
            this.violations = []
            this.warnings = Array.isArray(body.warnings) ? body.warnings : []
            this.live = { state: 'ok', text: 'preview live' }
          } else if (body && Array.isArray(body.violations)) {
            this.violations = body.violations
            this.warnings = []
            this.live = { state: 'bad', text: body.violations.length + ' issue(s)' }
          } else {
            this.live = { state: 'bad', text: (body && body.error) || 'preview failed' }
          }
        } catch (error) {
          if (seq !== this._previewSeq) {
            return
          }
          console.error('[repurpose] preview failed', error)
          this.live = { state: 'bad', text: 'preview failed' }
        }
      },

      save: async function () {
        if (this.saving || this.syncState) {
          return
        }

        if (draft) {
          await draft.flush()
          return
        }
        var options
        try {
          options = jsonPost(this.payload())
          options.headers['If-Match'] = this.revision
        } catch (error) {
          this.badJson(error)
          return
        }

        this.saving = true
        this.saveLabel = 'Saving…'
        try {
          var response = await fetch(this.urls.save, options)
          if (409 === response.status || 404 === response.status) {
            this.syncState = 409 === response.status ? 'changed' : 'deleted'
            this.saveLabel = 'Not saved'
            return
          }

          var body = await response.json()
          if (response.ok) {
            if (this.revision !== body.revision) {
              initialSpec = JSON.parse(options.body)
            }
            this.revision = body.revision
            this.violations = []
            try {
              this.dirty = JSON.stringify(this.payload()) !== options.body
            } catch {
              this.dirty = true
            }
            this.saveLabel = 'Saved ✓'
            this.live = { state: 'ok', text: 'saved' }
          } else {
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

      addSlide: function (after = this.spec.slides.length - 1) {
        this.insertSlide(after, { layout: 'bottom', align: 'left', title: 'New slide', imageLayout: 'full', images: [{ focusX: 0.5, focusY: 0.5, zoom: 1 }], palette: {}, texts: [] })
      },
      duplicateSlide: function (index) {
        this.insertSlide(index, JSON.parse(JSON.stringify(this.spec.slides[index])))
      },
      insertSlide: function (after, slide) {
        if (!this.canInsertSlide) {
          return
        }
        var index = after + 1
        this.spec.slides.splice(index, 0, slide)
        this.revealSlide(index)
      },
      get canInsertSlide() {
        return null === this.slideLimit || this.spec.slides.length < this.slideLimit
      },
      addText: function (slide) {
        if (!Array.isArray(slide.texts)) {
          slide.texts = []
        }
        slide.texts.push({ content: 'Text', x: 0.08, y: 0.08, width: 0.84, size: 0.05, align: 'left', font: 'body', color: '', highlight: '' })
      },
      removeText: function (slide, index) {
        var text = slide.texts[index]
        if (this.textSelection?.text === text) {
          this.textSelection = null
        }
        if (this._editObj === text) {
          this.closeTextPop()
        }
        this.removedText = { slide: slide, text: text, index: index }
        slide.texts.splice(index, 1)
      },
      undoRemoveText: function () {
        var removed = this.removedText
        this.removedText = null
        if (removed && this.spec.slides.includes(removed.slide)) {
          removed.slide.texts.splice(removed.index, 0, removed.text)
          this.revealControl(this.spec.slides.indexOf(removed.slide), '.free-text textarea', removed.index)
        }
      },
      removeSlide: function (index) {
        var slide = this.spec.slides[index]
        if (this.textSelection?.slide === slide) {
          this.textSelection = null
        }
        if (this.crop?.slide === slide) {
          this.crop = null
        }
        if (this.textDraft?.slide === slide || this._editObj === slide || slide.texts.includes(this._editObj)) {
          this.closeTextPop()
        }
        if (this.removedText && this.removedText.slide === slide) {
          this.removedText = null
        }
        this.spec.slides.splice(index, 1)
        this.slidesOpen.splice(index, 1)
      },
      // One panel open at a time: the list stays scannable and the open row is
      // unambiguously the slide being worked on.
      toggleSlide: function (index) {
        var wasOpen = this.slidesOpen[index]
        this.slidesOpen = this.spec.slides.map(function () { return false })
        if (! wasOpen) {
          this.slidesOpen[index] = true
        }
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
        if (url && (!this.dirty || window.confirm('Discard unsaved changes and switch to ' + (this.vocab.networkLabels[target] || target) + '?'))) {
          window.location.href = url
          return
        }
        event.target.value = this.network
      },

      thumbUrl: function (filename) {
        return (this.urls.mediaThumb || '').replace('RPFILENAME', encodeURIComponent(filename))
      },

      // Image cells: one for a `full` slide, two for a split layout. The editor
      // renders a picker + focus/zoom controls per cell, each bound to images[k].
      cellCount: function (slide) {
        return slide && slide.imageLayout && 'full' !== slide.imageLayout ? 2 : 1
      },
      imageCellAt: function (slide, point) {
        switch (slide.imageLayout) {
          case 'split-h': return point.x >= 0.5 ? 1 : 0
          case 'split-v': return point.y >= 0.5 ? 1 : 0
          default: return 0
        }
      },
      get croppedImage() {
        return this.crop ? this.crop.slide.images[this.crop.cell] : null
      },
      cropFrame: function (slide) {
        var cell = this.crop.cell
        switch (slide.imageLayout) {
          case 'split-h': return { left: cell * 50 + '%', top: '0', width: '50%', height: '100%' }
          case 'split-v': return { left: '0', top: cell * 50 + '%', width: '100%', height: '50%' }
          default: return { left: '0', top: '0', width: '100%', height: '100%' }
        }
      },
      beginCrop: function (slideIndex, cell) {
        var slide = this.spec.slides[slideIndex]
        this.setPreviewMode('slides')
        this.closeTextPop()
        this.textSelection = null
        this.placingText = false
        this.crop = { slide: slide, cell: cell }
        this.revealSlide(slideIndex, false)
        var index = this.previewIndexOf(slide)
        this.$nextTick(() => {
          document.querySelectorAll('.slide-canvas')[index]?.scrollIntoView({ block: 'nearest', inline: 'nearest' })
          document.getElementById('rp-crop-done')?.focus({ preventScroll: true })
        })
      },
      finishCrop: function () {
        if (this.crop) {
          if (this._drag?.image) {
            this.deckTextCancel({ pointerId: this._drag.pointerId })
          }
          var index = this.previewIndexOf(this.crop.slide)
          this.crop = null
          document.querySelectorAll('.slide-canvas')[index]?.focus({ preventScroll: true })
        }
      },
      startCropDrag: function (event) {
        var svg = event.currentTarget.querySelector('svg')
        var point = svg && slidePoint(svg, event)
        if (!point || this.imageCellAt(this.crop.slide, point) !== this.crop.cell) {
          return
        }
        var image = svg.querySelector('[data-rp-image="' + this.crop.cell + '"] > image')
        if (!image) {
          return
        }
        var frame = svg.viewBox.baseVal
        var box = svg.getBoundingClientRect()
        var imageFrame = this.cropFrame(this.crop.slide)
        var width = frame.width * parseFloat(imageFrame.width) / 100
        var height = frame.height * parseFloat(imageFrame.height) / 100
        var crop = this.croppedImage
        event.preventDefault()
        this.startDrag({
          image: crop, block: image, pointerId: event.pointerId, moved: false,
          startX: event.clientX, startY: event.clientY, x0: crop.focusX, y0: crop.focusY,
          overflowX: Math.max(0, image.width.baseVal.value - width),
          overflowY: Math.max(0, image.height.baseVal.value - height),
          unit: frame.width / box.width,
        })
      },
      setPreviewMode: function (mode) {
        if (mode !== this.previewMode) {
          this.closeTextPop()
          this.textSelection = null
          this.closeActionMenu(false)
          this.placingText = false
          this.crop = null
          this.previewMode = mode
        }
      },
      imageSlots: function (slide) {
        var slots = []
        for (var k = 0; k < this.cellCount(slide); k++) {
          slots.push(k)
        }

        return slots
      },
      // Materialise a cell per slot the layout needs, so the pickers and sliders
      // bind to real (reactive) nodes. Called when the layout changes and on load,
      // rather than lazily inside a render expression.
      ensureCells: function (slide) {
        slide.images = Array.isArray(slide.images) ? slide.images : []
        while (slide.images.length < this.cellCount(slide)) {
          slide.images.push({ focusX: 0.5, focusY: 0.5, zoom: 1 })
        }
      },
      // A cell's image object, created on demand so the sliders always bind to a
      // real (reactive) node even before a media file is chosen.
      cellImage: function (slide, k) {
        slide.images = Array.isArray(slide.images) ? slide.images : []
        if (!slide.images[k]) {
          slide.images[k] = { focusX: 0.5, focusY: 0.5, zoom: 1 }
        }

        return slide.images[k]
      },
      slideHasMedia: function (slide) {
        return (slide.images || []).some(function (img) { return img && img.media })
      },

      pickMedia: function (index, cell, mode) {
        this._pickSlide = index
        this._pickCell = cell
        this.mediaModal = { open: true, src: 'upload' === mode ? this.urls.mediaUpload : this.urls.mediaIndex }
      },
      pickVideo: function (mode) {
        this.pickMedia('video', null, mode)
      },
      audioError: function (track, field) {
        var path = 'audio.' + track + '.' + field
        var violation = this.violations.find(function (item) { return item.path === path })
        return violation ? violation.message : ''
      },
      pickAudio: function (track, mode) {
        this.pickMedia('audio', track, mode)
      },
      fontPairingChoice: function () {
        var pairing = this.spec.fontPairing
        return pairing && 'object' === typeof pairing ? 'custom' : (pairing || '')
      },
      chooseFontPairing: function (value) {
        this.spec.fontPairing = 'custom' === value ? { heading: '', body: '' } : value
      },
      // Options rendered by x-for exist only after this tick.
      syncSelect: function (el, value) {
        this.$nextTick(function () { el.value = value })
      },
      clearVideo: function () {
        this.spec.video = ''
        this.selectedVideo = ''
        this.video = null
        this.videoError = false
      },
      clearMedia: function (index, cell) {
        var slide = this.spec.slides[index]
        if (slide && slide.images && slide.images[cell]) {
          slide.images[cell].media = ''
          if (this.crop?.slide === slide && this.crop.cell === cell) {
            this.finishCrop()
          }
        }
      },
      closeMediaModal: function () {
        this.mediaModal = { open: false, src: '' }
        this._pickSlide = null
        this._pickCell = null
      },
      // Receives the picked media from the /admin/media picker iframe and drops its
      // filename onto the cell whose "Choose"/"Upload" opened the modal.
      onMediaMessage: function (event) {
        if (event.origin !== window.location.origin) {
          return
        }
        var data = event.data
        if (!this.mediaModal.open || !data || 'pw-media-picker-select' !== data.type || !data.media) {
          return
        }
        if ('audio' === this._pickSlide) {
          var audioFilename = data.media.fileName || ''
          if (!/^[a-z0-9_.-]+\.(mp3|wav|m4a|ogg|flac)$/i.test(audioFilename)) {
            this.notice = this.messages.audioInvalid
            return
          }
          this.spec.audio[this._pickCell].media = audioFilename
          this.closeMediaModal()
          return
        }
        if ('video' === this._pickSlide) {
          var filename = data.media.fileName || ''
          if (!/\.(mp4|webm)$/i.test(filename)) {
            this.notice = this.messages.videoInvalid
            return
          }
          this.spec.video = filename
          this.selectedVideo = filename
          this.video = null
          this.videoError = false
          this.setPreviewMode('video')
          this.closeMediaModal()
          return
        }
        var slide = this.spec.slides[this._pickSlide]
        if (!slide) {
          return
        }
        var name = data.media.fileName || data.media.name || ''
        if (name) {
          this.cellImage(slide, this._pickCell || 0).media = name
        }
        this.closeMediaModal()
      },

      get canvasEditing() {
        return 'visual' === this.view && 'slides' === this.previewMode
      },

      editorKey: function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's' && !event.altKey && !event.shiftKey) {
          event.preventDefault()
          if (!event.repeat) return this.save()
          return
        }
        if (!this.canvasEditing) {
          return
        }
        if ('Escape' === event.key) {
          if (this.actionMenu) {
            this.closeActionMenu()
          } else if (this._drag?.selection) {
            this.deckTextCancel({ pointerId: this._drag.pointerId })
          } else {
            this.placingText = false
            this.finishCrop()
            this.textSelection = null
          }
          return
        }
        if (event.key.toLowerCase() === 't' && event.target.closest('.deck-wrap')
            && !event.altKey && !event.ctrlKey && !event.metaKey && !event.repeat
            && !event.target.closest('input, textarea, select, [contenteditable], dialog, [role="menu"]')) {
          event.preventDefault()
          this.togglePlaceText()
        }
      },

      togglePlaceText: function () {
        var placing = !this.placingText
        this.closeTextPop()
        this.textSelection = null
        this.crop = null
        this.placingText = placing
        if (placing) {
          var index = this.slidesOpen.indexOf(true)
          var target = this.previewIndexOf(this.spec.slides[index])
          var canvas = document.querySelectorAll('.slide-canvas')[Math.max(0, target)]
          if (canvas) {
            canvas.focus()
          }
        }
      },

      canvasKey: function (index, event) {
        if (!this.canvasEditing || this._drag) {
          return
        }
        if ('ContextMenu' === event.key || (event.shiftKey && 'F10' === event.key)) {
          event.preventDefault()
          this.openCanvasMenu(index, event, true)
        } else if ('Enter' === event.key && this.placingText) {
          event.preventDefault()
          var slideIndex = this.previewSlideIndex(index)
          var svg = event.currentTarget.querySelector('svg')
          if (slideIndex >= 0 && svg) {
            this.startTextAt(slideIndex, svg, { x: 0.5, y: 0.5 })
          }
        } else if (this.placingText || this.crop || !this.textSelection || !this.previewMatches(index, this.textSelection.slide)) {
          return
        } else if ('Enter' === event.key) {
          event.preventDefault()
          this.editSelectedText()
        } else if (Object.hasOwn(ARROW_DIRECTIONS, event.key) && !event.altKey && !event.ctrlKey && !event.metaKey) {
          event.preventDefault()
          this.nudgeText(event.currentTarget, ARROW_DIRECTIONS[event.key], event.shiftKey ? 10 : 1)
        }
      },

      openCanvasMenu: function (index, event, keyboard = false) {
        var slideIndex = this.previewSlideIndex(index)
        var slide = this.spec.slides[slideIndex]
        var svg = event.currentTarget.querySelector('svg')
        if (!this.canvasEditing || !slide || !svg) {
          return
        }
        event.preventDefault()
        var point = keyboard ? { x: 0.5, y: 0.5 } : slidePoint(svg, event)
        if (!point) {
          return
        }
        var block = event.target.closest('[data-rp-edit]')
        var ref = block && block.getAttribute('data-rp-edit')
        var textIndex = ref && ref.startsWith('texts.') ? this.previewTextIndex(index, slide, Number(ref.slice(6))) : -1
        if (textIndex >= 0) {
          this.openTextMenu(slideIndex, textIndex, event)
          return
        }

        var items = ['textHere']
        if (this.copiedText) {
          items.push('pasteHere')
        }
        var cell = this.imageCellAt(slide, point)
        if (ref && !ref.startsWith('texts.') && slide[ref]) {
          items.unshift('convertText')
        } else if (!keyboard && slide.images[cell] && slide.images[cell].media) {
          items.push('replaceImage')
          if (svg.querySelector('[data-rp-image="' + cell + '"]')) {
            items.push('cropImage')
          }
        }
        this.openActionMenu(event, { slide: slide, svg: svg, point: point, ref: ref, cell: cell, items: items }, keyboard)
      },

      openTextMenu: function (slideIndex, textIndex, event) {
        var slide = this.spec.slides[slideIndex]
        this.openActionMenu(event, { slide: slide, text: slide.texts[textIndex], items: [
          'duplicateText', 'copyText', 'textSettings', 'removeText',
        ] })
      },

      openSlideMenu: function (index, event) {
        var slide = this.spec.slides[index]
        if (slide && 'visual' === this.view) {
          event.preventDefault()
          this.openActionMenu(event, { slide: slide, items: ['duplicateSlide', 'insertSlide'] })
        }
      },
      openPreviewSlideMenu: function (index, event) {
        if (this.canvasEditing) {
          this.openSlideMenu(this.previewSlideIndex(index), event)
        }
      },

      openActionMenu: function (event, target, keyboard = false) {
        var trigger = event.currentTarget
        if ('contextmenu' === event.type && !trigger.matches('[tabindex], button')) {
          trigger = trigger.querySelector('[aria-haspopup="menu"]') || trigger
        }
        if (this.actionMenu && this.actionMenu.trigger === trigger && 'contextmenu' !== event.type && !keyboard) {
          this.closeActionMenu()
          return
        }
        this.closeTextPop()
        this.placingText = false
        this.crop = null
        clearTimeout(this._hoverTimer)
        var rect = trigger.getBoundingClientRect()
        var atPointer = 'contextmenu' === event.type && !keyboard
        this.actionMenu = Object.assign(target, {
          trigger: trigger,
          left: atPointer ? event.clientX : rect.left,
          top: atPointer ? event.clientY : rect.bottom + 4,
        })
        this.$nextTick(() => {
          if (!this.actionMenu || this.actionMenu.trigger !== trigger) {
            return
          }
          var menu = this.$refs.actionMenu
          this.actionMenu.left = clamp(this.actionMenu.left, 8, window.innerWidth - menu.offsetWidth - 8)
          this.actionMenu.top = clamp(this.actionMenu.top, 8, window.innerHeight - menu.offsetHeight - 8)
          menu.querySelector('[role="menuitem"]').focus({ preventScroll: true })
        })
      },

      closeActionMenu: function (restoreFocus = true) {
        var menu = this.actionMenu
        this.actionMenu = null
        if (menu && restoreFocus) {
          menu.trigger.focus({ preventScroll: true })
        }
      },

      actionMenuKey: function (event) {
        var items = Array.from(event.currentTarget.querySelectorAll('[role="menuitem"]'))
        var index = items.indexOf(document.activeElement)
        switch (event.key) {
          case 'Escape':
          case 'Tab':
            event.stopPropagation()
            if ('Escape' === event.key) {
              event.preventDefault()
            }
            this.closeActionMenu()
            return
          case 'ArrowDown': index += 1; break
          case 'ArrowUp': index -= 1; break
          case 'Home': index = 0; break
          case 'End': index = items.length - 1; break
          default: return
        }
        event.preventDefault()
        items[(index + items.length) % items.length].focus()
      },

      actionUnavailable: function (action) {
        return ['insertSlide', 'duplicateSlide'].includes(action) && !this.canInsertSlide
      },

      runCanvasAction: function (action) {
        if (this.actionUnavailable(action)) {
          return
        }
        var menu = this.actionMenu
        var slide = menu.slide
        var index = this.spec.slides.indexOf(slide)
        this.closeActionMenu()
        if (index < 0) {
          return
        }
        var textIndex = menu.text ? slide.texts.indexOf(menu.text) : -1
        if (menu.text && textIndex < 0) {
          return
        }
        switch (action) {
          case 'textHere': this.startTextAt(index, menu.svg, menu.point); break
          case 'pasteHere': slide.texts.push(placedText(menu.point, this.copiedText)); break
          case 'convertText': this.freeTextFromField(index, menu.ref); break
          case 'copyText': this.copiedText = Object.assign({}, menu.text); break
          case 'duplicateText': this.duplicateText(index, textIndex); break
          case 'textSettings': this.revealControl(index, '.free-text', textIndex, 'input[type="range"]'); break
          case 'removeText':
            this.removeText(slide, textIndex)
            this.$nextTick(() => document.getElementById('rp-undo-text').focus({ preventScroll: true }))
            break
          case 'replaceImage': this.pickMedia(index, menu.cell, 'choose'); break
          case 'cropImage': this.beginCrop(index, menu.cell); break
          case 'duplicateSlide': this.duplicateSlide(index); break
          case 'insertSlide': this.addSlide(index); break
        }
      },

      duplicateText: function (slideIndex, textIndex) {
        var texts = this.spec.slides[slideIndex].texts
        var original = texts[textIndex]
        var dx = original.x + original.width > 0.97 ? -0.03 : 0.03
        var dy = original.y > 0.9 ? -0.04 : 0.04
        texts.push(Object.assign({}, original, {
          x: clamp(Number((original.x + dx).toFixed(3)), 0, 1 - original.width),
          y: Number(clamp(original.y + dy, 0, 0.95).toFixed(3)),
        }))
        this.revealControl(slideIndex, '.free-text textarea', texts.length - 1)
      },

      startTextAt: function (slideIndex, svg, point) {
        this.closeTextPop()
        this.placingText = false
        this.crop = null
        var box = placedText(point)
        this.textDraft = { slide: this.spec.slides[slideIndex], box: box }
        this._editObj = this.textDraft.box
        this._editKey = 'content'
        this.textPopTrigger = svg.parentElement
        var screen = svg.getBoundingClientRect()
        var x = screen.left + screen.width * point.x
        var y = screen.top + screen.height * box.y
        this.showTextPop({ left: x, top: y, bottom: y }, 'new')
        this.revealSlide(slideIndex, false)
      },

      deckTextClick: function (index, event) {
        // Ignore the click dispatched after a drag release.
        if (performance.now() - this._dragEndedAt < 300 || !this.canvasEditing || this.crop) {
          return
        }
        var slideIndex = this.previewSlideIndex(index)
        var slide = this.spec.slides[slideIndex]
        if (!slide) {
          return
        }
        this.closeActionMenu(false)
        var svg = event.currentTarget.querySelector('svg')
        if (this.placingText) {
          var point = svg && slidePoint(svg, event)
          if (point) {
            event.stopPropagation()
            this.startTextAt(slideIndex, svg, point)
          }
          return
        }
        var block = event.target.closest('[data-rp-edit]')
        if (!block) {
          this.textSelection = null
          return
        }
        if (this.selectText(index, block)) {
          block.focus({ preventScroll: true })
        }
      },
      canvasTextFocus: function (index, event) {
        if (!this.placingText && !this.crop && !this._drag && event.target.matches('[data-rp-edit]')) {
          this.selectText(index, event.target)
        }
      },
      editSelectedText: function () {
        var selected = this.textSelection
        if (!selected?.block.isConnected) {
          return
        }
        this.closeTextPop()
        this._editObj = selected.text || selected.slide
        this._editKey = selected.text ? 'content' : selected.field
        this.textPopTrigger = selected.block.closest('.slide-canvas')
        var ref = selected.text ? 'texts.' + selected.slide.texts.indexOf(selected.text) : selected.field
        this.showTextPop(selected.block.getBoundingClientRect(), ref)
      },
      canvasDoubleClick: function (index, event) {
        var block = event.target.closest('[data-rp-edit]')
        if (block && !this.placingText && !this.crop && !this._drag && performance.now() - this._dragEndedAt >= 300
            && this.selectText(index, block)) {
          this.editSelectedText()
        }
      },
      showTextPop: function (rect, ref) {
        clearTimeout(this._hoverTimer)
        var width = Math.min(304, window.innerWidth - 16)
        var left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))
        // Flip above the block when the editor would overflow the viewport bottom.
        var top = rect.bottom + 8 + 160 > window.innerHeight ? Math.max(8, rect.top - 168) : rect.bottom + 8
        this.textPop = { open: true, top: top, left: left, ref: ref }
        this.$nextTick(() => {
          if (this.textPop.open) {
            this.$refs.textPopField.focus({ preventScroll: true })
          }
        })
      },
      get textPopLabel() {
        var ref = this.textPop.ref
        if ('new' === ref) {
          return this.messages.canvasTextLabel
        }
        if (0 === ref.indexOf('texts.')) {
          return 'Free text ' + (parseInt(ref.slice(6), 10) + 1)
        }

        return ref.charAt(0).toUpperCase() + ref.slice(1)
      },
      get editValue() {
        return (this._editObj && this._editObj[this._editKey]) || ''
      },
      set editValue(value) {
        if (this._editObj) {
          this._editObj[this._editKey] = value
        }
        var draft = this.textDraft
        if (draft) {
          var index = draft.slide.texts.indexOf(draft.box)
          if (index < 0 && value.trim()) {
            draft.slide.texts.push(draft.box)
          } else if (index >= 0 && !value.trim()) {
            draft.slide.texts.splice(index, 1)
          }
        }
      },
      closeTextPop: function (restoreFocus = false) {
        if (restoreFocus) {
          this.textPopTrigger?.focus({ preventScroll: true })
        }
        this.textPopTrigger = null
        this.textDraft = null
        this._editObj = null
        this._editKey = ''
        this.textPop = { open: false, top: 0, left: 0, ref: '' }
      },
      draftMarker: function () {
        var box = this.textDraft.box
        return {
          left: (box.x + ('right' === box.align ? box.width : 0)) * 100 + '%',
          top: box.y * 100 + '%',
        }
      },
      // Open the slide's panel row (closing the others) and bring it into view.
      revealSlide: function (index, scroll = true) {
        this.slidesOpen = this.spec.slides.map(function (_, i) { return i === index })
        if (scroll) {
          this.$nextTick(() => {
            document.getElementById('rp-slide-item-' + index)?.scrollIntoView({ block: 'nearest' })
          })
        }
      },
      revealControl: function (slideIndex, selector, index, child) {
        this.revealSlide(slideIndex, false)
        this.$nextTick(() => {
          var row = document.getElementById('rp-slide-item-' + slideIndex)
          var target = row && row.querySelectorAll(selector)[index]
          var control = child ? target?.querySelector(child) : target
          if (control) {
            control.scrollIntoView({ block: 'nearest' })
            control.focus({ preventScroll: true })
          }
        })
      },
      // Dwelling on a slide in the deck syncs the panel to it — long enough that
      // a mouse merely crossing the deck does not jump the panel around.
      hoverSlide: function (index) {
        clearTimeout(this._hoverTimer)
        index = this.previewSlideIndex(index)
        if (index < 0 || this._drag || this.textPop.open || this.actionMenu || this.placingText || this.crop || this.slidesOpen[index]) {
          return
        }
        var self = this
        this._hoverTimer = setTimeout(function () { self.revealSlide(index) }, 400)
      },
      hoverSlideLeave: function () {
        clearTimeout(this._hoverTimer)
      },

      syncCanvas: function (index, canvas, editable) {
        this.$nextTick(() => {
          var blocks = canvas.querySelectorAll('[data-rp-edit]')
          blocks.forEach(block => {
            if (editable) {
              block.setAttribute('tabindex', '0')
            } else {
              block.removeAttribute('tabindex')
            }
          })
          var selected = this.textSelection
          if (!editable || !selected || !this.previewMatches(index, selected.slide)) {
            return
          }
          if (!(selected.text ? hasText(selected.text) : String(selected.slide[selected.field] || '').trim())) {
            this.textSelection = null
            return
          }
          var ref = selected.text
            ? 'texts.' + this._previewTargets[index].texts.findIndex(item => item.text === selected.text)
            : selected.field
          var block = canvas.querySelector('[data-rp-edit="' + ref + '"]')
          if (block && block !== selected.block && this.selectText(index, block) && document.activeElement === canvas) {
            block.focus({ preventScroll: true })
          }
        })
      },
      selectText: function (index, block) {
        var slideIndex = this.previewSlideIndex(index)
        if (!this.canvasEditing || slideIndex < 0 || !block?.ownerSVGElement) {
          return null
        }
        if (this.textSelection?.block === block) {
          return this.textSelection
        }
        var slide = this.spec.slides[slideIndex]
        var svg = block.ownerSVGElement
        var frame = svg.viewBox.baseVal
        var ref = block.getAttribute('data-rp-edit')
        var freeText = ref.startsWith('texts.')
        var entry = freeText ? this._previewTargets[index].texts[Number(ref.slice(6))] : null
        var geom = !freeText && slide[ref] ? this._measureBlock(svg, block, ref) : null
        if (entry ? !slide.texts.includes(entry.text) : !geom) {
          return null
        }
        var bounds = block.getBBox()
        var selected = {
          block: block, slide: slide, text: entry?.text, field: geom ? ref : null, geom: geom,
          x0: entry ? Number(entry.x) : this._freeTextX(slide.align, geom.width),
          y0: entry ? Number(entry.y) : geom.y,
          bounds: {
            x: (bounds.x - frame.x) / frame.width, y: (bounds.y - frame.y) / frame.height,
            width: bounds.width / frame.width, height: bounds.height / frame.height,
          },
        }
        this.textSelection = selected
        this.renderTextPosition(this.textSelection, this.textCoordinates(selected))
        if (!this.slidesOpen[slideIndex]) {
          this.revealSlide(slideIndex, false)
        }

        return this.textSelection
      },
      textCoordinates: function (selected) {
        return { x: Number(selected.text?.x ?? selected.x0), y: Number(selected.text?.y ?? selected.y0) }
      },
      textRightLimit: function (selected) {
        return 1 - Number(selected.text?.width ?? selected.geom?.width ?? 0.84)
      },
      renderTextPosition: function (selected, position) {
        var frame = selected.block.ownerSVGElement.viewBox.baseVal
        var dx = position.x - selected.x0
        var dy = position.y - selected.y0
        if (dx || dy) {
          selected.block.setAttribute('transform', 'translate(' + (dx * frame.width) + ' ' + (dy * frame.height) + ')')
        } else {
          selected.block.removeAttribute('transform')
        }
        var bounds = selected.bounds
        selected.outline = {
          left: (bounds.x + dx) * 100 + '%', top: (bounds.y + dy) * 100 + '%',
          width: bounds.width * 100 + '%', height: bounds.height * 100 + '%',
        }
      },
      commitTextPosition: function (selected, position) {
        var x = textPosition(position.x, this.textRightLimit(selected))
        var y = textPosition(position.y, 0.95)
        var current = this.textCoordinates(selected)
        if (x !== current.x || y !== current.y) {
          if (selected.field) {
            selected.text = this._fieldToFreeText(selected.slide, selected.field, selected.geom, x, y)
            selected.field = null
          } else {
            selected.text.x = x
            selected.text.y = y
          }
        }
        this.renderTextPosition(selected, { x: x, y: y })
      },
      nudgeText: function (canvas, direction, step) {
        var selected = this.textSelection
        var svg = selected.block.ownerSVGElement
        if (canvas.querySelector('svg') !== svg) {
          return
        }
        // Keep keyboard focus on a stable node while the preview swaps the SVG.
        canvas.focus({ preventScroll: true })
        var frame = svg.viewBox.baseVal
        var position = this.textCoordinates(selected)
        this.commitTextPosition(selected, { x: position.x + direction[0] * step / frame.width, y: position.y + direction[1] * step / frame.height })
      },

      // Move the SVG group locally while dragging, then update the spec on release.
      deckTextDown: function (index, event) {
        if (this._drag || !this.canvasEditing || this.placingText || 0 !== event.button) {
          return
        }
        var slideIndex = this.previewSlideIndex(index)
        var slide = this.spec.slides[slideIndex]
        if (this.crop) {
          if (this.crop.slide === slide) {
            this.startCropDrag(event)
          }
          return
        }
        var selected = this.selectText(index, event.target.closest('[data-rp-edit]'))
        if (!selected) {
          return
        }
        var svg = selected.block.ownerSVGElement
        var frame = svg.viewBox.baseVal
        var matrix = svg.getScreenCTM()
        var position = this.textCoordinates(selected)
        event.preventDefault() // no text selection while dragging
        event.currentTarget.focus({ preventScroll: true })
        this.startDrag({
          selection: selected,
          pointerId: event.pointerId,
          moved: false,
          startX: event.clientX,
          startY: event.clientY,
          x0: position.x,
          y0: position.y,
          screenWidth: frame.width * matrix.a,
          screenHeight: frame.height * matrix.d,
        })
      },
      startDrag: function (drag) {
        this._drag = drag
        window.addEventListener('pointermove', this._boundDragMove)
        window.addEventListener('pointerup', this._boundDragUp)
        window.addEventListener('pointercancel', this._boundDragCancel)
      },
      deckTextMove: function (event) {
        var d = this._drag
        if (!d || d.pointerId !== event.pointerId) {
          return
        }
        var dx = event.clientX - d.startX
        var dy = event.clientY - d.startY
        if (!d.moved) {
          if (Math.abs(dx) + Math.abs(dy) < 4) {
            return // still a click, not a drag
          }
          d.moved = true
          this.closeTextPop()
          if (!d.image) {
            d.guides = this._showAlignmentGuides(d.selection.block)
          }
        }
        if (d.image) {
          d.nx = d.overflowX > 0.1 ? clamp(d.x0 - dx * d.unit / d.overflowX, 0, 1) : d.x0
          d.ny = d.overflowY > 0.1 ? clamp(d.y0 - dy * d.unit / d.overflowY, 0, 1) : d.y0
          d.block.setAttribute('transform', 'translate(' + (d.x0 - d.nx) * d.overflowX + ' ' + (d.y0 - d.ny) * d.overflowY + ')')
          return
        }
        var selected = d.selection
        var right = this.textRightLimit(selected)
        var bounds = selected.bounds
        var x = clamp(d.x0 + dx / d.screenWidth, 0, right)
        var y = clamp(d.y0 + dy / d.screenHeight, 0, 0.95)
        var distance = event.altKey ? 0 : SNAP_DISTANCE_PX
        d.position = {
          x: snapPosition(x, right, bounds.x - selected.x0, bounds.width, d.guides.columns, distance / d.screenWidth),
          y: snapPosition(y, 0.95, bounds.y - selected.y0, bounds.height, d.guides.rows, distance / d.screenHeight),
        }
        this.renderTextPosition(selected, d.position)
      },
      _showAlignmentGuides: function (block) {
        var svg = block.ownerSVGElement
        var frame = svg.viewBox.baseVal
        var columns = new Map()
        var rows = new Map()
        var overlay = document.createElement('div')
        overlay.className = 'slide-guides'
        overlay.setAttribute('aria-hidden', 'true')
        var addLine = function (positions, ratio, side) {
          if (ratio <= 0 || ratio >= 1 || positions.has(ratio)) {
            return
          }
          var line = document.createElement('span')
          line.className = 'slide-guide-' + side
          line.style[side] = ratio * 100 + '%'
          positions.set(ratio, line)
          overlay.append(line)
        }
        svg.querySelectorAll('[data-rp-edit], [data-rp-guide]').forEach(function (element) {
          if (element === block) {
            return
          }
          var bounds = element.getBBox()
          // A peer may still carry its temporary translation while awaiting a preview.
          var transforms = element.transform?.baseVal
          var translation = transforms?.numberOfItems ? transforms.getItem(0).matrix : null
          ALIGNMENT_ANCHORS.forEach(function (fraction) {
            addLine(columns, (bounds.x + bounds.width * fraction + (translation?.e ?? 0) - frame.x) / frame.width, 'left')
            addLine(rows, (bounds.y + bounds.height * fraction + (translation?.f ?? 0) - frame.y) / frame.height, 'top')
          })
        })
        svg.after(overlay)

        return { overlay: overlay, columns: columns, rows: rows }
      },
      deckTextUp: function (event) {
        var d = this._endDrag(event)
        if (!d || !d.moved) {
          return
        }
        this._dragEndedAt = performance.now()
        if (d.image) {
          d.image.focusX = Number(d.nx.toFixed(4))
          d.image.focusY = Number(d.ny.toFixed(4))
        } else {
          this.commitTextPosition(d.selection, d.position)
        }
      },
      deckTextCancel: function (event) {
        var d = this._endDrag(event)
        if (d?.moved && d.image) {
          d.block.removeAttribute('transform')
        } else if (d?.moved) {
          this.renderTextPosition(d.selection, this.textCoordinates(d.selection))
        }
      },
      _endDrag: function (event) {
        var d = this._drag
        if (!d || d.pointerId !== event.pointerId) {
          return null
        }
        this._drag = null
        if (d.guides) {
          d.guides.overlay.remove()
        }
        if (d.previewSlides) {
          this.slides = d.previewSlides
          this._previewTargets = d.previewTargets
        }
        window.removeEventListener('pointermove', this._boundDragMove)
        window.removeEventListener('pointerup', this._boundDragUp)
        window.removeEventListener('pointercancel', this._boundDragCancel)

        return d
      },

      // Convert a stack field (title / tagline / paragraph) into a free text box
      // that renders the same: geometry is read off the rendered SVG block (the
      // final laid-out font size, measured line widths, resolved colours) rather
      // than recomputed, so the renderer's shrink-to-fit and palette resolution
      // stay authoritative when moving the block or using its "→ free text" button.
      freeTextFromField: function (slideIndex, field) {
        var slide = this.spec.slides[slideIndex]
        if (!slide) {
          return
        }
        var index = this.previewIndexOf(slide)
        var svg = document.querySelectorAll('.slide-canvas > svg')[index]
        var block = svg ? svg.querySelector('[data-rp-edit="' + field + '"]') : null
        var geom = (block ? this._measureBlock(svg, block, field) : null) || this._fallbackGeometry(slide, field)
        this._fieldToFreeText(slide, field, geom, this._freeTextX(slide.align, geom.width), geom.y)
      },
      _measureBlock: function (svg, block, field) {
        var line = block.querySelector('text')
        var frameW = svg.viewBox.baseVal.width
        var frameH = svg.viewBox.baseVal.height
        var fontSize = line ? Number(line.getAttribute('font-size')) : 0
        if (!fontSize || !frameW || !frameH) {
          return null
        }
        var maxLine = 0
        Array.prototype.forEach.call(block.querySelectorAll('text'), function (t) {
          maxLine = Math.max(maxLine, Number(t.getAttribute('textLength')) || 0)
        })
        // The box hugs the widest measured line (plus a hair against rounding),
        // so the drag range stays wide; the anchor maths in _freeTextX keeps the
        // text where it rendered. Taglines are measured in original case but
        // displayed uppercase, which the converted box stores — give them
        // headroom so the re-measure does not wrap earlier.
        var width = (maxLine / frameW) * ('tagline' === field ? 1.25 : 1) + 0.005

        return {
          size: Number((fontSize / frameW).toFixed(4)),
          // The renderer puts each baseline at top + fontSize × 0.82.
          y: Number(((Number(line.getAttribute('y')) - fontSize * 0.82) / frameH).toFixed(3)),
          width: Number(Math.max(0.05, Math.min(0.84, width)).toFixed(3)),
          // Free texts inherit the slide text colour, which is what the title and
          // paragraph render in; the tagline's accent must be frozen explicitly.
          color: 'tagline' === field ? line.getAttribute('fill') || '' : '',
        }
      },
      // Pre-shrink stack sizes and a rough vertical spot, for a field the
      // preview has not rendered yet (converted before the debounce landed).
      _fallbackGeometry: function (slide, field) {
        var scale = Number(slide.textScale) || 1
        var baseSizes = { title: 0.11, tagline: 0.035, paragraph: 0.034 }
        var stackTops = { top: 0.09, center: 0.4, bottom: 0.6 }

        return {
          size: baseSizes[field] * scale,
          y: stackTops[slide.layout] || 0.6,
          width: 0.84,
          color: 'tagline' === field
            ? slide.palette.accent || (this.spec.palette && this.spec.palette.accent) || '#38bdf8'
            : '',
        }
      },
      // The stack anchors at an 8% margin; the box left edge that keeps a
      // narrower (hugging) box's anchor where the stack rendered it.
      _freeTextX: function (align, width) {
        var x = 0.08
        if ('center' === align) {
          x = 0.5 - width / 2
        } else if ('right' === align) {
          x = 0.92 - width
        }

        return Math.max(0, Math.min(1 - width, x))
      },
      _fieldToFreeText: function (slide, field, geom, x, y) {
        var content = String(slide[field] || '').trim()
        if ('' === content) {
          return
        }
        if ('tagline' === field) {
          // The stack renders taglines uppercase; a free text has no case
          // transform, so bake it into the content.
          content = content.toUpperCase()
        }
        if (!Array.isArray(slide.texts)) {
          slide.texts = []
        }
        slide.texts.push({
          content: content,
          x: textPosition(x, 1 - geom.width),
          y: textPosition(y, 0.95),
          width: geom.width,
          size: Number(Math.max(0.01, Math.min(0.3, geom.size)).toFixed(4)),
          align: slide.align || 'left',
          font: 'title' === field ? 'heading' : 'body',
          color: geom.color,
          highlight: 'title' === field ? slide.highlight || '' : '',
        })
        slide[field] = ''
        if ('title' === field) {
          slide.highlight = ''
        }

        return slide.texts.at(-1)
      },

      // Mouse wheel over any range slider nudges it by one step — delegated from
      // the layout root so every slider (zoom, overlay, crop, free-text box…)
      // gets it without per-input wiring. Ctrl+wheel is left to the deck zoom.
      sliderWheel: function (event) {
        var input = event.target
        if (event.ctrlKey || 0 === event.deltaY || !(input instanceof HTMLInputElement) || 'range' !== input.type) {
          return
        }
        event.preventDefault()
        var step = Number(input.step) || 1
        var min = '' === input.min ? 0 : Number(input.min)
        var max = '' === input.max ? 100 : Number(input.max)
        // Snap to the step grid to dodge float drift (0.15000000000000002).
        var decimals = (String(step).split('.')[1] || '').length
        var next = Number(input.value) + (event.deltaY < 0 ? step : -step)
        input.value = String(Math.min(max, Math.max(min, Number(next.toFixed(decimals)))))
        input.dispatchEvent(new Event('input', { bubbles: true }))
      },
      // Ctrl+wheel (or a trackpad pinch, reported the same way) over the deck
      // zooms the preview; the factor follows the wheel delta so a pinch stays
      // smooth and a notched wheel moves in comfortable jumps.
      deckZoomWheel: function (event) {
        if (! event.ctrlKey) {
          return
        }
        event.preventDefault()
        var next = this.zoom * Math.exp(-event.deltaY * 0.002)
        this.zoom = Math.min(3, Math.max(0.5, Math.round(next * 100) / 100))
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

      // Preview zoom — an inspection tool, deliberately not persisted: the deck
      // must come back at 100% (= real feed size) so text size keeps being judged
      // at the scale a scrolling viewer actually sees.
      get slideWidth() {
        return Math.round(this.feedWidth * this.zoom)
      },
      get deckNote() {
        if (1 === this.zoom) {
          return this.feedWidth + 'px · 100%'
        }

        return this.slideWidth + 'px · ' + Math.round(100 * this.zoom) + '%'
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
          this.closeTextPop()
          this.closeActionMenu(false)
          this.textSelection = null
          this.placingText = false
          this.crop = null
          this.removedText = null
          switchingView = true
          this.view = 'source'
          var self = this
          this.$nextTick(function () {
            self.ensureSourceEditor()
            self.setSource(text)
            switchingView = false
          })
          return
        }

        // Source → visual: adopt whatever the source now holds.
        try {
          this.spec = JSON.parse(this.sourceText())
          this.normalize()
          this._previewTargets = null
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
            draft?.capture()
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

      // Vector export: bundle the self-contained slides as SVG files.
      exportSvg: async function () {
        var svgs = Array.prototype.slice.call(document.querySelectorAll('.slide svg'))
        if (0 === svgs.length) {
          return
        }

        this.exportingSvg = true
        this.exportSvgLabel = 'Bundling…'
        try {
          var serializer = new XMLSerializer()
          var sources = svgs.map(function (svg) {
            var copy = svg.cloneNode(true)
            copy.querySelectorAll('[data-rp-edit][tabindex]').forEach(block => block.removeAttribute('tabindex'))

            return serializer.serializeToString(copy)
          })
          var response = await fetch(this.urls.export, jsonPost({ svgs: sources }))
          if (!response.ok) {
            throw new Error('Export failed (' + response.status + ')')
          }
          download(await response.blob(), this.filename + '-svg.zip')
          this.exportSvgLabel = 'Downloaded ✓'
        } catch (error) {
          console.error('[repurpose] svg export failed', error)
          this.exportSvgLabel = 'Export failed'
        } finally {
          var self = this
          setTimeout(function () { self.exportSvgLabel = 'Export .svg' }, 2000)
          this.exportingSvg = false
        }
      },

      // Video export: rasterise every slide to PNG (as the .zip export does) and
      // post them for ffmpeg to stitch into an .mp4 slideshow. ffmpeg is the site's
      // own dependency — a 503 comes back with an install hint, shown as a notice.
      exportVideo: async function () {
        var svgs = Array.prototype.slice.call(document.querySelectorAll('.slide svg'))
        if (0 === svgs.length) {
          return
        }

        this.exportingVideo = true
        this.exportVideoLabel = 'Encoding…'
        this.notice = ''
        try {
          var pngs = []
          for (var i = 0; i < svgs.length; i++) {
            pngs.push(await svgToPng(svgs[i]))
          }
          var response = await fetch(this.urls.exportVideo, jsonPost({ slides: pngs, audio: this.payload().audio || null }))
          if (response.ok) {
            download(await response.blob(), this.filename + '.mp4')
            this.exportVideoLabel = 'Downloaded ✓'
          } else {
            var body = await response.json().catch(function () { return {} })
            // Audio problems come back as field violations, shown on their track.
            var violations = Array.isArray(body.violations) ? body.violations : []
            if (violations.length) {
              this.violations = violations
            }
            this.notice = body.error || (violations.length ? violations[0].message : 'Video export failed (' + response.status + ')')
            this.exportVideoLabel = 503 === response.status ? 'ffmpeg required' : 'Export failed'
          }
        } catch (error) {
          console.error('[repurpose] video export failed', error)
          this.exportVideoLabel = 'Export failed'
        } finally {
          var self = this
          setTimeout(function () { self.exportVideoLabel = 'Slideshow .mp4' }, 2500)
          this.exportingVideo = false
        }
      },

      // Pinterest "post directly": rasterise the cover slide, store it as a public
      // PNG (Pinterest fetches the media URL server-side), then open Pinterest's own
      // create-pin screen pre-filled with it — the user confirms and publishes there.
      pinToPinterest: async function () {
        var svgs = Array.prototype.slice.call(document.querySelectorAll('.slide svg'))
        if (0 === svgs.length) {
          return
        }

        this.pinning = true
        this.pinLabel = 'Preparing…'
        try {
          var png = await svgToPng(svgs[0])
          var response = await fetch(this.urls.pinImage, jsonPost({ png: png, spec: this.payload() }))
          if (!response.ok) {
            throw new Error('Pin failed (' + response.status + ')')
          }
          var body = await response.json()
          window.open(body.pinUrl, '_blank', 'noopener')
          this.pinLabel = 'Opened Pinterest ✓'
        } catch (error) {
          console.error('[repurpose] pin failed', error)
          this.pinLabel = 'Pin failed'
        } finally {
          var self = this
          setTimeout(function () { self.pinLabel = '📌 Pin to Pinterest' }, 2500)
          this.pinning = false
        }
      },
    }
  }

  document.addEventListener('alpine:init', function () {
    window.Alpine.data('repurposeStudio', studio)
  })
})()
