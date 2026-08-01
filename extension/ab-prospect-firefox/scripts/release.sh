#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ -z "${AMO_JWT_ISSUER:-}" || -z "${AMO_JWT_SECRET:-}" ]]; then
  echo "Defina AMO_JWT_ISSUER e AMO_JWT_SECRET antes de rodar este script." >&2
  echo "Gere o par em https://addons.mozilla.org/developers/addon/api/key/" >&2
  exit 1
fi

rm -rf artifacts
npx web-ext sign \
  --api-key="$AMO_JWT_ISSUER" \
  --api-secret="$AMO_JWT_SECRET" \
  --channel=unlisted \
  --source-dir=. \
  --artifacts-dir=artifacts \
  --ignore-files=package.json \
  --ignore-files=package-lock.json \
  --ignore-files=README.md \
  --ignore-files="scripts/**" \
  --ignore-files="artifacts/**" \
  --ignore-files=scripts

version=$(node -p "require('./manifest.json').version")
xpi=$(find artifacts -maxdepth 1 -name '*.xpi' | head -n1)

if [[ -z "$xpi" ]]; then
  echo "A assinatura não gerou um arquivo .xpi. Veja o log acima." >&2
  exit 1
fi

dest="../../public/files/ab-prospect-firefox-v${version}.xpi"
cp "$xpi" "$dest"
echo "Extensão assinada publicada em: $dest"
