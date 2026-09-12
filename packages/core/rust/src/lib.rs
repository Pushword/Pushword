//! Optional HTML analysis and experimental Comrak rendering of Pushword content.
pub mod split;
use comrak::{
    Arena, Options,
    html::{self, ChildRendering, Context},
    nodes::{Attributes, Node, NodeValue},
    options::Plugins,
    parse_document,
};
use std::fmt::{self, Write};

/// Convert the supported Markdown subset. This is not a complete PHP replacement.
pub fn markdown(source: &str, fenced_code_pre_class: &str) -> String {
    let mut options = Options::default();
    options.extension.strikethrough = true;
    options.extension.table = true;
    options.extension.tasklist = true;
    options.extension.header_attributes = true;
    options.extension.link_attributes = true;
    options.extension.inline_code_attributes = true;
    // Pushword permits embedded HTML. This rendering option is unrelated to
    // unsafe Rust, which remains forbidden by this crate's lint configuration.
    options.render.r#unsafe = true;

    let arena = Arena::new();
    let root = parse_document(&arena, source, &options);
    let mut output = String::with_capacity(source.len());
    html::format_document_with_formatter(
        root,
        &options,
        &mut output,
        &Plugins::default(),
        render,
        fenced_code_pre_class,
    )
    .expect("writing HTML to a String cannot fail");
    output
}

fn attributes(attrs: Option<&Attributes>) -> Vec<(String, String)> {
    let Some(attrs) = attrs else {
        return Vec::new();
    };
    let mut values = Vec::new();
    if !attrs.classes.is_empty() {
        set_attribute(&mut values, "class", &attrs.classes.join(" "));
    }
    // PHP removes event handlers, including mixed case.
    for (name, value) in &attrs.pairs {
        if !name.to_ascii_lowercase().starts_with("on") && !(name == "class" && value.is_empty()) {
            set_attribute(&mut values, name, value);
        }
    }
    if let Some(id) = &attrs.id {
        set_attribute(&mut values, "id", id);
    }
    values
}

fn set_attribute(attrs: &mut Vec<(String, String)>, name: &str, value: &str) {
    if let Some((_, previous)) = attrs.iter_mut().find(|(key, _)| key == name) {
        value.clone_into(previous);
    } else {
        attrs.push((name.into(), value.into()));
    }
}

fn write_attributes(output: &mut dyn Write, attrs: &[(String, String)]) -> fmt::Result {
    for (name, value) in attrs {
        write!(output, " {name}=\"")?;
        html::escape(output, value)?;
        output.write_char('"')?;
    }
    Ok(())
}

fn encode_url(url: &str) -> String {
    let bytes = url.as_bytes();
    let mut output = String::with_capacity(bytes.len());
    for (index, &byte) in bytes.iter().enumerate() {
        let escaped = byte == b'%'
            && bytes.get(index + 1).is_some_and(u8::is_ascii_hexdigit)
            && bytes.get(index + 2).is_some_and(u8::is_ascii_hexdigit);
        if byte.is_ascii_alphanumeric() || b"!#$&'()*+,-./:;=?@_~".contains(&byte) || escaped {
            output.push(char::from(byte));
        } else {
            write!(output, "%{byte:02X}").expect("writing to a String cannot fail");
        }
    }
    output
}

fn loose_task(node: Node<'_>) -> bool {
    matches!(node.data().value, NodeValue::TaskItem(_))
        && node.parent().is_some_and(
            |parent| matches!(&parent.data().value, NodeValue::List(list) if !list.tight),
        )
}

fn checkbox(output: &mut dyn Write, checked: bool) -> fmt::Result {
    output.write_str("<input")?;
    if checked {
        output.write_str(" checked=\"\"")?;
    }
    output.write_str(" disabled=\"\" type=\"checkbox\"> ")
}

