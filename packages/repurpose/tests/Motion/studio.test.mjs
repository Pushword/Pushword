import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {test} from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../src/Resources/public/repurpose.js', import.meta.url), 'utf8');
const origin = 'https://example.test';
function studio(config = {}, fetch, confirm, globals = {}) {
  let factory;
  const window = {location: {origin}, confirm, RP_STUDIO: {
    spec: {slides: [{title: 'Test', images: [{}]}]},
    urls: {mediaIndex: '/admin/media', mediaUpload: '/admin/media/new'},
    messages: {videoInvalid: 'Choose a video', audioInvalid: 'Choose audio'},
    ...config,
  }, Alpine: {data: (_, value) => { factory = value; }}, addEventListener() {}, removeEventListener() {}};
  vm.runInNewContext(source, {
    __stryker__: {activeMutant: process.env.__STRYKER_ACTIVE_MUTANT__},
    window, fetch, document: {addEventListener: (_, callback) => callback()}, ...globals,
  });
  return factory();
}
function selection(fileName, eventOrigin = origin) {
  return {origin: eventOrigin, data: {type: 'pw-media-picker-select', media: {fileName}}};
}

test('a revision check detects another save while keeping the local draft intact', async () => {
  let current = '"1-1"';
  const requests = [];
  const state = studio({revision: current, urls: {revision: '/revision', save: '/save'}}, async url => {
    requests.push(url);
    return {ok: true, json: async () => ({revision: current})};
  });

  await state.checkRevision();
  assert.equal(state.syncState, '');
  state.spec.slides[0].title = 'Local edit';
  state.dirty = true;
  current = '"1-2"';
  await state.checkRevision();
  assert.equal(state.syncState, 'changed');
  assert.equal(state.revision, '"1-1"');
  assert.equal(state.spec.slides[0].title, 'Local edit');
  assert.equal(state.dirty, true);
  await state.save();
  assert.deepEqual(requests, ['/revision', '/revision'], 'a stale tab cannot submit another save');
});

test('a revision check retries after a network error and detects a deleted carousel', async () => {
  const requests = [];
  const state = studio({revision: '"1-1"', urls: {revision: '/revision', save: '/save'}}, async url => {
    requests.push(url);
    if (requests.length === 1) {
      throw new TypeError('Network error');
    }
    return {ok: false, status: 404};
  });
  state.spec.slides[0].title = 'Local edit';
  state.dirty = true;
  await state.checkRevision();
  assert.equal(state.syncState, '');
  await state.checkRevision();
  assert.equal(state.syncState, 'deleted');
  assert.equal(state.spec.slides[0].title, 'Local edit');
  assert.equal(state.dirty, true);
  await state.save();
  assert.deepEqual(requests, ['/revision', '/revision']);
});

for (const [status, syncState] of [[409, 'changed'], [404, 'deleted']]) {
  test(`save returning ${status} leaves the local draft available`, async () => {
    const revision = '"1-1"';
    const state = studio({revision, urls: {save: '/save'}}, async (url, options) => {
      assert.equal(url, '/save');
      assert.equal(options.headers['If-Match'], revision);
      assert.equal(JSON.parse(options.body).slides[0].title, 'Local edit');
      return {status};
    }, undefined, {setTimeout() {}});

    state.spec.slides[0].title = 'Local edit';
    state.dirty = true;
    await state.save();
    assert.equal(state.syncState, syncState);
    assert.equal(state.revision, revision);
    assert.equal(state.spec.slides[0].title, 'Local edit');
    assert.equal(state.dirty, true);
  });
}

test('a successful save keeps edits typed during the request and ignores an older revision check', async () => {
  let resolveCheck;
  let resolveSave;
  const state = studio({revision: '"1-1"', urls: {revision: '/revision', save: '/save'}}, url => {
    if (url === '/revision') {
      return new Promise(resolve => { resolveCheck = resolve; });
    }
    return new Promise(resolve => { resolveSave = resolve; });
  }, undefined, {setTimeout() {}});

  const check = state.checkRevision();
  const save = state.save();
  state.spec.slides[0].title = 'Typed during save';
  resolveSave({ok: true, json: async () => ({revision: '"1-2"'})});
  await save;
  resolveCheck({ok: true, json: async () => ({revision: '"1-1"'})});
  await check;

  assert.equal(state.syncState, '');
  assert.equal(state.revision, '"1-2"');
  assert.equal(state.spec.slides[0].title, 'Typed during save');
  assert.equal(state.dirty, true);
});

