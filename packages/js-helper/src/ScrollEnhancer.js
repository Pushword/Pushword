/**
 * Demo in Draft
 */

class ScrollYEnhancer {
    constructor(
        selector = '.enhance-scroll-y',
        chevron = '<div class="scroller absolute left-[128px] z-10 -mt-[10px] h-[44px] w-[44px] cursor-pointer rounded-full border border-gray-200 bg-white text-center text-3xl leading-none text-gray-600 hover:bg-gray-100 select-none" onclick="scrollPreviousDiv(this)">⌄</div><div class="relative z-0 -mt-8 h-8 w-full bg-gradient-to-t from-white to-transparent"></div>',
        insertAfterBegin = '<div class="fixed left-0 z-0 -mt-3 h-3 w-full bg-gradient-to-b from-white to-transparent"></div>'
    ) {
        this.chevron = chevron;
        this.insertAfterBegin = insertAfterBegin;
        window.scrollPreviousDiv = this.scrollPreviousDiv;
        window.manageScrollYControllerVisibility = this.manageScrollYControllerVisibility;

        document.querySelectorAll(selector).forEach((element) => {
          this.enhanceScrollY(element)
          this.mouseSliderY(element)
          this.wheelScroll(element)
          element.onscroll = function () {
            manageScrollYControllerVisibility(this)
          }
        })
      }

      wheelScroll(element) {
        element.addEventListener('wheel', (evt) => {
          evt.preventDefault()
          element.classList.toggle('scroll-smooth')
          element.scrollTop += evt.deltaY
          element.classList.toggle('scroll-smooth')
        })
        return this
      }

    enhanceScrollY(element) {
        if (element.scrollHeight <= element.clientHeight) return;
        element.insertAdjacentHTML('afterBegin', this.insertAfterBegin);
        element.insertAdjacentHTML('afterEnd', this.chevron);
    }

    scrollPreviousDiv(element) {
        const previousDiv = element.previousElementSibling;
        if (!previousDiv) return;
        if (element.textContent === '⌄') {
            previousDiv.scrollTop += 25; // ~ one line
            return;
        }
        previousDiv.scrollTop = 0;
    }

    manageScrollYControllerVisibility(element) {
        const scroller = element.parentNode.querySelector('.scroller');
        if (scroller.textContent === '⌄') {
            const isAtMaxScroll = element.scrollTop >= element.scrollHeight - element.clientHeight - 10;
            if (isAtMaxScroll) {
                scroller.textContent = '⌃';
                scroller.classList.add('pt-[11px]');
                scroller.classList.add('text-gray-200');
            }
            return;
        } else {
            scroller.textContent = '⌄';
            scroller.classList.remove('pt-[11px]');
            scroller.classList.remove('text-gray-200');
        }
    }

    mouseSliderY(toSlide, speed = 1) {
        if ('ontouchstart' in document.documentElement) {
            return;
        }
        toSlide.classList.add('overflow-y-hidden');
        let isDown = false;
        let startX;
        let scrollTop;
        toSlide.addEventListener('mousedown', (e) => {
            isDown = true;
            //toSlide.classList.add('active');
            startX = e.pageY - toSlide.offsetTop;
            scrollTop = toSlide.scrollTop;
        });
        toSlide.addEventListener('mouseleave', () => {
            isDown = false;
            //toSlide.classList.remove('active');
        });
        toSlide.addEventListener('mouseup', () => {
            isDown = false;
            //toSlide.classList.remove('active');
        });
        toSlide.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageY - toSlide.offsetTop;
            const walk = (x - startX) * speed;
            toSlide.scrollTop = scrollTop - walk;
        });
    }
}

class ScrollXEnhancer {
    constructor(
        selector = '.enhance-scroll-x',
        chevronRight = '<div class="scroll-right fixed right-0 top-1/3 z-20 h-[44px] w-[44px] cursor-pointer select-none rounded-full border border-gray-200 bg-white pt-[6px] text-center text-3xl leading-none text-gray-600 hover:bg-gray-100" onclick="scrollX(this)">🠆</div>',
        chevronLeft = '<div class="scroll-left fixed left-[22px] top-1/3 z-20 h-[44px] w-[44px] cursor-pointer select-none rounded-full border border-gray-200 bg-white pt-[6px] text-center text-3xl leading-none text-gray-600 hover:bg-gray-100" onclick="scrollX(this)">🠄</div>'
    ) {
        this.chevronLeft = chevronLeft;
        this.chevronRight = chevronRight;
        window.scrollLeft = this.scrollLeft;
        window.scrollX = this.scrollX;
        window.manageScrollXControllerVisibility = this.manageScrollXControllerVisibility;

        document.querySelectorAll(selector).forEach((element) => {
          this.enhanceScrollX(element)
          this.mouseSliderX(element)
          this.wheelScroll(element)
          element.onscroll = function () {
            manageScrollXControllerVisibility(this)
          }
        })
      }

      wheelScroll(element) {
        element.addEventListener('wheel', (evt) => {
          evt.preventDefault()
          if (evt.target.closest('.enhance-scroll-y')) return
          if (window.isScrolling === true) return
          element.classList.toggle('scroll-smooth')
          element.scrollLeft += evt.deltaY
          element.classList.toggle('scroll-smooth')
        })
      }

    enhanceScrollX(element) {
        if (element.scrollWidth <= element.clientWidth) return;
        element.insertAdjacentHTML('afterbegin', this.chevronLeft + this.chevronRight);
    }

    scrollX(scroller) {
        const element = scroller.parentNode;
        if (!element) return;

        const scrollToRight = scroller.classList.contains('scroll-right');

        const oppositeSelector = scrollToRight ? 'scroll-left' : 'scroll-right';
        const oppositeController = element.querySelector('.' + oppositeSelector);

        const nextElementToScroll = element.children[3]; // work only with equal width block
        const toScrollWidth =
            nextElementToScroll.offsetWidth +
            parseInt(window.getComputedStyle(nextElementToScroll).marginLeft);
        element.scrollLeft += scrollToRight ? toScrollWidth : -toScrollWidth;

    }

    manageScrollXControllerVisibility(element) {
        const scrollLeftElement = element.querySelector('.scroll-left');
        const scrollRightElement = element.querySelector('.scroll-right');
        scrollRightElement.classList.remove('opacity-30');
        scrollLeftElement.classList.remove('opacity-30');

        const isAtMaxScroll = element.scrollLeft >= element.scrollWidth - element.clientWidth;
        if (isAtMaxScroll) scrollRightElement.classList.add('opacity-30');
        if (element.scrollLeft === 0) scrollLeftElement.classList.add('opacity-30');
    }

    mouseSliderX(toSlide, speed = 1) {
        if ('ontouchstart' in document.documentElement) {
            return;
        }
        toSlide.classList.add('overflow-x-hidden');
        let isDown = false;
        let startX;
        let scrollLeft;
        toSlide.addEventListener('mousedown', (e) => {
            isDown = true;
            startX = e.pageX - toSlide.offsetLeft;
            scrollLeft = toSlide.scrollLeft;
        });
        toSlide.addEventListener('mouseleave', () => {
            isDown = false;
        });
        toSlide.addEventListener('mouseup', () => {
            isDown = false;
        });
        toSlide.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - toSlide.offsetLeft;
            const walk = (x - startX) * speed;
            toSlide.scrollLeft = scrollLeft - walk;
        });
    }
}
