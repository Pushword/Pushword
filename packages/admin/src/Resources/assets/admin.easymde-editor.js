import * as EasyMDE from 'easymde';
window.EasyMDE = EasyMDE;

export function easyMDEditor() {
  var timeoutPreviewRender = null;
  $('textarea[data-editor="markdown"]').each(function () {
    var editorElement = $(this)[0];
    new EasyMDE({
      element: editorElement,
      toolbar: [
        'bold',
        'italic',
        'heading-2',
        'heading-3',
        '|',
        'unordered-list',
        'ordered-list',
        '|',
        'link',
        'image',
        'quote',
        'code',
        'side-by-side',
        'fullscreen',
        {
          name: 'guide',
          action: '/admin/markdown-cheatsheet',
          className: 'fa fa-question-circle',
          noDisable: true,
          title: 'Documentation',
          default: true,
        },
      ],
      status: ['autosave', 'lines', 'words', 'cursor'],
      spellChecker: false,
      nativeSpellcheck: true,
      previewImagesInEditor: true,
      insertTexts: {
        link: ['[', ']()'],
        image: ['![', '](/media/default/...)'],
      },
      //minHeight: "70vh",
      maxHeight: '70vh',
      syncSideBySidePreviewScroll: false,
      previewRender: function (editorContent, preview) {
        $(editorElement).val(editorContent);
        if (!document.getElementById('previewf')) {
          customPreview(editorContent, editorElement, preview);
        }
        document.addEventListener('keyup', function (e) {
          clearTimeout(timeoutPreviewRender);
          timeoutPreviewRender = setTimeout(function () {
            customPreview(editorContent, editorElement, preview);
          }, 1000);
        });
      },
      /**/
    });
  });

  function customPreview(editorContent, editorElement, preview) {
    var preloadIframeElement = document.querySelector('iframe.load-preview');
    var previewIframeElement = document.querySelector('iframe.preview-visible');

    var scrollTop = preloadIframeElement
      ? previewIframeElement.contentWindow.window.scrollY
      : 0;
    var XHR = new XMLHttpRequest();
    var form = $(editorElement).closest('form');
    var actionUrl = form.attr('action');
    var urlEncodedData = form.serialize() + '&btn_preview';

    createIframes(preview);

    XHR.addEventListener('load', function (event) {
      var preloadIframeElement = preview.querySelector('iframe.load-preview');
      var previewIframeElement = preview.querySelector(
        'iframe.preview-visible'
      );

      preloadIframeElement.srcdoc = XHR.response;
      preloadIframeElement.onload = function () {
        preloadIframeElement.classList.toggle('preview-visible');
        previewIframeElement.classList.toggle('preview-visible');
        preloadIframeElement.classList.toggle('load-preview');
        previewIframeElement.classList.toggle('load-preview');

        preloadIframeElement.contentWindow.scrollTo(0, scrollTop, {
          duration: 0,
        });
        previewIframeElement.contentWindow.scrollTo(0, scrollTop, {
          duration: 0,
        });
      };
    });
    XHR.addEventListener('error', function (event) {
      preview.innerHTML = "Oups! Quelque chose s'est mal passé.";
    });
    XHR.open('POST', actionUrl);
    XHR.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    XHR.send(urlEncodedData);
  }

  function createIframes(preview) {
    if (!document.getElementById('previewf')) {
      preview.innerHTML =
        '<iframe width=100% height=100% class=preview-visible id=previewf src="about:blank" allowtransparency="true" frameborder="0" border="0" cellspacing="0"></iframe>' +
        '<iframe width=100% height=100% class="load-preview" src="about:blank" allowtransparency="true" frameborder="0" border="0" cellspacing="0"></iframe>';
    }
  }
}
