<?php

namespace atk4\ui;

class App
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }
    
    use \atk4\core\HookTrait;
    use \atk4\core\DynamicMethodTrait;
    use \atk4\core\FactoryTrait;
    use \atk4\core\AppScopeTrait;
    use \atk4\core\DIContainerTrait;
    
    // @var array|false Location where to load JS/CSS files
    public $cdn = [
        'atk'              => 'https://cdn.rawgit.com/atk4/ui/1.6.4/public',
        'jquery'           => 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1',
        'serialize-object' => 'https://cdnjs.cloudflare.com/ajax/libs/jquery-serialize-object/2.5.0',
        'semantic-ui'      => 'https://cdn.jsdelivr.net/npm/fomantic-ui@2.6.4/dist',
    ];
    
    // @var string Version of Agile UI
    public $version = '1.6.4';

    // @var string Name of application
    public $title = 'Agile UI - Untitled Application';
    
    // @var Layout\Generic
    public $layout = null; // the top-most view object
    
    /**
     * Set one or more directories where templates should reside.
     *
     * @var string|array
     */
    public $template_dir = null;
    
    // @var string Name of skin
    public $skin = 'semantic-ui';
    
    /**
     * Will replace an exception handler with our own, that will output errors nicely.
     *
     * @var bool
     */
    public $catch_exceptions = true;
    
    /**
     * Will display error if callback wasn't triggered.
     *
     * @var bool
     */
    public $catch_runaway_callbacks = true;
    
    /**
     * Will always run application even if developer didn't explicitly executed run();.
     *
     * @var bool
     */
    public $always_run = true;
    
    /**
     * Will be set to true after app->run() is called, which may be done automatically
     * on exit.
     *
     * @var bool
     */
    public $run_called = false;
    
    /**
     * Will be set to true, when exit is called. Sometimes exit is intercepted by shutdown
     * handler and we don't want to execute 'beforeExit' multiple times.
     *
     * @var bool
     */
    public $exit_called = false;
    
    // @var bool
    public $_cwd_restore = true;
    
    /**
     * function setModel(MyModel $m);.
     *
     * is considered 'WARNING' even though MyModel descends from the parent class. This
     * is not an incompatible class. We want to write clean PHP code and therefore this
     * warning is disabled by default until it's fixed correctly in PHP.
     *
     * See: http://stackoverflow.com/a/42840762/204819
     *
     * @var bool
     */
    public $fix_incompatible = true;
    
    // @var bool
    public $is_rendering = false;
    
    // @var Persistence\UI
    public $ui_persistence = null;
    
    /**
     * @var View For internal use
     */
    public $html = null;
    
    /**
     * @var LoggerInterface, target for objects with DebugTrait
     */
    public $logger = null;
    
    // @var \atk4\data\Persistence
    public $db = null;
    
    /**
     * After catch a throwable (Error or Exception)
     * Application will call a new instance of itself with this flag setted to true
     * When this flag is active, you can disable functions
     * like routing or other functionality that will make this go in a loop or break output
     *
     * @var bool
     */
    public $is_catch_throwable = false;
    
    /**
     * Constructor.
     *
     * @param array $defaults
     */
    public function __construct($defaults = [])
    {
        ini_set('display_errors',false);
        
        $this->app = $this;
        
        // Process defaults
        if (is_string($defaults)) {
            $defaults = ['title' => $defaults];
        }
        
        if (isset($defaults[0])) {
            $defaults['title'] = $defaults[0];
            unset($defaults[0]);
        }
        
        /*
        if (is_array($defaults)) {
            throw new Exception(['Constructor requires array argument', 'arg' => $defaults]);
        }*/
        $this->setDefaults($defaults);
        /*

        foreach ($defaults as $key => $val) {
            if (is_array($val)) {
                $this->$key = array_merge(isset($this->$key) && is_array($this->$key) ? $this->$key : [], $val);
            } elseif (!is_null($val)) {
                $this->$key = $val;
            }
        }
         */
        
        // Set up template folder
        if ($this->template_dir === null) {
            $this->template_dir = [];
        } elseif (!is_array($this->template_dir)) {
            $this->template_dir = [$this->template_dir];
        }
        $this->template_dir[] = __DIR__.'/../template/'.$this->skin;
        
        // Set our exception handler
        if ($this->catch_exceptions) {
            set_exception_handler(function ($exception) {
                return $this->caughtThrowable($exception);
            });
        }
        
        if (!$this->_initialized) {
            //$this->init();
        }
        
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            
            if ($this->fix_incompatible) {
                // PHP 7.0 introduces strict checks for method patterns. But only 7.2 introduced parameter type widening
                //
                // https://en.wikipedia.org/wiki/Covariance_and_contravariance_(computer_science)#Contravariant_method_argument_type
                // https://wiki.php.net/rfc/parameter-no-type-variance
                //
                // We wish to start using type-hinting more in our classes, but it would break any extends in 3rd party code unless
                // they are on 7.2.
                
                if (version_compare(PHP_VERSION, '7.0.0') >= 0 && version_compare(PHP_VERSION, '7.2.0') < 0) {
                    return strpos($errstr, 'Declaration of') === 0;
                }
            }
            
            // Practical to solve the issue of continuous looping and no errors out
            // we must have TOTAL control over error_handling
            //
            // this is all the $error_types
            // we can now mute/suppress displaying errors on some of this :
            // DISPLAY ERRORS :
            // - deprecated is ignored
            // - notice is ignored
            // - warning is ignored only when debug trait is not used
            // LOG ERRORS
            // - ANY errors is not ignored when debug trait is used
            // logging will be triggered or in this function or in the catch exeception
            //
            // @TODO need to remove suppression (@) on other files, notice at now must be set to ignore
            //
            // Ignoring means that will output a ugly modal window without any text
            //
            // just test more before go to production and....
            // Obviously 5 minutes later new errors will come up, because we like to lose weekends ;)
            
            $is_debug_active = (isset($this->_debugTrait));
            
            $error_name = '';
            
            $mute_deprecated = true;
            $mute_notice = true;
            $mute_warning = !$is_debug_active;
            
            /**
             * if we ignore some errors, this doesn't mean that will not log it
             *
             * when debug is active, ignored error will be logged
             */
            $log_muted_errors = $is_debug_active;
            
            
            $mute_this_error = false;
            switch ($errno) {
                
                case E_ERROR:
                    $error_name = 'E_ERROR';
                break;    // 1
                
                case E_WARNING:
                    $error_name = 'E_WARNING';
                    $mute_this_error = $mute_warning;
                break;  // 2
                
                case E_PARSE:
                    $error_name = 'E_PARSE';
                break;    // 4
                
                case E_NOTICE:
                    $error_name = 'E_NOTICE';
                    $mute_this_error = $mute_notice;
                break;   // 8
                
                case E_CORE_ERROR:
                    $error_name = 'E_CORE_ERROR';
                break;   // 16
                
                case E_CORE_WARNING:
                    $error_name = 'E_CORE_WARNING';
                    $mute_this_error = $mute_warning;
                break; // 32
                
                case E_COMPILE_ERROR:
                    $error_name = 'E_COMPILE_ERROR';
                break;    // 64
                
                case E_COMPILE_WARNING:
                    $error_name = 'E_COMPILE_WARNING';
                    $mute_this_error = $mute_warning;
                break;  // 128
                
                case E_USER_ERROR:
                    $error_name = 'E_USER_ERROR';
                break;   // 256
                
                case E_USER_WARNING:
                    $error_name = 'E_USER_WARNING';
                    $mute_this_error = $mute_warning;
                break; // 512
                
                case E_USER_NOTICE:
                    $error_name = 'E_USER_NOTICE';
                    $mute_this_error = $mute_notice;
                break;  // 1024
                
                case E_STRICT:
                    $error_name = 'E_STRICT';
                break;   // 2048
                
                case E_RECOVERABLE_ERROR:
                    $error_name = 'E_RECOVERABLE_ERROR';
                break;    // 4096
                
                case E_DEPRECATED:
                    $error_name = 'E_DEPRECATED';
                    $mute_this_error = $mute_deprecated;
                break;   // 8192
                
                case E_USER_DEPRECATED:
                    $error_name = 'E_USER_DEPRECATED';
                    $mute_this_error = $mute_deprecated;
                break;  // 16384
            }
            
            if($mute_this_error)
            {
                if($log_muted_errors)
                {
                    $this->logThrowable(new \ErrorException($error_name .':' . $errstr, $errno, $errno, $errfile, $errline));
                }
            } else {
                throw new \ErrorException($error_name .':' . $errstr, $errno, $errno, $errfile, $errline);
            }
            
            /**
             * @see http://php.net/manual/en/function.set-error-handler.php
             * If the function returns FALSE then the normal error handler continues.
             *
             */
            return true;
        },E_ALL);
        
        // Always run app on shutdown
        if ($this->always_run) {
            
            if ($this->_cwd_restore) {
                $this->_cwd_restore = getcwd();
            }
            
            register_shutdown_function(function () {
                
                if (is_string($this->_cwd_restore)) {
                    chdir($this->_cwd_restore);
                }
                
                $e = error_get_last();
                if(!is_null($e))
                {
                    $error = new \ErrorException($e['message'],$e['type'],E_ERROR,$e['file'],$e['line']);
                    $this->caughtThrowable($error);
                }
                
                if (!$this->run_called) {
                    // try/catch moved to run method to catch direct calls
                    $this->run();
                }
                
                $this->callExit();
            });
        }
        
        // Set up UI persistence
        if (!isset($this->ui_persistence)) {
            $this->ui_persistence = new Persistence\UI();
        }
    }
    
    public function callExit()
    {
        if (!$this->exit_called) {
            $this->exit_called = true;
            $this->hook('beforeExit');
        }
        exit;
    }
    
    /**
     * Most of the ajax request will require sending exception in json
     * instead of html, except for tab.
     *
     * @return bool
     */
    protected function isJsonRequest()
    {
        $ajax = false;
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $ajax = true;
        }
        
        return $ajax && !isset($_GET['__atk_tab']);
    }
    
    /**
     * Outputs debug info.
     *
     * @param string $str
     */
    public function outputDebug($str)
    {
        echo 'DEBUG:'.$str.'<br/>';
    }
    
    /**
     * Will perform a preemptive output and terminate. Do not use this
     * directly, instead call it form Callback, jsCallback or similar
     * other classes.
     *
     * @param string $output
     */
    public function terminate($output = null)
    {
        if ($output !== null) {
            echo $output;
        }
        $this->run_called = true; // prevent shutdown function from triggering.
        $this->callExit();
    }
    
    /**
     * Initializes layout.
     *
     * @param string|Layout\Generic|array $seed
     *
     * @return $this
     */
    public function initLayout($seed)
    {
        $layout = $this->factory($seed, null, 'Layout');
        $layout->app = $this;
        
        if (!$this->html) {
            $this->html = new View(['defaultTemplate' => 'html.html']);
            $this->html->app = $this;
            $this->html->init();
        }
        
        $this->layout = $this->html->add($layout);
        
        $this->initIncludes();
        
        return $this;
    }
    
    /**
     * Initialize JS and CSS includes.
     */
    public function initIncludes()
    {
        // jQuery
        $url = isset($this->cdn['jquery']) ? $this->cdn['jquery'] : '../public';
        $this->requireJS($url.'/jquery.min.js');
        
        // Semantic UI
        $url = isset($this->cdn['semantic-ui']) ? $this->cdn['semantic-ui'] : '../public';
        $this->requireJS($url.'/semantic.min.js');
        $this->requireCSS($url.'/semantic.min.css');
        
        // Serialize Object
        $url = isset($this->cdn['serialize-object']) ? $this->cdn['serialize-object'] : '../public';
        $this->requireJS($url.'/jquery.serialize-object.min.js');
        
        // Agile UI
        $url = isset($this->cdn['atk']) ? $this->cdn['atk'] : '../public';
        $this->requireJS($url.'/atkjs-ui.min.js');
        $this->requireCSS($url.'/agileui.css');
    }
    
    /**
     * Adds a <style> block to the HTML Header. Not escaped. Try to avoid
     * and use file include instead.
     *
     * @param string $style CSS rules, like ".foo { background: red }".
     */
    public function addStyle($style)
    {
        if (!$this->html) {
            throw new Exception(['App does not know how to add style']);
        }
        $this->html->template->appendHTML('HEAD', $this->getTag('style', $style));
    }
    
    /**
     * Normalizes class name.
     *
     * @param string $name
     *
     * @return string
     */
    public function normalizeClassNameApp($name)
    {
        return '\\'.__NAMESPACE__.'\\'.$name;
    }
    
    /**
     * Add a new object into the app. You will need to have Layout first.
     *
     * @param mixed  $seed   New object to add
     * @param string $region
     *
     * @return object
     */
    public function add($seed, $region = null)
    {
        if (!$this->layout) {
            throw new Exception(['If you use $app->add() you should first call $app->setLayout()']);
        }
        
        return $this->layout->add($seed, $region);
    }
    
    /**
     * Runs app and echo rendered template.
     */
    public function run()
    {
        try {
            
            $this->run_called = true;
            $this->hook('beforeRender');
            $this->is_rendering = true;
            
            // if no App layout set
            if (!isset($this->html)) {
                throw new Exception(['App layout should be set.']);
            }
            
            $this->html->template->set('title', $this->title);
            $this->html->renderAll();
            $this->html->template->appendHTML('HEAD', $this->html->getJS());
            $this->is_rendering = false;
            $this->hook('beforeOutput');
            
            if (isset($_GET['__atk_callback']) && $this->catch_runaway_callbacks) {
                $this->terminate('!! Callback requested, but never reached. You may be missing some arguments in '.$_SERVER['REQUEST_URI']);
            }
            
            echo $this->html->template->render();
            
        } catch (\Throwable $e) {
            
            // in PHP 7.0 Error and Exception are throwable
            // catching only Exception, never catch Errors
            
            $this->caughtThrowable($e);
        }
    }
    
    /**
     * Initialize app.
     */
    public function init()
    {
        $this->_init();
    }
    
    /**
     * Load template by template file name.
     *
     * @param string $name
     *
     * @throws Exception
     *
     * @return Template
     */
    public function loadTemplate($name)
    {
        $template = new Template();
        $template->app = $this;
        
        if (in_array($name[0], ['.', '/', '\\']) || strpos($name, ':\\') !== false) {
            return $template->load($name);
        } else {
            $dir = is_array($this->template_dir) ? $this->template_dir : [$this->template_dir];
            foreach ($dir as $td) {
                if ($t = $template->tryLoad($td.'/'.$name)) {
                    return $t;
                }
            }
        }
        
        throw new Exception(['Can not find template file', 'name'=>$name, 'template_dir'=>$this->template_dir]);
    }
    
    /**
     * Connects database.
     *
     * @param string $dsn      Format as PDO DSN or use "mysql://user:pass@host/db;option=blah", leaving user and password arguments = null
     * @param string $user
     * @param string $password
     * @param array  $args
     *
     * @throws \atk4\data\Exception
     * @throws \atk4\dsql\Exception
     *
     * @return \atk4\data\Persistence
     */
    public function dbConnect($dsn, $user = null, $password = null, $args = [])
    {
        return $this->db = $this->add(\atk4\data\Persistence::connect($dsn, $user, $password, $args));
    }
    
    protected function getRequestURI()
    {
        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // IIS
            $request_uri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) { // Apache
            $request_uri = $_SERVER['REQUEST_URI'];
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
            $request_uri = $_SERVER['ORIG_PATH_INFO'];
            // This one comes without QUERY string
        } else {
            $request_uri = '';
        }
        $request_uri = explode('?', $request_uri, 2);
        
        return $request_uri[0];
    }
    
    /**
     * @var null
     */
    public $page = null;
    
    /**
     * Build a URL that application can use for js call-backs. Some framework integration will use a different routing
     * mechanism for NON-HTML response.
     *
     * @param array|string $page           URL as string or array with page name as first element and other GET arguments
     * @param bool         $needRequestUri Simply return $_SERVER['REQUEST_URI'] if needed
     * @param array        $extra_args     Additional URL arguments
     *
     * @return string
     */
    public function jsURL($page = [], $needRequestUri = false, $extra_args = [])
    {
        return $this->url($page, $needRequestUri, $extra_args);
    }
    
    /**
     * Build a URL that application can use for loading HTML data.
     *
     * @param array|string $page           URL as string or array with page name as first element and other GET arguments
     * @param bool         $needRequestUri Simply return $_SERVER['REQUEST_URI'] if needed
     * @param array        $extra_args     Additional URL arguments
     *
     * @return string
     */
    public function url($page = [], $needRequestUri = false, $extra_args = [])
    {
        if ($needRequestUri) {
            return $_SERVER['REQUEST_URI'];
        }
        
        $sticky = $this->sticky_get_arguments;
        $result = $extra_args;
        
        if ($this->page === null) {
            $uri = $this->getRequestURI();
            
            if (substr($uri, -1, 1) == '/') {
                $this->page = 'index';
            } else {
                $this->page = basename($uri, '.php');
            }
        }
        
        // if page passed as string, then simply use it
        if (is_string($page)) {
            return $page;
        }
        
        // use current page by default
        if (!isset($page[0])) {
            $page[0] = $this->page;
        }
        
        //add sticky arguments
        if (is_array($sticky) && !empty($sticky)) {
            foreach ($sticky as $key => $val) {
                if ($val === true) {
                    if (isset($_GET[$key])) {
                        $val = $_GET[$key];
                    } else {
                        continue;
                    }
                }
                if (!isset($result[$key])) {
                    $result[$key] = $val;
                }
            }
        }
        
        // add arguments
        foreach ($page as $arg => $val) {
            if ($arg === 0) {
                continue;
            }
            
            if ($val === null || $val === false) {
                unset($result[$arg]);
            } else {
                $result[$arg] = $val;
            }
        }
        
        // put URL together
        $args = http_build_query($result);
        $url = ($page[0] ? $page[0].'.php' : '').($args ? '?'.$args : '');
        
        return $url;
    }
    
    /**
     * Make current get argument with specified name automatically appended to all generated URLs.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function stickyGet($name)
    {
        if (isset($_GET[$name])) {
            $this->sticky_get_arguments[$name] = $_GET[$name];
            
            return $_GET[$name];
        }
    }
    
    /**
     * @var array global sticky arguments
     */
    protected $sticky_get_arguments = [];
    
    /**
     * Remove sticky GET which was set by stickyGET.
     *
     * @param string $name
     */
    public function stickyForget($name)
    {
        unset($this->sticky_get_arguments[$name]);
    }
    
    /**
     * Adds additional JS script include in aplication template.
     *
     * @param string $url
     * @param bool   $isAsync Whether or not you want Async loading.
     * @param bool   $isDefer Whether or not you want Defer loading.
     *
     * @return $this
     */
    public function requireJS($url, $isAsync = false, $isDefer = false)
    {
        $this->html->template->appendHTML('HEAD', $this->getTag('script', ['src' => $url, 'defer' => $isDefer, 'async' => $isAsync], '')."\n");
        
        return $this;
    }
    
    /**
     * Adds additional CSS stylesheet include in aplication template.
     *
     * @param string $url
     *
     * @return $this
     */
    public function requireCSS($url)
    {
        $this->html->template->appendHTML('HEAD', $this->getTag('link/', ['rel' => 'stylesheet', 'type' => 'text/css', 'href' => $url])."\n");
        
        return $this;
    }
    
    /**
     * A convenient wrapper for sending user to another page.
     *
     * @param array|string $page Destination page
     */
    public function redirect($page)
    {
        header('Location: '.$this->url($page));
        
        $this->run_called = true; // prevent shutdown function from triggering.
        $this->callExit();
    }
    
    /**
     * Generate action for redirecting user to another page.
     *
     * @param string|array $page Destination URL or page/arguments
     */
    public function jsRedirect($page)
    {
        return new jsExpression('document.location = []', [$this->url($page)]);
    }
    
    /**
     * Construct HTML tag with supplied attributes.
     *
     * $html = getTag('img/', ['src'=>'foo.gif','border'=>0]);
     * // "<img src="foo.gif" border="0"/>"
     *
     *
     * The following rules are respected:
     *
     * 1. all array key=>val elements appear as attributes with value escaped.
     * getTag('div/', ['data'=>'he"llo']);
     * --> <div data="he\"llo"/>
     *
     * 2. boolean value true will add attribute without value
     * getTag('td', ['nowrap'=>true]);
     * --> <td nowrap>
     *
     * 3. null and false value will ignore the attribute
     * getTag('img', ['src'=>false]);
     * --> <img>
     *
     * 4. passing key 0=>"val" will re-define the element itself
     * getTag('img', ['input', 'type'=>'picture']);
     * --> <input type="picture" src="foo.gif">
     *
     * 5. use '/' at end of tag to close it.
     * getTag('img/', ['src'=>'foo.gif']);
     * --> <img src="foo.gif"/>
     *
     * 6. if main tag is self-closing, overriding it keeps it self-closing
     * getTag('img/', ['input', 'type'=>'picture']);
     * --> <input type="picture" src="foo.gif"/>
     *
     * 7. simple way to close tag. Any attributes to closing tags are ignored
     * getTag('/td');
     * --> </td>
     *
     * 7b. except for 0=>'newtag'
     * getTag('/td', ['th', 'align'=>'left']);
     * --> </th>
     *
     * 8. using $value will add value inside tag. It will also encode value.
     * getTag('a', ['href'=>'foo.html'] ,'click here >>');
     * --> <a href="foo.html">click here &gt;&gt;</a>
     *
     * 9. you may skip attribute argument.
     * getTag('b','text in bold');
     * --> <b>text in bold</b>
     *
     * 10. pass array as 3rd parameter to nest tags. Each element can be either string (inserted as-is) or
     * array (passed to getTag recursively)
     * getTag('a', ['href'=>'foo.html'], [['b','click here'], ' for fun']);
     * --> <a href="foo.html"><b>click here</b> for fun</a>
     *
     * 11. extended example:
     * getTag('a', ['href'=>'hello'], [
     *    ['b', 'class'=>'red', [
     *        ['i', 'class'=>'blue', 'welcome']
     *    ]]
     * ]);
     * --> <a href="hello"><b class="red"><i class="blue">welcome</i></b></a>'
     *
     * @param string|array $tag
     * @param string       $attr
     * @param string|array $value
     *
     * @return string
     */
    public function getTag($tag = null, $attr = null, $value = null)
    {
        if ($tag === null) {
            $tag = 'div';
        } elseif (is_array($tag)) {
            $tmp = $tag;
            
            if (isset($tmp[0])) {
                $tag = $tmp[0];
                
                if (is_array($tag)) {
                    // OH a bunch of tags
                    $output = '';
                    foreach ($tmp as $subtag) {
                        $output .= $this->getTag($subtag);
                    }
                    
                    return $output;
                }
                
                unset($tmp[0]);
            } else {
                $tag = 'div';
            }
            
            if (isset($tmp[1])) {
                $value = $tmp[1];
                unset($tmp[1]);
            } else {
                $value = null;
            }
            
            $attr = $tmp;
        }
        if ($tag[0] === '<') {
            return $tag;
        }
        if (is_string($attr)) {
            $value = $attr;
            $attr = null;
        }
        
        if (is_string($value)) {
            $value = $this->encodeHTML($value);
        } elseif (is_array($value)) {
            $result = [];
            foreach ($value as $v) {
                $result[] = is_array($v) ? $this->getTag(...$v) : $v;
            }
            $value = implode('', $result);
        }
        
        if (!$attr) {
            return "<$tag>".($value !== null ? $value."</$tag>" : '');
        }
        $tmp = [];
        if (substr($tag, -1) == '/') {
            $tag = substr($tag, 0, -1);
            $postfix = '/';
        } elseif (substr($tag, 0, 1) == '/') {
            return isset($attr[0]) ? '</'.$attr[0].'>' : '<'.$tag.'>';
        } else {
            $postfix = '';
        }
        foreach ($attr as $key => $val) {
            if ($val === false) {
                continue;
            }
            if ($val === true) {
                $tmp[] = "$key";
            } elseif ($key === 0) {
                $tag = $val;
            } else {
                $tmp[] = "$key=\"".$this->encodeAttribute($val).'"';
            }
        }
        
        return "<$tag".($tmp ? (' '.implode(' ', $tmp)) : '').$postfix.'>'.($value !== null ? $value."</$tag>" : '');
    }
    
    /**
     * Encodes string - removes HTML special chars.
     *
     * @param string $val
     *
     * @return string
     */
    public function encodeAttribute($val)
    {
        return htmlspecialchars($val);
    }
    
    /**
     * Encodes string - removes HTML entities.
     *
     * @param string $val
     *
     * @return string
     */
    public function encodeHTML($val)
    {
        return htmlentities($val);
    }
    
    /**
     * handle catch of throwable
     *
     * @param \Throwable $e
     *
     * @return bool
     */
    public function caughtThrowable(\Throwable $e)
    {
        // @TODO add a flag for level of verbosity of errors dispay to user
        $this->catch_runaway_callbacks = false;
        
        // run_called moved here because is used on any case
        $this->run_called = true;
        
        // if class App was extended
        // we get the right class to call
        // if not we get wrong data like : title => Agile Toolkit - untitled
        $AppClassName = get_class($this);
        
        // we valorize flag is_catch_throwable to true
        // to notice that this instance is an error
        $l            = new $AppClassName(['is_catch_throwable' => true]);
        $l->initLayout('Centered');
        
        $this->logThrowable($e);
        
        //check for error type.
        if($e instanceof \Exception)
        {
            if ($e instanceof \atk4\core\Exception)
            {
                $l->layout->template->setHTML('Content', $e->getHTML());
            }
            else
            {
                // to give a more readable and small trace output
                // i get the current working dir and the absolute dir of this file
                // transform both in arrays and intersect the two to get a common path for replace
                $cwd = explode(DIRECTORY_SEPARATOR,getcwd());
                $dir = explode(DIRECTORY_SEPARATOR,__DIR__);
                $common_path = implode(DIRECTORY_SEPARATOR,array_intersect($cwd,$dir)).DIRECTORY_SEPARATOR;
                
                $message = $l->layout->add(['Message', get_class($e),'icon' => 'triangle exclamation'])->addClass('error')->removeClass('padded');
                $message->text->addParagraph($e->getMessage());
                $message->text->addParagraph('in ' . $e->getFile() . '(' . $e->getLine() . ')');
                
                $l->layout->add(['Text', '<hr/>']);
                $l->layout->add(['Text', nl2br(str_replace($common_path,'',$e->getTraceAsString()))]);
            }
        }
        
        // @TODO i think this is clearly a duplicate \Error or \Exception not from atk4 need the same work, non sense make a duplicate
        // @TODO check if an \Error or an \Exception can be casted to \atk4\core\Exception ? if we find this code will be 3/4 lines, and error output will we uniform
        if($e instanceof \Error)
        {
            if ($e instanceof \Error)
            {
                /**
                 * this is a repetition of the exception format block but Error is not an Exception
                 */
                $cwd = explode(DIRECTORY_SEPARATOR,getcwd());
                $dir = explode(DIRECTORY_SEPARATOR,__DIR__);
                $common_path = implode(DIRECTORY_SEPARATOR,array_intersect($cwd,$dir)).DIRECTORY_SEPARATOR;
                
                $message = $l->layout->add(['Message', get_class($e),'icon' => 'exclamation'])->addClass('error')->removeClass('padded');
                $message->text->addParagraph($e->getMessage());
                $message->text->addParagraph('in ' . $e->getFile() . '(' . $e->getLine() . ')');
                
                $l->layout->add(['Text', '<hr/>']);
                $l->layout->add(['Text', nl2br(str_replace($common_path,'',$e->getTraceAsString()))]);
            }
        }
        
        $l->layout->template->tryDel('Header');
        
        if ($this->isJsonRequest())
        {
            $jsonData = [
                'success'   => false,
                'message' => $l->layout->getHtml(),
            ];
            
            echo json_encode($jsonData);
        }
        else
        {
            $l->catch_runaway_callbacks = false;
            $l->run();
        }
        
        $this->callExit();
    }
    
    /**
     * if debug is active log throwable
     *
     * @param \Throwable $t
     */
    private function logThrowable(\Throwable $t)
    {
        $can_log = $this->_debugTrait ?? false;
        if($can_log === false) return;
        
        $debug_msg = 'UNDEFINED ERROR'; // <-- Impossible to output
        
        if ($t instanceof \atk4\core\Exception)
        {
            $debug_msg = $t->getColorfulText();
        }
        else
        {
            $debug_msg = [
                ' ====================================== ',
                ' TYPE : ' . get_class($t),
                ' MSG  : [' . $t->getCode() . '] ' . $t->getMessage(),
                ' ============== DEBUG ================= ',
                ' FILE :' . $t->getFile(),
                ' LINE :' . $t->getLine(),
                ' ============== TRACE ================= ',
                $t->getTraceAsString(),
                ' ====================================== ',
            ];
            
            $debug_msg = implode(PHP_EOL, $debug_msg);
        }
        
        $this->debug(PHP_EOL . $debug_msg . PHP_EOL);
    }
}
