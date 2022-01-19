const WatchExternalFilesPlugin = require("webpack-watch-files-plugin").default;
const Encore = require("@symfony/webpack-encore");
const tailwindcss = require("tailwindcss");
const EncoreHelper = require("@pushword/js-helper/src/encore.js");

module.exports = EncoreHelper.getEncore().getWebpackConfig();
