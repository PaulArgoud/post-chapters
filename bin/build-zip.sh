#!/usr/bin/env bash
#
# Build the distributable plugin archive: build/post-chapters.zip
#
# The archive contains a single top-level "post-chapters/" folder, as
# expected by the WordPress plugin uploader. Only files known to git are
# packaged, and every path listed in .distignore is left out (development
# tooling, tests, contributor docs, .po sources...).
#
# Usage: bin/build-zip.sh [--wporg]
#
#   --wporg  Build build/post-chapters-wporg.zip for WordPress.org instead: same
#            files, without the "Update URI" header of post-chapters.php (Plugin
#            Check rejects it on WordPress.org-hosted plugins).
#
set -euo pipefail

SLUG="post-chapters"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${ROOT}/build"
ZIP_FILE="${BUILD_DIR}/${SLUG}.zip"

WPORG=0
for arg in "$@"; do
	case "${arg}" in
		--wporg) WPORG=1 ;;
		*) echo "Usage: bin/build-zip.sh [--wporg]" >&2; exit 1 ;;
	esac
done
if [[ "${WPORG}" == 1 ]]; then
	ZIP_FILE="${BUILD_DIR}/${SLUG}-wporg.zip"
fi
DISTIGNORE="${ROOT}/.distignore"

for tool in rsync zip unzip; do
	if ! command -v "${tool}" > /dev/null 2>&1; then
		echo "Error: ${tool} is required." >&2
		exit 1
	fi
done

if [[ ! -f "${DISTIGNORE}" ]]; then
	echo "Error: ${DISTIGNORE} not found." >&2
	exit 1
fi

VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "${ROOT}/${SLUG}.php" | head -n 1 | tr -d '[:space:]')"
if [[ -z "${VERSION}" ]]; then
	echo "Error: could not read the Version header of ${SLUG}.php." >&2
	exit 1
fi

# Stage in a private temporary folder (outside the project, removed on exit).
TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/${SLUG}-build.XXXXXX")"
trap 'rm -rf "${TMP_DIR}"' EXIT
STAGE_DIR="${TMP_DIR}/${SLUG}"
mkdir -p "${STAGE_DIR}" "${BUILD_DIR}"
rm -f "${ZIP_FILE}"

# Step 1: collect the source files. In a git checkout, only the files known to
# git (tracked or staged) are taken, so local leftovers such as a PHPStan cache,
# an .env.local or editor files can never end up in the release, even when
# .distignore does not list them. Files deleted from the working tree but not
# yet from the index are skipped. Outside git (e.g. an extracted source
# archive), the whole folder is taken and .distignore alone does the filtering.
SOURCE_DIR="${TMP_DIR}/source"
mkdir -p "${SOURCE_DIR}"
if git -C "${ROOT}" rev-parse --is-inside-work-tree > /dev/null 2>&1; then
	command -v tar > /dev/null 2>&1 || { echo "Error: tar is required." >&2; exit 1; }
	FILE_LIST="${TMP_DIR}/files.lst"
	: > "${FILE_LIST}"
	while IFS= read -r -d '' file; do
		if [[ -f "${ROOT}/${file}" ]]; then
			printf '%s\0' "${file}" >> "${FILE_LIST}"
		fi
	done < <(git -C "${ROOT}" ls-files -z --cached)
	if [[ ! -s "${FILE_LIST}" ]]; then
		echo "Error: git lists no file in ${ROOT}." >&2
		exit 1
	fi
	(cd "${ROOT}" && tar --null -T "${FILE_LIST}" -cf -) | tar -xf - -C "${SOURCE_DIR}"
else
	echo "Warning: ${ROOT} is not a git checkout; packaging every file not listed in .distignore." >&2
	rsync -a "${ROOT}/" "${SOURCE_DIR}/"
fi

# Step 2: drop everything listed in .distignore (development tooling, tests,
# contributor docs, .po sources, build/ itself...).
rsync -a --exclude-from="${DISTIGNORE}" "${SOURCE_DIR}/" "${STAGE_DIR}/"

# WordPress.org build: drop the "Update URI" header line.
if [[ "${WPORG}" == 1 ]]; then
	MAIN_FILE="${STAGE_DIR}/${SLUG}.php"
	grep -v -E '^[[:space:]]*\*[[:space:]]*Update URI:' "${MAIN_FILE}" > "${MAIN_FILE}.tmp"
	mv "${MAIN_FILE}.tmp" "${MAIN_FILE}"
	if grep -q 'Update URI:' "${MAIN_FILE}"; then
		echo "Error: could not remove the Update URI header." >&2
		exit 1
	fi
fi

# Zip it with a single top-level folder, without extra file attributes.
(
	cd "${TMP_DIR}"
	zip -r -q -X "${SLUG}.zip" "${SLUG}"
)
mv "${TMP_DIR}/${SLUG}.zip" "${ZIP_FILE}"

# Sanity checks: the main file is there, no development file leaked.
LISTING="$(unzip -Z1 "${ZIP_FILE}")"
if ! grep -qx "${SLUG}/${SLUG}.php" <<< "${LISTING}"; then
	echo "Error: ${SLUG}.php is missing from the archive." >&2
	exit 1
fi
if grep -E "^${SLUG}/(vendor|node_modules|tests|bin|build|tmp|\.[^/]+)/|^${SLUG}/(composer\.(json|lock)|package(-lock)?\.json|phpcs\.xml(\.dist)?|phpunit\.xml(\.dist)?|phpstan\.neon(\.dist)?|\.[^/]+)$|\.(po|log)$" <<< "${LISTING}"; then
	echo "Error: the development files above leaked into the archive; check .distignore." >&2
	exit 1
fi

echo "Built ${ZIP_FILE} (version ${VERSION}, $(grep -c . <<< "${LISTING}") entries)."
