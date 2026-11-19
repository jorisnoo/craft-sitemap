#!/usr/bin/env bash
# The CI service is a disposable, empty MySQL database. Only use this script with one.
set -euo pipefail
: "${SITEMAP_TEST_DSN:?Set SITEMAP_TEST_DSN to a disposable MySQL database}"
export CRAFT_DB_DSN="$SITEMAP_TEST_DSN" CRAFT_DB_USER="${SITEMAP_TEST_USER:-root}" CRAFT_DB_PASSWORD="${SITEMAP_TEST_PASSWORD:-}"
root=$(mktemp -d)
trap 'rm -rf "$root"' EXIT
trap 'echo "Integration failed at line $LINENO" >&2; grep -h -E "Exception|Error:" "$root"/storage/logs/*.log 2>/dev/null | tail -6 >&2 || true' ERR
export CRAFT_BASE_PATH="$root" CRAFT_APP_ID=sitemap-integration CRAFT_SECURITY_KEY=integration-only CRAFT_ENVIRONMENT=test
export PRIMARY_SITE_URL='https://sitemap.example/shop/'
export TEST_DYNAMIC_BASE='@web/fr/'
mkdir -p "$root"/{config,templates,web}
printf '%s\n' '<?php return ["modules" => ["craft-sitemap" => Noo\CraftSitemap\Module::class], "bootstrap" => ["craft-sitemap"]];' > "$root/config/app.php"
# Simulate a web SAPI in a subprocess using Craft's real web request and router.
printf '%s\n' '<?php return ["components" => ["request" => static fn () => Craft::createObject(["isConsoleRequest" => false] + craft\helpers\App::webRequestConfig())]];' > "$root/config/app.web.php"
printf '%s\n' '<?php return ["omitScriptNameInUrls" => true, "disallowRobots" => false];' > "$root/config/general.php"
printf 'Page template\n' > "$root/templates/index.twig"
touch "$root/web/index.php"
craft() { php tests/integration/craft.php "$@"; }
craft install/craft --interactive=0 --username=admin --password=integration-password --email=admin@example.test --siteName=Sitemap --siteUrl='$PRIMARY_SITE_URL' --language=en-US > "$root/install.log" || { tail -20 "$root/install.log"; exit 1; }
craft seed
xml=$(craft request sitemap.xml)
[[ "$xml" == *'<loc>https://sitemap.example/shop/visible</loc>'* && "$xml" != *'/hidden</loc>'* ]] || { echo "Unexpected sitemap: $xml" >&2; exit 1; }
[[ $(craft request sitemap-DEFAULT.xml) == "$xml" ]]
craft rename
xml=$(craft request sitemap.xml)
[[ "$xml" == *'/renamed</loc>'* && "$xml" != *'/visible</loc>'* ]]
[[ $(craft request robots.txt) == *'Sitemap: https://sitemap.example/shop/sitemap.xml'* ]]
printf 'User-agent: *\nDisallow: /private/\n' > "$root/templates/robots.txt.twig"
[[ $(craft request robots.txt) == *'Disallow: /private/'* ]]
printf 'User-agent: *\nDisallow: /routed/\n' > "$root/templates/policy.twig"
printf "%s\n" "<?php return ['robots.txt' => ['template' => 'policy']];" > "$root/config/routes.php"
[[ $(craft request robots.txt) == *'Disallow: /routed/'* ]]
echo 'Craft integration: index, page, template resolution, mount and robots precedence passed.'
