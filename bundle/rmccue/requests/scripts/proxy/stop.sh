#!/usr/bin/env bash

PROXYDIR="$PWD/$(dirname $0)"
PORT=${PORT:-9000}

PIDFILE="$PROXYDIR/proxy-$PORT.pid"

set -x

start-stop-daemon --verbose --stop --pidfile $PIDFILE --remove-pidfile
