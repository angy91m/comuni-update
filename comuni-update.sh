#!/bin/bash
SCRIPT_DIR=$(realpath "$(dirname "$0")")
LAST_CYCLE_FILE=$(cat "$SCRIPT_DIR/bk/last-cycle.txt")
LAST_CYCLE_FILE=${LAST_CYCLE_FILE%,*}
if [ ! -f "$SCRIPT_DIR/bk/$LAST_CYCLE_FILE" ]; then
    echo "Invalid file"
    exit 1
fi
if [ ! -L "$SCRIPT_DIR/comuni.json" ]; then
    ln -s "$SCRIPT_DIR/bk/$LAST_CYCLE_FILE" "$SCRIPT_DIR/comuni.json"
fi
php "$SCRIPT_DIR/comuni-update.php" && \
LAST_CYCLE_FILE=$(cat "$SCRIPT_DIR/bk/last-cycle.txt") && \
LAST_CYCLE_FILE=${LAST_CYCLE_FILE%,*} && \
ln -sfn "$SCRIPT_DIR/bk/$LAST_CYCLE_FILE" "$SCRIPT_DIR/comuni.json"
