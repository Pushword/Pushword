const cream = '#f2ecd9';
const pine = '#163b35';
const orange = '#e96839';
const reveal = (frame, from = 0, length = 24) => 1 - (1 - Math.max(0, Math.min(1, (frame - from) / length))) ** 4;

const shell = (html, background = pine, color = cream) => `
  <style>
    @font-face{font-family:NordHeading;src:url(../public/fonts/montserrat.ttf)}
    @font-face{font-family:NordBody;src:url(../public/fonts/work-sans.ttf)}
    .nord{position:absolute;inset:0;overflow:hidden;font-family:NordBody}
    .nord .photo{position:absolute;width:100%;height:100%;object-fit:cover}
    .nord .label{position:absolute;left:76px;top:108px;font-size:25px;letter-spacing:5px;text-transform:uppercase}
    .nord .word{overflow:hidden}
    .nord .word>div{font-family:NordHeading;font-weight:700;line-height:1.08}
    .nord svg{position:absolute;inset:0}
  </style>
  <div class="nord" style="background:${background};color:${color}">${html}</div>`;
const photo = (style = '', src = 'aurora.jpg') => `<img class="photo" src="../public/photos/${src}" alt="" style="${style}">`;
const label = (text, style = '', name = '') => `<div class="label ${name}" style="${style}">${text}</div>`;
const word = (text, from, style = '') => `<div class="word" data-from="${from}"><div style="${style}">${text}</div></div>`;
const svg = (html) => `<svg width="1080" height="1920" viewBox="0 0 1080 1920" aria-hidden="true">${html}</svg>`;
const style = (root, selector, values) => Object.assign(root.querySelector(selector).style, values);
const attr = (root, selector, name, value) => root.querySelector(selector).setAttribute(name, value);
function words(root, frame) {
  for (const element of root.querySelectorAll('.word')) {
    element.firstElementChild.style.transform = `translateY(${(1 - reveal(frame, Number(element.dataset.from))) * 115}%)`;
  }
}

const opening = {
  html: () => shell(`
    ${photo('object-position:52% center')}
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,#102b3566,transparent 45%,#102b35b8)"></div>
    ${label('Une parenthèse<br>au grand air', '', 'intro')}
    <div style="position:absolute;left:56px;right:40px;top:625px">${word('NORD.', 12, 'font-size:284px;letter-spacing:-19px')}</div>
    ${svg(`<path class="line" d="M 78 1090 C 270 1020 335 1190 510 1125 S 820 1040 1000 1100" pathLength="1" fill="none" stroke="${orange}" stroke-width="7" stroke-linecap="round" stroke-dasharray="1"/>
      <circle class="dot" cx="78" cy="1090" r="9" fill="${orange}"/>`)}
    ${label('Laisser une place<br>à l’inattendu.', 'top:auto;bottom:190px;letter-spacing:2px', 'tagline')}
    ${label('PUSHWORD / MOTION STUDY 01', 'left:auto;right:76px;top:auto;bottom:84px;font-size:19px;letter-spacing:3px')}
  `),
  render(root, frame) {
    words(root, frame);
    const line = reveal(frame, 22, 42);
    style(root, '.photo', {transform: `scale(${1.12 - frame * 0.0005})`});
    style(root, '.intro', {opacity: reveal(frame, 8)});
    style(root, '.tagline', {opacity: reveal(frame, 36)});
    attr(root, '.line', 'stroke-dashoffset', 1 - line);
    attr(root, '.dot', 'r', 9 * line);
  },
};

const route = {
  html: () => shell(`
    ${label('01 / Prendre le temps')}
    <div style="position:absolute;top:270px;left:76px;font-size:142px;letter-spacing:-7px">${word('Sortir', 5)}${word('du cadre.', 12)}</div>
    <div class="arch" style="position:absolute;right:-105px;top:745px;width:650px;height:840px;border-radius:340px 340px 0 0;overflow:hidden">
      ${photo('object-position:65% center')}
    </div>
    ${svg(`<path d="M 132 805 C 365 805 390 1050 190 1120 S 100 1390 350 1490" fill="none" stroke="#b4b8a9" stroke-width="3" stroke-dasharray="6 12"/>
      <path class="line" d="M 132 805 C 365 805 390 1050 190 1120 S 100 1390 350 1490" pathLength="1" fill="none" stroke="${orange}" stroke-width="7" stroke-linecap="round" stroke-dasharray="1"/>
      ${[[132,805,18],[190,1120,48],[350,1490,78]].map(([cx,cy,start]) => `<g class="stop" data-from="${start}"><circle cx="${cx}" cy="${cy}" r="20" fill="${cream}" stroke="${orange}" stroke-width="6"/></g>`).join('')}`)}
    ${[[190,745,'Marcher.',22],[270,1090,'Respirer.',52],[80,1580,'Ralentir.',82]].map(([left,top,text,start]) => `<div class="caption" data-from="${start}" style="position:absolute;left:${left}px;top:${top}px;font-size:37px">${text}</div>`).join('')}
    ${label('Un chemin. Pas une course.', 'top:auto;bottom:86px;font-size:20px;letter-spacing:2px')}
  `, cream, pine),
  render(root, frame) {
    words(root, frame);
    style(root, '.arch', {transform: `translateX(${(1 - reveal(frame, 8, 36)) * 250}px) rotate(9deg)`});
    style(root, '.photo', {transform: `scale(${1.04 + frame * 0.0003})`});
    attr(root, '.line', 'stroke-dashoffset', 1 - reveal(frame, 18, 68));
    for (const element of root.querySelectorAll('.stop, .caption')) {
      element.style.opacity = reveal(frame, Number(element.dataset.from), element.classList.contains('stop') ? 10 : 16);
    }
  },
};

