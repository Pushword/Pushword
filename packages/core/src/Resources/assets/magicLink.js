const form = document.querySelector('form[data-magic-login]')
let submitted = false

function connect() {
  if (!form || submitted || document.prerendering || document.visibilityState !== 'visible') return
  submitted = true
  form.requestSubmit(form.querySelector('button[type="submit"]'))
}

document.addEventListener('prerenderingchange', connect)
document.addEventListener('visibilitychange', connect)
connect()
