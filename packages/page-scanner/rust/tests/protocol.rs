use serde_json::Value;
use std::io::Write;
use std::process::{Command, Stdio};

#[test]
fn serves_multiple_requests_without_mixing_responses() {
    let mut child = Command::new(env!("CARGO_BIN_EXE_pushword-page-facts"))
        .stdin(Stdio::piped())
        .stdout(Stdio::piped())
        .spawn()
        .expect("start worker");
    let stdin = child.stdin.as_mut().expect("worker stdin");
    stdin
        .write_all(b"{\"version\":1,\"id\":4,\"operation\":\"scan_rendered_html\",\"documents\":[\"<a href='/one'>1</a>\"]}\n{\"version\":1,\"id\":5,\"operation\":\"scan_rendered_html\",\"documents\":[\"<img src=/x>\"]}\n")
        .expect("send requests");
    let output = child.wait_with_output().expect("worker output");
    assert!(output.status.success());

    let lines: Vec<Value> = output
        .stdout
        .split(|byte| *byte == b'\n')
        .filter(|line| !line.is_empty())
        .map(|line| serde_json::from_slice(line).expect("JSON response"))
        .collect();
    assert_eq!(lines.len(), 2);
    assert_eq!(lines[0]["id"], 4);
    assert_eq!(lines[0]["documents"][0]["hrefs"][0], "/one");
    assert_eq!(
        lines[0]["documents"][0]["linked_attributes"][0]["value"],
        "/one"
    );
    assert_eq!(lines[1]["id"], 5);
    assert_eq!(lines[1]["documents"][0]["missing_alt"][0], "/x");
}

#[test]
fn rejects_unknown_operations() {
    let mut child = Command::new(env!("CARGO_BIN_EXE_pushword-page-facts"))
        .stdin(Stdio::piped())
        .stdout(Stdio::null())
        .stderr(Stdio::null())
        .spawn()
        .expect("start worker");
    child
        .stdin
        .take()
        .expect("worker stdin")
        .write_all(b"{\"version\":1,\"id\":1,\"operation\":\"unknown\",\"documents\":[]}\n")
        .expect("send request");
    assert!(!child.wait().expect("worker exit").success());
}
