export function extendTailwindTypography() {
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
}
