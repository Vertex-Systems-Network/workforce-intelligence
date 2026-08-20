(function () {
  'use strict'

  function statusElement() {
    return document.getElementById('workintel-boot-status')
  }

  function show(title, message, error) {
    var status = statusElement()
    if (!status) return

    status.innerHTML = ''

    var box = document.createElement('div')
    box.style.maxWidth = '820px'
    box.style.padding = '24px'
    box.style.border = '1px solid #3f3f50'
    box.style.borderRadius = '12px'
    box.style.background = '#131318'
    box.style.textAlign = 'left'

    var heading = document.createElement('strong')
    heading.style.display = 'block'
    heading.style.marginBottom = '8px'
    heading.style.color = error ? '#ef4444' : '#f4f4f5'
    heading.textContent = title

    var detail = document.createElement('div')
    detail.style.opacity = '.82'
    detail.style.whiteSpace = 'pre-wrap'
    detail.style.wordBreak = 'break-word'
    detail.textContent = message || ''

    box.appendChild(heading)
    box.appendChild(detail)
    status.appendChild(box)
  }

  window.__WORKINTEL_BOOT_LOADER__ = true

  window.addEventListener('DOMContentLoaded', function () {
    var label = document.getElementById('workintel-boot-label')
    if (label) {
      label.textContent = 'Starting Workforce Intelligence…'
    }
  })

  window.addEventListener('error', function (event) {
    var source = event.filename ? '\nSource: ' + event.filename + (event.lineno ? ':' + event.lineno : '') : ''
    show(
      'Frontend JavaScript error',
      (event.message || 'A JavaScript file failed to load or execute.') + source,
      true
    )
  })

  window.addEventListener('unhandledrejection', function (event) {
    var reason = event.reason
    var message = reason && reason.stack
      ? reason.stack
      : reason && reason.message
        ? reason.message
        : String(reason || 'Unhandled promise rejection')

    show('Frontend startup error', message, true)
  })

  window.setTimeout(function () {
    if (window.__WORKINTEL_REACT_MOUNTED__) return

    var status = statusElement()
    if (!status) return

    show(
      'React bundle did not mount',
      'The Laravel page and JavaScript boot loader are working, but the Vite React entry did not mount within 5 seconds. Open DevTools → Network and check the app-*.js request status and Content-Type.',
      true
    )
  }, 5000)
})()
