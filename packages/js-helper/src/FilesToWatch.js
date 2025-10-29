export default function getFilesToWatch(basePath = './..') {
  return [
    basePath + '/vendor/pushword/core/src/templates/**/*.html.twig',
    basePath + '/vendor/pushword/conversation/src/templates/**/*.html.twig',
    basePath + '/vendor/pushword/admin-block-editor/src/templates/**/*.html.twig',
    basePath + '/vendor/pushword/advanced-main-image/src/templates/**/*.html.twig',
    basePath + '/templates/**/*.html.twig',
    basePath + '/var/TailwindGeneratorCache/*',
    basePath + '/src/Twig/AppExtension.php',
  ]
}
