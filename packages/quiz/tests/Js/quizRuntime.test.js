import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const runtimeSrc = readFileSync(join(here, '../../src/Resources/public/quiz.js'), 'utf8')

// quiz.js is a self-invoking IIFE that reads the global `document`. Eval it in
// the jsdom global scope, then make sure its init has run (jsdom may still be
// "loading", in which case the runtime defers to DOMContentLoaded).
function bootRuntime() {
  ;(0, eval)(runtimeSrc)
  document.dispatchEvent(new Event('DOMContentLoaded'))
}

function answersHtml() {
  return (
    '<ul class="pw-quiz-answers">' +
    '<li><button type="button" class="pw-quiz-a" data-correct aria-pressed="false">' +
    '<span class="pw-quiz-a-text">Right</span><span class="pw-quiz-a-mark"></span></button></li>' +
    '<li><button type="button" class="pw-quiz-a" aria-pressed="false">' +
    '<span class="pw-quiz-a-text">Wrong</span><span class="pw-quiz-a-mark"></span></button></li>' +
    '</ul>'
  )
}

function questionHtml(idx) {
  return (
    '<li class="pw-quiz-q" data-q="' +
    idx +
    '">' +
    '<p class="pw-quiz-question">Q' +
    idx +
    '</p>' +
    answersHtml() +
    '</li>'
  )
}

const configHtml =
  '<script type="application/json" class="pw-quiz-config">' +
  '{"feedback":"immediate","pass":50,"results":[]}</script>'

// A single, levelless quiz: [data-pw-quiz] sits on the .pw-quiz section itself.
function singleQuiz() {
  return (
    '<section class="pw-quiz" id="q1" data-pw-quiz data-slug="single">' +
    '<ol class="pw-quiz-questions">' +
    questionHtml(0) +
    questionHtml(1) +
    '</ol>' +
    '<div class="pw-quiz-result" hidden><div class="pw-quiz-score"></div></div>' +
    configHtml +
    '</section>'
  )
}

// A leveled quiz: [data-pw-quiz] sits on each inner .pw-quiz-level, NOT on the
// outer .pw-quiz--levels section — the structure that triggered the bug.
function leveledQuiz() {
  return (
    '<section class="pw-quiz pw-quiz--levels" id="q2">' +
    '<div class="pw-quiz-tabs" role="tablist">' +
    '<button type="button" class="pw-quiz-tab" role="tab" id="q2-tab-0" aria-selected="true" tabindex="0">Easy</button>' +
    '</div>' +
    '<div class="pw-quiz-panel" role="tabpanel" id="q2-panel-0" aria-labelledby="q2-tab-0">' +
    '<div class="pw-quiz-level" data-pw-quiz data-slug="q2.0" data-next-tab="">' +
    '<ol class="pw-quiz-questions">' +
    questionHtml(0) +
    questionHtml(1) +
    '</ol>' +
    '<div class="pw-quiz-result" hidden><div class="pw-quiz-score"></div></div>' +
    configHtml +
    '</div></div>' +
    '</section>'
  )
}

beforeEach(() => {
  document.body.innerHTML = ''
  window.localStorage.clear()
})

describe('quiz runtime — leveled quiz (regression)', () => {
  it('flips the no-JS reveal gate on the outer .pw-quiz section', () => {
    document.body.innerHTML = leveledQuiz()
    const section = document.querySelector('.pw-quiz--levels')

    // No-JS view: the gate matches, so the correct answer is revealed (crawler/SEO).
    expect(section.matches('.pw-quiz:not(.pw-quiz--js)')).toBe(true)

    bootRuntime()

    // The fix: the section itself gains pw-quiz--js, so the reveal CSS no longer
    // matches and the correct answer is NOT pre-highlighted before answering.
    expect(section.classList.contains('pw-quiz--js')).toBe(true)
    expect(section.matches('.pw-quiz:not(.pw-quiz--js)')).toBe(false)

    const correct = section.querySelector('.pw-quiz-a[data-correct]')
    expect(correct.classList.contains('pw-quiz-a--correct')).toBe(false)
  })

  it('still enhances the level interactively (one question at a time)', () => {
    document.body.innerHTML = leveledQuiz()
    bootRuntime()

    const level = document.querySelector('.pw-quiz-level')
    expect(level.getAttribute('data-pw-quiz-ready')).toBe('1')
    expect(level.classList.contains('pw-quiz--js')).toBe(true)

    const questions = level.querySelectorAll('.pw-quiz-q')
    expect(questions[0].hasAttribute('data-locked')).toBe(false)
    expect(questions[1].hasAttribute('data-locked')).toBe(true)
  })
})

