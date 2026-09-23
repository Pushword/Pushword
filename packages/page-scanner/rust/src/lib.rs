#![forbid(unsafe_code)]

use html5ever::tendril::StrTendril;
use html5ever::tokenizer::states::RawKind;
use html5ever::tokenizer::{
    BufferQueue, TagKind, Token, TokenSink, TokenSinkResult, Tokenizer, TokenizerOpts,
};
use regex::Regex;
use scraper::{Html, Selector};
use serde::Serialize;
use std::cell::RefCell;
use std::collections::HashSet;
use std::sync::LazyLock;

static HREF: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r#"(?i)<a\s[^>]*?href=(?:"([^"']*)"|'([^"']*)')"#).expect("valid href regex")
});
static TEMPLATE: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r"(?is)<template\b[^>]*>.*?</template>").expect("valid template regex")
});
static IMAGE: LazyLock<Regex> =
    LazyLock::new(|| Regex::new(r"(?i)<img\s[^>]*>").expect("valid image regex"));
static ALT: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r#"(?i)\salt\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))"#).expect("valid alt regex")
});
static SRC: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r#"(?i)\ssrc\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))"#).expect("valid src regex")
});
static DECORATIVE: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r#"(?i)\s(?:role\s*=\s*["']?presentation|aria-hidden\s*=\s*["']?true)"#)
        .expect("valid decorative regex")
});
static ANCHORED: LazyLock<Selector> =
    LazyLock::new(|| Selector::parse("[id], [name]").expect("valid anchor selector"));
static CODE_SAMPLE: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r"(?is)<code\b[^>]*>.*?</code>|<pre\b[^>]*>.*?</pre>")
        .expect("valid code sample regex")
});
static LINKED_ATTRIBUTE: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r#"(?is) (href|data-rot|src|data-img|data-bg)=(?:"(.+?)"|'(.+?)'|([^\s>]+)[\s>])"#)
        .expect("valid linked attribute regex")
});
static SRCSET: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r#"(?i)\s(?:srcset|imagesrcset|data-srcset)=(?:"([^"\n]*)"|'([^'\n]*)')"#)
        .expect("valid srcset regex")
});
static LITERAL_BLOCK: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r"(?is)<code\b[^>]*>.*?</code>|<pre\b[^>]*>.*?</pre>|<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>")
        .expect("valid literal block regex")
});
static DATE_SHORTCODE: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r#"(?i)\bdate\(['"]?%?(?:Y[-+]1|[YSWBMAe])['"]?\)"#)
        .expect("valid date shortcode regex")
});
// The PHP pattern runs without /u: its UTF-8 byte ranges admit U+0080–U+00FF.
// Keep that byte-level accepted set for valid UTF-8 input.
static WEB_LINK: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r"^((?:(http:|https:)//([A-Za-z0-9_\x{80}-\x{ff}-]+\.)+[A-Za-z0-9_-]+){0,1}(/?[A-Za-z0-9_\x{80}-\x{ff}~,;\-./?%&+#=]*))$")
        .expect("valid web link regex")
});

#[derive(Debug, PartialEq, Eq, Serialize)]
pub struct Facts {
    pub hrefs: Vec<String>,
    pub missing_alt: Vec<String>,
    pub anchors: Vec<String>,
    pub linked_docs: Vec<String>,
    pub crawlable_links: Vec<String>,
    pub mailto_links: Vec<String>,
    pub date_shortcodes: Vec<String>,
}

#[derive(Default)]
struct AnchorSink {
    state: RefCell<AnchorState>,
}

#[derive(Default)]
struct AnchorState {
    anchors: HashSet<String>,
    seen_html: bool,
    seen_head: bool,
    seen_body: bool,
    seen_regular_tag: bool,
    template_depth: usize,
    table_depth: usize,
    select_depth: usize,
    foreign_stack: Vec<&'static str>,
    requires_dom: bool,
}

impl TokenSink for AnchorSink {
    type Handle = ();

