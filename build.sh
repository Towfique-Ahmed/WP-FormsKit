#!/usr/bin/env bash
#
# Build installable ZIP packages for the WP FormsKit plugins.
#
# Each ZIP contains the plugin directory at its root (e.g. wp-formskit/…),
# which is exactly what "Plugins → Add New → Upload Plugin" expects in
# WordPress. Output lands in ./dist.
#
# Usage:
#   ./build.sh            # build both plugins
#   ./build.sh free       # build only the free plugin
#   ./build.sh pro        # build only the pro plugin
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="${ROOT_DIR}/dist"

FREE_SLUG="wp-formskit"
PRO_SLUG="wp-formskit-pro"

# Path suffixes that should never ship inside a distributable ZIP. These are
# anchored to the plugin slug at package time (see build_plugin).
EXCLUDE_SUFFIXES=(
	".git/*"
	".github/*"
	"node_modules/*"
	"tests/*"
	".distignore"
)
# Name-based excludes matched anywhere in the tree.
EXCLUDE_GLOBS=(
	"*.DS_Store"
	"*.map"
)

read_version() {
	# Extract "Version: x.y.z" from a plugin's main header file.
	local main_file="$1"
	grep -i -m1 "^[[:space:]]*\*[[:space:]]*Version:" "${main_file}" \
		| sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r' | xargs
}

build_plugin() {
	local slug="$1"
	local src="${ROOT_DIR}/${slug}"

	if [[ ! -d "${src}" ]]; then
		echo "!! Plugin directory not found: ${slug}" >&2
		return 1
	fi

	local main_file="${src}/${slug}.php"
	local version="unknown"
	if [[ -f "${main_file}" ]]; then
		version="$(read_version "${main_file}")"
	fi

	local zip_name="${slug}-${version}.zip"
	local zip_path="${DIST_DIR}/${zip_name}"

	rm -f "${zip_path}"

	# Assemble the -x exclude args for zip (archive paths are prefixed by slug).
	local exclude_args=()
	local suffix
	for suffix in "${EXCLUDE_SUFFIXES[@]}"; do
		exclude_args+=("-x" "${slug}/${suffix}")
	done
	local glob
	for glob in "${EXCLUDE_GLOBS[@]}"; do
		exclude_args+=("-x" "${slug}/${glob}")
	done

	echo ">> Packaging ${slug} v${version}"
	( cd "${ROOT_DIR}" && zip -rq "${zip_path}" "${slug}" "${exclude_args[@]}" )

	# Also drop a version-less copy for convenience / stable download URLs.
	cp -f "${zip_path}" "${DIST_DIR}/${slug}.zip"

	echo "   -> dist/${zip_name}"
	echo "   -> dist/${slug}.zip"
}

main() {
	command -v zip >/dev/null 2>&1 || { echo "Error: 'zip' is required but not installed." >&2; exit 1; }

	mkdir -p "${DIST_DIR}"

	local target="${1:-all}"
	case "${target}" in
		free) build_plugin "${FREE_SLUG}" ;;
		pro)  build_plugin "${PRO_SLUG}" ;;
		all)
			build_plugin "${FREE_SLUG}"
			build_plugin "${PRO_SLUG}"
			;;
		*)
			echo "Unknown target: ${target} (use: free | pro | all)" >&2
			exit 1
			;;
	esac

	echo ""
	echo "Done. Packages in ./dist:"
	ls -1sh "${DIST_DIR}"/*.zip 2>/dev/null || true
}

main "$@"