describe('quiz runtime — single quiz (unchanged)', () => {
  it('marks the .pw-quiz section as JS-enhanced', () => {
    document.body.innerHTML = singleQuiz()
    const section = document.querySelector('.pw-quiz')
    expect(section.matches('.pw-quiz:not(.pw-quiz--js)')).toBe(true)

    bootRuntime()

    expect(section.classList.contains('pw-quiz--js')).toBe(true)
    expect(section.matches('.pw-quiz:not(.pw-quiz--js)')).toBe(false)
  })
})

// A personality test: answers carry weights (no data-correct); every outcome is
// server-rendered and hidden, and the runtime reveals the highest-tallied one.
function profileAnswer(weights, label) {
  return (
    '<li><button type="button" class="pw-quiz-a" data-weights=\'' +
    JSON.stringify(weights) +
    '\' aria-pressed="false">' +
    '<span class="pw-quiz-a-text">' +
    label +
    '</span><span class="pw-quiz-a-mark"></span></button></li>'
  )
}

function profileQuestion(idx, aWeights, bWeights) {
  return (
    '<li class="pw-quiz-q" data-q="' +
    idx +
    '"><p class="pw-quiz-question">Q' +
    idx +
    '</p><ul class="pw-quiz-answers">' +
    profileAnswer(aWeights, 'A') +
    profileAnswer(bWeights, 'B') +
    '</ul></li>'
  )
}

function profileQuiz() {
  return (
    '<section class="pw-quiz pw-quiz--profile" id="qp" data-pw-quiz data-slug="perso">' +
    '<ol class="pw-quiz-questions">' +
    profileQuestion(0, { explorer: 2 }, { builder: 2 }) +
    profileQuestion(1, { explorer: 1 }, { builder: 1 }) +
    '</ol>' +
    '<div class="pw-quiz-result" hidden><div class="pw-quiz-score"></div>' +
    '<div class="pw-quiz-profiles">' +
    '<div class="pw-quiz-profile" data-profile-key="explorer" hidden><h3>Explorer</h3></div>' +
    '<div class="pw-quiz-profile" data-profile-key="builder" hidden><h3>Builder</h3></div>' +
    '</div></div>' +
    '<script type="application/json" class="pw-quiz-config">{"mode":"profile","feedback":"end","labels":{"profile":"Your profile:"}}</script>' +
    '</section>'
  )
}

describe('quiz runtime — personality test (profile mode)', () => {
  it('reveals the highest-tallied profile and never flags a correct answer', () => {
    document.body.innerHTML = profileQuiz()
    bootRuntime()

    const answers = document.querySelectorAll('.pw-quiz-a')
    // Pick the "explorer" answer on both questions (indices 0 and 2).
    answers[0].click()
    answers[2].click()

    const cards = document.querySelectorAll('.pw-quiz-profile')
    const explorer = document.querySelector('.pw-quiz-profile[data-profile-key="explorer"]')
    const builder = document.querySelector('.pw-quiz-profile[data-profile-key="builder"]')
    expect(explorer.hidden).toBe(false)
    expect(builder.hidden).toBe(true)

    // No right/wrong semantics: the chosen answer is marked neutrally.
    expect(answers[0].classList.contains('pw-quiz-a--chosen')).toBe(true)
    expect(answers[0].classList.contains('pw-quiz-a--chosen-correct')).toBe(false)
    expect(cards.length).toBe(2)
  })

  it('breaks a tie by profile declaration order', () => {
    // One question whose only answer weighs both profiles equally → a tie, which
    // must resolve to the first-declared card (explorer), not builder.
    document.body.innerHTML =
      '<section class="pw-quiz pw-quiz--profile" id="qt" data-pw-quiz data-slug="tie">' +
      '<ol class="pw-quiz-questions">' +
      '<li class="pw-quiz-q" data-q="0"><ul class="pw-quiz-answers">' +
      profileAnswer({ explorer: 1, builder: 1 }, 'Both') +
      '</ul></li>' +
      '</ol>' +
      '<div class="pw-quiz-result" hidden><div class="pw-quiz-score"></div>' +
      '<div class="pw-quiz-profiles">' +
      '<div class="pw-quiz-profile" data-profile-key="explorer" hidden><h3>Explorer</h3></div>' +
      '<div class="pw-quiz-profile" data-profile-key="builder" hidden><h3>Builder</h3></div>' +
      '</div></div>' +
      '<script type="application/json" class="pw-quiz-config">{"mode":"profile","feedback":"end"}</script>' +
      '</section>'
    bootRuntime()

    document.querySelector('.pw-quiz-a').click()

    expect(document.querySelector('.pw-quiz-profile[data-profile-key="explorer"]').hidden).toBe(false)
    expect(document.querySelector('.pw-quiz-profile[data-profile-key="builder"]').hidden).toBe(true)
  })
})

