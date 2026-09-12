//! Analyse canonical HTML fragments without changing PHP's slugging rules.
//!
//! Unsupported or repaired markup is explicitly declined. PHP remains the
//! reference for those documents, rather than accepting a different DOM tree.
use ego_tree::NodeRef;
use scraper::{ElementRef, Html, Node};
use serde::{Deserialize, Serialize};

#[derive(Debug, Deserialize)]
#[serde(deny_unknown_fields)]
pub struct Document {
    pub html: String,
    pub toc: bool,
}

#[derive(Debug, Serialize)]
pub struct Heading {
    pub seed: String,
    pub label: String,
    pub level: u8,
    pub listed: bool,
}

#[derive(Debug, Serialize)]
pub struct Analysis {
    pub chapeau: String,
    /// HTML before/between/after heading-id slots, excluding the chapeau.
    pub segments: Vec<String>,
    pub headings: Vec<Heading>,
    pub paragraphs: Vec<String>,
    pub paragraphs_with_chapeau: Vec<String>,
}

#[derive(Default)]
struct Walker {
    html: String,
    slots: Vec<(usize, usize)>,
    headings: Vec<Heading>,
    paragraphs: Vec<(usize, String)>,
    breaks: Vec<usize>,
    heading_starts: Vec<usize>,
    cutoff: bool,
}

fn escape(value: &str, attribute: bool) -> String {
    let mut output = String::with_capacity(value.len());
    for ch in value.chars() {
        match ch {
            '&' => output.push_str("&amp;"),
            '<' if !attribute => output.push_str("&lt;"),
            '>' if !attribute => output.push_str("&gt;"),
            '"' if attribute => output.push_str("&quot;"),
            '\u{a0}' => output.push_str("&nbsp;"),
            _ => output.push(ch),
        }
    }
    output
}

fn paragraph_text(element: ElementRef<'_>) -> String {
    // PHP's /\s+/ without /u and trim() use ASCII whitespace.
    element
        .text()
        .collect::<String>()
        .split([' ', '\t', '\n', '\r', '\x0b', '\x0c'])
        .filter(|word| !word.is_empty())
        .collect::<Vec<_>>()
        .join(" ")
}

impl Walker {
    fn visit(&mut self, node: NodeRef<'_, Node>, depth: usize, body_start: usize) -> Option<()> {
        if depth > 128 {
            return None;
        }
        match node.value() {
            Node::Text(text) => self.html.push_str(&escape(text, false)),
            Node::Comment(comment) => {
                if comment.contains(['<', '>']) || comment.contains("--") {
                    return None;
                }
                if &**comment == "break" {
                    if depth != 0 {
                        return None;
                    }
                    self.breaks.push(self.html.len());
                }
                if self.html.len() >= body_start
                    && matches!(
                        comment.trim_matches([' ', '\t', '\r', '\n', '\0', '\x0b']),
                        "stop-toc" | "end-toc"
                    )
                {
                    self.cutoff = true;
                }
                self.html.push_str("<!--");
                self.html.push_str(comment);
                self.html.push_str("-->");
            }
            Node::Element(_) => {
                let element = ElementRef::wrap(node)?;
                let tag = element.value().name();
                // These HTML elements have the same explicit, balanced tree in
                // Masterminds and html5ever. Foreign/raw/template content falls back.
                if !matches!(
                    tag,
                    "h1" | "h2"
                        | "h3"
                        | "h4"
                        | "h5"
                        | "h6"
                        | "p"
                        | "div"
                        | "section"
                        | "article"
                        | "aside"
                        | "span"
                        | "a"
                        | "em"
                        | "strong"
                        | "b"
                        | "i"
                        | "s"
                        | "del"
                        | "code"
                        | "pre"
                        | "blockquote"
                        | "ul"
                        | "ol"
                        | "li"
                        | "br"
                        | "hr"
                        | "img"
                        | "figure"
                        | "figcaption"
                        | "table"
                        | "thead"
                        | "tbody"
                        | "tfoot"
                        | "tr"
                        | "th"
                        | "td"
                ) {
                    return None;
                }
                let heading =
                    tag.len() == 2 && tag.starts_with('h') && tag.as_bytes()[1].is_ascii_digit();
                if heading && depth != 0 {
                    return None;
                }
                let start = self.html.len();
                if tag.starts_with('h') && depth == 0 {
                    self.heading_starts.push(start);
                }
                let in_body = start >= body_start;
                self.html.push('<');
                self.html.push_str(tag);
                let mut slot = None;
                for (name, value) in element.value().attrs() {
                    // Empty attributes have library-specific boolean rules.
                    if value.is_empty() || name.contains(':') || name == "xmlns" {
                        return None;
                    }
                    let start = self.html.len();
                    self.html.push(' ');
                    self.html.push_str(name);
                    self.html.push_str("=\"");
                    self.html.push_str(&escape(value, true));
                    self.html.push('"');
                    if heading && in_body && name == "id" {
                        slot = Some((start, self.html.len()));
                    }
                }
                let insert = self.html.len();
                self.html.push('>');
                if heading && in_body {
                    self.slots.push(slot.unwrap_or((insert, insert)));
                    let text = element.text().collect::<String>();
                    // PHP's ?: treats the string "0" as false.
                    let title = element.attr("title").filter(|v| !v.is_empty() && *v != "0");
                    let id = element.attr("id").filter(|v| !v.is_empty() && *v != "0");
                    self.headings.push(Heading {
                        seed: id.or(title).unwrap_or(&text).to_owned(),
                        label: title.unwrap_or(&text).to_owned(),
                        level: tag.as_bytes()[1] - b'0',
                        listed: !self.cutoff,
                    });
                }
                if tag == "p" && depth == 0 {
                    let text = paragraph_text(element);
                    if !text.is_empty() {
                        self.paragraphs.push((start, text));
                    }
                }
                for child in node.children() {
                    self.visit(child, depth + 1, body_start)?;
                }
                if !matches!(tag, "br" | "hr" | "img") {
                    self.html.push_str("</");
                    self.html.push_str(tag);
                    self.html.push('>');
                }
            }
            _ => return None,
        }
        Some(())
    }
}

