#!/usr/bin/env bash
# Convert a Direktorium PDF to Markdown using marker-pdf (CPU, no OCR).
# Usage: ./marker-pdf.sh /path/to/direktórium.pdf [output_dir]
#
# Models are cached in a Docker named volume (marker-models) so they are
# only downloaded once.

set -euo pipefail

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <pdf_file> [output_dir]" >&2
    exit 1
fi

PDF_FILE=$(realpath "$1")
PDF_DIR=$(dirname "$PDF_FILE")
OUTPUT_DIR=${2:-"$PDF_DIR/output"}
mkdir -p "$OUTPUT_DIR"

docker run --rm \
    -v "$PDF_DIR":/work/input:ro \
    -v "$OUTPUT_DIR":/work/output \
    -v marker-models:/root/.cache \
    marker-cli:latest \
    "/work/input/$(basename "$PDF_FILE")" \
    --output_dir /work/output \
    --output_format markdown \
    --disable_image_extraction \
    --disable_multiprocessing \
    --disable_ocr \
    --paginate_output
