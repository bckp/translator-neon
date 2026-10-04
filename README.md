# NEON translation source

`bckp/translator-neon` implements core 3.x `TranslationSource`. It requires PHP 8.4+ and `nette/neon`; the core itself does not depend on the NEON decoder.

This is the `nextgen` development branch for the first standalone release. Its Composer package name and PHP namespace are unchanged from the original core plugin.

## Development

Clone this repository and the matching core branch as sibling directories. Composer links the unreleased core through a path repository:

```sh
git clone --branch nextgen https://github.com/bckp/translator-core.git
git clone --branch nextgen https://github.com/bckp/translator-neon.git
cd translator-neon
COMPOSER_ROOT_VERSION=1.0.x-dev composer install
composer tests
composer phpstan
composer phpcs
```

PHPStan runs at level 8. Source code uses native types, strict types and attributes without docblocks. PHP CS Fixer uses PER Coding Style 3.0 with tab indentation and PHP 8.4 migration rules; EditorConfig selects the same indentation. CI checks PHP 8.4 and 8.5.

## Usage

```php
<?php

declare(strict_types=1);

use Bckp\Translator\CatalogueBuilder;
use Bckp\Translator\Neon\NeonSource;
use Bckp\Translator\Storage\MemoryStorage;
use Bckp\Translator\StringList;
use Bckp\Translator\Translator;

$source = new NeonSource(__DIR__ . '/locales');
$builder = new CatalogueBuilder(new MemoryStorage(), 'cs');
$builder->addSource('files', $source);
```

Directory order is explicit. To merge shared translations and application overrides, register:

```php
// Later directories override earlier ones.
$builder->addSource('other-files', new NeonSource(
    new StringList(__DIR__ . '/shared', __DIR__ . '/overrides'),
));

// A fixed release version avoids automatic content hashing.
$builder->addSource('immutable-files', new NeonSource(
    __DIR__ . '/release-locales',
    'release-42',
));
```

Then initialize the translator:

```php
$translator = new Translator($builder->compile());
echo $translator->translate('messages.welcome');
```

## Versioning and file format

Constructing a source performs no IO. `getVersion($locale)` hashes matching files' paths and contents, in deterministic order. It detects new files, deleted files and content changes with unchanged modification times. Files of other locales do not change that locale's version.

With a configured version string, `getVersion()` returns that string without touching the filesystem. Change it on deployment or call `forceRecompile()` when the files change.

Directories are searched recursively. Files are sorted by pathname within each directory; configured directory order is retained. File names follow `{resource}.{locale}.neon`. Locale spelling is normalized, so `messages.CS_CZ.neon` matches `cs-cz`; its keys start with `messages.`.

The loader parses only the requested locale and returns `MessageCatalogue`. Each entry must be a string or a map of known plural variants (`zero`, `one`, `two`, `few`, `many`, `other`) whose values are strings. At least one plural variant must be defined. Numeric and boolean values must be quoted if intended as text. Empty files contribute no messages.

```neon
welcome: 'Vítejte'
counter: '0'
hidden: ''
people:
    one: '%d člověk'
    few: '%d lidé'
    other: '%d lidí'
```

Unreadable paths throw `RuntimeException`; invalid NEON or translation values throw `InvalidTranslationException`. The builder does not publish a replacement catalogue if loading fails.
