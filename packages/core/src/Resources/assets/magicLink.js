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

const resendButton = document.querySelector('[data-magic-resend]')
const resendStatus = document.querySelector('[data-magic-resend-status]')

if (resendButton && resendStatus) {
  const availableAt = Date.now() + Number(resendButton.dataset.resendSeconds) * 1000
  let timer

  function updateResend() {
    const seconds = Math.max(0, Math.ceil((availableAt - Date.now()) / 1000))
    resendButton.disabled = seconds > 0
    resendStatus.hidden = seconds === 0
    if (seconds > 0) {
      resendStatus.textContent = resendStatus.dataset.waitMessage.replace('{seconds}', seconds)
    } else {
      clearInterval(timer)
    }
  }

  updateResend()
  if (resendButton.disabled) timer = setInterval(updateResend, 1000)
  document.addEventListener('visibilitychange', updateResend)
}
