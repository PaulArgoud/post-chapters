#!/usr/bin/env bash
#
# Check that the translation files in languages/ are complete and up to date.
#
# Fails (exit 1) when:
#   - "wp i18n make-pot" warns about a string (e.g. placeholders without a
#     "translators:" comment, mismatched singular/plural placeholders);
#   - a msgid/msgctxt found in the code (PHP, JS, block.json) is missing from
#     languages/paginova.pot;
#   - a .po file has untranslated, fuzzy or obsolete (#~) entries, misses a
#     string of the code, or fails "msgfmt --check";
#   - a compiled file is missing or stale: .mo, .l10n.php (wp i18n make-php)
#     or JS .json (wp i18n make-json, named after md5 of the script path).
#
# Requirements: WP-CLI (with the bundled i18n command), GNU gettext
# (msgfmt, msgcmp, msgunfmt), PHP CLI.
#
# Usage: bin/check-i18n.sh
#   WP_CLI_BIN  WP-CLI command (default: "wp"). May contain arguments, e.g.
#               WP_CLI_BIN="php wp-cli.phar" or a wp-env wrapper. It is run
#               from the plugin root with paths relative to it, and its working
#               directory must be the plugin root (true for wp-env with
#               --env-cwd=wp-content/plugins/post-chapters).
#   PHP_BIN     PHP CLI used for the semantic comparisons (default: "php").
#
# Refresh workflow (after changing strings), from the plugin root:
#   wp i18n make-pot . languages/paginova.pot --domain=paginova \
#     --exclude=vendor,tests,bin,node_modules,build,.claude,.github --package-name="Paginova" \
#     --headers='{"Report-Msgid-Bugs-To":"https://github.com/PaulArgoud/paginova/issues"}'
#   for po in languages/*.po; do
#     msgmerge --quiet --no-wrap --backup=off --update "$po" languages/paginova.pot
#     msgattrib --no-obsolete --no-wrap -o "$po" "$po"
#   done
#   (translate the new / fuzzy entries, remove the fuzzy flags)
#   for po in languages/*.po; do msgfmt --check --statistics -o "${po%.po}.mo" "$po"; done
#   wp i18n make-json languages --no-purge
#   wp i18n make-php languages
#   bin/check-i18n.sh
#
set -euo pipefail

DOMAIN="paginova"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LANG_DIR="languages"
POT="${LANG_DIR}/${DOMAIN}.pot"
# Inside build/ (git-ignored, excluded from make-pot and from the zip) so that
# a containerized WP-CLI that only sees the plugin folder can write there too.
TMP_REL="build/i18n-check"
EXCLUDE="vendor,tests,bin,node_modules,build,.claude,.github"

read -r -a WP <<< "${WP_CLI_BIN:-wp}"
read -r -a PHP <<< "${PHP_BIN:-php}"

export LC_ALL=C

cd "${ROOT}"

for tool in msgfmt msgcmp msgunfmt diff; do
	if ! command -v "${tool}" > /dev/null 2>&1; then
		echo "Error: ${tool} is required (GNU gettext / diffutils)." >&2
		exit 2
	fi
done
if ! command -v "${WP[0]}" > /dev/null 2>&1; then
	echo "Error: WP-CLI not found (${WP[0]}); set WP_CLI_BIN." >&2
	exit 2
fi
if ! command -v "${PHP[0]}" > /dev/null 2>&1; then
	echo "Error: PHP CLI not found (${PHP[0]}); set PHP_BIN." >&2
	exit 2
fi
if [[ ! -f "${POT}" ]]; then
	echo "Error: ${POT} not found." >&2
	exit 1
fi

rm -rf "${TMP_REL}" 2> /dev/null || true
mkdir -p "${TMP_REL}/po" "${TMP_REL}/json" "${TMP_REL}/php" "${TMP_REL}/mo"
# Best effort: some network file systems keep ".smbdelete*" placeholders for a
# while; a leftover in the git-ignored build/ folder must not fail the check.
trap 'rm -rf "${ROOT:?}/${TMP_REL}" 2> /dev/null || true' EXIT

ERRORS=0
fail() {
	echo "FAIL: $*" >&2
	ERRORS=$((ERRORS + 1))
}

# WP-CLI as root (containers, some CI images) needs --allow-root.
WP_EXTRA=()
if [[ "$(id -u)" == "0" ]]; then
	WP_EXTRA+=(--allow-root)
fi

# 1. POT generated from the code.
CODE_POT="${TMP_REL}/code.pot"
echo "== Extracting strings from the code (wp i18n make-pot)"
MAKE_POT_LOG="$("${WP[@]}" i18n make-pot . "${CODE_POT}" \
	--domain="${DOMAIN}" \
	--exclude="${EXCLUDE}" \
	--package-name="Paginova" \
	--headers='{"Report-Msgid-Bugs-To":"https://github.com/PaulArgoud/paginova/issues"}' \
	"${WP_EXTRA[@]+"${WP_EXTRA[@]}"}" 2>&1)" || {
	echo "${MAKE_POT_LOG}" >&2
	echo "Error: wp i18n make-pot failed." >&2
	exit 1
}
if grep -qi "warning" <<< "${MAKE_POT_LOG}"; then
	grep -i "warning" <<< "${MAKE_POT_LOG}" >&2
	fail "wp i18n make-pot reported warnings (see above)."
fi
if [[ ! -s "${CODE_POT}" ]]; then
	echo "Error: ${CODE_POT} was not generated." >&2
	exit 1
fi

