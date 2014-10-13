#! /bin/sh
while true; do

if ps -ef |grep -v grep |grep "phpci:run-builds"; then
        echo "nothing todo"
else
    echo "starting phpci cron"
    /phpci/console phpci:run-builds
fi
  sleep 15
done