// The server renders each score band's Markdown message to HTML; the runtime
// injects it verbatim into the score box (no client-side escaping).
function bandedQuiz(bandMsg) {
  return (
    '<section class="pw-quiz" id="qb" data-pw-quiz data-slug="banded">' +
    '<ol class="pw-quiz-questions">' +
    questionHtml(0) +
    questionHtml(1) +
    '</ol>' +
    '<div class="pw-quiz-result" hidden><div class="pw-quiz-score"></div></div>' +
    '<script type="application/json" class="pw-quiz-config">' +
    JSON.stringify({ feedback: 'immediate', pass: 50, results: [{ min: 0, msg: bandMsg }] }) +
    '</script>' +
    '</section>'
  )
}

describe('quiz runtime — score band (Markdown)', () => {
  it('injects the pre-rendered band HTML as markup, not escaped text', () => {
    document.body.innerHTML = bandedQuiz('<strong>Nice</strong> — <a href="https://example.com">more</a>')
    bootRuntime()

    const questions = document.querySelectorAll('.pw-quiz-q')
    // Answer both questions to finish the quiz and render the score box.
    questions[0].querySelector('.pw-quiz-a').click()
    questions[1].querySelector('.pw-quiz-a').click()

    const band = document.querySelector('.pw-quiz-band')
    expect(band).not.toBeNull()
    // Real elements land in the DOM (Markdown was resolved server-side)…
    expect(band.querySelector('strong')).not.toBeNull()
    expect(band.querySelector('a[href="https://example.com"]')).not.toBeNull()
    // …and the raw tags are not shown as literal text.
    expect(band.textContent).not.toContain('<strong>')
  })
})

// Stub window.fetch, recording every call; the caller restores it afterwards.
function mockFetch() {
  const calls = []
  window.fetch = (url, opts) => {
    calls.push({ url: url, opts: opts })
    return Promise.resolve({ ok: true, json: () => Promise.resolve({ percentile: 0 }) })
  }
  return calls
}

// A one-question quiz that finishes on the first click, carrying `config`.
function resultQuiz(id, slug, config) {
  return (
    '<section class="pw-quiz" id="' +
    id +
    '" data-pw-quiz data-slug="' +
    slug +
    '"><ol class="pw-quiz-questions">' +
    questionHtml(0) +
    '</ol><div class="pw-quiz-result" hidden><div class="pw-quiz-score"></div></div>' +
    '<script type="application/json" class="pw-quiz-config">' +
    JSON.stringify(config) +
    '</script></section>'
  )
}

