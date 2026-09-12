use proptest::prelude::*;
use pushword_html_minifier::minify;

proptest! {
    #![proptest_config(ProptestConfig::with_cases(512))]

    #[test]
    fn plain_unicode_text_and_protected_code_survive(
        text in "[a-zA-Z0-9 éà中🦀\\n\\t]{0,1024}",
    ) {
        let html = format!("<!DOCTYPE html><html><head></head><body><pre>{text}</pre></body></html>");
        // HTML5 consumes one initial LF in <pre>; do not confuse that rule with
        // minification losing whitespace inside an already parsed code block.
        let expected = text.strip_prefix('\n').unwrap_or(&text);
        let protected = format!("<pre>{expected}</pre>");
        let output = minify(&html);
        prop_assert!(output.contains(&protected));
    }

    #[test]
    fn fragmented_and_malformed_markup_does_not_panic(body in prop::collection::vec(
        prop_oneof![
            ".{0,64}",
            prop::sample::select(vec!["<div>", "</div>", "<template>", "</template>", "<svg>", "<pre>", "</pre>", "<script>", "</script>", "<!--", "-->", "&amp;"])
                .prop_map(str::to_owned),
        ],
        0..64,
    )) {
        let input = format!("<!DOCTYPE html><html><body>{}</body></html>", body.concat());
        let output = minify(&input);
        prop_assert!(output.starts_with("<!DOCTYPE html><html>"));
    }
}
