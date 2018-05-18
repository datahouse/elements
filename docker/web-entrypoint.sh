#!/bin/bash

set -e

if [[ ! -d /srv/data/yaml ]]; then
    echo "creating yaml storage directory"
    mkdir -p /srv/data/yaml
fi

if [[ ! -d /srv/data/blobs ]]; then
    echo "creating blobs storage directory"
    mkdir -p /srv/data/blobs
fi

if [[ ! -d /var/lib/php/sessions ]]; then
    echo "creating sessions directory"
    mkdir -p /var/lib/php/sessions
fi

# Ensure the data and sessions directories are writeable by the www-data
# user. Just to be extra sure, we do that even if the directory already
# exists.
chown -R www-data. /srv/data/yaml /srv/data/blobs /var/lib/php/sessions

# Apache gets grumpy about PID files pre-existing
rm -f /var/run/apache2/apache2.pid

exec apache2 -DFOREGROUND
