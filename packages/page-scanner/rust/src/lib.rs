#![forbid(unsafe_code)]

use regex::Regex;
use scraper::{Html, Selector};
use serde::Serialize;
use std::collections::HashSet;
use std::sync::LazyLock;

static HREF: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r#"(?i)<a\s[^>]*?href=(?:"([^"']*)"|'([^"']*)')"#).expect("valid href regex")
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
    for image in IMAGE.find_iter(html) {
        let tag = image.as_str();
        if DECORATIVE.is_match(tag) || !attribute(tag, &ALT).is_empty() {
            continue;
        }

        let src = attribute(tag, &SRC);
        if seen.insert(src.clone()) {
            missing_alt.push(if src.is_empty() { tag.to_owned() } else { src });
        }
    }

    let document = Html::parse_document(html);
    let mut anchors = HashSet::new();
    for node in document.select(&ANCHORED) {
        if let Some(value) = node.value().attr("id") {
            anchors.insert(value.to_owned());
        }
        if let Some(value) = node.value().attr("name") {
            anchors.insert(value.to_owned());
        }
    }
    let mut anchors: Vec<String> = anchors.into_iter().collect();
    anchors.sort();

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
    use super::{Facts, extract};

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
    fn collects_id_and_name_values_once() {
        assert_eq!(
            extract("<a id='one' name='old'></a><div id='one'></div>").anchors,
            vec!["old", "one"]
        );
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
