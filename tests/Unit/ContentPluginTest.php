<?php

namespace Tazzios\Pdfviewer\Tests\Unit;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\TestApplication;
use Joomla\CMS\TestDatabase;
use Joomla\CMS\Uri\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PlgContentPdfviewer;

/**
 * Tests for the {pdfviewer ...} content plugin.
 */
final class ContentPluginTest extends TestCase
{
    private const DEFAULT_PARAMS = [
        'viewer'      => 'pdfjs',
        'style'       => 'Embed',
        'linktext'    => 'Download',
        'embedwidth'  => '100%',
        'embedheight' => '800',
        'popupwidth'  => '800',
        'popupheight' => '800',
        'debug'       => '0',
    ];

    protected function setUp(): void
    {
        Factory::$application = new TestApplication();
        Factory::$database    = new TestDatabase();
        Uri::$base            = 'https://example.org/';
        Uri::$rootPath        = '';
        ComponentHelper::$params = [];
    }

    /**
     * Runs the plugin over a piece of article text and returns the result.
     */
    private function render(string $text, array $request = [], array $params = [], string $context = 'com_content.article'): string
    {
        Factory::$application = new TestApplication($request);

        $plugin  = new PlgContentPdfviewer(null, ['params' => array_merge(self::DEFAULT_PARAMS, $params)]);
        $article = (object) ['text' => $text];
        $pparams = null;

        $plugin->onContentPrepare($context, $article, $pparams, 0);

        return $article->text;
    }

    /**
     * Returns the decoded src attribute of the first iframe or the href of the first link.
     */
    private function viewerUrl(string $html): string
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');

        foreach ($doc->getElementsByTagName('iframe') as $iframe) {
            return $iframe->getAttribute('src');
        }

        foreach ($doc->getElementsByTagName('a') as $link) {
            if ($link->hasAttribute('href')) {
                return $link->getAttribute('href');
            }
        }

