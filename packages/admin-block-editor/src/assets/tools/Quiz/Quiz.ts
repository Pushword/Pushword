import './Quiz.css'
import make from '../utils/make'
import ToolboxIcon from './toolbox-icon.svg?raw'
import SelectIcon from '../Abstract/icon/folder.svg?raw'
import UploadIcon from '../Abstract/icon/upload.svg?raw'
import { MediaUtils } from '../utils/media'
import { API, BlockToolData } from '@editorjs/editorjs'
import { BaseTool } from '../Abstract/BaseTool'

interface QuizAnswer {
  a?: string
  correct?: boolean
  media?: string
  alt?: string
}

interface QuizQuestion {
  q?: string
  media?: string
  video?: string
  alt?: string
  explanation?: string
  answers?: QuizAnswer[]
}

interface QuizResultBand {
  min?: number
  msg?: string
}

export interface QuizData extends BlockToolData {
  title?: string
  difficulty?: string
  feedback?: string
  cta?: string
  ctaTitle?: string
  numbering?: string
  labels?: Record<string, string>
  results?: QuizResultBand[]
  questions?: QuizQuestion[]
}

const LABEL_DEFAULTS: Record<string, string> = {
  question: 'Question',
  questions: 'questions',
  explanation: 'Explanation',
  score: 'Your score:',
  better: 'Better than {p}% of participants',
}

/**
 * Editor for the `{{ quiz('…json…') }}` block: add/remove questions and answers,
 * flag the correct answer(s), attach an image or video, write the explanation.
 * Round-trips to/from a single-quoted Twig-string JSON payload.
 */
export default class Quiz extends BaseTool {
  declare public data: QuizData
  private wrapper!: HTMLElement
  private mediaPickerMessageHandler: ((event: MessageEvent) => void) | null = null

  public static toolbox = {
    title: 'Quiz',
    icon: ToolboxIcon,
  }

  constructor({ data, api, readOnly }: { data: QuizData; api: API; readOnly: boolean }) {
    super({ data, api, readOnly })

    const questions =
      Array.isArray(data.questions) && data.questions.length > 0
        ? data.questions
        : [{ q: '', answers: [{ a: '', correct: true }, { a: '' }] }]

    this.data = {
      title: data.title || '',
      difficulty: data.difficulty || '',
      feedback: data.feedback || 'immediate',
      cta: data.cta || '',
      ctaTitle: data.ctaTitle || '',
      numbering: data.numbering || '',
      labels: data.labels || {},
      results: Array.isArray(data.results) ? data.results : [],
      questions,
    }
  }

  public render(): HTMLElement {
    this.wrapper = make.element('div', 'cdx-quiz')
    this.wrapper.appendChild(
      make.element('div', 'cdx-quiz__header', {}, ToolboxIcon + '<span>Quiz</span>'),
    )

    const meta = make.element('div', 'cdx-quiz__meta')
    meta.appendChild(this.inputEl('cdx-quiz__title', 'Title', this.data.title || ''))
    meta.appendChild(this.inputEl('cdx-quiz__difficulty', 'Difficulty', this.data.difficulty || ''))
    const feedback = make.element('select', ['cdx-quiz__feedback']) as HTMLSelectElement
    make.option(feedback, 'immediate', 'immediate')
    make.option(feedback, 'end', 'end')
    feedback.value = this.data.feedback || 'immediate'
    meta.appendChild(feedback)
    const numbering = make.element('select', ['cdx-quiz__numbering']) as HTMLSelectElement
    make.option(numbering, '', 'No numbering')
    make.option(numbering, 'A', 'A, B, C…')
    make.option(numbering, 'a', 'a, b, c…')
    make.option(numbering, '1', '1, 2, 3…')
    numbering.value = this.data.numbering || ''
    meta.appendChild(numbering)
    meta.appendChild(this.inputEl('cdx-quiz__cta', 'End form (conversation type, optional)', this.data.cta || ''))
    this.wrapper.appendChild(meta)
    this.wrapper.appendChild(
      this.inputEl('cdx-quiz__cta-title', 'End form heading (call to action, optional)', this.data.ctaTitle || ''),
    )

    const questions = make.element('div', 'cdx-quiz__questions')
    ;(this.data.questions || []).forEach((q) => questions.appendChild(this.buildQuestion(q)))
    this.wrapper.appendChild(questions)
    this.wrapper.appendChild(
      make.element('button', 'cdx-quiz__add', { type: 'button' }, '+ Question', () => {
        questions.appendChild(
          this.buildQuestion({ q: '', answers: [{ a: '', correct: true }, { a: '' }] }),
        )
      }),
    )

    this.wrapper.appendChild(
      make.element('div', 'cdx-quiz__subtitle', {}, 'Score bands (optional)'),
    )
    const results = make.element('div', 'cdx-quiz__results')
    ;(this.data.results || []).forEach((r) => results.appendChild(this.buildResult(r)))
    this.wrapper.appendChild(results)
    this.wrapper.appendChild(
      make.element('button', 'cdx-quiz__add', { type: 'button' }, '+ Band', () => {
        results.appendChild(this.buildResult({ min: 0, msg: '' }))
      }),
    )

    // Author-defined UI words (no i18n). Empty = English default (the placeholder).
    this.wrapper.appendChild(
      make.element('div', 'cdx-quiz__subtitle', {}, 'Labels (optional — override the default words)'),
    )
    const labels = make.element('div', 'cdx-quiz__labels')
    Object.keys(LABEL_DEFAULTS).forEach((key) => {
      const input = this.inputEl('cdx-quiz__label', LABEL_DEFAULTS[key], (this.data.labels || {})[key] || '')
      input.dataset.label = key
      labels.appendChild(input)
    })
    this.wrapper.appendChild(labels)

    return this.wrapper
  }

