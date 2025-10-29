declare module '*.css' {
  const content: any
  export default content
}

declare module '*.svg?raw' {
  const content: string
  export default content
}

declare module '@codexteam/ajax' {
  export interface AjaxOptions {
    url?: string
    data?: object
    accept?: string
    headers?: object
    beforeSend?: (files: File[]) => void
    fieldName?: string
    type?: string
  }
  export type AjaxFileOptionsParam = {
    accept: string
  }
  export interface AjaxResponse<T = object> {
    body: T
  }
  export function selectFiles(options: AjaxFileOptionsParam): Promise<File[]>
  export function transport(options: AjaxOptions): Promise<AjaxResponse>
  export function post(options: AjaxOptions): Promise<AjaxResponse>
  export const contentType: {
    JSON: string
  }
}

declare module '@editorjs/marker' {
  const Marker: any
  export default Marker
}

// todo, drop drag and drop in favor of dedicated sidebar
declare module 'editorjs-drag-drop' {
  const DragDrop: any
  export default DragDrop
}

// https://github.com/kommitters/editorjs-undo/pull/287
declare module 'editorjs-undo' {
  const Undo: any
  export default Undo
}

// todo make a PR to update to ts and vite https://github.com/SotaProject/strikethrough
declare module '@sotaproject/strikethrough' {
  const Strikethrough: any
  export default Strikethrough
}
