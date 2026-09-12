.PHONY: build test

build:
	cargo build --release --locked

test: build
	cargo test --locked
	cargo clippy --locked --all-targets -- -D warnings
	cargo fmt --check
	cd ../../.. && composer test-native-static-generator
	php -d disable_functions=proc_open tests/shared-hosting.php
