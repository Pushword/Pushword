//! Port of Pushword's HtmlMinifier rules; HTML parsing uses html5ever via scraper.
//! Runtime-dependent control serialization belongs to the PHP adapter.
use ego_tree::{NodeId, NodeRef};
use html5ever::{ParseOpts, parse_document, tendril::TendrilSink, tree_builder::TreeBuilderOpts};
use regex::Regex;
use scraper::{ElementRef, Html, HtmlTreeSink, Node, Selector};
use std::{borrow::Cow, collections::HashMap, sync::LazyLock};

#[derive(Clone, Copy, PartialEq, Eq)]
enum Serialization {
    Legacy,
    Html5,
    Html5WithXmlPrefixes,
}

static COMMENTS: LazyLock<Regex> = LazyLock::new(|| Regex::new(r"(?s)<!--(.*?)-->").unwrap());
static NEWLINES: LazyLock<Regex> = LazyLock::new(|| Regex::new(r"[ \t]*\n[ \t\r\n\x0c]*").unwrap());
static HORIZONTAL: LazyLock<Regex> = LazyLock::new(|| Regex::new(r"[ \t]{2,}").unwrap());
const BLOCK: &str = "address|article|aside|base|blockquote|body|canvas|dd|details|dialog|div|dl|dt|fieldset|figcaption|figure|footer|form|h[1-6]|head|header|hgroup|hr|html|li|link|main|map|meta|nav|ol|p|picture|section|source|style|summary|table|tbody|td|tfoot|th|thead|title|tr|ul|video";
static BEFORE_BLOCK: LazyLock<Regex> =
    LazyLock::new(|| Regex::new(&format!(r"[ \t]+(</?(?:{BLOCK})(?-u:\b))")).unwrap());
static AFTER_BLOCK: LazyLock<Regex> =
    LazyLock::new(|| Regex::new(&format!(r"(</?(?:{BLOCK})(?-u:\b)[^>]*>)[ \t]+")).unwrap());
static PROTECTED: LazyLock<Vec<(&str, Selector)>> = LazyLock::new(|| {
    ["pre", "code", "script", "textarea"]
        .into_iter()
        .map(|tag| (tag, Selector::parse(tag).unwrap()))
        .collect()
});
static STYLE: LazyLock<Selector> = LazyLock::new(|| Selector::parse("style").unwrap());
static PLACEHOLDER: LazyLock<Regex> = LazyLock::new(|| {
    Regex::new(r"<(?:pre|code|script|textarea)-placeholder-[0-9]+></(?:pre|code|script|textarea)-placeholder-[0-9]+>").unwrap()
});

fn escape_text(text: &str) -> String {
    let mut output = String::with_capacity(text.len());
    append_escaped_text(text, &mut output);
    output
}

fn append_escaped_text(mut text: &str, output: &mut String) {
    while let Some(index) = text.find(['&', '<', '>']) {
        output.push_str(&text[..index]);
        output.push_str(match text.as_bytes()[index] {
            b'&' => "&amp;",
            b'<' => "&lt;",
            _ => "&gt;",
        });
        // Matched ASCII characters always sit on UTF-8 character boundaries.
        text = &text[index + 1..];
    }
    output.push_str(text);
}

fn replace_whitespace(regex: &Regex, html: String, replacement: &str) -> String {
    match regex.replace_all(&html, replacement) {
        Cow::Borrowed(_) => html,
        Cow::Owned(replaced) => replaced,
    }
}

fn escape_uri(value: &str, html5: bool) -> String {
    let leading = value.len() - value.trim_start_matches([' ', '\t', '\r', '\n']).len();
    let mut output = String::new();
    if html5 {
        output.extend(value[..leading].chars().filter(|ch| *ch == ' '));
    }
    for byte in value[leading..].bytes() {
        if html5 && byte == b'&' {
            output.push_str("&amp;");
        } else if html5 && byte == b'"' {
            output.push_str("&quot;");
        } else if (0x21..0x7f).contains(&byte) {
            output.push(byte as char);
        } else {
            use std::fmt::Write;
            write!(output, "%{byte:02X}").unwrap();
        }
    }
    output
}