for (const [view, shortcut] of [['visual', {key: 's', ctrlKey: true}], ['source', {key: 'S', metaKey: true}]]) {
  test(`the save shortcut submits the current ${view} edit`, async () => {
    const revision = '"1-1"';
    const requests = [];
    const state = studio({revision, urls: {save: '/save'}}, async (url, options) => {
      requests.push(url);
      assert.equal(options.headers['If-Match'], revision);
      assert.equal(JSON.parse(options.body).slides[0].title, 'Edited title');
      return {ok: true, json: async () => ({revision: '"1-2"'})};
    }, undefined, {setTimeout() {}});
    state.view = view;
    if (view === 'source') {
      state.$refs = {source: {value: '{"slides":[{"title":"Edited title"}]}'}};
    } else {
      state.spec.slides[0].title = 'Edited title';
    }

    let prevented = false;
    await state.editorKey({...shortcut, preventDefault() { prevented = true; }});

    assert.equal(prevented, true);
    assert.deepEqual(requests, ['/save']);
    assert.equal(state.revision, '"1-2"');
    assert.equal(state.saveLabel, 'Saved ✓');
  });
}

test('the save shortcut leaves other keys alone and ignores key repeats', () => {
  const state = studio();
  state.view = 'source';
  state.save = () => assert.fail('Unexpected save');
  let prevented = 0;
  const press = (key, modifiers) => state.editorKey({key, ...modifiers, preventDefault() { prevented += 1; }});

  press('s');
  press('a', {ctrlKey: true});
  press('s', {ctrlKey: true, altKey: true});
  press('S', {ctrlKey: true, shiftKey: true});
  assert.equal(prevented, 0);

  press('s', {ctrlKey: true, repeat: true});
  assert.equal(prevented, 1);
});

test('a selected block snaps to nearby guides on both axes unless Alt is held', async () => {
  const moved = {content: 'Move this', x: 0.1, y: 0.2, width: 0.3};
  const preview = ['<svg/>'];
  let overlay;
  const state = studio({spec: {slides: [{texts: [moved]}]}},
    async () => ({ok: true, json: async () => ({slides: preview})}), undefined, {
      document: {
        addEventListener: (_, callback) => callback(),
        createElement: () => ({style: {}, lines: [], setAttribute() {},
          classList: {toggle(name, on) { this[name] = on; }},
          append(line) { this.lines.push(line); }, remove() { overlay = undefined; }}),
      },
      performance: {now: () => 1000},
    });
  state.$nextTick = () => {};
  state._previewTargets = state.previewTargets();
  let transform;
  const block = {
    getAttribute: () => 'texts.0',
    getBBox: () => ({x: 120, y: 110, width: 150, height: 55}),
    setAttribute: (_, value) => { transform = value; },
    removeAttribute: () => { transform = undefined; },
    ownerSVGElement: {
      viewBox: {baseVal: {x: 0, y: 0, width: 1000, height: 500}},
      getScreenCTM: () => ({a: 0.5, d: 0.5}),
      querySelectorAll: () => [block,
        {getBBox: () => ({x: 200, y: 100, width: 400, height: 200})},
        {getBBox: () => ({x: 0, y: 0, width: 1000, height: 200})}],
      after: element => { overlay = element; },
    },
  };
  const start = {target: {closest: () => block}, currentTarget: {focus() {}}, button: 0, pointerId: 1,
    clientX: 100, clientY: 100, preventDefault() {}};
  const move = {pointerId: 1, clientX: 138, clientY: 115};
  state.deckTextDown(0, start);
  assert.equal(state.textSelection.outline.left, '12%', 'selection outlines the visible block before movement');
  assert.equal(state.textSelection.outline.width, '15%');
  state.deckTextMove({...move, clientX: 102, clientY: 100});
  assert.equal(overlay, undefined, 'clicks do not display guides');
  state.deckTextMove(move);
  assert.ok(overlay, 'the guides are visible after moving');
  assert.deepEqual(overlay.lines.map(line => line.style.left).filter(Boolean).sort(), ['20%', '40%', '50%', '60%']);
  assert.deepEqual(overlay.lines.map(line => line.style.top).filter(Boolean).sort(), ['20%', '40%', '60%']);
  const active = () => overlay.lines.filter(line => line.classList['is-snapped']).map(line => line.style.left || line.style.top);
  assert.deepEqual(active(), ['20%', '40%']);
  assert.equal(state.textSelection.outline.left, '20%');
  assert.equal(Math.round(parseFloat(state.textSelection.outline.top)), 29);
  const snapTransform = transform;
  state.deckTextMove({...move, altKey: true});
  assert.deepEqual(active(), []);
  assert.equal(parseFloat(state.textSelection.outline.left).toFixed(1), '19.6');
  state.deckTextMove(move);
  state.deckTextDown(0, {...start, pointerId: 2});
  state.deckTextMove({...move, pointerId: 2, clientX: 150});
  state.deckTextUp({pointerId: 2});
  assert.ok(overlay, 'a second pointer must not end the first drag');
  assert.equal(transform, snapTransform);
  await state.preview();
  assert.notEqual(state.slides, preview, 'a pending preview must not replace the slide during the drag');
  state.deckTextCancel({pointerId: 1});
  assert.equal(state.slides, preview);
  assert.equal(overlay, undefined);
  assert.equal(transform, undefined);
  assert.equal(state.textSelection.outline.left, '12%', 'cancelling restores the selected outline');
  assert.equal(state.spec.slides[0].texts[0].x, 0.1);

  state.deckTextDown(0, start);
  state.deckTextMove(move);
  state.deckTextUp({pointerId: 1});
  assert.equal(overlay, undefined);
  assert.equal(state.spec.slides[0].texts[0].x, 0.18);
  assert.equal(state.spec.slides[0].texts[0].y, 0.27);
  assert.equal(state.textSelection.outline.left, '20%');
});