  private buildQuestion(q: QuizQuestion): HTMLElement {
    const el = make.element('div', 'cdx-quiz__q')

    const head = make.element('div', 'cdx-quiz__q-head')
    head.appendChild(make.element('span', null, {}, 'Question'))
    head.appendChild(
      make.element('button', 'cdx-quiz__del', { type: 'button', title: 'Remove question' }, '✕', () =>
        el.remove(),
      ),
    )
    el.appendChild(head)

    el.appendChild(this.textareaEl('cdx-quiz__q-text', 'Question text', q.q || ''))

    el.appendChild(
      this.buildMediaField('cdx-quiz__q-media', 'Image filename — also the video poster', q.media || ''),
    )

    const row = make.element('div', 'cdx-quiz__row')
    row.appendChild(this.inputEl('cdx-quiz__q-video', 'Video URL (optional)', q.video || ''))
    row.appendChild(this.inputEl('cdx-quiz__q-alt', 'Media alt text (required for video)', q.alt || ''))
    el.appendChild(row)

    const answers = make.element('div', 'cdx-quiz__answers')
    ;(q.answers || []).forEach((a) => answers.appendChild(this.buildAnswer(a)))
    el.appendChild(answers)
    el.appendChild(
      make.element('button', 'cdx-quiz__add-a', { type: 'button' }, '+ Answer', () => {
        answers.appendChild(this.buildAnswer({ a: '' }))
      }),
    )

    el.appendChild(
      this.textareaEl('cdx-quiz__q-explanation', 'Explanation (shown after answering)', q.explanation || ''),
    )

    return el
  }

  private buildAnswer(a: QuizAnswer): HTMLElement {
    const el = make.element('div', 'cdx-quiz__a')

    const main = make.element('div', 'cdx-quiz__a-main')
    const correct = make.element('input', 'cdx-quiz__a-correct', {
      type: 'checkbox',
      title: 'Mark as a correct answer',
    }) as HTMLInputElement
    correct.checked = !!a.correct
    main.appendChild(correct)
    main.appendChild(this.inputEl('cdx-quiz__a-text', 'Answer', a.a || ''))
    main.appendChild(
      make.element('button', 'cdx-quiz__del', { type: 'button', title: 'Remove answer' }, '✕', () =>
        el.remove(),
      ),
    )
    el.appendChild(main)

    el.appendChild(this.buildMediaField('cdx-quiz__a-media', 'Answer image (optional)', a.media || ''))

    return el
  }

  private buildResult(r: QuizResultBand): HTMLElement {
    const el = make.element('div', 'cdx-quiz__result-row')
    const min = make.element('input', ['cdx-quiz__input', 'cdx-quiz__result-min'], {
      type: 'number',
      min: '0',
      max: '100',
      placeholder: 'min %',
    }) as HTMLInputElement
    min.value = String(r.min ?? 0)
    el.appendChild(min)
    el.appendChild(this.inputEl('cdx-quiz__result-msg', 'Message', r.msg || ''))
    el.appendChild(
      make.element('button', 'cdx-quiz__del', { type: 'button' }, '✕', () => el.remove()),
    )
    return el
  }

  private inputEl(cls: string, placeholder: string, value: string): HTMLInputElement {
    const input = make.element('input', ['cdx-quiz__input', cls], {
      type: 'text',
      placeholder,
    }) as HTMLInputElement
    input.value = value
    return input
  }

  private textareaEl(cls: string, placeholder: string, value: string): HTMLTextAreaElement {
    const textarea = make.element('textarea', ['cdx-quiz__textarea', cls], {
      placeholder,
    }) as HTMLTextAreaElement
    textarea.value = value
    return textarea
  }

