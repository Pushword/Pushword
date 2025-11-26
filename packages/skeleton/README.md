# Pushword: Skeleton / Demo App

This package is used for testing purpose, for demoing and by the installer. It's not a copy-paster skeleton, prefer use the [installer](https://pushword.piedweb.com/installation)

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)

[![Code Coverage](https://codecov.io/gh/Pushword/Pushword/branch/main/graph/badge.svg)](https://codecov.io/gh/Pushword/Pushword/tree/main)
[![Type Coverage](https://shepherd.dev/github/pushword/pushword/coverage.svg)](https://shepherd.dev/github/pushword/pushword)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/core.svg?style=flat)](https://packagist.org/packages/pushword/core)

## Build it

```bash
cd packages/skeleton;

rm -rf media && cp -r media~ media

php bin/console doctrine:schema:update --force
php bin/console doctrine:fixtures:load

# Add an admin user :
read -p 'Email: ' emailvar
read -sp 'Password: ' passvar
php bin/console pw:user:create $emailvar $passvar ROLE_SUPER_ADMIN
#php bin/console pw:user:create admin@example.tld p@ssword ROLE_SUPER_ADMIN

# Install Bundle Assets
php bin/console assets:install
# `yarn build` Should not be runned because it's erasing the documentation assets.
# the files are there only to help final user on a new installation

# Launch Server and Play
symfony server:start -d
```

## Contributing

If you're interested in contributing to Pushword, please read our [contributing docs](https://pushword.piedweb.com/contribute) before submitting a pull request.

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/Pushword/Core/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license#license) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
