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

any(.[]; .type == "auditSummary")
and all(.[] | select(.type == "auditAdvisory") | .data;
  (.advisory.severity != "high" and .advisory.severity != "critical")
  or trusted_build_braces
)