function canvasSvg() {
  return {
    getScreenCTM: () => ({inverse: () => ({})}),
    createSVGPoint: () => ({matrixTransform() { return {x: (this.x - 100) * 2, y: (this.y - 70) * 2}; }}),
    viewBox: {baseVal: {x: 0, y: 0, width: 1000, height: 500}},
    getBoundingClientRect: () => ({left: 100, top: 70, width: 500, height: 250}),
  };
}

test('arrow keys move the keyboard-focused text in slide pixels and keep it selected through new previews', () => {
  const text = {content: 'Move me', x: 0.1, y: 0.949, width: 0.3};
  let active;
  const state = studio({spec: {slides: [{texts: [{content: ''}, text]}]}}, undefined, undefined, {
    document: {addEventListener: (_, callback) => callback(), get activeElement() { return active; }},
  });
  state.$nextTick = callback => callback();
  state._previewTargets = state.previewTargets();
  let block;
  const canvas = {
    focus() { active = canvas; },
    querySelector: selector => selector === 'svg' ? block.ownerSVGElement : block,
    querySelectorAll: () => [block],
  };
  function renderBlock(x, y) {
    const element = {
      matches: () => true,
      getAttribute: () => 'texts.0',
      getBBox: () => ({x, y, width: 150, height: 26}),
      ownerSVGElement: canvasSvg(),
      setAttribute() {}, removeAttribute() {},
      focus() { active = element; },
    };
    return element;
  }
  block = renderBlock(100, 470);
  state.canvasTextFocus(0, {target: block});
  assert.equal(state.textSelection.text, text, 'empty text boxes do not consume a rendered index');
  let handled = 0;
  const press = (key, modifiers) => state.canvasKey(0, {key, currentTarget: canvas, preventDefault() { handled += 1; }, ...modifiers});
  press('ArrowRight');
  assert.equal(text.x, 0.101);
  const rendered = state.previewTargets();
  press('ArrowRight');
  press('ArrowDown', {shiftKey: true});
  press('ArrowLeft', {altKey: true});
  assert.equal(handled, 3);
  assert.equal(text.x, 0.102);
  assert.equal(text.y, 0.95);
  assert.equal(active, canvas);

  block = renderBlock(101, 470);
  state._previewTargets = rendered;
  state.syncCanvas(0, canvas, true);
  assert.equal(active, block, 'focus follows the replacement SVG element');
  assert.equal(state.textSelection.text, text);
  assert.equal(parseFloat(state.textSelection.outline.left).toFixed(1), '10.2', 'an older preview still shows the latest offset');
  press('ArrowUp', {shiftKey: true});
  assert.equal(text.y, 0.93);
});

