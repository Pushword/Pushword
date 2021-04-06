module.exports = {
    //mode: "jit",
    purge: {}, // directly in webpack
    theme: {
        minHeight: {
            0: "0",
            "vw-3/4": "75vh",
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
