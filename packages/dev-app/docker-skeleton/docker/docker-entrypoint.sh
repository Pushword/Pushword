#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# Development: the project is bind-mounted, so vendor/ is whatever the host has.
	if [ ! -d vendor ] || [ -z "$(ls -A vendor 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Pushword has no migrations: the schema is derived from the entities, and this is
	# idempotent, so it also covers the update-the-image case.
	php bin/console doctrine:schema:update --force --no-interaction

	# `var/` is a volume, and an instance with no account cannot be logged into. A
	# development project is bind-mounted and was installed on the host, so
	# `pw:docker:init` marks it and this is skipped; an empty production volume gets an
	# account and nothing else. No starter content: production content is yours, and it
	# arrives with the database you restore or the files pw:flat:sync reads.
	if [ ! -f var/.pushword-seeded ]; then
		if php bin/console pw:user:create \
			"${PUSHWORD_ADMIN_EMAIL:-admin@example.tld}" \
			"${PUSHWORD_ADMIN_PASSWORD:-p@ssword}" \
			ROLE_SUPER_ADMIN -q
		then
			echo "~~ Super admin created: ${PUSHWORD_ADMIN_EMAIL:-admin@example.tld}"
			if [ -z "${PUSHWORD_ADMIN_PASSWORD}" ]; then
				echo '~~ Its password is the published default, p@ssword. Log in on /admin'
				echo '~~ and change it, or set PUSHWORD_ADMIN_PASSWORD and start from an'
				echo '~~ empty volume.'
			fi
		else
			# A var/ restored from a backup already has its accounts.
			echo '~~ No account created: this database already has one.'
		fi

		touch var/.pushword-seeded
	fi

	php bin/console assets:install --symlink --relative -q
	php bin/console cache:clear -q
fi

exec docker-php-entrypoint "$@"
