FROM php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143

RUN set -eux; \
    curl -fsSL -o /tmp/debian-archive-keyring.deb \
        https://deb.debian.org/debian/pool/main/d/debian-archive-keyring/debian-archive-keyring_2023.3+deb12u2_all.deb; \
    dpkg -i /tmp/debian-archive-keyring.deb; \
    rm /tmp/debian-archive-keyring.deb; \
    printf 'deb http://archive.debian.org/debian stretch main\n' > /etc/apt/sources.list; \
    echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg mariadb-client \
        default-libmysqlclient-dev; \
    docker-php-ext-install pdo_mysql; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /workspace
