FROM uselagoon/php-8.1-cli

COPY . /app

RUN composer install

CMD /app/vendor/bin/robo test