        $this->fail('No viewer url found in: ' . $html);
    }

    /**
     * Returns every attribute name used on any element, to detect injected event handlers.
     */
    private function attributeNames(string $html): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
        $names = [];

        foreach ($doc->getElementsByTagName('*') as $element) {
            foreach ($element->attributes as $attribute) {
                $names[] = strtolower($attribute->name);
            }
        }

        return $names;
    }

    private function assertNoEventHandlers(string $html): void
    {
        $handlers = array_filter($this->attributeNames($html), static fn ($name) => str_starts_with($name, 'on'));
        $this->assertSame([], array_values($handlers), 'Output contains an injected event handler: ' . $html);
        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
    }

    public function testTextWithoutTagIsUntouched(): void
    {
        $text = '<p>Just an article about pdf files.</p>';

        $this->assertSame($text, $this->render($text));
    }

    public function testSmartSearchIndexerIsSkipped(): void
    {
        $text = '<p>{pdfviewer file=/docs/a.pdf}</p>';

        $this->assertSame($text, $this->render($text, [], [], 'com_finder.indexer'));
    }

    public function testEmbedsRelativeFileWithDefaults(): void
    {
        $html = $this->render('<p>{pdfviewer file=/docs/manual.pdf }</p>');

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringNotContainsString('{pdfviewer', $html);
        $this->assertSame(
            'https://example.org/plugins/content/pdfviewer/assets/pdfjs/web/viewer.html?file=/docs/manual.pdf#pagemode=none',
            $this->viewerUrl($html)
        );
        $this->assertStringContainsString('width:100%;', $html);
        $this->assertStringContainsString('height:800px;', $html);
    }

    public function testRelativeFileGetsSubfolderOfTheSite(): void
    {
        Uri::$rootPath = '/test';

        $html = $this->render('{pdfviewer file=/docs/manual.pdf}');

        $this->assertStringContainsString('?file=/test/docs/manual.pdf', $this->viewerUrl($html));
    }

    public function testAbsoluteUrlIsKept(): void
    {
        $html = $this->render('{pdfviewer file=https://cdn.example.com/a.pdf}');

        $this->assertStringContainsString('?file=https://cdn.example.com/a.pdf', $this->viewerUrl($html));
    }

    public function testFileNameWithSpacesAndBrackets(): void
    {
        $html = $this->render('{pdfviewer file="/docs/my file (1)+2.pdf"}');

        $this->assertStringContainsString('?file=/docs/my%20file%20%281%29%2B2.pdf', $this->viewerUrl($html));
    }

    public function testEveryTagIsReplaced(): void
    {
        $html = $this->render('{pdfviewer file=/a.pdf} text { pdfviewer file=/b.pdf }');

        $this->assertSame(2, substr_count($html, '<iframe'));
        $this->assertStringContainsString('file=/a.pdf', $html);
        $this->assertStringContainsString('file=/b.pdf', $html);
    }

    public function testParametersAreCaseInsensitiveAndAllowSpaceBeforeEquals(): void
    {
        $html = $this->render('{pdfviewer FILE =/a.pdf PAGE=3 }');

        $this->assertStringEndsWith('?file=/a.pdf#page=3&pagemode=none', $this->viewerUrl($html));
    }

    public function testNonPdfFilenameShowsNothing(): void
    {
        $this->assertSame('<p></p>', $this->render('<p>{pdfviewer file=/a.docx filename="report.docx"}</p>'));
    }

    public function testShowPdfPreviewNoShowsNothing(): void
    {
        $this->assertSame('', $this->render('{pdfviewer file=/a.pdf showpdfpreview=No}'));
    }

    public function testJdownloadsNotInstalledMessage(): void
    {
        $this->assertSame('jdownloads is not installed', $this->render('{pdfviewer jdownloadsid=4}'));
    }

    #[DataProvider('pageReferenceProvider')]
    public function testPageReferenceFromTag(string $tag, string $expectedEnd): void
    {
        $this->assertStringEndsWith($expectedEnd, $this->viewerUrl($this->render($tag)));
    }

    public static function pageReferenceProvider(): array
    {
        return [
            'page'             => ['{pdfviewer file=/a.pdf page=5}', '#page=5&pagemode=none'],
            'page zero'        => ['{pdfviewer file=/a.pdf page=0}', '#pagemode=none'],
            'search'           => ['{pdfviewer file=/a.pdf search="foo bar"}', '#search=foo%20bar&pagemode=none'],
            'search phrase'    => ['{pdfviewer file=/a.pdf search="foo bar" phrase=true}', '#search=foo%20bar&phrase=true&pagemode=none'],
            'search over page' => ['{pdfviewer file=/a.pdf search=foo page=5}', '#search=foo&pagemode=none'],
            'nameddest'        => ['{pdfviewer file=/a.pdf nameddest=chapter2}', '#nameddest=chapter2&pagemode=none'],
            'zoom'             => ['{pdfviewer file=/a.pdf zoom=page-width}', '#zoom=page-width&pagemode=none'],
            'pagemode'         => ['{pdfviewer file=/a.pdf pagemode=bookmarks}', '#pagemode=bookmarks'],
            'page zoom mode'   => ['{pdfviewer file=/a.pdf page=2 zoom=page-fit pagemode=thumbs}', '#page=2&zoom=page-fit&pagemode=thumbs'],
        ];
    }

    #[DataProvider('urlPriorityProvider')]
    public function testUrlParametersTakePriority(array $request, string $expectedEnd): void
    {
        $html = $this->render('{pdfviewer file=/a.pdf search=tagsearch page=9}', $request);

        $this->assertStringEndsWith($expectedEnd, $this->viewerUrl($html));
    }

    public static function urlPriorityProvider(): array
    {
        return [
            'no url params'     => [[], '#search=tagsearch&pagemode=none'],
            'url page'          => [['page' => '4'], '#page=4&pagemode=none'],
            'url search'        => [['search' => 'hello', 'page' => '4'], '#search=hello&pagemode=none'],
            'url nameddest'     => [['nameddest' => 'intro', 'page' => '4'], '#nameddest=intro&pagemode=none'],
            'smart search wins' => [['highlight' => base64_encode('["alpha","beta"]'), 'search' => 'hello'], '#search=alpha%20beta&pagemode=none'],
            'url phrase'        => [['search' => 'a b', 'phrase' => 'true'], '#search=a%20b&phrase=true&pagemode=none'],
            'url zoom'          => [['zoom' => 'page-height'], '#search=tagsearch&zoom=page-height&pagemode=none'],
        ];
    }

    public function testPopupStyle(): void
    {
        $html = $this->render('{pdfviewer file=/a.pdf style=popup linktext="Open the manual"}');

        $this->assertStringContainsString('data-bs-toggle="modal"', $html);
        $this->assertStringContainsString('Open the manual', $html);
        $this->assertStringContainsString('height:800px;width:800px;', $html);
        $this->assertStringContainsString('viewer.html?file=/a.pdf', $this->viewerUrl($html));
    }

    public function testPopupIdsAreUniqueOnOnePage(): void
    {
        $html = $this->render(str_repeat('{pdfviewer file=/a.pdf style=popup}', 5));

        preg_match_all('/id="(pdfModal[^"]+)"/', $html, $ids);
        $this->assertCount(5, $ids[1]);
        $this->assertCount(5, array_unique($ids[1]));

        preg_match_all('/data-bs-target="#([^"]+)"/', $html, $targets);
        $this->assertSame($ids[1], $targets[1]);
    }

    public function testNewWindowStyle(): void
    {
        $html = $this->render('{pdfviewer file=/a.pdf style=new}');

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('>Download</a>', $html);
    }

    public function testDefaultStyleFromPluginSettings(): void
    {
        $html = $this->render('{pdfviewer file=/a.pdf}', [], ['style' => 'New', 'linktext' => 'Bekijk']);

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('>Bekijk</a>', $html);
    }

    public function testCustomSize(): void
    {
        $html = $this->render('{pdfviewer file=/a.pdf width=80% height=500}');

        $this->assertStringContainsString('width:80%;height:500px;', $html);

        $html = $this->render('{pdfviewer file=/a.pdf width=640 height=480}');

        $this->assertStringContainsString('width:640px;height:480px;', $html);
    }

    public function testTemplateOverrideOfViewer(): void
    {
        $dir = JPATH_ROOT . '/templates/cassiopeia/html/plg_content_pdfviewer/assets/pdfjs/web';
        @mkdir($dir, 0777, true);
        touch($dir . '/viewer.html');

        try {
            $html = $this->render('{pdfviewer file=/a.pdf}');
            $this->assertStringStartsWith(
                'https://example.org/templates/cassiopeia/html/plg_content_pdfviewer/assets/pdfjs/web/viewer.html?file=',
                $this->viewerUrl($html)
            );
        } finally {
            unlink($dir . '/viewer.html');
        }
    }

    public function testPdfImageNeedsJdownloads(): void
    {
        $this->assertSame(
            'Only jdownloads PDF files can be converted to image',
            $this->render('{pdfviewer file=/a.pdf viewer=pdfimage}')
        );
    }

    // ---------------------------------------------------------------------
    // Security: visitors control the url parameters, so nothing from the
    // request may break out of the HTML attribute it is placed in.
    // ---------------------------------------------------------------------

    #[DataProvider('maliciousRequestProvider')]
    public function testUrlParametersCannotInjectHtml(array $request): void
    {
        foreach (['embed', 'popup', 'new'] as $style) {
            $html = $this->render('{pdfviewer file=/a.pdf style=' . $style . '}', $request);

            $this->assertNoEventHandlers($html);
            $this->assertStringContainsString('viewer.html?file=/a.pdf', $this->viewerUrl($html));
        }
    }

    public static function maliciousRequestProvider(): array
    {
        $payload = '" onload="alert(1)" x="';

        return [
            'search'    => [['search' => $payload]],
            'nameddest' => [['nameddest' => $payload]],
            'zoom'      => [['zoom' => $payload]],
            'pagemode'  => [['pagemode' => $payload]],
            'phrase'    => [['search' => 'a', 'phrase' => $payload]],
            'highlight' => [['highlight' => base64_encode('["' . $payload . '"]')]],
            'highlight raw' => [['highlight' => base64_encode('x" onmouseover="alert(1)')]],
        ];
    }

    public function testUnknownZoomAndPagemodeFromUrlAreIgnored(): void
    {
        $html = $this->render('{pdfviewer file=/a.pdf}', ['zoom' => 'bogus', 'pagemode' => 'bogus']);

        $this->assertStringEndsWith('?file=/a.pdf#pagemode=none', $this->viewerUrl($html));
    }

    public function testNumericZoomIsAllowed(): void
    {
        $html = $this->render('{pdfviewer file=/a.pdf}', ['zoom' => '150']);

        $this->assertStringEndsWith('#zoom=150&pagemode=none', $this->viewerUrl($html));
    }

    public function testLinktextAndSizeFromTagAreEscaped(): void
    {
        $html = $this->render('{pdfviewer file=/a.pdf style=popup linktext="a&quot;b" width="1px;background:url(x)"}');

        $this->assertNoEventHandlers($html);
        $this->assertStringNotContainsString('background:url', $html);
    }

    public function testJdownloadsIdIsCastToInteger(): void
    {
        @mkdir(JPATH_ADMINISTRATOR . '/components/com_jdownloads', 0777, true);

        try {
            $html = $this->render('{pdfviewer jdownloadsid="4 OR 1"}');
            $this->assertStringContainsString(
                'file=https://example.org/index.php%3Foption%3Dcom_jdownloads%26task%3Ddownload.send%26id%3D4#',
                $this->viewerUrl($html)
            );
        } finally {
            rmdir(JPATH_ADMINISTRATOR . '/components/com_jdownloads');
        }
    }

    public function testPdfImageQueryUsesIntegerId(): void
    {
        if (extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick is installed; this test covers the missing extension path.');
        }

        @mkdir(JPATH_ADMINISTRATOR . '/components/com_jdownloads', 0777, true);

        try {
            $html = $this->render('{pdfviewer jdownloadsid="4 OR 1" viewer=pdfimage}');
            $this->assertStringContainsString('Imagick', $html);
            $this->assertStringNotContainsString('OR', implode("\n", Factory::$database->queries));
        } finally {
            rmdir(JPATH_ADMINISTRATOR . '/components/com_jdownloads');
        }
    }
}
