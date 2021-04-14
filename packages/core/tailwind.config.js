module.exports = {
    mode: "jit",
    purge: {
        mode: "all",
        content: [
            "./src/templates/**/*.html.twig",
            "./src/templates/*.html.twig",
            "./../conversation/src/templates/*.html.twig",
            "./../admin-block-editor/src/templates/block/*.html.twig",
        ],
        defaultExtractor: (content) => content.match(/[\w-/:]+(?<!:)/g) || [],
    }, // directly in webpack
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
            typography: {
                DEFAULT: {
                    css: {
                        color: "#333",
                        a: {
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
    plugins: [require("@tailwindcss/typography"), require("@tailwindcss/aspect-ratio"), require("@tailwindcss/forms")],
};