fn render(
    context: &mut Context<&str>,
    node: Node<'_>,
    entering: bool,
) -> Result<ChildRendering, fmt::Error> {
    let ast = node.data();
    match &ast.value {
        NodeValue::Link(link) => {
            if entering {
                let mut attrs = attributes(ast.attrs.as_deref());
                set_attribute(&mut attrs, "href", &encode_url(&link.url));
                if !link.title.is_empty() {
                    set_attribute(&mut attrs, "title", &link.title);
                }
                if attrs
                    .iter()
                    .any(|(key, value)| key == "target" && value == "_blank")
                    && !attrs.iter().any(|(key, _)| key == "rel")
                {
                    set_attribute(&mut attrs, "rel", "noopener noreferrer");
                }
                context.write_str("<a")?;
                write_attributes(context, &attrs)?;
                context.write_char('>')?;
            } else {
                context.write_str("</a>")?;
            }
        }
        NodeValue::Heading(heading) => {
            if entering {
                context.cr()?;
                write!(context, "<h{}", heading.level)?;
                write_attributes(context, &attributes(ast.attrs.as_deref()))?;
                context.write_char('>')?;
            } else {
                writeln!(context, "</h{}>", heading.level)?;
            }
        }
        NodeValue::Code(code) => {
            if entering {
                context.write_str("<code")?;
                write_attributes(context, &attributes(ast.attrs.as_deref()))?;
                context.write_char('>')?;
                context.escape(&code.literal)?;
                context.write_str("</code>")?;
            }
        }
        NodeValue::CodeBlock(code) if code.fenced => {
            if entering {
                context.cr()?;
                context.write_str("<pre")?;
                let pre_class = context.user;
                if !pre_class.is_empty() {
                    context.write_str(" class=\"")?;
                    context.escape(pre_class)?;
                    context.write_char('"')?;
                }
                context.write_str("><code")?;
                if let Some(language) = code.info.split_ascii_whitespace().next() {
                    context.write_str(" class=\"")?;
                    if !language.starts_with("language-") {
                        context.write_str("language-")?;
                    }
                    context.escape(language)?;
                    context.write_char('"')?;
                }
                context.write_char('>')?;
                context.escape(&code.literal)?;
                context.write_str("</code></pre>\n")?;
            }
        }
        NodeValue::TaskItem(task) => {
            let list_item = node
                .parent()
                .is_some_and(|parent| matches!(parent.data().value, NodeValue::List(_)));
            if entering {
                if list_item {
                    context.cr()?;
                    context.write_str("<li>")?;
                }
                if !loose_task(node) {
                    checkbox(context, task.symbol.is_some())?;
                }
            } else if list_item {
                context.write_str("</li>\n")?;
            }
        }
        NodeValue::Paragraph => {
            html::format_node_default(context, node, entering)?;
            if entering
                && let Some(parent) = node.parent().filter(|parent| loose_task(parent))
                && parent
                    .first_child()
                    .is_some_and(|first| first.same_node(node))
                && let NodeValue::TaskItem(task) = parent.data().value
            {
                checkbox(context, task.symbol.is_some())?;
            }
        }
        _ => return html::format_node_default(context, node, entering),
    }
    Ok(ChildRendering::HTML)
}

#[cfg(test)]
mod tests {
    use super::markdown;
    use serde::Deserialize;

    #[derive(Deserialize)]
    struct Case {
        name: String,
        markdown: String,
        pre_class: String,
        compatible: bool,
        php: String,
    }

    #[test]
    fn shared_php_corpus_records_parity_and_remaining_gaps() {
        let cases: Vec<Case> =
            serde_json::from_str(include_str!("../tests/markdown.json")).unwrap();
        for case in cases {
            let actual = markdown(&case.markdown, &case.pre_class);
            if case.compatible {
                assert_eq!(actual, case.php, "{}", case.name);
            } else {
                assert_ne!(
                    actual, case.php,
                    "Recheck and promote resolved gap: {}",
                    case.name
                );
            }
        }
    }

    #[test]
    fn standard_markdown_and_unicode() {
        assert_eq!(
            markdown("## Café 🦀\n\nHello **world**.", ""),
            "<h2>Café 🦀</h2>\n<p>Hello <strong>world</strong>.</p>\n"
        );
        assert_eq!(markdown("", ""), "");
    }

    #[test]
    fn site_class_is_per_call_and_escaped() {
        let source = "```language-php\n<b>&\n```";
        assert_eq!(
            markdown(source, "light\"&"),
            "<pre class=\"light&quot;&amp;\"><code class=\"language-php\">&lt;b&gt;&amp;\n</code></pre>\n"
        );
        assert_eq!(
            markdown(source, ""),
            "<pre><code class=\"language-php\">&lt;b&gt;&amp;\n</code></pre>\n"
        );
    }

    #[test]
    fn link_attributes_preserve_markup_and_filter_event_handlers() {
        assert_eq!(
            markdown(
                "[*Café*](/docs){.button OnClick=\"bad\" target=\"_blank\"}",
                ""
            ),
            "<p><a class=\"button\" target=\"_blank\" href=\"/docs\" rel=\"noopener noreferrer\"><em>Café</em></a></p>\n"
        );
    }

    #[test]
    fn dynamic_pushword_extensions_are_still_unsupported() {
        assert_eq!(
            markdown("#[hidden](/path)", ""),
            "<p>#<a href=\"/path\">hidden</a></p>\n"
        );
    }
}