test('text menus open at the keyboard focus, pointer or sidebar button', () => {
  const state = studio({spec: {slides: [{texts: [{content: 'Edit me'}]}]}}, undefined, undefined, {clearTimeout() {}});
  state.$nextTick = () => {};
  state._previewTargets = state.previewTargets();
  let focused;
  const canvas = {
    matches: () => true,
    querySelector: () => canvasSvg(),
    getBoundingClientRect: () => ({left: 100, bottom: 320}),
    focus(options) { focused = [canvas, options.preventScroll]; },
  };
  const block = {
    isConnected: true,
    closest: () => block,
    getAttribute: () => 'texts.0',
    getBoundingClientRect: () => ({left: 150, bottom: 190}),
    focus(options) { focused = [block, options.preventScroll]; },
  };
  const openWithKeyboard = () => state.canvasKey(0, {
    type: 'keydown', key: 'F10', shiftKey: true, target: block, currentTarget: canvas, preventDefault() {},
  });

  openWithKeyboard();
  assert.deepEqual(Array.from(state.actionMenu.items), ['duplicateText', 'copyText', 'textSettings', 'removeText']);
  assert.equal(state.actionMenu.left, 150);
  assert.equal(state.actionMenu.top, 194);
  state.closeActionMenu();
  assert.deepEqual(focused, [block, true]);

  openWithKeyboard();
  block.isConnected = false;
  state.closeActionMenu();
  assert.deepEqual(focused, [canvas, true], 'focus returns to the canvas if a preview replaced the text');

  state.openCanvasMenu(0, {
    type: 'contextmenu', target: block, currentTarget: canvas, clientX: 188, clientY: 170, preventDefault() {},
  });
  assert.equal(state.actionMenu.left, 188);
  assert.equal(state.actionMenu.top, 170);
  state.closeActionMenu();

  const button = {getBoundingClientRect: () => ({left: 340, bottom: 240})};
  state.openTextMenu(0, 0, {type: 'click', target: block, currentTarget: button, clientX: 350, clientY: 235});
  assert.equal(state.actionMenu.left, 340);
  assert.equal(state.actionMenu.top, 244);
});

test('placed text uses the SVG position, fits the edge and only exists while it has content', () => {
  const state = studio({spec: {slides: [{texts: []}]}}, undefined, undefined, {performance: {now: () => 1000}});
  state._previewTargets = state.previewTargets();
  state.showTextPop = () => {};
  const slide = state.spec.slides[0];
  const svg = canvasSvg();
  const event = {
    currentTarget: {querySelector: () => svg},
    clientX: 550, clientY: 318, stopPropagation() {},
  };

  state.placingText = true;
  state.deckTextClick(0, event);
  assert.equal(state.placingText, false);
  assert.equal(slide.texts.length, 0);
  state.closeTextPop();
  assert.equal(slide.texts.length, 0);

  state.placingText = true;
  state.deckTextClick(0, event);
  state.editValue = '   ';
  assert.equal(slide.texts.length, 0);
  state.editValue = 'Right edge';
  const inserted = slide.texts[0];
  assert.equal(inserted.x, 0.54);
  assert.equal(inserted.y, 0.95);
  assert.equal(inserted.x + inserted.width, 0.9);
  assert.equal(inserted.align, 'right');
  state.editValue = '';
  assert.equal(slide.texts.length, 0);
  state.editValue = 'New text';
  state.closeTextPop();
  assert.equal(slide.texts.length, 1);
  assert.equal(slide.texts[0].content, 'New text');
});

test('copy and paste keep styling and paste an independent box inside the destination', () => {
  const original = {content: 'Caption', width: 0.8334, x: 0.08, y: 0.2, align: 'left', font: 'heading', size: 0.04, color: '#ff6600', highlight: '#112233'};
  const first = {texts: [original]};
  const second = {texts: []};
  const state = studio({spec: {slides: [first, second]}});
  const trigger = {focus() {}};
  state.actionMenu = {slide: first, text: original, trigger};
  state.runCanvasAction('copyText');
  original.content = 'Edited after copy';

  state.actionMenu = {slide: second, point: {x: 0.9, y: 0.4}, trigger};
  state.runCanvasAction('pasteHere');
  const pasted = second.texts[0];
  assert.equal(pasted.content, 'Caption');
  assert.equal(pasted.x, 0.9);
  assert.equal(pasted.x + pasted.width, 1);
  assert.equal(pasted.y, 0.4);
  assert.equal(pasted.color, '#ff6600');
  assert.equal(pasted.highlight, '#112233');
  assert.equal(pasted.font, 'heading');
  assert.equal(pasted.size, 0.04);
  assert.notEqual(pasted, state.copiedText);
});

