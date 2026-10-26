#!/usr/bin/env sh
set -eu
API_BASE="$(printenv API_BASE 2>/dev/null || printf '%s' 'https://mirrorhub.ir/beta/api/v1')"
printf '%s\n' '== First page of mirrors ==' 
curl --fail --silent --show-error "$API_BASE/mirrors?per_page=5"
printf '\n%s\n' '== Software categories ==' 
curl --fail --silent --show-error "$API_BASE/categories?type=software"
printf '\n%s\n' '== Public status summary ==' 
curl --fail --silent --show-error "$API_BASE/status"
printf '\n%s\n' '== OpenAPI definition ==' 
curl --fail --silent --show-error "$API_BASE/openapi.json"