    fn process_token(&self, token: Token, _line_number: u64) -> TokenSinkResult<Self::Handle> {
        let Token::TagToken(tag) = token else {
            return TokenSinkResult::Continue;
        };
        let name: &str = tag.name.as_ref();
        let mut state = self.state.borrow_mut();
        if tag.kind == TagKind::EndTag {
            state.seen_regular_tag = true;
            match name {
                "template" => state.template_depth = state.template_depth.saturating_sub(1),
                "table" => state.table_depth = state.table_depth.saturating_sub(1),
                "select" => state.select_depth = state.select_depth.saturating_sub(1),
                "svg" | "math" => {
                    state.requires_dom |= state.foreign_stack.pop() != Some(name);
                }
                _ => {}
            }
            return TokenSinkResult::Continue;
        }

        // The HTML5 tree builder can discard or merge tags the tokenizer emits.
        match name {
            "html" => {
                state.requires_dom |= state.seen_html;
                state.seen_html = true;
            }
            "body" => {
                state.requires_dom |= state.seen_body;
                state.seen_body = true;
            }
            "head" => {
                state.requires_dom |= state.seen_head || state.seen_regular_tag;
                state.seen_head = true;
            }
            "template" => state.template_depth += 1,
            "table" => {
                state.requires_dom |= state.table_depth > 0;
                state.table_depth += 1;
            }
            "select" => state.select_depth += 1,
            "svg" => state.foreign_stack.push("svg"),
            "math" => state.foreign_stack.push("math"),
            "frameset" => state.requires_dom = true,
            "script" | "style" | "title" | "textarea" | "noscript" | "plaintext"
                if !state.foreign_stack.is_empty() =>
            {
                state.requires_dom = true;
            }
            "tr" | "td" | "th" | "tbody" | "thead" | "tfoot" | "caption" | "colgroup" | "col"
                if state.table_depth == 0 || state.select_depth > 0 =>
            {
                state.requires_dom = true;
            }
            _ => {}
        }
        if name != "html" && name != "head" {
            state.seen_regular_tag = true;
        }
        if state.template_depth > 0 && (name == "html" || name == "body") {
            state.requires_dom = true;
        }

        for attribute in &tag.attrs {
            if attribute.name.local.as_ref() == "id" || attribute.name.local.as_ref() == "name" {
                state.anchors.insert(attribute.value.to_string());
            }
        }

        if name == "plaintext" {
            return TokenSinkResult::Plaintext;
        }
        let raw = match name {
            "title" | "textarea" => Some(RawKind::Rcdata),
            "style" | "xmp" | "iframe" | "noembed" | "noframes" | "noscript" => {
                Some(RawKind::Rawtext)
            }
            "script" => Some(RawKind::ScriptData),
            _ => None,
        };
        raw.map_or(TokenSinkResult::Continue, TokenSinkResult::RawData)
    }
}

fn extract_anchors(html: &str) -> Vec<String> {
    let input = BufferQueue::default();
    input.push_back(StrTendril::from_slice(html));
    let tokenizer = Tokenizer::new(AnchorSink::default(), TokenizerOpts::default());
    assert!(matches!(
        tokenizer.feed(&input),
        html5ever::TokenizerResult::Done
    ));
    tokenizer.end();

    let state = tokenizer.sink.state.into_inner();
    if state.requires_dom {
        return dom_anchors(html);
    }

    let mut anchors: Vec<String> = state.anchors.into_iter().collect();
    anchors.sort();
    anchors
}

fn dom_anchors(html: &str) -> Vec<String> {
    let document = Html::parse_document(html);
    let mut anchors = HashSet::new();
    for node in document.select(&ANCHORED) {
        for attribute in ["id", "name"] {
            if let Some(value) = node.value().attr(attribute) {
                anchors.insert(value.to_owned());
            }
        }
    }
    let mut anchors: Vec<String> = anchors.into_iter().collect();
    anchors.sort();
    anchors
}

fn decrypt(value: &str) -> String {
    let bytes: Vec<u8> = value
        .bytes()
        .map(|byte| match byte {
            b'a'..=b'z' => (byte - b'a' + 13) % 26 + b'a',
            b'A'..=b'Z' => (byte - b'A' + 13) % 26 + b'A',
            _ => byte,
        })
        .collect();
    let path = String::from_utf8(bytes).expect("ROT13 preserves UTF-8");

    if let Some(rest) = path.strip_prefix('-') {
        format!("http://{rest}")
    } else if let Some(rest) = path.strip_prefix('_') {
        format!("https://{rest}")
    } else if let Some(rest) = path.strip_prefix('@') {
        format!("mailto:{rest}")
    } else {
        path
    }
}

fn is_web_link(uri: &str) -> bool {
    if [
        "https://wa.me/",
        "https://maps.app.goo.gl",
        "https://goo.gl/",
        "https://g.page/",
        "https://www.tripadvisor.fr/",
        "https://www.facebook.com/",
    ]
    .iter()
    .any(|prefix| uri.starts_with(prefix))
    {
        return false;
    }

    // PCRE's $ also matches immediately before one final newline.
    WEB_LINK.is_match(uri.strip_suffix('\n').unwrap_or(uri))
}

