#! /bin/sh

sudo -u www-data -H sh -c "cd /phpci && git config --global core.compression 0"
while true; do

if ps -ef |grep -v grep |grep "phpci:run-builds"; then
        echo "nothing todo"
else
    echo "starting phpci cron"
    sudo -u www-data -H sh -c "/phpci/console phpci:run-builds"
fi
  sleep 15
done