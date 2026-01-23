import HeaderTool from "@editorjs/header";
class Header extends HeaderTool {
  // Wait for PR  https://github.com/editor-js/header/pull/74/files merged
  static get sanitize() {
    return {
      level: false,
      text: {
        br: true,
        small: true
      }
    };
  }
}
export {
  Header as default
};
