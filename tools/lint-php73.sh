#!/usr/bin/env bash
#
# Lint every PHP file this module loads at runtime with a real PHP 7.3
# interpreter.
#
# Why: composer.json declares "php": ">=7.3" and AGENTS.md §1 sets 7.3 as the
# cross-module floor, but the dev box runs 8.1. Everything this module used to
# ship parsed fine on 8.1 and 263-style green test runs while being unloadable
# on 7.3: 265 typed properties (7.4+), 45 arrow functions (7.4+). FA includes
# hooks.php and the page/ scripts directly, so the first include is a fatal
# parse error, not a graceful degradation.
#
# Resolution order for a 7.3 interpreter:
#   1. $PHP73_BIN
#   2. php7.3 / php73 on PATH
#   3. a container runtime (podman, then docker) using the php:7.3-alpine
#      image, with this directory bind-mounted
#
# A 7.4 interpreter is deliberately NOT accepted: passing on 7.4 says nothing
# about 7.3, which is the version that has to work.
#
# Usage: tools/lint-php73.sh
# Exit:  0 = clean, 1 = incompatible with 7.3, 2 = no 7.3 interpreter found

set -uo pipefail

cd "$(dirname "$0")/.." || exit 2

# Everything FA loads or executes: the autoloaded classes, the unit tests, the
# hooks class, the page scripts and the includes/ helpers. vendor/ is pruned --
# its floor is governed by the package that ships it, and pinning
# config.platform.php in composer.json is what keeps that honest.
TARGETS=(src tests includes pages hooks.php)
IMAGE="${PHP73_IMAGE:-docker.io/library/php:7.3-alpine}"

collect() {
	local t="$1"
	if [ -f "$t" ]; then
		printf '%s\n' "$t"
	elif [ -d "$t" ]; then
		find "$t" -type d -name vendor -prune -o -type f -name '*.php' -print
	fi
}

FILES=()
for t in "${TARGETS[@]}"; do
	while IFS= read -r f; do
		[ -n "$f" ] && FILES+=("$f")
	done < <(collect "$t")
done

if [ "${#FILES[@]}" -eq 0 ]; then
	echo "No PHP files found to lint." >&2
	exit 2
fi

is_php73() {
	"$@" -r 'exit(PHP_MAJOR_VERSION === 7 && PHP_MINOR_VERSION === 3 ? 0 : 1);' >/dev/null 2>&1
}

lint_with_binary() {
	local bin="$1" fail=0 f out
	for f in "${FILES[@]}"; do
		out="$("$bin" -l "$f" 2>&1)"
		case "$out" in
		"No syntax errors"*) ;;
		*)
			echo "$out" | grep -E 'Parse error|Fatal error|Errors parsing'
			fail=1
			;;
		esac
	done
	return $fail
}

# 1/2: a local 7.3 binary.
for c in "${PHP73_BIN:-}" php7.3 php73; do
	[ -n "$c" ] || continue
	command -v "$c" >/dev/null 2>&1 || [ -x "$c" ] || continue
	if is_php73 "$c"; then
		echo "Linting ${#FILES[@]} files with $c ($("$c" -r 'echo PHP_VERSION;'))"
		lint_with_binary "$c"
		exit $?
	fi
done

# 3: a container runtime with the official 7.3 image.
for rt in podman docker; do
	command -v "$rt" >/dev/null 2>&1 || continue
	"$rt" run --rm "$IMAGE" php -r 'exit(PHP_MAJOR_VERSION === 7 && PHP_MINOR_VERSION === 3 ? 0 : 1);' >/dev/null 2>&1 || continue

	echo "No local PHP 7.3 found; linting ${#FILES[@]} files in $IMAGE."
	# -v "$PWD:/r:ro" needs SELinux relabelling on some hosts.
	if ! "$rt" run --rm -v "$PWD:/r:ro,Z" "$IMAGE" true >/dev/null 2>&1; then
		"$rt" run --rm -v "$PWD:/r:ro" "$IMAGE" true >/dev/null 2>&1 || {
			echo "Could not bind-mount $PWD into $IMAGE." >&2
			exit 2
		}
		MOUNT="$PWD:/r:ro"
	else
		MOUNT="$PWD:/r:ro,Z"
	fi

	# The target list is passed as arguments, not interpolated into the script:
	# TARGETS is a host-side array and would be empty inside the container.
	"$rt" run --rm -v "$MOUNT" "$IMAGE" sh -c '
		cd /r || exit 2
		fail=0
		count=0
		for t in "$@"; do
			if [ -f "$t" ]; then
				list=$t
			elif [ -d "$t" ]; then
				list=$(find "$t" -type d -name vendor -prune -o -type f -name "*.php" -print)
			else
				continue
			fi
			for f in $list; do
				count=$((count + 1))
				out=$(php -l "$f" 2>&1)
				case "$out" in
				"No syntax errors"*) ;;
				*) echo "$out" | grep -E "Parse error|Fatal error|Errors parsing"; fail=1 ;;
				esac
			done
		done
		echo "Checked $count files with PHP $(php -r "echo PHP_VERSION;")"
		exit $fail
	' sh "${TARGETS[@]}"
	exit $?
done

cat >&2 <<EOF
No PHP 7.3 interpreter available.

This module targets PHP 7.3, so a 7.4 or 8.x interpreter cannot validate it.
Either install php7.3, point at an existing binary, or make a container
runtime available:

    PHP73_BIN=/path/to/php7.3 tools/lint-php73.sh
    PHP73_IMAGE=docker.io/library/php:7.3-alpine tools/lint-php73.sh
EOF
exit 2