// libxml versions use either the legacy HTML4 serializer or its newer HTML5 rules.
fn serialize(
    element: ElementRef<'_>,
    output: &mut String,
    placeholders: &HashMap<NodeId, String>,
    profile: Serialization,
) {
    if let Some(placeholder) = placeholders.get(&element.id()) {
        output.push_str(placeholder);
        return;
    }
    let tag = element.value().name();
    output.push('<');
    output.push_str(tag);
    // libxml emits namespace declarations before ordinary attributes.
    let attrs = &element.value().attrs;
    let xmlns = "http://www.w3.org/2000/xmlns/";
    let ordered = attrs
        .iter()
        .filter(|(name, _)| name.ns.as_ref() == xmlns)
        .chain(attrs.iter().filter(|(name, _)| name.ns.as_ref() != xmlns));
    for (name, value) in ordered {
        // DOMDocument rejects Alpine's @event shorthand attribute names.
        if name.local.contains('@') {
            continue;
        }
        output.push(' ');
        // Some HTML5 serializers drop XML prefixes on foreign lang/space attributes.
        if let Some(prefix) = name.prefix.as_ref().filter(|prefix| {
            !prefix.is_empty()
                && !(profile == Serialization::Html5
                    && prefix.as_ref() == "xml"
                    && matches!(name.local.as_ref(), "lang" | "space"))
        }) {
            output.push_str(prefix);
            output.push(':');
        }
        output.push_str(&name.local);
        if matches!(
            name.local.as_ref(),
            "checked"
                | "compact"
                | "declare"
                | "defer"
                | "disabled"
                | "ismap"
                | "multiple"
                | "nohref"
                | "noresize"
                | "noshade"
                | "nowrap"
                | "readonly"
                | "selected"
        ) {
            continue;
        }
        let uri = name.prefix.is_none()
            && (matches!(name.local.as_ref(), "href" | "src" | "action")
                || (tag == "a" && name.local.as_ref() == "name"));
        let mut escaped = if profile != Serialization::Legacy && uri {
            escape_uri(value, true)
        } else {
            let escaped = escape_text(value);
            if uri {
                escape_uri(&escaped, false)
            } else {
                escaped
            }
        };
        let has_double_quote = escaped.contains('"');
        let quote =
            if profile == Serialization::Legacy && has_double_quote && !escaped.contains('\'') {
                '\''
            } else {
                '"'
            };
        output.push('=');
        output.push(quote);
        if quote == '"' && has_double_quote {
            escaped = escaped.replace('"', "&quot;");
        }
        output.push_str(&escaped);
        output.push(quote);
    }
    output.push('>');
    append_children(
        *element,
        matches!(tag, "script" | "style"),
        output,
        placeholders,
        profile,
    );
    if !(profile != Serialization::Legacy && matches!(tag, "embed" | "source" | "track" | "wbr"))
        && !matches!(
            tag,
            "area"
                | "base"
                | "basefont"
                | "br"
                | "col"
                | "frame"
                | "hr"
                | "img"
                | "input"
                | "isindex"
                | "link"
                | "meta"
                | "param"
        )
    {
        output.push_str("</");
        output.push_str(tag);
        output.push('>');
    }
}

fn append_children(
    parent: NodeRef<'_, Node>,
    raw_text: bool,
    output: &mut String,
    placeholders: &HashMap<NodeId, String>,
    profile: Serialization,
) {
    for child in parent.children() {
        match child.value() {
            Node::Element(_) => serialize(
                ElementRef::wrap(child).unwrap(),
                output,
                placeholders,
                profile,
            ),
            Node::Fragment => append_children(child, raw_text, output, placeholders, profile),
            Node::Text(text) => {
                if raw_text {
                    output.push_str(text);
                } else {
                    append_escaped_text(text, output);
                }
            }
            Node::Comment(comment) => {
                output.push_str("<!--");
                output.push_str(comment);
                output.push_str("-->");
            }
            _ => {}
        }
    }
}

fn outer_html(element: ElementRef<'_>, profile: Serialization) -> String {
    let mut output = String::new();
    serialize(element, &mut output, &HashMap::new(), profile);
    output
}

fn has_protected_markup(html: &str) -> bool {
    ["<pre", "<code", "<script", "<textarea"]
        .iter()
        .any(|tag| html.contains(tag))
}

