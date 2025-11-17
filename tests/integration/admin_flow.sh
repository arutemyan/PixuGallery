#!/usr/bin/env bash
# Simple integration script: login -> access admin index -> call a protected API -> logout
# Usage:
# ADMIN_USER=admin ADMIN_PASS=secret BASE_URL=http://localhost:8000 ./tests/integration/admin_flow.sh

set -eu
BASE_URL=${BASE_URL:-http://localhost:8000}
USER=${ADMIN_USER:-}
PASS=${ADMIN_PASS:-}
COOKIEJAR=$(mktemp)

if [ -z "$USER" ] || [ -z "$PASS" ]; then
  echo "Please set ADMIN_USER and ADMIN_PASS environment variables"
  exit 2
fi

echo "Using base URL: $BASE_URL"

# 1) GET login page to get initial cookies and CSRF token
echo "Fetching login page..."
ADMIN_PATH=${ADMIN_PATH:-admin}
LOGIN_HTML=$(curl -s -c "$COOKIEJAR" "$BASE_URL/$ADMIN_PATH/login.php")
CSRF_TOKEN=$(echo "$LOGIN_HTML" | grep -oP 'name="csrf_token" value="\K[0-9a-f]+' | head -n1)

if [ -z "$CSRF_TOKEN" ]; then
  echo "Failed to extract CSRF token from login page. Aborting." >&2
  rm -f "$COOKIEJAR"
  exit 3
fi

echo "CSRF token: $CSRF_TOKEN"

# 2) POST login
echo "Posting login..."
LOGIN_RESP=$(curl -s -b "$COOKIEJAR" -c "$COOKIEJAR" -X POST "$BASE_URL/$ADMIN_PATH/login.php" \
  -d "username=$USER&password=$PASS&csrf_token=$CSRF_TOKEN" -D -)

echo "Login response snippet:"
echo "$LOGIN_RESP" | head -n20

# 3) Access admin index (should be accessible)
echo "Accessing admin index..."
curl -s -b "$COOKIEJAR" "$BASE_URL/admin/index.php" | head -n20

# 4) Call a protected API (paint list) as a representative GET
echo "Calling paint list API (expect 200 or 403 if not permitted)..."
curl -s -b "$COOKIEJAR" "$BASE_URL/admin/paint/api/list.php?limit=1"

# 5) Logout
# Get CSRF token from admin index (if present)
ADMIN_HTML=$(curl -s -b "$COOKIEJAR" "$BASE_URL/admin/index.php")
CSRF_TOKEN_ADMIN=$(echo "$ADMIN_HTML" | grep -oP 'name="csrf_token" value="\K[0-9a-f]+' | head -n1)
if [ -z "$CSRF_TOKEN_ADMIN" ]; then
  # try header-populated value or JS variable
  CSRF_TOKEN_ADMIN=$(echo "$ADMIN_HTML" | grep -oP "window.CSRF_TOKEN = '\K[0-9a-f]+" || true)
fi

if [ -z "$CSRF_TOKEN_ADMIN" ]; then
  echo "No CSRF token found on admin index; attempting logout without token (may fail)"
  curl -s -b "$COOKIEJAR" -X POST "$BASE_URL/admin/logout.php"
else
  echo "Logging out with CSRF token: $CSRF_TOKEN_ADMIN"
  curl -s -b "$COOKIEJAR" -X POST "$BASE_URL/admin/logout.php" -d "csrf_token=$CSRF_TOKEN_ADMIN"
fi

rm -f "$COOKIEJAR"

echo "Done."
