class HorizontalScroll {
  constructor(selectorToFindElementToScroll, scrollerSelectorOrContainer = null) {
    this.elementToScroll = document.querySelector(selectorToFindElementToScroll)
    this.scrollContainer = this.elementToScroll.querySelector('div:nth-child(2)')
    this.scroller =
      scrollerSelectorOrContainer instanceof HTMLElement
        ? scrollerSelectorOrContainer
        : document.querySelector(scrollerSelectorOrContainer || selectorToFindElementToScroll + '-scroller')
    this.scrollWidth = this.scrollContainer.offsetWidth + parseInt(window.getComputedStyle(this.scrollContainer).marginLeft)
    this.scrollContainerWidth = this.elementToScroll.scrollWidth - this.elementToScroll.clientWidth
  }

  init() {
    if (!('ontouchstart' in document.documentElement)) {
      this.elementToScroll.classList.add('overflow-x-hidden')
      this.scroller.classList.toggle('hidden')
    }
    return this
  }

  activateWheelScroll() {
    this.elementToScroll.addEventListener(
      'wheel',
      (evt) => {
        evt.preventDefault()
        this.elementToScroll.classList.toggle('scroll-smooth')
        this.elementToScroll.scrollLeft += evt.deltaY
        this.elementToScroll.classList.toggle('scroll-smooth')
      },
      { passive: true },
    )
    return this
  }

  scroll(scrollerClassToToggle = 'opacity-50') {
    const isRightScroll = window.event.target == this.scroller.children[1] || window.event.target.parentNode == this.scroller.children[1]
    const scrollPos = isRightScroll ? (this.elementToScroll.scrollLeft += this.scrollWidth) : (this.elementToScroll.scrollLeft -= this.scrollWidth)

    this.scroller.children[1].classList.toggle(scrollerClassToToggle, scrollPos >= this.scrollContainerWidth)
    this.scroller.children[1].classList.toggle('cursor-pointer', scrollPos < this.scrollContainerWidth)
    this.scroller.children[0].classList.toggle(scrollerClassToToggle, scrollPos <= 0)
    this.scroller.children[0].classList.toggle('cursor-pointer', scrollPos > 0)
  }
}

/*
 * Demo : https://codepen.io/PiedWeb/pen/ExrNWvP
 *
<script src="https://cdn.tailwindcss.com"></script>

<script src="https://cdn.tailwindcss.com"></script>

<div class="group/scroll relative max-w-[900px] m-3">
  <div id="toScroll" class="flex flex-nowrap space-x-3 overflow-x-hidden scroll-smooth">
    <div class="w-[250px] flex-none last:pr-8">
      <div class="flex w-full items-center justify-center bg-red-500 p-4">-</div>
    </div>
    <div class="w-[250px] flex-none last:pr-8">
      <div class="flex w-full items-center justify-center bg-orange-500 p-4">-</div>
    </div>
    <div class="w-[250px] flex-none last:pr-8">
      <div class="flex w-full items-center justify-center bg-blue-500 p-4">-</div>
    </div>
    <div class="w-[250px] flex-none last:pr-8">
      <div class="flex w-full items-center justify-center bg-green-500 p-4">-</div>
    </div>
  </div>
  <div class="mt-3 flex group-hover/scroll:flex justify-end" onclick="(new HorizontalScroll('#toScroll', this)).scroll('#toScroll')">
    <div class="cursor-default bg-slate-500 px-3 py-1 text-2xl text-white opacity-50">❮</div>
    <div class="cursor-default cursor-pointer ml-1 bg-slate-500 px-3 py-1 text-2xl text-white">❯</div>
  </div>
</div>

<hr>

<div class="relative m-3 max-w-[500px]">
  <div id="toScroll2" class="flex flex-nowrap space-x-3 scroll-smooth overflow-auto">
    <div class="w-[250px] flex-none last:pr-8">
      <div class="flex w-full items-center justify-center bg-red-500 p-4">-</div>
    </div>
    <div class="w-[250px] flex-none last:pr-8">
      <div class="flex w-full items-center justify-center bg-orange-500 p-4">-</div>
    </div>
    <div class="w-[250px] flex-none last:pr-8">
      <div class="flex w-full items-center justify-center bg-blue-500 p-4">-</div>
    </div>
    <div class="w-[250px] flex-none last:pr-8">
      <div class="flex w-full items-center justify-center bg-green-500 p-4">-</div>
    </div>
  </div>
  <div id="toScroll2-scroller" class="hidden" onclick="(new HorizontalScroll('#toScroll2', this)).scroll('hidden')">
    <div class="hidden cursor-default cursor-pointer bg-white px-2 text-2xl text-gray-600 white bg-opacity-50
                absolute h-full top-0 left-0 flex items-center"><span>❮</span></div>
    <div class="cursor-default cursor-pointer bg-white px-2 text-2xl text-gray-600
                absolute h-full top-0 right-0 flex items-center bg-opacity-50"><span>❯</span></div>
  </div>
  <script>document.addEventListener('DOMContentLoaded', function() {
     (new HorizontalScroll('#toScroll2')).init().activateWheelScroll();

    })
  </script>
</div>

<h1>test</h1>
<p class="h-[2000px] bg-slate-50">Lorem</p>
 */