fn minify_protected(document: &Html, capacity: usize, profile: Serialization) -> Option<String> {
    let mut placeholders = HashMap::new();
    let mut originals = HashMap::new();
    let mut restorations = HashMap::new();
    for (tag, selector) in PROTECTED.iter() {
        for (index, node) in document.select(selector).enumerate() {
            let original = outer_html(node, profile);
            let start = original.find('>').unwrap() + 1;
            let end = original.rfind("</").unwrap();
            // Nested markup, literal tags in raw text and placeholder collisions
            // need PHP's sequential replacement rules rather than node substitution.
            if has_protected_markup(&original[start..end]) || original.contains("-placeholder-") {
                return None;
            }
            let placeholder = originals.entry(original.clone()).or_insert_with(|| {
                format!("<{tag}-placeholder-{index}></{tag}-placeholder-{index}>")
            });
            placeholders.insert(node.id(), placeholder.clone());
            restorations.insert(placeholder.clone(), original);
        }
    }
    if document
        .select(&STYLE)
        .any(|node| has_protected_markup(&node.text().collect::<String>()))
    {
        return None;
    }
    let mut output = String::with_capacity(capacity);
    output.push_str("<!DOCTYPE html>");
    serialize(document.root_element(), &mut output, &placeholders, profile);
    output = collapse_whitespace(output);
    Some(
        PLACEHOLDER
            .replace_all(&output, |captures: &regex::Captures<'_>| {
                restorations
                    .get(&captures[0])
                    .cloned()
                    .unwrap_or_else(|| captures[0].to_owned())
            })
            .into_owned(),
    )
}

fn collapse_whitespace(mut output: String) -> String {
    output = replace_whitespace(&NEWLINES, output, " ");
    output = replace_whitespace(&HORIZONTAL, output, " ");
    output = replace_whitespace(&BEFORE_BLOCK, output, "$1");
    replace_whitespace(&AFTER_BLOCK, output, "$1")
}

pub fn minify(html: &str) -> String {
    minify_document(html, Serialization::Legacy)
}

pub fn minify_html5(html: &str) -> String {
    minify_document(html, Serialization::Html5)
}

pub fn minify_html5_with_xml_prefixes(html: &str) -> String {
    minify_document(html, Serialization::Html5WithXmlPrefixes)
}

fn minify_document(html: &str, profile: Serialization) -> String {
    let stripped = COMMENTS.replace_all(html, "");
    if !stripped.starts_with("<!DOCTYPE html>") {
        return stripped.into_owned();
    }

    let document = parse_document(
        HtmlTreeSink::new(Html::new_document()),
        ParseOpts {
            tree_builder: TreeBuilderOpts {
                scripting_enabled: false,
                ..Default::default()
            },
            ..Default::default()
        },
    )
    .one(stripped.as_ref());
    if let Some(output) = minify_protected(&document, stripped.len(), profile) {
        return output;
    }
    let mut output = String::with_capacity(stripped.len());
    output.push_str("<!DOCTYPE html>");
    serialize(
        document.root_element(),
        &mut output,
        &HashMap::new(),
        profile,
    );
    let mut protected = Vec::new();
    // Keep PHP's replacement order, including nested tags and repeated markup.
    for (tag, selector) in PROTECTED.iter() {
        for (index, node) in document.select(selector).enumerate() {
            let placeholder = format!("<{tag}-placeholder-{index}></{tag}-placeholder-{index}>");
            let original = outer_html(node, profile);
            output = output.replace(&original, &placeholder);
            protected.push((placeholder, original));
        }
    }

    output = collapse_whitespace(output);
    for (placeholder, original) in protected {
        output = output.replace(&placeholder, &original);
    }
    output
}

#[cfg(test)]
mod tests {
    use super::{escape_text, minify};
    use proptest::prelude::*;

    proptest! {
        #![proptest_config(ProptestConfig::with_cases(512))]

        #[test]
        fn streaming_escape_matches_the_original_replacements(text in ".{0,2048}") {
            let expected = text.replace('&', "&amp;").replace('<', "&lt;").replace('>', "&gt;");
            prop_assert_eq!(escape_text(&text), expected);
        }
    }

    #[test]
    fn fragments_only_lose_comments() {
        assert_eq!(minify(""), "");
        assert_eq!(minify("<p>  à  </p><!--\nx\n-->"), "<p>  à  </p>");
        assert_eq!(minify("<!-- unfinished"), "<!-- unfinished");
    }

    #[test]
    fn protects_code_and_inline_spacing() {
        let input = "<!DOCTYPE html><html><body><p>à :\n <strong>yes</strong></p>\n<pre> a\n\n b </pre></body></html>";
        let output = minify(input);
        assert!(output.contains("à : <strong>yes</strong>"));
        assert!(output.contains("</p><pre> a\n\n b </pre>"));
        assert_eq!(output, minify(&output));
    }

    #[test]
    fn svg_and_unicode_survive() {
        let input = "<!DOCTYPE html><html><body><svg viewBox=\"0 0 2 2\"><feDropShadow/></svg><p>à\u{a0}é 🦀</p></body></html>";
        let output = minify(input);
        assert!(output.contains("<feDropShadow>"));
        assert!(output.contains("viewBox="));
        assert!(output.contains("à\u{a0}é 🦀"));
    }
}
