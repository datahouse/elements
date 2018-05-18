#!/bin/bash

set -e

exec /usr/bin/php /srv/bgworker/vendor/datahouse/elements/bin/bgworker.php
