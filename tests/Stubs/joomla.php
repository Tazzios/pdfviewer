<?php

/**
 * Minimal stand-ins for the Joomla CMS classes the plugins use.
 *
 * The real Joomla Framework packages (joomla/input, joomla/filter,
 * joomla/registry) are installed through Composer, so input filtering and
 * parameters behave exactly as on a live site. Only the CMS classes, which
 * are not available as standalone packages, are replaced here.
 */

namespace Joomla\CMS\Plugin {
    use Joomla\Registry\Registry;

    class CMSPlugin
    {
        public $params;
        protected $_type = '';
        protected $_name = '';

        private $application;

        public function __construct($subject = null, array $config = [])
        {
            // Joomla 6 style: only the config array
            if (\is_array($subject)) {
                $config = $subject;
            }

            $this->params = new Registry($config['params'] ?? []);
            $this->_type  = $config['type'] ?? '';
            $this->_name  = $config['name'] ?? '';
        }

        public function setApplication($application): void
        {
            $this->application = $application;
        }

        public function getApplication()
        {
            return $this->application;
        }
    }

    class PluginHelper
    {
        /** @var array<string, object> keyed by "type/name" */
        public static array $plugins = [];
        public static string $layoutPath = '';

        public static function getPlugin($type, $plugin = null)
        {
            return static::$plugins[$type . '/' . $plugin] ?? false;
        }

        public static function getLayoutPath($type, $name, $layout = 'default')
        {
            return static::$layoutPath;
        }
    }
}

namespace Joomla\Database {
    interface DatabaseAwareInterface
    {
        public function setDatabase($db): void;
    }

    trait DatabaseAwareTrait
    {
        private $databaseAwareTraitDatabase;

        protected function getDatabase()
        {
            return $this->databaseAwareTraitDatabase;
        }

        public function setDatabase($db): void
        {
            $this->databaseAwareTraitDatabase = $db;
        }
    }

    final class ParameterType
    {
        public const INTEGER = 'int';
        public const STRING = 'string';
    }
}

namespace Joomla\CMS\Editor\Button {
    class Button
    {
        public function __construct(public string $name, public array $props = [], public array $options = [])
        {
        }

        public function get(string $name, $default = null)
        {
            return $this->props[$name] ?? $default;
        }

        public function getOptions(): array
        {
            return $this->options;
        }
    }
}

namespace Joomla\CMS\Object {
    #[\AllowDynamicProperties]
    class CMSObject
    {
        public function set($property, $value = null)
        {
            $this->$property = $value;
        }

        public function get($property, $default = null)
        {
            return $this->$property ?? $default;
        }
    }
}

namespace Joomla\CMS\Language {
    /**
     * Translates with the en-GB language files of both plugins, so a key
     * that is missing from a language file shows up as the bare key.
     */
    class Text
    {
        private static ?array $strings = null;

        public static function _($string)
        {
            if (static::$strings === null) {
                static::$strings = [];

                foreach (glob(\dirname(__DIR__, 2) . '/*/language/en-GB/*.ini') as $file) {
                    static::$strings += parse_ini_file($file, false, INI_SCANNER_RAW);
                }
            }

            return static::$strings[strtoupper($string)] ?? $string;
        }

        public static function sprintf($string, ...$args)
        {
            return sprintf(static::_($string), ...$args);
        }
    }
}

namespace Joomla\CMS\Session {
    class Session
    {
        public static string $token = 'abc123token';

        public static function getFormToken($forceNew = false)
        {
            return static::$token;
        }

        public static function checkToken($method = 'post')
        {
            return (bool) \Joomla\CMS\Factory::getApplication()->getInput()->get(static::$token, '', 'alnum');
        }
    }
}

namespace Joomla\CMS\Uri {
    class Uri
    {
        public static string $base = 'https://example.org/';
        public static string $rootPath = '';

        public static function base($pathonly = false)
        {
            return $pathonly ? rtrim(static::$rootPath, '/') : static::$base;
        }

