FROM php:8.3-cli-bookworm

ENV TZ=Asia/Shanghai \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_PROCESS_TIMEOUT=1200

# 系统依赖：libpq 供 pdo_pgsql，libevent 供 ext-event，libcurl 供 swoole 的 swoole-curl 组件
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        procps \
        libpq-dev \
        libevent-dev \
        libcurl4-openssl-dev \
        libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# 内置扩展：PG 驱动 + 常驻内存进程必需（pcntl/sockets）+ bcmath
RUN docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql bcmath pcntl sockets \
    && rm -rf /usr/src/php*

# PECL 扩展：redis（缓存/队列/Pub-Sub）、event 与 swoole（两套事件循环，按进程分派）
# 注意 1：libevent-dev 不可 purge，event.so 运行时动态链接 libevent_openssl/libevent_extra。
# 注意 2：event.so 依赖 sockets 扩展的 socket_ce 符号，必须让它的 ini 排在 sockets 之后，
#         否则报 "undefined symbol: socket_ce"（conf.d 按文件名排序加载）。
# 注意 3：swoole 编译耗时较长（约数分钟），关闭调试与额外组件以缩短构建。
RUN pecl install redis \
    && pecl install event \
    && pecl install --configureoptions 'enable-sockets="yes" enable-openssl="yes" enable-http2="no" enable-mysqlnd="no" enable-swoole-curl="yes" enable-cares="no" with-swoole-odbc="no" with-swoole-oracle="no" enable-swoole-pgsql="yes" enable-swoole-sqlite="no" enable-brotli="no" enable-zstd="no"' swoole \
    && docker-php-ext-enable redis \
    && docker-php-ext-enable --ini-name zz-event.ini event \
    && docker-php-ext-enable --ini-name zz-swoole.ini swoole \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /app

EXPOSE 8787 8788

CMD ["php", "start.php", "start"]