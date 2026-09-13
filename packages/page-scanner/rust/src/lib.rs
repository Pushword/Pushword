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

#[derive(Debug, PartialEq, Eq, Serialize)]
pub struct Facts {
    pub hrefs: Vec<String>,
    pub missing_alt: Vec<String>,
    pub anchors: Vec<String>,
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

    Facts {
        hrefs,
        missing_alt,
        anchors,
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
            }
        );
    }
}
