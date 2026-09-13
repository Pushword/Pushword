use pushword_content_probe::{
    markdown_if_supported_with_dates,
    split::{Document, analyze, diagnose},
};
use serde::{Deserialize, Serialize, de::DeserializeOwned};
use serde_json::Value;
use std::collections::HashMap;
use std::io::{self, BufRead, Read, Write};

const MAX_FRAME_BYTES: u64 = 16 * 1024 * 1024;

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct Request {
    version: u32,
    id: u64,
    operation: String,
    documents: Vec<Value>,
}

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct MarkdownDocument {
    markdown: String,
    fenced_code_pre_class: String,
    locale: Option<String>,
    allow_obfuscated_links: Option<bool>,
    date_values: Option<HashMap<String, String>>,
}

#[derive(Serialize)]
struct Response<T: Serialize> {
    version: u32,
    id: u64,
    documents: Vec<T>,
}

fn parse_documents<T: DeserializeOwned>(
    documents: Vec<Value>,
) -> Result<Vec<T>, serde_json::Error> {
    documents.into_iter().map(serde_json::from_value).collect()
}

fn main() -> Result<(), Box<dyn std::error::Error>> {
    let mut input = io::stdin().lock();
    let mut output = io::stdout().lock();
    loop {
        let mut frame = String::new();
        if input
            .by_ref()
            .take(MAX_FRAME_BYTES + 1)
            .read_line(&mut frame)?
            == 0
        {
            return Ok(());
        }
        if frame.len() as u64 > MAX_FRAME_BYTES || !frame.ends_with('\n') {
            return Err("oversized or incomplete request".into());
        }
        let request: Request = serde_json::from_str(&frame)?;
        if request.version != 1 {
            return Err("unsupported version or operation".into());
        }
        let mut frame = match request.operation.as_str() {
            "split_content" => serde_json::to_vec(&Response {
                version: 1,
                id: request.id,
                documents: parse_documents::<Document>(request.documents)?
                    .iter()
                    .map(analyze)
                    .collect(),
            })?,
            "diagnose_split" => serde_json::to_vec(&Response {
                version: 1,
                id: request.id,
                documents: parse_documents::<Document>(request.documents)?
                    .iter()
                    .map(|document| diagnose(document).err())
                    .collect(),
            })?,
            "render_markdown" => serde_json::to_vec(&Response {
                version: 1,
                id: request.id,
                documents: parse_documents::<MarkdownDocument>(request.documents)?
                    .iter()
                    .map(|document| {
                        if document.allow_obfuscated_links.is_none()
                            && document.markdown.contains('@')
                        {
                            return None;
                        }

                        markdown_if_supported_with_dates(
                            &document.markdown,
                            &document.fenced_code_pre_class,
                            document.locale.as_deref(),
                            document.allow_obfuscated_links.unwrap_or(false),
                            document.date_values.as_ref(),
                        )
                    })
                    .collect(),
            })?,
            _ => return Err("unsupported version or operation".into()),
        };
        frame.push(b'\n');
        if frame.len() as u64 > MAX_FRAME_BYTES {
            return Err("oversized response".into());
        }
        output.write_all(&frame)?;
        output.flush()?;
    }
}
