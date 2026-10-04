<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Bckp\Translator\CatalogueBuilder;
use Bckp\Translator\Neon\InvalidTranslationException;
use Bckp\Translator\Neon\NeonSource;
use Bckp\Translator\Storage\MemoryStorage;
use Bckp\Translator\Translator;
use Tester\Assert;

$path = TEMP_DIR . '/locales';
mkdir($path);
$file = $path . '/messages.cs.neon';
file_put_contents($file, "hello: Original\npeople:\n    one: '%d person'\n    few: '%d people'\n    other: '%d people'\n");
$storage = new MemoryStorage();
$request = static fn(): CatalogueBuilder => new CatalogueBuilder($storage, 'cs')
	->addSource('files', new NeonSource($path))->setCheckProbability(1);
$translator = new Translator($request()->compile());
Assert::same('Original', $translator->translate('messages.hello'));
Assert::same('3 people', $translator->translate('messages.people', 3));

file_put_contents($file, "hello: Updated\n");
Assert::same('Updated', $request()->compile()->get('messages.hello'));
Assert::same('Original', $translator->translate('messages.hello'));

$warm = new CatalogueBuilder($storage, 'cs')
	->addSource('files', new NeonSource(TEMP_DIR . '/missing'))->setCheckProbability(0);
Assert::same('Updated', $warm->compile()->get('messages.hello'));

$fixedRequest = static fn(): CatalogueBuilder => new CatalogueBuilder($storage, 'cs', namespace: 'fixed')
	->addSource('files', new NeonSource($path, 'release-1'))->setCheckProbability(1);
$fixed = $fixedRequest();
$live = new Translator($fixed->compile());
file_put_contents($file, "hello: Forced\n");
Assert::same('Updated', $fixedRequest()->compile()->get('messages.hello'));
$live->replaceCatalogue($fixed->forceRecompile());
Assert::same('Forced', $live->translate('messages.hello'));

file_put_contents($file, "hello: 123\n");
Assert::exception(static fn() => $fixed->forceRecompile(), InvalidTranslationException::class);
Assert::same('Forced', $live->translate('messages.hello'));
Assert::same('Forced', $fixed->compile()->get('messages.hello'));
Assert::same('Forced', $fixedRequest()->compile()->get('messages.hello'));
