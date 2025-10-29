import Attaches from './packages/admin-block-editor/src/assets/tools/Attaches/Attaches.ts'

console.log(
  Attaches.isItMarkdownExported(`{{ attaches('test', '/media/2.jpg', '2' )|unprose }}`),
)