  /**
   * A filename input paired with media-library "Select" and "Upload" buttons and
   * a thumbnail preview. The input keeps `cls` so save() still reads it. Reused
   * for the question image (which doubles as the video poster) and answer images.
   */
  private buildMediaField(cls: string, placeholder: string, value: string): HTMLElement {
    const field = make.element('div', 'cdx-quiz__media')

    const thumb = make.element('img', 'cdx-quiz__media-thumb') as HTMLImageElement
    const syncThumb = (current: string): void => {
      if (current) {
        thumb.src = MediaUtils.buildFullUrl(current)
        thumb.style.display = 'block'
      } else {
        thumb.removeAttribute('src')
        thumb.style.display = 'none'
      }
    }

    const input = this.inputEl(cls, placeholder, value)
    input.classList.add('cdx-quiz__media-input')
    input.addEventListener('input', () => syncThumb(input.value.trim()))

    const select = make.element(
      'button',
      'cdx-quiz__media-btn',
      { type: 'button', title: 'Browse the media library' },
      SelectIcon,
      () => this.openMediaPicker(input, syncThumb),
    )
    const upload = make.element(
      'button',
      'cdx-quiz__media-btn',
      { type: 'button', title: 'Upload a file' },
      UploadIcon,
      () => this.uploadMedia(input, syncThumb),
    )

    field.appendChild(thumb)
    field.appendChild(input)
    field.appendChild(select)
    field.appendChild(upload)
    syncThumb(value)

    return field
  }

  /** Open the shared admin media picker modal and write the chosen filename into `input`. */
  private openMediaPicker(input: HTMLInputElement, onSet: (value: string) => void): void {
    const picker = document.querySelector('select[id*="inline_image"]') as HTMLSelectElement | null
    const wrapper = picker ? (picker.closest('.pw-media-picker') as HTMLElement | null) : null
    const chooseButton = wrapper
      ? (wrapper.querySelector('[data-pw-media-picker-action="choose"]') as HTMLButtonElement | null)
      : null

    if (!picker || !chooseButton) {
      this.api.notifier.show({ message: 'Media picker not available', style: 'error' })
      return
    }

    this.cleanupMediaPickerHandler()
    this.mediaPickerMessageHandler = (event: MessageEvent): void => {
      if (event.origin !== window.location.origin) return
      const payload = event.data
      if (!payload || payload.type !== 'pw-media-picker-select' || payload.fieldId !== picker.id) return

      this.cleanupMediaPickerHandler()
      const media = payload.media
      if (!media) return

      const mediaName = media.fileName || String(media.id)
      input.value = mediaName
      onSet(mediaName)
    }

    window.addEventListener('message', this.mediaPickerMessageHandler)
    chooseButton.click()
  }

  /** Upload a local file through the shared media endpoint, then write its filename into `input`. */
  private uploadMedia(input: HTMLInputElement, onSet: (value: string) => void): void {
    const file = make.element('input', null, { type: 'file', accept: 'image/*' }) as HTMLInputElement
    file.style.display = 'none'
    file.addEventListener('change', async () => {
      const chosen = file.files?.[0]
      if (chosen) {
        const formData = new FormData()
        formData.append('image', chosen)
        try {
          const response = await fetch('/admin/media/block', { method: 'POST', body: formData })
          if (!response.ok) throw new Error(`HTTP ${response.status}`)
          const data = await response.json()
          const mediaName: string | undefined = data?.file?.media
          if (mediaName) {
            input.value = mediaName
            onSet(mediaName)
          }
        } catch {
          this.api.notifier.show({ message: 'Upload failed', style: 'error' })
        }
      }
      file.remove()
    })
    document.body.appendChild(file)
    file.click()
  }

  private cleanupMediaPickerHandler(): void {
    if (this.mediaPickerMessageHandler) {
      window.removeEventListener('message', this.mediaPickerMessageHandler)
      this.mediaPickerMessageHandler = null
    }
  }

  public destroy(): void {
    this.cleanupMediaPickerHandler()
  }

