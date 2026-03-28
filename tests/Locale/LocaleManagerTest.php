<?php

namespace VISU\Tests\Locale;

use PHPUnit\Framework\TestCase;
use VISU\Locale\LocaleManager;
use VISU\Signal\Dispatcher;
use VISU\Signals\Locale\LocaleChangedSignal;

class LocaleManagerTest extends TestCase
{
    private function createManager(): LocaleManager
    {
        return new LocaleManager();
    }

    // --- Basic API ---

    public function testDefaultLocaleIsEnglish(): void
    {
        $manager = $this->createManager();
        $this->assertSame('en', $manager->getCurrentLocale());
        $this->assertSame('en', $manager->getFallbackLocale());
    }

    public function testSetAndGetLocale(): void
    {
        $manager = $this->createManager();
        $manager->setLocale('de');
        $this->assertSame('de', $manager->getCurrentLocale());
    }

    public function testSetFallbackLocale(): void
    {
        $manager = $this->createManager();
        $manager->setFallbackLocale('fr');
        $this->assertSame('fr', $manager->getFallbackLocale());
    }

    // --- Loading translations ---

    public function testLoadArray(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', [
            'menu' => [
                'start' => 'Start Game',
                'quit' => 'Quit',
            ],
        ]);
        $manager->setLocale('en');

        $this->assertSame('Start Game', $manager->get('menu.start'));
        $this->assertSame('Quit', $manager->get('menu.quit'));
    }

    public function testLoadArrayFlat(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', [
            'greeting' => 'Hello',
        ]);
        $manager->setLocale('en');

        $this->assertSame('Hello', $manager->get('greeting'));
    }

    public function testLoadArrayDeepNesting(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', [
            'game' => [
                'ui' => [
                    'hud' => [
                        'health' => 'HP',
                    ],
                ],
            ],
        ]);
        $manager->setLocale('en');

        $this->assertSame('HP', $manager->get('game.ui.hud.health'));
    }

    public function testLoadArrayMergesExisting(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['a' => 'first']);
        $manager->loadArray('en', ['b' => 'second']);
        $manager->setLocale('en');

        $this->assertSame('first', $manager->get('a'));
        $this->assertSame('second', $manager->get('b'));
    }

    public function testLoadFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'locale_') . '.json';
        file_put_contents($tmpFile, json_encode([
            'menu' => ['play' => 'Play'],
        ]));

        try {
            $manager = $this->createManager();
            $manager->loadFile('en', $tmpFile);
            $manager->setLocale('en');

            $this->assertSame('Play', $manager->get('menu.play'));
        } finally {
            unlink($tmpFile);
        }
    }

    public function testLoadFileMissing(): void
    {
        $manager = $this->createManager();
        $this->expectException(\RuntimeException::class);
        $manager->loadFile('en', '/nonexistent/path.json');
    }

    public function testLoadFileInvalidJson(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'locale_') . '.json';
        file_put_contents($tmpFile, 'not json');

        try {
            $manager = $this->createManager();
            $this->expectException(\RuntimeException::class);
            $manager->loadFile('en', $tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testLoadDirectory(): void
    {
        $tmpDir = sys_get_temp_dir() . '/visu_locale_test_' . uniqid();
        mkdir($tmpDir);
        file_put_contents($tmpDir . '/en.json', json_encode(['hello' => 'Hello']));
        file_put_contents($tmpDir . '/de.json', json_encode(['hello' => 'Hallo']));

        try {
            $manager = $this->createManager();
            $manager->loadDirectory($tmpDir);

            $manager->setLocale('en');
            $this->assertSame('Hello', $manager->get('hello'));

            $manager->setLocale('de');
            $this->assertSame('Hallo', $manager->get('hello'));
        } finally {
            unlink($tmpDir . '/en.json');
            unlink($tmpDir . '/de.json');
            rmdir($tmpDir);
        }
    }

    public function testLoadDirectoryMissing(): void
    {
        $manager = $this->createManager();
        $this->expectException(\RuntimeException::class);
        $manager->loadDirectory('/nonexistent/dir');
    }

    // --- Translation resolution ---

    public function testGetReturnsKeyWhenMissing(): void
    {
        $manager = $this->createManager();
        $this->assertSame('missing.key', $manager->get('missing.key'));
    }

    public function testGetFallsBackToFallbackLocale(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['greeting' => 'Hello']);
        $manager->setFallbackLocale('en');
        $manager->setLocale('de');

        $this->assertSame('Hello', $manager->get('greeting'));
    }

    public function testGetPrefersCurrentLocale(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['greeting' => 'Hello']);
        $manager->loadArray('de', ['greeting' => 'Hallo']);
        $manager->setFallbackLocale('en');
        $manager->setLocale('de');

        $this->assertSame('Hallo', $manager->get('greeting'));
    }

    // --- Parameter interpolation ---

    public function testParameterInterpolation(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', [
            'welcome' => 'Welcome, :name!',
        ]);
        $manager->setLocale('en');

        $this->assertSame('Welcome, Alice!', $manager->get('welcome', ['name' => 'Alice']));
    }

    public function testMultipleParameters(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', [
            'info' => ':name has :count items',
        ]);
        $manager->setLocale('en');

        $this->assertSame('Alice has 5 items', $manager->get('info', ['name' => 'Alice', 'count' => 5]));
    }

    public function testParameterNotFound(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['msg' => 'Hello :name']);
        $manager->setLocale('en');

        $this->assertSame('Hello :name', $manager->get('msg'));
    }

    // --- Pluralization ---

    public function testChoiceSimpleTwoForms(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', [
            'items' => ':count item|:count items',
        ]);
        $manager->setLocale('en');

        $this->assertSame('1 item', $manager->choice('items', 1));
        $this->assertSame('5 items', $manager->choice('items', 5));
        $this->assertSame('0 items', $manager->choice('items', 0));
    }

    public function testChoiceThreeForms(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', [
            'apples' => 'No apples|One apple|:count apples',
        ]);
        $manager->setLocale('en');

        $this->assertSame('No apples', $manager->choice('apples', 0));
        $this->assertSame('One apple', $manager->choice('apples', 1));
        $this->assertSame('3 apples', $manager->choice('apples', 3));
    }

    public function testChoiceExplicitCounts(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', [
            'items' => '{0} Nothing|{1} One item|[2,*] :count items',
        ]);
        $manager->setLocale('en');

        $this->assertSame('Nothing', $manager->choice('items', 0));
        $this->assertSame('One item', $manager->choice('items', 1));
        $this->assertSame('99 items', $manager->choice('items', 99));
    }

    public function testChoiceExplicitRange(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', [
            'score' => '{0} No score|[1,3] Low|[4,7] Medium|[8,*] High',
        ]);
        $manager->setLocale('en');

        $this->assertSame('No score', $manager->choice('score', 0));
        $this->assertSame('Low', $manager->choice('score', 2));
        $this->assertSame('Medium', $manager->choice('score', 5));
        $this->assertSame('High', $manager->choice('score', 10));
    }

    public function testChoiceSingleForm(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['msg' => ':count things']);
        $manager->setLocale('en');

        $this->assertSame('3 things', $manager->choice('msg', 3));
    }

    // --- has() ---

    public function testHas(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['exists' => 'yes']);
        $manager->setLocale('en');

        $this->assertTrue($manager->has('exists'));
        $this->assertFalse($manager->has('missing'));
    }

    public function testHasChecksCurrentAndFallback(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['only_en' => 'English']);
        $manager->setFallbackLocale('en');
        $manager->setLocale('de');

        $this->assertTrue($manager->has('only_en'));
    }

    // --- Available locales ---

    public function testGetAvailableLocales(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['a' => 'b']);
        $manager->loadArray('de', ['a' => 'b']);
        $manager->loadArray('fr', ['a' => 'b']);

        $locales = $manager->getAvailableLocales();
        sort($locales);
        $this->assertSame(['de', 'en', 'fr'], $locales);
    }

    // --- resolveTranslations() ---

    public function testResolveTranslationsSimple(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['menu.start' => 'Start Game']);
        $manager->setLocale('en');

        $this->assertSame('Start Game', $manager->resolveTranslations('{t:menu.start}'));
    }

    public function testResolveTranslationsWithParams(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['welcome' => 'Welcome, :name!']);
        $manager->setLocale('en');

        $this->assertSame('Welcome, Alice!', $manager->resolveTranslations('{t:welcome|name=Alice}'));
    }

    public function testResolveTranslationsMultipleInString(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', [
            'hp' => 'HP',
            'mp' => 'MP',
        ]);
        $manager->setLocale('en');

        $this->assertSame('HP / MP', $manager->resolveTranslations('{t:hp} / {t:mp}'));
    }

    public function testResolveTranslationsMixedWithData(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['label' => 'Gold']);
        $manager->setLocale('en');

        // This tests the string after translation resolution still contains {data.binding}
        $result = $manager->resolveTranslations('{t:label}: {economy.gold}');
        $this->assertSame('Gold: {economy.gold}', $result);
    }

    // --- Signal dispatch ---

    public function testLocaleChangedSignal(): void
    {
        $dispatcher = new Dispatcher();
        $manager = new LocaleManager($dispatcher);

        $received = null;
        $dispatcher->register('locale.changed', function (LocaleChangedSignal $signal) use (&$received): void {
            $received = $signal;
        });

        $manager->setLocale('de');

        $this->assertNotNull($received);
        $this->assertSame('en', $received->previousLocale);
        $this->assertSame('de', $received->newLocale);
    }

    public function testNoSignalWhenLocaleUnchanged(): void
    {
        $dispatcher = new Dispatcher();
        $manager = new LocaleManager($dispatcher);

        $callCount = 0;
        $dispatcher->register('locale.changed', function () use (&$callCount): void {
            $callCount++;
        });

        $manager->setLocale('en'); // same as default
        $this->assertSame(0, $callCount);
    }

    // --- UIDataContext integration ---

    public function testUIDataContextTranslationIntegration(): void
    {
        $manager = $this->createManager();
        $manager->loadArray('en', ['ui.health' => 'Health']);
        $manager->setLocale('en');

        $ctx = new \VISU\UI\UIDataContext();
        $ctx->setLocaleManager($manager);
        $ctx->set('player.hp', 100);

        $result = $ctx->resolveBindings('{t:ui.health}: {player.hp}');
        $this->assertSame('Health: 100', $result);
    }

    public function testUIDataContextWithoutLocaleManager(): void
    {
        $ctx = new \VISU\UI\UIDataContext();
        $ctx->set('val', 42);

        // {t:...} expressions remain unresolved without locale manager
        $result = $ctx->resolveBindings('{t:some.key} = {val}');
        $this->assertSame('{t:some.key} = 42', $result);
    }
}