describe('quiz runtime — finished-attempt persistence', () => {
  it('replays a completed quiz on the next load: chosen answers, result, restart button', () => {
    document.body.innerHTML = singleQuiz()
    bootRuntime()

    const q = document.querySelectorAll('.pw-quiz-q')
    // Q0 answered correctly (first button), Q1 answered wrong (second button).
    q[0].querySelectorAll('.pw-quiz-a')[0].click()
    q[1].querySelectorAll('.pw-quiz-a')[1].click()

    // The finished attempt is stored under the quiz slug.
    expect(window.localStorage.getItem('pwQuizDone:single')).not.toBeNull()

    // Re-render the same quiz from scratch (a page reload) and boot again.
    document.body.innerHTML = singleQuiz()
    bootRuntime()

    const r = document.querySelectorAll('.pw-quiz-q')
    // Both questions come back marked answered with the same choices.
    expect(r[0].classList.contains('pw-quiz-q--answered')).toBe(true)
    expect(r[1].classList.contains('pw-quiz-q--answered')).toBe(true)
    expect(r[0].querySelectorAll('.pw-quiz-a')[0].classList.contains('pw-quiz-a--chosen-correct')).toBe(true)
    expect(r[1].querySelectorAll('.pw-quiz-a')[1].classList.contains('pw-quiz-a--chosen-wrong')).toBe(true)

    // The result box is revealed and a restart button is offered.
    expect(document.querySelector('.pw-quiz-result').hidden).toBe(false)
    expect(document.querySelector('.pw-quiz-restart')).not.toBeNull()
  })

  it('replays a completed personality test, revealing the same winning profile', () => {
    document.body.innerHTML = profileQuiz()
    bootRuntime()

    const answers = document.querySelectorAll('.pw-quiz-a')
    answers[0].click() // explorer on Q0
    answers[2].click() // explorer on Q1
    expect(window.localStorage.getItem('pwQuizDone:perso')).not.toBeNull()

    // Reload: the winning card comes back revealed, the rest hidden.
    document.body.innerHTML = profileQuiz()
    bootRuntime()

    expect(document.querySelector('.pw-quiz-profile[data-profile-key="explorer"]').hidden).toBe(false)
    expect(document.querySelector('.pw-quiz-profile[data-profile-key="builder"]').hidden).toBe(true)
    expect(document.querySelector('.pw-quiz-restart')).not.toBeNull()
    expect(Array.from(document.querySelectorAll('.pw-quiz-a')).every((b) => b.disabled)).toBe(true)
  })

  it('discards a stored attempt whose answer count no longer matches the quiz', () => {
    window.localStorage.setItem('pwQuizDone:single', JSON.stringify({ v: 1, a: [0] }))
    document.body.innerHTML = singleQuiz() // two questions, stored attempt has one
    bootRuntime()

    // Stale record ignored: the quiz starts fresh (first question live, rest locked).
    const q = document.querySelectorAll('.pw-quiz-q')
    expect(q[0].classList.contains('pw-quiz-q--answered')).toBe(false)
    expect(q[1].hasAttribute('data-locked')).toBe(true)
    expect(document.querySelector('.pw-quiz-result').hidden).toBe(true)
  })

  it('restart clears the stored attempt and resets the quiz to its first question', () => {
    document.body.innerHTML = singleQuiz()
    bootRuntime()
    const q = document.querySelectorAll('.pw-quiz-q')
    q[0].querySelectorAll('.pw-quiz-a')[0].click()
    q[1].querySelectorAll('.pw-quiz-a')[1].click()

    // Reload → restored state, then hit restart.
    document.body.innerHTML = singleQuiz()
    bootRuntime()
    document.querySelector('.pw-quiz-restart').click()

    expect(window.localStorage.getItem('pwQuizDone:single')).toBeNull()

    const r = document.querySelectorAll('.pw-quiz-q')
    expect(r[0].classList.contains('pw-quiz-q--answered')).toBe(false)
    expect(r[0].hasAttribute('data-locked')).toBe(false)
    expect(r[1].hasAttribute('data-locked')).toBe(true)
    expect(document.querySelector('.pw-quiz-result').hidden).toBe(true)
    const buttons = r[0].querySelectorAll('.pw-quiz-a')
    expect(buttons[0].disabled).toBe(false)
    expect(buttons[0].getAttribute('aria-pressed')).toBe('false')
  })
})

describe('quiz runtime — result submission endpoint', () => {
  const realFetch = window.fetch
  afterEach(() => {
    window.fetch = realFetch
  })

  it('posts to the absolute live-host endpoint from config as a simple request', () => {
    const calls = mockFetch()
    document.body.innerHTML = resultQuiz('qe', 'endpoint', {
      feedback: 'immediate',
      resultEndpoint: 'https://live.example/quiz/result',
    })
    bootRuntime()

    // Answering the only question finishes the quiz and submits the result.
    document.querySelector('.pw-quiz-a[data-correct]').click()

    expect(calls.length).toBe(1)
    // Absolute URL to the live host, so a PHP-less static origin still reaches it.
    expect(calls[0].url).toBe('https://live.example/quiz/result')
    // No headers → CORS "simple request", no preflight the static host can't answer.
    expect(calls[0].opts.headers).toBeUndefined()
  })

  it('falls back to the relative endpoint when config omits it (pre-change static HTML)', () => {
    const calls = mockFetch()
    document.body.innerHTML = resultQuiz('qf', 'legacy', { feedback: 'immediate' })
    bootRuntime()

    document.querySelector('.pw-quiz-a[data-correct]').click()

    expect(calls.length).toBe(1)
    expect(calls[0].url).toBe('/quiz/result')
  })

  it('does not re-post the result when a finished attempt is replayed on reload', () => {
    const calls = mockFetch()
    const html = () =>
      resultQuiz('qr', 'restore-endpoint', {
        feedback: 'immediate',
        resultEndpoint: 'https://live.example/quiz/result',
      })

    document.body.innerHTML = html()
    bootRuntime()
    document.querySelector('.pw-quiz-a[data-correct]').click()
    expect(calls.length).toBe(1) // the live finish records the attempt once

    // Reload: the stored attempt is replayed, but the stat must not be recorded again.
    document.body.innerHTML = html()
    bootRuntime()
    expect(document.querySelector('.pw-quiz-restart')).not.toBeNull() // proof it restored
    expect(calls.length).toBe(1) // no second POST
  })
})
