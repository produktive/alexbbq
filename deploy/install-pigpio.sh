#!/bin/bash
# Install pigpio (libpigpio) for the Maverick GPIO daemon.
#
# Bookworm: apt packages pigpio + libpigpio-dev.
# Trixie: those packages were removed — build joan2937/pigpio v79 from source.
#
# Maverick links libpigpio directly (gpioInitialise); pigpiod is not required.
#
# Usage (as root):
#   sudo ./deploy/install-pigpio.sh
set -euo pipefail

PIGPIO_VERSION="${PIGPIO_VERSION:-79}"
PIGPIO_URL="https://github.com/joan2937/pigpio/archive/refs/tags/v${PIGPIO_VERSION}.tar.gz"

log() { echo "==> $*"; }
die() { echo "Error: $*" >&2; exit 1; }

pigpio_installed() {
    [[ -f /usr/include/pigpio.h ]] && return 0
    [[ -f /usr/local/include/pigpio.h ]] && return 0
    ldconfig -p 2>/dev/null | grep -q 'libpigpio\.so' && return 0
    return 1
}

install_pigpio_apt() {
    log "Trying apt: pigpio libpigpio-dev"
    apt-get install -y pigpio libpigpio-dev
}

install_pigpio_from_source() {
    log "pigpio not in apt — building v${PIGPIO_VERSION} from source (Trixie / Pi OS 13+)"
    apt-get install -y wget make gcc

    local build_dir
    build_dir="$(mktemp -d)"
    trap 'rm -rf "$build_dir"' EXIT

    wget -qO "${build_dir}/pigpio.tar.gz" "$PIGPIO_URL"
    tar -xzf "${build_dir}/pigpio.tar.gz" -C "$build_dir"
    cd "${build_dir}/pigpio-${PIGPIO_VERSION}"

    make -j"$(nproc 2>/dev/null || echo 1)"
    make install
    ldconfig

    log "pigpio v${PIGPIO_VERSION} installed to /usr/local"
}

main() {
    if [[ "$(id -u)" -ne 0 ]]; then
        die "Run as root: sudo $0"
    fi

    if pigpio_installed; then
        log "pigpio already installed — skipping"
        exit 0
    fi

    if install_pigpio_apt 2>/dev/null; then
        log "pigpio installed via apt"
        exit 0
    fi

    install_pigpio_from_source

    if ! pigpio_installed; then
        die "pigpio install finished but libpigpio was not found — check build output"
    fi

    log "pigpio ready"
}

main "$@"
