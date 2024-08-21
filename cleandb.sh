#!/bin/sh
if [ -z $1 ]; then
  echo 'You must supply an SQL filename'
  exit 1
fi
# Strip MySQL security 'feature' (https://ddev.com/blog/mariadb-dump-breaking-change/)
sed -i '/^\/\*!999999\\- enable the sandbox mode \*\//d' $1
# Find-replace encoding utf8mb4_unicode_ai_ci
sed -i 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' $1
