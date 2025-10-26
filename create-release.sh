#!/bin/bash
# シンプルなリリース作成スクリプト（.gitattributes使用）

set -e

VERSION=${1:-$(date +%Y%m%d-%H%M%S)}
RELEASE_NAME="photo-site-${VERSION}"
OUTPUT_DIR="releases"

echo "リリース作成中: ${RELEASE_NAME}"

mkdir -p "${OUTPUT_DIR}"

# .gitattributesの設定に従って自動的に除外
git archive --format=tar.gz \
    --prefix="${RELEASE_NAME}/" \
    --output="${OUTPUT_DIR}/${RELEASE_NAME}.tar.gz" \
    HEAD

echo "✓ 完了: ${OUTPUT_DIR}/${RELEASE_NAME}.tar.gz"
echo "サイズ: $(du -h ${OUTPUT_DIR}/${RELEASE_NAME}.tar.gz | cut -f1)"
