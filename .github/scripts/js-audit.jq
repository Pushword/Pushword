# GHSA-vfj7-8cjw-p6xm has no patched release. These two Vite plugins only use
# repository-controlled build globs, never patterns received from CMS users.
# Remove this exception when upstream ships a fix. All other paths/advisories
# and any escalation to critical severity remain blocking.
def trusted_build_braces:
  .advisory.url == "https://github.com/advisories/GHSA-vfj7-8cjw-p6xm"
  and .advisory.module_name == "braces"
  and .advisory.severity == "high"
  and (.resolution.path | IN(
    "vite-plugin-static-copy>chokidar>braces",
    "@pushword/js-helper>vite-plugin-static-copy>chokidar>braces",
    "vite-plugin-symfony>fast-glob>micromatch>braces",
    "@pushword/js-helper>vite-plugin-symfony>fast-glob>micromatch>braces"
  ));

# GHSA-fc5q-39xc-vjpf has no patched release (checked 2026-11-18).
# Exploitation requires an attacker-controlled glob pattern. These measured paths
# only consume repository-controlled build/test/lint globs (including Sass file
# watching), never patterns supplied by CMS users. Keep the exact path allowlist;
# remove it when a patched picomatch is available. Other advisories, new paths and
# critical severity must still block the audit.
def trusted_build_picomatch:
  .advisory.url == "https://github.com/advisories/GHSA-fc5q-39xc-vjpf"
  and .advisory.module_name == "picomatch"
  and .advisory.severity == "high"
  and (.resolution.path | IN(
    "@pushword/js-helper>vite-plugin-compression2>@rollup/pluginutils>picomatch",
    "@pushword/js-helper>vite-plugin-static-copy>chokidar>anymatch>picomatch",
    "@pushword/js-helper>vite-plugin-static-copy>chokidar>readdirp>picomatch",
    "@pushword/js-helper>vite-plugin-symfony>fast-glob>micromatch>picomatch",
    "@pushword/js-helper>vite>picomatch",
    "@pushword/js-helper>vite>tinyglobby>picomatch",
    "@typescript-eslint/eslint-plugin>@typescript-eslint/type-utils>@typescript-eslint/typescript-estree>tinyglobby>picomatch",
    "sass-embedded>sass-embedded-all-unknown>sass>@parcel/watcher>picomatch",
    "typescript-eslint>@typescript-eslint/eslint-plugin>@typescript-eslint/type-utils>@typescript-eslint/typescript-estree>tinyglobby>picomatch",
    "typescript-eslint>@typescript-eslint/eslint-plugin>@typescript-eslint/type-utils>@typescript-eslint/utils>@typescript-eslint/typescript-estree>tinyglobby>picomatch",
    "vite-plugin-static-copy>chokidar>anymatch>picomatch",
    "vite-plugin-static-copy>chokidar>readdirp>picomatch",
    "vite-plugin-symfony>fast-glob>micromatch>picomatch",
    "vite>picomatch",
    "vite>tinyglobby>picomatch",
    "vitest>picomatch",
    "vitest>tinyglobby>picomatch"
  ));

any(.[]; .type == "auditSummary")
and all(.[] | select(.type == "auditAdvisory") | .data;
  (.advisory.severity != "high" and .advisory.severity != "critical")
  or trusted_build_braces
  or trusted_build_picomatch
)
