module.exports = {
    extendTailwindTypography: function () {
        return {
            DEFAULT: {
                css: {
                    ":where(a):not(:where([class~=not-prose] *)), :where(span[data-rot]):not(:where([class~=not-prose] *))":
                        {
                            color: "var(--primary)",
                            "&:hover": {
                                opacity: ".75",
                            },
                        },
                },
            },
        };
    },
    twFirstLetterPlugin: function ({ addVariant, e }) {
        addVariant("first-letter", ({ modifySelectors, separator }) => {
            modifySelectors(({ className }) => {
                return `.${e(`first-letter${separator}${className}`)}:first-letter`;
            });
        });
    },
    twFirstChildPlugin: function ({ addVariant, e }) {
        addVariant("first-child", ({ modifySelectors, separator }) => {
            modifySelectors(({ className }) => {
                return `.${e(`first-child${separator}${className}`)}:first-child`;
            });
        });
    },
};