  public save(): QuizData {
    const root = this.wrapper
    const val = (selector: string, scope: Element = root): string => {
      const el = scope.querySelector(selector) as HTMLInputElement | HTMLTextAreaElement | null
      return el ? el.value.trim() : ''
    }

    const data: QuizData = {
      feedback: (root.querySelector('.cdx-quiz__feedback') as HTMLSelectElement)?.value || 'immediate',
      questions: [],
    }

    const title = val('.cdx-quiz__title')
    if (title) data.title = title
    const difficulty = val('.cdx-quiz__difficulty')
    if (difficulty) data.difficulty = difficulty
    const cta = val('.cdx-quiz__cta')
    if (cta) data.cta = cta
    const ctaTitle = val('.cdx-quiz__cta-title')
    if (ctaTitle) data.ctaTitle = ctaTitle

    const numbering = (root.querySelector('.cdx-quiz__numbering') as HTMLSelectElement)?.value || ''
    if (numbering) data.numbering = numbering

    const labels: Record<string, string> = {}
    root.querySelectorAll('.cdx-quiz__label').forEach((labelEl) => {
      const key = (labelEl as HTMLElement).dataset.label || ''
      const value = (labelEl as HTMLInputElement).value.trim()
      if (key && value) labels[key] = value
    })
    if (Object.keys(labels).length > 0) data.labels = labels

    root.querySelectorAll('.cdx-quiz__q').forEach((qEl) => {
      const question: QuizQuestion = { q: val('.cdx-quiz__q-text', qEl), answers: [] }
      const media = val('.cdx-quiz__q-media', qEl)
      if (media) question.media = media
      const video = val('.cdx-quiz__q-video', qEl)
      if (video) question.video = video
      const alt = val('.cdx-quiz__q-alt', qEl)
      if (alt) question.alt = alt
      const explanation = val('.cdx-quiz__q-explanation', qEl)
      if (explanation) question.explanation = explanation

      qEl.querySelectorAll('.cdx-quiz__a').forEach((aEl) => {
        const text = val('.cdx-quiz__a-text', aEl)
        if (!text) return
        const answer: QuizAnswer = { a: text }
        if ((aEl.querySelector('.cdx-quiz__a-correct') as HTMLInputElement)?.checked) {
          answer.correct = true
        }
        const answerMedia = val('.cdx-quiz__a-media', aEl)
        if (answerMedia) answer.media = answerMedia
        question.answers!.push(answer)
      })

      data.questions!.push(question)
    })

    const results: QuizResultBand[] = []
    root.querySelectorAll('.cdx-quiz__result-row').forEach((rEl) => {
      const msg = val('.cdx-quiz__result-msg', rEl)
      if (!msg) return
      results.push({ min: Number(val('.cdx-quiz__result-min', rEl)) || 0, msg })
    })
    if (results.length > 0) data.results = results

    this.data = data
    return data
  }

  public validate(): boolean {
    return true
  }

  /** Strip empty optional fields so the exported JSON stays compact. */
  private static clean(data: QuizData): QuizData {
    const out: QuizData = {
      feedback: 'end' === data.feedback ? 'end' : 'immediate',
      questions: [],
    }
    if (data.title) out.title = data.title
    if (data.difficulty) out.difficulty = data.difficulty
    if (data.cta) out.cta = data.cta
    if (data.ctaTitle) out.ctaTitle = data.ctaTitle
    if (data.numbering) out.numbering = data.numbering
    if (data.labels && Object.keys(data.labels).length > 0) out.labels = data.labels

    ;(data.questions || []).forEach((q) => {
      const cq: QuizQuestion = { q: q.q || '', answers: [] }
      if (q.media) cq.media = q.media
      if (q.video) cq.video = q.video
      if (q.alt) cq.alt = q.alt
      if (q.explanation) cq.explanation = q.explanation
      ;(q.answers || []).forEach((a) => {
        if (!a.a) return
        const ca: QuizAnswer = { a: a.a }
        if (a.correct) ca.correct = true
        if (a.media) ca.media = a.media
        if (a.alt) ca.alt = a.alt
        cq.answers!.push(ca)
      })
      out.questions!.push(cq)
    })

    const bands = (data.results || []).filter((r) => r.msg).map((r) => ({ min: Number(r.min) || 0, msg: r.msg }))
    if (bands.length > 0) out.results = bands

    return out
  }

  public static exportToMarkdown(data: QuizData): string {
    const clean = Quiz.clean(data)
    if (!clean.questions || 0 === clean.questions.length) return ''

    // Inline JSON inside a single-quoted Twig string: double backslashes, then
    // escape single quotes. Twig sees only a string literal — it cannot choke
    // on the quiz structure, so a bad payload degrades instead of 500-ing.
    const escaped = JSON.stringify(clean).replace(/\\/g, '\\\\').replace(/'/g, "\\'")

    return `{{ quiz('${escaped}') }}`
  }

  static importFromMarkdown(editor: API, markdown: string): QuizData {
    const empty: QuizData = { feedback: 'immediate', questions: [] }
    const match = markdown.match(/\{\{\s*quiz\(\s*'((?:\\.|[^'\\])*)'\s*\)\s*\}\}/)
    if (!match) return empty

    const json = match[1].replace(/\\(['\\])/g, '$1')
    let data: QuizData
    try {
      data = JSON.parse(json)
    } catch {
      return empty
    }

    const block = editor.blocks.insert('quiz', data)
    editor.blocks.update(block.id, data)
    return data
  }

  static isItMarkdownExported(markdown: string): boolean {
    return /\{\{\s*quiz\(/.test(markdown)
  }
}
