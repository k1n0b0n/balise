#!/bin/sh
# Generate the GTFS-Realtime PHP classes into generated/ (ADR-0015), from the committed proto.
#   docker compose exec php sh scripts/generate-gtfs-realtime.sh                 # generate
#   docker compose exec php sh scripts/generate-gtfs-realtime.sh --update-proto  # first refresh proto/ from PROTO_COMMIT
#   sh scripts/generate-gtfs-realtime.sh --if-stale                              # skip when inputs are unchanged (setup)
set -eu

PROTO_COMMIT=474750a163088673df718838d4a1bb093391f9af
PROTOC_VERSION=36.2

case "$(uname -m)" in
    x86_64) arch=x86_64; sha256=121f6c7afe1d4d0e3ea6aab9432038599250134cbf4474cb1167d2c7decd4278 ;;
    aarch64 | arm64) arch=aarch_64; sha256=8b8f18bd2b30346efbc698dd5a73dd7c805f3ef8380f6dfc95c768f3f1852f6a ;;
    *) echo "Unsupported architecture: $(uname -m)" >&2; exit 1 ;;
esac

if [ "${1:-}" = "--update-proto" ]; then
    curl -fsSL -o proto/gtfs-realtime.proto "https://raw.githubusercontent.com/google/transit/${PROTO_COMMIT}/gtfs-realtime/proto/gtfs-realtime.proto"
fi

# The stamp is written last, so an interrupted run or a changed script or proto regenerates.
inputs=$(cat "$0" proto/gtfs-realtime.proto | sha256sum | cut -d' ' -f1)

if [ "${1:-}" = "--if-stale" ] && [ "$(cat generated/.inputs 2>/dev/null)" = "$inputs" ]; then
    exit 0
fi

work=$(mktemp -d)
# Staged next to generated/, on the same filesystem, so that publishing it is an atomic rename, not a copy.
out=$(mktemp -d generated.tmp.XXXXXX)
trap 'rm -rf "$work" "$out"' EXIT

curl -fsSL -o "$work/protoc.zip" "https://github.com/protocolbuffers/protobuf/releases/download/v${PROTOC_VERSION}/protoc-${PROTOC_VERSION}-linux-${arch}.zip"
echo "${sha256}  $work/protoc.zip" | sha256sum -c - > /dev/null
unzip -q "$work/protoc.zip" -d "$work/protoc"

# protoc cannot generate PHP from proto2 required fields, so the pristine upstream file is
# converted to proto3: same wire format, explicit presence kept with `optional`.
mkdir "$work/proto"
sed -E \
    -e 's/^syntax = "proto2";/syntax = "proto3";/' \
    -e 's/^([[:space:]]*)required /\1optional /' \
    -e 's/[[:space:]]*\[default = [^]]*\]//' \
    -e '/^[[:space:]]*extensions [0-9]+ to [0-9]+;/d' \
    -e 's/^([[:space:]]*)enum Cause \{/&\n\1  CAUSE_UNSPECIFIED = 0;/' \
    -e 's/^([[:space:]]*)enum Effect \{/&\n\1  EFFECT_UNSPECIFIED = 0;/' \
    -e 's/^([[:space:]]*)enum SeverityLevel \{/&\n\1  SEVERITY_LEVEL_UNSPECIFIED = 0;/' \
    proto/gtfs-realtime.proto > "$work/proto/gtfs-realtime.proto"

chmod 755 "$out"
"$work/protoc/bin/protoc" --proto_path="$work/proto" --php_out="$out" "$work/proto/gtfs-realtime.proto"
echo "$inputs" > "$out/.inputs"
rm -rf generated
mv "$out" generated

echo "Generated $(find generated -name '*.php' | wc -l | tr -d ' ') files from proto/gtfs-realtime.proto (google/transit@${PROTO_COMMIT}) with protoc ${PROTOC_VERSION}."
