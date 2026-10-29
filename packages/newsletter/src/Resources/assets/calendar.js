import { Calendar } from 'fullcalendar/all'
import classicTheme from 'fullcalendar/themes/classic'
import fr from 'fullcalendar/locales/fr'
import de from 'fullcalendar/locales/de'
import it from 'fullcalendar/locales/it'
import nl from 'fullcalendar/locales/nl'
import 'fullcalendar/skeleton.css'
import 'fullcalendar/themes/classic/theme.css'
import 'fullcalendar/themes/classic/palette.css'
import './calendar.css'
import { saveMove } from './calendar-move.js'

const element = document.querySelector('[data-newsletter-calendar]')
if (element) {
  const config = element.dataset
  const feedback = document.querySelector('[data-calendar-feedback]')
  const events = JSON.parse(config.events)
  const palette = ['#365d88', '#645388', '#386b59', '#86563c', '#786420']
  const colorFor = (audience) => {
    let hash = 0
    for (const letter of audience || '') hash = ((hash * 31) + letter.codePointAt(0)) >>> 0
    return palette[hash % palette.length]
  }
  for (const event of events) {
    event.color = colorFor(event.extendedProps.audience)
  }
  let saving = false
  const createDraft = (date) => {
    const url = new URL(config.createUrl, window.location.href)
    url.searchParams.set('scheduledAt', date)
    window.location.assign(url.href)
  }
  const calendar = new Calendar(element, {
    plugins: [classicTheme],
    locales: [fr, de, it, nl],
    locale: config.locale.replace('_', '-'),
    timeZone: config.timezone,
    initialView: 'dayGridMonth',
    monthText: config.month,
    weekTextLong: config.week,
    yearText: config.year,
    listText: config.agenda,
    height: 'auto',
    eventInteractive: true,
    eventDurationEditable: false,
    eventAllow: () => !saving,
    dayMaxEvents: 3,
    nowIndicator: true,
    noEventsContent: config.empty,
    dayCellClass: 'pw-calendar-day',
    navLinks: true,
    navLinkHint: (dateText) => config.createHint.replace('%date%', dateText),
    navLinkDayClick: (date) => createDraft(calendar.formatIso(date, true)),
    dateClick: ({ dateStr }) => createDraft(dateStr),
    events,
    eventDidMount({ event, el }) {
      const { audience, source, state } = event.extendedProps
      const label = [event.title, audience, state, source].filter(Boolean).join(' · ')
      el.title = label
      el.setAttribute('aria-label', label)
    },
    eventContent({ event, timeText, view }) {
      const wrapper = document.createElement('span')
      wrapper.className = 'pw-calendar-event' + (view.type.startsWith('list') ? ' pw-calendar-event-agenda' : '')
      const title = document.createElement('strong')
      title.textContent = [timeText, event.title].filter(Boolean).join(' ')
      const detail = document.createElement('small')
      detail.textContent = [event.extendedProps.state, event.extendedProps.audience, event.extendedProps.source].filter(Boolean).join(' · ')
      wrapper.append(title, detail)
      return { domNodes: [wrapper] }
    },
    async eventDrop(info) {
      saving = true
      element.setAttribute('aria-busy', 'true')
      try {
        await saveMove(info, config.timezone, config.token)
        feedback.textContent = config.saved
        feedback.className = 'pw-calendar-feedback alert alert-success'
      } catch (error) {
        info.revert()
        feedback.textContent = error.message === 'conflict' ? config.conflict : config.error
        feedback.className = 'pw-calendar-feedback alert alert-danger'
      } finally {
        saving = false
        element.removeAttribute('aria-busy')
        feedback.hidden = false
      }
    },
  })
  const responsiveToolbar = () => {
    const narrow = element.clientWidth < 650
    calendar.setOption('headerToolbar', narrow
      ? { start: 'prev,next today', end: 'title' }
      : { start: 'prev,next today', center: 'title', end: 'dayGridMonth,timeGridWeek,multiMonthYear,listMonth' })
    calendar.setOption('footerToolbar', narrow ? { start: 'dayGridMonth,timeGridWeek,multiMonthYear,listMonth' } : false)
  }
  calendar.setOption('windowResize', responsiveToolbar)
  responsiveToolbar()
  calendar.render()
}
