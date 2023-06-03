#!/bin/bash

REQUESTS_TAG="v2.0.7"

DOWNLOAD_LINK="https://github.com/WordPress/Requests/archive/refs/tags/${REQUESTS_TAG}.tar.gz"

SCRIPT_DIR=$(dirname "$0")

BUNDLE_DIR="${SCRIPT_DIR}/../bundle"

# First check if Requests is already installed.
if [ -d "${BUNDLE_DIR}/rmccue/requests" ]; then

	# Check if the version is correct.
	if [ -f "${BUNDLE_DIR}/rmccue/requests/src/Requests/Requests.php" ]; then
		VERSION=$(grep -oP "const VERSION = '\K[0-9\.]*" ${BUNDLE_DIR}/rmccue/requests/src/Requests.php)
		if [ "$VERSION" == "$REQUESTS_TAG" ]; then
			exit 0
		fi
	fi
fi

# Remove old version.
rm -rf "${BUNDLE_DIR}/rmccue"

# Download and extract Requests.
mkdir -p "${BUNDLE_DIR}/rmccue/requests"
curl -L "${DOWNLOAD_LINK}" | tar xz -C "${BUNDLE_DIR}/rmccue/requests" --strip-components=1
