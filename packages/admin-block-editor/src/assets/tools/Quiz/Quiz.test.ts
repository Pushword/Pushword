import { describe, it, expect } from 'vitest'
import Quiz, { QuizData } from './Quiz'
import { API } from '@editorjs/editorjs'

/**
 * Round-trip tests for the difficulty-levels mode: the editor must render N full
 * sub-quizzes, save them back as a `levels` array (with no root questions), and
 * export/import them through the `{{ quiz('…') }}` block without drift. A quiz
 * without levels must keep behaving as a single quiz.
 */

const api = { notifier: { show: () => {} }, i18n: { t: (k: string) => k } } as unknown as API

function mounted(data: QuizData): Quiz {
  const tool = new Quiz({ data, api, readOnly: false })
  tool.render() // build the DOM so save() can read it back
  return tool
}

function parseBlock(markdown: string): QuizData {
  const match = markdown.match(/\{\{\s*quiz\(\s*'((?:\\.|[^'\\])*)'\s*\)\s*\}\}/)
  if (!match) throw new Error('not a quiz block: ' + markdown)
  return JSON.parse(match[1].replace(/\\(['\\])/g, '$1'))
}

const leveled: QuizData = {
  title: 'Mountains',
  feedback: 'immediate',
  cta: 'newsletter',
  levels: [
    { difficulty: 'Easy', pass: 50, questions: [{ q: 'Q1', answers: [{ a: 'A', correct: true }, { a: 'B' }] }] },
    { difficulty: 'Hard', questions: [{ q: 'Q2', answers: [{ a: 'C', correct: true }, { a: 'D' }] }] },
  ],
}

describe('Quiz difficulty levels', () => {
  it('exports levels to a quiz block and drops the root questions', () => {
    const json = parseBlock(Quiz.exportToMarkdown(leveled))

    expect(json.title).toBe('Mountains')
    expect(json.cta).toBe('newsletter')
    expect(json.questions).toBeUndefined()
    expect(json.levels).toHaveLength(2)
    expect(json.levels![0].difficulty).toBe('Easy')
    expect(json.levels![0].pass).toBe(50)
    expect(json.levels![0].questions).toHaveLength(1)
  })

  it('renders in levels mode and saves the levels back', () => {
    const saved = mounted(leveled).save()

    expect(saved.levels).toHaveLength(2)
    expect(saved.levels![0].difficulty).toBe('Easy')
    expect(saved.levels![0].pass).toBe(50)
    expect(saved.levels![0].questions![0].answers.find((a) => a.correct)?.a).toBe('A')
    expect(saved.levels![1].difficulty).toBe('Hard')
    // The root keeps no questions of its own once levels carry them.
    expect(saved.questions).toEqual([])
  })

  it('stays a single quiz when no levels are declared', () => {
    const single: QuizData = { questions: [{ q: 'Solo', answers: [{ a: 'X', correct: true }, { a: 'Y' }] }] }
    const saved = mounted(single).save()

    expect(saved.questions).toHaveLength(1)
    expect(saved.levels).toBeUndefined()
  })

  it('round-trips export → parse → export without drift', () => {
    const markdown = Quiz.exportToMarkdown(leveled)
    expect(Quiz.exportToMarkdown(parseBlock(markdown))).toBe(markdown)
  })
})
