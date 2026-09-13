//! Optional HTML analysis and experimental Comrak rendering of Pushword content.
pub mod split;
use comrak::{
    Arena, Options,
    html::{self, ChildRendering, Context},
    nodes::{Attributes, ListType, Node, NodeValue, Sourcepos, TableAlignment},
    options::Plugins,
    parse_document,
};
use std::borrow::Cow;
use std::fmt::{self, Write};

struct RenderSettings<'a> {
    source: &'a str,
    line_starts: Vec<usize>,
    fenced_code_pre_class: &'a str,
}

impl RenderSettings<'_> {
    fn source_span(&self, position: Sourcepos) -> Option<&str> {
        if position.start.line != position.end.line {
            return None;
        }
        let line_start = *self.line_starts.get(position.start.line.checked_sub(1)?)?;
        self.source.get(
            line_start + position.start.column.checked_sub(1)?..line_start + position.end.column,
        )
    }
}

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
    apply_block_attributes(root);
    apply_list_item_attributes(root);
    let mut output = String::with_capacity(source.len());
    let settings = RenderSettings {
        source,
        line_starts: std::iter::once(0)
            .chain(source.match_indices('\n').map(|(index, _)| index + 1))
            .collect(),
        fenced_code_pre_class,
    };
    html::format_document_with_formatter(
        root,
        &options,
        &mut output,
        &Plugins::default(),
        render,
        &settings,
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

fn parse_link_attributes(input: &str) -> Option<(Vec<(String, String)>, usize)> {
    if !input.starts_with('{') {
        return None;
    }
    let mut attributes = Vec::new();
    let mut classes = Vec::new();
    let mut index = 1;
    while index < input.len() {
        let remaining = &input[index..];
        let character = remaining.chars().next()?;
        if character.is_ascii_whitespace() {
            index += character.len_utf8();
            continue;
        }
        if character == '}' {
            if !classes.is_empty() {
                attributes.insert(0, ("class".into(), classes.join(" ")));
            }
            return (!attributes.is_empty()).then_some((attributes, index + 1));
        }
        let shorthand = matches!(character, '#' | '.');
        if shorthand {
            index += 1;
        }
        let start = index;
        while index < input.len() {
            let character = input[index..].chars().next()?;
            if character.is_whitespace() || character == '}' || (!shorthand && character == '=') {
                break;
            }
            index += character.len_utf8();
        }
        if index == start {
            return None;
        }
        let name = &input[start..index];
        if shorthand {
            if character == '.' {
                let class = name.strip_prefix('-').unwrap_or(name);
                if !class.starts_with(|first: char| first == '_' || first.is_ascii_alphabetic()) {
                    return None;
                }
                classes.push(name.to_owned());
            } else {
                set_attribute(&mut attributes, "id", name);
            }
            continue;
        }
        if !input[index..].starts_with('=') {
            return None;
        }
        index += 1;
        let quote = input[index..].chars().next()?;
        let value = if matches!(quote, '\'' | '"') {
            index += 1;
            let start = index;
            while !input[index..].starts_with(quote) {
                index += input[index..].chars().next()?.len_utf8();
            }
            let value = &input[start..index];
            index += 1;
            value
        } else {
            let start = index;
            while index < input.len() {
                let character = input[index..].chars().next()?;
                if character.is_whitespace() || character == '}' {
                    break;
                }
                index += character.len_utf8();
            }
            &input[start..index]
        };
        if name.eq_ignore_ascii_case("class") {
            classes.extend(value.split_whitespace().map(str::to_owned));
        } else if !name.to_ascii_lowercase().starts_with("on") {
            set_attribute(&mut attributes, name, value);
        }
    }
    None
}

fn link_attributes(node: Node<'_>, settings: &RenderSettings<'_>) -> Vec<(String, String)> {
    let ast = node.data();
    let fallback = attributes(ast.attrs.as_deref());
    let source = settings.source_span(ast.sourcepos);
    drop(ast);
    if let Some(source) = source
        && source.ends_with('}')
        && let Some(start) = source.rfind('{')
        && let Some((attrs, consumed)) = parse_link_attributes(&source[start..])
        && consumed == source.len() - start
    {
        return attrs;
    }
    if let Some(next) = node.next_sibling() {
        let mut ast = next.data_mut();
        if let NodeValue::Text(text) = &mut ast.value
            && let Some((attrs, consumed)) = parse_link_attributes(text)
        {
            *text = Cow::Owned(text[consumed..].to_owned());
            return attrs;
        }
    }
    fallback
}

fn apply_attributes(node: Node<'_>, values: Vec<(String, String)>) {
    let mut attributes = node
        .data_mut()
        .attrs
        .take()
        .map_or_else(Attributes::default, |attrs| *attrs);
    for (name, value) in values {
        match name.as_str() {
            "class" => attributes
                .classes
                .extend(value.split_whitespace().map(str::to_owned)),
            "id" => attributes.id = Some(value),
            _ => attributes.pairs.push((name, value)),
        }
    }
    node.data_mut().attrs = Some(Box::new(attributes));
}

fn apply_block_attributes(root: Node<'_>) {
    for paragraph in root.descendants().collect::<Vec<_>>() {
        if !matches!(paragraph.data().value, NodeValue::Paragraph) {
            continue;
        }
        if paragraph
            .parent()
            .is_some_and(|parent| matches!(parent.data().value, NodeValue::Document))
            && let Some(last) = paragraph.last_child()
        {
            let suffix = {
                let ast = last.data();
                if let NodeValue::Text(text) = &ast.value {
                    text.rfind(" {").and_then(|start| {
                        parse_link_attributes(&text[start + 1..])
                            .filter(|(_, consumed)| *consumed == text.len() - start - 1)
                            .map(|(attrs, _)| (attrs, start))
                    })
                } else {
                    None
                }
            };
            if let Some((attrs, start)) = suffix {
                if let NodeValue::Text(text) = &mut last.data_mut().value {
                    *text = Cow::Owned(text[..start].to_owned());
                }
                apply_attributes(paragraph, attrs);
            }
        }
        let Some(first) = paragraph.first_child() else {
            continue;
        };
        let attributes = {
            let ast = first.data();
            match &ast.value {
                NodeValue::Text(text) => parse_link_attributes(text)
                    .filter(|(_, consumed)| *consumed == text.len())
                    .map(|(attrs, _)| attrs),
                _ => None,
            }
        };
        let Some(attributes) = attributes else {
            continue;
        };
        if let Some(next) = first.next_sibling()
            && matches!(
                next.data().value,
                NodeValue::SoftBreak | NodeValue::LineBreak
            )
        {
            next.detach();
            first.detach();
            apply_attributes(paragraph, attributes);
            continue;
        }
        if first.next_sibling().is_some() {
            continue;
        }
        let marker_line = paragraph.data().sourcepos.start.line;
        if let Some(next) = paragraph.next_sibling()
            && next.data().sourcepos.start.line == marker_line + 1
        {
            apply_attributes(next, attributes);
        } else if let Some(previous) = paragraph.previous_sibling()
            && previous.data().sourcepos.end.line + 1 == marker_line
        {
            apply_attributes(previous, attributes);
        }
        paragraph.detach();
    }
}

fn apply_list_item_attributes(root: Node<'_>) {
    for item in root.descendants() {
        if !matches!(item.data().value, NodeValue::Item(_)) {
            continue;
        }
        let Some(paragraph) = item.first_child() else {
            continue;
        };
        if !matches!(paragraph.data().value, NodeValue::Paragraph) {
            continue;
        }
        let Some(first) = paragraph.first_child() else {
            continue;
        };
        let parsed = {
            let ast = first.data();
            match &ast.value {
                NodeValue::Text(text) => parse_link_attributes(text),
                _ => None,
            }
        };
        if let Some((attrs, consumed)) = parsed
            && let NodeValue::Text(text) = &mut first.data_mut().value
            && text[consumed..].starts_with(' ')
        {
            *text = Cow::Owned(text[consumed + 1..].to_owned());
            apply_attributes(item, attrs);
        }
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

fn empty_table_head(node: Node<'_>) -> bool {
    node.children().next().is_some()
        && node.children().all(|cell| {
            cell.children().all(|child| {
                matches!(&child.data().value, NodeValue::Text(text) if text.trim().is_empty())
            })
        })
}

fn colspan_marker(node: Node<'_>) -> bool {
    let mut children = node.children();
    children.next().is_some_and(
        |child| matches!(&child.data().value, NodeValue::Text(text) if text.trim() == "->"),
    ) && children.next().is_none()
}

fn colspan(node: Node<'_>) -> usize {
    1 + node
        .following_siblings()
        .skip(1)
        .take_while(|sibling| colspan_marker(sibling))
        .count()
}

fn render_spanning_cell(
    context: &mut Context<&RenderSettings<'_>>,
    node: Node<'_>,
    entering: bool,
    span: usize,
) -> fmt::Result {
    let row = node.parent().expect("table cells have a row");
    let header = matches!(row.data().value, NodeValue::TableRow(true));
    let tag = if header { "th" } else { "td" };
    if entering {
        context.cr()?;
        write!(context, "<{tag}")?;
        let index = std::iter::successors(node.previous_sibling(), |sibling| {
            sibling.previous_sibling()
        })
        .count();
        let table = row.parent().expect("table rows have a table");
        if let NodeValue::Table(table) = &table.data().value {
            let alignment = table.alignments.get(index).unwrap_or(&TableAlignment::None);
            let name = match alignment {
                TableAlignment::Left => Some("left"),
                TableAlignment::Center => Some("center"),
                TableAlignment::Right => Some("right"),
                TableAlignment::None => None,
            };
            if let Some(name) = name {
                write!(context, " align=\"{name}\"")?;
            }
        }
        write!(context, " colspan=\"{span}\">")?;
    } else {
        write!(context, "</{tag}>")?;
    }
    Ok(())
}

fn render(
    context: &mut Context<&RenderSettings<'_>>,
    node: Node<'_>,
    entering: bool,
) -> Result<ChildRendering, fmt::Error> {
    let ast = node.data();
    match &ast.value {
        NodeValue::Link(link) => {
            if entering {
                let mut attrs = link_attributes(node, context.user);
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
                let pre_class = context.user.fenced_code_pre_class;
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
            let block_attributes = node
                .parent()
                .is_some_and(|parent| matches!(parent.data().value, NodeValue::Document))
                && ast.attrs.is_some();
            if block_attributes {
                if entering {
                    context.cr()?;
                    context.write_str("<p")?;
                    write_attributes(context, &attributes(ast.attrs.as_deref()))?;
                    context.write_char('>')?;
                } else {
                    context.write_str("</p>\n")?;
                }
            } else {
                html::format_node_default(context, node, entering)?;
            }
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
        NodeValue::List(list) if ast.attrs.is_some() => {
            let (tag, start) = match list.list_type {
                ListType::Bullet => ("ul", None),
                ListType::Ordered => ("ol", (list.start != 1).then_some(list.start)),
            };
            if entering {
                context.cr()?;
                write!(context, "<{tag}")?;
                write_attributes(context, &attributes(ast.attrs.as_deref()))?;
                if let Some(start) = start {
                    write!(context, " start=\"{start}\"")?;
                }
                context.write_str(">\n")?;
            } else {
                writeln!(context, "</{tag}>")?;
            }
        }
        NodeValue::Item(_) if ast.attrs.is_some() => {
            if entering {
                context.cr()?;
                context.write_str("<li")?;
                write_attributes(context, &attributes(ast.attrs.as_deref()))?;
                context.write_char('>')?;
            } else {
                context.write_str("</li>\n")?;
            }
        }
        NodeValue::TableRow(true) if empty_table_head(node) => {
            return Ok(ChildRendering::Skip);
        }
        NodeValue::TableCell => {
            if colspan_marker(node)
                && std::iter::successors(node.previous_sibling(), |sibling| {
                    sibling.previous_sibling()
                })
                .any(|sibling| !colspan_marker(sibling))
            {
                return Ok(ChildRendering::Skip);
            }
            let span = colspan(node);
            if span == 1 || colspan_marker(node) {
                return html::format_node_default(context, node, entering);
            }
            render_spanning_cell(context, node, entering, span)?;
        }
        _ => return html::format_node_default(context, node, entering),
    }
    Ok(ChildRendering::HTML)
}

#[cfg(test)]
mod tests {
    use super::markdown;
    use proptest::prelude::*;
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

    proptest! {
        #![proptest_config(ProptestConfig::with_cases(256))]

        #[test]
        fn arbitrary_utf8_markdown_never_panics(source in ".{0,2048}") {
            let _ = markdown(&source, "");
        }
    }
}
