#!/bin/bash
# Build minimp3 shared library for all platforms.
# Requires: zig (https://ziglang.org) for cross-compilation,
#           or falls back to native cc for current platform only.
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# Cross-compile all targets with zig
if command -v zig &>/dev/null; then
    echo "Using zig for cross-compilation..."

    mkdir -p darwin-arm64 darwin-x86_64 linux-x86_64 windows-x86_64

    zig cc -shared -O2 -o darwin-arm64/libminimp3.dylib   minimp3_wrapper.c -target aarch64-macos
    echo "  darwin-arm64/libminimp3.dylib"

    zig cc -shared -O2 -o darwin-x86_64/libminimp3.dylib  minimp3_wrapper.c -target x86_64-macos
    echo "  darwin-x86_64/libminimp3.dylib"

    zig cc -shared -O2 -o linux-x86_64/libminimp3.so      minimp3_wrapper.c -target x86_64-linux-gnu -lc
    echo "  linux-x86_64/libminimp3.so"

    zig cc -shared -O2 -o windows-x86_64/minimp3.dll      minimp3_wrapper.c -target x86_64-windows-gnu -lc
    echo "  windows-x86_64/minimp3.dll"

    echo "All platforms built successfully."
    exit 0
fi

# Fallback: native compiler for current platform only
echo "zig not found, building for current platform only..."

OS="$(uname -s)"
ARCH="$(uname -m)"

case "$OS" in
    Darwin)
        DIR="darwin-${ARCH}"
        mkdir -p "$DIR"
        cc -shared -O2 -fPIC -o "$DIR/libminimp3.dylib" minimp3_wrapper.c
        echo "Built $DIR/libminimp3.dylib"
        ;;
    Linux)
        DIR="linux-${ARCH}"
        mkdir -p "$DIR"
        cc -shared -O2 -fPIC -o "$DIR/libminimp3.so" minimp3_wrapper.c -lm
        echo "Built $DIR/libminimp3.so"
        ;;
    MINGW*|MSYS*|CYGWIN*)
        DIR="windows-${ARCH}"
        mkdir -p "$DIR"
        cc -shared -O2 -o "$DIR/minimp3.dll" minimp3_wrapper.c
        echo "Built $DIR/minimp3.dll"
        ;;
    *)
        echo "Unsupported OS: $OS" >&2
        exit 1
        ;;
esac
