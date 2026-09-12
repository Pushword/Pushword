//! Port of Pushword's HtmlMinifier rules; HTML parsing uses html5ever via scraper.
use ego_tree::NodeRef;
use regex::Regex;
use scraper::{ElementRef, Html, Node, Selector};
use std::{borrow::Cow, sync::LazyLock};

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

fn escape_uri(value: &str) -> String {
    let mut output = String::new();
    for byte in value.trim_start_matches([' ', '\t', '\r', '\n']).bytes() {
        if (0x21..0x7f).contains(&byte) {
            output.push(byte as char);
        } else {
            use std::fmt::Write;
            write!(output, "%{byte:02X}").unwrap();
        }
    }
    output
}

// DOMDocument::saveHTML uses HTML4 boolean/void tables, even after an HTML5 parse.
fn serialize(element: ElementRef<'_>, output: &mut String) {
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
        output.push(' ');
        if let Some(prefix) = name.prefix.as_ref().filter(|prefix| !prefix.is_empty()) {
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
        let mut escaped = escape_text(value).replace("&amp;{", "&{");
        if name.prefix.is_none()
            && (matches!(name.local.as_ref(), "href" | "src" | "action")
                || (tag == "a" && name.local.as_ref() == "name"))
        {
            escaped = escape_uri(&escaped);
        }
        let quote = if escaped.contains('"') && !escaped.contains('\'') {
            '\''
        } else {
            '"'
        };
        output.push('=');
        output.push(quote);
        if quote == '"' {
            escaped = escaped.replace('"', "&quot;");
        }
        output.push_str(&escaped);
        output.push(quote);
    }
    output.push('>');
    append_children(*element, matches!(tag, "script" | "style"), output);
    if !matches!(
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
    ) {
        output.push_str("</");
        output.push_str(tag);
        output.push('>');
    }
}

fn append_children(parent: NodeRef<'_, Node>, raw_text: bool, output: &mut String) {
    for child in parent.children() {
        match child.value() {
            Node::Element(_) => serialize(ElementRef::wrap(child).unwrap(), output),
            Node::Fragment => append_children(child, raw_text, output),
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

fn outer_html(element: ElementRef<'_>) -> String {
    let mut output = String::new();
    serialize(element, &mut output);
    output
}

pub fn minify(html: &str) -> String {
    let stripped = COMMENTS.replace_all(html, "");
    if !stripped.starts_with("<!DOCTYPE html>") {
        return stripped.into_owned();
    }

    let document = Html::parse_document(&stripped);
    let mut output = String::with_capacity(stripped.len());
    output.push_str("<!DOCTYPE html>");
    serialize(document.root_element(), &mut output);
    let mut protected = Vec::new();
    // Keep PHP's replacement order, including nested tags and repeated markup.
    for (tag, selector) in PROTECTED.iter() {
        for (index, node) in document.select(selector).enumerate() {
            let placeholder = format!("<{tag}-placeholder-{index}></{tag}-placeholder-{index}>");
            let original = outer_html(node);
            output = output.replace(&original, &placeholder);
            protected.push((placeholder, original));
        }
    }

    output = replace_whitespace(&NEWLINES, output, " ");
    output = replace_whitespace(&HORIZONTAL, output, " ");
    output = replace_whitespace(&BEFORE_BLOCK, output, "$1");
    output = replace_whitespace(&AFTER_BLOCK, output, "$1");
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
