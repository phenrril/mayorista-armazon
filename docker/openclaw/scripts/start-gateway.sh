#!/bin/sh
set -eu

PLUGIN_SRC="/home/node/.openclaw/workspace/.openclaw/mayorista-api"
PLUGIN_DST="/app/local-plugins/mayorista-api-v2"

if [ -d "$PLUGIN_SRC" ]; then
  rm -rf "$PLUGIN_DST"
  mkdir -p "$PLUGIN_DST"
  cp -R "$PLUGIN_SRC"/. "$PLUGIN_DST"/
  chmod -R go-w "$PLUGIN_DST"
fi

exec node dist/index.js gateway --bind "${OPENCLAW_GATEWAY_BIND:-lan}" --port 18789
