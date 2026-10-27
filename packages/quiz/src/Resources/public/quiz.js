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
  var DONE_PREFIX = 'pwQuizDone:' // + slug → the finished attempt to replay on return

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
    var slug = root.getAttribute('data-slug')
    var profileMode = 'profile' === config.mode
    var assessmentMode = 'assessment' === config.mode
    var recordResults = undefined === config.recordResults ? !assessmentMode : config.recordResults
    var immediate = !profileMode && !assessmentMode && 'end' !== config.feedback
    var questions = Array.prototype.slice.call(root.querySelectorAll('.pw-quiz-q'))
    if (0 === questions.length) return

    var total = questions.length
    var answered = 0
    var score = 0
    var scores = {} // profile mode: tallied weights per profile key
    var chosenAnswers = [] // {q, a} per answered question — optional enrichment for a logged-in visitor
    var chosenIdx = [] // chosen answer's index within each question — persisted to replay a finished attempt

    questions.forEach(function (q, idx) {
      if (idx > 0) q.setAttribute('data-locked', '1')
      var buttons = Array.prototype.slice.call(q.querySelectorAll('.pw-quiz-a'))
      var confirm = document.createElement('button')
      confirm.type = 'button'
      confirm.className = 'pw-quiz-confirm'
      var labels = config.labels || {}
      confirm.textContent = (assessmentMode && idx === total - 1 ? labels.finish : '') || labels.confirm || 'Confirm answer'
      confirm.disabled = true
      q.appendChild(confirm)

      if (assessmentMode) {
        var progress = document.createElement('progress')
        progress.className = 'pw-quiz-progress'
        progress.value = idx + 1
        progress.max = total
        progress.setAttribute('aria-label', (labels.question || 'Question') + ' ' + (idx + 1) + '/' + total)
        q.insertBefore(progress, q.firstChild)
      }
      if (assessmentMode && idx > 0) {
        var back = document.createElement('button')
        back.type = 'button'
        back.className = 'pw-quiz-back'
        back.textContent = (config.labels && config.labels.back) || 'Back'
        back.addEventListener('click', function () { assessmentStep(idx - 1, true) })
        q.insertBefore(back, confirm)
      }
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (q.hasAttribute('data-answered') || q.hasAttribute('data-locked')) return
          buttons.forEach(function (a) {
            a.setAttribute('aria-pressed', a === btn ? 'true' : 'false')
            a.classList.toggle('pw-quiz-a--selected', a === btn)
          })
          if (assessmentMode) rememberChoice(q, idx, btn, buttons)
          confirm.disabled = false
        })
      })
      confirm.addEventListener('click', function () {
        if (q.hasAttribute('data-answered') || q.hasAttribute('data-locked')) return
        var chosen = q.querySelector('.pw-quiz-a[aria-pressed="true"]')
        if (chosen) answer(q, idx, chosen, buttons)
      })
    })

    // Already played on this browser? Replay the finished attempt (chosen answers
    // + result + a "restart" button) instead of showing a blank quiz. Only fully
    // completed attempts are stored, and a changed question count discards a stale one.
    var done = readDone(slug)
    if (assessmentMode) assessmentStep(0, false)
    if (done && Array.isArray(done.a) && done.a.length === total &&
      (done.mode ? done.mode === (config.mode || 'quiz') : !assessmentMode) &&
      done.a.every(function (a, idx) { return Number.isInteger(a) && a >= 0 && a < questions[idx].querySelectorAll('.pw-quiz-a').length })) restore(done.a)

    function rememberChoice(q, idx, chosen, buttons) {
      var qText = q.querySelector('.pw-quiz-question')
      var aText = chosen.querySelector('.pw-quiz-a-text')
      chosenAnswers[idx] = {
        q: qText ? qText.textContent.trim() : '',
        a: (aText ? aText.textContent : chosen.textContent).trim(),
      }
      chosenIdx[idx] = buttons.indexOf(chosen)
    }

    function assessmentStep(idx, focus) {
      root.classList.remove('pw-quiz--done')
      delete root.dataset.pwQuizResult
      var resultBox = root.querySelector('.pw-quiz-result')
      if (resultBox) resultBox.hidden = true
      questions.forEach(function (q, i) {
        q.hidden = i !== idx
        if (i !== idx) q.setAttribute('data-locked', '1')
        else q.removeAttribute('data-locked')
        q.removeAttribute('data-answered')
        var confirm = q.querySelector('.pw-quiz-confirm')
        confirm.hidden = false
        confirm.disabled = undefined === chosenIdx[i]
        Array.prototype.forEach.call(q.querySelectorAll('.pw-quiz-a'), function (btn, a) {
          btn.disabled = false
          btn.removeAttribute('aria-disabled')
          var selected = a === chosenIdx[i]
          btn.setAttribute('aria-pressed', selected ? 'true' : 'false')
          btn.classList.toggle('pw-quiz-a--selected', selected)
        })
      })
      if (focus) {
        softScroll(questions[idx])
        var heading = questions[idx].querySelector('.pw-quiz-question')
        heading.tabIndex = -1
        heading.focus({preventScroll: true})
      }
    }

    // The per-question answered state, shared by a live click and a restore replay.
    function applyChoice(q, idx, chosen, buttons) {
      q.setAttribute('data-answered', '1')
      q.classList.add('pw-quiz-q--answered')

      rememberChoice(q, idx, chosen, buttons)

      var isCorrect = false
      if (profileMode) {
        // No right or wrong: each answer tallies weights toward one or more profiles.
        var weights = parseWeights(chosen.getAttribute('data-weights'))
        for (var key in weights) if (Object.prototype.hasOwnProperty.call(weights, key)) scores[key] = (scores[key] || 0) + weights[key]
      } else {
        isCorrect = chosen.hasAttribute('data-correct')
        if (isCorrect) score++
        q.classList.add(isCorrect ? 'pw-quiz-q--correct' : 'pw-quiz-q--wrong')
      }

      buttons.forEach(function (btn) {
        btn.disabled = true
        btn.setAttribute('aria-disabled', 'true')
        btn.classList.remove('pw-quiz-a--selected')
        if (immediate && btn.hasAttribute('data-correct')) btn.classList.add('pw-quiz-a--correct')
      })
      q.querySelector('.pw-quiz-confirm').hidden = true
      chosen.setAttribute('aria-pressed', 'true')
      if (profileMode) chosen.classList.add('pw-quiz-a--chosen')
      else chosen.classList.add(isCorrect ? 'pw-quiz-a--chosen-correct' : 'pw-quiz-a--chosen-wrong')

      answered++
    }

    function answer(q, idx, chosen, buttons) {
      if (assessmentMode) {
        rememberChoice(q, idx, chosen, buttons)
        if (idx === total - 1) finish(false)
        else assessmentStep(idx + 1, true)
        return
      }
      applyChoice(q, idx, chosen, buttons)

      if (answered >= total) {
        finish(false)
        return
      }

      var next = questions[idx + 1]
      if (next) {
        next.removeAttribute('data-locked')
        softScroll(next)
        next.querySelector('.pw-quiz-a').focus({ preventScroll: true })
      }
    }

    // Replay a stored attempt: reveal every question at once, mark its recorded
    // answer, then show the result. finish(true) skips re-recording the attempt.
    function restore(savedAnswers) {
      if (assessmentMode) {
        questions.forEach(function (q, idx) {
          var buttons = Array.prototype.slice.call(q.querySelectorAll('.pw-quiz-a'))
          rememberChoice(q, idx, buttons[savedAnswers[idx]], buttons)
        })
        finish(true)
        return
      }
      questions.forEach(function (q, idx) {
        q.removeAttribute('data-locked')
        var buttons = Array.prototype.slice.call(q.querySelectorAll('.pw-quiz-a'))
        var chosen = buttons[savedAnswers[idx]]
        if (chosen) applyChoice(q, idx, chosen, buttons)
      })
      finish(true)
    }

    function finish(restored) {
      root.classList.add('pw-quiz--done')
      var resultBox = root.querySelector('.pw-quiz-result')
      if (resultBox) {
        resultBox.hidden = false
        if (!restored) {
          resultBox.tabIndex = -1
          resultBox.focus({ preventScroll: true })
        }
      }

      if (assessmentMode) finishAssessment(resultBox, restored)
      else if (profileMode) finishProfile(resultBox, restored)
      else finishScore(resultBox, restored)

      addRestartButton(resultBox)
      // Persist a fresh finish only — a replay is already stored (and its
      // finishScore/finishProfile skip the result POST so stats aren't inflated).
      if (!restored) storeDone(slug, chosenIdx, config.mode || 'quiz')
      var outcome = {quiz: slug, mode: config.mode || 'quiz', answers: chosenAnswers.slice(), restored: restored}
      if (assessmentMode) outcome.value = assessmentValue()
      else if (profileMode) outcome.profile = winningKey(root, scores)
      else outcome.score = Math.round((score / total) * 100)
      root.dataset.pwQuizResult = JSON.stringify(outcome)
      root.dispatchEvent(new CustomEvent('pushword:quiz:complete', {bubbles: true, detail: outcome}))
    }

    function assessmentValue() {
      return Math.min.apply(null, questions.map(function (q, i) {
        return Number(q.querySelectorAll('.pw-quiz-a')[chosenIdx[i]].getAttribute('data-value'))
      }))
    }

    function finishAssessment(resultBox, restored) {
      questions.forEach(function (q) { q.hidden = true })
      var value = assessmentValue()
      var scoreBox = root.querySelector('.pw-quiz-score')
      if (scoreBox) scoreBox.innerHTML = '<div class="pw-quiz-band">' + bandMessage(value, config.results || []) + '</div>'
      if (resultBox) {
        if (!restored) softScroll(resultBox)
        if (!resultBox.querySelector('.pw-quiz-edit')) {
          var edit = document.createElement('button')
          edit.type = 'button'
          edit.className = 'pw-quiz-edit'
          edit.textContent = (config.labels && config.labels.editAnswers) || 'Change my answers'
          edit.addEventListener('click', function () { clearDone(slug); assessmentStep(0, true) })
          resultBox.appendChild(edit)
        }
      }
      if (!restored && recordResults) submitProfileResult(slug, String(value), scoreBox, config, chosenAnswers)
      maybeShowCta(root, 'quizValue=' + encodeURIComponent(value))
    }

    function finishScore(resultBox, restored) {
      var pct = Math.round((score / total) * 100)
      var scoreBox = root.querySelector('.pw-quiz-score')
      if (scoreBox) scoreBox.innerHTML = buildScoreHtml(pct, score, total, config)
      if (resultBox && !restored) softScroll(resultBox)

      if (!restored && recordResults) submitResult(slug, pct, scoreBox, config, chosenAnswers)
      maybeShowCta(root, 'quizScore=' + pct)
      maybeOfferNextLevel(scoreBox, pct, config, levelCtx)
    }

    function finishProfile(resultBox, restored) {
      var winner = winningKey(root, scores)
      var scoreBox = root.querySelector('.pw-quiz-score')

      // Reveal the winning outcome among the server-rendered profile cards.
      var cards = root.querySelectorAll('.pw-quiz-profile')
      var card = null
      for (var i = 0; i < cards.length; i++) {
        if (cards[i].getAttribute('data-profile-key') === winner) card = cards[i]
      }
      if (!card && cards.length) card = cards[0]
      for (i = 0; i < cards.length; i++) cards[i].hidden = cards[i] !== card

      if (scoreBox && config.labels && config.labels.profile) {
        scoreBox.innerHTML = '<p class="pw-quiz-profile-intro">' + escapeHtml(config.labels.profile) + '</p>'
      }
      if (resultBox && !restored) softScroll(resultBox)

      if (!restored && recordResults) submitProfileResult(slug, winner, scoreBox, config, chosenAnswers)
      maybeShowCta(root, 'quizProfile=' + encodeURIComponent(winner || ''))
    }

    // A "restart" affordance on the result box: clear the stored attempt and reset
    // the quiz in place, back to its first-question state.
    function addRestartButton(resultBox) {
      if (!resultBox || resultBox.querySelector('.pw-quiz-restart')) return
      var labels = config.labels || {}
      var btn = document.createElement('button')
      btn.type = 'button'
      btn.className = 'pw-quiz-restart'
      btn.textContent = '↻ ' + (labels.restart || 'Restart')
      btn.addEventListener('click', restart)
      resultBox.appendChild(btn)
    }

    function restart() {
      clearDone(slug)

      answered = 0
      score = 0
      scores = {}
      chosenAnswers = []
      chosenIdx = []
      root.classList.remove('pw-quiz--done')

      var resultBox = root.querySelector('.pw-quiz-result')
      if (resultBox) resultBox.hidden = true
      var scoreBox = root.querySelector('.pw-quiz-score')
      if (scoreBox) scoreBox.innerHTML = ''
      var cards = root.querySelectorAll('.pw-quiz-profile')
      for (var c = 0; c < cards.length; c++) cards[c].hidden = true

      questions.forEach(function (q, idx) {
        q.removeAttribute('data-answered')
        q.classList.remove('pw-quiz-q--answered', 'pw-quiz-q--correct', 'pw-quiz-q--wrong')
        if (idx > 0) q.setAttribute('data-locked', '1')
        else q.removeAttribute('data-locked')
        var confirm = q.querySelector('.pw-quiz-confirm')
        confirm.hidden = false
        confirm.disabled = true

        var buttons = Array.prototype.slice.call(q.querySelectorAll('.pw-quiz-a'))
        buttons.forEach(function (btn) {
          btn.disabled = false
          btn.removeAttribute('aria-disabled')
          btn.setAttribute('aria-pressed', 'false')
          btn.classList.remove(
            'pw-quiz-a--selected',
            'pw-quiz-a--correct',
            'pw-quiz-a--chosen',
            'pw-quiz-a--chosen-correct',
            'pw-quiz-a--chosen-wrong',
          )
        })
      })

      delete root.dataset.pwQuizResult
      if (assessmentMode) assessmentStep(0, true)
      else softScroll(root)
    }
  }

  // The profile with the highest tally wins; ties break by declaration order (the
  // order the cards appear in), so iterate the cards rather than the scores map.
  function winningKey(root, scores) {
    var cards = root.querySelectorAll('.pw-quiz-profile')
    var best = null
    var bestVal = -Infinity
    for (var i = 0; i < cards.length; i++) {
      var key = cards[i].getAttribute('data-profile-key')
      var val = scores[key] || 0
      if (val > bestVal) {
        bestVal = val
        best = key
      }
    }
    return best
  }

  function parseWeights(raw) {
    if (!raw) return {}
    try {
      var w = JSON.parse(raw)
      return w && 'object' === typeof w ? w : {}
    } catch (e) {
      return {}
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
    // `band` is server-rendered Markdown→HTML (trusted, same source as page
    // content), so it is injected as markup rather than escaped.
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
      (band ? '<div class="pw-quiz-band">' + band + '</div>' : '') +
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

  function submitResult(slug, pct, scoreBox, config, answers) {
    if (!slug || !scoreBox || !window.fetch) return
    var line = scoreBox.querySelector('.pw-quiz-percentile')

    // No Content-Type header: keeps this a CORS "simple request" (no preflight),
    // so a statically served page can POST cross-origin to the live host, which
    // reads the raw JSON body regardless.
    fetch(resultEndpoint(config), {
      method: 'POST',
      body: JSON.stringify({ quiz: slug, score: pct, answers: answers || [], signature: config.resultSignature }),
    })
      .then(function (r) {
        return r.ok ? r.json() : null
      })
      .then(function (data) {
        // Skip "better than 0%": it is meaningless and reads as a put-down.
        if (false === config.showComparison || !data || 'number' !== typeof data.percentile || data.percentile <= 0 || !line) return
        var tpl = (config.labels && config.labels.better) || 'Better than {p}% of participants'
        line.textContent = tpl.replace('{p}', data.percentile)
        line.hidden = false
      })
      .catch(function () {})
  }

  // Personality mode: record the chosen profile and show how common it is.
  function submitProfileResult(slug, result, scoreBox, config, answers) {
    if (!slug || !result || !scoreBox || !window.fetch) return

    fetch(resultEndpoint(config), {
      method: 'POST',
      body: JSON.stringify({ quiz: slug, mode: config.mode, result: result, answers: answers || [], signature: config.resultSignature }),
    })
      .then(function (r) {
        return r.ok ? r.json() : null
      })
      .then(function (data) {
        if (false === config.showComparison || !data || 'number' !== typeof data.share || data.share <= 0) return
        var tpl = (config.labels && config.labels.share) || '{p}% got the same profile'
        var line = document.createElement('p')
        line.className = 'pw-quiz-share'
        line.textContent = tpl.replace('{p}', data.share)
        scoreBox.appendChild(line)
      })
      .catch(function () {})
  }

  /* ----- conversion form (pushword/conversation), deferred to the end ----- */

  function maybeShowCta(root, query) {
    var cta = root.querySelector('.pw-quiz-cta')
    if (!cta) return

    // The form replaces this inner holder (js-helper swaps the element's
    // outerHTML), so the CTA title sitting next to it survives.
    var holder = cta.querySelector('[data-quiz-cta]')
    if (!holder) return

    var url = holder.getAttribute('data-quiz-cta')
    if (!url) return

    // Carry the attempt's outcome along so the lead can be attributed to it.
    url += (url.indexOf('?') === -1 ? '?' : '&') + query

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

  /* ----- finished-attempt storage (per quiz slug) ----- */

  function doneKey(slug) {
    return DONE_PREFIX + slug
  }

  function readDone(slug) {
    if (!slug) return null
    try {
      return JSON.parse(window.localStorage.getItem(doneKey(slug))) || null
    } catch (e) {
      return null
    }
  }

  function storeDone(slug, answers, mode) {
    if (!slug) return
    try {
      window.localStorage.setItem(doneKey(slug), JSON.stringify({ v: 1, mode: mode, a: answers }))
    } catch (e) {}
  }

  function clearDone(slug) {
    if (!slug) return
    try {
      window.localStorage.removeItem(doneKey(slug))
    } catch (e) {}
  }

  /* ----- helpers ----- */

  // Absolute live-host endpoint when the page is served statically; falls back
  // to the same-origin relative path when the config omits it.
  function resultEndpoint(config) {
    return (config && config.resultEndpoint) || RESULT_ENDPOINT
  }

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
