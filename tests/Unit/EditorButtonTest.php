<?php

namespace Tazzios\Pdfviewer\Tests\Unit;

use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Factory;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\TestApplication;
use Joomla\CMS\TestDatabase;
use Joomla\CMS\TestUser;
use Joomla\Event\Event;
use PHPUnit\Framework\TestCase;
use Tazzios\Plugin\EditorsXtd\Pdfviewer\Extension\Pdfviewer;

/**
 * Event like Joomla 5's EditorButtonsSetupEvent.
 */
final class ButtonsSetupEvent extends Event
{
    public array $added = [];

    public function __construct(private string $editorId, private array $disabled = [])
    {
        parent::__construct('onEditorButtonsSetup');
    }

    public function getEditorId(): string
    {
        return $this->editorId;
    }

    public function getDisabledButtons(): array
    {
        return $this->disabled;
    }

    public function getButtonsRegistry(): self
    {
        return $this;
    }

    public function add($button): void
    {
        $this->added[] = $button;
    }
}

/**
 * Event like Joomla 5's AjaxEvent, which collects results with addResult().
 */
final class AjaxResultEvent extends Event
{
    public array $results = [];

    public function addResult($data): void
    {
        $this->results[] = $data;
    }
}

/**
 * Tests for the editors-xtd button.
 */
final class EditorButtonTest extends TestCase
{
    private const JD_DIR = JPATH_ADMINISTRATOR . '/components/com_jdownloads';

    protected function setUp(): void
    {
        Factory::$application     = new TestApplication();
        Factory::$database        = new TestDatabase();
        PluginHelper::$plugins    = [];
        PluginHelper::$layoutPath = dirname(__DIR__, 2) . '/editor_button/tmpl/default.php';
    }

    protected function tearDown(): void
    {
        if (is_dir(self::JD_DIR)) {
            rmdir(self::JD_DIR);
        }
    }

    private function plugin(?TestUser $user, array $request = [], array $userState = [], array $params = []): Pdfviewer
    {
        $app            = new TestApplication($request);
        $app->identity  = $user;
        $app->userState = $userState;

        Factory::$application = $app;

        $plugin = new Pdfviewer(null, ['type' => 'editors-xtd', 'name' => 'pdfviewer', 'params' => $params]);
        $plugin->setApplication($app);

        return $plugin;
    }

    private function validRequest(): array
    {
        return [Session::$token => '1', 'e_name' => 'jform_articletext'];
    }

    public function testSubscribesToButtonAndAjaxEvents(): void
    {
        $events = Pdfviewer::getSubscribedEvents();

        $this->assertSame('onEditorButtonsSetup', $events['onEditorButtonsSetup']);
        $this->assertSame('onAjaxPdfviewer', $events['onAjaxPdfviewer']);
    }

    public function testButtonForEditor(): void
    {
        $button = $this->plugin(TestUser::editor())->onDisplay('jform_articletext');

        $this->assertInstanceOf(Button::class, $button);
        $this->assertSame('pdfviewer', $button->name);
        $this->assertSame('modal', $button->get('action'));
        $this->assertSame('editors-xtd_pdfviewer', $button->get('name'));
        $this->assertSame('85vh', $button->getOptions()['height']);

        $link = $button->get('link');
        $this->assertStringStartsWith('index.php?option=com_ajax&plugin=pdfviewer&group=editors-xtd&format=html&tmpl=component&', $link);
        $this->assertStringContainsString('&' . Session::$token . '=1', $link);
        $this->assertStringEndsWith('&e_name=jform_articletext', $link);
        $this->assertStringNotContainsString('&amp;', $link);
    }

    public function testEditorIdIsUrlEncodedInLink(): void
    {
        $button = $this->plugin(TestUser::editor())->onDisplay('a b&c');

        $this->assertStringEndsWith('&e_name=a%20b%26c', $button->get('link'));
    }

