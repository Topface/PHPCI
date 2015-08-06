FROM docker.core.tf/topface-dev

MAINTAINER Stan Gumeniuk s.gumeniuk@topface.com

# docker build -t topface/phpci .

RUN apt-get update && apt-get install -y gawk && echo "zend_extension=xdebug.so" > /etc/php5/mods-available/xdebug.ini && curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/bin/composer


ADD ./ /phpci

RUN chmod u+x /phpci/daemon.sh && chown -R www-data /phpci && echo 'root:12345' |chpasswd && passwd www-data -d && cd /phpci && git config --global core.compression 0 && chmod 777 /var/run/ && chmod 777 /bin/

EXPOSE 22

CMD /phpci/daemon.sh