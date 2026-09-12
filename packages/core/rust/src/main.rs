//! Measurement probe only: this is not the Pushword Markdown backend.
use pushword_content_probe::markdown;
use serde::Deserialize;
use std::io::{self, Read, Write};

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct Request {
    documents: Vec<String>,
    #[serde(default)]
    fenced_code_pre_class: String,
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
    let documents: Vec<String> = request
        .documents
        .iter()
        .map(|source| markdown(source, &request.fenced_code_pre_class))
        .collect();
    let mut stdout = io::stdout().lock();
    serde_json::to_writer(&mut stdout, &documents)?;
    stdout.write_all(b"\n")?;
    Ok(())
}