    public function testNoButtonForGuest(): void
    {
        $this->assertNull($this->plugin(new TestUser())->onDisplay('jform_articletext'));
        $this->assertNull($this->plugin(null)->onDisplay('jform_articletext'));
    }

    public function testNoButtonForUserWithoutArticleRights(): void
    {
        $this->assertNull($this->plugin(TestUser::editor([]))->onDisplay('jform_articletext'));
    }

    public function testButtonForUserWhoMayCreateInOneCategory(): void
    {
        $user             = TestUser::editor([]);
        $user->categories = [8];

        $this->assertNotNull($this->plugin($user)->onDisplay('jform_articletext'));
    }

    public function testButtonForUserWhoIsEditingAnArticle(): void
    {
        $plugin = $this->plugin(TestUser::editor([]), [], ['com_content.edit.article.id' => [12]]);

        $this->assertNotNull($plugin->onDisplay('jform_articletext'));
    }

    public function testButtonForUserWhoMayEditOwnArticles(): void
    {
        $this->assertNotNull($this->plugin(TestUser::editor(['core.edit.own']))->onDisplay('jform_articletext'));
    }

    public function testButtonsSetupEventAddsButton(): void
    {
        $event = new ButtonsSetupEvent('jform_articletext');

        $this->plugin(TestUser::editor())->onEditorButtonsSetup($event);

        $this->assertCount(1, $event->added);
        $this->assertInstanceOf(Button::class, $event->added[0]);
    }

    public function testDisabledButtonIsNotAdded(): void
    {
        $event = new ButtonsSetupEvent('jform_articletext', ['pdfviewer']);

        $this->plugin(TestUser::editor())->onEditorButtonsSetup($event);

        $this->assertSame([], $event->added);
    }

    public function testButtonsSetupEventWithoutRights(): void
    {
        $event = new ButtonsSetupEvent('jform_articletext');

        $this->plugin(new TestUser())->onEditorButtonsSetup($event);

        $this->assertSame([], $event->added);
    }

    public function testJoomla4ButtonIsCmsObjectWithOptionsArray(): void
    {
        $plugin = $this->plugin(TestUser::editor());
        $button = $plugin->onDisplay('jform_articletext');
        $legacy = $plugin->legacyButton($button->props, $button->getOptions());

        $this->assertInstanceOf(CMSObject::class, $legacy);
        $this->assertTrue($legacy->get('modal'));
        $this->assertSame($button->get('link'), $legacy->get('link'));
        $this->assertSame('file', $legacy->get('icon'));
        $this->assertIsArray($legacy->get('options'), 'Joomla 4 ignores options given as a string');
        $this->assertSame('80', $legacy->get('options')['bodyHeight']);
    }

    public function testDialogNeedsToken(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);

