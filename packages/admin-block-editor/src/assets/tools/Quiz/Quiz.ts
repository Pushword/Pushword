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
  weights?: Record<string, number>
  profile?: string
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

interface QuizProfile {
  key?: string
  title?: string
  msg?: string
  media?: string
  alt?: string
}

export interface QuizData extends BlockToolData {
  mode?: string
  title?: string
  difficulty?: string
  label?: string
  pass?: number
  feedback?: string
  cta?: string
  ctaTitle?: string
  numbering?: string
  labels?: Record<string, string>
  results?: QuizResultBand[]
  profiles?: QuizProfile[]
  questions?: QuizQuestion[]
  levels?: QuizData[]
}

const LABEL_DEFAULTS: Record<string, string> = {
  question: 'Question',
  questions: 'questions',
  explanation: 'Explanation',
  score: 'Your score:',
  better: 'Better than {p}% of participants',
}

/**
 * Editor for the quiz block: add/remove questions and answers, flag the correct
 * answer(s), attach an image or video, write the explanation. Exports a
 * `{% quiz %}…{% endquiz %}` block with pretty-printed raw JSON, and imports both
 * that and the legacy `{{ quiz('…') }}` form.
 */
export default class Quiz extends BaseTool {
  declare public data: QuizData
  private wrapper!: HTMLElement
  private singleSection!: HTMLElement
  private levelsSection!: HTMLElement
  private levelsList!: HTMLElement
  private profilesSection!: HTMLElement
  private levelsToggle!: HTMLInputElement
  private profileToggle!: HTMLInputElement
  private levelsMode = false
  private profileMode = false
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
      mode: 'profile' === data.mode ? 'profile' : 'quiz',
      title: data.title || '',
      difficulty: data.difficulty || '',
      feedback: data.feedback || 'immediate',
      cta: data.cta || '',
      ctaTitle: data.ctaTitle || '',
      numbering: data.numbering || '',
      labels: data.labels || {},
      results: Array.isArray(data.results) ? data.results : [],
      profiles: Array.isArray(data.profiles) ? data.profiles : [],
      questions,
      levels: Array.isArray(data.levels) ? data.levels : [],
    }
    this.levelsMode = (this.data.levels || []).length > 0
    this.profileMode = 'profile' === this.data.mode
  }

  public render(): HTMLElement {
    this.wrapper = make.element('div', 'cdx-quiz')
    this.wrapper.appendChild(
      make.element('div', 'cdx-quiz__header', {}, ToolboxIcon + '<span>Quiz</span>'),
    )

    const meta = make.element('div', 'cdx-quiz__meta')
    meta.appendChild(this.inputEl('cdx-quiz__title', 'Title', this.data.title || ''))
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

    // Difficulty-levels toggle: a single quiz, or several levels behind tabs.
    const modeWrap = make.element('label', 'cdx-quiz__mode')
    this.levelsToggle = make.element('input', 'cdx-quiz__mode-toggle', { type: 'checkbox' }) as HTMLInputElement
    this.levelsToggle.checked = this.levelsMode
    this.levelsToggle.addEventListener('change', () => {
      this.levelsMode = this.levelsToggle.checked
      // Difficulty levels and a personality test are mutually exclusive.
      if (this.levelsMode) {
        this.profileMode = false
        this.profileToggle.checked = false
      }

      if (this.levelsMode && 0 === this.levelsList.children.length) {
        this.levelsList.appendChild(this.buildLevel({}))
      }

      this.applyMode()
    })
    modeWrap.appendChild(this.levelsToggle)
    modeWrap.appendChild(make.element('span', null, {}, 'Multiple difficulty levels (tabs)'))
    this.wrapper.appendChild(modeWrap)

    // Personality-test toggle: answers weigh profiles instead of being correct.
    const profileWrap = make.element('label', 'cdx-quiz__mode')
    this.profileToggle = make.element('input', 'cdx-quiz__mode-toggle', { type: 'checkbox' }) as HTMLInputElement
    this.profileToggle.checked = this.profileMode
    this.profileToggle.addEventListener('change', () => {
      this.profileMode = this.profileToggle.checked
      if (this.profileMode) {
        this.levelsMode = false
        this.levelsToggle.checked = false
        if (0 === this.profilesSection.querySelectorAll('.cdx-quiz__profile').length) {
          this.profilesSection.querySelector('.cdx-quiz__profiles')?.appendChild(this.buildProfile({}))
        }
      }

      this.applyMode()
    })
    profileWrap.appendChild(this.profileToggle)
    profileWrap.appendChild(make.element('span', null, {}, 'Personality test (profiles instead of correct answers)'))
    this.wrapper.appendChild(profileWrap)

    // Single-quiz editor (difficulty + questions + score bands). Its questions are
    // reused by the personality test — only their answers read weights, not a flag.
    this.singleSection = make.element('div', 'cdx-quiz__single')
    this.singleSection.appendChild(this.inputEl('cdx-quiz__difficulty', 'Difficulty', this.data.difficulty || ''))
    this.singleSection.appendChild(this.buildQuestionsBlock(this.data.questions || []))
    this.singleSection.appendChild(this.buildResultsBlock(this.data.results || []))
    this.wrapper.appendChild(this.singleSection)

    // Personality-test outcomes (shown instead of score bands in profile mode).
    this.profilesSection = this.buildProfilesBlock(this.data.profiles || [])
    this.wrapper.appendChild(this.profilesSection)

    // Multi-level editor: one full sub-quiz per level.
    this.levelsSection = make.element('div', 'cdx-quiz__levels')
    this.levelsList = make.element('div', 'cdx-quiz__levels-list')
    ;(this.data.levels || []).forEach((level) => this.levelsList.appendChild(this.buildLevel(level)))
    this.levelsSection.appendChild(this.levelsList)
    this.levelsSection.appendChild(
      make.element('button', 'cdx-quiz__add', { type: 'button' }, '+ Level', () => {
        this.levelsList.appendChild(this.buildLevel({}))
      }),
    )
    this.wrapper.appendChild(this.levelsSection)

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

    this.applyMode()

    return this.wrapper
  }

  /** Reflect the active mode (single quiz / difficulty levels / personality test). */
  private applyMode(): void {
    // A CSS class swaps the per-answer control (correct flag <-> profile weights)
    // and hides the score bands / difficulty in personality mode.
    this.wrapper.classList.toggle('cdx-quiz--profile', this.profileMode)
    // The questions live in the single section, reused by the personality test.
    this.singleSection.hidden = this.levelsMode && !this.profileMode
    this.levelsSection.hidden = !this.levelsMode || this.profileMode
    this.profilesSection.hidden = !this.profileMode
  }

  /** A profiles list plus its "+ Profile" button (personality-test outcomes). */
  private buildProfilesBlock(profiles: QuizProfile[]): HTMLElement {
    const block = make.element('div', 'cdx-quiz__profiles-block')
    block.appendChild(make.element('div', 'cdx-quiz__subtitle', {}, 'Profiles (personality results)'))
    const list = make.element('div', 'cdx-quiz__profiles')
    profiles.forEach((p) => list.appendChild(this.buildProfile(p)))
    block.appendChild(list)
    block.appendChild(
      make.element('button', 'cdx-quiz__add', { type: 'button' }, '+ Profile', () => {
        list.appendChild(this.buildProfile({}))
      }),
    )
    return block
  }

  /** One personality outcome: its key (referenced by answer weights), title, description, image. */
  private buildProfile(p: QuizProfile): HTMLElement {
    const el = make.element('div', 'cdx-quiz__profile')

    const head = make.element('div', 'cdx-quiz__level-head')
    head.appendChild(make.element('span', null, {}, 'Profile'))
    head.appendChild(
      make.element('button', 'cdx-quiz__del', { type: 'button', title: 'Remove profile' }, '✕', () => el.remove()),
    )
    el.appendChild(head)

    const row = make.element('div', 'cdx-quiz__row')
    row.appendChild(this.inputEl('cdx-quiz__profile-key', 'Key (used by answer weights)', p.key || ''))
    row.appendChild(this.inputEl('cdx-quiz__profile-title', 'Title', p.title || ''))
    el.appendChild(row)

    el.appendChild(this.textareaEl('cdx-quiz__profile-msg', 'Description (shown on the result card)', p.msg || ''))
    el.appendChild(this.buildMediaField('cdx-quiz__profile-media', 'Result image (optional)', p.media || ''))

    return el
  }

  /** A questions list plus its "+ Question" button. Reused per level. */
  private buildQuestionsBlock(questions: QuizQuestion[]): HTMLElement {
    const block = make.element('div', 'cdx-quiz__questions-block')
    const list = make.element('div', 'cdx-quiz__questions')
    questions.forEach((q) => list.appendChild(this.buildQuestion(q)))
    block.appendChild(list)
    block.appendChild(
      make.element('button', 'cdx-quiz__add', { type: 'button' }, '+ Question', () => {
        list.appendChild(this.buildQuestion({ q: '', answers: [{ a: '', correct: true }, { a: '' }] }))
      }),
    )
    return block
  }

  /** A score-bands list plus its "+ Band" button. Reused per level. */
  private buildResultsBlock(results: QuizResultBand[]): HTMLElement {
    const block = make.element('div', 'cdx-quiz__results-block')
    block.appendChild(make.element('div', 'cdx-quiz__subtitle', {}, 'Score bands (optional)'))
    const list = make.element('div', 'cdx-quiz__results')
    results.forEach((r) => list.appendChild(this.buildResult(r)))
    block.appendChild(list)
    block.appendChild(
      make.element('button', 'cdx-quiz__add', { type: 'button' }, '+ Band', () => {
        list.appendChild(this.buildResult({ min: 0, msg: '' }))
      }),
    )
    return block
  }

  /** One difficulty level: its label/difficulty/pass plus a full sub-quiz. */
  private buildLevel(level: QuizData): HTMLElement {
    const el = make.element('div', 'cdx-quiz__level')

    const head = make.element('div', 'cdx-quiz__level-head')
    head.appendChild(make.element('span', null, {}, 'Level'))
    head.appendChild(
      make.element('button', 'cdx-quiz__del', { type: 'button', title: 'Remove level' }, '✕', () => el.remove()),
    )
    el.appendChild(head)

    const row = make.element('div', 'cdx-quiz__row')
    row.appendChild(this.inputEl('cdx-quiz__level-label', 'Tab label (defaults to difficulty)', level.label || ''))
    row.appendChild(this.inputEl('cdx-quiz__level-difficulty', 'Difficulty', level.difficulty || ''))
    const pass = make.element('input', ['cdx-quiz__input', 'cdx-quiz__level-pass'], {
      type: 'number',
      min: '0',
      max: '100',
      placeholder: 'Pass % (default 50)',
    }) as HTMLInputElement
    if (level.pass !== undefined && level.pass !== null) pass.value = String(level.pass)
    row.appendChild(pass)
    el.appendChild(row)

    el.appendChild(this.buildQuestionsBlock(level.questions || []))
    el.appendChild(this.buildResultsBlock(level.results || []))

    return el
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

    // Personality mode only (a CSS class shows it in place of the correct flag):
    // which profiles this answer weighs, e.g. "explorer:2, builder".
    const weights = this.inputEl('cdx-quiz__a-weights', 'Profiles (e.g. explorer:2, builder)', Quiz.weightsToStr(a.weights, a.profile))
    el.appendChild(weights)

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
          if (!response.ok) throw new Error(await MediaUtils.uploadErrorMessage(response))
          const data = await response.json()
          const mediaName: string | undefined = data?.file?.media
          if (mediaName) {
            input.value = mediaName
            onSet(mediaName)
          }
        } catch (error) {
          const detail = error instanceof Error ? error.message : ''
          this.api.notifier.show({
            message: detail ? `Upload failed (${detail})` : 'Upload failed',
            style: 'error',
          })
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

  private static val(selector: string, scope: Element): string {
    const el = scope.querySelector(selector) as HTMLInputElement | HTMLTextAreaElement | null
    return el ? el.value.trim() : ''
  }

  /** Read every `.cdx-quiz__q` within `scope` into question objects. */
  private readQuestions(scope: Element): QuizQuestion[] {
    const val = Quiz.val
    const questions: QuizQuestion[] = []
    scope.querySelectorAll('.cdx-quiz__q').forEach((qEl) => {
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
        if (this.profileMode) {
          const weights = Quiz.parseWeights(val('.cdx-quiz__a-weights', aEl))
          if (Object.keys(weights).length > 0) answer.weights = weights
        } else if ((aEl.querySelector('.cdx-quiz__a-correct') as HTMLInputElement)?.checked) {
          answer.correct = true
        }
        const answerMedia = val('.cdx-quiz__a-media', aEl)
        if (answerMedia) answer.media = answerMedia
        question.answers!.push(answer)
      })

      questions.push(question)
    })
    return questions
  }

  /** Read every `.cdx-quiz__profile` within `scope` into personality outcomes. */
  private readProfiles(scope: Element): QuizProfile[] {
    const profiles: QuizProfile[] = []
    scope.querySelectorAll('.cdx-quiz__profile').forEach((pEl) => {
      const key = Quiz.val('.cdx-quiz__profile-key', pEl)
      const title = Quiz.val('.cdx-quiz__profile-title', pEl)
      if (!key || !title) return
      const profile: QuizProfile = { key, title }
      const msg = Quiz.val('.cdx-quiz__profile-msg', pEl)
      if (msg) profile.msg = msg
      const media = Quiz.val('.cdx-quiz__profile-media', pEl)
      if (media) profile.media = media
      profiles.push(profile)
    })
    return profiles
  }

  /** Serialise `weights` (+ the `profile` shorthand) into the "key:points, key" input text. */
  private static weightsToStr(weights?: Record<string, number>, profile?: string): string {
    const map: Record<string, number> = { ...(weights || {}) }
    if (profile && !(profile in map)) map[profile] = 1
    return Object.keys(map)
      .map((k) => (1 === map[k] ? k : `${k}:${map[k]}`))
      .join(', ')
  }

  /** Parse the "key:points, key" input text back into a weights map (bare key == 1 point). */
  private static parseWeights(raw: string): Record<string, number> {
    const out: Record<string, number> = {}
    raw.split(',').forEach((part) => {
      const seg = part.trim()
      if (!seg) return
      const at = seg.lastIndexOf(':')
      if (-1 === at) {
        out[seg] = 1
        return
      }
      const key = seg.slice(0, at).trim()
      const value = Number(seg.slice(at + 1).trim())
      if (key) out[key] = Number.isFinite(value) && 0 !== value ? value : 1
    })
    return out
  }

  /** Read every `.cdx-quiz__result-row` within `scope` into score bands. */
  private readResults(scope: Element): QuizResultBand[] {
    const results: QuizResultBand[] = []
    scope.querySelectorAll('.cdx-quiz__result-row').forEach((rEl) => {
      const msg = Quiz.val('.cdx-quiz__result-msg', rEl)
      if (!msg) return
      results.push({ min: Number(Quiz.val('.cdx-quiz__result-min', rEl)) || 0, msg })
    })
    return results
  }

  public save(): QuizData {
    const root = this.wrapper
    const val = (selector: string): string => Quiz.val(selector, root)

    const data: QuizData = {
      feedback: (root.querySelector('.cdx-quiz__feedback') as HTMLSelectElement)?.value || 'immediate',
      questions: [],
    }

    const title = val('.cdx-quiz__title')
    if (title) data.title = title
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

    if (this.profileMode) {
      data.mode = 'profile'
      data.feedback = 'end' // no correct answer to reveal mid-way
      data.questions = this.readQuestions(this.singleSection)
      const profiles = this.readProfiles(this.profilesSection)
      if (profiles.length > 0) data.profiles = profiles
    } else if (this.levelsMode) {
      const levels: QuizData[] = []
      this.levelsList.querySelectorAll('.cdx-quiz__level').forEach((levelEl) => {
        const level: QuizData = { questions: this.readQuestions(levelEl) }
        const label = Quiz.val('.cdx-quiz__level-label', levelEl)
        if (label) level.label = label
        const difficulty = Quiz.val('.cdx-quiz__level-difficulty', levelEl)
        if (difficulty) level.difficulty = difficulty
        const passValue = Quiz.val('.cdx-quiz__level-pass', levelEl)
        if (passValue) level.pass = Number(passValue)
        const levelResults = this.readResults(levelEl)
        if (levelResults.length > 0) level.results = levelResults
        levels.push(level)
      })
      data.levels = levels
      // The root keeps no questions of its own once levels carry them.
    } else {
      const difficulty = val('.cdx-quiz__difficulty')
      if (difficulty) data.difficulty = difficulty
      data.questions = this.readQuestions(this.singleSection)
      const results = this.readResults(this.singleSection)
      if (results.length > 0) data.results = results
    }

    this.data = data
    return data
  }

  public validate(): boolean {
    return true
  }

  private static cleanQuestions(questions: QuizQuestion[] | undefined): QuizQuestion[] {
    const out: QuizQuestion[] = []
    ;(questions || []).forEach((q) => {
      const cq: QuizQuestion = { q: q.q || '', answers: [] }
      if (q.media) cq.media = q.media
      if (q.video) cq.video = q.video
      if (q.alt) cq.alt = q.alt
      if (q.explanation) cq.explanation = q.explanation
      ;(q.answers || []).forEach((a) => {
        if (!a.a) return
        const ca: QuizAnswer = { a: a.a }
        if (a.correct) ca.correct = true
        // Canonicalise personality weights: an explicit map, or the `profile` shorthand (== {key: 1}).
        if (a.weights && Object.keys(a.weights).length > 0) ca.weights = a.weights
        else if (a.profile) ca.weights = { [a.profile]: 1 }
        if (a.media) ca.media = a.media
        if (a.alt) ca.alt = a.alt
        cq.answers!.push(ca)
      })
      out.push(cq)
    })
    return out
  }

  private static cleanBands(results: QuizResultBand[] | undefined): QuizResultBand[] {
    return (results || []).filter((r) => r.msg).map((r) => ({ min: Number(r.min) || 0, msg: r.msg ?? '' }))
  }

  private static cleanProfiles(profiles: QuizProfile[] | undefined): QuizProfile[] {
    return (profiles || [])
      .filter((p) => p.key && p.title)
      .map((p) => {
        const cp: QuizProfile = { key: p.key ?? '', title: p.title ?? '' }
        if (p.msg) cp.msg = p.msg
        if (p.media) cp.media = p.media
        if (p.alt) cp.alt = p.alt
        return cp
      })
  }

  private static cleanLevel(level: QuizData): QuizData {
    const out: QuizData = { questions: Quiz.cleanQuestions(level.questions) }
    if (level.label) out.label = level.label
    if (level.difficulty) out.difficulty = level.difficulty
    if (level.pass !== undefined && level.pass !== null && !Number.isNaN(Number(level.pass))) {
      out.pass = Number(level.pass)
    }

    const bands = Quiz.cleanBands(level.results)
    if (bands.length > 0) out.results = bands
    return out
  }

  /** Strip empty optional fields so the exported JSON stays compact. */
  private static clean(data: QuizData): QuizData {
    const isProfile = 'profile' === data.mode

    // Shared metadata, in one place for every mode. Personality tests always
    // score at the end (no correct answer to reveal).
    const out: QuizData = { feedback: isProfile || 'end' === data.feedback ? 'end' : 'immediate' }
    if (isProfile) out.mode = 'profile'
    if (data.title) out.title = data.title
    if (data.cta) out.cta = data.cta
    if (data.ctaTitle) out.ctaTitle = data.ctaTitle
    if (data.numbering) out.numbering = data.numbering
    if (data.labels && Object.keys(data.labels).length > 0) out.labels = data.labels

    // Personality test: profile cards + weighted-answer questions.
    if (isProfile) {
      const profiles = Quiz.cleanProfiles(data.profiles)
      if (profiles.length > 0) out.profiles = profiles
      out.questions = Quiz.cleanQuestions(data.questions)
      return out
    }

    // Levels own the questions; the root keeps only the shared metadata.
    if (Array.isArray(data.levels) && data.levels.length > 0) {
      out.levels = data.levels.map((level) => Quiz.cleanLevel(level))
      return out
    }

    if (data.difficulty) out.difficulty = data.difficulty
    out.questions = Quiz.cleanQuestions(data.questions)

    const bands = Quiz.cleanBands(data.results)
    if (bands.length > 0) out.results = bands

    return out
  }

  public static exportToMarkdown(data: QuizData): string {
    const clean = Quiz.clean(data)
    const hasQuestions = !!clean.questions && clean.questions.length > 0
    const hasLevels = !!clean.levels && clean.levels.length > 0
    if (!hasQuestions && !hasLevels) return ''

    // The `{% quiz %}` body is raw JSON: no Twig-string escaping (apostrophes stay
    // literal) and pretty-printed so the stored content reads and diffs cleanly.
    // JSON.stringify never emits blank lines, which the Markdown pipeline splits on.
    return `{% quiz %}\n${JSON.stringify(clean, null, 2)}\n{% endquiz %}`
  }

  static importFromMarkdown(editor: API, markdown: string): QuizData {
    const empty: QuizData = { feedback: 'immediate', questions: [] }
    const json = Quiz.extractJson(markdown)
    if (json === null) return empty

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

  /**
   * Pull the JSON out of either the `{% quiz %}` block (raw body) or the legacy
   * `{{ quiz('…') }}` function (a single-quoted Twig string, so `\'`/`\\` are
   * unescaped back to JSON).
   */
  private static extractJson(markdown: string): string | null {
    const tag = markdown.match(/\{%\s*quiz\s*%\}([\s\S]*?)\{%\s*endquiz\s*%\}/)
    if (tag) return (tag[1] ?? '').trim()

    const fn = markdown.match(/\{\{\s*quiz\(\s*'((?:\\.|[^'\\])*)'\s*\)\s*\}\}/)
    if (fn) return (fn[1] ?? '').replace(/\\(['\\])/g, '$1')

    return null
  }

  static isItMarkdownExported(markdown: string): boolean {
    return /\{%\s*quiz\s*%\}/.test(markdown) || /\{\{\s*quiz\(/.test(markdown)
  }
}
