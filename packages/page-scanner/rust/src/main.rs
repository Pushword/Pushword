#![forbid(unsafe_code)]

use pushword_page_facts::{Facts, extract};
use serde::{Deserialize, Serialize};
use std::io::{self, BufRead, Read, Write};

const MAX_FRAME_BYTES: usize = 16 * 1024 * 1024;

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct Request {
    version: u8,
    id: u64,
    operation: String,
    documents: Vec<String>,
}

#[derive(Serialize)]
struct Response {
    version: u8,
    id: u64,
    documents: Vec<Facts>,
}

fn main() -> Result<(), Box<dyn std::error::Error>> {
    let stdin = io::stdin();
    let mut input = stdin.lock();
    let mut output = io::stdout().lock();
    let mut line = String::new();

    loop {
        line.clear();
        if input
            .by_ref()
            .take((MAX_FRAME_BYTES + 1) as u64)
            .read_line(&mut line)?
            == 0
        {
            return Ok(());
        }
        if line.len() > MAX_FRAME_BYTES || !line.ends_with('\n') {
            return Err("invalid request frame".into());
        }

        let request: Request = serde_json::from_str(&line)?;
        if request.version != 1 || request.operation != "scan_rendered_html" {
            return Err("unsupported request".into());
        }

        let response = Response {
            version: 1,
            id: request.id,
            documents: request.documents.iter().map(|html| extract(html)).collect(),
        };
        serde_json::to_writer(&mut output, &response)?;
        output.write_all(b"\n")?;
        output.flush()?;
    }
}
