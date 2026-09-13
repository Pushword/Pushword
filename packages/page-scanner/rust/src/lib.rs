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

#[derive(Debug, PartialEq, Eq, Serialize)]
pub struct LinkedAttribute {
    pub name: String,
    pub value: String,
}

#[derive(Debug, PartialEq, Eq, Serialize)]
pub struct Facts {
    pub hrefs: Vec<String>,
    pub missing_alt: Vec<String>,
    pub anchors: Vec<String>,
    pub linked_attributes: Vec<LinkedAttribute>,
    pub srcsets: Vec<String>,
    pub date_shortcodes: Vec<String>,
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
    let linked_attributes = LINKED_ATTRIBUTE
        .captures_iter(&searchable)
        .map(|capture| LinkedAttribute {
            name: capture.get(1).expect("attribute name").as_str().to_owned(),
            value: (2..=4)
                .find_map(|index| capture.get(index))
                .expect("attribute value")
                .as_str()
                .to_owned(),
        })
        .collect();
    let srcsets = SRCSET
        .captures_iter(&searchable)
        .map(|capture| {
            capture
                .get(1)
                .or_else(|| capture.get(2))
                .expect("quoted srcset")
                .as_str()
                .to_owned()
        })
        .collect();

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
        linked_attributes,
        srcsets,
        date_shortcodes,
    }
}

#[cfg(test)]
mod tests {
    use super::{Facts, LinkedAttribute, extract};

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
                linked_attributes: vec![
                    LinkedAttribute {
                        name: "href".into(),
                        value: "/one".into()
                    },
                    LinkedAttribute {
                        name: "href".into(),
                        value: "/two".into()
                    },
                    LinkedAttribute {
                        name: "src".into(),
                        value: "/x".into()
                    },
                    LinkedAttribute {
                        name: "src".into(),
                        value: "/x".into()
                    },
                    LinkedAttribute {
                        name: "src".into(),
                        value: "/y".into()
                    },
                    LinkedAttribute {
                        name: "src".into(),
                        value: "/z".into()
                    },
                ],
                srcsets: vec![],
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
                linked_attributes: vec![],
                srcsets: vec![],
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
                linked_attributes: vec![],
                srcsets: vec![],
                date_shortcodes: vec![],
            }
        );
    }

    #[test]
    fn collects_scanner_links_and_srcsets_outside_code_samples() {
        let facts = extract(
            "<code><a href='/ignore'>x</a></code><a href='/one'>x</a><img src='/one' data-img='/two' srcset='/three 1x, /four 2x'><pre><img src='/ignore'></pre>",
        );

        assert_eq!(
            facts.linked_attributes,
            vec![
                LinkedAttribute {
                    name: "href".into(),
                    value: "/one".into(),
                },
                LinkedAttribute {
                    name: "src".into(),
                    value: "/one".into(),
                },
                LinkedAttribute {
                    name: "data-img".into(),
                    value: "/two".into(),
                },
            ]
        );
        assert_eq!(facts.srcsets, vec!["/three 1x, /four 2x"]);
    }

    #[test]
    fn preserves_the_php_scanners_empty_quoted_attribute_match() {
        assert_eq!(
            extract("<a href=\"\"></a><a href=\"/next\">").linked_attributes,
            vec![LinkedAttribute {
                name: "href".into(),
                value: "\"></a><a href=".into(),
            }]
        );
    }

    #[test]
    fn preserves_unquoted_attribute_separator_and_empty_srcset() {
        let facts = extract("<img src=/one data-img='/two' srcset=\"\">");
        assert_eq!(
            facts.linked_attributes,
            vec![LinkedAttribute {
                name: "src".into(),
                value: "/one".into(),
            }]
        );
        assert_eq!(facts.srcsets, vec![""]);
    }

    #[test]
    fn collects_distinct_date_shortcodes_outside_literal_blocks() {
        let facts = extract(
            "date(Y) date(Y) DATE(Y) date(M) <code>date(S)</code><pre>date(W)</pre><script>date(B)</script><style>date(A)</style>",
        );
        assert_eq!(facts.date_shortcodes, vec!["date(Y)", "DATE(Y)", "date(M)"]);
    }
}
