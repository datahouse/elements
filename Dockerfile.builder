FROM registry.datalan.ch/datahouse/it-docker-builder-php:v1

# add various (and way too many) tools required for the internal CSS and
# JS minimization logic (assetic).
RUN apt-get update \
  && apt-get install -y \
      default-jre-headless \
      coffeescript \
      ruby-sass \
      python3-pkg-resources \
      python3-pyscss \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

# copy php config with stuff necessary for elements
COPY docker/50-php-settings-elements.ini \
    /etc/php/7.0/cli/conf.d/50-elements.ini

# This container is not intended to be run directly.
CMD /bin/false