        $this->plugin(TestUser::editor(), ['e_name' => 'jform_articletext'])->renderDialog();
    }

    public function testDialogIsRefusedForGuests(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);

        $this->plugin(new TestUser(), $this->validRequest())->renderDialog();
    }

    public function testDialogIsRefusedForUsersWithoutArticleRights(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);

        $this->plugin(TestUser::editor([]), $this->validRequest())->renderDialog();
    }

    public function testGuestNeverSeesJdownloadsTitles(): void
    {
        mkdir(self::JD_DIR, 0777, true);
        Factory::$database->result = [['id' => 1, 'title' => 'Secret report']];

        try {
            $this->plugin(new TestUser(), $this->validRequest())->renderDialog();
            $this->fail('Expected an exception');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('Secret report', $e->getMessage());
        }

        $this->assertSame([], Factory::$database->queries);
    }

    public function testDialogForEditorWithoutJdownloads(): void
    {
        $html = $this->plugin(TestUser::editor(), $this->validRequest())->renderDialog();

        $this->assertStringContainsString('id="pdfviewer-form"', $html);
        $this->assertStringContainsString('data-editor="jform_articletext"', $html);
        $this->assertStringContainsString('jDownloads is not installed.', $html);
        $this->assertMatchesRegularExpression('/id="pdfviewer-source-jd"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/id="pdfviewer-source-file"[^>]*checked/', $html);
        $this->assertStringNotContainsString('behavior.polyfill', $html);
        $this->assertSame(
            'plg_editors-xtd_pdfviewer/pdfviewer.js',
            Factory::$application->document->scripts[Pdfviewer::SCRIPT_ASSET]
        );
    }

    public function testDialogListsJdownloadsFilesEscaped(): void
    {
        mkdir(self::JD_DIR, 0777, true);
        Factory::$database->result = [
            ['id' => '7', 'title' => 'Report <script>alert(1)</script> & "quotes"'],
            ['id' => '3', 'title' => 'Older file'],
        ];

        $html = $this->plugin(TestUser::editor(), $this->validRequest())->renderDialog();

        $this->assertStringContainsString('<option value="7">Report &lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;quotes&quot;</option>', $html);
        $this->assertStringContainsString('<option value="3">Older file</option>', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertMatchesRegularExpression('/id="pdfviewer-source-jd"[^>]*checked/', $html);

        $query = Factory::$database->queries[0];
        $this->assertStringContainsString('`#__jdownloads_files`', $query);
        $this->assertStringContainsString('`published` = 1', $query);
    }

    public function testEditorIdFromRequestIsCleaned(): void
    {
        $request           = $this->validRequest();
        $request['e_name'] = 'jform_articletext"><script>';

        $html = $this->plugin(TestUser::editor(), $request)->renderDialog();

        $this->assertStringContainsString('data-editor="jform_articletextscript"', $html);
    }

    /**
     * Decodes the settings JSON the dialog gives to the script.
     */
    private function dialogSettings(string $html): array
    {
        $this->assertMatchesRegularExpression('/data-settings="([^"]*)"/', $html);
        preg_match('/data-settings="([^"]*)"/', $html, $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true);
    }

    public function testButtonDefaultsAreShownInDialog(): void
    {
        $params = ['viewer' => 'pdfimage', 'style' => 'popup', 'popupwidth' => '90%', 'popupheight' => '600'];

        $html     = $this->plugin(TestUser::editor(), $this->validRequest(), [], $params)->renderDialog();
        $settings = $this->dialogSettings($html);

        $this->assertStringContainsString('default (pdfimage)', $html);
        $this->assertStringContainsString('default (popup)', $html);
        $this->assertSame('pdfimage', $settings['button']['viewer']);
        $this->assertSame('popup', $settings['button']['style']);
        $this->assertSame(['width' => '90%', 'height' => '600'], $settings['button']['sizes']['popup']);
        $this->assertSame(['width' => '100%', 'height' => '800'], $settings['button']['sizes']['embed']);
    }

    public function testContentPluginSettingsAreGivenToTheScript(): void
    {
        PluginHelper::$plugins['content/pdfviewer'] = (object) [
            'params' => '{"viewer":"pdfimage","style":"Popup","embedheight":"500","popupwidth":"70%"}',
        ];

        $settings = $this->dialogSettings($this->plugin(TestUser::editor(), $this->validRequest())->renderDialog());

        $this->assertSame('pdfimage', $settings['content']['viewer']);
        $this->assertSame('popup', $settings['content']['style'], 'the content plugin stores Popup with a capital');
        $this->assertSame('500', $settings['content']['sizes']['embed']['height']);
        $this->assertSame('70%', $settings['content']['sizes']['popup']['width']);
    }

    public function testEmptyButtonSettingsFallBackToContentPlugin(): void
    {
        PluginHelper::$plugins['content/pdfviewer'] = (object) ['params' => '{"style":"New","embedwidth":"640"}'];

        $defaults = $this->plugin(TestUser::editor(), [], [], ['style' => '', 'embedwidth' => ''])->getButtonDefaults();

        $this->assertSame('new', $defaults['style']);
        $this->assertSame('640', $defaults['sizes']['embed']['width']);
    }

    public function testInvalidSettingsFallBack(): void
    {
        PluginHelper::$plugins['content/pdfviewer'] = (object) ['params' => '{"viewer":"evil\\"","style":"x","embedwidth":"1px;x"}'];

        $defaults = $this->plugin(TestUser::editor())->getContentDefaults();

        $this->assertSame('pdfjs', $defaults['viewer']);
        $this->assertSame('embed', $defaults['style']);
        $this->assertSame('100%', $defaults['sizes']['embed']['width']);
    }

    public function testSettingsJsonCannotBreakTheAttribute(): void
    {
        $html = $this->plugin(TestUser::editor(), $this->validRequest(), [], ['embedwidth' => '"><script>'])->renderDialog();

        $this->assertStringNotContainsString('"><script>', $html);
        $this->assertSame('100%', $this->dialogSettings($html)['button']['sizes']['embed']['width']);
    }

    public function testManifestHasButtonSettings(): void
    {
        $manifest = simplexml_load_file(dirname(__DIR__, 2) . '/editor_button/pdfviewer.xml');
        $names    = array_map('strval', $manifest->xpath('//config//field/@name'));

        foreach (['viewer', 'style', 'embedwidth', 'embedheight', 'popupwidth', 'popupheight'] as $name) {
            $this->assertContains($name, $names);
        }
    }

    public function testAjaxEventJoomla5AddsResult(): void
    {
        $event = new AjaxResultEvent('onAjaxPdfviewer');

        $this->plugin(TestUser::editor(), $this->validRequest())->onAjaxPdfviewer($event);

        $this->assertCount(1, $event->results);
        $this->assertStringContainsString('id="pdfviewer-form"', $event->results[0]);
    }

    public function testAjaxEventJoomla4SetsResultArgument(): void
    {
        $event = new Event('onAjaxPdfviewer', ['result' => ['earlier']]);

        $this->plugin(TestUser::editor(), $this->validRequest())->onAjaxPdfviewer($event);

        $result = $event->getArgument('result');
        $this->assertSame('earlier', $result[0]);
        $this->assertStringContainsString('id="pdfviewer-form"', $result[1]);
    }

    public function testServiceProviderAndManifestMatch(): void
    {
        $root     = dirname(__DIR__, 2) . '/editor_button';
        $manifest = simplexml_load_file($root . '/pdfviewer.xml');

        $this->assertSame('Tazzios\Plugin\EditorsXtd\Pdfviewer', (string) $manifest->namespace);
        $this->assertSame('src', (string) $manifest->namespace['path']);
        $this->assertSame('services', (string) $manifest->files->xpath('folder[@plugin="pdfviewer"]')[0]);
        $this->assertFileExists($root . '/services/provider.php');
        $this->assertFileExists($root . '/media/js/pdfviewer.js');
        $this->assertFileDoesNotExist($root . '/pdfviewer.php', 'old Joomla 3 entry file must be gone');

        $provider = file_get_contents($root . '/services/provider.php');
        $this->assertStringContainsString('use Tazzios\Plugin\EditorsXtd\Pdfviewer\Extension\Pdfviewer;', $provider);
    }

    public function testNoJoomla3ApiIsUsed(): void
    {
        $root = dirname(__DIR__, 2) . '/editor_button';
        $code = '';

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (in_array($file->getExtension(), ['php', 'js'], true)) {
                $code .= file_get_contents($file->getPathname());
            }
        }

        foreach (['JPlugin', 'JFactory', 'JObject', 'JSession', 'JPluginHelper', 'behavior.polyfill', 'auto-fit'] as $old) {
            $this->assertDoesNotMatchRegularExpression('/\b' . preg_quote($old, '/') . '\b/', $code, $old . ' does not exist in Joomla 4+');
        }
    }
}
