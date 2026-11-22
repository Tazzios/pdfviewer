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

        public function __construct($subject = null, array $config = [])
        {
            $this->params = new Registry($config['params'] ?? []);
            $this->_type  = $config['type'] ?? '';
            $this->_name  = $config['name'] ?? '';
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

        public function __construct(array $request = [])
        {
            $this->input = new Input($request);
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

    class TestDatabase
    {
        public array $queries = [];
        public array $result = [];

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
