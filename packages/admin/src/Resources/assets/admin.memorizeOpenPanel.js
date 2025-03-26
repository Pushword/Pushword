export function memorizeOpenPanel() {
  if (!$('.collapse').length) return

  $('.collapse').on('shown.bs.collapse', function () {
    var active = $(this).attr('id')
    var panels = localStorage.panels === 'undefined' || localStorage.panels === undefined ? new Array() : JSON.parse(localStorage.panels)
    if ($.inArray(active, panels) == -1) panels.push(active)
    localStorage.panels = JSON.stringify(panels)

    $("[href='#" + active + "'] .fa-plus")
      .removeClass('fa-plus')
      .addClass('fa-minus')
  })

  $('.collapse').on('hidden.bs.collapse', function () {
    var active = $(this).attr('id')
    var panels = localStorage.panels === 'undefined' || localStorage.panels === undefined ? new Array() : JSON.parse(localStorage.panels)
    var elementIndex = $.inArray(active, panels)
    if (elementIndex !== -1) {
      panels.splice(elementIndex, 1)
    }
    localStorage.panels = JSON.stringify(panels)

    $("[href='#" + active + "'] .fa-minus")
      .removeClass('fa-minus')
      .addClass('fa-plus')
  })

  function onInit() {
    var panels = localStorage.panels === 'undefined' || localStorage.panels === undefined ? new Array() : JSON.parse(localStorage.panels)
    for (var i in panels) {
      if ($('#' + panels[i]).hasClass('collapse')) {
        $('#' + panels[i]).collapse('show')
        $("[href='#" + panels[i] + "'] .fa-plus")
          .removeClass('fa-plus')
          .addClass('fa-minus')
      }
    }
  }

  onInit()
  onErrorOpenPanel()

  function onErrorOpenPanel() {
    document.querySelectorAll('.sonata-ba-field-error-messages').forEach(function (element) {
      var panel = element.closest('.collapse')
      if (panel) {
        $(panel).collapse('show')
      }
    })
  }
}
