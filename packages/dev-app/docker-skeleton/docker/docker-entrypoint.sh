#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
	# Development: the project is bind-mounted, so vendor/ is whatever the host has.
	if [ ! -d vendor ] || [ -z "$(ls -A vendor 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# A compiled container holds absolute paths: `%kernel.project_dir%/var/app.db` is
	# baked as wherever the project stood when the cache was written. In production
	# var/ is a volume, so it commonly arrives from somewhere else — a restored
	# backup, or the project `composer create-project` built on the host — and reusing
	# that cache points every path at a directory this filesystem does not have. The
	# first symptom is a SQLite "unable to open database file" from the schema update
	# below. The entrypoint clears the cache on the way out anyway, so nothing is lost
	# by refusing to start from one built elsewhere.
	#
	# The contents, not the directory: compose.yaml gives development its own volume
	# for var/cache (same reason, solved by keeping the two apart), and a mount point
	# cannot be removed from inside the container.
	if [ -d var/cache ]; then
		find var/cache -mindepth 1 -delete
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
		# Ask the application whether the configured database already has an account,
		# rather than naming its physical table here (`user` is reserved by PostgreSQL).
		# insert decide. `pw:user:create` fails on exactly one thing — the unique
		# constraint over `email` — so a database whose admin is any other address used
		# to take the insert happily and end up with a *second* super admin, holding the
		# published default credentials. The marker cannot stand in for this check: the
		# documented backup copies `app.db` alone, so a restore arrives without it.
		if php bin/console pw:user:exists -q; then
			echo '~~ No account created: this database already has one.'
		else
			admin_email="${PUSHWORD_ADMIN_EMAIL:-admin@example.tld}"
			admin_password="${PUSHWORD_ADMIN_PASSWORD:-}"
			require_change=''
			if [ -z "$admin_password" ]; then
				admin_password="$(php -r 'echo bin2hex(random_bytes(16));')"
				require_change='--require-password-change'
			fi

			if php bin/console pw:user:create "$admin_email" "$admin_password" ROLE_SUPER_ADMIN $require_change -q; then
				echo "~~ Super admin created: $admin_email"
				if [ -n "$require_change" ]; then
					echo "~~ One-time temporary password: $admin_password"
					echo '~~ It must be changed on first login and will not be displayed again.'
				fi
			else
				echo '~~ Warning: no account exists and none could be created. Make one with'
				echo '~~ `docker compose exec pushword php bin/console pw:user:create`.'
			fi
		fi

		touch var/.pushword-seeded
	fi

	php bin/console assets:install --symlink --relative -q
	php bin/console cache:clear -q
fi

exec docker-php-entrypoint "$@"
