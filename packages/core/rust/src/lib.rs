//! Optional HTML analysis and experimental Comrak rendering of Pushword content.
pub mod split;
use comrak::{
    Arena, Options,
    html::{self, ChildRendering, Context},
    nodes::{Attributes, ListType, Node, NodeValue, Sourcepos, TableAlignment},
    options::Plugins,
    parse_document,
};
use regex::Regex;
use std::borrow::Cow;
use std::collections::HashMap;
use std::fmt::{self, Write};
use std::sync::OnceLock;

struct RenderSettings<'a> {
    source: &'a str,
    line_starts: Vec<usize>,
    fenced_code_pre_class: &'a str,
    locale: Option<&'a str>,
    date_values: Option<&'a HashMap<String, String>>,
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

/// Decline Markdown whose rendering depends on Pushword's site services.
/// The check is deliberately conservative: false positives cost PHP work,
/// whereas a false negative would change the rendered page.
pub fn markdown_if_supported(source: &str, fenced_code_pre_class: &str) -> Option<String> {
    markdown_if_supported_with_context(source, fenced_code_pre_class, None, true)
}

pub fn markdown_if_supported_with_context(
    source: &str,
    fenced_code_pre_class: &str,
    locale: Option<&str>,
    allow_obfuscated_links: bool,
) -> Option<String> {
    markdown_if_supported_with_dates(
        source,
        fenced_code_pre_class,
        locale,
        allow_obfuscated_links,
        None,
    )
}

pub fn markdown_if_supported_with_dates(
    source: &str,
    fenced_code_pre_class: &str,
    locale: Option<&str>,
    allow_obfuscated_links: bool,
    date_values: Option<&HashMap<String, String>>,
) -> Option<String> {
    if source.contains("[!")
        || source.contains("![")
        || (source.contains("date(") && date_values.is_none())
        || (source.contains("#[") && source.contains("mailto:") && source.contains('@'))
    {
        return None;
    }

    let phone = contains_phone(source);
    // PHP leaves literal non-breaking spaces in phone numbers as plain text.
    if (phone && (locale.is_none() || source.contains('\u{a0}')))
        || (!allow_obfuscated_links && (source.contains("#[") || phone))
        || (phone && (source.contains("**") || source.contains('_') || source.contains("  \n")))
        || source.contains("00390")
        || source.starts_with("| | | -> |")
        || source.lines().any(|line| {
            (line.starts_with("    |") && line.trim_start().starts_with("| ---"))
                || (line.len() >= 20 && line.trim().is_empty())
        })
        || dotted_date_range_pattern().is_match(source)
    {
        return None;
    }

    markdown_with_context(source, fenced_code_pre_class, locale, date_values)
}

fn dotted_date_range_pattern() -> &'static Regex {
    static PATTERN: OnceLock<Regex> = OnceLock::new();
    PATTERN.get_or_init(|| {
        Regex::new(r"\b\d{2}\.\d{2}\.-\d{2}\.\d{2}\.\d{4}\b").expect("valid date range pattern")
    })
}

fn contains_phone(source: &str) -> bool {
    let bytes = source.as_bytes();
    for start in 0..bytes.len() {
        let prefix = if bytes[start..].starts_with(b"+33") {
            3
        } else if bytes[start..].starts_with(b"0033") {
            4
        } else if bytes[start] == b'0' {
            1
        } else {
            continue;
        };
        let mut position = start + prefix;
        skip_phone_separators(bytes, &mut position);
        if !bytes
            .get(position)
            .is_some_and(|digit| (b'1'..=b'9').contains(digit))
        {
            continue;
        }
        position += 1;
        let mut pairs = 0;
        while pairs < 4 {
            skip_phone_separators(bytes, &mut position);
            if bytes.get(position).is_some_and(u8::is_ascii_digit)
                && bytes.get(position + 1).is_some_and(u8::is_ascii_digit)
            {
                position += 2;
                pairs += 1;
            } else {
                break;
            }
        }
        if pairs == 4 {
            return true;
        }
    }
    false
}

