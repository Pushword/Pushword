use pushword_html_minifier::minify;
use serde::{Deserialize, Serialize};
use std::io::{self, BufRead, Read, Write};
use std::process::ExitCode;

// Match the PHP adapter's limit. Each frame is released before reading the next.
const MAX_FRAME_BYTES: u64 = 16 * 1024 * 1024;

#[derive(Deserialize)]
#[serde(deny_unknown_fields)]
struct Request {
    version: u32,
    id: u64,
    operation: String,
    documents: Vec<String>,
}

#[derive(Serialize)]
struct Response {
    version: u32,
    id: u64,
    documents: Vec<String>,
}

fn run() -> Result<(), Box<dyn std::error::Error>> {
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
        if request.version != 1 || request.operation != "minify_html" {
            return Err("unsupported protocol version or operation".into());
        }
        let documents = request.documents.iter().map(|html| minify(html)).collect();
        serde_json::to_writer(
            &mut output,
            &Response {
                version: 1,
                id: request.id,
                documents,
            },
        )?;
        output.write_all(b"\n")?;
        output.flush()?;
    }
}

fn main() -> ExitCode {
    match run() {
        Ok(()) => ExitCode::SUCCESS,
        Err(error) => {
            eprintln!("pushword-html-minifier: {error}");
            ExitCode::FAILURE
        }
    }
}
