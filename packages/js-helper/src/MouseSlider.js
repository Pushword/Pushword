export function mouseSlider(toSlide, speed = 1) {
    if (!('ontouchstart' in document.documentElement)) {
        toSlide.classList.add('overflow-x-hidden');
    }
    let isDown = false;
    let startX;
    let scrollLeft;
    toSlide.addEventListener('mousedown', (e) => {
        isDown = true;
        //toSlide.classList.add('active');
        startX = e.pageX - toSlide.offsetLeft;
        scrollLeft = toSlide.scrollLeft;
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
        const x = e.pageX - toSlide.offsetLeft;
        const walk = (x - startX) * speed;
        toSlide.scrollLeft = scrollLeft - walk;
    });
}