test('a stale preview still targets the right slide and text after moves and deletion', () => {
  const first = {texts: [{content: 'Remove'}, {content: 'Keep', x: 0.1, y: 0.2, width: 0.36}]};
  const second = {texts: []};
  const state = studio({spec: {slides: [first, second]}});
  state.$nextTick = () => {};
  state._previewTargets = state.previewTargets();
  state.moveSlideTo(0, 1);
  state.removeText(first, 0);
  assert.equal(state.previewSlideIndex(0), 1);
  assert.equal(state.previewTextIndex(0, first, 0), -1);
  assert.equal(state.previewTextIndex(0, first, 1), 0);
  state.duplicateText(1, 0);
  state.undoRemoveText();
  assert.deepEqual(first.texts.map(text => text.content), ['Remove', 'Keep', 'Keep']);
  assert.equal(first.texts[2].x, 0.13);
  assert.equal(first.texts[2].y, 0.24);
  state.removeSlide(1);
  assert.equal(state.previewSlideIndex(0), -1);
});

test('slide duplication and insertion follow the target and respect the network limit', () => {
  const first = {title: 'First', texts: [{content: 'Keep'}], images: [{media: 'one.jpg', focusX: 0.5}]};
  const second = {title: 'Second'};
  const state = studio({slideLimit: 4, spec: {slides: [first, second]}});
  state.$nextTick = () => {};
  state.duplicateSlide(0);
  const copy = state.spec.slides[1];
  copy.texts[0].content = 'Copy';
  copy.images[0].focusX = 0.3;
  assert.equal(first.texts[0].content, 'Keep');
  assert.equal(first.images[0].focusX, 0.5);
  state.addSlide(1);
  assert.deepEqual(state.spec.slides.map(slide => slide.title), ['First', 'First', 'New slide', 'Second']);
  assert.equal(state.slidesOpen[2], true);
  assert.equal(state.actionUnavailable('duplicateSlide'), true);
  state.duplicateSlide(0);
  assert.equal(state.spec.slides.length, 4);
});

test('the image context menu and crop drag target the clicked cell even over the slide overlay', () => {
  const first = {media: 'first.jpg', focusX: 0.5, focusY: 0.5};
  const second = {media: 'second.jpg', focusX: 0.5, focusY: 0.6};
  const slide = {imageLayout: 'split-h', images: [first, second], texts: []};
  const state = studio({spec: {slides: [slide]}}, undefined, undefined, {performance: {now: () => 1000}});
  state._previewTargets = state.previewTargets();
  let openedMenu;
  state.openActionMenu = (_, menu) => { openedMenu = menu; };
  let transform;
  const image = {
    width: {baseVal: {value: 750}}, height: {baseVal: {value: 500}},
    setAttribute: (_, value) => { transform = value; },
    removeAttribute: () => { transform = undefined; },
  };
  const svg = {...canvasSvg(), querySelector: selector => selector.startsWith('[data-rp-image="1"]') ? image : null};
  const event = {
    target: {closest: () => null}, currentTarget: {querySelector: () => svg},
    clientX: 508, clientY: 180, pointerId: 4, button: 0, preventDefault() {},
  };

  state.openCanvasMenu(0, event);
  assert.equal(openedMenu.cell, 1);
  assert.deepEqual(Array.from(openedMenu.items), ['textHere', 'replaceImage', 'cropImage', 'freeImageLayout']);
  state.crop = {slide, cell: 1};
  state.deckTextDown(0, {...event, clientX: 200});
  assert.equal(state._drag, null, 'the other cell cannot drag the selected crop');
  state.deckTextDown(0, event);
  state.deckTextMove({...event, clientX: 558, clientY: 200});
  assert.match(transform, /^translate\(100(?:\.0+)? 0\)$/);
  state.deckTextCancel(event);
  assert.equal(transform, undefined);
  assert.equal(second.focusX, 0.5);

  state.deckTextDown(0, event);
  state.deckTextMove({...event, clientX: 758, clientY: 200});
  state.deckTextUp(event);
  assert.equal(second.focusX, 0);
  assert.equal(second.focusY, 0.6, 'a fitted axis stays fixed');
  assert.equal(first.focusX, 0.5);
  state.crop = null;
  state.actionMenu = {...openedMenu, trigger: {focus() {}}};
  state.runCanvasAction('freeImageLayout');
  assert.equal(slide.imageLayout, 'free');
  assert.equal(second.x, 0.55);
});

