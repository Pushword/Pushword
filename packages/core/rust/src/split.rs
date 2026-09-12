//! Prepare split content from rendered HTML while retaining PHP's slug rules.
//!
//! The serializer matches the Masterminds output used by TOC\MarkupFixer for
//! supported structures. Ambiguous markers and qualified XLink attributes
//! are declined so the PHP reference handles them.
use ego_tree::NodeRef;
use scraper::{ElementRef, Html, Node};
use serde::{Deserialize, Serialize};

const BREAK_MARKER: &str = "<!--break-->";

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

#[derive(Debug, PartialEq, Eq, Serialize)]
#[serde(rename_all = "snake_case")]
pub enum DeclineReason {
    NormalizedControl,
    ParseError,
    DepthLimit,
    UnsupportedAttribute,
    AmbiguousBreak,
    AmbiguousHeading,
}

#[derive(Default)]
struct Walker {
    html: String,
    slots: Vec<(usize, usize)>,
    headings: Vec<Heading>,
    breaks: usize,
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

fn paragraphs(html: &str) -> Vec<String> {
    Html::parse_fragment(&legacy_entities(html))
        .root_element()
        .children()
        .filter_map(ElementRef::wrap)
        .filter(|element| element.value().name() == "p")
        .map(paragraph_text)
        .filter(|text| !text.is_empty())
        .collect()
}

fn legacy_entities(html: &str) -> String {
    // Masterminds leaves an unterminated &nbsp in text untouched, while
    // html5ever accepts it as a non-breaking space.
    let mut output = String::with_capacity(html.len());
    let mut remaining = html;
    while let Some(index) = remaining.find("&nbsp") {
        output.push_str(&remaining[..index]);
        remaining = &remaining[index + 5..];
        output.push_str(if remaining.starts_with(';') {
            "&nbsp"
        } else {
            "&amp;nbsp"
        });
    }
    output.push_str(remaining);
    output
}

fn first_break_in_attribute(html: &str) -> bool {
    let Some(marker) = html.find(BREAK_MARKER) else {
        return false;
    };
    let bytes = html.as_bytes();
    let mut tag = false;
    let mut quote = 0;
    let mut i = 0;
    while i < marker {
        if quote != 0 {
            if bytes[i] == quote {
                quote = 0;
            }
        } else if bytes[i..].starts_with(b"<!--") {
            if let Some(end) = html[i + 4..marker].find("-->") {
                i += end + 7;
                continue;
            }
        } else {
            match bytes[i] {
                b'<' => tag = true,
                b'>' => tag = false,
                b'"' | b'\'' if tag => quote = bytes[i],
                _ => {}
            }
        }
        i += 1;
    }
    quote != 0
}

fn non_boolean_attribute(name: &str, html_namespace: bool) -> bool {
    html_namespace
        && (name.starts_with("data-")
            || matches!(
                name,
                "href"
                    | "hreflang"
                    | "http-equiv"
                    | "icon"
                    | "id"
                    | "keytype"
                    | "kind"
                    | "label"
                    | "lang"
                    | "language"
                    | "list"
                    | "maxlength"
                    | "media"
                    | "method"
                    | "name"
                    | "placeholder"
                    | "rel"
                    | "rows"
                    | "rowspan"
                    | "sandbox"
                    | "spellcheck"
                    | "scope"
                    | "seamless"
                    | "shape"
                    | "size"
                    | "sizes"
                    | "span"
                    | "src"
                    | "srcdoc"
                    | "srclang"
                    | "srcset"
                    | "start"
                    | "step"
                    | "style"
                    | "summary"
                    | "tabindex"
                    | "target"
                    | "title"
                    | "type"
                    | "value"
                    | "width"
                    | "border"
                    | "charset"
                    | "cite"
                    | "class"
                    | "code"
                    | "codebase"
                    | "color"
                    | "cols"
                    | "colspan"
                    | "content"
                    | "coords"
                    | "data"
                    | "datetime"
                    | "default"
                    | "dir"
                    | "dirname"
                    | "enctype"
                    | "for"
                    | "form"
                    | "formaction"
                    | "headers"
                    | "height"
                    | "accept"
                    | "accept-charset"
                    | "accesskey"
                    | "action"
                    | "align"
                    | "alt"
                    | "bgcolor"
            ))
}

fn void_element(tag: &str) -> bool {
    matches!(
        tag,
        "area"
            | "base"
            | "br"
            | "col"
            | "command"
            | "embed"
            | "hr"
            | "img"
            | "input"
            | "keygen"
            | "link"
            | "meta"
            | "param"
            | "source"
            | "track"
            | "wbr"
    )
}

impl Walker {
    fn visit(&mut self, node: NodeRef<'_, Node>, depth: usize) -> Result<(), DeclineReason> {
        if depth > 128 {
            return Err(DeclineReason::DepthLimit);
        }
        match node.value() {
            Node::Text(text) => {
                let raw = node
                    .parent()
                    .and_then(ElementRef::wrap)
                    .is_some_and(|parent| {
                        matches!(
                            parent.value().name(),
                            "script" | "style" | "xmp" | "iframe" | "noembed" | "noframes"
                        )
                    });
                if raw {
                    // The unterminated-entity rewrite is only valid in parsed text.
                    if text.contains("&amp;nbsp") {
                        return Err(DeclineReason::ParseError);
                    }
                    self.html.push_str(text);
                } else {
                    self.html.push_str(&escape(text, false));
                }
            }
            Node::Comment(comment) => {
                if &**comment == "break" {
                    self.breaks += 1;
                }
                if matches!(
                    comment.trim_matches([' ', '\t', '\r', '\n', '\0', '\x0b']),
                    "stop-toc" | "end-toc"
                ) {
                    self.cutoff = true;
                }
                self.html.push_str("<!--");
                self.html.push_str(comment);
                self.html.push_str("-->");
            }
            Node::Element(_) => {
                let element = ElementRef::wrap(node).ok_or(DeclineReason::ParseError)?;
                let tag = element.value().name();
                let heading = tag.len() == 2
                    && tag.starts_with('h')
                    && matches!(tag.as_bytes()[1], b'1'..=b'6');
                let html_namespace =
                    element.value().name.ns.as_ref() == "http://www.w3.org/1999/xhtml";
                if element.value().attrs.keys().any(|name| {
                    name.prefix
                        .as_ref()
                        .is_some_and(|prefix| prefix.as_ref() == "xlink")
                }) {
                    return Err(DeclineReason::UnsupportedAttribute);
                }
                self.html.push('<');
                self.html.push_str(tag);
                let mut slot = None;
                for (name, value) in element.value().attrs() {
                    if value.contains("<h") {
                        return Err(DeclineReason::AmbiguousHeading);
                    }
                    if value.contains(BREAK_MARKER) {
                        return Err(DeclineReason::AmbiguousBreak);
                    }
                    if name == "xmlns"
                        && matches!(
                            value,
                            "http://www.w3.org/2000/svg" | "http://www.w3.org/1998/Math/MathML"
                        )
                    {
                        continue;
                    }
                    let start = self.html.len();
                    self.html.push(' ');
                    self.html.push_str(name);
                    if !value.is_empty() || non_boolean_attribute(name, html_namespace) {
                        self.html.push_str("=\"");
                        self.html.push_str(&escape(value, true));
                        self.html.push('"');
                    }
                    if heading && name == "id" {
                        slot = Some((start, self.html.len()));
                    }
                }
                let insert = self.html.len();
                let foreign_empty = !html_namespace && node.children().next().is_none();
                self.html.push_str(if foreign_empty { " />" } else { ">" });
                if heading {
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
                for child in node.children() {
                    self.visit(child, depth + 1)?;
                }
                if !foreign_empty && !(html_namespace && void_element(tag)) {
                    self.html.push_str("</");
                    self.html.push_str(tag);
                    self.html.push('>');
                }
            }
            _ => return Err(DeclineReason::ParseError),
        }
        Ok(())
    }
}

pub fn analyze(document: &Document) -> Option<Analysis> {
    diagnose(document).ok()
}

pub fn diagnose(document: &Document) -> Result<Analysis, DeclineReason> {
    if first_break_in_attribute(&document.html) {
        return Err(DeclineReason::AmbiguousBreak);
    }
    // CR/NUL and form feed are normalized differently by the two HTML parsers.
    if document.toc && document.html.contains(['\r', '\0', '\x0b', '\x0c']) {
        return Err(DeclineReason::NormalizedControl);
    }
    let body_start = document
        .html
        .find(BREAK_MARKER)
        .map_or(0, |i| i + BREAK_MARKER.len());
    let chapeau = if body_start == 0 {
        ""
    } else {
        &document.html[..body_start - BREAK_MARKER.len()]
    };
    let source_body = &document.html[body_start..];
    let parsed = Html::parse_fragment(&legacy_entities(source_body));
    let mut walker = Walker::default();
    for node in parsed.root_element().children() {
        walker.visit(node, 0)?;
    }
    if source_body.matches(BREAK_MARKER).count() != walker.breaks {
        return Err(DeclineReason::AmbiguousBreak);
    }
    let body = if document.toc {
        &walker.html
    } else {
        source_body
    };
    let intro_end = if document.toc {
        body.find("<h").unwrap_or(0)
    } else {
        0
    };
    let intro = if intro_end == 0 {
        ""
    } else {
        &body[..intro_end]
    };
    let content = if intro_end == 0 {
        body
    } else {
        &body[intro_end..]
    };
    let content = if intro.trim().ends_with(BREAK_MARKER) {
        format!("{BREAK_MARKER}{content}")
    } else {
        content.to_owned()
    };
    let main = content
        .split_once(BREAK_MARKER)
        .map_or(content.as_str(), |(first, _)| first);
    let paragraph_values = paragraphs(main);
    let paragraphs_with_chapeau = paragraphs(&format!("{chapeau}{intro}{main}"));
    let mut segments = Vec::new();
    let mut previous = 0;
    if document.toc {
        for (start, end) in walker.slots {
            segments.push(body[previous..start].to_owned());
            previous = end;
        }
    }
    segments.push(body[previous..].to_owned());
    Ok(Analysis {
        chapeau: chapeau.to_owned(),
        segments,
        headings: if document.toc {
            walker.headings
        } else {
            Vec::new()
        },
        paragraphs: paragraph_values,
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
