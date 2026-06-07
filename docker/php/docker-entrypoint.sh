#!/usr/bin/env bash
set -euo pipefail

mkdir -p \
    cache \
    datafiles/editor \
    datafiles/tmp/uploads \
    files/captcha \
    files/images \
    files/public \
    files/thumbnails \
    imports \
    logs

chown -R www-data:www-data cache datafiles files imports logs 2>/dev/null || true
chmod -R a+rwX cache datafiles files imports logs 2>/dev/null || true

exec "$@"