test('switching from a split gives both images room to move and preserves existing frames', () => {
  const slide = {imageLayout: 'split-h', overlay: 0.35, images: [{media: 'a.jpg'}, {media: 'b.jpg', x: 0.4, width: 0.32}]};
  const state = studio({spec: {slides: [slide]}});
  state.setImageLayout(slide, 'free');
  assert.deepEqual(slide.images.map(({x, y, width, height}) => [x, y, width, height]), [[0.05, 0.25, 0.4, 0.5], [0.4, 0.25, 0.32, 0.5]]);
  state.addImage(slide);
  assert.deepEqual(Array.from(state.imageSlots(slide)), [0, 1, 2]);
  assert.equal(slide.images[2].height, 0.5);
  state.clearMedia(0, 0);
  assert.equal(slide.images[0].media, 'b.jpg');
  assert.equal(slide.overlay, 0.35);
  assert.equal(state.payload().slides[0].images.length, 1);
  state.setImageLayout(slide, 'split-v');
  assert.deepEqual(Array.from(state.imageSlots(slide)), [0, 1]);
  state.setImageLayout(slide, 'free');
  assert.equal(slide.images[0].x, 0.4);
  assert.equal(slide.images[0].y, 0.25);
  state.clearMedia(0, 0);
  assert.equal(slide.overlay, 0);
  assert.deepEqual(Array.from(state.imageSlots(slide)), [0]);

  const vertical = {imageLayout: 'split-v', images: [{media: 'a.jpg'}, {media: 'b.jpg'}]};
  state.setImageLayout(vertical, 'free');
  assert.deepEqual(vertical.images.map(({x, y, width, height}) => [x, y, width, height]), [[0.25, 0.05, 0.5, 0.4], [0.25, 0.55, 0.5, 0.4]]);
});

test('dragging a free image moves the topmost rendered frame within the slide', () => {
  const first = {media: 'a.jpg', x: 0.2, y: 0.2, width: 0.4, height: 0.4};
  const second = {media: 'b.jpg', x: 0.3, y: 0.3, width: 0.5, height: 0.5};
  const slide = {imageLayout: 'free', images: [first, {}, second]};
  const state = studio({spec: {slides: [slide]}}, undefined, undefined, {performance: {now: () => 1000}});
  state._previewTargets = state.previewTargets();
  let openedMenu;
  state.openActionMenu = (_, menu) => { openedMenu = menu; };
  const svgValue = value => ({baseVal: {value}});
  const blocks = [first, second].map(({x, y, width, height}, index) => {
    const rect = {x: svgValue(x * 1000), y: svgValue(y * 500), width: svgValue(width * 1000), height: svgValue(height * 500)};
    const picture = {width: svgValue(750), height: svgValue(350), setAttribute(name, value) { this[name] = value; }};
    const block = {
      getAttribute(name) {
        if (name === 'data-rp-free-image') return String(index);
        return name === 'transform' ? this.transformAttribute : null;
      },
      querySelector: selector => ({'image': picture, '[data-rp-guide="image"]': rect})[selector],
      setAttribute(name, value) { if (name === 'transform') this.transformAttribute = value; },
      removeAttribute(name) { if (name === 'transform') delete this.transformAttribute; },
      transform: {baseVal: {
        get numberOfItems() { return Number(Boolean(block.transformAttribute)); },
        getItem() {
          const [e, f] = block.transformAttribute.match(/-?[\d.]+/g).map(Number);
          return {matrix: {e, f}};
        },
      }},
    };
    return block;
  });
  const svg = {
    ...canvasSvg(), getScreenCTM: () => ({a: 0.5, d: 0.5, inverse: () => ({})}),
    querySelectorAll: selector => selector === '[data-rp-free-image]' ? blocks : [],
  };
  const event = {
    target: {closest: () => null}, currentTarget: {querySelector: selector => selector === 'svg' ? svg : null, focus() {}},
    clientX: 300, clientY: 170, pointerId: 5, button: 0, preventDefault() {},
  };
  state.openCanvasMenu(0, event);
  assert.equal(openedMenu.cell, 2);
  assert.deepEqual(Array.from(openedMenu.items), ['textHere', 'replaceImage', 'cropImage']);
  state.crop = {slide, cell: 2};
  assert.equal(state.cropFrame(slide).top, '30%');
  state.crop = null;
  state.openCanvasMenu(0, {...event, clientX: 115});
  assert.deepEqual(Array.from(openedMenu.items), ['textHere']);
  for (const outside of [{clientX: 530}, {clientY: 95}, {clientY: 285}]) {
    state.openCanvasMenu(0, {...event, ...outside});
    assert.deepEqual(Array.from(openedMenu.items), ['textHere']);
  }

  state.textSelection = {slide};
  state.deckTextDown(0, {...event, clientX: 115});
  assert.equal(state._drag, null);
  state.deckTextDown(0, event);
  assert.equal(state.textSelection, null);
  state.deckTextMove({...event, clientX: 301});
  assert.equal(blocks[1].transformAttribute, undefined);
  state.deckTextMove({...event, clientX: 350, clientY: 145});
  assert.equal(blocks[0].transformAttribute, undefined);
  assert.deepEqual(blocks[1].transformAttribute.match(/-?[\d.]+/g).map(value => Math.round(Number(value))), [100, -50]);
  state.editorKey({key: 'Escape'});
  assert.equal(blocks[1].transformAttribute, undefined);
  assert.deepEqual([second.x, second.y], [0.3, 0.3]);

  state.deckTextDown(0, event);
  state.deckTextMove({...event, clientY: 600});
  assert.equal(Math.round(Number(blocks[1].transformAttribute.match(/-?[\d.]+/g)[1])), 100);
  state.deckTextMove({...event, clientX: 600, clientY: 10});
  state.deckTextUp(event);
  assert.deepEqual([second.x, second.y], [0.5, 0]);
  assert.deepEqual([first.x, first.y], [0.2, 0.2]);
  assert.deepEqual(JSON.parse(JSON.stringify(state.payload().slides[0].images[1])), {media: 'b.jpg', x: 0.5, y: 0, width: 0.5, height: 0.5});

  const moved = {...event, clientX: 540, clientY: 95};
  const movedTransform = blocks[1].transformAttribute;
  state.deckTextDown(0, moved);
  state.deckTextMove({...moved, clientX: 525, clientY: 120});
  state.deckTextCancel(moved);
  assert.equal(blocks[1].transformAttribute, movedTransform, 'cancelling a second drag keeps the previously moved frame');

  state.ensureCells(slide);
  state.crop = {slide, cell: 2};
  assert.deepEqual(JSON.parse(JSON.stringify(state.cropFrame(slide))), {left: '50%', top: '0%', width: '50%', height: '50%'});
  state.deckTextDown(0, event);
  assert.equal(state._drag, null, 'crop ignores other images');
  state.deckTextDown(0, moved);
  state.deckTextMove({...moved, clientX: 590, clientY: 105});
  state.deckTextUp(moved);
  assert.equal(second.focusX, 0.1);
  assert.equal(second.focusY, 0.3);
  assert.equal(second.x, 0.5);
  state.crop = null;
  state.clearMedia(0, 2);
  state.deckTextDown(0, moved);
  assert.equal(state._drag, null, 'an old preview cannot drag a deleted image');
});

