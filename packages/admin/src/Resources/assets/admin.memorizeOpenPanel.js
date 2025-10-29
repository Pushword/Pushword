export function memorizeOpenPanel() {
  if (!jQuery('.collapse').length) return

  jQuery('.collapse').on('shown.bs.collapse', function () {
    var active = jQuery(this).attr('id')
    var panels =
      localStorage.panels === 'undefined' || localStorage.panels === undefined
        ? new Array()
        : JSON.parse(localStorage.panels)
    if (jQuery.inArray(active, panels) == -1) panels.push(active)
    localStorage.panels = JSON.stringify(panels)

    jQuery("[href='#" + active + "'] .fa-plus")
      .removeClass('fa-plus')
      .addClass('fa-minus')
  })

  jQuery('.collapse').on('hidden.bs.collapse', function () {
    var active = jQuery(this).attr('id')
    var panels =
      localStorage.panels === 'undefined' || localStorage.panels === undefined
        ? new Array()
        : JSON.parse(localStorage.panels)
    var elementIndex = jQuery.inArray(active, panels)
    if (elementIndex !== -1) {
      panels.splice(elementIndex, 1)
    }
    localStorage.panels = JSON.stringify(panels)

    jQuery("[href='#" + active + "'] .fa-minus")
      .removeClass('fa-minus')
      .addClass('fa-plus')
  })

  function onInit() {
    var panels =
      localStorage.panels === 'undefined' || localStorage.panels === undefined
        ? new Array()
        : JSON.parse(localStorage.panels)
    for (var i in panels) {
      if (jQuery('#' + panels[i]).hasClass('collapse')) {
        jQuery('#' + panels[i]).collapse('show')
        jQuery("[href='#" + panels[i] + "'] .fa-plus")
          .removeClass('fa-plus')
          .addClass('fa-minus')
      }
    }
  }

  onInit()
  onErrorOpenPanel()

  function onErrorOpenPanel() {
    document
      .querySelectorAll('.sonata-ba-field-error-messages')
      .forEach(function (element) {
        var panel = element.closest('.collapse')
        if (panel) {
          jQuery(panel).collapse('show')
        }
      })
  }
}
