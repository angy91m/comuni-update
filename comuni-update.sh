#!/bin/bash
SCRIPT_DIR=$(realpath "$(dirname "$0")")
cd "$SCRIPT_DIR"
LAST_CYCLE_CONTENT=$(cat bk/last-cycle.txt)
OLD_IFS=$IFS
IFS=','
read -ra LAST_CYCLE_ARR <<< "$LAST_CYCLE_CONTENT"
IFS=$OLD_IFS
LAST_CYCLE_FILE_COMUNI=${LAST_CYCLE_ARR[0]}
LAST_CYCLE_FILE_PROVINCE=${LAST_CYCLE_ARR[1]}
if [ ! -f "bk/$LAST_CYCLE_FILE_COMUNI" ]; then
    echo "Invalid file comuni"
    exit 1
fi
if [ ! -f "bk/$LAST_CYCLE_FILE_PROVINCE" ]; then
    echo "Invalid file province"
    exit 1
fi
if [ ! -L "comuni.json" ]; then
    ln -s "bk/$LAST_CYCLE_FILE_COMUNI" comuni.json
fi
if [ ! -L "province.json" ]; then
    ln -s "bk/$LAST_CYCLE_FILE_PROVINCE" province.json
fi
php comuni-update.php "$@" && \
LAST_CYCLE_CONTENT=$(cat bk/last-cycle.txt) && \
OLD_IFS=$IFS && \
IFS=',' && \
read -ra LAST_CYCLE_ARR <<< "$LAST_CYCLE_CONTENT" && \
IFS=$OLD_IFS && \
LAST_CYCLE_FILE_COMUNI=${LAST_CYCLE_ARR[0]} && \
LAST_CYCLE_FILE_PROVINCE=${LAST_CYCLE_ARR[1]} && \
ln -sfn "bk/$LAST_CYCLE_FILE_COMUNI" comuni.json && \
ln -sfn "bk/$LAST_CYCLE_FILE_PROVINCE" province.json