const shelter = {
  html: () => shell(`
    ${photo('', 'camp.jpg')}
    <div style="position:absolute;inset:0;background:linear-gradient(180deg,#102b3599,transparent 56%,#102b35b3)"></div>
    ${label('02 / Habiter la nuit', '', 'intro')}
    <div style="position:absolute;top:285px;left:76px;font-size:112px;letter-spacing:-5px">${word('La nuit nous', 14)}${word('appartient.', 23)}</div>
    ${svg(`<path class="line" d="M 720 660 L 815 710 L 770 830 L 920 870" pathLength="1" fill="none" stroke="${cream}" stroke-width="2" opacity=".6" stroke-dasharray="1"/>
      ${[[720,660,35],[815,710,48],[770,830,62],[920,870,78]].map(([cx,cy,start]) => `<g class="star" data-from="${start}"><path d="M ${cx-10} ${cy} H ${cx+10} M ${cx} ${cy-10} V ${cy+10}" stroke="${cream}" stroke-width="3"/></g>`).join('')}`)}
    ${label('Couper le bruit.<br>Garder l’essentiel.', 'top:auto;bottom:180px;font-size:30px;letter-spacing:2px', 'tagline')}
  `),
  render(root, frame) {
    words(root, frame);
    style(root, '.photo', {transform: `scale(${1.11 - frame * 0.00035})`, clipPath: `inset(0 ${(1 - reveal(frame, 0, 30)) * 100}% 0 0)`});
    style(root, '.intro', {opacity: reveal(frame, 9)});
    style(root, '.tagline', {opacity: reveal(frame, 48)});
    attr(root, '.line', 'stroke-dashoffset', 1 - reveal(frame, 35, 45));
    for (const element of root.querySelectorAll('.star')) element.style.opacity = reveal(frame, Number(element.dataset.from), 10);
  },
};

const rhythm = {
  html: () => shell(`
    ${label('03 / Faire de la place')}
    <div style="position:absolute;left:76px;top:400px;font-size:105px;letter-spacing:-5px;line-height:1.35">
      ${['Explorer.', 'Ressentir.', 'Recommencer.'].map((text,index) => `<div style="margin-bottom:28px">${word(text, index * 12 + 5, `color:${index === 1 ? cream : pine}`)}</div>`).join('')}
    </div>
    <div class="wipe" style="position:absolute;left:76px;right:76px;top:1020px;height:520px;overflow:hidden">
      ${photo('object-position:center 65%')}
    </div>
    ${label('Le plus beau programme :<br>ne pas tout prévoir.', 'top:auto;bottom:160px;letter-spacing:2px;font-size:28px', 'tagline')}
  `, orange, pine),
  render(root, frame) {
    words(root, frame);
    style(root, '.wipe', {clipPath: `inset(0 ${(1 - reveal(frame, 25, 30)) * 100}% 0 0)`});
    style(root, '.photo', {transform: `scale(${1.2 - frame * 0.001})`});
    style(root, '.tagline', {opacity: reveal(frame, 48)});
  },
};

const closing = {
  html: () => shell(`
    <div class="circle" style="position:absolute;width:720px;height:720px;left:180px;top:-185px;border-radius:50%;overflow:hidden">
      ${photo('object-position:center 30%')}
    </div>
    <div style="position:absolute;top:680px;left:76px;font-size:122px;letter-spacing:-6px">${word('Le monde', 14)}${word('est dehors.', 23)}</div>
    <div class="tagline" style="position:absolute;left:80px;top:1070px;font-size:36px">À vous d’écrire la suite.</div>
    ${svg(`<g class="arrow"><circle cx="176" cy="1370" r="96" fill="${orange}"/>
      <path d="M 130 1370 H 220 M 190 1340 L 220 1370 L 190 1400" fill="none" stroke="${pine}" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/></g>`)}
    ${label('NORD.', 'top:auto;bottom:150px;font-family:NordHeading;font-size:56px;letter-spacing:-2px')}
    ${label('PUSHWORD REPURPOSE / MOTION STUDY 01', 'top:auto;bottom:86px;font-size:19px;letter-spacing:3px')}
  `, cream, pine),
  render(root, frame) {
    words(root, frame);
    style(root, '.circle', {transform: `translateY(${(1 - reveal(frame, 0, 36)) * -240}px)`});
    style(root, '.photo', {transform: `scale(${1.1 - frame * 0.0004})`});
    style(root, '.tagline', {opacity: reveal(frame, 44)});
    attr(root, '.arrow', 'transform', `translate(${(1 - reveal(frame, 48, 25)) * -70} 0)`);
    attr(root, '.arrow', 'opacity', reveal(frame, 48));
  },
};

export const scenes = {'slide-1': opening, 'slide-2': route, 'slide-3': shelter, 'slide-4': rhythm, 'slide-5': closing};
