const EncoreHelper = require('@pushword/js-helper/src/encore.js')
const Encore = require('@symfony/webpack-encore')

module.exports = EncoreHelper.getEncore(Encore).getWebpackConfig()
