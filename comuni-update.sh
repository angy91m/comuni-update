#!/bin/bash
SCRIPT_DIR=$(realpath "$(dirname "$0")")
LAST_CYCLE_CONTENT=$(cat "$SCRIPT_DIR/bk/last-cycle.txt")
OLD_IFS=$IFS
IFS=','
read -ra LAST_CYCLE_ARR <<< "$LAST_CYCLE_CONTENT"
IFS=$OLD_IFS
LAST_CYCLE_FILE_COMUNI=${LAST_CYCLE_ARR[0]}
LAST_CYCLE_FILE_PROVINCE=${LAST_CYCLE_ARR[1]}
if [ ! -f "$SCRIPT_DIR/bk/$LAST_CYCLE_FILE_COMUNI" ]; then
    echo "Invalid file comuni"
    exit 1
fi
if [ ! -f "$SCRIPT_DIR/bk/$LAST_CYCLE_FILE_PROVINCE" ]; then
    echo "Invalid file province"
    exit 1
fi
if [ ! -L "$SCRIPT_DIR/comuni.json" ]; then
    ln -s "$SCRIPT_DIR/bk/$LAST_CYCLE_FILE_COMUNI" "$SCRIPT_DIR/comuni.json"
fi
if [ ! -L "$SCRIPT_DIR/province.json" ]; then
    ln -s "$SCRIPT_DIR/bk/$LAST_CYCLE_FILE_PROVINCE" "$SCRIPT_DIR/province.json"
fi
php "$SCRIPT_DIR/comuni-update.php" "$@" && \
LAST_CYCLE_CONTENT=$(cat "$SCRIPT_DIR/bk/last-cycle.txt") && \
OLD_IFS=$IFS && \
IFS=',' && \
read -ra LAST_CYCLE_ARR <<< "$LAST_CYCLE_CONTENT" && \
IFS=$OLD_IFS && \
LAST_CYCLE_FILE_COMUNI=${LAST_CYCLE_ARR[0]} && \
LAST_CYCLE_FILE_PROVINCE=${LAST_CYCLE_ARR[1]} && \
ln -sfn "$SCRIPT_DIR/bk/$LAST_CYCLE_FILE_COMUNI" "$SCRIPT_DIR/comuni.json" && \
ln -sfn "$SCRIPT_DIR/bk/$LAST_CYCLE_FILE_PROVINCE" "$SCRIPT_DIR/province.json"