import { describe, it, expect, beforeEach } from 'vitest'
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