fn attribute(tag: &str, pattern: &Regex) -> String {
    let Some(captures) = pattern.captures(tag) else {
        return String::new();
    };

    (1..=3)
        .find_map(|index| captures.get(index))
        .map_or_else(String::new, |value| value.as_str().trim().to_owned())
}

pub fn extract(html: &str) -> Facts {
    let hrefs = HREF
        .captures_iter(html)
        .map(|capture| {
            capture
                .get(1)
                .or_else(|| capture.get(2))
                .expect("one quoted href")
                .as_str()
                .to_owned()
        })
        .collect();

    let mut seen = HashSet::new();
    let mut missing_alt = Vec::new();
    let outside_templates = TEMPLATE.replace_all(html, "");
    for image in IMAGE.find_iter(&outside_templates) {
        let tag = image.as_str();
        if DECORATIVE.is_match(tag) || !attribute(tag, &ALT).is_empty() {
            continue;
        }

        let src = attribute(tag, &SRC);
        if seen.insert(src.clone()) {
            missing_alt.push(if src.is_empty() { tag.to_owned() } else { src });
        }
    }

    let anchors = extract_anchors(html);

    let searchable = CODE_SAMPLE.replace_all(html, "");
    let mut linked_docs = Vec::new();
    let mut seen_links = HashSet::new();
    let mut crawlable_links = Vec::new();
    let mut seen_crawlable = HashSet::new();
    let mut mailto_links = Vec::new();
    let mut add_link = |uri: String, crawlable: bool| {
        if crawlable && seen_crawlable.insert(uri.clone()) {
            crawlable_links.push(uri.clone());
        }
        if seen_links.insert(uri.clone()) {
            linked_docs.push(uri);
        }
    };
    for capture in LINKED_ATTRIBUTE.captures_iter(&searchable) {
        let obfuscated = capture.get(1).expect("attribute name").as_str() == "data-rot";
        let raw = (2..=4)
            .find_map(|index| capture.get(index))
            .expect("attribute value")
            .as_str();
        let uri = if obfuscated {
            decrypt(raw)
        } else {
            raw.to_owned()
        };

        if !obfuscated && (uri.contains("mailto:") || uri.contains("tel:")) {
            mailto_links.push(uri);
            continue;
        }
        if !uri.is_empty() && is_web_link(&uri) {
            add_link(uri, !obfuscated);
        }
    }

    for capture in SRCSET.captures_iter(&searchable) {
        let srcset = capture
            .get(1)
            .or_else(|| capture.get(2))
            .expect("quoted srcset")
            .as_str();
        for entry in srcset.split(',') {
            let entry =
                entry.trim_matches(|c| matches!(c, ' ' | '\t' | '\n' | '\r' | '\0' | '\u{0b}'));
            let uri = entry
                .split(|c: char| c.is_ascii_whitespace())
                .next()
                .unwrap_or("");
            if !uri.is_empty() && is_web_link(uri) {
                add_link(uri.to_owned(), true);
            }
        }
    }

    let without_literals = LITERAL_BLOCK.replace_all(html, "");
    let mut seen_dates = HashSet::new();
    let date_shortcodes = DATE_SHORTCODE
        .find_iter(&without_literals)
        .filter_map(|value| {
            let shortcode = value.as_str();
            seen_dates.insert(shortcode).then(|| shortcode.to_owned())
        })
        .collect();

    Facts {
        hrefs,
        missing_alt,
        anchors,
        linked_docs,
        crawlable_links,
        mailto_links,
        date_shortcodes,
    }
}

#[cfg(test)]
mod tests {
    use super::{Facts, extract, extract_anchors};
    use scraper::{Html, Selector};
    use std::collections::HashSet;

    fn reference_anchors(html: &str) -> Vec<String> {
        let selector = Selector::parse("[id], [name]").expect("valid selector");
        let mut anchors = HashSet::new();
        for node in Html::parse_document(html).select(&selector) {
            for attribute in ["id", "name"] {
                if let Some(value) = node.value().attr(attribute) {
                    anchors.insert(value.to_owned());
                }
            }
        }
        let mut anchors: Vec<String> = anchors.into_iter().collect();
        anchors.sort();
        anchors
    }