        public static function root($pathonly = false, $path = null)
        {
            return $pathonly ? static::$rootPath : static::$base;
        }
    }
}

namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        public static array $calls = [];

        public static function _($key, ...$args)
        {
            static::$calls[] = $key;

            return null;
        }
    }
}

namespace Joomla\CMS\Component {
    use Joomla\Registry\Registry;

    class ComponentHelper
    {
        public static array $params = [];

        public static function getParams($option)
        {
            return new Registry(static::$params[$option] ?? []);
        }
    }
}

namespace Joomla\CMS {
    use Joomla\Input\Input;

    class TestApplication
    {
        public Input $input;
        public string $template = 'cassiopeia';
        public array $messages = [];
        public ?TestUser $identity = null;
        public array $userState = [];
        public TestDocument $document;

        public function __construct(array $request = [])
        {
            $this->input    = new Input($request);
            $this->document = new TestDocument();
        }

        public function getIdentity()
        {
            return $this->identity;
        }

        public function getUserState($key, $default = null)
        {
            return $this->userState[$key] ?? $default;
        }

        public function getDocument()
        {
            return $this->document;
        }

        public function getInput(): Input
        {
            return $this->input;
        }

        public function getTemplate($params = false)
        {
            return $this->template;
        }

        public function get($name, $default = null)
        {
            return $default;
        }

        public function enqueueMessage($msg, $type = 'message')
        {
            $this->messages[] = [$msg, $type];
        }
    }

    class TestUser
    {
        public int $id = 0;
        public int $guest = 1;
        /** @var string[] actions on com_content the user may do */
        public array $allowed = [];
        public array $categories = [];

        public static function editor(array $allowed = ['core.create']): self
        {
            $user          = new self();
            $user->id      = 42;
            $user->guest   = 0;
            $user->allowed = $allowed;

            return $user;
        }

        public function authorise($action, $asset = null)
        {
            return \in_array($action, $this->allowed, true);
        }

        public function getAuthorisedCategories($component, $action)
        {
            return $this->categories;
        }
    }

    class TestDocument
    {
        public array $scripts = [];
        public array $styles = [];

        public function getWebAssetManager()
        {
            return $this;
        }

        public function registerAndUseScript($name, $uri = '', $options = [], $attributes = [], $dependencies = [])
        {
            $this->scripts[$name] = $uri;

            return $this;
        }

        public function registerAndUseStyle($name, $uri = '', $options = [], $attributes = [], $dependencies = [])
        {
            $this->styles[$name] = $uri;

            return $this;
        }
    }

    class TestQuery
    {
        private array $parts = [];

        public function __call($method, $args)
        {
            $this->parts[] = strtoupper($method) . ' ' . implode(', ', array_map(
                static fn ($arg) => \is_array($arg) ? implode(', ', $arg) : (string) $arg,
                \array_slice($args, 0, $method === 'bind' ? 2 : 1)
            ));

            return $this;
        }

        public function __toString(): string
        {
            return implode(' ', $this->parts);
        }
    }

    class TestDatabase
    {
        public array $queries = [];
        public array $result = [];

        public function getQuery($new = false)
        {
            return new TestQuery();
        }

        public function createQuery()
        {
            return new TestQuery();
        }

        public function quoteName($name)
        {
            return \is_array($name) ? array_map(fn ($n) => '`' . $n . '`', $name) : '`' . $name . '`';
        }

        public function setQuery($query)
        {
            $this->queries[] = (string) $query;

            return $this;
        }

        public function loadAssocList()
        {
            return $this->result;
        }
    }

    class TestContainer
    {
        public function __construct(private TestDatabase $db)
        {
        }

        public function get($id)
        {
            return $this->db;
        }
    }

    class Factory
    {
        public static ?TestApplication $application = null;
        public static ?TestDatabase $database = null;

        public static function getApplication()
        {
            return static::$application ??= new TestApplication();
        }

        public static function getContainer()
        {
            return new TestContainer(static::$database ??= new TestDatabase());
        }

        public static function getDbo()
        {
            return static::$database ??= new TestDatabase();
        }
    }
}
