import initMermaid from 'monaco-mermaid'

export function registerMermaid(monaco) {
  initMermaid(monaco)

  // Monaco shares one theme across editors, including Mermaid embedded in Markdown.
  // Scope these tokens so other languages keep their standard light palette.
  monaco.editor.defineTheme('pushword-light', {
    base: 'vs',
    inherit: true,
    colors: {},
    rules: [
      { token: 'typeKeyword.mermaid', foreground: '6D28D9', fontStyle: 'bold' },
      { token: 'keyword.mermaid', foreground: '1D4ED8' },
      { token: 'transition.mermaid', foreground: '047857', fontStyle: 'bold' },
      { token: 'string.mermaid', foreground: '92400E' },
      { token: 'variable.mermaid', foreground: '334155' },
      { token: 'comment.mermaid', foreground: '64748B' },
    ],
  })
}