fn skip_phone_separators(bytes: &[u8], position: &mut usize) {
    loop {
        let remaining = &bytes[*position..];
        if remaining.starts_with(b"&nbsp;") {
            *position += 6;
        } else if remaining.starts_with(&[0xc2, 0xa0]) {
            *position += 2;
        } else if remaining
            .first()
            .is_some_and(|byte| byte.is_ascii_whitespace() || matches!(byte, b'.' | b'-'))
        {
            *position += 1;
        } else {
            return;
        }
    }
}

/// Convert the supported Markdown subset. This is not a complete PHP replacement.
pub fn markdown(source: &str, fenced_code_pre_class: &str) -> String {
    markdown_with_context(source, fenced_code_pre_class, None, None)
        .expect("Markdown without date values cannot be declined")
}

fn markdown_with_context(
    source: &str,
    fenced_code_pre_class: &str,
    locale: Option<&str>,
    date_values: Option<&HashMap<String, String>>,
) -> Option<String> {
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
        locale,
        date_values,
    };
    if let Some(values) = date_values {
        for node in root.descendants() {
            match &node.data().value {
                NodeValue::Text(text) if !date_text_supported(node, text, &settings, values) => {
                    return None;
                }
                NodeValue::Link(link)
                    if link.url.contains("date(") || link.title.contains("date(") =>
                {
                    return None;
                }
                NodeValue::HtmlInline(html) if html.contains("date(") => return None,
                NodeValue::HtmlBlock(html) if html.literal.contains("date(") => return None,
                _ => {}
            }
        }
    }
    html::format_document_with_formatter(
        root,
        &options,
        &mut output,
        &Plugins::default(),
        render,
        &settings,
    )
    .expect("writing HTML to a String cannot fail");
    Some(output)
}

fn date_pattern() -> &'static Regex {
    static PATTERN: OnceLock<Regex> = OnceLock::new();
    PATTERN.get_or_init(|| Regex::new(r"date\([^)]+\)").expect("valid date pattern"))
}

fn date_text_supported(
    node: Node<'_>,
    text: &str,
    settings: &RenderSettings<'_>,
    values: &HashMap<String, String>,
) -> bool {
    let rendered = date_pattern()
        .find_iter(text)
        .map(|found| found.as_str())
        .collect::<Vec<_>>();
    if rendered.is_empty() {
        return true;
    }
    let Some(raw) = settings.source_span(node.data().sourcepos) else {
        return false;
    };
    let source = date_pattern().find_iter(raw).collect::<Vec<_>>();
    rendered.len() == source.len()
        && rendered.iter().zip(source).all(|(token, found)| {
            let escaped = raw[..found.start()]
                .bytes()
                .rev()
                .take_while(|byte| *byte == b'\\')
                .count()
                % 2
                == 1;
            !escaped && *token == found.as_str() && values.contains_key(*token)
        })
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

fn obfuscated_link(node: Node<'_>) -> bool {
    node.previous_sibling().is_some_and(
        |previous| matches!(&previous.data().value, NodeValue::Text(text) if text.ends_with('#')),
    )
}

fn obfuscated_link_attributes(
    node: Node<'_>,
    settings: &RenderSettings<'_>,
) -> Vec<(String, String)> {
    let mut attrs = link_attributes(node, settings);
    for name in ["class", "id"] {
        if let Some(index) = attrs.iter().position(|(key, _)| key == name) {
            let attribute = attrs.remove(index);
            attrs.push(attribute);
        }
    }
    attrs
}

fn rot13_link(url: &str) -> String {
    let url = url
        .strip_prefix("http://")
        .map(|path| format!("-{path}"))
        .or_else(|| url.strip_prefix("https://").map(|path| format!("_{path}")))
        .or_else(|| url.strip_prefix("mailto:").map(|path| format!("@{path}")))
        .unwrap_or_else(|| url.to_owned());
    url.chars()
        .map(|character| match character {
            'a'..='z' => char::from(b'a' + (character as u8 - b'a' + 13) % 26),
            'A'..='Z' => char::from(b'A' + (character as u8 - b'A' + 13) % 26),
            _ => character,
        })
        .collect::<String>()
        .replace("&nzc;", "&")
}

fn write_link_attributes(output: &mut dyn Write, attrs: &[(String, String)]) -> fmt::Result {
    for (name, value) in attrs {
        if value.is_empty() {
            if name != "class" && name != "style" {
                write!(output, " {name}")?;
            }
        } else {
            write!(output, " {name}=\"{}\"", value.replace('"', "&quot;"))?;
        }
    }
    Ok(())
}

fn email_pattern() -> &'static Regex {
    static PATTERN: OnceLock<Regex> = OnceLock::new();
    PATTERN.get_or_init(|| {
        Regex::new(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}")
            .expect("the e-mail pattern is valid")
    })
}

