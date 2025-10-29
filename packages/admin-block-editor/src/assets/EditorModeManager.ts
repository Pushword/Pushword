import { API, OutputData } from '@editorjs/editorjs'
import { logger } from './tools/utils/logger'

/**
 * Gestionnaire des modes d'édition (EditorJS, JSON, Markdown)
 */
export class EditorModeManager {
  private readonly editorId: string
  private readonly monacoInstanceKey: string

  // Constantes
  private readonly EDITOR_MODES = {
    EDITORJS: null,
    JSON: 'json',
    MARKDOWN: 'markdown',
  } as const

  private readonly TEXTAREA_STYLES = {
    minHeight: '70vh',
    maxHeight: '71vh',
    width: '100%',
    // display: 'block',
  } as const

  constructor(editorId: string) {
    this.editorId = editorId
    this.monacoInstanceKey = `monacoEditorInstance${editorId}`
  }

  /**
   * Récupère l'élément input de l'éditeur
   */
  private getEditorInput(): HTMLInputElement | HTMLTextAreaElement | null {
    const editorHolder = document.getElementById(this.editorId)
    if (!editorHolder) {
      logger.warn("Élément holder de l'éditeur non trouvé", { editorId: this.editorId })
      return null
    }

    const inputId = editorHolder.getAttribute('data-input-id')

    const input = document.getElementById(inputId || '') as
      | HTMLInputElement
      | HTMLTextAreaElement
      | null

    if (!input) {
      logger.warn('Élément input non trouvé', { inputId })
    }

    return input
  }

  /**
   * Récupère le mode actuel de l'éditeur
   */
  private getCurrentMode(): string | null {
    const input = this.getEditorInput()
    const mode = input ? input.getAttribute('data-editor') : null

    return mode
  }

  private getEditorInstance(): API {
    if (!window.editors[this.editorId]) {
      logger.error('Instance EditorJS non trouvée', {
        editorId: this.editorId,
        availableEditors: Object.keys(window.editors || {}),
      })
      throw new Error(`Editor instance for editorId ${this.editorId} not found`)
    }

    return window.editors[this.editorId]
  }

  /**
   * Récupère l'instance Monaco
   */
  private getMonacoInstance(): any {
    const instance = (window as any)[this.monacoInstanceKey]
    return instance
  }

  private setMonacoInstance(instance: any): void {
    ;(window as any)[this.monacoInstanceKey] = instance
  }

  private disposeMonacoInstance(): void {
    const instance = this.getMonacoInstance()
    if (instance) {
      try {
        instance.dispose()
        this.setMonacoInstance(null)
      } catch (error) {
        logger.error('Erreur lors du nettoyage de Monaco', {
          editorId: this.editorId,
          error,
        })
      }
    }
  }

  private applyTextareaStyles(textarea: HTMLTextAreaElement): void {
    Object.assign(textarea.style, this.TEXTAREA_STYLES)
  }

  /**
   * Crée un textarea avec les attributs de base
   */
  private createTextarea(
    input: HTMLInputElement | HTMLTextAreaElement,
    content: string,
    mode: string,
  ): HTMLTextAreaElement {
    const textarea = document.createElement('textarea')
    textarea.id = input.id
    textarea.name = input.name
    textarea.value = content
    textarea.setAttribute('data-editor', mode)
    textarea.setAttribute('data-editorjs', input.getAttribute('data-editorjs') || '')
    this.applyTextareaStyles(textarea)
    return textarea
  }

  /**
   * Crée un input hidden
   */
  private createHiddenInput(textarea: HTMLTextAreaElement): HTMLInputElement {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.id = textarea.id
    input.name = textarea.name
    input.setAttribute('data-editorjs', textarea.getAttribute('data-editorjs') || '')
    return input
  }

  /**
   * Supprime l'éditeur Monaco du DOM
   */
  private removeMonacoFromDOM(): void {
    const monacoEditor = document.querySelector('.monaco-editor')
    if (monacoEditor && monacoEditor.parentNode) {
      monacoEditor.parentNode.parentNode?.removeChild(monacoEditor.parentNode)
    }
  }

