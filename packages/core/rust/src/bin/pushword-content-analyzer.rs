use pushword_content_probe::split::{Analysis, Document, analyze};
use serde::{Deserialize, Serialize};
use std::io::{self, BufRead, Read, Write};

const MAX_FRAME_BYTES: u64 = 16 * 1024 * 1024;

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct Request {
    version: u32,
    id: u64,
    operation: String,
    documents: Vec<Document>,
}

#[derive(Serialize)]
struct Response {
    version: u32,
    id: u64,
    documents: Vec<Option<Analysis>>,
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
        if request.version != 1 || request.operation != "split_content" {
            return Err("unsupported version or operation".into());
        }
        let response = Response {
            version: 1,
            id: request.id,
            documents: request.documents.iter().map(analyze).collect(),
        };
        let mut frame = serde_json::to_vec(&response)?;
        frame.push(b'\n');
        if frame.len() as u64 > MAX_FRAME_BYTES {
            return Err("oversized response".into());
        }
        output.write_all(&frame)?;
        output.flush()?;
    }
}
