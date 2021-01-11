CodeMirror.defineMode('twigmarkdown', function (config) {
  return CodeMirror.overlayMode(
    CodeMirror.getMode(config, 'gfm'),
    CodeMirror.getMode(config, 'twig')
  );
});

this.codemirror = CodeMirror.fromTextArea(el, {
  mode: 'twigmarkdown',
  backdrop: backdrop,
  theme: options.theme != undefined ? options.theme : 'easymde',
  tabSize: options.tabSize != undefined ? options.tabSize : 2,
  indentUnit: options.tabSize != undefined ? options.tabSize : 2,
  indentWithTabs: options.indentWithTabs === false ? false : true,
  lineNumbers: false,
  autofocus: options.autofocus === true ? true : false,
  extraKeys: keyMaps,
  lineWrapping: options.lineWrapping === false ? false : true,
  allowDropFileTypes: ['text/plain'],
  placeholder: options.placeholder || el.getAttribute('placeholder') || '',
  styleSelectedText:
    options.styleSelectedText != undefined
      ? options.styleSelectedText
      : !isMobile(),
  configureMouse: configureMouse,
  inputStyle:
    options.inputStyle != undefined
      ? options.inputStyle
      : isMobile()
      ? 'contenteditable'
      : 'textarea',
  spellcheck:
    options.nativeSpellcheck != undefined ? options.nativeSpellcheck : true,
});
