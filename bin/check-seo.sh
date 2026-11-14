#!/usr/bin/env bash
#
# Check the chapters against the SEO plugins, on a running wp-env site (http://localhost:8888).
#
# For each plugin (Yoast SEO, Rank Math, SEOPress, All in One SEO, The SEO Framework), installs and
# activates it, then checks the head of the second chapter of a demo post with bin/check-seo.php.
# The demo post and the plugins are created in the wp-env site (the PHPUnit suite resets it).
# Needs network access (wordpress.org). Run it before a release: the plugins change their output.
#
# Usage: bin/check-seo.sh
#
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASE="http://localhost:8888"
SLUG="seo-check-guide"
WPENV=(npx -y @wordpress/env@12)

cd "${ROOT}"

run() {
	"${WPENV[@]}" run cli bash -c "$1" 2>&1 | grep -av '^ℹ\|^✔'
}

if ! curl -fsS -o /dev/null "${BASE}/"; then
	echo "The wp-env site is not running: npx @wordpress/env@12 start" >&2
	exit 1
fi

echo "Preparing the demo post..."
run "wp rewrite structure '/%postname%/' --hard >/dev/null; wp theme activate twentytwentyfive >/dev/null 2>&1; wp plugin activate paginova >/dev/null 2>&1; \
for p in wordpress-seo seo-by-rank-math wp-seopress all-in-one-seo-pack autodescription; do wp plugin deactivate \$p >/dev/null 2>&1; done; \
wp option update rank_math_registration_skip 1 >/dev/null; wp option update rank_math_is_configured 1 >/dev/null; \
wp post delete \$(wp post list --name=${SLUG} --post_status=any --format=ids) --force >/dev/null 2>&1; \
wp post create --post_status=publish --post_title='A beginner guide to sourdough bread' --post_name=${SLUG} \
--post_excerpt='How to bake sourdough bread at home.' --post_content=\"Intro.\n\n<!-- wp:multipage/subpage {\\\"title\\\":\\\"Making the starter\\\"} -->\n[nextpage title=\\\"Making the starter\\\"]\n<!-- /wp:multipage/subpage -->\n\nFlour and water.\n\n<!-- wp:multipage/subpage {\\\"title\\\":\\\"Mixing the dough\\\"} -->\n[nextpage title=\\\"Mixing the dough\\\"]\n<!-- /wp:multipage/subpage -->\n\nSalt.\" --porcelain >/dev/null"

failures=0
for plugin in "wordpress-seo:Yoast SEO" "seo-by-rank-math:Rank Math" "wp-seopress:SEOPress" "all-in-one-seo-pack:All in One SEO" "autodescription:The SEO Framework"; do
	slug="${plugin%%:*}"
	label="${plugin#*:}"
	version="$(run "wp plugin install ${slug} --activate >/dev/null 2>&1 || wp plugin activate ${slug} >/dev/null 2>&1; wp plugin get ${slug} --field=version" | tail -n 1)"
	if ! php "${ROOT}/bin/check-seo.php" "${BASE}" "/${SLUG}/" "${label} ${version}"; then
		failures=$((failures + 1))
	fi
	run "wp plugin deactivate ${slug} >/dev/null 2>&1"
done

if [[ "${failures}" -gt 0 ]]; then
	echo "${failures} SEO plugin(s) failed."
	exit 1
fi
echo "All SEO plugins OK."
