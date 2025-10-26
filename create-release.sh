#!/bin/bash
# リリースパッケージ作成スクリプト

set -e

# バージョン番号（引数で指定、デフォルトは日付ベース）
VERSION=${1:-$(date +%Y%m%d-%H%M%S)}
RELEASE_NAME="photo-site-${VERSION}"
OUTPUT_DIR="releases"
ARCHIVE_FILE="${OUTPUT_DIR}/${RELEASE_NAME}.tar.gz"

echo "========================================="
echo "Photo Site リリースパッケージ作成"
echo "========================================="
echo "バージョン: ${VERSION}"
echo ""

# 出力ディレクトリを作成
mkdir -p "${OUTPUT_DIR}"

# 除外ファイルリストを作成
cat > .git-archive-exclude << 'EOF'
# 開発/テスト用ファイルを除外
tests/
.github/
.phpunit.cache/
phpunit.xml
phpunit.xml.dist
init.phpe
create-release.sh
.git-archive-exclude

# CI/CD
.gitlab-ci.yml
.travis.yml

# ドキュメント（必要に応じてコメントアウト）
# README.md
# CLAUDE.md

# Git関連
.gitignore
.gitattributes
EOF

echo "Git archiveでファイルをエクスポート中..."
git archive --format=tar.gz \
    --prefix="${RELEASE_NAME}/" \
    --output="${ARCHIVE_FILE}" \
    HEAD \
    $(git ls-files | grep -v -f .git-archive-exclude)

echo ""
echo "✓ リリースパッケージを作成しました:"
echo "  ${ARCHIVE_FILE}"
echo ""
echo "パッケージ内容:"
tar -tzf "${ARCHIVE_FILE}" | head -20
echo "  ..."
echo ""
echo "ファイル数: $(tar -tzf ${ARCHIVE_FILE} | wc -l)"
echo "サイズ: $(du -h ${ARCHIVE_FILE} | cut -f1)"
echo ""
echo "展開方法:"
echo "  tar -xzf ${ARCHIVE_FILE}"
