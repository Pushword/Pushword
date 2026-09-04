# syntax=docker/dockerfile:1
#
# Pushword on FrankenPHP. Copied into your project by `php bin/console pw:docker:init`.
# The layout follows dunglas/symfony-docker; what differs is documented inline, and in
# https://pushword.piedweb.com/docker

# FrankenPHP 1.12.7 still embeds vulnerable Go dependencies. Rebuild the same immutable
# stable revision with patched dependencies until the next release includes them. Both
# digests are multi-platform image indexes for that release.
FROM dunglas/frankenphp:1.12.7-builder-php8.4@sha256:4936ac46f73695ad7994d4cfd9eac7ce1e0a4666c0da3834d601ed90b36e8bb3 AS frankenphp_builder

ARG FRANKENPHP_VERSION=1.12.7

WORKDIR /go/src/app/caddy
RUN go get \
		github.com/getkin/kin-openapi@v0.144.0 \
		golang.org/x/crypto@v0.55.0 \
		google.golang.org/grpc@v1.83.1

WORKDIR /go/src/app/caddy/frankenphp
RUN GOBIN=/patched ../../go.sh install -buildvcs=false -ldflags "-w -s -X 'github.com/caddyserver/caddy/v2.CustomVersion=FrankenPHP $FRANKENPHP_VERSION PHP $PHP_VERSION Caddy' -X 'github.com/caddyserver/caddy/v2.CustomBinaryName=frankenphp' -X 'github.com/caddyserver/caddy/v2/modules/caddyhttp.ServerHeader=FrankenPHP Caddy'"

FROM dunglas/frankenphp:1.12.7-php8.4@sha256:77bc2d40a58ace3a9425e4cbb0c40d044188dfd850e44a943ed043d743625df3 AS pushword_base

COPY --from=frankenphp_builder /patched/frankenphp /usr/local/bin/frankenphp

WORKDIR /app

# git: pushword/flat commits content. brotli: the static generator shells out to it.
# The four image binaries: spatie/image-optimizer's chain, used on every derivative.
RUN apt-get update && apt-get upgrade -y && apt-get install --no-install-recommends -y \
		brotli \
		gifsicle \
		git \
		libjpeg-turbo-progs \
		optipng \
		pngquant \
		webp \
	&& rm -rf /var/lib/apt/lists/* \
	&& setcap cap_net_bind_service=+ep /usr/local/bin/frankenphp

# curl, dom, fileinfo, iconv, libxml, mbstring, pdo, pdo_sqlite and sqlite3 already ship
# with the base image; this is the rest of what /installation asks for. imagick makes
# ImageReader pick a driver faster than gd without needing FFI.
RUN install-php-extensions \
		@composer \
		apcu \
		bcmath \
		exif \
		gd \
		imagick \
		intl \
		opcache \
		pdo_pgsql \
		soap \
		zip

# The same Caddyfile a native `frankenphp run` uses: the container only overrides its
# placeholders through the environment.
COPY --link Caddyfile /etc/frankenphp/Caddyfile
COPY --link docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENV CADDY_ADMIN="localhost:2019" \
	CADDY_SERVER_NAME=":80"

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
	CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics") ? 1 : 0);'


# --- Development -------------------------------------------------------------------
# The project is bind-mounted (see compose.override.yaml), so nothing is copied in.
# Runs as root: a bind mount keeps its host ownership, and matching uids inside the
# container is not worth the trouble for a dev box.

FROM pushword_base AS pushword_dev

ENV APP_ENV=dev XDEBUG_MODE=off

COPY --link docker/php.dev.ini /usr/local/etc/php/conf.d/pushword.ini

RUN install-php-extensions xdebug


# --- Production --------------------------------------------------------------------

FROM pushword_base AS pushword_builder

COPY --link composer.* symfony.* ./

# --no-scripts: post-autoload-dump boots the kernel to run pending bundle installers,
# which needs a database. The entrypoint does that at boot instead.
RUN composer install --no-cache --prefer-dist --no-dev --no-scripts --no-progress --no-autoloader

COPY --link . ./

RUN composer dump-autoload --classmap-authoritative --no-dev --no-scripts


FROM pushword_base AS pushword_prod

ENV APP_ENV=prod

COPY --link docker/php.prod.ini /usr/local/etc/php/conf.d/pushword.ini
COPY --from=pushword_builder --link /app /app

# Everything Pushword writes at runtime. Four of these are the volumes compose.prod.yaml
# declares, and creating them here owned by www-data is what makes a *fresh* named
# volume writable: Docker seeds an empty volume from the image directory, ownership
# included. public/bundles is not a volume — it is the symlink tree `assets:install`
# rebuilds at every boot, and /app itself stays root-owned.
RUN set -eux; \
	mkdir -p var media public/media public/bundles static; \
	chown -R www-data:www-data var media public/media public/bundles static; \
	# Caddy's own state — TLS certificates and its autosaved config — is root-owned in
	# the upstream image. Without this, automatic HTTPS has nowhere to store a
	# certificate and every boot logs a permission error.
	chown -R www-data:www-data /data /config

USER www-data
