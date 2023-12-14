/**
 * Demo in Draft
 */

/**
 * Demo in Draft
 */

class ScrollYEnhancer {
  constructor(selector = '.enhance-scroll-y', arrow = '<div class="scroller absolute left-[128px] z-10 -mt-[10px] h-[36px] w-[36px] cursor-pointer rounded-full border border-gray-200 bg-white text-center text-3xl leading-[23px] text-gray-500 hover:text-gray-700 select-none" onclick="scrollPreviousDiv(this)">⌄</div><div class="relative z-0 -mt-8 h-8 w-full bg-gradient-to-t from-white to-transparent"></div>', fadeout = '<div class="sticky left-0 -top-3 z-0 -mt-3 h-3 w-full bg-gradient-to-b from-white to-transparent"></div>') {
    window.scrollPreviousDiv = this.scrollPreviousDiv
    window.manageScrollYControllerVisibility = this.manageScrollYControllerVisibility

    document.querySelectorAll(selector).forEach((element) => {
      if (element.scrollHeight <= element.clientHeight - 20) {
        // 20 = padding-bottom
        element.classList.remove('enhance-scroll-y')
        return
      }

      this.arrow = element.dataset.arrow ?? arrow
      this.fadeout = element.dataset.fadeout ?? fadeout
      element.classList.remove(selector)
      this.enhanceScrollY(element)
      this.mouseSliderY(element)
      this.wheelScrollY(element)
      element.onscroll = function () {
        manageScrollYControllerVisibility(this)
      }
    })
  }

  wheelScrollY(element) {
    element.addEventListener('wheel', (evt) => {
      if (window.isScrolling === true) return
      evt.preventDefault()
      window.isScrolling = true

      const before = element.scrollTop
      element.scrollTop += evt.deltaY

      if (before === element.scrollTop) {
        if ((parent = element.closest('.enhance-scroll-x')) && new Date().getTime() - window.lastScrollTime > 200 && scrollX(parent.parentNode.querySelector(evt.deltaY > 0 ? '.scroll-right' : '.scroll-left'))) {
          window.lastScrollTime = new Date().getTime()
          window.isScrolling = false
          return
        }

        if (new Date().getTime() - window.lastScrollTime > 200) {
          window.lastScrollTime = new Date().getTime()
          const toScrollHeight = element.dataset.toscroll ?? 600
          window.scrollBy({ top: evt.deltaY > 0 ? toScrollHeight : -toScrollHeight, left: 0, behavior: 'smooth' })
        }
      } else window.lastScrollTime = new Date().getTime()
      window.isScrolling = false
    })
  }

  enhanceScrollY(element) {
    if (element.scrollHeight <= element.clientHeight) return
    element.insertAdjacentHTML('afterBegin', this.fadeout)
    element.insertAdjacentHTML('afterEnd', this.arrow)
  }

  scrollPreviousDiv(element) {
    const previousDiv = element.previousElementSibling
    if (!previousDiv) return
    if (element.textContent === '⌄') {
      previousDiv.scrollTop += 25 // ~ one line
      return
    }
    previousDiv.scrollTop = 0
  }

  manageScrollYControllerVisibility(element) {
    const scroller = element.parentNode.querySelector('.scroller')
    const isAtMaxScroll = element.scrollTop >= element.scrollHeight - element.clientHeight - 8
    if (scroller.textContent === '⌄' || isAtMaxScroll) {
      if (isAtMaxScroll) {
        scroller.textContent = '⌃'
        scroller.classList.add('pt-[12px]')
        scroller.classList.add('text-gray-200')
      }
      return
    } else {
      scroller.textContent = '⌄'
      scroller.classList.remove('pt-[12px]')
      scroller.classList.remove('text-gray-200')
    }
  }

  mouseSliderY(toSlide, speed = 1) {
    if ('ontouchstart' in document.documentElement) {
      return
    }
    toSlide.classList.add('overflow-y-hidden')
    let isDown = false
    let startX
    let scrollTop
    toSlide.addEventListener('mousedown', (e) => {
      isDown = true
      //toSlide.classList.add('active');
      startX = e.pageY - toSlide.offsetTop
      scrollTop = toSlide.scrollTop
    })
    toSlide.addEventListener('mouseleave', () => {
      isDown = false
      //toSlide.classList.remove('active');
    })
    toSlide.addEventListener('mouseup', () => {
      isDown = false
      //toSlide.classList.remove('active');
    })
    toSlide.addEventListener('mousemove', (e) => {
      if (!isDown) return
      e.preventDefault()
      const x = e.pageY - toSlide.offsetTop
      const walk = (x - startX) * speed
      toSlide.scrollTop = scrollTop - walk
    })
  }
}

