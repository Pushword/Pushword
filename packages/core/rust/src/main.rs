//! Measurement probe only: this is not the Pushword Markdown backend.
use pulldown_cmark::{Options, Parser, html};
use serde::Deserialize;
use std::io::{self, Read, Write};

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct Request {
    documents: Vec<String>,
}

fn markdown(source: &str) -> String {
    let options =
        Options::ENABLE_TABLES | Options::ENABLE_STRIKETHROUGH | Options::ENABLE_TASKLISTS;
    let parser = Parser::new_ext(source, options);
    let mut output = String::with_capacity(source.len());
    html::push_html(&mut output, parser);
    output
}

fn main() -> Result<(), Box<dyn std::error::Error>> {
    let mut input = String::new();
    io::stdin()
        .take(16 * 1024 * 1024 + 1)
        .read_to_string(&mut input)?;
    if input.len() > 16 * 1024 * 1024 {
        return Err("probe input exceeds 16 MiB".into());
    }
    let request: Request = serde_json::from_str(&input)?;
    let documents: Vec<String> = request.documents.iter().map(|s| markdown(s)).collect();
    let mut stdout = io::stdout().lock();
    serde_json::to_writer(&mut stdout, &documents)?;
    stdout.write_all(b"\n")?;
    Ok(())
}

#[cfg(test)]
mod tests {
    use super::markdown;

    #[test]
    fn converts_standard_markdown_and_unicode() {
        assert_eq!(
            markdown("## Café 🦀\n\nHello **world**."),
            "<h2>Café 🦀</h2>\n<p>Hello <strong>world</strong>.</p>\n"
        );
        assert_eq!(markdown(""), "");
    }

    #[test]
    fn does_not_claim_to_implement_pushword_extensions() {
        assert_eq!(
            markdown("#[hidden](/path)"),
            "<p>#<a href=\"/path\">hidden</a></p>\n"
        );
    }
}
