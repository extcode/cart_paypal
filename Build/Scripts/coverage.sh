#!/usr/bin/env bash
#
# Runs all phpunit tests and measures the code coverage of Classes/.
#
# Usage:
#   Build/Scripts/coverage.sh [additional phpunit arguments]
#
# Every testsuite defined in Build/phpunit.xml.dist runs in its own phpunit
# process, like in phpunit.sh, the functional bootstrap leaks global state
# into the unit tests. The coverage of all suites is merged with phpcov, the
# summary is printed and the html report is written to
# .Build/coverage/index.html.
#
# Requirements:
#   - php with xdebug or pcov
#
# Environment variables used as overrides:
#   typo3DatabaseDriver  database driver to test against, defaults to "pdo_sqlite"

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

requireBinary phpunit
requireBinary phpcov

if ! runPhp -r 'exit(extension_loaded("xdebug") || extension_loaded("pcov") ? 0 : 1);'; then
    echo "Missing coverage driver, please enable xdebug or pcov." >&2
    exit 1
fi

export typo3DatabaseDriver="${typo3DatabaseDriver:-pdo_sqlite}"
export XDEBUG_MODE=coverage

COVERAGE_PATH="${PROJECT_ROOT}/.Build/coverage"
COVERAGE_DATA_PATH="${COVERAGE_PATH}/data"
rm -rf "${COVERAGE_PATH}"
mkdir -p "${COVERAGE_DATA_PATH}"

mapfile -t SUITES < <(runPhp "${BIN_DIR}/phpunit" -c Build/phpunit.xml.dist --list-suites \
    | sed -n 's/^ - \(.*\) ([0-9]\{1,\} tests\{0,1\})$/\1/p')

if [ ${#SUITES[@]} -eq 0 ]; then
    echo "No testsuites found in Build/phpunit.xml.dist." >&2
    exit 1
fi

STATUS=0
for suite in "${SUITES[@]}"; do
    echo "Running testsuite \"${suite}\" ..."
    runPhp "${BIN_DIR}/phpunit" \
        -c Build/phpunit.xml.dist \
        --testsuite "${suite}" \
        --coverage-php "${COVERAGE_DATA_PATH}/${suite}.cov" \
        "$@" || STATUS=$?
done

runPhp "${BIN_DIR}/phpcov" merge \
    --text php://stdout \
    --html "${COVERAGE_PATH}" \
    "${COVERAGE_DATA_PATH}"

echo "HTML report: ${COVERAGE_PATH}/index.html"

exit "${STATUS}"
