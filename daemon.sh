#!/bin/sh

sudo -u www-data -H sh -c "cd /phpci && git config --global core.compression 0"

adduser www-data sudo
echo '%sudo ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers

while true; do

#if ps -ef |grep -v grep |grep "phpci:run-builds"; then
#        echo "$(date +"%T") : nothing todo"
#         sudo -u www-data -H sh -c "/phpci/console phpci:check-builds --verbose"
#else
    echo "$(date +"%T") : starting phpci cron"
    sudo -u www-data -H sh -c "/phpci/console phpci:run-builds --verbose" &
#fi
sleep 60
done