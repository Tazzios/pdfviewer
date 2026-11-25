<?php

namespace Tazzios\Pdfviewer\Tests\Unit;

use Joomla\CMS\Factory;
use Joomla\CMS\TestApplication;
use Joomla\Event\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tazzios\Plugin\Content\Pdfviewer\Extension\Pdfviewer;

/**
 * Checks that both plugins follow the Joomla 5/6 extension structure.
 */
final class JoomlaStandardsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public static function pluginProvider(): array
    {
        return [
            'content plugin' => ['content_plugin', 'Tazzios\Plugin\Content\Pdfviewer', 'plg_content_pdfviewer'],
            'editor button'  => ['editor_button', 'Tazzios\Plugin\EditorsXtd\Pdfviewer', 'plg_editors-xtd_pdfviewer'],
        ];
    }

    #[DataProvider('pluginProvider')]
    public function testManifestUsesNamespaceAndServiceProvider(string $dir, string $namespace, string $element): void
    {
        $root     = self::ROOT . '/' . $dir;
        $manifest = simplexml_load_file($root . '/pdfviewer.xml');

        $this->assertSame($namespace, (string) $manifest->namespace);
        $this->assertSame('src', (string) $manifest->namespace['path']);
        $this->assertSame('services', (string) $manifest->files->xpath('folder[@plugin="pdfviewer"]')[0]);
        $this->assertSame([], $manifest->files->xpath('filename[@plugin]'), 'no legacy entry file');
        $this->assertFileDoesNotExist($root . '/pdfviewer.php', 'old entry file must be gone');
        $this->assertFileExists($root . '/src/Extension/Pdfviewer.php');

        $provider = file_get_contents($root . '/services/provider.php');
        $this->assertStringContainsString('use ' . $namespace . '\Extension\Pdfviewer;', $provider);
        $this->assertStringContainsString('setDatabase(', $provider);

        $this->assertSame($element, strtolower((string) $manifest->name));
        $this->assertFileExists($root . '/language/en-GB/' . $element . '.ini');
        $this->assertFileExists($root . '/language/en-GB/' . $element . '.sys.ini');
    }

    #[DataProvider('pluginProvider')]
    public function testEveryLanguageKeyExists(string $dir, string $namespace, string $element): void
    {
        $root    = self::ROOT . '/' . $dir;
        $strings = parse_ini_file($root . '/language/en-GB/' . $element . '.ini', false, INI_SCANNER_RAW);
        $sys     = parse_ini_file($root . '/language/en-GB/' . $element . '.sys.ini', false, INI_SCANNER_RAW);
        $this->assertNotFalse($strings);
        $this->assertNotFalse($sys);

        $prefix = strtoupper(str_replace('-', '', $element)) . '_';
        $code   = file_get_contents($root . '/pdfviewer.xml');

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/src')) as $file) {
            if ($file->getExtension() === 'php') {
                $code .= file_get_contents($file->getPathname());
            }
        }

        if (is_dir($root . '/tmpl')) {
            $code .= file_get_contents($root . '/tmpl/default.php');
        }

        preg_match_all('/\b' . preg_quote($prefix, '/') . '[A-Z0-9_]+/', $code, $keys);
        $this->assertNotEmpty($keys[0]);

        foreach (array_unique($keys[0]) as $key) {
            $this->assertArrayHasKey($key, $strings, $key . ' is missing in ' . $element . '.ini');
        }

        $this->assertArrayHasKey($prefix . 'XML_DESCRIPTION', $sys);
        $this->assertArrayHasKey(strtoupper($element), $sys);
    }

    #[DataProvider('pluginProvider')]
    public function testNoOldOrDeprecatedApi(string $dir): void
    {
        $code = '';

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::ROOT . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $code .= file_get_contents($file->getPathname());
            }
        }

        $old = [
            'J3 class names'            => '/\bJ(Factory|Plugin|PluginHelper|Text|Uri|Session|Object|Registry)\b/',
            'CMSObject'                 => '/\bCMSObject\b/',
            'Factory::getDbo'           => '/getDbo\(/',
            'getQuery(true)'            => '/getQuery\(\s*true\s*\)/',
            'database from container'   => '/getContainer\(\)->get\(DatabaseInterface/',
            'application input prop'    => '/->input\b/',
            'Factory::getApplication'   => '/Factory::getApplication\(\)(?!\);)/',
        ];

        foreach ($old as $name => $pattern) {
            // The service provider may hand the application to the plugin.
            $check = preg_replace('/\$plugin->setApplication\(Factory::getApplication\(\)\);/', '', $code);
            $this->assertDoesNotMatchRegularExpression($pattern, $check, $name . ' is old or deprecated');
        }
    }

    public function testContentPluginSubscribesToContentPrepare(): void
    {
        $this->assertSame(['onContentPrepare' => 'onContentPrepare'], Pdfviewer::getSubscribedEvents());
    }

    public function testContentPluginAcceptsEventWithPositionalArguments(): void
    {
        Factory::$application = new TestApplication();

        $plugin = new Pdfviewer(['params' => ['style' => 'Embed', 'embedheight' => '800', 'embedwidth' => '100%']]);
        $plugin->setApplication(Factory::$application);

        $article = (object) ['text' => '{pdfviewer file=/a.pdf}'];
        $plugin->onContentPrepare(new Event('onContentPrepare', ['com_content.article', $article, null, 0]));

        $this->assertStringContainsString('<iframe', $article->text);
    }
}
