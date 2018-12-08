#!/usr/bin/env bash

docker run -it --rm \
    -u $UID:$GID \
    -v $PWD:/srv \
    -v $HOME/.composer:/.composer \
    -v $HOME/projects/composer.phar:/usr/local/bin/composer \
    -w /srv \
    php:7.1-alpine sh