  /**
   * Affiche/masque l'éditeur EditorJS
   */
  private toggleEditorJsVisibility(visible: boolean): void {
    const holder = document.getElementById(this.editorId)
    if (holder) {
      holder.style.display = visible ? 'block' : 'none'
    } else {
      logger.warn('Holder EditorJS non trouvé pour changer la visibilité', {
        editorId: this.editorId,
      })
    }
  }

  /**
   * Initialise Monaco Editor pour un textarea
   */
  private initMonacoEditor(textarea: HTMLTextAreaElement): void {
    setTimeout(() => {
      try {
        const monacoInstance = window.monacoHelper?.transformTextareaToMonaco(textarea)
        this.setMonacoInstance(monacoInstance)
        // Vérifier que l'instance est bien accessible
        setTimeout(() => {
          const storedInstance = this.getMonacoInstance()
          if (!storedInstance) {
            logger.warn('Instance Monaco perdue après initialisation', {
              editorId: this.editorId,
            })
          }
        }, 100)
      } catch (error) {
        logger.error("Erreur lors de l'initialisation de Monaco", {
          editorId: this.editorId,
          error,
        })
      }
    }, 0)
  }

  private switchTo(format: string = 'json'): void {
    try {
      const editorInstance = this.getEditorInstance()
      const input = this.getEditorInput()

      if (!input) {
        logger.error('Input non trouvé pour la transition vers Markdown', {
          editorId: this.editorId,
        })
        return
      }

      editorInstance.saver
        .save()
        .then(async (outputData: OutputData) => {
          try {
            let textareaContent = ''
            let textareaFormat = ''

            if (format === 'json') {
              textareaContent = JSON.stringify(outputData, null, 2)
              textareaFormat = this.EDITOR_MODES.JSON
            } else {
              textareaContent = await new window.EditorJsExportMarkdown(
                editorInstance,
                outputData,
              ).exportToMarkdown()
              textareaFormat = this.EDITOR_MODES.MARKDOWN
            }

            const textarea = this.createTextarea(input, textareaContent, textareaFormat)

            input.parentNode?.replaceChild(textarea, input)
            this.toggleEditorJsVisibility(false)
            this.initMonacoEditor(textarea)
          } catch (exportError) {
            logger.error("Erreur lors de l'export Markdown", {
              editorId: this.editorId,
              error: exportError,
              outputData,
            })
          }
        })
        .catch((saveError) => {
          logger.error('Erreur lors de la sauvegarde EditorJS', {
            editorId: this.editorId,
            error: saveError,
          })
        })
    } catch (error) {
      logger.error('Erreur lors de la transition vers Markdown', {
        editorId: this.editorId,
        error,
      })
    }
  }

  /**
   * Passe de Markdown à EditorJS
   */
  private switchFrom(format: string = 'markdown'): void {
    try {
      const textarea = this.getEditorInput() as HTMLTextAreaElement
      if (!textarea) {
        logger.error('Textarea non trouvé pour la transition depuis Markdown', {
          editorId: this.editorId,
        })
        return
      }

      const monacoInstance = this.getMonacoInstance()
      if (!monacoInstance) {
        logger.error('Instance Monaco non disponible pour la transition', {
          editorId: this.editorId,
        })
        return
      }

      const textareaContent = monacoInstance.getValue()

      if (format === 'markdown')
        try {
          new window.EditorJsParseMarkdown(
            this.getEditorInstance(),
            textareaContent,
          ).parseMarkdown()
        } catch (parseError) {
          logger.error('Erreur lors du parsing Markdown', {
            editorId: this.editorId,
            error: parseError,
            content: textareaContent.substring(0, 100) + '...',
          })
          throw parseError
        }
      else
        try {
          const parsedData = JSON.parse(textareaContent)
          this.getEditorInstance().blocks.render(parsedData)
        } catch (parseError) {
          logger.error('Erreur lors du parsing JSON pour EditorJS', {
            editorId: this.editorId,
            error: parseError,
            content: textareaContent.substring(0, 100) + '...',
          })
          // Continuer même en cas d'erreur de parsing pour restaurer l'interface
        }

      //this.getEditorInstance().blocks.render()
      this.removeMonacoFromDOM()
      this.disposeMonacoInstance()

      const hiddenInput = this.createHiddenInput(textarea)
      textarea.parentNode?.replaceChild(hiddenInput, textarea)
      this.toggleEditorJsVisibility(true)
    } catch (error) {
      logger.error('Erreur lors de la transition depuis ' + format, {
        editorId: this.editorId,
        error,
      })
      throw error
    }
  }

