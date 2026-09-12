.PHONY: build test audit

build:
	cargo build --release --locked

test: build
	cargo test --locked
	cargo test --release --locked
	cargo clippy --locked --all-targets -- -D warnings
	cargo fmt --check
	RUSTDOCFLAGS="-D warnings" cargo doc --locked --no-deps
	cd ../../.. && composer test-native-core

audit:
	cargo audit --deny warnings