pub fn analyze(document: &Document) -> Option<Analysis> {
    // CR/NUL and form feed are normalized differently by the two HTML parsers.
    if document.html.contains(['\r', '\0', '\x0b', '\x0c']) {
        return None;
    }
    let body_start = document.html.find("<!--break-->").map_or(0, |i| i + 12);
    let parsed = Html::parse_fragment(&document.html);
    if !parsed.errors.is_empty() {
        return None;
    }
    let mut walker = Walker::default();
    for node in parsed.root_element().children() {
        walker.visit(node, 0, body_start)?;
    }
    // This declines implicit end tags, foster parenting, duplicate attributes,
    // unknown entity spelling, quote changes and all other serialization drift.
    if walker.html != document.html {
        return None;
    }
    if !document
        .html
        .match_indices("<!--break-->")
        .map(|(i, _)| i)
        .eq(walker.breaks.iter().copied())
    {
        return None;
    }
    let body = &document.html[body_start..];
    let intro_end = if document.toc {
        body.find("<h").map_or(body_start, |i| body_start + i)
    } else {
        body_start
    };
    if document.toc && body.contains("<h") && !walker.heading_starts.contains(&intro_end) {
        return None;
    }
    let primary_end = walker
        .breaks
        .iter()
        .copied()
        .find(|&i| i >= intro_end)
        .unwrap_or(document.html.len());
    // A break immediately before the first heading belongs to the intro under
    // the legacy split rules. Leave this unusual layout to PHP.
    if document.toc
        && body[..intro_end - body_start]
            .trim_end()
            .ends_with("<!--break-->")
    {
        return None;
    }
    let paragraphs = walker
        .paragraphs
        .iter()
        .filter(|(i, _)| *i >= intro_end && *i < primary_end)
        .map(|(_, text)| text.clone())
        .collect();
    let paragraphs_with_chapeau = walker
        .paragraphs
        .into_iter()
        .filter(|(i, _)| *i < primary_end)
        .map(|(_, text)| text)
        .collect();
    let mut segments = Vec::new();
    let mut previous = body_start;
    if document.toc {
        for (start, end) in walker.slots {
            segments.push(document.html[previous..start].to_owned());
            previous = end;
        }
    }
    segments.push(document.html[previous..].to_owned());
    Some(Analysis {
        chapeau: if body_start == 0 {
            String::new()
        } else {
            document.html[..body_start - 12].to_owned()
        },
        segments,
        headings: if document.toc {
            walker.headings
        } else {
            Vec::new()
        },
        paragraphs,
        paragraphs_with_chapeau,
    })
}

#[cfg(test)]
mod tests {
    use super::{Document, analyze};
    use proptest::prelude::*;
    use serde::Deserialize;

    #[derive(Deserialize)]
    struct Case {
        name: String,
        html: String,
        native: bool,
    }

    #[test]
    fn shared_eligibility_corpus() {
        let cases: Vec<Case> = serde_json::from_str(include_str!("../tests/split.json")).unwrap();
        for case in cases {
            assert_eq!(
                analyze(&Document {
                    html: case.html,
                    toc: true
                })
                .is_some(),
                case.native,
                "{}",
                case.name
            );
        }
    }

    #[test]
    fn all_fields_come_from_one_document() {
        let result = analyze(&Document {
            html: "<p>Lede.</p><!--break--><p>Intro.</p><h2>Title</h2><p>Body.</p><!--break--><p>Extra.</p>".into(),
            toc: true,
        }).unwrap();
        assert_eq!(result.chapeau, "<p>Lede.</p>");
        assert_eq!(result.headings[0].seed, "Title");
        assert_eq!(
            result.segments,
            [
                "<p>Intro.</p><h2",
                ">Title</h2><p>Body.</p><!--break--><p>Extra.</p>"
            ]
        );
        assert_eq!(result.paragraphs, ["Body."]);
        assert_eq!(result.paragraphs_with_chapeau, ["Lede.", "Intro.", "Body."]);
    }

    #[test]
    fn depth_is_bounded() {
        assert!(
            analyze(&Document {
                html: format!("{}text{}", "<div>".repeat(200), "</div>".repeat(200)),
                toc: true
            })
            .is_none()
        );
    }

    proptest! {
        #![proptest_config(ProptestConfig::with_cases(512))]

        #[test]
        fn generated_plain_documents_have_valid_slots(titles in prop::collection::vec("[A-Za-z0-9 ]{1,40}", 0..50)) {
            let html = titles.iter().map(|title| format!("<h2>{title}</h2><p>Text.</p>")).collect::<String>();
            let result = analyze(&Document { html, toc: true }).unwrap();
            prop_assert_eq!(result.headings.len(), titles.len());
            prop_assert_eq!(result.segments.len(), titles.len() + 1);
            for (heading, title) in result.headings.iter().zip(titles) {
                prop_assert_eq!(&heading.seed, &title);
            }
        }

        #[test]
        fn arbitrary_utf8_never_panics(html in ".{0,2048}") {
            let _ = analyze(&Document { html, toc: true });
        }
    }
}