    #[test]
    fn extracts_in_order_and_deduplicates_missing_alt_by_source() {
        assert_eq!(
            extract(
                "<a href='/one'>1</a><a class=x href=\"/two\">2</a><img src=/x><img src=/x><img alt='ok' src=/y><img role=presentation src=/z>"
            ),
            Facts {
                hrefs: vec!["/one".into(), "/two".into()],
                missing_alt: vec!["/x".into()],
                anchors: vec![],
                linked_docs: vec![
                    "/one".into(),
                    "/two".into(),
                    "/x".into(),
                    "/y".into(),
                    "/z".into()
                ],
                crawlable_links: vec![
                    "/one".into(),
                    "/two".into(),
                    "/x".into(),
                    "/y".into(),
                    "/z".into()
                ],
                mailto_links: vec![],
                date_shortcodes: vec![],
            }
        );
    }

    #[test]
    fn reports_first_sourceless_image_as_a_tag() {
        assert_eq!(
            extract("<img width=8><img width=16>"),
            Facts {
                hrefs: vec![],
                missing_alt: vec!["<img width=8>".into()],
                anchors: vec![],
                linked_docs: vec![],
                crawlable_links: vec![],
                mailto_links: vec![],
                date_shortcodes: vec![],
            }
        );
    }

    #[test]
    fn skips_images_inside_templates() {
        assert_eq!(
            extract("<template id=card><img src=/inside></template><img src=/outside>").missing_alt,
            vec!["/outside"]
        );
    }

    #[test]
    fn reports_images_between_templates() {
        assert_eq!(
            extract("<template><img src=/a></template><img src=/between><template><img src=/b></template>")
                .missing_alt,
            vec!["/between"]
        );
    }

    #[test]
    fn skips_images_inside_uppercase_multiline_templates() {
        assert!(
            extract("<TEMPLATE id=card>\n<img src=/inside>\n</TEMPLATE>")
                .missing_alt
                .is_empty()
        );
    }

    #[test]
    fn collects_id_and_name_values_once() {
        assert_eq!(
            extract("<a id='one' name='old'></a><div id='one'></div>").anchors,
            vec!["old", "one"]
        );
    }

    #[test]
    fn anchor_tokenizer_matches_the_html5_dom_on_malformed_markup() {
        let cases = [
            "<!-- <div id='comment'> --><div id=ok name='old'></div>",
            "<div id='a&amp;b' name='&#x41;'></div><div id='a&amp;b'></div>",
            "<div ID='first' id='second' NAME=third></div>",
            "<script>const x = \"<div id='fake'>\";</script><div id='real'>",
            "<style><div id='fake'></style><div id='real'>",
            "<textarea><div id='fake'></textarea><div id='real'>",
            "<title><div id='fake'></title><div id='real'>",
            "<select><div id='ignored'></div><option id='real'>x</option></select>",
            "<table><div id='fostered'></div><tr><td id='cell'></td></tr></table>",
            "<template><div id='inside'></div></template><div id='outside'>",
            "<noscript><div id='inside'></div></noscript><div id='outside'>",
            "<svg><g id='graphic'></g></svg><div id='outside'>",
            "<math><mi id='math'></mi></math><div id='outside'>",
            "<head><div id='body'></div><meta name='description'>",
            "<html id='first'><html id='second'><body id='third'><body id='fourth'>",
            "<html><html id='merged'><body><body name='merged-body'>",
            "<frameset id='frame'><div id='ignored'></div><frame name='child'></frameset>",
            "<select><optgroup><option><div id='after-select'></div></select>",
            "<table><tr><td><select><div id='after-select'></div></select></td></tr></table>",
            "<svg><foreignObject><div id='foreign'></div></foreignObject></svg>",
            "<svg><script><div id='script'></div></script></svg>",
            "<math><script><div id='math-script'></div></script></math>",
            "<template><html id='nested'><body id='nested-body'></template>",
            "<table><tr id='row'></tr><td id='orphan'></td></table>",
            "<plaintext><div id='fake'></div>",
        ];

        for html in cases {
            assert_eq!(extract_anchors(html), reference_anchors(html), "{html}");
        }
    }

