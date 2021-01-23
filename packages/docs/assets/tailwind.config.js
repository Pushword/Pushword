const colors = require("tailwindcss/colors");

module.exports = {
    purge: {}, // directly in webpack
    theme: {
        colors: {
            "blue-gray": colors.blueGray,
            "cool-gray": colors.coolGray,
            gray: colors.gray,
            transparent: "transparent",
            current: "currentColor",
            black: colors.black,
            white: colors.white,
            gray: colors.coolGray,
            red: colors.red,
            yellow: colors.amber,
            green: colors.emerald,
            blue: colors.blue,
            indigo: colors.indigo,
            purple: colors.violet,
            pink: colors.pink,
        },
        flex: {
            1: "1 1 0%",
            auto: "1 1 auto",
            initial: "0 1 auto",
            inherit: "inherit",
            none: "none",
            full: "1 0 100%;",
            "half-50": "0 1 45%",
            "half-30": "0 1 25%",
            "half-70": "0 1 65%",
            "half-25": "0 1 20%",
            "half-75": "0 1 70%",
        },
        extend: {
            screens: {
                light: { raw: "(prefers-color-scheme: light)" },
                dark: { raw: "(prefers-color-scheme: dark)" },
            },
            typography: (theme) => ({
                DEFAULT: {
                    css: {
                        "code::before": { content: "" },
                        "code::after": { content: "" },
                        code: {
                            backgroundColor: theme("colors.gray.200"),
                            borderRadius: ".375rem",
                            fontSize: "85%",
                            padding: ".2em .4em",
                            textDecoration: "none",
                            fontWeight: 400,
                        },
                        "ul > li::before": {
                            backgroundColor: theme("colors.gray.600"),
                        },
                        color: "#333",
                        "a, span[data-rot]": {
                            boxShadow: "inset 0 -6px 0 #FED7AA",
                            color: "#333",
                            textDecoration: "none",
                            "&:hover": {
                                opacity: "0.75",
                            },
                        },
                    },
                },
                light: {
                    css: [
                        {
                            color: theme("colors.gray.100"),
                            "a, span[data-rot]": {
                                color: "#fff",
                                boxShadow: "inset 0 -6px 0 #9A3412",
                            },
                            '[class~="lead"]': {
                                color: theme("colors.gray.300"),
                            },
                            strong: {
                                color: "white",
                            },
                            "ol > li::before": {
                                color: theme("colors.gray.400"),
                            },
                            "ul > li::before": {
                                backgroundColor: theme("colors.gray.600"),
                            },
                            hr: {
                                borderColor: theme("colors.gray.200"),
                            },
                            blockquote: {
                                color: theme("colors.gray.200"),
                                borderLeftColor: theme("colors.gray.600"),
                            },
                            h1: {
                                color: theme("colors.gray.100"),
                            },
                            h2: {
                                color: theme("colors.gray.100"),
                            },
                            h3: {
                                color: theme("colors.gray.100"),
                            },
                            h4: {
                                color: theme("colors.gray.100"),
                            },
                            "figure figcaption": {
                                color: theme("colors.gray.400"),
                            },
                            code: {
                                backgroundColor: theme("colors.gray.600"),
                                color: theme("colors.gray.100"),
                            },
                            "a code": {
                                color: theme("colors.gray.100"),
                            },
                            pre: {
                                color: theme("colors.gray.200"),
                                backgroundColor: theme("colors.gray.600"),
                            },
                            thead: {
                                color: theme("colors.gray.100"),
                                borderBottomColor: theme("colors.gray.400"),
                            },
                            "tbody tr": {
                                borderBottomColor: theme("colors.gray.600"),
                            },
                        },
                    ],
                },
            }),
            colors: {
                primary: "var(--primary)",
            },
        },
    },
    variants: {
        extend: {
            typography: ["dark"],
        },
        width: ["responsive", "hover", "focus"],
    },
    plugins: [
        require("@tailwindcss/typography"),
        require("@tailwindcss/aspect-ratio"),
    ],
};