# 2. Every msgid/msgctxt of the code is in the committed POT.
echo "== ${POT} contains every string of the code"
if ! msgcmp --use-untranslated "${POT}" "${CODE_POT}" 2> "${TMP_REL}/msgcmp.log"; then
	grep -v "warning: this message is not used" "${TMP_REL}/msgcmp.log" >&2 || true
	fail "${POT} is missing strings of the code: regenerate it with wp i18n make-pot."
fi
if grep -q "warning: this message is not used" "${TMP_REL}/msgcmp.log"; then
	echo "Note: ${POT} contains strings that are no longer in the code (regenerate it to drop them)."
fi

# 3. Every .po file: complete, valid, and its compiled files are current.
shopt -s nullglob
PO_FILES=("${LANG_DIR}/${DOMAIN}"-*.po)
if [[ ${#PO_FILES[@]} -eq 0 ]]; then
	fail "no ${LANG_DIR}/${DOMAIN}-*.po file found."
fi

for po in "${PO_FILES[@]}"; do
	base="$(basename "${po}" .po)"
	echo "== ${po}"
	cp "${po}" "${TMP_REL}/po/"

	if ! stats="$(msgfmt --check --statistics -o "${TMP_REL}/mo/${base}.mo" "${po}" 2>&1)"; then
		echo "${stats}" >&2
		fail "${po}: msgfmt --check failed."
		continue
	fi
	echo "   ${stats}"
	if grep -Eq "fuzzy|untranslated" <<< "${stats}"; then
		fail "${po}: has fuzzy or untranslated entries."
	fi
	if grep -q '^#~' "${po}"; then
		fail "${po}: has obsolete (#~) entries; run msgattrib --no-obsolete."
	fi
	# Translated (not fuzzy) for every string of the code.
	if ! msgcmp "${po}" "${CODE_POT}" 2> "${TMP_REL}/msgcmp-${base}.log"; then
		grep -v "warning: this message is not used" "${TMP_REL}/msgcmp-${base}.log" >&2 || true
		fail "${po}: does not translate every string of the code; run msgmerge and translate."
	fi

	# .mo up to date (compared through msgunfmt, independent of the gettext version).
	mo="${LANG_DIR}/${base}.mo"
	if [[ ! -f "${mo}" ]]; then
		fail "${mo} is missing."
	elif ! diff -q <(msgunfmt --no-wrap "${mo}") <(msgunfmt --no-wrap "${TMP_REL}/mo/${base}.mo") > /dev/null; then
		fail "${mo} is out of date: msgfmt -o ${mo} ${po}"
	fi
done

# 4. .l10n.php and JS .json files regenerated from the .po files must match
#    the committed ones (compared as data, so WP-CLI versions do not matter).
if [[ ${#PO_FILES[@]} -gt 0 ]]; then
	echo "== .l10n.php and .json files"
	"${WP[@]}" i18n make-php "${TMP_REL}/po" "${TMP_REL}/php" "${WP_EXTRA[@]+"${WP_EXTRA[@]}"}" > /dev/null
	"${WP[@]}" i18n make-json "${TMP_REL}/po" "${TMP_REL}/json" --no-purge "${WP_EXTRA[@]+"${WP_EXTRA[@]}"}" > /dev/null

	# shellcheck disable=SC2016
	COMPARE='
		$kind = $argv[1];
		$read = function ( $file ) use ( $kind ) {
			$data = "php" === $kind ? include $file : json_decode( (string) file_get_contents( $file ), true );
			if ( ! is_array( $data ) ) {
				return null;
			}
			// Volatile metadata only; every translation and plural form is compared.
			unset( $data["generator"], $data["x-generator"], $data["pot-creation-date"] );
			return $data;
		};
		$sort = function ( &$value ) use ( &$sort ) {
			if ( is_array( $value ) ) {
				ksort( $value, SORT_STRING );
				foreach ( $value as &$item ) {
					$sort( $item );
				}
			}
		};
		$x = $read( $argv[2] );
		$y = $read( $argv[3] );
		$sort( $x );
		$sort( $y );
		exit( null !== $x && $x === $y ? 0 : 1 );
	'

	for generated in "${TMP_REL}"/php/*.l10n.php "${TMP_REL}"/json/*.json; do
		name="$(basename "${generated}")"
		kind="json"
		[[ "${name}" == *.l10n.php ]] && kind="php"
		committed="${LANG_DIR}/${name}"
		if [[ ! -f "${committed}" ]]; then
			fail "${committed} is missing: run wp i18n make-${kind} ${LANG_DIR}$([[ "${kind}" == "json" ]] && echo " --no-purge")"
		elif ! "${PHP[@]}" -r "${COMPARE}" "${kind}" "${committed}" "${generated}"; then
			fail "${committed} is out of date: run wp i18n make-${kind} ${LANG_DIR}$([[ "${kind}" == "json" ]] && echo " --no-purge")"
		fi
	done

	# Committed JSON/PHP files that no .po produces any more (renamed script,
	# removed locale...).
	for committed in "${LANG_DIR}/${DOMAIN}"-*.json "${LANG_DIR}/${DOMAIN}"-*.l10n.php; do
		name="$(basename "${committed}")"
		if [[ ! -f "${TMP_REL}/json/${name}" && ! -f "${TMP_REL}/php/${name}" ]]; then
			fail "${committed} is stale (not generated from any .po file); delete it."
		fi
	done
fi

if [[ ${ERRORS} -gt 0 ]]; then
	echo "i18n check: ${ERRORS} problem(s) found." >&2
	exit 1
fi

echo "i18n check: OK."
