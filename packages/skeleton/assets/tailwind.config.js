const colors = require("tailwindcss/colors");

module.exports = {
    mode: "jit",
    purge: {}, // directly in webpack
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
            typography: (theme) => ({
                DEFAULT: {
                    css: {
                        color: "#333",
                        "a, span[data-rot]": {
                            color: theme("colors.yellow.600"),
                            "&:hover": {
                                opacity: "0.75",
                            },
                        },
                    },
                },
            }),
            colors: {
                primary: "var(--primary)",
                secondary: "var(--secondary)",
            },
        },
    },
    variants: {
        width: ["responsive", "hover", "focus"],
    },
    plugins: [require("@tailwindcss/typography"), require("@tailwindcss/aspect-ratio")],
};