    #[test]
    fn anchor_tokenizer_matches_the_html5_dom_on_short_mixed_markup() {
        let fragments = [
            "<div id='a'>",
            "</div>",
            "<p name='b'>",
            "</p>",
            "<a id='c'>",
            "</a>",
            "<table id='d'>",
            "</table>",
            "<tr id='e'>",
            "</tr>",
            "<select id='f'>",
            "</select>",
            "<option id='g'>",
            "</option>",
            "<template id='h'>",
            "</template>",
            "<svg id='i'>",
            "</svg>",
            "<math id='p'>",
            "</math>",
            "<script id='j'>",
            "</script>",
            "<textarea id='r'>",
            "</textarea>",
            "<title id='s'>",
            "</title>",
            "<head id='k'>",
            "</head>",
            "<body id='l'>",
            "</body>",
            "<noscript id='m'>",
            "</noscript>",
            "<plaintext id='n'>",
            "<span id='o'>",
            "<!-- <i id='q'> -->",
        ];
        let mut seed = 0x5eed_u64;
        for _ in 0..10_000 {
            let mut html = String::new();
            for _ in 0..8 {
                seed = seed.wrapping_mul(6364136223846793005).wrapping_add(1);
                html.push_str(fragments[(seed >> 32) as usize % fragments.len()]);
            }
            assert_eq!(extract_anchors(&html), reference_anchors(&html), "{html}");
        }
    }

    #[test]
    fn empty_html_has_no_facts() {
        assert_eq!(
            extract(""),
            Facts {
                hrefs: vec![],
                missing_alt: vec![],
                anchors: vec![],
                linked_docs: vec![],
                crawlable_links: vec![],
                mailto_links: vec![],
                date_shortcodes: vec![],
            }
        );
    }

    #[test]
    fn collects_scanner_links_and_srcsets_outside_code_samples() {
        let facts = extract(
            "<code><a href='/ignore'>x</a></code><a href='/one'>x</a><img src='/one' data-img='/two' srcset='/three 1x, /four 2x'><pre><img src='/ignore'></pre>",
        );

        assert_eq!(facts.linked_docs, vec!["/one", "/two", "/three", "/four"]);
        assert_eq!(facts.crawlable_links, facts.linked_docs);
    }

    #[test]
    fn preserves_the_php_scanners_empty_quoted_attribute_match() {
        assert_eq!(
            extract("<a href=\"\"></a><a href=\"/next\">").linked_docs,
            Vec::<String>::new()
        );
    }

    #[test]
    fn preserves_unquoted_attribute_separator_and_empty_srcset() {
        let facts = extract("<img src=/one data-img='/two' srcset=\"\">");
        assert_eq!(facts.linked_docs, vec!["/one"]);
        assert_eq!(facts.crawlable_links, vec!["/one"]);
    }

    #[test]
    fn decrypts_obfuscated_links_and_reports_clear_mailto_links() {
        let facts = extract(
            "<span data-rot='/uvqqra'></span><a href='/hidden'></a><a href='mailto:one@example.tld'></a><a href='tel:123'></a><span data-rot='@n@rknzcyr.gyq'></span>",
        );
        assert_eq!(facts.linked_docs, vec!["/hidden"]);
        assert_eq!(facts.crawlable_links, vec!["/hidden"]);
        assert_eq!(
            facts.mailto_links,
            vec!["mailto:one@example.tld", "tel:123"]
        );
    }

    #[test]
    fn keeps_the_php_exclusions_and_case_sensitive_data_rot_check() {
        let facts = extract(
            "<a href='https://wa.me/123'></a><a href='https://www.facebook.com/example'></a><span DATA-ROT='/uvqqra'></span>",
        );
        assert_eq!(facts.linked_docs, vec!["/uvqqra"]);
        assert_eq!(facts.crawlable_links, vec!["/uvqqra"]);
    }

    #[test]
    fn accepts_latin_one_urls_the_php_byte_regex_accepts() {
        let facts = extract("<a href='/é/ß/ø'></a><a href='https://éxample.com/ß'></a>");
        assert_eq!(facts.linked_docs, vec!["/é/ß/ø", "https://éxample.com/ß"]);
    }

    #[test]
    fn keeps_a_final_newline_in_a_link_accepted_by_php() {
        let facts = extract("<a href=\"/one\n\">x</a>");
        assert_eq!(facts.linked_docs, vec!["/one\n"]);
    }

    #[test]
    fn collects_distinct_date_shortcodes_outside_literal_blocks() {
        let facts = extract(
            "date(Y) date(Y) DATE(Y) date(M) <code>date(S)</code><pre>date(W)</pre><script>date(B)</script><style>date(A)</style>",
        );
        assert_eq!(facts.date_shortcodes, vec!["date(Y)", "DATE(Y)", "date(M)"]);
    }
}
