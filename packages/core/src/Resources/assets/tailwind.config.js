const plugin = require("tailwindcss/plugin");
import { extendTailwindTypography } from "@pushword/js-helper/src/tailwind.helpers.js";

module.exports = {
    mode: "jit",
    theme: {
        minHeight: {
            0: "0",
            "screen-1/4": "25vh",
            "screen-3/4": "75vh",
            "screen-1/3": "33vh",
            "screen-2/3": "66vh",
            "screen-1/2": "50vh",
            screen: "100vh",
            full: "100%",
        },
        extend: {
            typography: extendTailwindTypography(),
            colors: {
                primary: "var(--primary)",
                secondary: "var(--secondary)",
            },
        },
    },
    variants: {},
    plugins: [
        require("@tailwindcss/typography"),
        require("@tailwindcss/aspect-ratio"),
        require("@tailwindcss/forms"),
        plugin(function ({ addVariant, e }) {
            addVariant("first-letter", ({ modifySelectors, separator }) => {
                modifySelectors(({ className }) => {
                    return `.${e(`first-letter${separator}${className}`)}:first-letter`;
                });
            });
        }),
        plugin(function ({ addVariant, e }) {
            addVariant("first-child", ({ modifySelectors, separator }) => {
                modifySelectors(({ className }) => {
                    return `.${e(`first-child${separator}${className}`)}:first-child`;
                });
            });
        }),
    ],
};