fn phone_pattern() -> &'static Regex {
    static PATTERN: OnceLock<Regex> = OnceLock::new();
    PATTERN.get_or_init(|| {
        Regex::new(r"(?:(?:\+|00)33|0)(?:\s|&nbsp;)*[1-9](?:(?:[\s.-]|&nbsp;)*[0-9]{2}){4}")
            .expect("the phone pattern is valid")
    })
}

fn email_boundary(text: &str, start: usize, end: usize) -> bool {
    let bytes = text.as_bytes();
    let previous = start.checked_sub(1).and_then(|index| bytes.get(index));
    let next = bytes.get(end);
    !previous.is_some_and(|byte| byte.is_ascii_alphanumeric() || b"._+-\"'>".contains(byte))
        && next.is_none_or(|byte| b" \t\n.,!? )>\x0b\x0c\x0d</".contains(byte))
}

fn phone_boundary(text: &str, start: usize, end: usize) -> bool {
    let bytes = text.as_bytes();
    (start == 0 || bytes[start - 1] != b'>')
        && bytes
            .get(end)
            .is_none_or(|byte| b" \t\n.,!?)>\x0b\x0c\x0d</;".contains(byte))
}

fn write_encoded_email(output: &mut dyn Write, email: &str) -> fmt::Result {
    let (local, domain) = email.split_once('@').expect("matched e-mail has an @ sign");
    write!(
        output,
        "<span class=nojs>{local}{}{domain}</span> <span class=\"cea hidden\">{}</span>",
        include_str!("email-at.svg").trim_end(),
        rot13_link(email)
    )
}

fn write_phone(
    output: &mut dyn Write,
    number: &str,
    locale: &str,
    encoded_nbsp: bool,
) -> fmt::Result {
    let number = if encoded_nbsp {
        Cow::Owned(number.replace('\u{a0}', "&nbsp;"))
    } else {
        Cow::Borrowed(number)
    };
    let readable = if locale == "fr" && number.starts_with("+33") {
        format!("0{}", number[3..].strip_prefix(' ').unwrap_or(&number[3..]))
    } else {
        number.to_string()
    };
    let target = format!(
        "tel:{}",
        number.replace([' ', '.'], "").replace("&nbsp;", "")
    );
    write!(
        output,
        "<span data-rot=\"{}\">{}</span>",
        rot13_link(&target),
        readable.replace(' ', "&nbsp;")
    )
}