  private showOrHideBtn(show: boolean = true, btn: string = 'all'): void {
    let btnToggleMarkdown = document.querySelector(
      `[onclick="toggleEditor('markdown')"]`,
    ) as HTMLElement
    let btnToggleEditor = document.querySelector(
      `[onclick="toggleEditor()"]`,
    ) as HTMLElement
    if (btnToggleMarkdown && ['markdown', 'all'].includes(btn)) {
      btnToggleMarkdown.style.opacity = show ? '1' : '0'
      btnToggleMarkdown.style.pointerEvents = show ? 'auto' : 'none'
    }
    if (btnToggleEditor && ['json', 'all'].includes(btn)) {
      btnToggleEditor.style.opacity = show ? '1' : '0'
      btnToggleEditor.style.pointerEvents = show ? 'auto' : 'none'
    }
  }

  /**
   * Toggle l'éditeur JSON
   */
  public toggleEditor(): void {
    const currentMode = this.getCurrentMode()
    this.showOrHideBtn(true)

    if (currentMode === this.EDITOR_MODES.MARKDOWN) {
      this.toggleMarkdownEditor()
      return
    }

    // Toggle entre EditorJS et JSON
    if (currentMode === this.EDITOR_MODES.JSON) {
      this.switchFrom('json')
    } else {
      this.showOrHideBtn(false, 'markdown')
      this.switchTo('json')
    }
  }

  public toggleMarkdownEditor(): void {
    const currentMode = this.getCurrentMode()
    this.showOrHideBtn(true)

    // Si en mode JSON, revenir  à EditorJS
    if (currentMode === this.EDITOR_MODES.JSON) {
      this.toggleEditor()
      return
    }

    // Toggle entre EditorJS et Markdown
    if (currentMode === this.EDITOR_MODES.MARKDOWN) {
      this.switchFrom('markdown')
    } else {
      this.showOrHideBtn(false, 'json')
      this.switchTo('markdown')
    }
  }

  /**
   * Méthode de diagnostic pour afficher l'état complet du manager
   */
  public getDiagnosticInfo(): any {
    const input = this.getEditorInput()
    const currentMode = this.getCurrentMode()
    const editorInstance = window.editors?.[this.editorId]
    const monacoInstance = this.getMonacoInstance()

    const diagnostic = {
      editorId: this.editorId,
      monacoInstanceKey: this.monacoInstanceKey,
      currentMode: currentMode || 'EditorJS (par défaut)',
      hasInput: !!input,
      inputInfo: input
        ? {
            id: input.id,
            tagName: input.tagName,
            type: input.getAttribute('type') || 'textarea',
            dataEditor: input.getAttribute('data-editor'),
            dataEditorjs: input.getAttribute('data-editorjs'),
            valueLength: input.value?.length || 0,
          }
        : null,
      hasEditorInstance: !!editorInstance,
      hasMonacoInstance: !!monacoInstance,
      editorHolder: {
        exists: !!document.getElementById(this.editorId),
        display: document.getElementById(this.editorId)?.style.display || 'default',
      },
      availableEditors: Object.keys(window.editors || {}),
      timestamp: new Date().toISOString(),
    }

    return diagnostic
  }

  /**
   * Méthode pour forcer le nettoyage de toutes les instances
   */
  public forceCleanup(): void {
    logger.warn('Nettoyage forcé demandé', { editorId: this.editorId })

    try {
      this.removeMonacoFromDOM()
      this.disposeMonacoInstance()
      this.toggleEditorJsVisibility(true)
    } catch (error) {
      logger.error('Erreur lors du nettoyage forcé', { editorId: this.editorId, error })
    }
  }
}