test('audio errors stay attached to the exact track and field and clear after validation', () => {
  const state = studio();
  state.violations = [
    {path: 'slides[0].title', message: 'Add a title'},
    {path: 'audio.voice.volume', message: 'Voice volume is out of range'},
    {path: 'audio.music.fadeOut', message: 'Music fade is out of range'},
  ];
  assert.equal(state.audioError('voice', 'volume'), 'Voice volume is out of range');
  assert.equal(state.audioError('music', 'fadeOut'), 'Music fade is out of range');
  assert.equal(state.audioError('music', 'volume'), '');
  assert.equal(state.audioError('voice', 'fadeOut'), '');
  state.violations = [];
  assert.equal(state.audioError('voice', 'volume'), '');
});

test('a video export refused for an audio file shows the error on that track', async () => {
  const violation = {path: 'audio.voice.media', message: 'Audio media is missing or unsupported: gone.wav'};
  const state = studio({urls: {exportVideo: '/export-video'}},
    async () => ({ok: false, status: 422, json: async () => ({violations: [violation]})}), undefined, {
      document: {addEventListener: (_, callback) => callback(), querySelectorAll: () => [{getAttribute: () => '64'}],
        createElement: () => ({getContext: () => ({drawImage() {}}), toDataURL: () => 'data:image/png;base64,AA=='})},
      XMLSerializer: class { serializeToString() { return '<svg/>'; } },
      btoa: value => Buffer.from(value, 'binary').toString('base64'),
      Image: class { set src(_) { this.onload(); } },
      setTimeout() {},
    });
  await state.exportVideo();
  assert.equal(state.audioError('voice', 'media'), violation.message);
  assert.equal(state.notice, violation.message);
});

test('network switching names the destination and preserves edits when cancelled', () => {
  let message;
  const state = studio({network: 'instagram', networkUrls: {youtube: '/youtube'}, vocab: {networkLabels: {youtube: 'YouTube Shorts'}}}, undefined,
    value => { message = value; return false; });
  state.dirty = true;
  const event = {target: {value: 'youtube'}};
  state.switchNetwork(event);
  assert.equal(message, 'Discard unsaved changes and switch to YouTube Shorts?');
  assert.equal(event.target.value, 'instagram');
  assert.equal(state.dirty, true);
  assert.equal(state.spec.slides[0].title, 'Test');
});

