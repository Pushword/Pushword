import HeaderTool from './../../node_modules/@editorjs/header/src/index.ts'

export default class Header extends HeaderTool {
  // Wait for PR  https://github.com/editor-js/header/pull/74/files merged
  static get sanitize() {
    return {
      level: false,
      text: {
        br: true,
        small: true,
      },
    }
  }
}