class ScrollXEnhancer {
  constructor(selector = '.enhance-scroll-x', arrowRight = '<div class="scroll-right relative left-[calc(100vw-62px)] -mt-[36px] top-1/3 z-20 h-[36px] w-[36px] cursor-pointer select-none rounded-full border border-gray-200 bg-white text-center text-3xl leading-none text-gray-500 hover:text-gray-700" onclick="scrollX(this)">›</div>', arrowLeft = '<div class="scroll-left relative left-[22px] top-1/3 z-20 h-[36px] w-[36px] cursor-pointer select-none rounded-full border border-gray-200 bg-white text-center text-3xl leading-none text-gray-500 hover:text-gray-700" onclick="scrollX(this)">‹</div>') {
    window.scrollLeft = this.scrollLeft
    window.scrollX = this.scrollX
    window.manageScrollXControllerVisibility = this.manageScrollXControllerVisibility

    document.querySelectorAll(selector).forEach((element) => {
      if (element.scrollWidth <= element.clientWidth - 12) {
        // 20 = padding-bottom
        element.classList.remove('enhance-scroll-x')
        return
      }

      this.arrowLeft = element.dataset.arrowleft ?? arrowLeft
      this.arrowRight = element.dataset.arrowright ?? arrowRight
      element.classList.remove(selector)
      this.enhanceScrollX(element)
      this.mouseSliderX(element)
      this.wheelScrollX(element)
      element.onscroll = function () {
        manageScrollXControllerVisibility(this)
      }
    })
  }

  wheelScrollX(element) {
    element.addEventListener('wheel', (evt) => {
      if (window.isScrolling === true) return
      evt.preventDefault()
      window.isScrolling = true

      if (evt.target.closest('.enhance-scroll-y')) {
        window.isScrolling = false
        return
      }

      const before = element.scrollLeft
      element.scrollLeft += evt.deltaY

      if (before === element.scrollLeft) {
        if (new Date().getTime() - window.lastScrollTime > 200) {
          window.lastScrollTime = new Date().getTime()
          const toScrollHeight = element.dataset.toscroll ?? 600
          window.scrollBy({ top: evt.deltaY > 0 ? toScrollHeight : -toScrollHeight, left: 0, behavior: 'smooth' })
        }
      } else window.lastScrollTime = new Date().getTime()
      window.isScrolling = false
    })
  }

  enhanceScrollX(element) {
    if (element.scrollWidth <= element.clientWidth) return
    element.insertAdjacentHTML('beforebegin', this.arrowLeft + this.arrowRight)
  }

  scrollX(scroller, selector = '.enhance-scroll-x') {
    const element = scroller.parentNode.querySelector(selector)
    if (!element) return

    const scrollToRight = scroller.classList.contains('scroll-right')

    const nextElementToScroll = element.children[3] // work only with equal width block
    const toScrollWidth = nextElementToScroll.offsetWidth + parseInt(window.getComputedStyle(nextElementToScroll).marginLeft)
    const before = element.scrollLeft
    element.scrollLeft += scrollToRight ? toScrollWidth : -toScrollWidth
    return before !== element.scrollLeft
  }

  manageScrollXControllerVisibility(element) {
    const scrollLeftElement = element.parentNode.querySelector('.scroll-left')
    const scrollRightElement = element.parentNode.querySelector('.scroll-right')
    scrollRightElement.classList.remove('opacity-30')
    scrollLeftElement.classList.remove('opacity-30')

    const isAtMaxScroll = element.scrollLeft >= element.scrollWidth - element.clientWidth
    if (isAtMaxScroll) scrollRightElement.classList.add('opacity-30')
    if (element.scrollLeft === 0) scrollLeftElement.classList.add('opacity-30')
  }

  mouseSliderX(toSlide, speed = 1) {
    if ('ontouchstart' in document.documentElement) {
      return
    }
    toSlide.classList.add('overflow-x-hidden')
    let isDown = false
    let startX
    let scrollLeft
    toSlide.addEventListener('mousedown', (e) => {
      isDown = true
      startX = e.pageX - toSlide.offsetLeft
      scrollLeft = toSlide.scrollLeft
    })
    toSlide.addEventListener('mouseleave', () => {
      isDown = false
    })
    toSlide.addEventListener('mouseup', () => {
      isDown = false
    })
    toSlide.addEventListener('mousemove', (e) => {
      if (!isDown) return
      e.preventDefault()
      const x = e.pageX - toSlide.offsetLeft
      const walk = (x - startX) * speed
      toSlide.scrollLeft = scrollLeft - walk
    })
  }
}


module.exports = {
  ScrollXEnhancer: ScrollXEnhancer,
  ScrollYEnhancer: ScrollYEnhancer,
}