fn render_contact_text(
    output: &mut Context<&RenderSettings<'_>>,
    text: &str,
    follows_html: bool,
) -> fmt::Result {
    let mut candidates = email_pattern()
        .find_iter(text)
        .map(|candidate| (candidate.start(), candidate.end(), false))
        .collect::<Vec<_>>();
    if output.user.locale.is_some() {
        candidates.extend(
            phone_pattern()
                .find_iter(text)
                .map(|candidate| (candidate.start(), candidate.end(), true)),
        );
    }
    candidates.sort_by_key(|candidate| candidate.0);
    let mut cursor = 0;
    for (start, end, is_phone) in candidates {
        if start < cursor || (follows_html && start == 0) {
            continue;
        }
        let valid = if is_phone {
            phone_boundary(text, start, end)
        } else {
            email_boundary(text, start, end)
        };
        if !valid {
            continue;
        }
        output.escape(&text[cursor..start])?;
        if is_phone {
            let locale = output.user.locale.expect("phone has locale");
            let encoded_nbsp = output.user.source.contains("&nbsp;");
            write_phone(output, &text[start..end], locale, encoded_nbsp)?;
        } else {
            write_encoded_email(output, &text[start..end])?;
        }
        cursor = end;
    }
    output.escape(&text[cursor..])
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
        NodeValue::Text(text) if entering => {
            let text = if text.ends_with('#')
                && node
                    .next_sibling()
                    .is_some_and(|next| matches!(next.data().value, NodeValue::Link(_)))
            {
                &text[..text.len() - 1]
            } else {
                text
            };
            let replaced = context.user.date_values.map(|values| {
                date_pattern().replace_all(text, |capture: &regex::Captures<'_>| {
                    values
                        .get(&capture[0])
                        .cloned()
                        .unwrap_or_else(|| capture[0].to_owned())
                })
            });
            let text = replaced.as_deref().unwrap_or(text);
            if node
                .ancestors()
                .any(|ancestor| matches!(ancestor.data().value, NodeValue::Link(_)))
            {
                context.escape(text)?;
            } else {
                render_contact_text(
                    context,
                    text,
                    node.previous_sibling().is_some_and(|previous| {
                        matches!(previous.data().value, NodeValue::HtmlInline(_))
                    }),
                )?;
            }
        }
        NodeValue::Link(link) => {
            if obfuscated_link(node) {
                if entering {
                    let mut attrs = obfuscated_link_attributes(node, context.user);
                    set_attribute(&mut attrs, "data-rot", &rot13_link(&encode_url(&link.url)));
                    context.write_str("<span")?;
                    write_link_attributes(context, &attrs)?;
                    context.write_char('>')?;
                } else {
                    context.write_str("</span>")?;
                }
                return Ok(ChildRendering::HTML);
            }
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
    use super::{
        markdown, markdown_if_supported, markdown_if_supported_with_context,
        markdown_if_supported_with_dates,
    };
    use proptest::prelude::*;
    use serde::Deserialize;
    use std::collections::HashMap;

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
    fn obfuscated_links_match_the_default_php_component() {
        assert_eq!(
            markdown("#[hidden](/path)", ""),
            "<p><span data-rot=\"/cngu\">hidden</span></p>\n"
        );
        assert_eq!(
            markdown(
                "#[*Café*](https://example.com/café){.button target=\"_blank\"}",
                ""
            ),
            "<p><span target=\"_blank\" class=\"button\" data-rot=\"_rknzcyr.pbz/pns%P3%N9\"><em>Café</em></span></p>\n"
        );
        assert_eq!(
            markdown("`#[literal](/path)`", ""),
            "<p><code>#[literal](/path)</code></p>\n"
        );
    }

    #[test]
    fn site_dependent_markdown_is_declined() {
        for source in [
            "> [!note] Notice",
            "![alt](/image.jpg)",
            "date(Y)",
            "#[contact](mailto:contact@example.com)",
            "Call +33 1 23 45 67 89",
            "Call 01&nbsp;23&nbsp;45&nbsp;67&nbsp;89",
            "Call 01\u{a0}23\u{a0}45\u{a0}67\u{a0}89",
        ] {
            assert_eq!(markdown_if_supported(source, ""), None, "{source}");
        }
        assert_eq!(
            markdown_if_supported("A **simple** paragraph", ""),
            Some("<p>A <strong>simple</strong> paragraph</p>\n".into())
        );
        assert_eq!(
            markdown_if_supported("#[hidden](/path)", ""),
            Some("<p><span data-rot=\"/cngu\">hidden</span></p>\n".into())
        );
        assert!(
            markdown_if_supported("contact@example.com", "")
                .is_some_and(|html| html.contains("class=\"cea hidden\""))
        );
    }

    #[test]
    fn ambiguous_real_world_markdown_uses_php() {
        for source in [
            "01.05.-15.05.2027  \n26.06.-10.07.2027",
            "Call **04 76 95 23 00** for details.",
            "_Call 04.75.81.72.62_",
            "Call 0495  \n515303 for details.",
            "ITA Airways 00390685960020",
            "| A | B |\n    | --- | --- |\n    | 1 | 2 |",
            "| | | -> |\n| --- | --- | --- |\n| | Price | -> |",
            "- **Itinerary** : Route\n                      \n                      Next day",
        ] {
            assert_eq!(
                markdown_if_supported_with_context(source, "", Some("fr"), true),
                None,
                "{source}"
            );
        }
    }

    #[test]
    fn date_values_only_replace_unescaped_markdown_text() {
        let values = HashMap::from([("date(Y)".to_owned(), "2026".to_owned())]);
        let render =
            |source| markdown_if_supported_with_dates(source, "", Some("fr"), true, Some(&values));
        assert_eq!(render("Café date(Y)"), Some("<p>Café 2026</p>\n".into()));
        assert_eq!(
            render("[date(Y)](/archive)"),
            Some("<p><a href=\"/archive\">2026</a></p>\n".into())
        );
        assert_eq!(
            render("`date(Y)` and date(Y)"),
            Some("<p><code>date(Y)</code> and 2026</p>\n".into())
        );
        assert_eq!(render("[date(Y)](/archive/date(Y))"), None);
        assert_eq!(render("<span title=\"date(Y)\">date(Y)</span>"), None);
        assert_eq!(render("\\date(Y)"), None);
        assert_eq!(render("date(Y) and d&#97;te(Y)"), None);
        assert_eq!(
            markdown_if_supported_with_dates(
                "date(Y)",
                "",
                Some("fr"),
                true,
                Some(&HashMap::new())
            ),
            None
        );
        assert_eq!(
            markdown_if_supported_with_context("date(Y)", "", Some("fr"), true),
            None
        );
    }

    #[test]
    fn phone_links_use_the_requested_locale() {
        assert_eq!(
            markdown_if_supported_with_context("Call +33 1 23 45 67 89", "", Some("fr"), true),
            Some("<p>Call <span data-rot=\"gry:+33123456789\">01&nbsp;23&nbsp;45&nbsp;67&nbsp;89</span></p>\n".into())
        );
        assert_eq!(
            markdown_if_supported_with_context("Call +33 1 23 45 67 89", "", Some("en"), true),
            Some("<p>Call <span data-rot=\"gry:+33123456789\">+33&nbsp;1&nbsp;23&nbsp;45&nbsp;67&nbsp;89</span></p>\n".into())
        );
        assert_eq!(
            markdown_if_supported_with_context("Call 01 23 45 67 89", "", Some("fr"), false),
            None
        );
        assert_eq!(
            markdown_if_supported_with_context(
                "Call 01&nbsp;23&nbsp;45&nbsp;67&nbsp;89",
                "",
                Some("fr"),
                true
            ),
            Some("<p>Call <span data-rot=\"gry:0123456789\">01&nbsp;23&nbsp;45&nbsp;67&nbsp;89</span></p>\n".into())
        );
        assert_eq!(
            markdown_if_supported_with_context(
                "Call 01&nbsp;23&nbsp;45&nbsp;67&nbsp;89 and \u{a0}",
                "",
                Some("fr"),
                true
            ),
            None
        );
        assert_eq!(
            markdown_if_supported_with_context(
                "Call 01\u{a0}23\u{a0}45\u{a0}67\u{a0}89",
                "",
                Some("fr"),
                true
            ),
            None
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
