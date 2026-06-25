/*
 * Pushword quiz runtime — plain ES, no build step.
 *
 * The page already ships the full quiz as readable HTML (SEO + no-JS). This
 * script progressively enhances each `[data-pw-quiz]` into an interactive game:
 * one question at a time, immediate feedback, score donut, anonymous percentile,
 * and an optional end-of-quiz conversion form (pushword/conversation).
 *
 * Idempotent: safe to evaluate more than once and to re-scan after DOM changes.
 */
;(function () {
  'use strict'

  var RESULT_ENDPOINT = '/quiz/result'
  var STORAGE_KEY = 'pwQuizId'

  function initAll() {
    // Multi-level quizzes first: each sets up its tab selector and then inits its
    // own panels (marking them ready), so the standalone pass below skips them.
    var leveled = document.querySelectorAll('.pw-quiz--levels:not([data-pw-levels-ready])')
    for (var l = 0; l < leveled.length; l++) initLevels(leveled[l])

    var quizzes = document.querySelectorAll('[data-pw-quiz]:not([data-pw-quiz-ready])')
    for (var i = 0; i < quizzes.length; i++) initQuiz(quizzes[i])
  }

  /* ----- multi-level tab selector (WAI-ARIA tabs pattern) ----- */

  function initLevels(section) {
    section.setAttribute('data-pw-levels-ready', '1')
    // Flip the no-JS gate on the .pw-quiz section itself: in a leveled quiz the
    // [data-pw-quiz] flag lands on each .pw-quiz-level, so without this the
    // `.pw-quiz:not(.pw-quiz--js)` CSS keeps revealing the correct answer.
    section.classList.add('pw-quiz--js')

    var tabs = Array.prototype.slice.call(section.querySelectorAll('.pw-quiz-tab'))
    var panels = Array.prototype.slice.call(section.querySelectorAll('.pw-quiz-panel'))
    if (0 === tabs.length) return

    function activate(idx, focus) {
      for (var i = 0; i < tabs.length; i++) {
        var selected = i === idx
        tabs[i].setAttribute('aria-selected', selected ? 'true' : 'false')
        tabs[i].tabIndex = selected ? 0 : -1
        if (panels[i]) panels[i].hidden = !selected
        if (selected && focus) tabs[i].focus()
      }
    }

    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () {
        activate(i, false)
      })
      tab.addEventListener('keydown', function (e) {
        var idx = -1
        if ('ArrowRight' === e.key || 'ArrowDown' === e.key) idx = (i + 1) % tabs.length
        else if ('ArrowLeft' === e.key || 'ArrowUp' === e.key) idx = (i - 1 + tabs.length) % tabs.length
        else if ('Home' === e.key) idx = 0
        else if ('End' === e.key) idx = tabs.length - 1
        if (idx >= 0) {
          e.preventDefault()
          activate(idx, true)
        }
      })
    })

    function goToTab(tabId) {
      for (var i = 0; i < tabs.length; i++) {
        if (tabs[i].id === tabId) {
          activate(i, true)
          return
        }
      }
    }

    var levels = Array.prototype.slice.call(section.querySelectorAll('.pw-quiz-level'))
    levels.forEach(function (level) {
      initQuiz(level, { goToTab: goToTab, nextTabId: level.getAttribute('data-next-tab') || '' })
    })
  }

  function readConfig(root) {
    var node = root.querySelector('.pw-quiz-config')
    if (!node) return {}
    try {
      return JSON.parse(node.textContent) || {}
    } catch (e) {
      return {}
    }
  }

  function initQuiz(root, levelCtx) {
    root.setAttribute('data-pw-quiz-ready', '1')
    root.classList.add('pw-quiz--js')

    var config = readConfig(root)
    var immediate = 'end' !== config.feedback
    var questions = Array.prototype.slice.call(root.querySelectorAll('.pw-quiz-q'))
    if (0 === questions.length) return

    var total = questions.length
    var answered = 0
    var score = 0

    questions.forEach(function (q, idx) {
      if (idx > 0) q.setAttribute('data-locked', '1')
      var buttons = Array.prototype.slice.call(q.querySelectorAll('.pw-quiz-a'))
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (q.hasAttribute('data-answered') || q.hasAttribute('data-locked')) return
          answer(q, idx, btn, buttons)
        })
      })
    })

    function answer(q, idx, chosen, buttons) {
      q.setAttribute('data-answered', '1')
      q.classList.add('pw-quiz-q--answered')

      var isCorrect = chosen.hasAttribute('data-correct')
      if (isCorrect) score++
      q.classList.add(isCorrect ? 'pw-quiz-q--correct' : 'pw-quiz-q--wrong')

      buttons.forEach(function (btn) {
        btn.disabled = true
        btn.setAttribute('aria-disabled', 'true')
        if (immediate && btn.hasAttribute('data-correct')) btn.classList.add('pw-quiz-a--correct')
      })
      chosen.setAttribute('aria-pressed', 'true')
      chosen.classList.add(isCorrect ? 'pw-quiz-a--chosen-correct' : 'pw-quiz-a--chosen-wrong')

      answered++
      if (answered >= total) {
        finish()
        return
      }

      var next = questions[idx + 1]
      if (next) {
        next.removeAttribute('data-locked')
        softScroll(next)
      }
    }

    function finish() {
      root.classList.add('pw-quiz--done')
      var pct = Math.round((score / total) * 100)

      var resultBox = root.querySelector('.pw-quiz-result')
      var scoreBox = root.querySelector('.pw-quiz-score')
      if (resultBox) resultBox.hidden = false
      if (scoreBox) scoreBox.innerHTML = buildScoreHtml(pct, score, total, config)
      if (resultBox) softScroll(resultBox)

      submitResult(root.getAttribute('data-slug'), pct, scoreBox, config)
      maybeShowCta(root, pct)
      maybeOfferNextLevel(scoreBox, pct, config, levelCtx)
    }
  }

  // When a level is passed (score >= its threshold, default 50%), surface a
  // button that jumps to the next level's tab.
  function maybeOfferNextLevel(scoreBox, pct, config, levelCtx) {
    if (!scoreBox || !levelCtx || !levelCtx.nextTabId) return
    var pass = 'number' === typeof config.pass ? config.pass : 50
    if (pct < pass) return

    var labels = config.labels || {}
    var btn = document.createElement('button')
    btn.type = 'button'
    btn.className = 'pw-quiz-next'
    btn.textContent = (labels.nextLevel || 'Next level') + ' →'
    btn.addEventListener('click', function () {
      levelCtx.goToTab(levelCtx.nextTabId)
    })
    scoreBox.appendChild(btn)
  }

  function buildScoreHtml(pct, score, total, config) {
    var band = bandMessage(pct, config.results || [])
    var labels = config.labels || {}
    return (
      donutSvg(pct) +
      '<p class="pw-quiz-correct">' +
      (labels.score ? escapeHtml(labels.score) + ' ' : '') +
      score +
      '/' +
      total +
      '</p>' +
      (band ? '<p class="pw-quiz-band">' + escapeHtml(band) + '</p>' : '') +
      '<p class="pw-quiz-percentile" hidden></p>'
    )
  }

  function bandMessage(pct, bands) {
    var best = null
    for (var i = 0; i < bands.length; i++) {
      if (pct >= bands[i].min && (null === best || bands[i].min > best.min)) best = bands[i]
    }
    return best ? best.msg : ''
  }

  function donutSvg(pct) {
    var r = 52
    var c = 2 * Math.PI * r
    var dash = (pct / 100) * c
    return (
      '<svg class="pw-quiz-donut" viewBox="0 0 120 120" role="img" aria-label="' +
      pct +
      '%">' +
      '<circle class="pw-quiz-donut-bg" cx="60" cy="60" r="' +
      r +
      '" fill="none" stroke-width="12"/>' +
      '<circle class="pw-quiz-donut-fg" cx="60" cy="60" r="' +
      r +
      '" fill="none" stroke-width="12" stroke-linecap="round" ' +
      'stroke-dasharray="' +
      dash +
      ' ' +
      c +
      '" transform="rotate(-90 60 60)"/>' +
      '<text class="pw-quiz-donut-text" x="60" y="60" text-anchor="middle" dominant-baseline="central">' +
      pct +
      '%</text>' +
      '</svg>'
    )
  }

  function submitResult(slug, pct, scoreBox, config) {
    if (!slug || !scoreBox || !window.fetch) return
    var line = scoreBox.querySelector('.pw-quiz-percentile')

    fetch(RESULT_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ quiz: slug, score: pct }),
    })
      .then(function (r) {
        return r.ok ? r.json() : null
      })
      .then(function (data) {
        // Skip "better than 0%": it is meaningless and reads as a put-down.
        if (!data || 'number' !== typeof data.percentile || data.percentile <= 0 || !line) return
        var tpl = (config.labels && config.labels.better) || 'Better than {p}% of participants'
        line.textContent = tpl.replace('{p}', data.percentile)
        line.hidden = false
      })
      .catch(function () {})
  }

  /* ----- conversion form (pushword/conversation), deferred to the end ----- */

  function maybeShowCta(root, pct) {
    var cta = root.querySelector('.pw-quiz-cta')
    if (!cta) return

    // The form replaces this inner holder (js-helper swaps the element's
    // outerHTML), so the CTA title sitting next to it survives.
    var holder = cta.querySelector('[data-quiz-cta]')
    if (!holder) return

    var url = holder.getAttribute('data-quiz-cta')
    if (!url) return

    // Carry the score along so the lead can be attributed to this attempt.
    url += (url.indexOf('?') === -1 ? '?' : '&') + 'quizScore=' + pct

    // pushword/js-helper loads `[data-live]` blocks and re-scans on DOMChanged.
    holder.setAttribute('data-live', url)
    document.dispatchEvent(new Event('DOMChanged'))

    // Prefill the form from a previously stored identity, and capture it on submit.
    watchCtaForm(cta)
  }

  function watchCtaForm(cta) {
    var identity = readIdentity()

    var observer = new MutationObserver(function () {
      var email = cta.querySelector('input[type="email"], input[name*="Email" i]')
      var name = cta.querySelector('input[name*="Name" i]')
      if (!email && !name) return
      if (identity) {
        if (name && identity.n && !name.value) name.value = identity.n
        if (email && identity.e && !email.value) email.value = identity.e
      }
      observer.disconnect()
    })
    observer.observe(cta, { childList: true, subtree: true })

    cta.addEventListener(
      'submit',
      function () {
        var email = cta.querySelector('input[type="email"], input[name*="Email" i]')
        var name = cta.querySelector('input[name*="Name" i]')
        storeIdentity({ n: name ? name.value : '', e: email ? email.value : '' })
      },
      true,
    )
  }

  function readIdentity() {
    try {
      return JSON.parse(window.localStorage.getItem(STORAGE_KEY)) || null
    } catch (e) {
      return null
    }
  }

  function storeIdentity(id) {
    if (!id.e && !id.n) return
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(id))
    } catch (e) {}
  }

  /* ----- helpers ----- */

  function softScroll(el) {
    if (!el.scrollIntoView) return
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
    el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' })
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]
    })
  }

  if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', initAll)
  } else {
    initAll()
  }
  document.addEventListener('DOMChanged', initAll)
})()
