#!/usr/bin/env bash

PROXYDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PORT=${PORT:-9000}

PROXYBIN=${PROXYBIN:-"$(which mitmdump)"}
ARGS="-s '$PROXYDIR/proxy.py' -p $PORT"
if [[ ! -z "$AUTH" ]]; then
	ARGS="$ARGS --proxyauth $AUTH"
fi
PIDFILE="$PROXYDIR/proxy-$PORT.pid"

set -x

start-stop-daemon --verbose --start --background --pidfile $PIDFILE --make-pidfile --exec $PROXYBIN -- $ARGS

ps -p $(cat $PIDFILE) u
sleep 2
ps -p $(cat $PIDFILE) u