test('voice and music pickers keep independent levels, fades and mute in the saved payload', () => {
  const state = studio();
  state.normalize();
  assert.equal(state.payload().audio, undefined, 'empty controls must not create audio tracks');
  state.pickAudio('voice', 'choose');
  state.onMediaMessage(selection('narration.wav'));
  state.spec.audio.voice.volume = 0;
  state.spec.audio.voice.fadeOut = 0.5;
  state.pickAudio('music', 'upload');
  state.onMediaMessage(selection('picture.jpg'));
  assert.equal(state.notice, 'Choose audio');
  assert.equal(state.mediaModal.open, true);
  state.onMediaMessage(selection('music.MP3', 'https://other.test'));
  assert.equal(state.spec.audio.music.media, '');
  state.onMediaMessage(selection('music.MP3'));
  assert.equal(state.spec.audio.music.volume, 0.3);
  assert.equal(state.spec.audio.voice.media, 'narration.wav');
  assert.equal(state.payload().audio.voice.volume, 0);
  assert.equal(state.payload().audio.voice.fadeOut, 0.5);
  assert.equal(state.payload().audio.ducking, true);
  state.spec.audio.voice.media = '';
  state.spec.audio.ducking = false;
  assert.equal(state.payload().audio.voice, undefined);
  assert.equal(state.payload().audio.music.media, 'music.MP3');
  assert.equal(state.payload().audio.ducking, false);
  assert.equal(state.spec.slides[0].images[0].media, undefined);
});

test('choosing a rendered video switches preview without changing slide images', () => {
  const state = studio();
  state.pickVideo('choose');
  assert.equal(state.mediaModal.src, '/admin/media');
  state.onMediaMessage(selection('reel.mp4'));
  assert.equal(state.spec.video, 'reel.mp4');
  assert.equal(state.selectedVideo, 'reel.mp4');
  assert.equal(state.previewMode, 'video');
  assert.equal(state.mediaModal.open, false);
  assert.equal(state.spec.slides[0].images[0].media, undefined);
  assert.equal(state.payload().video, 'reel.mp4');
  state.clearVideo();
  assert.equal(state.video, null);
  assert.equal(state.selectedVideo, '');
  assert.equal(state.payload().video, undefined);
});

test('video picker rejects unsupported media and messages from other origins', () => {
  const state = studio();
  state.pickVideo('upload');
  assert.equal(state.mediaModal.src, '/admin/media/new');
  state.onMediaMessage(selection('picture.jpg'));
  assert.equal(state.notice, 'Choose a video');
  assert.equal(state.mediaModal.open, true);
  assert.equal(state.spec.video, undefined);
  state.onMediaMessage(selection('reel.mp4', 'https://other.test'));
  assert.equal(state.spec.video, undefined);
  state.onMediaMessage(selection('reel.WEBM'));
  assert.equal(state.spec.video, 'reel.WEBM');
});

test('image picker still writes its own cell and ignores late selections', () => {
  const state = studio();
  state.pickMedia(0, 0, 'choose');
  state.onMediaMessage(selection('photo.jpg'));
  assert.equal(state.spec.slides[0].images[0].media, 'photo.jpg');
  assert.equal(state.spec.video, undefined);
  state.onMediaMessage(selection('late.jpg'));
  assert.equal(state.spec.slides[0].images[0].media, 'photo.jpg');
});

test('an attached render opens in video mode and preview refreshes its media URL', async () => {
  const video = {name: 'reel.mp4', url: '/media/reel.mp4'};
  let nextVideo = video;
  const state = studio({spec: {video: 'reel.mp4', slides: [{title: 'Test'}]}, video},
    async () => ({ok: true, json: async () => ({slides: ['<svg/>'], video: nextVideo})}));
  assert.equal(state.previewMode, 'video');
  assert.equal(state.video.url, '/media/reel.mp4');
  state.videoError = true;
  await state.preview();
  assert.equal(state.live.state, 'ok');
  assert.equal(state.videoError, true, 'editing slides must not hide an existing playback error');
  nextVideo = {name: 'replacement.webm', url: '/media/replacement.webm'};
  state.spec.video = 'replacement.webm';
  await state.preview();
  assert.equal(state.video.url, '/media/replacement.webm');
  assert.equal(state.selectedVideo, 'replacement.webm');
  assert.equal(state.videoError, false);
  nextVideo = null;
  state.spec.video = 'missing.mp4';
  await state.preview();
  assert.equal(state.video, null);
  assert.equal(state.selectedVideo, 'missing.mp4');
  assert.equal(state.slides.length, 1);
});
