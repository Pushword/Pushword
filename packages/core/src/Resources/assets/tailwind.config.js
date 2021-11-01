const plugin = require("tailwindcss/plugin");
const colors = require("tailwindcss/colors");

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
        colors: {
            transparent: "transparent",
            current: "currentColor",
            black: "#000",
            white: "#fff",
            bluegray: colors.blueGray,
            coolgray: colors.coolGray,
            gray: colors.gray,
            truegray: colors.trueGray,
            warmgray: colors.warmGray,
            red: colors.red,
            orange: colors.orange,
            amber: colors.amber,
            yellow: colors.yellow,
            lime: colors.lime,
            green: colors.green,
            emerald: colors.emerald,
            teal: colors.teal,
            cyan: colors.cyan,
            sky: colors.sky,
            blue: colors.blue,
            indigo: colors.indigo,
            violet: colors.violet,
            purple: colors.purple,
            fuchsia: colors.fuchsia,
            pink: colors.pink,
            rose: colors.rose,
        },
        extend: {
            typography: {
                DEFAULT: {
                    css: {
                        color: "#333",
                        "a, span[data-rot]": {
                            color: "var(--primary)",
                            "&:hover": {
                                opacity: ".75",
                            },
                        },
                    },
                },
            },
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
